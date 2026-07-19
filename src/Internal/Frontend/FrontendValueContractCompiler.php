<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Choice;
use BlackOps\Core\Validation\Attribute\Count;
use BlackOps\Core\Validation\Attribute\Email;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Core\Validation\Attribute\Regex;
use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\FromHeader;
use BlackOps\Http\Attribute\FromPath;
use BlackOps\Http\Attribute\FromQuery;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class FrontendValueContractCompiler
{
    public function __construct(
        private FrontendScalarTypeCompiler $types = new FrontendScalarTypeCompiler(),
    ) {}

    /** @param class-string<OperationValue> $value */
    public function compile(string $value, string $method, string $path): FrontendValueContract
    {
        $reflection = new ReflectionClass($value);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new FrontendValueContract($value, []);
        }

        $fields = [];
        foreach ($constructor->getParameters() as $parameter) {
            $fields[] = $this->field($reflection, $parameter, $method);
        }
        usort($fields, static fn(FrontendValueFieldContract $left, FrontendValueFieldContract $right): int => strcmp(
            $left->name,
            $right->name,
        ));

        $this->assertPathBindings($fields, $path);

        return new FrontendValueContract($value, $fields);
    }

    /** @param ReflectionClass<OperationValue> $value */
    private function field(
        ReflectionClass $value,
        ReflectionParameter $parameter,
        string $method,
    ): FrontendValueFieldContract {
        [$type, $nullable] = $this->types->compile($parameter->getType());
        [$source, $transportName] = $this->binding($parameter);
        if (in_array(strtoupper($method), ['GET', 'HEAD'], strict: true) && $source === 'body') {
            throw new InvalidArgumentException('Frontend contract does not permit a body for GET or HEAD.');
        }

        $property = $value->hasProperty($parameter->getName()) ? $value->getProperty($parameter->getName()) : null;

        return new FrontendValueFieldContract(
            $parameter->getName(),
            $type,
            $nullable,
            !$parameter->isDefaultValueAvailable(),
            $source,
            $transportName,
            $this->sensitive($property),
            $this->validations($property),
        );
    }

    /** @return array{string, string} */
    private function binding(ReflectionParameter $parameter): array
    {
        $matches = [];
        foreach ([
            FromPath::class => 'path',
            FromQuery::class => 'query',
            FromHeader::class => 'header',
            FromBody::class => 'body',
        ] as $attribute => $source) {
            $attributes = $parameter->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);
            if (count($attributes) > 1) {
                throw new InvalidArgumentException('Frontend contract binding attribute must not be repeated.');
            }
            if ($attributes !== []) {
                $instance = $attributes[0]->newInstance();
                $matches[] = [$source, $instance->name ?? $parameter->getName()];
            }
        }

        if (count($matches) > 1) {
            throw new InvalidArgumentException('Frontend contract value field has conflicting binding sources.');
        }

        [$source, $transportName] = $matches[0] ?? ['body', $parameter->getName()];
        if ($transportName === '') {
            throw new InvalidArgumentException('Frontend contract transport name must not be empty.');
        }

        return [$source, $transportName];
    }

    private function sensitive(?ReflectionProperty $property): bool
    {
        if ($property === null) {
            return false;
        }

        $attributes = $property->getAttributes(Sensitive::class);
        if (count($attributes) > 1) {
            throw new InvalidArgumentException('Frontend contract sensitive attribute must not be repeated.');
        }

        return $attributes !== [];
    }

    /** @return list<FrontendValidationContract> */
    private function validations(?ReflectionProperty $property): array
    {
        if ($property === null) {
            return [];
        }

        $validations = [];
        foreach ([
            NotBlank::class => ['not_blank', 'validation.not_blank'],
            Length::class => ['length', 'validation.length'],
            Range::class => ['range', 'validation.range'],
            Email::class => ['email', 'validation.email'],
            Regex::class => ['regex', 'validation.regex'],
            Count::class => ['count', 'validation.count'],
            Choice::class => ['choice', 'validation.choice'],
        ] as $attribute => [$rule, $code]) {
            $attributes = $property->getAttributes($attribute);
            if (count($attributes) > 1) {
                throw new InvalidArgumentException('Frontend contract validation attribute must not be repeated.');
            }
            if ($attributes !== []) {
                $validations[] = new FrontendValidationContract(
                    $rule,
                    $code,
                    $this->validationParameters($attributes[0]->newInstance()),
                );
            }
        }

        usort($validations, static fn(
            FrontendValidationContract $left,
            FrontendValidationContract $right,
        ): int => strcmp($left->rule, $right->rule));

        return $validations;
    }

    /** @return array<string, bool|float|int|string|list<bool|float|int|string>> */
    private function validationParameters(object $rule): array
    {
        $parameters = match (true) {
            $rule instanceof Length, $rule instanceof Range, $rule instanceof Count => [
                ...($rule->min === null ? [] : ['min' => $rule->min]),
                ...($rule->max === null ? [] : ['max' => $rule->max]),
            ],
            $rule instanceof Regex => ['pattern' => $rule->pattern],
            $rule instanceof Choice => ['choices' => $rule->choices],
            default => [],
        };
        ksort($parameters);

        /** @var array<string, bool|float|int|string|list<bool|float|int|string>> */
        return $parameters;
    }

    /** @param list<FrontendValueFieldContract> $fields */
    private function assertPathBindings(array $fields, string $path): void
    {
        $matches = [];
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)(?::[^}]*)?\}/', $path, $matches);
        $placeholders = $matches[1] ?? [];
        $bindings = array_map(
            static fn(FrontendValueFieldContract $field): string => $field->transportName,
            array_values(array_filter(
                $fields,
                static fn(FrontendValueFieldContract $field): bool => $field->source === 'path',
            )),
        );
        sort($placeholders);
        sort($bindings);

        if ($placeholders !== $bindings || count($bindings) !== count(array_unique($bindings))) {
            throw new InvalidArgumentException('Frontend contract path bindings do not match the route template.');
        }
    }
}

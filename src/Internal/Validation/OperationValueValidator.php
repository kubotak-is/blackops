<?php

declare(strict_types=1);

namespace BlackOps\Internal\Validation;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Choice;
use BlackOps\Core\Validation\Attribute\Count;
use BlackOps\Core\Validation\Attribute\Email;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Core\Validation\Attribute\Regex;
use BlackOps\Core\Validation\Violation;
use ReflectionClass;
use ReflectionProperty;

final readonly class OperationValueValidator
{
    public function __construct(
        private OperationValueRuleEvaluator $evaluator = new OperationValueRuleEvaluator(),
    ) {}

    /**
     * @return list<Violation>
     */
    public function validate(OperationValue $value): array
    {
        $properties = array_values(array_filter(
            new ReflectionClass($value)->getProperties(),
            static fn(ReflectionProperty $property): bool => $property->isPromoted(),
        ));
        usort($properties, static fn(ReflectionProperty $left, ReflectionProperty $right): int => strcmp(
            $left->getName(),
            $right->getName(),
        ));

        $violations = [];
        foreach ($properties as $property) {
            foreach ($this->rules($property) as [$rule, $name, $code]) {
                if ($this->evaluator->passes($rule, $property->getValue($value))) {
                    continue;
                }

                $violations[] = new Violation($property->getName(), $name, $code);
            }
        }

        return $violations;
    }

    /**
     * @return list<array{object, string, string}>
     */
    private function rules(ReflectionProperty $property): array
    {
        $rules = [];
        foreach ([
            NotBlank::class => ['not_blank', 'validation.not_blank'],
            Length::class => ['length', 'validation.length'],
            Range::class => ['range', 'validation.range'],
            Email::class => ['email', 'validation.email'],
            Regex::class => ['regex', 'validation.regex'],
            Count::class => ['count', 'validation.count'],
            Choice::class => ['choice', 'validation.choice'],
        ] as $attribute => [$name, $code]) {
            foreach ($property->getAttributes($attribute) as $reflection) {
                $rules[] = [$reflection->newInstance(), $name, $code];
            }
        }

        return $rules;
    }
}

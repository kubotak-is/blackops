<?php

declare(strict_types=1);

namespace BlackOps\Outcome\Internal;

use BlackOps\Core\Outcome;
use InvalidArgumentException;
use ReflectionProperty;
use stdClass;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class StructuredOutcomeNormalizer
{
    public function __construct(
        private StructuredOutcomeCompiler $compiler = new StructuredOutcomeCompiler(),
    ) {}

    /** @return array<string, mixed> */
    public function normalize(Outcome $outcome): array
    {
        return $this->normalizeObject($outcome, $this->compiler->compile($outcome::class), $outcome::class);
    }

    /** @return array<string, mixed> */
    private function normalizeObject(object $value, StructuredOutcomeShape $shape, string $path): array
    {
        if ($value::class !== $shape->class) {
            throw new InvalidArgumentException(sprintf('Structured outcome object at %s has an invalid class.', $path));
        }

        $normalized = [];
        foreach ($shape->fields as $field) {
            $property = new ReflectionProperty($shape->class, $field->name);
            if (!$property->isInitialized($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Structured outcome field %s.%s is uninitialized.',
                    $path,
                    $field->name,
                ));
            }
            $normalized[$field->name] = $this->normalizeField(
                $property->getValue($value),
                $field,
                $path . '.' . $field->name,
            );
        }

        return $normalized;
    }

    private function normalizeField(mixed $value, StructuredOutcomeField $field, string $path): mixed
    {
        if ($value === null) {
            if ($field->nullable) {
                return null;
            }

            throw new InvalidArgumentException(sprintf('Structured outcome field %s must not be null.', $path));
        }

        return match ($field->kind) {
            'string' => is_string($value) ? $value : $this->invalid($path),
            'integer' => is_int($value) ? $value : $this->invalid($path),
            'float' => is_float($value) && is_finite($value) ? $value : $this->invalid($path),
            'boolean' => is_bool($value) ? $value : $this->invalid($path),
            'dto' => is_object($value) && $field->dto !== null
                ? $this->normalizeDto($value, $field->dto, $path)
                : $this->invalid($path),
            'list' => $this->normalizeList($value, $field, $path),
        };
    }

    /** @return array<string, mixed>|stdClass */
    private function normalizeDto(object $value, StructuredOutcomeShape $shape, string $path): array|stdClass
    {
        $normalized = $this->normalizeObject($value, $shape, $path);

        return $normalized === [] ? new stdClass() : $normalized;
    }

    /** @return list<array<string, mixed>|stdClass> */
    private function normalizeList(mixed $value, StructuredOutcomeField $field, string $path): array
    {
        if (!is_array($value) || !array_is_list($value) || $field->dto === null) {
            $this->invalid($path);
        }

        $normalized = [];
        for ($index = 0; $index < count($value); ++$index) {
            if (!is_object($value[$index]) || $value[$index]::class !== $field->dto->class) {
                $this->invalid($path . '[' . $index . ']');
            }
            $normalized[] = $this->normalizeDto($value[$index], $field->dto, $path . '[' . $index . ']');
        }

        return $normalized;
    }

    private function invalid(string $path): never
    {
        throw new InvalidArgumentException(sprintf(
            'Structured outcome value at %s does not match its contract.',
            $path,
        ));
    }
}

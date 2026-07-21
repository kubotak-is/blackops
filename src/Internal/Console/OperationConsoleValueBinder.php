<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Violation;
use InvalidArgumentException;
use ReflectionClass;

final readonly class OperationConsoleValueBinder
{
    /**
     * @param array<string, mixed> $values
     * @return OperationValue|list<Violation>
     */
    public function bind(OperationConsoleCommandMetadata $command, array $values): OperationValue|array
    {
        $arguments = [];
        foreach ($command->options as $option) {
            /** @var mixed $value */
            $value = $values[$option->name] ?? null;
            if ($value === null) {
                if ($option->required) {
                    return [new Violation($option->property, 'required', 'binding.required')];
                }
                $arguments[] = $option->default;
                continue;
            }
            $decoded = $this->decode($option, $value);
            if ($decoded instanceof Violation) {
                return [$decoded];
            }
            $arguments[] = $decoded;
        }

        $value = new ReflectionClass($command->value)->newInstanceArgs($arguments);
        if (!$value instanceof OperationValue) {
            throw new InvalidArgumentException('Console bound value must implement OperationValue.');
        }

        return $value;
    }

    private function decode(OperationConsoleOptionMetadata $option, mixed $value): string|int|float|bool|Violation
    {
        if (!is_string($value)) {
            return match ($option->type) {
                'int' => is_int($value) ? $value : $this->typeViolation($option),
                'float' => is_float($value) ? $value : $this->typeViolation($option),
                'bool' => is_bool($value) ? $value : $this->typeViolation($option),
                'string' => $this->typeViolation($option),
            };
        }

        return match ($option->type) {
            'string' => $value,
            'int' => $this->integer($option, $value),
            'float' => $this->float($option, $value),
            'bool' => match ($value) {
                'true' => true,
                'false' => false,
                default => $this->typeViolation($option),
            },
        };
    }

    private function integer(OperationConsoleOptionMetadata $option, string $value): int|Violation
    {
        if (preg_match('/^(?:0|-?[1-9][0-9]*)$/D', $value) !== 1) {
            return $this->typeViolation($option);
        }
        $decoded = (int) $value;

        return (string) $decoded === $value ? $decoded : $this->typeViolation($option);
    }

    private function float(OperationConsoleOptionMetadata $option, string $value): float|Violation
    {
        if (preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?$/D', $value) !== 1) {
            return $this->typeViolation($option);
        }
        $decoded = filter_var($value, FILTER_VALIDATE_FLOAT);

        return $decoded !== false && is_finite($decoded) ? $decoded : $this->typeViolation($option);
    }

    private function typeViolation(OperationConsoleOptionMetadata $option): Violation
    {
        return new Violation($option->property, 'type', 'binding.type');
    }
}

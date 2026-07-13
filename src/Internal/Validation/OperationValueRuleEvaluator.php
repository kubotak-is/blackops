<?php

declare(strict_types=1);

namespace BlackOps\Internal\Validation;

use BlackOps\Core\Validation\Attribute\Choice;
use BlackOps\Core\Validation\Attribute\Count;
use BlackOps\Core\Validation\Attribute\Email;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Core\Validation\Attribute\Regex;

final readonly class OperationValueRuleEvaluator
{
    public function passes(object $rule, mixed $value): bool
    {
        return match (true) {
            $rule instanceof NotBlank => is_string($value) && preg_match('/^\s*$/u', $value) === 0,
            $rule instanceof Length => $this->length($value, $rule),
            $rule instanceof Range => $this->range($value, $rule),
            $rule instanceof Email => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            $rule instanceof Regex => is_string($value) && preg_match($rule->pattern, $value) === 1,
            $rule instanceof Count => is_array($value) && $this->inside(count($value), $rule->min, $rule->max),
            $rule instanceof Choice => is_scalar($value) && in_array($value, $rule->choices, strict: true),
            default => false,
        };
    }

    private function length(mixed $value, Length $rule): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $length = preg_match_all('/./us', $value);

        return $length !== false && $this->inside($length, $rule->min, $rule->max);
    }

    private function range(mixed $value, Range $rule): bool
    {
        return (
            (is_int($value) || is_float($value))
            && is_finite((float) $value)
            && $this->inside($value, $rule->min, $rule->max)
        );
    }

    private function inside(int|float $value, int|float|null $min, int|float|null $max): bool
    {
        return ($min === null || $value >= $min) && ($max === null || $value <= $max);
    }
}

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
use LogicException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SymfonyOperationValueConstraintFactory
{
    public function create(object $rule): ?Constraint
    {
        return match (true) {
            $rule instanceof NotBlank => new Assert\NotBlank(
                normalizer: static fn(string $value): string => (
                    preg_replace(pattern: '/^\s+|\s+$/u', replacement: '', subject: $value) ?? $value
                ),
            ),
            $rule instanceof Length => $this->length($rule),
            $rule instanceof Range => new Assert\Range(min: $rule->min, max: $rule->max),
            $rule instanceof Email => new Assert\Email(),
            $rule instanceof Regex => new Assert\Regex($rule->pattern),
            $rule instanceof Count => new Assert\Count(
                min: $this->nonNegative($rule->min),
                max: $this->nonNegative($rule->max),
            ),
            $rule instanceof Choice => new Assert\Choice(choices: $rule->choices, match: true),
            default => null,
        };
    }

    private function length(Length $rule): Constraint
    {
        if ($rule->max === 0) {
            return new Assert\Blank();
        }

        return new Assert\Length(
            min: $this->nonNegative($rule->min),
            max: $this->positive($rule->max),
            countUnit: Assert\Length::COUNT_CODEPOINTS,
        );
    }

    /** @return non-negative-int|null */
    private function nonNegative(?int $value): ?int
    {
        if ($value !== null && $value < 0) {
            throw new LogicException('Validation bound must not be negative.');
        }

        return $value;
    }

    /** @return positive-int|null */
    private function positive(?int $value): ?int
    {
        if ($value !== null && $value <= 0) {
            throw new LogicException('Validation bound must be positive.');
        }

        return $value;
    }
}

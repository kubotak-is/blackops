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
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class OperationValueRuleEvaluator
{
    private ValidatorInterface $validator;

    public function __construct(
        ?ValidatorInterface $validator = null,
        private SymfonyOperationValueConstraintFactory $constraints = new SymfonyOperationValueConstraintFactory(),
    ) {
        $this->validator = $validator ?? Validation::createValidator();
    }

    public function passes(object $rule, mixed $value): bool
    {
        if (!$this->supportsValue($rule, $value)) {
            return false;
        }

        $constraint = $this->constraints->create($rule);

        return $constraint !== null && count($this->validator->validate($value, $constraint)) === 0;
    }

    private function supportsValue(object $rule, mixed $value): bool
    {
        return match (true) {
            $this->isStringRule($rule) => is_string($value),
            $rule instanceof Range => $this->isFiniteNumber($value),
            $rule instanceof Count => is_array($value),
            $rule instanceof Choice => $this->isFiniteScalar($value),
            default => false,
        };
    }

    private function isStringRule(object $rule): bool
    {
        return $rule instanceof NotBlank || $rule instanceof Length || $rule instanceof Email || $rule instanceof Regex;
    }

    private function isFiniteNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    private function isFiniteScalar(mixed $value): bool
    {
        return is_scalar($value) && (!is_float($value) || is_finite($value));
    }
}

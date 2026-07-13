<?php

declare(strict_types=1);

namespace BlackOps\Http\Binding;

use BlackOps\Core\Validation\Violation;
use RuntimeException;

final class OperationValueBindingException extends RuntimeException
{
    /**
     * @param list<Violation> $violations
     */
    private function __construct(
        private readonly array $violations,
    ) {
        parent::__construct('HTTP operation value binding failed.');
    }

    public static function required(string $field): self
    {
        return new self([new Violation($field, 'required', 'binding.required')]);
    }

    public static function type(string $field): self
    {
        return new self([new Violation($field, 'type', 'binding.type')]);
    }

    /**
     * @return list<Violation>
     */
    public function violations(): array
    {
        return $this->violations;
    }
}

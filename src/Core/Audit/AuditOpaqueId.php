<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final readonly class AuditOpaqueId
{
    public function __construct(
        public string $value,
    ) {
        AuditValidationException::assertSafeString($value, 71, 71);
        if (preg_match('/^oid:v1:[a-f0-9]{64}$/', $value) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

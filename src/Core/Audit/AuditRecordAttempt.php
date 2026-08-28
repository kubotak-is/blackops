<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final readonly class AuditRecordAttempt
{
    public function __construct(
        public int $number,
        public AuditAttemptKind $kind,
    ) {
        if ($number < 1 || $number > AuditValidationException::MAX_SAFE_INTEGER) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
    }

    /** @return array{number:int,kind:string} */
    public function toArray(): array
    {
        return ['number' => $this->number, 'kind' => $this->kind->value];
    }
}

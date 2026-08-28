<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final readonly class AuditRecordOutcome
{
    public function __construct(
        public AuditOutcomeStatus $status,
        public string $code,
    ) {
        AuditValidationException::assertGrammarString($code, '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', 128);
    }

    /** @return array{status:string,code:string} */
    public function toArray(): array
    {
        return ['status' => $this->status->value, 'code' => $this->code];
    }
}

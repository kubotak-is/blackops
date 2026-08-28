<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final readonly class AuditRecordPolicy
{
    public function __construct(
        public string $id,
        public int $version,
        public AuditPolicyDecision $decision,
        public string $reasonCode,
        public AuditDelivery $auditDelivery,
    ) {
        AuditValidationException::assertSafeString($id);
        if ($version < 1 || $version > AuditValidationException::MAX_SAFE_INTEGER) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
        AuditValidationException::assertGrammarString($reasonCode, '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', 128);
    }

    /** @return array{id:string,version:int,decision:string,reasonCode:string,auditDelivery:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'decision' => $this->decision->value,
            'reasonCode' => $this->reasonCode,
            'auditDelivery' => $this->auditDelivery->value,
        ];
    }
}

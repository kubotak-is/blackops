<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Relay;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Record\AuditLogicalRecordCandidate;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class AuditIngressCanonicalizer
{
    public function __construct(
        private AuditProtectedBodyCodec $protection,
        private AuditRecordValidator $validator,
    ) {}

    public function canonicalize(#[\SensitiveParameter] AuditOutboxRecord $record): CanonicalAuditLogicalRecord
    {
        $ingress = $record->protectedIngress();
        $bindings = $ingress->toArray();
        $envelope = $this->protection->unprotectOutbox($ingress);
        $envelopeValue = $envelope->toArray();

        if (!$this->hasBindings($bindings, $envelopeValue)) {
            throw AuditProtectionException::unavailable();
        }

        $logical = $envelopeValue['logicalRecord'] ?? null;
        if (!is_array($logical)) {
            throw AuditProtectionException::unavailable();
        }

        /** @var array<string,mixed> $logical */
        $candidate = new AuditLogicalRecordCandidate($logical);
        $validated = $this->validator->validateLogical($candidate);

        return new CanonicalAuditLogicalRecord($validated);
    }

    /** @param array<string,mixed> $bindings @param array<string,mixed> $envelope */
    private function hasBindings(#[\SensitiveParameter] array $bindings, #[\SensitiveParameter] array $envelope): bool
    {
        return (
            ($envelope['kind'] ?? null) === 'audit_ingress_envelope'
            && ($envelope['logicalRecord']['policy']['auditDelivery'] ?? null) === AuditDelivery::Durable->value
            && ($envelope['recordId'] ?? null) === ($bindings['recordId'] ?? null)
            && ($envelope['partitionId'] ?? null) === ($bindings['partitionId'] ?? null)
            && ($envelope['tenantBinding'] ?? null) === ($bindings['tenantBinding'] ?? null)
            && ($envelope['ingressOrdinal'] ?? null) === ($bindings['ingressOrdinal'] ?? null)
            && ($envelope['contentHash'] ?? null) === ($bindings['contentHash'] ?? null)
        );
    }
}

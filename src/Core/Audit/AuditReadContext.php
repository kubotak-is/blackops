<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final readonly class AuditReadContext
{
    private function __construct(
        public AuditReadPurpose $purpose,
        public ?AuditTypedReference $actor,
        public AuditTypedReference $execution,
        public ?AuditTypedReference $tenant,
        public string $requestedAt,
        public string $nonce,
        public string $requestRef,
        public AuditReadAuthorizationDecision $authorizationDecision,
    ) {}

    public static function fromBasis(AuditReadRequestBasis $basis, AuditReadAuthorizationDecision $decision): self
    {
        return new self(
            purpose: $basis->purpose,
            actor: $basis->actor,
            execution: $basis->execution,
            tenant: $basis->tenant,
            requestedAt: $basis->requestedAt,
            nonce: $basis->nonce,
            requestRef: $basis->requestRef(),
            authorizationDecision: $decision,
        );
    }

    public function basis(): AuditReadRequestBasis
    {
        return new AuditReadRequestBasis(
            $this->purpose,
            $this->actor,
            $this->execution,
            $this->tenant,
            $this->requestedAt,
            $this->nonce,
        );
    }

    /** @return array{schemaVersion:int,kind:string,purpose:string,actor:array{type:string,id:string}|null,execution:array{type:string,id:string},tenant:array{type:string,id:string}|null,requestedAt:string,nonce:string,requestRef:string,authorizationDecision:string} */
    public function toArray(): array
    {
        return [
            'schemaVersion' => 1,
            'kind' => 'audit_read_context',
            'purpose' => $this->purpose->value,
            'actor' => $this->actor?->toArray(),
            'execution' => $this->execution->toArray(),
            'tenant' => $this->tenant?->toArray(),
            'requestedAt' => $this->requestedAt,
            'nonce' => $this->nonce,
            'requestRef' => $this->requestRef,
            'authorizationDecision' => $this->authorizationDecision->value,
        ];
    }
}

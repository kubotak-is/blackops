<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Outbox;

use BlackOps\Internal\Audit\Delivery\AuditOutboxStateSnapshot;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;

final class AuditOutboxRecord
{
    private \SensitiveParameterValue $snapshot;

    private function __construct(
        #[\SensitiveParameter]
        AuditProtectedIngress $protectedIngress,
        #[\SensitiveParameter]
        AuditOutboxStateSnapshot $stateSnapshot,
    ) {
        $ingress = $protectedIngress->toArray();
        $state = $stateSnapshot->toArray();
        if (
            array_keys($ingress) !== [
                'recordId',
                'partitionId',
                'tenantBinding',
                'ingressOrdinal',
                'idempotencyKeyHash',
                'contentHash',
                'protectedBody',
            ]
            || array_keys($state) !== [
                'state',
                'attempt',
                'availableAt',
                'leaseFencingToken',
                'leaseExpiresAt',
                'version',
            ]
        ) {
            throw self::integrity();
        }

        $this->snapshot = new \SensitiveParameterValue([
            'protectedIngress' => $protectedIngress,
            'stateSnapshot' => $stateSnapshot,
        ]);
    }

    public static function fromParts(
        #[\SensitiveParameter]
        AuditProtectedIngress $protectedIngress,
        #[\SensitiveParameter]
        AuditOutboxStateSnapshot $stateSnapshot,
    ): self {
        try {
            return new self($protectedIngress, $stateSnapshot);
        } catch (AuditOutboxStoreException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw self::integrity();
        }
    }

    public function protectedIngress(): AuditProtectedIngress
    {
        $value = $this->value('protectedIngress');
        if (!$value instanceof AuditProtectedIngress) {
            throw self::integrity();
        }

        return $value;
    }

    public function stateSnapshot(): AuditOutboxStateSnapshot
    {
        $value = $this->value('stateSnapshot');
        if (!$value instanceof AuditOutboxStateSnapshot) {
            throw self::integrity();
        }

        return $value;
    }

    /** @return array{schemaVersion:int,kind:string,recordId:string,partitionId:string,tenantBinding:?array{type:string,id:string},ingressOrdinal:int,idempotencyKeyHash:string,contentHash:string,state:string,attempt:int,availableAt:?string,leaseFencingToken:?string,leaseExpiresAt:?string,protectedBody:string,version:int} */
    public function toArray(): array
    {
        $ingress = $this->protectedIngress()->toArray();
        /** @var array{recordId:string,partitionId:string,tenantBinding:?array{type:string,id:string},ingressOrdinal:int,idempotencyKeyHash:string,contentHash:string,protectedBody:string} $ingress */
        $state = $this->stateSnapshot()->toArray();
        return [
            'schemaVersion' => 1,
            'kind' => 'audit_outbox_record',
            'recordId' => $ingress['recordId'],
            'partitionId' => $ingress['partitionId'],
            'tenantBinding' => $ingress['tenantBinding'],
            'ingressOrdinal' => $ingress['ingressOrdinal'],
            'idempotencyKeyHash' => $ingress['idempotencyKeyHash'],
            'contentHash' => $ingress['contentHash'],
            'state' => $state['state'],
            'attempt' => $state['attempt'],
            'availableAt' => $state['availableAt'],
            'leaseFencingToken' => $state['leaseFencingToken'],
            'leaseExpiresAt' => $state['leaseExpiresAt'],
            'protectedBody' => $ingress['protectedBody'],
            'version' => $state['version'],
        ];
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw self::integrity();
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw self::integrity();
        }
        if (!is_array($value) || !array_key_exists($key, $value)) {
            throw self::integrity();
        }

        return $value[$key];
    }

    private static function integrity(): AuditOutboxStoreException
    {
        return new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
    }
}

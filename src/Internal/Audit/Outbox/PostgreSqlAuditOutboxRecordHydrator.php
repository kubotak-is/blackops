<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Outbox;

use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxStateSnapshot;

final readonly class PostgreSqlAuditOutboxRecordHydrator
{
    /** @mago-expect lint:no-redundant-readonly */
    public function __construct(
        private readonly PostgreSqlAuditProtectedIngressHydrator $protectedIngress,
    ) {}

    /** @param array<array-key,mixed> $row */
    public function hydrate(
        #[\SensitiveParameter]
        array $row,
        AuditOutboxConfiguration $configuration,
    ): AuditOutboxRecord {
        try {
            $keys = [
                'schema_version',
                'kind',
                'record_id',
                'partition_id',
                'tenant_type',
                'tenant_id',
                'ingress_ordinal',
                'idempotency_key_hash',
                'content_hash',
                'state',
                'attempt',
                'available_at',
                'lease_fencing_token',
                'lease_expires_at',
                'protected_body',
                'version',
            ];
            if (array_is_list($row) || array_keys($row) !== $keys) {
                throw new \RuntimeException();
            }
            if ($this->integer($row['schema_version'], 1, 1) !== 1 || $row['kind'] !== 'audit_outbox_record') {
                throw new \RuntimeException();
            }

            $ingress = $this->protectedIngress->hydrate([
                'record_id' => $row['record_id'],
                'partition_id' => $row['partition_id'],
                'tenant_type' => $row['tenant_type'],
                'tenant_id' => $row['tenant_id'],
                'ingress_ordinal' => $row['ingress_ordinal'],
                'idempotency_key_hash' => $row['idempotency_key_hash'],
                'content_hash' => $row['content_hash'],
                'protected_body' => $row['protected_body'],
            ]);
            $state = AuditOutboxStateSnapshot::fromArray([
                'state' => $row['state'],
                'attempt' => $this->integer($row['attempt'], 0, 32),
                'availableAt' => $row['available_at'],
                'leaseFencingToken' => $row['lease_fencing_token'],
                'leaseExpiresAt' => $row['lease_expires_at'],
                'version' => $this->integer($row['version'], 1, 9_007_199_254_740_991),
            ], $configuration);

            return AuditOutboxRecord::fromParts($ingress, $state);
        } catch (AuditOutboxStoreException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
        }
    }

    private function integer(
        #[\SensitiveParameter]
        mixed $value,
        #[\SensitiveParameter]
        int $minimum,
        #[\SensitiveParameter]
        int $maximum,
    ): int {
        if (is_int($value)) {
            if ($value < $minimum || $value > $maximum) {
                throw new \RuntimeException();
            }

            return $value;
        }
        if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) !== 1) {
            throw new \RuntimeException();
        }
        $maximumText = (string) $maximum;
        if (
            strlen($value) > strlen($maximumText)
            || strlen($value) === strlen($maximumText) && strcmp($value, $maximumText) > 0
        ) {
            throw new \RuntimeException();
        }
        $integer = (int) $value;
        if ($integer < $minimum) {
            throw new \RuntimeException();
        }

        return $integer;
    }
}

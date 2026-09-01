<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Outbox;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class PostgreSqlAuditProtectedIngressHydrator
{
    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    private const int MAX_BODY_BYTES = 1_048_576;

    private const string UUID_V7_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D';

    /**
     * @mago-expect lint:literal-named-argument
     * @param array<array-key,mixed> $row
     */
    public function hydrate(#[\SensitiveParameter] array $row): AuditProtectedIngress
    {
        try {
            $keys = [
                'record_id',
                'partition_id',
                'tenant_type',
                'tenant_id',
                'ingress_ordinal',
                'idempotency_key_hash',
                'content_hash',
                'protected_body',
            ];
            if (array_is_list($row) || array_keys($row) !== $keys) {
                throw new \RuntimeException();
            }

            $recordId = $this->uuid($row['record_id']);
            $partitionId = $this->partition($row['partition_id']);
            $tenant = $this->tenant($row['tenant_type'], $row['tenant_id']);
            $ordinal = $this->integer($row['ingress_ordinal'], 1, self::MAX_SAFE_INTEGER);
            $idempotencyHash = $this->hash($row['idempotency_key_hash']);
            $contentHash = $this->hash($row['content_hash']);
            $body = PostgreSqlBytea::string($row['protected_body']);
            if (strlen($body) < 53 || strlen($body) > self::MAX_BODY_BYTES || substr($body, 0, 4) !== 'BOPD') {
                throw new \RuntimeException();
            }

            return AuditProtectedIngress::fromArray([
                'recordId' => $recordId,
                'partitionId' => $partitionId,
                'tenantBinding' => $tenant?->toArray(),
                'ingressOrdinal' => $ordinal,
                'idempotencyKeyHash' => $idempotencyHash,
                'contentHash' => $contentHash,
                'protectedBody' => $body,
            ]);
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

    private function string(#[\SensitiveParameter] mixed $value): string
    {
        if (!is_string($value)) {
            throw new \RuntimeException();
        }

        return $value;
    }

    private function uuid(#[\SensitiveParameter] mixed $value): string
    {
        $uuid = $this->string($value);
        if (preg_match(self::UUID_V7_PATTERN, $uuid) !== 1) {
            throw new \RuntimeException();
        }

        return $uuid;
    }

    private function partition(#[\SensitiveParameter] mixed $value): string
    {
        $partition = $this->string($value);
        if (preg_match('/\A(?:system:global|tenant:[a-f0-9]{64})\z/D', $partition) !== 1) {
            throw new \RuntimeException();
        }

        return $partition;
    }

    private function hash(#[\SensitiveParameter] mixed $value): string
    {
        $hash = $this->string($value);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $hash) !== 1) {
            throw new \RuntimeException();
        }

        return $hash;
    }

    private function tenant(#[\SensitiveParameter] mixed $type, #[\SensitiveParameter] mixed $id): ?AuditTypedReference
    {
        if ($type === null && $id === null) {
            return null;
        }
        if (!is_string($type) || !is_string($id)) {
            throw new \RuntimeException();
        }

        return new AuditTypedReference($type, $id);
    }
}

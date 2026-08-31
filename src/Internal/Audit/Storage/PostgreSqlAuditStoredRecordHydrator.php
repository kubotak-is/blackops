<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Storage;

use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;

/**
 * The hydrator owns the canonical stored-row grammar and maps malformed input
 * to the fixed integrity failure at one boundary.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class PostgreSqlAuditStoredRecordHydrator
{
    private const int MAX_BODY_BYTES = 1_048_756;

    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    public function __construct(
        private AuditPartitionIdDeriver $partitions,
    ) {}

    /**
     * @param array<array-key,mixed> $row
     */
    public function hydrate(#[\SensitiveParameter] array $row): AuditStoredRecord
    {
        try {
            $keys = [
                'schema_version',
                'kind',
                'record_id',
                'partition_id',
                'tenant_type',
                'tenant_id',
                'segment_id',
                'sequence',
                'previous_record_hash',
                'content_hash',
                'record_hash',
                'recorded_at',
                'event_category',
                'protected_body',
            ];
            if (array_is_list($row) || array_keys($row) !== $keys) {
                throw new \RuntimeException('Malformed audit row.');
            }

            $schemaVersion = self::integer($row['schema_version'], 1, 1);
            $kind = self::string($row['kind']);
            $recordId = self::string($row['record_id']);
            $partitionId = self::string($row['partition_id']);
            $tenant = self::tenant($row['tenant_type'], $row['tenant_id']);
            if ($this->partitions->derive($tenant) !== $partitionId) {
                throw new \RuntimeException('Audit partition binding is invalid.');
            }
            $segmentId = self::string($row['segment_id']);
            $sequence = self::integer($row['sequence'], 1, self::MAX_SAFE_INTEGER);
            $previousRecordHash = self::nullableString($row['previous_record_hash']);
            $contentHash = self::string($row['content_hash']);
            $recordHash = self::string($row['record_hash']);
            $recordedAt = self::string($row['recorded_at']);
            AuditRecordTime::assertTimestamp($recordedAt);
            $eventCategory = self::string($row['event_category']);
            $protectedBody = PostgreSqlBytea::string($row['protected_body']);
            if (
                strlen($protectedBody) < 53
                || strlen($protectedBody) > self::MAX_BODY_BYTES
                || substr($protectedBody, offset: 0, length: 4) !== 'BOPD'
            ) {
                throw new \RuntimeException('Malformed audit protected body.');
            }

            return AuditStoredRecord::fromArray([
                'schemaVersion' => $schemaVersion,
                'kind' => $kind,
                'recordId' => $recordId,
                'partitionId' => $partitionId,
                'tenantBinding' => $tenant?->toArray(),
                'segmentId' => $segmentId,
                'sequence' => $sequence,
                'previousRecordHash' => $previousRecordHash,
                'contentHash' => $contentHash,
                'recordHash' => $recordHash,
                'recordedAt' => $recordedAt,
                'eventCategory' => $eventCategory,
                'protectedBody' => $protectedBody,
            ]);
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    private static function integer(mixed $value, int $minimum, int $maximum): int
    {
        if (is_int($value)) {
            if ($value < $minimum || $value > $maximum) {
                throw new \RuntimeException('Malformed audit integer.');
            }

            return $value;
        }
        if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) !== 1) {
            throw new \RuntimeException('Malformed audit integer.');
        }

        $maximumText = (string) $maximum;
        if (
            strlen($value) > strlen($maximumText)
            || strlen($value) === strlen($maximumText) && strcmp($value, $maximumText) > 0
        ) {
            throw new \RuntimeException('Malformed audit integer.');
        }

        $integer = (int) $value;
        if ($integer < $minimum) {
            throw new \RuntimeException('Malformed audit integer.');
        }

        return $integer;
    }

    private static function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \RuntimeException('Malformed audit string.');
        }

        return $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value !== null && !is_string($value)) {
            throw new \RuntimeException('Malformed audit string.');
        }

        return $value;
    }

    private static function tenant(mixed $type, mixed $id): ?AuditTypedReference
    {
        if ($type === null && $id === null) {
            return null;
        }
        if (!is_string($type) || !is_string($id)) {
            throw new \RuntimeException('Malformed audit tenant.');
        }

        return new AuditTypedReference($type, $id);
    }
}

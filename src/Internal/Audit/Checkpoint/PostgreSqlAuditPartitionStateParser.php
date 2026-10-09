<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final class PostgreSqlAuditPartitionStateParser
{
    private const string UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    private const string HASH = '/^[0-9a-f]{64}$/D';

    private const string PARTITION = '/^(?:system:global|tenant:[a-f0-9]{64})$/D';

    private const int MAX_SAFE_INTEGER = AuditValidationException::MAX_SAFE_INTEGER;

    private const array STATE_REQUIRED_PATTERNS = [
        'partition_id' => self::PARTITION,
        'active_segment_id' => self::UUID_V7,
    ];

    private function __construct() {}

    /**
     * @param array<array-key,mixed>|false $row
     * @return array{partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:string,stateVersion:int}
     */
    public static function parse(
        #[\SensitiveParameter]
        array|false $row,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
    ): array {
        try {
            if ($row === false) {
                throw new AuditStoreException(AuditStoreFailure::SegmentConflict);
            }
            $keys = [
                'partition_id',
                'tenant_type',
                'tenant_id',
                'active_segment_id',
                'last_sequence',
                'head_record_hash',
                'last_assigned_recorded_at',
                'state_version',
            ];
            if (array_keys($row) !== $keys) {
                throw new \RuntimeException();
            }
            $rowPartition = $row['partition_id'];
            $tenantPair = [$row['tenant_type'], $row['tenant_id']];
            $expectedTenantPair = $tenant === null ? [null, null] : [$tenant->type, $tenant->id];
            $active = $row['active_segment_id'];
            $head = $row['head_record_hash'];
            $recordedAt = $row['last_assigned_recorded_at'];
            if (
                $rowPartition !== $partitionId
                || $tenantPair !== $expectedTenantPair
                || $head !== null && (!is_string($head) || preg_match(self::HASH, $head) !== 1)
                || !is_string($recordedAt)
            ) {
                throw new \RuntimeException();
            }
            /** @var array{0:?string,1:?string} $tenantPair */
            foreach (self::STATE_REQUIRED_PATTERNS as $field => $pattern) {
                $value = $row[$field];
                if (!is_string($value) || preg_match($pattern, $value) !== 1) {
                    throw new \RuntimeException();
                }
            }
            /** @var string $rowPartition */
            /** @var ?string $tenantType */
            /** @var ?string $tenantId */
            /** @var string $active */
            /** @var ?string $head */
            /** @var string $recordedAt */
            [$tenantType, $tenantId] = $tenantPair;
            $lastSequence = PostgreSqlAuditCheckpointInteger::read($row['last_sequence'], 0, self::MAX_SAFE_INTEGER);
            $stateVersion = PostgreSqlAuditCheckpointInteger::read($row['state_version'], 1, self::MAX_SAFE_INTEGER);
            if (($lastSequence === 0) !== ($head === null)) {
                throw new \RuntimeException();
            }
            AuditRecordTime::assertTimestamp($recordedAt);

            return [
                'partitionId' => $rowPartition,
                'tenantType' => $tenantType,
                'tenantId' => $tenantId,
                'activeSegmentId' => $active,
                'lastSequence' => $lastSequence,
                'headRecordHash' => $head,
                'lastAssignedRecordedAt' => $recordedAt,
                'stateVersion' => $stateVersion,
            ];
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }
}

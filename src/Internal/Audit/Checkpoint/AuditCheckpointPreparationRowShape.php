<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final class AuditCheckpointPreparationRowShape
{
    private const string UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    private const string PARTITION = '/^(?:system:global|tenant:[a-f0-9]{64})$/D';

    private const string FENCING_TOKEN_PATTERN = '/^aft:v1:[a-f0-9]{64}$/D';

    private const string FINGERPRINT = '/^[a-f0-9]{64}$/D';

    private const array PREPARATION_STRING_PATTERNS = [
        'checkpoint_id' => self::UUID_V7,
        'partition_id' => self::PARTITION,
        'segment_id' => self::UUID_V7,
        'close_fencing_token' => self::FENCING_TOKEN_PATTERN,
        'signing_key_fingerprint' => self::FINGERPRINT,
    ];

    private function __construct() {}

    /**
     * @param array<array-key,mixed> $row
     * @return array{checkpointId:string,partitionId:string,segmentId:string,closedStateVersion:int,closeFencingToken:string,unsignedBytes:string,signingKeyFingerprint:string}
     */
    public static function read(#[\SensitiveParameter] array $row): array
    {
        try {
            $keys = [
                'checkpoint_id',
                'partition_id',
                'segment_id',
                'closed_state_version',
                'close_fencing_token',
                'unsigned_checkpoint',
                'signing_key_fingerprint',
            ];
            if (array_keys($row) !== $keys) {
                throw new \RuntimeException();
            }

            $checkpointId = $row['checkpoint_id'];
            $partitionId = $row['partition_id'];
            $segmentId = $row['segment_id'];
            $token = $row['close_fencing_token'];
            $fingerprint = $row['signing_key_fingerprint'];
            foreach (self::PREPARATION_STRING_PATTERNS as $field => $pattern) {
                $value = $row[$field];
                if (!is_string($value) || preg_match($pattern, $value) !== 1) {
                    throw new \RuntimeException();
                }
            }
            /** @var string $checkpointId */
            /** @var string $partitionId */
            /** @var string $segmentId */
            /** @var string $token */
            /** @var string $fingerprint */

            $version = PostgreSqlAuditCheckpointInteger::read($row['closed_state_version'], 2, 9_007_199_254_740_990);
            $bytes = PostgreSqlAuditCheckpointBytes::database($row['unsigned_checkpoint']);

            return [
                'checkpointId' => $checkpointId,
                'partitionId' => $partitionId,
                'segmentId' => $segmentId,
                'closedStateVersion' => $version,
                'closeFencingToken' => $token,
                'unsignedBytes' => $bytes,
                'signingKeyFingerprint' => $fingerprint,
            ];
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final class PostgreSqlAuditSegmentRowParser
{
    private const string UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    private const array SEGMENT_REQUIRED_PATTERNS = [
        'partition_id' => '/^(?:system:global|tenant:[a-f0-9]{64})$/D',
        'segment_id' => self::UUID_V7,
    ];

    private function __construct() {}

    /**
     * @param array<array-key,mixed> $row
     * @return array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int}
     */
    public static function parse(
        #[\SensitiveParameter]
        array $row,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array {
        try {
            $keys = [
                'partition_id',
                'segment_id',
                'state',
                'first_sequence',
                'final_sequence',
                'head_record_hash',
                'predecessor_record_hash',
                'opened_at',
                'closed_at',
                'successor_segment_id',
                'close_fencing_token',
                'state_version',
            ];
            if (array_keys($row) !== $keys) {
                throw new \RuntimeException();
            }

            $rowPartition = $row['partition_id'];
            $rowSegment = $row['segment_id'];
            $state = $row['state'];
            if (
                $rowPartition !== $partitionId
                || $rowSegment !== $segmentId
                || !in_array($state, ['open', 'closed', 'sealed'], strict: true)
            ) {
                throw new \RuntimeException();
            }
            /** @var string $rowPartition */
            /** @var string $rowSegment */
            /** @var 'open'|'closed'|'sealed' $state */
            foreach (self::SEGMENT_REQUIRED_PATTERNS as $field => $pattern) {
                $value = $row[$field];
                if (!is_string($value) || preg_match($pattern, $value) !== 1) {
                    throw new \RuntimeException();
                }
            }

            $first = PostgreSqlAuditCheckpointInteger::read(
                $row['first_sequence'],
                1,
                AuditValidationException::MAX_SAFE_INTEGER,
            );
            $final = $row['final_sequence'] === null
                ? null
                : PostgreSqlAuditCheckpointInteger::read(
                    $row['final_sequence'],
                    1,
                    AuditValidationException::MAX_SAFE_INTEGER,
                );
            $version = PostgreSqlAuditCheckpointInteger::read(
                $row['state_version'],
                1,
                AuditValidationException::MAX_SAFE_INTEGER,
            );
            $text = PostgreSqlAuditSegmentTextFields::read($row, $rowSegment);
            /** @var array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int} $segment */
            $segment = [
                'partitionId' => $rowPartition,
                'segmentId' => $rowSegment,
                'state' => $state,
                'firstSequence' => $first,
                'finalSequence' => $final,
                'headRecordHash' => $text['headRecordHash'],
                'predecessorRecordHash' => $text['predecessorRecordHash'],
                'openedAt' => $text['openedAt'],
                'closedAt' => $text['closedAt'],
                'successorSegmentId' => $text['successorSegmentId'],
                'closeFencingToken' => $text['closeFencingToken'],
                'stateVersion' => $version,
            ];
            PostgreSqlAuditSegmentLifecycle::assertValid($segment);

            return $segment;
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final class PostgreSqlAuditClosedSegmentEndpoint
{
    private const string HASH = '/^[0-9a-f]{64}$/D';

    private const int MAX_SAFE_INTEGER = AuditValidationException::MAX_SAFE_INTEGER;

    private function __construct() {}

    /**
     * @param array<array-key,mixed>|false $row
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $target
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int}|null $previous
     */
    public static function assertMatches(
        #[\SensitiveParameter]
        array|false $row,
        #[\SensitiveParameter]
        array $target,
        #[\SensitiveParameter]
        ?array $previous,
    ): void {
        try {
            if ($target['firstSequence'] === 1) {
                if ($row !== false || $previous !== null) {
                    throw new \RuntimeException();
                }

                return;
            }
            if ($row === false || $previous === null) {
                throw new \RuntimeException();
            }
            $keys = ['segment_id', 'sequence', 'previous_record_hash', 'record_hash'];
            if (array_keys($row) !== $keys) {
                throw new \RuntimeException();
            }
            $endpointSegment = $row['segment_id'];
            $recordHash = $row['record_hash'];
            if ($endpointSegment !== $previous['segmentId']) {
                throw new \RuntimeException();
            }
            /** @var string $expectedRecordHash */
            $expectedRecordHash = $target['predecessorRecordHash'];
            if (!is_string($recordHash) || !hash_equals($recordHash, $expectedRecordHash)) {
                throw new \RuntimeException();
            }
            /** @var string $endpointSegment */
            /** @var string $recordHash */
            if (
                $row['previous_record_hash'] !== null
                && (
                    !is_string($row['previous_record_hash'])
                    || preg_match(self::HASH, $row['previous_record_hash']) !== 1
                )
            ) {
                throw new \RuntimeException();
            }
            $sequence = PostgreSqlAuditCheckpointInteger::read($row['sequence'], 1, self::MAX_SAFE_INTEGER);
            if ($sequence !== ($target['firstSequence'] - 1)) {
                throw new \RuntimeException();
            }
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }
}

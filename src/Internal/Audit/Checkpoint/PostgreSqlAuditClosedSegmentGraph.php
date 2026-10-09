<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final class PostgreSqlAuditClosedSegmentGraph
{
    private const int MAX_SAFE_INTEGER = AuditValidationException::MAX_SAFE_INTEGER;

    private function __construct() {}

    /**
     * @param array<array-key,mixed>|false $row
     * @return array{partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:string,stateVersion:int}
     */
    public static function state(
        #[\SensitiveParameter]
        array|false $row,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
    ): array {
        return PostgreSqlAuditPartitionStateParser::parse($row, $tenant, $partitionId);
    }

    /**
     * @param array<array-key,mixed>|false $row
     * @param array{partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:string,stateVersion:int} $state
     * @return array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int}
     */
    public static function target(
        #[\SensitiveParameter]
        array|false $row,
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array {
        try {
            if ($row === false) {
                throw new AuditStoreException(AuditStoreFailure::SegmentConflict);
            }
            $segment = PostgreSqlAuditSegmentRowParser::parse($row, $partitionId, $segmentId);
            $segmentState = $segment['state'];
            if (!in_array($segmentState, ['closed', 'sealed'], strict: true)) {
                throw new AuditStoreException(AuditStoreFailure::SegmentConflict);
            }
            /** @var 'closed'|'sealed' $segmentState */
            if ($segment['stateVersion'] < 2) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            if ($state['activeSegmentId'] === $segmentId) {
                throw new AuditStoreException(AuditStoreFailure::SegmentConflict);
            }
            /** @var array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $segment */
            if ($segment['finalSequence'] >= self::MAX_SAFE_INTEGER) {
                throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
            }
            /** @var array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $closedSegment */
            $closedSegment = $segment;
            PostgreSqlAuditClosedSegmentRelations::assertToken($partitionId, $closedSegment);

            return $closedSegment;
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /**
     * @param list<array<array-key,mixed>> $rows
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $target
     * @return array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int}|null
     */
    public static function previous(
        #[\SensitiveParameter]
        array $rows,
        #[\SensitiveParameter]
        array $target,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): ?array {
        return PostgreSqlAuditClosedSegmentNeighbors::previous($rows, $target, $partitionId, $segmentId);
    }

    /**
     * @param list<array<array-key,mixed>> $rows
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $target
     * @return array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int}
     */
    public static function successor(
        #[\SensitiveParameter]
        array $rows,
        #[\SensitiveParameter]
        array $target,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array {
        return PostgreSqlAuditClosedSegmentNeighbors::successor($rows, $target, $partitionId, $segmentId);
    }

    /**
     * @param array<array-key,mixed>|false $row
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $target
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int}|null $previous
     */
    public static function endpoint(
        #[\SensitiveParameter]
        array|false $row,
        #[\SensitiveParameter]
        array $target,
        #[\SensitiveParameter]
        ?array $previous,
    ): void {
        PostgreSqlAuditClosedSegmentEndpoint::assertMatches($row, $target, $previous);
    }
}

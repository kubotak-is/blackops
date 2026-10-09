<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final class PostgreSqlAuditClosedSegmentNeighbors
{
    private function __construct() {}

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
        try {
            if ($target['firstSequence'] === 1) {
                if ($rows !== []) {
                    throw new \RuntimeException();
                }

                return null;
            }
            $neighbor = PostgreSqlAuditClosedSegmentRelations::neighborRow($rows, $segmentId);
            $previous = PostgreSqlAuditSegmentRowParser::parse($neighbor['row'], $partitionId, $neighbor['segmentId']);
            $previousState = $previous['state'];
            if (!in_array($previousState, ['closed', 'sealed'], strict: true)) {
                throw new \RuntimeException();
            }
            /** @var 'closed'|'sealed' $previousState */
            /** @var array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $previous */
            if ($previous['stateVersion'] < 2) {
                throw new \RuntimeException();
            }
            PostgreSqlAuditClosedSegmentRelations::edge($previous, $target);
            PostgreSqlAuditClosedSegmentRelations::assertToken($partitionId, $previous);

            return $previous;
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
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
        try {
            $neighbor = PostgreSqlAuditClosedSegmentRelations::neighborRow($rows, $segmentId);
            if ($neighbor['segmentId'] !== $target['successorSegmentId']) {
                throw new \RuntimeException();
            }
            $successor = PostgreSqlAuditSegmentRowParser::parse($neighbor['row'], $partitionId, $neighbor['segmentId']);
            PostgreSqlAuditClosedSegmentRelations::edge($target, $successor);
            $successorState = $successor['state'];
            /** @var 'open'|'closed'|'sealed' $successorState */
            $hasClosedSuccessor = in_array($successorState, ['closed', 'sealed'], strict: true);
            if ($hasClosedSuccessor) {
                if ($successor['stateVersion'] < 2) {
                    throw new \RuntimeException();
                }
                /** @var array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $closedSuccessor */
                $closedSuccessor = $successor;
                PostgreSqlAuditClosedSegmentRelations::assertToken($partitionId, $closedSuccessor);
            }

            return $successor;
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditValidationException;

final class PostgreSqlAuditClosedSegmentRelations
{
    private const int MAX_SAFE_INTEGER = AuditValidationException::MAX_SAFE_INTEGER;

    private function __construct() {}

    /**
     * @param list<array<array-key,mixed>> $rows
     * @return array{row:array<array-key,mixed>,segmentId:string}
     */
    public static function neighborRow(
        #[\SensitiveParameter]
        array $rows,
        #[\SensitiveParameter]
        string $targetId,
    ): array {
        if (count($rows) !== 1 || !is_array($rows[0]) || !is_string($rows[0]['segment_id'] ?? null)) {
            throw new \RuntimeException();
        }
        $segmentId = $rows[0]['segment_id'];
        if ($segmentId === $targetId) {
            throw new \RuntimeException();
        }

        return ['row' => $rows[0], 'segmentId' => $segmentId];
    }

    /**
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $left
     * @param array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int} $right
     */
    public static function edge(#[\SensitiveParameter] array $left, #[\SensitiveParameter] array $right): void
    {
        if (
            $left['finalSequence'] >= self::MAX_SAFE_INTEGER
            || $right['firstSequence'] !== ($left['finalSequence'] + 1)
            || $right['predecessorRecordHash'] === null
            || !hash_equals($right['predecessorRecordHash'], $left['headRecordHash'])
            || $left['successorSegmentId'] !== $right['segmentId']
            || $left['closedAt'] !== $right['openedAt']
        ) {
            throw new \RuntimeException();
        }
    }

    /** @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $segment */
    public static function assertToken(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        array $segment,
    ): void {
        $head = hex2bin($segment['headRecordHash']);
        if ($head === false) {
            throw new \RuntimeException();
        }
        $domain = 'blackops.audit.segment.close.v1';
        $preimage =
            's:'
            . strlen($domain)
            . ':'
            . $domain
            . 's:'
            . strlen($partitionId)
            . ':'
            . $partitionId
            . 's:'
            . strlen($segment['segmentId'])
            . ':'
            . $segment['segmentId']
            . 'i:'
            . $segment['finalSequence']
            . 'b:'
            . strlen($head)
            . ':'
            . $head
            . 's:'
            . strlen($segment['successorSegmentId'])
            . ':'
            . $segment['successorSegmentId']
            . 's:'
            . strlen($segment['closedAt'])
            . ':'
            . $segment['closedAt'];

        if (!hash_equals($segment['closeFencingToken'], 'aft:v1:' . hash('sha256', $preimage))) {
            throw new \RuntimeException();
        }
    }
}

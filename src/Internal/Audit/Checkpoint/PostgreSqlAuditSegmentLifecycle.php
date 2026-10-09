<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

final class PostgreSqlAuditSegmentLifecycle
{
    private function __construct() {}

    /** @param array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int} $segment */
    public static function assertValid(#[\SensitiveParameter] array $segment): void
    {
        $first = $segment['firstSequence'];
        $final = $segment['finalSequence'];
        $head = $segment['headRecordHash'];
        $predecessor = $segment['predecessorRecordHash'];
        $state = $segment['state'];
        $opened = $segment['openedAt'];
        $closed = $segment['closedAt'];
        $successor = $segment['successorSegmentId'];
        $token = $segment['closeFencingToken'];
        if (($first === 1) !== ($predecessor === null) || ($final === null) !== ($head === null)) {
            throw new \RuntimeException();
        }
        $open = $state === 'open';
        if ($open !== ($closed === null) || $open !== ($successor === null) || $open !== ($token === null)) {
            throw new \RuntimeException();
        }
        if (!$open && $final === null) {
            throw new \RuntimeException();
        }
        if ($final !== null && $final < $first || $closed !== null && strcmp($closed, $opened) < 0) {
            throw new \RuntimeException();
        }
    }
}

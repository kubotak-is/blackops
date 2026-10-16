<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

use BlackOps\Core\Audit\AuditValidationException;
use InvalidArgumentException;

final class AuditHistoryClosedSegmentRelations
{
    private function __construct() {}

    /**
     * @param array{partitionId:string,segmentId:string,state:string,stateVersion:int,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,predecessorRecordHash:?string,previousSegmentId:?string,successorSegmentId:string,closeFencingToken:string,closedAt:string} $v
     */
    public static function proof(#[\SensitiveParameter] array $v): void
    {
        if (($v['firstSequence'] === 1) !== ($v['predecessorRecordHash'] === null)) {
            throw new InvalidArgumentException('History closed relation is invalid.');
        }
        if (($v['predecessorRecordHash'] === null) !== ($v['previousSegmentId'] === null)) {
            throw new InvalidArgumentException('History closed relation is invalid.');
        }
        if (
            $v['lastSequence'] < $v['firstSequence']
            || $v['recordCount'] !== ($v['lastSequence'] - $v['firstSequence'] + 1)
        ) {
            throw new InvalidArgumentException('History closed relation is invalid.');
        }
        if ($v['previousSegmentId'] === $v['segmentId'] || $v['successorSegmentId'] === $v['segmentId']) {
            throw new InvalidArgumentException('History closed relation is invalid.');
        }

        self::version($v['state'], $v['stateVersion']);
    }

    /**
     * @param array{installation_ref:string,binding_digest:string,policy_digest:string,partition_id:string,segment_id:string,first_sequence:int,last_sequence:int,successor_segment_id:string,previous_binding_digest:?string} $v
     */
    public static function binding(#[\SensitiveParameter] array $v): void
    {
        if ($v['last_sequence'] < $v['first_sequence'] || $v['successor_segment_id'] === $v['segment_id']) {
            throw new InvalidArgumentException('History closed relation is invalid.');
        }
    }

    public static function version(#[\SensitiveParameter] string $state, #[\SensitiveParameter] int $version): void
    {
        $minimum = match ($state) {
            'open' => 1,
            'closed', 'sealed' => 2,
            default => throw new InvalidArgumentException('History version relation is invalid.'),
        };
        if ($version < $minimum || $version > AuditValidationException::MAX_SAFE_INTEGER) {
            throw new InvalidArgumentException('History version relation is invalid.');
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

use InvalidArgumentException;

final class AuditHistorySegmentLifecycle
{
    private function __construct() {}

    /**
     * @param array{partition_id:string,segment_id:string,state:string,first_sequence:int,final_sequence:?int,head_record_hash:?string,predecessor_record_hash:?string,opened_at:string,closed_at:?string,successor_segment_id:?string,close_fencing_token:?string,state_version:int} $v
     */
    public static function assertValid(#[\SensitiveParameter] array $v): void
    {
        foreach ([
            [$v['first_sequence'] === 1, $v['predecessor_record_hash'] === null],
            [$v['final_sequence'] === null, $v['head_record_hash'] === null],
        ] as [$left, $right]) {
            if ($left !== $right) {
                throw new InvalidArgumentException('History segment relation is invalid.');
            }
        }
        if ($v['successor_segment_id'] === $v['segment_id']) {
            throw new InvalidArgumentException('History segment relation is invalid.');
        }
        if ($v['final_sequence'] !== null && $v['final_sequence'] < $v['first_sequence']) {
            throw new InvalidArgumentException('History segment relation is invalid.');
        }
        if ($v['closed_at'] !== null && $v['closed_at'] < $v['opened_at']) {
            throw new InvalidArgumentException('History segment relation is invalid.');
        }

        $state = $v['state'];
        $closureValues = $state === 'open'
            ? [$v['closed_at'], $v['successor_segment_id'], $v['close_fencing_token']]
            : [
                $v['final_sequence'],
                $v['head_record_hash'],
                $v['closed_at'],
                $v['successor_segment_id'],
                $v['close_fencing_token'],
            ];
        foreach ($closureValues as $value) {
            if (($state === 'open') !== ($value === null)) {
                throw new InvalidArgumentException('History segment relation is invalid.');
            }
        }

        AuditHistoryClosedSegmentRelations::version($state, $v['state_version']);
    }
}

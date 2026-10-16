<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

use InvalidArgumentException;

final class AuditHistoryCheckpointEndRelations
{
    private function __construct() {}

    /**
     * @param array{work_id:?string,reservation_digest:?string,last_ordinal:int,last_step_digest:?string,preparation_checkpoint_id:?string,unsigned_sha256:?string,unsigned_length:?int,candidate_sha256:?string,candidate_length:?int,complete_sha256:?string,complete_length:?int,higher_work_ordinal_present:bool} $v
     */
    public static function assertValid(#[\SensitiveParameter] array $v): void
    {
        foreach ([
            [$v['work_id'], $v['reservation_digest']],
            [$v['preparation_checkpoint_id'], $v['unsigned_sha256']],
            [$v['unsigned_sha256'], $v['unsigned_length']],
            [$v['candidate_sha256'], $v['candidate_length']],
            [$v['complete_sha256'], $v['complete_length']],
        ] as [$left, $right]) {
            if (($left === null) !== ($right === null)) {
                throw new InvalidArgumentException('History checkpoint end relation is invalid.');
            }
        }
        if (($v['last_ordinal'] === 0) !== ($v['last_step_digest'] === null) || $v['higher_work_ordinal_present']) {
            throw new InvalidArgumentException('History checkpoint end relation is invalid.');
        }

        $work = $v['work_id'] !== null;
        $preparation = $v['preparation_checkpoint_id'] !== null;
        $candidate = $v['candidate_sha256'] !== null;
        $complete = $v['complete_sha256'] !== null;
        $valid = match ([$work, $v['last_ordinal']]) {
            [false, 0] => !$preparation && !$candidate && !$complete,
            [true, 0] => !$candidate && !$complete,
            [true, 1] => $preparation && !$candidate && !$complete,
            [true, 2] => $preparation && $candidate,
            [true, 3], [true, 4] => $preparation && $candidate && $complete,
            default => false,
        };
        if (!$valid) {
            throw new InvalidArgumentException('History checkpoint end relation is invalid.');
        }
    }
}

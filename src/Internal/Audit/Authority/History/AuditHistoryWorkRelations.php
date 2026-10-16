<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

use InvalidArgumentException;

final class AuditHistoryWorkRelations
{
    private function __construct() {}

    /**
     * @param array{reservation_present:bool,preparation_present:bool,complete_present:bool,step_count:int} $v
     */
    public static function presence(#[\SensitiveParameter] array $v): void
    {
        if (!$v['reservation_present']) {
            if ($v['preparation_present'] || $v['complete_present'] || $v['step_count'] !== 0) {
                throw new InvalidArgumentException('History work relation is invalid.');
            }

            return;
        }

        $valid = match ($v['step_count']) {
            0 => !$v['complete_present'],
            1 => $v['preparation_present'] && !$v['complete_present'],
            2 => $v['preparation_present'],
            3, 4 => $v['preparation_present'] && $v['complete_present'],
            default => false,
        };
        if (!$valid) {
            throw new InvalidArgumentException('History work relation is invalid.');
        }
    }

    /**
     * @param array{work_id:string,installation_ref:string,reservation_digest:string,policy_digest:string,artifact_kind:string,partition_id:string,segment_id:string,production_class:string,segment_binding_digest:?string,checkpoint_reservation_digest:null,intent_id:null,receipt_checkpoint_id:null,anchor_reservation_digest:null,anchor_receipt_id:null} $v
     */
    public static function reservation(#[\SensitiveParameter] array $v): void
    {
        if (($v['segment_binding_digest'] !== null) !== ($v['production_class'] === 'control')) {
            throw new InvalidArgumentException('History work relation is invalid.');
        }
    }

    /**
     * @param array{work_id:string,ordinal:int,reservation_digest:string,previous_step_ordinal:?int,previous_step_digest:string,authorizing_policy_digest:string,phase:string,step_digest:string} $v
     */
    public static function step(#[\SensitiveParameter] array $v): void
    {
        $phase = match ($v['ordinal']) {
            1 => 'preparation_bound',
            2 => 'complete_candidate',
            3 => 'artifact_persisted',
            4 => 'ingress_acknowledged',
            default => throw new InvalidArgumentException('History work relation is invalid.'),
        };
        if ($v['phase'] !== $phase) {
            throw new InvalidArgumentException('History work relation is invalid.');
        }
        if ($v['ordinal'] === 1) {
            if ($v['previous_step_ordinal'] !== null || $v['previous_step_digest'] !== $v['reservation_digest']) {
                throw new InvalidArgumentException('History work relation is invalid.');
            }

            return;
        }
        if (
            $v['previous_step_ordinal'] !== ($v['ordinal'] - 1)
            || $v['previous_step_digest'] === $v['reservation_digest']
        ) {
            throw new InvalidArgumentException('History work relation is invalid.');
        }
    }
}

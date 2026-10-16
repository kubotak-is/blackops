<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

use InvalidArgumentException;

final class AuditHistoryContextRelations
{
    private function __construct() {}

    /**
     * @param array{schemaVersion:int,phase:int,installationRef:string,schemaName:string,tenantType:?string,tenantId:?string,partitionId:string,segmentId:?string,workId:?string} $v
     */
    public static function header(#[\SensitiveParameter] array $v): void
    {
        if (($v['tenantType'] === null) !== ($v['tenantId'] === null)) {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
        if (($v['partitionId'] === 'system:global') !== ($v['tenantType'] === null)) {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
        if ($v['phase'] < 10 && $v['segmentId'] === null) {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
        if ($v['phase'] >= 10 && $v['partitionId'] !== 'system:global') {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
    }

    /**
     * @param array{document_sha256:string,policy_version:int,root_policy_digest:string,head_digest:string,revision:int,current_segment_id:string,current_first_sequence:int,current_predecessor_record_hash:?string,closed_segment_tail_digest:?string,policy_state_digest:string,transition_tail_digest:string,acknowledged_transition_digest:?string,transition_ack_digest:?string} $v
     */
    public static function externalAnchor(#[\SensitiveParameter] array $v): void
    {
        if (($v['acknowledged_transition_digest'] === null) !== ($v['transition_ack_digest'] === null)) {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
        if (($v['current_first_sequence'] === 1) !== ($v['current_predecessor_record_hash'] === null)) {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
    }

    /**
     * @param array{partition_id:string,tenant_type:?string,tenant_id:?string,active_segment_id:string,last_sequence:int,head_record_hash:?string,last_assigned_recorded_at:?string,state_version:int} $v
     */
    public static function partition(#[\SensitiveParameter] array $v): void
    {
        if (($v['tenant_type'] === null) !== ($v['tenant_id'] === null)) {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
        if (($v['partition_id'] === 'system:global') !== ($v['tenant_type'] === null)) {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
        if (($v['last_sequence'] === 0) !== ($v['head_record_hash'] === null)) {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
    }

    /**
     * @param array{segment_id:string,sequence:int,previous_record_hash:?string,record_hash:string} $v
     */
    public static function recordEndpoint(#[\SensitiveParameter] array $v): void
    {
        if (($v['sequence'] === 1) !== ($v['previous_record_hash'] === null)) {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
    }

    /**
     * @param array{published_tail_digest:?string,staged_tail_digest:?string,actual_current_segment_id:string,actual_current_first_sequence:int,actual_current_predecessor_record_hash:?string} $v
     */
    public static function stagedBegin(#[\SensitiveParameter] array $v): void
    {
        if (($v['actual_current_first_sequence'] === 1) !== ($v['actual_current_predecessor_record_hash'] === null)) {
            throw new InvalidArgumentException('History context relation is invalid.');
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

use InvalidArgumentException;

final class AuditHistoryPublicationRelations
{
    private function __construct() {}

    /**
     * @param array{installation_ref:string,transition_id:string,transition_digest:string,authorizing_policy_digest:string,previous_transition_digest:?string,previous_policy_state_digest:?string,new_policy_state_digest:string,cause:string} $v
     */
    public static function transition(#[\SensitiveParameter] array $v): void
    {
        if (($v['previous_transition_digest'] === null) !== ($v['previous_policy_state_digest'] === null)) {
            throw new InvalidArgumentException('History publication relation is invalid.');
        }
        if ($v['previous_transition_digest'] === null && $v['cause'] !== 'root_revision') {
            throw new InvalidArgumentException('History publication relation is invalid.');
        }
    }

    /**
     * @param array{installation_ref:string,revision:int,head_digest:string,previous_revision:?int,previous_head_digest:?string,policy_digest:string,policy_state_digest:string,closed_segment_tail_digest:?string,transition_tail_digest:string,acknowledged_transition_digest:?string,transition_ack_digest:?string,document_sha256:string} $v
     */
    public static function publication(#[\SensitiveParameter] array $v): void
    {
        if (($v['previous_revision'] === null) !== ($v['previous_head_digest'] === null)) {
            throw new InvalidArgumentException('History publication relation is invalid.');
        }
        $validRevision = $v['previous_revision'] === null
            ? $v['revision'] === 1
            : $v['previous_revision'] === ($v['revision'] - 1);
        if (!$validRevision) {
            throw new InvalidArgumentException('History publication relation is invalid.');
        }
        if (($v['acknowledged_transition_digest'] === null) !== ($v['transition_ack_digest'] === null)) {
            throw new InvalidArgumentException('History publication relation is invalid.');
        }
    }

    /**
     * @param array{accepted_revision:int,accepted_head_digest:?string,next_revision:int,next_head_digest:?string,next_document_sha256:?string,higher_than_next_present:bool,staged_tip_binding_digest:?string} $v
     */
    public static function end(#[\SensitiveParameter] array $v): void
    {
        if (($v['accepted_revision'] === 0) !== ($v['accepted_head_digest'] === null)) {
            throw new InvalidArgumentException('History publication relation is invalid.');
        }
        if (($v['next_head_digest'] === null) !== ($v['next_document_sha256'] === null)) {
            throw new InvalidArgumentException('History publication relation is invalid.');
        }
        $difference = $v['next_revision'] - $v['accepted_revision'];
        if ($difference !== 0 && $difference !== 1) {
            throw new InvalidArgumentException('History publication relation is invalid.');
        }
        if (
            $difference === 0
            && (
                $v['accepted_head_digest'] === null
                || $v['next_head_digest'] !== $v['accepted_head_digest']
                || $v['next_document_sha256'] === null
            )
        ) {
            throw new InvalidArgumentException('History publication relation is invalid.');
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

use InvalidArgumentException;

final class AuditHistoryNativeGroup
{
    private const string OPTIONAL_FENCING = '?F';

    private const string REQUIRED_FENCING = 'F';

    /** @var array<int,array<string,string>> */
    private const array SCHEMAS = [
        1 => [
            'schemaVersion' => 'v1',
            'phase' => 'phase',
            'installationRef' => 'I',
            'schemaName' => 'S',
            'tenantType' => '?I',
            'tenantId' => '?I',
            'partitionId' => 'P',
            'segmentId' => '?U',
            'workId' => '?U',
        ],
        3 => [
            'document_sha256' => 'H',
            'policy_version' => 'N',
            'root_policy_digest' => 'H',
            'head_digest' => 'H',
            'revision' => 'N',
            'current_segment_id' => 'U',
            'current_first_sequence' => 'N',
            'current_predecessor_record_hash' => '?H',
            'closed_segment_tail_digest' => '?H',
            'policy_state_digest' => 'H',
            'transition_tail_digest' => 'H',
            'acknowledged_transition_digest' => '?H',
            'transition_ack_digest' => '?H',
        ],
        5 => ['policy_digest' => 'H', 'policy_version' => 'N'],
        16 => [
            'partition_id' => 'P',
            'tenant_type' => '?I',
            'tenant_id' => '?I',
            'active_segment_id' => 'U',
            'last_sequence' => 'Z',
            'head_record_hash' => '?H',
            'last_assigned_recorded_at' => '?T',
            'state_version' => 'N',
        ],
        17 => [
            'partition_id' => 'P',
            'segment_id' => 'U',
            'state' => 'stateClosed',
            'first_sequence' => 'N',
            'final_sequence' => '?N',
            'head_record_hash' => '?H',
            'predecessor_record_hash' => '?H',
            'opened_at' => 'T',
            'closed_at' => '?T',
            'successor_segment_id' => '?U',
            'close_fencing_token' => self::OPTIONAL_FENCING,
            'state_version' => 'N',
        ],
        18 => [
            'partition_id' => 'P',
            'segment_id' => 'U',
            'state' => 'stateSuccessor',
            'first_sequence' => 'N',
            'final_sequence' => '?N',
            'head_record_hash' => '?H',
            'predecessor_record_hash' => '?H',
            'opened_at' => 'T',
            'closed_at' => '?T',
            'successor_segment_id' => '?U',
            'close_fencing_token' => self::OPTIONAL_FENCING,
            'state_version' => 'N',
        ],
        19 => [
            'partition_id' => 'P',
            'segment_id' => 'U',
            'state' => 'stateClosed',
            'first_sequence' => 'N',
            'final_sequence' => '?N',
            'head_record_hash' => '?H',
            'predecessor_record_hash' => '?H',
            'opened_at' => 'T',
            'closed_at' => '?T',
            'successor_segment_id' => '?U',
            'close_fencing_token' => self::OPTIONAL_FENCING,
            'state_version' => 'N',
        ],
        21 => [
            'segment_id' => 'U',
            'sequence' => 'N',
            'previous_record_hash' => '?H',
            'record_hash' => 'H',
        ],
        23 => [
            'partition_id' => 'P',
            'segment_id' => 'U',
            'state' => 'stateOpen',
            'first_sequence' => 'N',
            'final_sequence' => '?N',
            'head_record_hash' => '?H',
            'predecessor_record_hash' => '?H',
            'opened_at' => 'T',
            'closed_at' => '?T',
            'successor_segment_id' => '?U',
            'close_fencing_token' => self::OPTIONAL_FENCING,
            'state_version' => 'N',
        ],
        24 => [
            'partitionId' => 'P',
            'segmentId' => 'U',
            'state' => 'stateClosed',
            'stateVersion' => 'N',
            'firstSequence' => 'N',
            'lastSequence' => 'N',
            'recordCount' => 'N',
            'firstRecordHash' => 'H',
            'chainHeadHash' => 'H',
            'predecessorRecordHash' => '?H',
            'previousSegmentId' => '?U',
            'successorSegmentId' => 'U',
            'closeFencingToken' => self::REQUIRED_FENCING,
            'closedAt' => 'T',
        ],
        25 => [
            'reservation_present' => 'B',
            'preparation_present' => 'B',
            'complete_present' => 'B',
            'step_count' => 'stepCount',
        ],
        26 => [
            'work_id' => 'U',
            'installation_ref' => 'I',
            'reservation_digest' => 'H',
            'policy_digest' => 'H',
            'artifact_kind' => 'checkpointArtifact',
            'partition_id' => 'P',
            'segment_id' => 'U',
            'production_class' => 'productionClass',
            'segment_binding_digest' => '?H',
            'checkpoint_reservation_digest' => 'null',
            'intent_id' => 'null',
            'receipt_checkpoint_id' => 'null',
            'anchor_reservation_digest' => 'null',
            'anchor_receipt_id' => 'null',
        ],
        28 => [
            'work_id' => 'U',
            'ordinal' => 'ordinal',
            'reservation_digest' => 'H',
            'previous_step_ordinal' => '?stepOrdinal',
            'previous_step_digest' => 'H',
            'authorizing_policy_digest' => 'H',
            'phase' => 'stepPhase',
            'step_digest' => 'H',
        ],
        31 => [
            'checkpoint_id' => 'U',
            'partition_id' => 'P',
            'segment_id' => 'U',
            'closed_state_version' => 'closedVersion',
            'close_fencing_token' => self::REQUIRED_FENCING,
            'signing_key_fingerprint' => 'H',
        ],
        33 => ['checkpoint_id' => 'U', 'checkpoint_hash' => 'H'],
        35 => [
            'work_id' => '?U',
            'reservation_digest' => '?H',
            'last_ordinal' => 'ordinalZero',
            'last_step_digest' => '?H',
            'preparation_checkpoint_id' => '?U',
            'unsigned_sha256' => '?H',
            'unsigned_length' => '?length',
            'candidate_sha256' => '?H',
            'candidate_length' => '?length',
            'complete_sha256' => '?H',
            'complete_length' => '?length',
            'higher_work_ordinal_present' => 'B',
        ],
        48 => ['tail_digest' => '?H', 'requested_binding_digest' => '?H'],
        49 => [
            'installation_ref' => 'I',
            'binding_digest' => 'H',
            'policy_digest' => 'H',
            'partition_id' => 'systemPartition',
            'segment_id' => 'U',
            'first_sequence' => 'N',
            'last_sequence' => 'N',
            'successor_segment_id' => 'U',
            'previous_binding_digest' => '?H',
        ],
        51 => ['oldest_binding_digest' => '?H'],
        52 => [
            'published_tail_digest' => '?H',
            'staged_tail_digest' => '?H',
            'actual_current_segment_id' => 'U',
            'actual_current_first_sequence' => 'N',
            'actual_current_predecessor_record_hash' => '?H',
        ],
        53 => [
            'installation_ref' => 'I',
            'binding_digest' => 'H',
            'policy_digest' => 'H',
            'partition_id' => 'systemPartition',
            'segment_id' => 'U',
            'first_sequence' => 'N',
            'last_sequence' => 'N',
            'successor_segment_id' => 'U',
            'previous_binding_digest' => '?H',
        ],
        55 => ['reached_published_tail_digest' => '?H', 'staged_tip_next_binding_digest' => 'null'],
        64 => ['tail_digest' => 'H', 'acknowledged_transition_digest' => '?H'],
        65 => [
            'installation_ref' => 'I',
            'transition_id' => 'H',
            'transition_digest' => 'H',
            'authorizing_policy_digest' => 'H',
            'previous_transition_digest' => '?H',
            'previous_policy_state_digest' => '?H',
            'new_policy_state_digest' => 'H',
            'cause' => 'transitionCause',
        ],
        67 => ['oldest_transition_digest' => 'H'],
        68 => [
            'installation_ref' => 'I',
            'acknowledgement_digest' => 'H',
            'authorizing_policy_digest' => 'H',
            'transition_id' => 'H',
            'transition_digest' => 'H',
            'ingress_key' => 'I',
        ],
        72 => ['tail_revision' => 'N', 'tail_head_digest' => 'H'],
        73 => [
            'installation_ref' => 'I',
            'revision' => 'N',
            'head_digest' => 'H',
            'previous_revision' => '?N',
            'previous_head_digest' => '?H',
            'policy_digest' => 'H',
            'policy_state_digest' => 'H',
            'closed_segment_tail_digest' => '?H',
            'transition_tail_digest' => 'H',
            'acknowledged_transition_digest' => '?H',
            'transition_ack_digest' => '?H',
            'document_sha256' => 'H',
        ],
        75 => ['bootstrap_head_digest' => 'H'],
        76 => [
            'installation_ref' => 'I',
            'revision' => 'N',
            'head_digest' => 'H',
            'previous_revision' => '?N',
            'previous_head_digest' => '?H',
            'policy_digest' => 'H',
            'policy_state_digest' => 'H',
            'closed_segment_tail_digest' => '?H',
            'transition_tail_digest' => 'H',
            'acknowledged_transition_digest' => '?H',
            'transition_ack_digest' => '?H',
            'document_sha256' => 'H',
        ],
        79 => [
            'accepted_revision' => 'Z',
            'accepted_head_digest' => '?H',
            'next_revision' => 'N',
            'next_head_digest' => '?H',
            'next_document_sha256' => '?H',
            'higher_than_next_present' => 'B',
            'staged_tip_binding_digest' => '?H',
        ],
    ];

    private \SensitiveParameterValue $snapshot;

    private function __construct(
        AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag $tag,
        #[\SensitiveParameter]
        array $scalars,
    ) {
        try {
            $schema = self::SCHEMAS[$tag->value] ?? null;
            if ($schema === null || array_is_list($scalars)) {
                throw new InvalidArgumentException();
            }
            $keys = array_keys($scalars);
            foreach ($keys as $key) {
                if (
                    !is_string($key)
                    || \ReflectionReference::fromArrayElement($scalars, $key) instanceof \ReflectionReference
                ) {
                    throw new InvalidArgumentException();
                }
            }
            $expected = array_keys($schema);
            sort($keys);
            sort($expected);
            if ($keys !== $expected) {
                throw new InvalidArgumentException();
            }

            /** @var array<string,scalar|null> $admitted */
            $admitted = [];
            foreach ($schema as $key => $kind) {
                $admitted[$key] = AuditHistoryScalar::admit($kind, $scalars[$key]);
            }

            switch ($tag->value) {
                case 1:
                    /** @var array{schemaVersion:int,phase:int,installationRef:string,schemaName:string,tenantType:?string,tenantId:?string,partitionId:string,segmentId:?string,workId:?string} $admitted */
                    AuditHistoryContextRelations::header($admitted);
                    break;
                case 3:
                    /** @var array{document_sha256:string,policy_version:int,root_policy_digest:string,head_digest:string,revision:int,current_segment_id:string,current_first_sequence:int,current_predecessor_record_hash:?string,closed_segment_tail_digest:?string,policy_state_digest:string,transition_tail_digest:string,acknowledged_transition_digest:?string,transition_ack_digest:?string} $admitted */
                    AuditHistoryContextRelations::externalAnchor($admitted);
                    break;
                case 16:
                    /** @var array{partition_id:string,tenant_type:?string,tenant_id:?string,active_segment_id:string,last_sequence:int,head_record_hash:?string,last_assigned_recorded_at:?string,state_version:int} $admitted */
                    AuditHistoryContextRelations::partition($admitted);
                    break;
                case 21:
                    /** @var array{segment_id:string,sequence:int,previous_record_hash:?string,record_hash:string} $admitted */
                    AuditHistoryContextRelations::recordEndpoint($admitted);
                    break;
                case 52:
                    /** @var array{published_tail_digest:?string,staged_tail_digest:?string,actual_current_segment_id:string,actual_current_first_sequence:int,actual_current_predecessor_record_hash:?string} $admitted */
                    AuditHistoryContextRelations::stagedBegin($admitted);
                    break;
                case 17:
                case 18:
                case 19:
                case 23:
                    /** @var array{partition_id:string,segment_id:string,state:string,first_sequence:int,final_sequence:?int,head_record_hash:?string,predecessor_record_hash:?string,opened_at:string,closed_at:?string,successor_segment_id:?string,close_fencing_token:?string,state_version:int} $admitted */
                    AuditHistorySegmentLifecycle::assertValid($admitted);
                    break;
                case 24:
                    /** @var array{partitionId:string,segmentId:string,state:string,stateVersion:int,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,predecessorRecordHash:?string,previousSegmentId:?string,successorSegmentId:string,closeFencingToken:string,closedAt:string} $admitted */
                    AuditHistoryClosedSegmentRelations::proof($admitted);
                    break;
                case 49:
                case 53:
                    /** @var array{installation_ref:string,binding_digest:string,policy_digest:string,partition_id:string,segment_id:string,first_sequence:int,last_sequence:int,successor_segment_id:string,previous_binding_digest:?string} $admitted */
                    AuditHistoryClosedSegmentRelations::binding($admitted);
                    break;
                case 25:
                    /** @var array{reservation_present:bool,preparation_present:bool,complete_present:bool,step_count:int} $admitted */
                    AuditHistoryWorkRelations::presence($admitted);
                    break;
                case 26:
                    /** @var array{work_id:string,installation_ref:string,reservation_digest:string,policy_digest:string,artifact_kind:string,partition_id:string,segment_id:string,production_class:string,segment_binding_digest:?string,checkpoint_reservation_digest:null,intent_id:null,receipt_checkpoint_id:null,anchor_reservation_digest:null,anchor_receipt_id:null} $admitted */
                    AuditHistoryWorkRelations::reservation($admitted);
                    break;
                case 28:
                    /** @var array{work_id:string,ordinal:int,reservation_digest:string,previous_step_ordinal:?int,previous_step_digest:string,authorizing_policy_digest:string,phase:string,step_digest:string} $admitted */
                    AuditHistoryWorkRelations::step($admitted);
                    break;
                case 35:
                    /** @var array{work_id:?string,reservation_digest:?string,last_ordinal:int,last_step_digest:?string,preparation_checkpoint_id:?string,unsigned_sha256:?string,unsigned_length:?int,candidate_sha256:?string,candidate_length:?int,complete_sha256:?string,complete_length:?int,higher_work_ordinal_present:bool} $admitted */
                    AuditHistoryCheckpointEndRelations::assertValid($admitted);
                    break;
                case 65:
                    /** @var array{installation_ref:string,transition_id:string,transition_digest:string,authorizing_policy_digest:string,previous_transition_digest:?string,previous_policy_state_digest:?string,new_policy_state_digest:string,cause:string} $admitted */
                    AuditHistoryPublicationRelations::transition($admitted);
                    break;
                case 73:
                case 76:
                    /** @var array{installation_ref:string,revision:int,head_digest:string,previous_revision:?int,previous_head_digest:?string,policy_digest:string,policy_state_digest:string,closed_segment_tail_digest:?string,transition_tail_digest:string,acknowledged_transition_digest:?string,transition_ack_digest:?string,document_sha256:string} $admitted */
                    AuditHistoryPublicationRelations::publication($admitted);
                    break;
                case 79:
                    /** @var array{accepted_revision:int,accepted_head_digest:?string,next_revision:int,next_head_digest:?string,next_document_sha256:?string,higher_than_next_present:bool,staged_tip_binding_digest:?string} $admitted */
                    AuditHistoryPublicationRelations::end($admitted);
                    break;
                default:
                    break;
            }

            $this->snapshot = new \SensitiveParameterValue(['tag' => $tag, 'scalars' => $admitted]);
        } catch (\Throwable) {
            throw new InvalidArgumentException('History native group is invalid.');
        }
    }

    public static function fromNative(
        AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag $tag,
        #[\SensitiveParameter]
        array $scalars,
    ): self {
        return new self($tag, $scalars);
    }

    public static function fromDecoded(
        AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag $tag,
        #[\SensitiveParameter]
        array $scalars,
    ): self {
        return new self($tag, $scalars);
    }

    public function tag(): AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag
    {
        /** @var array{tag:AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag,scalars:array<string,scalar|null>} $snapshot */
        $snapshot = $this->snapshot->getValue();

        return $snapshot['tag'];
    }

    /** @return array<string,scalar|null> */
    public function scalars(): array
    {
        /** @var array{tag:AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag,scalars:array<string,scalar|null>} $snapshot */
        $snapshot = $this->snapshot->getValue();

        return $snapshot['scalars'];
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        throw new InvalidArgumentException('History native group cannot be serialized.');
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw new InvalidArgumentException('History native group cannot be unserialized.');
    }

    private function __clone(): void
    {
        throw new InvalidArgumentException('History native group cannot be cloned.');
    }
}

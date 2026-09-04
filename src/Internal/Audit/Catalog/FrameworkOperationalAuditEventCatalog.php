<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Catalog;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Core\Audit\AuditSourceOwner;

final class FrameworkOperationalAuditEventCatalog
{
    private static ?AuditEventCatalog $instance = null;

    private function __construct() {}

    public static function catalog(): AuditEventCatalog
    {
        if (self::$instance instanceof AuditEventCatalog) {
            return self::$instance;
        }
        $events = [
            self::event(
                'outbox.delivery.dead_letter_retry_requested',
                AuditActionPhase::Requested,
                'outbox_record',
                AuditOutcomeStatus::Unknown,
                'outbox_retry_requested',
                AuditPolicyDecision::NotApplicable,
                'outbox_retry_requested',
                AuditDelivery::Durable,
            ),
            self::event(
                'outbox.delivery.dead_lettered',
                AuditActionPhase::Failed,
                'outbox_record',
                AuditOutcomeStatus::Failed,
                'outbox_dead_lettered',
                AuditPolicyDecision::NotApplicable,
                'outbox_dead_lettered',
                AuditDelivery::Durable,
            ),
            self::event(
                'outbox.delivery.retry_scheduled',
                AuditActionPhase::Committed,
                'outbox_record',
                AuditOutcomeStatus::Unknown,
                'outbox_retry_scheduled',
                AuditPolicyDecision::NotApplicable,
                'outbox_retry_scheduled',
                AuditDelivery::Durable,
            ),
            self::event(
                'replay.observer.failed',
                AuditActionPhase::Failed,
                'lifecycle_journal',
                AuditOutcomeStatus::Failed,
                'replay_failed',
                AuditPolicyDecision::NotApplicable,
                'replay_failed',
                AuditDelivery::Durable,
            ),
            self::event(
                'replay.observer.progressed',
                AuditActionPhase::Committed,
                'lifecycle_journal',
                AuditOutcomeStatus::Unknown,
                'replay_progressed',
                AuditPolicyDecision::NotApplicable,
                'replay_progressed',
                AuditDelivery::Durable,
            ),
            self::event(
                'replay.observer.started',
                AuditActionPhase::Started,
                'lifecycle_journal',
                AuditOutcomeStatus::Unknown,
                'replay_started',
                AuditPolicyDecision::NotApplicable,
                'replay_started',
                AuditDelivery::Durable,
            ),
            self::event(
                'replay.observer.succeeded',
                AuditActionPhase::Succeeded,
                'lifecycle_journal',
                AuditOutcomeStatus::Succeeded,
                'replay_succeeded',
                AuditPolicyDecision::NotApplicable,
                'replay_succeeded',
                AuditDelivery::Durable,
            ),
            self::event(
                'retention.hold.placed',
                AuditActionPhase::Committed,
                'operation',
                AuditOutcomeStatus::Succeeded,
                'retention_hold_placed',
                AuditPolicyDecision::NotApplicable,
                'retention_hold_placed',
                AuditDelivery::Durable,
            ),
            self::event(
                'retention.hold.released',
                AuditActionPhase::Committed,
                'operation',
                AuditOutcomeStatus::Succeeded,
                'retention_hold_released',
                AuditPolicyDecision::NotApplicable,
                'retention_hold_released',
                AuditDelivery::Durable,
            ),
            self::event(
                'retention.purge.committed',
                AuditActionPhase::Committed,
                'operation_data',
                AuditOutcomeStatus::Succeeded,
                'retention_purged',
                AuditPolicyDecision::NotApplicable,
                'retention_purged',
                AuditDelivery::Durable,
            ),
            self::event(
                'rotation.storage.failed',
                AuditActionPhase::Failed,
                'protected_storage',
                AuditOutcomeStatus::Failed,
                'rotation_failed',
                AuditPolicyDecision::NotApplicable,
                'rotation_failed',
                AuditDelivery::Durable,
            ),
            self::event(
                'rotation.storage.progressed',
                AuditActionPhase::Committed,
                'protected_storage',
                AuditOutcomeStatus::Unknown,
                'rotation_progressed',
                AuditPolicyDecision::NotApplicable,
                'rotation_progressed',
                AuditDelivery::Durable,
            ),
            self::event(
                'rotation.storage.started',
                AuditActionPhase::Started,
                'protected_storage',
                AuditOutcomeStatus::Unknown,
                'rotation_started',
                AuditPolicyDecision::NotApplicable,
                'rotation_started',
                AuditDelivery::Durable,
            ),
            self::event(
                'rotation.storage.succeeded',
                AuditActionPhase::Succeeded,
                'protected_storage',
                AuditOutcomeStatus::Succeeded,
                'rotation_succeeded',
                AuditPolicyDecision::NotApplicable,
                'rotation_succeeded',
                AuditDelivery::Durable,
            ),
        ];
        usort(
            $events,
            static fn(AuditEventDefinition $left, AuditEventDefinition $right): int => (
                [$left->type, $left->version, $left->evidenceVersion] <=> [
                    $right->type,
                    $right->version,
                    $right->evidenceVersion,
                ]
            ),
        );
        return self::$instance = new AuditEventCatalogCodec()->catalog(
            'blackops.framework.operations',
            1,
            AuditSourceOwner::Framework,
            $events,
        );
    }

    /** @mago-expect lint:excessive-parameter-list */
    private static function event(
        string $type,
        AuditActionPhase $phase,
        string $resource,
        AuditOutcomeStatus $status,
        string $outcome,
        AuditPolicyDecision $policy,
        string $reason,
        AuditDelivery $delivery,
    ): AuditEventDefinition {
        return new AuditEventDefinition(
            $type,
            1,
            1,
            AuditEventCategory::from(explode('.', $type)[0]),
            [$type],
            [new AuditSemanticTuple($type, $phase, $resource, $status, $outcome, $policy, $reason)],
            [],
            13,
            $delivery,
        );
    }
}

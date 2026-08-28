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

final class FrameworkAuditEventCatalog
{
    private static ?AuditEventCatalog $instance = null;

    private function __construct() {}

    public static function catalog(): AuditEventCatalog
    {
        if (self::$instance instanceof AuditEventCatalog) {
            return self::$instance;
        }
        $events = [];
        $events[] = self::event(
            'security.authentication.succeeded',
            AuditActionPhase::Succeeded,
            'authentication',
            AuditOutcomeStatus::Succeeded,
            'authenticated',
            AuditPolicyDecision::Allow,
            'authenticated',
            AuditDelivery::Durable,
        );
        $events[] = self::event(
            'security.authentication.invalid',
            AuditActionPhase::Denied,
            'authentication',
            AuditOutcomeStatus::Denied,
            'authentication_invalid',
            AuditPolicyDecision::Deny,
            'authentication_invalid',
            AuditDelivery::Durable,
        );
        /** @var array<string,array{0:AuditActionPhase,1:AuditOutcomeStatus,2:string,3:AuditPolicyDecision,4:AuditDelivery}> $families */
        $families = [
            'allow' => [
                AuditActionPhase::Succeeded,
                AuditOutcomeStatus::Succeeded,
                'authorization_allowed',
                AuditPolicyDecision::Allow,
                AuditDelivery::Durable,
            ],
            'deny' => [
                AuditActionPhase::Denied,
                AuditOutcomeStatus::Denied,
                'authorization_denied',
                AuditPolicyDecision::Deny,
                AuditDelivery::Durable,
            ],
        ];
        foreach ($families as $name => $tuple) {
            $events[] = self::event(
                'security.authorization.' . $name,
                $tuple[0],
                'operation',
                $tuple[1],
                $tuple[2],
                $tuple[3],
                $tuple[2],
                $tuple[4],
            );
            $events[] = self::event(
                'security.authorization.' . $name,
                $tuple[0],
                'operation',
                $tuple[1],
                $tuple[2],
                $tuple[3],
                $tuple[2],
                AuditDelivery::Required,
                2,
            );
        }
        foreach (['status', 'journal', 'outcome', 'diagnostics'] as $purpose) {
            $events[] = self::event(
                'access.operation_' . $purpose . '.requested',
                AuditActionPhase::Requested,
                'operation',
                AuditOutcomeStatus::Unknown,
                'read_requested',
                AuditPolicyDecision::NotApplicable,
                'read_requested',
                AuditDelivery::Required,
            );
            $events[] = self::event(
                'access.operation_' . $purpose . '.unavailable',
                AuditActionPhase::Unknown,
                'operation',
                AuditOutcomeStatus::Unknown,
                'resource_unavailable',
                AuditPolicyDecision::NotApplicable,
                'resource_unavailable',
                AuditDelivery::Required,
            );
            $events[] = self::event(
                'access.operation_' . $purpose . '.accessed',
                AuditActionPhase::Succeeded,
                'operation',
                AuditOutcomeStatus::Succeeded,
                'read_accessed',
                AuditPolicyDecision::Allow,
                'authorization_allowed',
                AuditDelivery::Required,
            );
        }
        foreach (['query', 'export', 'sign', 'verify'] as $purpose) {
            $events[] = self::event(
                'audit.access.' . $purpose . '.requested',
                AuditActionPhase::Requested,
                'audit_trail',
                AuditOutcomeStatus::Unknown,
                'audit_access_requested',
                AuditPolicyDecision::NotApplicable,
                'audit_access_requested',
                AuditDelivery::Durable,
            );
            $events[] = self::event(
                'audit.access.' . $purpose . '.unavailable',
                AuditActionPhase::Unknown,
                'audit_trail',
                AuditOutcomeStatus::Unknown,
                'resource_unavailable',
                AuditPolicyDecision::NotApplicable,
                'resource_unavailable',
                AuditDelivery::Durable,
            );
            $events[] = self::event(
                'audit.access.' . $purpose . '.accessed',
                AuditActionPhase::Succeeded,
                'audit_trail',
                AuditOutcomeStatus::Succeeded,
                'audit_accessed',
                AuditPolicyDecision::Allow,
                'authorization_allowed',
                AuditDelivery::Required,
            );
        }
        usort(
            $events,
            static fn(AuditEventDefinition $a, AuditEventDefinition $b): int => (
                [$a->type, $a->version, $a->evidenceVersion] <=> [$b->type, $b->version, $b->evidenceVersion]
            ),
        );
        return self::$instance = new AuditEventCatalogCodec()->catalog(
            'blackops.framework.security_access',
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
        int $version = 1,
    ): AuditEventDefinition {
        return new AuditEventDefinition(
            $type,
            $version,
            $version,
            AuditEventCategory::from(explode('.', $type)[0] === 'security' ? 'security' : 'access'),
            [$type],
            [new AuditSemanticTuple($type, $phase, $resource, $status, $outcome, $policy, $reason)],
            [],
            13,
            $delivery,
        );
    }
}

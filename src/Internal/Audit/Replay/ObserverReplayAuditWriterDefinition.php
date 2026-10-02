<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;

final class ObserverReplayAuditWriterDefinition
{
    /** @var array<string, array{phase:AuditActionPhase,status:AuditOutcomeStatus,outcomeCode:string}> */
    private const array EXPECTED = [
        'replay.observer.failed' => [
            'phase' => AuditActionPhase::Failed,
            'status' => AuditOutcomeStatus::Failed,
            'outcomeCode' => 'replay_failed',
        ],
        'replay.observer.progressed' => [
            'phase' => AuditActionPhase::Committed,
            'status' => AuditOutcomeStatus::Unknown,
            'outcomeCode' => 'replay_progressed',
        ],
        'replay.observer.started' => [
            'phase' => AuditActionPhase::Started,
            'status' => AuditOutcomeStatus::Unknown,
            'outcomeCode' => 'replay_started',
        ],
        'replay.observer.succeeded' => [
            'phase' => AuditActionPhase::Succeeded,
            'status' => AuditOutcomeStatus::Succeeded,
            'outcomeCode' => 'replay_succeeded',
        ],
    ];

    private function __construct() {}

    public static function definition(
        #[\SensitiveParameter]
        AuditEventCatalog $catalog,
        #[\SensitiveParameter]
        string $eventType,
    ): AuditEventDefinition {
        try {
            $expected = self::EXPECTED[$eventType] ?? throw new \LogicException();
            foreach ($catalog->events as $definition) {
                if ($definition instanceof AuditEventDefinition && self::matches($definition, $expected, $eventType)) {
                    return $definition;
                }
            }
        } catch (AuditDeliveryException $failure) {
            throw ObserverReplayAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }

        throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
    }

    /** @param array{phase:AuditActionPhase,status:AuditOutcomeStatus,outcomeCode:string} $expected */
    public static function matches(
        #[\SensitiveParameter]
        AuditEventDefinition $definition,
        #[\SensitiveParameter]
        array $expected,
        #[\SensitiveParameter]
        string $eventType,
    ): bool {
        if (
            [
                $definition->type,
                $definition->version,
                $definition->evidenceVersion,
                $definition->category,
                $definition->actionTypes,
                $definition->evidenceFields,
                $definition->maxEvidenceBytes,
                $definition->delivery,
                count($definition->semanticTuples),
            ] !== [
                $eventType,
                1,
                1,
                AuditEventCategory::Replay,
                [$eventType],
                [],
                13,
                AuditDelivery::Durable,
                1,
            ]
        ) {
            return false;
        }
        $tuple = $definition->semanticTuples[0] ?? null;
        if (!$tuple instanceof AuditSemanticTuple) {
            return false;
        }

        return (
            [
                $tuple->actionType,
                $tuple->phase,
                $tuple->resourceType,
                $tuple->outcomeStatus,
                $tuple->outcomeCode,
                $tuple->policyDecision,
                $tuple->policyReasonCode,
            ] === [
                $eventType,
                $expected['phase'],
                'lifecycle_journal',
                $expected['status'],
                $expected['outcomeCode'],
                AuditPolicyDecision::NotApplicable,
                $expected['outcomeCode'],
            ]
        );
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\OperationalOutbox;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;

final class GenericOutboxAuditCatalog
{
    private const string CATALOG_HASH = '9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b';
    private const string CATALOG_ID = 'blackops.framework.operations';
    private const string RESOURCE_TYPE = 'outbox_record';

    private function __construct() {}

    public static function definition(
        #[\SensitiveParameter]
        string $eventType,
        AuditSourceRuntime $runtime,
    ): AuditEventDefinition {
        $expected = match ($eventType) {
            'outbox.delivery.retry_scheduled' => [
                'phase' => AuditActionPhase::Committed,
                'status' => AuditOutcomeStatus::Unknown,
                'outcome' => 'outbox_retry_scheduled',
                'runtime' => AuditSourceRuntime::Relay,
            ],
            'outbox.delivery.dead_lettered' => [
                'phase' => AuditActionPhase::Failed,
                'status' => AuditOutcomeStatus::Failed,
                'outcome' => 'outbox_dead_lettered',
                'runtime' => AuditSourceRuntime::Relay,
            ],
            'outbox.delivery.dead_letter_retry_requested' => [
                'phase' => AuditActionPhase::Requested,
                'status' => AuditOutcomeStatus::Unknown,
                'outcome' => 'outbox_retry_requested',
                'runtime' => AuditSourceRuntime::Cli,
            ],
            default => throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid),
        };
        try {
            $catalog = self::catalog();
            foreach ($catalog->events as $definition) {
                if (!self::matches($definition, $expected, $eventType)) {
                    continue;
                }
                if ($expected['runtime'] !== $runtime) {
                    throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::TransactionInvalid);
                }

                return $definition;
            }
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }

        throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
    }

    private static function catalog(): AuditEventCatalog
    {
        try {
            $catalog = FrameworkOperationalAuditEventCatalog::catalog();
            if (
                [
                    'catalogId' => $catalog->catalogId,
                    'catalogVersion' => $catalog->catalogVersion,
                    'owner' => $catalog->owner,
                    'catalogHash' => $catalog->catalogHash,
                ] !== [
                    'catalogId' => self::CATALOG_ID,
                    'catalogVersion' => 1,
                    'owner' => AuditSourceOwner::Framework,
                    'catalogHash' => self::CATALOG_HASH,
                ]
            ) {
                throw new \LogicException();
            }

            return $catalog;
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    /**
     * @param array{
     *     phase:AuditActionPhase,
     *     status:AuditOutcomeStatus,
     *     outcome:string,
     *     runtime:AuditSourceRuntime
     * } $expected
     */
    private static function matches(
        #[\SensitiveParameter]
        AuditEventDefinition $definition,
        #[\SensitiveParameter]
        array $expected,
        #[\SensitiveParameter]
        string $eventType,
    ): bool {
        if (
            [
                'type' => $definition->type,
                'version' => $definition->version,
                'evidenceVersion' => $definition->evidenceVersion,
                'category' => $definition->category,
                'actionTypes' => $definition->actionTypes,
                'semanticTuples' => count($definition->semanticTuples),
                'evidenceFields' => $definition->evidenceFields,
                'maxEvidenceBytes' => $definition->maxEvidenceBytes,
                'delivery' => $definition->delivery,
            ] !== [
                'type' => $eventType,
                'version' => 1,
                'evidenceVersion' => 1,
                'category' => AuditEventCategory::Outbox,
                'actionTypes' => [$eventType],
                'semanticTuples' => 1,
                'evidenceFields' => [],
                'maxEvidenceBytes' => 13,
                'delivery' => AuditDelivery::Durable,
            ]
        ) {
            return false;
        }
        $tuple = $definition->semanticTuples[0];

        return (
            [
                'actionType' => $tuple->actionType,
                'phase' => $tuple->phase,
                'resourceType' => $tuple->resourceType,
                'outcomeStatus' => $tuple->outcomeStatus,
                'outcomeCode' => $tuple->outcomeCode,
                'policyDecision' => $tuple->policyDecision,
                'policyReasonCode' => $tuple->policyReasonCode,
            ] === [
                'actionType' => $eventType,
                'phase' => $expected['phase'],
                'resourceType' => self::RESOURCE_TYPE,
                'outcomeStatus' => $expected['status'],
                'outcomeCode' => $expected['outcome'],
                'policyDecision' => AuditPolicyDecision::NotApplicable,
                'policyReasonCode' => $expected['outcome'],
            ]
        );
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Replay;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditRecordAction;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditRecordEvent;
use BlackOps\Core\Audit\AuditRecordEvidence;
use BlackOps\Core\Audit\AuditRecordOutcome;
use BlackOps\Core\Audit\AuditRecordPolicy;
use BlackOps\Core\Audit\AuditRecordResource;
use BlackOps\Core\Audit\AuditRecordSource;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use DateTimeZone;
use Psr\Clock\ClockInterface;

final class ObserverReplayAuditWriterRecord
{
    private const string CATALOG_HASH = '9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b';
    private const string COMPONENT = 'blackops.replay.observer';
    private const string POLICY_ID = 'blackops.replay.observer';

    private function __construct() {}

    /** @param array{clock:ClockInterface} $values */
    public static function occurredAt(#[\SensitiveParameter] array $values): string
    {
        if (array_keys($values) !== ['clock']) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $value = $values['clock']->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s.u\\Z');
            AuditValidationException::assertSafeString($value, 27, 27);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::ResourceUnavailable);
        }

        return $value;
    }

    /** @param array{opaqueIds:HmacAuditOpaqueIdDeriver} $values */
    public static function opaqueId(
        #[\SensitiveParameter]
        array $values,
        #[\SensitiveParameter]
        AuditOpaqueIdPurpose $purpose,
        #[\SensitiveParameter]
        string $context,
    ): string {
        if (array_keys($values) !== ['opaqueIds']) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $value = (string) $values['opaqueIds']->derive($purpose, $context);
            AuditValidationException::assertSafeString($value, 1, 256);

            return $value;
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::KeyUnavailable);
        }
    }

    /** @param array{selector:AuditDeliverySelector} $values */
    public static function selector(#[\SensitiveParameter] array $values): AuditDeliverySelector
    {
        try {
            if (array_keys($values) !== ['selector']) {
                throw new \LogicException();
            }

            return $values['selector'];
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    public static function tenantReference(#[\SensitiveParameter] ?TenantRef $tenant): ?AuditTypedReference
    {
        if ($tenant === null) {
            return null;
        }
        try {
            $type = $tenant->type();
            $id = $tenant->id();
            $roundTrip = new TenantRef($type, $id);
            if ([$roundTrip->type(), $roundTrip->id()] !== [$type, $id]) {
                throw new \LogicException();
            }

            return new AuditTypedReference($type, $id);
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    /**
     * @param array{
     *     values:array{
     *         opaqueIds:HmacAuditOpaqueIdDeriver,
     *         selector:AuditDeliverySelector,
     *         delivery:AuditDeliveryCoordinator,
     *         clock:ClockInterface,
     *         actor:AuditRecordActor,
     *         correlation:AuditRecordCorrelation,
     *         buildId:string
     *     },
     *     definition:AuditEventDefinition,
     *     actionId:string,
     *     resourceId:string,
     *     occurredAt:string,
     *     tenant:?AuditTypedReference
     * } $input
     */
    public static function draft(#[\SensitiveParameter] array $input): AuditRecordDraft
    {
        try {
            if (
                array_keys($input) !== [
                    'values',
                    'definition',
                    'actionId',
                    'resourceId',
                    'occurredAt',
                    'tenant',
                ]
            ) {
                throw new \LogicException();
            }
            $definition = $input['definition'];
            $tuple = $definition->semanticTuples[0] ?? throw new \LogicException();
            $values = $input['values'];
            if (
                array_keys($values) !== [
                    'opaqueIds',
                    'selector',
                    'delivery',
                    'clock',
                    'actor',
                    'correlation',
                    'buildId',
                ]
            ) {
                throw new \LogicException();
            }
            $buildId = $values['buildId'];
            $actor = $values['actor'];
            $correlation = $values['correlation'];
            AuditValidationException::assertSafeString($buildId, 1, 256);

            return new AuditRecordDraft(
                new AuditRecordEvent(
                    $definition->type,
                    $definition->version,
                    $definition->category,
                    self::CATALOG_HASH,
                ),
                new AuditRecordAction($input['actionId'], $definition->type, $tuple->phase),
                $actor,
                new AuditRecordResource($tuple->resourceType, $input['resourceId']),
                new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
                new AuditRecordPolicy(
                    self::POLICY_ID,
                    1,
                    AuditPolicyDecision::NotApplicable,
                    $tuple->policyReasonCode,
                    AuditDelivery::Durable,
                ),
                $input['tenant'],
                $input['occurredAt'],
                new AuditRecordSource(
                    AuditSourceOwner::Framework,
                    self::COMPONENT,
                    AuditSourceRuntime::Replay,
                    $buildId,
                ),
                $correlation,
                new AuditRecordEvidence(1),
            );
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw ObserverReplayAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }
}

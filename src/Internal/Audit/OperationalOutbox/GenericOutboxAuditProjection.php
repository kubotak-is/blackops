<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\OperationalOutbox;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
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
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use Psr\Clock\ClockInterface;

final class GenericOutboxAuditProjection
{
    private const string CATALOG_HASH = '9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b';
    private const string COMPONENT = 'blackops.outbox.delivery';
    private const string RESOURCE_TYPE = 'outbox_record';

    private function __construct() {}

    /**
     * @return array{
     *     actor:AuditRecordActor,
     *     correlation:AuditRecordCorrelation,
     *     runtime:AuditSourceRuntime,
     *     buildId:string
     * }
     */
    public static function capture(
        #[\SensitiveParameter]
        ActorContext $actor,
        #[\SensitiveParameter]
        AuditRecordCorrelation $correlation,
        AuditSourceRuntime $runtime,
        #[\SensitiveParameter]
        string $buildId,
    ): array {
        try {
            if (!in_array($runtime, [AuditSourceRuntime::Relay, AuditSourceRuntime::Cli], strict: true)) {
                throw new \LogicException();
            }
            AuditValidationException::assertSafeString($buildId, 1, 256);
            $execution = self::reference($actor->execution());
            if ($execution === null) {
                throw new \LogicException();
            }

            return [
                'actor' => new AuditRecordActor(self::reference($actor->authorization()), $execution),
                'correlation' => AuditRecordCorrelation::fromArray($correlation->toArray()),
                'runtime' => $runtime,
                'buildId' => $buildId,
            ];
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::ConfigurationInvalid);
        }
    }

    public static function occurredAt(#[\SensitiveParameter] ClockInterface $clock): string
    {
        try {
            $value = $clock
                ->now()
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\\TH:i:s.u\\Z');
            AuditValidationException::assertSafeString($value, 27, 27);
            if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\\.[0-9]{6}Z\z/D', $value) !== 1) {
                throw new \LogicException();
            }

            return $value;
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::ResourceUnavailable);
        }
    }

    public static function opaqueId(
        #[\SensitiveParameter]
        HmacAuditOpaqueIdDeriver $opaqueIds,
        #[\SensitiveParameter]
        AuditOpaqueIdPurpose $purpose,
        #[\SensitiveParameter]
        string $context,
    ): string {
        try {
            $value = (string) $opaqueIds->derive($purpose, $context);
            AuditValidationException::assertSafeString($value, 1, 256);

            return $value;
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::KeyUnavailable);
        }
    }

    /**
     * @param array{runtime:AuditSourceRuntime,buildId:string} $source
     * @param array{actionId:string,resourceId:string,occurredAt:string,tenant:AuditTypedReference|null} $material
     */
    public static function draft(
        #[\SensitiveParameter]
        AuditEventDefinition $definition,
        #[\SensitiveParameter]
        AuditRecordActor $actor,
        #[\SensitiveParameter]
        AuditRecordCorrelation $correlation,
        #[\SensitiveParameter]
        array $source,
        #[\SensitiveParameter]
        array $material,
    ): AuditRecordDraft {
        try {
            if (
                array_keys($source) !== ['runtime', 'buildId']
                || array_keys($material) !== ['actionId', 'resourceId', 'occurredAt', 'tenant']
            ) {
                throw new \LogicException();
            }
            $tuple = $definition->semanticTuples[0];
            $runtime = $source['runtime'];
            $buildId = $source['buildId'];
            $actionId = $material['actionId'];
            $resourceId = $material['resourceId'];
            $occurredAt = $material['occurredAt'];
            $tenant = $material['tenant'];
            if (!in_array($runtime, [AuditSourceRuntime::Relay, AuditSourceRuntime::Cli], strict: true)) {
                throw new \LogicException();
            }

            return new AuditRecordDraft(
                new AuditRecordEvent(
                    $definition->type,
                    $definition->version,
                    $definition->category,
                    self::CATALOG_HASH,
                ),
                new AuditRecordAction($actionId, $definition->type, $tuple->phase),
                $actor,
                new AuditRecordResource(self::RESOURCE_TYPE, $resourceId),
                new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
                new AuditRecordPolicy(
                    self::COMPONENT,
                    1,
                    AuditPolicyDecision::NotApplicable,
                    $tuple->policyReasonCode,
                    AuditDelivery::Durable,
                ),
                $tenant,
                $occurredAt,
                new AuditRecordSource(AuditSourceOwner::Framework, self::COMPONENT, $runtime, $buildId),
                $correlation,
                new AuditRecordEvidence(1),
            );
        } catch (AuditDeliveryException $failure) {
            throw GenericOutboxAuditFailure::remap($failure);
        } catch (\Throwable) {
            throw GenericOutboxAuditFailure::fresh(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private static function reference(#[\SensitiveParameter] ?ActorRef $reference): ?AuditTypedReference
    {
        if ($reference === null) {
            return null;
        }

        return new AuditTypedReference($reference->type(), $reference->id());
    }
}

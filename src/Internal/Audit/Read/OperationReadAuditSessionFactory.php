<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Read;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditReadRequestBasis;
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
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use Psr\Clock\ClockInterface;

/**
 * Keeps fixed draft construction and the sealed dependency snapshot cohesive.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class OperationReadAuditSessionFactory
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        AuditReadEventWriter $writer,
        #[\SensitiveParameter]
        HmacAuditOpaqueIdDeriver $opaqueIds,
        #[\SensitiveParameter]
        OperationReadNonceSource $nonces,
        #[\SensitiveParameter]
        ClockInterface $clock,
        #[\SensitiveParameter]
        OperationReadExecutionSource $executions,
        AuditSourceRuntime $runtime,
        #[\SensitiveParameter]
        string $buildId,
    ) {
        try {
            AuditValidationException::assertSafeString($buildId, 1, 256);
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ConfigurationInvalid);
        }

        $this->snapshot = new \SensitiveParameterValue([
            'writer' => $writer,
            'opaqueIds' => $opaqueIds,
            'nonces' => $nonces,
            'clock' => $clock,
            'executions' => $executions,
            'runtime' => $runtime,
            'buildId' => $buildId,
        ]);
    }

    public function begin(
        AuditReadPurpose $purpose,
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?ActorRef $currentActor,
        #[\SensitiveParameter]
        ?TenantRef $currentTenant,
    ): OperationReadAuditSession {
        $actor = $this->reference($currentActor);
        $tenant = $this->reference($currentTenant);
        $execution = $this->execution();
        $resourceId = $this->resourceId($operationId);
        $requestedAt = $this->requestedAt();
        $nonce = $this->nonce();

        try {
            $basis = new AuditReadRequestBasis($purpose, $actor, $execution, $tenant, $requestedAt, $nonce);
            $catalog = FrameworkAuditEventCatalog::catalog();
            $requestedDefinition = $this->definition($catalog, $purpose, 'requested');
            $accessedDefinition = $this->definition($catalog, $purpose, 'accessed');
            $unavailableDefinition = $this->definition($catalog, $purpose, 'unavailable');
            $requested = $this->draft($catalog, $requestedDefinition, $basis, $resourceId);
            $accessed = $this->draft($catalog, $accessedDefinition, $basis, $resourceId);
            $unavailable = $this->draft($catalog, $unavailableDefinition, $basis, $resourceId);
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }

        $writer = $this->value('writer');
        if (!$writer instanceof AuditReadEventWriter) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            return OperationReadAuditSession::open($writer, $basis, $requested, $accessed, $unavailable);
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    /** @return array<never,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<never,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<never,never> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['writer', 'opaqueIds', 'nonces', 'clock', 'executions', 'runtime', 'buildId']
            || !$value['writer'] instanceof AuditReadEventWriter
            || !$value['opaqueIds'] instanceof HmacAuditOpaqueIdDeriver
            || !$value['nonces'] instanceof OperationReadNonceSource
            || !$value['clock'] instanceof ClockInterface
            || !$value['executions'] instanceof OperationReadExecutionSource
            || !$value['runtime'] instanceof AuditSourceRuntime
            || !is_string($value['buildId'])
            || !array_key_exists($key, $value)
        ) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            AuditValidationException::assertSafeString($value['buildId'], 1, 256);
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }

        return $value[$key];
    }

    private function reference(#[\SensitiveParameter] ActorRef|TenantRef|null $reference): ?AuditTypedReference
    {
        if ($reference === null) {
            return null;
        }
        try {
            return new AuditTypedReference($reference->type(), $reference->id());
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function execution(): AuditTypedReference
    {
        $source = $this->value('executions');
        if (!$source instanceof OperationReadExecutionSource) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $reference = $source->current();
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
        }
        try {
            return new AuditTypedReference($reference->type, $reference->id);
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function resourceId(#[\SensitiveParameter] OperationId $operationId): string
    {
        $deriver = $this->value('opaqueIds');
        if (!$deriver instanceof HmacAuditOpaqueIdDeriver) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            return (string) $deriver->derive(AuditOpaqueIdPurpose::Resource, $operationId->toString());
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::KeyUnavailable);
        }
    }

    private function requestedAt(): string
    {
        $clock = $this->value('clock');
        if (!$clock instanceof ClockInterface) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $value = $clock
                ->now()
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\\TH:i:s.u\\Z');
            AuditValidationException::assertSafeString($value, 27, 27);
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/D', $value) !== 1) {
                throw new \LogicException();
            }
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
        }

        return $value;
    }

    private function nonce(): string
    {
        $source = $this->value('nonces');
        if (!$source instanceof OperationReadNonceSource) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $bytes = $source->generate();
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
        }
        if (strlen($bytes) !== 16) {
            throw new AuditDeliveryException(AuditDeliveryFailure::ConfigurationInvalid);
        }

        return bin2hex($bytes);
    }

    private function definition(
        AuditEventCatalog $catalog,
        AuditReadPurpose $purpose,
        string $phase,
    ): AuditEventDefinition {
        $type = 'access.operation_' . $purpose->value . '.' . $phase;
        foreach ($catalog->events as $definition) {
            if (
                $definition->type === $type
                && $definition->version === 1
                && $definition->evidenceVersion === 1
                && $definition->delivery === AuditDelivery::Required
            ) {
                return $definition;
            }
        }

        throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
    }

    private function draft(
        AuditEventCatalog $catalog,
        AuditEventDefinition $definition,
        #[\SensitiveParameter]
        AuditReadRequestBasis $basis,
        #[\SensitiveParameter]
        string $resourceId,
    ): AuditRecordDraft {
        $runtime = $this->value('runtime');
        $buildId = $this->value('buildId');
        if (!$runtime instanceof AuditSourceRuntime || !is_string($buildId)) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            AuditValidationException::assertSafeString($buildId, 1, 256);
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            $tuple = $definition->semanticTuples[0];
            $type = $definition->type;

            return new AuditRecordDraft(
                new AuditRecordEvent($type, $definition->version, $definition->category, $catalog->catalogHash),
                new AuditRecordAction($basis->requestRef(), $type, $tuple->phase),
                new AuditRecordActor($basis->actor, $basis->execution),
                new AuditRecordResource($tuple->resourceType, $resourceId),
                new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
                new AuditRecordPolicy(
                    'operation-read',
                    1,
                    $tuple->policyDecision,
                    $tuple->policyReasonCode,
                    AuditDelivery::Required,
                ),
                $basis->tenant,
                $basis->requestedAt,
                new AuditRecordSource(
                    AuditSourceOwner::Framework,
                    $this->component($basis->purpose),
                    $runtime,
                    $buildId,
                ),
                AuditRecordCorrelation::fromArray([]),
                new AuditRecordEvidence($definition->evidenceVersion),
            );
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private function component(AuditReadPurpose $purpose): string
    {
        return 'operation-' . $purpose->value;
    }
}

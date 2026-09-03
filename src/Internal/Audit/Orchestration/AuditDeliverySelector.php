<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class AuditDeliverySelector
{
    public function __construct(
        private ImmutableAuditEventCatalogLookup $lookup,
        private AuditRecordValidator $validator,
    ) {}

    public function select(
        AuditEventCatalog $catalog,
        AuditEventDefinition $definition,
        #[\SensitiveParameter]
        mixed $requirement,
    ): AuditDeliveryPlan {
        if (!is_string($requirement) || !in_array($requirement, ['required', 'durable'], strict: true)) {
            throw self::policyFailure();
        }

        try {
            if ($this->lookup->resolve($catalog->catalogHash) !== $catalog) {
                throw new \RuntimeException();
            }
            $member = $this->member($catalog, $definition);
            if ($member !== $definition || $definition->delivery->value !== $requirement) {
                throw new \RuntimeException();
            }

            return AuditDeliveryPlan::fromSelection(
                $catalog->catalogHash,
                $definition->type,
                $definition->version,
                $definition->evidenceVersion,
                $definition->delivery,
            );
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw self::policyFailure();
        }
    }

    public function forDraft(#[\SensitiveParameter] AuditRecordDraft $draft): AuditDeliveryPlan
    {
        try {
            $this->validator->validateDraft($draft);
            $catalog = $this->lookup->resolve($draft->event->catalogHash);
            if (!$catalog instanceof AuditEventCatalog) {
                throw new \RuntimeException();
            }
            $definition = $this->definition($catalog, $draft);
            if ($definition === null || $draft->policy->auditDelivery !== $definition->delivery) {
                throw new \RuntimeException();
            }

            return AuditDeliveryPlan::fromSelection(
                $catalog->catalogHash,
                $definition->type,
                $definition->version,
                $definition->evidenceVersion,
                $definition->delivery,
            );
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw self::policyFailure();
        }
    }

    public function validate(
        AuditDeliveryPlan $plan,
        #[\SensitiveParameter]
        AuditDeliveryRequest $request,
    ): AuditDelivery {
        try {
            $draft = $request->draft();
            $resolved = $this->forDraft($draft);
            if (
                $resolved->catalogHash() !== $plan->catalogHash()
                || $resolved->eventType() !== $plan->eventType()
                || $resolved->eventVersion() !== $plan->eventVersion()
                || $resolved->evidenceVersion() !== $plan->evidenceVersion()
                || $resolved->delivery() !== $plan->delivery()
            ) {
                throw new \RuntimeException();
            }
            $key = $request->idempotencyKey();
            if ($plan->delivery() === AuditDelivery::Required && $key !== null) {
                throw new \RuntimeException();
            }
            if ($plan->delivery() === AuditDelivery::Durable && $key === null) {
                throw new \RuntimeException();
            }

            return $plan->delivery();
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw self::policyFailure();
        }
    }

    private function member(
        AuditEventCatalog $catalog,
        #[\SensitiveParameter]
        AuditEventDefinition $definition,
    ): ?AuditEventDefinition {
        foreach ($catalog->events as $event) {
            if ($event === $definition) {
                return $event;
            }
        }

        return null;
    }

    private function definition(
        AuditEventCatalog $catalog,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): ?AuditEventDefinition {
        foreach ($catalog->events as $definition) {
            if (
                $definition->type === $draft->event->type
                && $definition->version === $draft->event->version
                && $definition->evidenceVersion === $draft->evidence->version
            ) {
                return $definition;
            }
        }

        return null;
    }

    private static function policyFailure(): AuditDeliveryException
    {
        return new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOutcomeStatus;
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
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryPlan;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use PHPUnit\Framework\TestCase;

final class AuditDeliverySelectorTest extends TestCase
{
    public function testRawSelectionAcceptsOnlyTheDefinitionDelivery(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $required = $this->definition($catalog, AuditDelivery::Required);
        $durable = $this->definition($catalog, AuditDelivery::Durable);

        $requiredPlan = $selector->select($catalog, $required, 'required');
        self::assertSame(AuditDelivery::Required, $requiredPlan->delivery());
        self::assertSame($required->type, $requiredPlan->eventType());

        $durablePlan = $selector->select($catalog, $durable, 'durable');
        self::assertSame(AuditDelivery::Durable, $durablePlan->delivery());
        self::assertSame($durable->type, $durablePlan->eventType());

        foreach ([
            null,
            'best_effort',
            'unknown',
            AuditDelivery::Required,
            1,
            true,
            false,
            [],
            'durable',
        ] as $requirement) {
            try {
                $selector->select($catalog, $required, $requirement);
                self::fail('raw requirement was accepted outside its fixed delivery');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            }
        }

        $applicationRequired = $this->applicationCatalog(AuditDelivery::Required);
        $applicationDurable = $this->applicationCatalog(AuditDelivery::Durable);
        $applicationLookup = new ImmutableAuditEventCatalogLookup([
            $catalog,
            $applicationRequired,
            $applicationDurable,
        ]);
        $applicationSelector = new AuditDeliverySelector(
            $applicationLookup,
            new AuditRecordValidator($applicationLookup),
        );
        self::assertSame(
            AuditDelivery::Required,
            $applicationSelector->select($applicationRequired, $applicationRequired->events[0], 'required')->delivery(),
        );
        self::assertSame(
            AuditDelivery::Durable,
            $applicationSelector->select($applicationDurable, $applicationDurable->events[0], 'durable')->delivery(),
        );
    }

    public function testCatalogAndDefinitionIdentityAreRequired(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $definition = $this->definition($catalog, AuditDelivery::Required);

        $untrustedCatalog = clone $catalog;
        try {
            $selector->select($untrustedCatalog, $definition, 'required');
            self::fail('untrusted catalog was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }

        $untrustedDefinition = clone $definition;
        try {
            $selector->select($catalog, $untrustedDefinition, 'required');
            self::fail('untrusted definition was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }

        try {
            $selector->select($catalog, $definition, 'durable');
            self::fail('delivery substitution was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }
    }

    public function testDraftValidationAndPlanSubstitutionAreRejected(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $draft = $this->draft($catalog, AuditDelivery::Required);
        $request = new AuditDeliveryRequest($draft, null);
        $plan = $selector->forDraft($draft);
        self::assertSame(AuditDelivery::Required, $selector->validate($plan, $request));

        foreach ([
            AuditDeliveryPlan::fromSelection(str_repeat('b', 64), $plan->eventType(), 1, 1, AuditDelivery::Required),
            AuditDeliveryPlan::fromSelection(
                $plan->catalogHash(),
                'access.operation_status.accessed',
                1,
                1,
                AuditDelivery::Required,
            ),
            AuditDeliveryPlan::fromSelection($plan->catalogHash(), $plan->eventType(), 2, 1, AuditDelivery::Required),
            AuditDeliveryPlan::fromSelection($plan->catalogHash(), $plan->eventType(), 1, 2, AuditDelivery::Required),
        ] as $substituted) {
            try {
                $selector->validate($substituted, $request);
                self::fail('substituted plan was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            }
        }

        try {
            $selector->validate($plan, new AuditDeliveryRequest($draft, 'not-allowed'));
            self::fail('required plan accepted an idempotency key');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }
    }

    public function testDraftCategoryPolicyAndNullableDeliverySubstitutionsFailBeforeDelivery(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));

        $requiredDraft = $this->draft($catalog, AuditDelivery::Required);
        $requiredRequest = new AuditDeliveryRequest($requiredDraft, null);
        $requiredPlan = $selector->forDraft($requiredDraft);
        $wrongCategory = $requiredDraft->event->category === AuditEventCategory::Business
            ? AuditEventCategory::Security
            : AuditEventCategory::Business;
        $categoryDraft = $this->draftWith($catalog, AuditDelivery::Required, category: $wrongCategory);
        try {
            $selector->forDraft($categoryDraft);
            self::fail('draft category substitution was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }

        $wrongPolicyDelivery = $requiredDraft->policy->auditDelivery === AuditDelivery::Required
            ? AuditDelivery::Durable
            : AuditDelivery::Required;
        $policyDraft = $this->draftWith($catalog, AuditDelivery::Required, policyDelivery: $wrongPolicyDelivery);
        try {
            $selector->forDraft($policyDraft);
            self::fail('draft policy delivery substitution was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }

        $wrongPlan = AuditDeliveryPlan::fromSelection(
            $requiredPlan->catalogHash(),
            $requiredPlan->eventType(),
            $requiredPlan->eventVersion(),
            $requiredPlan->evidenceVersion(),
            AuditDelivery::Durable,
        );
        try {
            $selector->validate($wrongPlan, $requiredRequest);
            self::fail('plan delivery substitution was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }

        $durableDraft = $this->draft($catalog, AuditDelivery::Durable);
        $durablePlan = $selector->forDraft($durableDraft);
        try {
            $selector->validate($durablePlan, new AuditDeliveryRequest($durableDraft, null));
            self::fail('durable request without nullable idempotency key was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }

        self::assertSame(AuditDelivery::Durable, $selector->validate(
            $durablePlan,
            new AuditDeliveryRequest($durableDraft, 'idempotency-c3'),
        ));
    }

    private function definition(AuditEventCatalog $catalog, AuditDelivery $delivery): AuditEventDefinition
    {
        foreach ($catalog->events as $definition) {
            if ($definition->delivery === $delivery) {
                return $definition;
            }
        }

        self::fail('delivery definition unavailable');
    }

    private function draft(AuditEventCatalog $catalog, AuditDelivery $delivery): AuditRecordDraft
    {
        $definition = $this->definition($catalog, $delivery);
        $tuple = $definition->semanticTuples[0];
        return new AuditRecordDraft(
            new AuditRecordEvent($definition->type, $definition->version, $definition->category, $catalog->catalogHash),
            new AuditRecordAction('action-1', $tuple->actionType, $tuple->phase),
            new AuditRecordActor(
                new AuditTypedReference('user', 'user-1'),
                new AuditTypedReference('execution', 'execution-1'),
            ),
            new AuditRecordResource($tuple->resourceType, 'resource-1'),
            new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            new AuditRecordPolicy('policy-1', 1, $tuple->policyDecision, $tuple->policyReasonCode, $delivery),
            null,
            '2026-09-02T00:00:00.000000Z',
            new AuditRecordSource(AuditSourceOwner::Framework, 'test', AuditSourceRuntime::Cli, 'build-1'),
            AuditRecordCorrelation::fromArray([]),
            new AuditRecordEvidence($definition->evidenceVersion),
        );
    }

    private function draftWith(
        AuditEventCatalog $catalog,
        AuditDelivery $delivery,
        ?AuditEventCategory $category = null,
        ?AuditDelivery $policyDelivery = null,
    ): AuditRecordDraft {
        $definition = $this->definition($catalog, $delivery);
        $tuple = $definition->semanticTuples[0];

        return new AuditRecordDraft(
            new AuditRecordEvent(
                $definition->type,
                $definition->version,
                $category ?? $definition->category,
                $catalog->catalogHash,
            ),
            new AuditRecordAction('action-1', $tuple->actionType, $tuple->phase),
            new AuditRecordActor(
                new AuditTypedReference('user', 'user-1'),
                new AuditTypedReference('execution', 'execution-1'),
            ),
            new AuditRecordResource($tuple->resourceType, 'resource-1'),
            new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
            new AuditRecordPolicy(
                'policy-1',
                1,
                $tuple->policyDecision,
                $tuple->policyReasonCode,
                $policyDelivery ?? $delivery,
            ),
            null,
            '2026-09-02T00:00:00.000000Z',
            new AuditRecordSource(AuditSourceOwner::Framework, 'test', AuditSourceRuntime::Cli, 'build-1'),
            AuditRecordCorrelation::fromArray([]),
            new AuditRecordEvidence($definition->evidenceVersion),
        );
    }

    private function applicationCatalog(AuditDelivery $delivery): AuditEventCatalog
    {
        $eventType = 'application.c3.' . $delivery->value;
        $tuple = new \BlackOps\Core\Audit\AuditSemanticTuple(
            $eventType,
            AuditActionPhase::Succeeded,
            'resource',
            AuditOutcomeStatus::Succeeded,
            'ok',
            AuditPolicyDecision::Allow,
            'ok',
        );
        $event = new AuditEventDefinition(
            $eventType,
            1,
            1,
            AuditEventCategory::Business,
            [$eventType],
            [$tuple],
            [],
            13,
            $delivery,
        );

        return new AuditEventCatalogCodec()->catalog(
            'application.c3.' . $delivery->value,
            1,
            \BlackOps\Core\Audit\AuditSourceOwner::Application,
            [$event],
        );
    }
}

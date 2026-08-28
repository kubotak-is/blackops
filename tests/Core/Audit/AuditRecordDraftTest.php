<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Audit;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCategory;
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
use BlackOps\Core\Audit\AuditValidationException;
use PHPUnit\Framework\TestCase;

final class AuditRecordDraftTest extends TestCase
{
    public function testDraftHasTheExactThirteenMemberWire(): void
    {
        $draft = $this->draft();
        $value = $draft->toArray();
        self::assertSame(
            [
                'schemaVersion',
                'kind',
                'event',
                'action',
                'actor',
                'resource',
                'outcome',
                'policy',
                'tenant',
                'occurredAt',
                'source',
                'correlation',
                'evidence',
            ],
            array_keys($value),
        );
        self::assertSame(1, $value['schemaVersion']);
        self::assertSame('audit', $value['kind']);
        self::assertArrayNotHasKey('recordId', $value);
        self::assertArrayNotHasKey('recordedAt', $value);
        self::assertArrayNotHasKey('time', $value);
        self::assertArrayNotHasKey('integrity', $value);
        self::assertSame(['operationId' => null], $value['correlation']);
    }

    public function testDraftDoesNotOfferTrustedConstructionMembers(): void
    {
        $constructor = new \ReflectionClass(AuditRecordDraft::class)->getConstructor();
        self::assertNotNull($constructor);
        $names = array_map(
            static fn(\ReflectionParameter $parameter): string => $parameter->getName(),
            $constructor->getParameters(),
        );
        self::assertSame(
            [
                'event',
                'action',
                'actor',
                'resource',
                'outcome',
                'policy',
                'tenant',
                'occurredAt',
                'source',
                'correlation',
                'evidence',
            ],
            $names,
        );
    }

    public function testDraftTimestampIsStrict(): void
    {
        try {
            $this->draft(occurredAt: '2026-08-20T03:20:00Z');
            self::fail('non-fractional timestamp accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('grammar_invalid', $exception->code());
        }
    }

    private function draft(string $occurredAt = '2026-08-20T03:20:00.123456Z'): AuditRecordDraft
    {
        return new AuditRecordDraft(
            event: new AuditRecordEvent('business.order.created', 1, AuditEventCategory::Business, str_repeat('a', 64)),
            action: new AuditRecordAction('action-1', 'business.order.created', AuditActionPhase::Succeeded),
            actor: new AuditRecordActor(null, new AuditTypedReference('cli', 'exec-1')),
            resource: new AuditRecordResource('order', 'order-1'),
            outcome: new AuditRecordOutcome(AuditOutcomeStatus::Succeeded, 'ok'),
            policy: new AuditRecordPolicy(
                'policy-1',
                1,
                AuditPolicyDecision::Allow,
                'allowed',
                AuditDelivery::Required,
            ),
            tenant: null,
            occurredAt: $occurredAt,
            source: new AuditRecordSource(AuditSourceOwner::Application, 'orders', AuditSourceRuntime::Http, 'build-1'),
            correlation: AuditRecordCorrelation::fromArray(['operationId' => null]),
            evidence: new AuditRecordEvidence(1),
        );
    }
}

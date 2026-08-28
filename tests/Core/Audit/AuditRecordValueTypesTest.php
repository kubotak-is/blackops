<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Audit;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditAttemptKind;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditRecordAction;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordAttempt;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditRecordEvent;
use BlackOps\Core\Audit\AuditRecordEvidence;
use BlackOps\Core\Audit\AuditRecordOutcome;
use BlackOps\Core\Audit\AuditRecordPolicy;
use BlackOps\Core\Audit\AuditRecordResource;
use BlackOps\Core\Audit\AuditRecordSource;
use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use PHPUnit\Framework\TestCase;

final class AuditRecordValueTypesTest extends TestCase
{
    public function testEnumsAndNestedValueArraysAreClosed(): void
    {
        self::assertSame(['initial', 'retry', 'replay'], array_column(AuditAttemptKind::cases(), 'value'));
        self::assertSame(
            ['http', 'cli', 'worker', 'scheduler', 'relay', 'replay'],
            array_column(AuditSourceRuntime::cases(), 'value'),
        );
        $reference = new AuditTypedReference(' Type/V1 ', 'ID:AbC/01');
        $attempt = new AuditRecordAttempt(2, AuditAttemptKind::Retry);
        $correlation = AuditRecordCorrelation::fromArray([
            'operationId' => 'operation-1',
            'correlationId' => null,
            'attempt' => $attempt->toArray(),
            'traceId' => str_repeat('a', 32),
            'spanId' => str_repeat('b', 16),
            'sampled' => true,
        ]);
        self::assertSame(
            [
                'operationId' => 'operation-1',
                'correlationId' => null,
                'attempt' => ['number' => 2, 'kind' => 'retry'],
                'traceId' => str_repeat('a', 32),
                'spanId' => str_repeat('b', 16),
                'sampled' => true,
            ],
            $correlation->toArray(),
        );
        self::assertSame(['type' => ' Type/V1 ', 'id' => 'ID:AbC/01'], $reference->toArray());
        self::assertSame(
            ['version' => 1, 'result_code' => 'ok', 'count' => 2],
            new AuditRecordEvidence([
                'version' => 1,
                'result_code' => 'ok',
                'count' => 2,
            ])->toArray(),
        );
    }

    public function testCorrelationOmissionAndExplicitNullRemainDistinct(): void
    {
        self::assertSame([], AuditRecordCorrelation::fromArray([])->toArray());
        self::assertSame(
            ['operationId' => null],
            AuditRecordCorrelation::fromArray(['operationId' => null])->toArray(),
        );
        self::assertSame(['sampled' => null], AuditRecordCorrelation::fromArray(['sampled' => null])->toArray());
        self::assertSame(
            ['operationId' => null, 'attempt' => null],
            AuditRecordCorrelation::fromArray(['operationId' => null, 'attempt' => null])->toArray(),
        );
    }

    public function testCorrelationFactoryRejectsOmissionSentinelsWithoutNormalizingInput(): void
    {
        self::assertSame(
            ['operationId' => ' operation-1 '],
            AuditRecordCorrelation::fromArray(['operationId' => ' operation-1 '])->toArray(),
        );
        self::assertSame(['sampled' => false], AuditRecordCorrelation::fromArray(['sampled' => false])->toArray());
        foreach ([
            ['operationId' => false],
            ['operationId' => ''],
            ['attempt' => false],
            ['sampled' => ''],
            ['unknown' => null],
        ] as $members) {
            try {
                AuditRecordCorrelation::fromArray($members);
                self::fail('omission sentinel accepted');
            } catch (AuditValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testValueBoundariesAndTimestampRoundTripAreStrict(): void
    {
        new AuditRecordAttempt(1, AuditAttemptKind::Initial);
        new AuditRecordAttempt(AuditValidationException::MAX_SAFE_INTEGER, AuditAttemptKind::Replay);
        new AuditRecordTime('2026-08-20T03:20:00.123456Z', '2026-08-20T03:20:00.123456Z');
        new AuditRecordEvent('business.order.created', 1, AuditEventCategory::Business, str_repeat('a', 64));
        new AuditRecordAction(str_repeat('i', 256), 'business.order.created', AuditActionPhase::Succeeded);
        new AuditRecordResource('order', str_repeat('r', 256));
        new AuditRecordOutcome(AuditOutcomeStatus::Succeeded, 'ok');
        new AuditRecordPolicy('policy', 1, AuditPolicyDecision::Allow, 'allowed', AuditDelivery::Required);
        new AuditRecordSource(AuditSourceOwner::Application, 'component', AuditSourceRuntime::Http, 'build');

        foreach ([
            fn(): object => new AuditRecordAttempt(0, AuditAttemptKind::Initial),
            fn(): object => new AuditRecordAttempt(
                AuditValidationException::MAX_SAFE_INTEGER + 1,
                AuditAttemptKind::Initial,
            ),
            fn(): object => AuditRecordCorrelation::fromArray(['traceId' => str_repeat('a', 31)]),
            fn(): object => AuditRecordCorrelation::fromArray(['spanId' => str_repeat('A', 16)]),
            fn(): object => new AuditRecordTime('2026-02-30T03:20:00.123456Z', '2026-02-30T03:20:00.123456Z'),
            fn(): object => new AuditRecordTime('2026-08-20T03:20:00.12345Z', '2026-08-20T03:20:00.123456Z'),
            fn(): object => new AuditRecordEvent('Bad!', 1, AuditEventCategory::Business, str_repeat('a', 64)),
            fn(): object => new AuditRecordOutcome(AuditOutcomeStatus::Failed, 'Bad!'),
            fn(): object => new AuditRecordEvidence(['version' => 1, 'value' => new \stdClass()]),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('invalid value accepted');
            } catch (AuditValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }
}

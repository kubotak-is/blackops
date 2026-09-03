<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditDelivery;
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
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryPlan;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use PHPUnit\Framework\TestCase;

final class AuditDeliveryValueTypesTest extends TestCase
{
    public function testFailureEnumIsClosedAndExceptionIsValueFree(): void
    {
        self::assertSame(
            [
                'configuration_invalid',
                'policy_invalid',
                'transaction_invalid',
                'key_unavailable',
                'resource_unavailable',
                'storage_unavailable',
            ],
            array_map(
                static fn(AuditDeliveryFailure $failure): string => $failure->value,
                AuditDeliveryFailure::cases(),
            ),
        );

        $exception = new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
        self::assertSame('Audit delivery failed.', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        self::assertSame('policy_invalid', $exception->code());
        self::assertSame([], $exception->__debugInfo());
        self::assertSame([], $exception->__serialize());
        self::assertStringNotContainsString('policy_invalid', var_export($exception, true));
    }

    public function testPlanBindsOnlyTrustedSelectionValues(): void
    {
        $plan = AuditDeliveryPlan::fromSelection(
            str_repeat('a', 64),
            'access.operation_status.requested',
            1,
            1,
            AuditDelivery::Required,
        );
        self::assertSame(str_repeat('a', 64), $plan->catalogHash());
        self::assertSame('access.operation_status.requested', $plan->eventType());
        self::assertSame(1, $plan->eventVersion());
        self::assertSame(1, $plan->evidenceVersion());
        self::assertSame(AuditDelivery::Required, $plan->delivery());

        foreach ([
            ['',                  'access.operation_status.requested', 1, 1],
            [str_repeat('a', 63), 'access.operation_status.requested', 1, 1],
            [str_repeat('a', 64), 'not safe',                          1, 1],
            [str_repeat('a', 64), 'access.operation_status.requested', 0, 1],
        ] as [$catalogHash, $eventType, $eventVersion, $evidenceVersion]) {
            try {
                AuditDeliveryPlan::fromSelection(
                    $catalogHash,
                    $eventType,
                    $eventVersion,
                    $evidenceVersion,
                    AuditDelivery::Required,
                );
                self::fail('invalid plan input was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            }
        }
    }

    public function testRequestKeepsDraftAndExactIdempotencyKeyWithoutNormalization(): void
    {
        $draft = $this->draft(AuditDelivery::Durable);
        $request = new AuditDeliveryRequest($draft, '  key-01  ');
        self::assertSame($draft, $request->draft());
        self::assertSame('  key-01  ', $request->idempotencyKey());
        self::assertSame([], $request->__debugInfo());
        self::assertSame([], $request->__serialize());

        foreach (['', str_repeat('x', 257), "bad\u{0000}key", "e\u{0301}"] as $key) {
            try {
                new AuditDeliveryRequest($draft, $key);
                if ($key === "e\u{0301}" && !class_exists('Normalizer')) {
                    continue;
                }
                self::fail('invalid idempotency key was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            }
        }
    }

    public function testUnserializeCannotInjectSensitiveValues(): void
    {
        $draft = $this->draft(AuditDelivery::Required);
        foreach ([
            AuditDeliveryException::class => static function () use ($draft): void {
                $exception = new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
                $exception->__unserialize(['canary' => $draft]);
            },
            AuditDeliveryRequest::class => static function () use ($draft): void {
                $request = new AuditDeliveryRequest($draft, null);
                $request->__unserialize(['canary' => $draft]);
            },
        ] as $type => $operation) {
            try {
                $operation();
                self::fail('unserialize accepted a value-bearing payload');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure(), $type);
            }
        }
    }

    private function draft(AuditDelivery $delivery): AuditRecordDraft
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        foreach ($catalog->events as $definition) {
            if ($definition->delivery !== $delivery) {
                continue;
            }
            $tuple = $definition->semanticTuples[0];
            return new AuditRecordDraft(
                new AuditRecordEvent(
                    $definition->type,
                    $definition->version,
                    $definition->category,
                    $catalog->catalogHash,
                ),
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

        self::fail('delivery fixture is unavailable');
    }
}

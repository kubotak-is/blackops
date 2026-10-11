<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Anchor\AuditAnchorBinding;
use BlackOps\Internal\Audit\Anchor\AuditAnchorClaims;
use BlackOps\Internal\Audit\Anchor\AuditAnchorConfiguration;
use BlackOps\Internal\Audit\Anchor\AuditAnchorException;
use BlackOps\Internal\Audit\Anchor\AuditAnchorExpectation;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFailure;
use BlackOps\Internal\Audit\Anchor\AuditAnchorIntent;
use PHPUnit\Framework\TestCase;

final class AuditAnchorPrivacyTest extends TestCase
{
    public function testCarriersHaveEmptyRepresentationsAndFreshValueFreeUnserializeFailures(): void
    {
        $canary = 'anchor-privacy-canary';
        $frame = \BlackOps\Internal\Audit\Anchor\AuditAnchorFrame::create(
            [
                'intentId' => $canary,
                'partitionId' => 'system:global',
                'checkpointId' => 'checkpoint-privacy',
                'checkpointHash' => AuditAnchorFixture::CHECKPOINT_HASH,
                'checkpointBytesHash' => AuditAnchorFixture::CHECKPOINT_BYTES_HASH,
                'anchorStoreRef' => 'anchor-store',
                'anchorObjectRef' => 'object-privacy',
                'expectedPredecessorVersion' => null,
                'expectedPredecessorHash' => null,
                'conditionalCreateToken' => $canary,
            ],
            AuditAnchorBinding::fromArray([
                'segmentId' => AuditAnchorFixture::SEGMENT,
                'previousAnchorReceiptHash' => null,
                'checkpointSignedAt' => '2030-01-02T03:03:05.123456Z',
                'anchorReceiptId' => $canary,
            ]),
            $canary,
            new AuditAnchorExpectation(0, null, null),
            AuditAnchorFixture::NOW,
        );
        $intent = $frame->intent();
        $claimed = new AuditAnchorClaims(new AuditAnchorConfiguration())->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            $canary,
            AuditAnchorFixture::NOW,
        );
        foreach ([
            $intent,
            $frame->binding(),
            AuditAnchorFixture::expectation($frame),
            $claimed->leaseOrigin(),
            $claimed,
        ] as $carrier) {
            foreach ([
                serialize($carrier),
                (string) json_encode($carrier, JSON_THROW_ON_ERROR),
                var_export($carrier, true),
            ] as $representation) {
                self::assertStringNotContainsString($canary, $representation);
                self::assertStringNotContainsString('intent-001', $representation);
            }
        }
        foreach ([
            AuditAnchorIntent::class,
            AuditAnchorExpectation::class,
            $frame::class,
            $frame->binding()::class,
            $claimed->leaseOrigin()::class,
        ] as $class) {
            try {
                new \ReflectionClass($class)
                    ->newInstanceWithoutConstructor()
                    ->__unserialize(['canary' => $canary]);
                self::fail('unserialize accepted data');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString($canary, $exception->getMessage());
            }
        }
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            try {
                new AuditAnchorConfiguration([$canary], $canary);
                self::fail('invalid configuration accepted');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::ConfigurationInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString($canary, json_encode($exception->getTrace(), JSON_THROW_ON_ERROR));
                self::assertStringNotContainsString($canary, var_export($exception->getTrace(), true));
            }

            try {
                $invalid = $intent->toArray();
                $invalid['unknown'] = $canary;
                AuditAnchorIntent::fromArray($invalid);
                self::fail('canary input accepted');
            } catch (AuditAnchorException $exception) {
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString($canary, $exception->getMessage());
                self::assertStringNotContainsString($canary, $exception->getTraceAsString());
                self::assertStringNotContainsString($canary, json_encode($exception->getTrace(), JSON_THROW_ON_ERROR));
            }

            try {
                new AuditAnchorClaims(new AuditAnchorConfiguration())->externalSubmission(
                    $frame,
                    AuditAnchorFixture::expectation($frame),
                    $canary,
                    $canary,
                );
                self::fail('invalid transition time accepted');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString($canary, json_encode($exception->getTrace(), JSON_THROW_ON_ERROR));
            }
        } finally {
            if ($previousIgnoreArgs !== false) {
                ini_set('zend.exception_ignore_args', $previousIgnoreArgs);
            }
        }
    }

    public function testReferenceBackedInputAndProjectionMutationRemainDetached(): void
    {
        $intentId = 'reference-intent';
        $request = [
            'intentId' => &$intentId,
            'partitionId' => 'system:global',
            'checkpointId' => 'reference-checkpoint',
            'checkpointHash' => AuditAnchorFixture::CHECKPOINT_HASH,
            'checkpointBytesHash' => AuditAnchorFixture::CHECKPOINT_BYTES_HASH,
            'anchorStoreRef' => 'anchor-store',
            'anchorObjectRef' => 'reference-object',
            'expectedPredecessorVersion' => null,
            'expectedPredecessorHash' => null,
            'conditionalCreateToken' => 'reference-token',
        ];
        $input = &$request;
        $frame = \BlackOps\Internal\Audit\Anchor\AuditAnchorFrame::create(
            $input,
            AuditAnchorBinding::fromArray([
                'segmentId' => AuditAnchorFixture::SEGMENT,
                'previousAnchorReceiptHash' => null,
                'checkpointSignedAt' => '2030-01-02T03:03:05.123456Z',
                'anchorReceiptId' => 'reference-receipt',
            ]),
            'reference-raw-key',
            new AuditAnchorExpectation(0, null, null),
            AuditAnchorFixture::NOW,
        );
        $intentId = 'caller-mutated-input';
        self::assertSame('caller-mutated-input', $input['intentId']);
        self::assertSame('reference-intent', $frame->intent()->toArray()['intentId']);

        $projection = $frame->toArray();
        $projectedIntent = &$projection['intent'];
        $projectedIntent['intentId'] = 'caller-mutated-projection';
        self::assertSame('reference-intent', $frame->intent()->toArray()['intentId']);
    }
}

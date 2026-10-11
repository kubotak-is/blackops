<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Anchor\AuditAnchorBinding;
use BlackOps\Internal\Audit\Anchor\AuditAnchorCanonical;
use BlackOps\Internal\Audit\Anchor\AuditAnchorClaims;
use BlackOps\Internal\Audit\Anchor\AuditAnchorException;
use BlackOps\Internal\Audit\Anchor\AuditAnchorExpectation;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFailure;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFrame;
use BlackOps\Internal\Audit\Anchor\AuditAnchorIntent;
use BlackOps\Internal\Audit\AuditJcsCodec;
use PHPUnit\Framework\TestCase;

final class AuditAnchorIntentTest extends TestCase
{
    public function testGenesisIsExactThirtyEightFieldDetachedPresentation(): void
    {
        $intent = AuditAnchorFixture::frame()->intent();
        $value = $intent->toArray();
        self::assertCount(38, $value);
        self::assertSame('prepared', $intent->state()->value);
        self::assertSame(1, $intent->version());
        self::assertSame(AuditAnchorCanonical::stateHash($value), $value['stateHash']);
        $value['intentId'] = 'caller-mutated';
        self::assertSame('intent-001', $intent->toArray()['intentId']);
    }

    public function testUnknownFieldHashOrStateMatrixMutationFailsWithoutCause(): void
    {
        $value = AuditAnchorFixture::frame()->intent()->toArray();
        $mutations = [
            array_merge($value, ['unknown' => true]),
            array_replace($value, ['stateHash' => str_repeat('d', 64)]),
            array_replace($value, ['preCallClaimMode' => null]),
            array_replace($value, ['attempt' => '1']),
        ];
        $mutations[2]['stateHash'] = AuditAnchorCanonical::stateHash($mutations[2]);
        $mutations[3]['stateHash'] = AuditAnchorCanonical::stateHash($mutations[3]);
        foreach ($mutations as $mutated) {
            try {
                AuditAnchorIntent::fromArray($mutated);
                self::fail('invalid intent accepted');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testAttemptZeroCannotCarryAnActiveLookupStatus(): void
    {
        $value = AuditAnchorFixture::frame()->intent()->toArray();
        $value['preCallLookupStatus'] = 'pending';
        $value['stateHash'] = AuditAnchorCanonical::stateHash($value);
        try {
            \BlackOps\Internal\Audit\Anchor\AuditAnchorIntent::fromArray($value);
            self::fail('attempt zero pending status accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
        }
    }

    public function testPostCallUncertainRequiresPositiveAttemptEvenWithRecomputedHash(): void
    {
        $value = AuditAnchorFixture::frame()->intent()->toArray();
        $value['externalCallPhase'] = 'post_call_uncertain';
        $value['preCallClaimMode'] = null;
        $value['preCallLookupStatus'] = null;
        $value['nextAvailableAt'] = '2030-01-02T03:09:05.123456Z';
        $value['stateHash'] = AuditAnchorCanonical::stateHash($value);
        try {
            AuditAnchorIntent::fromArray($value);
            self::fail('zero-attempt post-call intent accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $carrier = AuditAnchorFixture::frame()->toArray();
        $carrier['intent'] = $value;
        try {
            AuditAnchorFrame::restore($carrier);
            self::fail('zero-attempt post-call frame accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testAttemptThirtyThreeAndUnsafeVersionAreRejectedEvenWithRecomputedHashes(): void
    {
        $value = AuditAnchorFixture::frame()->intent()->toArray();
        foreach ([
            ['attempt' => 33],
            ['version' => AuditJcsCodec::MAX_SAFE_INTEGER + 1],
        ] as $mutation) {
            $mutated = [...$value, ...$mutation];
            if (array_key_exists('attempt', $mutation)) {
                $mutated['stateHash'] = AuditAnchorCanonical::stateHash($mutated);
            }
            try {
                AuditAnchorIntent::fromArray($mutated);
                self::fail('unsafe intent accepted');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testReorderedKeysAndDetachedProjectionsRemainExact(): void
    {
        $frame = AuditAnchorFixture::frame();
        $value = $frame->toArray();
        $reordered = array_reverse($value['intent'], true);
        $restored = AuditAnchorFrame::restore([
            'intent' => $reordered,
            'binding' => $value['binding'],
            'leaseOrigin' => null,
        ]);
        self::assertSame($frame->intent()->toArray(), $restored->intent()->toArray());

        $detached = $frame->intent()->toArray();
        $detached['intentId'] = 'caller-mutated';
        self::assertSame('intent-001', $frame->intent()->toArray()['intentId']);
    }

    public function testUnsafeScalarTypesAndControlTextFailClosed(): void
    {
        $value = AuditAnchorFixture::frame()->intent()->toArray();
        foreach ([
            ['intentId' => "bad\0id"],
            ['version' => 1.0],
            ['attempt' => true],
            ['updatedAt' => 20300102],
        ] as $mutation) {
            try {
                AuditAnchorIntent::fromArray([...$value, ...$mutation]);
                self::fail('unsafe intent scalar accepted');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testUtf8NfcByteBoundsAndNullableFieldsRemainTyped(): void
    {
        $value = AuditAnchorFixture::frame()->intent()->toArray();
        $nfc = str_repeat('a', 254) . "\u{00E9}";
        self::assertSame(256, strlen($nfc));
        $value['intentId'] = $nfc;
        $value['idempotencyKeyHash'] = AuditAnchorCanonical::idempotencyKeyHash($nfc, 'raw-idempotency-key');
        $value['stateHash'] = AuditAnchorCanonical::stateHash($value);
        self::assertSame($nfc, AuditAnchorIntent::fromArray($value)->toArray()['intentId']);

        $tooLong = str_repeat('a', 255) . "\u{00E9}";
        self::assertSame(257, strlen($tooLong));
        $value['intentId'] = $tooLong;
        $value['idempotencyKeyHash'] = AuditAnchorCanonical::idempotencyKeyHash($tooLong, 'raw-idempotency-key');
        $value['stateHash'] = AuditAnchorCanonical::stateHash($value);
        try {
            AuditAnchorIntent::fromArray($value);
            self::fail('257-byte UTF-8 field accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
        }

        $base = AuditAnchorFixture::frame()->toArray();
        $request = [
            'intentId' => "e\u{0301}",
            'partitionId' => $base['intent']['partitionId'],
            'checkpointId' => $base['intent']['checkpointId'],
            'checkpointHash' => $base['intent']['checkpointHash'],
            'checkpointBytesHash' => $base['intent']['checkpointBytesHash'],
            'anchorStoreRef' => $base['intent']['anchorStoreRef'],
            'anchorObjectRef' => $base['intent']['anchorObjectRef'],
            'expectedPredecessorVersion' => null,
            'expectedPredecessorHash' => null,
            'conditionalCreateToken' => $base['intent']['conditionalCreateToken'],
        ];
        $binding = AuditAnchorBinding::fromArray($base['binding']);
        try {
            AuditAnchorFrame::create(
                $request,
                $binding,
                'raw-idempotency-key',
                new AuditAnchorExpectation(1, str_repeat('a', 64), null),
                AuditAnchorFixture::NOW,
            );
            self::fail('non-NFC field accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $request['intentId'] = "\u{00E9}";
        try {
            AuditAnchorFrame::create(
                $request,
                $binding,
                'raw-idempotency-key',
                new AuditAnchorExpectation(1, str_repeat('a', 64), null),
                AuditAnchorFixture::NOW,
            );
            self::fail('non-genesis expectation admitted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(
            "\u{00E9}",
            AuditAnchorFrame::create(
                $request,
                $binding,
                'raw-idempotency-key',
                new AuditAnchorExpectation(0, null, null),
                AuditAnchorFixture::NOW,
            )
                ->intent()
                ->toArray()['intentId'],
        );

        $nullable = AuditAnchorFixture::frame()->intent()->toArray();
        self::assertNull($nullable['expectedPredecessorVersion']);
        self::assertNull($nullable['expectedPredecessorHash']);
        self::assertNull($nullable['acceptedObjectVersion']);
        self::assertNull(AuditAnchorIntent::fromArray($nullable)->toArray()['acceptedObjectHash']);
        foreach ([
            ['acceptedObjectVersion' => '1'],
            ['acceptedObjectHash' => false],
            ['acceptedAnchoredAt' => false],
        ] as $mutation) {
            $invalid = [...$nullable, ...$mutation];
            $invalid['stateHash'] = AuditAnchorCanonical::stateHash($invalid);
            try {
                AuditAnchorIntent::fromArray($invalid);
                self::fail('invalid nullable field accepted');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
            }
        }
    }

    public function testVersionCapacityFailureReturnsNoRevision(): void
    {
        $value = AuditAnchorFixture::frame()->toArray();
        $value['intent']['version'] = AuditJcsCodec::MAX_SAFE_INTEGER;
        $value['intent']['previousStateHash'] = str_repeat('e', 64);
        $value['intent']['stateHash'] = AuditAnchorCanonical::stateHash($value['intent']);
        $frame = AuditAnchorFrame::restore($value);
        $before = $frame->toArray();
        try {
            new AuditAnchorClaims(new \BlackOps\Internal\Audit\Anchor\AuditAnchorConfiguration())->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'capacity-token',
                AuditAnchorFixture::NOW,
            );
            self::fail('safe integer capacity accepted a revision');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame($before, $frame->toArray());
    }
}

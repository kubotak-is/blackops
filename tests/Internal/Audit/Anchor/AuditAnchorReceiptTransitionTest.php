<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Anchor\AuditAnchorCanonical;
use BlackOps\Internal\Audit\Anchor\AuditAnchorClaims;
use BlackOps\Internal\Audit\Anchor\AuditAnchorConfiguration;
use BlackOps\Internal\Audit\Anchor\AuditAnchorException;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFailure;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFrame;
use BlackOps\Internal\Audit\Anchor\AuditAnchorIntent;
use BlackOps\Internal\Audit\Anchor\AuditAnchorPostCallTransition;
use BlackOps\Internal\Audit\Anchor\AuditAnchorPreCallTransition;
use BlackOps\Internal\Audit\Anchor\AuditAnchorReceiptBody;
use BlackOps\Internal\Audit\Anchor\AuditAnchorReceiptTransition;
use BlackOps\Internal\Audit\AuditJcsCodec;
use PHPUnit\Framework\TestCase;

final class AuditAnchorReceiptTransitionTest extends TestCase
{
    public function testReceiptInputPersistReceiptPersistAndAcknowledgementSequence(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $receipt = new AuditAnchorReceiptTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token-1',
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
        $frame = $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('accepted'),
            AuditAnchorFixture::NOW,
        );
        $frame = $receipt->inputClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'input-token',
            AuditAnchorFixture::LATER,
        );
        self::assertSame('receipt_input_claim', $frame->leaseOrigin()?->toArray()['operation']);
        $frame = $receipt->inputPersist(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'signing-key',
            AuditAnchorFixture::LATER,
        );
        $value = $frame->intent()->toArray();
        self::assertSame('receipt_inputs_persisted', $value['state']);
        $golden = AuditAnchorFixture::golden();
        $receiptBody = AuditAnchorReceiptBody::build($frame->intent(), 'signing-key', AuditAnchorFixture::LATER);
        self::assertSame($golden['hashes']['receiptPreimageHash'], $receiptBody['receiptPreimageHash']);
        self::assertSame($golden['hashes']['receiptBytesHash'], $receiptBody['receiptBytesHash']);
        self::assertSame($golden['hashes']['publicReceiptHash'], $receiptBody['anchorReceiptHash']);
        self::assertSame(
            hex2bin($golden['hashes']['receiptPreimageBodyBytesHex']),
            AuditAnchorCanonical::jcs($receiptBody['body']),
        );
        self::assertSame(
            hex2bin($golden['hashes']['receiptBytesBodyBytesHex']),
            AuditAnchorCanonical::jcs($receiptBody['body']),
        );
        $mutated = $value;
        $mutated['receiptPreimageHash'] = str_repeat('d', 64);
        $mutated['stateHash'] = AuditAnchorCanonical::stateHash($mutated);
        try {
            AuditAnchorIntent::fromArray($mutated);
            self::fail('receipt preimage substitution accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
        }
        $frame = $receipt->persistClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'persist-token',
            AuditAnchorFixture::LATER,
        );
        self::assertSame('receipt_persist_claim', $frame->leaseOrigin()?->toArray()['operation']);
        $frame = $receipt->persist(
            $frame,
            AuditAnchorFixture::expectation($frame),
            $this->receiptHash($frame),
            AuditAnchorFixture::LATER,
        );
        $persistedValue = $frame->toArray();
        $persistedValue['intent']['anchorReceiptHash'] = str_repeat('d', 64);
        $persistedValue['intent']['stateHash'] = AuditAnchorCanonical::stateHash($persistedValue['intent']);
        try {
            AuditAnchorFrame::restore($persistedValue);
            self::fail('recomputed state hash admitted a substituted receipt hash');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
        }
        try {
            AuditAnchorIntent::fromArray($persistedValue['intent']);
            self::fail('recomputed state hash admitted a substituted public receipt hash');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        $frame = $receipt->acknowledgementClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'ack-token',
            AuditAnchorFixture::LATER,
        );
        self::assertSame('acknowledgement_claim', $frame->leaseOrigin()?->toArray()['operation']);
        $frame = $receipt->acknowledge($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::LATER);
        self::assertSame('acknowledged', $frame->intent()->toArray()['state']);
    }

    public function testExpiredInternalClaimReclaimRequiresAFreshClaim(): void
    {
        $configuration = new AuditAnchorConfiguration(1, 1);
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $receipt = new AuditAnchorReceiptTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token',
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
        $frame = $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('accepted'),
            AuditAnchorFixture::NOW,
        );
        $frame = $receipt->inputClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'input-token',
            AuditAnchorFixture::NOW,
        );
        $expiredAt = $frame->intent()->toArray()['leaseExpiresAt'];
        $frame = $receipt->reclaim($frame, AuditAnchorFixture::expectation($frame), $expiredAt);
        self::assertNull($frame->intent()->toArray()['leaseFencingToken']);
        try {
            $receipt->inputPersist($frame, AuditAnchorFixture::expectation($frame), 'signing-key', $expiredAt);
            self::fail('reclaimed internal lease persisted without a fresh claim');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
        }
    }

    public function testReceiptInputRollbackRejectsBeforeReceiptBodyConstruction(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $receipt = new AuditAnchorReceiptTransition($configuration);
        $frame = $this->acceptedFrame($claims, $pre, $post);
        $frame = $receipt->inputClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'input-token',
            AuditAnchorFixture::LATER,
        );
        $before = $frame->toArray();
        try {
            $receipt->inputPersist(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'signing-key',
                AuditAnchorFixture::NOW,
            );
            self::fail('receipt input persisted after a trusted-clock rollback');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame($before, $frame->toArray());
    }

    public function testEveryInternalReceiptStateReclaimsWithoutLosingPersistedInputs(): void
    {
        $configuration = new AuditAnchorConfiguration(1, 1);
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $receipt = new AuditAnchorReceiptTransition($configuration);
        $frame = $this->acceptedFrame($claims, $pre, $post);

        $frame = $receipt->inputClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'input-token',
            AuditAnchorFixture::LATER,
        );
        $expired = $frame->intent()->toArray()['leaseExpiresAt'];
        $frame = $receipt->reclaim($frame, AuditAnchorFixture::expectation($frame), $expired);
        self::assertSame('externally_accepted', $frame->intent()->toArray()['state']);

        $frame = $receipt->inputClaim($frame, AuditAnchorFixture::expectation($frame), 'input-token-fresh', $expired);
        $frame = $receipt->inputPersist($frame, AuditAnchorFixture::expectation($frame), 'signing-key', $expired);
        self::assertSame('receipt_inputs_persisted', $frame->intent()->toArray()['state']);
        $frame = $receipt->persistClaim($frame, AuditAnchorFixture::expectation($frame), 'persist-token', $expired);
        $expired = $frame->intent()->toArray()['leaseExpiresAt'];
        $frame = $receipt->reclaim($frame, AuditAnchorFixture::expectation($frame), $expired);
        self::assertSame('receipt_inputs_persisted', $frame->intent()->toArray()['state']);
        self::assertNotNull($frame->intent()->toArray()['receiptPreimageHash']);

        $frame = $receipt->persistClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'persist-token-fresh',
            $expired,
        );
        $frame = $receipt->persist(
            $frame,
            AuditAnchorFixture::expectation($frame),
            $this->receiptHash($frame),
            $expired,
        );
        self::assertSame('receipt_persisted', $frame->intent()->toArray()['state']);
        $frame = $receipt->acknowledgementClaim($frame, AuditAnchorFixture::expectation($frame), 'ack-token', $expired);
        $expired = $frame->intent()->toArray()['leaseExpiresAt'];
        $frame = $receipt->reclaim($frame, AuditAnchorFixture::expectation($frame), $expired);
        self::assertSame('receipt_persisted', $frame->intent()->toArray()['state']);
        self::assertNotNull($frame->intent()->toArray()['anchorReceiptHash']);
        $frame = $receipt->acknowledgementClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'ack-token-fresh',
            $expired,
        );
        $frame = $receipt->acknowledge($frame, AuditAnchorFixture::expectation($frame), $expired);
        self::assertSame('acknowledged', $frame->intent()->toArray()['state']);
        self::assertSame($frame->intent()->toArray()['updatedAt'], $frame->intent()->toArray()['acknowledgedAt']);
    }

    public function testReceiptBodyAndSettlementSubstitutionsFailClosed(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $receipt = new AuditAnchorReceiptTransition($configuration);
        $frame = $this->acceptedFrame($claims, $pre, $post);
        $frame = $receipt->inputClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'input-token',
            AuditAnchorFixture::LATER,
        );
        $frame = $receipt->inputPersist(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'signing-key',
            AuditAnchorFixture::LATER,
        );
        $value = $frame->toArray();
        foreach ([
            ['receiptSigningKeyRef' => 'other-key'],
            ['receiptSignedAt' => '2030-01-02T03:11:05.123456Z'],
            ['receiptPreimageHash' => str_repeat('d', 64)],
            ['receiptBytesHash' => str_repeat('e', 64)],
        ] as $mutation) {
            $mutated = $value;
            $mutated['intent'] = [...$mutated['intent'], ...$mutation];
            $mutated['intent']['stateHash'] = AuditAnchorCanonical::stateHash($mutated['intent']);
            try {
                \BlackOps\Internal\Audit\Anchor\AuditAnchorFrame::restore($mutated);
                self::fail('receipt substitution accepted');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
            }
        }
        $frame = $receipt->persistClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'persist-token',
            AuditAnchorFixture::LATER,
        );
        $before = $frame->toArray();
        try {
            $receipt->persist(
                $frame,
                AuditAnchorFixture::expectation($frame),
                str_repeat('f', 64),
                AuditAnchorFixture::LATER,
            );
            self::fail('wrong public receipt hash accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
        }
        self::assertSame($before, $frame->toArray());
    }

    public function testPersistChecksPublicReceiptHashBeforeCapacityFailure(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $receipt = new AuditAnchorReceiptTransition($configuration);
        $frame = $this->acceptedFrame($claims, $pre, $post);
        $frame = $receipt->inputClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'input-token',
            AuditAnchorFixture::LATER,
        );
        $frame = $receipt->inputPersist(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'signing-key',
            AuditAnchorFixture::LATER,
        );
        $value = $frame->toArray();
        $value['intent']['version'] = AuditJcsCodec::MAX_SAFE_INTEGER - 1;
        $value['intent']['previousStateHash'] = $value['intent']['stateHash'];
        $value['intent']['stateHash'] = AuditAnchorCanonical::stateHash($value['intent']);
        $frame = AuditAnchorFrame::restore($value);
        $frame = $receipt->persistClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'persist-token',
            AuditAnchorFixture::LATER,
        );
        $expected = AuditAnchorFixture::expectation($frame);
        $before = $frame->toArray();

        try {
            $receipt->persist($frame, $expected, str_repeat('f', 64), AuditAnchorFixture::LATER);
            self::fail('wrong public receipt hash reached capacity failure');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame($before, $frame->toArray());

        try {
            $receipt->persist($frame, $expected, $this->receiptHash($frame), AuditAnchorFixture::LATER);
            self::fail('valid public receipt hash advanced at safe integer capacity');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame($before, $frame->toArray());
    }

    public function testInternalSettlementCannotUseAnotherClaimPurpose(): void
    {
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $receipt = new AuditAnchorReceiptTransition($configuration);
        $frame = $this->acceptedFrame($claims, $pre, $post);
        $frame = $receipt->inputClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'input-token',
            AuditAnchorFixture::LATER,
        );
        try {
            $receipt->persistClaim(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'wrong-purpose-token',
                AuditAnchorFixture::LATER,
            );
            self::fail('receipt persist claim crossed input claim purpose');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
        }
    }

    private function acceptedFrame(
        AuditAnchorClaims $claims,
        AuditAnchorPreCallTransition $pre,
        AuditAnchorPostCallTransition $post,
    ): \BlackOps\Internal\Audit\Anchor\AuditAnchorFrame {
        $frame = AuditAnchorFixture::frame();
        $frame = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token',
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        $frame = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
        return $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('accepted'),
            AuditAnchorFixture::NOW,
        );
    }

    private function receiptHash(\BlackOps\Internal\Audit\Anchor\AuditAnchorFrame $frame): string
    {
        $value = $frame->intent()->toArray();
        $body = [
            'schemaVersion' => 1,
            'kind' => 'audit_anchor_receipt',
            'anchorReceiptId' => $value['anchorReceiptId'],
            'partitionId' => $value['partitionId'],
            'segmentId' => $value['segmentId'],
            'checkpointId' => $value['checkpointId'],
            'checkpointHash' => $value['checkpointHash'],
            'checkpointBytesHash' => $value['checkpointBytesHash'],
            'anchorStoreRef' => $value['anchorStoreRef'],
            'anchorObjectRef' => $value['anchorObjectRef'],
            'anchorObjectVersion' => $value['acceptedObjectVersion'],
            'previousAnchorReceiptHash' => $value['previousAnchorReceiptHash'],
            'anchoredAt' => $value['acceptedAnchoredAt'],
            'signedAt' => $value['receiptSignedAt'],
            'signatureAlgorithm' => 'ed25519-v1',
            'signingKeyRef' => $value['receiptSigningKeyRef'],
        ];
        return \BlackOps\Internal\Audit\Artifact\AuditAnchorReceiptCodec::signingInput($body)['hash'];
    }
}

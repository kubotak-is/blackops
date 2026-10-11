<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Anchor\AuditAnchorBinding;
use BlackOps\Internal\Audit\Anchor\AuditAnchorCanonical;
use BlackOps\Internal\Audit\Anchor\AuditAnchorClaims;
use BlackOps\Internal\Audit\Anchor\AuditAnchorConfiguration;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFrame;
use BlackOps\Internal\Audit\Anchor\AuditAnchorPostCallTransition;
use BlackOps\Internal\Audit\Anchor\AuditAnchorPreCallTransition;
use BlackOps\Internal\Audit\Anchor\AuditAnchorReceiptBody;
use BlackOps\Internal\Audit\Anchor\AuditAnchorReceiptTransition;
use PHPUnit\Framework\TestCase;

final class AuditAnchorCanonicalTest extends TestCase
{
    public function testStateAndIdempotencyDomainsAreDeterministicAndDistinct(): void
    {
        $value = AuditAnchorFixture::frame()->intent()->toArray();
        self::assertSame(AuditAnchorCanonical::stateHash($value), $value['stateHash']);
        self::assertSame(
            AuditAnchorCanonical::idempotencyKeyHash('intent-001', 'raw-idempotency-key'),
            $value['idempotencyKeyHash'],
        );
        self::assertNotSame(
            AuditAnchorCanonical::receiptPreimageHash(['value' => 'body']),
            AuditAnchorCanonical::receiptBytesHash(['value' => 'body']),
        );
    }

    public function testIndependentNodeFixtureMatchesStateReceiptAndScheduleBytes(): void
    {
        $path = __DIR__ . '/../../../Fixtures/Audit/audit-anchor-state-v1.json';
        $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        self::assertSame(
            $fixture['hashes']['receiptPreimageHash'],
            AuditAnchorCanonical::receiptPreimageHash($fixture['receiptBody']),
        );
        self::assertSame(
            $fixture['hashes']['receiptBytesHash'],
            AuditAnchorCanonical::receiptBytesHash($fixture['receiptBody']),
        );
        $preimageBytes = hex2bin($fixture['hashes']['receiptPreimageBytesHex']);
        $bytesPreimage = hex2bin($fixture['hashes']['receiptBytesPreimageHex']);
        $receiptBodyBytes = hex2bin($fixture['hashes']['receiptPreimageBodyBytesHex']);
        self::assertIsString($preimageBytes);
        self::assertIsString($bytesPreimage);
        self::assertIsString($receiptBodyBytes);
        self::assertSame($fixture['hashes']['receiptPreimageHash'], hash('sha256', $preimageBytes));
        self::assertSame($fixture['hashes']['receiptBytesHash'], hash('sha256', $bytesPreimage));
        self::assertNotSame($fixture['hashes']['receiptPreimageHash'], hash(
            'sha256',
            \BlackOps\Internal\Audit\AuditLengthPrefix::encode('blackops.audit.anchor.intent.receipt-preimage.v1')
                . \BlackOps\Internal\Audit\AuditLengthPrefix::encode($receiptBodyBytes),
        ));
        self::assertSame($fixture['intent']['stateHash'], AuditAnchorCanonical::stateHash($fixture['intent']));
        self::assertSame('2030-01-02T03:09:05.123456Z', $fixture['schedule']['leaseExpiry']);
        foreach ($fixture['trace']['branches'] as $name => $branch) {
            self::assertSame($branch['scheduledAt'], $fixture['schedule']['branchSchedules'][$name]['300']);
            foreach ($branch['transitions'] as $transition) {
                self::assertSame($transition['fromVersion'] + 1, $transition['toVersion']);
                $successor = json_decode($transition['successorBytes'], true, 512, JSON_THROW_ON_ERROR);
                self::assertSame($transition['successorStateHash'], $successor['stateHash']);
                self::assertSame($transition['successorStateHash'], AuditAnchorCanonical::stateHash($successor));
                self::assertSame($transition['previousStateHash'], $successor['previousStateHash']);
                self::assertSame($transition['updatedAt'], $successor['updatedAt']);
            }
        }
        $complete = $fixture['trace']['completeSequence'];
        self::assertSame(
            [
                'external_submission_claim',
                'lookup_absent',
                'call_start',
                'result_accepted',
                'receipt_input_claim',
                'receipt_input_persist',
                'receipt_persist_claim',
                'receipt_persist',
                'acknowledgement_claim',
                'acknowledge',
            ],
            array_column($complete, 'operation'),
        );
        $previousVersion = $fixture['intent']['version'];
        $previousStateHash = $fixture['intent']['stateHash'];
        foreach ($complete as $transition) {
            self::assertSame($previousVersion, $transition['fromVersion']);
            self::assertSame($previousVersion + 1, $transition['toVersion']);
            self::assertSame($previousStateHash, $transition['previousStateHash']);
            $successor = json_decode($transition['successorBytes'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($transition['toVersion'], $successor['version']);
            self::assertSame($transition['successorStateHash'], $successor['stateHash']);
            self::assertSame($transition['successorStateHash'], AuditAnchorCanonical::stateHash($successor));
            self::assertSame($transition['updatedAt'], $successor['updatedAt']);
            $previousVersion = $successor['version'];
            $previousStateHash = $successor['stateHash'];
        }
        self::assertSame('acknowledged', $successor['state']);
        self::assertSame($successor['updatedAt'], $successor['acknowledgedAt']);
        self::assertSame(
            $fixture['hashes']['publicReceiptHash'],
            \BlackOps\Internal\Audit\Artifact\AuditAnchorReceiptCodec::signingInput($fixture['receiptBody'])['hash'],
        );
    }

    public function testActualCompleteSequenceMatchesIndependentNodeSuccessorVectors(): void
    {
        $fixture = AuditAnchorFixture::golden();
        $expected = $fixture['trace']['completeSequence'];
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $receipt = new AuditAnchorReceiptTransition($configuration);
        $frame = AuditAnchorFixture::frame();
        $index = 0;
        $advance = static function (
            string $operation,
            AuditAnchorFrame $before,
            AuditAnchorFrame $after,
            string $now,
        ) use (&$index, $expected): void {
            $vector = $expected[$index++];
            $value = $after->intent()->toArray();
            self::assertSame($operation, $vector['operation']);
            self::assertSame($value['version'] - 1, $vector['fromVersion']);
            self::assertSame($value['version'], $vector['toVersion']);
            self::assertSame($before->intent()->toArray()['stateHash'], $vector['previousStateHash']);
            self::assertSame($now, $vector['updatedAt']);
            self::assertSame($value['stateHash'], $vector['successorStateHash']);
            self::assertSame($vector['scheduledAt'], $value['nextAvailableAt']);
            self::assertSame($vector['successorBytes'], AuditAnchorCanonical::jcs($value));
        };

        $next = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'external-token-1',
            AuditAnchorFixture::NOW,
        );
        $advance('external_submission_claim', $frame, $next, AuditAnchorFixture::NOW);
        $frame = $next;
        $next = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::reconciliation('external_object_absent'),
            AuditAnchorFixture::NOW,
        );
        $advance('lookup_absent', $frame, $next, AuditAnchorFixture::NOW);
        $frame = $next;
        $next = $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
        $advance('call_start', $frame, $next, AuditAnchorFixture::NOW);
        $frame = $next;
        $next = $post->result(
            $frame,
            AuditAnchorFixture::expectation($frame),
            AuditAnchorFixture::result('accepted'),
            AuditAnchorFixture::NOW,
        );
        $advance('result_accepted', $frame, $next, AuditAnchorFixture::NOW);
        $frame = $next;
        $next = $receipt->inputClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'input-token',
            AuditAnchorFixture::LATER,
        );
        $advance('receipt_input_claim', $frame, $next, AuditAnchorFixture::LATER);
        $frame = $next;
        $next = $receipt->inputPersist(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'signing-key',
            AuditAnchorFixture::LATER,
        );
        $advance('receipt_input_persist', $frame, $next, AuditAnchorFixture::LATER);
        $frame = $next;
        $next = $receipt->persistClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'persist-token',
            AuditAnchorFixture::LATER,
        );
        $advance('receipt_persist_claim', $frame, $next, AuditAnchorFixture::LATER);
        $frame = $next;
        $receiptValue = AuditAnchorReceiptBody::build($frame->intent(), 'signing-key', AuditAnchorFixture::LATER);
        $next = $receipt->persist(
            $frame,
            AuditAnchorFixture::expectation($frame),
            $receiptValue['anchorReceiptHash'],
            AuditAnchorFixture::LATER,
        );
        $advance('receipt_persist', $frame, $next, AuditAnchorFixture::LATER);
        $frame = $next;
        $next = $receipt->acknowledgementClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'ack-token',
            AuditAnchorFixture::LATER,
        );
        $advance('acknowledgement_claim', $frame, $next, AuditAnchorFixture::LATER);
        $frame = $next;
        $next = $receipt->acknowledge($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::LATER);
        $advance('acknowledge', $frame, $next, AuditAnchorFixture::LATER);
        self::assertCount($index, $expected);
    }

    public function testActualLookupFoundSequenceMatchesIndependentNodeSuccessorVectors(): void
    {
        $fixture = AuditAnchorFixture::golden();
        $lookup = $fixture['trace']['foundLookup'];
        $expected = $fixture['trace']['foundSequence'];
        $request = $lookup['request'];
        $binding = AuditAnchorBinding::fromArray($lookup['binding']);
        self::assertSame($lookup['binding'], $binding->toArray());
        self::assertSame(7, $request['expectedPredecessorVersion']);
        self::assertSame(8, $lookup['reconciliation']['foundObject']['anchorObjectVersion']);
        self::assertNotSame($request['expectedPredecessorHash'], $binding->toArray()['previousAnchorReceiptHash']);
        $frame = AuditAnchorFrame::create(
            $request,
            $binding,
            'raw-idempotency-key',
            new \BlackOps\Internal\Audit\Anchor\AuditAnchorExpectation(0, null, null),
            AuditAnchorFixture::NOW,
        );
        self::assertSame($request, $lookup['request']);
        self::assertSame($binding->toArray(), $frame->binding()->toArray());
        $configuration = new AuditAnchorConfiguration();
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $receipt = new AuditAnchorReceiptTransition($configuration);
        $index = 0;
        $advance = static function (
            string $operation,
            AuditAnchorFrame $before,
            AuditAnchorFrame $after,
            string $now,
        ) use (&$index, $expected): void {
            $vector = $expected[$index++];
            $value = $after->intent()->toArray();
            self::assertSame($operation, $vector['operation']);
            self::assertSame($value['version'] - 1, $vector['fromVersion']);
            self::assertSame($value['version'], $vector['toVersion']);
            self::assertSame($before->intent()->toArray()['stateHash'], $vector['previousStateHash']);
            self::assertSame($now, $vector['updatedAt']);
            self::assertSame($value['stateHash'], $vector['successorStateHash']);
            self::assertSame($vector['scheduledAt'], $value['nextAvailableAt']);
            self::assertSame($vector['successorBytes'], AuditAnchorCanonical::jcs($value));
        };

        $next = $claims->externalSubmission(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'found-external-token',
            AuditAnchorFixture::NOW,
        );
        $advance('external_submission_claim', $frame, $next, AuditAnchorFixture::NOW);
        $frame = $next;
        $next = $pre->lookup(
            $frame,
            AuditAnchorFixture::expectation($frame),
            $lookup['reconciliation'],
            AuditAnchorFixture::NOW,
        );
        $advance('lookup_found', $frame, $next, AuditAnchorFixture::NOW);
        self::assertSame(
            $lookup['reconciliation']['foundObject']['anchorObjectVersion'],
            $next->intent()->toArray()['acceptedObjectVersion'],
        );
        self::assertSame(
            $lookup['reconciliation']['foundObject']['acceptedObjectHash'],
            $next->intent()->toArray()['acceptedObjectHash'],
        );
        self::assertSame(
            $lookup['reconciliation']['foundObject']['acceptedAnchoredAt'],
            $next->intent()->toArray()['acceptedAnchoredAt'],
        );
        self::assertSame($lookup['binding']['anchorReceiptId'], $next->intent()->toArray()['anchorReceiptId']);
        self::assertSame($lookup['binding']['segmentId'], $next->intent()->toArray()['segmentId']);
        self::assertSame(
            $lookup['binding']['previousAnchorReceiptHash'],
            $next->intent()->toArray()['previousAnchorReceiptHash'],
        );
        $frame = $next;
        $next = $receipt->inputClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'found-input-token',
            AuditAnchorFixture::LATER,
        );
        $advance('receipt_input_claim', $frame, $next, AuditAnchorFixture::LATER);
        $frame = $next;
        $next = $receipt->inputPersist(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'signing-key',
            AuditAnchorFixture::LATER,
        );
        $advance('receipt_input_persist', $frame, $next, AuditAnchorFixture::LATER);
        $frame = $next;
        $next = $receipt->persistClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'found-persist-token',
            AuditAnchorFixture::LATER,
        );
        $advance('receipt_persist_claim', $frame, $next, AuditAnchorFixture::LATER);
        $frame = $next;
        $receiptValue = AuditAnchorReceiptBody::build($frame->intent(), 'signing-key', AuditAnchorFixture::LATER);
        self::assertSame($fixture['trace']['foundReceiptBody'], $receiptValue['body']);
        $foundBodyBytes = hex2bin($fixture['trace']['foundHashes']['receiptPreimageBodyBytesHex']);
        self::assertIsString($foundBodyBytes);
        self::assertSame($foundBodyBytes, $receiptValue['bytes']);
        self::assertSame($fixture['trace']['foundHashes']['receiptPreimageHash'], $receiptValue['receiptPreimageHash']);
        self::assertSame($fixture['trace']['foundHashes']['receiptBytesHash'], $receiptValue['receiptBytesHash']);
        self::assertSame($fixture['trace']['foundHashes']['publicReceiptHash'], $receiptValue['anchorReceiptHash']);
        $next = $receipt->persist(
            $frame,
            AuditAnchorFixture::expectation($frame),
            $receiptValue['anchorReceiptHash'],
            AuditAnchorFixture::LATER,
        );
        $advance('receipt_persist', $frame, $next, AuditAnchorFixture::LATER);
        $frame = $next;
        $next = $receipt->acknowledgementClaim(
            $frame,
            AuditAnchorFixture::expectation($frame),
            'found-ack-token',
            AuditAnchorFixture::LATER,
        );
        $advance('acknowledgement_claim', $frame, $next, AuditAnchorFixture::LATER);
        $frame = $next;
        $next = $receipt->acknowledge($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::LATER);
        $advance('acknowledge', $frame, $next, AuditAnchorFixture::LATER);
        self::assertCount($index, $expected);
        self::assertSame('acknowledged', $next->intent()->toArray()['state']);
    }
}

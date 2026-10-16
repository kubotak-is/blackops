<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Authority\History;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Authority\History\AuditHistoryCheckpointTag;
use BlackOps\Internal\Audit\Authority\History\AuditHistoryControlTag;
use BlackOps\Internal\Audit\Authority\History\AuditHistoryGroupCodec;
use BlackOps\Internal\Audit\Authority\History\AuditHistoryNativeGroup;
use BlackOps\Internal\Audit\Authority\History\AuditHistoryRecord;
use BlackOps\Internal\Audit\Authority\History\AuditHistoryScalar;
use BlackOps\Internal\Audit\Authority\History\AuditHistoryTag;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuditHistoryGroupCodecTest extends TestCase
{
    public function testEveryNativeTagUsesAnExplicitCanonicalVector(): void
    {
        $vectors = self::nativeVectors();
        self::assertCount(31, $vectors);
        self::assertSame(
            [
                1,
                3,
                5,
                16,
                17,
                18,
                19,
                21,
                23,
                24,
                25,
                26,
                28,
                31,
                33,
                35,
                48,
                49,
                51,
                52,
                53,
                55,
                64,
                65,
                67,
                68,
                72,
                73,
                75,
                76,
                79,
            ],
            array_map(static fn(array $vector): int => $vector[0]->value, array_values($vectors)),
        );

        $codec = new AuditHistoryGroupCodec();
        foreach ($vectors as [$tag, $scalars, $expected]) {
            $group = AuditHistoryNativeGroup::fromNative($tag, $scalars);

            self::assertSame($expected, $codec->encode($group), $tag->name);

            $decoded = $codec->decode($tag, $expected);
            self::assertSame($tag, $decoded->tag(), $tag->name);
            $expectedScalars = $scalars;
            $actualScalars = $decoded->scalars();
            ksort($expectedScalars);
            ksort($actualScalars);
            self::assertSame($expectedScalars, $actualScalars, $tag->name);
        }
    }

    public function testNineteenNonGroupTagsAreRejectedBeforeDecoding(): void
    {
        $tags = [
            AuditHistoryTag::AcceptedAuthorityBytes,
            AuditHistoryTag::FloorHint,
            AuditHistoryTag::EmptyInstalledSource,
            AuditHistoryTag::PublishedSegmentEnvelope,
            AuditHistoryTag::StagedSegmentEnvelope,
            AuditHistoryTag::Trailer,
            AuditHistoryCheckpointTag::NoPredecessorSegment,
            AuditHistoryCheckpointTag::NoPreviousRecordEndpoint,
            AuditHistoryCheckpointTag::ReservationEnvelope,
            AuditHistoryCheckpointTag::WorkStepEnvelope,
            AuditHistoryCheckpointTag::CompleteCandidateBytes,
            AuditHistoryCheckpointTag::PreparedUnsignedBytes,
            AuditHistoryCheckpointTag::PersistedCompleteBytes,
            AuditHistoryControlTag::TransitionEnvelope,
            AuditHistoryControlTag::TransitionAckEnvelope,
            AuditHistoryControlTag::NoTransitionAcknowledgement,
            AuditHistoryControlTag::PublicationDocument,
            AuditHistoryControlTag::CandidatePublicationDocument,
            AuditHistoryControlTag::NoNextPublication,
        ];
        self::assertCount(19, $tags);

        $codec = new AuditHistoryGroupCodec();
        foreach ($tags as $tag) {
            $this->assertInvalid(
                static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative($tag, []),
                'History native group is invalid.',
            );
            $this->assertInvalid(
                static fn(): AuditHistoryNativeGroup => $codec->decode($tag, '{}'),
                'History tag does not carry a native group.',
            );
        }

        foreach ([
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(\BlackOps\Core\Attribute\SensitiveMode::Omit, []),
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromDecoded(\BlackOps\Core\Attribute\SensitiveMode::Omit, []),
            static fn(): AuditHistoryNativeGroup => $codec->decode(\BlackOps\Core\Attribute\SensitiveMode::Omit, '{}'),
        ] as $foreignTagCall) {
            try {
                $foreignTagCall();
                self::fail('Expected a foreign enum TypeError.');
            } catch (\TypeError) {
                continue;
            }
        }
    }

    public function testCheckpointEndMatrixIncludesBothRecoveryRows(): void
    {
        $vectors = self::nativeVectors();
        $base = $vectors['CheckpointEnd'][1];
        $h0 = str_repeat('0', 64);
        $h1 = str_repeat('1', 64);
        $h2 = str_repeat('2', 64);
        $checkpointId = '01941f29-7c00-7000-8000-000000000102';
        $rows = [
            [false, 0, false, false, false],
            [true,  0, false, false, false],
            [true,  0, true,  false, false],
            [true,  1, true,  false, false],
            [true,  2, true,  true,  false],
            [true,  2, true,  true,  true],
            [true,  3, true,  true,  true],
            [true,  4, true,  true,  true],
        ];

        foreach ($rows as [$work, $ordinal, $preparation, $candidate, $complete]) {
            $end = $base;
            $end['work_id'] = $work ? '01941f29-7c00-7000-8000-000000000103' : null;
            $end['reservation_digest'] = $work ? $h0 : null;
            $end['last_ordinal'] = $ordinal;
            $end['last_step_digest'] = $ordinal === 0 ? null : $h2;
            $end['preparation_checkpoint_id'] = $preparation ? $checkpointId : null;
            $end['unsigned_sha256'] = $preparation ? $h0 : null;
            $end['unsigned_length'] = $preparation ? 1 : null;
            $end['candidate_sha256'] = $candidate ? $h1 : null;
            $end['candidate_length'] = $candidate ? 1 : null;
            $end['complete_sha256'] = $complete ? $h2 : null;
            $end['complete_length'] = $complete ? 1 : null;

            $this->assertValidGroup(AuditHistoryCheckpointTag::CheckpointEnd, $end);
        }

        $invalid = $base;
        $invalid['last_ordinal'] = 1;
        $invalid['last_step_digest'] = $h2;
        $invalid['preparation_checkpoint_id'] = null;
        $invalid['unsigned_sha256'] = null;
        $invalid['unsigned_length'] = null;
        $invalid['candidate_sha256'] = null;
        $invalid['candidate_length'] = null;
        $invalid['complete_sha256'] = null;
        $invalid['complete_length'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $invalid);

        $invalid = $base;
        $invalid['last_ordinal'] = 2;
        $invalid['last_step_digest'] = $h2;
        $invalid['candidate_sha256'] = null;
        $invalid['candidate_length'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $invalid);

        $invalid = $base;
        $invalid['last_ordinal'] = 3;
        $invalid['last_step_digest'] = $h2;
        $invalid['complete_sha256'] = null;
        $invalid['complete_length'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $invalid);
    }

    public function testInvalidRowsCrossAllAdmissionBoundaries(): void
    {
        $vectors = self::nativeVectors();
        $h1 = str_repeat('1', 64);
        $h2 = str_repeat('2', 64);

        $header = $vectors['Header'][1];
        $header['schemaVersion'] = 0;

        $headerDomain = $vectors['Header'][1];
        $headerDomain['segmentId'] = 'not-a-uuid';

        $stepDomain = $vectors['WorkStepNative'][1];
        $stepDomain['ordinal'] = 5;
        $stepDomain['previous_step_ordinal'] = 4;
        $stepDomain['previous_step_digest'] = $h1;
        $stepDomain['phase'] = 'ingress_acknowledged';

        $preparationVersion = $vectors['PreparationNative'][1];
        $preparationVersion['closed_state_version'] = 1;

        $segmentRelation = $vectors['TargetSegmentNative'][1];
        $segmentRelation['final_sequence'] = null;

        $partitionRelation = $vectors['PartitionNative'][1];
        $partitionRelation['last_sequence'] = 1;

        $contextRelation = $vectors['Header'][1];
        $contextRelation['segmentId'] = null;

        $transitionRelation = $vectors['TransitionNative'][1];
        $transitionRelation['cause'] = 'segment_advance';

        $end = $vectors['CheckpointEnd'][1];
        $workReservationPair = $end;
        $workReservationPair['reservation_digest'] = null;

        $preparationUnsignedPair = $end;
        $preparationUnsignedPair['unsigned_sha256'] = null;
        $preparationUnsignedPair['unsigned_length'] = null;

        $unsignedLengthPair = $end;
        $unsignedLengthPair['unsigned_length'] = null;

        $candidateAtZero = $end;
        $candidateAtZero['last_ordinal'] = 0;
        $candidateAtZero['last_step_digest'] = null;
        $candidateAtZero['complete_sha256'] = null;
        $candidateAtZero['complete_length'] = null;

        $candidateLengthPair = $end;
        $candidateLengthPair['last_ordinal'] = 2;
        $candidateLengthPair['last_step_digest'] = $h1;
        $candidateLengthPair['candidate_length'] = null;
        $candidateLengthPair['complete_sha256'] = null;
        $candidateLengthPair['complete_length'] = null;

        $completeLengthPair = $end;
        $completeLengthPair['complete_length'] = null;

        $completeAtFour = $end;
        $completeAtFour['last_ordinal'] = 4;
        $completeAtFour['last_step_digest'] = $h2;
        $completeAtFour['complete_sha256'] = null;
        $completeAtFour['complete_length'] = null;

        /** @var array<string,array{0:AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag,1:array<string,scalar|null>}> $rows */
        $rows = [
            'header-version' => [AuditHistoryTag::Header, $header],
            'header-domain' => [AuditHistoryTag::Header, $headerDomain],
            'work-domain' => [AuditHistoryCheckpointTag::WorkStepNative, $stepDomain],
            'preparation-version' => [AuditHistoryCheckpointTag::PreparationNative, $preparationVersion],
            'segment-relation' => [AuditHistoryCheckpointTag::TargetSegmentNative, $segmentRelation],
            'partition-relation' => [AuditHistoryCheckpointTag::PartitionNative, $partitionRelation],
            'context-relation' => [AuditHistoryTag::Header, $contextRelation],
            'transition-relation' => [AuditHistoryControlTag::TransitionNative, $transitionRelation],
            'work-reservation-pair' => [AuditHistoryCheckpointTag::CheckpointEnd, $workReservationPair],
            'preparation-unsigned-pair' => [AuditHistoryCheckpointTag::CheckpointEnd, $preparationUnsignedPair],
            'unsigned-length-pair' => [AuditHistoryCheckpointTag::CheckpointEnd, $unsignedLengthPair],
            'candidate-at-zero' => [AuditHistoryCheckpointTag::CheckpointEnd, $candidateAtZero],
            'candidate-length-pair' => [AuditHistoryCheckpointTag::CheckpointEnd, $candidateLengthPair],
            'complete-length-pair' => [AuditHistoryCheckpointTag::CheckpointEnd, $completeLengthPair],
            'complete-at-four' => [AuditHistoryCheckpointTag::CheckpointEnd, $completeAtFour],
        ];

        foreach ($rows as [$tag, $scalars]) {
            $this->assertInvalidGroupAtAllBoundaries($tag, $scalars);
        }
    }

    public function testNativeAdmissionChecksDomainsRelationsAndRepresentations(): void
    {
        $vectors = self::nativeVectors();

        $header = $vectors['Header'][1];
        $header['schemaVersion'] = 0;
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(
                AuditHistoryTag::Header,
                $header,
            ),
            'History native group is invalid.',
        );

        $header = $vectors['Header'][1];
        $header['segmentId'] = 'not-a-uuid';
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(
                AuditHistoryTag::Header,
                $header,
            ),
            'History native group is invalid.',
        );

        $header = $vectors['Header'][1];
        $header['installationRef'] = "e\u{0301}";
        $this->assertInvalidGroupWithWireMutation(
            AuditHistoryTag::Header,
            $header,
            $vectors['Header'][1],
            '"installationRef":"install-main"',
            "\"installationRef\":\"e\u{0301}\"",
        );

        $header['installationRef'] = str_repeat('i', 257);
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::Header, $header);

        $policy = $vectors['InstalledNextPolicy'][1];
        $policy['policy_digest'] = "\xC3\x28";
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(
                AuditHistoryTag::InstalledNextPolicy,
                $policy,
            ),
            'History native group is invalid.',
        );

        $policy = $vectors['InstalledNextPolicy'][1];
        $digest = $policy['policy_digest'];
        $policy['policy_digest'] = &$digest;
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(
                AuditHistoryTag::InstalledNextPolicy,
                $policy,
            ),
            'History native group is invalid.',
        );
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromDecoded(
                AuditHistoryTag::InstalledNextPolicy,
                $policy,
            ),
            'History native group is invalid.',
        );
        unset($policy['policy_digest']);

        $policy['policy_digest'] = $vectors['InstalledNextPolicy'][1]['policy_digest'];
        $policy['policy_version'] = 9_007_199_254_740_992;
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(
                AuditHistoryTag::InstalledNextPolicy,
                $policy,
            ),
            'History native group is invalid.',
        );

        $presence = $vectors['CheckpointPresence'][1];
        $presence['reservation_present'] = false;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $step = $vectors['WorkStepNative'][1];
        $step['ordinal'] = 5;
        $step['previous_step_ordinal'] = 4;
        $step['previous_step_digest'] = str_repeat('1', 64);
        $step['phase'] = 'ingress_acknowledged';
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(
                AuditHistoryCheckpointTag::WorkStepNative,
                $step,
            ),
            'History native group is invalid.',
        );

        $preparation = $vectors['PreparationNative'][1];
        $preparation['closed_state_version'] = 1;
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(
                AuditHistoryCheckpointTag::PreparationNative,
                $preparation,
            ),
            'History native group is invalid.',
        );

        $preparation['closed_state_version'] = 9_007_199_254_740_991;
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(
                AuditHistoryCheckpointTag::PreparationNative,
                $preparation,
            ),
            'History native group is invalid.',
        );

        $closed = $vectors['TargetSegmentNative'][1];
        $closed['final_sequence'] = null;
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(
                AuditHistoryCheckpointTag::TargetSegmentNative,
                $closed,
            ),
            'History native group is invalid.',
        );

        $anchor = $vectors['ExternalAnchor'][1];
        $anchor['acknowledged_transition_digest'] = str_repeat('3', 64);
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::ExternalAnchor, $anchor);

        $candidate = $vectors['CandidatePublicationNative'][1];
        $candidate['previous_revision'] = 2;
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative(
                AuditHistoryControlTag::CandidatePublicationNative,
                $candidate,
            ),
            'History native group is invalid.',
        );

        $end = $vectors['CheckpointEnd'][1];
        $end['higher_work_ordinal_present'] = true;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end['higher_work_ordinal_present'] = false;
        $end['unsigned_length'] = 1_048_576;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $reservation = $vectors['ReservationNative'][1];
        $reservation['production_class'] = 'ordinary';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::ReservationNative, $reservation);

        $header = array_replace($vectors['Header'][1], [
            'tenantType' => 'organization',
            'partitionId' => 'tenant:' . str_repeat('0', 64),
        ]);
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::Header, $header);
    }

    public function testSegmentAndPublicationVersionBoundsRemainDistinct(): void
    {
        $vectors = self::nativeVectors();
        $maximum = AuditValidationException::MAX_SAFE_INTEGER;

        $closed = $vectors['TargetSegmentNative'][1];
        $closed['state_version'] = $maximum;
        $this->assertValidGroup(AuditHistoryCheckpointTag::TargetSegmentNative, $closed);

        $sealed = $vectors['PredecessorSegmentNative'][1];
        $sealed['state_version'] = $maximum;
        $this->assertValidGroup(AuditHistoryCheckpointTag::PredecessorSegmentNative, $sealed);

        $openSuccessor = $vectors['SuccessorSegmentNative'][1];
        $openSuccessor['state'] = 'open';
        $openSuccessor['first_sequence'] = 2;
        $openSuccessor['predecessor_record_hash'] = str_repeat('0', 64);
        $openSuccessor['final_sequence'] = null;
        $openSuccessor['head_record_hash'] = null;
        $openSuccessor['closed_at'] = null;
        $openSuccessor['successor_segment_id'] = null;
        $openSuccessor['close_fencing_token'] = null;
        $openSuccessor['state_version'] = 1;
        $this->assertValidGroup(AuditHistoryCheckpointTag::SuccessorSegmentNative, $openSuccessor);

        $acceptedPublication = $vectors['PublicationEnd'][1];
        $acceptedPublication['next_revision'] = $acceptedPublication['accepted_revision'];
        $acceptedPublication['next_head_digest'] = $acceptedPublication['accepted_head_digest'];
        $this->assertValidGroup(AuditHistoryControlTag::PublicationEnd, $acceptedPublication);

        $emptyPublication = $vectors['PublicationEnd'][1];
        $emptyPublication['accepted_revision'] = 0;
        $emptyPublication['accepted_head_digest'] = null;
        $emptyPublication['next_revision'] = 1;
        $this->assertValidGroup(AuditHistoryControlTag::PublicationEnd, $emptyPublication);

        $closed['state_version'] = 1;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::TargetSegmentNative, $closed);

        $closedMaxPlusOne = $vectors['TargetSegmentNative'][1];
        $closedMaxPlusOne['state_version'] = $maximum + 1;
        $this->assertInvalidGroupWithWireMutation(
            AuditHistoryCheckpointTag::TargetSegmentNative,
            $closedMaxPlusOne,
            $vectors['TargetSegmentNative'][1],
            '"state_version":2',
            '"state_version":' . ($maximum + 1),
        );

        $sealed = $vectors['PredecessorSegmentNative'][1];
        $sealed['state_version'] = 1;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::PredecessorSegmentNative, $sealed);

        $sealedMaxPlusOne = $vectors['PredecessorSegmentNative'][1];
        $sealedMaxPlusOne['state_version'] = $maximum + 1;
        $this->assertInvalidGroupWithWireMutation(
            AuditHistoryCheckpointTag::PredecessorSegmentNative,
            $sealedMaxPlusOne,
            $vectors['PredecessorSegmentNative'][1],
            '"state_version":2',
            '"state_version":' . ($maximum + 1),
        );

        $preparation = $vectors['PreparationNative'][1];
        $preparation['closed_state_version'] = 2;
        $this->assertValidGroup(AuditHistoryCheckpointTag::PreparationNative, $preparation);
        $preparation['closed_state_version'] = $maximum - 1;
        $this->assertValidGroup(AuditHistoryCheckpointTag::PreparationNative, $preparation);
        $preparation['closed_state_version'] = 1;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::PreparationNative, $preparation);
        $preparation['closed_state_version'] = $maximum;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::PreparationNative, $preparation);

        $publicationEnd = $vectors['PublicationEnd'][1];
        $publicationEnd['accepted_revision'] = $maximum;
        $publicationEnd['next_revision'] = $maximum;
        $publicationEnd['next_head_digest'] = $publicationEnd['accepted_head_digest'];
        $this->assertValidGroup(AuditHistoryControlTag::PublicationEnd, $publicationEnd);
    }

    public function testLocalRelationsRejectIsolatedMismatches(): void
    {
        $vectors = self::nativeVectors();
        $h0 = str_repeat('0', 64);
        $h1 = str_repeat('1', 64);
        $h2 = str_repeat('2', 64);

        $header = $vectors['Header'][1];
        $header['schemaVersion'] = '1';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::Header, $header);

        $header = $vectors['Header'][1];
        $header['phase'] = 3.0;
        $this->assertInvalidGroupWithWireMutation(
            AuditHistoryTag::Header,
            $header,
            $vectors['Header'][1],
            '"phase":3',
            '"phase":3.0',
        );

        $header = $vectors['Header'][1];
        $header['phase'] = true;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::Header, $header);

        $header = $vectors['Header'][1];
        $header['schemaName'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::Header, $header);

        $header = $vectors['Header'][1];
        $header['installationRef'] = "bad\xC3(";
        $this->assertInvalidGroupWithWireMutation(
            AuditHistoryTag::Header,
            $header,
            $vectors['Header'][1],
            '"installationRef":"install-main"',
            '"installationRef":"' . $header['installationRef'] . '"',
        );

        $header = $vectors['Header'][1];
        $header['installationRef'] = "bad\u{0000}value";
        $this->assertInvalidGroupWithWireMutation(
            AuditHistoryTag::Header,
            $header,
            $vectors['Header'][1],
            '"installationRef":"install-main"',
            '"installationRef":"bad\u0000value"',
        );

        $header = $vectors['Header'][1];
        $header['installationRef'] = "bad\u{200B}value";
        $this->assertInvalidGroupWithWireMutation(
            AuditHistoryTag::Header,
            $header,
            $vectors['Header'][1],
            '"installationRef":"install-main"',
            '"installationRef":"bad\u200Bvalue"',
        );

        $header = $vectors['Header'][1];
        $header['installationRef'] = "caf\u{00E9}";
        $this->assertValidGroup(AuditHistoryTag::Header, $header);

        $header = $vectors['Header'][1];
        $header['installationRef'] = str_repeat('i', 256);
        $this->assertValidGroup(AuditHistoryTag::Header, $header);

        $header = $vectors['Header'][1];
        $header['phase'] = 10;
        $header['tenantType'] = 'organization';
        $header['tenantId'] = 'tenant-main';
        $header['partitionId'] = 'tenant:' . $h0;
        $header['segmentId'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::Header, $header);

        $external = $vectors['ExternalAnchor'][1];
        $external['current_first_sequence'] = 2;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::ExternalAnchor, $external);

        $partition = $vectors['PartitionNative'][1];
        $partition['last_sequence'] = 1;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::PartitionNative, $partition);

        $partition = $vectors['PartitionNative'][1];
        $partition['tenant_type'] = 'organization';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::PartitionNative, $partition);

        $partition = $vectors['PartitionNative'][1];
        $partition['tenant_type'] = 'organization';
        $partition['tenant_id'] = 'tenant-main';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::PartitionNative, $partition);

        $partition = $vectors['PartitionNative'][1];
        $partition['partition_id'] = 'tenant:' . $h0;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::PartitionNative, $partition);

        $partition = $vectors['PartitionNative'][1];
        $partition['partition_id'] = 'tenant:' . $h0;
        $partition['tenant_type'] = 'organization';
        $partition['tenant_id'] = 'tenant-main';
        $this->assertValidGroup(AuditHistoryCheckpointTag::PartitionNative, $partition);

        $endpoint = $vectors['PreviousRecordEndpoint'][1];
        $endpoint['sequence'] = 2;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::PreviousRecordEndpoint, $endpoint);

        $stagedBegin = $vectors['StagedSegmentsBegin'][1];
        $stagedBegin['actual_current_first_sequence'] = 2;
        $stagedBegin['actual_current_predecessor_record_hash'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::StagedSegmentsBegin, $stagedBegin);

        $open = $vectors['CurrentOpenSegmentNative'][1];
        $open['first_sequence'] = 2;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CurrentOpenSegmentNative, $open);

        $open = $vectors['CurrentOpenSegmentNative'][1];
        $open['closed_at'] = $open['opened_at'];
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CurrentOpenSegmentNative, $open);

        $closed = $vectors['TargetSegmentNative'][1];
        $closed['closed_at'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::TargetSegmentNative, $closed);

        $closed = $vectors['TargetSegmentNative'][1];
        $closed['head_record_hash'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::TargetSegmentNative, $closed);

        $closed = $vectors['TargetSegmentNative'][1];
        $closed['first_sequence'] = 2;
        $closed['predecessor_record_hash'] = $h0;
        $closed['final_sequence'] = 1;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::TargetSegmentNative, $closed);

        $closed = $vectors['TargetSegmentNative'][1];
        $closed['closed_at'] = '2026-10-13T12:34:56.123456Z';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::TargetSegmentNative, $closed);

        $closed = $vectors['TargetSegmentNative'][1];
        $closed['successor_segment_id'] = $closed['segment_id'];
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::TargetSegmentNative, $closed);

        foreach ([
            [AuditHistoryCheckpointTag::TargetSegmentNative,      'TargetSegmentNative'],
            [AuditHistoryCheckpointTag::SuccessorSegmentNative,   'SuccessorSegmentNative'],
            [AuditHistoryCheckpointTag::PredecessorSegmentNative, 'PredecessorSegmentNative'],
        ] as [$tag, $vector]) {
            $closed = $vectors[$vector][1];
            $closed['successor_segment_id'] = null;
            $this->assertInvalidGroupAtAllBoundaries($tag, $closed);
        }

        $proof = $vectors['OwnedClosedProof'][1];
        $proof['firstSequence'] = 2;
        $proof['predecessorRecordHash'] = $h0;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::OwnedClosedProof, $proof);

        $proof = $vectors['OwnedClosedProof'][1];
        $proof['previousSegmentId'] = $proof['successorSegmentId'];
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::OwnedClosedProof, $proof);

        $proof = $vectors['OwnedClosedProof'][1];
        $proof['lastSequence'] = 2;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::OwnedClosedProof, $proof);

        $proof = $vectors['OwnedClosedProof'][1];
        $proof['successorSegmentId'] = $proof['segmentId'];
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::OwnedClosedProof, $proof);

        $binding = $vectors['PublishedSegmentNative'][1];
        $binding['first_sequence'] = 2;
        $binding['last_sequence'] = 1;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::PublishedSegmentNative, $binding);

        $binding = $vectors['PublishedSegmentNative'][1];
        $binding['successor_segment_id'] = $binding['segment_id'];
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryTag::PublishedSegmentNative, $binding);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['step_count'] = 0;
        $presence['preparation_present'] = false;
        $presence['complete_present'] = true;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['step_count'] = 1;
        $presence['preparation_present'] = false;
        $presence['complete_present'] = false;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['step_count'] = 3;
        $presence['complete_present'] = false;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['step_count'] = 4;
        $presence['complete_present'] = false;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['reservation_present'] = false;
        $presence['preparation_present'] = false;
        $presence['complete_present'] = false;
        $presence['step_count'] = 0;
        $this->assertValidGroup(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['preparation_present'] = false;
        $presence['complete_present'] = false;
        $presence['step_count'] = 0;
        $this->assertValidGroup(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['preparation_present'] = true;
        $presence['complete_present'] = false;
        $presence['step_count'] = 1;
        $this->assertValidGroup(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['step_count'] = 0;
        $presence['preparation_present'] = true;
        $presence['complete_present'] = false;
        $this->assertValidGroup(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['step_count'] = 2;
        $presence['complete_present'] = false;
        $this->assertValidGroup(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['step_count'] = 3;
        $this->assertValidGroup(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $presence = $vectors['CheckpointPresence'][1];
        $presence['step_count'] = 4;
        $this->assertValidGroup(AuditHistoryCheckpointTag::CheckpointPresence, $presence);

        $reservation = $vectors['ReservationNative'][1];
        $reservation['segment_binding_digest'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::ReservationNative, $reservation);

        $reservation = $vectors['ReservationNative'][1];
        $reservation['production_class'] = 'ordinary';
        $reservation['segment_binding_digest'] = null;
        $this->assertValidGroup(AuditHistoryCheckpointTag::ReservationNative, $reservation);

        $step = $vectors['WorkStepNative'][1];
        $this->assertValidGroup(AuditHistoryCheckpointTag::WorkStepNative, $step);

        $step['ordinal'] = 2;
        $step['previous_step_ordinal'] = 1;
        $step['previous_step_digest'] = $h1;
        $step['phase'] = 'complete_candidate';
        $this->assertValidGroup(AuditHistoryCheckpointTag::WorkStepNative, $step);

        $step['ordinal'] = 3;
        $step['previous_step_ordinal'] = 2;
        $step['previous_step_digest'] = $h1;
        $step['phase'] = 'artifact_persisted';
        $this->assertValidGroup(AuditHistoryCheckpointTag::WorkStepNative, $step);

        $step['ordinal'] = 4;
        $step['previous_step_ordinal'] = 3;
        $step['previous_step_digest'] = $h1;
        $step['phase'] = 'ingress_acknowledged';
        $this->assertValidGroup(AuditHistoryCheckpointTag::WorkStepNative, $step);

        $step = $vectors['WorkStepNative'][1];
        $step['ordinal'] = 2;
        $step['previous_step_ordinal'] = 4;
        $step['previous_step_digest'] = $h1;
        $step['phase'] = 'complete_candidate';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::WorkStepNative, $step);

        $step = $vectors['WorkStepNative'][1];
        $step['phase'] = 'complete_candidate';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::WorkStepNative, $step);

        $step = $vectors['WorkStepNative'][1];
        $step['ordinal'] = 2;
        $step['previous_step_ordinal'] = 1;
        $step['previous_step_digest'] = $h1;
        $step['phase'] = 'preparation_bound';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::WorkStepNative, $step);

        $step['ordinal'] = 3;
        $step['previous_step_ordinal'] = 2;
        $step['phase'] = 'complete_candidate';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::WorkStepNative, $step);

        $step['ordinal'] = 4;
        $step['previous_step_ordinal'] = 3;
        $step['phase'] = 'artifact_persisted';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::WorkStepNative, $step);

        $step = $vectors['WorkStepNative'][1];
        $step['ordinal'] = 2;
        $step['previous_step_ordinal'] = 1;
        $step['previous_step_digest'] = $step['reservation_digest'];
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::WorkStepNative, $step);

        $end = $vectors['CheckpointEnd'][1];
        $end['work_id'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['last_ordinal'] = 0;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['last_ordinal'] = 1;
        $end['last_step_digest'] = $h1;
        $end['complete_sha256'] = null;
        $end['complete_length'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['last_ordinal'] = 1;
        $end['last_step_digest'] = $h1;
        $end['candidate_sha256'] = null;
        $end['candidate_length'] = null;
        $end['complete_sha256'] = $h2;
        $end['complete_length'] = 1;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['last_ordinal'] = 2;
        $end['last_step_digest'] = $h1;
        $end['candidate_sha256'] = null;
        $end['candidate_length'] = null;
        $end['complete_sha256'] = null;
        $end['complete_length'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['last_ordinal'] = 3;
        $end['last_step_digest'] = $h1;
        $end['complete_sha256'] = null;
        $end['complete_length'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['last_ordinal'] = 0;
        $end['last_step_digest'] = null;
        $end['candidate_sha256'] = $h1;
        $end['candidate_length'] = 1;
        $end['complete_sha256'] = null;
        $end['complete_length'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['last_ordinal'] = 1;
        $end['last_step_digest'] = $h1;
        $end['candidate_sha256'] = null;
        $end['candidate_length'] = null;
        $end['complete_sha256'] = null;
        $end['complete_length'] = null;
        $this->assertValidGroup(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['last_ordinal'] = 4;
        $end['last_step_digest'] = $h1;
        $end['complete_sha256'] = null;
        $end['complete_length'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['work_id'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['preparation_checkpoint_id'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['preparation_checkpoint_id'] = null;
        $end['unsigned_sha256'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['last_ordinal'] = 2;
        $end['last_step_digest'] = $h1;
        $end['candidate_sha256'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $end = $vectors['CheckpointEnd'][1];
        $end['complete_sha256'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryCheckpointTag::CheckpointEnd, $end);

        $decodedEnd = $vectors['CheckpointEnd'][1];
        $decodedEnd['candidate_sha256'] = null;
        $this->assertInvalidGroupWithWireMutation(
            AuditHistoryCheckpointTag::CheckpointEnd,
            $decodedEnd,
            $vectors['CheckpointEnd'][1],
            '"candidate_length":1,"candidate_sha256":"' . $h1 . '"',
            '"candidate_length":1,"candidate_sha256":null',
            'History native group is invalid.',
        );

        $transition = $vectors['TransitionNative'][1];
        $transition['cause'] = 'segment_advance';
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryControlTag::TransitionNative, $transition);

        $transition = $vectors['TransitionNative'][1];
        $transition['previous_transition_digest'] = $h0;
        $transition['previous_policy_state_digest'] = $h1;
        $this->assertValidGroup(AuditHistoryControlTag::TransitionNative, $transition);

        $publication = $vectors['PublicationNative'][1];
        $publication['previous_revision'] = 1;
        $publication['previous_head_digest'] = $h0;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryControlTag::PublicationNative, $publication);

        $candidate = $vectors['CandidatePublicationNative'][1];
        $candidate['revision'] = 3;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryControlTag::CandidatePublicationNative, $candidate);

        $publication = $vectors['PublicationNative'][1];
        $publication['acknowledged_transition_digest'] = $h0;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryControlTag::PublicationNative, $publication);

        $publicationEnd = $vectors['PublicationEnd'][1];
        $publicationEnd['accepted_revision'] = 0;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryControlTag::PublicationEnd, $publicationEnd);

        $publicationEnd = $vectors['PublicationEnd'][1];
        $publicationEnd['next_head_digest'] = null;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryControlTag::PublicationEnd, $publicationEnd);

        $publicationEnd = $vectors['PublicationEnd'][1];
        $publicationEnd['next_revision'] = 3;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryControlTag::PublicationEnd, $publicationEnd);

        $publicationEnd = $vectors['PublicationEnd'][1];
        $publicationEnd['next_revision'] = 1;
        $publicationEnd['next_head_digest'] = $h1;
        $this->assertInvalidGroupAtAllBoundaries(AuditHistoryControlTag::PublicationEnd, $publicationEnd);
    }

    public function testScalarRejectsUnknownNullableLookingDescriptors(): void
    {
        $h0 = str_repeat('0', 64);
        self::assertSame($h0, AuditHistoryScalar::admit('?H', $h0));
        self::assertSame(1, AuditHistoryScalar::admit('?N', 1));
        self::assertSame(1, AuditHistoryScalar::admit('?stepOrdinal', 1));
        self::assertSame(2, AuditHistoryScalar::admit('?stepOrdinal', 2));
        self::assertSame(3, AuditHistoryScalar::admit('?stepOrdinal', 3));

        $this->assertInvalid(
            static fn(): bool|int|string|null => AuditHistoryScalar::admit('?B', true),
            'History scalar is invalid.',
        );
        $this->assertInvalid(
            static fn(): bool|int|string|null => AuditHistoryScalar::admit('?phase', 1),
            'History scalar is invalid.',
        );
        $this->assertInvalid(
            static fn(): bool|int|string|null => AuditHistoryScalar::admit('?phase', null),
            'History scalar is invalid.',
        );
        $this->assertInvalid(
            static fn(): bool|int|string|null => AuditHistoryScalar::admit('?stepOrdinal', 4),
            'History integer scalar is invalid.',
        );
    }

    /** @param array<string,scalar|null> $scalars */
    private function assertValidGroup(
        AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag $tag,
        array $scalars,
    ): void {
        $codec = new AuditHistoryGroupCodec();
        $group = AuditHistoryNativeGroup::fromNative($tag, $scalars);
        $decodedGroup = AuditHistoryNativeGroup::fromDecoded($tag, $group->scalars());
        $decoded = $codec->decode($tag, $codec->encode($group));
        self::assertSame($tag, $decodedGroup->tag());
        self::assertSame($tag, $decoded->tag());
    }

    public function testDecodePreflightsFlatShapeBeforeCanonicalDecoding(): void
    {
        $codec = new AuditHistoryGroupCodec();
        $vectors = self::nativeVectors();

        $nested = '{"installationRef":"install-main","partitionId":"system:global","phase":3,"schemaName":"audit_history","schemaVersion":1,"segmentId":{"secret":"history-secret-value"},"tenantId":null,"tenantType":null,"workId":null}';
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => $codec->decode(AuditHistoryTag::Header, $nested),
            'History native group bytes are invalid.',
        );
        $nestedList = str_replace('{"secret":"history-secret-value"}', '["history-secret-value"]', $nested);
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => $codec->decode(AuditHistoryTag::Header, $nestedList),
            'History native group bytes are invalid.',
        );

        $unterminated = '{"installationRef":"install-main","partitionId":"system:global","phase":3,"schemaName":"audit_history","schemaVersion":1,"segmentId":"01941f29-7c00-7000-8000-000000000101","tenantId":null,"tenantType":null,"workId":"unterminated}';
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => $codec->decode(AuditHistoryTag::Header, $unterminated),
            'History native group bytes are invalid.',
        );

        foreach (['install-{main}', 'install-[main]', 'install-"main'] as $installationRef) {
            $withQuotedText = $vectors['Header'][1];
            $withQuotedText['installationRef'] = $installationRef;
            $quotedGroup = AuditHistoryNativeGroup::fromNative(AuditHistoryTag::Header, $withQuotedText);
            $quotedBytes = $codec->encode($quotedGroup);
            $quotedExpected = $quotedGroup->scalars();
            $quotedActual = $codec->decode(AuditHistoryTag::Header, $quotedBytes)->scalars();
            ksort($quotedExpected);
            ksort($quotedActual);
            self::assertSame($quotedExpected, $quotedActual);
        }

        $nonCanonical = '{"schemaVersion":1,"phase":3,"installationRef":"install-main","schemaName":"audit_history","partitionId":"system:global","segmentId":"01941f29-7c00-7000-8000-000000000101","tenantType":null,"tenantId":null,"workId":null}';
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => $codec->decode(AuditHistoryTag::Header, $nonCanonical),
            'History native group is not canonical.',
        );

        $unknownField = '{"extra":true,"installationRef":"install-main","partitionId":"system:global","phase":3,"schemaName":"audit_history","schemaVersion":1,"segmentId":"01941f29-7c00-7000-8000-000000000101","tenantId":null,"tenantType":null,"workId":null}';
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => $codec->decode(AuditHistoryTag::Header, $unknownField),
            'History native group is invalid.',
        );

        $missingField = '{"installationRef":"install-main","partitionId":"system:global","phase":3,"schemaName":"audit_history","schemaVersion":1,"segmentId":"01941f29-7c00-7000-8000-000000000101","tenantId":null,"tenantType":null}';
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => $codec->decode(AuditHistoryTag::Header, $missingField),
            'History native group is invalid.',
        );

        $wrongType = '{"installationRef":"install-main","partitionId":"system:global","phase":"3","schemaName":"audit_history","schemaVersion":1,"segmentId":"01941f29-7c00-7000-8000-000000000101","tenantId":null,"tenantType":null,"workId":null}';
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => $codec->decode(AuditHistoryTag::Header, $wrongType),
            'History native group is invalid.',
        );

        $invalidPresence = '{"complete_present":true,"preparation_present":false,"reservation_present":true,"step_count":1}';
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => $codec->decode(
                AuditHistoryCheckpointTag::CheckpointPresence,
                $invalidPresence,
            ),
            'History native group is invalid.',
        );
    }

    public function testDecodeFailuresDoNotExposeSensitiveBytesOrPreviousExceptions(): void
    {
        $original = ini_get('zend.exception_ignore_args');
        $codec = new AuditHistoryGroupCodec();
        $vectors = self::nativeVectors();
        $nested = '{"installationRef":"install-main","partitionId":"system:global","phase":3,"schemaName":"audit_history","schemaVersion":1,"segmentId":{"secret":"history-secret-value"},"tenantId":null,"tenantType":null,"workId":null}';

        try {
            ini_set('zend.exception_ignore_args', '0');
            $failure = null;
            try {
                $codec->decode(AuditHistoryTag::Header, $nested);
            } catch (InvalidArgumentException $exception) {
                $failure = $exception;
            }

            self::assertInstanceOf(InvalidArgumentException::class, $failure);
            self::assertNull($failure->getPrevious());
            self::assertStringNotContainsString('history-secret-value', var_export($failure->getTrace(), true));

            $jcsFailure = null;
            try {
                $codec->decode(AuditHistoryTag::Header, '{"history-secret-value":"value' . "\xC3(" . '"}');
            } catch (InvalidArgumentException $exception) {
                $jcsFailure = $exception;
            }

            self::assertInstanceOf(InvalidArgumentException::class, $jcsFailure);
            self::assertSame('History native group bytes are invalid.', $jcsFailure->getMessage());
            self::assertNull($jcsFailure->getPrevious());
            self::assertStringNotContainsString('history-secret-value', var_export($jcsFailure->getTrace(), true));

            $helperFailure = null;
            try {
                $codec->decode(
                    AuditHistoryTag::Header,
                    '{"installationRef":"history-secret-value","partitionId":"system:global","phase":99,"schemaName":"audit_history","schemaVersion":1,"segmentId":"01941f29-7c00-7000-8000-000000000101","tenantId":null,"tenantType":null,"workId":null}',
                );
            } catch (InvalidArgumentException $exception) {
                $helperFailure = $exception;
            }

            self::assertInstanceOf(InvalidArgumentException::class, $helperFailure);
            self::assertSame('History native group is invalid.', $helperFailure->getMessage());
            self::assertNull($helperFailure->getPrevious());
            self::assertStringNotContainsString('history-secret-value', var_export($helperFailure->getTrace(), true));

            $sentinelHash = str_repeat('a', 64);
            $header = $vectors['Header'][1];
            $header['installationRef'] = 'history-secret-value';
            $headerBytes = $codec->encode(AuditHistoryNativeGroup::fromNative(AuditHistoryTag::Header, $header));
            $target = $vectors['TargetSegmentNative'][1];
            $target['head_record_hash'] = $sentinelHash;
            $targetBytes = $codec->encode(AuditHistoryNativeGroup::fromNative(
                AuditHistoryCheckpointTag::TargetSegmentNative,
                $target,
            ));
            $closed = $vectors['OwnedClosedProof'][1];
            $closed['firstRecordHash'] = $sentinelHash;
            $closedBytes = $codec->encode(AuditHistoryNativeGroup::fromNative(
                AuditHistoryCheckpointTag::OwnedClosedProof,
                $closed,
            ));
            $reservation = $vectors['ReservationNative'][1];
            $reservation['installation_ref'] = 'history-secret-value';
            $reservationBytes = $codec->encode(AuditHistoryNativeGroup::fromNative(
                AuditHistoryCheckpointTag::ReservationNative,
                $reservation,
            ));
            $checkpointEnd = $vectors['CheckpointEnd'][1];
            $checkpointEnd['reservation_digest'] = $sentinelHash;
            $checkpointEndBytes = $codec->encode(AuditHistoryNativeGroup::fromNative(
                AuditHistoryCheckpointTag::CheckpointEnd,
                $checkpointEnd,
            ));
            $transition = $vectors['TransitionNative'][1];
            $transition['installation_ref'] = 'history-secret-value';
            $transitionBytes = $codec->encode(AuditHistoryNativeGroup::fromNative(
                AuditHistoryControlTag::TransitionNative,
                $transition,
            ));
            $timestamp = $target['opened_at'];
            $timestampBytes = $targetBytes;

            /** @var list<array{0:AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag,1:string,2:string,3:string}> $privacyCases */
            $privacyCases = [
                [
                    AuditHistoryTag::Header,
                    $headerBytes,
                    '"segmentId":"' . $header['segmentId'] . '"',
                    '"segmentId":null',
                ],
                [
                    AuditHistoryCheckpointTag::TargetSegmentNative,
                    $targetBytes,
                    '"final_sequence":1',
                    '"final_sequence":null',
                ],
                [
                    AuditHistoryCheckpointTag::OwnedClosedProof,
                    $closedBytes,
                    '"previousSegmentId":null',
                    '"previousSegmentId":"' . $closed['segmentId'] . '"',
                ],
                [
                    AuditHistoryCheckpointTag::ReservationNative,
                    $reservationBytes,
                    '"production_class":"control"',
                    '"production_class":"ordinary"',
                ],
                [
                    AuditHistoryCheckpointTag::CheckpointEnd,
                    $checkpointEndBytes,
                    '"work_id":"' . $checkpointEnd['work_id'] . '"',
                    '"work_id":null',
                ],
                [
                    AuditHistoryControlTag::TransitionNative,
                    $transitionBytes,
                    '"cause":"root_revision"',
                    '"cause":"segment_advance"',
                ],
                [
                    AuditHistoryCheckpointTag::TargetSegmentNative,
                    $timestampBytes,
                    '"opened_at":"' . $timestamp . '"',
                    '"opened_at":"not-a-timestamp"',
                ],
            ];

            foreach ($privacyCases as [$tag, $bytes, $old, $new]) {
                $replacements = 0;
                $invalidBytes = str_replace($old, $new, $bytes, $replacements);
                self::assertSame(1, $replacements);
                $failure = null;
                try {
                    $codec->decode($tag, $invalidBytes);
                } catch (InvalidArgumentException $exception) {
                    $failure = $exception;
                }

                self::assertInstanceOf(InvalidArgumentException::class, $failure);
                self::assertSame('History native group is invalid.', $failure->getMessage());
                self::assertNull($failure->getPrevious());
                $trace = var_export($failure->getTrace(), true);
                self::assertStringNotContainsString('history-secret-value', $trace);
                self::assertStringNotContainsString($sentinelHash, $trace);
            }
        } finally {
            if ($original !== false) {
                ini_set('zend.exception_ignore_args', $original);
            }
        }
    }

    public function testRecordProjectionAndPayloadBoundsRemainExplicit(): void
    {
        $vectors = self::nativeVectors();
        $native = AuditHistoryNativeGroup::fromNative($vectors['Header'][0], $vectors['Header'][1]);
        $payload = $vectors['Header'][2];

        $record = AuditHistoryRecord::fromCapture(AuditHistoryTag::Header, $payload, $native);
        self::assertSame(AuditHistoryTag::Header, $record->tag());
        self::assertSame($payload, $record->bytes());
        self::assertSame($native, $record->native());
        $read = AuditHistoryRecord::fromRead(AuditHistoryTag::Header, $payload, $native);
        self::assertSame($payload, $read->bytes());
        self::assertSame($native, $read->native());

        $this->assertInvalid(
            static fn(): AuditHistoryRecord => AuditHistoryRecord::fromCapture(AuditHistoryTag::Header, $payload, null),
            'History record projection is invalid.',
        );
        $this->assertInvalid(
            static fn(): AuditHistoryRecord => AuditHistoryRecord::fromCapture(
                AuditHistoryTag::AcceptedAuthorityBytes,
                'x',
                $native,
            ),
            'History record projection is invalid.',
        );
        $this->assertInvalid(
            static fn(): AuditHistoryRecord => AuditHistoryRecord::fromCapture(
                AuditHistoryCheckpointTag::WorkStepNative,
                $payload,
                $native,
            ),
            'History record projection is invalid.',
        );
        $this->assertInvalid(
            static fn(): AuditHistoryRecord => AuditHistoryRecord::fromCapture(
                AuditHistoryTag::FloorHint,
                str_repeat('x', 4_097),
                null,
            ),
            'History record exceeds its byte bound.',
        );
        $bounded = AuditHistoryRecord::fromCapture(
            AuditHistoryCheckpointTag::CompleteCandidateBytes,
            str_repeat('x', 1_048_575),
            null,
        );
        self::assertSame(1_048_575, strlen($bounded->bytes()));
        $this->assertInvalid(
            static fn(): AuditHistoryRecord => AuditHistoryRecord::fromCapture(
                AuditHistoryCheckpointTag::CompleteCandidateBytes,
                str_repeat('x', 1_048_576),
                null,
            ),
            'History record exceeds its byte bound.',
        );

        self::assertSame([], $native->__debugInfo());
        $this->assertInvalid(
            static fn(): array => $native->__serialize(),
            'History native group cannot be serialized.',
        );
        $this->assertInvalid(static fn(): string => serialize($native), 'History native group cannot be serialized.');
        $this->assertInvalid(
            static function () use ($native): void {
                $native->__unserialize([]);
            },
            'History native group cannot be unserialized.',
        );
        $nativePayload =
            'O:' . strlen(AuditHistoryNativeGroup::class) . ':"' . AuditHistoryNativeGroup::class . '":0:{}';
        $this->assertInvalid(
            static fn(): mixed => unserialize($nativePayload),
            'History native group cannot be unserialized.',
        );
        $this->assertNotCloneable(static function () use ($native): void {
            clone $native;
        });

        self::assertSame([], $record->__debugInfo());
        $this->assertInvalid(static fn(): array => $record->__serialize(), 'History record cannot be serialized.');
        $this->assertInvalid(static fn(): string => serialize($record), 'History record cannot be serialized.');
        $this->assertInvalid(
            static function () use ($record): void {
                $record->__unserialize([]);
            },
            'History record cannot be unserialized.',
        );
        $recordPayload = 'O:' . strlen(AuditHistoryRecord::class) . ':"' . AuditHistoryRecord::class . '":0:{}';
        $this->assertInvalid(
            static fn(): mixed => unserialize($recordPayload),
            'History record cannot be unserialized.',
        );
        $this->assertNotCloneable(static function () use ($record): void {
            clone $record;
        });
    }

    public function testEmptyMarkersAndTrailerUseTheirExactRepresentations(): void
    {
        $empty = AuditHistoryRecord::fromCapture(AuditHistoryTag::EmptyInstalledSource, '', null);
        self::assertSame('', $empty->bytes());
        self::assertNull($empty->native());

        $this->assertInvalid(
            static fn(): AuditHistoryRecord => AuditHistoryRecord::fromCapture(
                AuditHistoryTag::EmptyInstalledSource,
                'x',
                null,
            ),
            'History absence record is invalid.',
        );

        $trailer = AuditHistoryRecord::fromCapture(AuditHistoryTag::Trailer, str_repeat('t', 48), null);
        self::assertSame(48, strlen($trailer->bytes()));
        $this->assertInvalid(
            static fn(): AuditHistoryRecord => AuditHistoryRecord::fromCapture(
                AuditHistoryTag::Trailer,
                str_repeat('t', 47),
                null,
            ),
            'History trailer is invalid.',
        );
    }

    /** @param callable():mixed $callback */
    private function assertInvalid(callable $callback, string $message): void
    {
        try {
            $callback();
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    /** @param array<string,scalar|null> $scalars */
    private function assertInvalidGroupAtAllBoundaries(
        AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag $tag,
        array $scalars,
    ): void {
        $message = 'History native group is invalid.';
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative($tag, $scalars),
            $message,
        );
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromDecoded($tag, $scalars),
            $message,
        );

        $bytes = new AuditJcsCodec()->canonicalJson($scalars);
        $codec = new AuditHistoryGroupCodec();
        $this->assertInvalid(static fn(): AuditHistoryNativeGroup => $codec->decode($tag, $bytes), $message);
    }

    /** @param array<string,scalar|null> $invalid @param array<string,scalar|null> $valid */
    private function assertInvalidGroupWithWireMutation(
        AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag $tag,
        array $invalid,
        array $valid,
        string $old,
        string $new,
        string $wireMessage = 'History native group bytes are invalid.',
    ): void {
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromNative($tag, $invalid),
            'History native group is invalid.',
        );
        $this->assertInvalid(
            static fn(): AuditHistoryNativeGroup => AuditHistoryNativeGroup::fromDecoded($tag, $invalid),
            'History native group is invalid.',
        );

        $codec = new AuditHistoryGroupCodec();
        $validBytes = $codec->encode(AuditHistoryNativeGroup::fromNative($tag, $valid));
        $replacements = 0;
        $invalidBytes = str_replace($old, $new, $validBytes, $replacements);
        self::assertSame(1, $replacements);
        $this->assertInvalid(static fn(): AuditHistoryNativeGroup => $codec->decode($tag, $invalidBytes), $wireMessage);
    }

    /** @param callable():mixed $callback */
    private function assertNotCloneable(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected private clone access failure.');
        } catch (\Error) {
            return;
        }
    }

    /**
     * @return array<string,array{0:AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag,1:array<string,scalar|null>,2:string}>
     */
    private static function nativeVectors(): array
    {
        $h0 = str_repeat('0', 64);
        $h1 = str_repeat('1', 64);
        $h2 = str_repeat('2', 64);
        $a = '01941f29-7c00-7000-8000-000000000101';
        $b = '01941f29-7c00-7000-8000-000000000102';
        $c = '01941f29-7c00-7000-8000-000000000103';
        $time = '2026-10-14T12:34:56.123456Z';
        $fencing = 'aft:v1:' . $h0;

        return [
            'Header' => [
                AuditHistoryTag::Header,
                [
                    'schemaVersion' => 1,
                    'phase' => 3,
                    'installationRef' => 'install-main',
                    'schemaName' => 'audit_history',
                    'tenantType' => null,
                    'tenantId' => null,
                    'partitionId' => 'system:global',
                    'segmentId' => $a,
                    'workId' => null,
                ],
                '{"installationRef":"install-main","partitionId":"system:global","phase":3,"schemaName":"audit_history","schemaVersion":1,"segmentId":"'
                    . $a
                    . '","tenantId":null,"tenantType":null,"workId":null}',
            ],
            'ExternalAnchor' => [
                AuditHistoryTag::ExternalAnchor,
                [
                    'document_sha256' => $h0,
                    'policy_version' => 1,
                    'root_policy_digest' => $h1,
                    'head_digest' => $h2,
                    'revision' => 1,
                    'current_segment_id' => $a,
                    'current_first_sequence' => 1,
                    'current_predecessor_record_hash' => null,
                    'closed_segment_tail_digest' => null,
                    'policy_state_digest' => $h0,
                    'transition_tail_digest' => $h1,
                    'acknowledged_transition_digest' => null,
                    'transition_ack_digest' => null,
                ],
                '{"acknowledged_transition_digest":null,"closed_segment_tail_digest":null,"current_first_sequence":1,"current_predecessor_record_hash":null,"current_segment_id":"'
                    . $a
                    . '","document_sha256":"'
                    . $h0
                    . '","head_digest":"'
                    . $h2
                    . '","policy_state_digest":"'
                    . $h0
                    . '","policy_version":1,"revision":1,"root_policy_digest":"'
                    . $h1
                    . '","transition_ack_digest":null,"transition_tail_digest":"'
                    . $h1
                    . '"}',
            ],
            'InstalledNextPolicy' => [
                AuditHistoryTag::InstalledNextPolicy,
                ['policy_digest' => $h0, 'policy_version' => 1],
                '{"policy_digest":"' . $h0 . '","policy_version":1}',
            ],
            'PartitionNative' => [
                AuditHistoryCheckpointTag::PartitionNative,
                [
                    'partition_id' => 'system:global',
                    'tenant_type' => null,
                    'tenant_id' => null,
                    'active_segment_id' => $a,
                    'last_sequence' => 0,
                    'head_record_hash' => null,
                    'last_assigned_recorded_at' => null,
                    'state_version' => 1,
                ],
                '{"active_segment_id":"'
                    . $a
                    . '","head_record_hash":null,"last_assigned_recorded_at":null,"last_sequence":0,"partition_id":"system:global","state_version":1,"tenant_id":null,"tenant_type":null}',
            ],
            'TargetSegmentNative' => [
                AuditHistoryCheckpointTag::TargetSegmentNative,
                [
                    'partition_id' => 'system:global',
                    'segment_id' => $a,
                    'state' => 'closed',
                    'first_sequence' => 1,
                    'final_sequence' => 1,
                    'head_record_hash' => $h1,
                    'predecessor_record_hash' => null,
                    'opened_at' => $time,
                    'closed_at' => $time,
                    'successor_segment_id' => $b,
                    'close_fencing_token' => $fencing,
                    'state_version' => 2,
                ],
                '{"close_fencing_token":"'
                    . $fencing
                    . '","closed_at":"'
                    . $time
                    . '","final_sequence":1,"first_sequence":1,"head_record_hash":"'
                    . $h1
                    . '","opened_at":"'
                    . $time
                    . '","partition_id":"system:global","predecessor_record_hash":null,"segment_id":"'
                    . $a
                    . '","state":"closed","state_version":2,"successor_segment_id":"'
                    . $b
                    . '"}',
            ],
            'SuccessorSegmentNative' => [
                AuditHistoryCheckpointTag::SuccessorSegmentNative,
                [
                    'partition_id' => 'system:global',
                    'segment_id' => $b,
                    'state' => 'closed',
                    'first_sequence' => 1,
                    'final_sequence' => 1,
                    'head_record_hash' => $h1,
                    'predecessor_record_hash' => null,
                    'opened_at' => $time,
                    'closed_at' => $time,
                    'successor_segment_id' => $c,
                    'close_fencing_token' => $fencing,
                    'state_version' => 2,
                ],
                '{"close_fencing_token":"'
                    . $fencing
                    . '","closed_at":"'
                    . $time
                    . '","final_sequence":1,"first_sequence":1,"head_record_hash":"'
                    . $h1
                    . '","opened_at":"'
                    . $time
                    . '","partition_id":"system:global","predecessor_record_hash":null,"segment_id":"'
                    . $b
                    . '","state":"closed","state_version":2,"successor_segment_id":"'
                    . $c
                    . '"}',
            ],
            'PredecessorSegmentNative' => [
                AuditHistoryCheckpointTag::PredecessorSegmentNative,
                [
                    'partition_id' => 'system:global',
                    'segment_id' => $c,
                    'state' => 'sealed',
                    'first_sequence' => 1,
                    'final_sequence' => 1,
                    'head_record_hash' => $h1,
                    'predecessor_record_hash' => null,
                    'opened_at' => $time,
                    'closed_at' => $time,
                    'successor_segment_id' => $a,
                    'close_fencing_token' => $fencing,
                    'state_version' => 2,
                ],
                '{"close_fencing_token":"'
                    . $fencing
                    . '","closed_at":"'
                    . $time
                    . '","final_sequence":1,"first_sequence":1,"head_record_hash":"'
                    . $h1
                    . '","opened_at":"'
                    . $time
                    . '","partition_id":"system:global","predecessor_record_hash":null,"segment_id":"'
                    . $c
                    . '","state":"sealed","state_version":2,"successor_segment_id":"'
                    . $a
                    . '"}',
            ],
            'PreviousRecordEndpoint' => [
                AuditHistoryCheckpointTag::PreviousRecordEndpoint,
                ['segment_id' => $a, 'sequence' => 1, 'previous_record_hash' => null, 'record_hash' => $h1],
                '{"previous_record_hash":null,"record_hash":"' . $h1 . '","segment_id":"' . $a . '","sequence":1}',
            ],
            'CurrentOpenSegmentNative' => [
                AuditHistoryCheckpointTag::CurrentOpenSegmentNative,
                [
                    'partition_id' => 'system:global',
                    'segment_id' => $a,
                    'state' => 'open',
                    'first_sequence' => 1,
                    'final_sequence' => 1,
                    'head_record_hash' => $h1,
                    'predecessor_record_hash' => null,
                    'opened_at' => $time,
                    'closed_at' => null,
                    'successor_segment_id' => null,
                    'close_fencing_token' => null,
                    'state_version' => 1,
                ],
                '{"close_fencing_token":null,"closed_at":null,"final_sequence":1,"first_sequence":1,"head_record_hash":"'
                    . $h1
                    . '","opened_at":"'
                    . $time
                    . '","partition_id":"system:global","predecessor_record_hash":null,"segment_id":"'
                    . $a
                    . '","state":"open","state_version":1,"successor_segment_id":null}',
            ],
            'OwnedClosedProof' => [
                AuditHistoryCheckpointTag::OwnedClosedProof,
                [
                    'partitionId' => 'system:global',
                    'segmentId' => $a,
                    'state' => 'closed',
                    'stateVersion' => 2,
                    'firstSequence' => 1,
                    'lastSequence' => 1,
                    'recordCount' => 1,
                    'firstRecordHash' => $h1,
                    'chainHeadHash' => $h1,
                    'predecessorRecordHash' => null,
                    'previousSegmentId' => null,
                    'successorSegmentId' => $b,
                    'closeFencingToken' => $fencing,
                    'closedAt' => $time,
                ],
                '{"chainHeadHash":"'
                    . $h1
                    . '","closeFencingToken":"'
                    . $fencing
                    . '","closedAt":"'
                    . $time
                    . '","firstRecordHash":"'
                    . $h1
                    . '","firstSequence":1,"lastSequence":1,"partitionId":"system:global","predecessorRecordHash":null,"previousSegmentId":null,"recordCount":1,"segmentId":"'
                    . $a
                    . '","state":"closed","stateVersion":2,"successorSegmentId":"'
                    . $b
                    . '"}',
            ],
            'CheckpointPresence' => [
                AuditHistoryCheckpointTag::CheckpointPresence,
                [
                    'reservation_present' => true,
                    'preparation_present' => true,
                    'complete_present' => true,
                    'step_count' => 2,
                ],
                '{"complete_present":true,"preparation_present":true,"reservation_present":true,"step_count":2}',
            ],
            'ReservationNative' => [
                AuditHistoryCheckpointTag::ReservationNative,
                [
                    'work_id' => $c,
                    'installation_ref' => 'install-main',
                    'reservation_digest' => $h0,
                    'policy_digest' => $h1,
                    'artifact_kind' => 'audit_checkpoint',
                    'partition_id' => 'system:global',
                    'segment_id' => $a,
                    'production_class' => 'control',
                    'segment_binding_digest' => $h2,
                    'checkpoint_reservation_digest' => null,
                    'intent_id' => null,
                    'receipt_checkpoint_id' => null,
                    'anchor_reservation_digest' => null,
                    'anchor_receipt_id' => null,
                ],
                '{"anchor_receipt_id":null,"anchor_reservation_digest":null,"artifact_kind":"audit_checkpoint","checkpoint_reservation_digest":null,"installation_ref":"install-main","intent_id":null,"partition_id":"system:global","policy_digest":"'
                    . $h1
                    . '","production_class":"control","receipt_checkpoint_id":null,"reservation_digest":"'
                    . $h0
                    . '","segment_binding_digest":"'
                    . $h2
                    . '","segment_id":"'
                    . $a
                    . '","work_id":"'
                    . $c
                    . '"}',
            ],
            'WorkStepNative' => [
                AuditHistoryCheckpointTag::WorkStepNative,
                [
                    'work_id' => $c,
                    'ordinal' => 1,
                    'reservation_digest' => $h0,
                    'previous_step_ordinal' => null,
                    'previous_step_digest' => $h0,
                    'authorizing_policy_digest' => $h1,
                    'phase' => 'preparation_bound',
                    'step_digest' => $h2,
                ],
                '{"authorizing_policy_digest":"'
                    . $h1
                    . '","ordinal":1,"phase":"preparation_bound","previous_step_digest":"'
                    . $h0
                    . '","previous_step_ordinal":null,"reservation_digest":"'
                    . $h0
                    . '","step_digest":"'
                    . $h2
                    . '","work_id":"'
                    . $c
                    . '"}',
            ],
            'PreparationNative' => [
                AuditHistoryCheckpointTag::PreparationNative,
                [
                    'checkpoint_id' => $b,
                    'partition_id' => 'system:global',
                    'segment_id' => $a,
                    'closed_state_version' => 2,
                    'close_fencing_token' => $fencing,
                    'signing_key_fingerprint' => $h2,
                ],
                '{"checkpoint_id":"'
                    . $b
                    . '","close_fencing_token":"'
                    . $fencing
                    . '","closed_state_version":2,"partition_id":"system:global","segment_id":"'
                    . $a
                    . '","signing_key_fingerprint":"'
                    . $h2
                    . '"}',
            ],
            'CompleteCheckpointNative' => [
                AuditHistoryCheckpointTag::CompleteCheckpointNative,
                ['checkpoint_id' => $b, 'checkpoint_hash' => $h2],
                '{"checkpoint_hash":"' . $h2 . '","checkpoint_id":"' . $b . '"}',
            ],
            'CheckpointEnd' => [
                AuditHistoryCheckpointTag::CheckpointEnd,
                [
                    'work_id' => $c,
                    'reservation_digest' => $h0,
                    'last_ordinal' => 3,
                    'last_step_digest' => $h2,
                    'preparation_checkpoint_id' => $b,
                    'unsigned_sha256' => $h0,
                    'unsigned_length' => 1,
                    'candidate_sha256' => $h1,
                    'candidate_length' => 1,
                    'complete_sha256' => $h2,
                    'complete_length' => 1,
                    'higher_work_ordinal_present' => false,
                ],
                '{"candidate_length":1,"candidate_sha256":"'
                    . $h1
                    . '","complete_length":1,"complete_sha256":"'
                    . $h2
                    . '","higher_work_ordinal_present":false,"last_ordinal":3,"last_step_digest":"'
                    . $h2
                    . '","preparation_checkpoint_id":"'
                    . $b
                    . '","reservation_digest":"'
                    . $h0
                    . '","unsigned_length":1,"unsigned_sha256":"'
                    . $h0
                    . '","work_id":"'
                    . $c
                    . '"}',
            ],
            'PublishedSegmentsBegin' => [
                AuditHistoryTag::PublishedSegmentsBegin,
                ['tail_digest' => $h0, 'requested_binding_digest' => $h1],
                '{"requested_binding_digest":"' . $h1 . '","tail_digest":"' . $h0 . '"}',
            ],
            'PublishedSegmentNative' => [
                AuditHistoryTag::PublishedSegmentNative,
                [
                    'installation_ref' => 'install-main',
                    'binding_digest' => $h0,
                    'policy_digest' => $h1,
                    'partition_id' => 'system:global',
                    'segment_id' => $a,
                    'first_sequence' => 1,
                    'last_sequence' => 1,
                    'successor_segment_id' => $b,
                    'previous_binding_digest' => null,
                ],
                '{"binding_digest":"'
                    . $h0
                    . '","first_sequence":1,"installation_ref":"install-main","last_sequence":1,"partition_id":"system:global","policy_digest":"'
                    . $h1
                    . '","previous_binding_digest":null,"segment_id":"'
                    . $a
                    . '","successor_segment_id":"'
                    . $b
                    . '"}',
            ],
            'PublishedSegmentsEnd' => [
                AuditHistoryTag::PublishedSegmentsEnd,
                ['oldest_binding_digest' => $h0],
                '{"oldest_binding_digest":"' . $h0 . '"}',
            ],
            'StagedSegmentsBegin' => [
                AuditHistoryTag::StagedSegmentsBegin,
                [
                    'published_tail_digest' => $h0,
                    'staged_tail_digest' => $h1,
                    'actual_current_segment_id' => $a,
                    'actual_current_first_sequence' => 2,
                    'actual_current_predecessor_record_hash' => $h2,
                ],
                '{"actual_current_first_sequence":2,"actual_current_predecessor_record_hash":"'
                    . $h2
                    . '","actual_current_segment_id":"'
                    . $a
                    . '","published_tail_digest":"'
                    . $h0
                    . '","staged_tail_digest":"'
                    . $h1
                    . '"}',
            ],
            'StagedSegmentNative' => [
                AuditHistoryTag::StagedSegmentNative,
                [
                    'installation_ref' => 'install-main',
                    'binding_digest' => $h1,
                    'policy_digest' => $h2,
                    'partition_id' => 'system:global',
                    'segment_id' => $b,
                    'first_sequence' => 1,
                    'last_sequence' => 1,
                    'successor_segment_id' => $c,
                    'previous_binding_digest' => null,
                ],
                '{"binding_digest":"'
                    . $h1
                    . '","first_sequence":1,"installation_ref":"install-main","last_sequence":1,"partition_id":"system:global","policy_digest":"'
                    . $h2
                    . '","previous_binding_digest":null,"segment_id":"'
                    . $b
                    . '","successor_segment_id":"'
                    . $c
                    . '"}',
            ],
            'StagedSegmentsEnd' => [
                AuditHistoryTag::StagedSegmentsEnd,
                ['reached_published_tail_digest' => $h0, 'staged_tip_next_binding_digest' => null],
                '{"reached_published_tail_digest":"' . $h0 . '","staged_tip_next_binding_digest":null}',
            ],
            'TransitionsBegin' => [
                AuditHistoryControlTag::TransitionsBegin,
                ['tail_digest' => $h0, 'acknowledged_transition_digest' => $h1],
                '{"acknowledged_transition_digest":"' . $h1 . '","tail_digest":"' . $h0 . '"}',
            ],
            'TransitionNative' => [
                AuditHistoryControlTag::TransitionNative,
                [
                    'installation_ref' => 'install-main',
                    'transition_id' => $h0,
                    'transition_digest' => $h1,
                    'authorizing_policy_digest' => $h2,
                    'previous_transition_digest' => null,
                    'previous_policy_state_digest' => null,
                    'new_policy_state_digest' => $h0,
                    'cause' => 'root_revision',
                ],
                '{"authorizing_policy_digest":"'
                    . $h2
                    . '","cause":"root_revision","installation_ref":"install-main","new_policy_state_digest":"'
                    . $h0
                    . '","previous_policy_state_digest":null,"previous_transition_digest":null,"transition_digest":"'
                    . $h1
                    . '","transition_id":"'
                    . $h0
                    . '"}',
            ],
            'TransitionsEnd' => [
                AuditHistoryControlTag::TransitionsEnd,
                ['oldest_transition_digest' => $h0],
                '{"oldest_transition_digest":"' . $h0 . '"}',
            ],
            'TransitionAckNative' => [
                AuditHistoryControlTag::TransitionAckNative,
                [
                    'installation_ref' => 'install-main',
                    'acknowledgement_digest' => $h0,
                    'authorizing_policy_digest' => $h1,
                    'transition_id' => $h2,
                    'transition_digest' => $h0,
                    'ingress_key' => 'ingress-main',
                ],
                '{"acknowledgement_digest":"'
                    . $h0
                    . '","authorizing_policy_digest":"'
                    . $h1
                    . '","ingress_key":"ingress-main","installation_ref":"install-main","transition_digest":"'
                    . $h0
                    . '","transition_id":"'
                    . $h2
                    . '"}',
            ],
            'PublicationsBegin' => [
                AuditHistoryControlTag::PublicationsBegin,
                ['tail_revision' => 1, 'tail_head_digest' => $h0],
                '{"tail_head_digest":"' . $h0 . '","tail_revision":1}',
            ],
            'PublicationNative' => [
                AuditHistoryControlTag::PublicationNative,
                [
                    'installation_ref' => 'install-main',
                    'revision' => 1,
                    'head_digest' => $h0,
                    'previous_revision' => null,
                    'previous_head_digest' => null,
                    'policy_digest' => $h1,
                    'policy_state_digest' => $h2,
                    'closed_segment_tail_digest' => null,
                    'transition_tail_digest' => $h0,
                    'acknowledged_transition_digest' => null,
                    'transition_ack_digest' => null,
                    'document_sha256' => $h1,
                ],
                '{"acknowledged_transition_digest":null,"closed_segment_tail_digest":null,"document_sha256":"'
                    . $h1
                    . '","head_digest":"'
                    . $h0
                    . '","installation_ref":"install-main","policy_digest":"'
                    . $h1
                    . '","policy_state_digest":"'
                    . $h2
                    . '","previous_head_digest":null,"previous_revision":null,"revision":1,"transition_ack_digest":null,"transition_tail_digest":"'
                    . $h0
                    . '"}',
            ],
            'PublicationsEnd' => [
                AuditHistoryControlTag::PublicationsEnd,
                ['bootstrap_head_digest' => $h0],
                '{"bootstrap_head_digest":"' . $h0 . '"}',
            ],
            'CandidatePublicationNative' => [
                AuditHistoryControlTag::CandidatePublicationNative,
                [
                    'installation_ref' => 'install-main',
                    'revision' => 2,
                    'head_digest' => $h1,
                    'previous_revision' => 1,
                    'previous_head_digest' => $h0,
                    'policy_digest' => $h2,
                    'policy_state_digest' => $h0,
                    'closed_segment_tail_digest' => $h1,
                    'transition_tail_digest' => $h2,
                    'acknowledged_transition_digest' => null,
                    'transition_ack_digest' => null,
                    'document_sha256' => $h2,
                ],
                '{"acknowledged_transition_digest":null,"closed_segment_tail_digest":"'
                    . $h1
                    . '","document_sha256":"'
                    . $h2
                    . '","head_digest":"'
                    . $h1
                    . '","installation_ref":"install-main","policy_digest":"'
                    . $h2
                    . '","policy_state_digest":"'
                    . $h0
                    . '","previous_head_digest":"'
                    . $h0
                    . '","previous_revision":1,"revision":2,"transition_ack_digest":null,"transition_tail_digest":"'
                    . $h2
                    . '"}',
            ],
            'PublicationEnd' => [
                AuditHistoryControlTag::PublicationEnd,
                [
                    'accepted_revision' => 1,
                    'accepted_head_digest' => $h0,
                    'next_revision' => 2,
                    'next_head_digest' => $h1,
                    'next_document_sha256' => $h2,
                    'higher_than_next_present' => false,
                    'staged_tip_binding_digest' => $h0,
                ],
                '{"accepted_head_digest":"'
                    . $h0
                    . '","accepted_revision":1,"higher_than_next_present":false,"next_document_sha256":"'
                    . $h2
                    . '","next_head_digest":"'
                    . $h1
                    . '","next_revision":2,"staged_tip_binding_digest":"'
                    . $h0
                    . '"}',
            ],
        ];
    }
}

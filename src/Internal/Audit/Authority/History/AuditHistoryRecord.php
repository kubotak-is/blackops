<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authority\History;

use InvalidArgumentException;

final class AuditHistoryRecord
{
    private const array SCALAR_TAGS = [
        1 => true,
        3 => true,
        5 => true,
        16 => true,
        17 => true,
        18 => true,
        19 => true,
        21 => true,
        23 => true,
        24 => true,
        25 => true,
        26 => true,
        28 => true,
        31 => true,
        33 => true,
        35 => true,
        48 => true,
        49 => true,
        51 => true,
        52 => true,
        53 => true,
        55 => true,
        64 => true,
        65 => true,
        67 => true,
        68 => true,
        72 => true,
        73 => true,
        75 => true,
        76 => true,
        79 => true,
    ];

    private const array EMPTY_TAGS = [6 => true, 20 => true, 22 => true, 70 => true, 78 => true];

    private \SensitiveParameterValue $snapshot;

    private function __construct(
        AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag $tag,
        #[\SensitiveParameter]
        string $payload,
        ?AuditHistoryNativeGroup $native,
    ) {
        $length = strlen($payload);
        $isScalar = array_key_exists($tag->value, self::SCALAR_TAGS);
        if ($isScalar !== ($native !== null)) {
            throw new InvalidArgumentException('History record projection is invalid.');
        }
        if ($native !== null && $native->tag() !== $tag) {
            throw new InvalidArgumentException('History record projection is invalid.');
        }

        $maximum = match ($tag) {
            AuditHistoryTag::FloorHint => 4_096,
            AuditHistoryTag::AcceptedAuthorityBytes,
            AuditHistoryControlTag::PublicationDocument,
            AuditHistoryControlTag::CandidatePublicationDocument,
                => 1_048_576,
            AuditHistoryCheckpointTag::ReservationEnvelope,
            AuditHistoryCheckpointTag::WorkStepEnvelope,
            AuditHistoryTag::PublishedSegmentEnvelope,
            AuditHistoryTag::StagedSegmentEnvelope,
            AuditHistoryControlTag::TransitionEnvelope,
            AuditHistoryControlTag::TransitionAckEnvelope,
                => 65_536,
            AuditHistoryCheckpointTag::CompleteCandidateBytes,
            AuditHistoryCheckpointTag::PreparedUnsignedBytes,
            AuditHistoryCheckpointTag::PersistedCompleteBytes,
                => 1_048_575,
            AuditHistoryTag::Trailer => 48,
            default => 16_384,
        };
        if ($length > $maximum) {
            throw new InvalidArgumentException('History record exceeds its byte bound.');
        }

        if ($tag === AuditHistoryTag::Trailer && $length !== 48) {
            throw new InvalidArgumentException('History trailer is invalid.');
        }
        if (array_key_exists($tag->value, self::EMPTY_TAGS) && $length !== 0) {
            throw new InvalidArgumentException('History absence record is invalid.');
        }
        if ($tag !== AuditHistoryTag::Trailer && !array_key_exists($tag->value, self::EMPTY_TAGS) && $length === 0) {
            throw new InvalidArgumentException('History record cannot be empty.');
        }

        $this->snapshot = new \SensitiveParameterValue([
            'tag' => $tag,
            'payload' => $payload,
            'native' => $native,
        ]);
    }

    public static function fromCapture(
        AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag $tag,
        #[\SensitiveParameter]
        string $payload,
        ?AuditHistoryNativeGroup $native,
    ): self {
        return new self($tag, $payload, $native);
    }

    public static function fromRead(
        AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag $tag,
        #[\SensitiveParameter]
        string $payload,
        ?AuditHistoryNativeGroup $native,
    ): self {
        return new self($tag, $payload, $native);
    }

    public function tag(): AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag
    {
        /** @var array{tag:AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag,payload:string,native:?AuditHistoryNativeGroup} $snapshot */
        $snapshot = $this->snapshot->getValue();

        return $snapshot['tag'];
    }

    public function bytes(): string
    {
        /** @var array{tag:AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag,payload:string,native:?AuditHistoryNativeGroup} $snapshot */
        $snapshot = $this->snapshot->getValue();

        return $snapshot['payload'];
    }

    public function native(): ?AuditHistoryNativeGroup
    {
        /** @var array{tag:AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag,payload:string,native:?AuditHistoryNativeGroup} $snapshot */
        $snapshot = $this->snapshot->getValue();

        return $snapshot['native'];
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        throw new InvalidArgumentException('History record cannot be serialized.');
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw new InvalidArgumentException('History record cannot be unserialized.');
    }

    private function __clone(): void
    {
        throw new InvalidArgumentException('History record cannot be cloned.');
    }
}

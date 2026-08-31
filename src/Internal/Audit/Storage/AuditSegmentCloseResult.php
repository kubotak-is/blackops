<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Storage;

/**
 * The result retains the complete close snapshot while exposing typed access
 * and serialization guards for the immutable boundary.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class AuditSegmentCloseResult
{
    private \SensitiveParameterValue $snapshot;

    /**
     * The close transition carries each persisted field so accessors can
     * validate the captured snapshot without exposing mutable state.
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public function __construct(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $closedSegmentId,
        #[\SensitiveParameter]
        string $successorSegmentId,
        int $firstSequence,
        int $finalSequence,
        #[\SensitiveParameter]
        ?string $predecessorRecordHash,
        #[\SensitiveParameter]
        string $headHash,
        #[\SensitiveParameter]
        string $closeFencingToken,
        string $closedAt,
        bool $changed,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'partitionId' => $partitionId,
            'closedSegmentId' => $closedSegmentId,
            'successorSegmentId' => $successorSegmentId,
            'firstSequence' => $firstSequence,
            'finalSequence' => $finalSequence,
            'predecessorRecordHash' => $predecessorRecordHash,
            'headHash' => $headHash,
            'closeFencingToken' => $closeFencingToken,
            'closedAt' => $closedAt,
            'changed' => $changed,
        ]);
    }

    public function partitionId(): string
    {
        return $this->stringValue('partitionId');
    }

    public function closedSegmentId(): string
    {
        return $this->stringValue('closedSegmentId');
    }

    public function successorSegmentId(): string
    {
        return $this->stringValue('successorSegmentId');
    }

    public function firstSequence(): int
    {
        return $this->intValue('firstSequence');
    }

    public function finalSequence(): int
    {
        return $this->intValue('finalSequence');
    }

    public function predecessorRecordHash(): ?string
    {
        $value = $this->value('predecessorRecordHash');
        if ($value !== null && !is_string($value)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $value;
    }

    public function headHash(): string
    {
        return $this->stringValue('headHash');
    }

    public function closeFencingToken(): string
    {
        return $this->stringValue('closeFencingToken');
    }

    public function closedAt(): string
    {
        return $this->stringValue('closedAt');
    }

    public function changed(): bool
    {
        $value = $this->value('changed');
        if (!is_bool($value)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $value;
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
    }

    private function stringValue(string $key): string
    {
        $value = $this->value($key);
        if (!is_string($value)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $value;
    }

    private function intValue(string $key): int
    {
        $value = $this->value($key);
        if (!is_int($value)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $value;
    }

    private function value(string $key): mixed
    {
        try {
            $snapshot = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if (!is_array($snapshot) || !array_key_exists($key, $snapshot)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $snapshot[$key];
    }
}

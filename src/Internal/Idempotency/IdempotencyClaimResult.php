<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

final readonly class IdempotencyClaimResult
{
    public function __construct(
        private IdempotencyClaimStatus $status,
        private ProcessingRecord|TerminalRecord $record,
    ) {
        if ($status === IdempotencyClaimStatus::Claimed && !$record instanceof ProcessingRecord) {
            throw new \InvalidArgumentException('A claimed result must contain a processing record.');
        }
    }

    public function status(): IdempotencyClaimStatus
    {
        return $this->status;
    }

    public function claimed(): bool
    {
        return $this->status === IdempotencyClaimStatus::Claimed;
    }

    public function existingSameFingerprint(): bool
    {
        return $this->status === IdempotencyClaimStatus::ExistingSameFingerprint;
    }

    public function existingConflict(): bool
    {
        return $this->status === IdempotencyClaimStatus::ExistingConflict;
    }

    public function record(): ProcessingRecord|TerminalRecord
    {
        return $this->record;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Transport\InMemory;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationClaim;
use DateInterval;
use DateTimeImmutable;

final class InMemoryOperationRecord
{
    private InMemoryOperationState $state = InMemoryOperationState::Available;

    private int $claimSequence = 0;

    private ?DateTimeImmutable $leaseExpiresAt = null;

    public function __construct(
        private DeferredOperationMessage $message,
    ) {}

    public function operationId(): string
    {
        return $this->message->operationId()->toString();
    }

    public function availableAt(): DateTimeImmutable
    {
        return $this->message->availableAt();
    }

    public function isEligible(DateTimeImmutable $claimedAt): bool
    {
        return match ($this->state) {
            InMemoryOperationState::Available => $this->availableAt() <= $claimedAt,
            InMemoryOperationState::Claimed => $this->leaseExpiresAt !== null && $this->leaseExpiresAt <= $claimedAt,
            InMemoryOperationState::Settled => false,
        };
    }

    public function claim(DateTimeImmutable $claimedAt, DateInterval $leaseDuration): OperationClaim
    {
        if (!$this->isEligible($claimedAt)) {
            throw new DeferredTransportException('In-memory deferred operation is not eligible for claim.');
        }

        $this->state = InMemoryOperationState::Claimed;
        ++$this->claimSequence;
        $this->leaseExpiresAt = $claimedAt->add($leaseDuration);

        return new OperationClaim($this->message, $this->claimToken());
    }

    public function heartbeat(
        OperationClaim $claim,
        DateTimeImmutable $heartbeatAt,
        DateInterval $leaseDuration,
    ): OperationClaim {
        $this->assertCurrent($claim, $heartbeatAt);
        $this->leaseExpiresAt = $heartbeatAt->add($leaseDuration);

        return $claim;
    }

    public function acknowledge(OperationClaim $claim, DateTimeImmutable $acknowledgedAt): void
    {
        $this->assertCurrent($claim, $acknowledgedAt);
        $this->state = InMemoryOperationState::Settled;
        $this->leaseExpiresAt = null;
    }

    public function release(OperationClaim $claim, DateTimeImmutable $releasedAt, DateTimeImmutable $availableAt): void
    {
        $this->assertCurrent($claim, $releasedAt);
        $this->message = new DeferredOperationMessage(
            $this->message->operationId(),
            $this->message->operationType(),
            $this->message->schemaVersion(),
            $this->message->encodedPayload(),
            $this->message->encodedContext(),
            $availableAt,
        );
        $this->state = InMemoryOperationState::Available;
        $this->leaseExpiresAt = null;
    }

    private function assertCurrent(OperationClaim $claim, DateTimeImmutable $operatedAt): void
    {
        if ($this->state === InMemoryOperationState::Settled) {
            throw new DeferredTransportException('In-memory deferred operation claim is already settled.');
        }

        if (
            $this->state !== InMemoryOperationState::Claimed
            || !hash_equals($this->claimToken(), $claim->claimToken())
            || $this->leaseExpiresAt === null
            || $this->leaseExpiresAt <= $operatedAt
        ) {
            throw new DeferredTransportException('In-memory deferred operation claim is stale.');
        }
    }

    private function claimToken(): string
    {
        return $this->operationId() . ':' . $this->claimSequence;
    }
}

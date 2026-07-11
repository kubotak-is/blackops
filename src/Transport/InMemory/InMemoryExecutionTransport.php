<?php

declare(strict_types=1);

namespace BlackOps\Transport\InMemory;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\ExecutionTransport;
use BlackOps\Core\Execution\OperationClaim;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

final class InMemoryExecutionTransport implements ExecutionTransport
{
    /** @var array<string, InMemoryOperationRecord> */
    private array $records = [];

    private DateInterval $leaseDuration;

    public function __construct(
        private readonly ClockInterface $clock,
        int $leaseSeconds,
    ) {
        if ($leaseSeconds < 1) {
            throw new InvalidArgumentException('In-memory lease duration must be positive.');
        }

        $this->leaseDuration = new DateInterval('PT' . $leaseSeconds . 'S');
    }

    public function enqueue(DeferredOperationMessage $message): DeferredAcknowledgement
    {
        $operationId = $message->operationId()->toString();

        if (array_key_exists($operationId, $this->records)) {
            throw new DeferredTransportException('In-memory deferred operation ID is already enqueued.');
        }

        $this->records[$operationId] = new InMemoryOperationRecord($message);

        return new DeferredAcknowledgement($message->operationId(), $this->clock->now());
    }

    public function claim(ClaimRequest $request): ?OperationClaim
    {
        $eligible = array_values(array_filter(
            $this->records,
            static fn(InMemoryOperationRecord $record): bool => $record->isEligible($request->claimedAt()),
        ));

        if ($eligible === []) {
            return null;
        }

        usort($eligible, $this->compare(...));

        return $eligible[0]->claim($request->claimedAt(), $this->leaseDuration);
    }

    public function heartbeat(OperationClaim $claim): OperationClaim
    {
        return $this->record($claim)->heartbeat($claim, $this->clock->now(), $this->leaseDuration);
    }

    public function acknowledge(OperationClaim $claim): void
    {
        $this->record($claim)->acknowledge($claim, $this->clock->now());
    }

    public function release(OperationClaim $claim, DateTimeImmutable $availableAt): void
    {
        $this->record($claim)->release($claim, $this->clock->now(), $availableAt);
    }

    private function compare(InMemoryOperationRecord $left, InMemoryOperationRecord $right): int
    {
        $availableOrder = $left->availableAt() <=> $right->availableAt();

        return $availableOrder !== 0 ? $availableOrder : strcmp($left->operationId(), $right->operationId());
    }

    private function record(OperationClaim $claim): InMemoryOperationRecord
    {
        $operationId = $claim->message()->operationId()->toString();

        if (!array_key_exists($operationId, $this->records)) {
            throw new DeferredTransportException('In-memory deferred operation claim is unknown.');
        }

        return $this->records[$operationId];
    }
}

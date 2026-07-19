<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Outcome;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

#[PublicApi]
final readonly class OperationStatus
{
    private function __construct(
        private OperationId $operationId,
        private string $operationType,
        private OperationStatusState $state,
        private ?int $attempt = null,
        private ?DateTimeImmutable $retryAt = null,
        private ?Outcome $outcome = null,
        private ?OperationStatusError $error = null,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $operationType)) {
            throw new InvalidArgumentException('Operation status requires a valid operation type.');
        }

        if ($attempt !== null && $attempt < 1) {
            throw new InvalidArgumentException('Operation status attempt must be greater than or equal to one.');
        }
    }

    public static function accepted(OperationId $operationId, string $operationType): self
    {
        return new self($operationId, $operationType, OperationStatusState::Accepted);
    }

    public static function running(OperationId $operationId, string $operationType, int $attempt): self
    {
        return new self($operationId, $operationType, OperationStatusState::Running, attempt: $attempt);
    }

    public static function retryScheduled(
        OperationId $operationId,
        string $operationType,
        int $attempt,
        DateTimeImmutable $retryAt,
    ): self {
        return new self(
            $operationId,
            $operationType,
            OperationStatusState::RetryScheduled,
            $attempt,
            $retryAt->setTimezone(new DateTimeZone('UTC')),
        );
    }

    public static function completed(
        OperationId $operationId,
        string $operationType,
        Outcome $outcome = new EmptyOutcome(),
    ): self {
        return new self($operationId, $operationType, OperationStatusState::Completed, outcome: $outcome);
    }

    public static function rejected(
        OperationId $operationId,
        string $operationType,
        string $category,
        string $code,
    ): self {
        return new self(
            $operationId,
            $operationType,
            OperationStatusState::Rejected,
            error: OperationStatusError::rejected($category, $code),
        );
    }

    public static function failed(OperationId $operationId, string $operationType): self
    {
        return new self(
            $operationId,
            $operationType,
            OperationStatusState::Failed,
            error: OperationStatusError::failed(),
        );
    }

    public static function deadLettered(OperationId $operationId, string $operationType): self
    {
        return new self(
            $operationId,
            $operationType,
            OperationStatusState::DeadLettered,
            error: OperationStatusError::deadLettered(),
        );
    }

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function operationType(): string
    {
        return $this->operationType;
    }

    public function state(): OperationStatusState
    {
        return $this->state;
    }

    public function attempt(): ?int
    {
        return $this->attempt;
    }

    public function retryAt(): ?DateTimeImmutable
    {
        return $this->retryAt;
    }

    public function outcome(): ?Outcome
    {
        return $this->outcome;
    }

    public function error(): ?OperationStatusError
    {
        return $this->error;
    }
}

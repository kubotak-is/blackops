<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationEnvelope;
use RuntimeException;
use Throwable;

final class OperationExecutionFailed extends RuntimeException
{
    public function __construct(
        private readonly OperationEnvelope $envelope,
        private readonly string $operationType,
        private readonly Throwable $primaryFailure,
        private readonly bool $journalRecorded,
        private readonly ?Throwable $recordingFailure = null,
    ) {
        parent::__construct('Operation execution failed.', previous: $primaryFailure);
    }

    public function operationId(): OperationId
    {
        return $this->envelope->id();
    }

    public function envelope(): OperationEnvelope
    {
        return $this->envelope;
    }

    public function operationType(): string
    {
        return $this->operationType;
    }

    public function primaryFailure(): Throwable
    {
        return $this->primaryFailure;
    }

    public function journalRecorded(): bool
    {
        return $this->journalRecorded;
    }

    public function recordingFailure(): ?Throwable
    {
        return $this->recordingFailure;
    }
}

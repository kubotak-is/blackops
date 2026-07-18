<?php

declare(strict_types=1);

namespace BlackOps\Internal\Logging;

use BlackOps\Core\OperationEnvelope;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\OperationExecutionFailed;
use Throwable;

final readonly class FrameworkOperationFailureReporter
{
    public function __construct(
        private ExecutionScopedLogger $logger,
        private ExecutionScopeProvider $scope,
    ) {}

    public function report(OperationExecutionFailed $failure): void
    {
        $this->reportThrowable(
            $failure->envelope(),
            $failure->operationType(),
            $failure->primaryFailure(),
            $failure->journalRecorded(),
            $failure->recordingFailure(),
        );
    }

    public function reportThrowable(
        OperationEnvelope $envelope,
        string $operationType,
        Throwable $failure,
        bool $journalRecorded,
        ?Throwable $recordingFailure = null,
    ): void {
        $this->scope->run(
            $envelope,
            function () use ($failure, $journalRecorded, $recordingFailure): void {
                $this->logger->frameworkError(
                    $failure::class,
                    $journalRecorded,
                    $recordingFailure === null ? null : $recordingFailure::class,
                );
            },
            $operationType,
        );
    }
}

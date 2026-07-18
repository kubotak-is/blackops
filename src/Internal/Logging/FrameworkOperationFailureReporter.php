<?php

declare(strict_types=1);

namespace BlackOps\Internal\Logging;

use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\OperationExecutionFailed;

final readonly class FrameworkOperationFailureReporter
{
    public function __construct(
        private ExecutionScopedLogger $logger,
        private ExecutionScopeProvider $scope,
    ) {}

    public function report(OperationExecutionFailed $failure): void
    {
        $recordingFailure = $failure->recordingFailure();

        $this->scope->run(
            $failure->envelope(),
            function () use ($failure, $recordingFailure): void {
                $this->logger->frameworkError(
                    $failure->primaryFailure()::class,
                    $failure->journalRecorded(),
                    $recordingFailure === null ? null : $recordingFailure::class,
                );
            },
            $failure->operationType(),
        );
    }
}

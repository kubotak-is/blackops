<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Execution\ExecutionScopeProvider;
use Closure;
use LogicException;
use Throwable;

/** @mago-expect lint:no-empty-catch-clause */
final readonly class ApplicationOperationInvocationLifecycle
{
    public function __construct(
        private ExecutionScopeProvider $scope,
        private ApplicationDatabaseConnectionLifecycle $connection,
        private ?ApplicationJournalObservations $observations,
    ) {}

    /**
     * @template TResult
     * @param Closure(): TResult $invoke
     * @param Closure(TResult): bool $failed
     * @return TResult
     */
    public function run(Closure $invoke, Closure $failed): mixed
    {
        $prepared = false;
        $stateFinished = false;
        $connectionFinished = false;
        try {
            $this->connection->prepare();
            $prepared = true;
            $result = $invoke();
            $stateFinished = true;
            $this->finishState();
            if ($failed($result)) {
                $connectionFinished = true;
                $this->connection->finishFailedInvocation();

                return $result;
            }
            $connectionFinished = true;
            $this->connection->finishSuccessfulInvocation();

            return $result;
        } catch (Throwable $primary) {
            if (!$stateFinished) {
                try {
                    $this->finishState();
                } catch (Throwable) {
                }
            }
            if ($prepared && !$connectionFinished) {
                try {
                    $this->connection->finishFailedInvocation();
                } catch (Throwable) {
                }
            }

            throw $primary;
        }
    }

    private function finishState(): void
    {
        if ($this->scope->current() !== null || $this->scope->currentOperationTypeId() !== null) {
            throw new LogicException('Application invocation left an operation scope active.');
        }
        $this->observations?->flush();
    }
}

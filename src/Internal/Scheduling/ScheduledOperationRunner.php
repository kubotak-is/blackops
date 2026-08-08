<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class ScheduledOperationRunner implements ScheduledOperationRunService
{
    public function __construct(
        private OperationRegistry $operations,
        private PostgreSqlScheduleStore $store,
        private ScheduleEvaluator $evaluator,
        private ScheduledOperationRuntime $runtime,
        private ScheduledOperationDefinitionResolver $definitions,
        private PostgreSqlScheduledOccurrenceLifecycle $occurrences,
        private ClockInterface $clock,
        private ?TelemetryTracer $telemetry = null,
    ) {}

    public function run(): ScheduledOperationRunResult
    {
        $span = $this->telemetry?->start('blackops.operation.schedule.evaluate', attributes: [
            'blackops.runtime.kind' => 'scheduler',
        ]);
        try {
            $result = $this->runSchedules();
            $span?->result($result->failed === 0 ? 'completed' : 'failed');
            return $result;
        } catch (Throwable $failure) {
            $span?->fail($failure);
            throw $failure;
        } finally {
            $span?->end();
        }
    }

    private function runSchedules(): ScheduledOperationRunResult
    {
        $evaluated = 0;
        $accepted = 0;
        $skippedMisfire = 0;
        $skippedOverlap = 0;
        $failed = 0;
        $scheduled = array_values(array_filter(
            $this->operations->all(),
            static fn(OperationMetadata $metadata): bool => $metadata->schedule !== null,
        ));
        usort($scheduled, static fn(OperationMetadata $left, OperationMetadata $right): int => strcmp(
            $left->schedule->name ?? '',
            $right->schedule->name ?? '',
        ));

        foreach ($scheduled as $metadata) {
            ++$evaluated;
            $scheduleName = $metadata->schedule?->name;
            if ($scheduleName === null) {
                ++$failed;
                continue;
            }

            try {
                $this->store->withScheduleLock($scheduleName, function () use (
                    $metadata,
                    $scheduleName,
                    &$accepted,
                    &$failed,
                    &$skippedMisfire,
                    &$skippedOverlap,
                ): void {
                    foreach ($this->store->recoverClaimed($scheduleName) as $occurrence) {
                        $this->invoke($metadata, $occurrence, $accepted, $failed);
                    }

                    try {
                        $evaluation = $this->evaluator->evaluate($metadata);
                    } catch (Throwable) {
                        ++$failed;
                        return;
                    }

                    foreach ($evaluation->occurrences as $occurrence) {
                        if ($occurrence->state === 'skipped_misfire') {
                            ++$skippedMisfire;
                            continue;
                        }
                        if ($occurrence->state === 'skipped_overlap') {
                            ++$skippedOverlap;
                            continue;
                        }
                        if ($occurrence->state === 'claimed') {
                            $this->invoke($metadata, $occurrence, $accepted, $failed);
                        }
                    }
                });
            } catch (Throwable) {
                // A connection or lock failure must not expose its details or abort
                // processing of the remaining schedules.
                ++$failed;
            }
        }

        return new ScheduledOperationRunResult($evaluated, $accepted, $skippedMisfire, $skippedOverlap, $failed);
    }

    /** @param-out int $accepted @param-out int $failed */
    private function invoke(
        OperationMetadata $metadata,
        ScheduleOccurrence $occurrence,
        int &$accepted,
        int &$failed,
    ): void {
        try {
            $definition = $this->definitions->resolve($metadata);
            $result = $this->runtime->invoke($metadata, $definition, $occurrence);
            if ($result instanceof DeferredAcknowledgement) {
                $this->completeIfStillClaimed($occurrence, 'accepted');
                ++$accepted;
                return;
            }
            if ($result->isRejected()) {
                $this->completeIfStillClaimed($occurrence, 'rejected', $result->rejectionReason()->code());
                ++$failed;
            } else {
                $this->completeIfStillClaimed($occurrence, 'completed');
                ++$accepted;
            }
        } catch (Throwable) {
            $this->recordInvocationFailure($occurrence);
            ++$failed;
        }
    }

    private function completeIfStillClaimed(
        ScheduleOccurrence $occurrence,
        string $targetState,
        ?string $category = null,
    ): void {
        if ($occurrence->operationId === null) {
            throw new \RuntimeException('Scheduled invocation has no operation identity.');
        }

        $current = $this->store->occurrence($occurrence->scheduleName, $occurrence->scheduledAt);
        if ($current === null || $current->state !== 'claimed') {
            return;
        }

        $this->occurrences->transition(
            $occurrence->operationId,
            'claimed',
            $targetState,
            $category,
            $this->clock->now(),
        );
    }

    private function recordInvocationFailure(ScheduleOccurrence $occurrence): void
    {
        if ($occurrence->operationId === null) {
            throw new \RuntimeException('Scheduled invocation has no operation identity.');
        }

        $current = $this->store->occurrence($occurrence->scheduleName, $occurrence->scheduledAt);
        if ($current === null) {
            throw new \RuntimeException('Scheduled invocation occurrence could not be reloaded.');
        }
        if ($current->state !== 'claimed') {
            return;
        }

        $this->occurrences->transition(
            $occurrence->operationId,
            'claimed',
            'failed',
            'scheduled_invocation_failed',
            $this->clock->now(),
        );
    }
}

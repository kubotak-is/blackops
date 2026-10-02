<?php

declare(strict_types=1);

namespace BlackOps\Internal\Replay;

use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Telemetry\TelemetryMetrics;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRequest;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBinding;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplaySelector;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayStore;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Coordinates guarded replay delivery and terminal checkpoint handling.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class ObserverReplayRuntime
{
    private const CURSOR_PERSISTENCE_FAILURE = 'Observer replay cursor persistence failed.';

    public function __construct(
        private PostgreSqlObserverReplayStore $store,
        private ObserverReplayTargetRegistry $targets,
        private ObservedJournalRecordProjector $projector,
        private int $batchSize = 100,
        private ?TelemetryTracer $telemetry = null,
        private ?TelemetryMetrics $metrics = null,
    ) {
        if ($batchSize < 1 || $batchSize > 1000) {
            throw new InvalidArgumentException('Replay batch size must be between 1 and 1000.');
        }
    }

    /** @param list<string> $targetNames */
    public function dryRun(
        PostgreSqlObserverReplaySelector $selector,
        array $targetNames,
        ?int $batchSize = null,
    ): ObserverReplayResult {
        $this->targets->resolve($targetNames);
        $batch = $this->store->select($selector, $batchSize ?? $this->batchSize, null);
        $ids = array_map(
            static fn(\BlackOps\Journal\JournalRecord $record): string => $record->recordId->toString(),
            $batch['records'],
        );
        return new ObserverReplayResult(count($ids), 0, 0, $batch['hasMore'], !$batch['hasMore'], $ids);
    }

    public function replay(#[\SensitiveParameter] ObserverReplayRequest $request): ObserverReplayResult
    {
        $span = $this->telemetry?->start('blackops.observer.replay', attributes: [
            'blackops.runtime.kind' => 'observer_replay',
        ]);
        try {
            $this->validateRequest($request);
            /** @var list<string> $targetNames */
            $targetNames = $this->normaliseTargets($request->targetNames);
            /** @var list<JournalObserverBinding> $targets */
            $targets = $this->targets->resolve($targetNames);
            $binding = $this->store->begin(
                new PostgreSqlObserverReplayBeginRequest(
                    $request->checkpoint,
                    $request->selector,
                    $targetNames,
                    $request->actor,
                    $request->reason,
                    $request->now,
                ),
            );
            ['result' => $result, 'failure' => $failure] = $this->deliver($request, $targets, $binding);
            if ($failure === null && $result === null) {
                $failure = new JournalObservationFailed('Observer replay did not produce a result.');
            }
            $finalizationFailure = $failure === null ? $this->finalize($request, $result, $binding->auditId) : null;
            $unlockFailure = $this->release($request->checkpoint);
            if ($failure !== null) {
                throw $failure;
            }
            if ($finalizationFailure !== null) {
                throw $finalizationFailure;
            }
            if ($unlockFailure !== null) {
                throw $unlockFailure;
            }
            if ($result === null) {
                throw new JournalObservationFailed('Observer replay did not produce a result.');
            }
            $span?->result($result->failed > 0 ? 'failed' : 'completed');
            return $result;
        } catch (Throwable $failure) {
            $span?->fail($failure);
            $this->metrics?->observerFailure('replay', 'replay_failed');
            throw $failure;
        } finally {
            $span?->end();
        }
    }

    public function resume(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $actor,
        #[\SensitiveParameter]
        string $reason,
        #[\SensitiveParameter]
        ?int $batchSize = null,
        #[\SensitiveParameter]
        ?DateTimeImmutable $now = null,
    ): ObserverReplayResult {
        $binding = $this->store->load($checkpoint);
        return $this->replay(
            new ObserverReplayRequest(
                $binding->selector,
                $binding->targets,
                $checkpoint,
                $actor,
                $reason,
                $now,
                $batchSize,
            ),
        );
    }

    /**
     * @param list<JournalObserverBinding> $targets
     * @return array{result: ?ObserverReplayResult, failure: ?Throwable}
     */
    private function deliver(
        #[\SensitiveParameter]
        ObserverReplayRequest $request,
        #[\SensitiveParameter]
        array $targets,
        #[\SensitiveParameter]
        PostgreSqlObserverReplayBinding $binding,
    ): array {
        try {
            $batch = $this->store->select(
                $request->selector,
                $request->batchSize ?? $this->batchSize,
                $binding->cursor,
            );
        } catch (Throwable $exception) {
            return [
                'result' => null,
                'failure' => $this->recordFailure($request->checkpoint, $exception, $request->now, $binding->auditId),
            ];
        }

        $ids = [];
        foreach ($batch['records'] as $record) {
            try {
                $observed = $this->projector->project($record);
                foreach ($targets as $target) {
                    $this->targets->observe($target, $observed);
                }
                foreach ($targets as $target) {
                    $this->targets->flush($target);
                }
            } catch (Throwable $exception) {
                return [
                    'result' => null,
                    'failure' => $this->recordFailure(
                        $request->checkpoint,
                        $exception,
                        $request->now,
                        $binding->auditId,
                    ),
                ];
            }

            try {
                $cursor = $this->store->cursorFor($request->selector, $record);
                $this->store->advanceSelected($request->checkpoint, $cursor, $binding->auditId, $record, $request->now);
            } catch (Throwable) {
                return [
                    'result' => null,
                    'failure' => new JournalObservationFailed(self::CURSOR_PERSISTENCE_FAILURE),
                ];
            }
            $ids[] = $record->recordId->toString();
        }

        return [
            'result' => new ObserverReplayResult(
                count($ids),
                count($ids),
                0,
                $batch['hasMore'],
                !$batch['hasMore'],
                $ids,
            ),
            'failure' => null,
        ];
    }

    private function validateRequest(ObserverReplayRequest $request): void
    {
        if (
            $request->hasUnsafeActorOrReason()
            || strlen($request->checkpoint) > 128
            || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $request->checkpoint) !== 1
        ) {
            throw new InvalidArgumentException('Replay actor, reason, and checkpoint must be non-empty.');
        }
        if ($request->batchSize !== null && ($request->batchSize < 1 || $request->batchSize > 1000)) {
            throw new InvalidArgumentException('Replay batch size must be between 1 and 1000.');
        }
    }

    /** @param list<string> $targetNames @return list<string> */
    private function normaliseTargets(array $targetNames): array
    {
        $targetNames = array_values(array_unique(array_map('strval', $targetNames)));
        sort($targetNames);
        return $targetNames;
    }

    private function recordFailure(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        Throwable $exception,
        #[\SensitiveParameter]
        ?DateTimeImmutable $now,
        #[\SensitiveParameter]
        string $auditId,
    ): Throwable {
        try {
            $this->store->fail($checkpoint, $exception, $now, $auditId);
        } catch (Throwable) {
            return new JournalObservationFailed('Observer replay delivery failed.');
        }
        return new JournalObservationFailed('Observer replay delivery failed.');
    }

    private function finalize(
        #[\SensitiveParameter]
        ObserverReplayRequest $request,
        #[\SensitiveParameter]
        ?ObserverReplayResult $result,
        #[\SensitiveParameter]
        string $auditId,
    ): ?Throwable {
        if ($result === null) {
            return null;
        }
        try {
            $this->store->finishInvocation(
                $request->checkpoint,
                $result->hasMore ? 'paused' : 'complete',
                $request->now,
                $auditId,
            );
        } catch (Throwable $exception) {
            return $exception;
        }
        return null;
    }

    private function release(#[\SensitiveParameter] string $checkpoint): ?Throwable
    {
        try {
            $this->store->unlock($checkpoint);
        } catch (Throwable $exception) {
            return $exception;
        }
        return null;
    }
}

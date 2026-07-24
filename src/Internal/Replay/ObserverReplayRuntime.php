<?php

declare(strict_types=1);

namespace BlackOps\Internal\Replay;

use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
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
    public function __construct(
        private PostgreSqlObserverReplayStore $store,
        private ObserverReplayTargetRegistry $targets,
        private ObservedJournalRecordProjector $projector,
        private int $batchSize = 100,
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

    /** @mago-expect lint:halstead */
    public function replay(ObserverReplayRequest $request): ObserverReplayResult
    {
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
        $result = null;
        $failure = null;
        try {
            $result = $this->deliver($request, $targets, $binding);
        } catch (Throwable $exception) {
            $failure = $this->recordFailure($request->checkpoint, $exception, $request->now, $binding->auditId);
        }
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
        return $result;
    }

    public function resume(
        string $checkpoint,
        string $actor,
        string $reason,
        ?int $batchSize = null,
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

    /** @param list<JournalObserverBinding> $targets */
    private function deliver(
        ObserverReplayRequest $request,
        array $targets,
        PostgreSqlObserverReplayBinding $binding,
    ): ObserverReplayResult {
        $cursor = $binding->cursor;
        $batch = $this->store->select($request->selector, $request->batchSize ?? $this->batchSize, $cursor);
        $ids = [];
        foreach ($batch['records'] as $record) {
            $observed = $this->projector->project($record);
            foreach ($targets as $target) {
                $this->targets->observe($target, $observed);
            }
            foreach ($targets as $target) {
                $this->targets->flush($target);
            }
            $ids[] = $record->recordId->toString();
            $this->store->advance(
                $request->checkpoint,
                $this->store->cursorFor($request->selector, $record),
                1,
                $binding->auditId,
                $request->now,
            );
        }
        return new ObserverReplayResult(count($ids), count($ids), 0, $batch['hasMore'], !$batch['hasMore'], $ids);
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
        string $checkpoint,
        Throwable $exception,
        ?DateTimeImmutable $now,
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
        ObserverReplayRequest $request,
        ?ObserverReplayResult $result,
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

    private function release(string $checkpoint): ?Throwable
    {
        try {
            $this->store->unlock($checkpoint);
        } catch (Throwable $exception) {
            return $exception;
        }
        return null;
    }
}

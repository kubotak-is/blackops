<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\Time\TimeCodec;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Journal\CanonicalJournalReader;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Exception\JournalReadFailed;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\Outcome\Exception\OutcomeStoreException;
use BlackOps\Outcome\OutcomeReader;
use BlackOps\Outcome\OutcomeRecord;
use Doctrine\DBAL\Exception as DbalException;
use PDOException;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class OperationDiagnosticsQuery
{
    public function __construct(
        private CanonicalJournalReader $journal,
        private OutcomeReader $outcomes,
        private DiagnosticsSourceReader $sources,
        private LifecycleStateMachine $lifecycle = new LifecycleStateMachine(),
        private DiagnosticsSafeProjector $projector = new DiagnosticsSafeProjector(),
        private TimeCodec $time = new TimeCodec(),
    ) {}

    public function find(OperationId $operationId): OperationDiagnosticsResult
    {
        try {
            $state = $this->sources->deferredState($operationId);
            $deadLetter = $this->sources->deadLetter($operationId);
            $audits = $this->sources->purgeAudits($operationId);
            $records = $this->records($operationId);
        } catch (OperationDiagnosticsException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw OperationDiagnosticsException::storageFailed();
        }

        if ($this->identityIsUnavailable($state, $records, $deadLetter)) {
            return new OperationDiagnosticsUnavailable();
        }

        try {
            return new OperationDiagnosticsFound(
                $state === null
                    ? $this->inline($operationId, $records, $deadLetter, $audits)
                    : $this->deferred($operationId, $state, $records, $deadLetter, $audits),
            );
        } catch (OperationDiagnosticsException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw OperationDiagnosticsException::decodeFailed();
        }
    }

    /** @param list<JournalRecord> $records */
    private function identityIsUnavailable(
        ?DiagnosticsDeferredState $state,
        array $records,
        ?DiagnosticsDeadLetter $deadLetter,
    ): bool {
        if ($state !== null || $records !== []) {
            return false;
        }
        if ($deadLetter !== null) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        return true;
    }

    /** @return list<JournalRecord> */
    private function records(OperationId $operationId): array
    {
        try {
            $records = [];
            foreach ($this->journal->records($operationId) as $record) {
                $records[] = $record;
            }

            return $records;
        } catch (OperationDiagnosticsException $exception) {
            throw $exception;
        } catch (JournalReadFailed $exception) {
            throw $this->readerFailure($exception);
        } catch (Throwable) {
            throw OperationDiagnosticsException::storageFailed();
        }
    }

    /**
     * @param list<JournalRecord> $records
     * @param list<DiagnosticsPurgeAudit> $audits
     */
    private function inline(
        OperationId $operationId,
        array $records,
        ?DiagnosticsDeadLetter $deadLetter,
        array $audits,
    ): OperationDiagnostics {
        $validated = $this->validateJournal($operationId, $records);
        if (!$this->isJournalOnlyStrategyValid($validated, $records)) {
            throw OperationDiagnosticsException::integrityFailed();
        }
        if ($deadLetter !== null || $this->wasPurged($audits, RetentionPurgeTarget::DeadLetter)) {
            throw OperationDiagnosticsException::integrityFailed();
        }
        if ($this->wasPurged($audits, RetentionPurgeTarget::Journal)) {
            throw OperationDiagnosticsException::integrityFailed();
        }
        if ($this->wasPurged($audits, RetentionPurgeTarget::TransportPayload)) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        $outcome = null;
        $outcomeAvailability = DiagnosticsAvailability::NotApplicable;
        if ($validated->state === LifecycleState::Completed) {
            $completed = $this->completedData($records);
            $completedData = $completed->data;
            if (!$completedData instanceof OperationCompletedData) {
                throw OperationDiagnosticsException::integrityFailed();
            }
            $outcome = $this->projector->outcome(
                $completedData->outcome,
                $this->time->format($completed->occurredAt),
                'journal',
            );
            $outcomeAvailability = DiagnosticsAvailability::Available;
            if ($this->wasPurged($audits, RetentionPurgeTarget::Outcome)) {
                throw OperationDiagnosticsException::integrityFailed();
            }
        }
        if (
            $validated->state !== LifecycleState::Completed
            && $this->wasPurged($audits, RetentionPurgeTarget::Outcome)
        ) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        return new OperationDiagnostics(
            $validated->identity,
            new DiagnosticsState($validated->state, $validated->state->isTerminal(), 'journal'),
            new DiagnosticsAvailabilitySet(
                DiagnosticsAvailability::NotApplicable,
                DiagnosticsAvailability::Available,
                $outcomeAvailability,
                DiagnosticsAvailability::NotApplicable,
            ),
            $validated->timeline,
            $validated->attempts,
            $outcome,
        );
    }

    /** @param list<JournalRecord> $records */
    private function isJournalOnlyStrategyValid(DiagnosticsValidatedJournal $journal, array $records): bool
    {
        if ($journal->identity->strategy === 'inline') {
            return true;
        }

        if ($journal->identity->strategy !== 'deferred' || $journal->attempts !== []) {
            return false;
        }

        $events = array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records);
        if ($journal->state === LifecycleState::Failed) {
            return $events === [
                JournalEvent::OperationReceived,
                JournalEvent::OperationFailed,
            ];
        }

        return (
            $journal->state === LifecycleState::Rejected
            && !in_array(JournalEvent::OperationAccepted, $events, strict: true)
        );
    }

    /**
     * @param list<JournalRecord> $records
     * @param list<DiagnosticsPurgeAudit> $audits
     */
    private function deferred(
        OperationId $operationId,
        DiagnosticsDeferredState $state,
        array $records,
        ?DiagnosticsDeadLetter $deadLetter,
        array $audits,
    ): OperationDiagnostics {
        if ($state->operationId !== $operationId->toString()) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        $journalPurged = $this->wasPurged($audits, RetentionPurgeTarget::Journal);
        if ($records === [] && !$journalPurged) {
            throw OperationDiagnosticsException::integrityFailed();
        }
        if ($records !== [] && $journalPurged) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        $identity = new DiagnosticsIdentity(
            $state->operationId,
            $state->type,
            $state->schemaVersion,
            'deferred',
            null,
            null,
            null,
        );
        $timeline = [];
        $attempts = $this->stateAttempt($state);
        if ($records !== []) {
            $validated = $this->validateJournal($operationId, $records);
            if (
                $validated->identity->strategy !== 'deferred'
                || $validated->identity->type !== $state->type
                || $validated->identity->schemaVersion !== $state->schemaVersion
                || $validated->state !== $state->state
                || $state->nextSequence !== (count($records) + 1)
            ) {
                throw OperationDiagnosticsException::integrityFailed();
            }
            $identity = $validated->identity;
            $timeline = $validated->timeline;
            $attempts = $validated->attempts;
            $this->validateDeferredAttempts($state, $attempts);
        }

        $transportPurged = $this->wasPurged($audits, RetentionPurgeTarget::TransportPayload);
        if (!$state->payloadPurged && $transportPurged) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        [$outcome, $outcomeAvailability] = $this->deferredOutcome($operationId, $state->state, $audits);
        $deadLetterAvailability = $this->deadLetterAvailability($state, $deadLetter, $audits, $records);

        return new OperationDiagnostics(
            $identity,
            new DiagnosticsState($state->state, $state->state->isTerminal(), 'transport'),
            new DiagnosticsAvailabilitySet(
                $state->payloadPurged ? DiagnosticsAvailability::Purged : DiagnosticsAvailability::Available,
                $journalPurged ? DiagnosticsAvailability::Purged : DiagnosticsAvailability::Available,
                $outcomeAvailability,
                $deadLetterAvailability,
            ),
            $timeline,
            $attempts,
            $outcome,
        );
    }

    /** @param list<DiagnosticsAttempt> $attempts */
    private function validateDeferredAttempts(DiagnosticsDeferredState $state, array $attempts): void
    {
        foreach ($attempts as $index => $attempt) {
            if ($attempt->number !== ($index + 1)) {
                throw OperationDiagnosticsException::integrityFailed();
            }
        }

        $last = $attempts === [] ? null : $attempts[array_key_last($attempts)];
        $lastNumber = $last === null ? 0 : $last->number;
        if ($state->attemptNumber !== $lastNumber) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        if ($state->state !== LifecycleState::Running) {
            if ($state->currentAttemptId !== null || $state->currentAttemptStartedAt !== null) {
                throw OperationDiagnosticsException::integrityFailed();
            }

            return;
        }
        if (
            $last === null
            || $last->attemptId !== $state->currentAttemptId
            || $last->startedAt !== $state->currentAttemptStartedAt
        ) {
            throw OperationDiagnosticsException::integrityFailed();
        }
    }

    /**
     * @param list<DiagnosticsPurgeAudit> $audits
     *
     * @return array{DiagnosticsOutcome|null, DiagnosticsAvailability}
     */
    private function deferredOutcome(OperationId $operationId, LifecycleState $state, array $audits): array
    {
        $purged = $this->wasPurged($audits, RetentionPurgeTarget::Outcome);
        $record = $this->outcome($operationId);

        if ($state !== LifecycleState::Completed) {
            if ($record !== null || $purged) {
                throw OperationDiagnosticsException::integrityFailed();
            }

            return [null, DiagnosticsAvailability::NotApplicable];
        }
        if ($record !== null && $purged) {
            throw OperationDiagnosticsException::integrityFailed();
        }
        if ($record === null) {
            if (!$purged) {
                throw OperationDiagnosticsException::integrityFailed();
            }

            return [null, DiagnosticsAvailability::Purged];
        }
        if ($record->operationId()->toString() !== $operationId->toString()) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        return [
            $this->projector->outcome($record->outcome(), $this->time->format($record->completedAt()), 'outcome_store'),
            DiagnosticsAvailability::Available,
        ];
    }

    private function outcome(OperationId $operationId): ?OutcomeRecord
    {
        try {
            return $this->outcomes->find($operationId);
        } catch (OperationDiagnosticsException $exception) {
            throw $exception;
        } catch (OutcomeStoreException $exception) {
            throw $this->readerFailure($exception);
        } catch (Throwable) {
            throw OperationDiagnosticsException::storageFailed();
        }
    }

    /**
     * @param list<DiagnosticsPurgeAudit> $audits
     * @param list<JournalRecord> $records
     */
    private function deadLetterAvailability(
        DiagnosticsDeferredState $state,
        ?DiagnosticsDeadLetter $deadLetter,
        array $audits,
        array $records,
    ): DiagnosticsAvailability {
        $purged = $this->wasPurged($audits, RetentionPurgeTarget::DeadLetter);
        if ($state->state !== LifecycleState::DeadLettered) {
            if ($deadLetter !== null || $purged) {
                throw OperationDiagnosticsException::integrityFailed();
            }

            return DiagnosticsAvailability::NotApplicable;
        }
        if ($deadLetter !== null && $purged) {
            throw OperationDiagnosticsException::integrityFailed();
        }
        if ($deadLetter === null) {
            if (!$purged) {
                throw OperationDiagnosticsException::integrityFailed();
            }

            return DiagnosticsAvailability::Purged;
        }
        if ($deadLetter->operationId !== $state->operationId) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        foreach ($records as $record) {
            if ($record->event !== JournalEvent::OperationDeadLettered) {
                continue;
            }
            $data = $record->data;
            if (
                !$data instanceof OperationDeadLetteredData
                || $data->finalAttemptId?->toString() !== $deadLetter->finalAttemptId
                || $data->finalAttemptNumber !== $deadLetter->finalAttemptNumber
                || $data->reasonType !== $deadLetter->reasonType
                || $this->time->format($data->movedAt) !== $deadLetter->movedAt
            ) {
                throw OperationDiagnosticsException::integrityFailed();
            }
        }

        return DiagnosticsAvailability::Available;
    }

    /**
     * @param list<JournalRecord> $records
     */
    private function validateJournal(OperationId $operationId, array $records): DiagnosticsValidatedJournal
    {
        if ($records === []) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        $first = $records[0];
        $identity = $this->projector->identity($first);
        $fingerprint = $this->identityFingerprint($first);
        $state = null;
        $timeline = [];
        $attempts = [];
        $attemptNumbers = [];
        $failedAttempts = [];

        foreach ($records as $index => $record) {
            if (
                $record->sequence !== ($index + 1)
                || $record->operation->id->toString() !== $operationId->toString()
                || $this->identityFingerprint($record) !== $fingerprint
            ) {
                throw OperationDiagnosticsException::integrityFailed();
            }

            try {
                $state = $this->lifecycle->next($state, $record->event);
            } catch (Throwable) {
                throw OperationDiagnosticsException::integrityFailed();
            }

            $this->validateAttempt($record, $attempts, $attemptNumbers, $failedAttempts);
            $timeline[] = $this->projector->timeline($record);
        }

        return new DiagnosticsValidatedJournal(
            $identity,
            $state,
            $timeline,
            array_values(array_map(
                static fn(array $attempt): DiagnosticsAttempt => new DiagnosticsAttempt(
                    $attempt['id'],
                    $attempt['number'],
                    $attempt['startedAt'],
                    $attempt['events'],
                ),
                $attempts,
            )),
        );
    }

    /**
     * @param array<string, array{id: string, number: int, startedAt: string, events: list<int>}> $attempts
     * @param array<int, string> $attemptNumbers
     * @param array<string, true> $failedAttempts
     */
    private function validateAttempt(
        JournalRecord $record,
        array &$attempts,
        array &$attemptNumbers,
        array &$failedAttempts,
    ): void {
        $attempt = $record->attempt;
        if (
            in_array(
                $record->event,
                [JournalEvent::AttemptStarted, JournalEvent::AttemptSucceeded, JournalEvent::AttemptFailed],
                strict: true,
            )
            && $attempt === null
        ) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        if ($attempt !== null) {
            $id = $attempt->id->toString();
            $startedAt = $this->time->format($attempt->startedAt);
            $known = $attempts[$id] ?? null;
            if ($known !== null && ($known['number'] !== $attempt->number || $known['startedAt'] !== $startedAt)) {
                throw OperationDiagnosticsException::integrityFailed();
            }
            if (array_key_exists($attempt->number, $attemptNumbers) && $attemptNumbers[$attempt->number] !== $id) {
                throw OperationDiagnosticsException::integrityFailed();
            }
            if ($known === null && $record->event !== JournalEvent::AttemptStarted) {
                throw OperationDiagnosticsException::integrityFailed();
            }
            if ($known !== null && $record->event === JournalEvent::AttemptStarted) {
                throw OperationDiagnosticsException::integrityFailed();
            }
            if ($known === null && $attempt->number !== (count($attempts) + 1)) {
                throw OperationDiagnosticsException::integrityFailed();
            }

            $attemptNumbers[$attempt->number] = $id;
            $attempts[$id] ??= [
                'id' => $id,
                'number' => $attempt->number,
                'startedAt' => $startedAt,
                'events' => [],
            ];
            $attempts[$id]['events'][] = $record->sequence;

            if ($record->event === JournalEvent::AttemptFailed) {
                $failedAttempts[$id] = true;
            }
        }

        if ($record->event === JournalEvent::AttemptRetryScheduled) {
            $data = $record->data;
            if (
                !$data instanceof AttemptRetryScheduledData
                || !array_key_exists($data->failedAttemptId->toString(), $failedAttempts)
                || $attempt === null
                || $attempt->id->toString() !== $data->failedAttemptId->toString()
                || $data->nextAttemptNumber !== ($attempt->number + 1)
            ) {
                throw OperationDiagnosticsException::integrityFailed();
            }
        }
    }

    /** @return list<DiagnosticsAttempt> */
    private function stateAttempt(DiagnosticsDeferredState $state): array
    {
        if ($state->state !== LifecycleState::Running) {
            if ($state->currentAttemptId !== null || $state->currentAttemptStartedAt !== null) {
                throw OperationDiagnosticsException::integrityFailed();
            }

            return [];
        }
        if (
            $state->attemptNumber < 1
            || $state->currentAttemptId === null
            || $state->currentAttemptStartedAt === null
        ) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        $startedAt = $state->currentAttemptStartedAt;

        return [new DiagnosticsAttempt($state->currentAttemptId, $state->attemptNumber, $startedAt, [])];
    }

    /** @param list<JournalRecord> $records */
    private function completedData(array $records): JournalRecord
    {
        $completed = null;
        foreach ($records as $record) {
            if ($record->event !== JournalEvent::OperationCompleted) {
                continue;
            }
            if ($completed !== null || !$record->data instanceof OperationCompletedData) {
                throw OperationDiagnosticsException::integrityFailed();
            }
            $completed = $record;
        }
        if (!$completed instanceof JournalRecord) {
            throw OperationDiagnosticsException::integrityFailed();
        }

        return $completed;
    }

    private function identityFingerprint(JournalRecord $record): string
    {
        $operation = $record->operation;
        $actors = $operation->actorContext;

        return json_encode([
            $operation->id->toString(),
            $operation->type,
            (string) $operation->schemaVersion,
            $operation->strategy,
            $operation->correlationId->toString(),
            $operation->causationId?->toString() ?? '',
            $actors?->origin()?->id() ?? '',
            $actors?->origin()?->type() ?? '',
            $actors?->authorization()?->id() ?? '',
            $actors?->authorization()?->type() ?? '',
            $actors?->execution()->id() ?? '',
            $actors?->execution()->type() ?? '',
        ], JSON_THROW_ON_ERROR);
    }

    /** @param list<DiagnosticsPurgeAudit> $audits */
    private function wasPurged(array $audits, RetentionPurgeTarget $target): bool
    {
        foreach ($audits as $audit) {
            if ($audit->target === $target) {
                return true;
            }
        }

        return false;
    }

    private function readerFailure(Throwable $exception): OperationDiagnosticsException
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof DbalException || $current instanceof PDOException) {
                return OperationDiagnosticsException::storageFailed();
            }
        }

        return $exception->getPrevious() === null
            ? OperationDiagnosticsException::storageFailed()
            : OperationDiagnosticsException::decodeFailed();
    }
}

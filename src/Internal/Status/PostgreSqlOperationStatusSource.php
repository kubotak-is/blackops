<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\Exception\JournalReadFailed;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\Outcome\Exception\OutcomeStoreException;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Status\OperationStatus;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusDeferredState;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusFailureKind;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReader;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReadFailed;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use PDOException;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class PostgreSqlOperationStatusSource implements OperationStatusSource
{
    private PostgreSqlStatusReader $reader;
    private PostgreSqlCanonicalJournalStore $journal;
    private PostgreSqlOutcomeStore $outcomes;

    public function __construct(
        private Connection $connection,
        private OperationRegistry $registry,
        string $schema = 'blackops',
        private OperationStatusJournalValidator $validator = new OperationStatusJournalValidator(),
    ) {
        $this->reader = new PostgreSqlStatusReader($connection, $schema);
        $this->journal = new PostgreSqlCanonicalJournalStore($connection, $schema);
        $this->outcomes = new PostgreSqlOutcomeStore($connection, $schema);
    }

    public function findSubject(OperationId $operationId): ?OperationStatusSubject
    {
        try {
            $subject = $this->reader->findSubject($operationId);
            if ($subject === null) {
                return null;
            }

            return new OperationStatusSubject(
                OperationId::fromString($subject->operationId),
                $subject->operationType,
                $subject->originActorId === null
                    ? null
                    : new ActorRef($subject->originActorId, (string) $subject->originActorType),
            );
        } catch (OperationStatusSourceException $exception) {
            throw $exception;
        } catch (PostgreSqlStatusReadFailed $exception) {
            throw $this->statusReaderFailure($exception);
        } catch (Throwable) {
            throw OperationStatusSourceException::integrityFailed();
        }
    }

    public function readDetail(OperationStatusSubject $subject): OperationStatusDetailResult
    {
        $metadata = $this->registry->findByTypeId($subject->operationType);
        if ($metadata !== null && is_a($metadata->outcome, EphemeralOutcome::class, allow_string: true)) {
            return new OperationStatusDetailUnavailable();
        }

        if ($this->connection->isTransactionActive()) {
            throw OperationStatusSourceException::storageFailed();
        }

        try {
            $this->connection->beginTransaction();
            $this->connection->executeStatement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            if ($this->connection->fetchOne('SHOW transaction_isolation') !== 'repeatable read') {
                throw OperationStatusSourceException::integrityFailed();
            }
            if ($this->connection->fetchOne('SHOW transaction_read_only') !== 'on') {
                throw OperationStatusSourceException::integrityFailed();
            }
            $result = $this->readDetailSnapshot($subject);
            $this->connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                try {
                    $this->connection->rollBack();
                } catch (Throwable) {
                    throw OperationStatusSourceException::storageFailed();
                }
            }
            if ($exception instanceof OperationStatusSourceException) {
                throw $exception;
            }
            if ($this->containsDatabaseFailure($exception)) {
                throw OperationStatusSourceException::storageFailed();
            }

            throw OperationStatusSourceException::integrityFailed();
        }
    }

    private function readDetailSnapshot(OperationStatusSubject $subject): OperationStatusDetailResult
    {
        $state = $this->deferredState($subject->operationId);
        $snapshot = new OperationStatusSnapshot(
            $this->records($subject->operationId),
            $this->purgeTargets($subject->operationId),
            $this->outcomeExists($subject->operationId),
            $this->deadLetterExists($subject->operationId),
        );

        if ($state === null) {
            return $this->journalOnly($subject, $snapshot);
        }

        return $this->deferred($subject, $state, $snapshot);
    }

    private function journalOnly(
        OperationStatusSubject $subject,
        OperationStatusSnapshot $snapshot,
    ): OperationStatusDetailResult {
        if ($snapshot->records === []) {
            if (
                count($snapshot->purgeTargets) === 1
                && $snapshot->wasPurged(RetentionPurgeTarget::Journal->value)
                && !$snapshot->outcomeExists
                && !$snapshot->deadLetterExists
            ) {
                return new OperationStatusDetailExpired();
            }

            throw OperationStatusSourceException::integrityFailed();
        }
        if ($snapshot->purgeTargets !== []) {
            throw OperationStatusSourceException::integrityFailed();
        }

        $journal = $this->validator->validate($subject->operationId, $snapshot->records);
        $this->validateSubjectIdentity($subject, $journal);
        $this->validateOutcomeAbsence($snapshot);
        $this->validateDeadLetter($journal->state, $snapshot);

        if ($journal->operation->strategy === 'inline') {
            return new OperationStatusDetail($this->statusFromJournal($journal));
        }
        if ($journal->operation->strategy !== 'deferred' || !$this->isPreAcceptanceTerminal($journal)) {
            throw OperationStatusSourceException::integrityFailed();
        }

        return new OperationStatusDetail($this->statusFromJournal($journal));
    }

    /** @mago-expect lint:halstead */
    private function deferred(
        OperationStatusSubject $subject,
        PostgreSqlStatusDeferredState $state,
        OperationStatusSnapshot $snapshot,
    ): OperationStatusDetailResult {
        if (
            $state->operationId !== $subject->operationId->toString()
            || $state->operationType !== $subject->operationType
        ) {
            throw OperationStatusSourceException::integrityFailed();
        }

        $this->validateTransportRetention($state, $snapshot);
        $journalPurged = $snapshot->wasPurged(RetentionPurgeTarget::Journal->value);
        if ($snapshot->records === [] && !$journalPurged) {
            throw OperationStatusSourceException::integrityFailed();
        }
        if ($snapshot->records !== [] && $journalPurged) {
            throw OperationStatusSourceException::integrityFailed();
        }

        $journal = null;
        if ($snapshot->records !== []) {
            $journal = $this->validator->validate($subject->operationId, $snapshot->records);
            $this->validateSubjectIdentity($subject, $journal);
            $this->validateDeferredJournal($state, $journal);
        }
        if ($journal === null && !$state->state->isTerminal()) {
            throw OperationStatusSourceException::integrityFailed();
        }

        $this->validateDeferredAttempt($state, $journal);
        $outcome = $this->deferredOutcome($state, $journal, $snapshot);
        $this->validateDeadLetter($state->state, $snapshot);

        if ($state->state === LifecycleState::Rejected && $journalPurged) {
            return new OperationStatusDetailExpired();
        }
        if ($state->state === LifecycleState::Completed && $outcome === null) {
            return new OperationStatusDetailExpired();
        }

        return new OperationStatusDetail($this->statusFromDeferred($state, $journal, $outcome));
    }

    private function validateSubjectIdentity(
        OperationStatusSubject $subject,
        ValidatedOperationStatusJournal $journal,
    ): void {
        $subjectOrigin = $subject->originActor;
        $journalOrigin = $journal->operation->actorContext?->origin();
        if (
            !$journal->operation->id->equals($subject->operationId)
            || $journal->operation->type !== $subject->operationType
            || ($subjectOrigin === null) !== ($journalOrigin === null)
        ) {
            throw OperationStatusSourceException::integrityFailed();
        }
        if (
            $subjectOrigin !== null
            && $journalOrigin !== null
            && ($subjectOrigin->id() !== $journalOrigin->id() || $subjectOrigin->type() !== $journalOrigin->type())
        ) {
            throw OperationStatusSourceException::integrityFailed();
        }
    }

    private function validateDeferredJournal(
        PostgreSqlStatusDeferredState $state,
        ValidatedOperationStatusJournal $journal,
    ): void {
        if (
            $journal->operation->strategy !== 'deferred'
            || $journal->operation->type !== $state->operationType
            || $journal->operation->schemaVersion !== $state->schemaVersion
            || $journal->state !== $state->state
            || $state->nextSequence !== (count($journal->records) + 1)
        ) {
            throw OperationStatusSourceException::integrityFailed();
        }
    }

    private function validateTransportRetention(
        PostgreSqlStatusDeferredState $state,
        OperationStatusSnapshot $snapshot,
    ): void {
        if ($state->payloadPurged !== $snapshot->wasPurged(RetentionPurgeTarget::TransportPayload->value)) {
            throw OperationStatusSourceException::integrityFailed();
        }
    }

    private function validateDeferredAttempt(
        PostgreSqlStatusDeferredState $state,
        ?ValidatedOperationStatusJournal $journal,
    ): void {
        $active = $state->state === LifecycleState::Running;
        if ($state->state === LifecycleState::Accepted) {
            if (
                $state->attemptNumber !== 0
                || $state->currentAttemptId !== null
                || $state->currentAttemptStartedAt !== null
            ) {
                throw OperationStatusSourceException::integrityFailed();
            }

            return;
        }
        if ($state->attemptNumber < 1) {
            throw OperationStatusSourceException::integrityFailed();
        }

        $currentAttemptId = $state->currentAttemptId;
        $currentAttemptStartedAt = $state->currentAttemptStartedAt;
        if ($active && ($currentAttemptId === null || $currentAttemptStartedAt === null)) {
            throw OperationStatusSourceException::integrityFailed();
        }
        if (!$active && ($state->currentAttemptId !== null || $state->currentAttemptStartedAt !== null)) {
            throw OperationStatusSourceException::integrityFailed();
        }

        if ($journal === null) {
            return;
        }
        $last = $journal->lastAttempt();
        if ($last === null || $last->number !== $state->attemptNumber) {
            throw OperationStatusSourceException::integrityFailed();
        }
        if (
            $active
            && ($last->id !== $currentAttemptId || !$this->sameTime($last->startedAt, $currentAttemptStartedAt))
        ) {
            throw OperationStatusSourceException::integrityFailed();
        }
        if (
            $state->state === LifecycleState::RetryScheduled
            && ($journal->retryAt === null || !$this->sameTime($journal->retryAt, $state->availableAt))
        ) {
            throw OperationStatusSourceException::integrityFailed();
        }
    }

    private function deferredOutcome(
        PostgreSqlStatusDeferredState $state,
        ?ValidatedOperationStatusJournal $journal,
        OperationStatusSnapshot $snapshot,
    ): ?Outcome {
        $purged = $snapshot->wasPurged(RetentionPurgeTarget::Outcome->value);
        if ($state->state !== LifecycleState::Completed) {
            $this->validateOutcomeAbsence($snapshot);

            return null;
        }
        if ($snapshot->outcomeExists && $purged) {
            throw OperationStatusSourceException::integrityFailed();
        }
        if (!$snapshot->outcomeExists) {
            if (!$purged) {
                throw OperationStatusSourceException::integrityFailed();
            }

            return null;
        }

        $record = $this->outcome($state->operationId);
        if ($record === null || $record->operationId()->toString() !== $state->operationId) {
            throw OperationStatusSourceException::integrityFailed();
        }
        $this->validateExpectedOutcome($state->operationType, $record->outcome());
        if ($journal !== null) {
            $journalOutcome = $this->journalOutcome($journal);
            if ($journalOutcome::class !== $record->outcome()::class) {
                throw OperationStatusSourceException::integrityFailed();
            }
        }

        return $record->outcome();
    }

    private function validateOutcomeAbsence(OperationStatusSnapshot $snapshot): void
    {
        if ($snapshot->outcomeExists || $snapshot->wasPurged(RetentionPurgeTarget::Outcome->value)) {
            throw OperationStatusSourceException::integrityFailed();
        }
    }

    private function validateDeadLetter(LifecycleState $state, OperationStatusSnapshot $snapshot): void
    {
        $exists = $snapshot->deadLetterExists;
        $purged = $snapshot->wasPurged(RetentionPurgeTarget::DeadLetter->value);
        if ($state !== LifecycleState::DeadLettered) {
            if ($exists || $purged) {
                throw OperationStatusSourceException::integrityFailed();
            }

            return;
        }
        if ($exists === $purged) {
            throw OperationStatusSourceException::integrityFailed();
        }
    }

    private function statusFromJournal(ValidatedOperationStatusJournal $journal): OperationStatus
    {
        $id = $journal->operation->id;
        $type = $journal->operation->type;

        return match ($journal->state) {
            LifecycleState::Accepted => OperationStatus::accepted($id, $type),
            LifecycleState::Running, LifecycleState::Supervising => OperationStatus::running(
                $id,
                $type,
                $journal->lastAttempt()->number ?? throw OperationStatusSourceException::integrityFailed(),
            ),
            LifecycleState::RetryScheduled => OperationStatus::retryScheduled(
                $id,
                $type,
                $journal->lastAttempt()->number ?? throw OperationStatusSourceException::integrityFailed(),
                $journal->retryAt ?? throw OperationStatusSourceException::integrityFailed(),
            ),
            LifecycleState::Completed => OperationStatus::completed($id, $type, $this->journalOutcome($journal)),
            LifecycleState::Rejected => $this->rejectedStatus($journal),
            LifecycleState::Failed => $this->failedStatus($journal),
            LifecycleState::DeadLettered => $this->deadLetteredStatus($journal),
            LifecycleState::Received,
            LifecycleState::Finalizing,
                => throw OperationStatusSourceException::integrityFailed(),
        };
    }

    private function statusFromDeferred(
        PostgreSqlStatusDeferredState $state,
        ?ValidatedOperationStatusJournal $journal,
        ?Outcome $outcome,
    ): OperationStatus {
        $id = OperationId::fromString($state->operationId);

        return match ($state->state) {
            LifecycleState::Accepted => OperationStatus::accepted($id, $state->operationType),
            LifecycleState::Running, LifecycleState::Supervising => OperationStatus::running(
                $id,
                $state->operationType,
                $state->attemptNumber,
            ),
            LifecycleState::RetryScheduled => OperationStatus::retryScheduled(
                $id,
                $state->operationType,
                $state->attemptNumber,
                $state->availableAt,
            ),
            LifecycleState::Completed => OperationStatus::completed(
                $id,
                $state->operationType,
                $outcome ?? throw OperationStatusSourceException::integrityFailed(),
            ),
            LifecycleState::Rejected => $this->rejectedStatus(
                $journal ?? throw OperationStatusSourceException::integrityFailed(),
            ),
            LifecycleState::Failed => OperationStatus::failed($id, $state->operationType),
            LifecycleState::DeadLettered => OperationStatus::deadLettered($id, $state->operationType),
            LifecycleState::Received,
            LifecycleState::Finalizing,
                => throw OperationStatusSourceException::integrityFailed(),
        };
    }

    private function journalOutcome(ValidatedOperationStatusJournal $journal): Outcome
    {
        $record = $this->uniqueTerminalRecord($journal, JournalEvent::OperationCompleted);
        if (!$record->data instanceof OperationCompletedData) {
            throw OperationStatusSourceException::integrityFailed();
        }
        $this->validateExpectedOutcome($journal->operation->type, $record->data->outcome);

        return $record->data->outcome;
    }

    private function rejectedStatus(ValidatedOperationStatusJournal $journal): OperationStatus
    {
        $record = $this->uniqueTerminalRecord($journal, JournalEvent::OperationRejected);
        if (!$record->data instanceof OperationRejectedData) {
            throw OperationStatusSourceException::integrityFailed();
        }

        return OperationStatus::rejected(
            $journal->operation->id,
            $journal->operation->type,
            $record->data->reason->category()->value,
            $record->data->reason->code(),
        );
    }

    private function failedStatus(ValidatedOperationStatusJournal $journal): OperationStatus
    {
        $record = $this->uniqueTerminalRecord($journal, JournalEvent::OperationFailed);
        if (!$record->data instanceof OperationFailedData) {
            throw OperationStatusSourceException::integrityFailed();
        }

        return OperationStatus::failed($journal->operation->id, $journal->operation->type);
    }

    private function deadLetteredStatus(ValidatedOperationStatusJournal $journal): OperationStatus
    {
        $record = $this->uniqueTerminalRecord($journal, JournalEvent::OperationDeadLettered);
        if (!$record->data instanceof OperationDeadLetteredData) {
            throw OperationStatusSourceException::integrityFailed();
        }

        return OperationStatus::deadLettered($journal->operation->id, $journal->operation->type);
    }

    private function uniqueTerminalRecord(ValidatedOperationStatusJournal $journal, JournalEvent $event): JournalRecord
    {
        $found = null;
        foreach ($journal->records as $record) {
            if ($record->event !== $event) {
                continue;
            }
            if ($found !== null) {
                throw OperationStatusSourceException::integrityFailed();
            }
            $found = $record;
        }

        return $found ?? throw OperationStatusSourceException::integrityFailed();
    }

    private function validateExpectedOutcome(string $operationType, Outcome $outcome): void
    {
        $expected = $this->registry->findByTypeId($operationType)?->outcome;
        if ($expected === null || $outcome::class !== $expected) {
            throw OperationStatusSourceException::integrityFailed();
        }
    }

    private function isPreAcceptanceTerminal(ValidatedOperationStatusJournal $journal): bool
    {
        if (
            $journal->attempts !== []
            || !in_array($journal->state, [LifecycleState::Rejected, LifecycleState::Failed], strict: true)
        ) {
            return false;
        }

        return !array_any(
            $journal->records,
            static fn(JournalRecord $record): bool => $record->event === JournalEvent::OperationAccepted,
        );
    }

    private function deferredState(OperationId $operationId): ?PostgreSqlStatusDeferredState
    {
        try {
            return $this->reader->deferredState($operationId);
        } catch (PostgreSqlStatusReadFailed $exception) {
            throw $this->statusReaderFailure($exception);
        }
    }

    /** @return array<string, true> */
    private function purgeTargets(OperationId $operationId): array
    {
        try {
            $targets = [];
            foreach ($this->reader->purgeTargets($operationId) as $target) {
                if (array_key_exists($target->value, $targets)) {
                    throw OperationStatusSourceException::integrityFailed();
                }
                $targets[$target->value] = true;
            }

            return $targets;
        } catch (OperationStatusSourceException $exception) {
            throw $exception;
        } catch (PostgreSqlStatusReadFailed $exception) {
            throw $this->statusReaderFailure($exception);
        }
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
        } catch (JournalReadFailed $exception) {
            throw $this->readerFailure($exception);
        } catch (Throwable) {
            throw OperationStatusSourceException::storageFailed();
        }
    }

    private function outcomeExists(OperationId $operationId): bool
    {
        try {
            return $this->reader->outcomeExists($operationId);
        } catch (PostgreSqlStatusReadFailed $exception) {
            throw $this->statusReaderFailure($exception);
        }
    }

    private function deadLetterExists(OperationId $operationId): bool
    {
        try {
            return $this->reader->deadLetterExists($operationId);
        } catch (PostgreSqlStatusReadFailed $exception) {
            throw $this->statusReaderFailure($exception);
        }
    }

    private function outcome(string $operationId): ?OutcomeRecord
    {
        try {
            return $this->outcomes->find(OperationId::fromString($operationId));
        } catch (OutcomeStoreException $exception) {
            throw $this->readerFailure($exception);
        } catch (Throwable) {
            throw OperationStatusSourceException::storageFailed();
        }
    }

    private function statusReaderFailure(PostgreSqlStatusReadFailed $exception): OperationStatusSourceException
    {
        return $exception->kind === PostgreSqlStatusFailureKind::Storage
            ? OperationStatusSourceException::storageFailed()
            : OperationStatusSourceException::integrityFailed();
    }

    private function readerFailure(Throwable $exception): OperationStatusSourceException
    {
        if ($this->containsDatabaseFailure($exception)) {
            return OperationStatusSourceException::storageFailed();
        }

        return OperationStatusSourceException::decodeFailed();
    }

    private function containsDatabaseFailure(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof DbalException || $current instanceof PDOException) {
                return true;
            }
        }

        return false;
    }

    private function sameTime(DateTimeImmutable $left, DateTimeImmutable $right): bool
    {
        return $left->format('U.u') === $right->format('U.u');
    }
}

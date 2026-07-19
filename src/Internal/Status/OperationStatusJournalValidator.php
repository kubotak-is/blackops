<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use DateTimeImmutable;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class OperationStatusJournalValidator
{
    public function __construct(
        private LifecycleStateMachine $lifecycle = new LifecycleStateMachine(),
    ) {}

    /** @param list<JournalRecord> $records */
    public function validate(OperationId $operationId, array $records): ValidatedOperationStatusJournal
    {
        if ($records === []) {
            throw OperationStatusSourceException::integrityFailed();
        }

        $first = $records[0];
        $fingerprint = $this->identityFingerprint($first);
        $state = null;
        $attempts = [];
        $attemptNumbers = [];
        $failedAttempts = [];
        $retryAt = null;

        foreach ($records as $index => $record) {
            if (
                $record->sequence !== ($index + 1)
                || !$record->operation->id->equals($operationId)
                || $this->identityFingerprint($record) !== $fingerprint
            ) {
                throw OperationStatusSourceException::integrityFailed();
            }

            try {
                $state = $this->lifecycle->next($state, $record->event);
            } catch (Throwable) {
                throw OperationStatusSourceException::integrityFailed();
            }

            $scheduled = $this->validateAttempt($record, $attempts, $attemptNumbers, $failedAttempts);
            if ($scheduled !== null) {
                if ($retryAt !== null) {
                    throw OperationStatusSourceException::integrityFailed();
                }
                $retryAt = $scheduled;
                continue;
            }
            if ($record->event === JournalEvent::AttemptStarted) {
                $retryAt = null;
            }
        }

        return new ValidatedOperationStatusJournal(
            $first->operation,
            $state ?? throw OperationStatusSourceException::integrityFailed(),
            $records,
            array_values($attempts),
            $retryAt,
        );
    }

    /**
     * @param array<string, OperationStatusJournalAttempt> $attempts
     * @param array<int, string> $attemptNumbers
     * @param array<string, true> $failedAttempts
     */
    private function validateAttempt(
        JournalRecord $record,
        array &$attempts,
        array &$attemptNumbers,
        array &$failedAttempts,
    ): ?DateTimeImmutable {
        $attempt = $record->attempt;
        if (
            in_array(
                $record->event,
                [
                    JournalEvent::AttemptStarted,
                    JournalEvent::AttemptSucceeded,
                    JournalEvent::AttemptFailed,
                    JournalEvent::AttemptRetryScheduled,
                ],
                strict: true,
            )
            && $attempt === null
        ) {
            throw OperationStatusSourceException::integrityFailed();
        }

        if ($attempt !== null) {
            $id = $attempt->id->toString();
            $known = $attempts[$id] ?? null;
            if (
                $known !== null
                && ($known->number !== $attempt->number || !$this->sameTime($known->startedAt, $attempt->startedAt))
            ) {
                throw OperationStatusSourceException::integrityFailed();
            }
            if (array_key_exists($attempt->number, $attemptNumbers) && $attemptNumbers[$attempt->number] !== $id) {
                throw OperationStatusSourceException::integrityFailed();
            }
            if ($known === null && $record->event !== JournalEvent::AttemptStarted) {
                throw OperationStatusSourceException::integrityFailed();
            }
            if ($known !== null && $record->event === JournalEvent::AttemptStarted) {
                throw OperationStatusSourceException::integrityFailed();
            }
            if ($known === null && $attempt->number !== (count($attempts) + 1)) {
                throw OperationStatusSourceException::integrityFailed();
            }

            $attemptNumbers[$attempt->number] = $id;
            $attempts[$id] ??= new OperationStatusJournalAttempt($id, $attempt->number, $attempt->startedAt);
            if ($record->event === JournalEvent::AttemptFailed) {
                $failedAttempts[$id] = true;
            }
        }

        if ($record->event !== JournalEvent::AttemptRetryScheduled) {
            return null;
        }

        $data = $record->data;
        if (
            !$data instanceof AttemptRetryScheduledData
            || $attempt === null
            || !array_key_exists($attempt->id->toString(), $failedAttempts)
            || !$data->failedAttemptId->equals($attempt->id)
            || $data->nextAttemptNumber !== ($attempt->number + 1)
        ) {
            throw OperationStatusSourceException::integrityFailed();
        }

        return $data->scheduledAt;
    }

    /** @mago-expect lint:halstead */
    private function identityFingerprint(JournalRecord $record): string
    {
        $operation = $record->operation;
        $actors = $operation->actorContext;

        return json_encode([
            (string) $record->schemaVersion,
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

    private function sameTime(DateTimeImmutable $left, DateTimeImmutable $right): bool
    {
        return $left->format('U.u') === $right->format('U.u');
    }
}

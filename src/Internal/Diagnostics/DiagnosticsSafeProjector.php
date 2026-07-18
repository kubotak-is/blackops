<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Outcome;
use BlackOps\Core\Time\TimeCodec;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;

final readonly class DiagnosticsSafeProjector
{
    public function __construct(
        private SensitiveProjectionFilter $sensitive = new SensitiveProjectionFilter(),
        private TimeCodec $time = new TimeCodec(),
    ) {}

    public function identity(JournalRecord $record): DiagnosticsIdentity
    {
        $operation = $record->operation;

        return new DiagnosticsIdentity(
            $operation->id->toString(),
            $operation->type,
            $operation->schemaVersion,
            $operation->strategy,
            $operation->correlationId->toString(),
            $operation->causationId?->toString(),
            $this->actors($operation->actorContext),
        );
    }

    public function timeline(JournalRecord $record): DiagnosticsTimelineEntry
    {
        return new DiagnosticsTimelineEntry(
            $record->sequence,
            $record->event->value,
            $this->time->format($record->occurredAt),
            $record->attempt?->id->toString(),
            $record->attempt?->number,
            $this->eventData($record),
        );
    }

    public function outcome(Outcome $outcome, ?string $completedAt, string $source): DiagnosticsOutcome
    {
        return new DiagnosticsOutcome(
            $outcome::class,
            $completedAt,
            $source,
            $this->sensitive->projectObject($outcome),
        );
    }

    private function actors(?ActorContext $actors): ?DiagnosticsSafeActorContext
    {
        if ($actors === null) {
            return null;
        }

        $origin = $actors->origin();
        $authorization = $actors->authorization();

        return new DiagnosticsSafeActorContext(
            $origin === null ? null : new DiagnosticsSafeActor($origin->type()),
            $authorization === null ? null : new DiagnosticsSafeActor($authorization->type()),
            new DiagnosticsSafeActor($actors->execution()->type()),
        );
    }

    /** @return array<string, mixed> */
    private function eventData(JournalRecord $record): array
    {
        $data = $record->data;

        return match ($record->event) {
            JournalEvent::OperationReceived => $data instanceof OperationReceivedData
                ? $this->sensitive->projectObject($data->value)
                : throw OperationDiagnosticsException::integrityFailed(),
            JournalEvent::OperationAccepted, JournalEvent::AttemptStarted, JournalEvent::AttemptSucceeded => $data
                instanceof EmptyJournalData
                    ? []
                    : throw OperationDiagnosticsException::integrityFailed(),
            JournalEvent::AttemptFailed => $data instanceof AttemptFailedData
                ? ['errorType' => $data->errorType, 'retryable' => $data->retryable]
                : throw OperationDiagnosticsException::integrityFailed(),
            JournalEvent::AttemptRetryScheduled => $data instanceof AttemptRetryScheduledData
                ? [
                    'failedAttemptId' => $data->failedAttemptId->toString(),
                    'nextAttemptNumber' => $data->nextAttemptNumber,
                    'scheduledAt' => $this->time->format($data->scheduledAt),
                    'delayMilliseconds' => $data->delayMilliseconds,
                ]
                : throw OperationDiagnosticsException::integrityFailed(),
            JournalEvent::OperationCompleted => $data instanceof OperationCompletedData
                ? []
                : throw OperationDiagnosticsException::integrityFailed(),
            JournalEvent::OperationRejected => $data instanceof OperationRejectedData
                ? $this->rejection($data)
                : throw OperationDiagnosticsException::integrityFailed(),
            JournalEvent::OperationFailed => $data instanceof OperationFailedData
                ? ['errorType' => $data->errorType, 'retryable' => $data->retryable]
                : throw OperationDiagnosticsException::integrityFailed(),
            JournalEvent::OperationDeadLettered => $data instanceof OperationDeadLetteredData
                ? [
                    'finalAttemptId' => $data->finalAttemptId?->toString(),
                    'finalAttemptNumber' => $data->finalAttemptNumber,
                    'reasonType' => $data->reasonType,
                    'movedAt' => $this->time->format($data->movedAt),
                ]
                : throw OperationDiagnosticsException::integrityFailed(),
        };
    }

    /** @return array<string, mixed> */
    private function rejection(OperationRejectedData $data): array
    {
        return [
            'category' => $data->reason->category()->value,
            'code' => $data->reason->code(),
            'violations' => array_map(static fn($violation): array => [
                'field' => $violation->field,
                'rule' => $violation->rule,
                'code' => $violation->code,
            ], $data->reason->violations()),
        ];
    }
}

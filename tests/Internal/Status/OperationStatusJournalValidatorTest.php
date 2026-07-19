<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Status;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Status\OperationStatusJournalValidator;
use BlackOps\Internal\Status\OperationStatusSourceException;
use BlackOps\Internal\Status\OperationStatusSourceFailure;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OperationStatusJournalValidatorTest extends TestCase
{
    private const string OPERATION_ID = '019f72ab-1000-7000-8000-000000000001';
    private const string CORRELATION_ID = '019f72ab-2000-7000-8000-000000000001';

    public function testAcceptsExecutionActorChangesAcrossAcceptanceRetryAndLeaseRecovery(): void
    {
        $records = $this->records();
        $validator = new OperationStatusJournalValidator();

        $retryScheduled = $validator->validate($this->operationId(), array_slice($records, 0, 5));
        $completed = $validator->validate($this->operationId(), $records);

        self::assertSame(LifecycleState::RetryScheduled, $retryScheduled->state);
        self::assertSame('2026-07-19T00:01:00.000000+00:00', $retryScheduled->retryAt?->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(LifecycleState::Completed, $completed->state);
        self::assertCount(2, $completed->attempts);
        self::assertSame(2, $completed->lastAttempt()?->number);
    }

    public function testRejectsOriginAndAuthorizationActorChanges(): void
    {
        foreach ([
            ['other-origin',   'authorization-private'],
            ['origin-private', 'other-authorization'],
        ] as [$originId, $authorizationId]) {
            $records = $this->records();
            $records[4] = $this->record(
                5,
                JournalEvent::AttemptRetryScheduled,
                new AttemptRetryScheduledData(
                    $this->attempt(1)->id,
                    2,
                    new DateTimeImmutable('2026-07-19T00:01:00Z'),
                    60_000,
                ),
                $this->attempt(1),
                $originId,
                $authorizationId,
                'worker-recovery',
            );

            try {
                new OperationStatusJournalValidator()->validate($this->operationId(), $records);
                self::fail('Expected status journal integrity failure.');
            } catch (OperationStatusSourceException $exception) {
                self::assertSame(OperationStatusSourceFailure::Integrity, $exception->failure);
            }
        }
    }

    /** @return list<JournalRecord> */
    private function records(): array
    {
        $first = $this->attempt(1);
        $second = $this->attempt(2);

        return [
            $this->record(
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new StatusActorValue()),
                executionId: 'http-user',
            ),
            $this->record(2, JournalEvent::OperationAccepted, executionId: 'http-user'),
            $this->record(3, JournalEvent::AttemptStarted, attempt: $first, executionId: 'worker-one'),
            $this->record(
                4,
                JournalEvent::AttemptFailed,
                new AttemptFailedData('TemporaryFailure', 'restricted', true),
                $first,
                executionId: 'worker-one',
            ),
            $this->record(
                5,
                JournalEvent::AttemptRetryScheduled,
                new AttemptRetryScheduledData($first->id, 2, new DateTimeImmutable('2026-07-19T00:01:00Z'), 60_000),
                $first,
                executionId: 'worker-recovery',
            ),
            $this->record(6, JournalEvent::AttemptStarted, attempt: $second, executionId: 'worker-two'),
            $this->record(7, JournalEvent::AttemptSucceeded, attempt: $second, executionId: 'worker-three'),
            $this->record(
                8,
                JournalEvent::OperationCompleted,
                new OperationCompletedData(new StatusActorOutcome()),
                $second,
                executionId: 'worker-three',
            ),
        ];
    }

    private function record(
        int $sequence,
        JournalEvent $event,
        ?JournalData $data = null,
        ?JournalAttempt $attempt = null,
        string $originId = 'origin-private',
        string $authorizationId = 'authorization-private',
        string $executionId = 'worker-private',
    ): JournalRecord {
        return new JournalRecord(
            JournalRecordId::fromString(sprintf('019f72ab-3000-7000-8000-%012d', $sequence)),
            1,
            $event,
            new DateTimeImmutable(sprintf('2026-07-19T00:00:%02dZ', $sequence)),
            $sequence,
            new JournalOperation(
                $this->operationId(),
                'status.actor.continuity',
                1,
                'deferred',
                CorrelationId::fromString(self::CORRELATION_ID),
                actorContext: new ActorContext(
                    new ActorRef($originId, 'customer'),
                    new ActorRef($authorizationId, 'customer'),
                    new ActorRef($executionId, 'system'),
                ),
            ),
            $attempt,
            $data ?? new EmptyJournalData(),
        );
    }

    private function attempt(int $number): JournalAttempt
    {
        return new JournalAttempt(
            AttemptId::fromString(sprintf('019f72ab-4000-7000-8000-%012d', $number)),
            $number,
            new DateTimeImmutable(sprintf('2026-07-19T00:00:%02dZ', $number + 2)),
        );
    }

    private function operationId(): OperationId
    {
        return OperationId::fromString(self::OPERATION_ID);
    }
}

final readonly class StatusActorValue implements \BlackOps\Core\OperationValue {}

final readonly class StatusActorOutcome implements \BlackOps\Core\Outcome {}

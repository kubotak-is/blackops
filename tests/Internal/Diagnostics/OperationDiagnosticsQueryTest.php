<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Diagnostics;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Internal\Diagnostics\DiagnosticsAvailability;
use BlackOps\Internal\Diagnostics\DiagnosticsDeadLetter;
use BlackOps\Internal\Diagnostics\DiagnosticsDeferredState;
use BlackOps\Internal\Diagnostics\DiagnosticsFailureCode;
use BlackOps\Internal\Diagnostics\DiagnosticsPurgeAudit;
use BlackOps\Internal\Diagnostics\DiagnosticsSourceReader;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsException;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsFound;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsQuery;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsUnavailable;
use BlackOps\Journal\CanonicalJournalReader;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\Outcome\Exception\OutcomeStoreException;
use BlackOps\Outcome\OutcomeReader;
use BlackOps\Outcome\OutcomeRecord;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class OperationDiagnosticsQueryTest extends TestCase
{
    private const OPERATION_ID = '019f5b0e-d13f-73b4-8f57-1f60680fd001';
    private const ATTEMPT_ID = '019f5b0e-d13f-73b4-8f57-1f60680fd002';
    private const CORRELATION_ID = '019f5b0e-d13f-73b4-8f57-1f60680fd003';

    public function testInlineCompletedProjectsOnlySafeDataAndJournalOutcome(): void
    {
        $records = $this->recordsFor(LifecycleState::Completed, 'inline');
        $result = $this->query($records)->find($this->operationId());

        self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
        $diagnostics = $result->diagnostics;
        self::assertSame(LifecycleState::Completed, $diagnostics->state->current);
        self::assertSame('journal', $diagnostics->state->source);
        self::assertSame(DiagnosticsAvailability::NotApplicable, $diagnostics->availability->transportPayload);
        self::assertSame(DiagnosticsAvailability::Available, $diagnostics->availability->journal);
        self::assertSame(DiagnosticsAvailability::Available, $diagnostics->availability->outcome);
        self::assertSame('[masked]', $diagnostics->identity->actors?->origin?->id);
        self::assertSame('customer', $diagnostics->identity->actors?->origin?->type);
        self::assertArrayNotHasKey('password', $diagnostics->timeline[0]->data);
        self::assertArrayNotHasKey('apiToken', $diagnostics->timeline[0]->data['metadata']);
        self::assertSame('[masked]', $diagnostics->timeline[0]->data['displayName']);
        self::assertSame(DiagnosticsTestOutcome::class, $diagnostics->outcome?->type);
        self::assertSame('journal', $diagnostics->outcome?->source);
        self::assertArrayNotHasKey('secret', $diagnostics->outcome?->data ?? []);
    }

    public function testInlineRejectedAndFailedAreFound(): void
    {
        $rejected = $this->query($this->recordsFor(LifecycleState::Rejected, 'inline'))->find($this->operationId());
        $failed = $this->query($this->recordsFor(LifecycleState::Failed, 'inline'))->find($this->operationId());

        self::assertInstanceOf(OperationDiagnosticsFound::class, $rejected);
        self::assertSame('validation', $rejected->diagnostics->timeline[2]->data['category']);
        self::assertInstanceOf(OperationDiagnosticsFound::class, $failed);
        self::assertSame(RuntimeException::class, $failed->diagnostics->timeline[2]->data['errorType']);
        self::assertArrayNotHasKey('errorMessage', $failed->diagnostics->timeline[2]->data);
        self::assertArrayNotHasKey('errorMessage', $failed->diagnostics->timeline[3]->data);
    }

    public function testAttemptlessDeferredAcceptanceFailureIsFound(): void
    {
        $records = [
            $this->record(1, JournalEvent::OperationReceived, new OperationReceivedData($this->value()), 'deferred'),
            $this->record(
                2,
                JournalEvent::OperationFailed,
                new OperationFailedData(RuntimeException::class, 'hidden', false),
                'deferred',
            ),
        ];

        $result = $this->query($records)->find($this->operationId());

        self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
        self::assertSame(LifecycleState::Failed, $result->diagnostics->state->current);
        self::assertSame([], $result->diagnostics->attempts);
        self::assertSame('deferred', $result->diagnostics->identity->strategy);
    }

    public function testDeferredJournalOnlyBindingAndPreTransportRejectionsAreFound(): void
    {
        $cases = [
            [
                $this->record(
                    1,
                    JournalEvent::OperationRejected,
                    new OperationRejectedData(RejectionReason::validation('binding.failed')),
                    'deferred',
                ),
            ],
            [
                $this->record(
                    1,
                    JournalEvent::OperationReceived,
                    new OperationReceivedData($this->value()),
                    'deferred',
                ),
                $this->record(
                    2,
                    JournalEvent::OperationRejected,
                    new OperationRejectedData(RejectionReason::forbidden('authorization.denied')),
                    'deferred',
                ),
            ],
        ];

        foreach ($cases as $records) {
            $result = $this->query($records)->find($this->operationId());

            self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
            self::assertSame(LifecycleState::Rejected, $result->diagnostics->state->current);
            self::assertSame('journal', $result->diagnostics->state->source);
            self::assertSame([], $result->diagnostics->attempts);
            self::assertSame('deferred', $result->diagnostics->identity->strategy);
        }
    }

    public function testDeferredAcceptedOrAttemptedJournalWithoutStateFailsIntegrityValidation(): void
    {
        $this->expectException(OperationDiagnosticsException::class);

        $this->query($this->recordsFor(LifecycleState::Rejected, 'deferred'))->find($this->operationId());
    }

    public function testJournalAttemptNumberMustStartAtOne(): void
    {
        $attempt = new JournalAttempt(
            AttemptId::fromString(self::ATTEMPT_ID),
            2,
            new DateTimeImmutable('2026-07-18T00:00:03Z'),
        );
        $records = [
            $this->record(1, JournalEvent::OperationReceived, new OperationReceivedData($this->value())),
            $this->record(2, JournalEvent::AttemptStarted, attempt: $attempt),
        ];

        try {
            $this->query($records)->find($this->operationId());
            self::fail('Expected safe diagnostics integrity failure.');
        } catch (OperationDiagnosticsException $exception) {
            self::assertSame(DiagnosticsFailureCode::IntegrityFailed, $exception->diagnosticsCode);
            self::assertSame('diagnostics.integrity_failed', $exception->getMessage());
        }
    }

    public function testDeferredJournalAcceptsExecutionActorChangesAcrossRetryAndLeaseRecovery(): void
    {
        $records = $this->actorContinuityRecords();

        $result = $this->query($records, $this->actorContinuityState($records), $this->actorContinuityOutcome())->find(
            $this->operationId(),
        );

        self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
        self::assertSame(LifecycleState::Completed, $result->diagnostics->state->current);
        self::assertCount(2, $result->diagnostics->attempts);
        self::assertSame('[masked]', $result->diagnostics->identity->actors?->origin?->id);
        self::assertSame('[masked]', $result->diagnostics->identity->actors?->authorization?->id);
        self::assertSame('[masked]', $result->diagnostics->identity->actors?->execution?->id);
    }

    public function testDeferredJournalStillRejectsDurableActorAndRecordSchemaChanges(): void
    {
        foreach (['origin', 'authorization', 'record_schema'] as $change) {
            $records = $this->actorContinuityRecords();
            $records[4] = $this->record(
                5,
                JournalEvent::AttemptRetryScheduled,
                new AttemptRetryScheduledData(
                    $this->attempt()->id,
                    2,
                    new DateTimeImmutable('2026-07-18T00:01:00Z'),
                    60_000,
                ),
                'deferred',
                $this->attempt(),
                $change === 'origin' ? 'other-origin' : 'origin-private-id',
                $change === 'authorization' ? 'other-authorization' : 'authorization-private-id',
                'worker-recovery',
                $change === 'record_schema' ? 2 : 1,
            );

            try {
                $this->query($records, $this->actorContinuityState($records), $this->actorContinuityOutcome())->find(
                    $this->operationId(),
                );
                self::fail('Expected diagnostics integrity failure.');
            } catch (OperationDiagnosticsException $exception) {
                self::assertSame(DiagnosticsFailureCode::IntegrityFailed, $exception->diagnosticsCode);
            }
        }
    }

    #[DataProvider('deferredStates')]
    public function testDeferredStatesUseTransportAuthority(LifecycleState $state): void
    {
        $records = $this->recordsFor($state, 'deferred');
        $sourceState = $this->state($state, count($records) + 1);
        $outcome = $state === LifecycleState::Completed
            ? new OutcomeRecord(
                $this->operationId(),
                new DiagnosticsTestOutcome('done', 'hidden'),
                new DateTimeImmutable('2026-07-18T00:00:05Z'),
            )
            : null;
        $deadLetter = $state === LifecycleState::DeadLettered
            ? new DiagnosticsDeadLetter(
                self::OPERATION_ID,
                self::ATTEMPT_ID,
                1,
                RuntimeException::class,
                '2026-07-18T00:00:05.000000Z',
            )
            : null;

        $result = $this->query($records, $sourceState, $outcome, $deadLetter)->find($this->operationId());

        self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
        self::assertSame($state, $result->diagnostics->state->current);
        self::assertSame('transport', $result->diagnostics->state->source);
        self::assertSame($state->isTerminal(), $result->diagnostics->state->terminal);
        if ($state === LifecycleState::DeadLettered) {
            self::assertSame(DiagnosticsAvailability::Available, $result->diagnostics->availability->deadLetter);
            self::assertArrayNotHasKey(
                'reasonMessage',
                $result->diagnostics->timeline[array_key_last($result->diagnostics->timeline)]->data,
            );
        }
    }

    /** @return iterable<string, array{LifecycleState}> */
    public static function deferredStates(): iterable
    {
        yield 'accepted' => [LifecycleState::Accepted];
        yield 'running' => [LifecycleState::Running];
        yield 'retry scheduled' => [LifecycleState::RetryScheduled];
        yield 'completed' => [LifecycleState::Completed];
        yield 'rejected' => [LifecycleState::Rejected];
        yield 'failed' => [LifecycleState::Failed];
        yield 'dead lettered' => [LifecycleState::DeadLettered];
    }

    public function testPartiallyPurgedDeferredOperationRemainsFound(): void
    {
        $audits = [
            $this->audit(RetentionPurgeTarget::TransportPayload),
            $this->audit(RetentionPurgeTarget::Journal),
            $this->audit(RetentionPurgeTarget::Outcome),
        ];
        $state = new DiagnosticsDeferredState(
            self::OPERATION_ID,
            'diagnostics.test',
            1,
            LifecycleState::Completed,
            6,
            true,
            1,
            null,
            null,
        );

        $result = $this->query([], $state, audits: $audits)->find($this->operationId());

        self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
        self::assertNull($result->diagnostics->identity->correlationId);
        self::assertSame(DiagnosticsAvailability::Purged, $result->diagnostics->availability->transportPayload);
        self::assertSame(DiagnosticsAvailability::Purged, $result->diagnostics->availability->journal);
        self::assertSame(DiagnosticsAvailability::Purged, $result->diagnostics->availability->outcome);
        self::assertSame([], $result->diagnostics->timeline);
    }

    public function testTransportTombstoneAloneProvesPayloadWasPurged(): void
    {
        $state = new DiagnosticsDeferredState(
            self::OPERATION_ID,
            'diagnostics.test',
            1,
            LifecycleState::Failed,
            6,
            true,
            1,
            null,
            null,
        );

        $result = $this->query([], $state, audits: [$this->audit(RetentionPurgeTarget::Journal)])->find(
            $this->operationId(),
        );

        self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
        self::assertSame(DiagnosticsAvailability::Purged, $result->diagnostics->availability->transportPayload);
        self::assertSame(DiagnosticsAvailability::Purged, $result->diagnostics->availability->journal);
    }

    public function testTransportPurgeAuditContradictsAvailablePayload(): void
    {
        $state = new DiagnosticsDeferredState(
            self::OPERATION_ID,
            'diagnostics.test',
            1,
            LifecycleState::Failed,
            6,
            false,
            1,
            null,
            null,
        );

        $this->expectException(OperationDiagnosticsException::class);

        $this->query([], $state, audits: [
            $this->audit(RetentionPurgeTarget::Journal),
            $this->audit(RetentionPurgeTarget::TransportPayload),
        ])->find($this->operationId());
    }

    public function testStateOnlyAttemptRequiresRunningPairAndRejectsPairOutsideRunning(): void
    {
        $states = [
            new DiagnosticsDeferredState(
                self::OPERATION_ID,
                'diagnostics.test',
                1,
                LifecycleState::Running,
                4,
                false,
                1,
                null,
                null,
            ),
            new DiagnosticsDeferredState(
                self::OPERATION_ID,
                'diagnostics.test',
                1,
                LifecycleState::Failed,
                6,
                false,
                1,
                self::ATTEMPT_ID,
                '2026-07-18T00:00:03.000000Z',
            ),
        ];

        foreach ($states as $state) {
            try {
                $this->query([], $state, audits: [$this->audit(RetentionPurgeTarget::Journal)])->find(
                    $this->operationId(),
                );
                self::fail('Expected safe diagnostics integrity failure.');
            } catch (OperationDiagnosticsException $exception) {
                self::assertSame(DiagnosticsFailureCode::IntegrityFailed, $exception->diagnosticsCode);
            }
        }
    }

    public function testDanglingDeadLetterWithoutIdentitySourceFailsIntegrityValidation(): void
    {
        $deadLetter = new DiagnosticsDeadLetter(
            self::OPERATION_ID,
            self::ATTEMPT_ID,
            1,
            RuntimeException::class,
            '2026-07-18T00:00:05.000000Z',
        );

        try {
            $this->query([], deadLetter: $deadLetter)->find($this->operationId());
            self::fail('Expected safe diagnostics integrity failure.');
        } catch (OperationDiagnosticsException $exception) {
            self::assertSame(DiagnosticsFailureCode::IntegrityFailed, $exception->diagnosticsCode);
            self::assertSame('diagnostics.integrity_failed', $exception->getMessage());
        }
    }

    public function testMissingAndFullyPurgedWithoutIdentityAreUnavailable(): void
    {
        $missing = $this->query([])->find($this->operationId());
        $purged = $this->query([], audits: [$this->audit(RetentionPurgeTarget::Journal)])->find($this->operationId());

        self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $missing);
        self::assertSame('operation.unavailable', $missing->code);
        self::assertInstanceOf(OperationDiagnosticsUnavailable::class, $purged);
        self::assertSame($missing->code, $purged->code);
    }

    public function testSequenceStateOutcomeDeadLetterAndPurgeInconsistenciesFailSafely(): void
    {
        $cases = [
            $this->query([$this->record(
                2,
                JournalEvent::OperationReceived,
                new OperationReceivedData($this->value()),
            )]),
            $this->query(
                $this->recordsFor(LifecycleState::Accepted, 'deferred'),
                $this->state(LifecycleState::Running, 3),
            ),
            $this->query(
                $this->recordsFor(LifecycleState::Completed, 'deferred'),
                $this->state(LifecycleState::Completed, 6),
            ),
            $this->query(
                $this->recordsFor(LifecycleState::DeadLettered, 'deferred'),
                $this->state(LifecycleState::DeadLettered, 6),
            ),
            $this->query(
                $this->recordsFor(LifecycleState::Accepted, 'deferred'),
                $this->state(LifecycleState::Accepted, 3),
                audits: [$this->audit(RetentionPurgeTarget::Journal)],
            ),
        ];

        foreach ($cases as $query) {
            try {
                $query->find($this->operationId());
                self::fail('Expected safe diagnostics integrity failure.');
            } catch (OperationDiagnosticsException $exception) {
                self::assertSame(DiagnosticsFailureCode::IntegrityFailed, $exception->diagnosticsCode);
                self::assertSame('diagnostics.integrity_failed', $exception->getMessage());
            }
        }
    }

    public function testUnknownRetryAttemptFailsIntegrityValidation(): void
    {
        $attempt = $this->attempt();
        $records = [
            $this->record(1, JournalEvent::OperationReceived, new OperationReceivedData($this->value()), 'deferred'),
            $this->record(2, JournalEvent::OperationAccepted, strategy: 'deferred'),
            $this->record(3, JournalEvent::AttemptStarted, attempt: $attempt, strategy: 'deferred'),
            $this->record(
                4,
                JournalEvent::AttemptFailed,
                new AttemptFailedData(RuntimeException::class, 'hidden', true),
                'deferred',
                $attempt,
            ),
            $this->record(
                5,
                JournalEvent::AttemptRetryScheduled,
                new AttemptRetryScheduledData(
                    AttemptId::fromString('019f5b0e-d13f-73b4-8f57-1f60680fd099'),
                    2,
                    new DateTimeImmutable('2026-07-18T00:00:05Z'),
                    1_000,
                ),
                'deferred',
                $attempt,
            ),
        ];

        $this->expectException(OperationDiagnosticsException::class);
        $this->query($records, $this->state(LifecycleState::RetryScheduled, 6))->find($this->operationId());
    }

    public function testStorageAndDecodeFailuresUseDistinctSafeCodes(): void
    {
        $storage = $this->query([], sourceFailure: OperationDiagnosticsException::storageFailed());
        $decode = $this->query(
            $this->recordsFor(LifecycleState::Completed, 'deferred'),
            $this->state(LifecycleState::Completed, 6),
            outcomeFailure: new OutcomeStoreException('restricted detail', previous: new RuntimeException('hidden')),
        );

        try {
            $storage->find($this->operationId());
            self::fail('Expected safe storage failure.');
        } catch (OperationDiagnosticsException $exception) {
            self::assertSame(DiagnosticsFailureCode::StorageFailed, $exception->diagnosticsCode);
            self::assertSame('diagnostics.storage_failed', $exception->getMessage());
        }

        try {
            $decode->find($this->operationId());
            self::fail('Expected safe decode failure.');
        } catch (OperationDiagnosticsException $exception) {
            self::assertSame(DiagnosticsFailureCode::DecodeFailed, $exception->diagnosticsCode);
            self::assertSame('diagnostics.decode_failed', $exception->getMessage());
        }
    }

    /** @return list<JournalRecord> */
    private function recordsFor(LifecycleState $state, string $strategy): array
    {
        $attempt = $this->attempt();
        $records = [
            $this->record(1, JournalEvent::OperationReceived, new OperationReceivedData($this->value()), $strategy),
        ];
        if ($strategy === 'deferred') {
            $records[] = $this->record(2, JournalEvent::OperationAccepted, strategy: $strategy);
        }
        if ($state === LifecycleState::Accepted) {
            return $records;
        }

        $records[] = $this->record(
            count($records) + 1,
            JournalEvent::AttemptStarted,
            attempt: $attempt,
            strategy: $strategy,
        );
        if ($state === LifecycleState::Running) {
            return $records;
        }
        if ($state === LifecycleState::Rejected) {
            $records[] = $this->record(
                count($records) + 1,
                JournalEvent::OperationRejected,
                new OperationRejectedData(RejectionReason::validation('validation.failed')),
                $strategy,
                $attempt,
            );

            return $records;
        }
        if ($state === LifecycleState::Completed) {
            $records[] = $this->record(
                count($records) + 1,
                JournalEvent::AttemptSucceeded,
                attempt: $attempt,
                strategy: $strategy,
            );
            $records[] = $this->record(
                count($records) + 1,
                JournalEvent::OperationCompleted,
                new OperationCompletedData(new DiagnosticsTestOutcome('done', 'hidden')),
                $strategy,
                $attempt,
            );

            return $records;
        }

        $records[] = $this->record(
            count($records) + 1,
            JournalEvent::AttemptFailed,
            new AttemptFailedData(RuntimeException::class, 'hidden', $state === LifecycleState::RetryScheduled),
            $strategy,
            $attempt,
        );
        if ($state === LifecycleState::RetryScheduled) {
            $records[] = $this->record(
                count($records) + 1,
                JournalEvent::AttemptRetryScheduled,
                new AttemptRetryScheduledData(
                    AttemptId::fromString(self::ATTEMPT_ID),
                    2,
                    new DateTimeImmutable('2026-07-18T00:00:05Z'),
                    1_000,
                ),
                $strategy,
                $attempt,
            );

            return $records;
        }
        if ($state === LifecycleState::DeadLettered) {
            $records[] = $this->record(
                count($records) + 1,
                JournalEvent::OperationDeadLettered,
                new OperationDeadLetteredData(
                    AttemptId::fromString(self::ATTEMPT_ID),
                    1,
                    RuntimeException::class,
                    'hidden',
                    new DateTimeImmutable('2026-07-18T00:00:05Z'),
                ),
                $strategy,
                $attempt,
            );

            return $records;
        }

        $records[] = $this->record(
            count($records) + 1,
            JournalEvent::OperationFailed,
            new OperationFailedData(RuntimeException::class, 'hidden', false),
            $strategy,
            $attempt,
        );

        return $records;
    }

    /** @return list<JournalRecord> */
    private function actorContinuityRecords(): array
    {
        $first = $this->attempt();
        $second = new JournalAttempt(
            AttemptId::fromString('019f5b0e-d13f-73b4-8f57-1f60680fd004'),
            2,
            new DateTimeImmutable('2026-07-18T00:00:06Z'),
        );

        return [
            $this->record(
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData($this->value()),
                'deferred',
                originId: 'origin-private-id',
                authorizationId: 'authorization-private-id',
                executionId: 'http-user',
            ),
            $this->record(
                2,
                JournalEvent::OperationAccepted,
                strategy: 'deferred',
                originId: 'origin-private-id',
                authorizationId: 'authorization-private-id',
                executionId: 'http-user',
            ),
            $this->record(
                3,
                JournalEvent::AttemptStarted,
                strategy: 'deferred',
                attempt: $first,
                originId: 'origin-private-id',
                authorizationId: 'authorization-private-id',
                executionId: 'worker-one',
            ),
            $this->record(
                4,
                JournalEvent::AttemptFailed,
                new AttemptFailedData(RuntimeException::class, 'hidden', true),
                'deferred',
                $first,
                'origin-private-id',
                'authorization-private-id',
                'worker-one',
            ),
            $this->record(
                5,
                JournalEvent::AttemptRetryScheduled,
                new AttemptRetryScheduledData($first->id, 2, new DateTimeImmutable('2026-07-18T00:01:00Z'), 60_000),
                'deferred',
                $first,
                'origin-private-id',
                'authorization-private-id',
                'worker-recovery',
            ),
            $this->record(
                6,
                JournalEvent::AttemptStarted,
                strategy: 'deferred',
                attempt: $second,
                originId: 'origin-private-id',
                authorizationId: 'authorization-private-id',
                executionId: 'worker-two',
            ),
            $this->record(
                7,
                JournalEvent::AttemptSucceeded,
                strategy: 'deferred',
                attempt: $second,
                originId: 'origin-private-id',
                authorizationId: 'authorization-private-id',
                executionId: 'worker-three',
            ),
            $this->record(
                8,
                JournalEvent::OperationCompleted,
                new OperationCompletedData(new DiagnosticsTestOutcome('done', 'hidden')),
                'deferred',
                $second,
                'origin-private-id',
                'authorization-private-id',
                'worker-three',
            ),
        ];
    }

    /** @param list<JournalRecord> $records */
    private function actorContinuityState(array $records): DiagnosticsDeferredState
    {
        return new DiagnosticsDeferredState(
            self::OPERATION_ID,
            'diagnostics.test',
            1,
            LifecycleState::Completed,
            count($records) + 1,
            false,
            2,
            null,
            null,
        );
    }

    private function actorContinuityOutcome(): OutcomeRecord
    {
        return new OutcomeRecord(
            $this->operationId(),
            new DiagnosticsTestOutcome('done', 'hidden'),
            new DateTimeImmutable('2026-07-18T00:00:08Z'),
        );
    }

    private function record(
        int $sequence,
        JournalEvent $event,
        ?JournalData $data = null,
        string $strategy = 'inline',
        ?JournalAttempt $attempt = null,
        string $originId = 'origin-private-id',
        ?string $authorizationId = null,
        string $executionId = 'worker-private-id',
        int $recordSchemaVersion = 1,
    ): JournalRecord {
        return new JournalRecord(
            JournalRecordId::fromString(sprintf('019f5b0e-d13f-73b4-8f57-1f60680fd%03d', $sequence + 10)),
            $recordSchemaVersion,
            $event,
            new DateTimeImmutable(sprintf('2026-07-18T00:00:%02d.000000Z', $sequence)),
            $sequence,
            new JournalOperation(
                $this->operationId(),
                'diagnostics.test',
                1,
                $strategy,
                CorrelationId::fromString(self::CORRELATION_ID),
                actorContext: new ActorContext(
                    new ActorRef($originId, 'customer'),
                    $authorizationId === null ? null : new ActorRef($authorizationId, 'customer'),
                    new ActorRef($executionId, 'system'),
                ),
            ),
            $attempt,
            $data ?? new EmptyJournalData(),
        );
    }

    private function attempt(): JournalAttempt
    {
        return new JournalAttempt(
            AttemptId::fromString(self::ATTEMPT_ID),
            1,
            new DateTimeImmutable('2026-07-18T00:00:03Z'),
        );
    }

    private function value(): DiagnosticsTestValue
    {
        return new DiagnosticsTestValue('hello', 'hidden', 'private-name', ['apiToken' => 'hidden', 'visible' => 'ok']);
    }

    private function state(LifecycleState $state, int $nextSequence): DiagnosticsDeferredState
    {
        return new DiagnosticsDeferredState(
            self::OPERATION_ID,
            'diagnostics.test',
            1,
            $state,
            $nextSequence,
            false,
            in_array($state, [LifecycleState::Accepted], strict: true) ? 0 : 1,
            $state === LifecycleState::Running ? self::ATTEMPT_ID : null,
            $state === LifecycleState::Running ? '2026-07-18T00:00:03.000000Z' : null,
        );
    }

    private function audit(RetentionPurgeTarget $target): DiagnosticsPurgeAudit
    {
        return new DiagnosticsPurgeAudit($target, 1, '2026-07-18T01:00:00.000000Z');
    }

    /**
     * @param list<JournalRecord> $records
     * @param list<DiagnosticsPurgeAudit> $audits
     */
    private function query(
        array $records,
        ?DiagnosticsDeferredState $state = null,
        ?OutcomeRecord $outcome = null,
        ?DiagnosticsDeadLetter $deadLetter = null,
        array $audits = [],
        ?OperationDiagnosticsException $sourceFailure = null,
        ?OutcomeStoreException $outcomeFailure = null,
    ): OperationDiagnosticsQuery {
        return new OperationDiagnosticsQuery(
            new DiagnosticsTestJournalReader($records),
            new DiagnosticsTestOutcomeReader($outcome, $outcomeFailure),
            new DiagnosticsTestSourceReader($state, $deadLetter, $audits, $sourceFailure),
        );
    }

    private function operationId(): OperationId
    {
        return OperationId::fromString(self::OPERATION_ID);
    }
}

final readonly class DiagnosticsTestValue implements OperationValue
{
    /** @param array<string, string> $metadata */
    public function __construct(
        public string $message,
        #[Sensitive]
        public string $password,
        #[Sensitive(SensitiveMode::Mask)]
        public string $displayName,
        public array $metadata,
    ) {}
}

final readonly class DiagnosticsTestOutcome implements Outcome
{
    public function __construct(
        public string $message,
        #[Sensitive]
        public string $secret,
    ) {}
}

final readonly class DiagnosticsTestJournalReader implements CanonicalJournalReader
{
    /** @param list<JournalRecord> $records */
    public function __construct(
        private array $records,
    ) {}

    public function records(OperationId $operationId): iterable
    {
        yield from $this->records;
    }
}

final readonly class DiagnosticsTestOutcomeReader implements OutcomeReader
{
    public function __construct(
        private ?OutcomeRecord $record,
        private ?OutcomeStoreException $failure,
    ) {}

    public function find(OperationId $operationId): ?OutcomeRecord
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->record;
    }
}

final readonly class DiagnosticsTestSourceReader implements DiagnosticsSourceReader
{
    /** @param list<DiagnosticsPurgeAudit> $audits */
    public function __construct(
        private ?DiagnosticsDeferredState $state,
        private ?DiagnosticsDeadLetter $deadLetter,
        private array $audits,
        private ?OperationDiagnosticsException $failure,
    ) {}

    public function deferredState(OperationId $operationId): ?DiagnosticsDeferredState
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->state;
    }

    public function deadLetter(OperationId $operationId): ?DiagnosticsDeadLetter
    {
        return $this->deadLetter;
    }

    public function purgeAudits(OperationId $operationId): array
    {
        return $this->audits;
    }
}

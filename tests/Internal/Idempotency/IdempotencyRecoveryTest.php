<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Idempotency;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Outcome;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Idempotency\IdempotencyClaimResult;
use BlackOps\Internal\Idempotency\IdempotencyRecordState;
use BlackOps\Internal\Idempotency\IdempotencyRecovery;
use BlackOps\Internal\Idempotency\IdempotencyReplayFailure;
use BlackOps\Internal\Idempotency\IdempotencyResponseSnapshot;
use BlackOps\Internal\Idempotency\IdempotencyScopeHash;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\IdempotencyStore;
use BlackOps\Internal\Idempotency\InMemoryIdempotencyStore;
use BlackOps\Internal\Idempotency\OperationFingerprint;
use BlackOps\Internal\Idempotency\ProcessingRecord;
use BlackOps\Internal\Idempotency\TerminalRecord;
use BlackOps\Journal\CanonicalJournalReader;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use RuntimeException;

final class IdempotencyRecoveryTest extends TestCase
{
    private const string OPERATION = '019f8fbf-43b2-791c-9e4b-9d73d28e477e';

    public function testInlineCompletedEvidenceIsRecoveredAndSnapshotted(): void
    {
        $store = new InMemoryIdempotencyStore();
        $processing = $this->claim($store, new Inline());
        $recovery = $this->recovery($store, $this->reader($this->records(JournalEvent::OperationCompleted)));

        $result = $recovery->inline($processing);

        self::assertTrue($result?->isCompleted());
        self::assertTrue($result?->isReplayed());
        $terminal = $store->find($processing->scope());
        self::assertInstanceOf(TerminalRecord::class, $terminal);
        self::assertNotNull($terminal->response());
    }

    public function testInlineRejectedEvidenceIsRecoveredWithTypedReason(): void
    {
        $store = new InMemoryIdempotencyStore();
        $processing = $this->claim($store, new Inline());
        $result = $this->recovery($store, $this->reader($this->records(JournalEvent::OperationRejected)))->inline(
            $processing,
        );

        self::assertTrue($result?->isRejected());
        self::assertSame('fixture.rejected', $result?->rejectionReason()->code());
    }

    public function testInlineFailedEvidenceClosesAtSafeReplayFailure(): void
    {
        $store = new InMemoryIdempotencyStore();
        $processing = $this->claim($store, new Inline());
        $this->expectException(IdempotencyReplayFailure::class);

        try {
            $this->recovery($store, $this->reader($this->records(JournalEvent::OperationFailed)))->inline($processing);
        } finally {
            $terminal = $store->find($processing->scope());
            self::assertInstanceOf(TerminalRecord::class, $terminal);
            self::assertTrue($terminal->result()?->isInternalFailure());
            self::assertNotNull($terminal->response());
        }
    }

    public function testInternalFailureSnapshotFailureStillTerminalizesSafeResult(): void
    {
        $store = new InMemoryIdempotencyStore();
        $processing = $this->claim($store, new Inline());
        $responses = $this->createStub(ResponseFactoryInterface::class);
        $responses->method('createResponse')->willThrowException(new RuntimeException('renderer failed'));
        $recovery = new IdempotencyRecovery(
            $this->reader($this->records(JournalEvent::OperationFailed)),
            $store,
            new JsonOperationResponder($responses, new Psr17Factory()),
        );
        $this->expectException(IdempotencyReplayFailure::class);

        try {
            $recovery->inline($processing);
        } finally {
            $terminal = $store->find($processing->scope());
            self::assertInstanceOf(TerminalRecord::class, $terminal);
            self::assertTrue($terminal->result()?->isInternalFailure());
            self::assertNull($terminal->response());
        }
    }

    public function testMissingEvidenceRemainsProcessing(): void
    {
        $store = new InMemoryIdempotencyStore();
        $processing = $this->claim($store, new Inline());

        self::assertNull($this->recovery($store, $this->reader([]))->inline($processing));
        self::assertInstanceOf(ProcessingRecord::class, $store->find($processing->scope()));
    }

    public function testIncompleteValidatedJournalRemainsProcessing(): void
    {
        $store = new InMemoryIdempotencyStore();
        $processing = $this->claim($store, new Inline());

        self::assertNull($this->recovery(
            $store,
            $this->reader(array_slice($this->records(JournalEvent::OperationCompleted), 0, 2)),
        )->inline($processing));
        self::assertInstanceOf(ProcessingRecord::class, $store->find($processing->scope()));
    }

    public function testDeferredAcceptedEvidenceUsesDurableAcceptedAt(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'sqlite3', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE "main"."operations" (operation_id varchar(64) PRIMARY KEY, operation_type varchar(255), state varchar(32), accepted_at varchar(64))',
        );
        $acceptedAt = '2026-07-24 01:02:03.123456+00:00';
        $connection->insert('main.operations', [
            'operation_id' => self::OPERATION,
            'operation_type' => 'fixture.operation',
            'state' => 'completed',
            'accepted_at' => $acceptedAt,
        ]);
        $store = new InMemoryIdempotencyStore();
        $processing = $this->claim($store, new Deferred());
        $recovery = new IdempotencyRecovery(
            $this->reader($this->deferredRecords()),
            $store,
            $this->responder(),
            $connection,
            'main',
        );

        $acknowledgement = $recovery->deferred($processing);

        self::assertSame($acceptedAt, $acknowledgement?->acceptedAt()->format('Y-m-d H:i:s.uP'));
        self::assertTrue($acknowledgement?->isReplayed());
        self::assertNotNull($store->response($processing->operationId()));
    }

    public function testAmbiguousEvidenceFailsClosedWithoutExecutingAnything(): void
    {
        $store = new InMemoryIdempotencyStore();
        $processing = $this->claim($store, new Inline());
        $records = $this->records(JournalEvent::OperationCompleted);
        $records[] = $this->record(
            5,
            JournalEvent::OperationRejected,
            new OperationRejectedData(RejectionReason::conflict('other')),
        );
        $this->expectException(IdempotencyReplayFailure::class);

        $this->recovery($store, $this->reader($records))->inline($processing);
    }

    public function testInlineCasLoserReplaysRejectedWinner(): void
    {
        $delegate = new InMemoryIdempotencyStore();
        $processing = $this->claim($delegate, new Inline());
        $store = new RecoveryRaceStore($delegate, 'winner-inline');
        $result = $this->recovery($store, $this->reader($this->records(JournalEvent::OperationCompleted)))->inline(
            $processing,
        );

        self::assertTrue($result?->isRejected());
        self::assertSame('winner.result', $result?->rejectionReason()->code());
        self::assertTrue($result?->isReplayed());
    }

    public function testInlineCasLoserReplaysCompletedWinner(): void
    {
        $delegate = new InMemoryIdempotencyStore();
        $processing = $this->claim($delegate, new Inline());
        $store = new RecoveryRaceStore($delegate, 'winner-completed');
        $result = $this->recovery($store, $this->reader($this->records(JournalEvent::OperationCompleted)))->inline(
            $processing,
        );

        self::assertTrue($result?->isCompleted());
        self::assertSame('winner', $result?->outcome()->value ?? null);
        self::assertTrue($result?->isReplayed());
    }

    public function testInlineCasLoserPreservesProcessing(): void
    {
        $delegate = new InMemoryIdempotencyStore();
        $processing = $this->claim($delegate, new Inline());
        $store = new RecoveryRaceStore($delegate, 'processing');
        $result = $this->recovery($store, $this->reader($this->records(JournalEvent::OperationCompleted)))->inline(
            $processing,
        );

        self::assertSame('idempotency_in_progress', $result?->rejectionReason()->code());
        self::assertInstanceOf(ProcessingRecord::class, $delegate->find($processing->scope()));
    }

    public function testDeferredCasLoserReplaysDurableWinnerAcceptedAt(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'sqlite3', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE "main"."operations" (operation_id varchar(64) PRIMARY KEY, operation_type varchar(255), state varchar(32), accepted_at varchar(64))',
        );
        $connection->insert('main.operations', [
            'operation_id' => self::OPERATION,
            'operation_type' => 'fixture.operation',
            'state' => 'completed',
            'accepted_at' => '2026-07-24 01:02:03.123456+00:00',
        ]);
        $delegate = new InMemoryIdempotencyStore();
        $processing = $this->claim($delegate, new Deferred());
        $store = new RecoveryRaceStore($delegate, 'winner-deferred');
        $recovery = new IdempotencyRecovery(
            $this->reader($this->deferredRecords()),
            $store,
            $this->responder(),
            $connection,
            'main',
        );

        $acknowledgement = $recovery->deferred($processing);

        self::assertSame(
            '2026-07-24T02:03:04.654321+00:00',
            $acknowledgement?->acceptedAt()->format('Y-m-d\TH:i:s.uP'),
        );
    }

    public function testInvalidDeferredEvidenceStorageFailureLeavesProcessingAndUsesSafeBoundary(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'sqlite3', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE "main"."operations" (operation_id varchar(64) PRIMARY KEY, operation_type varchar(255), state varchar(32), accepted_at varchar(64))',
        );
        $connection->insert('main.operations', [
            'operation_id' => self::OPERATION,
            'operation_type' => 'fixture.operation',
            'state' => 'completed',
            'accepted_at' => '2026-07-24 01:02:03.123456+00:00',
        ]);
        $delegate = new InMemoryIdempotencyStore();
        $processing = $this->claim($delegate, new Deferred());
        $store = new RecoveryRaceStore($delegate, 'reject-deferred-terminal');
        $recovery = new IdempotencyRecovery(
            $this->reader($this->records(JournalEvent::OperationCompleted)),
            $store,
            $this->responder(),
            $connection,
            'main',
        );
        $this->expectException(IdempotencyReplayFailure::class);

        try {
            $recovery->deferred($processing);
        } finally {
            self::assertInstanceOf(ProcessingRecord::class, $delegate->find($processing->scope()));
        }
    }

    public function testInvalidEvidenceCasWinnerIsReplayed(): void
    {
        $delegate = new InMemoryIdempotencyStore();
        $processing = $this->claim($delegate, new Inline());
        $store = new RecoveryRaceStore($delegate, 'internal-winner');
        $records = $this->records(JournalEvent::OperationCompleted);
        $records[] = $this->record(
            5,
            JournalEvent::OperationRejected,
            new OperationRejectedData(RejectionReason::conflict('other')),
        );

        $result = $this->recovery($store, $this->reader($records))->inline($processing);

        self::assertTrue($result?->isCompleted());
        self::assertSame('winner', $result?->outcome()->value ?? null);
    }

    public function testInvalidEvidenceCasProcessingRemainsInProgress(): void
    {
        $delegate = new InMemoryIdempotencyStore();
        $processing = $this->claim($delegate, new Inline());
        $store = new RecoveryRaceStore($delegate, 'internal-processing');
        $records = $this->records(JournalEvent::OperationCompleted);
        $records[] = $this->record(
            5,
            JournalEvent::OperationRejected,
            new OperationRejectedData(RejectionReason::conflict('other')),
        );

        $result = $this->recovery($store, $this->reader($records))->inline($processing);

        self::assertSame('idempotency_in_progress', $result?->rejectionReason()->code());
    }

    public function testProductionDependenciesExposeCustomIdempotencySchemaSetting(): void
    {
        $factory = new Psr17Factory();
        $dependencies = new \BlackOps\Internal\Runtime\ProductionRuntimeDependencies(
            new \BlackOps\Transport\PostgreSql\PostgreSqlSystemClock(),
            new class implements \BlackOps\Journal\CanonicalJournalWriter {
                public function append(JournalRecord $record): void {}
            },
            $factory,
            $factory,
            idempotencySchema: 'custom_schema',
        );

        self::assertSame('custom_schema', $dependencies->idempotencySchema);
    }

    private function recovery(IdempotencyStore $store, CanonicalJournalReader $reader): IdempotencyRecovery
    {
        return new IdempotencyRecovery($reader, $store, $this->responder());
    }

    private function responder(): JsonOperationResponder
    {
        $factory = new Psr17Factory();

        return new JsonOperationResponder($factory, $factory);
    }

    private function claim(InMemoryIdempotencyStore $store, ExecutionStrategy $strategy): ProcessingRecord
    {
        $key = new IdempotencyKey('recovery-key');
        $scope = new IdempotencyScopeHasher()->hash(
            'fixture.operation',
            new \BlackOps\Core\ActorRef('user', 'user'),
            $key,
        );
        $fingerprint = new OperationFingerprint(1, str_repeat('a', 64));
        $created = new DateTimeImmutable('2026-07-24T00:00:00Z');
        $record = $store
            ->claim(
                $scope,
                $key->hash(),
                $fingerprint,
                OperationId::fromString(self::OPERATION),
                $strategy,
                $created,
                $created->modify('+1 day'),
            )
            ->record();
        self::assertInstanceOf(ProcessingRecord::class, $record);

        return $record;
    }

    /** @param list<JournalRecord> $records */
    private function reader(array $records): CanonicalJournalReader
    {
        return new class($records) implements CanonicalJournalReader {
            /** @param list<JournalRecord> $records */
            public function __construct(
                private array $records,
            ) {}

            public function records(OperationId $operationId): iterable
            {
                yield from $this->records;
            }
        };
    }

    /** @return list<JournalRecord> */
    private function records(JournalEvent $terminal): array
    {
        $records = [
            $this->record(1, JournalEvent::OperationReceived),
            $this->record(2, JournalEvent::AttemptStarted, attempt: $this->attempt()),
        ];
        if ($terminal === JournalEvent::OperationCompleted) {
            $records[] = $this->record(3, JournalEvent::AttemptSucceeded, attempt: $this->attempt());
            $records[] = $this->record(
                4,
                $terminal,
                new OperationCompletedData(new RecoveryOutcome('ok')),
                $this->attempt(),
            );
        } elseif ($terminal === JournalEvent::OperationRejected) {
            $records[] = $this->record(
                3,
                $terminal,
                new OperationRejectedData(RejectionReason::conflict('fixture.rejected')),
            );
        } else {
            $records[] = $this->record(
                3,
                JournalEvent::AttemptFailed,
                new AttemptFailedData('Failure', 'hidden', false),
                $this->attempt(),
            );
            $records[] = $this->record(
                4,
                $terminal,
                new \BlackOps\Journal\Data\OperationFailedData('Failure', 'hidden', false),
                $this->attempt(),
            );
        }

        return $records;
    }

    /** @return list<JournalRecord> */
    private function deferredRecords(): array
    {
        return [
            $this->record(1, JournalEvent::OperationReceived, strategy: 'deferred'),
            $this->record(2, JournalEvent::OperationAccepted, strategy: 'deferred'),
        ];
    }

    private function record(
        int $sequence,
        JournalEvent $event,
        \BlackOps\Journal\JournalData $data = new EmptyJournalData(),
        ?JournalAttempt $attempt = null,
        string $strategy = 'inline',
    ): JournalRecord {
        return new JournalRecord(
            JournalRecordId::fromString(sprintf('019f8fbf-5000-7000-8000-%012d', $sequence)),
            1,
            $event,
            new DateTimeImmutable(sprintf('2026-07-24T00:00:%02dZ', $sequence)),
            $sequence,
            new JournalOperation(
                $this->operationId(),
                'fixture.operation',
                1,
                $strategy,
                CorrelationId::fromString('019f8fbf-6000-7000-8000-000000000001'),
            ),
            $attempt,
            $data,
        );
    }

    private function attempt(): JournalAttempt
    {
        return new JournalAttempt(
            AttemptId::fromString('019f8fbf-7000-7000-8000-000000000001'),
            1,
            new DateTimeImmutable('2026-07-24T00:00:02Z'),
        );
    }

    private function operationId(): OperationId
    {
        return OperationId::fromString(self::OPERATION);
    }
}

final readonly class RecoveryOutcome implements Outcome
{
    public function __construct(
        public string $value,
    ) {}
}

final class RecoveryRaceStore implements IdempotencyStore
{
    private bool $raced = false;

    public function __construct(
        private InMemoryIdempotencyStore $delegate,
        private string $mode,
    ) {}

    public function claim(
        IdempotencyScopeHash $scope,
        \BlackOps\Idempotency\IdempotencyKeyHash $key,
        \BlackOps\Internal\Idempotency\OperationFingerprint $fingerprint,
        OperationId $operationId,
        ExecutionStrategy $strategy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): IdempotencyClaimResult {
        return $this->delegate->claim($scope, $key, $fingerprint, $operationId, $strategy, $createdAt, $expiresAt);
    }

    public function terminalize(
        OperationId $operationId,
        TerminalRecord $record,
        IdempotencyRecordState $expectedState = IdempotencyRecordState::Processing,
    ): bool {
        if ($this->raced) {
            return $this->delegate->terminalize($operationId, $record, $expectedState);
        }
        $this->raced = true;
        if (in_array($this->mode, ['processing', 'internal-processing'], true)) {
            return false;
        }
        if ($this->mode === 'reject-deferred-terminal') {
            throw new DeferredTransportException('Projection check rejected recovery terminal.');
        }
        $winner = $record;
        if ($this->mode === 'internal-winner') {
            $winner = new TerminalRecord(
                $record->scope(),
                $record->key(),
                $record->fingerprint(),
                $record->operationId(),
                $record->strategy(),
                $record->createdAt(),
                $record->expiresAt(),
                $record->response(),
                new \BlackOps\Internal\Idempotency\IdempotencyResultSnapshot(OperationResult::completed(
                    new RecoveryOutcome('winner'),
                    $record->operationId(),
                )),
            );
        } elseif ($this->mode === 'winner-inline') {
            $winner = new TerminalRecord(
                $record->scope(),
                $record->key(),
                $record->fingerprint(),
                $record->operationId(),
                $record->strategy(),
                $record->createdAt(),
                $record->expiresAt(),
                new IdempotencyResponseSnapshot(1, 409, ['content-type' => 'application/json'], '{"winner":true}'),
                new \BlackOps\Internal\Idempotency\IdempotencyResultSnapshot(OperationResult::rejected(
                    RejectionReason::conflict('winner.result'),
                    $record->operationId(),
                )),
            );
        } elseif ($this->mode === 'winner-completed') {
            $winner = new TerminalRecord(
                $record->scope(),
                $record->key(),
                $record->fingerprint(),
                $record->operationId(),
                $record->strategy(),
                $record->createdAt(),
                $record->expiresAt(),
                new IdempotencyResponseSnapshot(1, 200, ['content-type' => 'application/json'], '{"winner":true}'),
                new \BlackOps\Internal\Idempotency\IdempotencyResultSnapshot(OperationResult::completed(
                    new RecoveryOutcome('winner'),
                    $record->operationId(),
                )),
            );
        } elseif ($this->mode === 'winner-deferred') {
            $winner = new TerminalRecord(
                $record->scope(),
                $record->key(),
                $record->fingerprint(),
                $record->operationId(),
                $record->strategy(),
                $record->createdAt(),
                $record->expiresAt(),
                $record->response(),
                $record->result(),
                new DateTimeImmutable('2026-07-24T02:03:04.654321+00:00'),
            );
        }
        $this->delegate->terminalize($operationId, $winner, $expectedState);

        return false;
    }

    public function find(IdempotencyScopeHash $scope): \BlackOps\Internal\Idempotency\ProcessingRecord|TerminalRecord|null
    {
        return $this->delegate->find($scope);
    }

    public function attachResponse(OperationId $operationId, IdempotencyResponseSnapshot $snapshot): bool
    {
        return $this->delegate->attachResponse($operationId, $snapshot);
    }

    public function response(OperationId $operationId): ?IdempotencyResponseSnapshot
    {
        return $this->delegate->response($operationId);
    }
}

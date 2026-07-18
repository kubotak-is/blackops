<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Internal\Diagnostics\DiagnosticsAvailability;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsFound;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsQuery;
use BlackOps\Internal\Diagnostics\PostgreSqlDiagnosticsSourceReader;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlDiagnosticsReader;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PostgreSqlDiagnosticsQueryIntegrationTest extends TestCase
{
    private const SCHEMA = 'blackops_p14_003_query';
    private const OPERATION_ID = '019f5b0e-d13f-73b4-8f57-1f60680ff001';
    private const ATTEMPT_ID = '019f5b0e-d13f-73b4-8f57-1f60680ff002';
    private const CORRELATION_ID = '019f5b0e-d13f-73b4-8f57-1f60680ff003';

    private Connection $connection;
    private PostgreSqlCanonicalJournalStore $journal;
    private PostgreSqlOutcomeStore $outcomes;
    private OperationDiagnosticsQuery $query;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');

        $sender = new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA);
        $sender->migrate();
        $sender->enqueue(
            new DeferredOperationMessage(
                $this->operationId(),
                'diagnostics.integration',
                1,
                '{"private":"payload"}',
                '{"private":"context"}',
                new DateTimeImmutable('2026-07-18T00:00:00Z'),
            ),
        );

        $this->journal = new PostgreSqlCanonicalJournalStore($this->connection, self::SCHEMA);
        $this->journal->migrate();
        foreach ($this->completedRecords() as $record) {
            $this->journal->append($record);
        }
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET state = :state, next_sequence = 6, attempt_number = 1
            WHERE operation_id = :operation_id',
            [
                'state' => LifecycleState::Completed->value,
                'operation_id' => self::OPERATION_ID,
            ],
        );

        $this->outcomes = new PostgreSqlOutcomeStore($this->connection, self::SCHEMA);
        $this->outcomes->save(
            new OutcomeRecord(
                $this->operationId(),
                new PostgreSqlDiagnosticsOutcome('ready', 'private-outcome'),
                new DateTimeImmutable('2026-07-18T00:00:05Z'),
            ),
        );
        $this->query = new OperationDiagnosticsQuery(
            $this->journal,
            $this->outcomes,
            new PostgreSqlDiagnosticsSourceReader(new PostgreSqlDiagnosticsReader($this->connection, self::SCHEMA)),
        );
    }

    public function testQueriesCompletedDeferredOperationThroughRealReaders(): void
    {
        $result = $this->query->find($this->operationId());

        self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
        self::assertSame(LifecycleState::Completed, $result->diagnostics->state->current);
        self::assertSame('transport', $result->diagnostics->state->source);
        self::assertSame('deferred', $result->diagnostics->identity->strategy);
        self::assertSame('[masked]', $result->diagnostics->identity->actors?->origin?->id);
        self::assertArrayNotHasKey('credential', $result->diagnostics->timeline[0]->data);
        self::assertSame('visible', $result->diagnostics->timeline[0]->data['message']);
        self::assertSame(DiagnosticsAvailability::Available, $result->diagnostics->availability->outcome);
        self::assertSame('outcome_store', $result->diagnostics->outcome?->source);
        self::assertArrayNotHasKey('secret', $result->diagnostics->outcome?->data ?? []);
        self::assertCount(1, $result->diagnostics->attempts);
        self::assertSame([3, 4, 5], $result->diagnostics->attempts[0]->events);
    }

    public function testReturnsFoundWithPerSourcePurgedAvailabilityAfterRetention(): void
    {
        $this->connection->transactional(function (): void {
            $this->connection->executeStatement('DELETE FROM ' . self::SCHEMA . '.outcomes WHERE operation_id = :id', [
                'id' => self::OPERATION_ID,
            ]);
            $this->connection->executeStatement('DELETE FROM ' . self::SCHEMA . '.journal WHERE operation_id = :id', [
                'id' => self::OPERATION_ID,
            ]);
            $this->connection->executeStatement('UPDATE ' . self::SCHEMA . '.operations
                SET encoded_payload = NULL, encoded_context = NULL, payload_purged_at = :purged_at
                WHERE operation_id = :id', [
                'id' => self::OPERATION_ID,
                'purged_at' => '2026-07-18T01:00:00Z',
            ]);
            foreach ([
                RetentionPurgeTarget::TransportPayload,
                RetentionPurgeTarget::Journal,
                RetentionPurgeTarget::Outcome,
            ] as $index => $target) {
                $this->connection->executeStatement(
                    'INSERT INTO '
                    . self::SCHEMA
                    . '.retention_purge_audits (
                    audit_id, operation_id, target, affected_count, policy, purged_at, purged_by
                ) VALUES (:audit_id, :operation_id, :target, 1, :restricted_policy, :purged_at, :restricted_actor)',
                    [
                        'audit_id' => sprintf('019f5b0e-d13f-73b4-8f57-1f60680ff%03d', $index + 10),
                        'operation_id' => self::OPERATION_ID,
                        'target' => $target->value,
                        'restricted_policy' => 'private-policy',
                        'purged_at' => '2026-07-18T01:00:00Z',
                        'restricted_actor' => 'private-actor',
                    ],
                );
            }
        });

        $result = $this->query->find($this->operationId());

        self::assertInstanceOf(OperationDiagnosticsFound::class, $result);
        self::assertSame(LifecycleState::Completed, $result->diagnostics->state->current);
        self::assertNull($result->diagnostics->identity->correlationId);
        self::assertSame(DiagnosticsAvailability::Purged, $result->diagnostics->availability->transportPayload);
        self::assertSame(DiagnosticsAvailability::Purged, $result->diagnostics->availability->journal);
        self::assertSame(DiagnosticsAvailability::Purged, $result->diagnostics->availability->outcome);
        self::assertSame([], $result->diagnostics->timeline);
        self::assertNull($result->diagnostics->outcome);
    }

    /** @return list<JournalRecord> */
    private function completedRecords(): array
    {
        $attempt = new JournalAttempt(
            AttemptId::fromString(self::ATTEMPT_ID),
            1,
            new DateTimeImmutable('2026-07-18T00:00:03Z'),
        );

        return [
            $this->record(
                1,
                JournalEvent::OperationReceived,
                new OperationReceivedData(new PostgreSqlDiagnosticsValue('visible', 'private-credential')),
            ),
            $this->record(2, JournalEvent::OperationAccepted),
            $this->record(3, JournalEvent::AttemptStarted, attempt: $attempt),
            $this->record(4, JournalEvent::AttemptSucceeded, attempt: $attempt),
            $this->record(
                5,
                JournalEvent::OperationCompleted,
                new OperationCompletedData(new PostgreSqlDiagnosticsOutcome('ready', 'private-outcome')),
                $attempt,
            ),
        ];
    }

    private function record(
        int $sequence,
        JournalEvent $event,
        ?JournalData $data = null,
        ?JournalAttempt $attempt = null,
    ): JournalRecord {
        return new JournalRecord(
            JournalRecordId::fromString(sprintf('019f5b0e-d13f-73b4-8f57-1f60680ff%03d', $sequence + 20)),
            1,
            $event,
            new DateTimeImmutable(sprintf('2026-07-18T00:00:%02dZ', $sequence)),
            $sequence,
            new JournalOperation(
                $this->operationId(),
                'diagnostics.integration',
                1,
                'deferred',
                CorrelationId::fromString(self::CORRELATION_ID),
                actorContext: new ActorContext(
                    new ActorRef('private-origin', 'customer'),
                    null,
                    new ActorRef('private-worker', 'system'),
                ),
            ),
            $attempt,
            $data ?? new EmptyJournalData(),
        );
    }

    private function operationId(): OperationId
    {
        return OperationId::fromString(self::OPERATION_ID);
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
    }
}

final readonly class PostgreSqlDiagnosticsValue implements OperationValue
{
    public function __construct(
        public string $message,
        #[Sensitive]
        public string $credential,
    ) {}
}

final readonly class PostgreSqlDiagnosticsOutcome implements Outcome
{
    public function __construct(
        public string $status,
        #[Sensitive]
        public string $secret,
    ) {}
}

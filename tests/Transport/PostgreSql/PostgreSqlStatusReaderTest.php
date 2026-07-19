<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusFailureKind;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReader;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReadFailed;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PostgreSqlStatusReaderTest extends TestCase
{
    private const string SCHEMA = 'blackops_p16_003_reader';
    private const string OPERATION_ID = '019f70ab-1000-7000-8000-000000000001';
    private const string JOURNAL_ONLY_ID = '019f70ab-1000-7000-8000-000000000002';

    private Connection $connection;
    private PostgreSqlCanonicalJournalStore $journal;
    private PostgreSqlStatusReader $reader;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $sender = new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA);
        $sender->migrate();
        $sender->enqueue(
            new DeferredOperationMessage(
                $this->operationId(),
                'status.reader',
                1,
                '{"private":"payload-secret"}',
                '{"private":"context-secret"}',
                new DateTimeImmutable('2026-07-19T10:00:00+09:00'),
            ),
        );
        $this->journal = new PostgreSqlCanonicalJournalStore($this->connection, self::SCHEMA);
        $this->journal->migrate();
        $this->reader = new PostgreSqlStatusReader($this->connection, self::SCHEMA);
    }

    public function testSubjectProjectsOnlyIdentityTypeAndOriginActor(): void
    {
        $this->journal->append($this->received($this->operationId(), 'status.reader', 'deferred'));

        $subject = $this->reader->findSubject($this->operationId());

        self::assertNotNull($subject);
        self::assertSame(self::OPERATION_ID, $subject->operationId);
        self::assertSame('status.reader', $subject->operationType);
        self::assertSame('origin-private-id', $subject->originActorId);
        self::assertSame('customer', $subject->originActorType);
        self::assertSame(
            ['operationId', 'operationType', 'originActorId', 'originActorType'],
            array_keys(get_object_vars($subject)),
        );
        self::assertStringNotContainsString('secret', json_encode($subject, JSON_THROW_ON_ERROR));
    }

    public function testOperationsRowWithoutJournalKeepsOriginActorNull(): void
    {
        $subject = $this->reader->findSubject($this->operationId());

        self::assertNotNull($subject);
        self::assertSame('status.reader', $subject->operationType);
        self::assertNull($subject->originActorId);
        self::assertNull($subject->originActorType);
    }

    public function testJournalOnlySubjectSupportsInlineAndPreAcceptanceTerminalIdentity(): void
    {
        $id = OperationId::fromString(self::JOURNAL_ONLY_ID);
        $this->journal->append($this->received($id, 'status.inline', 'inline'));

        $subject = $this->reader->findSubject($id);

        self::assertNotNull($subject);
        self::assertSame(self::JOURNAL_ONLY_ID, $subject->operationId);
        self::assertSame('status.inline', $subject->operationType);
        self::assertSame('origin-private-id', $subject->originActorId);
    }

    public function testUnknownDoesNotInferSubjectFromPurgeAudit(): void
    {
        $unknown = OperationId::fromString('019f70ab-1000-7000-8000-000000000099');
        $this->insertPurge($unknown, RetentionPurgeTarget::Journal);

        self::assertNull($this->reader->findSubject($unknown));
    }

    public function testOperationsAndJournalTypeMismatchIsIntegrityFailure(): void
    {
        $this->journal->append($this->received($this->operationId(), 'status.other', 'deferred'));

        try {
            $this->reader->findSubject($this->operationId());
            self::fail('Expected subject identity mismatch.');
        } catch (PostgreSqlStatusReadFailed $exception) {
            self::assertSame(PostgreSqlStatusFailureKind::Integrity, $exception->kind);
            self::assertSame('PostgreSQL status read failed.', $exception->getMessage());
        }
    }

    public function testPartialOriginActorIsIntegrityFailure(): void
    {
        $this->journal->append($this->received($this->operationId(), 'status.reader', 'deferred'));
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . ".journal
            SET encoded_record = convert_to(
                (convert_from(encoded_record, 'UTF8')::jsonb #- '{operation,actors,origin,type}')::text,
                'UTF8'
            )
            WHERE operation_id = :operation_id",
            ['operation_id' => self::OPERATION_ID],
        );

        $this->expectException(PostgreSqlStatusReadFailed::class);

        $this->reader->findSubject($this->operationId());
    }

    public function testReadsDeferredStateOutcomeDeadLetterAndPurgeEvidenceWithoutRestrictedDetail(): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET state = :state,
                attempt_number = 2,
                next_sequence = 7,
                available_at = :available_at
            WHERE operation_id = :operation_id',
            [
                'state' => LifecycleState::RetryScheduled->value,
                'available_at' => '2026-07-19T09:30:00.123456Z',
                'operation_id' => self::OPERATION_ID,
            ],
        );
        $this->insertPurge($this->operationId(), RetentionPurgeTarget::Journal);

        $state = $this->reader->deferredState($this->operationId());
        $targets = $this->reader->purgeTargets($this->operationId());

        self::assertNotNull($state);
        self::assertSame(LifecycleState::RetryScheduled, $state->state);
        self::assertSame(2, $state->attemptNumber);
        self::assertSame('2026-07-19T09:30:00.123456+00:00', $state->availableAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame([RetentionPurgeTarget::Journal], $targets);
        self::assertFalse($this->reader->outcomeExists($this->operationId()));
        self::assertFalse($this->reader->deadLetterExists($this->operationId()));
    }

    public function testSubjectSqlProjectionDoesNotSelectRestrictedColumnsOrWholeCanonicalRecord(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Transport/PostgreSql/PostgreSqlStatusReader.php');
        self::assertIsString($source);
        $start = strpos($source, 'private function transportSubject');
        $end = strpos($source, 'private function rowExists');
        self::assertIsInt($start);
        self::assertIsInt($end);
        $subjectSource = substr($source, $start, $end - $start);

        self::assertStringContainsString("#>> '{operation,type}' AS operation_type", $subjectSource);
        self::assertStringContainsString("#>> '{operation,actors,origin,id}' AS origin_actor_id", $subjectSource);
        self::assertStringNotContainsString('AS encoded_record', $subjectSource);
        self::assertStringNotContainsString('encoded_payload', $subjectSource);
        self::assertStringNotContainsString('encoded_context', $subjectSource);
        self::assertStringNotContainsString('retentionPurgeAuditsTable', $subjectSource);
        self::assertStringNotContainsString('outcomesTable', $subjectSource);
        self::assertStringNotContainsString('deadLettersTable', $subjectSource);
    }

    public function testStorageFailureUsesSafeClassification(): void
    {
        $this->connection->executeStatement('DROP SCHEMA ' . self::SCHEMA . ' CASCADE');

        try {
            $this->reader->findSubject($this->operationId());
            self::fail('Expected storage failure.');
        } catch (PostgreSqlStatusReadFailed $exception) {
            self::assertSame(PostgreSqlStatusFailureKind::Storage, $exception->kind);
            self::assertSame('PostgreSQL status read failed.', $exception->getMessage());
        }
    }

    private function received(OperationId $operationId, string $type, string $strategy): JournalRecord
    {
        return new JournalRecord(
            JournalRecordId::fromString('019f70ab-2000-7000-8000-' . substr($operationId->toString(), -12)),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-19T00:00:01Z'),
            1,
            new JournalOperation(
                $operationId,
                $type,
                1,
                $strategy,
                CorrelationId::fromString('019f70ab-3000-7000-8000-' . substr($operationId->toString(), -12)),
                actorContext: new ActorContext(
                    new ActorRef('origin-private-id', 'customer'),
                    new ActorRef('authorization-private-id', 'user'),
                    new ActorRef('execution-private-id', 'worker'),
                ),
            ),
            null,
            new OperationReceivedData(new PostgreSqlStatusReaderValue('payload-secret')),
        );
    }

    private function insertPurge(OperationId $operationId, RetentionPurgeTarget $target): void
    {
        static $audit = 1;
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.retention_purge_audits (
            audit_id, operation_id, target, affected_count, policy, purged_at, purged_by
        ) VALUES (
            :audit_id, :operation_id, :target, 1, :policy, :purged_at, :actor
        )', [
            'audit_id' => sprintf('019f70ab-4000-7000-8000-%012d', $audit++),
            'operation_id' => $operationId->toString(),
            'target' => $target->value,
            'policy' => 'private-policy',
            'purged_at' => '2026-07-19T01:00:00Z',
            'actor' => 'private-actor',
        ]);
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

final readonly class PostgreSqlStatusReaderValue implements OperationValue
{
    public function __construct(
        public string $secret,
    ) {}
}

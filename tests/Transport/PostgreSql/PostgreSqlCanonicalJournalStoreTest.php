<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Validation\Violation;
use BlackOps\Internal\Migration\DoctrineMigrationDependencyFactory;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\Exception\JournalReadFailed;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRecordCodec;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PostgreSqlCanonicalJournalStoreTest extends TestCase
{
    private const SCHEMA = 'blackops_p1_015';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const ATTEMPT_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687698';
    private const CORRELATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687699';
    private const RECORD_ID_1 = '019f32ab-2be0-7b38-a0a7-1ab2f968769a';
    private const RECORD_ID_2 = '019f32ab-2be0-7b38-a0a7-1ab2f968769b';
    private const RECORD_ID_3 = '019f32ab-2be0-7b38-a0a7-1ab2f968769c';

    private Connection $connection;
    private PostgreSqlCanonicalJournalStore $store;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->store = new PostgreSqlCanonicalJournalStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $this->store->migrate();
    }

    public function testMigrationCreatesJournalTableWithExpectedShape(): void
    {
        $encodedRecordType = $this->connection->fetchOne("SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'journal'
              AND column_name = 'encoded_record'");

        $primaryKeyCount = $this->connection->fetchOne("SELECT count(*)
            FROM information_schema.table_constraints
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'journal'
              AND constraint_type = 'PRIMARY KEY'");

        $uniqueCount = $this->connection->fetchOne("SELECT count(*)
            FROM information_schema.table_constraints
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'journal'
              AND constraint_type = 'UNIQUE'");

        self::assertSame('bytea', $encodedRecordType);
        self::assertSame(1, (int) $primaryKeyCount);
        self::assertSame(1, (int) $uniqueCount);
    }

    public function testProgrammaticMigrationCreatesDoctrineCompatibleMetadataStorage(): void
    {
        $columns = $this->connection->fetchAllKeyValue("SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'schema_migrations'
            ORDER BY column_name");
        $versionLength = $this->connection->fetchOne("SELECT character_maximum_length
            FROM information_schema.columns
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'schema_migrations'
              AND column_name = 'version'");

        self::assertSame(
            [
                'executed_at' => 'timestamp without time zone',
                'execution_time' => 'integer',
                'version' => 'character varying',
            ],
            $columns,
        );
        self::assertSame(191, (int) $versionLength);

        $metadata = DoctrineMigrationDependencyFactory::create($this->connection, self::SCHEMA)->getMetadataStorage();
        $metadata->ensureInitialized();

        self::assertCount(0, $metadata->getExecutedMigrations());
    }

    public function testAppendsAndReadsRecordsInSequenceOrder(): void
    {
        $first = $this->record(
            self::RECORD_ID_1,
            JournalEvent::OperationReceived,
            1,
            new OperationReceivedData(new StoredJournalValue('hello')),
            null,
        );
        $second = $this->record(
            self::RECORD_ID_2,
            JournalEvent::OperationCompleted,
            2,
            new OperationCompletedData(new StoredJournalOutcome('done')),
            new JournalAttempt(
                AttemptId::fromString(self::ATTEMPT_ID),
                1,
                new DateTimeImmutable('2026-07-08T00:00:01.000000Z'),
            ),
        );

        $this->store->append($second);
        $this->store->append($first);

        $records = array_values(iterator_to_array($this->store->records(OperationId::fromString(self::OPERATION_ID))));

        self::assertCount(2, $records);
        self::assertSame([1, 2], array_column($records, 'sequence'));
        self::assertSame(JournalEvent::OperationReceived, $records[0]->event);
        self::assertSame(JournalEvent::OperationCompleted, $records[1]->event);
        self::assertInstanceOf(OperationReceivedData::class, $records[0]->data);
        self::assertInstanceOf(StoredJournalValue::class, $records[0]->data->value);
        self::assertSame('hello', $records[0]->data->value->message);
        self::assertInstanceOf(OperationCompletedData::class, $records[1]->data);
        self::assertInstanceOf(StoredJournalOutcome::class, $records[1]->data->outcome);
        self::assertSame('done', $records[1]->data->outcome->message);
    }

    public function testAppendsAndReadsRejectedData(): void
    {
        $this->store->append($this->record(
            self::RECORD_ID_3,
            JournalEvent::OperationRejected,
            1,
            new OperationRejectedData(RejectionReason::conflict('postgres_rejected')),
        ));

        $records = array_values(iterator_to_array($this->store->records(OperationId::fromString(self::OPERATION_ID))));

        self::assertCount(1, $records);
        self::assertInstanceOf(OperationRejectedData::class, $records[0]->data);
        self::assertSame(RejectionCategory::Conflict, $records[0]->data->reason->category());
        self::assertSame('postgres_rejected', $records[0]->data->reason->code());
        self::assertSame([], $records[0]->data->reason->violations());
    }

    public function testAppendsAndReadsCanonicalActorContext(): void
    {
        $actors = new ActorContext(
            new ActorRef('canonical-origin-123', 'user'),
            null,
            new ActorRef('canonical-runtime-456', 'system'),
        );
        $this->store->append($this->record(self::RECORD_ID_3, JournalEvent::OperationReceived, 1, actors: $actors));

        $records = array_values(iterator_to_array($this->store->records(OperationId::fromString(self::OPERATION_ID))));

        self::assertCount(1, $records);
        self::assertSame('canonical-origin-123', $records[0]->operation->actorContext?->origin()?->id());
        self::assertSame('user', $records[0]->operation->actorContext?->origin()?->type());
        self::assertNull($records[0]->operation->actorContext?->authorization());
        self::assertSame('canonical-runtime-456', $records[0]->operation->actorContext?->execution()->id());
        self::assertSame('system', $records[0]->operation->actorContext?->execution()->type());
    }

    public function testAppendsAndReadsValidationViolationsWithoutRawValues(): void
    {
        $secret = 'database-secret-must-not-be-copied';
        $violations = [new Violation('password', 'not_blank', 'validation.not_blank')];
        $this->store->append($this->record(
            self::RECORD_ID_3,
            JournalEvent::OperationRejected,
            1,
            new OperationRejectedData(RejectionReason::validation('validation.failed', $violations)),
        ));

        $records = array_values(iterator_to_array($this->store->records(OperationId::fromString(self::OPERATION_ID))));
        $encoded = $this->connection->fetchOne('SELECT encoded_record::text FROM ' . self::SCHEMA . '.journal');

        self::assertCount(1, $records);
        self::assertInstanceOf(OperationRejectedData::class, $records[0]->data);
        self::assertEquals($violations, $records[0]->data->reason->violations());
        self::assertIsString($encoded);
        self::assertStringNotContainsString($secret, $encoded);
        self::assertStringNotContainsString('postgres.test', $encoded);
        self::assertStringNotContainsString('password', $encoded);
    }

    public function testUnknownEnvelopeKeyIsRejectedWithoutPlaintextFallback(): void
    {
        $this->store->append($this->record(
            self::RECORD_ID_1,
            JournalEvent::OperationReceived,
            1,
            new OperationReceivedData(new StoredJournalValue('private')),
        ));
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.journal
             SET encoded_record = substring(encoded_record FROM 1 FOR 8)
                 || convert_to(\'bad-key1\', \'UTF8\')
                 || substring(encoded_record FROM 17)',
        );

        $this->expectException(JournalReadFailed::class);
        iterator_to_array($this->store->records(OperationId::fromString(self::OPERATION_ID)));
    }

    public function testPurposeRowFieldAndTagTamperingFailClosed(): void
    {
        $record = $this->record(self::RECORD_ID_1, JournalEvent::OperationReceived, 1);
        $this->store->append($record);
        $original = PostgreSqlBytea::string($this->connection->fetchOne(
            'SELECT encoded_record FROM ' . self::SCHEMA . '.journal',
        ));
        $plaintext = new PostgreSqlJournalRecordCodec()->encode($record);
        $contexts = [
            new StorageProtectionContext(
                StoragePurpose::OutcomePayload,
                self::RECORD_ID_1,
                self::OPERATION_ID,
                'postgres.test',
                1,
            ),
            new StorageProtectionContext(
                StoragePurpose::JournalRecord,
                self::RECORD_ID_2,
                self::OPERATION_ID,
                'postgres.test',
                1,
            ),
            new StorageProtectionContext(
                StoragePurpose::JournalRecord,
                self::RECORD_ID_1,
                self::OPERATION_ID,
                'wrong.field',
                1,
            ),
        ];
        foreach ($contexts as $context) {
            $this->connection->executeStatement(
                'UPDATE ' . self::SCHEMA . '.journal SET encoded_record = :encoded',
                ['encoded' => PostgreSqlTestStorageProtection::codec()->encrypt($plaintext, $context)],
                ['encoded' => \Doctrine\DBAL\ParameterType::BINARY],
            );
            try {
                iterator_to_array($this->store->records(OperationId::fromString(self::OPERATION_ID)));
                self::fail('Expected AAD mismatch failure.');
            } catch (JournalReadFailed) {
                // Expected fail-closed behavior.
            }
            $this->connection->executeStatement(
                'UPDATE ' . self::SCHEMA . '.journal SET encoded_record = :encoded',
                ['encoded' => $original],
                ['encoded' => \Doctrine\DBAL\ParameterType::BINARY],
            );
        }
        $tampered = $original;
        $tampered[strlen($tampered) - 1] = $tampered[strlen($tampered) - 1] ^ "\x01";
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.journal SET encoded_record = :encoded',
            ['encoded' => $tampered],
            ['encoded' => \Doctrine\DBAL\ParameterType::BINARY],
        );
        $this->expectException(JournalReadFailed::class);
        iterator_to_array($this->store->records(OperationId::fromString(self::OPERATION_ID)));
    }

    public function testAppendsAndReadsRetryScheduledData(): void
    {
        $this->store->append($this->record(
            self::RECORD_ID_3,
            JournalEvent::AttemptRetryScheduled,
            1,
            new AttemptRetryScheduledData(
                AttemptId::fromString(self::ATTEMPT_ID),
                2,
                new DateTimeImmutable('2026-07-19T23:22:56.143069+09:00'),
                1_000,
            ),
        ));

        $records = array_values(iterator_to_array($this->store->records(OperationId::fromString(self::OPERATION_ID))));
        $encoded = $this->connection->fetchOne(
            "SELECT encode(encoded_record, 'escape') FROM " . self::SCHEMA . '.journal',
        );

        self::assertCount(1, $records);
        self::assertInstanceOf(AttemptRetryScheduledData::class, $records[0]->data);
        self::assertSame(self::ATTEMPT_ID, $records[0]->data->failedAttemptId->toString());
        self::assertSame(2, $records[0]->data->nextAttemptNumber);
        self::assertSame('2026-07-19T14:22:56.143069+00:00', $records[0]->data->scheduledAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(1_000, $records[0]->data->delayMilliseconds);
        self::assertIsString($encoded);
        self::assertStringStartsWith('BOPD', $encoded);
    }

    public function testAppendsAndReadsOperationFailedData(): void
    {
        $this->store->append($this->record(
            self::RECORD_ID_3,
            JournalEvent::OperationFailed,
            1,
            new OperationFailedData(\RuntimeException::class, 'boom', false),
        ));

        $records = array_values(iterator_to_array($this->store->records(OperationId::fromString(self::OPERATION_ID))));

        self::assertCount(1, $records);
        self::assertInstanceOf(OperationFailedData::class, $records[0]->data);
        self::assertSame(\RuntimeException::class, $records[0]->data->errorType);
        self::assertSame('boom', $records[0]->data->errorMessage);
        self::assertFalse($records[0]->data->retryable);
    }

    public function testAppendsAndReadsOperationDeadLetteredData(): void
    {
        $this->store->append($this->record(
            self::RECORD_ID_3,
            JournalEvent::OperationDeadLettered,
            1,
            new OperationDeadLetteredData(
                AttemptId::fromString(self::ATTEMPT_ID),
                1,
                \RuntimeException::class,
                'boom',
                new DateTimeImmutable('2026-07-19T23:22:56.654321+09:00'),
            ),
        ));

        $records = array_values(iterator_to_array($this->store->records(OperationId::fromString(self::OPERATION_ID))));
        $encoded = $this->connection->fetchOne(
            "SELECT encode(encoded_record, 'escape') FROM " . self::SCHEMA . '.journal',
        );

        self::assertCount(1, $records);
        self::assertInstanceOf(OperationDeadLetteredData::class, $records[0]->data);
        self::assertSame(self::ATTEMPT_ID, $records[0]->data->finalAttemptId?->toString());
        self::assertSame(1, $records[0]->data->finalAttemptNumber);
        self::assertSame(\RuntimeException::class, $records[0]->data->reasonType);
        self::assertSame('boom', $records[0]->data->reasonMessage);
        self::assertSame('2026-07-19T14:22:56.654321+00:00', $records[0]->data->movedAt->format('Y-m-d\TH:i:s.uP'));
        self::assertIsString($encoded);
        self::assertStringStartsWith('BOPD', $encoded);
    }

    public function testDuplicateRecordIdFails(): void
    {
        $this->store->append($this->record(self::RECORD_ID_1, JournalEvent::OperationReceived, 1));

        $this->expectException(\BlackOps\Journal\Exception\JournalWriteFailed::class);

        $this->store->append($this->record(self::RECORD_ID_1, JournalEvent::AttemptStarted, 2));
    }

    public function testDuplicateOperationSequenceFails(): void
    {
        $this->store->append($this->record(self::RECORD_ID_1, JournalEvent::OperationReceived, 1));

        $this->expectException(\BlackOps\Journal\Exception\JournalWriteFailed::class);

        $this->store->append($this->record(self::RECORD_ID_2, JournalEvent::AttemptStarted, 1));
    }

    private function connection(): Connection
    {
        $host = (string) (getenv('POSTGRES_HOST') ?: 'postgres');
        $port = (int) (getenv('POSTGRES_PORT') ?: '5432');
        $db = (string) (getenv('POSTGRES_DB') ?: 'blackops');
        $user = (string) (getenv('POSTGRES_USER') ?: 'blackops');
        $password = (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops');

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $host,
            'port' => $port,
            'dbname' => $db,
            'user' => $user,
            'password' => $password,
        ]);
    }

    private function record(
        string $recordId,
        JournalEvent $event,
        int $sequence,
        ?\BlackOps\Journal\JournalData $data = null,
        ?JournalAttempt $attempt = null,
        ?ActorContext $actors = null,
    ): JournalRecord {
        return new JournalRecord(
            JournalRecordId::fromString($recordId),
            1,
            $event,
            new DateTimeImmutable('2026-07-08T00:00:00.123456Z'),
            $sequence,
            new JournalOperation(
                OperationId::fromString(self::OPERATION_ID),
                'postgres.test',
                1,
                'inline',
                CorrelationId::fromString(self::CORRELATION_ID),
                actorContext: $actors,
            ),
            $attempt,
            $data ?? new \BlackOps\Journal\EmptyJournalData(),
        );
    }
}

final readonly class StoredJournalValue implements OperationValue
{
    public function __construct(
        public string $message,
    ) {}
}

final readonly class StoredJournalOutcome implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

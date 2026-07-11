<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Outcome;
use BlackOps\Outcome\Exception\OutcomeStoreException;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Outcome\OutcomeStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PostgreSqlOutcomeStoreTest extends TestCase
{
    private const SCHEMA = 'blackops_p6_010_outcome';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687902';

    private Connection $connection;
    private PostgreSqlOutcomeStore $store;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $sender = new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA);
        $sender->migrate();
        $sender->enqueue($this->message());
        $this->store = new PostgreSqlOutcomeStore($this->connection, self::SCHEMA);
    }

    public function testStoreImplementsPortAndSchemaHasExpectedShape(): void
    {
        self::assertInstanceOf(OutcomeStore::class, $this->store);
        $columns = $this->connection->fetchAllKeyValue("SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'outcomes'
            ORDER BY ordinal_position");

        self::assertSame(
            [
                'operation_id' => 'uuid',
                'outcome_type' => 'text',
                'schema_version' => 'integer',
                'encoded_payload' => 'bytea',
                'completed_at' => 'timestamp with time zone',
            ],
            $columns,
        );
        self::assertSame('RESTRICT', $this->connection->fetchOne("SELECT rc.delete_rule
            FROM information_schema.referential_constraints rc
            WHERE rc.constraint_schema = '"
        . self::SCHEMA
        . "'
              AND rc.constraint_name = 'outcomes_operation_id_fkey'"));
        self::assertTrue((bool) $this->connection->fetchOne("SELECT EXISTS (
            SELECT 1
            FROM pg_indexes
            WHERE schemaname = '"
        . self::SCHEMA
        . "'
              AND indexname = 'outcomes_completed_at_idx'
        )"));
    }

    public function testSavesAndFindsTypedOutcomeWithUtcCompletionTime(): void
    {
        $this->markCompleted();
        $id = OperationId::fromString(self::OPERATION_ID);
        $this->store->save(
            new OutcomeRecord(
                $id,
                new PostgreSqlStoredOutcome('ready'),
                new DateTimeImmutable('2026-07-12T09:30:00+09:00'),
            ),
        );

        $record = $this->store->find($id);

        self::assertNotNull($record);
        self::assertSame($id, $record->operationId());
        self::assertInstanceOf(PostgreSqlStoredOutcome::class, $record->outcome());
        self::assertSame('ready', $record->outcome()->message);
        self::assertSame('2026-07-12T00:30:00.000000+00:00', $record->completedAt()->format('Y-m-d\TH:i:s.uP'));
    }

    public function testUnknownOperationReturnsNull(): void
    {
        self::assertNull($this->store->find(OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687903')));
    }

    public function testDuplicateSaveDoesNotOverwriteExistingOutcome(): void
    {
        $this->markCompleted();
        $id = OperationId::fromString(self::OPERATION_ID);
        $this->store->save(new OutcomeRecord($id, new PostgreSqlStoredOutcome('first'), new DateTimeImmutable()));

        try {
            $this->store->save(new OutcomeRecord($id, new PostgreSqlStoredOutcome('second'), new DateTimeImmutable()));
            self::fail('Expected duplicate outcome save to fail.');
        } catch (OutcomeStoreException) {
            $record = $this->store->find($id);
            self::assertNotNull($record);
            self::assertSame('first', $record->outcome()->message);
        }
    }

    public function testSaveForUnknownOperationFails(): void
    {
        $this->expectException(OutcomeStoreException::class);

        $this->store->save(
            new OutcomeRecord(
                OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687903'),
                new PostgreSqlStoredOutcome('missing'),
                new DateTimeImmutable(),
            ),
        );
    }

    public function testSaveForNonCompletedOperationFailsWithoutCreatingOutcome(): void
    {
        $id = OperationId::fromString(self::OPERATION_ID);

        try {
            $this->store->save(
                new OutcomeRecord($id, new PostgreSqlStoredOutcome('too-early'), new DateTimeImmutable()),
            );
            self::fail('Expected non-completed outcome save to fail.');
        } catch (OutcomeStoreException $exception) {
            self::assertStringContainsString('completed operation', $exception->getMessage());
            self::assertNull($this->store->find($id));
        }
    }

    public function testUnknownSchemaVersionFails(): void
    {
        $this->insertRaw(PostgreSqlStoredOutcome::class, 99, $this->payload(PostgreSqlStoredOutcome::class));

        $this->expectException(OutcomeStoreException::class);
        $this->expectExceptionMessage('schema version');

        $this->store->find(OperationId::fromString(self::OPERATION_ID));
    }

    public function testCorruptPayloadFails(): void
    {
        $this->insertRaw(PostgreSqlStoredOutcome::class, 1, '{broken');

        $this->expectException(OutcomeStoreException::class);
        $this->expectExceptionMessage('payload is corrupt');

        $this->store->find(OperationId::fromString(self::OPERATION_ID));
    }

    public function testPayloadTypeMismatchFails(): void
    {
        $this->insertRaw(AnotherPostgreSqlStoredOutcome::class, 1, $this->payload(PostgreSqlStoredOutcome::class));

        $this->expectException(OutcomeStoreException::class);
        $this->expectExceptionMessage('type does not match');

        $this->store->find(OperationId::fromString(self::OPERATION_ID));
    }

    public function testRestoredNonOutcomeFails(): void
    {
        PostgreSqlNotOutcome::$constructions = 0;
        $this->insertRaw(PostgreSqlNotOutcome::class, 1, $this->payload(PostgreSqlNotOutcome::class));

        $this->expectException(OutcomeStoreException::class);
        $this->expectExceptionMessage('type is not an Outcome');

        try {
            $this->store->find(OperationId::fromString(self::OPERATION_ID));
        } finally {
            self::assertSame(0, PostgreSqlNotOutcome::$constructions);
        }
    }

    private function insertRaw(string $type, int $version, string $payload): void
    {
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.outcomes (
            operation_id, outcome_type, schema_version, encoded_payload, completed_at
        ) VALUES (
            :operation_id, :outcome_type, :schema_version, convert_to(:payload, \'UTF8\'), :completed_at
        )', [
            'operation_id' => self::OPERATION_ID,
            'outcome_type' => $type,
            'schema_version' => $version,
            'payload' => $payload,
            'completed_at' => '2026-07-12 00:30:00+00:00',
        ]);
    }

    private function markCompleted(): void
    {
        $this->connection->executeStatement('UPDATE '
        . self::SCHEMA
        . ".operations SET state = 'completed' WHERE operation_id = :operation_id", [
            'operation_id' => self::OPERATION_ID,
        ]);
    }

    private function payload(string $class): string
    {
        return json_encode([
            '__class' => $class,
            'properties' => ['message' => 'stored'],
        ], JSON_THROW_ON_ERROR);
    }

    private function message(): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString(self::OPERATION_ID),
            'outcome.store.test',
            1,
            '{}',
            '{}',
            new DateTimeImmutable('2026-07-12T00:00:00Z'),
        );
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

final readonly class PostgreSqlStoredOutcome implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

final readonly class AnotherPostgreSqlStoredOutcome implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

final class PostgreSqlNotOutcome
{
    public static int $constructions = 0;

    public function __construct(
        public readonly string $message,
    ) {
        ++self::$constructions;
    }
}

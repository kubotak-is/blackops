<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Outcome;
use BlackOps\Core\OutcomeData;
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

    public function testVersionTwoRoundTripsStructuredOutcomeListsNullableDtoAndFloat(): void
    {
        $this->markCompleted();
        $id = OperationId::fromString(self::OPERATION_ID);
        $author = new PostgreSqlOutcomeAuthor('author-1', 'Alice');
        $outcome = new PostgreSqlStructuredOutcome(
            [
                new PostgreSqlOutcomeItem($author, 'item-1'),
                new PostgreSqlOutcomeItem(null, 'item-2'),
            ],
            $author,
            1.0,
        );
        $this->store->save(new OutcomeRecord($id, $outcome, new DateTimeImmutable('2026-07-12T00:30:00Z')));

        $record = $this->store->find($id);

        self::assertNotNull($record);
        self::assertEquals($outcome, $record->outcome());
        self::assertSame(1.0, $record->outcome()->ratio);
        self::assertSame(2, $this->connection->fetchOne('SELECT schema_version FROM '
        . self::SCHEMA
        . '.outcomes WHERE operation_id = :operation_id', ['operation_id' => self::OPERATION_ID]));
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

    public function testEphemeralOutcomeCannotBeStored(): void
    {
        $this->markCompleted();
        $id = OperationId::fromString(self::OPERATION_ID);

        try {
            $this->store->save(
                new OutcomeRecord(
                    $id,
                    new PostgreSqlEphemeralOutcome('raw-secret-must-not-appear'),
                    new DateTimeImmutable(),
                ),
            );
            self::fail('Expected ephemeral outcome storage to fail.');
        } catch (OutcomeStoreException $exception) {
            self::assertStringContainsString('Ephemeral outcomes cannot be stored', $exception->getMessage());
            self::assertStringNotContainsString('raw-secret-must-not-appear', $exception->getMessage());
        }

        self::assertSame(0, $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::SCHEMA . '.outcomes'));
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

    public function testVersionOneSchemaIsRejectedWithoutFallback(): void
    {
        $this->insertRaw(PostgreSqlStoredOutcome::class, 1, $this->payload(PostgreSqlStoredOutcome::class));

        $this->expectException(OutcomeStoreException::class);
        $this->expectExceptionMessage('schema version');

        $this->store->find(OperationId::fromString(self::OPERATION_ID));
    }

    public function testCorruptPayloadFails(): void
    {
        $this->insertRaw(PostgreSqlStoredOutcome::class, 2, '{broken');

        $this->expectException(OutcomeStoreException::class);
        $this->expectExceptionMessage('payload is corrupt');

        $this->store->find(OperationId::fromString(self::OPERATION_ID));
    }

    public function testPayloadTypeMismatchFails(): void
    {
        $this->insertRaw(AnotherPostgreSqlStoredOutcome::class, 2, $this->payload(PostgreSqlStoredOutcome::class));

        $this->expectException(OutcomeStoreException::class);
        $this->expectExceptionMessage('type does not match');

        $this->store->find(OperationId::fromString(self::OPERATION_ID));
    }

    public function testStoredFieldShapeMismatchFails(): void
    {
        $this->insertRaw(PostgreSqlStoredOutcome::class, 2, json_encode([
            '__class' => PostgreSqlStoredOutcome::class,
            'properties' => ['message' => 'stored', 'unknown' => 'not-allowed'],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(OutcomeStoreException::class);
        $this->expectExceptionMessage('payload is corrupt');

        $this->store->find(OperationId::fromString(self::OPERATION_ID));
    }

    public function testUnknownOutcomeClassFails(): void
    {
        $class = 'Unknown\\StoredOutcome';
        $this->insertRaw($class, 2, $this->payload($class));

        $this->expectException(OutcomeStoreException::class);
        $this->expectExceptionMessage('type is not an Outcome');

        $this->store->find(OperationId::fromString(self::OPERATION_ID));
    }

    public function testRestoredNonOutcomeFails(): void
    {
        PostgreSqlNotOutcome::$constructions = 0;
        $this->insertRaw(PostgreSqlNotOutcome::class, 2, $this->payload(PostgreSqlNotOutcome::class));

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

final readonly class PostgreSqlEphemeralOutcome implements EphemeralOutcome
{
    public function __construct(
        public string $token,
    ) {}
}

final readonly class AnotherPostgreSqlStoredOutcome implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

final readonly class PostgreSqlOutcomeAuthor implements OutcomeData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}

final readonly class PostgreSqlOutcomeItem implements OutcomeData
{
    public function __construct(
        public ?PostgreSqlOutcomeAuthor $author,
        public string $id,
    ) {}
}

final readonly class PostgreSqlStructuredOutcome implements Outcome
{
    /** @param list<PostgreSqlOutcomeItem> $items */
    public function __construct(
        #[ListOf(PostgreSqlOutcomeItem::class)]
        public array $items,
        public PostgreSqlOutcomeAuthor $author,
        public float $ratio,
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

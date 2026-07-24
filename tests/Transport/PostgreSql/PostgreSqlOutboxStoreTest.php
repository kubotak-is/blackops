<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PostgreSqlOutboxStoreTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'dbname' => 'blackops',
            'user' => 'blackops',
            'password' => 'blackops',
            'host' => 'postgres',
            'driver' => 'pdo_pgsql',
        ]);
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS outbox_test CASCADE');
        new PostgreSqlOutboxStore($this->connection, 'outbox_test')->migrate();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS outbox_test CASCADE');
        $this->connection->close();
    }

    public function testInsertPersistsPendingRecordAndSensitiveColumnsAreNotAdded(): void
    {
        $recorded = new DateTimeImmutable('2026-07-24T01:02:03.123456+00:00');
        $record = new PostgreSqlOutboxRecord(
            OutboxRecordId::fromString('019f45b2-7c2d-7abc-8def-0123456789ab'),
            OperationId::fromString('019f45b2-7c2d-7abc-8def-0123456789ac'),
            'mail.send',
            1,
            '{"address":"opaque"}',
            '{"operationId":"opaque"}',
            $recorded,
            $recorded,
            'app',
        );

        new PostgreSqlOutboxStore($this->connection, 'outbox_test')->insert($record);

        $row = $this->connection->fetchAssociative('SELECT * FROM "outbox_test"."outbox_records"');
        self::assertSame('pending', $row['state']);
        self::assertSame('1', (string) $row['state_version']);
        self::assertSame('app', $row['connection_name']);
        self::assertArrayNotHasKey('credential', $row);
        self::assertArrayNotHasKey('sql', $row);
    }

    public function testSchemaFixesClaimBeforeStateAndVersion(): void
    {
        $statements = implode("\n", new PostgreSqlOutboxSchema('outbox_test')->statements());

        self::assertStringContainsString(
            "state text NOT NULL DEFAULT 'pending' CHECK (state = 'pending')",
            $statements,
        );
        self::assertStringContainsString(
            'state_version bigint NOT NULL DEFAULT 1 CHECK (state_version = 1)',
            $statements,
        );
        self::assertStringContainsString('operation_id uuid NOT NULL UNIQUE', $statements);
        self::assertStringNotContainsString('lease_owner', $statements);
        self::assertStringNotContainsString('retry_count', $statements);
    }

    public function testDuplicateOperationIsRejectedByDatabase(): void
    {
        $recorded = new DateTimeImmutable('2026-07-24T01:02:03.123456+00:00');
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            $recorded,
        ));

        $this->expectException(\Throwable::class);
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ad',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            $recorded,
        ));
    }

    public function testDatabaseRejectsSentStateUpdate(): void
    {
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03.123456+00:00'),
        ));

        $rejected = false;
        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('UPDATE "outbox_test"."outbox_records" SET state = \'sent\'');
        } catch (\Throwable) {
            $rejected = true;
        } finally {
            $this->connection->rollBack();
        }

        self::assertTrue($rejected);
        self::assertSame('pending', $this->connection->fetchOne('SELECT state FROM "outbox_test"."outbox_records"'));
    }

    public function testDatabaseRejectsStateVersionTwoUpdate(): void
    {
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03.123456+00:00'),
        ));

        $rejected = false;
        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('UPDATE "outbox_test"."outbox_records" SET state_version = 2');
        } catch (\Throwable) {
            $rejected = true;
        } finally {
            $this->connection->rollBack();
        }

        self::assertTrue($rejected);
        self::assertSame(
            '1',
            (string) $this->connection->fetchOne('SELECT state_version FROM "outbox_test"."outbox_records"'),
        );
    }

    #[DataProvider('invalidRecordFields')]
    public function testRecordRejectsEachInvalidFieldBeforeDatabaseCall(
        string $operationType,
        int $schemaVersion,
        string $connectionName,
    ): void {
        $recorded = new DateTimeImmutable('2026-07-24T01:02:03+00:00');
        $id = OutboxRecordId::fromString('019f45b2-7c2d-7abc-8def-0123456789ab');
        $operation = OperationId::fromString('019f45b2-7c2d-7abc-8def-0123456789ac');

        $this->expectException(\InvalidArgumentException::class);
        new PostgreSqlOutboxRecord(
            $id,
            $operation,
            $operationType,
            $schemaVersion,
            '{}',
            '{}',
            $recorded,
            $recorded,
            $connectionName,
        );
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function invalidRecordFields(): iterable
    {
        yield 'empty operation type' => ['', 1, 'app'];
        yield 'empty connection name' => ['mail.send', 1, ''];
        yield 'zero schema version' => ['mail.send', 0, 'app'];
    }

    public function testSchemaHelperAndVersionedMigrationShareOutboxContract(): void
    {
        $helper = array_map(
            static fn(string $statement): string => self::normalizeSql($statement),
            array_slice(new PostgreSqlOutboxSchema('outbox_test')->statements(), 1),
        );
        require_once __DIR__ . '/../../../migrations/postgresql/Version20260724010000.php';
        $migration = new \BlackOps\Migrations\PostgreSql\Version20260724010000(
            $this->connection,
            new NullLogger(),
            'outbox_test',
        );
        $migration->up(new Schema());
        $migrationSql = array_map(static fn(object $query): string => self::normalizeSql(
            $query->getStatement(),
        ), $migration->getSql());

        self::assertSame($helper, $migrationSql);
    }

    /** @return PostgreSqlOutboxRecord */
    private function record(string $recordId, string $operationId, DateTimeImmutable $recorded): PostgreSqlOutboxRecord
    {
        return new PostgreSqlOutboxRecord(
            OutboxRecordId::fromString($recordId),
            OperationId::fromString($operationId),
            'mail.send',
            1,
            '{"address":"opaque"}',
            '{"operationId":"opaque"}',
            $recorded,
            $recorded,
            'app',
        );
    }

    private static function normalizeSql(string $sql): string
    {
        return (string) preg_replace('/\\s+/', ' ', trim($sql));
    }
}

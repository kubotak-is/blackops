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
            "state text NOT NULL DEFAULT 'pending' CHECK (state IN ('pending','leased','retry_scheduled','sent','dead_lettered'))",
            $statements,
        );
        self::assertStringContainsString(
            'state_version bigint NOT NULL DEFAULT 1 CHECK (state_version >= 1)',
            $statements,
        );
        self::assertStringContainsString('operation_id uuid NOT NULL UNIQUE', $statements);
        self::assertStringContainsString('fencing_token', $statements);
        self::assertStringContainsString('attempt_count', $statements);
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

    public function testDatabaseAcceptsSentStateUpdate(): void
    {
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03.123456+00:00'),
        ));

        self::assertSame(
            1,
            $this->connection->executeStatement('UPDATE "outbox_test"."outbox_records" SET state = \'sent\''),
        );
        self::assertSame('sent', $this->connection->fetchOne('SELECT state FROM "outbox_test"."outbox_records"'));
    }

    public function testDatabaseAcceptsStateVersionTwoUpdate(): void
    {
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03.123456+00:00'),
        ));

        self::assertSame(
            1,
            $this->connection->executeStatement('UPDATE "outbox_test"."outbox_records" SET state_version = 2'),
        );
        self::assertSame(
            '2',
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
        require_once __DIR__ . '/../../../migrations/postgresql/Version20260724100000.php';
        $migration = new \BlackOps\Migrations\PostgreSql\Version20260724100000(
            $this->connection,
            new NullLogger(),
            'outbox_test',
        );
        $migration->up(new Schema());
        $migrationSql = array_map(static fn(object $query): string => self::normalizeSql(
            $query->getStatement(),
        ), $migration->getSql());

        self::assertNotEmpty($helper);
        self::assertNotEmpty($migrationSql);
        self::assertContains(
            'ALTER TABLE "outbox_test"."outbox_records" DROP CONSTRAINT IF EXISTS outbox_records_state_version_check',
            $migrationSql,
        );
        self::assertContains(
            'ALTER TABLE "outbox_test"."outbox_records" ADD CONSTRAINT outbox_records_state_version_check CHECK (state_version >= 1)',
            $migrationSql,
        );
    }

    public function testRelayMigrationUpgradesAndDownPreservesTheOriginalOutboxRecord(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS outbox_test CASCADE');
        $this->connection->executeStatement('CREATE SCHEMA outbox_test');
        require_once __DIR__ . '/../../../migrations/postgresql/Version20260724010000.php';
        $old = new \BlackOps\Migrations\PostgreSql\Version20260724010000(
            $this->connection,
            new NullLogger(),
            'outbox_test',
        );
        $this->applyMigration($old);
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03+00:00'),
        ));

        require_once __DIR__ . '/../../../migrations/postgresql/Version20260724100000.php';
        $relay = new \BlackOps\Migrations\PostgreSql\Version20260724100000(
            $this->connection,
            new NullLogger(),
            'outbox_test',
        );
        $this->applyMigration($relay);
        $claim = $store->claimBatch('relay-a', 1, new DateTimeImmutable('2026-07-24T01:02:04+00:00'), 60)[0];
        $store->markSent($claim);
        $upSqlCount = count($relay->getSql());
        $relay->down(new Schema());
        foreach (array_slice($relay->getSql(), $upSqlCount) as $query) {
            $this->connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }

        self::assertSame('pending', $this->connection->fetchOne('SELECT state FROM "outbox_test"."outbox_records"'));
        self::assertSame(
            '1',
            (string) $this->connection->fetchOne('SELECT state_version FROM "outbox_test"."outbox_records"'),
        );
        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('UPDATE "outbox_test"."outbox_records" SET state=\'sent\'');
            self::fail('The original pending-state constraint was not restored.');
        } catch (\Throwable) {
            $this->connection->rollBack();
        }
    }

    public function testTwoConnectionsClaimOnceAndExpiredLeaseReclaimsWithMonotonicFence(): void
    {
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03.123456+00:00'),
        ));
        $otherConnection = $this->connection();
        $other = new PostgreSqlOutboxStore($otherConnection, 'outbox_test');
        $now = new DateTimeImmutable('2026-07-24T01:02:04+00:00');
        $first = $store->claimBatch('relay-a', 1, $now, 60);
        self::assertCount(1, $first);
        self::assertCount(0, $other->claimBatch('relay-b', 1, $now, 60));
        $reclaimed = $other->claimBatch('relay-b', 1, $now->modify('+61 seconds'), 60);
        self::assertCount(1, $reclaimed);
        self::assertSame(2, $reclaimed[0]->fencingToken);
        self::assertSame(
            $first[0]->message->operationId()->toString(),
            $reclaimed[0]->message->operationId()->toString(),
        );
    }

    public function testOverlappingClaimTransactionsSkipLockedRowsWithoutDuplicateOwnership(): void
    {
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03+00:00'),
        ));
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ad',
            '019f45b2-7c2d-7abc-8def-0123456789ae',
            new DateTimeImmutable('2026-07-24T01:02:03+00:00'),
        ));
        $this->connection->beginTransaction();
        $this->connection->executeQuery(
            'SELECT record_id FROM "outbox_test"."outbox_records" ORDER BY record_id LIMIT 1 FOR UPDATE',
        );
        $otherConnection = $this->connection();
        $other = new PostgreSqlOutboxStore($otherConnection, 'outbox_test');
        $now = new DateTimeImmutable('2026-07-24T01:02:04+00:00');
        $otherClaims = $other->claimBatch('relay-b', 2, $now, 60);
        self::assertCount(1, $otherClaims);
        $this->connection->rollBack();
        $firstClaims = $store->claimBatch('relay-a', 2, $now, 60);
        self::assertCount(1, $firstClaims);
        self::assertNotSame($otherClaims[0]->recordId->toString(), $firstClaims[0]->recordId->toString());
        self::assertSame(
            2,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM "outbox_test"."outbox_records" WHERE state=\'leased\'',
            ),
        );
        $otherConnection->close();
    }

    public function testExpiredHeartbeatIsRejectedWithoutMutatingLease(): void
    {
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03+00:00'),
        ));
        $claim = $store->claimBatch('relay-a', 1, new DateTimeImmutable('2026-07-24T01:02:04+00:00'), 60)[0];
        $expired = new DateTimeImmutable('2026-07-24T01:03:05+00:00');
        $before = $this->connection->fetchOne('SELECT lease_expires_at FROM "outbox_test"."outbox_records"');

        $this->expectException(\Throwable::class);
        try {
            $store->heartbeat($claim, $expired, 60);
        } finally {
            self::assertSame(
                $before,
                $this->connection->fetchOne('SELECT lease_expires_at FROM "outbox_test"."outbox_records"'),
            );
        }
    }

    public function testEachStaleSettlementLeavesCurrentOwnerUntouched(): void
    {
        foreach (['sent', 'retry', 'dead'] as $settlement) {
            $this->connection->executeStatement('DROP SCHEMA IF EXISTS outbox_test CASCADE');
            new PostgreSqlOutboxStore($this->connection, 'outbox_test')->migrate();
            $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
            $store->insert($this->record(
                '019f45b2-7c2d-7abc-8def-0123456789ab',
                '019f45b2-7c2d-7abc-8def-0123456789ac',
                new DateTimeImmutable('2026-07-24T01:02:03+00:00'),
            ));
            $first = $store->claimBatch('relay-a', 1, new DateTimeImmutable('2026-07-24T01:02:04+00:00'), 1)[0];
            $second = $store->claimBatch('relay-b', 1, new DateTimeImmutable('2026-07-24T01:02:06+00:00'), 60)[0];
            try {
                match ($settlement) {
                    'sent' => $store->markSent($first),
                    'retry' => $store->scheduleRetry(
                        $first,
                        new DateTimeImmutable('2026-07-24T01:03:00+00:00'),
                        'v1:' . str_repeat('a', 64),
                    ),
                    default => $store->moveToDeadLetter($first, 'v1:' . str_repeat('a', 64)),
                };
                self::fail('Stale settlement unexpectedly succeeded.');
            } catch (\Throwable) {
                self::assertSame(
                    'leased',
                    $this->connection->fetchOne('SELECT state FROM "outbox_test"."outbox_records"'),
                );
                self::assertSame(
                    'relay-b',
                    $this->connection->fetchOne('SELECT relay_id FROM "outbox_test"."outbox_records"'),
                );
                self::assertSame(
                    $second->fencingToken,
                    (int) $this->connection->fetchOne('SELECT fencing_token FROM "outbox_test"."outbox_records"'),
                );
            }
        }
    }

    public function testRawFailureFingerprintIsRejectedWithoutStateMutation(): void
    {
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03+00:00'),
        ));
        $claim = $store->claimBatch('relay-a', 1, new DateTimeImmutable('2026-07-24T01:02:04+00:00'), 60)[0];
        $this->expectException(\Throwable::class);
        try {
            $store->moveToDeadLetter($claim, 'secret exception message');
        } finally {
            self::assertSame('leased', $this->connection->fetchOne('SELECT state FROM "outbox_test"."outbox_records"'));
            self::assertNull($this->connection->fetchOne(
                'SELECT failure_fingerprint FROM "outbox_test"."outbox_records"',
            ));
        }
    }

    public function testClaimRejectsTransportEnvelopeIntegrityMismatchWithoutReturningPayload(): void
    {
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03+00:00'),
        ));
        $this->connection->executeStatement('UPDATE "outbox_test"."outbox_records" SET content_type = \'text/plain\'');

        $this->expectException(\Throwable::class);
        $store->claimBatch('relay-a', 1, new DateTimeImmutable('2026-07-24T01:02:04+00:00'), 60);
    }

    public function testDeadLetterRetryWritesAuditAndRejectsNonDeadLetter(): void
    {
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_test');
        $store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
            new DateTimeImmutable('2026-07-24T01:02:03.123456+00:00'),
        ));
        $now = new DateTimeImmutable('2026-07-24T01:02:04+00:00');
        $claim = $store->claimBatch('relay-a', 1, $now, 60)[0];
        $store->moveToDeadLetter($claim, 'v1:' . hash('sha256', 'failure'));
        $store->retryDeadLetter($claim->recordId, 'operator', 'manual retry', $now);
        self::assertSame(
            'retry_scheduled',
            $this->connection->fetchOne('SELECT state FROM "outbox_test"."outbox_records"'),
        );
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT count(*) FROM "outbox_test"."outbox_dead_letter_retry_audits"'),
        );
        $row = $this->connection->fetchAssociative(
            'SELECT record_id::text, operation_id::text, attempt_count, dead_lettered_at FROM "outbox_test"."outbox_records"',
        );
        self::assertSame($claim->recordId->toString(), $row['record_id']);
        self::assertSame($claim->message->operationId()->toString(), $row['operation_id']);
        self::assertSame($claim->attemptCount, (int) $row['attempt_count']);
        self::assertNull($row['dead_lettered_at']);
        $audit = $this->connection->fetchAssociative(
            'SELECT record_id::text, operation_id::text, actor, reason, retried_at, previous_attempt_count FROM "outbox_test"."outbox_dead_letter_retry_audits"',
        );
        self::assertSame($claim->recordId->toString(), $audit['record_id']);
        self::assertSame($claim->message->operationId()->toString(), $audit['operation_id']);
        self::assertSame('operator', $audit['actor']);
        self::assertSame('manual retry', $audit['reason']);
        self::assertNotEmpty($audit['retried_at']);
        self::assertSame($claim->attemptCount, (int) $audit['previous_attempt_count']);
        try {
            $store->retryDeadLetter($claim->recordId, 'operator', 'not dead', $now);
            self::fail('Non-dead retry was accepted.');
        } catch (\Throwable) {
            self::assertSame(
                'retry_scheduled',
                $this->connection->fetchOne('SELECT state FROM "outbox_test"."outbox_records"'),
            );
            self::assertSame(
                $claim->attemptCount,
                (int) $this->connection->fetchOne('SELECT attempt_count FROM "outbox_test"."outbox_records"'),
            );
            self::assertSame(
                1,
                (int) $this->connection->fetchOne(
                    'SELECT count(*) FROM "outbox_test"."outbox_dead_letter_retry_audits"',
                ),
            );
        }
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

    private function applyMigration(object $migration): void
    {
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $this->connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
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

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;

final class PostgreSqlDeferredOperationSenderTest extends TestCase
{
    private const SCHEMA = 'blackops_p3_002';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.123456Z'),
        );
        $this->sender->migrate();
    }

    public function testMigrationCreatesOperationsTableWithExpectedShape(): void
    {
        $columns = $this->connection->fetchAllKeyValue("SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'operations'
            ORDER BY ordinal_position");

        self::assertSame('uuid', $columns['operation_id']);
        self::assertSame('text', $columns['operation_type']);
        self::assertSame('integer', $columns['schema_version']);
        self::assertSame('bytea', $columns['encoded_payload']);
        self::assertSame('bytea', $columns['encoded_context']);
        self::assertSame('timestamp with time zone', $columns['payload_purged_at']);
        self::assertSame('text', $columns['content_type']);
        self::assertSame('text', $columns['encoding']);
        self::assertSame('text', $columns['key_id']);
        self::assertSame('text', $columns['state']);
        self::assertSame('bigint', $columns['state_version']);
        self::assertSame('integer', $columns['attempt_number']);
        self::assertSame('bigint', $columns['next_sequence']);
        self::assertSame('timestamp with time zone', $columns['available_at']);
        self::assertSame('timestamp with time zone', $columns['accepted_at']);
        self::assertSame('uuid', $columns['current_attempt_id']);
        self::assertSame('timestamp with time zone', $columns['current_attempt_started_at']);
    }

    public function testMigrationMakesPayloadColumnsNullableForTerminalTombstones(): void
    {
        $columns = $this->connection->fetchAllAssociativeIndexed(
            "SELECT column_name, is_nullable
            FROM information_schema.columns
            WHERE table_schema = '"
            . self::SCHEMA
            . "'
              AND table_name = 'operations'
              AND column_name IN ('encoded_payload', 'encoded_context', 'payload_purged_at')",
        );

        self::assertSame('YES', $columns['encoded_payload']['is_nullable']);
        self::assertSame('YES', $columns['encoded_context']['is_nullable']);
        self::assertSame('YES', $columns['payload_purged_at']['is_nullable']);
    }

    public function testMigrationCreatesDeadLettersTableWithExpectedShape(): void
    {
        $columns = $this->connection->fetchAllKeyValue("SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'dead_letters'
            ORDER BY ordinal_position");

        $primaryKeyCount = $this->connection->fetchOne("SELECT count(*)
            FROM information_schema.table_constraints
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'dead_letters'
              AND constraint_type = 'PRIMARY KEY'");

        self::assertSame('uuid', $columns['operation_id']);
        self::assertSame('uuid', $columns['final_attempt_id']);
        self::assertSame('integer', $columns['final_attempt_number']);
        self::assertSame('text', $columns['reason_type']);
        self::assertSame('text', $columns['reason_message']);
        self::assertSame('timestamp with time zone', $columns['moved_at']);
        self::assertSame('timestamp with time zone', $columns['created_at']);
        self::assertSame(1, (int) $primaryKeyCount);
    }

    public function testMigrationCreatesRetentionHoldsTableWithRestrictForeignKey(): void
    {
        $columns = $this->connection->fetchAllKeyValue("SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'retention_holds'
            ORDER BY ordinal_position");
        $deleteRule = $this->connection->fetchOne(
            "SELECT rc.delete_rule
            FROM information_schema.referential_constraints rc
            JOIN information_schema.table_constraints tc
                ON tc.constraint_catalog = rc.constraint_catalog
                AND tc.constraint_schema = rc.constraint_schema
                AND tc.constraint_name = rc.constraint_name
            WHERE tc.table_schema = '"
            . self::SCHEMA
            . "'
              AND tc.table_name = 'retention_holds'
              AND tc.constraint_name = 'retention_holds_operation_id_fkey'",
        );

        self::assertSame('uuid', $columns['hold_id']);
        self::assertSame('uuid', $columns['operation_id']);
        self::assertSame('text', $columns['category']);
        self::assertSame('text', $columns['reason']);
        self::assertSame('timestamp with time zone', $columns['placed_at']);
        self::assertSame('text', $columns['placed_by']);
        self::assertSame('timestamp with time zone', $columns['released_at']);
        self::assertSame('text', $columns['released_by']);
        self::assertSame('timestamp with time zone', $columns['created_at']);
        self::assertSame('RESTRICT', $deleteRule);
    }

    public function testTerminalOperationCanBeTombstoned(): void
    {
        $this->sender->enqueue($this->message());
        $updated = $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . ".operations
            SET state = 'completed',
                encoded_payload = NULL,
                encoded_context = NULL,
                payload_purged_at = :purged_at
            WHERE operation_id = :operation_id",
            [
                'operation_id' => self::OPERATION_ID,
                'purged_at' => '2026-07-12 00:00:00+00:00',
            ],
        );

        $row = $this->connection->fetchAssociative('SELECT
                encoded_payload,
                encoded_context,
                to_char(payload_purged_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS payload_purged_at
            FROM ' . self::SCHEMA . '.operations');

        self::assertSame(1, $updated);
        self::assertIsArray($row);
        self::assertNull($row['encoded_payload']);
        self::assertNull($row['encoded_context']);
        self::assertSame('2026-07-12T00:00:00.000000Z', $row['payload_purged_at']);
    }

    public function testNonTerminalOperationCannotBeTombstoned(): void
    {
        $this->sender->enqueue($this->message());

        $this->expectException(Exception::class);

        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET encoded_payload = NULL,
                encoded_context = NULL,
                payload_purged_at = :purged_at
            WHERE operation_id = :operation_id',
            [
                'operation_id' => self::OPERATION_ID,
                'purged_at' => '2026-07-12 00:00:00+00:00',
            ],
        );
    }

    public function testRetentionHoldRestrictsOperationDelete(): void
    {
        $this->sender->enqueue($this->message());
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.retention_holds (
                hold_id,
                operation_id,
                category,
                reason,
                placed_at,
                placed_by
            ) VALUES (
                :hold_id,
                :operation_id,
                :category,
                :reason,
                :placed_at,
                :placed_by
            )', [
            'hold_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9688811',
            'operation_id' => self::OPERATION_ID,
            'category' => 'legal',
            'reason' => 'legal request',
            'placed_at' => '2026-07-10 00:00:00+00:00',
            'placed_by' => 'legal-team',
        ]);

        $this->expectException(Exception::class);

        $this->connection->executeStatement('DELETE FROM '
        . self::SCHEMA
        . '.operations WHERE operation_id = :operation_id', ['operation_id' => self::OPERATION_ID]);
    }

    public function testEnqueueStoresMessageAndReturnsAcknowledgement(): void
    {
        $message = $this->message();

        $acknowledgement = $this->sender->enqueue($message);

        $row = $this->connection->fetchAssociative("SELECT
                operation_id::text,
                operation_type,
                schema_version,
                convert_from(encoded_payload, 'UTF8') AS encoded_payload,
                convert_from(encoded_context, 'UTF8') AS encoded_context,
                content_type,
                encoding,
                key_id,
                state,
                state_version,
                next_sequence,
                to_char(available_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS available_at,
                to_char(accepted_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS accepted_at
            FROM " . self::SCHEMA . '.operations');

        self::assertIsArray($row);
        self::assertSame($message->operationId(), $acknowledgement->operationId());
        self::assertSame('2026-07-10T00:00:01.123456+00:00', $acknowledgement->acceptedAt()->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(self::OPERATION_ID, $row['operation_id']);
        self::assertSame('report.generate', $row['operation_type']);
        self::assertSame(1, $row['schema_version']);
        self::assertSame('{"reportId":"r1"}', $row['encoded_payload']);
        self::assertSame('{"correlationId":"c1"}', $row['encoded_context']);
        self::assertSame('application/vnd.blackops.deferred-operation+json', $row['content_type']);
        self::assertSame('utf8', $row['encoding']);
        self::assertNull($row['key_id']);
        self::assertSame('accepted', $row['state']);
        self::assertSame(1, (int) $row['state_version']);
        self::assertSame(1, (int) $row['next_sequence']);
        self::assertSame('2026-07-10T00:00:00.000000Z', $row['available_at']);
        self::assertSame('2026-07-10T00:00:01.123456Z', $row['accepted_at']);
    }

    public function testDuplicateOperationIdFailsWithDeferredTransportException(): void
    {
        $this->sender->enqueue($this->message());

        $this->expectException(DeferredTransportException::class);

        $this->sender->enqueue($this->message());
    }

    private function message(): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            1,
            '{"reportId":"r1"}',
            '{"correlationId":"c1"}',
            new DateTimeImmutable('2026-07-10T00:00:00.000000Z'),
        );
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
}

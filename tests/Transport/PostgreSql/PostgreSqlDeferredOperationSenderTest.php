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

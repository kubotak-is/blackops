<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class PostgreSqlDeferredOperationSenderTest extends TestCase
{
    private const SCHEMA = 'blackops_p3_002';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    private PDO $pdo;
    private PostgreSqlDeferredOperationSender $sender;

    protected function setUp(): void
    {
        $this->pdo = $this->pdo();
        $this->pdo->exec('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->pdo,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.123456Z'),
        );
        $this->sender->migrate();
    }

    public function testMigrationCreatesOperationsTableWithExpectedShape(): void
    {
        $columns = $this->pdo->query("SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = '"
        . self::SCHEMA
        . "'
              AND table_name = 'operations'
            ORDER BY ordinal_position")->fetchAll(PDO::FETCH_KEY_PAIR);

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
        self::assertSame('bigint', $columns['next_sequence']);
        self::assertSame('timestamp with time zone', $columns['available_at']);
        self::assertSame('timestamp with time zone', $columns['accepted_at']);
    }

    public function testEnqueueStoresMessageAndReturnsAcknowledgement(): void
    {
        $message = $this->message();

        $acknowledgement = $this->sender->enqueue($message);

        $row = $this->pdo->query("SELECT
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
            FROM " . self::SCHEMA . '.operations')->fetch(PDO::FETCH_ASSOC);

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

    private function pdo(): PDO
    {
        $host = (string) (getenv('POSTGRES_HOST') ?: 'postgres');
        $port = (string) (getenv('POSTGRES_PORT') ?: '5432');
        $db = (string) (getenv('POSTGRES_DB') ?: 'blackops');
        $user = (string) (getenv('POSTGRES_USER') ?: 'blackops');
        $password = (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops');

        return new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }
}

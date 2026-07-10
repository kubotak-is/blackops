<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPlanItem;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionTarget;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditStore;
use BlackOps\Transport\PostgreSql\PostgreSqlTransportPayloadTombstoneService;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class PostgreSqlTransportPayloadTombstoneServiceTest extends TestCase
{
    private const SCHEMA = 'blackops_p5_008';
    private const ELIGIBLE_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688c01';
    private const HELD_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688c02';
    private const NON_TERMINAL_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688c03';
    private const DEAD_LETTER_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688c04';
    private const AUDIT_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688c05';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlTransportPayloadTombstoneService $service;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.000000Z'),
        );
        $this->sender->migrate();
        $this->service = new PostgreSqlTransportPayloadTombstoneService(
            $this->connection,
            new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA),
            self::SCHEMA,
            new FixedTombstoneClock('2026-07-12T00:00:00.000000Z'),
            new FixedRetentionPurgeAuditIdGenerator([self::AUDIT_ID]),
        );
    }

    public function testTombstoneClearsPayloadAndContextAndRecordsAudit(): void
    {
        $this->seedRows();

        $purged = $this->service->tombstone(
            $this->plan(),
            RetentionPolicyRef::fromString('production-retention-v1'),
            RetentionActorRef::fromString('system:retention'),
        );

        self::assertSame(1, $purged);

        $eligible = $this->operationRow(self::ELIGIBLE_OPERATION);
        $held = $this->operationRow(self::HELD_OPERATION);
        $nonTerminal = $this->operationRow(self::NON_TERMINAL_OPERATION);
        $deadLetter = $this->operationRow(self::DEAD_LETTER_OPERATION);
        $audit = $this->auditRow(self::AUDIT_ID);

        self::assertNull($eligible['encoded_payload']);
        self::assertNull($eligible['encoded_context']);
        self::assertSame('2026-07-12T00:00:00.000000Z', $eligible['payload_purged_at']);
        self::assertSame('{"operationId":"' . self::HELD_OPERATION . '"}', $held['encoded_payload']);
        self::assertSame('{"operationId":"' . self::NON_TERMINAL_OPERATION . '"}', $nonTerminal['encoded_payload']);
        self::assertSame('{"operationId":"' . self::DEAD_LETTER_OPERATION . '"}', $deadLetter['encoded_payload']);
        self::assertSame(self::ELIGIBLE_OPERATION, $audit['operation_id']);
        self::assertSame('transport_payload', $audit['target']);
        self::assertSame(1, $audit['affected_count']);
        self::assertSame('production-retention-v1', $audit['policy']);
        self::assertSame('system:retention', $audit['purged_by']);
        self::assertArrayNotHasKey('encoded_payload', $audit);
    }

    private function seedRows(): void
    {
        $this->operation(self::ELIGIBLE_OPERATION, 'completed');
        $this->operation(self::HELD_OPERATION, 'completed');
        $this->operation(self::NON_TERMINAL_OPERATION, 'accepted');
        $this->operation(self::DEAD_LETTER_OPERATION, 'dead_lettered');
        $this->hold(self::HELD_OPERATION);
    }

    private function operation(string $operationId, string $state): void
    {
        $this->sender->enqueue($this->message($operationId));
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET state = :state,
                updated_at = :updated_at
            WHERE operation_id = :operation_id',
            [
                'operation_id' => $operationId,
                'state' => $state,
                'updated_at' => '2026-07-08 00:00:00+00:00',
            ],
        );
    }

    private function hold(string $operationId): void
    {
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
            'hold_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9688c06',
            'operation_id' => $operationId,
            'category' => 'legal',
            'reason' => 'legal request',
            'placed_at' => '2026-07-08 00:00:00+00:00',
            'placed_by' => 'legal-team',
        ]);
    }

    private function plan(): RetentionPlan
    {
        return new RetentionPlan([
            $this->item(self::ELIGIBLE_OPERATION, RetentionTarget::TransportPayload),
            $this->item(self::HELD_OPERATION, RetentionTarget::TransportPayload),
            $this->item(self::NON_TERMINAL_OPERATION, RetentionTarget::TransportPayload),
            $this->item(self::DEAD_LETTER_OPERATION, RetentionTarget::DeadLetter),
        ]);
    }

    private function item(string $operationId, RetentionTarget $target): RetentionPlanItem
    {
        return new RetentionPlanItem(
            OperationId::fromString($operationId),
            $target,
            new DateTimeImmutable('2026-07-08T00:00:00Z'),
            new DateTimeImmutable('2026-07-09T00:00:00Z'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function operationRow(string $operationId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT
                convert_from(encoded_payload, \'UTF8\') AS encoded_payload,
                convert_from(encoded_context, \'UTF8\') AS encoded_context,
                to_char(payload_purged_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS payload_purged_at
            FROM ' . self::SCHEMA . '.operations
            WHERE operation_id = :operation_id',
            ['operation_id' => $operationId],
        );

        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditRow(string $auditId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT
                operation_id::text AS operation_id,
                target,
                affected_count,
                policy,
                purged_by
            FROM ' . self::SCHEMA . '.retention_purge_audits
            WHERE audit_id = :audit_id',
            ['audit_id' => $auditId],
        );

        self::assertIsArray($row);

        return $row;
    }

    private function message(string $operationId): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString($operationId),
            'report.generate',
            1,
            '{"operationId":"' . $operationId . '"}',
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

final readonly class FixedTombstoneClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}

final class FixedRetentionPurgeAuditIdGenerator implements PostgreSqlRetentionPurgeAuditIdGenerator
{
    private int $index = 0;

    /**
     * @param list<string> $values
     */
    public function __construct(
        private array $values,
    ) {}

    public function generate(DateTimeImmutable $time): RetentionPurgeAuditId
    {
        return RetentionPurgeAuditId::fromString($this->values[$this->index++]);
    }
}

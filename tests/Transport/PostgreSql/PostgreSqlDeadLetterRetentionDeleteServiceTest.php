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
use BlackOps\Transport\PostgreSql\PostgreSqlDeadLetterRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class PostgreSqlDeadLetterRetentionDeleteServiceTest extends TestCase
{
    private const SCHEMA = 'blackops_p5_009';
    private const ELIGIBLE_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688d01';
    private const HELD_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688d02';
    private const PAYLOAD_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688d03';
    private const AUDIT_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688d04';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlDeadLetterRetentionDeleteService $service;

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
        $this->service = new PostgreSqlDeadLetterRetentionDeleteService(
            $this->connection,
            new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA),
            self::SCHEMA,
            new FixedDeadLetterDeleteClock('2026-07-12T00:00:00.000000Z'),
            new FixedDeadLetterAuditIdGenerator([self::AUDIT_ID]),
        );
    }

    public function testDeleteRemovesDeadLetterOnlyAndRecordsAudit(): void
    {
        $this->seedRows();

        $deleted = $this->service->delete(
            $this->plan(),
            RetentionPolicyRef::fromString('production-retention-v1'),
            RetentionActorRef::fromString('system:retention'),
        );

        self::assertSame(1, $deleted);
        self::assertFalse($this->deadLetterExists(self::ELIGIBLE_OPERATION));
        self::assertTrue($this->deadLetterExists(self::HELD_OPERATION));
        self::assertTrue($this->operationExists(self::ELIGIBLE_OPERATION));
        self::assertTrue($this->operationExists(self::HELD_OPERATION));
        self::assertTrue($this->operationExists(self::PAYLOAD_OPERATION));

        $audit = $this->auditRow(self::AUDIT_ID);

        self::assertSame(self::ELIGIBLE_OPERATION, $audit['operation_id']);
        self::assertSame('dead_letter', $audit['target']);
        self::assertSame(1, $audit['affected_count']);
        self::assertSame('production-retention-v1', $audit['policy']);
        self::assertSame('system:retention', $audit['purged_by']);
    }

    private function seedRows(): void
    {
        $this->operation(self::ELIGIBLE_OPERATION);
        $this->operation(self::HELD_OPERATION);
        $this->operation(self::PAYLOAD_OPERATION);
        $this->deadLetter(self::ELIGIBLE_OPERATION);
        $this->deadLetter(self::HELD_OPERATION);
        $this->hold(self::HELD_OPERATION);
    }

    private function operation(string $operationId): void
    {
        $this->sender->enqueue($this->message($operationId));
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . ".operations
            SET state = 'dead_lettered',
                updated_at = :updated_at
            WHERE operation_id = :operation_id",
            [
                'operation_id' => $operationId,
                'updated_at' => '2026-07-08 00:00:00+00:00',
            ],
        );
    }

    private function deadLetter(string $operationId): void
    {
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.dead_letters (
                operation_id,
                final_attempt_id,
                final_attempt_number,
                reason_type,
                reason_message,
                moved_at
            ) VALUES (
                :operation_id,
                NULL,
                NULL,
                :reason_type,
                :reason_message,
                :moved_at
            )', [
            'operation_id' => $operationId,
            'reason_type' => \RuntimeException::class,
            'reason_message' => 'boom',
            'moved_at' => '2026-07-08 00:00:00+00:00',
        ]);
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
            'hold_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9688d05',
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
            $this->item(self::ELIGIBLE_OPERATION, RetentionTarget::DeadLetter),
            $this->item(self::HELD_OPERATION, RetentionTarget::DeadLetter),
            $this->item(self::PAYLOAD_OPERATION, RetentionTarget::TransportPayload),
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

    private function deadLetterExists(string $operationId): bool
    {
        return (bool) $this->connection->fetchOne('SELECT EXISTS (
                SELECT 1 FROM ' . self::SCHEMA . '.dead_letters WHERE operation_id = :operation_id
            )', [
            'operation_id' => $operationId,
        ]);
    }

    private function operationExists(string $operationId): bool
    {
        return (bool) $this->connection->fetchOne('SELECT EXISTS (
                SELECT 1 FROM ' . self::SCHEMA . '.operations WHERE operation_id = :operation_id
            )', [
            'operation_id' => $operationId,
        ]);
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

final readonly class FixedDeadLetterDeleteClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}

final class FixedDeadLetterAuditIdGenerator implements PostgreSqlRetentionPurgeAuditIdGenerator
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

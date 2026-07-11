<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPlanItem;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeAuditPort;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use BlackOps\Core\Retention\RetentionTarget;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class PostgreSqlOutcomeRetentionDeleteServiceTest extends TestCase
{
    private const SCHEMA = 'blackops_p6_010_outcome_retention';
    private const ELIGIBLE = '019f32ab-2be0-7b38-a0a7-1ab2f9687911';
    private const HELD = '019f32ab-2be0-7b38-a0a7-1ab2f9687912';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $sender = new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA);
        $sender->migrate();

        foreach ([self::ELIGIBLE, self::HELD] as $operationId) {
            $sender->enqueue($this->message($operationId));
            $this->outcome($operationId);
        }

        $this->hold(self::HELD);
    }

    public function testDeletesUnheldOutcomeAndRecordsAuditInTransaction(): void
    {
        $service = new PostgreSqlOutcomeRetentionDeleteService(
            $this->connection,
            new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA),
            self::SCHEMA,
            new FixedOutcomeRetentionClock(),
            new FixedOutcomeRetentionAuditIdGenerator(),
        );

        $deleted = $service->delete(
            $this->plan([self::ELIGIBLE, self::HELD]),
            RetentionPolicyRef::fromString('outcome-14-days'),
            RetentionActorRef::fromString('system:retention'),
        );

        self::assertSame(1, $deleted);
        self::assertFalse($this->exists(self::ELIGIBLE));
        self::assertTrue($this->exists(self::HELD));
        $audit = $this->connection->fetchAssociative('SELECT operation_id::text, target, affected_count
            FROM ' . self::SCHEMA . '.retention_purge_audits');
        self::assertIsArray($audit);
        self::assertSame(self::ELIGIBLE, $audit['operation_id']);
        self::assertSame('outcome', $audit['target']);
        self::assertSame(1, $audit['affected_count']);
    }

    public function testAuditFailureRollsBackOutcomeDelete(): void
    {
        $service = new PostgreSqlOutcomeRetentionDeleteService(
            $this->connection,
            new FailingOutcomeRetentionAuditPort(),
            self::SCHEMA,
            new FixedOutcomeRetentionClock(),
            new FixedOutcomeRetentionAuditIdGenerator(),
        );

        $this->expectException(DeferredTransportException::class);

        try {
            $service->delete(
                $this->plan([self::ELIGIBLE]),
                RetentionPolicyRef::fromString('outcome-14-days'),
                RetentionActorRef::fromString('system:retention'),
            );
        } finally {
            self::assertTrue($this->exists(self::ELIGIBLE));
        }
    }

    /** @param list<string> $ids */
    private function plan(array $ids): RetentionPlan
    {
        return new RetentionPlan(array_map(
            static fn(string $id): RetentionPlanItem => new RetentionPlanItem(
                OperationId::fromString($id),
                RetentionTarget::Outcome,
                new DateTimeImmutable('2026-06-20T00:00:00Z'),
                new DateTimeImmutable('2026-07-04T00:00:00Z'),
            ),
            $ids,
        ));
    }

    private function outcome(string $operationId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.outcomes (
            operation_id, outcome_type, schema_version, encoded_payload, completed_at
        ) VALUES (:operation_id, :type, 1, convert_to(:payload, \'UTF8\'), :completed_at)',
            [
                'operation_id' => $operationId,
                'type' => 'retention.test',
                'payload' => '{}',
                'completed_at' => '2026-06-20 00:00:00+00:00',
            ],
        );
    }

    private function hold(string $operationId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.retention_holds (
            hold_id, operation_id, category, reason, placed_at, placed_by
        ) VALUES (:hold_id, :operation_id, :category, :reason, :placed_at, :placed_by)',
            [
                'hold_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9687913',
                'operation_id' => $operationId,
                'category' => 'legal',
                'reason' => 'legal request',
                'placed_at' => '2026-07-01 00:00:00+00:00',
                'placed_by' => 'legal-team',
            ],
        );
    }

    private function exists(string $operationId): bool
    {
        return (bool) $this->connection->fetchOne('SELECT EXISTS (
            SELECT 1 FROM ' . self::SCHEMA . '.outcomes WHERE operation_id = :operation_id
        )', [
            'operation_id' => $operationId,
        ]);
    }

    private function message(string $operationId): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString($operationId),
            'outcome.retention.test',
            1,
            '{}',
            '{}',
            new DateTimeImmutable('2026-07-01T00:00:00Z'),
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

final readonly class FixedOutcomeRetentionClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-12T00:00:00Z');
    }
}

final readonly class FixedOutcomeRetentionAuditIdGenerator implements PostgreSqlRetentionPurgeAuditIdGenerator
{
    public function generate(DateTimeImmutable $time): RetentionPurgeAuditId
    {
        return RetentionPurgeAuditId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687914');
    }
}

final readonly class FailingOutcomeRetentionAuditPort implements RetentionPurgeAuditPort
{
    public function record(RetentionPurgeAuditRecord $record): void
    {
        throw new RuntimeException('audit unavailable');
    }
}

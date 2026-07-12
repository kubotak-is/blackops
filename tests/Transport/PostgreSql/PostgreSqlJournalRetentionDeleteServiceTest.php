<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
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
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class PostgreSqlJournalRetentionDeleteServiceTest extends TestCase
{
    private const SCHEMA = 'blackops_p6_013_journal';
    private const INLINE_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9689a01';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
        );
        $sender->migrate();
        foreach (new PostgreSqlJournalSchema(self::SCHEMA)->statements() as $statement) {
            $this->connection->executeStatement($statement);
        }
    }

    public function testDeletesInlineJournalAndRecordsPayloadFreeAffectedCount(): void
    {
        $this->journal(self::INLINE_OPERATION, 1, '2026-06-01 00:00:00+00:00');
        $this->journal(self::INLINE_OPERATION, 2, '2026-06-02 00:00:00+00:00');

        $deleted = $this->service(new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA))->delete(
            $this->plan(self::INLINE_OPERATION, '2026-06-02T00:00:00Z'),
            $this->policy(),
            $this->actor(),
        );

        self::assertSame(2, $deleted);
        self::assertSame(0, $this->journalCount(self::INLINE_OPERATION));
        $audit = $this->connection->fetchAssociative('SELECT
                operation_id::text AS operation_id, target, affected_count, policy, purged_by
            FROM ' . self::SCHEMA . '.retention_purge_audits');
        self::assertIsArray($audit);
        self::assertSame(
            [
                'operation_id' => self::INLINE_OPERATION,
                'target' => 'journal',
                'affected_count' => 2,
                'policy' => 'retention-v1',
                'purged_by' => 'system:retention',
            ],
            $audit,
        );
    }

    public function testSkipsWhenNewJournalWasAppendedAfterPlanning(): void
    {
        $this->journal(self::INLINE_OPERATION, 1, '2026-06-01 00:00:00+00:00');
        $plan = $this->plan(self::INLINE_OPERATION, '2026-06-01T00:00:00Z');
        $this->journal(self::INLINE_OPERATION, 2, '2026-06-02 00:00:00+00:00');

        $deleted = $this->service(new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA))->delete(
            $plan,
            $this->policy(),
            $this->actor(),
        );

        self::assertSame(0, $deleted);
        self::assertSame(2, $this->journalCount(self::INLINE_OPERATION));
        self::assertSame(0, $this->auditCount());
    }

    public function testSkipsInlineJournalWhenHoldWasPlacedAfterPlanning(): void
    {
        $this->journal(self::INLINE_OPERATION, 1, '2026-06-01 00:00:00+00:00');
        $plan = $this->plan(self::INLINE_OPERATION, '2026-06-01T00:00:00Z');
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.retention_holds (
            hold_id, operation_id, category, reason, placed_at, placed_by
        ) VALUES (
            :hold_id, :operation_id, :category, :reason, :placed_at, :placed_by
        )', [
            'hold_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9689a20',
            'operation_id' => self::INLINE_OPERATION,
            'category' => 'audit',
            'reason' => 'investigation',
            'placed_at' => '2026-07-01 00:00:00+00:00',
            'placed_by' => 'auditor',
        ]);

        $deleted = $this->service(new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA))->delete(
            $plan,
            $this->policy(),
            $this->actor(),
        );

        self::assertSame(0, $deleted);
        self::assertSame(1, $this->journalCount(self::INLINE_OPERATION));
        self::assertSame(0, $this->auditCount());
    }

    public function testAuditFailureRollsBackJournalDeletion(): void
    {
        $this->journal(self::INLINE_OPERATION, 1, '2026-06-01 00:00:00+00:00');

        try {
            $this->service(new FailingJournalRetentionAudit())->delete(
                $this->plan(self::INLINE_OPERATION, '2026-06-01T00:00:00Z'),
                $this->policy(),
                $this->actor(),
            );
            self::fail('Expected the audit failure to abort the purge.');
        } catch (DeferredTransportException $exception) {
            self::assertStringContainsString('journal', $exception->getMessage());
        }

        self::assertSame(1, $this->journalCount(self::INLINE_OPERATION));
        self::assertSame(0, $this->auditCount());
    }

    private function service(RetentionPurgeAuditPort $audit): PostgreSqlJournalRetentionDeleteService
    {
        return new PostgreSqlJournalRetentionDeleteService(
            $this->connection,
            $audit,
            self::SCHEMA,
            new FixedJournalRetentionClock(),
            new FixedJournalRetentionAuditIdGenerator(),
        );
    }

    private function plan(string $operationId, string $basis): RetentionPlan
    {
        $basisAt = new DateTimeImmutable($basis);

        return new RetentionPlan([new RetentionPlanItem(
            OperationId::fromString($operationId),
            RetentionTarget::Journal,
            $basisAt,
            $basisAt->modify('+30 days'),
        )]);
    }

    private function journal(string $operationId, int $sequence, string $occurredAt): void
    {
        $recordId = $this->recordId($operationId, $sequence);
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.journal (
            record_id, operation_id, sequence, event, schema_version, occurred_at, encoded_record
        ) VALUES (
            :record_id, :operation_id, :sequence, :event, 1, :occurred_at, convert_to(:record, \'UTF8\')
        )', [
            'record_id' => $recordId,
            'operation_id' => $operationId,
            'sequence' => $sequence,
            'event' => 'operation.tested',
            'occurred_at' => $occurredAt,
            'record' => '{"secret":"not audited"}',
        ]);
    }

    private function recordId(string $operationId, int $sequence): string
    {
        $hex = md5($operationId . ':' . $sequence);

        return (
            substr($hex, 0, 8)
            . '-'
            . substr($hex, 8, 4)
            . '-'
            . substr($hex, 12, 4)
            . '-'
            . substr($hex, 16, 4)
            . '-'
            . substr($hex, 20, 12)
        );
    }

    private function journalCount(string $operationId): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM '
        . self::SCHEMA
        . '.journal WHERE operation_id = :operation_id', ['operation_id' => $operationId]);
    }

    private function auditCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.retention_purge_audits');
    }

    private function policy(): RetentionPolicyRef
    {
        return RetentionPolicyRef::fromString('retention-v1');
    }

    private function actor(): RetentionActorRef
    {
        return RetentionActorRef::fromString('system:retention');
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

final readonly class FixedJournalRetentionClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-12T00:00:00Z');
    }
}

final readonly class FixedJournalRetentionAuditIdGenerator implements PostgreSqlRetentionPurgeAuditIdGenerator
{
    public function generate(DateTimeImmutable $time): RetentionPurgeAuditId
    {
        return RetentionPurgeAuditId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9689aff');
    }
}

final readonly class FailingJournalRetentionAudit implements RetentionPurgeAuditPort
{
    public function record(RetentionPurgeAuditRecord $record): void
    {
        throw new RuntimeException('audit unavailable');
    }
}

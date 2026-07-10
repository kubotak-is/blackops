<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeAuditPort;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PostgreSqlRetentionPurgeAuditStoreTest extends TestCase
{
    private const SCHEMA = 'blackops_p5_006';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688a01';
    private const AUDIT_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688a02';
    private const UNKNOWN_OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688a03';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlRetentionPurgeAuditStore $store;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.000000Z'),
        );
        $this->store = new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA);
        $this->sender->migrate();
        $this->sender->enqueue($this->message());
    }

    public function testStoreImplementsRetentionPurgeAuditPort(): void
    {
        self::assertInstanceOf(RetentionPurgeAuditPort::class, $this->store);
    }

    public function testRecordPersistsPayloadFreeAuditMetadata(): void
    {
        $this->store->record($this->record());
        $row = $this->auditRow(self::AUDIT_ID);

        self::assertSame(self::AUDIT_ID, $row['audit_id']);
        self::assertSame(self::OPERATION_ID, $row['operation_id']);
        self::assertSame('transport_payload', $row['target']);
        self::assertSame(2, $row['affected_count']);
        self::assertSame('production-retention-v1', $row['policy']);
        self::assertSame('2026-07-10T15:00:00.000000Z', $row['purged_at']);
        self::assertSame('system:retention', $row['purged_by']);
        self::assertArrayNotHasKey('encoded_payload', $row);
        self::assertArrayNotHasKey('payload', $row);
    }

    public function testTableDoesNotExposePayloadColumns(): void
    {
        $columns = $this->connection->fetchFirstColumn('SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = :schema
                AND table_name = :table
            ORDER BY ordinal_position', [
            'schema' => self::SCHEMA,
            'table' => 'retention_purge_audits',
        ]);

        self::assertSame(
            [
                'audit_id',
                'operation_id',
                'target',
                'affected_count',
                'policy',
                'purged_at',
                'purged_by',
                'created_at',
            ],
            $columns,
        );
    }

    public function testRecordRejectsUnknownOperationId(): void
    {
        $this->expectException(DeferredTransportException::class);

        $this->store->record(
            new RetentionPurgeAuditRecord(
                RetentionPurgeAuditId::fromString(self::AUDIT_ID),
                OperationId::fromString(self::UNKNOWN_OPERATION_ID),
                RetentionPurgeTarget::Journal,
                1,
                RetentionPolicyRef::fromString('production-retention-v1'),
                new DateTimeImmutable('2026-07-10T00:00:00Z'),
                RetentionActorRef::fromString('system:retention'),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function auditRow(string $auditId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT
                audit_id::text AS audit_id,
                operation_id::text AS operation_id,
                target,
                affected_count,
                policy,
                to_char(purged_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS purged_at,
                purged_by
            FROM ' . self::SCHEMA . '.retention_purge_audits
            WHERE audit_id = :audit_id',
            ['audit_id' => $auditId],
        );

        self::assertIsArray($row);

        return $row;
    }

    private function record(): RetentionPurgeAuditRecord
    {
        return new RetentionPurgeAuditRecord(
            RetentionPurgeAuditId::fromString(self::AUDIT_ID),
            OperationId::fromString(self::OPERATION_ID),
            RetentionPurgeTarget::TransportPayload,
            2,
            RetentionPolicyRef::fromString('production-retention-v1'),
            new DateTimeImmutable('2026-07-11T00:00:00+09:00'),
            RetentionActorRef::fromString('system:retention'),
        );
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

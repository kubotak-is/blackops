<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Journal\LifecycleState;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlDiagnosticsFailureKind;
use BlackOps\Transport\PostgreSql\PostgreSqlDiagnosticsReader;
use BlackOps\Transport\PostgreSql\PostgreSqlDiagnosticsReadFailed;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PostgreSqlDiagnosticsReaderTest extends TestCase
{
    private const SCHEMA = 'blackops_p14_003_reader';
    private const OPERATION_ID = '019f5b0e-d13f-73b4-8f57-1f60680fe001';
    private const ATTEMPT_ID = '019f5b0e-d13f-73b4-8f57-1f60680fe002';

    private Connection $connection;
    private PostgreSqlDiagnosticsReader $reader;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $sender = new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA);
        $sender->migrate();
        $sender->enqueue(
            new DeferredOperationMessage(
                $this->operationId(),
                'diagnostics.reader',
                2,
                '{"private":"payload"}',
                '{"private":"context"}',
                new DateTimeImmutable('2026-07-18T00:00:00Z'),
            ),
        );
        $this->reader = new PostgreSqlDiagnosticsReader($this->connection, self::SCHEMA);
    }

    public function testReadsOnlySafeDeferredStateFields(): void
    {
        $state = $this->reader->deferredState($this->operationId());

        self::assertNotNull($state);
        self::assertSame(self::OPERATION_ID, $state->operationId);
        self::assertSame('diagnostics.reader', $state->type);
        self::assertSame(2, $state->schemaVersion);
        self::assertSame(LifecycleState::Accepted, $state->state);
        self::assertSame(1, $state->nextSequence);
        self::assertFalse($state->payloadPurged);
        self::assertSame(0, $state->attemptNumber);
        self::assertNull($state->currentAttemptId);
        self::assertNull($state->currentAttemptStartedAt);
    }

    public function testReadsDeadLetterWithoutRestrictedMessage(): void
    {
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.dead_letters (
            operation_id, final_attempt_id, final_attempt_number, reason_type, reason_message, moved_at
        ) VALUES (
            :operation_id, :attempt_id, 1, :reason_type, :restricted, :moved_at
        )', [
            'operation_id' => self::OPERATION_ID,
            'attempt_id' => self::ATTEMPT_ID,
            'reason_type' => \RuntimeException::class,
            'restricted' => 'private failure detail',
            'moved_at' => '2026-07-18T00:00:05Z',
        ]);

        $detail = $this->reader->deadLetter($this->operationId());

        self::assertNotNull($detail);
        self::assertSame(self::OPERATION_ID, $detail->operationId);
        self::assertSame(self::ATTEMPT_ID, $detail->finalAttemptId);
        self::assertSame(1, $detail->finalAttemptNumber);
        self::assertSame(\RuntimeException::class, $detail->reasonType);
        self::assertSame('2026-07-18T00:00:05.000000Z', $detail->movedAt);
        self::assertSame(
            ['operationId', 'finalAttemptId', 'finalAttemptNumber', 'reasonType', 'movedAt'],
            array_keys(get_object_vars($detail)),
        );
    }

    public function testReadsOnlySafePurgeAuditEvidence(): void
    {
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.retention_purge_audits (
            audit_id, operation_id, target, affected_count, policy, purged_at, purged_by
        ) VALUES (
            :audit_id, :operation_id, :target, 1, :restricted_policy, :purged_at, :restricted_actor
        )', [
            'audit_id' => '019f5b0e-d13f-73b4-8f57-1f60680fe003',
            'operation_id' => self::OPERATION_ID,
            'target' => RetentionPurgeTarget::Journal->value,
            'restricted_policy' => 'private-policy-detail',
            'purged_at' => '2026-07-18T01:00:00Z',
            'restricted_actor' => 'private-actor-id',
        ]);

        $audits = $this->reader->purgeAudits($this->operationId());

        self::assertCount(1, $audits);
        self::assertSame(RetentionPurgeTarget::Journal, $audits[0]->target);
        self::assertSame(1, $audits[0]->affectedCount);
        self::assertSame('2026-07-18T01:00:00.000000Z', $audits[0]->purgedAt);
        self::assertSame(['target', 'affectedCount', 'purgedAt'], array_keys(get_object_vars($audits[0])));
    }

    public function testStorageFailureUsesSafeCode(): void
    {
        $this->connection->executeStatement('DROP SCHEMA ' . self::SCHEMA . ' CASCADE');

        try {
            $this->reader->deferredState($this->operationId());
            self::fail('Expected diagnostics storage failure.');
        } catch (PostgreSqlDiagnosticsReadFailed $exception) {
            self::assertSame(PostgreSqlDiagnosticsFailureKind::Storage, $exception->kind);
            self::assertSame('PostgreSQL diagnostics read failed.', $exception->getMessage());
        }
    }

    private function operationId(): OperationId
    {
        return OperationId::fromString(self::OPERATION_ID);
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

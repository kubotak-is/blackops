<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Transport\PostgreSql\PostgreSqlDeadLetterRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPlanner;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditStore;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeService;
use BlackOps\Transport\PostgreSql\PostgreSqlTransportPayloadTombstoneService;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class PostgreSqlRetentionPurgeServiceTest extends TestCase
{
    private const SCHEMA = 'blackops_p5_010';
    private const PAYLOAD_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688e01';
    private const DEAD_LETTER_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688e02';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlRetentionPurgeService $service;

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

        $audit = new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA);
        $ids = new FixedPurgeServiceAuditIdGenerator([
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e11',
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e12',
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e13',
        ]);
        $clock = new FixedPurgeServiceClock('2026-07-12T00:00:00.000000Z');

        $this->service = new PostgreSqlRetentionPurgeService(
            new PostgreSqlRetentionPlanner($this->connection, self::SCHEMA),
            new PostgreSqlTransportPayloadTombstoneService($this->connection, $audit, self::SCHEMA, $clock, $ids),
            new PostgreSqlDeadLetterRetentionDeleteService($this->connection, $audit, self::SCHEMA, $clock, $ids),
        );
    }

    public function testPurgePlansAndExecutesSupportedTargets(): void
    {
        $this->seedRows();

        $result = $this->service->purge(
            new RetentionPolicy(
                RetentionPeriod::days(1),
                RetentionPeriod::days(30),
                RetentionPeriod::days(14),
                RetentionPeriod::days(2),
            ),
            RetentionPolicyRef::fromString('production-retention-v1'),
            RetentionActorRef::fromString('system:retention'),
            new DateTimeImmutable('2026-07-11T00:00:00Z'),
        );

        self::assertSame(3, $result->plan()->count());
        self::assertSame(2, $result->transportPayloadsPurged());
        self::assertSame(1, $result->deadLettersDeleted());
        self::assertSame(3, $result->totalAffected());
        self::assertNull($this->operationPayload(self::PAYLOAD_OPERATION));
        self::assertNull($this->operationPayload(self::DEAD_LETTER_OPERATION));
        self::assertFalse($this->deadLetterExists(self::DEAD_LETTER_OPERATION));
        self::assertSame(3, $this->auditCount());
    }

    private function seedRows(): void
    {
        $this->operation(self::PAYLOAD_OPERATION, 'completed', '2026-07-09 00:00:00+00:00');
        $this->operation(self::DEAD_LETTER_OPERATION, 'dead_lettered', '2026-07-08 00:00:00+00:00');
        $this->deadLetter(self::DEAD_LETTER_OPERATION, '2026-07-08 00:00:00+00:00');
    }

    private function operation(string $operationId, string $state, string $updatedAt): void
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
                'updated_at' => $updatedAt,
            ],
        );
    }

    private function deadLetter(string $operationId, string $movedAt): void
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
            'moved_at' => $movedAt,
        ]);
    }

    private function operationPayload(string $operationId): ?string
    {
        $payload = $this->connection->fetchOne(
            'SELECT convert_from(encoded_payload, \'UTF8\')
            FROM ' . self::SCHEMA . '.operations
            WHERE operation_id = :operation_id',
            ['operation_id' => $operationId],
        );

        return is_string($payload) ? $payload : null;
    }

    private function deadLetterExists(string $operationId): bool
    {
        return (bool) $this->connection->fetchOne('SELECT EXISTS (
                SELECT 1 FROM ' . self::SCHEMA . '.dead_letters WHERE operation_id = :operation_id
            )', [
            'operation_id' => $operationId,
        ]);
    }

    private function auditCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.retention_purge_audits');
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

final readonly class FixedPurgeServiceClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}

final class FixedPurgeServiceAuditIdGenerator implements PostgreSqlRetentionPurgeAuditIdGenerator
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

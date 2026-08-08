<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Scheduler;

use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationSender;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Outbox\OutboxRelayConfiguration;
use BlackOps\Internal\Outbox\OutboxRelayRuntime;
use BlackOps\Internal\Scheduler\MaintenanceScheduler;
use BlackOps\Internal\Scheduler\MaintenanceTask;
use BlackOps\Internal\Scheduler\MaintenanceTaskResult;
use BlackOps\Internal\Scheduler\OutboxRelayMaintenanceTask;
use BlackOps\Tests\Transport\PostgreSql\PostgreSqlTestStorageProtection;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class OutboxRelayMaintenanceTaskTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS scheduler_outbox_test CASCADE');
        $this->store()->migrate();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS scheduler_outbox_test CASCADE');
        $this->connection->close();
    }

    public function testRelayFailureIsIsolatedAndFiniteTaskReturnsSafeResult(): void
    {
        $store = $this->store();
        $runtime = new OutboxRelayRuntime(
            $store,
            new ThrowingSchedulerSender(),
            new OutboxRelayConfiguration('scheduler-relay'),
            new PostgreSqlSystemClock(),
        );
        $this->connection->executeStatement('DROP SCHEMA scheduler_outbox_test CASCADE');
        $relay = new OutboxRelayMaintenanceTask($runtime);
        $later = new LaterSchedulerTask();
        $result = new MaintenanceScheduler([$relay, $later])->run(new DateTimeImmutable('2026-07-24T01:02:04Z'));

        self::assertSame(2, $result->count());
        self::assertSame('outbox-relay', $result->taskResults()[0]->taskName());
        self::assertSame(0, $result->taskResults()[0]->affectedCount());
        self::assertSame('Outbox relay failed safely.', $result->taskResults()[0]->summary());
        self::assertSame(3, $result->taskResults()[1]->affectedCount());
    }

    private function store(): PostgreSqlOutboxStore
    {
        return new PostgreSqlOutboxStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            new ReflectionJsonOperationCodec(),
            'scheduler_outbox_test',
        );
    }
}

final class ThrowingSchedulerSender implements OperationSender
{
    public function enqueue(DeferredOperationMessage $message): DeferredAcknowledgement
    {
        throw new \RuntimeException('not reached');
    }
}

final class LaterSchedulerTask implements MaintenanceTask
{
    public function name(): string
    {
        return 'later';
    }

    public function run(DateTimeImmutable $now): MaintenanceTaskResult
    {
        return new MaintenanceTaskResult('later', 3, 'ran');
    }
}

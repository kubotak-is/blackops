<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Outbox;

use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationSender;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Internal\Outbox\OutboxRelayConfiguration;
use BlackOps\Internal\Outbox\OutboxRelayRuntime;
use BlackOps\Internal\Outbox\PcntlOutboxSignalHeartbeat;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxClaim;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class OutboxRelayRuntimeTest extends TestCase
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
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS outbox_runtime_test CASCADE');
        new PostgreSqlOutboxStore($this->connection, 'outbox_runtime_test')->migrate();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS outbox_runtime_test CASCADE');
        $this->connection->close();
    }

    public function testBlockingDeliveryReceivesPeriodicHeartbeatOnSeparateConnection(): void
    {
        if (!function_exists('posix_kill')) {
            self::markTestSkipped('POSIX signal sending is unavailable.');
        }

        $recorded = new DateTimeImmutable('now');
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_runtime_test');
        $store->insert(
            new PostgreSqlOutboxRecord(
                OutboxRecordId::fromString('019f45b2-7c2d-7abc-8def-0123456789ab'),
                OperationId::fromString('019f45b2-7c2d-7abc-8def-0123456789ac'),
                'mail.send',
                1,
                '{}',
                '{}',
                $recorded,
                $recorded,
                'app',
            ),
        );
        $heartbeatConnection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $heartbeatStore = new PostgreSqlOutboxStore($heartbeatConnection, 'outbox_runtime_test');
        $heartbeats = 0;
        $signals = new PcntlOutboxSignalHeartbeat(
            static function (PostgreSqlOutboxClaim $claim) use ($heartbeatStore, &$heartbeats): void {
                ++$heartbeats;
                $heartbeatStore->heartbeat($claim, new DateTimeImmutable('now'), 3);
            },
            1,
            3,
            2,
        );
        $runtime = new OutboxRelayRuntime(
            $store,
            new BlockingSender(2.2),
            new OutboxRelayConfiguration('relay-runtime', leaseSeconds: 3, heartbeatSeconds: 1, graceSeconds: 2),
            new PostgreSqlSystemClock(),
            $heartbeatStore,
            $signals,
        );

        $result = $runtime->runBatch($recorded->modify('+1 second'));
        $heartbeatConnection->close();

        self::assertSame(1, $result->sent);
        self::assertGreaterThanOrEqual(2, $heartbeats);
        self::assertSame(
            'sent',
            $this->connection->fetchOne('SELECT state FROM "outbox_runtime_test"."outbox_records"'),
        );
    }

    public function testCrashAfterAcceptanceReplaysOneOperationAndConvergesToSent(): void
    {
        $now = new DateTimeImmutable('2026-07-24T01:02:04+00:00');
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_runtime_test');
        $store->insert($this->record('019f45b2-7c2d-7abc-8def-0123456789ab', '019f45b2-7c2d-7abc-8def-0123456789ac'));
        $sender = new PostgreSqlDeferredOperationSender($this->connection, 'outbox_runtime_test', $now);
        $sender->migrate();
        $claim = $store->claimBatch('relay-a', 1, $now, 60)[0];
        $sender->enqueue($claim->message);
        $recoveryConnection = $this->connection();
        $recoveryStore = new PostgreSqlOutboxStore($recoveryConnection, 'outbox_runtime_test');
        $recoverySender = new PostgreSqlDeferredOperationSender($recoveryConnection, 'outbox_runtime_test', $now);
        $clock = new FrozenOutboxClock($now->modify('+61 seconds'));
        $runtime = new OutboxRelayRuntime(
            $recoveryStore,
            $recoverySender,
            new OutboxRelayConfiguration('relay-b', leaseSeconds: 60, heartbeatSeconds: 10),
            $clock,
        );

        self::assertSame(1, $runtime->runBatch()->sent);
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT count(*) FROM "outbox_runtime_test"."operations"'),
        );
        self::assertSame(
            'sent',
            $this->connection->fetchOne('SELECT state FROM "outbox_runtime_test"."outbox_records"'),
        );
        self::assertSame(
            2,
            (int) $this->connection->fetchOne('SELECT fencing_token FROM "outbox_runtime_test"."outbox_records"'),
        );
        self::assertSame(
            $now->format('Y-m-d H:i:s.uP'),
            new DateTimeImmutable((string) $this->connection->fetchOne(
                'SELECT accepted_at FROM "outbox_runtime_test"."operations"',
            ))->format('Y-m-d H:i:s.uP'),
        );
        $recoveryConnection->close();
    }

    public function testRetryBackoffDeadLetterFingerprintAndBatchIsolationAreSafe(): void
    {
        $now = new DateTimeImmutable('2026-07-24T01:02:04+00:00');
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_runtime_test');
        $store->insert($this->record('019f45b2-7c2d-7abc-8def-0123456789ab', '019f45b2-7c2d-7abc-8def-0123456789ac'));
        $store->insert($this->record('019f45b2-7c2d-7abc-8def-0123456789ad', '019f45b2-7c2d-7abc-8def-0123456789ae'));
        $clock = new FrozenOutboxClock($now);
        $runtime = new OutboxRelayRuntime(
            $store,
            new SelectiveFailureSender(),
            new OutboxRelayConfiguration(
                'relay-retry',
                batchSize: 2,
                maxAttempts: 2,
                initialBackoffSeconds: 2,
                maxBackoffSeconds: 4,
            ),
            $clock,
        );

        $first = $runtime->runBatch();
        self::assertSame(2, $first->claimed);
        self::assertSame(1, $first->sent);
        self::assertSame(1, $first->retried);
        $next = new DateTimeImmutable((string) $this->connection->fetchOne(
            'SELECT next_attempt_at FROM "outbox_runtime_test"."outbox_records" WHERE state=\'retry_scheduled\'',
        ));
        self::assertGreaterThanOrEqual($now->modify('+2 seconds')->getTimestamp(), $next->getTimestamp());
        $fingerprint = (string) $this->connection->fetchOne(
            'SELECT failure_fingerprint FROM "outbox_runtime_test"."outbox_records" WHERE state=\'retry_scheduled\'',
        );
        self::assertSame(
            'v1:' . hash('sha256', "blackops.outbox.relay.failure.v1\0" . RuntimeException::class),
            $fingerprint,
        );

        $clock->set($now->modify('+3 seconds'));
        self::assertSame(1, $runtime->runBatch()->deadLettered);
        self::assertSame(
            $fingerprint,
            $this->connection->fetchOne(
                'SELECT failure_fingerprint FROM "outbox_runtime_test"."outbox_records" WHERE state=\'dead_lettered\'',
            ),
        );
    }

    public function testStopAfterFirstClaimFinishesOwnedBatchWithoutClaimingAnotherBatch(): void
    {
        if (!function_exists('posix_kill')) {
            self::markTestSkipped('POSIX signal sending is unavailable.');
        }

        $now = new DateTimeImmutable('2026-07-24T01:02:04+00:00');
        $store = new PostgreSqlOutboxStore($this->connection, 'outbox_runtime_test');
        $store->insert($this->record('019f45b2-7c2d-7abc-8def-0123456789ab', '019f45b2-7c2d-7abc-8def-0123456789ac'));
        $store->insert($this->record('019f45b2-7c2d-7abc-8def-0123456789ad', '019f45b2-7c2d-7abc-8def-0123456789ae'));
        $store->insert($this->record('019f45b2-7c2d-7abc-8def-0123456789af', '019f45b2-7c2d-7abc-8def-0123456789b0'));
        $signals = new PcntlOutboxSignalHeartbeat(
            static function (PostgreSqlOutboxClaim $claim) use ($store): void {
                $store->heartbeat($claim, new DateTimeImmutable('now'), 3);
            },
            1,
            3,
            2,
        );
        $runtime = new OutboxRelayRuntime(
            $store,
            new StopAfterFirstSender(),
            new OutboxRelayConfiguration(
                'relay-stop',
                batchSize: 2,
                leaseSeconds: 3,
                heartbeatSeconds: 1,
                graceSeconds: 2,
            ),
            new FrozenOutboxClock($now),
            $store,
            $signals,
        );

        $result = $runtime->runBatch();
        self::assertSame(2, $result->claimed);
        self::assertSame(2, $result->sent);
        self::assertSame(
            2,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM "outbox_runtime_test"."outbox_records" WHERE state=\'sent\'',
            ),
        );
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM "outbox_runtime_test"."outbox_records" WHERE state=\'pending\'',
            ),
        );
        self::assertTrue($runtime->stopRequested());
    }

    private function record(string $recordId, string $operationId): PostgreSqlOutboxRecord
    {
        $time = new DateTimeImmutable('2026-07-24T01:02:03+00:00');
        return new PostgreSqlOutboxRecord(
            OutboxRecordId::fromString($recordId),
            OperationId::fromString($operationId),
            'mail.send',
            1,
            '{}',
            '{}',
            $time,
            $time,
            'app',
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

final class BlockingSender implements OperationSender
{
    public function __construct(
        private readonly float $seconds,
    ) {}

    public function enqueue(DeferredOperationMessage $message): DeferredAcknowledgement
    {
        $until = microtime(true) + $this->seconds;
        while (microtime(true) < $until) {
            usleep(50_000);
        }

        return new DeferredAcknowledgement($message->operationId(), new DateTimeImmutable('now'));
    }
}

final class SelectiveFailureSender implements OperationSender
{
    public function enqueue(DeferredOperationMessage $message): DeferredAcknowledgement
    {
        if ($message->operationId()->toString() === '019f45b2-7c2d-7abc-8def-0123456789ac') {
            throw new RuntimeException('secret transport detail');
        }
        return new DeferredAcknowledgement($message->operationId(), new DateTimeImmutable('2026-07-24T01:02:04Z'));
    }
}

final class StopAfterFirstSender implements OperationSender
{
    private int $calls = 0;

    public function enqueue(DeferredOperationMessage $message): DeferredAcknowledgement
    {
        ++$this->calls;
        if ($this->calls === 1) {
            $pid = getmypid();
            if ($pid === false || !posix_kill($pid, SIGTERM)) {
                throw new RuntimeException('SIGTERM could not be sent to the test process.');
            }
            pcntl_signal_dispatch();
        }
        return new DeferredAcknowledgement($message->operationId(), new DateTimeImmutable('2026-07-24T01:02:04Z'));
    }
}

final class FrozenOutboxClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }

    public function set(DateTimeImmutable $time): void
    {
        $this->time = $time;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationSender;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Internal\Console\OutboxDeadLetterRetryCommand;
use BlackOps\Internal\Console\OutboxRelayDaemonCommand;
use BlackOps\Internal\Console\OutboxRelayRunCommand;
use BlackOps\Internal\Outbox\OutboxRelayConfiguration;
use BlackOps\Internal\Outbox\OutboxRelayRuntime;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class OutboxRelayCommandsTest extends TestCase
{
    private Connection $connection;
    private PostgreSqlOutboxStore $store;
    private FrozenCommandClock $clock;

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
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS command_outbox_test CASCADE');
        $this->store = new PostgreSqlOutboxStore($this->connection, 'command_outbox_test');
        $this->store->migrate();
        $this->clock = new FrozenCommandClock(new DateTimeImmutable('2026-07-24T01:02:04+00:00'));
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS command_outbox_test CASCADE');
        $this->connection->close();
    }

    public function testRunCommandDefaultBatchesUntilEmptyAndRejectsInvalidOptions(): void
    {
        $this->store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
        ));
        $this->store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ad',
            '019f45b2-7c2d-7abc-8def-0123456789ae',
        ));
        $command = new OutboxRelayRunCommand($this->runtime());
        $tester = new CommandTester($command);
        $tester->execute([]);
        self::assertSame("claimed: 1\nsent: 1\nretried: 0\ndead-lettered: 0\nstale: 0\n", $tester->getDisplay());

        $this->store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789af',
            '019f45b2-7c2d-7abc-8def-0123456789b0',
        ));
        $this->store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789b1',
            '019f45b2-7c2d-7abc-8def-0123456789b2',
        ));
        $tester->execute(['--batches' => '2']);
        self::assertStringContainsString("claimed: 2\nsent: 2", $tester->getDisplay());

        $this->store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789b3',
            '019f45b2-7c2d-7abc-8def-0123456789b4',
        ));
        $this->store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789b5',
            '019f45b2-7c2d-7abc-8def-0123456789b6',
        ));
        $this->store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789b7',
            '019f45b2-7c2d-7abc-8def-0123456789b8',
        ));
        $tester->execute(['--until-empty' => true]);
        self::assertStringContainsString("claimed: 4\nsent: 4", $tester->getDisplay());

        try {
            $tester->execute(['--batches' => '1', '--until-empty' => true]);
            self::fail('Conflicting run options were accepted.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        $this->expectException(\InvalidArgumentException::class);
        $tester->execute(['--batches' => '0']);
    }

    public function testDaemonIterationsAndReuseAndOptionValidation(): void
    {
        $command = new OutboxRelayDaemonCommand($this->runtime(), 1);
        $tester = new CommandTester($command);
        $tester->execute(['--iterations' => '2', '--interval-milliseconds' => '1']);
        self::assertSame(2, substr_count($tester->getDisplay(), 'claimed: 0'));
        $tester->execute(['--iterations' => '1', '--interval-milliseconds' => '1']);
        self::assertSame(1, substr_count($tester->getDisplay(), 'claimed: 0'));

        try {
            $tester->execute(['--interval-milliseconds' => '0']);
            self::fail('Invalid daemon interval was accepted.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        $this->expectException(\InvalidArgumentException::class);
        $tester->execute(['--iterations' => '0']);
    }

    public function testDeadLetterRetryRequiresSafeInputsAndWritesAudit(): void
    {
        $this->store->insert($this->record(
            '019f45b2-7c2d-7abc-8def-0123456789ab',
            '019f45b2-7c2d-7abc-8def-0123456789ac',
        ));
        $claim = $this->store->claimBatch('relay-a', 1, $this->clock->now(), 60)[0];
        $this->store->moveToDeadLetter($claim, 'v1:' . str_repeat('a', 64));
        $tester = new CommandTester(new OutboxDeadLetterRetryCommand($this->store, $this->clock));
        $tester->execute([
            'record-id' => $claim->recordId->toString(),
            '--actor' => 'operator',
            '--reason' => 'manual retry',
        ]);
        self::assertSame(
            'retry_scheduled',
            $this->connection->fetchOne('SELECT state FROM "command_outbox_test"."outbox_records"'),
        );
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM "command_outbox_test"."outbox_dead_letter_retry_audits"',
            ),
        );
        self::assertNull($this->connection->fetchOne(
            'SELECT dead_lettered_at FROM "command_outbox_test"."outbox_records"',
        ));
        $audit = $this->connection->fetchAssociative(
            'SELECT record_id::text, operation_id::text, actor, reason, retried_at, previous_attempt_count FROM "command_outbox_test"."outbox_dead_letter_retry_audits"',
        );
        self::assertSame($claim->recordId->toString(), $audit['record_id']);
        self::assertSame($claim->message->operationId()->toString(), $audit['operation_id']);
        self::assertSame('operator', $audit['actor']);
        self::assertSame('manual retry', $audit['reason']);
        self::assertNotEmpty($audit['retried_at']);
        self::assertSame($claim->attemptCount, (int) $audit['previous_attempt_count']);

        $this->expectException(\InvalidArgumentException::class);
        $tester->execute(['record-id' => $claim->recordId->toString(), '--actor' => '', '--reason' => '']);
    }

    private function runtime(): OutboxRelayRuntime
    {
        return new OutboxRelayRuntime(
            $this->store,
            new ImmediateCommandSender(),
            new OutboxRelayConfiguration('command-relay', batchSize: 1),
            $this->clock,
        );
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
}

final class ImmediateCommandSender implements OperationSender
{
    public function enqueue(DeferredOperationMessage $message): DeferredAcknowledgement
    {
        return new DeferredAcknowledgement($message->operationId(), new DateTimeImmutable('2026-07-24T01:02:04Z'));
    }
}

final class FrozenCommandClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

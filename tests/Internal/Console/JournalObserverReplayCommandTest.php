<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Console\JournalObserverReplayCommand;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Internal\Replay\ObserverReplayRuntime;
use BlackOps\Internal\Replay\ObserverReplayTargetRegistry;
use BlackOps\Journal\JournalObserver;
use BlackOps\Journal\ObservedJournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayStore;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class JournalObserverReplayCommandTest extends TestCase
{
    public function testRejectsMissingSelectorAndNonExclusiveFlagsBeforeDatabaseQuery(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => getenv('POSTGRES_PORT') ?: 5432,
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore($connection, 'blackops'),
            new ObserverReplayTargetRegistry([]),
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
        );
        $command = new JournalObserverReplayCommand($runtime);
        $this->expectException(\InvalidArgumentException::class);
        $command->run(new ArrayInput(['--dry-run' => true, '--confirm' => true]), new BufferedOutput());
    }

    public function testDryRunWritesSafeCountersOnly(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => getenv('POSTGRES_PORT') ?: 5432,
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $schema = 'blackops_cli_replay';
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');
        foreach (new \BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema($schema)->statements() as $sql)
            $connection->executeStatement($sql);
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore($connection, $schema),
            new ObserverReplayTargetRegistry([new JournalObserverBinding('noop', new NoopObserver())]),
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
        );
        $output = new BufferedOutput();
        new JournalObserverReplayCommand($runtime)->run(new ArrayInput([
            '--dry-run' => true,
            '--operation-id' => '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
            '--observer' => ['noop'],
        ]), $output);
        $text = $output->fetch();
        self::assertStringContainsString('selected: 0', $text);
        self::assertStringNotContainsString('checkpoint:', $text);
        self::assertStringNotContainsString('operator', $text);
        self::assertStringNotContainsString('payload', $text);
        $timeOutput = new BufferedOutput();
        new JournalObserverReplayCommand($runtime)->run(new ArrayInput([
            '--dry-run' => true,
            '--from' => '2026-07-01T00:00:00Z',
            '--to' => '2026-07-02T00:00:00Z',
            '--observer' => ['noop'],
        ]), $timeOutput);
        self::assertStringContainsString('selected: 0', $timeOutput->fetch());
    }

    public function testConfirmPrintsCheckpointAndResumeUsesPersistedBinding(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => getenv('POSTGRES_PORT') ?: 5432,
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $schema = 'blackops_cli_confirm';
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');
        foreach (new \BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema($schema)->statements() as $sql)
            $connection->executeStatement($sql);
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore($connection, $schema),
            new ObserverReplayTargetRegistry([new JournalObserverBinding('noop', new NoopObserver())]),
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
        );
        $command = new JournalObserverReplayCommand($runtime);
        $output = new BufferedOutput();
        $command->run(new ArrayInput([
            '--confirm' => true,
            '--operation-id' => '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
            '--observer' => ['noop'],
            '--checkpoint' => 'confirm-checkpoint',
            '--actor' => 'operator',
            '--reason' => 'test',
        ]), $output);
        $text = $output->fetch();
        self::assertStringContainsString('checkpoint: confirm-checkpoint', $text);
        $resumeOutput = new BufferedOutput();
        $command->run(new ArrayInput([
            '--confirm' => true,
            '--resume' => 'confirm-checkpoint',
            '--actor' => 'operator',
            '--reason' => 'resume',
        ]), $resumeOutput);
        self::assertStringContainsString('complete: true', $resumeOutput->fetch());
        self::assertSame(
            2,
            (int) $connection->fetchOne('SELECT count(*) FROM "' . $schema . '"."observer_replay_audits"'),
        );
    }

    public function testStrictTimeAndBatchOptionsRejectBeforeQuery(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => getenv('POSTGRES_PORT') ?: 5432,
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore($connection, 'blackops'),
            new ObserverReplayTargetRegistry([]),
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
        );
        foreach (['0', '1001'] as $size) {
            $this->expectException(\InvalidArgumentException::class);
            try {
                new JournalObserverReplayCommand($runtime)->run(new ArrayInput([
                    '--dry-run' => true,
                    '--record-id' => '019f32ab-2be0-7b38-a0a7-1ab2f968769a',
                    '--observer' => ['missing'],
                    '--batch-size' => $size,
                ]), new BufferedOutput());
            } catch (\InvalidArgumentException $exception) {
                if ($size === '0')
                    throw $exception;
            }
        }
    }

    public function testInvalidCalendarDateIsRejected(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => getenv('POSTGRES_PORT') ?: 5432,
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore($connection, 'blackops'),
            new ObserverReplayTargetRegistry([]),
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
        );
        $this->expectException(\InvalidArgumentException::class);
        new JournalObserverReplayCommand($runtime)->run(new ArrayInput([
            '--dry-run' => true,
            '--from' => '2026-02-30T00:00:00Z',
            '--to' => '2026-03-01T00:00:00Z',
            '--observer' => ['missing'],
        ]), new BufferedOutput());
    }
}

final class NoopObserver implements JournalObserver
{
    public function observe(ObservedJournalRecord $record): void {}
}

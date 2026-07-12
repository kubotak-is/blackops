<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\DatabaseMigrationMigrateCommand;
use BlackOps\Internal\Console\DatabaseMigrationStatusCommand;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DatabaseMigrationCommandTest extends TestCase
{
    private const SCHEMA = 'blackops_database_migration_command';

    private Connection $connection;
    private DatabaseMigrationRunner $runner;

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
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);
    }

    public function testStatusShowsPendingThenAppliedBaseline(): void
    {
        $pending = new CommandTester(new DatabaseMigrationStatusCommand($this->runner));
        self::assertSame(0, $pending->execute([]));
        self::assertStringContainsString('applied: 0', $pending->getDisplay());
        self::assertStringContainsString('pending: 2', $pending->getDisplay());

        $migrate = new CommandTester(new DatabaseMigrationMigrateCommand($this->runner));
        self::assertSame(0, $migrate->execute([], ['interactive' => false]));
        self::assertStringContainsString('Database migrations applied', $migrate->getDisplay());
        self::assertStringContainsString('migrations: 2', $migrate->getDisplay());

        $applied = new CommandTester(new DatabaseMigrationStatusCommand($this->runner));
        self::assertSame(0, $applied->execute([]));
        self::assertStringContainsString('applied: 2', $applied->getDisplay());
        self::assertStringContainsString('pending: 0', $applied->getDisplay());
    }

    public function testDryRunPrintsPlanAndDoesNotInitializeSchema(): void
    {
        $tester = new CommandTester(new DatabaseMigrationMigrateCommand($this->runner));

        $status = $tester->execute(['--dry-run' => true], ['interactive' => false]);

        self::assertSame(0, $status);
        self::assertStringContainsString('Database migration dry run', $tester->getDisplay());
        self::assertStringContainsString('CREATE TABLE', $tester->getDisplay());
        self::assertFalse((bool) $this->connection->fetchOne('SELECT EXISTS (SELECT 1 FROM information_schema.schemata WHERE schema_name = :schema)', [
            'schema' => self::SCHEMA,
        ]));
    }

    public function testMigrateReportsNoPendingMigrationOnSecondApply(): void
    {
        $tester = new CommandTester(new DatabaseMigrationMigrateCommand($this->runner));
        $tester->execute([], ['interactive' => false]);

        self::assertSame(0, $tester->execute([], ['interactive' => false]));
        self::assertStringContainsString('migrations: 0', $tester->getDisplay());
        self::assertStringContainsString('No pending migrations.', $tester->getDisplay());
    }
}

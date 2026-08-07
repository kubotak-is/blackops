<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

require_once __DIR__ . '/../../../migrations/postgresql/Version20260728133000.php';

use BlackOps\Migrations\PostgreSql\Version20260728133000;
use BlackOps\Transport\PostgreSql\PostgreSqlScheduleSchema;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PostgreSqlScheduleSchemaTest extends TestCase
{
    public function testSchemaContainsStateOccurrenceConstraintsAndRecoveryIndexes(): void
    {
        $schema = new PostgreSqlScheduleSchema('blackops_test');
        $sql = implode("\n", $schema->statements());
        self::assertStringContainsString('schedule_states', $sql);
        self::assertStringContainsString('schedule_occurrences', $sql);
        self::assertStringContainsString('ON DELETE RESTRICT', $sql);
        self::assertStringContainsString('skipped_misfire', $sql);
        self::assertStringContainsString('schedule_occurrences_recovery_idx', $sql);
        self::assertStringContainsString("date_trunc('minute'", $sql);
    }

    public function testSchemaHelperAndMigrationUseTheSameTableAndIndexSql(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $migration = new Version20260728133000($connection, new NullLogger(), 'blackops_test');
        $migration->up(new Schema());
        $migrationSql = array_map(static fn(object $query): string => $query->getStatement(), $migration->getSql());
        $helperSql = new PostgreSqlScheduleSchema('blackops_test')->statements();
        self::assertStringContainsString('tenant_type text NULL', $helperSql[2]);
        self::assertStringContainsString('schedule_occurrences_recovery_idx', implode("\n", $migrationSql));
        self::assertStringContainsString('schedule_occurrences_state_idx', implode("\n", $migrationSql));
        $connection->close();
    }
}

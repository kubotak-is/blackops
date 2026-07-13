<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Migration;

use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use InvalidArgumentException;
use ParseError;
use PHPUnit\Framework\TestCase;

final class DatabaseMigrationRunnerTest extends TestCase
{
    private const SCHEMA = 'blackops_database_migration';
    private const LEGACY_SCHEMA = 'blackops_database_migration_legacy';

    private Connection $connection;

    /** @var list<string> */
    private array $migrationDirectories = [];

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::LEGACY_SCHEMA . ' CASCADE');
    }

    protected function tearDown(): void
    {
        foreach ($this->migrationDirectories as $directory) {
            if (is_link($directory) || is_file($directory)) {
                unlink($directory);
                continue;
            }

            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testDependencyFactoryRecognizesSchemaConfiguredMigrations(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);
        $migrations = $runner->dependencyFactory()->getMigrationPlanCalculator()->getMigrations();

        self::assertCount(2, $migrations);
        self::assertSame(
            'BlackOps\\Migrations\\PostgreSql\\Version20260712000000',
            (string) $migrations->getFirst()->getVersion(),
        );
    }

    public function testFreshDatabaseStatusIsReadOnlyAndReportsBaselinePending(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);

        $status = $runner->status();

        self::assertSame([], $status->appliedVersions);
        self::assertSame(
            [
                'BlackOps\\Migrations\\PostgreSql\\Version20260712000000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260712010000',
            ],
            $status->pendingVersions,
        );
        self::assertFalse($this->schemaExists(self::SCHEMA));
    }

    public function testDryRunReturnsCompletePlanWithoutCreatingSchemaOrTables(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);

        $result = $runner->dryRun();

        self::assertTrue($result->dryRun);
        self::assertSame(2, $result->migrations);
        self::assertNotEmpty($result->sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS "' . self::SCHEMA . '"."operations"', implode(
            "\n",
            $result->sql,
        ));
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS "'
        . self::SCHEMA
        . '"."retention_purge_audits"', implode("\n", $result->sql));
        self::assertStringContainsString('schema_migrations', implode("\n", $result->sql));
        self::assertStringContainsString('DROP CONSTRAINT IF EXISTS retention_holds_operation_id_fkey', implode(
            "\n",
            $result->sql,
        ));
        self::assertStringContainsString('DROP CONSTRAINT IF EXISTS retention_purge_audits_operation_id_fkey', implode(
            "\n",
            $result->sql,
        ));
        self::assertFalse($this->schemaExists(self::SCHEMA));
    }

    public function testApplyCreatesCurrentFrameworkSchemaAndDoctrineMetadata(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);

        $result = $runner->migrate();

        self::assertFalse($result->dryRun);
        self::assertSame(2, $result->migrations);
        self::assertSame(
            [
                'dead_letters',
                'journal',
                'operations',
                'outcomes',
                'retention_holds',
                'retention_purge_audits',
                'schema_migrations',
            ],
            $this->tables(self::SCHEMA),
        );
        self::assertSame(
            [
                'executed_at' => 'timestamp without time zone',
                'execution_time' => 'integer',
                'version' => 'character varying',
            ],
            $this->metadataColumnTypes(self::SCHEMA),
        );
        self::assertSame(191, (int) $this->connection->fetchOne('SELECT character_maximum_length
            FROM information_schema.columns
            WHERE table_schema = :schema
              AND table_name = :table
              AND column_name = :column', [
            'schema' => self::SCHEMA,
            'table' => 'schema_migrations',
            'column' => 'version',
        ]));
        self::assertSame(
            2,
            (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.schema_migrations'),
        );
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*)
            FROM information_schema.referential_constraints
            WHERE constraint_schema = :schema
              AND delete_rule = :delete_rule', [
            'schema' => self::SCHEMA,
            'delete_rule' => 'RESTRICT',
        ]));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*)
            FROM information_schema.table_constraints
            WHERE constraint_schema = :schema
              AND constraint_name IN (
                \'retention_holds_operation_id_fkey\',
                \'retention_purge_audits_operation_id_fkey\'
              )', ['schema' => self::SCHEMA]));
        self::assertSame(10, (int) $this->connection->fetchOne('SELECT count(*)
            FROM pg_indexes
            WHERE schemaname = :schema
              AND indexname IN (
                \'operations_eligible_idx\',
                \'operations_running_lease_idx\',
                \'journal_operation_sequence_idx\',
                \'journal_event_occurred_at_idx\',
                \'outcomes_completed_at_idx\',
                \'dead_letters_moved_at_idx\',
                \'retention_holds_operation_active_idx\',
                \'retention_purge_audits_operation_idx\',
                \'retention_purge_audits_purged_at_idx\',
                \'schema_migrations_pkey\'
              )', ['schema' => self::SCHEMA]));

        $metadata = $runner->dependencyFactory()->getMetadataStorage();
        $metadata->ensureInitialized();
        self::assertCount(2, $metadata->getExecutedMigrations());
    }

    public function testApplyWithNoPendingMigrationSucceedsWithoutChangingVersionRows(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);
        $runner->migrate();

        $result = $runner->migrate();
        $status = $runner->status();

        self::assertSame(0, $result->migrations);
        self::assertSame([], $result->sql);
        self::assertCount(2, $status->appliedVersions);
        self::assertSame([], $status->pendingVersions);
        self::assertSame(
            2,
            (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.schema_migrations'),
        );
    }

    public function testApplicationMigrationRunsAfterFrameworkMigrationsAndSharesMetadata(): void
    {
        $directory = $this->migrationDirectory();
        $version = 'Version20260713010101';
        $secondVersion = 'Version20260713010102';
        $this->writeApplicationMigration(
            $directory,
            $version,
            'CREATE TABLE "' . self::SCHEMA . '"."application_records" (id integer PRIMARY KEY)',
        );
        $this->writeApplicationMigration(
            $directory,
            $secondVersion,
            'ALTER TABLE "' . self::SCHEMA . '"."application_records" ADD COLUMN label text NULL',
        );
        $runner = new DatabaseMigrationRunner(
            $this->connection,
            self::SCHEMA,
            applicationMigrationDirectory: $directory,
        );

        self::assertSame(
            [
                'BlackOps\\Migrations\\PostgreSql\\Version20260712000000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260712010000',
                'App\\Migrations\\' . $version,
                'App\\Migrations\\' . $secondVersion,
            ],
            $runner->status()->pendingVersions,
        );

        $dryRun = $runner->dryRun();
        $sql = implode("\n", $dryRun->sql);
        self::assertSame(4, $dryRun->migrations);
        $frameworkPosition = strpos($sql, 'CREATE TABLE IF NOT EXISTS "' . self::SCHEMA . '"."operations"');
        $applicationPosition = strpos($sql, 'CREATE TABLE "' . self::SCHEMA . '"."application_records"');
        $secondApplicationPosition = strpos($sql, 'ALTER TABLE "' . self::SCHEMA . '"."application_records"');
        self::assertIsInt($frameworkPosition);
        self::assertIsInt($applicationPosition);
        self::assertIsInt($secondApplicationPosition);
        self::assertLessThan($applicationPosition, $frameworkPosition);
        self::assertLessThan($secondApplicationPosition, $applicationPosition);
        self::assertFalse($this->schemaExists(self::SCHEMA));

        $runner = new DatabaseMigrationRunner(
            $this->connection,
            self::SCHEMA,
            applicationMigrationDirectory: $directory,
        );
        $result = $runner->migrate();

        self::assertSame(4, $result->migrations);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*)
                FROM information_schema.tables
                WHERE table_schema = :schema
                  AND table_name = :table', [
            'schema' => self::SCHEMA,
            'table' => 'application_records',
        ]));
        self::assertSame(
            4,
            (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.schema_migrations'),
        );
        self::assertSame([], $runner->status()->pendingVersions);
    }

    public function testRejectsApplicationMigrationParseErrorWhenDatabaseCommandLoadsMigrations(): void
    {
        $directory = $this->migrationDirectory();
        file_put_contents($directory . '/Version20260713010201.php', '<?php this is not valid PHP');
        $runner = new DatabaseMigrationRunner(
            $this->connection,
            self::SCHEMA,
            applicationMigrationDirectory: $directory,
        );

        $this->expectException(ParseError::class);

        $runner->status();
    }

    public function testRejectsApplicationMigrationNamespaceMismatch(): void
    {
        $directory = $this->migrationDirectory();
        $this->writeMigrationSource($directory, 'Version20260713010301', 'Other\\Migrations', 'Version20260713010301');
        $runner = new DatabaseMigrationRunner(
            $this->connection,
            self::SCHEMA,
            applicationMigrationDirectory: $directory,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('matching App\Migrations AbstractMigration class');

        $runner->status();
    }

    public function testRejectsApplicationMigrationWhoseClassDoesNotMatchVersionFile(): void
    {
        $directory = $this->migrationDirectory();
        $this->writeMigrationSource(
            $directory,
            'Version20260713010401',
            'App\\Migrations',
            'UnknownMigration20260713010401',
        );
        $runner = new DatabaseMigrationRunner(
            $this->connection,
            self::SCHEMA,
            applicationMigrationDirectory: $directory,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('matching App\Migrations AbstractMigration class');

        $runner->status();
    }

    public function testRejectsApplicationMigrationClassThatDoesNotExtendAbstractMigration(): void
    {
        $directory = $this->migrationDirectory();
        $version = 'Version20260713010402';
        file_put_contents(
            $directory . '/' . $version . '.php',
            '<?php namespace App\\Migrations; final class ' . $version . ' {}',
        );
        $runner = new DatabaseMigrationRunner(
            $this->connection,
            self::SCHEMA,
            applicationMigrationDirectory: $directory,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('matching App\Migrations AbstractMigration class');

        $runner->status();
    }

    public function testRejectsExistingApplicationMigrationPathThatIsNotDirectory(): void
    {
        $path = $this->migrationDirectory();
        rmdir($path);
        file_put_contents($path, 'not-a-directory');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Application migration path must be a directory.');

        new DatabaseMigrationRunner($this->connection, self::SCHEMA, applicationMigrationDirectory: $path);
    }

    public function testRejectsApplicationMigrationSymlinkWithoutLoadingOutsidePhp(): void
    {
        $link = $this->migrationDirectory();
        rmdir($link);
        $outside = $this->migrationDirectory();
        $class = 'ApplicationMigrationOutsideProbe20260713010501';
        file_put_contents($outside . '/Version20260713010501.php', '<?php final class ' . $class . ' {}');
        symlink($outside, $link);

        try {
            new DatabaseMigrationRunner($this->connection, self::SCHEMA, applicationMigrationDirectory: $link);
            self::fail('Expected application migration symlink rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Application migration directory must not be a symbolic link.', $exception->getMessage());
        }

        self::assertFalse(class_exists($class, autoload: false));
    }

    public function testApplyAdoptsEmptySchemaCreatedByProgrammaticTestHelpers(): void
    {
        foreach (new PostgreSqlDeferredOperationSchema(self::SCHEMA)->statements() as $statement) {
            $this->connection->executeStatement($statement);
        }
        foreach (new PostgreSqlJournalSchema(self::SCHEMA)->statements() as $statement) {
            $this->connection->executeStatement($statement);
        }
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);

        $result = $runner->migrate();

        self::assertSame(2, $result->migrations);
        self::assertSame(
            2,
            (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.schema_migrations'),
        );
        self::assertSame([], $runner->status()->pendingVersions);
        self::assertSame(
            [
                'dead_letters',
                'journal',
                'operations',
                'outcomes',
                'retention_holds',
                'retention_purge_audits',
                'schema_migrations',
            ],
            $this->tables(self::SCHEMA),
        );
    }

    public function testApplyUpgradesLegacyMetadataWithoutLosingAppliedTimestamp(): void
    {
        $this->connection->executeStatement('CREATE SCHEMA ' . self::LEGACY_SCHEMA);
        $this->connection->executeStatement('CREATE TABLE ' . self::LEGACY_SCHEMA . '.schema_migrations (
            version text PRIMARY KEY,
            applied_at timestamptz NOT NULL
        )');
        $this->connection->executeStatement(
            'INSERT INTO ' . self::LEGACY_SCHEMA . '.schema_migrations (version, applied_at)
            VALUES (:version, :applied_at)',
            ['version' => 'LegacyVersion', 'applied_at' => '2026-07-11 15:00:00+00'],
        );
        $runner = new DatabaseMigrationRunner($this->connection, self::LEGACY_SCHEMA);

        $runner->migrate();

        self::assertSame(
            [
                'executed_at' => 'timestamp without time zone',
                'execution_time' => 'integer',
                'version' => 'character varying',
            ],
            $this->metadataColumnTypes(self::LEGACY_SCHEMA),
        );
        self::assertSame('2026-07-11 15:00:00', $this->connection->fetchOne(
            'SELECT executed_at::text
                FROM ' . self::LEGACY_SCHEMA . '.schema_migrations
                WHERE version = :version',
            ['version' => 'LegacyVersion'],
        ));
        self::assertCount(3, $runner->dependencyFactory()->getMetadataStorage()->getExecutedMigrations());
    }

    public function testBaselineDownIsIrreversible(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);
        $migration = $runner
            ->dependencyFactory()
            ->getMigrationPlanCalculator()
            ->getMigrations()
            ->getFirst()
            ->getMigration();

        $this->expectException(IrreversibleMigration::class);

        $migration->down(new Schema());
    }

    public function testOperationReferenceMigrationDownIsIrreversible(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);
        $migration = $runner
            ->dependencyFactory()
            ->getMigrationPlanCalculator()
            ->getMigrations()
            ->getLast()
            ->getMigration();

        $this->expectException(IrreversibleMigration::class);

        $migration->down(new Schema());
    }

    /** @return list<string> */
    private function tables(string $schema): array
    {
        return $this->connection->fetchFirstColumn('SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = :schema
            ORDER BY table_name', ['schema' => $schema]);
    }

    /** @return array<string, string> */
    private function metadataColumnTypes(string $schema): array
    {
        /** @var array<string, string> $types */
        $types = $this->connection->fetchAllKeyValue('SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = :schema
              AND table_name = :table
            ORDER BY column_name', [
            'schema' => $schema,
            'table' => 'schema_migrations',
        ]);

        return $types;
    }

    private function schemaExists(string $schema): bool
    {
        return (bool) $this->connection->fetchOne('SELECT EXISTS (SELECT 1 FROM information_schema.schemata WHERE schema_name = :schema)', [
            'schema' => $schema,
        ]);
    }

    private function migrationDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/blackops-application-migrations-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->migrationDirectories[] = $directory;

        return $directory;
    }

    private function writeApplicationMigration(string $directory, string $version, string $sql): void
    {
        $source = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Migrations;

            use Doctrine\DBAL\Schema\Schema;
            use Doctrine\Migrations\AbstractMigration;

            final class {{ class }} extends AbstractMigration
            {
                public function up(Schema $schema): void
                {
                    $this->addSql('{{ sql }}');
                }

                public function down(Schema $schema): void
                {
                }
            }
            PHP;

        file_put_contents($directory . '/' . $version . '.php', strtr($source, [
            '{{ class }}' => $version,
            '{{ sql }}' => $sql,
        ]));
    }

    private function writeMigrationSource(string $directory, string $version, string $namespace, string $class): void
    {
        $source = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace {{ namespace }};

            use Doctrine\DBAL\Schema\Schema;
            use Doctrine\Migrations\AbstractMigration;

            final class {{ class }} extends AbstractMigration
            {
                public function up(Schema $schema): void
                {
                }

                public function down(Schema $schema): void
                {
                }
            }
            PHP;

        file_put_contents($directory . '/' . $version . '.php', strtr($source, [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $class,
        ]));
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

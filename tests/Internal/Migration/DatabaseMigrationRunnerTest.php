<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Migration;

use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use BlackOps\Internal\Migration\DoctrineMigrationDependencyFactory;
use BlackOps\Migrations\PostgreSql\Version20260829000000;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use InvalidArgumentException;
use ParseError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DatabaseMigrationRunnerTest extends TestCase
{
    private const SCHEMA = 'blackops_database_migration';
    private const LEGACY_SCHEMA = 'blackops_database_migration_legacy';
    private const CURRENT_SCHEMA = 'blackops_database_migration_current';

    private Connection $connection;

    /** @var list<string> */
    private array $migrationDirectories = [];

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::LEGACY_SCHEMA . ' CASCADE');
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::CURRENT_SCHEMA . ' CASCADE');
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

    #[DataProvider('invalidMigrationSchemaNames')]
    public function testDependencyFactoryRejectsInvalidSchemaBeforeAnyDatabaseInteraction(string $schema): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchOne');
        $connection->expects(self::never())->method('executeQuery');
        $connection->expects(self::never())->method('executeStatement');
        $connection->expects(self::never())->method('beginTransaction');

        $this->expectException(InvalidArgumentException::class);

        DoctrineMigrationDependencyFactory::create($connection, $schema);
    }

    #[DataProvider('invalidMigrationSchemaNames')]
    public function testRunnerRejectsInvalidSchemaBeforeAnyDatabaseInteraction(string $schema): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchOne');
        $connection->expects(self::never())->method('executeQuery');
        $connection->expects(self::never())->method('executeStatement');
        $connection->expects(self::never())->method('beginTransaction');

        $this->expectException(InvalidArgumentException::class);

        new DatabaseMigrationRunner($connection, $schema);
    }

    /** @return \Generator<string, array{string}> */
    public static function invalidMigrationSchemaNames(): \Generator
    {
        yield 'empty' => [''];
        yield '64 bytes' => ['a' . str_repeat('b', 63)];
        yield 'same 63 byte prefix alias' => ['a' . str_repeat('b', 62) . 'x'];
        yield 'uppercase' => ['BlackOps'];
        yield 'non ascii' => ['schéma'];
        yield 'quoted alias' => ['"blackops"'];
        yield 'trailing line feed' => ["blackops\n"];
        yield 'leading digit' => ['1blackops'];
        yield 'punctuation' => ['blackops-audit'];
    }

    public function testDependencyFactoryRecognizesSchemaConfiguredMigrations(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);
        $migrations = $runner->dependencyFactory()->getMigrationPlanCalculator()->getMigrations();

        self::assertCount(13, $migrations);
        self::assertSame(
            'BlackOps\\Migrations\\PostgreSql\\Version20260712000000',
            (string) $migrations->getFirst()->getVersion(),
        );
        self::assertSame(
            'BlackOps\\Migrations\\PostgreSql\\Version20260901000000',
            (string) $migrations->getLast()->getVersion(),
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
                'BlackOps\\Migrations\\PostgreSql\\Version20260724000000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260724010000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260724100000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260724110000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260728133000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260803000000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260808000000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260808010000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260808100000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260829000000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260901000000',
            ],
            $status->pendingVersions,
        );
        self::assertFalse($this->schemaExists(self::SCHEMA));
    }

    public function testCatalogNormalizationPreservesQuotedLiteralCaseAndEscapes(): void
    {
        $sql = <<<'SQL'
            CHECK ("CaseSensitive" = 'ROTATION' AND category = 'rotation' AND label = 'it''s' AND label_upper = 'IT''S' AND marker = E'Cc\x01ROTATION')
            SQL;
        $expected = <<<'SQL'
            check ("CaseSensitive" = 'ROTATION' and category = 'rotation' and label = 'it''s' and label_upper = 'IT''S' and marker = e'Cc\x01ROTATION')
            SQL;

        self::assertSame(trim($expected), $this->normalizeCatalogSql($sql, self::SCHEMA));
    }

    public function testDryRunReturnsCompletePlanWithoutCreatingSchemaOrTables(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);

        $result = $runner->dryRun();

        self::assertTrue($result->dryRun);
        self::assertSame(13, $result->migrations);
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
        self::assertSame(13, $result->migrations);
        self::assertSame(
            [
                'audit_outbox_partition_state',
                'audit_outbox_records',
                'audit_partition_state',
                'audit_records',
                'audit_segments',
                'dead_letters',
                'idempotency_records',
                'journal',
                'observer_replay_audits',
                'observer_replay_checkpoints',
                'operations',
                'outbox_dead_letter_retry_audits',
                'outbox_records',
                'outcomes',
                'retention_holds',
                'retention_purge_audits',
                'schedule_occurrences',
                'schedule_states',
                'schema_migrations',
                'storage_protection_rotation_audits',
                'storage_protection_rotation_checkpoints',
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
            13,
            (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.schema_migrations'),
        );
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT count(*)
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
        self::assertSame(28, (int) $this->connection->fetchOne('SELECT count(*)
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
                \'schedule_occurrences_recovery_idx\',
                \'schedule_occurrences_state_idx\',
                \'schema_migrations_pkey\',
                \'audit_segments_pk\',
                \'audit_segments_segment_id_uq\',
                \'audit_partition_state_pk\',
                \'audit_records_pk\',
                \'audit_records_record_id_uq\',
                \'audit_records_partition_sequence_uq\',
                \'audit_records_partition_hash_uq\',
                \'audit_segments_one_open_idx\',
                \'audit_segments_successor_uq\',
                \'audit_records_segment_sequence_idx\',
                \'audit_records_recorded_at_idx\',
                \'audit_records_action_idx\',
                \'audit_records_resource_idx\',
                \'audit_outbox_records_unacknowledged_idx\',
                \'audit_outbox_records_pending_due_idx\',
                \'audit_outbox_records_leased_expiry_idx\'
              )', ['schema' => self::SCHEMA]));
        self::assertSame($this->expectedAuditColumns(), $this->auditColumns(self::SCHEMA));
        $auditConstraints = $this->auditConstraints(self::SCHEMA);
        self::assertSame($this->expectedAuditConstraints(), $auditConstraints);
        self::assertStringNotContainsString('[[:cntrl:]]', implode("\n", array_column(
            $auditConstraints,
            'definition',
        )));
        self::assertSame($this->expectedAuditIndexes(), $this->auditIndexes(self::SCHEMA));

        $metadata = $runner->dependencyFactory()->getMetadataStorage();
        $metadata->ensureInitialized();
        self::assertCount(13, $metadata->getExecutedMigrations());
    }

    public function testApplyWithNoPendingMigrationSucceedsWithoutChangingVersionRows(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);
        $runner->migrate();

        $result = $runner->migrate();
        $status = $runner->status();

        self::assertSame(0, $result->migrations);
        self::assertSame([], $result->sql);
        self::assertCount(13, $status->appliedVersions);
        self::assertSame([], $status->pendingVersions);
        self::assertSame(
            13,
            (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.schema_migrations'),
        );
    }

    public function testExistingMetadataInCurrentSchemaSupportsStatusAndRepeatedMigrate(): void
    {
        $initialSearchPath = $this->connection->fetchOne('SHOW search_path');
        $this->connection->executeStatement('SET search_path TO ' . self::CURRENT_SCHEMA . ', public');

        try {
            $first = new DatabaseMigrationRunner($this->connection, self::CURRENT_SCHEMA);
            self::assertSame(13, $first->migrate()->migrations);
            self::assertSame(self::CURRENT_SCHEMA, $this->connection->fetchOne('SELECT current_schema()'));
            $metadataBefore = $this->metadataRows(self::CURRENT_SCHEMA);
            $configuredSearchPath = $this->connection->fetchOne('SHOW search_path');

            $second = new DatabaseMigrationRunner($this->connection, self::CURRENT_SCHEMA);
            $status = $second->status();
            $result = $second->migrate();

            self::assertCount(13, $status->appliedVersions);
            self::assertSame([], $status->pendingVersions);
            self::assertSame(0, $result->migrations);
            self::assertSame([], $result->sql);
            self::assertSame($metadataBefore, $this->metadataRows(self::CURRENT_SCHEMA));
            self::assertSame($configuredSearchPath, $this->connection->fetchOne('SHOW search_path'));
        } finally {
            $this->connection->executeStatement('RESET search_path');
        }

        self::assertSame($initialSearchPath, $this->connection->fetchOne('SHOW search_path'));
    }

    public function testStatusAndDryRunDoNotCreateSchemaWhenTargetWouldBecomeCurrent(): void
    {
        $initialSearchPath = $this->connection->fetchOne('SHOW search_path');
        $this->connection->executeStatement('SET search_path TO ' . self::CURRENT_SCHEMA . ', public');

        try {
            $runner = new DatabaseMigrationRunner($this->connection, self::CURRENT_SCHEMA);

            self::assertCount(13, $runner->status()->pendingVersions);
            self::assertSame(13, $runner->dryRun()->migrations);
            self::assertFalse($this->schemaExists(self::CURRENT_SCHEMA));
            self::assertSame(self::CURRENT_SCHEMA . ', public', $this->connection->fetchOne('SHOW search_path'));
        } finally {
            $this->connection->executeStatement('RESET search_path');
        }

        self::assertSame($initialSearchPath, $this->connection->fetchOne('SHOW search_path'));
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
                'BlackOps\\Migrations\\PostgreSql\\Version20260724000000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260724010000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260724100000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260724110000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260728133000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260803000000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260808000000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260808010000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260808100000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260829000000',
                'BlackOps\\Migrations\\PostgreSql\\Version20260901000000',
                'App\\Migrations\\' . $version,
                'App\\Migrations\\' . $secondVersion,
            ],
            $runner->status()->pendingVersions,
        );

        $dryRun = $runner->dryRun();
        $sql = implode("\n", $dryRun->sql);
        self::assertSame(15, $dryRun->migrations);
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

        self::assertSame(15, $result->migrations);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*)
                FROM information_schema.tables
                WHERE table_schema = :schema
                  AND table_name = :table', [
            'schema' => self::SCHEMA,
            'table' => 'application_records',
        ]));
        self::assertSame(
            15,
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

        self::assertSame(13, $result->migrations);
        self::assertSame(
            13,
            (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.schema_migrations'),
        );
        self::assertSame([], $runner->status()->pendingVersions);
        self::assertSame(
            [
                'audit_outbox_partition_state',
                'audit_outbox_records',
                'audit_partition_state',
                'audit_records',
                'audit_segments',
                'dead_letters',
                'idempotency_records',
                'journal',
                'observer_replay_audits',
                'observer_replay_checkpoints',
                'operations',
                'outbox_dead_letter_retry_audits',
                'outbox_records',
                'outcomes',
                'retention_holds',
                'retention_purge_audits',
                'schedule_occurrences',
                'schedule_states',
                'schema_migrations',
                'storage_protection_rotation_audits',
                'storage_protection_rotation_checkpoints',
            ],
            $this->tables(self::SCHEMA),
        );
        self::assertSame(
            [
                'selector_kind',
                'selector_operation_id',
                'selector_record_id',
                'selector_from',
                'selector_to',
                'target_names',
            ],
            $this->columns(self::SCHEMA, 'observer_replay_checkpoints', [
                'selector_kind',
                'selector_operation_id',
                'selector_record_id',
                'selector_from',
                'selector_to',
                'target_names',
            ]),
        );
        self::assertSame(['target_names'], $this->columns(self::SCHEMA, 'observer_replay_audits', ['target_names']));
        self::assertGreaterThanOrEqual(
            5,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM information_schema.check_constraints WHERE constraint_schema = :schema AND constraint_name LIKE :pattern',
                ['schema' => self::SCHEMA, 'pattern' => '%replay%'],
            ),
        );
    }

    public function testCanonicalObserverReplayMigrationDownDropsOnlyItsTablesAndIndex(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);
        $available = $runner->dependencyFactory()->getMigrationPlanCalculator()->getMigrations()->getItems();
        $migration = null;
        foreach ($available as $candidate) {
            if (
                $candidate->getMigration()->getDescription()
                === 'Add canonical observer replay checkpoints and audit records.'
            ) {
                $migration = $candidate->getMigration();
                break;
            }
        }
        self::assertNotNull($migration);

        $migration->down(new Schema());
        $sql = array_map(static fn(object $query): string => $query->getStatement(), $migration->getSql());
        self::assertSame(
            [
                'DROP INDEX IF EXISTS "' . self::SCHEMA . '"."observer_replay_audits_checkpoint_idx"',
                'DROP TABLE IF EXISTS "' . self::SCHEMA . '"."observer_replay_audits"',
                'DROP TABLE IF EXISTS "' . self::SCHEMA . '"."observer_replay_checkpoints"',
            ],
            $sql,
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
        self::assertCount(14, $runner->dependencyFactory()->getMetadataStorage()->getExecutedMigrations());
    }

    public function testApplyUpgradesLegacyMetadataWhenTargetSchemaIsCurrent(): void
    {
        $this->connection->executeStatement('CREATE SCHEMA ' . self::CURRENT_SCHEMA);
        $this->connection->executeStatement('CREATE TABLE ' . self::CURRENT_SCHEMA . '.schema_migrations (
            version text PRIMARY KEY,
            applied_at timestamptz NOT NULL
        )');
        $this->connection->executeStatement(
            'INSERT INTO ' . self::CURRENT_SCHEMA . '.schema_migrations (version, applied_at)
            VALUES (:version, :applied_at)',
            ['version' => 'LegacyCurrentVersion', 'applied_at' => '2026-07-11 15:00:00+00'],
        );
        $this->connection->executeStatement('SET search_path TO ' . self::CURRENT_SCHEMA . ', public');

        try {
            $runner = new DatabaseMigrationRunner($this->connection, self::CURRENT_SCHEMA);
            $runner->migrate();

            self::assertSame('2026-07-11 15:00:00', $this->connection->fetchOne(
                'SELECT executed_at::text
                    FROM ' . self::CURRENT_SCHEMA . '.schema_migrations
                    WHERE version = :version',
                ['version' => 'LegacyCurrentVersion'],
            ));
            self::assertCount(14, $runner->dependencyFactory()->getMetadataStorage()->getExecutedMigrations());
            self::assertSame(self::CURRENT_SCHEMA . ', public', $this->connection->fetchOne('SHOW search_path'));
        } finally {
            $this->connection->executeStatement('RESET search_path');
        }
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

    public function testCanonicalAuditMigrationDownIsIrreversibleWithoutPlannedSql(): void
    {
        $migration = new Version20260829000000($this->connection, new NullLogger(), self::SCHEMA);

        try {
            $migration->down(new Schema());
            self::fail('Expected the canonical audit migration to be irreversible.');
        } catch (IrreversibleMigration $exception) {
            self::assertSame('Canonical audit records are retained for integrity evidence.', $exception->getMessage());
        }

        self::assertSame([], $migration->getSql());
    }

    public function testOutboxRelayMigrationDownPreservesOriginalTableContract(): void
    {
        $runner = new DatabaseMigrationRunner($this->connection, self::SCHEMA);
        $available = $runner->dependencyFactory()->getMigrationPlanCalculator()->getMigrations()->getItems();
        $migration = null;
        foreach ($available as $candidate) {
            if (
                $candidate->getMigration()->getDescription()
                === 'Add relay leasing, retry, fencing, and dead-letter audit persistence.'
            ) {
                $migration = $candidate->getMigration();
                break;
            }
        }
        self::assertNotNull($migration);

        $migration->down(new Schema());
        $sql = array_map(static fn(object $query): string => $query->getStatement(), $migration->getSql());
        self::assertSame(
            [
                'UPDATE "'
                    . self::SCHEMA
                    . '"."outbox_records" SET state=\'pending\', state_version=1, relay_id=NULL, lease_expires_at=NULL, leased_at=NULL, next_attempt_at=NULL, failure_fingerprint=NULL, failure_fingerprint_version=NULL, sent_at=NULL, dead_lettered_at=NULL',
                'DROP INDEX IF EXISTS "' . self::SCHEMA . '"."outbox_dead_letter_retry_audits_record_idx"',
                'DROP TABLE IF EXISTS "' . self::SCHEMA . '"."outbox_dead_letter_retry_audits"',
                'DROP INDEX IF EXISTS "' . self::SCHEMA . '"."outbox_records_claim_idx"',
                'DROP INDEX IF EXISTS "' . self::SCHEMA . '"."outbox_records_lease_idx"',
                'ALTER TABLE "' . self::SCHEMA . '"."outbox_records" DROP COLUMN IF EXISTS relay_id',
                'ALTER TABLE "' . self::SCHEMA . '"."outbox_records" DROP COLUMN IF EXISTS lease_expires_at',
                'ALTER TABLE "' . self::SCHEMA . '"."outbox_records" DROP COLUMN IF EXISTS fencing_token',
                'ALTER TABLE "' . self::SCHEMA . '"."outbox_records" DROP COLUMN IF EXISTS attempt_count',
                'ALTER TABLE "' . self::SCHEMA . '"."outbox_records" DROP COLUMN IF EXISTS next_attempt_at',
                'ALTER TABLE "' . self::SCHEMA . '"."outbox_records" DROP COLUMN IF EXISTS failure_fingerprint',
                'ALTER TABLE "' . self::SCHEMA . '"."outbox_records" DROP COLUMN IF EXISTS failure_fingerprint_version',
                'ALTER TABLE "' . self::SCHEMA . '"."outbox_records" DROP COLUMN IF EXISTS leased_at',
                'ALTER TABLE "' . self::SCHEMA . '"."outbox_records" DROP COLUMN IF EXISTS sent_at',
                'ALTER TABLE "' . self::SCHEMA . '"."outbox_records" DROP COLUMN IF EXISTS dead_lettered_at',
                'ALTER TABLE "'
                    . self::SCHEMA
                    . '"."outbox_records" DROP CONSTRAINT IF EXISTS outbox_records_state_check',
                'ALTER TABLE "'
                    . self::SCHEMA
                    . '"."outbox_records" DROP CONSTRAINT IF EXISTS outbox_records_state_version_check',
                'ALTER TABLE "'
                    . self::SCHEMA
                    . '"."outbox_records" ADD CONSTRAINT outbox_records_state_check CHECK (state = \'pending\')',
                'ALTER TABLE "'
                    . self::SCHEMA
                    . '"."outbox_records" ADD CONSTRAINT outbox_records_state_version_check CHECK (state_version = 1)',
            ],
            $sql,
        );
    }

    /**
     * @return list<array{table_name:string,column_name:string,ordinal_position:int,data_type:string,udt_name:string,character_maximum_length:int|null,is_nullable:string,column_default:string|null}>
     */
    private function auditColumns(string $schema): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT table_name, column_name, ordinal_position, data_type, udt_name,
                    character_maximum_length, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = :schema
               AND table_name IN (:tables)
             ORDER BY table_name, ordinal_position',
            ['schema' => $schema, 'tables' => $this->auditTableNames()],
            ['tables' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        return array_map(static fn(array $row): array => [
            'table_name' => (string) $row['table_name'],
            'column_name' => (string) $row['column_name'],
            'ordinal_position' => (int) $row['ordinal_position'],
            'data_type' => (string) $row['data_type'],
            'udt_name' => (string) $row['udt_name'],
            'character_maximum_length' => $row['character_maximum_length'] === null
                ? null
                : (int) $row['character_maximum_length'],
            'is_nullable' => (string) $row['is_nullable'],
            'column_default' => $row['column_default'] === null ? null : (string) $row['column_default'],
        ], $rows);
    }

    /** @return list<array{table_name:string,constraint_name:string,definition:string}> */
    private function auditConstraints(string $schema): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT cls.relname AS table_name,
                    con.conname AS constraint_name,
                    pg_get_constraintdef(con.oid, true) AS definition
             FROM pg_constraint con
             JOIN pg_class cls ON cls.oid = con.conrelid
             JOIN pg_namespace ns ON ns.oid = cls.relnamespace
             WHERE ns.nspname = :schema
               AND cls.relname IN (:tables)
               AND con.contype <> \'n\'
             ORDER BY cls.relname, con.conname',
            ['schema' => $schema, 'tables' => $this->auditTableNames()],
            ['tables' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        return array_map(fn(array $row): array => [
            'table_name' => (string) $row['table_name'],
            'constraint_name' => (string) $row['constraint_name'],
            'definition' => $this->normalizeCatalogSql((string) $row['definition'], $schema),
        ], $rows);
    }

    /** @return list<array{table_name:string,index_name:string,definition:string}> */
    private function auditIndexes(string $schema): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT tablename AS table_name, indexname AS index_name, indexdef AS definition
             FROM pg_indexes
             WHERE schemaname = :schema
               AND tablename IN (:tables)
             ORDER BY tablename, indexname',
            ['schema' => $schema, 'tables' => $this->auditTableNames()],
            ['tables' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        return array_map(fn(array $row): array => [
            'table_name' => (string) $row['table_name'],
            'index_name' => (string) $row['index_name'],
            'definition' => $this->normalizeCatalogIndex((string) $row['definition'], $schema),
        ], $rows);
    }

    /** @return list<string> */
    private function auditTableNames(): array
    {
        return ['audit_partition_state', 'audit_records', 'audit_segments'];
    }

    /**
     * PostgreSQL emits canonical constraint text with only whitespace and the
     * configured schema varying between runs; keep every operator and literal exact.
     */
    private function normalizeCatalogSql(string $sql, string $schema): string
    {
        $normalized = $this->normalizeSqlTokens($sql);

        return str_replace(['"' . $schema . '".', $schema . '.'], ['{schema}.', '{schema}.'], $normalized);
    }

    private function normalizeSqlTokens(string $sql): string
    {
        $normalized = '';
        $quote = null;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            if ($quote !== null) {
                $normalized .= $character;
                if ($character === '\\' && $quote === "'" && ($index + 1) < $length) {
                    $normalized .= $sql[++$index];
                    continue;
                }
                if ($character === $quote) {
                    if (($index + 1) < $length && $sql[$index + 1] === $quote) {
                        $normalized .= $sql[++$index];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
                $normalized .= $character;
                continue;
            }
            if (str_contains(" \t\r\n\f\v", $character)) {
                if ($normalized !== '' && !str_ends_with($normalized, ' ')) {
                    $normalized .= ' ';
                }
                continue;
            }
            $normalized .= strtolower($character);
        }

        return trim($normalized);
    }

    private function expectedCatalogCcCfPredicate(string $column): string
    {
        $characters = '';
        foreach ($this->unicodeCcCfCodePoints() as $codePoint) {
            if ($codePoint === 0) {
                continue;
            }

            $character = 'chr(' . $codePoint . ')';
            $characters = $characters === '' ? $character : '(' . $characters . ' || ' . $character . ')';
        }

        if ($characters === '') {
            throw new \LogicException('The Unicode control character fixture must not be empty.');
        }

        $characters = substr($characters, 1, -1);

        return "translate({$column}::text, {$characters}, ''::text) = {$column}::text";
    }

    /** @return list<int> */
    private function unicodeCcCfCodePoints(): array
    {
        return array_merge(range(0x0000, 0x001F), range(0x007F, 0x009F), [
            0x00AD,
            ...range(0x0600, 0x0605),
            0x061C,
            0x06DD,
            0x070F,
            ...range(0x0890, 0x0891),
            0x08E2,
            0x180E,
            ...range(0x200B, 0x200F),
            ...range(0x202A, 0x202E),
            ...range(0x2060, 0x2064),
            ...range(0x2066, 0x206F),
            0xFEFF,
            ...range(0xFFF9, 0xFFFB),
            0x110BD,
            0x110CD,
            ...range(0x13430, 0x1343F),
            ...range(0x1BCA0, 0x1BCA3),
            ...range(119155, 119162),
            0xE0001,
            ...range(0xE0020, 0xE007F),
        ]);
    }

    /**
     * The catalog adds the default btree marker and deterministic wrappers/casts
     * around these two partial predicates. Their predicate values stay exact.
     */
    private function normalizeCatalogIndex(string $sql, string $schema): string
    {
        $normalized = $this->normalizeCatalogSql($sql, $schema);
        $normalized = str_replace(' using btree', '', $normalized);

        return str_replace(
            [" where (state = 'open'::text)", ' where (successor_segment_id is not null)'],
            [" where state = 'open'", ' where successor_segment_id is not null'],
            $normalized,
        );
    }

    /**
     * @return list<array{table_name:string,column_name:string,ordinal_position:int,data_type:string,udt_name:string,character_maximum_length:int|null,is_nullable:string,column_default:string|null}>
     */
    private function expectedAuditColumns(): array
    {
        $definitions = [
            'audit_partition_state' => [
                ['partition_id',              'character varying',        'varchar',     71,   'NO',  null],
                ['tenant_type',               'character varying',        'varchar',     256,  'YES', null],
                ['tenant_id',                 'character varying',        'varchar',     256,  'YES', null],
                ['active_segment_id',         'uuid',                     'uuid',        null, 'NO',  null],
                ['last_sequence',             'bigint',                   'int8',        null, 'NO',  '0'],
                ['head_record_hash',          'character',                'bpchar',      64,   'YES', null],
                ['last_assigned_recorded_at', 'timestamp with time zone', 'timestamptz', null, 'NO',  null],
                ['state_version',             'bigint',                   'int8',        null, 'NO',  '1'],
            ],
            'audit_records' => [
                ['schema_version',       'smallint',                 'int2',        null, 'NO',  null],
                ['kind',                 'text',                     'text',        null, 'NO',  null],
                ['record_id',            'uuid',                     'uuid',        null, 'NO',  null],
                ['partition_id',         'character varying',        'varchar',     71,   'NO',  null],
                ['tenant_type',          'character varying',        'varchar',     256,  'YES', null],
                ['tenant_id',            'character varying',        'varchar',     256,  'YES', null],
                ['segment_id',           'uuid',                     'uuid',        null, 'NO',  null],
                ['sequence',             'bigint',                   'int8',        null, 'NO',  null],
                ['previous_record_hash', 'character',                'bpchar',      64,   'YES', null],
                ['content_hash',         'character',                'bpchar',      64,   'NO',  null],
                ['record_hash',          'character',                'bpchar',      64,   'NO',  null],
                ['recorded_at',          'timestamp with time zone', 'timestamptz', null, 'NO',  null],
                ['event_category',       'text',                     'text',        null, 'NO',  null],
                ['protected_body',       'bytea',                    'bytea',       null, 'NO',  null],
                ['action_type',          'character varying',        'varchar',     256,  'NO',  null],
                ['resource_type',        'character varying',        'varchar',     256,  'NO',  null],
                ['resource_id',          'character varying',        'varchar',     256,  'NO',  null],
            ],
            'audit_segments' => [
                ['partition_id',            'character varying',        'varchar',     71,   'NO',  null],
                ['segment_id',              'uuid',                     'uuid',        null, 'NO',  null],
                ['state',                   'text',                     'text',        null, 'NO',  null],
                ['first_sequence',          'bigint',                   'int8',        null, 'NO',  null],
                ['final_sequence',          'bigint',                   'int8',        null, 'YES', null],
                ['head_record_hash',        'character',                'bpchar',      64,   'YES', null],
                ['predecessor_record_hash', 'character',                'bpchar',      64,   'YES', null],
                ['opened_at',               'timestamp with time zone', 'timestamptz', null, 'NO',  null],
                ['closed_at',               'timestamp with time zone', 'timestamptz', null, 'YES', null],
                ['successor_segment_id',    'uuid',                     'uuid',        null, 'YES', null],
                ['close_fencing_token',     'character varying',        'varchar',     71,   'YES', null],
                ['state_version',           'bigint',                   'int8',        null, 'NO',  '1'],
            ],
        ];
        $columns = [];
        foreach ($definitions as $table => $tableColumns) {
            foreach ($tableColumns as $position => $definition) {
                [$name, $dataType, $udtName, $length, $nullable, $default] = $definition;
                $columns[] = [
                    'table_name' => $table,
                    'column_name' => $name,
                    'ordinal_position' => $position + 1,
                    'data_type' => $dataType,
                    'udt_name' => $udtName,
                    'character_maximum_length' => $length,
                    'is_nullable' => $nullable,
                    'column_default' => $default,
                ];
            }
        }

        return $columns;
    }

    /** @return list<array{table_name:string,constraint_name:string,definition:string}> */
    private function expectedAuditConstraints(): array
    {
        $definitions = [
            'audit_partition_state' => [
                'audit_partition_state_active_segment_fk' => 'foreign key (partition_id, active_segment_id) references {schema}.audit_segments(partition_id, segment_id) deferrable initially deferred',
                'audit_partition_state_head_check' => "check (last_sequence = 0 and head_record_hash is null or last_sequence > 0 and head_record_hash is not null and head_record_hash ~ '^[0-9a-f]{64}$'::text)",
                'audit_partition_state_partition_check' => "check (partition_id::text ~ '^(system:global|tenant:[0-9a-f]{64})$'::text)",
                'audit_partition_state_pk' => 'primary key (partition_id)',
                'audit_partition_state_sequence_check' => "check (last_sequence >= 0 and last_sequence <= '9007199254740991'::bigint)",
                'audit_partition_state_tenant_bounds_check' =>
                    'check (tenant_type is null and tenant_id is null or octet_length(tenant_type::text) >= 1 and octet_length(tenant_type::text) <= 256 and octet_length(tenant_id::text) >= 1 and octet_length(tenant_id::text) <= 256 and '
                        . $this->expectedCatalogCcCfPredicate('tenant_type')
                        . ' and '
                        . $this->expectedCatalogCcCfPredicate('tenant_id')
                        . ')',
                'audit_partition_state_tenant_pair_check' => "check (partition_id::text = 'system:global'::text and tenant_type is null and tenant_id is null or partition_id::text ~~ 'tenant:%'::text and tenant_type is not null and tenant_id is not null)",
                'audit_partition_state_version_check' => "check (state_version >= 1 and state_version <= '9007199254740991'::bigint)",
            ],
            'audit_records' => [
                'audit_records_action_check' =>
                    'check (octet_length(action_type::text) >= 1 and octet_length(action_type::text) <= 256 and '
                        . $this->expectedCatalogCcCfPredicate('action_type')
                        . ')',
                'audit_records_body_check' => "check (octet_length(protected_body) >= 53 and octet_length(protected_body) <= 1048756 and substring(protected_body from 1 for 4) = decode('424f5044'::text, 'hex'::text))",
                'audit_records_category_check' => "check (event_category = any (array['business'::text, 'security'::text, 'access'::text, 'configuration'::text, 'control'::text, 'retention'::text, 'replay'::text, 'rotation'::text, 'outbox'::text]))",
                'audit_records_hash_check' => "check (content_hash ~ '^[0-9a-f]{64}$'::text and record_hash ~ '^[0-9a-f]{64}$'::text and (previous_record_hash is null or previous_record_hash ~ '^[0-9a-f]{64}$'::text))",
                'audit_records_partition_hash_uq' => 'unique (partition_id, record_hash)',
                'audit_records_partition_sequence_uq' => 'unique (partition_id, sequence)',
                'audit_records_partition_tenant_check' =>
                    "check (partition_id::text ~ '^(system:global|tenant:[0-9a-f]{64})$'::text and (partition_id::text = 'system:global'::text and tenant_type is null and tenant_id is null or partition_id::text ~~ 'tenant:%'::text and tenant_type is not null and tenant_id is not null and octet_length(tenant_type::text) >= 1 and octet_length(tenant_type::text) <= 256 and octet_length(tenant_id::text) >= 1 and octet_length(tenant_id::text) <= 256 and "
                        . $this->expectedCatalogCcCfPredicate('tenant_type')
                        . ' and '
                        . $this->expectedCatalogCcCfPredicate('tenant_id')
                        . '))',
                'audit_records_pk' => 'primary key (partition_id, record_id)',
                'audit_records_previous_check' => 'check (sequence = 1 and previous_record_hash is null or sequence > 1 and previous_record_hash is not null)',
                'audit_records_record_id_check' => "check (record_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'::text)",
                'audit_records_record_id_uq' => 'unique (record_id)',
                'audit_records_resource_check' =>
                    'check (octet_length(resource_type::text) >= 1 and octet_length(resource_type::text) <= 256 and '
                        . $this->expectedCatalogCcCfPredicate('resource_type')
                        . ' and octet_length(resource_id::text) >= 1 and octet_length(resource_id::text) <= 256 and '
                        . $this->expectedCatalogCcCfPredicate('resource_id')
                        . ')',
                'audit_records_schema_kind_check' => "check (schema_version = 1 and kind = 'audit_stored_record'::text)",
                'audit_records_segment_fk' => 'foreign key (partition_id, segment_id) references {schema}.audit_segments(partition_id, segment_id)',
                'audit_records_sequence_check' => "check (sequence >= 1 and sequence <= '9007199254740991'::bigint)",
            ],
            'audit_segments' => [
                'audit_segments_boundary_check' => "check ((first_sequence = 1 and predecessor_record_hash is null or first_sequence > 1 and predecessor_record_hash is not null) and (final_sequence is null and head_record_hash is null or final_sequence is not null and head_record_hash is not null) and (head_record_hash is null or head_record_hash ~ '^[0-9a-f]{64}$'::text) and (predecessor_record_hash is null or predecessor_record_hash ~ '^[0-9a-f]{64}$'::text))",
                'audit_segments_lifecycle_check' => "check (state = 'open'::text and closed_at is null and successor_segment_id is null and close_fencing_token is null or (state = any (array['closed'::text, 'sealed'::text])) and final_sequence is not null and head_record_hash is not null and closed_at is not null and successor_segment_id is not null and close_fencing_token is not null)",
                'audit_segments_pk' => 'primary key (partition_id, segment_id)',
                'audit_segments_segment_id_check' => "check (partition_id::text ~ '^(system:global|tenant:[0-9a-f]{64})$'::text and segment_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'::text)",
                'audit_segments_segment_id_uq' => 'unique (segment_id)',
                'audit_segments_sequence_check' => "check (first_sequence >= 1 and first_sequence <= '9007199254740991'::bigint and (final_sequence is null or final_sequence >= first_sequence and final_sequence <= '9007199254740991'::bigint))",
                'audit_segments_state_check' => "check (state = any (array['open'::text, 'closed'::text, 'sealed'::text]))",
                'audit_segments_state_version_check' => "check (state_version >= 1 and state_version <= '9007199254740991'::bigint)",
                'audit_segments_successor_check' => "check ((successor_segment_id is null or successor_segment_id <> segment_id) and (close_fencing_token is null or close_fencing_token::text ~ '^aft:v1:[0-9a-f]{64}$'::text))",
                'audit_segments_successor_fk' => 'foreign key (partition_id, successor_segment_id) references {schema}.audit_segments(partition_id, segment_id) deferrable initially deferred',
                'audit_segments_time_check' => 'check (closed_at is null or closed_at >= opened_at)',
            ],
        ];
        $constraints = [];
        foreach ($definitions as $table => $tableConstraints) {
            foreach ($tableConstraints as $name => $definition) {
                $constraints[] = [
                    'table_name' => $table,
                    'constraint_name' => $name,
                    'definition' => $definition,
                ];
            }
        }

        return $constraints;
    }

    /** @return list<array{table_name:string,index_name:string,definition:string}> */
    private function expectedAuditIndexes(): array
    {
        return [
            [
                'table_name' => 'audit_partition_state',
                'index_name' => 'audit_partition_state_pk',
                'definition' => 'create unique index audit_partition_state_pk on {schema}.audit_partition_state (partition_id)',
            ],
            [
                'table_name' => 'audit_records',
                'index_name' => 'audit_records_action_idx',
                'definition' => 'create index audit_records_action_idx on {schema}.audit_records (partition_id, action_type, recorded_at, sequence)',
            ],
            [
                'table_name' => 'audit_records',
                'index_name' => 'audit_records_partition_hash_uq',
                'definition' => 'create unique index audit_records_partition_hash_uq on {schema}.audit_records (partition_id, record_hash)',
            ],
            [
                'table_name' => 'audit_records',
                'index_name' => 'audit_records_partition_sequence_uq',
                'definition' => 'create unique index audit_records_partition_sequence_uq on {schema}.audit_records (partition_id, sequence)',
            ],
            [
                'table_name' => 'audit_records',
                'index_name' => 'audit_records_pk',
                'definition' => 'create unique index audit_records_pk on {schema}.audit_records (partition_id, record_id)',
            ],
            [
                'table_name' => 'audit_records',
                'index_name' => 'audit_records_record_id_uq',
                'definition' => 'create unique index audit_records_record_id_uq on {schema}.audit_records (record_id)',
            ],
            [
                'table_name' => 'audit_records',
                'index_name' => 'audit_records_recorded_at_idx',
                'definition' => 'create index audit_records_recorded_at_idx on {schema}.audit_records (partition_id, recorded_at, sequence)',
            ],
            [
                'table_name' => 'audit_records',
                'index_name' => 'audit_records_resource_idx',
                'definition' => 'create index audit_records_resource_idx on {schema}.audit_records (partition_id, resource_type, resource_id, recorded_at, sequence)',
            ],
            [
                'table_name' => 'audit_records',
                'index_name' => 'audit_records_segment_sequence_idx',
                'definition' => 'create index audit_records_segment_sequence_idx on {schema}.audit_records (partition_id, segment_id, sequence)',
            ],
            [
                'table_name' => 'audit_segments',
                'index_name' => 'audit_segments_one_open_idx',
                'definition' => "create unique index audit_segments_one_open_idx on {schema}.audit_segments (partition_id) where state = 'open'",
            ],
            [
                'table_name' => 'audit_segments',
                'index_name' => 'audit_segments_pk',
                'definition' => 'create unique index audit_segments_pk on {schema}.audit_segments (partition_id, segment_id)',
            ],
            [
                'table_name' => 'audit_segments',
                'index_name' => 'audit_segments_segment_id_uq',
                'definition' => 'create unique index audit_segments_segment_id_uq on {schema}.audit_segments (segment_id)',
            ],
            [
                'table_name' => 'audit_segments',
                'index_name' => 'audit_segments_successor_uq',
                'definition' => 'create unique index audit_segments_successor_uq on {schema}.audit_segments (partition_id, successor_segment_id) where successor_segment_id is not null',
            ],
        ];
    }

    /** @return list<string> */
    /** @param list<string>|null $only */
    private function columns(string $schema, string $table, ?array $only = null): array
    {
        $sql = 'SELECT column_name FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table';
        $params = ['schema' => $schema, 'table' => $table];
        $types = [];
        if ($only !== null) {
            $sql .= ' AND column_name IN (:columns)';
            $params['columns'] = $only;
            $types['columns'] = \Doctrine\DBAL\ArrayParameterType::STRING;
        }
        $sql .= ' ORDER BY ordinal_position';
        $rows = $this->connection->fetchFirstColumn($sql, $params, $types);
        return array_values(array_map('strval', $rows));
    }

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

    /** @return list<array{version: string, executed_at: null|string, execution_time: null|int}> */
    private function metadataRows(string $schema): array
    {
        /** @var list<array{version: string, executed_at: null|string, execution_time: null|int}> $rows */
        $rows = $this->connection->fetchAllAssociative('SELECT version, executed_at::text, execution_time
            FROM ' . $schema . '.schema_migrations
            ORDER BY version');

        return $rows;
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

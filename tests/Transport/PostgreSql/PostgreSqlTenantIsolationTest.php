<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

require_once __DIR__ . '/../../../migrations/postgresql/Version20260712000000.php';
require_once __DIR__ . '/../../../migrations/postgresql/Version20260712010000.php';
require_once __DIR__ . '/../../../migrations/postgresql/Version20260724000000.php';
require_once __DIR__ . '/../../../migrations/postgresql/Version20260724010000.php';
require_once __DIR__ . '/../../../migrations/postgresql/Version20260724100000.php';
require_once __DIR__ . '/../../../migrations/postgresql/Version20260724110000.php';
require_once __DIR__ . '/../../../migrations/postgresql/Version20260728133000.php';
require_once __DIR__ . '/../../../migrations/postgresql/Version20260803000000.php';

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionHoldCategory;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPlanItem;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionTarget;
use BlackOps\Core\TenantRef;
use BlackOps\Idempotency\IdempotencyKeyHash;
use BlackOps\Internal\Idempotency\IdempotencyScopeHash;
use BlackOps\Internal\Idempotency\OperationFingerprint;
use BlackOps\Internal\Idempotency\PostgreSqlIdempotencyStore;
use BlackOps\Internal\Scheduling\PostgreSqlScheduledOccurrenceLifecycle;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Migrations\PostgreSql\Version20260803000000;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationLeaseStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationMessageCodec;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRecordCodec;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplaySelector;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayStore;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionHoldStore;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPlanner;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditStore;
use BlackOps\Transport\PostgreSql\PostgreSqlScheduleSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlTenantMetadata;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class PostgreSqlTenantIsolationTest extends TestCase
{
    private const array OLD_MIGRATIONS = [
        \BlackOps\Migrations\PostgreSql\Version20260712000000::class,
        \BlackOps\Migrations\PostgreSql\Version20260712010000::class,
        \BlackOps\Migrations\PostgreSql\Version20260724000000::class,
        \BlackOps\Migrations\PostgreSql\Version20260724010000::class,
        \BlackOps\Migrations\PostgreSql\Version20260724100000::class,
        \BlackOps\Migrations\PostgreSql\Version20260724110000::class,
        \BlackOps\Migrations\PostgreSql\Version20260728133000::class,
    ];

    private const string SCHEMA = 'blackops_p20_016c_evidence';
    private const string OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688e01';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA)->migrate();
        foreach (new PostgreSqlJournalSchema(self::SCHEMA)->statements() as $statement) {
            $this->connection->executeStatement($statement);
        }
        foreach (new PostgreSqlOutboxSchema(self::SCHEMA)->statements() as $statement) {
            $this->connection->executeStatement($statement);
        }
        foreach (new PostgreSqlScheduleSchema(self::SCHEMA)->statements() as $statement) {
            $this->connection->executeStatement($statement);
        }
        new PostgreSqlIdempotencyStore($this->connection, self::SCHEMA)->migrate();
    }

    public function testFreshCurrentAndEmptyLegacyMigrationShapesAndSafeNonEmptyGuard(): void
    {
        $operations = new PostgreSqlDeferredOperationSchema(self::SCHEMA)->operationsTable();
        self::assertSame(
            4,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name IN (\'tenant_type\',\'tenant_id\',\'origin_actor_type\',\'origin_actor_id\')',
                ['schema' => self::SCHEMA, 'table' => 'operations'],
            ),
        );

        $legacy = '"' . self::SCHEMA . '"."legacy_records"';
        $this->connection->executeStatement('CREATE TABLE ' . $legacy . ' (operation_id uuid PRIMARY KEY)');
        foreach (PostgreSqlTenantMetadata::alter($legacy, 'legacy_records') as $statement) {
            $this->connection->executeStatement($statement);
        }
        self::assertSame(
            2,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name IN (\'tenant_type\',\'tenant_id\')',
                ['schema' => self::SCHEMA, 'table' => 'legacy_records'],
            ),
        );

        $this->connection->executeStatement('INSERT INTO ' . $legacy . ' (operation_id) VALUES (:id)', [
            'id' => self::OPERATION,
        ]);
        foreach (PostgreSqlTenantMetadata::alter($legacy, 'legacy_records') as $statement) {
            $this->connection->executeStatement($statement);
        }
        $missing = '"' . self::SCHEMA . '"."legacy_missing"';
        $this->connection->executeStatement('CREATE TABLE ' . $missing . ' (operation_id uuid PRIMARY KEY)');
        $this->connection->executeStatement('INSERT INTO ' . $missing . ' (operation_id) VALUES (:id)', [
            'id' => self::OPERATION,
        ]);
        $before = $this->connection->fetchOne('SELECT count(*) FROM ' . $missing);
        $this->expectException(\Throwable::class);
        try {
            foreach (PostgreSqlTenantMetadata::alter($missing, 'legacy_missing') as $statement) {
                $this->connection->executeStatement($statement);
            }
        } finally {
            self::assertSame($before, $this->connection->fetchOne('SELECT count(*) FROM ' . $missing));
        }
    }

    public function testVersionMigrationAppliesToEmptyOldSchemaAndRejectsNonEmptyOldOperationsSafely(): void
    {
        $emptySchema = self::SCHEMA . '_migration_empty';
        $this->applyOldMigrations($emptySchema);
        $this->applyTenantMigration($emptySchema);

        self::assertSame(
            9,
            (int) $this->connection->fetchOne('SELECT count(*) FROM pg_constraint WHERE connamespace = CAST(:schema AS regnamespace) AND conname LIKE :suffix', [
                'schema' => $emptySchema,
                'suffix' => '%_tenant_pair_check',
            ]),
        );
        self::assertSame(
            3,
            (int) $this->connection->fetchOne('SELECT count(*) FROM pg_constraint WHERE connamespace = CAST(:schema AS regnamespace) AND conname LIKE :suffix', [
                'schema' => $emptySchema,
                'suffix' => '%_origin_actor_pair_check',
            ]),
        );

        $nonEmptySchema = self::SCHEMA . '_migration_blocked';
        $this->applyOldMigrations($nonEmptySchema);
        $operations = '"' . $nonEmptySchema . '"."operations"';
        $this->connection->executeStatement(
            'INSERT INTO '
            . $operations
            . ' (operation_id, operation_type, schema_version, encoded_payload, encoded_context, content_type, encoding, state, state_version, next_sequence, available_at, accepted_at) VALUES (:operation_id, :operation_type, 1, decode(:payload, \'base64\'), decode(:context, \'base64\'), :content_type, :encoding, :state, 1, 1, :available_at, :accepted_at)',
            [
                'operation_id' => self::OPERATION,
                'operation_type' => 'migration.evidence',
                'payload' => 'cGF5bG9hZA==',
                'context' => 'Y29udGV4dA==',
                'content_type' => 'application/json',
                'encoding' => 'json',
                'state' => 'accepted',
                'available_at' => '2026-08-03T00:00:00Z',
                'accepted_at' => '2026-08-03T00:00:00Z',
            ],
        );
        $before = $this->connection->fetchAssociative('SELECT operation_id, operation_type, state, state_version, next_sequence FROM '
        . $operations);
        $encodedBefore = $this->connection->fetchAssociative(
            'SELECT encode(encoded_payload, \'hex\') AS encoded_payload, encode(encoded_context, \'hex\') AS encoded_context FROM '
            . $operations,
        );

        $this->connection->beginTransaction();
        $failure = null;
        try {
            $this->applyTenantMigration($nonEmptySchema);
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            $this->connection->rollBack();
        }

        self::assertInstanceOf(\Throwable::class, $failure);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . $operations));
        self::assertSame(
            $before,
            $this->connection->fetchAssociative('SELECT operation_id, operation_type, state, state_version, next_sequence FROM '
            . $operations),
        );
        self::assertSame($encodedBefore, $this->connection->fetchAssociative(
            'SELECT encode(encoded_payload, \'hex\') AS encoded_payload, encode(encoded_context, \'hex\') AS encoded_context FROM '
            . $operations,
        ));
        self::assertStringNotContainsString('tenant-a', (string) $failure->getMessage());
        self::assertStringNotContainsString('payload', strtolower($failure->getMessage()));
        self::assertSame(
            0,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column',
                ['schema' => $nonEmptySchema, 'table' => 'operations', 'column' => 'tenant_type'],
            ),
        );
    }

    public function testPairConstraintsRejectPartialAndEmptySubjectsAcrossTransportRows(): void
    {
        $operations = new PostgreSqlDeferredOperationSchema(self::SCHEMA)->operationsTable();
        $sender = new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA);
        $sender->enqueue(
            new DeferredOperationMessage(
                OperationId::fromString(self::OPERATION),
                'tenant.evidence',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-08-03T00:00:00Z'),
                new TenantRef('customer', 'tenant-a'),
            ),
        );
        $this->connection->executeStatement(
            'UPDATE '
            . $operations
            . ' SET tenant_type = NULL, tenant_id = NULL, origin_actor_type = NULL, origin_actor_id = NULL WHERE operation_id = :operation_id',
            ['operation_id' => self::OPERATION],
        );
        self::assertSame(
            [null, null, null, null],
            array_values((array) $this->connection->fetchAssociative('SELECT tenant_type, tenant_id, origin_actor_type, origin_actor_id FROM '
            . $operations
            . ' WHERE operation_id = :id', ['id' => self::OPERATION])),
        );
        $this->connection->executeStatement('UPDATE '
        . $operations
        . ' SET tenant_type = :tenant_type, tenant_id = :tenant_id WHERE operation_id = :operation_id', [
            'tenant_type' => 'customer',
            'tenant_id' => 'tenant-a',
            'operation_id' => self::OPERATION,
        ]);
        foreach ([
            ['tenant_type' => 'customer', 'tenant_id' => null],
            ['tenant_type' => null, 'tenant_id' => 'tenant-a'],
            ['tenant_type' => '', 'tenant_id' => 'tenant-a'],
            ['tenant_type' => 'customer', 'tenant_id' => ''],
        ] as $subject) {
            $failed = false;
            try {
                $this->connection->executeStatement('UPDATE '
                . $operations
                . ' SET tenant_type = :tenant_type, tenant_id = :tenant_id WHERE operation_id = :operation_id', [
                    ...$subject,
                    'operation_id' => self::OPERATION,
                ]);
            } catch (\Throwable) {
                $failed = true;
            }
            self::assertTrue($failed);
            self::assertSame('customer', $this->connection->fetchOne('SELECT tenant_type FROM '
            . $operations
            . ' WHERE operation_id = :id', ['id' => self::OPERATION]));
        }
        foreach ([
            ['origin_actor_type' => 'user', 'origin_actor_id' => null],
            ['origin_actor_type' => null, 'origin_actor_id' => 'actor-a'],
            ['origin_actor_type' => '', 'origin_actor_id' => 'actor-a'],
            ['origin_actor_type' => 'user', 'origin_actor_id' => ''],
        ] as $subject) {
            $failed = false;
            try {
                $this->connection->executeStatement('UPDATE '
                . $operations
                . ' SET origin_actor_type = :origin_actor_type, origin_actor_id = :origin_actor_id WHERE operation_id = :operation_id', [
                    ...$subject,
                    'operation_id' => self::OPERATION,
                ]);
            } catch (\Throwable) {
                $failed = true;
            }
            self::assertTrue($failed);
            self::assertSame(
                [null, null],
                array_values((array) $this->connection->fetchAssociative('SELECT origin_actor_type, origin_actor_id FROM '
                . $operations
                . ' WHERE operation_id = :id', ['id' => self::OPERATION])),
            );
        }
    }

    public function testAllOperationOwnedTablesExposeTenantPairsAndOriginActorOnlyRequiredTables(): void
    {
        $tables = [
            'operations',
            'journal',
            'outcomes',
            'idempotency_records',
            'outbox_records',
            'dead_letters',
            'retention_holds',
            'retention_purge_audits',
            'schedule_occurrences',
        ];
        foreach ($tables as $table) {
            self::assertSame(
                1,
                (int) $this->connection->fetchOne('SELECT count(*) FROM pg_constraint WHERE connamespace = CAST(:schema AS regnamespace) AND conname = :constraint', [
                    'schema' => self::SCHEMA,
                    'constraint' => $table . '_tenant_pair_check',
                ]),
                $table,
            );
            $definition = (string) $this->connection->fetchOne(
                'SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE connamespace = CAST(:schema AS regnamespace) AND conname = :constraint',
                [
                    'schema' => self::SCHEMA,
                    'constraint' => $table . '_tenant_pair_check',
                ],
            );
            self::assertStringContainsString('tenant_type IS NULL', $definition, $table);
            self::assertStringContainsString("tenant_type <> ''", $definition, $table);
        }
        foreach (['operations', 'journal', 'outbox_records'] as $table) {
            self::assertSame(
                1,
                (int) $this->connection->fetchOne('SELECT count(*) FROM pg_constraint WHERE connamespace = CAST(:schema AS regnamespace) AND conname = :constraint', [
                    'schema' => self::SCHEMA,
                    'constraint' => $table . '_origin_actor_pair_check',
                ]),
                $table,
            );
            $definition = (string) $this->connection->fetchOne(
                'SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE connamespace = CAST(:schema AS regnamespace) AND conname = :constraint',
                [
                    'schema' => self::SCHEMA,
                    'constraint' => $table . '_origin_actor_pair_check',
                ],
            );
            self::assertStringContainsString('origin_actor_type IS NULL', $definition, $table);
            self::assertStringContainsString("origin_actor_type <> ''", $definition, $table);
        }
    }

    public function testWrongTenantRowsAreFilteredBeforeBlobDecodeAndOutboxCarriesTenant(): void
    {
        $operation = OperationId::fromString(self::OPERATION);
        $sender = new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA);
        $sender->enqueue(
            new DeferredOperationMessage(
                $operation,
                'tenant.evidence',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-08-03T00:00:00Z'),
                new TenantRef('customer', 'tenant-a'),
            ),
        );
        $journal = '"' . self::SCHEMA . '"."journal"';
        $this->connection->executeStatement(
            'INSERT INTO '
            . $journal
            . ' (record_id, operation_id, operation_type, sequence, event, schema_version, occurred_at, tenant_type, tenant_id, encoded_record) VALUES (:record, :operation, :type, 1, :event, 1, :at, :tenant_type, :tenant_id, decode(:blob, \'base64\'))',
            [
                'record' => '019f32ab-2be0-7b38-a0a7-1ab2f9688e02',
                'operation' => self::OPERATION,
                'type' => 'tenant.evidence',
                'event' => 'operation.received',
                'at' => '2026-08-03T00:00:00Z',
                'tenant_type' => 'customer',
                'tenant_id' => 'tenant-b',
                'blob' => 'bm90LWpzb24=',
            ],
        );
        self::assertSame(
            [],
            iterator_to_array(new PostgreSqlCanonicalJournalStore($this->connection, self::SCHEMA)->recordsForTenant(
                $operation,
                new TenantRef('customer', 'tenant-a'),
            )),
        );

        $outbox = new PostgreSqlOutboxStore($this->connection, self::SCHEMA);
        $outbox->insert(
            new \BlackOps\Transport\PostgreSql\PostgreSqlOutboxRecord(
                \BlackOps\Core\Identifier\OutboxRecordId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e03'),
                $operation,
                'tenant.evidence',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-08-03T00:00:00Z'),
                new DateTimeImmutable('2026-08-03T00:00:00Z'),
                'evidence',
                tenant: new TenantRef('customer', 'tenant-a'),
            ),
        );
        $claim = $outbox->claimBatch('evidence-relay', 1, new DateTimeImmutable('2026-08-03T00:00:01Z'), 60)[0];
        self::assertSame('tenant-a', $claim->message->tenant()?->id());

        $outcomes = new PostgreSqlOutcomeStore($this->connection, self::SCHEMA);
        $outcomes->migrate();
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."outcomes" (operation_id, outcome_type, schema_version, encoded_payload, completed_at, tenant_type, tenant_id) VALUES (:operation_id, :type, 1, decode(:blob, \'base64\'), :completed_at, :tenant_type, :tenant_id)',
            [
                'operation_id' => self::OPERATION,
                'type' => 'invalid.outcome',
                'blob' => 'bm90LWpzb24=',
                'completed_at' => '2026-08-03T00:00:00Z',
                'tenant_type' => 'customer',
                'tenant_id' => 'tenant-b',
            ],
        );
        self::assertNull($outcomes->findForTenant($operation, new TenantRef('customer', 'tenant-a')));

        $idempotency = new PostgreSqlIdempotencyStore($this->connection, self::SCHEMA);
        $idempotency->migrate();
        $key = new IdempotencyKeyHash(1, str_repeat('b', 64));
        $fingerprint = new OperationFingerprint(1, str_repeat('c', 64));
        self::assertTrue(
            $idempotency
                ->claim(
                    new IdempotencyScopeHash(2, str_repeat('a', 64)),
                    $key,
                    $fingerprint,
                    $operation,
                    new Deferred(),
                    new DateTimeImmutable('2026-08-03T00:00:00Z'),
                    new DateTimeImmutable('2026-08-04T00:00:00Z'),
                    new TenantRef('customer', 'tenant-a'),
                )
                ->claimed(),
        );
        self::assertTrue(
            $idempotency
                ->claim(
                    new IdempotencyScopeHash(2, str_repeat('d', 64)),
                    $key,
                    $fingerprint,
                    OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e04'),
                    new Deferred(),
                    new DateTimeImmutable('2026-08-03T00:00:00Z'),
                    new DateTimeImmutable('2026-08-04T00:00:00Z'),
                    new TenantRef('customer', 'tenant-b'),
                )
                ->claimed(),
        );
    }

    public function testRetentionAndScheduleRowsKeepTenantOrExplicitGlobalNull(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."schedule_states" (schedule_name, operation_type, cursor_at, created_at, updated_at) VALUES (\'evidence.daily\', \'evidence.operation\', :cursor, :created, :updated)',
            [
                'cursor' => '2026-08-03T00:00:00Z',
                'created' => '2026-08-03T00:00:00Z',
                'updated' => '2026-08-03T00:00:00Z',
            ],
        );
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."schedule_occurrences" (schedule_name, scheduled_at, evaluated_at, state, operation_id, tenant_type, tenant_id, created_at, updated_at) VALUES (:name, :scheduled, :evaluated, :state, :operation_id, :tenant_type, :tenant_id, :created, :updated)',
            [
                'name' => 'evidence.daily',
                'scheduled' => '2026-08-03T00:00:00Z',
                'evaluated' => '2026-08-03T00:00:00Z',
                'state' => 'skipped_misfire',
                'operation_id' => null,
                'tenant_type' => null,
                'tenant_id' => null,
                'created' => '2026-08-03T00:00:00Z',
                'updated' => '2026-08-03T00:00:00Z',
            ],
        );
        self::assertSame(
            ['', ''],
            array_values((array) $this->connection->fetchAssociative(
                'SELECT coalesce(tenant_type, \'\') AS tenant_type, coalesce(tenant_id, \'\') AS tenant_id FROM "'
                . self::SCHEMA
                . '"."schedule_occurrences"',
            )),
        );

        $sender = new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA);
        $sender->enqueue(
            new DeferredOperationMessage(
                OperationId::fromString(self::OPERATION),
                'tenant.evidence',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-08-03T00:00:00Z'),
                new TenantRef('customer', 'tenant-a'),
            ),
        );
        $hold = new PostgreSqlRetentionHoldStore($this->connection, self::SCHEMA)->place(
            OperationId::fromString(self::OPERATION),
            RetentionHoldCategory::Audit,
            'evidence',
            RetentionActorRef::fromString('auditor'),
            new DateTimeImmutable('2026-08-03T00:00:00Z'),
        );
        self::assertSame('tenant-a', $this->connection->fetchOne('SELECT tenant_id FROM "'
        . self::SCHEMA
        . '"."retention_holds" WHERE hold_id = :hold_id', ['hold_id' => $hold->id()->toString()]));

        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."schedule_occurrences" (schedule_name, scheduled_at, evaluated_at, state, operation_id, tenant_type, tenant_id, created_at, updated_at) VALUES (\'evidence.daily\', :scheduled, :evaluated, \'claimed\', :operation_id, \'customer\', \'tenant-a\', :created, :updated)',
            [
                'scheduled' => '2026-08-03T00:01:00Z',
                'evaluated' => '2026-08-03T00:01:00Z',
                'operation_id' => self::OPERATION,
                'created' => '2026-08-03T00:01:00Z',
                'updated' => '2026-08-03T00:01:00Z',
            ],
        );
        new PostgreSqlScheduledOccurrenceLifecycle($this->connection, self::SCHEMA)->transition(
            OperationId::fromString(self::OPERATION),
            'claimed',
            'accepted',
            null,
            new DateTimeImmutable('2026-08-03T00:02:00Z'),
            new TenantRef('customer', 'tenant-a'),
        );
        self::assertSame('tenant-a', $this->connection->fetchOne('SELECT tenant_id FROM "'
        . self::SCHEMA
        . '"."schedule_occurrences" WHERE operation_id = :operation_id', ['operation_id' => self::OPERATION]));
    }

    public function testRetentionPlanningDeletionAndAuditStayTenantScoped(): void
    {
        $operationA = OperationId::fromString(self::OPERATION);
        $operationB = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e07');
        $tenantA = new TenantRef('customer', 'tenant-a');
        $tenantB = new TenantRef('customer', 'tenant-b');
        $this->seedTenantJournal($operationA, $tenantA, '019f32ab-2be0-7b38-a0a7-1ab2f9688e08');
        $this->seedTenantJournal($operationB, $tenantB, '019f32ab-2be0-7b38-a0a7-1ab2f9688e09');

        $now = new DateTimeImmutable('2026-08-03T00:00:00Z');
        $policy = new RetentionPolicy(
            RetentionPeriod::days(365),
            RetentionPeriod::days(1),
            RetentionPeriod::days(365),
            RetentionPeriod::days(365),
            RetentionPeriod::days(365),
        );
        $plan = new PostgreSqlRetentionPlanner($this->connection, self::SCHEMA)->plan($policy, $now);
        $journalItems = $plan->forTarget(RetentionTarget::Journal);
        self::assertCount(2, $journalItems);
        $tenants = [];
        foreach ($journalItems as $item) {
            $tenants[$item->operationId()->toString()] = $item->tenant()?->id();
        }
        self::assertSame('tenant-a', $tenants[$operationA->toString()]);
        self::assertSame('tenant-b', $tenants[$operationB->toString()]);

        $audit = new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA);
        $delete = new PostgreSqlJournalRetentionDeleteService($this->connection, $audit, self::SCHEMA);
        $wrong = new RetentionPlan([
            new RetentionPlanItem(
                $operationA,
                RetentionTarget::Journal,
                $journalItems[0]->basisAt(),
                $journalItems[0]->eligibleAt(),
                $tenantB,
            ),
        ]);
        self::assertSame(0, $delete->delete(
            $wrong,
            RetentionPolicyRef::fromString('retention-v1'),
            RetentionActorRef::fromString('system'),
        ));
        self::assertSame(1, $this->journalCountForOperation($operationA));

        $correct = new RetentionPlan([$journalItems[0], $journalItems[1]]);
        self::assertSame(2, $delete->delete(
            $correct,
            RetentionPolicyRef::fromString('retention-v1'),
            RetentionActorRef::fromString('system'),
        ));
        $audits = $this->connection->fetchAllAssociative(
            'SELECT operation_id::text AS operation_id, tenant_type, tenant_id FROM "'
            . self::SCHEMA
            . '"."retention_purge_audits" ORDER BY operation_id',
        );
        self::assertSame(
            [
                ['operation_id' => $operationA->toString(), 'tenant_type' => 'customer', 'tenant_id' => 'tenant-a'],
                ['operation_id' => $operationB->toString(), 'tenant_type' => 'customer', 'tenant_id' => 'tenant-b'],
            ],
            $audits,
        );
    }

    public function testObserverReplayTimeSelectionCarriesTenantAndRejectsTamperedClearSubject(): void
    {
        $operationA = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e10');
        $operationB = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e11');
        $tenantA = new TenantRef('customer', 'tenant-a');
        $tenantB = new TenantRef('customer', 'tenant-b');
        $this->insertReplayJournal(
            $this->journalRecord($operationA, $tenantA, '019f32ab-2be0-7b38-a0a7-1ab2f9688e12', '2026-08-02T00:00:00Z'),
            $tenantA,
        );
        $this->insertReplayJournal(
            $this->journalRecord($operationB, $tenantB, '019f32ab-2be0-7b38-a0a7-1ab2f9688e13', '2026-08-02T00:01:00Z'),
            $tenantB,
        );

        $records = new PostgreSqlObserverReplayStore($this->connection, self::SCHEMA)->select(
            PostgreSqlObserverReplaySelector::time(
                new DateTimeImmutable('2026-08-01T00:00:00Z'),
                new DateTimeImmutable('2026-08-03T00:00:00Z'),
            ),
            10,
            null,
        )['records'];
        self::assertSame(
            ['tenant-a', 'tenant-b'],
            array_map(static fn(JournalRecord $record): ?string => $record->operation->tenant?->id(), $records),
        );

        $tampered = $this->journalRecord(
            OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e14'),
            $tenantB,
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e15',
            '2026-08-02T00:02:00Z',
        );
        $this->insertReplayJournal($tampered, $tenantA);
        $this->expectException(RuntimeException::class);
        new PostgreSqlObserverReplayStore($this->connection, self::SCHEMA)->select(
            PostgreSqlObserverReplaySelector::time(
                new DateTimeImmutable('2026-08-01T00:00:00Z'),
                new DateTimeImmutable('2026-08-03T00:00:00Z'),
            ),
            10,
            null,
        );
    }

    public function testSameIdDifferentTenantSenderFailsSafely(): void
    {
        $operations = new PostgreSqlDeferredOperationSchema(self::SCHEMA)->operationsTable();
        $sender = new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA);
        $sender->enqueue(
            new DeferredOperationMessage(
                OperationId::fromString(self::OPERATION),
                'concurrent.evidence',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-08-03T00:00:00Z'),
                new TenantRef('customer', 'tenant-a'),
            ),
        );
        try {
            $sender->enqueue(
                new DeferredOperationMessage(
                    OperationId::fromString(self::OPERATION),
                    'concurrent.evidence',
                    1,
                    '{}',
                    '{}',
                    new DateTimeImmutable('2026-08-03T00:00:00Z'),
                    new TenantRef('customer', 'tenant-b'),
                ),
            );
            self::fail('A duplicate operation ID with a different tenant must fail.');
        } catch (DeferredTransportException $exception) {
            self::assertStringNotContainsString('tenant-a', $exception->getMessage());
            self::assertStringNotContainsString('tenant-b', $exception->getMessage());
        }
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT count(*) FROM ' . $operations . ' WHERE operation_id = :id', [
                'id' => self::OPERATION,
            ]),
        );
        self::assertSame(
            [
                'tenant_type' => 'customer',
                'tenant_id' => 'tenant-a',
                'origin_actor_type' => null,
                'origin_actor_id' => null,
            ],
            $this->connection->fetchAssociative('SELECT tenant_type, tenant_id, origin_actor_type, origin_actor_id FROM '
            . $operations
            . ' WHERE operation_id = :id', ['id' => self::OPERATION]),
        );
    }

    public function testOpenTransactionsSkipLockedClaimsPreserveTenantCarrier(): void
    {
        $sender = new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA);
        $sender->enqueue(
            new DeferredOperationMessage(
                OperationId::fromString(self::OPERATION),
                'lease.evidence',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-08-03T00:00:00Z'),
                new TenantRef('customer', 'tenant-a'),
            ),
        );
        $b = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e06');
        $sender->enqueue(
            new DeferredOperationMessage(
                $b,
                'lease.evidence',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-08-03T00:00:00Z'),
                new TenantRef('customer', 'tenant-b'),
            ),
        );
        $other = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $schema = new PostgreSqlDeferredOperationSchema(self::SCHEMA);
        $left = new PostgreSqlDeferredOperationLeaseStore($this->connection, $schema, 'left');
        $right = new PostgreSqlDeferredOperationLeaseStore($other, $schema, 'right');
        $at = new DateTimeImmutable('2026-08-03T00:00:01Z');
        $this->connection->beginTransaction();
        $other->beginTransaction();
        try {
            $rowA = $left->selectEligible($at);
            $rowB = $right->selectEligible($at);
            self::assertNotNull($rowA);
            self::assertNotNull($rowB);
            $codec = new PostgreSqlDeferredOperationMessageCodec();
            self::assertSame('tenant-a', $codec->fromRow($rowA)->tenant()?->id());
            self::assertSame('tenant-b', $codec->fromRow($rowB)->tenant()?->id());
        } finally {
            $this->connection->rollBack();
            $other->rollBack();
            $other->close();
        }
    }

    private function seedTenantJournal(OperationId $operation, TenantRef $tenant, string $recordId): void
    {
        new PostgreSqlDeferredOperationSender($this->connection, self::SCHEMA)->enqueue(
            new DeferredOperationMessage(
                $operation,
                'retention.evidence',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-07-01T00:00:00Z'),
                $tenant,
            ),
        );
        $this->insertReplayJournal(
            $this->journalRecord($operation, $tenant, $recordId, '2026-07-01T00:00:00Z'),
            $tenant,
        );
    }

    private function journalCountForOperation(OperationId $operation): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM "'
        . self::SCHEMA
        . '"."journal" WHERE operation_id = :operation_id', ['operation_id' => $operation->toString()]);
    }

    private function journalRecord(
        OperationId $operation,
        TenantRef $tenant,
        string $recordId,
        string $occurredAt,
    ): JournalRecord {
        return new JournalRecord(
            \BlackOps\Core\Identifier\JournalRecordId::fromString($recordId),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable($occurredAt),
            1,
            new JournalOperation(
                $operation,
                'retention.evidence',
                1,
                'inline',
                CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e16'),
                tenant: $tenant,
            ),
            null,
            new EmptyJournalData(),
        );
    }

    private function insertReplayJournal(JournalRecord $record, TenantRef $clearTenant): void
    {
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."journal" (record_id, operation_id, operation_type, sequence, event, schema_version, occurred_at, tenant_type, tenant_id, encoded_record) VALUES (:record_id, :operation_id, :operation_type, :sequence, :event, :schema_version, :occurred_at, :tenant_type, :tenant_id, convert_to(:encoded, \'UTF8\'))',
            [
                'record_id' => $record->recordId->toString(),
                'operation_id' => $record->operation->id->toString(),
                'operation_type' => $record->operation->type,
                'sequence' => $record->sequence,
                'event' => $record->event->value,
                'schema_version' => $record->schemaVersion,
                'occurred_at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                'tenant_type' => $clearTenant->type(),
                'tenant_id' => $clearTenant->id(),
                'encoded' => new PostgreSqlJournalRecordCodec()->encode($record),
            ],
        );
    }

    private function applyOldMigrations(string $schema): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS "' . $schema . '" CASCADE');
        $this->connection->executeStatement('CREATE SCHEMA "' . $schema . '"');
        foreach (self::OLD_MIGRATIONS as $migrationClass) {
            $this->applyMigration($migrationClass, $schema);
        }
    }

    private function applyTenantMigration(string $schema): void
    {
        $this->applyMigration(Version20260803000000::class, $schema);
    }

    /** @param class-string<object> $migrationClass */
    private function applyMigration(string $migrationClass, string $schema): void
    {
        $migration = new $migrationClass($this->connection, new NullLogger(), $schema);
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $this->connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }
}

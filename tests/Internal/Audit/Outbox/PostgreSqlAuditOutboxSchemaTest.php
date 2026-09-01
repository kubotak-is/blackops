<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Outbox;

use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Throwable;

final class PostgreSqlAuditOutboxSchemaTest extends TestCase
{
    private const string REAL_SCHEMA = 'blackops_audit_outbox_schema_contract';

    private const string SYSTEM_PARTITION = 'system:global';

    private const string TENANT_PARTITION = 'tenant:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string RECORD_ID = '01941f29-7c00-7000-8000-000000000001';

    private const string SECOND_RECORD_ID = '01941f29-7c00-7000-8000-000000000002';

    private const string LEASE_TOKEN = '01941f29-7c00-7000-8000-000000000003';

    private const string HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string SECOND_HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testSchemaNameIsRejectedBeforeAnySqlAndUsesExactQualifiedNames(): void
    {
        foreach (['BlackOps', 'a' . str_repeat('b', 63), 'é', '"blackops"', "blackops\n"] as $invalid) {
            try {
                new PostgreSqlMigrationSchema($invalid);
            } catch (InvalidArgumentException $exception) {
                self::assertSame('PostgreSQL schema name must be a safe identifier.', $exception->getMessage());
                continue;
            }

            self::fail('Invalid schema name was accepted: ' . json_encode($invalid, JSON_THROW_ON_ERROR));
        }

        $schema = $this->schema();
        self::assertSame(self::REAL_SCHEMA, $schema->schemaName());
        self::assertSame('"' . self::REAL_SCHEMA . '"."audit_outbox_partition_state"', $schema->partitionStateTable());
        self::assertSame('"' . self::REAL_SCHEMA . '"."audit_outbox_records"', $schema->recordsTable());
    }

    public function testStaticStatementsHaveExactTablesColumnsConstraintsAndIndexes(): void
    {
        $schema = $this->schema();
        $statements = $schema->statements();

        self::assertCount(5, $statements);
        self::assertSame(
            ['audit_outbox_partition_state', 'audit_outbox_records'],
            array_map($this->tableName(...), array_slice($statements, 0, 2)),
        );
        self::assertSame(
            [
                'create index audit_outbox_records_unacknowledged_idx on {schema}."audit_outbox_records" (partition_id, ingress_ordinal) where state in (\'pending\', \'leased\')',
                'create index audit_outbox_records_pending_due_idx on {schema}."audit_outbox_records" (available_at, partition_id, ingress_ordinal) where state = \'pending\'',
                'create index audit_outbox_records_leased_expiry_idx on {schema}."audit_outbox_records" (lease_expires_at, partition_id, ingress_ordinal) where state = \'leased\'',
            ],
            array_map($this->normalizeSql(...), array_slice($statements, 2)),
        );

        self::assertSame(
            [
                ['name' => 'partition_id', 'definition' => 'varchar(71) not null'],
                ['name' => 'tenant_type', 'definition' => 'varchar(256) null'],
                ['name' => 'tenant_id', 'definition' => 'varchar(256) null'],
                ['name' => 'last_ingress_ordinal', 'definition' => 'bigint not null'],
                ['name' => 'last_assigned_recorded_at', 'definition' => 'timestamptz not null'],
                ['name' => 'state_version', 'definition' => 'bigint not null default 1'],
            ],
            $this->columns($statements[0]),
        );
        self::assertSame(
            [
                ['name' => 'schema_version', 'definition' => 'smallint not null'],
                ['name' => 'kind', 'definition' => 'text not null'],
                ['name' => 'record_id', 'definition' => 'uuid not null'],
                ['name' => 'partition_id', 'definition' => 'varchar(71) not null'],
                ['name' => 'tenant_type', 'definition' => 'varchar(256) null'],
                ['name' => 'tenant_id', 'definition' => 'varchar(256) null'],
                ['name' => 'ingress_ordinal', 'definition' => 'bigint not null'],
                ['name' => 'idempotency_key_hash', 'definition' => 'char(64) not null'],
                ['name' => 'content_hash', 'definition' => 'char(64) not null'],
                ['name' => 'state', 'definition' => 'text not null'],
                ['name' => 'attempt', 'definition' => 'smallint not null'],
                ['name' => 'available_at', 'definition' => 'timestamptz null'],
                ['name' => 'lease_fencing_token', 'definition' => 'uuid null'],
                ['name' => 'lease_expires_at', 'definition' => 'timestamptz null'],
                ['name' => 'protected_body', 'definition' => 'bytea not null'],
                ['name' => 'version', 'definition' => 'bigint not null'],
            ],
            $this->columns($statements[1]),
        );

        $expectedStateConstraints = [
            'audit_outbox_partition_state_pk',
            'audit_outbox_partition_state_partition_check',
            'audit_outbox_partition_state_tenant_pair_check',
            'audit_outbox_partition_state_tenant_bounds_check',
            'audit_outbox_partition_state_ordinal_check',
            'audit_outbox_partition_state_version_check',
        ];
        $expectedRecordConstraints = [
            'audit_outbox_records_pk',
            'audit_outbox_records_record_id_uq',
            'audit_outbox_records_idempotency_uq',
            'audit_outbox_records_partition_fk',
            'audit_outbox_records_schema_kind_check',
            'audit_outbox_records_record_id_check',
            'audit_outbox_records_partition_tenant_check',
            'audit_outbox_records_ordinal_check',
            'audit_outbox_records_hash_check',
            'audit_outbox_records_state_check',
            'audit_outbox_records_lease_token_check',
            'audit_outbox_records_body_check',
            'audit_outbox_records_version_check',
        ];
        self::assertSame($expectedStateConstraints, array_keys($this->constraints($statements[0])));
        self::assertSame($expectedRecordConstraints, array_keys($this->constraints($statements[1])));

        $ddl = $this->normalizeSql(implode("\n", $statements));
        foreach ([
            'audit_outbox_partition_state_partition_check' => "check (partition_id ~ '^(system:global|tenant:[0-9a-f]{64})$')",
            'audit_outbox_partition_state_ordinal_check' => 'last_ingress_ordinal between 1 and 9007199254740991',
            'audit_outbox_partition_state_version_check' => 'state_version = last_ingress_ordinal',
            'audit_outbox_records_partition_fk' => 'on update no action on delete restrict not deferrable',
            'audit_outbox_records_schema_kind_check' => "schema_version = 1 and kind = 'audit_outbox_record'",
            'audit_outbox_records_record_id_check' => "record_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'",
            'audit_outbox_records_ordinal_check' => 'ingress_ordinal between 1 and 9007199254740991',
            'audit_outbox_records_version_check' => 'version between 1 and 9007199254740991',
        ] as $constraint => $fragment) {
            self::assertStringContainsString('constraint ' . $constraint, $ddl);
            self::assertStringContainsString($fragment, $ddl);
        }
        self::assertStringContainsString('references {schema}."audit_outbox_partition_state" (partition_id)', $ddl);
        self::assertStringContainsString("state = 'pending' and attempt between 0 and 31", $ddl);
        self::assertStringContainsString("state = 'leased' and attempt between 1 and 32", $ddl);
        self::assertStringContainsString("state in ('relayed', 'poisoned') and attempt between 1 and 32", $ddl);
    }

    public function testStaticControlInventoryExcludesNulAndLineSeparators(): void
    {
        $ddl = implode("\n", $this->schema()->statements());
        $acceptedDdl = implode("\n", new PostgreSqlAuditSchema('blackops_audit_accepted')->statements());

        self::assertCount(235, $this->unicodeCcCfCodePoints());
        self::assertSame(
            $this->controlInventoryExpression($acceptedDdl),
            $this->controlInventoryExpression($ddl),
            'the outbox control inventory must remain byte-identical to the canonical audit schema',
        );
        self::assertSame(
            $this->controlInventoryExpressions($acceptedDdl),
            $this->controlInventoryExpressions($ddl),
            'every outbox Cc/Cf predicate must retain the complete canonical inventory',
        );
        self::assertCount(4, $this->controlInventoryExpressions($ddl));
        self::assertStringNotContainsString('[[:cntrl:]]', $ddl);
        self::assertStringNotContainsString('chr(0)', $ddl);
        self::assertStringNotContainsString('chr(8232)', $ddl);
        self::assertStringNotContainsString('chr(8233)', $ddl);
        self::assertStringContainsString('chr(119162)', $ddl);
        self::assertStringNotContainsString('chr(119163)', $ddl);
        self::assertStringNotContainsString('chr(119163)', $acceptedDdl);
        $extra = str_replace('chr(119162)', 'chr(119162) || chr(119163)', $ddl, $replacementCount);
        self::assertGreaterThan(0, $replacementCount);
        self::assertNotSame(
            $this->controlInventoryExpressions($acceptedDdl),
            $this->controlInventoryExpressions($extra),
            'an inventory code point added beyond Accepted B2 must fail complete parity',
        );
        foreach ($this->unicodeCcCfCodePoints() as $codePoint) {
            if ($codePoint === 0) {
                continue;
            }
            self::assertStringContainsString('chr(' . $codePoint . ')', $ddl);
        }
        foreach (['tenant_type', 'tenant_id'] as $column) {
            self::assertStringContainsString('translate(' . $column . ', ', $ddl);
        }
    }

    public function testRealCatalogHasOnlyTheExactTwoTablesAndContract(): void
    {
        $connection = $this->realConnection();
        $schema = $this->schema();

        try {
            $this->createRealSchema($connection, $schema);

            self::assertSame(
                ['audit_outbox_partition_state', 'audit_outbox_records'],
                $connection->fetchFirstColumn(
                    'SELECT table_name FROM information_schema.tables WHERE table_schema = :schema AND table_type = \'BASE TABLE\' ORDER BY table_name',
                    ['schema' => self::REAL_SCHEMA],
                    ['schema' => ParameterType::STRING],
                ),
            );

            $rows = $connection->fetchAllAssociative(
                'SELECT table_name, column_name, data_type, udt_name, character_maximum_length, is_nullable, column_default '
                . 'FROM information_schema.columns WHERE table_schema = :schema ORDER BY table_name, ordinal_position',
                ['schema' => self::REAL_SCHEMA],
                ['schema' => ParameterType::STRING],
            );
            $actualColumns = array_map(static fn(array $row): array => [
                $row['table_name'],
                $row['column_name'],
                $row['data_type'],
                $row['udt_name'],
                $row['character_maximum_length'] === null ? null : (int) $row['character_maximum_length'],
                $row['is_nullable'],
                $row['column_default'] === null ? false : str_contains((string) $row['column_default'], '1'),
            ], $rows);
            self::assertSame(
                [
                    ['audit_outbox_partition_state', 'partition_id', 'character varying', 'varchar', 71, 'NO', false],
                    ['audit_outbox_partition_state', 'tenant_type', 'character varying', 'varchar', 256, 'YES', false],
                    ['audit_outbox_partition_state', 'tenant_id', 'character varying', 'varchar', 256, 'YES', false],
                    ['audit_outbox_partition_state', 'last_ingress_ordinal', 'bigint', 'int8', null, 'NO', false],
                    [
                        'audit_outbox_partition_state',
                        'last_assigned_recorded_at',
                        'timestamp with time zone',
                        'timestamptz',
                        null,
                        'NO',
                        false,
                    ],
                    ['audit_outbox_partition_state', 'state_version', 'bigint', 'int8', null, 'NO', true],
                    ['audit_outbox_records', 'schema_version', 'smallint', 'int2', null, 'NO', false],
                    ['audit_outbox_records', 'kind', 'text', 'text', null, 'NO', false],
                    ['audit_outbox_records', 'record_id', 'uuid', 'uuid', null, 'NO', false],
                    ['audit_outbox_records', 'partition_id', 'character varying', 'varchar', 71, 'NO', false],
                    ['audit_outbox_records', 'tenant_type', 'character varying', 'varchar', 256, 'YES', false],
                    ['audit_outbox_records', 'tenant_id', 'character varying', 'varchar', 256, 'YES', false],
                    ['audit_outbox_records', 'ingress_ordinal', 'bigint', 'int8', null, 'NO', false],
                    ['audit_outbox_records', 'idempotency_key_hash', 'character', 'bpchar', 64, 'NO', false],
                    ['audit_outbox_records', 'content_hash', 'character', 'bpchar', 64, 'NO', false],
                    ['audit_outbox_records', 'state', 'text', 'text', null, 'NO', false],
                    ['audit_outbox_records', 'attempt', 'smallint', 'int2', null, 'NO', false],
                    [
                        'audit_outbox_records',
                        'available_at',
                        'timestamp with time zone',
                        'timestamptz',
                        null,
                        'YES',
                        false,
                    ],
                    ['audit_outbox_records', 'lease_fencing_token', 'uuid', 'uuid', null, 'YES', false],
                    [
                        'audit_outbox_records',
                        'lease_expires_at',
                        'timestamp with time zone',
                        'timestamptz',
                        null,
                        'YES',
                        false,
                    ],
                    ['audit_outbox_records', 'protected_body', 'bytea', 'bytea', null, 'NO', false],
                    ['audit_outbox_records', 'version', 'bigint', 'int8', null, 'NO', false],
                ],
                $actualColumns,
            );

            $constraintRows = $connection->fetchAllAssociative(
                'SELECT c.conname, c.contype, pg_get_constraintdef(c.oid, true) AS definition '
                . 'FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace '
                . 'JOIN pg_class r ON r.oid = c.conrelid '
                . 'WHERE n.nspname = :schema AND r.relname IN (\'audit_outbox_partition_state\', \'audit_outbox_records\') AND c.contype <> \'n\' ORDER BY c.conname',
                ['schema' => self::REAL_SCHEMA],
                ['schema' => ParameterType::STRING],
            );
            $constraintTypes = [];
            foreach ($constraintRows as $row) {
                $constraintTypes[$row['conname']] = $row['contype'];
                if ($row['conname'] === 'audit_outbox_records_partition_fk') {
                    self::assertStringContainsString(
                        'FOREIGN KEY (partition_id) REFERENCES '
                        . self::REAL_SCHEMA
                        . '.audit_outbox_partition_state(partition_id)',
                        (string) $row['definition'],
                    );
                    self::assertStringContainsString('ON DELETE RESTRICT', (string) $row['definition']);
                }
            }
            self::assertSame(
                [
                    'audit_outbox_partition_state_ordinal_check' => 'c',
                    'audit_outbox_partition_state_partition_check' => 'c',
                    'audit_outbox_partition_state_pk' => 'p',
                    'audit_outbox_partition_state_tenant_bounds_check' => 'c',
                    'audit_outbox_partition_state_tenant_pair_check' => 'c',
                    'audit_outbox_partition_state_version_check' => 'c',
                    'audit_outbox_records_body_check' => 'c',
                    'audit_outbox_records_hash_check' => 'c',
                    'audit_outbox_records_idempotency_uq' => 'u',
                    'audit_outbox_records_lease_token_check' => 'c',
                    'audit_outbox_records_ordinal_check' => 'c',
                    'audit_outbox_records_partition_fk' => 'f',
                    'audit_outbox_records_partition_tenant_check' => 'c',
                    'audit_outbox_records_pk' => 'p',
                    'audit_outbox_records_record_id_check' => 'c',
                    'audit_outbox_records_record_id_uq' => 'u',
                    'audit_outbox_records_schema_kind_check' => 'c',
                    'audit_outbox_records_state_check' => 'c',
                    'audit_outbox_records_version_check' => 'c',
                ],
                $constraintTypes,
            );

            $indexes = $connection->fetchAllAssociative(
                'SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = :schema ORDER BY indexname',
                ['schema' => self::REAL_SCHEMA],
                ['schema' => ParameterType::STRING],
            );
            self::assertSame(
                [
                    'audit_outbox_partition_state_pk',
                    'audit_outbox_records_idempotency_uq',
                    'audit_outbox_records_leased_expiry_idx',
                    'audit_outbox_records_pending_due_idx',
                    'audit_outbox_records_pk',
                    'audit_outbox_records_record_id_uq',
                    'audit_outbox_records_unacknowledged_idx',
                ],
                array_column($indexes, 'indexname'),
            );
            $indexDefinitions = array_column($indexes, 'indexdef', 'indexname');
            self::assertStringContainsString(
                '(partition_id, ingress_ordinal)',
                $indexDefinitions['audit_outbox_records_unacknowledged_idx'],
            );
            self::assertStringContainsString(
                "state = ANY (ARRAY['pending'::text, 'leased'::text])",
                $indexDefinitions['audit_outbox_records_unacknowledged_idx'],
            );
            self::assertStringContainsString(
                '(available_at, partition_id, ingress_ordinal)',
                $indexDefinitions['audit_outbox_records_pending_due_idx'],
            );
            self::assertStringContainsString(
                "state = 'pending'::text",
                $indexDefinitions['audit_outbox_records_pending_due_idx'],
            );
            self::assertStringContainsString(
                '(lease_expires_at, partition_id, ingress_ordinal)',
                $indexDefinitions['audit_outbox_records_leased_expiry_idx'],
            );
            self::assertStringContainsString(
                "state = 'leased'::text",
                $indexDefinitions['audit_outbox_records_leased_expiry_idx'],
            );
        } finally {
            $this->dropRealSchema($connection, $schema);
        }
    }

    public function testRealUnicodePositivesAndIsolatedU200ENegativesLeaveRowsUnchanged(): void
    {
        $connection = $this->realConnection();
        $schema = $this->schema();

        try {
            $this->createRealSchema($connection, $schema);
            $this->seedRealRows($connection, $schema);
            $state = $schema->partitionStateTable();
            $records = $schema->recordsTable();
            $stateProbe = "SELECT tenant_type, tenant_id FROM {$state} WHERE partition_id = :partition_id";
            $recordProbe = "SELECT tenant_type, tenant_id FROM {$records} WHERE record_id = :record_id";

            foreach (["line\u{2028}separator", "line\u{2029}separator", "music\u{1D17B}separator"] as $accepted) {
                foreach (['tenant_type', 'tenant_id'] as $column) {
                    $connection->executeStatement(
                        "UPDATE {$state} SET {$column} = :value WHERE partition_id = :partition_id",
                        ['value' => $accepted, 'partition_id' => self::TENANT_PARTITION],
                        ['value' => ParameterType::STRING, 'partition_id' => ParameterType::STRING],
                    );
                    $row = $connection->fetchAssociative(
                        $stateProbe,
                        ['partition_id' => self::TENANT_PARTITION],
                        ['partition_id' => ParameterType::STRING],
                    );
                    self::assertIsArray($row);
                    self::assertSame($accepted, $row[$column]);
                    $connection->executeStatement(
                        "UPDATE {$state} SET {$column} = :value WHERE partition_id = :partition_id",
                        [
                            'value' => $column === 'tenant_type' ? 'account' : 'tenant-a',
                            'partition_id' => self::TENANT_PARTITION,
                        ],
                        ['value' => ParameterType::STRING, 'partition_id' => ParameterType::STRING],
                    );
                }
                foreach (['tenant_type', 'tenant_id'] as $column) {
                    $connection->executeStatement(
                        "UPDATE {$records} SET {$column} = :value WHERE record_id = :record_id",
                        ['value' => $accepted, 'record_id' => self::RECORD_ID],
                        ['value' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
                    );
                    $row = $connection->fetchAssociative(
                        $recordProbe,
                        ['record_id' => self::RECORD_ID],
                        ['record_id' => ParameterType::STRING],
                    );
                    self::assertIsArray($row);
                    self::assertSame($accepted, $row[$column]);
                    $connection->executeStatement(
                        "UPDATE {$records} SET {$column} = :value WHERE record_id = :record_id",
                        ['value' => $column === 'tenant_type' ? 'account' : 'tenant-a', 'record_id' => self::RECORD_ID],
                        ['value' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
                    );
                }
            }

            $negativeCases = [
                [
                    $state,
                    'tenant_type',
                    'partition_id = :partition_id',
                    ['partition_id' => self::TENANT_PARTITION],
                    $stateProbe,
                    'audit_outbox_partition_state_tenant_bounds_check',
                ],
                [
                    $state,
                    'tenant_id',
                    'partition_id = :partition_id',
                    ['partition_id' => self::TENANT_PARTITION],
                    $stateProbe,
                    'audit_outbox_partition_state_tenant_bounds_check',
                ],
                [
                    $records,
                    'tenant_type',
                    'record_id = :record_id',
                    ['record_id' => self::RECORD_ID],
                    $recordProbe,
                    'audit_outbox_records_partition_tenant_check',
                ],
                [
                    $records,
                    'tenant_id',
                    'record_id = :record_id',
                    ['record_id' => self::RECORD_ID],
                    $recordProbe,
                    'audit_outbox_records_partition_tenant_check',
                ],
            ];
            foreach ($negativeCases as [$table, $column, $where, $parameters, $probe, $constraint]) {
                $types = array_fill_keys(array_keys($parameters), ParameterType::STRING);
                $before = $connection->fetchAssociative($probe, $parameters, $types);
                self::assertIsArray($before);
                try {
                    $connection->executeStatement(
                        "UPDATE {$table} SET {$column} = :value WHERE {$where}",
                        ['value' => "bad\u{200E}value", ...$parameters],
                        ['value' => ParameterType::STRING, ...$types],
                    );
                    self::fail('U+200E was accepted in ' . $column);
                } catch (Throwable $failure) {
                    $this->assertSqlStateAndConstraint($failure, '23514', $constraint);
                }
                self::assertSame($before, $connection->fetchAssociative($probe, $parameters, $types));
            }
        } finally {
            $this->dropRealSchema($connection, $schema);
        }
    }

    public function testRealMutationMatrixRejectsInvalidRowsWithSqlStateAndNoMutation(): void
    {
        $connection = $this->realConnection();
        $schema = $this->schema();

        try {
            $this->createRealSchema($connection, $schema);
            foreach ($this->mutationCases($schema) as $case) {
                $connection->executeStatement(
                    'TRUNCATE TABLE ' . $schema->recordsTable() . ', ' . $schema->partitionStateTable() . ' CASCADE',
                );
                $this->seedRealRows($connection, $schema);
                $before = $this->rowSnapshot($connection, $schema);
                try {
                    $connection->executeStatement($case['mutation']);
                    self::fail('Mutation unexpectedly succeeded: ' . $case['name']);
                } catch (Throwable $failure) {
                    $this->assertSqlStateAndConstraint($failure, $case['sqlState'], $case['constraint']);
                }
                self::assertSame($before, $this->rowSnapshot($connection, $schema), $case['name']);
            }
        } finally {
            $this->dropRealSchema($connection, $schema);
        }
    }

    public function testRealBodyRoundTripsAcrossSecondConnectionAndNonUtcSession(): void
    {
        $writer = $this->realConnection();
        $reader = $this->realConnection();
        $schema = $this->schema();
        $minimum = "BOPD\0" . str_repeat('m', 48);
        $maximum = "BOPD\0" . str_repeat('x', 1_048_571);

        try {
            $this->createRealSchema($writer, $schema);
            $this->seedRealRows($writer, $schema);
            $writer->executeStatement("SET TIME ZONE 'America/Los_Angeles'");
            $reader->executeStatement("SET TIME ZONE 'Asia/Tokyo'");
            $state = $schema->partitionStateTable();
            $records = $schema->recordsTable();
            $writer->executeStatement(
                "UPDATE {$state} SET last_assigned_recorded_at = CAST(:recorded_at AS timestamptz) WHERE partition_id = :partition_id",
                ['recorded_at' => '2026-09-01T03:04:05.123456Z', 'partition_id' => self::TENANT_PARTITION],
                ['recorded_at' => ParameterType::STRING, 'partition_id' => ParameterType::STRING],
            );

            foreach ([$minimum, $maximum] as $body) {
                $writer->executeStatement(
                    "UPDATE {$records} SET protected_body = :protected_body WHERE record_id = :record_id",
                    ['protected_body' => $body, 'record_id' => self::RECORD_ID],
                    ['protected_body' => ParameterType::BINARY, 'record_id' => ParameterType::STRING],
                );
                $row = $reader->fetchAssociative(
                    "SELECT protected_body, octet_length(protected_body)::text AS body_length, encode(substring(protected_body FROM 1 FOR 4), 'hex') AS body_magic, "
                    . "to_char((SELECT last_assigned_recorded_at FROM {$state} WHERE partition_id = :partition_id) AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS recorded_at "
                    . "FROM {$records} WHERE record_id = :record_id",
                    ['partition_id' => self::TENANT_PARTITION, 'record_id' => self::RECORD_ID],
                    ['partition_id' => ParameterType::STRING, 'record_id' => ParameterType::STRING],
                );
                self::assertIsArray($row);
                self::assertSame($body, PostgreSqlBytea::string($row['protected_body']));
                self::assertSame((string) strlen($body), $row['body_length']);
                self::assertSame('424f5044', $row['body_magic']);
                self::assertSame('2026-09-01T03:04:05.123456Z', $row['recorded_at']);
            }
        } finally {
            $reader->close();
            $this->dropRealSchema($writer, $schema);
        }
    }

    /** @return list<array{name:string,mutation:string,sqlState:string,constraint:string}> */
    private function mutationCases(PostgreSqlAuditOutboxSchema $schema): array
    {
        $state = $schema->partitionStateTable();
        $records = $schema->recordsTable();
        $record = self::RECORD_ID;
        $body = "decode('424f5044' || repeat('42', 49), 'hex')";
        $validForeignPartition = 'tenant:' . str_repeat('b', 64);

        return [
            [
                'name' => 'state ordinal lower bound',
                'mutation' =>
                    "UPDATE {$state} SET last_ingress_ordinal = 0 WHERE partition_id = '"
                        . self::TENANT_PARTITION
                        . "'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_partition_state_ordinal_check',
            ],
            [
                'name' => 'state ordinal upper bound',
                'mutation' =>
                    "UPDATE {$state} SET last_ingress_ordinal = 9007199254740992 WHERE partition_id = '"
                        . self::TENANT_PARTITION
                        . "'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_partition_state_ordinal_check',
            ],
            [
                'name' => 'state version mismatch',
                'mutation' =>
                    "UPDATE {$state} SET state_version = 2 WHERE partition_id = '" . self::TENANT_PARTITION . "'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_partition_state_version_check',
            ],
            [
                'name' => 'state tenant pair',
                'mutation' =>
                    "UPDATE {$state} SET tenant_type = NULL WHERE partition_id = '" . self::TENANT_PARTITION . "'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_partition_state_tenant_pair_check',
            ],
            [
                'name' => 'record schema version',
                'mutation' => "UPDATE {$records} SET schema_version = 2 WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_schema_kind_check',
            ],
            [
                'name' => 'record kind',
                'mutation' => "UPDATE {$records} SET kind = 'other' WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_schema_kind_check',
            ],
            [
                'name' => 'record UUID version',
                'mutation' => "UPDATE {$records} SET record_id = '01941f29-7c00-4000-8000-000000000001' WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_record_id_check',
            ],
            [
                'name' => 'record partition grammar',
                'mutation' => "UPDATE {$records} SET partition_id = 'tenant:bad' WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_partition_tenant_check',
            ],
            [
                'name' => 'record tenant pair',
                'mutation' => "UPDATE {$records} SET tenant_id = NULL WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_partition_tenant_check',
            ],
            [
                'name' => 'record ordinal lower bound',
                'mutation' => "UPDATE {$records} SET ingress_ordinal = 0 WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_ordinal_check',
            ],
            [
                'name' => 'idempotency hash alphabet',
                'mutation' => "UPDATE {$records} SET idempotency_key_hash = repeat('A', 64) WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_hash_check',
            ],
            [
                'name' => 'content hash length',
                'mutation' => "UPDATE {$records} SET content_hash = repeat('a', 63) WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_hash_check',
            ],
            [
                'name' => 'pending attempt upper bound',
                'mutation' => "UPDATE {$records} SET attempt = 32 WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_state_check',
            ],
            [
                'name' => 'pending availability nullability',
                'mutation' => "UPDATE {$records} SET available_at = NULL WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_state_check',
            ],
            [
                'name' => 'pending lease nullability',
                'mutation' =>
                    "UPDATE {$records} SET lease_fencing_token = '"
                        . self::LEASE_TOKEN
                        . "' WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_state_check',
            ],
            [
                'name' => 'lease token UUID version',
                'mutation' => "UPDATE {$records} SET state = 'leased', attempt = 1, available_at = NULL, lease_fencing_token = '01941f29-7c00-4000-8000-000000000004', lease_expires_at = '2026-09-01 03:05:00+00' WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_lease_token_check',
            ],
            [
                'name' => 'terminal attempt lower bound',
                'mutation' => "UPDATE {$records} SET state = 'relayed', attempt = 0, available_at = NULL WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_state_check',
            ],
            [
                'name' => 'unknown state',
                'mutation' => "UPDATE {$records} SET state = 'other' WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_state_check',
            ],
            [
                'name' => 'body magic',
                'mutation' => "UPDATE {$records} SET protected_body = decode('434f5044' || repeat('42', 49), 'hex') WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_body_check',
            ],
            [
                'name' => 'body minimum',
                'mutation' => "UPDATE {$records} SET protected_body = decode('424f5044' || repeat('42', 48), 'hex') WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_body_check',
            ],
            [
                'name' => 'body cap',
                'mutation' => "UPDATE {$records} SET protected_body = decode('424f5044' || repeat('42', 1048573), 'hex') WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_body_check',
            ],
            [
                'name' => 'version lower bound',
                'mutation' => "UPDATE {$records} SET version = 0 WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_version_check',
            ],
            [
                'name' => 'version upper bound',
                'mutation' => "UPDATE {$records} SET version = 9007199254740992 WHERE record_id = '{$record}'",
                'sqlState' => '23514',
                'constraint' => 'audit_outbox_records_version_check',
            ],
            [
                'name' => 'partition foreign key',
                'mutation' => $this->recordInsertSql(
                    $schema,
                    $validForeignPartition,
                    self::SECOND_RECORD_ID,
                    1,
                    self::SECOND_HASH,
                    self::SECOND_HASH,
                    $body,
                ),
                'sqlState' => '23503',
                'constraint' => 'audit_outbox_records_partition_fk',
            ],
            [
                'name' => 'record ID uniqueness',
                'mutation' => $this->recordInsertSql(
                    $schema,
                    self::TENANT_PARTITION,
                    self::RECORD_ID,
                    2,
                    self::SECOND_HASH,
                    self::SECOND_HASH,
                    $body,
                ),
                'sqlState' => '23505',
                'constraint' => 'audit_outbox_records_record_id_uq',
            ],
            [
                'name' => 'partition hash uniqueness',
                'mutation' => $this->recordInsertSql(
                    $schema,
                    self::TENANT_PARTITION,
                    self::SECOND_RECORD_ID,
                    2,
                    self::HASH,
                    self::SECOND_HASH,
                    $body,
                ),
                'sqlState' => '23505',
                'constraint' => 'audit_outbox_records_idempotency_uq',
            ],
            [
                'name' => 'partition ordinal uniqueness',
                'mutation' => $this->recordInsertSql(
                    $schema,
                    self::TENANT_PARTITION,
                    self::SECOND_RECORD_ID,
                    1,
                    self::SECOND_HASH,
                    self::SECOND_HASH,
                    $body,
                ),
                'sqlState' => '23505',
                'constraint' => 'audit_outbox_records_pk',
            ],
        ];
    }

    /** @return array{state:list<array<string,mixed>>,records:list<array<string,mixed>>} */
    private function rowSnapshot(Connection $connection, PostgreSqlAuditOutboxSchema $schema): array
    {
        $state = $connection->fetchAllAssociative(
            'SELECT partition_id, tenant_type, tenant_id, last_ingress_ordinal::text AS last_ingress_ordinal, '
            . 'to_char(last_assigned_recorded_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS last_assigned_recorded_at, '
            . 'state_version::text AS state_version FROM '
            . $schema->partitionStateTable()
            . ' ORDER BY partition_id',
        );
        $records = $connection->fetchAllAssociative(
            'SELECT schema_version::text AS schema_version, kind, record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
            . 'ingress_ordinal::text AS ingress_ordinal, idempotency_key_hash, content_hash, state, attempt::text AS attempt, '
            . 'available_at, lease_fencing_token::text AS lease_fencing_token, lease_expires_at, protected_body, version::text AS version '
            . 'FROM '
            . $schema->recordsTable()
            . ' ORDER BY partition_id, ingress_ordinal',
        );
        foreach ($records as &$record) {
            $record['protected_body'] = PostgreSqlBytea::string($record['protected_body']);
        }
        unset($record);

        return ['state' => $state, 'records' => $records];
    }

    private function schema(): PostgreSqlAuditOutboxSchema
    {
        return new PostgreSqlAuditOutboxSchema(new PostgreSqlMigrationSchema(self::REAL_SCHEMA));
    }

    private function createRealSchema(Connection $connection, PostgreSqlAuditOutboxSchema $schema): void
    {
        $connection->executeStatement('DROP SCHEMA IF EXISTS "' . $schema->schemaName() . '" CASCADE');
        $connection->executeStatement('CREATE SCHEMA "' . $schema->schemaName() . '"');
        foreach ($schema->statements() as $statement) {
            $connection->executeStatement($statement);
        }
    }

    private function dropRealSchema(Connection $connection, PostgreSqlAuditOutboxSchema $schema): void
    {
        try {
            $connection->executeStatement('DROP SCHEMA IF EXISTS "' . $schema->schemaName() . '" CASCADE');
        } finally {
            $connection->close();
        }
    }

    private function seedRealRows(Connection $connection, PostgreSqlAuditOutboxSchema $schema): void
    {
        $state = $schema->partitionStateTable();
        $connection->executeStatement(
            "INSERT INTO {$state} (partition_id, tenant_type, tenant_id, last_ingress_ordinal, last_assigned_recorded_at, state_version) "
            . 'VALUES (:partition_id, :tenant_type, :tenant_id, 1, CAST(:recorded_at AS timestamptz), 1)',
            [
                'partition_id' => self::TENANT_PARTITION,
                'tenant_type' => 'account',
                'tenant_id' => 'tenant-a',
                'recorded_at' => '2026-09-01T03:00:00.123456Z',
            ],
            [
                'partition_id' => ParameterType::STRING,
                'tenant_type' => ParameterType::STRING,
                'tenant_id' => ParameterType::STRING,
                'recorded_at' => ParameterType::STRING,
            ],
        );
        $connection->executeStatement($this->recordInsertSql(
            $schema,
            self::TENANT_PARTITION,
            self::RECORD_ID,
            1,
            self::HASH,
            self::SECOND_HASH,
            "decode('424f5044' || repeat('42', 49), 'hex')",
        ));
    }

    private function recordInsertSql(
        PostgreSqlAuditOutboxSchema $schema,
        string $partition,
        string $recordId,
        int $ordinal,
        string $idempotencyHash,
        string $contentHash,
        string $body,
    ): string {
        return "INSERT INTO {$schema->recordsTable()} (schema_version, kind, record_id, partition_id, tenant_type, tenant_id, ingress_ordinal, idempotency_key_hash, content_hash, state, attempt, available_at, lease_fencing_token, lease_expires_at, protected_body, version) VALUES (1, 'audit_outbox_record', '{$recordId}', '{$partition}', 'account', 'tenant-a', {$ordinal}, '{$idempotencyHash}', '{$contentHash}', 'pending', 0, '2026-09-01 03:00:00.123456+00', NULL, NULL, {$body}, 1)";
    }

    private function assertSqlStateAndConstraint(Throwable $failure, string $sqlState, string $constraint): void
    {
        $states = [];
        $messages = [];
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            $states[] = (string) $current->getCode();
            $messages[] = $current->getMessage();
        }
        self::assertContains($sqlState, $states, implode(' | ', $messages));
        self::assertStringContainsString($constraint, implode(' | ', $messages));
    }

    /** @return list<array{name:string,definition:string}> */
    private function columns(string $statement): array
    {
        $columns = [];
        foreach ($this->definitions($statement) as $part) {
            $normalized = $this->normalizeSql($part);
            if (str_starts_with($normalized, 'constraint ')) {
                break;
            }
            self::assertMatchesRegularExpression('/^([a-z_]+) (.+)$/', $normalized);
            preg_match('/^([a-z_]+) (.+)$/', $normalized, $matches);
            $columns[] = ['name' => $matches[1], 'definition' => $matches[2]];
        }

        return $columns;
    }

    /** @return array<string,string> */
    private function constraints(string $statement): array
    {
        $constraints = [];
        foreach ($this->definitions($statement) as $part) {
            $normalized = $this->normalizeSql($part);
            if (preg_match('/^constraint ([a-z_]+) (.+)$/', $normalized, $matches) === 1) {
                $constraints[$matches[1]] = $matches[2];
            }
        }

        return $constraints;
    }

    /** @return list<string> */
    private function definitions(string $statement): array
    {
        $start = strpos($statement, '(');
        $end = strrpos($statement, ')');
        self::assertIsInt($start);
        self::assertIsInt($end);
        $body = substr($statement, $start + 1, $end - $start - 1);
        $parts = [];
        $current = '';
        $depth = 0;
        $quoted = false;
        $length = strlen($body);
        for ($index = 0; $index < $length; $index++) {
            $character = $body[$index];
            if ($character === "'" && ($index === 0 || $body[$index - 1] !== '\\')) {
                if ($quoted && (($index + 1) >= $length || $body[$index + 1] !== "'")) {
                    $quoted = false;
                } elseif (!$quoted) {
                    $quoted = true;
                }
            }
            if (!$quoted) {
                if ($character === '(') {
                    $depth++;
                } elseif ($character === ')') {
                    $depth--;
                } elseif ($character === ',' && $depth === 0) {
                    $parts[] = trim($current);
                    $current = '';
                    continue;
                }
            }
            $current .= $character;
        }
        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    private function normalizeSql(string $sql): string
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
                $next = $index;
                while ($next < $length && str_contains(" \t\r\n\f\v", $sql[$next])) {
                    $next++;
                }
                $previous = $normalized === '' ? null : $normalized[strlen($normalized) - 1];
                $following = $next < $length ? $sql[$next] : null;
                if ($normalized !== '' && !str_ends_with($normalized, ' ') && $previous !== '(' && $following !== ')') {
                    $normalized .= ' ';
                }
                $index = $next - 1;
                continue;
            }
            $normalized .= strtolower($character);
        }

        return str_replace('"' . self::REAL_SCHEMA . '"', '{schema}', trim($normalized));
    }

    private function controlInventoryExpression(string $ddl): string
    {
        $needle = 'translate(tenant_type, ';
        $start = strpos($ddl, $needle);
        self::assertIsInt($start);
        $suffix = ') = tenant_type';
        $end = strpos($ddl, $suffix, $start);
        self::assertIsInt($end);

        return substr($ddl, $start, $end + strlen($suffix) - $start);
    }

    /** @return list<string> */
    private function controlInventoryExpressions(string $ddl): array
    {
        preg_match_all('/translate\((?:tenant_type|tenant_id), (.+?)\) = (?:tenant_type|tenant_id)/', $ddl, $matches);
        $expressions = [];
        foreach ($matches[0] ?? [] as $match) {
            self::assertIsString($match);
            $expressions[] = $match;
        }

        return $expressions;
    }

    private function tableName(string $statement): string
    {
        $normalized = $this->normalizeSql($statement);
        self::assertMatchesRegularExpression('/^create table \{schema\}\."([a-z_]+)"/', $normalized);

        return (string) preg_replace('/^create table \{schema\}\."([a-z_]+)".*$/', '$1', $normalized);
    }

    private function realConnection(): Connection
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
}

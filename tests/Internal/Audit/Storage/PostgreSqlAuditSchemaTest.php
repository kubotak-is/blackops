<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Storage;

use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Throwable;

final class PostgreSqlAuditSchemaTest extends TestCase
{
    private const string REAL_SCHEMA = 'blackops_audit_schema_contract';

    private const string SYSTEM_PARTITION = 'system:global';

    private const string TENANT_PARTITION = 'tenant:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string SEGMENT_ID = '01941f29-7c00-7000-8000-000000000001';

    private const string TENANT_SEGMENT_ID = '01941f29-7c00-7000-8000-000000000002';

    private const string RECORD_ID = '01941f29-7c00-7000-8000-000000000011';

    private const string TENANT_RECORD_ID = '01941f29-7c00-7000-8000-000000000012';

    private const string HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string TOKEN = 'aft:v1:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testUsesTheConfiguredSchemaAndExactStatementOrder(): void
    {
        $schema = new PostgreSqlAuditSchema('blackops_audit');
        $statements = $schema->statements();

        self::assertSame('blackops_audit', $schema->schemaName());
        self::assertSame('"blackops_audit"."audit_partition_state"', $schema->partitionStateTable());
        self::assertSame('"blackops_audit"."audit_segments"', $schema->segmentsTable());
        self::assertSame('"blackops_audit"."audit_records"', $schema->recordsTable());
        self::assertCount(9, $statements);
        self::assertStringStartsWith('CREATE TABLE "blackops_audit"."audit_segments"', $statements[0]);
        self::assertStringStartsWith('CREATE TABLE "blackops_audit"."audit_partition_state"', $statements[1]);
        self::assertStringStartsWith('CREATE TABLE "blackops_audit"."audit_records"', $statements[2]);
        self::assertSame($this->expectedIndexes(), array_map($this->normalizeSql(...), array_slice($statements, 3)));

        $tableStatements = array_slice($statements, 0, 3);
        self::assertSame(
            ['audit_segments', 'audit_partition_state', 'audit_records'],
            array_map($this->tableName(...), $tableStatements),
        );
        $ddl = implode("\n", $tableStatements);
        self::assertStringNotContainsString('IF NOT EXISTS', $ddl);
        self::assertSame($this->expectedColumns('segments'), $this->columns($tableStatements[0]));
        self::assertSame($this->expectedColumns('partition_state'), $this->columns($tableStatements[1]));
        self::assertSame($this->expectedColumns('records'), $this->columns($tableStatements[2]));
        self::assertSame($this->expectedConstraints(), array_merge(
            $this->constraints($tableStatements[0]),
            $this->constraints($tableStatements[1]),
            $this->constraints($tableStatements[2]),
        ));
        self::assertStringContainsString("where state = 'open'", $this->expectedIndexes()[0]);
        self::assertStringNotContainsString("where state = 'closed'", $this->expectedIndexes()[0]);
        foreach (['actor', 'policy', 'evidence', 'occurred_at', 'key_id'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $ddl);
        }
    }

    public function testSchemaNameUsesTheShared63ByteValidator(): void
    {
        $this->assertInvalidSchemaName('BlackOps');
        $this->assertInvalidSchemaName('a' . str_repeat('b', 63));
        $this->assertInvalidSchemaName('a' . str_repeat('b', 62) . 'x');
        $this->assertInvalidSchemaName('é');
        $this->assertInvalidSchemaName('"blackops"');
        $this->assertInvalidSchemaName("blackops\n");
    }

    public function testSqlNormalizationPreservesQuotedLiteralCaseAndEscapes(): void
    {
        $sql = <<<'SQL'
            CHECK ("CaseSensitive" = 'ROTATION' AND category = 'rotation' AND label = 'it''s' AND label_upper = 'IT''S' AND marker = E'Cc\x01ROTATION')
            SQL;
        $expected = <<<'SQL'
            check ("CaseSensitive" = 'ROTATION' and category = 'rotation' and label = 'it''s' and label_upper = 'IT''S' and marker = e'Cc\x01ROTATION')
            SQL;

        self::assertSame(trim($expected), $this->normalizeSql($sql));
    }

    public function testStaticCatalogUsesEveryUnicodeCcAndCfCodePointPostgreSqlCanEvaluate(): void
    {
        $schema = new PostgreSqlAuditSchema('blackops_audit');
        $ddl = implode("\n", $schema->statements());

        self::assertCount(235, $this->unicodeCcCfCodePoints());
        self::assertStringNotContainsString('[[:cntrl:]]', $ddl);
        self::assertStringNotContainsString('chr(0)', $ddl);
        self::assertStringNotContainsString('chr(8232)', $ddl);
        self::assertStringNotContainsString('chr(8233)', $ddl);
        foreach ($this->unicodeCcCfCodePoints() as $codePoint) {
            if ($codePoint === 0) {
                continue;
            }
            self::assertStringContainsString(
                'chr(' . $codePoint . ')',
                $ddl,
                'missing U+' . strtoupper(dechex($codePoint)),
            );
        }
        foreach (['tenant_type', 'tenant_id', 'action_type', 'resource_type', 'resource_id'] as $column) {
            self::assertStringContainsString($this->expectedControlFree($column), $ddl);
        }
    }

    public function testRealPostgreSqlHeadCheckAcceptsControlsAndRejectsPositiveNullWithoutMutation(): void
    {
        $connection = $this->realConnection();
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            $this->createRealSchema($connection, $schema);
            $this->insertSegment($connection, $schema, self::SYSTEM_PARTITION, self::SEGMENT_ID, 1, 1, self::HASH);
            $this->insertState(
                $connection,
                $schema,
                self::SYSTEM_PARTITION,
                null,
                null,
                self::SEGMENT_ID,
                1,
                self::HASH,
            );
            $this->insertSegment($connection, $schema, self::TENANT_PARTITION, self::TENANT_SEGMENT_ID, 1, null, null);
            $this->insertState(
                $connection,
                $schema,
                self::TENANT_PARTITION,
                'account',
                'tenant-a',
                self::TENANT_SEGMENT_ID,
                0,
                null,
            );

            self::assertSame(2, (int) $connection->fetchOne('SELECT count(*) FROM ' . $schema->partitionStateTable()));
            $before = $connection->fetchAssociative(
                'SELECT last_sequence::text AS last_sequence, head_record_hash FROM '
                . $schema->partitionStateTable()
                . ' WHERE partition_id = :partition_id',
                ['partition_id' => self::SYSTEM_PARTITION],
                ['partition_id' => ParameterType::STRING],
            );
            self::assertIsArray($before);
            self::assertSame('1', $before['last_sequence']);
            self::assertSame(self::HASH, $before['head_record_hash']);

            try {
                $connection->executeStatement(
                    'UPDATE '
                    . $schema->partitionStateTable()
                    . ' SET head_record_hash = NULL WHERE partition_id = :partition_id',
                    ['partition_id' => self::SYSTEM_PARTITION],
                    ['partition_id' => ParameterType::STRING],
                );
                self::fail('Positive last_sequence accepted a null head hash.');
            } catch (Throwable $failure) {
                $this->assertSqlStateAndConstraint($failure, '23514', 'audit_partition_state_head_check');
            }

            $after = $connection->fetchAssociative(
                'SELECT last_sequence::text AS last_sequence, head_record_hash FROM '
                . $schema->partitionStateTable()
                . ' WHERE partition_id = :partition_id',
                ['partition_id' => self::SYSTEM_PARTITION],
                ['partition_id' => ParameterType::STRING],
            );
            self::assertSame($before, $after);
        } finally {
            $this->dropRealSchema($connection, $schema);
        }
    }

    public function testRealPostgreSqlUnicodeCcCfChecksAcceptLineSeparatorsAndRejectCfWithoutMutation(): void
    {
        $connection = $this->realConnection();
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            $this->createRealSchema($connection, $schema);
            $this->insertSegment($connection, $schema, self::TENANT_PARTITION, self::TENANT_SEGMENT_ID, 1, null, null);
            $this->insertState(
                $connection,
                $schema,
                self::TENANT_PARTITION,
                'account',
                'tenant-a',
                self::TENANT_SEGMENT_ID,
                0,
                null,
            );
            $connection->executeStatement($this->recordInsertSql(
                $schema,
                self::TENANT_PARTITION,
                self::TENANT_RECORD_ID,
                self::TENANT_SEGMENT_ID,
                1,
                null,
                self::HASH,
                'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                'account',
                'tenant-a',
                "decode('424f5044' || repeat('42', 49), 'hex')",
            ));

            $stateProbe =
                'SELECT tenant_type, tenant_id FROM '
                . $schema->partitionStateTable()
                . ' WHERE partition_id = :partition_id';
            $recordProbe =
                'SELECT tenant_type, tenant_id, action_type, resource_type, resource_id FROM '
                . $schema->recordsTable()
                . ' WHERE record_id = :record_id';
            $stateParameters = ['partition_id' => self::TENANT_PARTITION];
            $stateTypes = ['partition_id' => ParameterType::STRING];
            $recordParameters = ['record_id' => self::TENANT_RECORD_ID];
            $recordTypes = ['record_id' => ParameterType::STRING];

            foreach (["line\u{2028}separator", "line\u{2029}separator"] as $accepted) {
                foreach (['tenant_type', 'tenant_id'] as $column) {
                    $connection->executeStatement(
                        'UPDATE '
                        . $schema->partitionStateTable()
                        . " SET {$column} = :value WHERE partition_id = :partition_id",
                        ['value' => $accepted, ...$stateParameters],
                        ['value' => ParameterType::STRING, ...$stateTypes],
                    );
                    $row = $connection->fetchAssociative($stateProbe, $stateParameters, $stateTypes);
                    self::assertIsArray($row);
                    self::assertSame($accepted, $row[$column]);
                    $connection->executeStatement(
                        'UPDATE '
                        . $schema->partitionStateTable()
                        . " SET {$column} = :value WHERE partition_id = :partition_id",
                        ['value' => $column === 'tenant_type' ? 'account' : 'tenant-a', ...$stateParameters],
                        ['value' => ParameterType::STRING, ...$stateTypes],
                    );
                }

                foreach (['tenant_type', 'tenant_id', 'action_type', 'resource_type', 'resource_id'] as $column) {
                    $connection->executeStatement(
                        'UPDATE ' . $schema->recordsTable() . " SET {$column} = :value WHERE record_id = :record_id",
                        ['value' => $accepted, ...$recordParameters],
                        ['value' => ParameterType::STRING, ...$recordTypes],
                    );
                    $row = $connection->fetchAssociative($recordProbe, $recordParameters, $recordTypes);
                    self::assertIsArray($row);
                    self::assertSame($accepted, $row[$column]);
                    $original = match ($column) {
                        'tenant_type' => 'account',
                        'tenant_id' => 'tenant-a',
                        'action_type' => 'action',
                        'resource_type' => 'resource',
                        'resource_id' => 'id',
                    };
                    $connection->executeStatement(
                        'UPDATE ' . $schema->recordsTable() . " SET {$column} = :value WHERE record_id = :record_id",
                        ['value' => $original, ...$recordParameters],
                        ['value' => ParameterType::STRING, ...$recordTypes],
                    );
                }
            }

            $negativeCases = [
                [
                    'table' => $schema->partitionStateTable(),
                    'column' => 'tenant_type',
                    'where' => 'partition_id = :partition_id',
                    'parameters' => $stateParameters,
                    'types' => $stateTypes,
                    'probe' => $stateProbe,
                    'constraint' => 'audit_partition_state_tenant_bounds_check',
                ],
                [
                    'table' => $schema->partitionStateTable(),
                    'column' => 'tenant_id',
                    'where' => 'partition_id = :partition_id',
                    'parameters' => $stateParameters,
                    'types' => $stateTypes,
                    'probe' => $stateProbe,
                    'constraint' => 'audit_partition_state_tenant_bounds_check',
                ],
                [
                    'table' => $schema->recordsTable(),
                    'column' => 'tenant_type',
                    'where' => 'record_id = :record_id',
                    'parameters' => $recordParameters,
                    'types' => $recordTypes,
                    'probe' => $recordProbe,
                    'constraint' => 'audit_records_partition_tenant_check',
                ],
                [
                    'table' => $schema->recordsTable(),
                    'column' => 'tenant_id',
                    'where' => 'record_id = :record_id',
                    'parameters' => $recordParameters,
                    'types' => $recordTypes,
                    'probe' => $recordProbe,
                    'constraint' => 'audit_records_partition_tenant_check',
                ],
                [
                    'table' => $schema->recordsTable(),
                    'column' => 'action_type',
                    'where' => 'record_id = :record_id',
                    'parameters' => $recordParameters,
                    'types' => $recordTypes,
                    'probe' => $recordProbe,
                    'constraint' => 'audit_records_action_check',
                ],
                [
                    'table' => $schema->recordsTable(),
                    'column' => 'resource_type',
                    'where' => 'record_id = :record_id',
                    'parameters' => $recordParameters,
                    'types' => $recordTypes,
                    'probe' => $recordProbe,
                    'constraint' => 'audit_records_resource_check',
                ],
                [
                    'table' => $schema->recordsTable(),
                    'column' => 'resource_id',
                    'where' => 'record_id = :record_id',
                    'parameters' => $recordParameters,
                    'types' => $recordTypes,
                    'probe' => $recordProbe,
                    'constraint' => 'audit_records_resource_check',
                ],
            ];
            foreach ($negativeCases as $case) {
                $before = $connection->fetchAssociative($case['probe'], $case['parameters'], $case['types']);
                self::assertIsArray($before);
                try {
                    $connection->executeStatement(
                        "UPDATE {$case['table']} SET {$case['column']} = :value WHERE {$case['where']}",
                        ['value' => "bad\u{200E}value", ...$case['parameters']],
                        ['value' => ParameterType::STRING, ...$case['types']],
                    );
                    self::fail('U+200E was accepted in ' . $case['column']);
                } catch (Throwable $failure) {
                    $this->assertSqlStateAndConstraint($failure, '23514', $case['constraint']);
                }
                self::assertSame($before, $connection->fetchAssociative(
                    $case['probe'],
                    $case['parameters'],
                    $case['types'],
                ));
            }
        } finally {
            $this->dropRealSchema($connection, $schema);
        }
    }

    public function testRealPostgreSqlDdlAcceptsExactBodyAndQueryIndexByteCaps(): void
    {
        $connection = $this->realConnection();
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            $this->createRealSchema($connection, $schema);
            $this->seedRealRows($connection, $schema);

            self::assertSame(1, $connection->executeStatement(
                'UPDATE '
                . $schema->recordsTable()
                . " SET protected_body = decode('424f5044' || repeat('42', 1048752), 'hex'), "
                . "action_type = repeat('a', 256), resource_type = repeat('b', 256), resource_id = repeat('c', 256) "
                . "WHERE record_id = '"
                . self::RECORD_ID
                . "'",
            ));

            self::assertSame([
                'body_length' => '1048756',
                'body_magic' => '424f5044',
                'action_length' => '256',
                'resource_type_length' => '256',
                'resource_id_length' => '256',
            ], $connection->fetchAssociative(
                'SELECT octet_length(protected_body)::text AS body_length, '
                . "encode(substring(protected_body FROM 1 FOR 4), 'hex') AS body_magic, "
                . 'octet_length(action_type)::text AS action_length, '
                . 'octet_length(resource_type)::text AS resource_type_length, '
                . 'octet_length(resource_id)::text AS resource_id_length '
                . 'FROM '
                . $schema->recordsTable()
                . " WHERE record_id = '"
                . self::RECORD_ID
                . "'",
            ));
        } finally {
            $this->dropRealSchema($connection, $schema);
        }
    }

    public function testRealPostgreSqlDdlRoundTripsNulBodiesAcrossASecondConnection(): void
    {
        $writer = $this->realConnection();
        $reader = $this->realConnection();
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);
        $recordId = self::RECORD_ID;
        $minimum = "BOPD\0" . str_repeat('m', 48);
        $maximum = "BOPD\0" . str_repeat('x', 1_048_751);

        try {
            $this->createRealSchema($writer, $schema);
            $this->seedRealRows($writer, $schema);

            self::assertSame(1, $writer->executeStatement(
                'UPDATE '
                . $schema->recordsTable()
                . ' SET protected_body = :protected_body WHERE record_id = :record_id',
                ['protected_body' => $minimum, 'record_id' => $recordId],
                ['protected_body' => ParameterType::BINARY, 'record_id' => ParameterType::STRING],
            ));
            $minimumRow = $reader->fetchAssociative(
                'SELECT protected_body, octet_length(protected_body)::text AS body_length, '
                . "encode(substring(protected_body FROM 1 FOR 4), 'hex') AS body_magic "
                . 'FROM '
                . $schema->recordsTable()
                . ' WHERE record_id = :record_id',
                ['record_id' => $recordId],
                ['record_id' => ParameterType::STRING],
            );
            self::assertIsArray($minimumRow);
            self::assertSame($minimum, PostgreSqlBytea::string($minimumRow['protected_body']));
            self::assertSame('53', $minimumRow['body_length']);
            self::assertSame('424f5044', $minimumRow['body_magic']);

            self::assertSame(1, $writer->executeStatement(
                'UPDATE '
                . $schema->recordsTable()
                . ' SET protected_body = :protected_body WHERE record_id = :record_id',
                ['protected_body' => $maximum, 'record_id' => $recordId],
                ['protected_body' => ParameterType::BINARY, 'record_id' => ParameterType::STRING],
            ));
            $maximumRow = $reader->fetchAssociative(
                'SELECT protected_body, octet_length(protected_body)::text AS body_length, '
                . "encode(substring(protected_body FROM 1 FOR 4), 'hex') AS body_magic "
                . 'FROM '
                . $schema->recordsTable()
                . ' WHERE record_id = :record_id',
                ['record_id' => $recordId],
                ['record_id' => ParameterType::STRING],
            );
            self::assertIsArray($maximumRow);
            self::assertSame($maximum, PostgreSqlBytea::string($maximumRow['protected_body']));
            self::assertSame('1048756', $maximumRow['body_length']);
            self::assertSame('424f5044', $maximumRow['body_magic']);
        } finally {
            $reader->close();
            $this->dropRealSchema($writer, $schema);
        }
    }

    public function testRealPostgreSqlDdlMutationMatrixRejectsInvalidRowsAndLeavesSeedUnchanged(): void
    {
        $connection = $this->realConnection();
        $schema = new PostgreSqlAuditSchema(self::REAL_SCHEMA);

        try {
            $this->createRealSchema($connection, $schema);
            foreach ($this->ddlMutationCases($schema) as $case) {
                $connection->executeStatement(
                    'TRUNCATE TABLE '
                    . $schema->recordsTable()
                    . ', '
                    . $schema->partitionStateTable()
                    . ', '
                    . $schema->segmentsTable()
                    . ' CASCADE',
                );
                $this->seedRealRows($connection, $schema);
                foreach ($case['setup'] as $statement) {
                    $connection->executeStatement($statement);
                }
                $before = $connection->fetchAllAssociative($case['probe']);

                try {
                    $connection->executeStatement($case['mutation']);
                    self::fail('DDL mutation unexpectedly succeeded: ' . $case['name']);
                } catch (Throwable $failure) {
                    $this->assertSqlStateAndConstraint($failure, $case['sqlState'], $case['constraint']);
                }

                self::assertSame($before, $connection->fetchAllAssociative($case['probe']), $case['name']);
            }
        } finally {
            $this->dropRealSchema($connection, $schema);
        }
    }

    /**
     * @return list<array{name:string,setup:list<string>,mutation:string,probe:string,sqlState:string,constraint:string}>
     */
    private function ddlMutationCases(PostgreSqlAuditSchema $schema): array
    {
        $state = $schema->partitionStateTable();
        $segments = $schema->segmentsTable();
        $records = $schema->recordsTable();
        $body = "decode('424f5044' || repeat('42', 49), 'hex')";
        $tenantSegment = $this->segmentInsertSql(
            $schema,
            self::TENANT_PARTITION,
            '01941f29-7c00-7000-8000-000000000003',
            1,
            null,
            null,
        );
        $closedSegment = $this->segmentInsertSql(
            $schema,
            self::TENANT_PARTITION,
            '01941f29-7c00-7000-8000-000000000004',
            1,
            1,
            self::HASH,
            successor: '01941f29-7c00-7000-8000-000000000005',
            state: 'closed',
        );
        $successorSegment = $this->segmentInsertSql(
            $schema,
            self::TENANT_PARTITION,
            '01941f29-7c00-7000-8000-000000000005',
            2,
            null,
            null,
            predecessor: self::HASH,
        );
        $duplicateIncomingSegment = $this->segmentInsertSql(
            $schema,
            self::TENANT_PARTITION,
            '01941f29-7c00-7000-8000-000000000006',
            2,
            2,
            self::HASH,
            predecessor: self::HASH,
            successor: '01941f29-7c00-7000-8000-000000000005',
            state: 'closed',
        );
        $baseRecord = $this->recordInsertSql(
            $schema,
            self::TENANT_PARTITION,
            '01941f29-7c00-7000-8000-000000000021',
            '01941f29-7c00-7000-8000-000000000003',
            1,
            null,
            'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc',
            'account',
            'tenant-a',
            $body,
        );

        return [
            [
                'name' => 'partition tenant pair',
                'setup' => [],
                'mutation' => "UPDATE {$state} SET tenant_type = 'account' WHERE partition_id = 'system:global'",
                'probe' => "SELECT partition_id, tenant_type, tenant_id FROM {$state} ORDER BY partition_id",
                'sqlState' => '23514',
                'constraint' => 'audit_partition_state_tenant_pair_check',
            ],
            [
                'name' => 'positive head iff',
                'setup' => [],
                'mutation' => "UPDATE {$state} SET head_record_hash = NULL WHERE partition_id = 'system:global'",
                'probe' => "SELECT partition_id, last_sequence::text AS last_sequence, head_record_hash FROM {$state} ORDER BY partition_id",
                'sqlState' => '23514',
                'constraint' => 'audit_partition_state_head_check',
            ],
            [
                'name' => 'segment lifecycle',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$segments} SET closed_at = '2026-08-30 00:00:01+00' WHERE segment_id = '"
                        . self::SEGMENT_ID
                        . "'",
                'probe' => "SELECT segment_id::text AS segment_id, state, closed_at FROM {$segments} ORDER BY segment_id",
                'sqlState' => '23514',
                'constraint' => 'audit_segments_lifecycle_check',
            ],
            [
                'name' => 'one open segment',
                'setup' => [],
                'mutation' => "INSERT INTO {$segments} (partition_id, segment_id, state, first_sequence, opened_at) VALUES ('system:global', '01941f29-7c00-7000-8000-000000000031', 'open', 1, '2026-08-30 00:00:00+00')",
                'probe' => "SELECT partition_id, count(*)::text AS count FROM {$segments} GROUP BY partition_id ORDER BY partition_id",
                'sqlState' => '23505',
                'constraint' => 'audit_segments_one_open_idx',
            ],
            [
                'name' => 'segment successor self',
                'setup' => [$successorSegment, $closedSegment],
                'mutation' => "UPDATE {$segments} SET successor_segment_id = segment_id WHERE segment_id = '01941f29-7c00-7000-8000-000000000004'",
                'probe' => "SELECT segment_id::text AS segment_id, successor_segment_id::text AS successor_segment_id FROM {$segments} ORDER BY segment_id",
                'sqlState' => '23514',
                'constraint' => 'audit_segments_successor_check',
            ],
            [
                'name' => 'segment close token grammar',
                'setup' => [$successorSegment, $closedSegment],
                'mutation' => "UPDATE {$segments} SET close_fencing_token = 'invalid' WHERE segment_id = '01941f29-7c00-7000-8000-000000000004'",
                'probe' => "SELECT segment_id::text AS segment_id, close_fencing_token FROM {$segments} ORDER BY segment_id",
                'sqlState' => '23514',
                'constraint' => 'audit_segments_successor_check',
            ],
            [
                'name' => 'duplicate incoming successor',
                'setup' => [$successorSegment, $closedSegment],
                'mutation' => $duplicateIncomingSegment,
                'probe' => "SELECT segment_id::text AS segment_id, successor_segment_id::text AS successor_segment_id FROM {$segments} ORDER BY segment_id",
                'sqlState' => '23505',
                'constraint' => 'audit_segments_successor_uq',
            ],
            [
                'name' => 'global segment id unique',
                'setup' => [$tenantSegment],
                'mutation' =>
                    "INSERT INTO {$segments} (partition_id, segment_id, state, first_sequence, opened_at) VALUES ('tenant:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '"
                        . self::SEGMENT_ID
                        . "', 'open', 1, '2026-08-30 00:00:00+00')",
                'probe' => "SELECT segment_id::text AS segment_id, count(*)::text AS count FROM {$segments} GROUP BY segment_id ORDER BY segment_id",
                'sqlState' => '23505',
                'constraint' => 'audit_segments_segment_id_uq',
            ],
            [
                'name' => 'segment boundary iff',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$segments} SET final_sequence = NULL WHERE segment_id = '" . self::SEGMENT_ID . "'",
                'probe' => "SELECT segment_id::text AS segment_id, final_sequence::text AS final_sequence, head_record_hash FROM {$segments} ORDER BY segment_id",
                'sqlState' => '23514',
                'constraint' => 'audit_segments_boundary_check',
            ],
            [
                'name' => 'record tenant pair',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET tenant_type = 'account' WHERE record_id = '" . self::RECORD_ID . "'",
                'probe' => "SELECT record_id::text AS record_id, tenant_type, tenant_id FROM {$records} ORDER BY record_id",
                'sqlState' => '23514',
                'constraint' => 'audit_records_partition_tenant_check',
            ],
            [
                'name' => 'record BOPD magic',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET protected_body = decode('00000000' || repeat('42', 49), 'hex') WHERE record_id = '"
                        . self::RECORD_ID
                        . "'",
                'probe' => "SELECT record_id::text AS record_id, encode(protected_body, 'hex') AS protected_body FROM {$records} ORDER BY record_id",
                'sqlState' => '23514',
                'constraint' => 'audit_records_body_check',
            ],
            [
                'name' => 'record BOPD minimum',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET protected_body = decode('424f5044', 'hex') WHERE record_id = '"
                        . self::RECORD_ID
                        . "'",
                'probe' => "SELECT record_id::text AS record_id, octet_length(protected_body)::text AS body_length FROM {$records} ORDER BY record_id",
                'sqlState' => '23514',
                'constraint' => 'audit_records_body_check',
            ],
            [
                'name' => 'record BOPD maximum plus one',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET protected_body = decode('424f5044' || repeat('42', 1048753), 'hex') WHERE record_id = '"
                        . self::RECORD_ID
                        . "'",
                'probe' => "SELECT record_id::text AS record_id, octet_length(protected_body)::text AS body_length FROM {$records} ORDER BY record_id",
                'sqlState' => '23514',
                'constraint' => 'audit_records_body_check',
            ],
            [
                'name' => 'record action control character',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET action_type = 'action' || chr(10) WHERE record_id = '"
                        . self::RECORD_ID
                        . "'",
                'probe' => "SELECT record_id::text AS record_id, action_type, resource_type, resource_id FROM {$records} ORDER BY record_id",
                'sqlState' => '23514',
                'constraint' => 'audit_records_action_check',
            ],
            [
                'name' => 'record resource type control character',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET resource_type = 'resource' || chr(9) WHERE record_id = '"
                        . self::RECORD_ID
                        . "'",
                'probe' => "SELECT record_id::text AS record_id, action_type, resource_type, resource_id FROM {$records} ORDER BY record_id",
                'sqlState' => '23514',
                'constraint' => 'audit_records_resource_check',
            ],
            [
                'name' => 'record resource id control character',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET resource_id = 'id' || chr(13) WHERE record_id = '" . self::RECORD_ID . "'",
                'probe' => "SELECT record_id::text AS record_id, action_type, resource_type, resource_id FROM {$records} ORDER BY record_id",
                'sqlState' => '23514',
                'constraint' => 'audit_records_resource_check',
            ],
            [
                'name' => 'record category',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET event_category = 'unknown' WHERE record_id = '" . self::RECORD_ID . "'",
                'probe' => "SELECT record_id::text AS record_id, event_category FROM {$records} ORDER BY record_id",
                'sqlState' => '23514',
                'constraint' => 'audit_records_category_check',
            ],
            [
                'name' => 'record action lower bound',
                'setup' => [],
                'mutation' => "UPDATE {$records} SET action_type = '' WHERE record_id = '" . self::RECORD_ID . "'",
                'probe' => "SELECT record_id::text AS record_id, action_type FROM {$records} ORDER BY record_id",
                'sqlState' => '23514',
                'constraint' => 'audit_records_action_check',
            ],
            [
                'name' => 'record resource lower bound',
                'setup' => [],
                'mutation' => "UPDATE {$records} SET resource_id = '' WHERE record_id = '" . self::RECORD_ID . "'",
                'probe' => "SELECT record_id::text AS record_id, resource_id FROM {$records} ORDER BY record_id",
                'sqlState' => '23514',
                'constraint' => 'audit_records_resource_check',
            ],
            [
                'name' => 'record action maximum plus one',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET action_type = repeat('a', 257) WHERE record_id = '" . self::RECORD_ID . "'",
                'probe' => "SELECT record_id::text AS record_id, action_type FROM {$records} ORDER BY record_id",
                'sqlState' => '22001',
                'constraint' => '',
            ],
            [
                'name' => 'record resource type maximum plus one',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET resource_type = repeat('a', 257) WHERE record_id = '"
                        . self::RECORD_ID
                        . "'",
                'probe' => "SELECT record_id::text AS record_id, resource_type FROM {$records} ORDER BY record_id",
                'sqlState' => '22001',
                'constraint' => '',
            ],
            [
                'name' => 'record resource id maximum plus one',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET resource_id = repeat('a', 257) WHERE record_id = '" . self::RECORD_ID . "'",
                'probe' => "SELECT record_id::text AS record_id, resource_id FROM {$records} ORDER BY record_id",
                'sqlState' => '22001',
                'constraint' => '',
            ],
            [
                'name' => 'record segment foreign key',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$records} SET segment_id = '01941f29-7c00-7000-8000-000000000099' WHERE record_id = '"
                        . self::RECORD_ID
                        . "'",
                'probe' => "SELECT record_id::text AS record_id, segment_id::text AS segment_id FROM {$records} ORDER BY record_id",
                'sqlState' => '23503',
                'constraint' => 'audit_records_segment_fk',
            ],
            [
                'name' => 'record global id unique',
                'setup' => [$tenantSegment, $baseRecord],
                'mutation' =>
                    "INSERT INTO {$records} (schema_version, kind, record_id, partition_id, tenant_type, tenant_id, segment_id, sequence, previous_record_hash, content_hash, record_hash, recorded_at, event_category, protected_body, action_type, resource_type, resource_id) VALUES (1, 'audit_stored_record', '"
                        . self::RECORD_ID
                        . "', 'tenant:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'account', 'tenant-a', '01941f29-7c00-7000-8000-000000000003', 1, NULL, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd', '2026-08-30 00:00:00+00', 'business', {$body}, 'action', 'resource', 'id')",
                'probe' => "SELECT record_id::text AS record_id, count(*)::text AS count FROM {$records} GROUP BY record_id ORDER BY record_id",
                'sqlState' => '23505',
                'constraint' => 'audit_records_record_id_uq',
            ],
            [
                'name' => 'record partition sequence unique',
                'setup' => [],
                'mutation' =>
                    "INSERT INTO {$records} (schema_version, kind, record_id, partition_id, tenant_type, tenant_id, segment_id, sequence, previous_record_hash, content_hash, record_hash, recorded_at, event_category, protected_body, action_type, resource_type, resource_id) VALUES (1, 'audit_stored_record', '01941f29-7c00-7000-8000-000000000098', 'system:global', NULL, NULL, '"
                        . self::SEGMENT_ID
                        . "', 1, NULL, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee', '2026-08-30 00:00:00+00', 'business', {$body}, 'action', 'resource', 'id')",
                'probe' => "SELECT partition_id, sequence::text AS sequence, count(*)::text AS count FROM {$records} GROUP BY partition_id, sequence ORDER BY partition_id, sequence",
                'sqlState' => '23505',
                'constraint' => 'audit_records_partition_sequence_uq',
            ],
            [
                'name' => 'record partition hash unique',
                'setup' => [],
                'mutation' =>
                    "INSERT INTO {$records} (schema_version, kind, record_id, partition_id, tenant_type, tenant_id, segment_id, sequence, previous_record_hash, content_hash, record_hash, recorded_at, event_category, protected_body, action_type, resource_type, resource_id) VALUES (1, 'audit_stored_record', '01941f29-7c00-7000-8000-000000000099', 'system:global', NULL, NULL, '"
                        . self::SEGMENT_ID
                        . "', 2, '"
                        . self::HASH
                        . "', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', '"
                        . self::HASH
                        . "', '2026-08-30 00:00:00+00', 'business', {$body}, 'action', 'resource', 'id')",
                'probe' => "SELECT partition_id, record_hash, count(*)::text AS count FROM {$records} GROUP BY partition_id, record_hash ORDER BY partition_id, record_hash",
                'sqlState' => '23505',
                'constraint' => 'audit_records_partition_hash_uq',
            ],
            [
                'name' => 'partition active segment foreign key',
                'setup' => [],
                'mutation' => "UPDATE {$state} SET active_segment_id = '01941f29-7c00-7000-8000-000000000099' WHERE partition_id = 'system:global'",
                'probe' => "SELECT partition_id, active_segment_id::text AS active_segment_id FROM {$state} ORDER BY partition_id",
                'sqlState' => '23503',
                'constraint' => 'audit_partition_state_active_segment_fk',
            ],
            [
                'name' => 'segment successor foreign key',
                'setup' => [],
                'mutation' =>
                    "UPDATE {$segments} SET state = 'closed', closed_at = '2026-08-30 00:00:01+00', successor_segment_id = '01941f29-7c00-7000-8000-000000000099', close_fencing_token = '"
                        . self::TOKEN
                        . "' WHERE segment_id = '"
                        . self::SEGMENT_ID
                        . "'",
                'probe' => "SELECT segment_id::text AS segment_id, state, successor_segment_id::text AS successor_segment_id FROM {$segments} ORDER BY segment_id",
                'sqlState' => '23503',
                'constraint' => 'audit_segments_successor_fk',
            ],
        ];
    }

    private function createRealSchema(Connection $connection, PostgreSqlAuditSchema $schema): void
    {
        $connection->executeStatement('DROP SCHEMA IF EXISTS "' . $schema->schemaName() . '" CASCADE');
        $connection->executeStatement('CREATE SCHEMA "' . $schema->schemaName() . '"');
        foreach ($schema->statements() as $statement) {
            $connection->executeStatement($statement);
        }
    }

    private function dropRealSchema(Connection $connection, PostgreSqlAuditSchema $schema): void
    {
        try {
            $connection->executeStatement('DROP SCHEMA IF EXISTS "' . $schema->schemaName() . '" CASCADE');
        } finally {
            $connection->close();
        }
    }

    private function seedRealRows(Connection $connection, PostgreSqlAuditSchema $schema): void
    {
        $this->insertSegment($connection, $schema, self::SYSTEM_PARTITION, self::SEGMENT_ID, 1, 1, self::HASH);
        $this->insertState($connection, $schema, self::SYSTEM_PARTITION, null, null, self::SEGMENT_ID, 1, self::HASH);
        $connection->executeStatement($this->recordInsertSql(
            $schema,
            self::SYSTEM_PARTITION,
            self::RECORD_ID,
            self::SEGMENT_ID,
            1,
            null,
            self::HASH,
            self::HASH,
            null,
            null,
            "decode('424f5044' || repeat('42', 49), 'hex')",
        ));
    }

    private function insertState(
        Connection $connection,
        PostgreSqlAuditSchema $schema,
        string $partition,
        ?string $tenantType,
        ?string $tenantId,
        string $segmentId,
        int $sequence,
        ?string $headHash,
    ): void {
        $type = $tenantType === null ? 'NULL' : "'{$tenantType}'";
        $id = $tenantId === null ? 'NULL' : "'{$tenantId}'";
        $head = $headHash === null ? 'NULL' : "'{$headHash}'";
        $connection->executeStatement(
            "INSERT INTO {$schema->partitionStateTable()} (partition_id, tenant_type, tenant_id, active_segment_id, last_sequence, head_record_hash, last_assigned_recorded_at, state_version) VALUES ('{$partition}', {$type}, {$id}, '{$segmentId}', {$sequence}, {$head}, '2026-08-30 00:00:00+00', 1)",
        );
    }

    private function insertSegment(
        Connection $connection,
        PostgreSqlAuditSchema $schema,
        string $partition,
        string $segmentId,
        int $firstSequence,
        ?int $finalSequence,
        ?string $headHash,
    ): void {
        $connection->executeStatement($this->segmentInsertSql(
            $schema,
            $partition,
            $segmentId,
            $firstSequence,
            $finalSequence,
            $headHash,
        ));
    }

    private function segmentInsertSql(
        PostgreSqlAuditSchema $schema,
        string $partition,
        string $segmentId,
        int $firstSequence,
        ?int $finalSequence,
        ?string $headHash,
        ?string $predecessor = null,
        ?string $successor = null,
        string $state = 'open',
    ): string {
        $final = $finalSequence === null ? 'NULL' : (string) $finalSequence;
        $head = $headHash === null ? 'NULL' : "'{$headHash}'";
        $predecessorValue = $predecessor === null ? 'NULL' : "'{$predecessor}'";
        $successorValue = $successor === null ? 'NULL' : "'{$successor}'";
        $closed = $state === 'open' ? 'NULL' : "'2026-08-30 00:00:01+00'";
        $token = $state === 'open' ? 'NULL' : "'" . self::TOKEN . "'";

        return "INSERT INTO {$schema->segmentsTable()} (partition_id, segment_id, state, first_sequence, final_sequence, head_record_hash, predecessor_record_hash, opened_at, closed_at, successor_segment_id, close_fencing_token, state_version) VALUES ('{$partition}', '{$segmentId}', '{$state}', {$firstSequence}, {$final}, {$head}, {$predecessorValue}, '2026-08-30 00:00:00+00', {$closed}, {$successorValue}, {$token}, 1)";
    }

    private function recordInsertSql(
        PostgreSqlAuditSchema $schema,
        string $partition,
        string $recordId,
        string $segmentId,
        int $sequence,
        ?string $previousHash,
        string $contentHash,
        string $recordHash,
        ?string $tenantType,
        ?string $tenantId,
        string $body,
    ): string {
        $previous = $previousHash === null ? 'NULL' : "'{$previousHash}'";
        $type = $tenantType === null ? 'NULL' : "'{$tenantType}'";
        $id = $tenantId === null ? 'NULL' : "'{$tenantId}'";

        return "INSERT INTO {$schema->recordsTable()} (schema_version, kind, record_id, partition_id, tenant_type, tenant_id, segment_id, sequence, previous_record_hash, content_hash, record_hash, recorded_at, event_category, protected_body, action_type, resource_type, resource_id) VALUES (1, 'audit_stored_record', '{$recordId}', '{$partition}', {$type}, {$id}, '{$segmentId}', {$sequence}, {$previous}, '{$contentHash}', '{$recordHash}', '2026-08-30 00:00:00+00', 'business', {$body}, 'action', 'resource', 'id')";
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
        if ($constraint !== '') {
            self::assertStringContainsString($constraint, implode(' | ', $messages));
        }
    }

    private function assertInvalidSchemaName(string $name): void
    {
        try {
            new PostgreSqlAuditSchema($name);
        } catch (InvalidArgumentException $exception) {
            self::assertSame('PostgreSQL schema name must be a safe identifier.', $exception->getMessage());
            return;
        }

        self::fail('Invalid PostgreSQL schema name was accepted.');
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

    /** @return list<array{name:string,definition:string}> */
    private function expectedColumns(string $table): array
    {
        return match ($table) {
            'segments' => [
                ['name' => 'partition_id', 'definition' => 'varchar(71) not null'],
                ['name' => 'segment_id', 'definition' => 'uuid not null'],
                ['name' => 'state', 'definition' => 'text not null'],
                ['name' => 'first_sequence', 'definition' => 'bigint not null'],
                ['name' => 'final_sequence', 'definition' => 'bigint null'],
                ['name' => 'head_record_hash', 'definition' => 'char(64) null'],
                ['name' => 'predecessor_record_hash', 'definition' => 'char(64) null'],
                ['name' => 'opened_at', 'definition' => 'timestamptz not null'],
                ['name' => 'closed_at', 'definition' => 'timestamptz null'],
                ['name' => 'successor_segment_id', 'definition' => 'uuid null'],
                ['name' => 'close_fencing_token', 'definition' => 'varchar(71) null'],
                ['name' => 'state_version', 'definition' => 'bigint not null default 1'],
            ],
            'partition_state' => [
                ['name' => 'partition_id', 'definition' => 'varchar(71) not null'],
                ['name' => 'tenant_type', 'definition' => 'varchar(256) null'],
                ['name' => 'tenant_id', 'definition' => 'varchar(256) null'],
                ['name' => 'active_segment_id', 'definition' => 'uuid not null'],
                ['name' => 'last_sequence', 'definition' => 'bigint not null default 0'],
                ['name' => 'head_record_hash', 'definition' => 'char(64) null'],
                ['name' => 'last_assigned_recorded_at', 'definition' => 'timestamptz not null'],
                ['name' => 'state_version', 'definition' => 'bigint not null default 1'],
            ],
            'records' => [
                ['name' => 'schema_version', 'definition' => 'smallint not null'],
                ['name' => 'kind', 'definition' => 'text not null'],
                ['name' => 'record_id', 'definition' => 'uuid not null'],
                ['name' => 'partition_id', 'definition' => 'varchar(71) not null'],
                ['name' => 'tenant_type', 'definition' => 'varchar(256) null'],
                ['name' => 'tenant_id', 'definition' => 'varchar(256) null'],
                ['name' => 'segment_id', 'definition' => 'uuid not null'],
                ['name' => 'sequence', 'definition' => 'bigint not null'],
                ['name' => 'previous_record_hash', 'definition' => 'char(64) null'],
                ['name' => 'content_hash', 'definition' => 'char(64) not null'],
                ['name' => 'record_hash', 'definition' => 'char(64) not null'],
                ['name' => 'recorded_at', 'definition' => 'timestamptz not null'],
                ['name' => 'event_category', 'definition' => 'text not null'],
                ['name' => 'protected_body', 'definition' => 'bytea not null'],
                ['name' => 'action_type', 'definition' => 'varchar(256) not null'],
                ['name' => 'resource_type', 'definition' => 'varchar(256) not null'],
                ['name' => 'resource_id', 'definition' => 'varchar(256) not null'],
            ],
            default => throw new \InvalidArgumentException('Unknown table fixture.'),
        };
    }

    private function expectedControlFree(string $column): string
    {
        $characters = implode(' || ', array_map(
            static fn(int $codePoint): string => 'chr(' . $codePoint . ')',
            array_values(array_filter(
                $this->unicodeCcCfCodePoints(),
                static fn(int $codePoint): bool => $codePoint !== 0,
            )),
        ));

        return "translate({$column}, {$characters}, '') = {$column}";
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

    /** @return array<string,string> */
    private function expectedConstraints(): array
    {
        return [
            'audit_segments_pk' => 'primary key (partition_id, segment_id)',
            'audit_segments_segment_id_uq' => 'unique (segment_id)',
            'audit_segments_state_check' => "check (state in ('open', 'closed', 'sealed'))",
            'audit_segments_segment_id_check' => "check (partition_id ~ '^(system:global|tenant:[0-9a-f]{64})$' and segment_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')",
            'audit_segments_sequence_check' => 'check (first_sequence between 1 and 9007199254740991 and (final_sequence is null or final_sequence between first_sequence and 9007199254740991))',
            'audit_segments_boundary_check' => "check (((first_sequence = 1 and predecessor_record_hash is null) or (first_sequence > 1 and predecessor_record_hash is not null)) and ((final_sequence is null and head_record_hash is null) or (final_sequence is not null and head_record_hash is not null)) and (head_record_hash is null or head_record_hash ~ '^[0-9a-f]{64}$') and (predecessor_record_hash is null or predecessor_record_hash ~ '^[0-9a-f]{64}$'))",
            'audit_segments_lifecycle_check' => "check ((state = 'open' and closed_at is null and successor_segment_id is null and close_fencing_token is null) or (state in ('closed', 'sealed') and final_sequence is not null and head_record_hash is not null and closed_at is not null and successor_segment_id is not null and close_fencing_token is not null))",
            'audit_segments_time_check' => 'check (closed_at is null or closed_at >= opened_at)',
            'audit_segments_successor_check' => "check ((successor_segment_id is null or successor_segment_id <> segment_id) and (close_fencing_token is null or close_fencing_token ~ '^aft:v1:[0-9a-f]{64}$'))",
            'audit_segments_state_version_check' => 'check (state_version between 1 and 9007199254740991)',
            'audit_segments_successor_fk' => 'foreign key (partition_id, successor_segment_id) references {schema}."audit_segments" (partition_id, segment_id) deferrable initially deferred',
            'audit_partition_state_pk' => 'primary key (partition_id)',
            'audit_partition_state_partition_check' => "check (partition_id ~ '^(system:global|tenant:[0-9a-f]{64})$')",
            'audit_partition_state_tenant_pair_check' => "check ((partition_id = 'system:global' and tenant_type is null and tenant_id is null) or (partition_id like 'tenant:%' and tenant_type is not null and tenant_id is not null))",
            'audit_partition_state_tenant_bounds_check' => "check ((tenant_type is null and tenant_id is null) or (octet_length(tenant_type) between 1 and 256 and octet_length(tenant_id) between 1 and 256 and {$this->expectedControlFree(
                'tenant_type',
            )} and {$this->expectedControlFree('tenant_id')}))",
            'audit_partition_state_sequence_check' => 'check (last_sequence between 0 and 9007199254740991)',
            'audit_partition_state_head_check' => "check ((last_sequence = 0 and head_record_hash is null) or (last_sequence > 0 and head_record_hash is not null and head_record_hash ~ '^[0-9a-f]{64}$'))",
            'audit_partition_state_version_check' => 'check (state_version between 1 and 9007199254740991)',
            'audit_partition_state_active_segment_fk' => 'foreign key (partition_id, active_segment_id) references {schema}."audit_segments" (partition_id, segment_id) deferrable initially deferred',
            'audit_records_pk' => 'primary key (partition_id, record_id)',
            'audit_records_schema_kind_check' => "check (schema_version = 1 and kind = 'audit_stored_record')",
            'audit_records_record_id_check' => "check (record_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')",
            'audit_records_partition_tenant_check' => "check (partition_id ~ '^(system:global|tenant:[0-9a-f]{64})$' and ((partition_id = 'system:global' and tenant_type is null and tenant_id is null) or (partition_id like 'tenant:%' and tenant_type is not null and tenant_id is not null and octet_length(tenant_type) between 1 and 256 and octet_length(tenant_id) between 1 and 256 and {$this->expectedControlFree(
                'tenant_type',
            )} and {$this->expectedControlFree('tenant_id')})))",
            'audit_records_sequence_check' => 'check (sequence between 1 and 9007199254740991)',
            'audit_records_previous_check' => 'check ((sequence = 1 and previous_record_hash is null) or (sequence > 1 and previous_record_hash is not null))',
            'audit_records_hash_check' => "check (content_hash ~ '^[0-9a-f]{64}$' and record_hash ~ '^[0-9a-f]{64}$' and (previous_record_hash is null or previous_record_hash ~ '^[0-9a-f]{64}$'))",
            'audit_records_category_check' => "check (event_category in ('business', 'security', 'access', 'configuration', 'control', 'retention', 'replay', 'rotation', 'outbox'))",
            'audit_records_body_check' => "check (octet_length(protected_body) between 53 and 1048756 and substring(protected_body from 1 for 4) = decode('424f5044', 'hex'))",
            'audit_records_action_check' => "check (octet_length(action_type) between 1 and 256 and {$this->expectedControlFree(
                'action_type',
            )})",
            'audit_records_resource_check' => "check (octet_length(resource_type) between 1 and 256 and {$this->expectedControlFree(
                'resource_type',
            )} and octet_length(resource_id) between 1 and 256 and {$this->expectedControlFree('resource_id')})",
            'audit_records_segment_fk' => 'foreign key (partition_id, segment_id) references {schema}."audit_segments" (partition_id, segment_id)',
            'audit_records_record_id_uq' => 'unique (record_id)',
            'audit_records_partition_sequence_uq' => 'unique (partition_id, sequence)',
            'audit_records_partition_hash_uq' => 'unique (partition_id, record_hash)',
        ];
    }

    /** @return list<string> */
    private function expectedIndexes(): array
    {
        return [
            'create unique index audit_segments_one_open_idx on {schema}."audit_segments" (partition_id) where state = \'open\'',
            'create unique index audit_segments_successor_uq on {schema}."audit_segments" (partition_id, successor_segment_id) where successor_segment_id is not null',
            'create index audit_records_segment_sequence_idx on {schema}."audit_records" (partition_id, segment_id, sequence)',
            'create index audit_records_recorded_at_idx on {schema}."audit_records" (partition_id, recorded_at, sequence)',
            'create index audit_records_action_idx on {schema}."audit_records" (partition_id, action_type, recorded_at, sequence)',
            'create index audit_records_resource_idx on {schema}."audit_records" (partition_id, resource_type, resource_id, recorded_at, sequence)',
        ];
    }

    /** @return list<array{name:string,definition:string}> */
    private function columns(string $statement): array
    {
        $parts = $this->definitions($statement);
        $columns = [];
        foreach ($parts as $part) {
            $normalized = $this->normalizeSql($part);
            if (str_starts_with($normalized, 'constraint ')) {
                break;
            }
            if (preg_match('/^([a-z_]+) (.+)$/', $normalized, $matches) !== 1) {
                self::fail('Unable to parse a PostgreSQL column definition.');
            }
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
            if (preg_match('/^constraint ([a-z_]+) (.+)$/', $normalized, $matches) !== 1) {
                continue;
            }
            $constraints[$matches[1]] = $matches[2];
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

        return str_replace('"blackops_audit"', '{schema}', trim($normalized));
    }

    private function tableName(string $statement): string
    {
        $normalized = $this->normalizeSql($statement);
        self::assertMatchesRegularExpression('/^create table \{schema\}\."([a-z_]+)"/', $normalized, $statement);

        return (string) preg_replace('/^create table \{schema\}\."([a-z_]+)".*$/', '$1', $normalized);
    }
}

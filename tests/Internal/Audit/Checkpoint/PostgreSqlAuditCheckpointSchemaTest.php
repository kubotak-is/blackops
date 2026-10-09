<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointSchema;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PostgreSqlAuditCheckpointSchemaTest extends TestCase
{
    public function testUsesConfiguredQualifiedTablesAndExactImmutableDdlShape(): void
    {
        $schema = new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema('blackops_audit'));
        $statements = $schema->statements();

        self::assertSame('blackops_audit', $schema->schemaName());
        self::assertSame('"blackops_audit"."audit_checkpoint_preparations"', $schema->preparationsTable());
        self::assertSame('"blackops_audit"."audit_checkpoints"', $schema->checkpointsTable());
        self::assertCount(2, $statements);
        self::assertStringStartsWith('CREATE TABLE "blackops_audit"."audit_checkpoint_preparations"', $statements[0]);
        self::assertStringStartsWith('CREATE TABLE "blackops_audit"."audit_checkpoints"', $statements[1]);
        self::assertStringNotContainsString('IF NOT EXISTS', implode("\n", $statements));
        self::assertStringNotContainsString('CREATE INDEX', implode("\n", $statements));

        self::assertStringContainsString('checkpoint_id uuid NOT NULL', $statements[0]);
        self::assertStringContainsString('partition_id varchar(71) NOT NULL', $statements[0]);
        self::assertStringContainsString('segment_id uuid NOT NULL', $statements[0]);
        self::assertStringContainsString('closed_state_version bigint NOT NULL', $statements[0]);
        self::assertStringContainsString('close_fencing_token varchar(71) NOT NULL', $statements[0]);
        self::assertStringContainsString('unsigned_checkpoint bytea NOT NULL', $statements[0]);
        self::assertStringContainsString('signing_key_fingerprint char(64) NOT NULL', $statements[0]);
        foreach ([
            'audit_checkpoint_preparations_pk',
            'audit_checkpoint_preparations_segment_uq',
            'audit_checkpoint_preparations_segment_fk',
            'audit_checkpoint_preparations_identity_check',
            'audit_checkpoint_preparations_version_check',
            'audit_checkpoint_preparations_token_check',
            'audit_checkpoint_preparations_bytes_check',
            'audit_checkpoint_preparations_key_check',
        ] as $constraint) {
            self::assertStringContainsString('CONSTRAINT ' . $constraint, $statements[0]);
        }
        self::assertStringContainsString('checkpoint_id::text ~', $statements[0]);
        self::assertStringContainsString('segment_id::text ~', $statements[0]);
        self::assertStringContainsString('closed_state_version BETWEEN 2 AND 9007199254740990', $statements[0]);
        self::assertStringContainsString('octet_length(unsigned_checkpoint) BETWEEN 1 AND 1048575', $statements[0]);
        self::assertStringContainsString(
            'REFERENCES "blackops_audit"."audit_segments" (partition_id, segment_id)',
            $statements[0],
        );

        self::assertSame(
            [
                'checkpoint_id uuid NOT NULL',
                'checkpoint_hash char(64) NOT NULL',
                'complete_bytes bytea NOT NULL',
            ],
            $this->columnDefinitions($statements[1]),
        );
        foreach ([
            'audit_checkpoints_pk',
            'audit_checkpoints_preparation_fk',
            'audit_checkpoints_hash_uq',
            'audit_checkpoints_hash_check',
            'audit_checkpoints_bytes_check',
        ] as $constraint) {
            self::assertStringContainsString('CONSTRAINT ' . $constraint, $statements[1]);
        }
        self::assertStringContainsString('checkpoint_hash ~ \'^[0-9a-f]{64}$\'', $statements[1]);
        self::assertStringContainsString('octet_length(complete_bytes) BETWEEN 1 AND 1048575', $statements[1]);
    }

    public function testSchemaNameValidationHappensBeforeCheckpointNamesAreBuilt(): void
    {
        foreach (['BlackOps', 'a' . str_repeat('b', 63), 'é', '"blackops"', "blackops\n"] as $invalid) {
            try {
                new PostgreSqlAuditCheckpointSchema(new PostgreSqlMigrationSchema($invalid));
                self::fail('invalid schema name was accepted');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('PostgreSQL schema name must be a safe identifier.', $exception->getMessage());
            }
        }
    }

    public function testIdentityAndImmutableDdlControlsCoverBothUuidColumnsAndNoMutationPath(): void
    {
        $statements = new PostgreSqlAuditCheckpointSchema(
            new PostgreSqlMigrationSchema('blackops_audit_checkpoint_f2'),
        )->statements();
        $preparation = $statements[0];
        $complete = $statements[1];

        self::assertMatchesRegularExpression('/checkpoint_id::text\s*~\s*\x27\^\[0-9a-f\]\{8\}/', $preparation);
        self::assertMatchesRegularExpression('/segment_id::text\s*~\s*\x27\^\[0-9a-f\]\{8\}/', $preparation);
        self::assertStringContainsString('closed_state_version BETWEEN 2 AND 9007199254740990', $preparation);
        self::assertStringContainsString('octet_length(unsigned_checkpoint) BETWEEN 1 AND 1048575', $preparation);
        self::assertStringContainsString('octet_length(complete_bytes) BETWEEN 1 AND 1048575', $complete);
        self::assertStringContainsString(
            'REFERENCES "blackops_audit_checkpoint_f2"."audit_checkpoint_preparations"',
            $complete,
        );

        $ddl = strtolower(implode("\n", $statements));
        foreach (['update ', 'delete ', 'upsert', 'insert or replace', 'signer', 'private_key'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $ddl);
        }
    }

    /** @return list<string> */
    private function columnDefinitions(string $statement): array
    {
        $definitions = [];
        foreach (['checkpoint_id', 'checkpoint_hash', 'complete_bytes'] as $column) {
            $offset = strpos($statement, $column . ' ');
            self::assertNotFalse($offset);
            $lineEnd = strpos($statement, ',', $offset);
            self::assertNotFalse($lineEnd);
            $definitions[] = trim(substr($statement, $offset, $lineEnd - $offset));
        }
        return $definitions;
    }
}

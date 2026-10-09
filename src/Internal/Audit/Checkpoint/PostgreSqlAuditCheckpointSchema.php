<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;

/**
 * Names and DDL for the immutable checkpoint persistence pair.
 *
 * The schema object deliberately owns no connection.  Migrations and the
 * checkpoint store can therefore use the same validated, quoted identifier.
 */
final readonly class PostgreSqlAuditCheckpointSchema
{
    public function __construct(
        private PostgreSqlMigrationSchema $schema,
    ) {}

    public function schemaName(): string
    {
        return $this->schema->name();
    }

    public function preparationsTable(): string
    {
        return $this->schema->table('audit_checkpoint_preparations');
    }

    public function checkpointsTable(): string
    {
        return $this->schema->table('audit_checkpoints');
    }

    /** @return list<string> */
    public function statements(): array
    {
        $preparations = $this->preparationsTable();
        $segments = $this->schema->table('audit_segments');
        $checkpoints = $this->checkpointsTable();

        return [
            "CREATE TABLE {$preparations} (
                checkpoint_id uuid NOT NULL,
                partition_id varchar(71) NOT NULL,
                segment_id uuid NOT NULL,
                closed_state_version bigint NOT NULL,
                close_fencing_token varchar(71) NOT NULL,
                unsigned_checkpoint bytea NOT NULL,
                signing_key_fingerprint char(64) NOT NULL,
                CONSTRAINT audit_checkpoint_preparations_pk PRIMARY KEY (checkpoint_id),
                CONSTRAINT audit_checkpoint_preparations_segment_uq UNIQUE (segment_id),
                CONSTRAINT audit_checkpoint_preparations_segment_fk FOREIGN KEY (partition_id, segment_id)
                    REFERENCES {$segments} (partition_id, segment_id),
                CONSTRAINT audit_checkpoint_preparations_identity_check CHECK (
                    partition_id ~ '^(system:global|tenant:[0-9a-f]{64})$'
                    AND checkpoint_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'
                    AND segment_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'
                ),
                CONSTRAINT audit_checkpoint_preparations_version_check CHECK (
                    closed_state_version BETWEEN 2 AND 9007199254740990
                ),
                CONSTRAINT audit_checkpoint_preparations_token_check CHECK (
                    close_fencing_token ~ '^aft:v1:[0-9a-f]{64}$'
                ),
                CONSTRAINT audit_checkpoint_preparations_bytes_check CHECK (
                    octet_length(unsigned_checkpoint) BETWEEN 1 AND 1048575
                ),
                CONSTRAINT audit_checkpoint_preparations_key_check CHECK (
                    signing_key_fingerprint ~ '^[0-9a-f]{64}$'
                )
            )",
            "CREATE TABLE {$checkpoints} (
                checkpoint_id uuid NOT NULL,
                checkpoint_hash char(64) NOT NULL,
                complete_bytes bytea NOT NULL,
                CONSTRAINT audit_checkpoints_pk PRIMARY KEY (checkpoint_id),
                CONSTRAINT audit_checkpoints_preparation_fk FOREIGN KEY (checkpoint_id)
                    REFERENCES {$preparations} (checkpoint_id),
                CONSTRAINT audit_checkpoints_hash_uq UNIQUE (checkpoint_hash),
                CONSTRAINT audit_checkpoints_hash_check CHECK (
                    checkpoint_hash ~ '^[0-9a-f]{64}$'
                ),
                CONSTRAINT audit_checkpoints_bytes_check CHECK (
                    octet_length(complete_bytes) BETWEEN 1 AND 1048575
                )
            )",
        ];
    }
}

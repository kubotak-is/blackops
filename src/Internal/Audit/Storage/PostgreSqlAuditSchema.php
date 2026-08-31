<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Storage;

use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;

final readonly class PostgreSqlAuditSchema
{
    private PostgreSqlMigrationSchema $schema;

    public function __construct(string $schema = 'blackops')
    {
        $this->schema = new PostgreSqlMigrationSchema($schema);
    }

    public function schemaName(): string
    {
        return $this->schema->name();
    }

    public function partitionStateTable(): string
    {
        return $this->schema->table('audit_partition_state');
    }

    public function segmentsTable(): string
    {
        return $this->schema->table('audit_segments');
    }

    public function recordsTable(): string
    {
        return $this->schema->table('audit_records');
    }

    /** @return list<string> */
    public function statements(): array
    {
        $segments = $this->segmentsTable();
        $partitionState = $this->partitionStateTable();
        $records = $this->recordsTable();

        return [
            $this->segmentsStatement($segments),
            $this->partitionStateStatement($partitionState, $segments),
            $this->recordsStatement($records, $segments),
            "CREATE UNIQUE INDEX audit_segments_one_open_idx ON {$segments} (partition_id) WHERE state = 'open'",
            'CREATE UNIQUE INDEX audit_segments_successor_uq ON '
                . "{$segments} (partition_id, successor_segment_id) WHERE successor_segment_id IS NOT NULL",
            "CREATE INDEX audit_records_segment_sequence_idx ON {$records} (partition_id, segment_id, sequence)",
            "CREATE INDEX audit_records_recorded_at_idx ON {$records} (partition_id, recorded_at, sequence)",
            "CREATE INDEX audit_records_action_idx ON {$records} (partition_id, action_type, recorded_at, sequence)",
            "CREATE INDEX audit_records_resource_idx ON {$records} (partition_id, resource_type, resource_id, recorded_at, sequence)",
        ];
    }

    private function segmentsStatement(string $table): string
    {
        return "CREATE TABLE {$table} (
            partition_id varchar(71) NOT NULL,
            segment_id uuid NOT NULL,
            state text NOT NULL,
            first_sequence bigint NOT NULL,
            final_sequence bigint NULL,
            head_record_hash char(64) NULL,
            predecessor_record_hash char(64) NULL,
            opened_at timestamptz NOT NULL,
            closed_at timestamptz NULL,
            successor_segment_id uuid NULL,
            close_fencing_token varchar(71) NULL,
            state_version bigint NOT NULL DEFAULT 1,
            CONSTRAINT audit_segments_pk PRIMARY KEY (partition_id, segment_id),
            CONSTRAINT audit_segments_segment_id_uq UNIQUE (segment_id),
            CONSTRAINT audit_segments_state_check CHECK (state IN ('open', 'closed', 'sealed')),
            CONSTRAINT audit_segments_segment_id_check CHECK (
                partition_id ~ '^(system:global|tenant:[0-9a-f]{64})$'
                AND
                segment_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'
            ),
            CONSTRAINT audit_segments_sequence_check CHECK (
                first_sequence BETWEEN 1 AND 9007199254740991
                AND (final_sequence IS NULL OR final_sequence BETWEEN first_sequence AND 9007199254740991)
            ),
            CONSTRAINT audit_segments_boundary_check CHECK (
                ((first_sequence = 1 AND predecessor_record_hash IS NULL)
                    OR (first_sequence > 1 AND predecessor_record_hash IS NOT NULL))
                AND ((final_sequence IS NULL AND head_record_hash IS NULL)
                    OR (final_sequence IS NOT NULL AND head_record_hash IS NOT NULL))
                AND (head_record_hash IS NULL OR head_record_hash ~ '^[0-9a-f]{64}$')
                AND (predecessor_record_hash IS NULL OR predecessor_record_hash ~ '^[0-9a-f]{64}$')
            ),
            CONSTRAINT audit_segments_lifecycle_check CHECK (
                (state = 'open'
                    AND closed_at IS NULL
                    AND successor_segment_id IS NULL
                    AND close_fencing_token IS NULL)
                OR (state IN ('closed', 'sealed')
                    AND final_sequence IS NOT NULL
                    AND head_record_hash IS NOT NULL
                    AND closed_at IS NOT NULL
                    AND successor_segment_id IS NOT NULL
                    AND close_fencing_token IS NOT NULL)
            ),
            CONSTRAINT audit_segments_time_check CHECK (closed_at IS NULL OR closed_at >= opened_at),
            CONSTRAINT audit_segments_successor_check CHECK (
                (successor_segment_id IS NULL OR successor_segment_id <> segment_id)
                AND (close_fencing_token IS NULL OR close_fencing_token ~ '^aft:v1:[0-9a-f]{64}$')
            ),
            CONSTRAINT audit_segments_state_version_check CHECK (
                state_version BETWEEN 1 AND 9007199254740991
            ),
            CONSTRAINT audit_segments_successor_fk FOREIGN KEY (partition_id, successor_segment_id)
                REFERENCES {$table} (partition_id, segment_id)
                DEFERRABLE INITIALLY DEFERRED
        )";
    }

    private function partitionStateStatement(string $table, string $segments): string
    {
        $controlCharacters = 'chr(1) || chr(2) || chr(3) || chr(4) || chr(5) || chr(6) || chr(7) || chr(8) || chr(9) || chr(10) || chr(11) || chr(12) || chr(13) || chr(14) || chr(15) || chr(16) || chr(17) || chr(18) || chr(19) || chr(20) || chr(21) || chr(22) || chr(23) || chr(24) || chr(25) || chr(26) || chr(27) || chr(28) || chr(29) || chr(30) || chr(31) || chr(127) || chr(128) || chr(129) || chr(130) || chr(131) || chr(132) || chr(133) || chr(134) || chr(135) || chr(136) || chr(137) || chr(138) || chr(139) || chr(140) || chr(141) || chr(142) || chr(143) || chr(144) || chr(145) || chr(146) || chr(147) || chr(148) || chr(149) || chr(150) || chr(151) || chr(152) || chr(153) || chr(154) || chr(155) || chr(156) || chr(157) || chr(158) || chr(159) || chr(173) || chr(1536) || chr(1537) || chr(1538) || chr(1539) || chr(1540) || chr(1541) || chr(1564) || chr(1757) || chr(1807) || chr(2192) || chr(2193) || chr(2274) || chr(6158) || chr(8203) || chr(8204) || chr(8205) || chr(8206) || chr(8207) || chr(8234) || chr(8235) || chr(8236) || chr(8237) || chr(8238) || chr(8288) || chr(8289) || chr(8290) || chr(8291) || chr(8292) || chr(8294) || chr(8295) || chr(8296) || chr(8297) || chr(8298) || chr(8299) || chr(8300) || chr(8301) || chr(8302) || chr(8303) || chr(65279) || chr(65529) || chr(65530) || chr(65531) || chr(69821) || chr(69837) || chr(78896) || chr(78897) || chr(78898) || chr(78899) || chr(78900) || chr(78901) || chr(78902) || chr(78903) || chr(78904) || chr(78905) || chr(78906) || chr(78907) || chr(78908) || chr(78909) || chr(78910) || chr(78911) || chr(113824) || chr(113825) || chr(113826) || chr(113827) || chr(119155) || chr(119156) || chr(119157) || chr(119158) || chr(119159) || chr(119160) || chr(119161) || chr(119162) || chr(917505) || chr(917536) || chr(917537) || chr(917538) || chr(917539) || chr(917540) || chr(917541) || chr(917542) || chr(917543) || chr(917544) || chr(917545) || chr(917546) || chr(917547) || chr(917548) || chr(917549) || chr(917550) || chr(917551) || chr(917552) || chr(917553) || chr(917554) || chr(917555) || chr(917556) || chr(917557) || chr(917558) || chr(917559) || chr(917560) || chr(917561) || chr(917562) || chr(917563) || chr(917564) || chr(917565) || chr(917566) || chr(917567) || chr(917568) || chr(917569) || chr(917570) || chr(917571) || chr(917572) || chr(917573) || chr(917574) || chr(917575) || chr(917576) || chr(917577) || chr(917578) || chr(917579) || chr(917580) || chr(917581) || chr(917582) || chr(917583) || chr(917584) || chr(917585) || chr(917586) || chr(917587) || chr(917588) || chr(917589) || chr(917590) || chr(917591) || chr(917592) || chr(917593) || chr(917594) || chr(917595) || chr(917596) || chr(917597) || chr(917598) || chr(917599) || chr(917600) || chr(917601) || chr(917602) || chr(917603) || chr(917604) || chr(917605) || chr(917606) || chr(917607) || chr(917608) || chr(917609) || chr(917610) || chr(917611) || chr(917612) || chr(917613) || chr(917614) || chr(917615) || chr(917616) || chr(917617) || chr(917618) || chr(917619) || chr(917620) || chr(917621) || chr(917622) || chr(917623) || chr(917624) || chr(917625) || chr(917626) || chr(917627) || chr(917628) || chr(917629) || chr(917630) || chr(917631)';
        return "CREATE TABLE {$table} (
            partition_id varchar(71) NOT NULL,
            tenant_type varchar(256) NULL,
            tenant_id varchar(256) NULL,
            active_segment_id uuid NOT NULL,
            last_sequence bigint NOT NULL DEFAULT 0,
            head_record_hash char(64) NULL,
            last_assigned_recorded_at timestamptz NOT NULL,
            state_version bigint NOT NULL DEFAULT 1,
            CONSTRAINT audit_partition_state_pk PRIMARY KEY (partition_id),
            CONSTRAINT audit_partition_state_partition_check CHECK (
                partition_id ~ '^(system:global|tenant:[0-9a-f]{64})$'
            ),
            CONSTRAINT audit_partition_state_tenant_pair_check CHECK (
                (partition_id = 'system:global' AND tenant_type IS NULL AND tenant_id IS NULL)
                OR (partition_id LIKE 'tenant:%' AND tenant_type IS NOT NULL AND tenant_id IS NOT NULL)
            ),
            CONSTRAINT audit_partition_state_tenant_bounds_check CHECK (
                (tenant_type IS NULL AND tenant_id IS NULL)
                OR (octet_length(tenant_type) BETWEEN 1 AND 256
                    AND octet_length(tenant_id) BETWEEN 1 AND 256
                    AND translate(tenant_type, {$controlCharacters}, '') = tenant_type
                    AND translate(tenant_id, {$controlCharacters}, '') = tenant_id)
            ),
            CONSTRAINT audit_partition_state_sequence_check CHECK (
                last_sequence BETWEEN 0 AND 9007199254740991
            ),
            CONSTRAINT audit_partition_state_head_check CHECK (
                (last_sequence = 0 AND head_record_hash IS NULL)
                OR (last_sequence > 0 AND head_record_hash IS NOT NULL
                    AND head_record_hash ~ '^[0-9a-f]{64}$')
            ),
            CONSTRAINT audit_partition_state_version_check CHECK (
                state_version BETWEEN 1 AND 9007199254740991
            ),
            CONSTRAINT audit_partition_state_active_segment_fk FOREIGN KEY (partition_id, active_segment_id)
                REFERENCES {$segments} (partition_id, segment_id)
                DEFERRABLE INITIALLY DEFERRED
        )";
    }

    private function recordsStatement(string $table, string $segments): string
    {
        $controlCharacters = 'chr(1) || chr(2) || chr(3) || chr(4) || chr(5) || chr(6) || chr(7) || chr(8) || chr(9) || chr(10) || chr(11) || chr(12) || chr(13) || chr(14) || chr(15) || chr(16) || chr(17) || chr(18) || chr(19) || chr(20) || chr(21) || chr(22) || chr(23) || chr(24) || chr(25) || chr(26) || chr(27) || chr(28) || chr(29) || chr(30) || chr(31) || chr(127) || chr(128) || chr(129) || chr(130) || chr(131) || chr(132) || chr(133) || chr(134) || chr(135) || chr(136) || chr(137) || chr(138) || chr(139) || chr(140) || chr(141) || chr(142) || chr(143) || chr(144) || chr(145) || chr(146) || chr(147) || chr(148) || chr(149) || chr(150) || chr(151) || chr(152) || chr(153) || chr(154) || chr(155) || chr(156) || chr(157) || chr(158) || chr(159) || chr(173) || chr(1536) || chr(1537) || chr(1538) || chr(1539) || chr(1540) || chr(1541) || chr(1564) || chr(1757) || chr(1807) || chr(2192) || chr(2193) || chr(2274) || chr(6158) || chr(8203) || chr(8204) || chr(8205) || chr(8206) || chr(8207) || chr(8234) || chr(8235) || chr(8236) || chr(8237) || chr(8238) || chr(8288) || chr(8289) || chr(8290) || chr(8291) || chr(8292) || chr(8294) || chr(8295) || chr(8296) || chr(8297) || chr(8298) || chr(8299) || chr(8300) || chr(8301) || chr(8302) || chr(8303) || chr(65279) || chr(65529) || chr(65530) || chr(65531) || chr(69821) || chr(69837) || chr(78896) || chr(78897) || chr(78898) || chr(78899) || chr(78900) || chr(78901) || chr(78902) || chr(78903) || chr(78904) || chr(78905) || chr(78906) || chr(78907) || chr(78908) || chr(78909) || chr(78910) || chr(78911) || chr(113824) || chr(113825) || chr(113826) || chr(113827) || chr(119155) || chr(119156) || chr(119157) || chr(119158) || chr(119159) || chr(119160) || chr(119161) || chr(119162) || chr(917505) || chr(917536) || chr(917537) || chr(917538) || chr(917539) || chr(917540) || chr(917541) || chr(917542) || chr(917543) || chr(917544) || chr(917545) || chr(917546) || chr(917547) || chr(917548) || chr(917549) || chr(917550) || chr(917551) || chr(917552) || chr(917553) || chr(917554) || chr(917555) || chr(917556) || chr(917557) || chr(917558) || chr(917559) || chr(917560) || chr(917561) || chr(917562) || chr(917563) || chr(917564) || chr(917565) || chr(917566) || chr(917567) || chr(917568) || chr(917569) || chr(917570) || chr(917571) || chr(917572) || chr(917573) || chr(917574) || chr(917575) || chr(917576) || chr(917577) || chr(917578) || chr(917579) || chr(917580) || chr(917581) || chr(917582) || chr(917583) || chr(917584) || chr(917585) || chr(917586) || chr(917587) || chr(917588) || chr(917589) || chr(917590) || chr(917591) || chr(917592) || chr(917593) || chr(917594) || chr(917595) || chr(917596) || chr(917597) || chr(917598) || chr(917599) || chr(917600) || chr(917601) || chr(917602) || chr(917603) || chr(917604) || chr(917605) || chr(917606) || chr(917607) || chr(917608) || chr(917609) || chr(917610) || chr(917611) || chr(917612) || chr(917613) || chr(917614) || chr(917615) || chr(917616) || chr(917617) || chr(917618) || chr(917619) || chr(917620) || chr(917621) || chr(917622) || chr(917623) || chr(917624) || chr(917625) || chr(917626) || chr(917627) || chr(917628) || chr(917629) || chr(917630) || chr(917631)';
        return "CREATE TABLE {$table} (
            schema_version smallint NOT NULL,
            kind text NOT NULL,
            record_id uuid NOT NULL,
            partition_id varchar(71) NOT NULL,
            tenant_type varchar(256) NULL,
            tenant_id varchar(256) NULL,
            segment_id uuid NOT NULL,
            sequence bigint NOT NULL,
            previous_record_hash char(64) NULL,
            content_hash char(64) NOT NULL,
            record_hash char(64) NOT NULL,
            recorded_at timestamptz NOT NULL,
            event_category text NOT NULL,
            protected_body bytea NOT NULL,
            action_type varchar(256) NOT NULL,
            resource_type varchar(256) NOT NULL,
            resource_id varchar(256) NOT NULL,
            CONSTRAINT audit_records_pk PRIMARY KEY (partition_id, record_id),
            CONSTRAINT audit_records_schema_kind_check CHECK (
                schema_version = 1 AND kind = 'audit_stored_record'
            ),
            CONSTRAINT audit_records_record_id_check CHECK (
                record_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'
            ),
            CONSTRAINT audit_records_partition_tenant_check CHECK (
                partition_id ~ '^(system:global|tenant:[0-9a-f]{64})$'
                AND ((partition_id = 'system:global' AND tenant_type IS NULL AND tenant_id IS NULL)
                OR (partition_id LIKE 'tenant:%'
                    AND tenant_type IS NOT NULL
                    AND tenant_id IS NOT NULL
                    AND octet_length(tenant_type) BETWEEN 1 AND 256
                    AND octet_length(tenant_id) BETWEEN 1 AND 256
                    AND translate(tenant_type, {$controlCharacters}, '') = tenant_type
                    AND translate(tenant_id, {$controlCharacters}, '') = tenant_id))
            ),
            CONSTRAINT audit_records_sequence_check CHECK (
                sequence BETWEEN 1 AND 9007199254740991
            ),
            CONSTRAINT audit_records_previous_check CHECK (
                (sequence = 1 AND previous_record_hash IS NULL)
                OR (sequence > 1 AND previous_record_hash IS NOT NULL)
            ),
            CONSTRAINT audit_records_hash_check CHECK (
                content_hash ~ '^[0-9a-f]{64}$'
                AND record_hash ~ '^[0-9a-f]{64}$'
                AND (previous_record_hash IS NULL OR previous_record_hash ~ '^[0-9a-f]{64}$')
            ),
            CONSTRAINT audit_records_category_check CHECK (
                event_category IN ('business', 'security', 'access', 'configuration', 'control',
                    'retention', 'replay', 'rotation', 'outbox')
            ),
            CONSTRAINT audit_records_body_check CHECK (
                octet_length(protected_body) BETWEEN 53 AND 1048756
                AND substring(protected_body FROM 1 FOR 4) = decode('424f5044', 'hex')
            ),
            CONSTRAINT audit_records_action_check CHECK (
                octet_length(action_type) BETWEEN 1 AND 256
                AND translate(action_type, {$controlCharacters}, '') = action_type
            ),
            CONSTRAINT audit_records_resource_check CHECK (
                octet_length(resource_type) BETWEEN 1 AND 256
                AND translate(resource_type, {$controlCharacters}, '') = resource_type
                AND octet_length(resource_id) BETWEEN 1 AND 256
                AND translate(resource_id, {$controlCharacters}, '') = resource_id
            ),
            CONSTRAINT audit_records_segment_fk FOREIGN KEY (partition_id, segment_id)
                REFERENCES {$segments} (partition_id, segment_id),
            CONSTRAINT audit_records_record_id_uq UNIQUE (record_id),
            CONSTRAINT audit_records_partition_sequence_uq UNIQUE (partition_id, sequence),
            CONSTRAINT audit_records_partition_hash_uq UNIQUE (partition_id, record_hash)
        )";
    }
}

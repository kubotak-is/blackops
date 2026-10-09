<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class PostgreSqlAuditClosedSegmentQueries
{
    public function __construct(
        private Connection $connection,
        private PostgreSqlAuditSchema $schema,
    ) {}

    public function lockPartition(#[\SensitiveParameter] string $partitionId): void
    {
        $this->connection->fetchOne(
            'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
            [
                'lock_key' => 'blackops.audit.partition.v1:' . $this->schema->schemaName() . ':' . $partitionId,
            ],
            ['lock_key' => ParameterType::STRING],
        );
    }

    /** @return array<array-key,mixed>|false */
    public function state(
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
    ): array|false {
        $parameters = ['partition_id' => $partitionId];
        if ($tenant !== null) {
            $parameters['tenant_type'] = $tenant->type;
            $parameters['tenant_id'] = $tenant->id;
        }

        return $this->connection->fetchAssociative(
            "SELECT partition_id, tenant_type, tenant_id, active_segment_id::text AS active_segment_id,
                    last_sequence, head_record_hash,
                    to_char(last_assigned_recorded_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS last_assigned_recorded_at,
                    state_version
                FROM {$this->schema->partitionStateTable()}
                WHERE partition_id = :partition_id AND {$this->tenantPredicate($tenant)}
                FOR UPDATE",
            $parameters,
            array_fill_keys(array_keys($parameters), ParameterType::STRING),
        );
    }

    /** @return array<array-key,mixed>|false */
    public function target(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array|false {
        return $this->connection->fetchAssociative(
            "SELECT partition_id, segment_id::text AS segment_id, state, first_sequence, final_sequence,
                    head_record_hash, predecessor_record_hash,
                    to_char(opened_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS opened_at,
                    CASE WHEN closed_at IS NULL THEN NULL ELSE to_char(closed_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') END AS closed_at,
                    successor_segment_id::text AS successor_segment_id, close_fencing_token, state_version
                FROM {$this->schema->segmentsTable()}
                WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)
                FOR UPDATE",
            ['partition_id' => $partitionId, 'segment_id' => $segmentId],
            ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
        );
    }

    /** @return list<array<array-key,mixed>> */
    public function successor(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array {
        return $this->connection->fetchAllAssociative(
            "SELECT partition_id, segment_id::text AS segment_id, state, first_sequence, final_sequence,
                    head_record_hash, predecessor_record_hash,
                    to_char(opened_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS opened_at,
                    CASE WHEN closed_at IS NULL THEN NULL ELSE to_char(closed_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') END AS closed_at,
                    successor_segment_id::text AS successor_segment_id, close_fencing_token, state_version
                FROM {$this->schema->segmentsTable()}
                WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)
                FOR UPDATE",
            ['partition_id' => $partitionId, 'segment_id' => $segmentId],
            ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
        );
    }

    /** @return list<array<array-key,mixed>> */
    public function previous(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array {
        return $this->connection->fetchAllAssociative(
            "SELECT partition_id, segment_id::text AS segment_id, state, first_sequence, final_sequence,
                    head_record_hash, predecessor_record_hash,
                    to_char(opened_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS opened_at,
                    CASE WHEN closed_at IS NULL THEN NULL ELSE to_char(closed_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') END AS closed_at,
                    successor_segment_id::text AS successor_segment_id, close_fencing_token, state_version
                FROM {$this->schema->segmentsTable()}
                WHERE partition_id = :partition_id AND successor_segment_id = CAST(:segment_id AS uuid)
                FOR UPDATE",
            ['partition_id' => $partitionId, 'segment_id' => $segmentId],
            ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
        );
    }

    /** @return array<array-key,mixed>|false */
    public function endpoint(
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        int $sequence,
    ): array|false {
        $parameters = [
            'partition_id' => $partitionId,
            'sequence' => (string) $sequence,
        ];
        if ($tenant !== null) {
            $parameters['tenant_type'] = $tenant->type;
            $parameters['tenant_id'] = $tenant->id;
        }

        return $this->connection->fetchAssociative(
            "SELECT segment_id::text AS segment_id, sequence, previous_record_hash, record_hash
                FROM {$this->schema->recordsTable()}
                WHERE partition_id = :partition_id AND sequence = :sequence
                  AND {$this->tenantPredicate($tenant)}
                FOR UPDATE",
            $parameters,
            array_fill_keys(array_keys($parameters), ParameterType::STRING),
        );
    }

    public function tenantMismatch(
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): bool {
        $value = $this->connection->fetchOne(
            'SELECT 1 FROM '
            . $this->schema->recordsTable()
            . ' WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)'
            . ' AND (tenant_type IS DISTINCT FROM :tenant_type OR tenant_id IS DISTINCT FROM :tenant_id) LIMIT 1',
            [
                'partition_id' => $partitionId,
                'segment_id' => $segmentId,
                'tenant_type' => $tenant?->type,
                'tenant_id' => $tenant?->id,
            ],
            [
                'partition_id' => ParameterType::STRING,
                'segment_id' => ParameterType::STRING,
                'tenant_type' => ParameterType::STRING,
                'tenant_id' => ParameterType::STRING,
            ],
        );

        return $value !== false && $value !== null;
    }

    /** @return list<array<array-key,mixed>> */
    public function records(
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
        #[\SensitiveParameter]
        string $after,
    ): array {
        $parameters = [
            'partition_id' => $partitionId,
            'segment_id' => $segmentId,
            'after' => $after,
        ];
        if ($tenant !== null) {
            $parameters['tenant_type'] = $tenant->type;
            $parameters['tenant_id'] = $tenant->id;
        }

        return $this->connection->fetchAllAssociative(
            "SELECT schema_version, kind, record_id::text AS record_id, partition_id, tenant_type, tenant_id,
                    segment_id::text AS segment_id, sequence, previous_record_hash, content_hash, record_hash,
                    to_char(recorded_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS recorded_at,
                    event_category, protected_body, action_type, resource_type, resource_id
                FROM {$this->schema->recordsTable()}
                WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)
                  AND {$this->tenantPredicate($tenant)} AND sequence > CAST(:after AS bigint)
                ORDER BY sequence ASC LIMIT 32",
            $parameters,
            array_fill_keys(array_keys($parameters), ParameterType::STRING),
        );
    }

    private function tenantPredicate(#[\SensitiveParameter] ?AuditTypedReference $tenant): string
    {
        return $tenant === null
            ? 'tenant_type IS NULL AND tenant_id IS NULL'
            : 'tenant_type = :tenant_type AND tenant_id = :tenant_id';
    }
}

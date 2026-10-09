<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class PostgreSqlAuditCheckpointRows
{
    private string $segments;

    public function __construct(
        private Connection $connection,
        private PostgreSqlAuditCheckpointSchema $schema,
        PostgreSqlAuditSchema $b2Schema,
    ) {
        $this->segments = $b2Schema->segmentsTable();
    }

    /** @return array<array-key,mixed>|false */
    public function preparation(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array|false {
        return $this->connection->fetchAssociative(
            "SELECT checkpoint_id::text AS checkpoint_id, partition_id, segment_id::text AS segment_id,
                    closed_state_version, close_fencing_token, unsigned_checkpoint, signing_key_fingerprint
                FROM {$this->schema->preparationsTable()}
                WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)
                FOR UPDATE",
            ['partition_id' => $partitionId, 'segment_id' => $segmentId],
            ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
        );
    }

    /** @return array<array-key,mixed>|false */
    public function complete(#[\SensitiveParameter] string $checkpointId): array|false
    {
        return $this->connection->fetchAssociative(
            "SELECT checkpoint_id::text AS checkpoint_id, checkpoint_hash, complete_bytes
                FROM {$this->schema->checkpointsTable()}
                WHERE checkpoint_id = CAST(:checkpoint_id AS uuid)
                FOR UPDATE",
            ['checkpoint_id' => $checkpointId],
            ['checkpoint_id' => ParameterType::STRING],
        );
    }

    public function checkpointIdExists(#[\SensitiveParameter] string $checkpointId): bool
    {
        $value = $this->connection->fetchOne(
            "SELECT 1 FROM {$this->schema->preparationsTable()}
                WHERE checkpoint_id = CAST(:checkpoint_id AS uuid)",
            ['checkpoint_id' => $checkpointId],
            ['checkpoint_id' => ParameterType::STRING],
        );

        return $value !== false && $value !== null;
    }

    /** @param array{checkpoint_id:string,partition_id:string,segment_id:string,closed_state_version:int,close_fencing_token:string,unsigned_checkpoint:string,signing_key_fingerprint:string} $parameters */
    public function insertPreparation(#[\SensitiveParameter] array $parameters): int
    {
        $affected = $this->connection->executeStatement(
            "INSERT INTO {$this->schema->preparationsTable()} (
                    checkpoint_id, partition_id, segment_id, closed_state_version,
                    close_fencing_token, unsigned_checkpoint, signing_key_fingerprint
                ) SELECT
                    CAST(:checkpoint_id AS uuid), :partition_id, CAST(:segment_id AS uuid),
                    :closed_state_version, :close_fencing_token, CAST(:unsigned_checkpoint AS bytea),
                    :signing_key_fingerprint
                WHERE EXISTS (
                    SELECT 1 FROM {$this->segments} AS s
                    WHERE s.partition_id = :partition_id AND s.segment_id = CAST(:segment_id AS uuid)
                      AND s.state = 'closed' AND s.state_version = :closed_state_version
                      AND s.close_fencing_token = :close_fencing_token
                )",
            $parameters,
            [
                'checkpoint_id' => ParameterType::STRING,
                'partition_id' => ParameterType::STRING,
                'segment_id' => ParameterType::STRING,
                'closed_state_version' => ParameterType::INTEGER,
                'close_fencing_token' => ParameterType::STRING,
                'unsigned_checkpoint' => ParameterType::BINARY,
                'signing_key_fingerprint' => ParameterType::STRING,
            ],
        );
        if (is_int($affected)) {
            return $affected;
        }
        if (
            preg_match('/^(?:0|[1-9][0-9]*)$/D', $affected) !== 1
            || strlen($affected) > strlen((string) PHP_INT_MAX)
            || strlen($affected) === strlen((string) PHP_INT_MAX) && strcmp($affected, (string) PHP_INT_MAX) > 0
        ) {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }

        return (int) $affected;
    }

    /** @param array{checkpoint_id:string,checkpoint_hash:string,complete_bytes:string} $parameters */
    public function insertComplete(#[\SensitiveParameter] array $parameters): int
    {
        $affected = $this->connection->executeStatement(
            "INSERT INTO {$this->schema->checkpointsTable()} (checkpoint_id, checkpoint_hash, complete_bytes)
                SELECT CAST(:checkpoint_id AS uuid), :checkpoint_hash, CAST(:complete_bytes AS bytea)
                WHERE EXISTS (
                    SELECT 1
                    FROM {$this->schema->preparationsTable()} AS p
                    JOIN {$this->segments} AS s
                      ON s.partition_id = p.partition_id AND s.segment_id = p.segment_id
                    WHERE p.checkpoint_id = CAST(:checkpoint_id AS uuid)
                      AND p.closed_state_version = s.state_version
                      AND p.close_fencing_token = s.close_fencing_token
                      AND s.state = 'closed'
                )",
            $parameters,
            [
                'checkpoint_id' => ParameterType::STRING,
                'checkpoint_hash' => ParameterType::STRING,
                'complete_bytes' => ParameterType::BINARY,
            ],
        );
        if (is_int($affected)) {
            return $affected;
        }
        if (
            preg_match('/^(?:0|[1-9][0-9]*)$/D', $affected) !== 1
            || strlen($affected) > strlen((string) PHP_INT_MAX)
            || strlen($affected) === strlen((string) PHP_INT_MAX) && strcmp($affected, (string) PHP_INT_MAX) > 0
        ) {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }

        return (int) $affected;
    }

    /** @return array<array-key,mixed>|false */
    public function predecessorSegment(
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
                FROM {$this->segments}
                WHERE partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid)
                FOR UPDATE",
            ['partition_id' => $partitionId, 'segment_id' => $segmentId],
            ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
        );
    }

    /** @return array<array-key,mixed>|false */
    public function predecessorCheckpoint(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array|false {
        return $this->connection->fetchAssociative(
            "SELECT p.checkpoint_id::text AS checkpoint_id, p.partition_id, p.segment_id::text AS segment_id,
                    p.closed_state_version, p.close_fencing_token, p.unsigned_checkpoint,
                    p.signing_key_fingerprint, c.checkpoint_hash, c.complete_bytes
                FROM {$this->schema->preparationsTable()} AS p
                JOIN {$this->schema->checkpointsTable()} AS c ON c.checkpoint_id = p.checkpoint_id
                WHERE p.partition_id = :partition_id AND p.segment_id = CAST(:segment_id AS uuid)
                FOR UPDATE",
            ['partition_id' => $partitionId, 'segment_id' => $segmentId],
            ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
        );
    }
}

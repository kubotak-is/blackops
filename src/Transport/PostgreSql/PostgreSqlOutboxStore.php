<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlOutboxStore
{
    private PostgreSqlOutboxSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        $this->schema = new PostgreSqlOutboxSchema($schema);
    }

    public function migrate(): void
    {
        try {
            foreach ($this->schema->statements() as $statement) {
                $this->connection->executeStatement($statement);
            }
        } catch (Throwable $exception) {
            throw new DeferredTransportException('Failed to migrate PostgreSQL outbox schema.', previous: $exception);
        }
    }

    public function insert(PostgreSqlOutboxRecord $record): void
    {
        $sql = "INSERT INTO {$this->schema->table()} (
            record_id, operation_id, operation_type, schema_version,
            encoded_payload, encoded_context, content_type, encoding, key_id,
            available_at, recorded_at, connection_name, state, state_version
        ) VALUES (
            :record_id, :operation_id, :operation_type, :schema_version,
            convert_to(:encoded_payload, 'UTF8'), convert_to(:encoded_context, 'UTF8'),
            :content_type, :encoding, :key_id, :available_at, :recorded_at,
            :connection_name, 'pending', 1
        )";

        try {
            $this->connection->executeStatement($sql, [
                'record_id' => $record->recordId->toString(),
                'operation_id' => $record->operationId->toString(),
                'operation_type' => $record->operationType,
                'schema_version' => $record->schemaVersion,
                'encoded_payload' => $record->encodedPayload,
                'encoded_context' => $record->encodedContext,
                'content_type' => 'application/vnd.blackops.deferred-operation+json',
                'encoding' => 'utf8',
                'key_id' => null,
                'available_at' => $this->timestamp($record->availableAt),
                'recorded_at' => $this->timestamp($record->recordedAt),
                'connection_name' => $record->connectionName,
            ]);
        } catch (Throwable $exception) {
            throw new DeferredTransportException('Failed to persist PostgreSQL outbox record.', previous: $exception);
        }
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }
}

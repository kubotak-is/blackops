<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationSender;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlDeferredOperationSender implements OperationSender
{
    private const CONTENT_TYPE = 'application/vnd.blackops.deferred-operation+json';
    private const ENCODING = 'utf8';

    private PostgreSqlDeferredOperationSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
        private ?DateTimeImmutable $fixedAcceptedAt = null,
    ) {
        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
    }

    public function migrate(): void
    {
        try {
            foreach ($this->schema->statements() as $statement) {
                $this->connection->executeStatement($statement);
            }
        } catch (Throwable $exception) {
            throw new DeferredTransportException(
                'Failed to migrate PostgreSQL deferred operation schema.',
                previous: $exception,
            );
        }
    }

    public function enqueue(DeferredOperationMessage $message): DeferredAcknowledgement
    {
        $acceptedAt = $this->acceptedAt();
        $table = $this->schema->operationsTable();
        $sql = "INSERT INTO {$table} (
            operation_id,
            operation_type,
            schema_version,
            encoded_payload,
            encoded_context,
            content_type,
            encoding,
            key_id,
            state,
            state_version,
            next_sequence,
            available_at,
            accepted_at
        ) VALUES (
            :operation_id,
            :operation_type,
            :schema_version,
            convert_to(:encoded_payload, 'UTF8'),
            convert_to(:encoded_context, 'UTF8'),
            :content_type,
            :encoding,
            :key_id,
            :state,
            :state_version,
            :next_sequence,
            :available_at,
            :accepted_at
        )";

        try {
            $this->connection->executeStatement($sql, [
                'operation_id' => $message->operationId()->toString(),
                'operation_type' => $message->operationType(),
                'schema_version' => $message->schemaVersion(),
                'encoded_payload' => $message->encodedPayload(),
                'encoded_context' => $message->encodedContext(),
                'content_type' => self::CONTENT_TYPE,
                'encoding' => self::ENCODING,
                'key_id' => null,
                'state' => 'accepted',
                'state_version' => 1,
                'next_sequence' => 1,
                'available_at' => $this->formatTimestamp($message->availableAt()),
                'accepted_at' => $this->formatTimestamp($acceptedAt),
            ]);
        } catch (Throwable $exception) {
            throw new DeferredTransportException(
                'Failed to enqueue PostgreSQL deferred operation.',
                previous: $exception,
            );
        }

        return new DeferredAcknowledgement($message->operationId(), $acceptedAt);
    }

    public function advanceNextSequence(DeferredOperationMessage $message, int $nextSequence): void
    {
        if ($nextSequence < 1) {
            throw new DeferredTransportException('Deferred operation next sequence must be positive.');
        }

        $table = $this->schema->operationsTable();
        $sql = "UPDATE {$table}
            SET next_sequence = :next_sequence,
                state_version = state_version + 1
            WHERE operation_id = :operation_id";

        try {
            $updated = $this->connection->executeStatement($sql, [
                'operation_id' => $message->operationId()->toString(),
                'next_sequence' => $nextSequence,
            ]);
        } catch (Throwable $exception) {
            throw new DeferredTransportException(
                'Failed to advance PostgreSQL deferred operation sequence.',
                previous: $exception,
            );
        }

        if ((int) $updated !== 1) {
            throw new DeferredTransportException('Deferred operation sequence advance did not update exactly one row.');
        }
    }

    private function formatTimestamp(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }

    private function acceptedAt(): DateTimeImmutable
    {
        return $this->fixedAcceptedAt ?? new DateTimeImmutable('now');
    }
}

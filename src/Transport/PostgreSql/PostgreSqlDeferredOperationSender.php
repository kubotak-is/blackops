<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationSender;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\StorageProtection\StoragePurpose;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
/** @mago-expect lint:too-many-methods */
final readonly class PostgreSqlDeferredOperationSender implements OperationSender
{
    private const CONTENT_TYPE = 'application/vnd.blackops.deferred-operation+json';
    private const ENCODING = 'utf8';

    private PostgreSqlDeferredOperationSchema $schema;

    public function __construct(
        private Connection $connection,
        private BopdEnvelopeCodec $protection,
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
            tenant_type, tenant_id, origin_actor_type, origin_actor_id,
            state,
            state_version,
            next_sequence,
            available_at,
            accepted_at
        ) VALUES (
            :operation_id,
            :operation_type,
            :schema_version,
            :encoded_payload,
            :encoded_context,
            :content_type,
            :encoding,
            :key_id,
            :tenant_type, :tenant_id, :origin_actor_type, :origin_actor_id,
            :state,
            :state_version,
            :next_sequence,
            :available_at,
            :accepted_at
        )";

        try {
            $inserted = $this->connection->executeStatement(
                $sql . ' ON CONFLICT (operation_id) DO NOTHING',
                [
                    'operation_id' => $message->operationId()->toString(),
                    'operation_type' => $message->operationType(),
                    'schema_version' => $message->schemaVersion(),
                    'encoded_payload' => $this->encodePayload($message),
                    'encoded_context' => $this->encodeContext($message),
                    'content_type' => self::CONTENT_TYPE,
                    'encoding' => self::ENCODING,
                    'key_id' => null,
                    'tenant_type' => $message->tenant()?->type(),
                    'tenant_id' => $message->tenant()?->id(),
                    'origin_actor_type' => $message->originActor()?->type(),
                    'origin_actor_id' => $message->originActor()?->id(),
                    'state' => 'accepted',
                    'state_version' => 1,
                    'next_sequence' => 1,
                    'available_at' => $this->formatTimestamp($message->availableAt()),
                    'accepted_at' => $this->formatTimestamp($acceptedAt),
                ],
                [
                    'encoded_payload' => ParameterType::BINARY,
                    'encoded_context' => ParameterType::BINARY,
                ],
            );
            if ((int) $inserted === 0) {
                $existing = $this->connection->fetchAssociative(
                    "SELECT operation_type, schema_version,
                        tenant_type, tenant_id, origin_actor_type, origin_actor_id,
                        encoded_payload, encoded_context,
                        content_type, encoding, key_id, available_at, accepted_at
                    FROM {$table} WHERE operation_id=:operation_id
                        AND tenant_type IS NOT DISTINCT FROM :tenant_type
                        AND tenant_id IS NOT DISTINCT FROM :tenant_id",
                    [
                        'operation_id' => $message->operationId()->toString(),
                        'tenant_type' => $message->tenant()?->type(),
                        'tenant_id' => $message->tenant()?->id(),
                    ],
                );
                if (!is_array($existing)) {
                    throw new DeferredTransportException('Deferred operation duplicate could not be read safely.');
                }
                if (!$this->sameMessage($existing, $message)) {
                    throw new DeferredTransportException(
                        'Deferred operation duplicate has mismatched message integrity.',
                    );
                }
                return new DeferredAcknowledgement(
                    $message->operationId(),
                    new DateTimeImmutable((string) $existing['accepted_at']),
                    replayed: true,
                );
            }
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }
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

    /** @param array<string, mixed> $existing */
    private function sameMessage(array $existing, DeferredOperationMessage $message): bool
    {
        return (
            $existing['operation_type'] === $message->operationType()
            && (int) $existing['schema_version'] === $message->schemaVersion()
            && $this->decodePayload($existing, $message) === $message->encodedPayload()
            && $this->decodeContext($existing, $message) === $message->encodedContext()
            && $existing['content_type'] === self::CONTENT_TYPE
            && $existing['encoding'] === self::ENCODING
            && $existing['key_id'] === null
            && $existing['tenant_type'] === $message->tenant()?->type()
            && $existing['tenant_id'] === $message->tenant()?->id()
            && $existing['origin_actor_type'] === $message->originActor()?->type()
            && $existing['origin_actor_id'] === $message->originActor()?->id()
            && $this->formatTimestamp(
                new DateTimeImmutable((string) $existing['available_at']),
            ) === $this->formatTimestamp($message->availableAt())
        );
    }

    private function acceptedAt(): DateTimeImmutable
    {
        return $this->fixedAcceptedAt ?? new DateTimeImmutable('now');
    }

    private function encodePayload(DeferredOperationMessage $message): string
    {
        return $this->protection->encrypt($message->encodedPayload(), $this->context(
            $message,
            StoragePurpose::DeferredPayload,
            'payload',
        ));
    }

    private function encodeContext(DeferredOperationMessage $message): string
    {
        return $this->protection->encrypt($message->encodedContext(), $this->context(
            $message,
            StoragePurpose::DeferredContext,
            'context',
        ));
    }

    /** @param array<string, mixed> $existing */
    private function decodePayload(array $existing, DeferredOperationMessage $message): string
    {
        $payload = PostgreSqlBytea::string($existing['encoded_payload'] ?? null);
        return $this->protection->decrypt($payload, $this->context(
            $message,
            StoragePurpose::DeferredPayload,
            'payload',
        ));
    }

    /** @param array<string, mixed> $existing */
    private function decodeContext(array $existing, DeferredOperationMessage $message): string
    {
        $context = PostgreSqlBytea::string($existing['encoded_context'] ?? null);
        return $this->protection->decrypt($context, $this->context(
            $message,
            StoragePurpose::DeferredContext,
            'context',
        ));
    }

    private function context(
        DeferredOperationMessage $message,
        StoragePurpose $purpose,
        string $field,
    ): StorageProtectionContext {
        return new StorageProtectionContext(
            $purpose,
            $message->operationId()->toString() . ':' . $field,
            $message->operationId()->toString(),
            $message->operationType(),
            $message->schemaVersion(),
            $message->tenant(),
        );
    }
}

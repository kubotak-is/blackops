<?php

declare(strict_types=1);

namespace BlackOps\Internal\Retention;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\Retention\RetentionPurgeAuditPort;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Retention\RetentionPurgeAuditWriter;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditStore;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final readonly class AuditedRetentionPurgeAuditPort implements RetentionPurgeAuditPort
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        RetentionPurgeAuditWriter $writer,
        #[\SensitiveParameter]
        LoggerInterface $logger,
        #[\SensitiveParameter]
        string $schema = 'blackops',
    ) {
        try {
            $schemaHelper = new PostgreSqlDeferredOperationSchema($schema);
            $legacy = new LoggingRetentionPurgeAuditPort(
                new PostgreSqlRetentionPurgeAuditStore($connection, $schema),
                $logger,
            );
            $this->snapshot = new \SensitiveParameterValue([
                'connection' => $connection,
                'writer' => $writer,
                'schema' => $schemaHelper,
                'legacy' => $legacy,
            ]);
        } catch (Throwable) {
            throw self::failure();
        }
    }

    public function record(#[\SensitiveParameter] RetentionPurgeAuditRecord $record): void
    {
        try {
            $values = $this->values();
            $this->assertOwnedRoot($values['connection'], $values['writer']);
            $normalized = $this->normalize($values['connection'], $values['schema'], $record);
            $values['writer']->committed(
                $normalized->id(),
                $normalized->operationId(),
                $normalized->target(),
                $normalized->tenant(),
            );
            $values['legacy']->record($normalized);
        } catch (Throwable) {
            throw self::failure();
        }
    }

    /** @return array<never, never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<never, never> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw self::failure();
    }

    public function __clone(): void
    {
        throw self::failure();
    }

    /** @return array{connection:Connection,writer:RetentionPurgeAuditWriter,schema:PostgreSqlDeferredOperationSchema,legacy:LoggingRetentionPurgeAuditPort} */
    private function values(): array
    {
        try {
            $value = $this->snapshot->getValue();
            if (
                !is_array($value)
                || array_keys($value) !== ['connection', 'writer', 'schema', 'legacy']
                || !$value['connection'] instanceof Connection
                || !$value['writer'] instanceof RetentionPurgeAuditWriter
                || !$value['schema'] instanceof PostgreSqlDeferredOperationSchema
                || !$value['legacy'] instanceof LoggingRetentionPurgeAuditPort
            ) {
                throw new \LogicException();
            }

            return [
                'connection' => $value['connection'],
                'writer' => $value['writer'],
                'schema' => $value['schema'],
                'legacy' => $value['legacy'],
            ];
        } catch (Throwable) {
            throw self::failure();
        }
    }

    private function assertOwnedRoot(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        RetentionPurgeAuditWriter $writer,
    ): void {
        try {
            $writer->assertConnection($connection);
            if (
                !$connection->isAutoCommit()
                || !$connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 1
            ) {
                throw new \RuntimeException();
            }
        } catch (Throwable) {
            throw self::failure();
        }
    }

    private function normalize(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        PostgreSqlDeferredOperationSchema $schema,
        #[\SensitiveParameter]
        RetentionPurgeAuditRecord $record,
    ): RetentionPurgeAuditRecord {
        $auditId = $record->id()->toString();
        $operationId = $record->operationId()->toString();
        if (
            RetentionPurgeAuditId::fromString($auditId)->toString() !== $auditId
            || OperationId::fromString($operationId)->toString() !== $operationId
            || !$record->target() instanceof RetentionPurgeTarget
            || $record->affectedCount() < 1
            || !$record->policy() instanceof \BlackOps\Core\Retention\RetentionPolicyRef
            || !$record->purgedBy() instanceof \BlackOps\Core\Retention\RetentionActorRef
            || !$record->purgedAt() instanceof \DateTimeImmutable
        ) {
            throw new \LogicException();
        }

        $tenant = $record->tenant();
        if ($record->target() === RetentionPurgeTarget::TransportPayload) {
            $tenant = $this->transportTenant($connection, $schema, $operationId);

            return $this->normalizedRecord($record, $auditId, $operationId, $tenant);
        }

        $this->validateTenant($tenant);

        return $this->normalizedRecord($record, $auditId, $operationId, $tenant);
    }

    private function normalizedRecord(
        #[\SensitiveParameter]
        RetentionPurgeAuditRecord $record,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        string $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): RetentionPurgeAuditRecord {
        return new RetentionPurgeAuditRecord(
            RetentionPurgeAuditId::fromString($auditId),
            OperationId::fromString($operationId),
            $record->target(),
            $record->affectedCount(),
            $record->policy(),
            $record->purgedAt(),
            $record->purgedBy(),
            $tenant,
        );
    }

    private function transportTenant(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        PostgreSqlDeferredOperationSchema $schema,
        #[\SensitiveParameter]
        string $operationId,
    ): ?TenantRef {
        $row = $connection->fetchAssociative(
            "SELECT operation_id::text AS operation_id, tenant_type, tenant_id
             FROM {$schema->operationsTable()}
             WHERE operation_id = :operation_id",
            ['operation_id' => $operationId],
        );
        if (
            !is_array($row)
            || array_keys($row) !== ['operation_id', 'tenant_type', 'tenant_id']
            || !is_string($row['operation_id'] ?? null)
            || $row['operation_id'] !== $operationId
        ) {
            throw new \RuntimeException();
        }

        $type = $row['tenant_type'] ?? null;
        $id = $row['tenant_id'] ?? null;
        if ($type === null && $id === null) {
            return null;
        }
        if (!is_string($type) || !is_string($id)) {
            throw new \RuntimeException();
        }

        return $this->tenant($type, $id);
    }

    private function validateTenant(?TenantRef $tenant): void
    {
        if ($tenant !== null) {
            $this->tenant($tenant->type(), $tenant->id());
        }
    }

    private function tenant(string $type, string $id): TenantRef
    {
        if (trim($type) !== $type || trim($id) !== $id) {
            throw new \RuntimeException();
        }

        new \BlackOps\Core\Audit\AuditTypedReference($type, $id);

        return new TenantRef($type, $id);
    }

    private static function failure(): DeferredTransportException
    {
        return new DeferredTransportException('Failed to deliver audited PostgreSQL retention purge.');
    }
}

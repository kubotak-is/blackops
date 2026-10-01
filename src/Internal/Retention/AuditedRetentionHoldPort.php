<?php

declare(strict_types=1);

namespace BlackOps\Internal\Retention;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionHold;
use BlackOps\Core\Retention\RetentionHoldCategory;
use BlackOps\Core\Retention\RetentionHoldPort;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Retention\RetentionHoldAuditWriter;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionHoldIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionHoldStore;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use BlackOps\Transport\PostgreSql\SymfonyRetentionHoldIdGenerator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final readonly class AuditedRetentionHoldPort implements RetentionHoldPort
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        RetentionHoldAuditWriter $writer,
        #[\SensitiveParameter]
        string $schema = 'blackops',
        #[\SensitiveParameter]
        ClockInterface $clock = new PostgreSqlSystemClock(),
        #[\SensitiveParameter]
        PostgreSqlRetentionHoldIdGenerator $ids = new SymfonyRetentionHoldIdGenerator(),
    ) {
        try {
            $schemaHelper = new PostgreSqlDeferredOperationSchema($schema);
            $native = new PostgreSqlRetentionHoldStore($connection, $schema, $clock, $ids);
            $this->snapshot = new \SensitiveParameterValue([
                'connection' => $connection,
                'writer' => $writer,
                'schema' => $schemaHelper,
                'native' => $native,
            ]);
        } catch (Throwable) {
            throw self::failure();
        }
    }

    public function place(
        #[\SensitiveParameter]
        OperationId $operationId,
        RetentionHoldCategory $category,
        #[\SensitiveParameter]
        string $reason,
        #[\SensitiveParameter]
        RetentionActorRef $placedBy,
        #[\SensitiveParameter]
        DateTimeImmutable $placedAt,
    ): RetentionHold {
        try {
            $values = $this->values();
            $this->assertAdmission($values['connection'], $values['writer']);

            $hold = $values['connection']->transactional(function () use (
                $values,
                $operationId,
                $category,
                $reason,
                $placedBy,
                $placedAt,
            ): RetentionHold {
                $this->assertOwnedRoot($values['connection']);
                $hold = $values['native']->place($operationId, $category, $reason, $placedBy, $placedAt);
                $tenant = $this->persistedTenant($values['connection'], $values['schema'], $hold);
                $values['writer']->placed($hold->id(), $hold->operationId(), $tenant);

                return $hold;
            });
            if (!$hold instanceof RetentionHold) {
                throw new \LogicException();
            }

            return $hold;
        } catch (Throwable) {
            throw self::failure();
        }
    }

    public function release(
        #[\SensitiveParameter]
        RetentionHoldId $holdId,
        #[\SensitiveParameter]
        RetentionActorRef $releasedBy,
        #[\SensitiveParameter]
        DateTimeImmutable $releasedAt,
    ): RetentionHold {
        try {
            $values = $this->values();
            $this->assertAdmission($values['connection'], $values['writer']);

            $hold = $values['connection']->transactional(function () use (
                $values,
                $holdId,
                $releasedBy,
                $releasedAt,
            ): RetentionHold {
                $this->assertOwnedRoot($values['connection']);
                $hold = $values['native']->release($holdId, $releasedBy, $releasedAt);
                $tenant = $this->persistedTenant($values['connection'], $values['schema'], $hold);
                $values['writer']->released($hold->id(), $hold->operationId(), $tenant);

                return $hold;
            });
            if (!$hold instanceof RetentionHold) {
                throw new \LogicException();
            }

            return $hold;
        } catch (Throwable) {
            throw self::failure();
        }
    }

    public function activeFor(#[\SensitiveParameter] OperationId $operationId): array
    {
        try {
            return $this->values()['native']->activeFor($operationId);
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

    /** @return array{connection:Connection,writer:RetentionHoldAuditWriter,schema:PostgreSqlDeferredOperationSchema,native:PostgreSqlRetentionHoldStore} */
    private function values(): array
    {
        try {
            $value = $this->snapshot->getValue();
            if (
                !is_array($value)
                || array_keys($value) !== ['connection', 'writer', 'schema', 'native']
                || !$value['connection'] instanceof Connection
                || !$value['writer'] instanceof RetentionHoldAuditWriter
                || !$value['schema'] instanceof PostgreSqlDeferredOperationSchema
                || !$value['native'] instanceof PostgreSqlRetentionHoldStore
            ) {
                throw new \LogicException();
            }

            return [
                'connection' => $value['connection'],
                'writer' => $value['writer'],
                'schema' => $value['schema'],
                'native' => $value['native'],
            ];
        } catch (Throwable) {
            throw self::failure();
        }
    }

    private function assertAdmission(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        RetentionHoldAuditWriter $writer,
    ): void {
        try {
            $writer->assertConnection($connection);
            if (
                !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw new \RuntimeException();
            }
        } catch (Throwable) {
            throw self::failure();
        }
    }

    private function assertOwnedRoot(#[\SensitiveParameter] Connection $connection): void
    {
        if (
            !$connection->isAutoCommit()
            || !$connection->isTransactionActive()
            || $connection->getTransactionNestingLevel() !== 1
        ) {
            throw new \RuntimeException();
        }
    }

    private function persistedTenant(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        PostgreSqlDeferredOperationSchema $schema,
        #[\SensitiveParameter]
        RetentionHold $hold,
    ): ?TenantRef {
        $row = $connection->fetchAssociative(
            "SELECT operation_id::text AS operation_id, tenant_type, tenant_id
             FROM {$schema->retentionHoldsTable()}
             WHERE hold_id = :hold_id",
            ['hold_id' => $hold->id()->toString()],
        );
        if (
            !is_array($row)
            || array_keys($row) !== ['operation_id', 'tenant_type', 'tenant_id']
            || !is_string($row['operation_id'] ?? null)
            || $row['operation_id'] !== $hold->operationId()->toString()
        ) {
            throw new \RuntimeException();
        }

        $type = $row['tenant_type'] ?? null;
        $id = $row['tenant_id'] ?? null;
        if ($type === null && $id === null) {
            return null;
        }
        if (
            !is_string($type)
            || !is_string($id)
            || $type === ''
            || $id === ''
            || trim($type) !== $type
            || trim($id) !== $id
        ) {
            throw new \RuntimeException();
        }

        return new TenantRef($type, $id);
    }

    private static function failure(): DeferredTransportException
    {
        return new DeferredTransportException('Failed to deliver audited PostgreSQL retention hold.');
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;

final readonly class PostgreSqlStorageProtectionRotationContext
{
    /** @param array<string,mixed> $row */
    public function create(
        StorageProtectionRotationScope $scope,
        PostgreSqlStorageProtectionRotationTarget $target,
        array $row,
    ): StorageProtectionContext {
        $operation = (string) ($row['operation_id'] ?? $row[$target->identity]);
        $type = $this->operationType($target, $row);
        $schema = $this->schema($target, $row);
        $tenant = $this->tenant($row);
        $identity = $this->identity($scope, $target, $row);
        return new StorageProtectionContext($scope->purpose, $identity, $operation, $type, $schema, $tenant);
    }

    /** @param array<string,mixed> $row */
    private function operationType(PostgreSqlStorageProtectionRotationTarget $target, array $row): string
    {
        if ($target->operationType === '') {
            return (string) ($row['operation_type'] ?? 'dead_letter');
        }
        return (string) ($row[$target->operationType] ?? '');
    }

    /** @param array<string,mixed> $row */
    private function schema(PostgreSqlStorageProtectionRotationTarget $target, array $row): int
    {
        if ($target->schema === '') {
            return (int) ($row['schema_version'] ?? 1);
        }
        return (int) ($row[$target->schema] ?? 1);
    }

    /** @param array<string,mixed> $row */
    private function tenant(array $row): ?TenantRef
    {
        if (!is_string($row['tenant_type'] ?? null) || !is_string($row['tenant_id'] ?? null)) {
            return null;
        }
        return new TenantRef($row['tenant_type'], $row['tenant_id']);
    }

    /** @param array<string,mixed> $row */
    private function identity(
        StorageProtectionRotationScope $scope,
        PostgreSqlStorageProtectionRotationTarget $target,
        array $row,
    ): string {
        $identity = (string) $row[$target->identity];
        if ($scope->purpose->value === 'deferred_payload') {
            return $identity . ':payload';
        }
        if ($scope->purpose->value === 'deferred_context') {
            return $identity . ':context';
        }
        if ($scope->purpose->value === 'idempotency_response' || $scope->purpose->value === 'idempotency_result') {
            return (string) $row['scope_version'] . ':' . (string) $row['scope_hash'];
        }
        return $identity;
    }
}

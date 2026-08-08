<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use Doctrine\DBAL\ParameterType;

final readonly class PostgreSqlStorageProtectionRotationPredicate
{
    public function build(
        PostgreSqlStorageProtectionRotationTarget $target,
        PostgreSqlStorageProtectionRotationRelation $relation,
        StorageProtectionRotationScope $scope,
        ?string $cursor,
    ): PostgreSqlStorageProtectionRotationSql {
        $column = $relation->column($target->columns[0]);
        $parts = new PostgreSqlStorageProtectionRotationSqlParts(
            [
                sprintf('%s IS NOT NULL', $column),
                sprintf("substring(%s FROM 1 FOR 4) = decode('424f5044', 'hex')", $column),
            ],
            [],
            [],
        );
        $oldHeader = 'BOPD' . chr(1) . chr(1) . pack('n', strlen($scope->oldKeyId)) . $scope->oldKeyId;
        $parts->addWhere(sprintf('substring(%s FROM 1 FOR ?) = ?', $column));
        $parts->addParam(strlen($oldHeader), ParameterType::INTEGER);
        $parts->addParam($oldHeader, ParameterType::BINARY);
        $this->appendTenant($parts, $relation, $scope);
        $this->appendCursor($parts, $target, $relation, $cursor);
        return new PostgreSqlStorageProtectionRotationSql($parts->where, $parts->params, $parts->types);
    }

    private function appendTenant(
        PostgreSqlStorageProtectionRotationSqlParts $parts,
        PostgreSqlStorageProtectionRotationRelation $relation,
        StorageProtectionRotationScope $scope,
    ): void {
        if ($scope->tenant === null) {
            return;
        }
        $parts->addWhere($relation->column('tenant_type') . ' = ? AND ' . $relation->column('tenant_id') . ' = ?');
        $parts->addParam($scope->tenant->type(), ParameterType::STRING);
        $parts->addParam($scope->tenant->id(), ParameterType::STRING);
    }

    private function appendCursor(
        PostgreSqlStorageProtectionRotationSqlParts $parts,
        PostgreSqlStorageProtectionRotationTarget $target,
        PostgreSqlStorageProtectionRotationRelation $relation,
        ?string $cursor,
    ): void {
        if ($cursor === null) {
            return;
        }
        if ($target->hasCompositeIdentity()) {
            $this->appendCompositeCursor($parts, $cursor);
            return;
        }
        $parts->addWhere($relation->column($target->identity) . ' > ?');
        $parts->addParam($cursor, ParameterType::STRING);
    }

    private function appendCompositeCursor(PostgreSqlStorageProtectionRotationSqlParts $parts, string $cursor): void
    {
        $match = [];
        if (preg_match('/^(\d+):([0-9a-f]{64})$/', $cursor, $match) !== 1) {
            throw new \InvalidArgumentException('Rotation checkpoint cursor is invalid.');
        }
        $parts->addWhere('(scope_version, scope_hash) > (?, ?)');
        $parts->addParam((int) $match[1], ParameterType::INTEGER);
        $parts->addParam($match[2], ParameterType::STRING);
    }
}

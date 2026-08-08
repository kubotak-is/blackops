<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationMode;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use BlackOps\StorageProtection\StoragePurpose;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlStorageProtectionRotationQuery
{
    private PostgreSqlStorageProtectionRotationPredicate $predicate;
    private PostgreSqlStorageProtectionRotationContext $contexts;

    public function __construct(
        private Connection $connection,
        private BopdEnvelopeCodec $codec,
        private string $schema = 'blackops',
    ) {
        $this->predicate = new PostgreSqlStorageProtectionRotationPredicate();
        $this->contexts = new PostgreSqlStorageProtectionRotationContext();
    }

    public function target(StoragePurpose $purpose): PostgreSqlStorageProtectionRotationTarget
    {
        return PostgreSqlStorageProtectionRotationTarget::forPurpose($purpose);
    }

    /** @return list<array<string,mixed>> */
    public function rows(
        string $table,
        PostgreSqlStorageProtectionRotationTarget $target,
        StorageProtectionRotationScope $scope,
        ?string $cursor,
        StorageProtectionRotationMode $mode,
    ): array {
        $relation = new PostgreSqlStorageProtectionRotationRelation($table, $target, $this->schema);
        $select = new PostgreSqlStorageProtectionRotationSelect($relation);
        $sql = $this->predicate->build($target, $relation, $scope, $cursor);
        $order = $target->hasCompositeIdentity() ? 'scope_version, scope_hash' : $relation->column($target->identity);
        $statement = sprintf(
            'SELECT %s FROM %s WHERE %s ORDER BY %s LIMIT %d',
            implode(', ', $select->columns($target, $mode)),
            $relation->from(),
            implode(' AND ', $sql->where),
            $order,
            $scope->batchSize,
        );
        return $this->connection->fetchAllAssociative($statement, $sql->params, $sql->types);
    }

    /** @return array<string,int> */
    public function remaining(
        string $table,
        PostgreSqlStorageProtectionRotationTarget $target,
        StorageProtectionRotationScope $scope,
    ): array {
        $where = [sprintf('%s IS NOT NULL', $target->columns[0])];
        $params = [];
        if ($scope->tenant !== null) {
            $where[] = 'tenant_type = ? AND tenant_id = ?';
            $params[] = $scope->tenant->type();
            $params[] = $scope->tenant->id();
        }
        $rows = $this->connection->iterateAssociative(
            'SELECT substring(' . $target->columns[0] . ' FROM 1 FOR 136) AS header FROM ' . $table . ' WHERE '
                . implode(' AND ', $where),
            $params,
        );
        $counts = [];
        foreach ($rows as $row) {
            $this->countHeader($counts, $row['header']);
        }
        ksort($counts);
        return $counts;
    }

    /** @param array<string,int> $counts */
    private function countHeader(array &$counts, mixed $header): void
    {
        try {
            $key = $this->codec->keyIdFromHeader(PostgreSqlBytea::string($header));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        } catch (Throwable) {
            $counts['protected'] = ($counts['protected'] ?? 0) + 1;
        }
    }

    /** @param array<string,mixed> $row */
    public function context(
        StorageProtectionRotationScope $scope,
        PostgreSqlStorageProtectionRotationTarget $target,
        array $row,
    ): StorageProtectionContext {
        return $this->contexts->create($scope, $target, $row);
    }

    public function cursor(string $table, StorageProtectionRotationScope $scope): ?string
    {
        $row = $this->connection->fetchAssociative(
            "SELECT scope_hash, cursor_value FROM {$table} WHERE checkpoint_id = ?",
            [$scope->checkpoint],
        );
        if ($row !== false && (string) $row['scope_hash'] !== $scope->scopeHash()) {
            throw new \InvalidArgumentException('Rotation checkpoint scope does not match.');
        }
        if (!is_string($row['cursor_value'] ?? null) || $row['cursor_value'] === '') {
            return null;
        }
        return $row['cursor_value'];
    }
}

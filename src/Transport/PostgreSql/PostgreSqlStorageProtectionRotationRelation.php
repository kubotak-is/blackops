<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

final readonly class PostgreSqlStorageProtectionRotationRelation
{
    private string $alias;

    public function __construct(
        private string $table,
        private PostgreSqlStorageProtectionRotationTarget $target,
        private string $schema,
    ) {
        $this->alias = match ($target->table) {
            'dead_letters' => 'd',
            'outcomes' => 'r',
            default => '',
        };
    }

    public function from(): string
    {
        if ($this->target->table === 'dead_letters') {
            return $this->table . ' d JOIN ' . $this->operations() . ' o ON o.operation_id = d.operation_id';
        }
        if ($this->target->table === 'outcomes') {
            return $this->table . ' r LEFT JOIN ' . $this->operations() . ' op ON op.operation_id = r.operation_id';
        }
        return $this->table;
    }

    public function column(string $column): string
    {
        if ($this->alias === '') {
            return $column;
        }
        return $this->alias . '.' . $column;
    }

    public function operationTypeColumn(): string
    {
        if ($this->target->table === 'dead_letters') {
            return 'o.operation_type';
        }
        if ($this->target->table === 'outcomes') {
            return 'op.operation_type';
        }
        if ($this->target->operationType === '') {
            return 'NULL AS operation_type';
        }
        return $this->target->operationType;
    }

    public function schemaColumn(): string
    {
        if ($this->target->table === 'dead_letters') {
            return 'o.schema_version';
        }
        if ($this->target->table === 'outcomes') {
            return 'op.schema_version';
        }
        if ($this->target->schema === '') {
            return '1 AS schema_version';
        }
        return $this->target->schema;
    }

    private function operations(): string
    {
        return PostgreSqlIdentifier::schema($this->schema)->qualify('operations');
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationMode;

final readonly class PostgreSqlStorageProtectionRotationSelect
{
    public function __construct(
        private PostgreSqlStorageProtectionRotationRelation $relation,
    ) {}

    /** @return list<string> */
    public function columns(
        PostgreSqlStorageProtectionRotationTarget $target,
        StorageProtectionRotationMode $mode,
    ): array {
        $columns = [];
        if ($target->hasCompositeIdentity()) {
            $columns[] = $this->relation->column('scope_version');
        }
        $columns[] = $this->relation->column($target->identity);
        $columns[] = $this->relation->column('operation_id');
        $columns[] = $this->relation->operationTypeColumn();
        $columns[] = $this->relation->schemaColumn();
        $columns[] = $this->relation->column('tenant_type');
        $columns[] = $this->relation->column('tenant_id');
        foreach ($target->columns as $column) {
            $columns[] = $this->storageColumn($column, $mode);
        }
        return $columns;
    }

    private function storageColumn(string $column, StorageProtectionRotationMode $mode): string
    {
        if ($mode->writes()) {
            return $this->relation->column($column);
        }
        return 'substring(' . $this->relation->column($column) . ' FROM 1 FOR 136) AS ' . $column . '_header';
    }
}

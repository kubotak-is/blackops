<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;

final readonly class PostgreSqlDeferredOperationMessageCodec
{
    /**
     * @param array<string, mixed> $row
     */
    public function fromRow(array $row): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString($this->string($row, 'operation_id')),
            $this->string($row, 'operation_type'),
            $this->integer($row, 'schema_version'),
            $this->string($row, 'encoded_payload'),
            $this->string($row, 'encoded_context'),
            new DateTimeImmutable($this->string($row, 'available_at')),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key]) || $row[$key] === '') {
            throw new DeferredTransportException('Claimed operation row contains an invalid string field.');
        }

        return $row[$key];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function integer(array $row, string $key): int
    {
        if (!array_key_exists($key, $row) || !is_int($row[$key])) {
            throw new DeferredTransportException('Claimed operation row contains an invalid integer field.');
        }

        return $row[$key];
    }
}

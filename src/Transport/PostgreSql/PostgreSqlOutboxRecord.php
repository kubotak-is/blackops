<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PostgreSqlOutboxRecord
{
    public function __construct(
        public OutboxRecordId $recordId,
        public OperationId $operationId,
        public string $operationType,
        public int $schemaVersion,
        public string $encodedPayload,
        public string $encodedContext,
        public DateTimeImmutable $availableAt,
        public DateTimeImmutable $recordedAt,
        public string $connectionName,
    ) {
        if ($operationType === '' || $connectionName === '') {
            throw new InvalidArgumentException('Outbox record names must not be empty.');
        }
        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Outbox record schema version must be positive.');
        }
    }
}

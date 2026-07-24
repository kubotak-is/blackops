<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PostgreSqlOutboxRecord
{
    /** @mago-expect lint:excessive-parameter-list */
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
        public string $contentType = 'application/vnd.blackops.deferred-operation+json',
        public string $encoding = 'utf8',
        public int $attemptCount = 0,
        public string $state = 'pending',
        public ?DateTimeImmutable $nextAttemptAt = null,
        public ?string $failureFingerprint = null,
    ) {
        if ($operationType === '' || $connectionName === '') {
            throw new InvalidArgumentException('Outbox record names must not be empty.');
        }
        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Outbox record schema version must be positive.');
        }
        if (
            $attemptCount < 0
            || !in_array($state, ['pending', 'leased', 'retry_scheduled', 'sent', 'dead_lettered'], strict: true)
        ) {
            throw new InvalidArgumentException('Outbox record state or attempt count is invalid.');
        }
    }
}

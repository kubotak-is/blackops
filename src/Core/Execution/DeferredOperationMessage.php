<?php

declare(strict_types=1);

namespace BlackOps\Core\Execution;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use InvalidArgumentException;

#[PublicApi]
final readonly class DeferredOperationMessage
{
    public function __construct(
        private OperationId $operationId,
        private string $operationType,
        private int $schemaVersion,
        private string $encodedPayload,
        private string $encodedContext,
        private DateTimeImmutable $availableAt,
    ) {
        if ($operationType === '') {
            throw new InvalidArgumentException('Operation type must not be empty.');
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Schema version must be greater than zero.');
        }
    }

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function operationType(): string
    {
        return $this->operationType;
    }

    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function encodedPayload(): string
    {
        return $this->encodedPayload;
    }

    public function encodedContext(): string
    {
        return $this->encodedContext;
    }

    public function availableAt(): DateTimeImmutable
    {
        return $this->availableAt;
    }
}

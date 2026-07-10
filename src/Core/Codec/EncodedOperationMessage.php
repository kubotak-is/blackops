<?php

declare(strict_types=1);

namespace BlackOps\Core\Codec;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class EncodedOperationMessage
{
    public function __construct(
        private string $operationType,
        private int $schemaVersion,
        private string $encodedPayload,
        private string $encodedContext,
    ) {
        if ($operationType === '') {
            throw new InvalidArgumentException('Operation type must not be empty.');
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Schema version must be greater than zero.');
        }

        if ($encodedPayload === '') {
            throw new InvalidArgumentException('Encoded payload must not be empty.');
        }

        if ($encodedContext === '') {
            throw new InvalidArgumentException('Encoded context must not be empty.');
        }
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
}

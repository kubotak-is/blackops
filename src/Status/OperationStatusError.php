<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class OperationStatusError
{
    public const string OPERATION_FAILED = 'operation_failed';
    public const string OPERATION_DEAD_LETTERED = 'operation_dead_lettered';

    private function __construct(
        private ?string $category,
        private string $code,
    ) {
        if ($category !== null && !self::isStableIdentifier($category)) {
            throw new InvalidArgumentException('Operation status error requires a valid stable category.');
        }

        if (!self::isStableIdentifier($code)) {
            throw new InvalidArgumentException('Operation status error requires a valid stable code.');
        }
    }

    public static function rejected(string $category, string $code): self
    {
        return new self($category, $code);
    }

    public static function failed(): self
    {
        return new self(null, self::OPERATION_FAILED);
    }

    public static function deadLettered(): self
    {
        return new self(null, self::OPERATION_DEAD_LETTERED);
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function code(): string
    {
        return $this->code;
    }

    private static function isStableIdentifier(string $value): bool
    {
        return preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value) === 1;
    }
}

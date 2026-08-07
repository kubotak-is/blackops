<?php

declare(strict_types=1);

namespace BlackOps\OperationData;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class OperationDataPurpose
{
    private function __construct(
        private string $code,
    ) {}

    public static function fromString(string $code): self
    {
        $length = strlen($code);
        if ($length < 1 || $length > 128 || !preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $code)) {
            throw new InvalidArgumentException('Operation data purpose must be a valid code.');
        }

        return new self($code);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function toString(): string
    {
        return $this->code;
    }
}

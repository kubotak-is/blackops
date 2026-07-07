<?php

declare(strict_types=1);

namespace BlackOps\Core\Exception;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final class InvalidIdentifierException extends \InvalidArgumentException
{
    public static function invalidUuidV7(string $identifierType): self
    {
        return new self(sprintf('%s requires a valid UUID version 7.', $identifierType));
    }
}

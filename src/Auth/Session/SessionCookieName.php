<?php

declare(strict_types=1);

namespace BlackOps\Auth\Session;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class SessionCookieName
{
    private string $value;

    public function __construct(string $value)
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $value) !== 1) {
            throw new InvalidArgumentException('Session cookie name is invalid.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }
}

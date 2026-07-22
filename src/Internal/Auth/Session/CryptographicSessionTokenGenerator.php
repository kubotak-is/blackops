<?php

declare(strict_types=1);

namespace BlackOps\Internal\Auth\Session;

use BlackOps\Auth\Session\RawSessionToken;

final readonly class CryptographicSessionTokenGenerator implements SessionTokenGenerator
{
    public function generate(): RawSessionToken
    {
        return RawSessionToken::fromRandomBytes(random_bytes(32));
    }
}

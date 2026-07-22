<?php

declare(strict_types=1);

namespace BlackOps\Internal\Auth\Session;

use BlackOps\Auth\Session\RawSessionToken;

interface SessionTokenGenerator
{
    public function generate(): RawSessionToken;
}

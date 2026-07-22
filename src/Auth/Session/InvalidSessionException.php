<?php

declare(strict_types=1);

namespace BlackOps\Auth\Session;

use BlackOps\Core\Attribute\PublicApi;
use RuntimeException;

#[PublicApi]
final class InvalidSessionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Session is invalid.');
    }
}

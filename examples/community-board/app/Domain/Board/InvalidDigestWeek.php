<?php

declare(strict_types=1);

namespace App\Domain\Board;

use InvalidArgumentException;

final class InvalidDigestWeek extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Digest week must be a valid ISO week.');
    }
}

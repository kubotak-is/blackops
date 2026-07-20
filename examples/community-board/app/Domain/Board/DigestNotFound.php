<?php

declare(strict_types=1);

namespace App\Domain\Board;

use RuntimeException;

final class DigestNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Digest could not be found.');
    }
}

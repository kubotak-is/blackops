<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DomainException;

final class PostNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('The requested board post is unavailable.');
    }
}

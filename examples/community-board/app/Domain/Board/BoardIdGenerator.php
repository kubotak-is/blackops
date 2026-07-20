<?php

declare(strict_types=1);

namespace App\Domain\Board;

interface BoardIdGenerator
{
    public function generate(): string;
}

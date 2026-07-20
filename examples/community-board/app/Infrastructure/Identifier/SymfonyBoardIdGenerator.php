<?php

declare(strict_types=1);

namespace App\Infrastructure\Identifier;

use App\Domain\Board\BoardIdGenerator;
use Symfony\Component\Uid\UuidV7;

final readonly class SymfonyBoardIdGenerator implements BoardIdGenerator
{
    public function generate(): string
    {
        return UuidV7::generate();
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Identity\IdentityIdentifier;
use Symfony\Component\Uid\Uuid;

final readonly class RandomIdentityIdentifier implements IdentityIdentifier
{
    public function generate(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}

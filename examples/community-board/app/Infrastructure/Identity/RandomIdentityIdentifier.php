<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Identity\IdentityIdentifier;
use BlackOps\Identifier\Uuidv7Generator;

final readonly class RandomIdentityIdentifier implements IdentityIdentifier
{
    public function __construct(
        private Uuidv7Generator $uuids,
    ) {}

    public function generate(): string
    {
        return $this->uuids->generate();
    }
}

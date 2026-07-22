<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Identity\Exception\RegistrationDisabled;

final readonly class DisabledRegistrationPolicy implements RegistrationPolicy
{
    public function assertRegistrationAllowed(): void
    {
        throw new RegistrationDisabled();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Identity;

final readonly class EnabledRegistrationPolicy implements RegistrationPolicy
{
    public function assertRegistrationAllowed(): void {}
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Identity\UserRepository;
use BlackOps\Auth\Session\SessionIdentityProvider;
use BlackOps\Core\ActorRef;

final readonly class ApplicationSessionIdentityProvider implements SessionIdentityProvider
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function resolve(string $identityId): ?ActorRef
    {
        $user = $this->users->findById($identityId);

        return $user === null ? null : new ActorRef($user->id, 'user');
    }
}

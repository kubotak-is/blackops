<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Identity\Exception\DuplicateEmail;
use App\Domain\Identity\Exception\InvalidCredentials;
use SensitiveParameter;

final readonly class IdentityService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private RegistrationPolicy $registration,
        private IdentityIdentifier $identifiers,
        private IdentityClock $clock,
    ) {}

    public function register(string $email, string $displayName, #[SensitiveParameter] string $password): User
    {
        $this->registration->assertRegistrationAllowed();
        $canonicalEmail = $this->canonicalEmail($email);
        if ($this->users->findByEmail($canonicalEmail) !== null) {
            throw new DuplicateEmail();
        }

        $now = $this->clock->now();
        $user = new User(
            $this->identifiers->generate(),
            trim($email),
            $canonicalEmail,
            trim($displayName),
            $this->passwords->hash($password),
            $now,
            $now,
        );
        $this->users->save($user);

        return $user;
    }

    public function authenticate(string $email, #[SensitiveParameter] string $password): User
    {
        $user = $this->users->findByEmail($this->canonicalEmail($email));
        $verified = $this->passwords->verifyCredential($password, $user?->passwordHash);
        if ($user === null || !$verified) {
            throw new InvalidCredentials();
        }

        if ($this->passwords->needsRehash($user->passwordHash)) {
            $this->users->updatePasswordHash($user->id, $this->passwords->hash($password));
        }

        return $user;
    }

    private function canonicalEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}

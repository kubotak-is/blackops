<?php

declare(strict_types=1);

namespace App\Identity;

use DateInterval;

final readonly class IdentityService
{
    public function __construct(
        private IdentityRepository $repository,
        private PasswordHasher $passwords,
        private SessionToken $tokens,
        private IdentityClock $clock,
        private UuidGenerator $identifiers,
        private SessionSettings $settings,
    ) {}

    public function register(string $email, string $displayName, string $password): AuthenticatedSession
    {
        $emailDisplay = trim($email);
        $emailCanonical = strtolower($emailDisplay);
        $passwordHash = $this->passwords->hash($password);
        $userId = $this->identifiers->generate();
        $user = new User($userId, $emailDisplay, trim($displayName));
        $now = $this->clock->now();
        $rawToken = $this->tokens->issue();

        $this->repository->transactional(function () use (
            $emailCanonical,
            $passwordHash,
            $rawToken,
            $user,
            $now,
        ): void {
            $this->repository->createUser(
                id: $user->id,
                emailCanonical: $emailCanonical,
                emailDisplay: $user->email,
                displayName: $user->displayName,
                passwordHash: $passwordHash,
                now: $now,
            );
            $this->createSession($user->id, $rawToken, $now);
        });

        return new AuthenticatedSession($user, $rawToken);
    }

    public function login(string $email, string $password, ?string $currentRawToken): AuthenticatedSession
    {
        $emailCanonical = strtolower(trim($email));
        $now = $this->clock->now();
        $newRawToken = $this->tokens->issue();

        return $this->repository->transactional(function () use (
            $emailCanonical,
            $password,
            $currentRawToken,
            $now,
            $newRawToken,
        ): AuthenticatedSession {
            $stored = $this->repository->findByCanonicalEmail($emailCanonical);
            if (!$this->passwords->verifyCredential($password, $stored?->passwordHash)) {
                throw new InvalidCredentials('Credentials are invalid.');
            }

            if ($stored === null) {
                throw new InvalidCredentials('Credentials are invalid.');
            }

            if ($this->passwords->needsRehash($stored->passwordHash)) {
                $this->repository->updatePasswordHash($stored->id, $this->passwords->hash($password), $now);
            }

            if ($currentRawToken !== null && $this->tokens->isValid($currentRawToken)) {
                $this->repository->revokeSession($this->tokens->hash($currentRawToken), $now);
            }

            $this->createSession($stored->id, $newRawToken, $now);

            return new AuthenticatedSession($stored->user(), $newRawToken);
        });
    }

    public function logout(?string $rawToken): void
    {
        if ($rawToken === null || !$this->tokens->isValid($rawToken)) {
            return;
        }

        $this->repository->revokeSession($this->tokens->hash($rawToken), $this->clock->now());
    }

    public function authenticate(string $rawToken): ?User
    {
        if (!$this->tokens->isValid($rawToken)) {
            return null;
        }

        return $this->repository->findByActiveTokenHash($this->tokens->hash($rawToken), $this->clock->now());
    }

    private function createSession(string $userId, string $rawToken, \DateTimeImmutable $now): void
    {
        $this->repository->createSession(
            id: $this->identifiers->generate(),
            userId: $userId,
            tokenHash: $this->tokens->hash($rawToken),
            issuedAt: $now,
            expiresAt: $now->add(new DateInterval('PT' . $this->settings->ttlSeconds . 'S')),
        );
    }
}

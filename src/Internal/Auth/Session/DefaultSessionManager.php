<?php

declare(strict_types=1);

namespace BlackOps\Internal\Auth\Session;

use BlackOps\Auth\Session\InvalidSessionException;
use BlackOps\Auth\Session\IssuedSession;
use BlackOps\Auth\Session\SessionConfiguration;
use BlackOps\Auth\Session\SessionIdentityProvider;
use BlackOps\Auth\Session\SessionManager;
use BlackOps\Core\ActorRef;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use SensitiveParameter;

final readonly class DefaultSessionManager implements SessionManager
{
    public function __construct(
        private SessionConfiguration $configuration,
        private SessionIdentityProvider $identities,
        private SessionStore $store,
        private SessionTokenGenerator $tokens,
        private SessionIdentifierGenerator $identifiers,
        private SessionClock $clock,
        private SessionTokenCodec $codec = new SessionTokenCodec(),
    ) {}

    public function issue(string $identityId): IssuedSession
    {
        $identityId = $this->identityId($identityId);
        $issuedAt = $this->clock->now();
        $expiresAt = $issuedAt->add(new DateInterval('PT' . $this->configuration->ttlSeconds . 'S'));
        $token = $this->tokens->generate();
        $tokenHash = $this->codec->hash($token->reveal());

        if ($tokenHash === null) {
            throw new InvalidArgumentException('Generated session token is invalid.');
        }

        $this->store->insert(
            new NewSessionRecord(
                $this->identifiers->generate($issuedAt),
                $identityId,
                $tokenHash,
                $issuedAt,
                $expiresAt,
            ),
        );

        return new IssuedSession($token, $issuedAt, $expiresAt);
    }

    public function authenticate(#[SensitiveParameter] string $rawToken): ?ActorRef
    {
        $tokenHash = $this->codec->hash($rawToken);

        if ($tokenHash === null) {
            return null;
        }

        $now = $this->clock->now();
        $threshold = $now->sub(new DateInterval('PT' . $this->configuration->touchIntervalSeconds . 'S'));

        $identityId = $this->store->authenticate($tokenHash, $now, $threshold);

        return $identityId === null ? null : $this->identities->resolve($identityId);
    }

    public function rotate(#[SensitiveParameter] string $rawToken): IssuedSession
    {
        $tokenHash = $this->codec->hash($rawToken);

        if ($tokenHash === null) {
            throw new InvalidSessionException();
        }

        $issuedAt = $this->clock->now();
        $expiresAt = $issuedAt->add(new DateInterval('PT' . $this->configuration->ttlSeconds . 'S'));
        $successor = $this->tokens->generate();
        $successorHash = $this->codec->hash($successor->reveal());

        if ($successorHash === null) {
            throw new InvalidArgumentException('Generated session token is invalid.');
        }

        $identityId = $this->store->rotate(
            $tokenHash,
            $this->identifiers->generate($issuedAt),
            $successorHash,
            $issuedAt,
            $expiresAt,
        );

        if ($identityId === null) {
            throw new InvalidSessionException();
        }

        return new IssuedSession($successor, $issuedAt, $expiresAt);
    }

    public function revoke(#[SensitiveParameter] ?string $rawToken): void
    {
        if ($rawToken === null) {
            return;
        }

        $tokenHash = $this->codec->hash($rawToken);

        if ($tokenHash === null) {
            return;
        }

        $this->store->revoke($tokenHash, $this->clock->now());
    }

    public function cleanup(DateTimeImmutable $retentionCutoff): int
    {
        return $this->store->cleanup($retentionCutoff, $this->clock->now());
    }

    private function identityId(string $identityId): string
    {
        if ($identityId === '' || strlen($identityId) > 255 || preg_match('/[\x00-\x1F\x7F]/', $identityId) === 1) {
            throw new InvalidArgumentException('Session identity is invalid.');
        }

        return $identityId;
    }
}

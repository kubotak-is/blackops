<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Identity\IdentityService;
use App\Identity\PasswordHasher;
use App\Identity\SessionSettings;
use App\Identity\SessionToken;
use App\Security\SessionHttpAuthenticator;
use App\Tests\Support\FrozenIdentityClock;
use App\Tests\Support\InMemoryIdentityRepository;
use App\Tests\Support\SequenceUuidGenerator;
use DateInterval;
use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class SessionHttpAuthenticatorTest extends TestCase
{
    public function testOnlyValidActiveBearerBecomesAUserActor(): void
    {
        $repository = new InMemoryIdentityRepository();
        $clock = new FrozenIdentityClock(new DateTimeImmutable('2026-07-20T00:00:00+00:00'));
        $tokens = new SessionToken();
        $service = new IdentityService(
            $repository,
            new PasswordHasher(),
            $tokens,
            $clock,
            new SequenceUuidGenerator([
                '019b1111-1111-7111-8111-111111111111',
                '019b1111-1111-7111-8111-111111111112',
            ]),
            new SessionSettings(60),
        );
        $session = $service->register('person@example.com', 'Person', 'a sufficiently long password');
        $authenticator = new SessionHttpAuthenticator($repository, $tokens, $clock);

        $anonymous = $authenticator->authenticate(new ServerRequest('GET', '/me'));
        $malformed = $authenticator->authenticate($this->request('Bearer malformed'));
        $unknown = $authenticator->authenticate($this->request('Bearer ' . str_repeat('A', 43)));
        $valid = $authenticator->authenticate($this->request('Bearer ' . $session->rawToken));

        self::assertTrue($anonymous->isAnonymous());
        self::assertTrue($malformed->isInvalid());
        self::assertTrue($unknown->isInvalid());
        self::assertSame('authentication.invalid_session', $unknown->code());
        self::assertTrue($valid->isAuthenticated());
        self::assertSame('user', $valid->actor()?->type());
        self::assertSame($session->user->id, $valid->actor()?->id());

        $clock->current = $clock->current->add(new DateInterval('PT61S'));
        self::assertTrue($authenticator->authenticate($this->request('Bearer ' . $session->rawToken))->isInvalid());
    }

    private function request(string $authorization): ServerRequest
    {
        return new ServerRequest('GET', '/me', ['Authorization' => $authorization]);
    }
}

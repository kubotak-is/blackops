<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\AuthenticationHttpHandler;
use App\Identity\IdentityService;
use App\Identity\PasswordHasher;
use App\Identity\SessionSettings;
use App\Identity\SessionToken;
use App\Tests\Support\FrozenIdentityClock;
use App\Tests\Support\InMemoryIdentityRepository;
use App\Tests\Support\SequenceUuidGenerator;
use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class AuthenticationHttpHandlerTest extends TestCase
{
    public function testRegistrationReturnsSafeSessionAndPrivateNoStoreHeaders(): void
    {
        $handler = $this->handler();
        $password = 'password-marker-never-reflected';
        $response = $handler->register($this->jsonRequest('/auth/users', [
            'email' => 'person@example.com',
            'displayName' => 'Person',
            'password' => $password,
        ]));
        $payload = $this->payload($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('no-cache', $response->getHeaderLine('Pragma'));
        self::assertSame('person@example.com', $payload['user']['email']);
        self::assertMatchesRegularExpression('/\\A[A-Za-z0-9_-]{43}\\z/D', $payload['sessionToken']);
        self::assertStringNotContainsString($password, (string) $response->getBody());
    }

    public function testValidationNeverReflectsRejectedPassword(): void
    {
        $handler = $this->handler();
        $password = 'short-marker';
        $response = $handler->register($this->jsonRequest('/auth/users', [
            'email' => 'not-an-email',
            'displayName' => '',
            'password' => $password,
        ]));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('identity.validation_failed', $this->payload($response)['code']);
        self::assertStringNotContainsString($password, (string) $response->getBody());
    }

    public function testProtocolFailuresUseStableSafeCodes(): void
    {
        $handler = $this->handler();
        $unsupported = new ServerRequest('POST', '/auth/users', ['Content-Type' => 'text/plain'], '{}');
        $unknown = $this->jsonRequest('/auth/users', [
            'email' => 'person@example.com',
            'displayName' => 'Person',
            'password' => 'a sufficiently long password',
            'unexpected' => 'must not pass',
        ]);
        $malformed = new ServerRequest('POST', '/auth/users', ['Content-Type' => 'application/json'], '{');

        self::assertSame('identity.unsupported_media_type', $this->payload($handler->register($unsupported))['code']);
        self::assertSame('identity.invalid_request', $this->payload($handler->register($unknown))['code']);
        self::assertSame('identity.invalid_request', $this->payload($handler->register($malformed))['code']);
    }

    public function testDuplicateAndInvalidCredentialsHaveStablePublicErrors(): void
    {
        $handler = $this->handler();
        $registration = [
            'email' => 'person@example.com',
            'displayName' => 'Person',
            'password' => 'a sufficiently long password',
        ];
        $handler->register($this->jsonRequest('/auth/users', $registration));

        $duplicate = $handler->register($this->jsonRequest('/auth/users', $registration));
        $unknown = $handler->login($this->jsonRequest('/auth/sessions', [
            'email' => 'unknown@example.com',
            'password' => 'a sufficiently long password',
        ]));
        $wrong = $handler->login($this->jsonRequest('/auth/sessions', [
            'email' => 'person@example.com',
            'password' => 'a sufficiently wrong password',
        ]));

        self::assertSame(409, $duplicate->getStatusCode());
        self::assertSame('identity.email_unavailable', $this->payload($duplicate)['code']);
        self::assertSame(401, $unknown->getStatusCode());
        self::assertSame($this->payload($unknown), $this->payload($wrong));
        self::assertSame('authentication.invalid_credentials', $this->payload($wrong)['code']);
    }

    public function testLogoutIsIdempotentWithoutCredentialAndRejectsMalformedBearer(): void
    {
        $handler = $this->handler();
        $missing = $handler->logout(new ServerRequest('DELETE', '/auth/sessions/current'));
        $malformed = $handler->logout(new ServerRequest('DELETE', '/auth/sessions/current', [
            'Authorization' => 'Bearer malformed',
        ]));

        self::assertSame(204, $missing->getStatusCode());
        self::assertSame('', (string) $missing->getBody());
        self::assertSame(400, $malformed->getStatusCode());
        self::assertSame('identity.invalid_request', $this->payload($malformed)['code']);
    }

    private function handler(): AuthenticationHttpHandler
    {
        return new AuthenticationHttpHandler(
            new IdentityService(
                new InMemoryIdentityRepository(),
                new PasswordHasher(),
                new SessionToken(),
                new FrozenIdentityClock(new DateTimeImmutable('2026-07-20T00:00:00+00:00')),
                new SequenceUuidGenerator([
                    '019b1111-1111-7111-8111-111111111111',
                    '019b1111-1111-7111-8111-111111111112',
                    '019b1111-1111-7111-8111-111111111113',
                    '019b1111-1111-7111-8111-111111111114',
                    '019b1111-1111-7111-8111-111111111115',
                    '019b1111-1111-7111-8111-111111111116',
                ]),
                new SessionSettings(),
            ),
        );
    }

    /** @param array<string, mixed> $body */
    private function jsonRequest(string $path, array $body): ServerRequest
    {
        return new ServerRequest(
            'POST',
            $path,
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    private function payload(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}

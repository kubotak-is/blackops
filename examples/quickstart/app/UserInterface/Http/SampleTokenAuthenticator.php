<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use BlackOps\Core\ActorRef;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class SampleTokenAuthenticator implements HttpAuthenticator
{
    private string $expectedToken;

    public function __construct()
    {
        $expectedToken = $_ENV['SAMPLE_API_TOKEN'] ?? null;

        if (!is_string($expectedToken) || trim($expectedToken) === '') {
            throw new RuntimeException('SAMPLE_API_TOKEN must be configured with a non-empty value.');
        }

        $this->expectedToken = $expectedToken;
    }

    public function authenticate(ServerRequestInterface $request): AuthenticationResult
    {
        $token = $request->getHeaderLine('X-Sample-Token');

        if ($token === '') {
            return AuthenticationResult::anonymous();
        }

        if (!hash_equals($this->expectedToken, $token)) {
            return AuthenticationResult::invalid('authentication.invalid_sample_token');
        }

        return AuthenticationResult::authenticated(new ActorRef('quickstart-user', 'user'));
    }
}

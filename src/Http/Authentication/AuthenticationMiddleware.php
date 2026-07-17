<?php

declare(strict_types=1);

namespace BlackOps\Http\Authentication;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use LogicException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;

#[PublicApi]
final readonly class AuthenticationMiddleware implements MiddlewareInterface
{
    private ResponseFactoryInterface $responses;
    private StreamFactoryInterface $streams;

    public function __construct(
        private HttpAuthenticator $authenticator,
        ?ResponseFactoryInterface $responses = null,
        ?StreamFactoryInterface $streams = null,
    ) {
        if ($responses !== null && $streams !== null) {
            $this->responses = $responses;
            $this->streams = $streams;

            return;
        }

        $default = $this->defaultFactory();
        $this->responses = $responses ?? $default;
        $this->streams = $streams ?? $default;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result = $this->authenticator->authenticate($request);

        if ($result->isAnonymous()) {
            return $handler->handle($request);
        }

        if ($result->isAuthenticated()) {
            $actor = $result->actor();

            if ($actor === null) {
                throw new LogicException('Authenticated result requires an actor.');
            }

            return $handler->handle($request->withAttribute(ActorRef::class, $actor));
        }

        $code = $result->code();

        if (!$result->isInvalid() || $code === null) {
            throw new LogicException('Authentication result has an invalid state.');
        }

        $body = json_encode([
            'status' => 'error',
            'category' => 'unauthorized',
            'code' => $code,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return $this->responses
            ->createResponse(401)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streams->createStream($body));
    }

    private function defaultFactory(): ResponseFactoryInterface&StreamFactoryInterface
    {
        /** @var class-string $factoryClass */
        $factoryClass = implode('\\', ['Nyholm', 'Psr7', 'Factory', 'Psr17Factory']);
        $factory = new ReflectionClass($factoryClass)->newInstance();

        if (!$factory instanceof ResponseFactoryInterface || !$factory instanceof StreamFactoryInterface) {
            throw new LogicException('Framework PSR-17 factory is unavailable.');
        }

        return $factory;
    }
}

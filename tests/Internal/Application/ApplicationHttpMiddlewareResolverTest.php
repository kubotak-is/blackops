<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationHttpMiddlewareConfiguration;
use BlackOps\Internal\Application\ApplicationHttpMiddlewareResolver;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ApplicationHttpMiddlewareResolverTest extends TestCase
{
    public function testResolvesMiddlewareInConfiguredOrder(): void
    {
        $outer = new ResolvedMiddleware('outer');
        $inner = new ResolvedMiddleware('inner');
        $configuration = ApplicationHttpMiddlewareConfiguration::fromConfiguration([
            'middleware' => ['http' => ['outer', 'inner']],
        ]);

        $resolved = new ApplicationHttpMiddlewareResolver(new MiddlewareTestContainer([
            'outer' => $outer,
            'inner' => $inner,
        ]))->resolve($configuration);

        self::assertSame([$outer, $inner], $resolved);
    }

    public function testRejectsUnavailableServiceWithoutExposingId(): void
    {
        $id = 'credential-that-must-not-appear';
        $configuration = ApplicationHttpMiddlewareConfiguration::fromConfiguration([
            'middleware' => ['http' => [$id]],
        ]);

        try {
            new ApplicationHttpMiddlewareResolver(new MiddlewareTestContainer([]))->resolve($configuration);
            self::fail('Expected unavailable middleware service.');
        } catch (LogicException $exception) {
            self::assertStringNotContainsString($id, $exception->getMessage());
        }
    }

    public function testRejectsServiceThatIsNotPsr15Middleware(): void
    {
        $configuration = ApplicationHttpMiddlewareConfiguration::fromConfiguration([
            'middleware' => ['http' => ['invalid']],
        ]);

        $this->expectException(LogicException::class);

        new ApplicationHttpMiddlewareResolver(new MiddlewareTestContainer(['invalid' => new \stdClass()]))->resolve(
            $configuration,
        );
    }

    public function testWrapsContainerFailureWithoutLeakingServiceOrCredential(): void
    {
        $serviceId = 'middleware-with-secret-id';
        $credential = 'credential-that-must-not-appear';
        $configuration = ApplicationHttpMiddlewareConfiguration::fromConfiguration([
            'middleware' => ['http' => [$serviceId]],
        ]);

        try {
            new ApplicationHttpMiddlewareResolver(new ThrowingMiddlewareTestContainer($credential))->resolve(
                $configuration,
            );
            self::fail('Expected middleware resolution failure.');
        } catch (LogicException $exception) {
            self::assertStringNotContainsString($serviceId, $exception->getMessage());
            self::assertStringNotContainsString($credential, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}

final readonly class ResolvedMiddleware implements MiddlewareInterface
{
    public function __construct(
        public string $name,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

final readonly class MiddlewareTestContainer implements ContainerInterface
{
    /** @param array<string, object> $services */
    public function __construct(
        private array $services,
    ) {}

    public function get(string $id): mixed
    {
        return $this->services[$id] ?? throw new LogicException('Service unavailable.');
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

final readonly class ThrowingMiddlewareTestContainer implements ContainerInterface
{
    public function __construct(
        private string $credential,
    ) {}

    public function get(string $id): mixed
    {
        throw new LogicException('Resolution failed with ' . $this->credential);
    }

    public function has(string $id): bool
    {
        return true;
    }
}

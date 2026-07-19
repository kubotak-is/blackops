<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Http;

use BlackOps\Internal\Http\OperationStatusAuthorizerResolver;
use BlackOps\Status\DenyOperationStatusAuthorizer;
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class OperationStatusAuthorizerResolverTest extends TestCase
{
    public function testMissingApplicationBindingUsesDefaultDeny(): void
    {
        $authorizer = new OperationStatusAuthorizerResolver(new StatusAuthorizerContainer())->resolve();

        self::assertInstanceOf(DenyOperationStatusAuthorizer::class, $authorizer);
    }

    public function testApplicationBindingIsUsedWithoutFrameworkOverride(): void
    {
        $expected = new AllowStatusAuthorizer();

        $authorizer = new OperationStatusAuthorizerResolver(new StatusAuthorizerContainer($expected))->resolve();

        self::assertSame($expected, $authorizer);
    }

    public function testInvalidOrFailingBindingIsSafeBootstrapFailure(): void
    {
        foreach ([new \stdClass(), new RuntimeException('container credential detail')] as $service) {
            try {
                new OperationStatusAuthorizerResolver(new StatusAuthorizerContainer($service))->resolve();
                self::fail('Expected invalid operation status authorizer binding.');
            } catch (LogicException $exception) {
                self::assertStringNotContainsString('credential', $exception->getMessage());
            }
        }
    }
}

final readonly class StatusAuthorizerContainer implements ContainerInterface
{
    public function __construct(
        private ?object $service = null,
    ) {}

    public function get(string $id): mixed
    {
        if ($this->service instanceof RuntimeException) {
            throw $this->service;
        }

        return $this->service ?? throw new LogicException('Missing service.');
    }

    public function has(string $id): bool
    {
        return $this->service !== null;
    }
}

final readonly class AllowStatusAuthorizer implements OperationStatusAuthorizer
{
    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        return OperationStatusAuthorizationDecision::allow();
    }
}

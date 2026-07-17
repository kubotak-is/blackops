<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Authorization;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuthorizationRequestTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const string CORRELATION_ID = '019f32ac-2be0-7b38-a0a7-1ab2f9687697';

    public function testIsFinalReadonlyPublicApi(): void
    {
        $reflection = new ReflectionClass(AuthorizationRequest::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }

    public function testAuthorizationPolicyIsPublicSingleMethodContract(): void
    {
        $reflection = new ReflectionClass(AuthorizationPolicy::class);

        self::assertTrue($reflection->isInterface());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertSame(
            ['decide'],
            array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $reflection->getMethods()),
        );
    }

    public function testExposesOnlyOperationValueContextAndAuthorizationActor(): void
    {
        $operation = new RequestOperation();
        $value = new RequestValue();
        $actor = new ActorRef('user-123', 'user');
        $context = $this->context($actor);
        $request = new AuthorizationRequest($operation, $value, $context, $actor);

        self::assertSame($operation, $request->operation());
        self::assertSame($value, $request->value());
        self::assertSame($context, $request->context());
        self::assertSame($actor, $request->actor());
        self::assertSame(
            ['operation', 'value', 'context', 'actor'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                new ReflectionClass($request)->getProperties(),
            ),
        );
    }

    public function testAcceptsEquivalentAuthorizationActorValue(): void
    {
        $context = $this->context(new ActorRef('user-123', 'user'));
        $actor = new ActorRef('user-123', 'user');

        self::assertSame(
            $actor,
            new AuthorizationRequest(new RequestOperation(), new RequestValue(), $context, $actor)->actor(),
        );
    }

    public function testRejectsActorThatDoesNotMatchContextAuthorization(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuthorizationRequest(
            new RequestOperation(),
            new RequestValue(),
            $this->context(new ActorRef('user-123', 'user')),
            new ActorRef('user-456', 'user'),
        );
    }

    public function testRejectsContextWithoutAuthorizationActor(): void
    {
        $context = new ExecutionContext(
            OperationId::fromString(self::OPERATION_ID),
            new DateTimeImmutable('2026-07-17T00:00:00Z'),
            CorrelationId::fromString(self::CORRELATION_ID),
            actorContext: new ActorContext(null, null, new ActorRef('http-runtime', 'system')),
        );

        $this->expectException(InvalidArgumentException::class);

        new AuthorizationRequest(
            new RequestOperation(),
            new RequestValue(),
            $context,
            new ActorRef('user-123', 'user'),
        );
    }

    private function context(ActorRef $authorization): ExecutionContext
    {
        return new ExecutionContext(
            OperationId::fromString(self::OPERATION_ID),
            new DateTimeImmutable('2026-07-17T00:00:00Z'),
            CorrelationId::fromString(self::CORRELATION_ID),
            actorContext: new ActorContext($authorization, $authorization, new ActorRef('http-runtime', 'system')),
        );
    }
}

final readonly class RequestOperation implements Operation {}

final readonly class RequestValue implements OperationValue {}

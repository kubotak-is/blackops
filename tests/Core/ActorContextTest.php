<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ActorContextTest extends TestCase
{
    public function testIsFinalReadonlyClassMarkedPublicApi(): void
    {
        $reflection = new ReflectionClass(ActorContext::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }

    public function testExposesOriginAuthorizationAndExecutionActors(): void
    {
        $origin = new ActorRef('user-123', 'user');
        $authorization = new ActorRef('service-456', 'service');
        $execution = new ActorRef('http-runtime', 'system');
        $context = new ActorContext($origin, $authorization, $execution);

        self::assertSame($origin, $context->origin());
        self::assertSame($authorization, $context->authorization());
        self::assertSame($execution, $context->execution());
    }

    public function testAllowsAnonymousOriginAndAuthorization(): void
    {
        $execution = new ActorRef('http-runtime', 'system');
        $context = new ActorContext(null, null, $execution);

        self::assertNull($context->origin());
        self::assertNull($context->authorization());
        self::assertSame($execution, $context->execution());
    }
}

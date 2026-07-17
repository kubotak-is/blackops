<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http\Authentication;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Http\Authentication\AuthenticationResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuthenticationResultTest extends TestCase
{
    public function testIsFinalReadonlyPublicApiWithPrivateConstructor(): void
    {
        $reflection = new ReflectionClass(AuthenticationResult::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertTrue($reflection->getConstructor()?->isPrivate());
    }

    public function testAnonymousHasNoActorOrCode(): void
    {
        $result = AuthenticationResult::anonymous();

        self::assertTrue($result->isAnonymous());
        self::assertFalse($result->isAuthenticated());
        self::assertFalse($result->isInvalid());
        self::assertNull($result->actor());
        self::assertNull($result->code());
    }

    public function testAuthenticatedContainsOnlyActor(): void
    {
        $actor = new ActorRef('user-123', 'user');
        $result = AuthenticationResult::authenticated($actor);

        self::assertFalse($result->isAnonymous());
        self::assertTrue($result->isAuthenticated());
        self::assertFalse($result->isInvalid());
        self::assertSame($actor, $result->actor());
        self::assertNull($result->code());
    }

    public function testInvalidContainsOnlyStableCode(): void
    {
        $result = AuthenticationResult::invalid('authentication.invalid');

        self::assertFalse($result->isAnonymous());
        self::assertFalse($result->isAuthenticated());
        self::assertTrue($result->isInvalid());
        self::assertNull($result->actor());
        self::assertSame('authentication.invalid', $result->code());
    }

    public function testInvalidRejectsUnstableCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AuthenticationResult::invalid('Bearer secret token');
    }
}

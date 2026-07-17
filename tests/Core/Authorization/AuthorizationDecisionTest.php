<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Authorization;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Authorization\AuthorizationDecision;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuthorizationDecisionTest extends TestCase
{
    public function testIsFinalReadonlyPublicApiWithPrivateConstructor(): void
    {
        $reflection = new ReflectionClass(AuthorizationDecision::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertTrue($reflection->getConstructor()?->isPrivate());
    }

    public function testAllowHasNoCode(): void
    {
        $decision = AuthorizationDecision::allow();

        self::assertTrue($decision->isAllowed());
        self::assertFalse($decision->isUnauthorized());
        self::assertFalse($decision->isForbidden());
        self::assertNull($decision->code());
    }

    public function testUnauthorizedContainsOnlyStableCode(): void
    {
        $decision = AuthorizationDecision::unauthorized('authorization.authentication_required');

        self::assertFalse($decision->isAllowed());
        self::assertTrue($decision->isUnauthorized());
        self::assertFalse($decision->isForbidden());
        self::assertSame('authorization.authentication_required', $decision->code());
    }

    public function testForbiddenContainsOnlyStableCode(): void
    {
        $decision = AuthorizationDecision::forbid('authorization.order_forbidden');

        self::assertFalse($decision->isAllowed());
        self::assertFalse($decision->isUnauthorized());
        self::assertTrue($decision->isForbidden());
        self::assertSame('authorization.order_forbidden', $decision->code());
    }

    public static function unstableCodes(): iterable
    {
        yield 'unauthorized' => [
            static fn(): AuthorizationDecision => AuthorizationDecision::unauthorized('Bearer secret token'),
        ];
        yield 'forbidden' => [
            static fn(): AuthorizationDecision => AuthorizationDecision::forbid('Permission denied!'),
        ];
    }

    #[DataProvider('unstableCodes')]
    public function testRejectsUnstableCode(callable $factory): void
    {
        $this->expectException(InvalidArgumentException::class);

        $factory();
    }
}

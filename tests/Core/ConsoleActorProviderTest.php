<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Console\ConsoleActorProvider;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ConsoleActorProviderTest extends TestCase
{
    public function testIsMinimalPublicInterfaceReturningOptionalActorRef(): void
    {
        $reflection = new ReflectionClass(ConsoleActorProvider::class);

        self::assertTrue($reflection->isInterface());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertSame(
            ['actor'],
            array_map(static fn($method): string => $method->getName(), $reflection->getMethods()),
        );
        self::assertSame('?BlackOps\\Core\\ActorRef', (string) $reflection->getMethod('actor')->getReturnType());
        self::assertNull(new NullConsoleActorProvider()->actor());
        self::assertSame('console-user', new ActorConsoleActorProvider()->actor()?->id());
    }
}

final readonly class NullConsoleActorProvider implements ConsoleActorProvider
{
    public function actor(): ?ActorRef
    {
        return null;
    }
}

final readonly class ActorConsoleActorProvider implements ConsoleActorProvider
{
    public function actor(): ?ActorRef
    {
        return new ActorRef('console-user', 'user');
    }
}

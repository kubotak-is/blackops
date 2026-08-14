<?php

declare(strict_types=1);

namespace BlackOps\Tests\Scheduling;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\ScheduleContext;
use BlackOps\Scheduling\ScheduledActorProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ScheduledActorProviderTest extends TestCase
{
    public function testPublicContractAcceptsScheduleContextAndNullableActor(): void
    {
        $reflection = new ReflectionClass(ScheduledActorProvider::class);
        $method = $reflection->getMethod('actor');

        self::assertTrue($reflection->isInterface());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        self::assertSame(ScheduleContext::class, $method->getParameters()[0]->getType()?->getName());
        self::assertSame(ActorRef::class, $method->getReturnType()?->getName());
        self::assertTrue($method->getReturnType()?->allowsNull());
        self::assertNotEmpty(new ScheduleContext('reports.daily', new DateTimeImmutable('now'), 'UTC')->name());
    }
}

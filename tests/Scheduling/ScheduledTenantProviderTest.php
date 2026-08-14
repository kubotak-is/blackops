<?php

declare(strict_types=1);

namespace BlackOps\Tests\Scheduling;

use BlackOps\Scheduling\ScheduledActorProvider;
use BlackOps\Scheduling\ScheduledTenantProvider;
use PHPUnit\Framework\TestCase;

final class ScheduledTenantProviderTest extends TestCase
{
    public function testTenantPortIsIndependentFromActorPort(): void
    {
        self::assertNotSame(ScheduledActorProvider::class, ScheduledTenantProvider::class);
        self::assertTrue(interface_exists(ScheduledTenantProvider::class));
    }
}

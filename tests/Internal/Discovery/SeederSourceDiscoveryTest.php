<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Discovery;

use BlackOps\Internal\Discovery\SeederSourceDiscovery;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\FixtureDatabaseSeeder;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\FixtureFirstSeeder;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\FixtureSecondSeeder;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\SeederFixtureState;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Application/Fixture/SeederDiscovery/FixtureSeeders.php';

final class SeederSourceDiscoveryTest extends TestCase
{
    public function testDiscoversOnlyInstantiableSeedersWithoutConstructingThem(): void
    {
        SeederFixtureState::reset();

        $seeders = new SeederSourceDiscovery()->discover([
            __DIR__ . '/../Application/Fixture/SeederDiscovery',
        ]);

        self::assertSame(
            [
                FixtureDatabaseSeeder::class,
                FixtureFirstSeeder::class,
                FixtureSecondSeeder::class,
            ],
            $seeders,
        );
        self::assertSame(0, SeederFixtureState::$rootConstructions);
        self::assertSame(0, SeederFixtureState::$childConstructions);
    }
}

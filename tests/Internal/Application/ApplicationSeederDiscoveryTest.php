<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationSeederConfiguration;
use BlackOps\Internal\Application\ApplicationSeederDiscovery;
use BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery\NotASeeder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixture/SeederDiscovery/FixtureSeeders.php';

final class ApplicationSeederDiscoveryTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testMissingDefaultRootIsOptionalAndPresentDefaultRootIsSelected(): void
    {
        $basePath = $this->directory();
        $seedDirectory = $basePath . '/app/Infrastructure/Seed';
        mkdir($seedDirectory, recursive: true);
        $snapshot = new ApplicationConfigurationSnapshot($basePath, [], [], [], []);

        $missing = new ApplicationSeederDiscovery()->discover($snapshot);
        self::assertSame([], $missing->seeders);
        self::assertNull($missing->root);

        file_put_contents($seedDirectory . '/DatabaseSeeder.php', <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace App\Infrastructure\Seed;
            use BlackOps\Database\Seeder;
            final readonly class DatabaseSeeder implements Seeder
            {
                public function run(): void {}
            }
            PHP);

        $configured = new ApplicationSeederDiscovery()->discover($snapshot);
        self::assertSame([ApplicationSeederConfiguration::DEFAULT_ROOT], $configured->seeders);
        self::assertSame(ApplicationSeederConfiguration::DEFAULT_ROOT, $configured->root);
    }

    public function testExplicitRootMustBeASeederInsideTheConfiguredDiscoveryRoots(): void
    {
        $basePath = dirname(path: __DIR__, levels: 3);
        $snapshot = new ApplicationConfigurationSnapshot(
            $basePath,
            [
                'database' => [
                    'seeding' => [
                        'root' => NotASeeder::class,
                        'discovery' => [__DIR__ . '/Fixture/SeederDiscovery'],
                    ],
                ],
            ],
            [],
            [],
            [],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configured root seeder was not discovered.');

        new ApplicationSeederDiscovery()->discover($snapshot);
    }
}

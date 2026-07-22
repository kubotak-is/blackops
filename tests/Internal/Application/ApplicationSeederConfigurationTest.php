<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationSeederConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ApplicationSeederConfigurationTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testUsesMissingSafeDefaultConvention(): void
    {
        $basePath = $this->directory();
        $configuration = ApplicationSeederConfiguration::fromSnapshot($this->snapshotFor($basePath));

        self::assertSame(ApplicationSeederConfiguration::DEFAULT_ROOT, $configuration->root);
        self::assertFalse($configuration->explicitRoot);
        self::assertSame([], $configuration->discoveryRoots);

        $seedDirectory = $basePath . '/app/Infrastructure/Seed';
        mkdir($seedDirectory, recursive: true);
        $configuration = ApplicationSeederConfiguration::fromSnapshot($this->snapshotFor($basePath));

        self::assertSame([realpath($seedDirectory)], $configuration->discoveryRoots);
    }

    public function testMergesExplicitRootAndDiscoveryWithDefaultsIndependently(): void
    {
        $basePath = $this->directory();
        $default = $basePath . '/app/Infrastructure/Seed';
        $custom = $basePath . '/app/Database/Seed';
        mkdir($default, recursive: true);
        mkdir($custom, recursive: true);

        $rootOnly = ApplicationSeederConfiguration::fromSnapshot($this->snapshotFor($basePath, [
            'root' => 'App\\Database\\Seed\\RootSeeder',
        ]));
        self::assertSame('App\\Database\\Seed\\RootSeeder', $rootOnly->root);
        self::assertTrue($rootOnly->explicitRoot);
        self::assertSame([realpath($default)], $rootOnly->discoveryRoots);

        $discoveryOnly = ApplicationSeederConfiguration::fromSnapshot($this->snapshotFor($basePath, [
            'discovery' => [$custom, $custom],
        ]));
        self::assertSame(ApplicationSeederConfiguration::DEFAULT_ROOT, $discoveryOnly->root);
        self::assertFalse($discoveryOnly->explicitRoot);
        self::assertSame([realpath($custom)], $discoveryOnly->discoveryRoots);
    }

    public function testRejectsInvalidExplicitConfigurationWithoutExposingPath(): void
    {
        $basePath = $this->directory();
        $outside = $this->directory();

        foreach ([
            ['root' => ''],
            ['root' => '\\App\\RootSeeder'],
            ['discovery' => []],
            ['discovery' => ['relative/path']],
            ['discovery' => [$basePath . '/missing']],
            ['discovery' => [$outside]],
        ] as $seeding) {
            try {
                ApplicationSeederConfiguration::fromSnapshot($this->snapshotFor($basePath, $seeding));
                self::fail('Expected invalid seeding configuration.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringNotContainsString($basePath, $exception->getMessage());
                self::assertStringNotContainsString($outside, $exception->getMessage());
            }
        }
    }

    public function testRejectsSymlinkEscape(): void
    {
        $basePath = $this->directory();
        $outside = $this->directory();
        mkdir($basePath . '/app', recursive: true);
        symlink($outside, $basePath . '/app/SeedLink');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inside the application base path');

        try {
            ApplicationSeederConfiguration::fromSnapshot($this->snapshotFor($basePath, [
                'discovery' => [$basePath . '/app/SeedLink'],
            ]));
        } finally {
            unlink($basePath . '/app/SeedLink');
        }
    }

    /** @param array<array-key, mixed>|null $seeding */
    private function snapshotFor(string $basePath, ?array $seeding = null): ApplicationConfigurationSnapshot
    {
        $configuration = $seeding === null ? [] : ['database' => ['seeding' => $seeding]];

        return new ApplicationConfigurationSnapshot($basePath, $configuration, [], [], []);
    }
}

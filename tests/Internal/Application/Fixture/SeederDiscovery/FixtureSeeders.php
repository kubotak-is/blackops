<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application\Fixture\SeederDiscovery;

use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;

final class SeederFixtureState
{
    public static int $rootConstructions = 0;

    public static int $childConstructions = 0;

    /** @var list<string> */
    public static array $events = [];

    public static function reset(): void
    {
        self::$rootConstructions = 0;
        self::$childConstructions = 0;
        self::$events = [];
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class SeederFixtureDependency
{
    public function value(): string
    {
        return 'dependency-ready';
    }
}

/** @mago-expect lint:single-class-per-file */
final class FixtureDatabaseSeeder implements Seeder
{
    public function __construct(
        private readonly SeederRunner $seeders,
    ) {
        ++SeederFixtureState::$rootConstructions;
    }

    public function run(): void
    {
        SeederFixtureState::$events[] = 'root:start';
        $this->seeders->run(FixtureFirstSeeder::class, FixtureSecondSeeder::class);
        SeederFixtureState::$events[] = 'root:end';
    }
}

/** @mago-expect lint:single-class-per-file */
final class FixtureFirstSeeder implements Seeder
{
    public function __construct(
        private readonly SeederFixtureDependency $dependency,
    ) {
        ++SeederFixtureState::$childConstructions;
    }

    public function run(): void
    {
        SeederFixtureState::$events[] = 'first:' . $this->dependency->value();
    }
}

/** @mago-expect lint:single-class-per-file */
final class FixtureSecondSeeder implements Seeder
{
    public function __construct()
    {
        ++SeederFixtureState::$childConstructions;
    }

    public function run(): void
    {
        SeederFixtureState::$events[] = 'second';
    }
}

/** @mago-expect lint:single-class-per-file */
abstract class AbstractFixtureSeeder implements Seeder {}

/** @mago-expect lint:single-class-per-file */
final readonly class NotASeeder {}

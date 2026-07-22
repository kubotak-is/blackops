<?php

declare(strict_types=1);

namespace App\Tests\Seed;

use App\Infrastructure\Seed\CommunityBoardSeeder;
use App\Infrastructure\Seed\DatabaseSeeder;
use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;
use PHPUnit\Framework\TestCase;

final class DatabaseSeederTest extends TestCase
{
    public function testRootSeederDelegatesToApplicationSeedersInExplicitOrder(): void
    {
        $runner = new RecordingSeederRunner();
        $seeder = new DatabaseSeeder($runner);

        self::assertInstanceOf(Seeder::class, $seeder);

        $seeder->run();

        self::assertSame([CommunityBoardSeeder::class], $runner->seeders);
    }
}

/** @mago-expect lint:single-class-per-file */
final class RecordingSeederRunner implements SeederRunner
{
    /** @var list<class-string<Seeder>> */
    public array $seeders = [];

    public function run(string ...$seeders): void
    {
        array_push($this->seeders, ...$seeders);
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;

final readonly class DatabaseSeeder implements Seeder
{
    public function __construct(
        private SeederRunner $seeders,
    ) {}

    public function run(): void
    {
        $this->seeders->run(CommunityBoardSeeder::class);
    }
}

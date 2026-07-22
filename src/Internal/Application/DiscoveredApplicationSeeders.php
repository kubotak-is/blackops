<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Database\Seeder;

final readonly class DiscoveredApplicationSeeders
{
    /**
     * @param list<class-string<Seeder>> $seeders
     * @param class-string<Seeder>|null $root
     */
    public function __construct(
        public array $seeders,
        public ?string $root,
    ) {}
}

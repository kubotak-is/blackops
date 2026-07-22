<?php

declare(strict_types=1);

namespace BlackOps\Internal\Seeder;

use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;

final readonly class CompiledSeederRuntime
{
    /** @param class-string<Seeder>|null $root */
    public function __construct(
        private SeederRunner $seeders,
        private ?string $root,
    ) {}

    public function configured(): bool
    {
        return $this->root !== null;
    }

    public function run(): void
    {
        if ($this->root === null) {
            throw new SeederRuntimeException('Database seeding is not configured.');
        }

        $this->seeders->run($this->root);
    }
}

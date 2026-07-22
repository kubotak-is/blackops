<?php

declare(strict_types=1);

namespace BlackOps\Database;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface SeederRunner
{
    /** @param class-string<Seeder> ...$seeders */
    public function run(string ...$seeders): void;
}

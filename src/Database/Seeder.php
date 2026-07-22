<?php

declare(strict_types=1);

namespace BlackOps\Database;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface Seeder
{
    public function run(): void;
}

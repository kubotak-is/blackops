<?php

declare(strict_types=1);

namespace BlackOps\Identifier;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface Uuidv7Generator
{
    public function generate(): string;
}

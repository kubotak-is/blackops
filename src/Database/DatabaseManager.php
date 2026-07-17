<?php

declare(strict_types=1);

namespace BlackOps\Database;

use BlackOps\Core\Attribute\PublicApi;
use Doctrine\DBAL\Connection;

#[PublicApi]
interface DatabaseManager
{
    public function connection(?string $name = null): Connection;
}

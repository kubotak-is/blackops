<?php

declare(strict_types=1);

namespace BlackOps\Database\Attribute;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
#[PublicApi]
final readonly class Transactional
{
    public ?string $connection;

    public function __construct(?string $connection = null)
    {
        if ($connection === null) {
            $this->connection = null;

            return;
        }

        $connection = trim($connection);

        if ($connection === '') {
            throw new InvalidArgumentException('Transactional connection name must not be empty.');
        }

        $this->connection = $connection;
    }
}

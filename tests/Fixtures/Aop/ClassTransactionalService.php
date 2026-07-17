<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop;

use BlackOps\Database\Attribute\Transactional;

#[Transactional(connection: 'app')]
class ClassTransactionalService
{
    public function inheritedDefault(string $value): string
    {
        return $value;
    }

    #[Transactional(connection: 'analytics')]
    public function namedOverride(string $value): string
    {
        return $value;
    }
}

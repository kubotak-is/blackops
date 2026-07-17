<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop;

use BlackOps\Database\Attribute\Transactional;

readonly class ReadonlyTransactionalService
{
    #[Transactional]
    public function execute(string $value): string
    {
        return $value;
    }
}

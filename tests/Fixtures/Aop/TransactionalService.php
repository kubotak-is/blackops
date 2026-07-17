<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop;

use BlackOps\Database\Attribute\Transactional;
use RuntimeException;

class TransactionalService
{
    public int $calls = 0;

    #[Transactional]
    public function execute(string $value): string
    {
        $this->calls++;

        if ($value === 'throw') {
            throw new RuntimeException('expected failure');
        }

        return $value;
    }
}

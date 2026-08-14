<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility;

use BlackOps\Database\Attribute\Transactional;

#[Transactional(connection: 'app')]
readonly class ReadonlySignatureService
{
    public function value(string $value = 'readonly'): string
    {
        return $value;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility;

use BlackOps\Database\Attribute\Transactional;

class InheritedSignatureParent
{
    #[Transactional]
    public function inherited(string $value = 'inherited'): string
    {
        return $value;
    }
}

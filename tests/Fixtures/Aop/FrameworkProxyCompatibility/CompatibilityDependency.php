<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility;

final readonly class CompatibilityDependency
{
    public function __construct(
        public string $value = 'dependency',
    ) {}
}

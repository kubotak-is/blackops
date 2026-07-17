<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

final readonly class RuntimeAopCompilation
{
    /** @param list<string> $proxyFiles */
    public function __construct(
        public array $proxyFiles,
    ) {}
}

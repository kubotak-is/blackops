<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyDefinition;

use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerationResult;

final readonly class FrameworkProxyDefinitionCompilation
{
    /**
     * @param array<string,FrameworkProxyDefinitionBinding> $bindings
     */
    public function __construct(
        public array $bindings,
        public ?FrameworkProxyGenerationResult $generation = null,
    ) {}

    public function binding(string $serviceId): ?FrameworkProxyDefinitionBinding
    {
        return $this->bindings[$serviceId] ?? null;
    }
}

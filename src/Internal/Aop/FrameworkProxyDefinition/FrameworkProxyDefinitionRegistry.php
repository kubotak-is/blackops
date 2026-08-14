<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyDefinition;

use Symfony\Component\DependencyInjection\Definition;
use WeakMap;

final class FrameworkProxyDefinitionRegistry
{
    /** @var WeakMap<Definition,FrameworkProxyDefinitionBinding> */
    private WeakMap $bindings;

    public function __construct()
    {
        $this->bindings = new WeakMap();
    }

    public function set(Definition $definition, FrameworkProxyDefinitionBinding $binding): void
    {
        $this->bindings[$definition] = $binding;
    }

    public function get(Definition $definition): ?FrameworkProxyDefinitionBinding
    {
        return $this->bindings[$definition] ?? null;
    }
}

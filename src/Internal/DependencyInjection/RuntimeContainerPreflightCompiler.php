<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class RuntimeContainerPreflightCompiler
{
    public function compile(ContainerBuilder $builder): void
    {
        $preflight = new ContainerBuilder();
        $definitions = [];
        foreach ($builder->getDefinitions() as $id => $definition) {
            $definitions[$id] = clone $definition;
        }
        $preflight->setDefinitions($definitions);
        foreach ($builder->getAliases() as $id => $alias) {
            $preflight->setAlias($id, clone $alias);
        }
        $preflight->getParameterBag()->add($builder->getParameterBag()->all());
        $preflight->compile();
    }
}

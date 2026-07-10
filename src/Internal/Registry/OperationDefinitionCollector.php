<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Operation;
use BlackOps\Core\Registry\OperationProvider;

final readonly class OperationDefinitionCollector
{
    /**
     * @param iterable<OperationProvider> $providers
     * @param iterable<class-string<Operation>> $discovered
     *
     * @return list<class-string<Operation>>
     */
    public function collect(iterable $providers, iterable $discovered = []): array
    {
        $definitions = [];
        $provided = [];

        foreach ($providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                $definitions[] = $definition;
                $provided[$definition] = true;
            }
        }

        $added = [];

        foreach ($discovered as $definition) {
            if (array_key_exists($definition, $provided) || array_key_exists($definition, $added)) {
                continue;
            }

            $definitions[] = $definition;
            $added[$definition] = true;
        }

        return $definitions;
    }
}

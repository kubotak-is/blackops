<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Core\Registry\OperationRegistry;

final readonly class OperationProviderCompiler
{
    public function __construct(
        private OperationMetadataCompiler $metadata = new OperationMetadataCompiler(),
        private OperationDefinitionCollector $definitions = new OperationDefinitionCollector(),
    ) {}

    /**
     * @param iterable<OperationProvider> $providers
     * @param iterable<class-string<\BlackOps\Core\Operation>> $discovered
     */
    public function compile(iterable $providers, iterable $discovered = []): OperationRegistry
    {
        $metadata = [];

        foreach ($this->definitions->collect($providers, $discovered) as $definition) {
            $metadata[] = $this->metadata->compile($definition);
        }

        return new OperationRegistry($metadata);
    }

    /**
     * @param iterable<class-string<\BlackOps\Core\Operation>> $definitions
     */
    public function compileDefinitions(iterable $definitions): OperationRegistry
    {
        return $this->compile([], $definitions);
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Core\Registry\OperationRegistry;

final readonly class OperationProviderCompiler
{
    public function __construct(
        private OperationMetadataCompiler $metadata = new OperationMetadataCompiler(),
    ) {}

    /**
     * @param iterable<OperationProvider> $providers
     */
    public function compile(iterable $providers): OperationRegistry
    {
        $metadata = [];

        foreach ($providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                $metadata[] = $this->metadata->compile($definition);
            }
        }

        return new OperationRegistry($metadata);
    }
}

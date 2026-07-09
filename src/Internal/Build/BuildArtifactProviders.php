<?php

declare(strict_types=1);

namespace BlackOps\Internal\Build;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\Registry\OperationProvider;

final readonly class BuildArtifactProviders
{
    /**
     * @param list<OperationProvider> $operationProviders
     * @param list<ServiceProvider> $serviceProviders
     */
    public function __construct(
        public array $operationProviders,
        public array $serviceProviders,
    ) {}
}

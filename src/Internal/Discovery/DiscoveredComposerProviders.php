<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\Registry\OperationProvider;

final readonly class DiscoveredComposerProviders
{
    /**
     * @param list<class-string<OperationProvider>> $operationProviders
     * @param list<class-string<ServiceProvider>> $serviceProviders
     */
    public function __construct(
        public array $operationProviders,
        public array $serviceProviders,
    ) {}
}

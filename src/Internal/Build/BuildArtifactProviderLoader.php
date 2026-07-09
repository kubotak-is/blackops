<?php

declare(strict_types=1);

namespace BlackOps\Internal\Build;

use BlackOps\Internal\DependencyInjection\ServiceProviderConfigLoader;
use BlackOps\Internal\Discovery\ComposerProviderDiscovery;
use BlackOps\Internal\Registry\OperationProviderConfigLoader;

final readonly class BuildArtifactProviderLoader
{
    public function __construct(
        private OperationProviderConfigLoader $operationProviders = new OperationProviderConfigLoader(),
        private ServiceProviderConfigLoader $serviceProviders = new ServiceProviderConfigLoader(),
        private ComposerProviderDiscovery $composerProviders = new ComposerProviderDiscovery(),
    ) {}

    public function load(
        string $operationProviderConfig,
        string $serviceProviderConfig,
        ?string $composerMetadata,
    ): BuildArtifactProviders {
        $operationProviders = $this->operationProviders->load($operationProviderConfig);
        $serviceProviders = $this->serviceProviders->load($serviceProviderConfig);

        if ($composerMetadata === null) {
            return new BuildArtifactProviders($operationProviders, $serviceProviders);
        }

        $discovered = $this->composerProviders->discover($composerMetadata);

        return new BuildArtifactProviders([
            ...$operationProviders,
            ...$this->operationProviders->fromEntries($discovered->operationProviders),
        ], [
            ...$serviceProviders,
            ...$this->serviceProviders->fromEntries($discovered->serviceProviders),
        ]);
    }
}

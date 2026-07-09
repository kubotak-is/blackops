<?php

declare(strict_types=1);

namespace BlackOps\Internal\Build;

use BlackOps\Internal\DependencyInjection\ServiceProviderConfigLoader;
use BlackOps\Internal\Discovery\ComposerProviderDiscovery;
use BlackOps\Internal\Discovery\DiscoveredComposerProviders;
use BlackOps\Internal\Discovery\InstalledComposerProviderDiscovery;
use BlackOps\Internal\Registry\OperationProviderConfigLoader;

final readonly class BuildArtifactProviderLoader
{
    public function __construct(
        private OperationProviderConfigLoader $operationProviders = new OperationProviderConfigLoader(),
        private ServiceProviderConfigLoader $serviceProviders = new ServiceProviderConfigLoader(),
        private ComposerProviderDiscovery $composerProviders = new ComposerProviderDiscovery(),
        private InstalledComposerProviderDiscovery $installedComposerProviders = new InstalledComposerProviderDiscovery(),
    ) {}

    public function load(
        string $operationProviderConfig,
        string $serviceProviderConfig,
        ?string $composerMetadata,
        ?string $installedComposerMetadata,
    ): BuildArtifactProviders {
        $operationProviders = $this->operationProviders->load($operationProviderConfig);
        $serviceProviders = $this->serviceProviders->load($serviceProviderConfig);

        foreach ($this->discoveries($composerMetadata, $installedComposerMetadata) as $discovered) {
            $operationProviders = [
                ...$operationProviders,
                ...$this->operationProviders->fromEntries($discovered->operationProviders),
            ];
            $serviceProviders = [
                ...$serviceProviders,
                ...$this->serviceProviders->fromEntries($discovered->serviceProviders),
            ];
        }

        return new BuildArtifactProviders($operationProviders, $serviceProviders);
    }

    /**
     * @return list<DiscoveredComposerProviders>
     */
    private function discoveries(?string $composerMetadata, ?string $installedComposerMetadata): array
    {
        $discoveries = [];

        if ($composerMetadata !== null) {
            $discoveries[] = $this->composerProviders->discover($composerMetadata);
        }

        if ($installedComposerMetadata !== null) {
            $discoveries[] = $this->installedComposerProviders->discover($installedComposerMetadata);
        }

        return $discoveries;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Discovery\SeederSourceDiscovery;
use InvalidArgumentException;

final readonly class ApplicationSeederDiscovery
{
    public function __construct(
        private SeederSourceDiscovery $discovery = new SeederSourceDiscovery(),
    ) {}

    public function discover(ApplicationConfigurationSnapshot $application): DiscoveredApplicationSeeders
    {
        $configuration = ApplicationSeederConfiguration::fromSnapshot($application);
        $seeders = $configuration->discoveryRoots === []
            ? []
            : $this->discovery->discover($configuration->discoveryRoots);

        if (in_array($configuration->root, $seeders, strict: true)) {
            $root = $configuration->root;

            return new DiscoveredApplicationSeeders($seeders, $root);
        }

        if ($configuration->explicitRoot) {
            throw new InvalidArgumentException('Configured root seeder was not discovered.');
        }

        return new DiscoveredApplicationSeeders($seeders, null);
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\ApplicationCommandCollisionValidator;
use BlackOps\Internal\Console\ApplicationCommandManifestFile;
use BlackOps\Internal\Console\ApplicationCommandMetadata;
use Throwable;

final readonly class ApplicationCommandRuntimeManifestLoader
{
    /**
     * @param list<ApplicationCommandMetadata> $explicit
     * @param list<string> $frameworkNames
     */
    public function load(
        ApplicationConfigurationSnapshot $configuration,
        array $explicit,
        array $frameworkNames,
    ): ?ApplicationCommandRuntimeManifest {
        try {
            $build = ApplicationBuildConfiguration::fromConfiguration($configuration->configuration());
            $buildId = ApplicationBuildId::fromConfiguration($configuration->configuration());
            $artifact = new ApplicationCommandManifestFile()->loadArtifact($build->commandManifest);
        } catch (Throwable) {
            return null;
        }

        if ($artifact->applicationBuildId !== $buildId) {
            return null;
        }

        return new ApplicationCommandRuntimeManifest($build, new ApplicationCommandCollisionValidator()->merge(
            $artifact->commands,
            $explicit,
            $frameworkNames,
        ));
    }
}

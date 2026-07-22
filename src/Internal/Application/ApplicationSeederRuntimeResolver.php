<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\DatabaseSeedRuntimeException;
use BlackOps\Internal\Database\RuntimeDatabaseServiceInjector;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Logging\MonologJsonlLoggerFactory;
use BlackOps\Internal\Logging\RuntimeLoggingServiceInjector;
use BlackOps\Internal\Runtime\RuntimeContainerArtifactLoader;
use BlackOps\Internal\Seeder\CompiledSeederRuntime;
use BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;

final readonly class ApplicationSeederRuntimeResolver
{
    public function resolve(ApplicationConfigurationSnapshot $configuration): CompiledSeederRuntime
    {
        $values = $configuration->configuration();

        try {
            $build = ApplicationBuildConfiguration::fromConfiguration($values);
            $buildId = ApplicationBuildId::fromConfiguration($values);
            $container = new RuntimeContainerArtifactLoader()->load(
                $build->container,
                $build->containerClass,
                $build->containerNamespace,
            );
            if (
                !$container instanceof ContainerInterface
                || !$container->hasParameter('blackops.application_build_id')
                || $container->getParameter('blackops.application_build_id') !== $buildId
            ) {
                throw DatabaseSeedRuntimeException::artifact();
            }
        } catch (DatabaseSeedRuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw DatabaseSeedRuntimeException::artifact();
        }

        try {
            $scope = new ExecutionScopeProvider();
            $logging = ApplicationLoggingConfiguration::fromConfiguration($values);
            new RuntimeLoggingServiceInjector()->inject(
                $container,
                $scope,
                new MonologJsonlLoggerFactory()->create($logging->stream, $logging->channel, $logging->minimumLevel),
            );

            if (array_key_exists('database', $values)) {
                $database = ApplicationDatabaseConfiguration::fromConfiguration($values);
                $databases = $database->databaseManager();
                new RuntimeDatabaseServiceInjector()->inject($container, $databases);
                new RuntimeTransactionServiceInjector()->inject($container, $databases, $scope);
            }

            $runtime = $container->get(CompiledSeederRuntime::class);
        } catch (Throwable) {
            throw DatabaseSeedRuntimeException::resolution();
        }

        if (!$runtime instanceof CompiledSeederRuntime) {
            throw DatabaseSeedRuntimeException::resolution();
        }

        return $runtime;
    }
}

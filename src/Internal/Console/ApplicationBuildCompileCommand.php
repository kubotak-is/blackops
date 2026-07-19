<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Http\Routing\HttpOperationManifestArtifact;
use BlackOps\Http\Routing\HttpOperationManifestArtifactCodec;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Internal\Aop\RuntimeAopCompiler;
use BlackOps\Internal\Application\ApplicationBuildConfiguration;
use BlackOps\Internal\Application\ApplicationBuildId;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationDatabaseConfiguration;
use BlackOps\Internal\Application\ApplicationHttpMiddlewareConfiguration;
use BlackOps\Internal\Application\ApplicationOperationDiscovery;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\DependencyInjection\ServiceProviderConfigLoader;
use BlackOps\Internal\Frontend\FrontendContractCompiler;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Registry\OperationDefinitionFactory;
use BlackOps\Internal\Registry\OperationManifestArtifact;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Registry\OperationProviderCompiler;
use BlackOps\Internal\Registry\OperationProviderConfigLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ApplicationBuildCompileCommand extends Command
{
    public const NAME = 'build:compile';

    public function __construct(
        private readonly ApplicationConfigurationSnapshot $configuration,
    ) {
        parent::__construct(self::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $build = ApplicationBuildConfiguration::fromConfiguration($this->configuration->configuration());
        $buildId = ApplicationBuildId::fromConfiguration($this->configuration->configuration());
        $operations = new OperationProviderConfigLoader()->fromEntries($this->configuration->operationProviders());
        $services = new ServiceProviderConfigLoader()->fromEntries($this->configuration->serviceProviders());
        $discovered = new ApplicationOperationDiscovery()->discover($this->configuration);
        $applicationConfiguration = $this->configuration->configuration();
        $database = is_array($applicationConfiguration['database'] ?? null)
            ? ApplicationDatabaseConfiguration::fromConfiguration($applicationConfiguration)
            : null;
        $registry = new OperationProviderCompiler(new \BlackOps\Internal\Registry\OperationMetadataCompiler(
            defaultTransactionConnection: $database?->default,
            knownTransactionConnections: $database === null ? [] : array_keys($database->connections),
        ))->compile($operations, $discovered);
        $definitions = new OperationDefinitionFactory()->classNamesFromProviders($operations, $discovered);
        $middleware = ApplicationHttpMiddlewareConfiguration::fromConfiguration($this->configuration->configuration());
        $http = new HttpRouteCompiler($registry)->compileManifest($definitions);
        $frontend = new FrontendContractCompiler()->compile(
            new OperationManifestArtifact(OperationManifestFile::SCHEMA_VERSION, $buildId, $registry),
            new HttpOperationManifestArtifact(HttpOperationManifestArtifactCodec::SCHEMA_VERSION, $buildId, $http),
        );

        new OperationManifestFile()->write($registry, $build->operationManifest, $buildId);
        new HttpOperationManifestFile()->write($http, $build->httpManifest, $buildId);
        new FrontendContractManifestFile()->write($frontend, $build->frontendManifest, $buildId);

        $compiler = new RuntimeContainerCompiler();
        $container = $compiler->builder();
        $compiler->apply($container, $services);
        $compiler->registerDatabaseServices($container);
        $compiler->registerHandlers($container, $registry);
        $compiler->registerAuthorizationPolicies($container, $registry);
        $compiler->registerHttpMiddleware($container, $middleware->http);
        $aop = new RuntimeAopCompiler();

        try {
            $aopCompilation = $aop->compile(
                $container,
                $build->container,
                $database?->default,
                $database === null ? [] : array_keys($database->connections),
            );
            $compiler->compile($container);
            new RuntimeContainerDumper()->dump(
                $container,
                $build->container,
                $build->containerClass,
                $build->containerNamespace,
                $aopCompilation->proxyFiles,
            );
        } catch (\Throwable $throwable) {
            $aop->discard($build->container);

            throw $throwable;
        }

        $output->writeln('Build artifacts written.');

        return Command::SUCCESS;
    }
}

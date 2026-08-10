<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Http\Routing\HttpOperationManifestArtifact;
use BlackOps\Http\Routing\HttpOperationManifestArtifactCodec;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyMetadata;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyMethodMetadata;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnership;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnershipMarker;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxySignatureClassification;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionBinding;
use BlackOps\Internal\Aop\FrameworkProxyRuntime\FrameworkProxyRuntimeInvocation;
use BlackOps\Internal\Aop\FrameworkProxyRuntime\FrameworkProxyRuntimeInvocationFactory;
use BlackOps\Internal\Aop\ProxyProfileArtifact\ProxyProfileArtifactPublisher;
use BlackOps\Internal\Aop\RuntimeAopCompiler;
use BlackOps\Internal\Application\ApplicationBuildConfiguration;
use BlackOps\Internal\Application\ApplicationBuildId;
use BlackOps\Internal\Application\ApplicationCommandDiscovery;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationDatabaseConfiguration;
use BlackOps\Internal\Application\ApplicationHttpMiddlewareConfiguration;
use BlackOps\Internal\Application\ApplicationOperationDiscovery;
use BlackOps\Internal\Application\ApplicationSeederDiscovery;
use BlackOps\Internal\Application\ExplicitApplicationCommands;
use BlackOps\Internal\DependencyInjection\FrameworkProxyDefinitionCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\DependencyInjection\RuntimeContainerPreflightCompiler;
use BlackOps\Internal\DependencyInjection\ServiceProviderConfigLoader;
use BlackOps\Internal\Frontend\FrontendContractCompiler;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Registry\OperationDefinitionFactory;
use BlackOps\Internal\Registry\OperationManifestArtifact;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Registry\OperationProviderCompiler;
use BlackOps\Internal\Registry\OperationProviderConfigLoader;
use BlackOps\Scheduling\ScheduledActorProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ApplicationBuildCompileCommand extends Command
{
    public const NAME = 'build:compile';

    public function __construct(
        private readonly ApplicationConfigurationSnapshot $configuration,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        FrameworkProxyProfileOption::configure($this);
    }

    /** @mago-expect lint:halstead */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $build = ApplicationBuildConfiguration::fromConfiguration($this->configuration->configuration());
        $buildId = ApplicationBuildId::fromConfiguration($this->configuration->configuration());
        $operations = new OperationProviderConfigLoader()->fromEntries($this->configuration->operationProviders());
        $services = new ServiceProviderConfigLoader()->fromEntries($this->configuration->serviceProviders());
        $discovered = new ApplicationOperationDiscovery()->discover($this->configuration);
        $seeders = new ApplicationSeederDiscovery()->discover($this->configuration);
        $explicitCommands = ExplicitApplicationCommands::from($this->configuration->commands())->metadata();
        $discoveredCommands = new ApplicationCommandCollisionValidator()->merge(
            new ApplicationCommandDiscovery()->discover($this->configuration),
            $explicitCommands,
            FrameworkCommandNames::all(),
        );
        $applicationConfiguration = $this->configuration->configuration();
        $database = is_array($applicationConfiguration['database'] ?? null)
            ? ApplicationDatabaseConfiguration::fromConfiguration($applicationConfiguration)
            : null;
        $registry = new OperationProviderCompiler(new \BlackOps\Internal\Registry\OperationMetadataCompiler(
            defaultTransactionConnection: $database?->default,
            knownTransactionConnections: $database === null ? [] : array_keys($database->connections),
        ))->compile($operations, $discovered);
        $operationCommands = new OperationConsoleMetadataCompiler()->compile($registry);
        new ApplicationCommandCollisionValidator()->validateOperationCommands(
            [...$explicitCommands, ...$discoveredCommands],
            $operationCommands,
            FrameworkCommandNames::all(),
        );
        $definitions = new OperationDefinitionFactory()->classNamesFromProviders($operations, $discovered);
        $middleware = ApplicationHttpMiddlewareConfiguration::fromConfiguration($this->configuration->configuration());
        $http = new HttpRouteCompiler($registry)->compileManifest($definitions);
        $frontend = new FrontendContractCompiler()->compile(
            new OperationManifestArtifact(OperationManifestFile::SCHEMA_VERSION, $buildId, $registry),
            new HttpOperationManifestArtifact(HttpOperationManifestArtifactCodec::SCHEMA_VERSION, $buildId, $http),
        );

        $compiler = new RuntimeContainerCompiler();
        $container = $compiler->builder();
        $container->setParameter('blackops.application_build_id', $buildId);
        $compiler->apply($container, $services);
        if (
            array_any(
                $registry->all(),
                static fn(\BlackOps\Core\Registry\OperationMetadata $metadata): bool => (
                    $metadata->schedule !== null
                    && $metadata->authorizationPolicy !== null
                ),
            )
            && !$container->has(ScheduledActorProvider::class)
        ) {
            throw new \InvalidArgumentException('Authorized scheduled operations require a scheduled actor provider.');
        }
        $compiler->registerUuidv7Generator($container);
        $compiler->registerStorageProtection($container);
        $compiler->registerDatabaseServices($container);
        $compiler->registerHandlers($container, $registry);
        $compiler->registerAuthorizationPolicies($container, $registry);
        $compiler->registerHttpMiddleware($container, $middleware->http);
        $compiler->registerApplicationCommands($container, array_map(
            /** @return class-string<Command> */
            static fn(ApplicationCommandMetadata $command): string => $command->class,
            $discoveredCommands,
        ));
        $compiler->registerSeeders($container, $seeders->seeders, $seeders->root);
        if ($seeders->seeders !== []) {
            new RuntimeContainerPreflightCompiler()->compile($container);
        }
        $profile = FrameworkProxyProfileOption::fromInput($input);
        $container->setParameter('blackops.proxy_profile', $profile->value);
        $aop = $profile->equals(FrameworkProxyProfile::RAY) ? new RuntimeAopCompiler() : null;
        $frameworkCompilation = null;
        $aopCompilation = null;

        try {
            if ($aop !== null) {
                $aopCompilation = $aop->compile(
                    $container,
                    $build->container,
                    $database?->default,
                    $database === null ? [] : array_keys($database->connections),
                );
            } else {
                $frameworkCompilation = new FrameworkProxyDefinitionCompiler()->compile(
                    $container,
                    $buildId,
                    dirname($build->container) . DIRECTORY_SEPARATOR . 'framework-proxies',
                    FrameworkProxyProfile::FRAMEWORK,
                    $database?->default,
                    $database === null ? [] : array_keys($database->connections),
                );
                $this->wireFrameworkRuntime($container, $frameworkCompilation);
            }
            $compiler->compile($container);
            $profileArtifactRoot = dirname($build->container) . DIRECTORY_SEPARATOR . 'proxy-profiles';
            $profileArtifact = $profile->equals(FrameworkProxyProfile::RAY)
                ? new ProxyProfileArtifactPublisher()->publishRay(
                    $profileArtifactRoot,
                    $buildId,
                    $aopCompilation?->proxyFiles ?? [],
                )
                : new ProxyProfileArtifactPublisher()->publishFramework(
                    $profileArtifactRoot,
                    $buildId,
                    $frameworkCompilation?->generation?->directory,
                    $frameworkCompilation?->generation?->manifest,
                );
            new RuntimeContainerDumper()->dump(
                $container,
                $build->container,
                $build->containerClass,
                $build->containerNamespace,
                [],
                $profile,
                null,
                null,
                $profileArtifact,
                $profileArtifactRoot . DIRECTORY_SEPARATOR . $buildId . '-' . $profileArtifact->contentHash,
            );
        } catch (\Throwable $throwable) {
            $aop?->discard($build->container);

            throw $throwable;
        }

        new OperationManifestFile()->write($registry, $build->operationManifest, $buildId);
        new HttpOperationManifestFile()->write($http, $build->httpManifest, $buildId);
        new FrontendContractManifestFile()->write($frontend, $build->frontendManifest, $buildId);
        new ApplicationCommandManifestFile()->write(
            $discoveredCommands,
            $operationCommands,
            $build->commandManifest,
            $buildId,
        );

        $output->writeln('Build artifacts written.');

        return Command::SUCCESS;
    }

    private function wireFrameworkRuntime(
        \Symfony\Component\DependencyInjection\ContainerBuilder $container,
        \BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionCompilation $compilation,
    ): void {
        if ($compilation->bindings === []) {
            return;
        }
        $factoryId = FrameworkProxyRuntimeInvocationFactory::class;
        $initializerId = \BlackOps\Internal\Aop\FrameworkProxyRuntime\FrameworkProxyRuntimeInitializer::class;
        if (!$container->has($factoryId)) {
            $container->register($factoryId, $factoryId)->setAutowired(true)->setPublic(false);
        }
        if (!$container->has($initializerId)) {
            $container
                ->register($initializerId, $initializerId)
                ->setArgument(0, new Reference($factoryId))
                ->setPublic(false);
        }
        foreach ($compilation->bindings as $serviceId => $binding) {
            $suffix = hash('sha256', $serviceId);
            $metadataId = 'blackops.framework.proxy.metadata.' . $suffix;
            $markerId = 'blackops.framework.proxy.marker.' . $suffix;
            $bindingId = 'blackops.framework.proxy.binding.' . $suffix;
            $invocationId = 'blackops.framework.proxy.invocation.' . $suffix;
            $profileId = 'blackops.framework.proxy.profile.' . $suffix;
            $ownershipId = 'blackops.framework.proxy.ownership.' . $suffix;
            $container->setDefinition(
                $profileId,
                new Definition(FrameworkProxyProfile::class)->setFactory([
                    FrameworkProxyProfile::class,
                    'from',
                ])->setArguments([FrameworkProxyProfile::FRAMEWORK]),
            );
            $container->setDefinition($ownershipId, new Definition(FrameworkProxyOwnership::class)->setFactory([
                FrameworkProxyOwnership::class,
                'from',
            ])->setArguments([$binding->metadata->ownership->value]));
            $methods = [];
            foreach ($binding->metadata->methods as $index => $method) {
                $methodId = 'blackops.framework.proxy.method.' . $suffix . '.' . $index;
                $container->setDefinition($methodId, new Definition(FrameworkProxyMethodMetadata::class, [
                    $method->name,
                    $method->declaringClass,
                    $method->transactionalConnection,
                    $method->transactional,
                    $method->afterCommit,
                    new Definition(FrameworkProxySignatureClassification::class)->setFactory([
                        FrameworkProxySignatureClassification::class,
                        'from',
                    ])->setArguments([$method->classification->value]),
                    $method->diagnosticCode,
                    $method->signature,
                    $method->parameters,
                    $method->returnType,
                    $method->unrelatedAttributes,
                ]));
                $methods[] = new Reference($methodId);
            }
            $container->setDefinition($metadataId, new Definition(FrameworkProxyMetadata::class, [
                $binding->metadata->sourceClass,
                new Reference($profileId),
                new Reference($ownershipId),
                $binding->metadata->classTransactional,
                $binding->metadata->classTransactionalConnection,
                $methods,
                $binding->metadata->proxyTarget,
                $binding->metadata->readonlyClass,
            ]));
            $container->setDefinition($markerId, new Definition(FrameworkProxyOwnershipMarker::class, [
                $binding->marker->sourceClass,
                new Reference($ownershipId),
                new Reference($profileId),
                $binding->marker->lifecycleOwned,
            ]));
            $container->setDefinition($bindingId, new Definition(FrameworkProxyDefinitionBinding::class, [
                $binding->serviceId,
                $binding->sourceClass,
                $binding->proxyClass,
                new Reference($metadataId),
                new Reference($markerId),
            ]));
            $container->setDefinition($invocationId, new Definition(FrameworkProxyRuntimeInvocation::class)->setFactory([
                new Reference($initializerId),
                'invocation',
            ])->setArguments([new Reference($bindingId)]));
            $container->getDefinition($serviceId)->addMethodCall('__blackopsInitialize', [new Reference(
                $invocationId,
            )]);
        }
    }
}

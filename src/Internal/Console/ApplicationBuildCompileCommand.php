<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Internal\Application\ApplicationBuildConfiguration;
use BlackOps\Internal\Application\ApplicationBuildId;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationOperationDiscovery;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\DependencyInjection\ServiceProviderConfigLoader;
use BlackOps\Internal\Registry\OperationDefinitionFactory;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Registry\OperationProviderCompiler;
use BlackOps\Internal\Registry\OperationProviderConfigLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ApplicationBuildCompileCommand extends Command
{
    public const NAME = 'blackops:build:compile';

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
        $registry = new OperationProviderCompiler()->compile($operations, $discovered);
        $definitions = new OperationDefinitionFactory()->classNamesFromProviders($operations, $discovered);

        new OperationManifestFile()->write($registry, $build->operationManifest, $buildId);
        new HttpOperationManifestFile()->write(
            new HttpRouteCompiler($registry)->compileManifest($definitions),
            $build->httpManifest,
            $buildId,
        );

        $compiler = new RuntimeContainerCompiler();
        $container = $compiler->builder();
        $compiler->apply($container, $services);
        $compiler->registerHandlers($container, $registry);
        $compiler->compile($container);
        new RuntimeContainerDumper()->dump(
            $container,
            $build->container,
            $build->containerClass,
            $build->containerNamespace,
        );

        $output->writeln('Build artifacts written.');

        return Command::SUCCESS;
    }
}

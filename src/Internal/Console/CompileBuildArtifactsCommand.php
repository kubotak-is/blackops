<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Internal\Build\BuildLock;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\DependencyInjection\ServiceProviderConfigLoader;
use BlackOps\Internal\Registry\OperationDefinitionFactory;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Registry\OperationProviderCompiler;
use BlackOps\Internal\Registry\OperationProviderConfigLoader;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Compile operation, HTTP, and runtime container artifacts.')]
final class CompileBuildArtifactsCommand extends Command
{
    public const NAME = 'blackops:build:compile';

    public function __construct(
        private readonly OperationProviderConfigLoader $operationProviders = new OperationProviderConfigLoader(),
        private readonly OperationProviderCompiler $operationCompiler = new OperationProviderCompiler(),
        private readonly OperationDefinitionFactory $definitions = new OperationDefinitionFactory(),
        private readonly OperationManifestFile $operationManifests = new OperationManifestFile(),
        private readonly HttpOperationManifestFile $httpManifests = new HttpOperationManifestFile(),
        private readonly ServiceProviderConfigLoader $serviceProviders = new ServiceProviderConfigLoader(),
        private readonly RuntimeContainerCompiler $containerCompiler = new RuntimeContainerCompiler(),
        private readonly RuntimeContainerDumper $containerDumper = new RuntimeContainerDumper(),
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'operation-providers',
                InputArgument::REQUIRED,
                'Path to the PHP operation provider config file.',
            )
            ->addArgument('service-providers', InputArgument::REQUIRED, 'Path to the PHP service provider config file.')
            ->addArgument(
                'operation-manifest',
                InputArgument::REQUIRED,
                'Path to the generated PHP operation manifest file.',
            )
            ->addArgument('http-manifest', InputArgument::REQUIRED, 'Path to the generated PHP HTTP manifest file.')
            ->addArgument('container', InputArgument::REQUIRED, 'Path to the generated PHP container file.')
            ->addOption(
                'container-class',
                null,
                InputOption::VALUE_REQUIRED,
                'Generated container class name.',
                'CompiledContainer',
            )
            ->addOption('container-namespace', null, InputOption::VALUE_REQUIRED, 'Generated container namespace.', '')
            ->addOption('lock', null, InputOption::VALUE_REQUIRED, 'Path to the build lock file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->nullableStringOption($input, 'lock');

        if ($lock !== null) {
            new BuildLock()->run($lock, function () use ($input): void {
                $this->compile($input);
            });

            $output->writeln('Build artifacts written.');

            return Command::SUCCESS;
        }

        $this->compile($input);
        $output->writeln('Build artifacts written.');

        return Command::SUCCESS;
    }

    private function compile(InputInterface $input): void
    {
        $providers = $this->operationProviders->load($this->stringArgument($input, 'operation-providers'));
        $registry = $this->operationCompiler->compile($providers);
        $definitions = $this->definitions->fromProviders($providers);
        $this->operationManifests->write($registry, $this->stringArgument($input, 'operation-manifest'));
        $this->httpManifests->write(
            new HttpRouteCompiler($registry)->compileManifest($definitions),
            $this->stringArgument($input, 'http-manifest'),
        );

        $builder = $this->containerCompiler->builder();
        $this->containerCompiler->apply(
            $builder,
            $this->serviceProviders->load($this->stringArgument($input, 'service-providers')),
        );
        $this->containerCompiler->compile($builder);
        $this->containerDumper->dump(
            $builder,
            $this->stringArgument($input, 'container'),
            $this->stringOption($input, 'container-class'),
            $this->stringOption($input, 'container-namespace'),
        );
    }

    private function stringArgument(InputInterface $input, string $name): string
    {
        if (!is_string($input->getArgument($name)) || $input->getArgument($name) === '') {
            throw new InvalidArgumentException('Build command argument must be a non-empty string.');
        }

        return (string) $input->getArgument($name);
    }

    private function stringOption(InputInterface $input, string $name): string
    {
        if (!is_string($input->getOption($name))) {
            throw new InvalidArgumentException('Build command option must be a string.');
        }

        return (string) $input->getOption($name);
    }

    private function nullableStringOption(InputInterface $input, string $name): ?string
    {
        if ($input->getOption($name) === null) {
            return null;
        }

        if (!is_string($input->getOption($name)) || $input->getOption($name) === '') {
            throw new InvalidArgumentException('Build command option must be a non-empty string.');
        }

        return (string) $input->getOption($name);
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Http\Routing\HttpOperationManifestArtifact;
use BlackOps\Http\Routing\HttpOperationManifestArtifactCodec;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Internal\Build\BuildArtifactFingerprintGuard;
use BlackOps\Internal\Build\BuildArtifactProviderLoader;
use BlackOps\Internal\Build\BuildLock;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\Frontend\FrontendContractCompiler;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Registry\OperationDefinitionFactory;
use BlackOps\Internal\Registry\OperationManifestArtifact;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Registry\OperationProviderCompiler;
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
        private readonly BuildArtifactProviderLoader $providers = new BuildArtifactProviderLoader(),
        private readonly OperationProviderCompiler $operationCompiler = new OperationProviderCompiler(),
        private readonly OperationDefinitionFactory $definitions = new OperationDefinitionFactory(),
        private readonly OperationManifestFile $operationManifests = new OperationManifestFile(),
        private readonly HttpOperationManifestFile $httpManifests = new HttpOperationManifestFile(),
        private readonly FrontendContractManifestFile $frontendManifests = new FrontendContractManifestFile(),
        private readonly RuntimeContainerCompiler $containerCompiler = new RuntimeContainerCompiler(),
        private readonly RuntimeContainerDumper $containerDumper = new RuntimeContainerDumper(),
        private readonly BuildArtifactFreshnessChecker $freshness = new BuildArtifactFreshnessChecker(),
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
            ->addArgument(
                'frontend-manifest',
                InputArgument::REQUIRED,
                'Path to the generated PHP frontend contract manifest file.',
            )
            ->addArgument('container', InputArgument::REQUIRED, 'Path to the generated PHP container file.')
            ->addOption(
                'application-build-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Application build identifier stored in both manifests.',
            )
            ->addOption(
                'container-class',
                null,
                InputOption::VALUE_REQUIRED,
                'Generated container class name.',
                'CompiledContainer',
            )
            ->addOption('container-namespace', null, InputOption::VALUE_REQUIRED, 'Generated container namespace.', '')
            ->addOption('lock', null, InputOption::VALUE_REQUIRED, 'Path to the build lock file.')
            ->addOption('fingerprint', null, InputOption::VALUE_REQUIRED, 'Path to the build fingerprint file.')
            ->addOption(
                'fingerprint-input',
                null,
                InputOption::VALUE_REQUIRED,
                'Additional input file paths included in the build fingerprint, separated by PATH_SEPARATOR.',
            )
            ->addOption(
                'composer-metadata',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to a Composer JSON metadata file exposing BlackOps providers.',
            )
            ->addOption(
                'installed-composer-metadata',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to a Composer installed packages JSON metadata file exposing BlackOps providers.',
            );
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

    /** @mago-expect lint:halstead */
    private function compile(InputInterface $input): void
    {
        $applicationBuildId = $this->requiredStringOption($input, 'application-build-id');
        $fingerprint = $this->nullableStringOption($input, 'fingerprint');
        $fingerprintInputs = new BuildArtifactFingerprintInputs()->collect(
            $this->stringArgument($input, 'operation-providers'),
            $this->stringArgument($input, 'service-providers'),
            $this->nullableStringOption($input, 'composer-metadata'),
            $this->nullableStringOption($input, 'installed-composer-metadata'),
            $this->nullableStringOption($input, 'fingerprint-input'),
        );
        $operationManifest = $this->stringArgument($input, 'operation-manifest');
        $httpManifest = $this->stringArgument($input, 'http-manifest');
        $frontendManifest = $this->stringArgument($input, 'frontend-manifest');
        $container = $this->stringArgument($input, 'container');

        if ($this->freshness->isFresh(
            $fingerprint,
            $fingerprintInputs,
            [$operationManifest, $httpManifest, $frontendManifest, $container],
            ['operation' => $operationManifest, 'http' => $httpManifest, 'frontend' => $frontendManifest],
            $applicationBuildId,
        )) {
            return;
        }

        $providers = $this->providers->load(
            $this->stringArgument($input, 'operation-providers'),
            $this->stringArgument($input, 'service-providers'),
            $this->nullableStringOption($input, 'composer-metadata'),
            $this->nullableStringOption($input, 'installed-composer-metadata'),
        );
        $registry = $this->operationCompiler->compile($providers->operationProviders);
        $definitions = $this->definitions->classNamesFromProviders($providers->operationProviders);
        $http = new HttpRouteCompiler($registry)->compileManifest($definitions);
        $frontend = new FrontendContractCompiler()->compile(
            new OperationManifestArtifact(OperationManifestFile::SCHEMA_VERSION, $applicationBuildId, $registry),
            new HttpOperationManifestArtifact(
                HttpOperationManifestArtifactCodec::SCHEMA_VERSION,
                $applicationBuildId,
                $http,
            ),
        );
        $this->operationManifests->write($registry, $operationManifest, $applicationBuildId);
        $this->httpManifests->write($http, $httpManifest, $applicationBuildId);
        $this->frontendManifests->write($frontend, $frontendManifest, $applicationBuildId);

        $builder = $this->containerCompiler->builder();
        $this->containerCompiler->apply($builder, $providers->serviceProviders);
        $this->containerCompiler->registerHandlers($builder, $registry);
        $this->containerCompiler->registerAuthorizationPolicies($builder, $registry);
        $this->containerCompiler->compile($builder);
        $this->containerDumper->dump(
            $builder,
            $container,
            $this->stringOption($input, 'container-class'),
            $this->stringOption($input, 'container-namespace'),
        );

        if ($fingerprint !== null) {
            new BuildArtifactFingerprintGuard()->update($fingerprint, $fingerprintInputs);
        }
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

    private function requiredStringOption(InputInterface $input, string $name): string
    {
        $value = $this->stringOption($input, $name);

        if (trim($value) === '') {
            throw new InvalidArgumentException('Build command option must be a non-empty string.');
        }

        return $value;
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

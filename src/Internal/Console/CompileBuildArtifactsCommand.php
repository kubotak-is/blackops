<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Internal\Build\BuildArtifactFingerprintGuard;
use BlackOps\Internal\Build\BuildArtifactProviderLoader;
use BlackOps\Internal\Build\BuildLock;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\Registry\OperationDefinitionFactory;
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

    private function compile(InputInterface $input): void
    {
        $fingerprint = $this->nullableStringOption($input, 'fingerprint');

        if (
            $fingerprint !== null
            && new BuildArtifactFingerprintGuard()->isFresh(
                $fingerprint,
                $this->fingerprintInputs($input),
                $this->artifactOutputs($input),
            )
        ) {
            return;
        }

        $providers = $this->providers->load(
            $this->stringArgument($input, 'operation-providers'),
            $this->stringArgument($input, 'service-providers'),
            $this->nullableStringOption($input, 'composer-metadata'),
        );
        $registry = $this->operationCompiler->compile($providers->operationProviders);
        $definitions = $this->definitions->fromProviders($providers->operationProviders);
        $this->operationManifests->write($registry, $this->stringArgument($input, 'operation-manifest'));
        $this->httpManifests->write(
            new HttpRouteCompiler($registry)->compileManifest($definitions),
            $this->stringArgument($input, 'http-manifest'),
        );

        $builder = $this->containerCompiler->builder();
        $this->containerCompiler->apply($builder, $providers->serviceProviders);
        $this->containerCompiler->compile($builder);
        $this->containerDumper->dump(
            $builder,
            $this->stringArgument($input, 'container'),
            $this->stringOption($input, 'container-class'),
            $this->stringOption($input, 'container-namespace'),
        );

        if ($fingerprint !== null) {
            new BuildArtifactFingerprintGuard()->update($fingerprint, $this->fingerprintInputs($input));
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

    /**
     * @return list<string>
     */
    private function fingerprintInputs(InputInterface $input): array
    {
        $paths = [
            $this->stringArgument($input, 'operation-providers'),
            $this->stringArgument($input, 'service-providers'),
        ];
        $composerMetadata = $this->nullableStringOption($input, 'composer-metadata');

        if ($composerMetadata !== null) {
            $paths[] = $composerMetadata;
        }

        return [
            ...$paths,
            ...$this->extraFingerprintInputs($input),
        ];
    }

    /**
     * @return list<string>
     */
    private function artifactOutputs(InputInterface $input): array
    {
        return [
            $this->stringArgument($input, 'operation-manifest'),
            $this->stringArgument($input, 'http-manifest'),
            $this->stringArgument($input, 'container'),
        ];
    }

    /**
     * @return list<string>
     */
    private function extraFingerprintInputs(InputInterface $input): array
    {
        $extra = $this->nullableStringOption($input, 'fingerprint-input');

        if ($extra === null) {
            return [];
        }

        $paths = explode(PATH_SEPARATOR, $extra);

        foreach ($paths as $path) {
            if ($path === '') {
                throw new InvalidArgumentException(
                    'Build command fingerprint input option must contain non-empty paths.',
                );
            }
        }

        return $paths;
    }
}

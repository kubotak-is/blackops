<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Internal\Registry\OperationDefinitionFactory;
use BlackOps\Internal\Registry\OperationProviderCompiler;
use BlackOps\Internal\Registry\OperationProviderConfigLoader;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Compile and dump the HTTP route manifest to a PHP file.')]
final class CompileHttpManifestCommand extends Command
{
    public const NAME = 'blackops:http-manifest:compile';

    public function __construct(
        private readonly OperationProviderConfigLoader $providers = new OperationProviderConfigLoader(),
        private readonly OperationProviderCompiler $operations = new OperationProviderCompiler(),
        private readonly OperationDefinitionFactory $definitions = new OperationDefinitionFactory(),
        private readonly HttpOperationManifestFile $files = new HttpOperationManifestFile(),
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('config', InputArgument::REQUIRED, 'Path to the PHP operation provider config file.')
            ->addArgument('output', InputArgument::REQUIRED, 'Path to the generated PHP HTTP manifest file.')
            ->addOption(
                'application-build-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Application build identifier stored in the manifest.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $providers = $this->providers->load($this->stringArgument($input, 'config'));
        $registry = $this->operations->compile($providers);
        $definitions = $this->definitions->fromProviders($providers);
        $manifest = new HttpRouteCompiler($registry)->compileManifest($definitions);
        $this->files->write(
            $manifest,
            $this->stringArgument($input, 'output'),
            $this->stringOption($input, 'application-build-id'),
        );
        $output->writeln('HTTP manifest written.');

        return Command::SUCCESS;
    }

    private function stringArgument(InputInterface $input, string $name): string
    {
        if (!is_string($input->getArgument($name)) || $input->getArgument($name) === '') {
            throw new InvalidArgumentException('HTTP manifest command argument must be a non-empty string.');
        }

        return (string) $input->getArgument($name);
    }

    private function stringOption(InputInterface $input, string $name): string
    {
        if (!is_string($input->getOption($name))) {
            throw new InvalidArgumentException('HTTP manifest command option must be a non-empty string.');
        }

        $value = (string) $input->getOption($name);

        if (trim($value) === '') {
            throw new InvalidArgumentException('HTTP manifest command option must be a non-empty string.');
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

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

#[AsCommand(name: self::NAME, description: 'Compile and dump the operation manifest to a PHP file.')]
final class CompileOperationManifestCommand extends Command
{
    public const NAME = 'blackops:operation-manifest:compile';

    public function __construct(
        private readonly OperationProviderConfigLoader $providers = new OperationProviderConfigLoader(),
        private readonly OperationProviderCompiler $compiler = new OperationProviderCompiler(),
        private readonly OperationManifestFile $files = new OperationManifestFile(),
        private readonly DevelopmentDiscoveryInput $discovery = new DevelopmentDiscoveryInput(),
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('config', InputArgument::REQUIRED, 'Path to the PHP operation provider config file.')
            ->addArgument('output', InputArgument::REQUIRED, 'Path to the generated PHP operation manifest file.')
            ->addOption(
                'application-build-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Application build identifier stored in the manifest.',
            );
        $this->discovery->configure($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $registry = $this->compiler->compile(
            $this->providers->load($this->stringArgument($input, 'config')),
            $this->discovery->optionalDefinitions($input),
        );
        $this->files->write(
            $registry,
            $this->stringArgument($input, 'output'),
            $this->stringOption($input, 'application-build-id'),
        );
        $output->writeln('Operation manifest written.');

        return Command::SUCCESS;
    }

    private function stringArgument(InputInterface $input, string $name): string
    {
        if (!is_string($input->getArgument($name)) || $input->getArgument($name) === '') {
            throw new InvalidArgumentException('Operation manifest command argument must be a non-empty string.');
        }

        return (string) $input->getArgument($name);
    }

    private function stringOption(InputInterface $input, string $name): string
    {
        if (!is_string($input->getOption($name))) {
            throw new InvalidArgumentException('Operation manifest command option must be a non-empty string.');
        }

        $value = (string) $input->getOption($name);

        if (trim($value) === '') {
            throw new InvalidArgumentException('Operation manifest command option must be a non-empty string.');
        }

        return $value;
    }
}

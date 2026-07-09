<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\DependencyInjection\ServiceProviderConfigLoader;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Compile and dump the runtime container to a PHP file.')]
final class CompileRuntimeContainerCommand extends Command
{
    public const NAME = 'blackops:container:compile';

    public function __construct(
        private readonly ServiceProviderConfigLoader $providers = new ServiceProviderConfigLoader(),
        private readonly RuntimeContainerCompiler $compiler = new RuntimeContainerCompiler(),
        private readonly RuntimeContainerDumper $dumper = new RuntimeContainerDumper(),
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('config', InputArgument::REQUIRED, 'Path to the PHP service provider config file.')
            ->addArgument('output', InputArgument::REQUIRED, 'Path to the generated PHP container file.')
            ->addOption(
                'class',
                null,
                InputOption::VALUE_REQUIRED,
                'Generated container class name.',
                'CompiledContainer',
            )
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Generated container namespace.', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $builder = $this->compiler->builder();
        $this->compiler->apply($builder, $this->providers->load($this->stringArgument($input, 'config')));
        $this->compiler->compile($builder);
        $this->dumper->dump(
            $builder,
            $this->stringArgument($input, 'output'),
            $this->stringOption($input, 'class'),
            $this->stringOption($input, 'namespace'),
        );

        $output->writeln('Runtime container written.');

        return Command::SUCCESS;
    }

    private function stringArgument(InputInterface $input, string $name): string
    {
        if (!is_string($input->getArgument($name)) || $input->getArgument($name) === '') {
            throw new InvalidArgumentException('Runtime container command argument must be a non-empty string.');
        }

        return (string) $input->getArgument($name);
    }

    private function stringOption(InputInterface $input, string $name): string
    {
        if (!is_string($input->getOption($name))) {
            throw new InvalidArgumentException('Runtime container command option must be a string.');
        }

        return (string) $input->getOption($name);
    }
}

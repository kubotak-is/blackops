<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Generator\OperationGenerator;
use BlackOps\Internal\Generator\OperationGeneratorInput;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeOperationCommand extends Command
{
    public const NAME = 'make:operation';

    public function __construct(
        private readonly OperationGenerator $generator,
    ) {
        parent::__construct(self::NAME);
        $this->addArgument('operation', InputArgument::REQUIRED, 'Feature and action as Feature/Action.')->addOption(
            'type',
            null,
            InputOption::VALUE_REQUIRED,
            'Stable dot-separated operation type.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $path */
        $path = $input->getArgument('operation');
        /** @var string|null $type */
        $type = $input->getOption('type');

        if (!is_string($path) || !is_string($type) || $type === '') {
            throw new InvalidArgumentException('Operation path and --type are required.');
        }

        foreach ($this->generator->generate(OperationGeneratorInput::from($path, $type)) as $generated) {
            $output->writeln('Created: ' . $generated);
        }

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Generator\AuthGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeAuthCommand extends Command
{
    public const string NAME = 'make:auth';

    public function __construct(
        private readonly AuthGenerator $generator,
    ) {
        parent::__construct(self::NAME);
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Update framework-owned authentication files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->generator->generate($input->getOption('force') === true);
        if ($result->current) {
            $output->writeln('Authentication starter is already current.');

            return Command::SUCCESS;
        }

        foreach ($result->created as $path) {
            $output->writeln('Created: ' . $path);
        }
        foreach ($result->updated as $path) {
            $output->writeln('Updated: ' . $path);
        }

        return Command::SUCCESS;
    }
}

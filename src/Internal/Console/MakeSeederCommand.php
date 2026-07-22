<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Generator\SeederGenerator;
use BlackOps\Internal\Generator\SeederGeneratorInput;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeSeederCommand extends Command
{
    public const NAME = 'make:seeder';

    public function __construct(
        private readonly SeederGenerator $generator,
    ) {
        parent::__construct(self::NAME);
        $this->addArgument('name', InputArgument::REQUIRED, 'Seeder name as one or more PascalCase segments.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $name */
        $name = $input->getArgument('name');

        if (!is_string($name) || $name === '') {
            throw new InvalidArgumentException('Seeder name is required.');
        }

        $output->writeln('Created: ' . $this->generator->generate(SeederGeneratorInput::from($name)));

        return Command::SUCCESS;
    }
}

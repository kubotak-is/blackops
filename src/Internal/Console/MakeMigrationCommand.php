<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Generator\MigrationGenerator;
use BlackOps\Internal\Generator\MigrationGeneratorInput;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeMigrationCommand extends Command
{
    public const NAME = 'make:migration';

    public function __construct(
        private readonly MigrationGenerator $generator,
    ) {
        parent::__construct(self::NAME);
        $this->addArgument('description', InputArgument::REQUIRED, 'Migration description in PascalCase.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $description */
        $description = $input->getArgument('description');

        if (!is_string($description) || $description === '') {
            throw new InvalidArgumentException('Migration description is required.');
        }

        $output->writeln('Created: ' . $this->generator->generate(MigrationGeneratorInput::from($description)));

        return Command::SUCCESS;
    }
}

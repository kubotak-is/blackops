<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Apply or preview BlackOps database migrations.')]
final class DatabaseMigrationMigrateCommand extends Command
{
    public const NAME = 'database:migrate';

    public const LEGACY_NAME = 'blackops:database:migrate';

    public function __construct(
        private readonly DatabaseMigrationRunner $runner,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print SQL without changing the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $input->getOption('dry-run') === true ? $this->runner->dryRun() : $this->runner->migrate();

        if ($result->dryRun) {
            $output->writeln('Database migration dry run');
            foreach ($result->sql as $statement) {
                $output->writeln($statement . ';');
            }
        }

        if (!$result->dryRun) {
            $output->writeln('Database migrations applied');
        }

        $output->writeln('migrations: ' . $result->migrations);

        if ($result->migrations === 0) {
            $output->writeln('No pending migrations.');
        }

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Show applied and pending BlackOps database migrations.')]
final class DatabaseMigrationStatusCommand extends Command
{
    public const NAME = 'database:status';

    public function __construct(
        private readonly DatabaseMigrationRunner $runner,
    ) {
        parent::__construct(self::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = $this->runner->status();

        $output->writeln('Database migration status');
        $output->writeln('applied: ' . count($status->appliedVersions));
        $output->writeln('pending: ' . count($status->pendingVersions));

        foreach ($status->appliedVersions as $version) {
            $output->writeln('applied_version: ' . $version);
        }

        foreach ($status->pendingVersions as $version) {
            $output->writeln('pending_version: ' . $version);
        }

        return Command::SUCCESS;
    }
}

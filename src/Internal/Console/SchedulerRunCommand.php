<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Scheduler\MaintenanceScheduler;
use BlackOps\Internal\Scheduler\MaintenanceSchedulerResult;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Run due BlackOps maintenance tasks once.')]
final class SchedulerRunCommand extends Command
{
    public const NAME = 'blackops:scheduler:run';

    public function __construct(
        private readonly MaintenanceScheduler $scheduler,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct(self::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->scheduler->run($this->clock->now());

        $output->writeln('Scheduler run completed');
        $this->writeResult($output, $result);

        return Command::SUCCESS;
    }

    private function writeResult(OutputInterface $output, MaintenanceSchedulerResult $result): void
    {
        $output->writeln('tasks: ' . $result->count());
        $output->writeln('total_affected: ' . $result->totalAffected());

        foreach ($result->taskResults() as $taskResult) {
            $output->writeln(
                $taskResult->taskName() . ' affected=' . $taskResult->affectedCount() . ' summary='
                    . $taskResult->summary(),
            );
        }
    }
}

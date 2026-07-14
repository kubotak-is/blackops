<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Scheduler\MaintenanceScheduler;
use BlackOps\Internal\Scheduler\MaintenanceSchedulerResult;
use BlackOps\Internal\Scheduler\NativeSleeper;
use BlackOps\Internal\Scheduler\Sleeper;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Run BlackOps maintenance tasks on an interval.')]
final class SchedulerDaemonCommand extends Command
{
    public const NAME = 'scheduler:daemon';

    public const LEGACY_NAME = 'blackops:scheduler:daemon';

    public function __construct(
        private readonly MaintenanceScheduler $scheduler,
        private readonly ClockInterface $clock,
        private readonly Sleeper $sleeper = new NativeSleeper(),
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->addOption(
            'interval',
            null,
            InputOption::VALUE_REQUIRED,
            'Seconds between scheduler iterations.',
            '60',
        )->addOption('iterations', null, InputOption::VALUE_REQUIRED, 'Maximum iterations before exiting.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $interval = $this->positiveIntOption($input, 'interval');
        $iterations = $this->optionalPositiveIntOption($input, 'iterations');
        $iteration = 0;

        while ($iterations === null || $iteration < $iterations) {
            ++$iteration;

            $result = $this->scheduler->run($this->clock->now());
            $output->writeln('Scheduler daemon iteration ' . $iteration . ' completed');
            $this->writeResult($output, $result);

            if ($iterations !== null && $iteration >= $iterations) {
                break;
            }

            $this->sleeper->sleep($interval);
        }

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

    private function positiveIntOption(InputInterface $input, string $name): int
    {
        if (!is_string($input->getOption($name))) {
            throw new InvalidArgumentException('Scheduler command option must be a positive integer.');
        }

        $value = (string) $input->getOption($name);

        if (!ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException('Scheduler command option must be a positive integer.');
        }

        return (int) $value;
    }

    private function optionalPositiveIntOption(InputInterface $input, string $name): ?int
    {
        if ($input->getOption($name) === null) {
            return null;
        }

        return $this->positiveIntOption($input, $name);
    }
}

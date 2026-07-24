<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Execution\PcntlSignalSupport;
use BlackOps\Internal\Outbox\OutboxRelayRuntime;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Run outbox delivery on an interval.')]
final class OutboxRelayDaemonCommand extends Command
{
    public const NAME = 'outbox:relay:daemon';

    public function __construct(
        private readonly OutboxRelayRuntime $runtime,
        private readonly int $pollIntervalMilliseconds = 1000,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->addOption(
            'interval-milliseconds',
            null,
            InputOption::VALUE_REQUIRED,
            'Polling interval in milliseconds.',
        )->addOption('iterations', null, InputOption::VALUE_REQUIRED, 'Maximum iterations before exiting.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!PcntlSignalSupport::available()) {
            throw new RuntimeException('PCNTL signal functions are required for outbox relay daemon.');
        }
        $interval = $input->getOption('interval-milliseconds') === null
            ? $this->pollIntervalMilliseconds
            : $this->positiveInt((string) $input->getOption('interval-milliseconds'));
        /** @var mixed $iterations */
        $iterations = $input->getOption('iterations');
        $limit = $iterations === null ? null : $this->positiveInt((string) $iterations);
        $this->runtime->runSignalLoop(function () use ($interval, $limit, $output): void {
            $i = 0;
            while (!$this->runtime->stopRequested() && ($limit === null || $i < $limit)) {
                ++$i;
                $result = $this->runtime->runBatch();
                $output->writeln(sprintf(
                    'claimed: %d sent: %d retried: %d dead-lettered: %d stale: %d',
                    $result->claimed,
                    $result->sent,
                    $result->retried,
                    $result->deadLettered,
                    $result->stale,
                ));
                if ($limit === null || $i < $limit) {
                    usleep(max(1, $interval * 1000));
                }
            }
        });
        return Command::SUCCESS;
    }

    private function positiveInt(string $value): int
    {
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException('Outbox relay command option must be a positive integer.');
        }
        return (int) $value;
    }
}

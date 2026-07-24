<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Outbox\OutboxRelayRuntime;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Deliver pending transactional outbox records.')]
final class OutboxRelayRunCommand extends Command
{
    public const NAME = 'outbox:relay:run';

    public function __construct(
        private readonly OutboxRelayRuntime $runtime,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->addOption('batches', null, InputOption::VALUE_REQUIRED, 'Maximum batches to process.')->addOption(
            'until-empty',
            null,
            InputOption::VALUE_NONE,
            'Continue until no records remain.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var mixed $batches */
        $batches = $input->getOption('batches');
        $untilEmpty = $input->getOption('until-empty') === true;
        if ($batches !== null && $untilEmpty) {
            throw new InvalidArgumentException('Specify either --batches or --until-empty, not both.');
        }
        $limit = $batches === null ? 1 : $this->positiveInt((string) $batches);
        $totals = [0, 0, 0, 0, 0];
        for ($iteration = 0; $untilEmpty || $iteration < $limit; ++$iteration) {
            $result = $this->runtime->runBatch();
            $totals[0] += $result->claimed;
            $totals[1] += $result->sent;
            $totals[2] += $result->retried;
            $totals[3] += $result->deadLettered;
            $totals[4] += $result->stale;
            if ($untilEmpty && $result->claimed === 0) {
                break;
            }
        }
        $output->writeln(sprintf('claimed: %d', $totals[0]));
        $output->writeln(sprintf('sent: %d', $totals[1]));
        $output->writeln(sprintf('retried: %d', $totals[2]));
        $output->writeln(sprintf('dead-lettered: %d', $totals[3]));
        $output->writeln(sprintf('stale: %d', $totals[4]));
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

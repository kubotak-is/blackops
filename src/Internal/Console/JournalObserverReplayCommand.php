<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Replay\ObserverReplayRequest;
use BlackOps\Internal\Replay\ObserverReplayResult;
use BlackOps\Internal\Replay\ObserverReplayRuntime;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Replay canonical journal records to selected observers.')]
final class JournalObserverReplayCommand extends Command
{
    public const NAME = 'journal:observer:replay';

    public function __construct(
        private readonly ObserverReplayRuntime $runtime,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addOption('operation-id', null, InputOption::VALUE_REQUIRED)
            ->addOption('record-id', null, InputOption::VALUE_REQUIRED)
            ->addOption('from', null, InputOption::VALUE_REQUIRED)
            ->addOption('to', null, InputOption::VALUE_REQUIRED)
            ->addOption('observer', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY)
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, default: '100')
            ->addOption('dry-run', null, InputOption::VALUE_NONE)
            ->addOption('confirm', null, InputOption::VALUE_NONE)
            ->addOption('checkpoint', null, InputOption::VALUE_REQUIRED)
            ->addOption('resume', null, InputOption::VALUE_REQUIRED)
            ->addOption('actor', null, InputOption::VALUE_REQUIRED)
            ->addOption('reason', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = JournalObserverReplayOptions::fromInput($input);
        $result = $options->dryRun ? $this->dryRun($options) : $this->confirm($options);
        $this->writeResult($output, $result, $options->checkpoint);
        return Command::SUCCESS;
    }

    private function confirm(JournalObserverReplayOptions $options): ObserverReplayResult
    {
        if ($options->actor === null || $options->reason === null) {
            throw new InvalidArgumentException('Replay actor and reason are required for confirmation.');
        }
        if ($options->resume !== null) {
            return $this->runtime->resume($options->resume, $options->actor, $options->reason, $options->batchSize);
        }
        if ($options->selector === null || $options->checkpoint === null) {
            throw new InvalidArgumentException('Replay selector and checkpoint are required for confirmation.');
        }
        return $this->runtime->replay(
            new ObserverReplayRequest(
                $options->selector,
                $options->observers,
                $options->checkpoint,
                $options->actor,
                $options->reason,
                batchSize: $options->batchSize,
            ),
        );
    }

    private function dryRun(JournalObserverReplayOptions $options): ObserverReplayResult
    {
        if ($options->selector === null) {
            throw new InvalidArgumentException('Replay selector is required for dry-run.');
        }
        return $this->runtime->dryRun($options->selector, $options->observers, $options->batchSize);
    }

    private function writeResult(OutputInterface $output, ObserverReplayResult $result, ?string $checkpoint): void
    {
        $output->writeln('selected: ' . $result->selected);
        if ($checkpoint !== null) {
            $output->writeln('checkpoint: ' . $checkpoint);
        }
        $output->writeln('delivered: ' . $result->delivered);
        $output->writeln('failed: ' . $result->failed);
        $output->writeln('has-more: ' . ($result->hasMore ? 'true' : 'false'));
        $output->writeln('complete: ' . ($result->complete ? 'true' : 'false'));
        if ($result->recordIds !== []) {
            $output->writeln('first-record-id: ' . $result->recordIds[0]);
            $output->writeln('last-record-id: ' . $result->recordIds[count($result->recordIds) - 1]);
        }
    }
}

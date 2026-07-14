<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Execution\WorkerExecutionInterruptedException;
use BlackOps\Internal\Execution\WorkerLoop;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Run the deferred operation worker loop.')]
final class WorkerRunCommand extends Command
{
    public const NAME = 'worker:run';

    public const LEGACY_NAME = 'blackops:worker:run';

    public function __construct(
        private readonly WorkerLoop $worker,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->addOption(
            'iterations',
            null,
            InputOption::VALUE_REQUIRED,
            'Maximum loop iterations before exiting.',
        )->addOption(
            'idle-sleep-milliseconds',
            null,
            InputOption::VALUE_REQUIRED,
            'Milliseconds to sleep when no claim is available.',
            '1000',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $processed = $this->worker->run(
                $this->optionalPositiveInt($input, 'iterations'),
                $this->positiveInt($input, 'idle-sleep-milliseconds'),
            );
        } catch (WorkerExecutionInterruptedException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('Worker stopped. Processed claims: ' . $processed);

        return Command::SUCCESS;
    }

    private function optionalPositiveInt(InputInterface $input, string $name): ?int
    {
        if ($input->getOption($name) === null) {
            return null;
        }

        return $this->positiveInt($input, $name);
    }

    private function positiveInt(InputInterface $input, string $name): int
    {
        return $this->parsePositiveInt($input->getOption($name));
    }

    private function parsePositiveInt(mixed $value): int
    {
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException('Worker command option must be a positive integer.');
        }

        return (int) $value;
    }
}

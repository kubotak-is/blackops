<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Exception\ConfigurationFailure;
use BlackOps\Internal\Scheduling\ScheduledOperationRunResult;
use BlackOps\Internal\Scheduling\ScheduledOperationRunService;
use Closure;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ScheduledOperationRunCommand extends Command
{
    public const NAME = 'operation:schedule:run';

    /** @var Closure(): ScheduledOperationRunService */
    private Closure $runner;

    /** @param Closure(): ScheduledOperationRunService $runner */
    public function __construct(Closure $runner)
    {
        $this->runner = $runner;
        parent::__construct(self::NAME);
        $this->setDescription('Evaluate and invoke application schedules once.');
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Write a versioned JSON object.');
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ExceptionInterface) {
            return $this->writeError($output, 'configuration_error', Command::INVALID, $this->jsonRequested($input));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = ($this->runner)()->run();
        } catch (InvalidArgumentException|ConfigurationFailure) {
            return $this->writeError($output, 'configuration_error', 2, $input->getOption('json') === true);
        } catch (Throwable) {
            return $this->writeError($output, 'runtime_error', 1, $input->getOption('json') === true);
        }

        return $this->writeResult($result, $input->getOption('json') === true, $output);
    }

    private function writeResult(ScheduledOperationRunResult $result, bool $json, OutputInterface $output): int
    {
        $payload = [
            'schemaVersion' => 1,
            'status' => $result->status(),
            'evaluated' => $result->evaluated,
            'accepted' => $result->accepted,
            'skipped_misfire' => $result->skippedMisfire,
            'skipped_overlap' => $result->skippedOverlap,
            'failed' => $result->failed,
        ];
        if ($json) {
            $output->writeln(json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $output->writeln('Scheduled operation run completed.');
            foreach (['evaluated', 'accepted', 'skipped_misfire', 'skipped_overlap', 'failed'] as $field) {
                $output->writeln($field . ': ' . $payload[$field]);
            }
        }

        return $result->failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function writeError(OutputInterface $output, string $code, int $exitCode, bool $json): int
    {
        if ($json) {
            $output->writeln(json_encode([
                'schemaVersion' => 1,
                'status' => 'failed',
                'code' => $code,
            ], JSON_THROW_ON_ERROR));
        } else {
            $output->writeln('Scheduled operation run failed [' . $code . '].');
        }

        return $exitCode;
    }

    private function jsonRequested(InputInterface $input): bool
    {
        return $input->hasParameterOption('--json');
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Diagnostics\SystemDiagnosticsCheck;
use BlackOps\Internal\Diagnostics\SystemDiagnosticsRenderer;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DiagnosticsCheckCommand extends Command
{
    public const NAME = 'diagnostics:check';

    public function __construct(
        private readonly SystemDiagnosticsCheck $check,
        private readonly SystemDiagnosticsRenderer $renderer = new SystemDiagnosticsRenderer(),
    ) {
        parent::__construct(self::NAME);
        $this->setDescription('Check application readiness and compiled artifacts.');
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Write one versioned JSON object.');
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ExceptionInterface) {
            return $this->inputError($output);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $json = $this->jsonOption($input);
        } catch (InvalidArgumentException) {
            return $this->inputError($output);
        }

        $report = $this->check->check();
        $output->write($this->renderer->render($report, json: $json), options: OutputInterface::OUTPUT_RAW);

        return $report->isPassing() ? self::SUCCESS : self::FAILURE;
    }

    private function jsonOption(InputInterface $input): bool
    {
        $json = $input->getOption('json');
        if (!is_bool($json)) {
            throw new InvalidArgumentException('Diagnostics JSON option is invalid.');
        }

        return $json;
    }

    private function inputError(OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $error->write("diagnostics.input_invalid\n", options: OutputInterface::OUTPUT_RAW);

        return self::INVALID;
    }
}

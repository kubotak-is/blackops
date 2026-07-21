<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use Closure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class OperationConsoleCommand extends Command
{
    /** @var Closure(): OperationConsoleRuntime */
    private Closure $runtime;

    /** @param Closure(): OperationConsoleRuntime $runtime */
    public function __construct(
        private readonly OperationConsoleCommandMetadata $metadata,
        Closure $runtime,
        private readonly OperationConsoleOutput $formatter = new OperationConsoleOutput(),
    ) {
        $this->runtime = $runtime;
        parent::__construct($metadata->name);
        $this->setDescription($metadata->description);
        foreach ($metadata->options as $option) {
            $this->addOption(
                $option->name,
                null,
                InputOption::VALUE_REQUIRED,
                default: $option->required ? null : $option->default,
            );
        }
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Write one versioned JSON object.');
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ConsoleRuntimeException) {
            return $this->formatter->write(
                new OperationConsoleInvocationResult([
                    'schemaVersion' => 1,
                    'status' => 'rejected',
                    'category' => 'validation',
                    'code' => 'binding.failed',
                    'violations' => [],
                ], 2),
                $this->jsonRequested($input),
                $output,
            );
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $values = [];
        foreach ($this->metadata->options as $option) {
            $values[$option->name] = $input->getOption($option->name);
        }
        $result = ($this->runtime)()->invoke($this->metadata, $values);

        return $this->formatter->write($result, $input->getOption('json') === true, $output);
    }

    private function jsonRequested(InputInterface $input): bool
    {
        try {
            return $input->getOption('json') === true;
        } catch (\Throwable) {
            return false;
        }
    }
}

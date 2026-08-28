<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Exception\ConfigurationFailure;
use BlackOps\Core\Queue\QueueStatusFailure;
use BlackOps\Core\Queue\QueueStatusSnapshot;
use BlackOps\Core\Queue\QueueStatusSource;
use BlackOps\Core\Queue\QueueStatusSourceException;
use BlackOps\Internal\Queue\QueueStatusRenderer;
use Closure;
use InvalidArgumentException;
use LogicException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class QueueStatusCommand extends Command
{
    public const NAME = 'queue:status';

    /** @var Closure(): QueueStatusSource */
    private readonly Closure $sourceFactory;

    /**
     * @param Closure(): QueueStatusSource $sourceFactory
     */
    public function __construct(
        Closure $sourceFactory,
        private readonly ClockInterface $clock,
        private readonly QueueStatusRenderer $renderer = new QueueStatusRenderer(),
    ) {
        $this->sourceFactory = $sourceFactory;
        parent::__construct(self::NAME);
        $this->setDescription('Show aggregate deferred-operation queue status.');
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

    /** @mago-expect lint:halstead */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $json = $this->jsonOption($input);
        } catch (InvalidArgumentException) {
            return $this->inputError($output);
        }

        try {
            $observedAt = $this->clock->now();
        } catch (Throwable) {
            return $this->failure($output, QueueStatusFailure::Storage);
        }

        try {
            $source = ($this->sourceFactory)();
            if (!$source instanceof QueueStatusSource) {
                return $this->failure($output, QueueStatusFailure::Configuration);
            }
        } catch (QueueStatusSourceException $exception) {
            return $this->failure($output, $exception->failure);
        } catch (InvalidArgumentException|LogicException|ConfigurationFailure) {
            return $this->failure($output, QueueStatusFailure::Configuration);
        } catch (Throwable) {
            return $this->failure($output, QueueStatusFailure::Storage);
        }

        try {
            $snapshot = $source->read($observedAt);
            if (!$snapshot instanceof QueueStatusSnapshot) {
                return $this->failure($output, QueueStatusFailure::Integrity);
            }
        } catch (QueueStatusSourceException $exception) {
            return $this->failure($output, $exception->failure);
        } catch (Throwable) {
            return $this->failure($output, QueueStatusFailure::Storage);
        }

        try {
            $bytes = $this->renderer->render($snapshot, json: $json);
        } catch (Throwable) {
            return $this->failure($output, QueueStatusFailure::Integrity);
        }
        $output->write($bytes, options: OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    private function jsonOption(InputInterface $input): bool
    {
        $json = $input->getOption('json');
        if (!is_bool($json)) {
            throw new InvalidArgumentException('Queue JSON option is invalid.');
        }

        return $json;
    }

    private function inputError(OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $error->write("queue.input_invalid\n", options: OutputInterface::OUTPUT_RAW);

        return self::INVALID;
    }

    private function failure(OutputInterface $output, QueueStatusFailure $failure): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $error->write('queue.' . $failure->value . "\n", options: OutputInterface::OUTPUT_RAW);

        return $failure === QueueStatusFailure::Configuration ? self::INVALID : self::FAILURE;
    }
}

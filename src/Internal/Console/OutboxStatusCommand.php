<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Exception\ConfigurationFailure;
use BlackOps\Core\Outbox\OutboxStatusFailure;
use BlackOps\Core\Outbox\OutboxStatusSnapshot;
use BlackOps\Core\Outbox\OutboxStatusSource;
use BlackOps\Core\Outbox\OutboxStatusSourceException;
use BlackOps\Internal\Outbox\OutboxStatusRenderer;
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

final class OutboxStatusCommand extends Command
{
    public const NAME = 'outbox:status';

    /** @var Closure(): OutboxStatusSource */
    private readonly Closure $sourceFactory;

    /** @param Closure(): OutboxStatusSource $sourceFactory */
    public function __construct(
        Closure $sourceFactory,
        private readonly ClockInterface $clock,
        private readonly OutboxStatusRenderer $renderer = new OutboxStatusRenderer(),
    ) {
        $this->sourceFactory = $sourceFactory;
        parent::__construct(self::NAME);
        $this->setDescription('Show aggregate transactional outbox status.');
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
            return $this->failure($output, OutboxStatusFailure::Storage);
        }

        try {
            $source = ($this->sourceFactory)();
            if (!$source instanceof OutboxStatusSource) {
                return $this->failure($output, OutboxStatusFailure::Configuration);
            }
        } catch (OutboxStatusSourceException $exception) {
            return $this->failure($output, $exception->failure);
        } catch (InvalidArgumentException|LogicException|ConfigurationFailure) {
            return $this->failure($output, OutboxStatusFailure::Configuration);
        } catch (Throwable) {
            return $this->failure($output, OutboxStatusFailure::Storage);
        }

        try {
            $snapshot = $source->read($observedAt);
            if (!$snapshot instanceof OutboxStatusSnapshot) {
                return $this->failure($output, OutboxStatusFailure::Integrity);
            }
        } catch (OutboxStatusSourceException $exception) {
            return $this->failure($output, $exception->failure);
        } catch (Throwable) {
            return $this->failure($output, OutboxStatusFailure::Storage);
        }

        try {
            $bytes = $this->renderer->render($snapshot, json: $json);
        } catch (Throwable) {
            return $this->failure($output, OutboxStatusFailure::Integrity);
        }
        $output->write($bytes, options: OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    private function jsonOption(InputInterface $input): bool
    {
        $json = $input->getOption('json');
        if (!is_bool($json)) {
            throw new InvalidArgumentException('Outbox JSON option is invalid.');
        }

        return $json;
    }

    private function inputError(OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $error->write("outbox.input_invalid\n", options: OutputInterface::OUTPUT_RAW);

        return self::INVALID;
    }

    private function failure(OutputInterface $output, OutboxStatusFailure $failure): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $error->write('outbox.' . $failure->value . "\n", options: OutputInterface::OUTPUT_RAW);

        return $failure === OutboxStatusFailure::Configuration ? self::INVALID : self::FAILURE;
    }
}

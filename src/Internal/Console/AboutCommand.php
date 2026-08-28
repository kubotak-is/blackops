<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\About\AboutMetadata;
use BlackOps\Internal\About\AboutMetadataSource;
use BlackOps\Internal\About\AboutRenderer;
use BlackOps\Internal\About\AboutReport;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetStatusSource;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class AboutCommand extends Command
{
    public const NAME = 'about';

    public function __construct(
        private readonly AboutMetadataSource $metadata,
        private readonly ProjectionBuildSetStatusSource $projection,
        private readonly AboutRenderer $renderer = new AboutRenderer(),
    ) {
        parent::__construct(self::NAME);
        $this->setDescription('Show installed BlackOps package, runtime, and projection status.');
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

        try {
            $metadata = $this->metadata->read();
            if (!$metadata instanceof AboutMetadata) {
                return $this->metadataUnavailable($output);
            }
        } catch (Throwable) {
            return $this->metadataUnavailable($output);
        }

        try {
            $projectionStatus = $this->projectionStatus();
        } catch (ProjectionBuildSetValidationException) {
            $projectionStatus = 'configuration_invalid';
        } catch (ProjectionBuildSetFilesystemException) {
            $projectionStatus = 'read_failed';
        } catch (Throwable) {
            return $this->metadataUnavailable($output);
        }

        try {
            $bytes = $this->renderer->render(new AboutReport($metadata, $projectionStatus), json: $json);
        } catch (Throwable) {
            return $this->metadataUnavailable($output);
        }
        $output->write($bytes, options: OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    private function projectionStatus(): string
    {
        $result = $this->projection->withStatus(static fn(ProjectionBuildSetRecoveryResult $recovery): string => $recovery->status->value);
        if (is_string($result) && in_array($result, AboutReport::PROJECTION_STATUSES, strict: true)) {
            return $result;
        }

        throw new InvalidArgumentException('About projection status result is invalid.');
    }

    private function jsonOption(InputInterface $input): bool
    {
        $json = $input->getOption('json');
        if (!is_bool($json)) {
            throw new InvalidArgumentException('About JSON option is invalid.');
        }

        return $json;
    }

    private function inputError(OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $error->write("about.input_invalid\n", options: OutputInterface::OUTPUT_RAW);

        return self::INVALID;
    }

    private function metadataUnavailable(OutputInterface $output): int
    {
        $error = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $error->write("about.metadata_unavailable\n", options: OutputInterface::OUTPUT_RAW);

        return self::FAILURE;
    }
}

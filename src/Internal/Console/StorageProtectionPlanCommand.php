<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationResult;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StorageProtectionPlanCommand extends Command
{
    public const NAME = 'storage:protection:plan';

    protected string $checkpointDefault = 'storage-protection-plan';

    private readonly StorageProtectionRotationInput $inputParser;

    private readonly StorageProtectionRotationOutput $outputWriter;

    public function __construct(
        protected readonly PostgreSqlStorageProtectionRotation $rotation,
    ) {
        parent::__construct(self::NAME);
        $this->inputParser = new StorageProtectionRotationInput();
        $this->outputWriter = new StorageProtectionRotationOutput();
    }

    protected function configure(): void
    {
        $this
            ->addOption('purpose', null, InputOption::VALUE_REQUIRED)
            ->addOption('tenant-type', null, InputOption::VALUE_REQUIRED)
            ->addOption('tenant-id', null, InputOption::VALUE_REQUIRED)
            ->addOption('old-key-id', null, InputOption::VALUE_REQUIRED)
            ->addOption('new-key-id', null, InputOption::VALUE_REQUIRED)
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, default: '100')
            ->addOption('checkpoint', null, InputOption::VALUE_REQUIRED, default: $this->checkpointDefault)
            ->addOption('actor', null, InputOption::VALUE_REQUIRED)
            ->addOption('reason', null, InputOption::VALUE_REQUIRED)
            ->addOption('json', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = $this->rotation->plan($this->scope($input));
            $this->writeResult($input, $output, $result);
            return Command::SUCCESS;
        } catch (InvalidArgumentException $exception) {
            return $this->inputFailure($input, $output, $exception);
        } catch (\Throwable) {
            return $this->storageFailure($input, $output);
        }
    }

    protected function scope(InputInterface $input): StorageProtectionRotationScope
    {
        return $this->inputParser->scope($input);
    }

    private function writeResult(
        InputInterface $input,
        OutputInterface $output,
        StorageProtectionRotationResult $result,
    ): void {
        if ($input->getOption('json') === true) {
            $this->outputWriter->json($output, $result);
            return;
        }
        $this->outputWriter->human($output, $result);
    }

    private function inputFailure(
        InputInterface $input,
        OutputInterface $output,
        InvalidArgumentException $exception,
    ): int {
        if ($input->getOption('json') === true) {
            $output->writeln(json_encode([
                'schemaVersion' => 1,
                'status' => 'failed',
                'code' => 'input_error',
            ], JSON_THROW_ON_ERROR));
            return 2;
        }
        $output->writeln('Input error: ' . $exception->getMessage());
        return 2;
    }

    private function storageFailure(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('json') === true) {
            $output->writeln(json_encode([
                'schemaVersion' => 1,
                'status' => 'failed',
                'code' => 'storage_error',
            ], JSON_THROW_ON_ERROR));
            return 1;
        }
        $output->writeln('Storage protection plan failed safely.');
        return 1;
    }
}

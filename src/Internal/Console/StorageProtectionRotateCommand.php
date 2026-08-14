<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationResult;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class StorageProtectionRotateCommand extends StorageProtectionPlanCommand
{
    public const NAME = 'storage:protection:rotate';

    protected string $checkpointDefault = 'storage-protection-rotate';

    public function __construct(PostgreSqlStorageProtectionRotation $rotation)
    {
        parent::__construct($rotation);
        $this->setName(self::NAME);
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('confirm', null, InputOption::VALUE_NONE)->addOption('dry-run', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('dry-run') === true || $input->getOption('confirm') !== true) {
            return parent::execute($input, $output);
        }
        try {
            if (!$input->hasParameterOption('--checkpoint')) {
                throw new InvalidArgumentException('Rotation checkpoint must be explicit for confirmation.');
            }
            $scope = new StorageProtectionRotationInput()->confirmedScope($input);
            $result = $this->rotation->rotate($scope);
            $this->writeResult($input, $output, $result);
            return $result->failed > 0 ? 1 : 0;
        } catch (InvalidArgumentException $exception) {
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
        } catch (\Throwable) {
            if ($input->getOption('json') === true) {
                $output->writeln(json_encode([
                    'schemaVersion' => 1,
                    'status' => 'failed',
                    'code' => 'storage_error',
                ], JSON_THROW_ON_ERROR));
                return 1;
            }
            $output->writeln('Storage protection rotation failed safely.');
            return 1;
        }
    }

    private function writeResult(
        InputInterface $input,
        OutputInterface $output,
        StorageProtectionRotationResult $result,
    ): void {
        if ($input->getOption('json') === true) {
            new StorageProtectionRotationOutput()->json($output, $result);
            return;
        }
        new StorageProtectionRotationOutput()->human($output, $result);
    }
}

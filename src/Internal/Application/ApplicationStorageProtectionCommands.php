<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\StorageProtectionLazyCommand;
use BlackOps\Internal\Console\StorageProtectionPlanCommand;
use BlackOps\Internal\Console\StorageProtectionRotateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

final class ApplicationStorageProtectionCommands
{
    /** @return list<Command> */
    public static function create(ApplicationConsoleCommandFactory $factory): array
    {
        return [
            new StorageProtectionLazyCommand(
                StorageProtectionPlanCommand::NAME,
                'Plan a bounded protected storage key rotation without writing data.',
                $factory->storageProtectionPlan(...),
                self::planOptions(...),
            ),
            new StorageProtectionLazyCommand(
                StorageProtectionRotateCommand::NAME,
                'Dry-run or apply a bounded protected storage key rotation.',
                $factory->storageProtectionRotate(...),
                self::rotateOptions(...),
            ),
        ];
    }

    private static function planOptions(Command $command): void
    {
        self::scopeOptions($command, 'storage-protection-plan');
        $command->addOption('actor', null, InputOption::VALUE_REQUIRED);
        $command->addOption('reason', null, InputOption::VALUE_REQUIRED);
        $command->addOption('json', null, InputOption::VALUE_NONE);
    }

    private static function rotateOptions(Command $command): void
    {
        self::scopeOptions($command, 'storage-protection-rotate');
        $command->addOption('actor', null, InputOption::VALUE_REQUIRED);
        $command->addOption('reason', null, InputOption::VALUE_REQUIRED);
        $command->addOption('confirm', null, InputOption::VALUE_NONE);
        $command->addOption('dry-run', null, InputOption::VALUE_NONE);
        $command->addOption('json', null, InputOption::VALUE_NONE);
    }

    private static function scopeOptions(Command $command, string $checkpoint): void
    {
        $command
            ->addOption('purpose', null, InputOption::VALUE_REQUIRED)
            ->addOption('tenant-type', null, InputOption::VALUE_REQUIRED)
            ->addOption('tenant-id', null, InputOption::VALUE_REQUIRED)
            ->addOption('old-key-id', null, InputOption::VALUE_REQUIRED)
            ->addOption('new-key-id', null, InputOption::VALUE_REQUIRED)
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, default: '100')
            ->addOption('checkpoint', null, InputOption::VALUE_REQUIRED, default: $checkpoint);
    }
}

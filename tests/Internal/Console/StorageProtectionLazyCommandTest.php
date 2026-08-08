<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\StorageProtectionLazyCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;

final class StorageProtectionLazyCommandTest extends TestCase
{
    public function testConfigurationFailureIsSafeJsonExitTwo(): void
    {
        $command = new StorageProtectionLazyCommand(
            'storage:protection:test',
            'test',
            static fn(): Command => throw new \LogicException('secret-provider-detail'),
            static function (Command $command): void {
                $command->addOption('json', null, InputOption::VALUE_NONE);
            },
        );
        $tester = new CommandTester($command);
        self::assertSame(2, $tester->execute(['--json' => true]));
        self::assertSame(
            ['schemaVersion' => 1, 'status' => 'failed', 'code' => 'configuration_error'],
            json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString('secret-provider-detail', $tester->getDisplay());
    }

    public function testUnknownOptionIsSafeInputErrorExitTwo(): void
    {
        $command = new StorageProtectionLazyCommand(
            'storage:protection:test',
            'test',
            static fn(): Command => new Command('inner'),
            static function (Command $command): void {
                $command->addOption('json', null, InputOption::VALUE_NONE);
            },
        );
        $tester = new CommandTester($command);
        self::assertSame(2, $tester->execute(['--unknown' => true, '--json' => true]));
        self::assertSame(
            ['schemaVersion' => 1, 'status' => 'failed', 'code' => 'input_error'],
            json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR),
        );
    }
}

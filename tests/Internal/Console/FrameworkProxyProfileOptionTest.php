<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Console\FrameworkProxyProfileOption;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class FrameworkProxyProfileOptionTest extends TestCase
{
    public function testRayIsTheDefaultAndFrameworkCanBeSelected(): void
    {
        $command = new class extends Command {
            protected function configure(): void
            {
                FrameworkProxyProfileOption::configure($this);
            }

            protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output,
            ): int {
                $output->write(FrameworkProxyProfileOption::fromInput($input)->value);
                return self::SUCCESS;
            }
        };
        $default = new CommandTester($command);
        $default->execute([]);
        self::assertSame(FrameworkProxyProfile::RAY, $default->getDisplay());

        $framework = new CommandTester($command);
        $framework->execute(['--proxy-profile' => FrameworkProxyProfile::FRAMEWORK]);
        self::assertSame(FrameworkProxyProfile::FRAMEWORK, $framework->getDisplay());
    }

    public function testInvalidProfileHasSafeDiagnostic(): void
    {
        $command = new class extends Command {
            protected function configure(): void
            {
                FrameworkProxyProfileOption::configure($this);
            }

            protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output,
            ): int {
                FrameworkProxyProfileOption::fromInput($input);
                return self::SUCCESS;
            }
        };
        $this->expectExceptionMessage('must be ray or framework');
        new CommandTester($command)->execute(['--proxy-profile' => 'unknown']);
    }
}

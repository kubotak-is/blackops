<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\MakeOperationCommand;
use BlackOps\Internal\Generator\OperationGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeOperationCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-make-operation-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
    }

    public function testGeneratesOperationAndPrintsProjectRelativePaths(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(0, $tester->execute([
            'operation' => 'Welcome/ShowWelcome',
            '--type' => 'welcome.show',
        ]));
        self::assertSame(
            "Created: app/Feature/Welcome/ShowWelcome/ShowWelcome.php\n"
            . "Created: app/Feature/Welcome/ShowWelcome/ShowWelcomeValue.php\n"
            . "Created: app/Feature/Welcome/ShowWelcome/ShowWelcomeOutcome.php\n",
            $tester->getDisplay(),
        );
        self::assertStringNotContainsString($this->directory, $tester->getDisplay());
    }

    public function testRequiresOperationType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operation path and --type are required.');

        new CommandTester($this->command())->execute(['operation' => 'Welcome/ShowWelcome']);
    }

    private function command(): MakeOperationCommand
    {
        return new MakeOperationCommand(
            new OperationGenerator($this->directory, dirname(__DIR__, levels: 3) . '/resources/stubs'),
        );
    }
}

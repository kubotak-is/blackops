<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\MakeAuthCommand;
use BlackOps\Internal\Generator\AuthGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeAuthCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-make-auth-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        file_put_contents($this->directory . '/composer.json', '{"autoload":{"psr-4":{"App\\\\":"app/"}}}');
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

    public function testCreatesStarterWithSafeRelativeOutputThenReportsNoop(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString("Created: app/Domain/Identity/User.php\n", $tester->getDisplay());
        self::assertStringContainsString("Created: config/auth.php\n", $tester->getDisplay());
        self::assertStringNotContainsString($this->directory, $tester->getDisplay());
        self::assertStringNotContainsString('credential-secret', $tester->getDisplay());

        $again = new CommandTester($this->command());
        self::assertSame(0, $again->execute([]));
        self::assertSame("Authentication starter is already current.\n", $again->getDisplay());
    }

    private function command(): MakeAuthCommand
    {
        return new MakeAuthCommand(
            new AuthGenerator($this->directory, dirname(__DIR__, levels: 3) . '/resources/stubs'),
        );
    }
}

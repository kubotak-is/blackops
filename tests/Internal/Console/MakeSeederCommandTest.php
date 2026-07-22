<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\MakeSeederCommand;
use BlackOps\Internal\Generator\SeederGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeSeederCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-make-seeder-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
    }

    public function testGeneratesSeederAndPrintsProjectRelativePath(): void
    {
        $tester = new CommandTester(new MakeSeederCommand(
            new SeederGenerator($this->directory, dirname(__DIR__, levels: 3) . '/resources/stubs'),
        ));

        self::assertSame(0, $tester->execute(['name' => 'Board/PostSeeder']));
        self::assertSame("Created: app/Infrastructure/Seed/Board/PostSeeder.php\n", $tester->getDisplay());
        self::assertStringNotContainsString($this->directory, $tester->getDisplay());
        self::assertFileExists($this->directory . '/app/Infrastructure/Seed/Board/PostSeeder.php');
    }
}

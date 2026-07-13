<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\MakeMigrationCommand;
use BlackOps\Internal\Generator\MigrationGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeMigrationCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-make-migration-' . bin2hex(random_bytes(8));
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

    public function testGeneratesMigrationAndPrintsProjectRelativePath(): void
    {
        $tester = new CommandTester(new MakeMigrationCommand(new MigrationGenerator(
            $this->directory,
            dirname(__DIR__, levels: 3) . '/resources/stubs',
            new readonly class implements ClockInterface {
                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable('2026-07-13T12:34:56Z');
                }
            },
        )));

        self::assertSame(0, $tester->execute(['description' => 'CreateOrdersTable']));
        self::assertSame("Created: migrations/Version20260713123456.php\n", $tester->getDisplay());
        self::assertStringNotContainsString($this->directory, $tester->getDisplay());
        self::assertFileExists($this->directory . '/migrations/Version20260713123456.php');
    }
}

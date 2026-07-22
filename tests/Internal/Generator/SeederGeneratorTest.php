<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Generator;

use BlackOps\Internal\Generator\ProjectFileWriter;
use BlackOps\Internal\Generator\SeederGenerator;
use BlackOps\Internal\Generator\SeederGeneratorInput;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeederGeneratorTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $entry) {
                $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($directory);
        }
    }

    public function testGeneratesRootAndNestedSeederFromFrameworkStub(): void
    {
        $directory = $this->directory();
        $generator = $this->generator($directory);

        self::assertSame(
            'app/Infrastructure/Seed/DatabaseSeeder.php',
            $generator->generate(SeederGeneratorInput::from('DatabaseSeeder')),
        );
        self::assertSame(
            'app/Infrastructure/Seed/Board/PostSeeder.php',
            $generator->generate(SeederGeneratorInput::from('Board/PostSeeder')),
        );

        $root = (string) file_get_contents($directory . '/app/Infrastructure/Seed/DatabaseSeeder.php');
        $nested = (string) file_get_contents($directory . '/app/Infrastructure/Seed/Board/PostSeeder.php');
        self::assertStringContainsString('namespace App\\Infrastructure\\Seed;', $root);
        self::assertStringContainsString('final readonly class DatabaseSeeder implements Seeder', $root);
        self::assertStringContainsString('namespace App\\Infrastructure\\Seed\\Board;', $nested);
        self::assertStringContainsString('final readonly class PostSeeder implements Seeder', $nested);
        self::assertStringContainsString('public function run(): void {}', $root);
        self::assertStringNotContainsString('SeederRunner', $root);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'absolute' => ['/DatabaseSeeder'];
        yield 'traversal' => ['../DatabaseSeeder'];
        yield 'dot' => ['Board/./PostSeeder'];
        yield 'empty segment' => ['Board//PostSeeder'];
        yield 'backslash' => ['Board\\PostSeeder'];
        yield 'lowercase' => ['board/PostSeeder'];
        yield 'reserved' => ['Board/Class'];
        yield 'control' => ["Board/Post\nSeeder"];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsInvalidNameBeforeWriting(string $name): void
    {
        $directory = $this->directory();

        try {
            $this->generator($directory)->generate(SeederGeneratorInput::from($name));
            self::fail('Expected invalid seeder name.');
        } catch (InvalidArgumentException) {
            self::assertDirectoryDoesNotExist($directory . '/app');
        }
    }

    public function testExistingFileAndDirectoryTargetsAreNeverOverwritten(): void
    {
        $directory = $this->directory();
        $target = $directory . '/app/Infrastructure/Seed';
        mkdir($target, recursive: true);
        file_put_contents($target . '/DatabaseSeeder.php', 'existing');

        try {
            $this->generator($directory)->generate(SeederGeneratorInput::from('DatabaseSeeder'));
            self::fail('Expected existing target rejection.');
        } catch (InvalidArgumentException) {
            self::assertSame('existing', file_get_contents($target . '/DatabaseSeeder.php'));
        }

        mkdir($target . '/BoardSeeder.php');
        try {
            $this->generator($directory)->generate(SeederGeneratorInput::from('BoardSeeder'));
            self::fail('Expected directory target rejection.');
        } catch (InvalidArgumentException) {
            self::assertDirectoryExists($target . '/BoardSeeder.php');
        }
    }

    public function testTemporaryWriteFailureLeavesNoPartialTree(): void
    {
        $directory = $this->directory();
        $writer = new ProjectFileWriter(static function (string $path, string $_contents): int {
            file_put_contents($path, 'partial');

            return 0;
        });

        try {
            $this->generator($directory, $writer)->generate(SeederGeneratorInput::from('Board/PostSeeder'));
            self::fail('Expected write failure.');
        } catch (InvalidArgumentException) {
            self::assertDirectoryDoesNotExist($directory . '/app');
        }
    }

    public function testPublishRacePreservesCompetingFileAndRemovesTemporaryFile(): void
    {
        $directory = $this->directory();
        $writer = new ProjectFileWriter(beforePublish: static function (
            string $_temporary,
            string $target,
            int $_index,
        ): void {
            file_put_contents($target, 'competing');
        });

        try {
            $this->generator($directory, $writer)->generate(SeederGeneratorInput::from('DatabaseSeeder'));
            self::fail('Expected publish race rejection.');
        } catch (InvalidArgumentException) {
            $target = $directory . '/app/Infrastructure/Seed';
            self::assertSame('competing', file_get_contents($target . '/DatabaseSeeder.php'));
            self::assertSame([], glob($target . '/.blackops-*.tmp') ?: []);
        }
    }

    public function testSymlinkAncestorCannotEscapeApplication(): void
    {
        $directory = $this->directory();
        $outside = $this->directory();
        symlink($outside, $directory . '/app');

        try {
            $this->generator($directory)->generate(SeederGeneratorInput::from('DatabaseSeeder'));
            self::fail('Expected symlink escape rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('outside the application', $exception->getMessage());
            self::assertSame([], array_values(array_diff(scandir($outside) ?: [], ['.', '..'])));
        }
    }

    public function testMissingAndRacedStubHideFrameworkPathAndWarnings(): void
    {
        $directory = $this->directory();
        $stubs = $directory . '/private/stubs';

        try {
            new SeederGenerator($directory, $stubs)->generate(SeederGeneratorInput::from('DatabaseSeeder'));
            self::fail('Expected missing stub failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Seeder generator stub is unavailable.', $exception->getMessage());
            self::assertStringNotContainsString($stubs, $exception->getMessage());
        }

        mkdir($stubs, recursive: true);
        copy(dirname(__DIR__, levels: 3) . '/resources/stubs/seeder.php.stub', $stubs . '/seeder.php.stub');
        $warnings = [];
        set_error_handler(static function (int $_severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });
        try {
            new SeederGenerator($directory, $stubs, beforeStubRead: static fn(string $path): bool => unlink(
                $path,
            ))->generate(SeederGeneratorInput::from('DatabaseSeeder'));
            self::fail('Expected raced stub failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Seeder generator stub is unavailable.', $exception->getMessage());
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $warnings);
        self::assertDirectoryDoesNotExist($directory . '/app');
    }

    private function generator(string $directory, ?ProjectFileWriter $writer = null): SeederGenerator
    {
        return new SeederGenerator(
            $directory,
            dirname(__DIR__, levels: 3) . '/resources/stubs',
            $writer ?? new ProjectFileWriter(),
        );
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/blackops-seeder-generator-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->directories[] = $directory;

        return $directory;
    }
}

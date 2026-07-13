<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Generator;

use BlackOps\Internal\Generator\OperationGenerator;
use BlackOps\Internal\Generator\OperationGeneratorInput;
use BlackOps\Internal\Generator\ProjectFileWriter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OperationGeneratorTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $this->remove($directory);
        }
    }

    public function testGeneratesTypedSelfHandledOperationValueAndOutcome(): void
    {
        $directory = $this->directory();
        $paths = $this->generator($directory)->generate(OperationGeneratorInput::from(
            'Welcome/ShowWelcome',
            'welcome.show',
        ));

        self::assertSame(
            [
                'app/Feature/Welcome/ShowWelcome/ShowWelcome.php',
                'app/Feature/Welcome/ShowWelcome/ShowWelcomeValue.php',
                'app/Feature/Welcome/ShowWelcome/ShowWelcomeOutcome.php',
            ],
            $paths,
        );

        $operation = (string) file_get_contents($directory . '/' . $paths[0]);
        $value = (string) file_get_contents($directory . '/' . $paths[1]);
        $outcome = (string) file_get_contents($directory . '/' . $paths[2]);

        self::assertStringContainsString('namespace App\\Feature\\Welcome\\ShowWelcome;', $operation);
        self::assertStringContainsString("#[OperationType('welcome.show')]", $operation);
        self::assertStringContainsString('final readonly class ShowWelcome implements Operation', $operation);
        self::assertStringContainsString('handle(ShowWelcomeValue $value): ShowWelcomeOutcome', $operation);
        self::assertStringContainsString('return new ShowWelcomeOutcome();', $operation);
        self::assertStringContainsString('final readonly class ShowWelcomeValue implements OperationValue', $value);
        self::assertStringContainsString('final readonly class ShowWelcomeOutcome implements Outcome', $outcome);

        foreach ([$operation, $value, $outcome] as $source) {
            self::assertStringNotContainsString('Accepts', $source);
            self::assertStringNotContainsString('Returns', $source);
            self::assertStringNotContainsString('OperationResult', $source);
            self::assertStringNotContainsString('@implements', $source);
            self::assertStringNotContainsString('ExecutionContext', $source);
            self::assertStringNotContainsString('Route', $source);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidInputs(): iterable
    {
        yield 'absolute path' => ['/Welcome/ShowWelcome', 'welcome.show'];
        yield 'one segment' => ['ShowWelcome', 'welcome.show'];
        yield 'additional segment' => ['Welcome/Admin/ShowWelcome', 'welcome.show'];
        yield 'empty segment' => ['Welcome/', 'welcome.show'];
        yield 'traversal' => ['../ShowWelcome', 'welcome.show'];
        yield 'backslash' => ['Welcome\\ShowWelcome', 'welcome.show'];
        yield 'lowercase feature' => ['welcome/ShowWelcome', 'welcome.show'];
        yield 'reserved action' => ['Welcome/Class', 'welcome.show'];
        yield 'control character' => ["Welcome/Show\nWelcome", 'welcome.show'];
        yield 'uppercase type' => ['Welcome/ShowWelcome', 'Welcome.Show'];
        yield 'invalid type separator' => ['Welcome/ShowWelcome', 'welcome-show'];
    }

    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidPathAndTypeBeforeWriting(string $path, string $type): void
    {
        $directory = $this->directory();

        try {
            $this->generator($directory)->generate(OperationGeneratorInput::from($path, $type));
            self::fail('Expected invalid generator input.');
        } catch (InvalidArgumentException) {
            self::assertSame([], array_values(array_diff(scandir($directory) ?: [], ['.', '..'])));
        }
    }

    public function testExistingTargetPreventsEveryWriteAndRemainsUnchanged(): void
    {
        $directory = $this->directory();
        $target = $directory . '/app/Feature/Welcome/ShowWelcome';
        mkdir($target, recursive: true);
        file_put_contents($target . '/ShowWelcomeValue.php', 'existing-value');

        try {
            $this->generator($directory)->generate(OperationGeneratorInput::from(
                'Welcome/ShowWelcome',
                'welcome.show',
            ));
            self::fail('Expected target collision.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString(
                'app/Feature/Welcome/ShowWelcome/ShowWelcomeValue.php',
                $exception->getMessage(),
            );
            self::assertSame('existing-value', file_get_contents($target . '/ShowWelcomeValue.php'));
            self::assertFileDoesNotExist($target . '/ShowWelcome.php');
            self::assertFileDoesNotExist($target . '/ShowWelcomeOutcome.php');
        }
    }

    public function testTemporaryWriteFailureLeavesNoPartialFilesOrDirectories(): void
    {
        $directory = $this->directory();
        $writes = 0;
        $writer = new ProjectFileWriter(static function (string $path, string $contents) use (&$writes): int {
            ++$writes;
            if ($writes === 2) {
                file_put_contents($path, 'partial');

                return 0;
            }

            return file_put_contents($path, $contents);
        });

        try {
            $this->generator($directory, $writer)->generate(OperationGeneratorInput::from(
                'Welcome/ShowWelcome',
                'welcome.show',
            ));
            self::fail('Expected temporary write failure.');
        } catch (InvalidArgumentException) {
            self::assertDirectoryDoesNotExist($directory . '/app');
        }
    }

    public function testPublishFailureRollsBackAlreadyPublishedTargets(): void
    {
        $directory = $this->directory();
        $writer = new ProjectFileWriter(beforePublish: static function (
            string $_temporary,
            string $_target,
            int $index,
        ): void {
            if ($index === 1) {
                throw new RuntimeException('simulated publish failure');
            }
        });

        try {
            $this->generator($directory, $writer)->generate(OperationGeneratorInput::from(
                'Welcome/ShowWelcome',
                'welcome.show',
            ));
            self::fail('Expected publish failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Project file generation failed.', $exception->getMessage());
            self::assertDirectoryDoesNotExist($directory . '/app');
        }
    }

    public function testPublishRacePreservesCompetingTargetAndHidesFilesystemWarningPaths(): void
    {
        $directory = $this->directory();
        $target = $directory . '/app/Feature/Welcome/ShowWelcome';
        $warnings = [];
        $firstTargetWasPublished = false;
        $writer = new ProjectFileWriter(beforePublish: static function (
            string $_temporary,
            string $publishTarget,
            int $index,
        ) use (&$firstTargetWasPublished): void {
            if ($index === 1) {
                $firstTargetWasPublished = is_file(dirname($publishTarget) . '/ShowWelcome.php');
                file_put_contents($publishTarget, 'created-by-another-actor');
            }
        });
        set_error_handler(static function (int $_severity, string $message, string $_file, int $_line) use (
            &$warnings,
        ): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $this->generator($directory, $writer)->generate(OperationGeneratorInput::from(
                'Welcome/ShowWelcome',
                'welcome.show',
            ));
            self::fail('Expected publish race collision.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString(
                'app/Feature/Welcome/ShowWelcome/ShowWelcomeValue.php',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString($directory, $exception->getMessage());
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertTrue($firstTargetWasPublished);
        self::assertFileDoesNotExist($target . '/ShowWelcome.php');
        self::assertSame('created-by-another-actor', file_get_contents($target . '/ShowWelcomeValue.php'));
        self::assertFileDoesNotExist($target . '/ShowWelcomeOutcome.php');
        self::assertSame([], glob($target . '/.blackops-*.tmp') ?: []);
    }

    public function testMissingStubDoesNotExposeFrameworkAbsolutePath(): void
    {
        $directory = $this->directory();
        $missing = $directory . '/private/framework/stubs';

        try {
            new OperationGenerator($directory, $missing)->generate(OperationGeneratorInput::from(
                'Welcome/ShowWelcome',
                'welcome.show',
            ));
            self::fail('Expected missing stub failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Operation generator stub is unavailable.', $exception->getMessage());
            self::assertStringNotContainsString($missing, $exception->getMessage());
            self::assertDirectoryDoesNotExist($directory . '/app');
        }
    }

    public function testStubReadRaceHidesFrameworkAbsolutePathAndWarning(): void
    {
        $directory = $this->directory();
        $stubs = $directory . '/private/framework/stubs';
        mkdir($stubs, recursive: true);
        foreach (['operation.php.stub', 'operation-value.php.stub', 'operation-outcome.php.stub'] as $stub) {
            copy(dirname(__DIR__, levels: 3) . '/resources/stubs/' . $stub, $stubs . '/' . $stub);
        }
        $removed = false;
        $warnings = [];
        $generator = new OperationGenerator($directory, $stubs, beforeStubRead: static function (string $path) use (
            &$removed,
        ): void {
            if (!$removed) {
                unlink($path);
                $removed = true;
            }
        });
        set_error_handler(static function (int $_severity, string $message, string $_file, int $_line) use (
            &$warnings,
        ): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $generator->generate(OperationGeneratorInput::from('Welcome/ShowWelcome', 'welcome.show'));
            self::fail('Expected stub read race failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Operation generator stub is unavailable.', $exception->getMessage());
            self::assertStringNotContainsString($stubs, $exception->getMessage());
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertDirectoryDoesNotExist($directory . '/app');
    }

    public function testExistingAncestorSymlinkCannotEscapeApplicationRoot(): void
    {
        $directory = $this->directory();
        $outside = $this->directory();
        symlink($outside, $directory . '/app');

        try {
            $this->generator($directory)->generate(OperationGeneratorInput::from(
                'Welcome/ShowWelcome',
                'welcome.show',
            ));
            self::fail('Expected symlink escape rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('outside the application', $exception->getMessage());
            self::assertSame([], array_values(array_diff(scandir($outside) ?: [], ['.', '..'])));
            self::assertTrue(is_link($directory . '/app'));
        }
    }

    public function testExistingFileCannotBeUsedAsTargetDirectoryAncestor(): void
    {
        $directory = $this->directory();
        file_put_contents($directory . '/app', 'existing-app-file');

        try {
            $this->generator($directory)->generate(OperationGeneratorInput::from(
                'Welcome/ShowWelcome',
                'welcome.show',
            ));
            self::fail('Expected non-directory ancestor rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('ancestor must be a directory', $exception->getMessage());
            self::assertSame('existing-app-file', file_get_contents($directory . '/app'));
        }
    }

    private function generator(string $directory, ?ProjectFileWriter $writer = null): OperationGenerator
    {
        return new OperationGenerator(
            $directory,
            dirname(__DIR__, levels: 3) . '/resources/stubs',
            $writer ?? new ProjectFileWriter(),
        );
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/blackops-operation-generator-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->directories[] = $directory;

        return $directory;
    }

    private function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isLink() || $entry->isFile()) {
                unlink($entry->getPathname());
                continue;
            }

            rmdir($entry->getPathname());
        }
        rmdir($directory);
    }
}

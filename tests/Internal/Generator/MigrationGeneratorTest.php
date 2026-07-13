<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Generator;

use BlackOps\Internal\Generator\MigrationGenerator;
use BlackOps\Internal\Generator\MigrationGeneratorInput;
use BlackOps\Internal\Generator\ProjectFileWriter;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class MigrationGeneratorTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $this->remove($directory);
        }
    }

    public function testGeneratesUtcDoctrineMigrationWithStandardConstructor(): void
    {
        $directory = $this->directory();
        $path = $this->generator(
            $directory,
            new FixedMigrationGeneratorClock('2026-07-14T08:09:10+09:00'),
        )->generate(MigrationGeneratorInput::from('CreateOrdersTable'));

        self::assertSame('migrations/Version20260713230910.php', $path);
        $source = (string) file_get_contents($directory . '/' . $path);
        self::assertStringContainsString('namespace App\Migrations;', $source);
        self::assertStringContainsString('final class Version20260713230910 extends AbstractMigration', $source);
        self::assertStringContainsString("return 'CreateOrdersTable';", $source);
        self::assertStringContainsString('public function up(Schema $schema): void', $source);
        self::assertStringContainsString('public function down(Schema $schema): void', $source);
        self::assertStringNotContainsString('function __construct', $source);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDescriptions(): iterable
    {
        yield 'empty' => [''];
        yield 'lowercase' => ['createOrdersTable'];
        yield 'snake case' => ['Create_Orders'];
        yield 'space' => ['Create Orders'];
        yield 'reserved keyword' => ['Class'];
        yield 'control character' => ["Create\nOrders"];
    }

    #[DataProvider('invalidDescriptions')]
    public function testRejectsInvalidDescriptionWithoutCreatingMigrationDirectory(string $description): void
    {
        $directory = $this->directory();

        try {
            $this->generator($directory)->generate(MigrationGeneratorInput::from($description));
            self::fail('Expected invalid migration description.');
        } catch (InvalidArgumentException) {
            self::assertDirectoryDoesNotExist($directory . '/migrations');
        }
    }

    public function testVersionCollisionPreservesExistingFile(): void
    {
        $directory = $this->directory();
        $generator = $this->generator($directory, new FixedMigrationGeneratorClock('2026-07-13T12:00:00Z'));
        $path = $generator->generate(MigrationGeneratorInput::from('CreateOrdersTable'));
        $existing = (string) file_get_contents($directory . '/' . $path);

        try {
            $generator->generate(MigrationGeneratorInput::from('CreateInvoicesTable'));
            self::fail('Expected migration version collision.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString($path, $exception->getMessage());
            self::assertSame($existing, file_get_contents($directory . '/' . $path));
            self::assertCount(1, glob($directory . '/migrations/Version*.php') ?: []);
        }
    }

    public function testWriteFailureLeavesNoMigrationFileOrEmptyDirectory(): void
    {
        $directory = $this->directory();
        $writer = new ProjectFileWriter(static function (string $path, string $_contents): int {
            file_put_contents($path, 'partial');

            return 0;
        });

        try {
            $this->generator($directory, writer: $writer)->generate(MigrationGeneratorInput::from('CreateOrdersTable'));
            self::fail('Expected migration write failure.');
        } catch (InvalidArgumentException) {
            self::assertDirectoryDoesNotExist($directory . '/migrations');
        }
    }

    public function testMissingStubDoesNotExposeFrameworkPathOrCreateDirectory(): void
    {
        $directory = $this->directory();
        $missing = $directory . '/private/framework/stubs';

        try {
            new MigrationGenerator($directory, $missing)->generate(MigrationGeneratorInput::from('CreateOrdersTable'));
            self::fail('Expected missing migration stub.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Migration generator stub is unavailable.', $exception->getMessage());
            self::assertStringNotContainsString($missing, $exception->getMessage());
            self::assertDirectoryDoesNotExist($directory . '/migrations');
        }
    }

    public function testStubReadRaceHidesFrameworkPathAndWarning(): void
    {
        $directory = $this->directory();
        $stubs = $directory . '/private/framework/stubs';
        mkdir($stubs, recursive: true);
        copy(dirname(__DIR__, levels: 3) . '/resources/stubs/migration.php.stub', $stubs . '/migration.php.stub');
        $warnings = [];
        $generator = new MigrationGenerator($directory, $stubs, beforeStubRead: static function (string $path): void {
            unlink($path);
        });
        set_error_handler(static function (int $_severity, string $message, string $_file, int $_line) use (
            &$warnings,
        ): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $generator->generate(MigrationGeneratorInput::from('CreateOrdersTable'));
            self::fail('Expected migration stub read race failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Migration generator stub is unavailable.', $exception->getMessage());
            self::assertStringNotContainsString($stubs, $exception->getMessage());
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertDirectoryDoesNotExist($directory . '/migrations');
    }

    private function generator(
        string $directory,
        ?ClockInterface $clock = null,
        ?ProjectFileWriter $writer = null,
    ): MigrationGenerator {
        return new MigrationGenerator(
            $directory,
            dirname(__DIR__, levels: 3) . '/resources/stubs',
            $clock,
            $writer ?? new ProjectFileWriter(),
        );
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/blackops-migration-generator-' . bin2hex(random_bytes(8));
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
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}

final readonly class FixedMigrationGeneratorClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}

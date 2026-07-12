<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class QuickstartApplicationArchitectureTest extends TestCase
{
    public function testInstalledTreeAndGeneratedBoundaries(): void
    {
        $root = $this->quickstart();

        foreach ([
            'app/ApplicationOperationProvider.php',
            'app/ApplicationServiceProvider.php',
            'app/Feature/Welcome/ShowWelcome/ShowWelcome.php',
            'app/Feature/Welcome/ShowWelcome/WelcomeValue.php',
            'app/Feature/Welcome/ShowWelcome/ShowWelcomeHandler.php',
            'app/Feature/Welcome/ShowWelcome/WelcomeShown.php',
            'app/Feature/Report/GenerateReport/GenerateReport.php',
            'app/Feature/Report/GenerateReport/GenerateReportValue.php',
            'app/Feature/Report/GenerateReport/GenerateReportHandler.php',
            'app/Feature/Report/GenerateReport/ReportGenerated.php',
            'app/Feature/Report/GenerateReport/ReportGenerationTemporarilyUnavailable.php',
            'bin/blackops',
            'bootstrap/app.php',
            'config/app.php',
            'config/database.php',
            'config/execution.php',
            'config/journal.php',
            'config/operations.php',
            'config/retention.php',
            'tests/.gitignore',
            'var/build/.gitignore',
            'var/log/.gitignore',
            '.env.example',
            '.gitignore',
            'composer.json',
            'README.md',
        ] as $path) {
            self::assertFileExists($root . '/' . $path);
        }

        self::assertFileDoesNotExist($root . '/composer.lock');
        self::assertDirectoryDoesNotExist($root . '/vendor');
        self::assertTrue(is_executable($root . '/bin/blackops'));
        self::assertFileDoesNotExist($root . '/compose.yaml');
        self::assertFileDoesNotExist($root . '/Dockerfile');
        self::assertSame(['.gitignore'], $this->files($root . '/var/build'));
        self::assertSame(['.gitignore'], $this->files($root . '/var/log'));
    }

    public function testComposerMetadataIsIndependentAndRootDoesNotAutoloadApplication(): void
    {
        /** @var array<string, mixed> $composer */
        $composer = json_decode(
            (string) file_get_contents($this->quickstart() . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('blackops/skeleton', $composer['name']);
        self::assertSame('project', $composer['type']);
        self::assertSame(['App\\' => 'app/'], $composer['autoload']['psr-4']);
        self::assertSame(['App\\Tests\\' => 'tests/'], $composer['autoload-dev']['psr-4']);
        self::assertArrayNotHasKey('repositories', $composer);
        self::assertSame('>=8.5', $composer['require']['php']);
        foreach ([
            'blackops/framework',
            'vlucas/phpdotenv',
            'nyholm/psr7',
            'nyholm/psr7-server',
            'laminas/laminas-httphandlerrunner',
        ] as $dependency) {
            self::assertArrayHasKey($dependency, $composer['require']);
        }

        self::assertStringNotContainsString(
            'composer.lock',
            (string) file_get_contents($this->quickstart() . '/.gitignore'),
        );

        /** @var array<string, mixed> $rootComposer */
        $rootComposer = json_decode(
            (string) file_get_contents(dirname(__DIR__, levels: 2) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertArrayNotHasKey('App\\', $rootComposer['autoload']['psr-4']);
        self::assertArrayNotHasKey('App\\', $rootComposer['autoload-dev']['psr-4']);
    }

    public function testApplicationUsesNoInternalApiAndLaminasStaysInEmitterEntrypoint(): void
    {
        foreach ($this->phpFiles($this->quickstart()) as $path) {
            $source = (string) file_get_contents($path);
            self::assertStringNotContainsString('BlackOps\\Internal', $source, $path);

            if (str_contains($source, 'Laminas\\')) {
                self::assertSame($this->quickstart() . '/public/index.php', $path);
            }
        }
    }

    public function testFeaturesDoNotReferenceEachOther(): void
    {
        foreach ($this->phpFiles($this->quickstart() . '/app/Feature/Welcome') as $path) {
            self::assertStringNotContainsString('App\\Feature\\Report', (string) file_get_contents($path), $path);
        }

        foreach ($this->phpFiles($this->quickstart() . '/app/Feature/Report') as $path) {
            self::assertStringNotContainsString('App\\Feature\\Welcome', (string) file_get_contents($path), $path);
        }
    }

    private function quickstart(): string
    {
        return dirname(__DIR__, levels: 2) . '/examples/quickstart';
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        ));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /** @return list<string> */
    private function files(string $directory): array
    {
        $files = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
        sort($files);

        return $files;
    }
}

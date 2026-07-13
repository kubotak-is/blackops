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
            'app/Feature/Welcome/ShowWelcome/ShowWelcome.php',
            'app/Feature/Welcome/ShowWelcome/WelcomeValue.php',
            'app/Feature/Welcome/ShowWelcome/WelcomeShown.php',
            'app/Feature/Report/GenerateReport/GenerateReport.php',
            'app/Feature/Report/GenerateReport/GenerateReportValue.php',
            'app/Feature/Report/GenerateReport/ReportGenerated.php',
            'app/Feature/Report/GenerateReport/ReportGenerationTemporarilyUnavailable.php',
            'blackops',
            'bin/setup',
            'bootstrap/app.php',
            'config/app.php',
            'config/database.php',
            'config/execution.php',
            'config/journal.php',
            'config/operations.php',
            'config/retention.php',
            'public/worker.php',
            'tests/.gitignore',
            'var/build/.gitignore',
            'var/log/.gitignore',
            '.env.example',
            '.gitignore',
            'composer.json',
            'README.md',
            'Caddyfile',
            'Caddyfile.classic',
            'Dockerfile',
            'Dockerfile.frankenphp',
            'compose.yaml',
        ] as $path) {
            self::assertFileExists($root . '/' . $path);
        }

        self::assertFileDoesNotExist($root . '/composer.lock');
        self::assertDirectoryDoesNotExist($root . '/vendor');
        self::assertTrue(is_executable($root . '/blackops'));
        self::assertFileDoesNotExist($root . '/bin/' . 'blackops');
        self::assertTrue(is_executable($root . '/bin/setup'));
        self::assertFileDoesNotExist($root . '/app/ApplicationOperationProvider.php');
        self::assertFileDoesNotExist($root . '/app/ApplicationServiceProvider.php');
        self::assertFileDoesNotExist($root . '/app/Feature/Welcome/ShowWelcome/ShowWelcomeHandler.php');
        self::assertFileDoesNotExist($root . '/app/Feature/Report/GenerateReport/GenerateReportHandler.php');
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
        self::assertArrayNotHasKey('version', $composer);
        self::assertSame('@php bin/setup', $composer['scripts']['post-create-project-cmd']);
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
                self::assertContains($path, [
                    $this->quickstart() . '/public/index.php',
                    $this->quickstart() . '/public/worker.php',
                ]);
            }
        }
    }

    public function testProjectRootConsoleEntrypointUsesOnlyPublicApplicationApi(): void
    {
        $source = (string) file_get_contents($this->quickstart() . '/blackops');

        self::assertStringContainsString('use BlackOps\\Application\\Application;', $source);
        self::assertStringContainsString("require __DIR__ . '/vendor/autoload.php';", $source);
        self::assertStringContainsString("require __DIR__ . '/bootstrap/app.php';", $source);
        self::assertStringContainsString('exit($application->console()->run());', $source);
        self::assertStringNotContainsString('BlackOps\\Internal', $source);
        self::assertStringNotContainsString('Symfony\\Component\\Console', $source);
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

    public function testOperationsAreSelfHandledAndBootstrapUsesDiscovery(): void
    {
        $root = $this->quickstart();
        $welcome = (string) file_get_contents($root . '/app/Feature/Welcome/ShowWelcome/ShowWelcome.php');
        $report = (string) file_get_contents($root . '/app/Feature/Report/GenerateReport/GenerateReport.php');
        $bootstrap = (string) file_get_contents($root . '/bootstrap/app.php');
        $operations = (string) file_get_contents($root . '/config/operations.php');

        self::assertStringContainsString('implements Operation', $welcome);
        self::assertStringContainsString('handle(WelcomeValue $value)', $welcome);
        self::assertStringContainsString('implements Operation', $report);
        self::assertStringContainsString('handle(GenerateReportValue $value, ExecutionContext $context)', $report);
        self::assertStringNotContainsString('OperationHandler', $welcome . $report);
        self::assertStringNotContainsString('OperationEnvelope', $welcome . $report);
        self::assertStringNotContainsString('@implements', $welcome . $report);
        self::assertStringNotContainsString('instanceof WelcomeValue', $welcome);
        self::assertStringNotContainsString('instanceof GenerateReportValue', $report);
        self::assertStringNotContainsString('HandledBy', $welcome . $report);
        self::assertStringNotContainsString('withOperations', $bootstrap);
        self::assertStringNotContainsString('withServices', $bootstrap);
        self::assertStringContainsString("'discovery'", $operations);
        self::assertStringContainsString("'providers' => []", $operations);
    }

    public function testLocalRuntimeKeepsBackgroundProcessesExplicit(): void
    {
        $root = $this->quickstart();
        $compose = (string) file_get_contents($root . '/compose.yaml');
        $cli = (string) file_get_contents($root . '/Dockerfile');
        $http = (string) file_get_contents($root . '/Dockerfile.frankenphp');
        $caddy = (string) file_get_contents($root . '/Caddyfile');
        $classicCaddy = (string) file_get_contents($root . '/Caddyfile.classic');
        $readme = (string) file_get_contents($root . '/README.md');
        $journal = (string) file_get_contents($root . '/config/journal.php');

        self::assertStringContainsString('postgres:18', $compose);
        self::assertStringContainsString('pg_isready', $compose);
        self::assertStringContainsString('profiles: ["worker"]', $compose);
        self::assertStringContainsString('profiles: ["classic-mode"]', $compose);
        self::assertStringContainsString('profiles: ["maintenance"]', $compose);
        self::assertStringContainsString('./Caddyfile:/etc/frankenphp/Caddyfile:ro', $compose);
        self::assertStringContainsString('./Caddyfile.classic:/etc/frankenphp/Caddyfile:ro', $compose);
        self::assertStringContainsString('${HTTP_PORT:-8080}:80', $compose);
        self::assertStringContainsString('${CLASSIC_HTTP_PORT:-8081}:80', $compose);
        self::assertStringContainsString('${FRANKENPHP_MAX_REQUESTS:-1000}', $compose);
        self::assertStringNotContainsString('worker-mode', $compose);
        self::assertStringNotContainsString('http-worker', $compose);
        self::assertStringNotContainsString('Caddyfile.worker', $compose);
        self::assertStringContainsString('file /app/public/worker.php', $caddy);
        self::assertStringContainsString('max_requests {$FRANKENPHP_MAX_REQUESTS:1000}', $caddy);
        self::assertStringNotContainsString('worker {', $classicCaddy);
        self::assertStringContainsString('php_server', $classicCaddy);
        self::assertStringContainsString(
            "curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome",
            $readme,
        );
        self::assertStringContainsString(
            "curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8081/welcome",
            $readme,
        );
        self::assertStringContainsString('postgres_data:', $compose);
        self::assertStringContainsString('php:8.5-cli-bookworm', $cli);
        self::assertStringContainsString('docker-php-ext-install pcntl pdo_pgsql zip', $cli);
        self::assertStringContainsString('dunglas/frankenphp:1-php8.5-bookworm', $http);
        self::assertStringNotContainsString('composer install', $compose . $cli . $http);
        self::assertStringNotContainsString('database:migrate', $compose . $cli . $http);
        self::assertStringContainsString("'delivery' => 'best_effort'", $journal);
    }

    public function testOperationGeneratorStubsAreOwnedByTheFrameworkPackage(): void
    {
        $root = dirname(__DIR__, levels: 2);

        foreach ([
            'operation.php.stub',
            'operation-value.php.stub',
            'operation-outcome.php.stub',
        ] as $stub) {
            $path = $root . '/resources/stubs/' . $stub;
            self::assertFileExists($path);
            $source = (string) file_get_contents($path);
            self::assertStringNotContainsString('Accepts', $source);
            self::assertStringNotContainsString('Returns', $source);
            self::assertStringNotContainsString('OperationResult', $source);
            self::assertFileDoesNotExist($this->quickstart() . '/resources/stubs/' . $stub);
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

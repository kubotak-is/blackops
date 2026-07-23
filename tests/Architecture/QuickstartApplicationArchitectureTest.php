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
            'app/ApplicationServiceProvider.php',
            'app/Feature/Welcome/ShowWelcome/ShowWelcome.php',
            'app/Feature/Welcome/ShowWelcome/WelcomeValue.php',
            'app/Feature/Welcome/ShowWelcome/WelcomeShown.php',
            'app/Feature/Report/GenerateReport/GenerateReport.php',
            'app/Feature/Report/GenerateReport/GenerateReportValue.php',
            'app/Feature/Report/GenerateReport/ReportGenerated.php',
            'app/Feature/Report/GenerateReport/ReportGenerationTemporarilyUnavailable.php',
            'app/Feature/Diagnostics/TriggerFailure/TriggerFailure.php',
            'app/Feature/Diagnostics/TriggerFailure/TriggerFailureValue.php',
            'app/Feature/Diagnostics/TriggerFailure/FailureTriggered.php',
            'app/Infrastructure/Seed/DatabaseSeeder.php',
            'app/Security/SampleUserAuthorizationPolicy.php',
            'app/UserInterface/Http/SampleTokenAuthenticator.php',
            'blackops',
            'bin/setup',
            'bootstrap/app.php',
            'config/app.php',
            'config/database.php',
            'config/diagnostics.php',
            'config/execution.php',
            'config/journal.php',
            'config/logging.php',
            'config/middleware.php',
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
        self::assertFileDoesNotExist($root . '/app/Feature/Welcome/ShowWelcome/ShowWelcomeHandler.php');
        self::assertFileDoesNotExist($root . '/app/Feature/Report/GenerateReport/GenerateReportHandler.php');
        self::assertFileDoesNotExist($root . '/app/Feature/Diagnostics/TriggerFailure/TriggerFailureHandler.php');
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
        self::assertSame(['php', 'blackops/framework'], array_keys($composer['require']));

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

    public function testPublicHttpEntrypointsUseFrameworkRuntimeBoundary(): void
    {
        foreach ($this->phpFiles($this->quickstart()) as $path) {
            $source = (string) file_get_contents($path);
            self::assertStringNotContainsString('BlackOps\\Internal', $source, $path);

            if (str_contains($path, '/public/index.php') || str_contains($path, '/public/worker.php')) {
                self::assertStringNotContainsString('Nyholm\\', $source, $path);
                self::assertStringNotContainsString('Laminas\\', $source, $path);
                self::assertStringNotContainsString('frankenphp_handle_request', $source, $path);
            }
        }

        self::assertStringContainsString(
            'SapiRuntime::run($application);',
            (string) file_get_contents($this->quickstart() . '/public/index.php'),
        );
        self::assertStringContainsString(
            'SapiRuntime::runWorker($application);',
            (string) file_get_contents($this->quickstart() . '/public/worker.php'),
        );

        $community = dirname($this->quickstart()) . '/community-board/public';
        foreach ([
            'index.php' => 'SapiRuntime::run($application);',
            'worker.php' => 'SapiRuntime::runWorker($application);',
        ] as $file => $call) {
            $source = (string) file_get_contents($community . '/' . $file);
            self::assertStringContainsString($call, $source);
            self::assertStringNotContainsString('Nyholm\\', $source);
            self::assertStringNotContainsString('Laminas\\', $source);
            self::assertStringNotContainsString('frankenphp_handle_request', $source);
        }

        $identity = (string) file_get_contents(
            dirname($this->quickstart()) . '/community-board/app/Infrastructure/Identity/RandomIdentityIdentifier.php',
        );
        self::assertStringContainsString('BlackOps\\Identifier\\Uuidv7Generator', $identity);
        self::assertStringNotContainsString('Symfony\\Component\\Uid', $identity);

        $board = (string) file_get_contents(
            dirname($this->quickstart()) . '/community-board/app/Infrastructure/Identifier/Uuidv7BoardIdGenerator.php',
        );
        self::assertStringContainsString('BlackOps\\Identifier\\Uuidv7Generator', $board);
        self::assertStringNotContainsString('Symfony\\Component\\Uid', $board);
    }

    public function testQuickstartBootstrapUsesFrameworkEnvironmentFileCapability(): void
    {
        $source = (string) file_get_contents($this->quickstart() . '/bootstrap/app.php');

        self::assertStringContainsString('->withEnvironmentFile()', $source);
        self::assertStringNotContainsString('Dotenv\\', $source);
        self::assertStringNotContainsString('$_ENV', $source);

        $community = dirname($this->quickstart()) . '/community-board/bootstrap/app.php';
        $communitySource = (string) file_get_contents($community);
        self::assertStringContainsString('->withEnvironmentFile()', $communitySource);
        self::assertStringNotContainsString('Dotenv\\', $communitySource);
        self::assertStringNotContainsString('$_ENV', $communitySource);
    }

    public function testInstalledConfigurationUsesTypedEnvironmentClosuresWithoutGlobalReads(): void
    {
        foreach ([$this->quickstart(), dirname($this->quickstart()) . '/community-board'] as $application) {
            foreach ($this->phpFiles($application . '/config') as $path) {
                $source = (string) file_get_contents($path);

                self::assertStringNotContainsString('$_ENV', $source, $path);
                self::assertStringNotContainsString('$_SERVER', $source, $path);
                self::assertStringNotContainsString('getenv(', $source, $path);
            }

            foreach (['app', 'database', 'execution', 'retention'] as $name) {
                $source = (string) file_get_contents($application . '/config/' . $name . '.php');

                self::assertStringContainsString('use BlackOps\Application\Environment;', $source);
                self::assertStringContainsString('static fn(Environment $env): array', $source);
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
        $failure = (string) file_get_contents($root . '/app/Feature/Diagnostics/TriggerFailure/TriggerFailure.php');
        $bootstrap = (string) file_get_contents($root . '/bootstrap/app.php');
        $operations = (string) file_get_contents($root . '/config/operations.php');
        $application = (string) file_get_contents($root . '/config/app.php');
        $middleware = (string) file_get_contents($root . '/config/middleware.php');
        $welcomeValue = (string) file_get_contents($root . '/app/Feature/Welcome/ShowWelcome/WelcomeValue.php');
        $reportValue = (string) file_get_contents($root . '/app/Feature/Report/GenerateReport/GenerateReportValue.php');
        $failureValue = (string) file_get_contents($root
        . '/app/Feature/Diagnostics/TriggerFailure/TriggerFailureValue.php');

        self::assertStringContainsString('implements Operation', $welcome);
        self::assertStringContainsString('handle(WelcomeValue $value)', $welcome);
        self::assertStringContainsString('implements Operation', $report);
        self::assertStringContainsString('handle(GenerateReportValue $value, ExecutionContext $context)', $report);
        self::assertStringContainsString('handle(TriggerFailureValue $value): FailureTriggered', $failure);
        self::assertStringContainsString('#[Authorize(SampleUserAuthorizationPolicy::class)]', $welcome);
        self::assertStringContainsString('#[Authorize(SampleUserAuthorizationPolicy::class)]', $report);
        self::assertStringContainsString('#[Authorize(SampleUserAuthorizationPolicy::class)]', $failure);
        self::assertStringContainsString("#[Route(method: 'POST', path: '/failures')]", $failure);
        self::assertStringContainsString("#[OperationType('diagnostics.failure.trigger')]", $failure);
        self::assertStringContainsString('private LoggerInterface $logger', $failure);
        self::assertStringContainsString("'reference' => \$value->reference", $failure);
        self::assertStringNotContainsString('sensitiveNote', $failure);
        self::assertStringNotContainsString('OperationHandler', $welcome . $report . $failure);
        self::assertStringNotContainsString('OperationEnvelope', $welcome . $report . $failure);
        self::assertStringNotContainsString('@implements', $welcome . $report . $failure);
        self::assertStringNotContainsString('instanceof WelcomeValue', $welcome);
        self::assertStringNotContainsString('instanceof GenerateReportValue', $report);
        self::assertStringNotContainsString('instanceof TriggerFailureValue', $failure);
        self::assertStringNotContainsString('HandledBy', $welcome . $report . $failure);
        self::assertStringNotContainsString('withOperations', $bootstrap);
        self::assertStringNotContainsString('withServices', $bootstrap);
        self::assertStringContainsString("'discovery'", $operations);
        self::assertStringContainsString("'providers' => []", $operations);
        self::assertStringContainsString('ApplicationServiceProvider::class', $application);
        self::assertStringContainsString(
            "'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php'",
            $application,
        );
        self::assertStringContainsString('AuthenticationMiddleware::class', $middleware);
        self::assertStringNotContainsString('sampleToken', $welcomeValue . $reportValue);
        self::assertStringNotContainsString('apiToken', $welcomeValue . $reportValue);
        self::assertStringContainsString('public string $recipientEmail', $reportValue);
        self::assertStringContainsString('#[Sensitive(SensitiveMode::Mask)]', $reportValue);
        self::assertStringContainsString('public string $reference', $failureValue);
        self::assertStringContainsString('public string $sensitiveNote', $failureValue);
        self::assertStringContainsString('#[Sensitive(SensitiveMode::Mask)]', $failureValue);
    }

    public function testLoggingUsesAnAbsoluteApplicationOwnedJsonlPath(): void
    {
        /** @var array{backend: array{driver: string, stream: string, channel: string, minimum_level: string}} $logging */
        $logging = require $this->quickstart() . '/config/logging.php';

        self::assertSame('jsonl', $logging['backend']['driver']);
        self::assertSame($this->quickstart() . '/var/log/application.jsonl', $logging['backend']['stream']);
        self::assertSame('blackops', $logging['backend']['channel']);
        self::assertSame('info', $logging['backend']['minimum_level']);
    }

    public function testAuthenticationSnapshotsExpectedTokenAndKeepsCredentialOutsideValues(): void
    {
        $root = $this->quickstart();
        $authenticator = (string) file_get_contents($root . '/app/UserInterface/Http/SampleTokenAuthenticator.php');
        $provider = (string) file_get_contents($root . '/app/ApplicationServiceProvider.php');
        $policy = (string) file_get_contents($root . '/app/Security/SampleUserAuthorizationPolicy.php');
        $environment = (string) file_get_contents($root . '/.env.example');

        self::assertStringContainsString('implements HttpAuthenticator', $authenticator);
        self::assertSame(1, substr_count($authenticator, "\$_ENV['SAMPLE_API_TOKEN']"));
        self::assertStringNotContainsString("?? 'local-example'", $authenticator);
        self::assertStringContainsString('SAMPLE_API_TOKEN must be configured with a non-empty value.', $authenticator);
        self::assertStringContainsString("getHeaderLine('X-Sample-Token')", $authenticator);
        self::assertStringContainsString('hash_equals($this->expectedToken, $token)', $authenticator);
        self::assertStringContainsString(
            "AuthenticationResult::invalid('authentication.invalid_sample_token')",
            $authenticator,
        );
        self::assertStringContainsString("new ActorRef('quickstart-user', 'user')", $authenticator);
        self::assertStringContainsString('HttpAuthenticator::class, SampleTokenAuthenticator::class', $provider);
        self::assertStringContainsString('implements AuthorizationPolicy', $policy);
        self::assertStringContainsString(
            "AuthorizationDecision::forbid('authorization.sample_user_required')",
            $policy,
        );
        self::assertStringContainsString('SAMPLE_API_TOKEN=local-example', $environment);
    }

    public function testSampleAuthenticatorFailsClosedWithoutANonEmptyExpectedToken(): void
    {
        require_once $this->quickstart() . '/app/UserInterface/Http/SampleTokenAuthenticator.php';

        $wasDefined = array_key_exists('SAMPLE_API_TOKEN', $_ENV);
        $previous = $_ENV['SAMPLE_API_TOKEN'] ?? null;

        try {
            foreach ([null, '', '   '] as $configured) {
                if ($configured === null) {
                    unset($_ENV['SAMPLE_API_TOKEN']);
                } else {
                    $_ENV['SAMPLE_API_TOKEN'] = $configured;
                }

                try {
                    new \App\UserInterface\Http\SampleTokenAuthenticator();
                    self::fail('Expected an unset or empty sample token configuration to fail closed.');
                } catch (\RuntimeException $exception) {
                    self::assertSame(
                        'SAMPLE_API_TOKEN must be configured with a non-empty value.',
                        $exception->getMessage(),
                    );
                }
            }
        } finally {
            if ($wasDefined && is_string($previous)) {
                $_ENV['SAMPLE_API_TOKEN'] = $previous;
            } else {
                unset($_ENV['SAMPLE_API_TOKEN']);
            }
        }
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

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Aop\FrameworkProxyCompatibility;

foreach ([
    'CompatibilityDependency.php',
    'CompatibilityOperationValue.php',
    'CompatibilityOperationOutcome.php',
    'CompatibilityOperation.php',
    'CompatibilityService.php',
    'SignatureMatrixContracts.php',
    'SignatureMatrixService.php',
    'ReadonlySignatureService.php',
    'InheritedSignatureParent.php',
    'InheritedSignatureService.php',
    'NeverSignatureService.php',
] as $fixture) {
    require_once __DIR__ . '/../../../Fixtures/Aop/FrameworkProxyCompatibility/' . $fixture;
}

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityDependency;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityOperation;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityOperationValue;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\InheritedSignatureService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\NeverSignatureService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\ReadonlySignatureService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\SignatureMatrixService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class FrameworkProxyCompatibilityTest extends TestCase
{
    #[DataProvider('profiles')]
    public function testProfilesRunTheSameTransactionAndAfterCommitMatrix(string $profile): void
    {
        $root = sys_get_temp_dir() . '/blackops-compatibility-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        $configuration = new ApplicationConfigurationSnapshot(
            dirname(__DIR__, 4),
            [
                'app' => ['build' => [
                    'operation_manifest' => $root . '/operation.php',
                    'http_manifest' => $root . '/http.php',
                    'frontend_manifest' => $root . '/frontend.php',
                    'container' => $root . '/container.php',
                    'container_class' => 'CompatibilityContainer' . bin2hex(random_bytes(4)),
                    'container_namespace' => __NAMESPACE__ . '\\Generated',
                    'application_build_id' => 'compatibility-' . $profile,
                ]],
                'database' => [
                    'default' => 'app',
                    'connections' => ['app' => ['driver' => 'pdo_pgsql']],
                    'framework' => ['connection' => 'app', 'schema' => 'blackops'],
                ],
            ],
            [CompatibilityOperationProvider::class],
            [CompatibilityServiceProvider::class, SignatureMatrixServiceProvider::class],
            [],
        );
        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($configuration))->execute([
            '--proxy-profile' => $profile,
        ]));
        self::assertSame(
            CompatibilityOperation::class,
            new OperationManifestFile()
                ->load($root . '/operation.php')
                ->findByTypeId('compatibility.operation')
                ?->definition,
        );
        $containerClass =
            __NAMESPACE__ . '\\Generated\\' . $configuration->configuration()['app']['build']['container_class'];
        $runtime = $this->runCompiledContainer($root . '/container.php', $containerClass, 'compatibility');
        self::assertTrue($runtime['same']);
        self::assertTrue($runtime['dependency']);
        self::assertSame('typed1,2,3', $runtime['typed']);
        self::assertSame('ok', $runtime['value']);
        self::assertSame(['value:ok', 'nested', 'record:queued:ok'], $runtime['events_before_queue']);
        self::assertSame(
            ['value:ok', 'nested', 'record:queued:ok', 'record:first', 'record:last', 'value:rollback', 'nested'],
            $runtime['events'],
        );
        self::assertStringNotContainsString('discard-first', implode(',', $runtime['events']));
        self::assertSame([0, 0], $runtime['direct_delta']);
        self::assertSame([1, 1], $runtime['outer_delta']);
        self::assertSame(2, $runtime['operation_calls']);
        self::assertSame(['compatibility queue rollback', 'compatibility rollback'], $runtime['failure']);
        self::assertSame('value', $runtime['signature']['union']);
        self::assertTrue($runtime['signature']['intersection']);
        self::assertSame('positional1,2,3', $runtime['signature']['variadic_positional']);
        self::assertTrue($runtime['signature']['parent']);
        self::assertNull($runtime['signature']['dnf']);
        self::assertNull($runtime['signature']['nullable']);
        self::assertSame(['mixed'], $runtime['signature']['mixed']);
        self::assertTrue($runtime['signature']['static']);
        self::assertTrue($runtime['signature']['self']);
        self::assertTrue($runtime['signature']['defaults']);
        self::assertSame('default', $runtime['signature']['unrelated']);
        self::assertTrue($runtime['signature']['class_attribute']);
        self::assertTrue($runtime['signature']['method_attribute']);
        self::assertTrue($runtime['signature']['parameter_attribute']);
        self::assertTrue($runtime['readonly']);
        self::assertTrue($runtime['inherited']);
    }

    public function testFrameworkNeverSignatureRunsInFreshRuntime(): void
    {
        $root = sys_get_temp_dir() . '/blackops-compatibility-never-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        $configuration = new ApplicationConfigurationSnapshot(
            dirname(__DIR__, 4),
            [
                'app' => ['build' => [
                    'operation_manifest' => $root . '/operation.php',
                    'http_manifest' => $root . '/http.php',
                    'frontend_manifest' => $root . '/frontend.php',
                    'container' => $root . '/container.php',
                    'container_class' => 'NeverCompatibilityContainer' . bin2hex(random_bytes(4)),
                    'container_namespace' => __NAMESPACE__ . '\\NeverGenerated',
                    'application_build_id' => 'compatibility-never-framework',
                ]],
                'database' => [
                    'default' => 'app',
                    'connections' => ['app' => ['driver' => 'pdo_pgsql']],
                    'framework' => ['connection' => 'app', 'schema' => 'blackops'],
                ],
            ],
            [],
            [NeverSignatureServiceProvider::class],
            [],
        );
        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($configuration))->execute([
            '--proxy-profile' => FrameworkProxyProfile::FRAMEWORK,
        ]));
        $build = $configuration->configuration()['app']['build'];
        $class = __NAMESPACE__ . '\\NeverGenerated\\' . $build['container_class'];
        $runtime = $this->runCompiledContainer((string) $build['container'], $class, 'never');
        self::assertTrue($runtime['never']);
        self::assertNotSame('', $runtime['message']);
    }

    public function testRayNeverFailureIsBoundedAndDoesNotExposeGeneratedSource(): void
    {
        $root = sys_get_temp_dir() . '/blackops-compatibility-ray-never-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        try {
            $process = proc_open(
                [PHP_BINARY, __DIR__ . '/ray-never-build-runner.php', $root],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            self::assertIsResource($process);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $started = microtime(true);
            $stdout = '';
            $stderr = '';
            while (true) {
                $status = proc_get_status($process);
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                if (!$status['running']) {
                    break;
                }
                if ((microtime(true) - $started) > 15.0) {
                    proc_terminate($process);
                    self::fail('Ray never failure runner timed out');
                }
                usleep(10_000);
            }
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            if ($exit === -1 && isset($status['exitcode'])) {
                $exit = (int) $status['exitcode'];
            }
            self::assertNotSame(0, $exit, trim($stderr . "\n" . $stdout));
            $diagnostic = $stderr . "\n" . $stdout;
            self::assertStringContainsString('A never-returning method must not return', $diagnostic);
            self::assertStringNotContainsString('function neverReturns', $diagnostic);
            self::assertStringNotContainsString('signature matrix never', $diagnostic);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testFrameworkNamedVariadicValuesArePreservedInFreshRuntime(): void
    {
        $root = sys_get_temp_dir() . '/blackops-compatibility-framework-named-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        try {
            $configuration = new ApplicationConfigurationSnapshot(
                dirname(__DIR__, 4),
                [
                    'app' => ['build' => [
                        'operation_manifest' => $root . '/operation.php',
                        'http_manifest' => $root . '/http.php',
                        'frontend_manifest' => $root . '/frontend.php',
                        'container' => $root . '/container.php',
                        'container_class' => 'FrameworkNamedContainer' . bin2hex(random_bytes(4)),
                        'container_namespace' => __NAMESPACE__ . '\\NamedGenerated',
                        'application_build_id' => 'compatibility-framework-named',
                    ]],
                    'database' => [
                        'default' => 'app',
                        'connections' => ['app' => ['driver' => 'pdo_pgsql']],
                        'framework' => ['connection' => 'app', 'schema' => 'blackops'],
                    ],
                ],
                [],
                [SignatureMatrixServiceProvider::class],
                [],
            );
            self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($configuration))->execute([
                '--proxy-profile' => FrameworkProxyProfile::FRAMEWORK,
            ]));
            $class =
                __NAMESPACE__
                . '\\NamedGenerated\\'
                . $configuration->configuration()['app']['build']['container_class'];
            $runtime = $this->runCompiledContainer($root . '/container.php', $class, 'signature');
            self::assertSame('named4', $runtime['named']);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testRayNamedVariadicCompatibilityExceptionIsBounded(): void
    {
        $root = sys_get_temp_dir() . '/blackops-compatibility-ray-named-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        try {
            $process = proc_open(
                [PHP_BINARY, __DIR__ . '/ray-named-build-runner.php', $root],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            self::assertIsResource($process);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $stdout = '';
            $stderr = '';
            $started = microtime(true);
            while (true) {
                $status = proc_get_status($process);
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                if (!$status['running']) {
                    break;
                }
                if ((microtime(true) - $started) > 15.0) {
                    proc_terminate($process);
                    $stdout .= (string) stream_get_contents($pipes[1]);
                    $stderr .= (string) stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    self::fail('Ray named variadic runner timed out');
                }
                usleep(10_000);
            }
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            self::assertSame(0, $exit, trim($stderr . "\n" . $stdout));
            $result = json_decode(trim($stdout), true);
            self::assertIsArray($result, $stderr . "\n" . $stdout);
            self::assertSame('named', $result['named']);
            self::assertStringNotContainsString('function variadic', $stdout . $stderr);
            self::assertStringNotContainsString('signature matrix never', $stdout . $stderr);
        } finally {
            $this->removeDirectory($root);
        }
    }

    /** @return array<string,array{string}> */
    public static function profiles(): array
    {
        return ['ray' => [FrameworkProxyProfile::RAY], 'framework' => [FrameworkProxyProfile::FRAMEWORK]];
    }

    /** @return array<string,mixed> */
    private function runCompiledContainer(string $path, string $class, string $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/runtime-runner.php', $path, $class, $scenario],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $started = microtime(true);
        $stdout = '';
        $stderr = '';
        while (true) {
            $status = proc_get_status($process);
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            if (!$status['running']) {
                break;
            }
            if ((microtime(true) - $started) > 15.0) {
                proc_terminate($process);
                self::fail('compiled container runner timed out');
            }
            usleep(10_000);
        }
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit === -1 && isset($status['exitcode'])) {
            $exit = (int) $status['exitcode'];
        }
        self::assertSame(0, $exit, trim($stderr . "\n" . $stdout));
        $decoded = json_decode(trim($stdout), true);
        self::assertIsArray($decoded, $stderr . "\n" . $stdout);
        return $decoded;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}

final readonly class CompatibilityOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [CompatibilityOperation::class];
    }
}

final readonly class CompatibilityServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(CompatibilityDependency::class);
        $services->autowire(CompatibilityService::class);
        $services->autowire(CompatibilityOperation::class);
    }
}

final readonly class SignatureMatrixServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(SignatureMatrixService::class);
        $services->autowire(ReadonlySignatureService::class);
        $services->autowire(InheritedSignatureService::class);
    }
}

final readonly class NeverSignatureServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(NeverSignatureService::class);
    }
}

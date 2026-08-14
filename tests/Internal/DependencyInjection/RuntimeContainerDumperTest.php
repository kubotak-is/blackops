<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\DependencyInjection;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerator;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Runtime\FrameworkProxyProfileLoader;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RuntimeContainerDumperTest extends TestCase
{
    public function testFrameworkDumpEmitsManifestAwareLoaderAndRejectsMixedInputs(): void
    {
        require_once __DIR__ . '/../../Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityService.php';
        $root = sys_get_temp_dir() . '/blackops-framework-dumper-' . bin2hex(random_bytes(5));
        mkdir($root . '/framework-proxies', 0o755, true);
        $generation = new FrameworkProxyGenerator()->generate(
            CompatibilityService::class,
            'dumper-build',
            $root . '/framework-proxies',
            FrameworkProxyProfile::FRAMEWORK,
        );
        $builder = new RuntimeContainerCompiler()->builder();
        new RuntimeContainerCompiler()->compile($builder);
        $path = $root . '/container.php';
        new RuntimeContainerDumper()->dump(
            $builder,
            $path,
            $this->className(),
            __NAMESPACE__ . '\\Generated',
            FrameworkProxyProfile::FRAMEWORK,
            $generation->manifest,
            $generation->directory,
        );
        $source = (string) file_get_contents($path);
        self::assertStringContainsString('FrameworkProxyProfileLoader', $source);
        self::assertStringContainsString($generation->manifest->manifestHash, $source);
    }

    public function testFrameworkLoaderRejectsBuildAndManifestMismatch(): void
    {
        require_once __DIR__ . '/../../Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityService.php';
        $root = sys_get_temp_dir() . '/blackops-framework-loader-' . bin2hex(random_bytes(5));
        mkdir($root . '/framework-proxies', 0o755, true);
        $generation = new FrameworkProxyGenerator()->generate(
            CompatibilityService::class,
            'dumper-build-mismatch',
            $root . '/framework-proxies',
        );
        $loader = new FrameworkProxyProfileLoader();
        $this->expectExceptionMessage('BO_PROXY_ARTIFACT_BUILD_MISMATCH');
        $loader->loadFramework($generation->directory, 'different-build', $generation->manifest->manifestHash);
    }

    public function testFrameworkProfileRejectsManifestOnlyAndDirectoryOnlyInputs(): void
    {
        require_once __DIR__ . '/../../Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityService.php';
        $root = sys_get_temp_dir() . '/blackops-framework-dumper-' . bin2hex(random_bytes(5));
        mkdir($root . '/framework-proxies', 0o755, true);
        $generation = new FrameworkProxyGenerator()->generate(
            CompatibilityService::class,
            'dumper-build-pairs',
            $root . '/framework-proxies',
        );
        $builder = new RuntimeContainerCompiler()->builder();
        new RuntimeContainerCompiler()->compile($builder);
        $dumper = new RuntimeContainerDumper();
        try {
            $dumper->dump(
                $builder,
                $root . '/manifest-only.php',
                $this->className(),
                '',
                FrameworkProxyProfile::FRAMEWORK,
                $generation->manifest,
                null,
            );
            self::fail('Expected Framework manifest-only input rejection.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        $this->expectException(\InvalidArgumentException::class);
        $dumper->dump(
            $builder,
            $root . '/directory-only.php',
            $this->className(),
            '',
            FrameworkProxyProfile::FRAMEWORK,
            null,
            $generation->directory,
        );
    }

    public function testFrameworkLoaderRejectsWrongManifestHash(): void
    {
        require_once __DIR__ . '/../../Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityService.php';
        $root = sys_get_temp_dir() . '/blackops-framework-loader-' . bin2hex(random_bytes(5));
        mkdir($root . '/framework-proxies', 0o755, true);
        $generation = new FrameworkProxyGenerator()->generate(
            CompatibilityService::class,
            'dumper-build-hash',
            $root . '/framework-proxies',
        );
        $this->expectExceptionMessage('BO_PROXY_ARTIFACT_MANIFEST_HASH');
        new FrameworkProxyProfileLoader()->loadFramework(
            $generation->directory,
            'dumper-build-hash',
            str_repeat('0', 64),
        );
    }

    public function testDumpsCompiledContainerToPhpFile(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $this->compiledBuilder($compiler);
        $path = $this->dumpPath();
        $class = $this->className();

        new RuntimeContainerDumper()->dump($builder, $path, $class, __NAMESPACE__ . '\\Generated');

        self::assertFileExists($path);
        self::assertStringContainsString('class ' . $class, (string) file_get_contents($path));
    }

    public function testDumpedContainerResolvesHandlerThroughPsrContainer(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $this->compiledBuilder($compiler);
        $path = $this->dumpPath();
        $class = $this->className();
        $namespace = __NAMESPACE__ . '\\Generated';

        new RuntimeContainerDumper()->dump($builder, $path, $class, $namespace);
        require_once $path;

        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();

        self::assertInstanceOf(ContainerInterface::class, $container);

        $handler = new HandlerResolver($container)->resolve(DumpedContainerHandler::class);

        self::assertInstanceOf(DumpedContainerHandler::class, $handler);
        self::assertSame('dumped-ready', $handler->dependency->value);
    }

    private function compiledBuilder(RuntimeContainerCompiler $compiler): ContainerBuilder
    {
        $builder = $compiler->builder();
        $builder->register(DumpedContainerDependency::class)->setAutowired(true)->setPublic(true);
        $builder->register(DumpedContainerHandler::class)->setAutowired(true)->setPublic(true);
        $compiler->compile($builder);

        return $builder;
    }

    private function dumpPath(): string
    {
        return sys_get_temp_dir() . '/blackops-runtime-container-' . bin2hex(random_bytes(8)) . '.php';
    }

    private function className(): string
    {
        return 'DumpedContainer' . bin2hex(random_bytes(8));
    }
}

final readonly class DumpedContainerDependency
{
    public function __construct(
        public string $value = 'dumped-ready',
    ) {}
}

final readonly class DumpedContainerHandler implements OperationHandler
{
    public function __construct(
        public DumpedContainerDependency $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed(new EmptyOutcome());
    }
}

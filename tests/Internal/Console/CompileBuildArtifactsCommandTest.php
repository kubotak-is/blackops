<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Http\Attribute\Route;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\Console\CompileBuildArtifactsCommand;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Registry\OperationManifestFile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class CompileBuildArtifactsCommandTest extends TestCase
{
    public function testCompilesRequiredConstructorSelfHandledOperationAndRegistersHandler(): void
    {
        $operationProviders = $this->path('required-operation-providers');
        $serviceProviders = $this->path('required-service-providers');
        $operationManifest = $this->path('required-operation-manifest');
        $httpManifest = $this->path('required-http-manifest');
        $containerPath = $this->path('required-container');
        $class = 'RequiredBuildContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        file_put_contents(
            $operationProviders,
            '<?php return [\\' . RequiredBuildOperationProvider::class . '::class];',
        );
        file_put_contents($serviceProviders, '<?php return [\\' . RequiredBuildServiceProvider::class . '::class];');

        $status = new CommandTester(new CompileBuildArtifactsCommand())->execute([
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $operationManifest,
            'http-manifest' => $httpManifest,
            'frontend-manifest' => $this->path('required-frontend-manifest'),
            'container' => $containerPath,
            '--application-build-id' => 'build-required-self-handled',
            '--container-class' => $class,
            '--container-namespace' => $namespace,
        ]);
        require_once $containerPath;
        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();
        $metadata = new OperationManifestFile()
            ->load($operationManifest)
            ->findByTypeId('build.required');

        self::assertSame(0, $status);
        self::assertSame(RequiredBuildOperation::class, $metadata?->handler);
        self::assertInstanceOf(RequiredBuildOperation::class, $container->get(RequiredBuildOperation::class));
        self::assertSame('required-ready', $container->get(RequiredBuildOperation::class)->dependency->value);
    }

    public function testCompilesBuildArtifactsFromProviderConfigs(): void
    {
        $operationProviders = $this->path('operation-providers');
        $serviceProviders = $this->path('service-providers');
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $frontendManifest = $this->path('frontend-manifest');
        $containerPath = $this->path('container');
        $fingerprint = $this->path('fingerprint');
        $class = 'BuildContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        file_put_contents($operationProviders, '<?php return [\\' . BuildOperationProvider::class . '::class];');
        file_put_contents($serviceProviders, '<?php return [\\' . BuildServiceProvider::class . '::class];');

        $status = new CommandTester(new CompileBuildArtifactsCommand())->execute([
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $operationManifest,
            'http-manifest' => $httpManifest,
            'frontend-manifest' => $frontendManifest,
            'container' => $containerPath,
            '--application-build-id' => 'build-artifacts-123',
            '--container-class' => $class,
            '--container-namespace' => $namespace,
            '--lock' => $this->path('build-lock'),
            '--fingerprint' => $fingerprint,
        ]);

        $operationArtifact = new OperationManifestFile()->loadArtifact($operationManifest);
        $httpArtifact = new HttpOperationManifestFile()->loadArtifact($httpManifest);
        $frontendArtifact = new FrontendContractManifestFile()->loadArtifact($frontendManifest);
        $httpMatch = $httpArtifact->manifest->toRegistry([new BuildOperation()])->match('GET', '/build');
        require_once $containerPath;
        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();

        self::assertSame(0, $status);
        self::assertSame('build-artifacts-123', $operationArtifact->applicationBuildId);
        self::assertSame(2, $httpArtifact->schemaVersion);
        self::assertSame($operationArtifact->applicationBuildId, $httpArtifact->applicationBuildId);
        self::assertSame($operationArtifact->applicationBuildId, $frontendArtifact->applicationBuildId);
        self::assertSame('build.operation', $httpArtifact->manifest->dispatcherData[0]['GET']['/build']);
        self::assertSame(
            BuildOperation::class,
            $operationArtifact->operations->findByTypeId('build.operation')?->definition,
        );
        self::assertNotNull($httpMatch);
        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertInstanceOf(BuildService::class, $container->get(BuildService::class));
        self::assertInstanceOf(BuildAuthorizationPolicy::class, $container->get(BuildAuthorizationPolicy::class));
        self::assertInstanceOf(BuildService::class, $container->get(BuildAuthorizationPolicy::class)->service);
        self::assertFileExists($fingerprint);
    }

    public function testSkipsBuildArtifactsWhenFingerprintMatchesAndOutputsExist(): void
    {
        $operationProviders = $this->path('operation-providers');
        $serviceProviders = $this->path('service-providers');
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $containerPath = $this->path('container');
        $fingerprint = $this->path('fingerprint');
        file_put_contents($operationProviders, '<?php return [\\' . BuildOperationProvider::class . '::class];');
        file_put_contents($serviceProviders, '<?php return [\\' . BuildServiceProvider::class . '::class];');
        $arguments = [
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $operationManifest,
            'http-manifest' => $httpManifest,
            'frontend-manifest' => $this->path('fingerprint-frontend-manifest'),
            'container' => $containerPath,
            '--application-build-id' => 'build-fingerprint-123',
            '--fingerprint' => $fingerprint,
        ];

        new CommandTester(new CompileBuildArtifactsCommand())->execute($arguments);
        $operationManifestTime = filemtime($operationManifest);
        new CommandTester(new CompileBuildArtifactsCommand())->execute($arguments);

        self::assertSame($operationManifestTime, filemtime($operationManifest));
    }

    public function testRecompilesFreshFingerprintWhenRequestedBuildIdChanges(): void
    {
        $operationProviders = $this->path('operation-providers');
        $serviceProviders = $this->path('service-providers');
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $containerPath = $this->path('container');
        $fingerprint = $this->path('fingerprint');
        file_put_contents($operationProviders, '<?php return [\\' . BuildOperationProvider::class . '::class];');
        file_put_contents($serviceProviders, '<?php return [\\' . BuildServiceProvider::class . '::class];');
        $arguments = [
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $operationManifest,
            'http-manifest' => $httpManifest,
            'frontend-manifest' => $this->path('build-id-frontend-manifest'),
            'container' => $containerPath,
            '--application-build-id' => 'build-fingerprint-first',
            '--fingerprint' => $fingerprint,
        ];

        new CommandTester(new CompileBuildArtifactsCommand())->execute($arguments);
        $arguments['--application-build-id'] = 'build-fingerprint-second';
        new CommandTester(new CompileBuildArtifactsCommand())->execute($arguments);

        self::assertSame(
            'build-fingerprint-second',
            new OperationManifestFile()->loadArtifact($operationManifest)->applicationBuildId,
        );
        self::assertSame(
            'build-fingerprint-second',
            new HttpOperationManifestFile()->loadArtifact($httpManifest)->applicationBuildId,
        );
    }

    public function testRecompilesFreshFingerprintWhenExistingManifestBuildIdsDiffer(): void
    {
        $operationProviders = $this->path('operation-providers');
        $serviceProviders = $this->path('service-providers');
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $containerPath = $this->path('container');
        $fingerprint = $this->path('fingerprint');
        file_put_contents($operationProviders, '<?php return [\\' . BuildOperationProvider::class . '::class];');
        file_put_contents($serviceProviders, '<?php return [\\' . BuildServiceProvider::class . '::class];');
        $arguments = [
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $operationManifest,
            'http-manifest' => $httpManifest,
            'frontend-manifest' => $this->path('mismatch-frontend-manifest'),
            'container' => $containerPath,
            '--application-build-id' => 'build-fingerprint-match',
            '--fingerprint' => $fingerprint,
        ];

        new CommandTester(new CompileBuildArtifactsCommand())->execute($arguments);
        $httpFiles = new HttpOperationManifestFile();
        $httpFiles->write($httpFiles->load($httpManifest), $httpManifest, 'build-fingerprint-other');
        new CommandTester(new CompileBuildArtifactsCommand())->execute($arguments);

        self::assertSame('build-fingerprint-match', $httpFiles->loadArtifact($httpManifest)->applicationBuildId);
    }

    public function testCompilesBuildArtifactsWithComposerMetadataProviders(): void
    {
        $operationProviders = $this->path('operation-providers');
        $serviceProviders = $this->path('service-providers');
        $composerMetadata = $this->path('composer-metadata');
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $containerPath = $this->path('container');
        $class = 'ComposerBuildContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        file_put_contents($operationProviders, '<?php return [\\' . BuildOperationProvider::class . '::class];');
        file_put_contents($serviceProviders, '<?php return [\\' . BuildServiceProvider::class . '::class];');
        file_put_contents($composerMetadata, json_encode([
            'extra' => [
                'blackops' => [
                    'operation-providers' => [ComposerBuildOperationProvider::class],
                    'service-providers' => [ComposerBuildServiceProvider::class],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $status = new CommandTester(new CompileBuildArtifactsCommand())->execute([
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $operationManifest,
            'http-manifest' => $httpManifest,
            'frontend-manifest' => $this->path('composer-frontend-manifest'),
            'container' => $containerPath,
            '--application-build-id' => 'build-composer-123',
            '--container-class' => $class,
            '--container-namespace' => $namespace,
            '--composer-metadata' => $composerMetadata,
        ]);

        $operationRegistry = new OperationManifestFile()->load($operationManifest);
        require_once $containerPath;
        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();

        self::assertSame(0, $status);
        self::assertSame(BuildOperation::class, $operationRegistry->findByTypeId('build.operation')?->definition);
        self::assertSame(
            ComposerBuildOperation::class,
            $operationRegistry->findByTypeId('composer.build.operation')?->definition,
        );
        self::assertInstanceOf(BuildService::class, $container->get(BuildService::class));
        self::assertInstanceOf(ComposerBuildService::class, $container->get(ComposerBuildService::class));
    }

    public function testCompilesBuildArtifactsWithInstalledComposerMetadataProviders(): void
    {
        $operationProviders = $this->path('operation-providers');
        $serviceProviders = $this->path('service-providers');
        $installedComposerMetadata = $this->path('installed-composer-metadata');
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $containerPath = $this->path('container');
        $class = 'InstalledBuildContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        file_put_contents($operationProviders, '<?php return [\\' . BuildOperationProvider::class . '::class];');
        file_put_contents($serviceProviders, '<?php return [\\' . BuildServiceProvider::class . '::class];');
        file_put_contents($installedComposerMetadata, json_encode([
            'packages' => [
                ['name' => 'vendor/empty'],
                [
                    'name' => 'vendor/with-providers',
                    'extra' => [
                        'blackops' => [
                            'operation-providers' => [ComposerBuildOperationProvider::class],
                            'service-providers' => [ComposerBuildServiceProvider::class],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $status = new CommandTester(new CompileBuildArtifactsCommand())->execute([
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $operationManifest,
            'http-manifest' => $httpManifest,
            'frontend-manifest' => $this->path('installed-frontend-manifest'),
            'container' => $containerPath,
            '--application-build-id' => 'build-installed-123',
            '--container-class' => $class,
            '--container-namespace' => $namespace,
            '--installed-composer-metadata' => $installedComposerMetadata,
        ]);

        $operationRegistry = new OperationManifestFile()->load($operationManifest);
        require_once $containerPath;
        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();

        self::assertSame(0, $status);
        self::assertSame(
            ComposerBuildOperation::class,
            $operationRegistry->findByTypeId('composer.build.operation')?->definition,
        );
        self::assertInstanceOf(ComposerBuildService::class, $container->get(ComposerBuildService::class));
    }

    public function testComposerMetadataParticipatesInBuildFingerprint(): void
    {
        $operationProviders = $this->path('operation-providers');
        $serviceProviders = $this->path('service-providers');
        $composerMetadata = $this->path('composer-metadata');
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $containerPath = $this->path('container');
        $fingerprint = $this->path('fingerprint');
        file_put_contents($operationProviders, '<?php return [\\' . BuildOperationProvider::class . '::class];');
        file_put_contents($serviceProviders, '<?php return [\\' . BuildServiceProvider::class . '::class];');
        file_put_contents($composerMetadata, json_encode(['extra' => ['blackops' => []]], JSON_THROW_ON_ERROR));
        $arguments = [
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $operationManifest,
            'http-manifest' => $httpManifest,
            'frontend-manifest' => $this->path('composer-fingerprint-frontend-manifest'),
            'container' => $containerPath,
            '--application-build-id' => 'build-composer-fingerprint',
            '--fingerprint' => $fingerprint,
            '--composer-metadata' => $composerMetadata,
        ];

        new CommandTester(new CompileBuildArtifactsCommand())->execute($arguments);
        file_put_contents($composerMetadata, json_encode([
            'extra' => [
                'blackops' => [
                    'operation-providers' => [ComposerBuildOperationProvider::class],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        new CommandTester(new CompileBuildArtifactsCommand())->execute($arguments);

        $operationRegistry = new OperationManifestFile()->load($operationManifest);

        self::assertSame(
            ComposerBuildOperation::class,
            $operationRegistry->findByTypeId('composer.build.operation')?->definition,
        );
    }

    public function testInstalledComposerMetadataParticipatesInBuildFingerprint(): void
    {
        $operationProviders = $this->path('operation-providers');
        $serviceProviders = $this->path('service-providers');
        $installedComposerMetadata = $this->path('installed-composer-metadata');
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $containerPath = $this->path('container');
        $fingerprint = $this->path('fingerprint');
        file_put_contents($operationProviders, '<?php return [\\' . BuildOperationProvider::class . '::class];');
        file_put_contents($serviceProviders, '<?php return [\\' . BuildServiceProvider::class . '::class];');
        file_put_contents($installedComposerMetadata, json_encode(['packages' => []], JSON_THROW_ON_ERROR));
        $arguments = [
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $operationManifest,
            'http-manifest' => $httpManifest,
            'frontend-manifest' => $this->path('installed-fingerprint-frontend-manifest'),
            'container' => $containerPath,
            '--application-build-id' => 'build-installed-fingerprint',
            '--fingerprint' => $fingerprint,
            '--installed-composer-metadata' => $installedComposerMetadata,
        ];

        new CommandTester(new CompileBuildArtifactsCommand())->execute($arguments);
        file_put_contents($installedComposerMetadata, json_encode([
            'packages' => [
                [
                    'extra' => [
                        'blackops' => [
                            'operation-providers' => [ComposerBuildOperationProvider::class],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        new CommandTester(new CompileBuildArtifactsCommand())->execute($arguments);

        $operationRegistry = new OperationManifestFile()->load($operationManifest);

        self::assertSame(
            ComposerBuildOperation::class,
            $operationRegistry->findByTypeId('composer.build.operation')?->definition,
        );
    }

    public function testRejectsMissingOperationProviderConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new CompileBuildArtifactsCommand())->execute([
            'operation-providers' => $this->path('missing-operations'),
            'service-providers' => $this->path('service-providers'),
            'operation-manifest' => $this->path('operation-manifest'),
            'http-manifest' => $this->path('http-manifest'),
            'frontend-manifest' => $this->path('frontend-manifest'),
            'container' => $this->path('container'),
            '--application-build-id' => 'build-missing-config',
        ]);
    }

    public function testRejectsMissingApplicationBuildId(): void
    {
        $operationProviders = $this->path('operation-providers');
        $serviceProviders = $this->path('service-providers');
        file_put_contents($operationProviders, '<?php return [\\' . BuildOperationProvider::class . '::class];');
        file_put_contents($serviceProviders, '<?php return [\\' . BuildServiceProvider::class . '::class];');

        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new CompileBuildArtifactsCommand())->execute([
            'operation-providers' => $operationProviders,
            'service-providers' => $serviceProviders,
            'operation-manifest' => $this->path('operation-manifest'),
            'http-manifest' => $this->path('http-manifest'),
            'frontend-manifest' => $this->path('frontend-manifest'),
            'container' => $this->path('container'),
        ]);
    }

    private function path(string $name): string
    {
        return sys_get_temp_dir() . '/blackops-build-' . $name . '-' . bin2hex(random_bytes(8)) . '.php';
    }
}

final readonly class BuildOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [BuildOperation::class];
    }
}

final readonly class RequiredBuildOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [RequiredBuildOperation::class];
    }
}

final readonly class RequiredBuildServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(RequiredBuildDependency::class);
    }
}

final readonly class BuildServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(BuildService::class);
    }
}

final readonly class ComposerBuildOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ComposerBuildOperation::class];
    }
}

final readonly class ComposerBuildServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ComposerBuildService::class);
    }
}

#[Route('GET', '/build')]
#[OperationType('build.operation')]
#[Authorize(BuildAuthorizationPolicy::class)]
#[Accepts(BuildValue::class)]
#[HandledBy(BuildHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class BuildOperation implements Operation {}

final readonly class BuildValue implements OperationValue {}

final readonly class BuildHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

final readonly class BuildService {}

final readonly class BuildAuthorizationPolicy implements AuthorizationPolicy
{
    public function __construct(
        public BuildService $service,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        return AuthorizationDecision::allow();
    }
}

final readonly class RequiredBuildDependency
{
    public function __construct(
        public string $value = 'required-ready',
    ) {}
}

#[Route('GET', '/required-build')]
#[OperationType('build.required')]
#[Accepts(BuildValue::class)]
#[Returns(EmptyOutcome::class)]
final readonly class RequiredBuildOperation implements Operation, OperationHandler
{
    public function __construct(
        public RequiredBuildDependency $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

#[Route('GET', '/composer-build')]
#[OperationType('composer.build.operation')]
#[Accepts(ComposerBuildValue::class)]
#[HandledBy(ComposerBuildHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class ComposerBuildOperation implements Operation {}

final readonly class ComposerBuildValue implements OperationValue {}

final readonly class ComposerBuildHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

final readonly class ComposerBuildService {}

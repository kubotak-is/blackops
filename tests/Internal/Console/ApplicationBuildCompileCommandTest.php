<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Database\DatabaseManager;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector;
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;
use BlackOps\Tests\Fixtures\Aop\TransactionalOperation;
use BlackOps\Tests\Fixtures\Aop\TransactionalService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Ray\Aop\WeavedInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class ApplicationBuildCompileCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-application-build-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o755, true);
    }

    public function testGeneratedContainerResolvesAutowiredAuthorizationPolicy(): void
    {
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $frontendManifest = $this->path('frontend-manifest');
        $containerPath = $this->path('container');
        $class = 'ApplicationBuildContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        $configuration = new ApplicationConfigurationSnapshot(
            dirname(__DIR__, 3),
            [
                'app' => [
                    'build' => [
                        'operation_manifest' => $operationManifest,
                        'http_manifest' => $httpManifest,
                        'frontend_manifest' => $frontendManifest,
                        'container' => $containerPath,
                        'container_class' => $class,
                        'container_namespace' => $namespace,
                        'application_build_id' => 'application-build-authorization',
                    ],
                ],
                'database' => [
                    'default' => 'app',
                    'connections' => [
                        'app' => [
                            'driver' => 'pdo_pgsql',
                            'password' => 'build-credential-that-must-not-appear',
                        ],
                    ],
                    'framework' => ['connection' => 'app', 'schema' => 'blackops'],
                ],
            ],
            [ApplicationBuildOperationProvider::class],
            [ApplicationBuildServiceProvider::class],
            [],
        );

        $status = new CommandTester(new ApplicationBuildCompileCommand($configuration))->execute([]);
        require_once $containerPath;
        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();
        $metadata = new OperationManifestFile()
            ->load($operationManifest)
            ->findByTypeId('application.build.authorized');
        $transactionalMetadata = new OperationManifestFile()
            ->load($operationManifest)
            ->findByTypeId('application.build.transactional');
        $operationArtifact = new OperationManifestFile()->loadArtifact($operationManifest);
        $httpArtifact = new HttpOperationManifestFile()->loadArtifact($httpManifest);
        $frontendArtifact = new FrontendContractManifestFile()->loadArtifact($frontendManifest);

        self::assertSame(0, $status);
        self::assertSame(FrontendContractManifestFile::SCHEMA_VERSION, $frontendArtifact->schemaVersion);
        self::assertSame('application-build-authorization', $operationArtifact->applicationBuildId);
        self::assertSame($operationArtifact->applicationBuildId, $httpArtifact->applicationBuildId);
        self::assertSame($operationArtifact->applicationBuildId, $frontendArtifact->applicationBuildId);
        self::assertSame(ApplicationBuildAuthorizationPolicy::class, $metadata?->authorizationPolicy);
        self::assertSame('app', $transactionalMetadata?->transactionConnection);
        self::assertInstanceOf(
            ApplicationBuildAuthorizationPolicy::class,
            $container->get(ApplicationBuildAuthorizationPolicy::class),
        );
        self::assertInstanceOf(
            ApplicationBuildPolicyDependency::class,
            $container->get(ApplicationBuildAuthorizationPolicy::class)->dependency,
        );
        self::assertInstanceOf(
            ApplicationBuildStatusAuthorizer::class,
            $container->get(OperationStatusAuthorizer::class),
        );
        $connection = $this->transactionConnection();
        $databases = $this->createStub(DatabaseManager::class);
        $databases->method('connection')->willReturn($connection);
        $container->set(DatabaseManager::class, $databases);
        $container->set(Connection::class, $connection);
        new RuntimeTransactionServiceInjector()->inject($container, $databases, new ExecutionScopeProvider());
        self::assertTrue($container->has(DatabaseManager::class));
        self::assertTrue($container->has(Connection::class));
        $transactional = $container->get(TransactionalService::class);
        self::assertInstanceOf(WeavedInterface::class, $transactional);
        self::assertNotInstanceOf(WeavedInterface::class, new TransactionalService());
        self::assertSame('application-build-aop', $transactional->execute('application-build-aop'));
        self::assertSame(1, $transactional->calls);
        $source = (string) file_get_contents($containerPath);
        self::assertStringContainsString("require_once __DIR__ . '/aop/", $source);
        self::assertStringNotContainsString('build-credential-that-must-not-appear', $source);
        self::assertStringNotContainsString("'password'", $source);

        foreach (glob($this->directory . '/aop/*.php') ?: [] as $proxySource) {
            $proxy = (string) file_get_contents($proxySource);
            self::assertStringNotContainsString('build-credential-that-must-not-appear', $proxy);
            self::assertStringNotContainsString("'password'", $proxy);
        }
    }

    private function path(string $name): string
    {
        return $this->directory . '/' . $name . '.php';
    }

    private function transactionConnection(): Connection
    {
        $active = false;
        $level = 0;
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('isTransactionActive')
            ->willReturnCallback(static function () use (&$active): bool {
                return $active;
            });
        $connection
            ->method('getTransactionNestingLevel')
            ->willReturnCallback(static function () use (&$level): int {
                return $level;
            });
        $connection
            ->method('beginTransaction')
            ->willReturnCallback(static function () use (&$active, &$level): void {
                $active = true;
                $level = 1;
            });
        $connection
            ->method('commit')
            ->willReturnCallback(static function () use (&$active, &$level): void {
                $active = false;
                $level = 0;
            });
        $connection
            ->method('rollBack')
            ->willReturnCallback(static function () use (&$active, &$level): void {
                $active = false;
                $level = 0;
            });

        return $connection;
    }
}

final readonly class ApplicationBuildOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ApplicationBuildOperation::class, TransactionalOperation::class];
    }
}

final readonly class ApplicationBuildServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ApplicationBuildPolicyDependency::class);
        $services->autowire(TransactionalService::class);
        $services->autowire(OperationStatusAuthorizer::class, ApplicationBuildStatusAuthorizer::class);
    }
}

final readonly class ApplicationBuildPolicyDependency {}

final readonly class ApplicationBuildStatusAuthorizer implements OperationStatusAuthorizer
{
    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        return OperationStatusAuthorizationDecision::allow();
    }
}

final readonly class ApplicationBuildValue implements OperationValue {}

final readonly class ApplicationBuildOutcome implements Outcome {}

#[OperationType('application.build.authorized')]
#[Authorize(ApplicationBuildAuthorizationPolicy::class)]
final readonly class ApplicationBuildOperation implements Operation
{
    public function handle(ApplicationBuildValue $value): ApplicationBuildOutcome
    {
        return new ApplicationBuildOutcome();
    }
}

final readonly class ApplicationBuildAuthorizationPolicy implements AuthorizationPolicy
{
    public function __construct(
        public ApplicationBuildPolicyDependency $dependency,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        return AuthorizationDecision::allow();
    }
}

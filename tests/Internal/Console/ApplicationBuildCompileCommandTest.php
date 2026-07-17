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
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Internal\Registry\OperationManifestFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ApplicationBuildCompileCommandTest extends TestCase
{
    public function testGeneratedContainerResolvesAutowiredAuthorizationPolicy(): void
    {
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $containerPath = $this->path('container');
        $class = 'ApplicationBuildContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        $configuration = new ApplicationConfigurationSnapshot(
            dirname(__DIR__, 3),
            [],
            [
                'app' => [
                    'build' => [
                        'operation_manifest' => $operationManifest,
                        'http_manifest' => $httpManifest,
                        'container' => $containerPath,
                        'container_class' => $class,
                        'container_namespace' => $namespace,
                        'application_build_id' => 'application-build-authorization',
                    ],
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

        self::assertSame(0, $status);
        self::assertSame(ApplicationBuildAuthorizationPolicy::class, $metadata?->authorizationPolicy);
        self::assertInstanceOf(
            ApplicationBuildAuthorizationPolicy::class,
            $container->get(ApplicationBuildAuthorizationPolicy::class),
        );
        self::assertInstanceOf(
            ApplicationBuildPolicyDependency::class,
            $container->get(ApplicationBuildAuthorizationPolicy::class)->dependency,
        );
    }

    private function path(string $name): string
    {
        return sys_get_temp_dir() . '/blackops-application-build-' . $name . '-' . bin2hex(random_bytes(8)) . '.php';
    }
}

final readonly class ApplicationBuildOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ApplicationBuildOperation::class];
    }
}

final readonly class ApplicationBuildServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ApplicationBuildPolicyDependency::class);
    }
}

final readonly class ApplicationBuildPolicyDependency {}

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

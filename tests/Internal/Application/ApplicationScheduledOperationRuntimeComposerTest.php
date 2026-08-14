<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Registry\OperationScheduleMetadata;
use BlackOps\Core\ScheduleContext;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationScheduledOperationRuntimeComposer;
use BlackOps\Scheduling\ScheduledActorProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;

final class ApplicationScheduledOperationRuntimeComposerTest extends TestCase
{
    public function testBuildIdBoundaryRejectsMismatchedCompiledArtifact(): void
    {
        $method = new ReflectionMethod(ApplicationScheduledOperationRuntimeComposer::class, 'assertBuildId');
        $configuration = $this->configuration('configured-build');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('build ID');

        $method->invoke(new ApplicationScheduledOperationRuntimeComposer(), 'artifact-build', $configuration);
    }

    public function testBuildIdBoundaryAcceptsConfiguredArtifact(): void
    {
        $method = new ReflectionMethod(ApplicationScheduledOperationRuntimeComposer::class, 'assertBuildId');

        $method->invoke(
            new ApplicationScheduledOperationRuntimeComposer(),
            'configured-build',
            $this->configuration('configured-build'),
        );
        self::assertTrue(true);
    }

    public function testAuthorizedScheduleRequiresProviderAndUnscheduledDoesNot(): void
    {
        $method = new ReflectionMethod(ApplicationScheduledOperationRuntimeComposer::class, 'provider');
        $composer = new ApplicationScheduledOperationRuntimeComposer();
        $authorized = new OperationRegistry([
            new OperationMetadata(
                'composer.authorized',
                ComposerScheduledOperation::class,
                ComposerScheduledValue::class,
                ComposerScheduledOperation::class,
                ComposerScheduledOutcome::class,
                Inline::class,
                authorizationPolicy: ComposerAuthorizationPolicy::class,
                schedule: new OperationScheduleMetadata('composer.authorized', '* * * * *', 'UTC'),
            ),
        ]);
        $unscheduled = new OperationRegistry([
            new OperationMetadata(
                'composer.unscheduled',
                ComposerScheduledOperation::class,
                ComposerScheduledValue::class,
                ComposerScheduledOperation::class,
                ComposerScheduledOutcome::class,
                Inline::class,
            ),
        ]);

        try {
            $method->invoke($composer, new ComposerContainer(), $authorized);
            self::fail('Expected an authorized schedule without a provider to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('provider', $exception->getMessage());
        }

        self::assertNull($method->invoke($composer, new ComposerContainer(), $unscheduled));
    }

    public function testProviderBoundaryRejectsWrongTypeAndReturnsConfiguredProvider(): void
    {
        $method = new ReflectionMethod(ApplicationScheduledOperationRuntimeComposer::class, 'provider');
        $composer = new ApplicationScheduledOperationRuntimeComposer();
        $operations = new OperationRegistry([]);
        $wrong = new ComposerContainer([ScheduledActorProvider::class => new \stdClass()]);

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($composer, $wrong, $operations);
    }

    public function testProviderBoundaryReturnsConfiguredProvider(): void
    {
        $method = new ReflectionMethod(ApplicationScheduledOperationRuntimeComposer::class, 'provider');
        $provider = new ComposerScheduledActorProvider();
        $resolved = $method->invoke(
            new ApplicationScheduledOperationRuntimeComposer(),
            new ComposerContainer([ScheduledActorProvider::class => $provider]),
            new OperationRegistry([]),
        );

        self::assertSame($provider, $resolved);
    }

    private function configuration(string $buildId): ApplicationConfigurationSnapshot
    {
        return new ApplicationConfigurationSnapshot(
            dirname(__DIR__, 3),
            ['app' => ['build' => ['application_build_id' => $buildId]]],
            [],
            [],
            [],
        );
    }
}

final class ComposerContainer implements ContainerInterface
{
    /** @param array<string, object> $services */
    public function __construct(
        private array $services = [],
    ) {}

    public function get(string $id): object
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

final readonly class ComposerScheduledOperation implements Operation
{
    public function handle(ComposerScheduledValue $value): ComposerScheduledOutcome
    {
        return new ComposerScheduledOutcome();
    }
}

final readonly class ComposerScheduledValue implements OperationValue {}

final readonly class ComposerScheduledOutcome implements Outcome {}

final readonly class ComposerAuthorizationPolicy {}

final readonly class ComposerScheduledActorProvider implements ScheduledActorProvider
{
    public function actor(ScheduleContext $context): ?ActorRef
    {
        return null;
    }
}

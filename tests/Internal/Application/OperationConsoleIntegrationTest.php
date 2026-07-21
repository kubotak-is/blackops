<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Console\ConsoleActorProvider;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\ConsoleCommand;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class OperationConsoleIntegrationTest extends TestCase
{
    use ApplicationTestDirectories {
        tearDown as private cleanupDirectories;
    }

    private const string SCHEMA = 'blackops_operation_console';

    protected function tearDown(): void
    {
        DriverManager::getConnection($this->connectionParameters())->executeStatement(
            'DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE',
        );
        $this->cleanupDirectories();
    }

    public function testBuildListHelpAndOperationLifecycleUseArtifactOnlyRuntime(): void
    {
        $directory = $this->applicationDirectory();
        ConsoleFixtureActorProvider::$actor = new ActorRef('console-user', 'user');
        ConsoleFixtureActorProvider::$throw = false;
        ConsoleFixtureActorProvider::$calls = 0;
        $connection = DriverManager::getConnection($this->connectionParameters());
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new DatabaseMigrationRunner($connection, self::SCHEMA)->migrate();
        $application = Application::configure($directory)
            ->withConfiguration()
            ->withOperations([ConsoleFixtureOperationProvider::class])
            ->withServices([ConsoleFixtureServiceProvider::class])
            ->create();
        self::assertSame(0, $application->console()->run(new ArrayInput([
            'command' => 'build:compile',
        ]), new BufferedOutput()));

        $runtime = Application::configure($directory)
            ->withConfiguration()
            ->withOperations([ConsoleFixtureOperationProvider::class])
            ->withServices([ConsoleFixtureServiceProvider::class])
            ->create();
        $list = new BufferedOutput();
        self::assertSame(0, $runtime->console()->run(new ArrayInput(['command' => 'list']), $list));
        self::assertStringContainsString('fixture:inline', $list->fetch());
        self::assertSame(0, ConsoleFixtureActorProvider::$calls);

        $help = new BufferedOutput();
        self::assertSame(0, $runtime->console()->run(new ArrayInput([
            'command' => 'help',
            'command_name' => 'fixture:inline',
        ]), $help));
        self::assertStringContainsString('--name', $help->fetch());
        self::assertSame(0, ConsoleFixtureActorProvider::$calls);

        $inline = new BufferedOutput();
        self::assertSame(0, $runtime->console()->run(new ArrayInput([
            'command' => 'fixture:inline',
            '--name' => 'Ada',
            '--json' => true,
        ]), $inline));
        self::assertSame(
            [
                'schemaVersion' => 1,
                'status' => 'completed',
                'outcome' => [
                    'execution' => 'console-runtime',
                    'message' => 'Hello Ada',
                    'origin' => 'console-user',
                ],
            ],
            json_decode($inline->fetch(), true, flags: JSON_THROW_ON_ERROR),
        );

        $void = new BufferedOutput();
        self::assertSame(0, $runtime->console()->run(new ArrayInput([
            'command' => 'fixture:void',
            '--json' => true,
        ]), $void));
        self::assertSame('{"schemaVersion":1,"status":"completed","outcome":{}}' . "\n", $void->fetch());

        $validation = new BufferedOutput();
        self::assertSame(2, $runtime->console()->run(new ArrayInput([
            'command' => 'fixture:inline',
            '--name' => '',
            '--json' => true,
        ]), $validation));
        $validationPayload = json_decode($validation->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('rejected', $validationPayload['status']);
        self::assertSame('validation.failed', $validationPayload['code']);
        self::assertArrayHasKey('operationId', $validationPayload);
        self::assertStringNotContainsString('Ada', $validation->fetch());

        $binding = new BufferedOutput();
        self::assertSame(2, $runtime->console()->run(new ArrayInput([
            'command' => 'fixture:inline',
            '--json' => true,
        ]), $binding));
        $bindingPayload = json_decode($binding->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('rejected', $bindingPayload['status']);
        self::assertSame('validation.failed', $bindingPayload['code']);
        self::assertArrayHasKey('operationId', $bindingPayload);

        $deferred = new BufferedOutput();
        self::assertSame(0, $runtime->console()->run(new ArrayInput([
            'command' => 'fixture:deferred',
            '--json' => true,
        ]), $deferred));
        $accepted = json_decode($deferred->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('accepted', $accepted['status']);
        self::assertArrayHasKey('operationId', $accepted);
        self::assertArrayHasKey('acceptedAt', $accepted);

        $business = new BufferedOutput();
        self::assertSame(1, $runtime->console()->run(new ArrayInput([
            'command' => 'fixture:business',
            '--json' => true,
        ]), $business));
        self::assertSame(
            'business_rule',
            json_decode($business->fetch(), true, flags: JSON_THROW_ON_ERROR)['category'],
        );

        $failure = new BufferedOutput();
        self::assertSame(1, $runtime->console()->run(new ArrayInput([
            'command' => 'fixture:failure',
            '--json' => true,
        ]), $failure));
        $failurePayload = json_decode($failure->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('error', $failurePayload['status']);
        self::assertSame('internal_error', $failurePayload['code']);
        self::assertArrayHasKey('operationId', $failurePayload);
        self::assertStringNotContainsString('operation-secret', $failure->fetch());

        ConsoleFixtureActorProvider::$actor = null;
        $denied = new BufferedOutput();
        self::assertSame(1, $runtime->console()->run(new ArrayInput([
            'command' => 'fixture:inline',
            '--name' => 'Denied',
            '--json' => true,
        ]), $denied));
        self::assertSame('unauthorized', json_decode($denied->fetch(), true, flags: JSON_THROW_ON_ERROR)['category']);

        ConsoleFixtureActorProvider::$throw = true;
        $providerFailure = new BufferedOutput();
        self::assertSame(1, $runtime->console()->run(new ArrayInput([
            'command' => 'fixture:inline',
            '--name' => 'credential-value',
            '--json' => true,
        ]), $providerFailure));
        self::assertSame(
            ['schemaVersion' => 1, 'status' => 'error', 'code' => 'internal_error'],
            json_decode($providerFailure->fetch(), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString('credential-value', $providerFailure->fetch());

        ConsoleFixtureActorProvider::$throw = false;
        $connection->executeStatement('DROP TABLE ' . self::SCHEMA . '.operations CASCADE');
        $transportFailure = new BufferedOutput();
        self::assertSame(1, $runtime->console()->run(new ArrayInput([
            'command' => 'fixture:deferred',
            '--json' => true,
        ]), $transportFailure));
        $transportPayload = json_decode($transportFailure->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('error', $transportPayload['status']);
        self::assertSame('internal_error', $transportPayload['code']);
        self::assertArrayHasKey('operationId', $transportPayload);
        self::assertStringNotContainsString('operations', $transportFailure->fetch());
    }

    private function applicationDirectory(): string
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        $build = $directory . '/var/build';
        mkdir($config);
        mkdir($build, recursive: true);
        $class = 'OperationConsoleContainer' . bin2hex(random_bytes(4));
        $this->writeConfig(
            $config,
            'app',
            'return '
            . var_export([
                'build' => [
                    'application_build_id' => 'operation-console-fixture',
                    'operation_manifest' => $build . '/operations.php',
                    'http_manifest' => $build . '/http.php',
                    'frontend_manifest' => $build . '/frontend.php',
                    'command_manifest' => $build . '/commands.php',
                    'container' => $build . '/container.php',
                    'container_class' => $class,
                    'container_namespace' => 'BlackOps\\Tests\\Generated\\OperationConsole',
                ],
            ], return: true)
            . ';',
        );
        $this->writeConfig(
            $config,
            'database',
            'return '
            . var_export([
                'connection' => $this->connectionParameters(),
                'schema' => self::SCHEMA,
            ], return: true)
            . ';',
        );

        return $directory;
    }

    /** @return array<string, mixed> */
    private function connectionParameters(): array
    {
        return [
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ];
    }
}

final readonly class ConsoleFixtureOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [
            ConsoleInlineOperation::class,
            ConsoleDeferredOperation::class,
            ConsoleBusinessOperation::class,
            ConsoleFailureOperation::class,
            ConsoleVoidOperation::class,
        ];
    }
}

final readonly class ConsoleFixtureServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ConsoleActorProvider::class, ConsoleFixtureActorProvider::class);
    }
}

final class ConsoleFixtureActorProvider implements ConsoleActorProvider
{
    public static ?ActorRef $actor = null;
    public static bool $throw = false;
    public static int $calls = 0;

    public function actor(): ?ActorRef
    {
        self::$calls++;
        if (self::$throw) {
            throw new \RuntimeException('credential-value');
        }

        return self::$actor;
    }
}

final readonly class ConsoleFixtureAuthorizationPolicy implements AuthorizationPolicy
{
    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        return $request->actor()->id() === 'console-user'
            ? AuthorizationDecision::allow()
            : AuthorizationDecision::unauthorized('console.actor_required');
    }
}

#[ConsoleCommand('fixture:inline', 'Run an inline operation.')]
#[OperationType('fixture.console.inline')]
#[Authorize(ConsoleFixtureAuthorizationPolicy::class)]
final readonly class ConsoleInlineOperation implements Operation
{
    public function handle(ConsoleInlineValue $value, ExecutionContext $context): ConsoleInlineOutcome
    {
        return new ConsoleInlineOutcome(
            'Hello ' . $value->name,
            $context->actorContext()?->origin()?->id() ?? 'none',
            $context->actorContext()?->execution()->id() ?? 'none',
        );
    }
}

final readonly class ConsoleInlineValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        public string $name,
    ) {}
}

final readonly class ConsoleInlineOutcome implements Outcome
{
    public function __construct(
        public string $message,
        public string $origin,
        public string $execution,
    ) {}
}

#[ConsoleCommand('fixture:deferred', 'Accept a deferred operation.')]
#[OperationType('fixture.console.deferred')]
#[ExecuteWith(Deferred::class)]
final readonly class ConsoleDeferredOperation implements Operation
{
    public function handle(ConsoleDeferredValue $value): ConsoleDeferredOutcome
    {
        return new ConsoleDeferredOutcome();
    }
}

final readonly class ConsoleDeferredValue implements OperationValue {}

final readonly class ConsoleDeferredOutcome implements Outcome {}

#[ConsoleCommand('fixture:business')]
#[OperationType('fixture.console.business')]
final readonly class ConsoleBusinessOperation implements Operation
{
    public function handle(ConsoleDeferredValue $value): ConsoleDeferredOutcome
    {
        throw OperationRejectedException::businessRule('fixture.rejected');
    }
}

#[ConsoleCommand('fixture:failure')]
#[OperationType('fixture.console.failure')]
final readonly class ConsoleFailureOperation implements Operation
{
    public function handle(ConsoleDeferredValue $value): ConsoleDeferredOutcome
    {
        throw new \RuntimeException('operation-secret');
    }
}

#[ConsoleCommand('fixture:void')]
#[OperationType('fixture.console.void')]
final readonly class ConsoleVoidOperation implements Operation
{
    public function handle(ConsoleDeferredValue $value): void {}
}

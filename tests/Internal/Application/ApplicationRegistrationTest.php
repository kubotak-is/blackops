<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Registry\OperationProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;

final class ApplicationRegistrationTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testCombinesConfigAndExplicitRegistrationsWithoutDuplicateIdentity(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig(
            $config,
            'operations',
            sprintf("return ['providers' => [%s::class]];", ConfigOperationProvider::class),
        );
        $this->writeConfig(
            $config,
            'app',
            sprintf(
                "return ['services' => [%s::class], 'commands' => [%s::class]];",
                ConfigServiceProvider::class,
                ConfigCommand::class,
            ),
        );

        $snapshot = $this->snapshot(
            Application::configure($directory)
                ->withConfiguration()
                ->withOperations([ConfigOperationProvider::class, new ExplicitOperationProvider()])
                ->withServices([new ConfigServiceProvider(), ExplicitServiceProvider::class])
                ->withCommands([new ConfigCommand(), ExplicitCommand::class])
                ->create(),
        );

        self::assertSame(
            [ConfigOperationProvider::class, ExplicitOperationProvider::class],
            array_map($this->identity(...), $snapshot->operationProviders()),
        );
        self::assertSame(
            [ConfigServiceProvider::class, ExplicitServiceProvider::class],
            array_map($this->identity(...), $snapshot->serviceProviders()),
        );
        self::assertSame(
            [ConfigCommand::class, ExplicitCommand::class],
            array_map($this->identity(...), $snapshot->commands()),
        );
    }

    public function testAcceptsOperationProviderListConfiguration(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig($config, 'operations', sprintf('return [%s::class];', ConfigOperationProvider::class));

        $snapshot = $this->snapshot(Application::configure($directory)->withConfiguration()->create());

        self::assertSame([ConfigOperationProvider::class], $snapshot->operationProviders());
    }

    public function testEnvironmentClosureRegistrationMatchesArrayRegistration(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig(
            $config,
            'operations',
            'use BlackOps\\Application\\Environment; return static fn (Environment $env): array => ["providers" => [$env->string("PROVIDER")]];',
        );

        $snapshot = $this->snapshot(
            Application::configure($directory)
                ->withConfiguration()
                ->withEnvironment(['PROVIDER' => ConfigOperationProvider::class])
                ->create(),
        );

        self::assertSame([ConfigOperationProvider::class], $snapshot->operationProviders());
    }

    public function testAcceptsGeneratorRegistrationInput(): void
    {
        $providers = (static function (): iterable {
            yield ExplicitOperationProvider::class;
        })();

        $snapshot = $this->snapshot(Application::configure($this->directory())->withOperations($providers)->create());

        self::assertSame([ExplicitOperationProvider::class], $snapshot->operationProviders());
    }

    public function testRejectsInvalidProviderAndCommandEntries(): void
    {
        foreach ([
            fn() => Application::configure($this->directory())->withOperations([RuntimeException::class])->create(),
            fn() => Application::configure($this->directory())->withServices([RuntimeException::class])->create(),
            fn() => Application::configure($this->directory())->withCommands([RuntimeException::class])->create(),
            fn() => Application::configure($this->directory())
                ->withOperations([RequiredArgumentOperationProvider::class])
                ->create(),
            fn() => Application::configure($this->directory())
                ->withCommands([RequiredArgumentCommand::class])
                ->create(),
        ] as $create) {
            try {
                $create();
                self::fail('Expected invalid registration.');
            } catch (ApplicationBootstrapException $exception) {
                self::assertStringNotContainsString('plain-secret', $exception->getMessage());
            }
        }
    }

    public function testRejectsConflictingCommandNames(): void
    {
        $this->expectException(ApplicationBootstrapException::class);
        $this->expectExceptionMessage('registered by more than one command');

        Application::configure($this->directory())
            ->withCommands([ConfigCommand::class, ConflictingCommand::class])
            ->create();
    }
}

final readonly class ConfigOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [];
    }
}

final readonly class ExplicitOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [];
    }
}

final readonly class RequiredArgumentOperationProvider implements OperationProvider
{
    public function __construct(
        private string $_dependency,
    ) {}

    public function definitions(): iterable
    {
        return [];
    }
}

final readonly class ConfigServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void {}
}

final readonly class ExplicitServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void {}
}

final class ConfigCommand extends Command
{
    public function __construct()
    {
        parent::__construct('application:config');
    }
}

final class ExplicitCommand extends Command
{
    public function __construct()
    {
        parent::__construct('application:explicit');
    }
}

final class ConflictingCommand extends Command
{
    public function __construct()
    {
        parent::__construct('application:config');
    }
}

final class RequiredArgumentCommand extends Command
{
    public function __construct(string $dependency)
    {
        parent::__construct('application:required');
    }
}

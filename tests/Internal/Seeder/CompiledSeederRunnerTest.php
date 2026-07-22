<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Seeder;

use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;
use BlackOps\Internal\Seeder\CompiledSeederRunner;
use BlackOps\Internal\Seeder\SeederRuntimeException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

final class CompiledSeederRunnerTest extends TestCase
{
    public function testRunsInArgumentOrderAndAllowsEmptyAndSequentialRepeats(): void
    {
        $state = new SeederTestState();
        $container = new SeederTestContainer();
        $runner = new CompiledSeederRunner($container);
        $container->set(FirstSeeder::class, new FirstSeeder($state));
        $container->set(SecondSeeder::class, new SecondSeeder($state));

        $runner->run();
        $runner->run(FirstSeeder::class, SecondSeeder::class, FirstSeeder::class);

        self::assertSame(['first', 'second', 'first'], $state->events);
    }

    public function testNestedSeederUsesTheSameRunnerAndLocator(): void
    {
        $state = new SeederTestState();
        $container = new SeederTestContainer();
        $runner = new CompiledSeederRunner($container);
        $container->set(NestedSeeder::class, new NestedSeeder($runner, $state));
        $container->set(FirstSeeder::class, new FirstSeeder($state));
        $container->set(SecondSeeder::class, new SecondSeeder($state));

        $runner->run(NestedSeeder::class);

        self::assertSame(['nested:start', 'first', 'second', 'nested:end'], $state->events);
    }

    public function testRejectsUnknownAndInvalidLocatorEntriesWithoutDynamicConstruction(): void
    {
        $container = new SeederTestContainer();
        $runner = new CompiledSeederRunner($container);

        try {
            $runner->run(UnknownSeeder::class);
            self::fail('Expected unknown seeder rejection.');
        } catch (SeederRuntimeException $exception) {
            self::assertSame('Seeder is not available in the compiled application.', $exception->getMessage());
        }

        $container->set(UnknownSeeder::class, new \stdClass());

        $this->expectException(SeederRuntimeException::class);
        $this->expectExceptionMessage('Compiled seeder service is invalid.');

        $runner->run(UnknownSeeder::class);
    }

    public function testCycleFailsBeforeSeederLogicIsReenteredAndStateIsCleared(): void
    {
        $state = new SeederTestState();
        $container = new SeederTestContainer();
        $runner = new CompiledSeederRunner($container);
        $container->set(CycleSeeder::class, new CycleSeeder($runner, $state));

        try {
            $runner->run(CycleSeeder::class);
            self::fail('Expected seeder cycle rejection.');
        } catch (SeederRuntimeException $exception) {
            self::assertSame('Seeder execution cycle detected.', $exception->getMessage());
        }

        self::assertSame(1, $state->calls);

        try {
            $runner->run(CycleSeeder::class);
            self::fail('Expected seeder cycle rejection on a later run.');
        } catch (SeederRuntimeException) {
            self::assertSame(2, $state->calls);
        }
    }

    public function testChildExceptionIsSafeAndStopsRemainingSeeders(): void
    {
        $state = new SeederTestState();
        $container = new SeederTestContainer();
        $runner = new CompiledSeederRunner($container);
        $container->set(FailingSeeder::class, new FailingSeeder());
        $container->set(SecondSeeder::class, new SecondSeeder($state));

        try {
            $runner->run(FailingSeeder::class, SecondSeeder::class);
            self::fail('Expected child seeder failure.');
        } catch (SeederRuntimeException $exception) {
            self::assertSame('Seeder execution failed.', $exception->getMessage());
            self::assertStringNotContainsString('credential-value', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame([], $state->events);
    }
}

/** @mago-expect lint:single-class-per-file */
final class SeederTestContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $services = [];

    public function set(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }

    public function get(string $id): object
    {
        if (!$this->has($id)) {
            throw new SeederTestNotFound();
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

/** @mago-expect lint:single-class-per-file */
final class SeederTestNotFound extends RuntimeException implements NotFoundExceptionInterface {}

/** @mago-expect lint:single-class-per-file */
final class SeederTestState
{
    /** @var list<string> */
    public array $events = [];

    public int $calls = 0;
}

/** @mago-expect lint:single-class-per-file */
final readonly class FirstSeeder implements Seeder
{
    public function __construct(
        private SeederTestState $state,
    ) {}

    public function run(): void
    {
        $this->state->events[] = 'first';
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class SecondSeeder implements Seeder
{
    public function __construct(
        private SeederTestState $state,
    ) {}

    public function run(): void
    {
        $this->state->events[] = 'second';
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class NestedSeeder implements Seeder
{
    public function __construct(
        private SeederRunner $seeders,
        private SeederTestState $state,
    ) {}

    public function run(): void
    {
        $this->state->events[] = 'nested:start';
        $this->seeders->run(FirstSeeder::class, SecondSeeder::class);
        $this->state->events[] = 'nested:end';
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class CycleSeeder implements Seeder
{
    public function __construct(
        private SeederRunner $seeders,
        private SeederTestState $state,
    ) {}

    public function run(): void
    {
        ++$this->state->calls;
        $this->seeders->run(self::class);
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class FailingSeeder implements Seeder
{
    public function run(): void
    {
        throw new RuntimeException('credential-value');
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class UnknownSeeder implements Seeder
{
    public function run(): void {}
}

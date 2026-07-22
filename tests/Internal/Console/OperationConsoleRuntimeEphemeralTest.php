<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Internal\Application\ApplicationOperationInvocationLifecycle;
use BlackOps\Internal\Console\OperationConsoleCommandMetadata;
use BlackOps\Internal\Console\OperationConsoleRuntime;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;

final class OperationConsoleRuntimeEphemeralTest extends TestCase
{
    public function testRejectsEphemeralOperationInjectedIntoCommandManifest(): void
    {
        $metadata = new OperationMetadata(
            'console.ephemeral',
            ConsoleEphemeralOperation::class,
            ConsoleEphemeralValue::class,
            ConsoleEphemeralOperation::class,
            ConsoleEphemeralOutcome::class,
            Inline::class,
        );
        $runtime = new OperationConsoleRuntime(
            new OperationRegistry([$metadata]),
            new class implements ContainerInterface {
                public function get(string $id): object
                {
                    throw new LogicException('Container must not be read.');
                }

                public function has(string $id): bool
                {
                    return false;
                }
            },
            $this->withoutConstructor(InlineDispatcher::class),
            $this->withoutConstructor(DeferredHttpOperationAcceptor::class),
            $this->withoutConstructor(ApplicationOperationInvocationLifecycle::class),
            $this->withoutConstructor(ExecutionScopeProvider::class),
            $this->withoutConstructor(ExecutionScopedLogger::class),
        );
        $command = new OperationConsoleCommandMetadata(
            'console.ephemeral',
            ConsoleEphemeralOperation::class,
            ConsoleEphemeralValue::class,
            ConsoleEphemeralOutcome::class,
            Inline::class,
            'console:ephemeral',
            'Unsafe command manifest entry.',
            [],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('unavailable through the console runtime');
        new ReflectionMethod(OperationConsoleRuntime::class, 'definition')->invoke($runtime, $command);
    }

    /** @template T of object @param class-string<T> $class @return T */
    private function withoutConstructor(string $class): object
    {
        return new ReflectionClass($class)->newInstanceWithoutConstructor();
    }
}

final readonly class ConsoleEphemeralOperation implements Operation {}

final readonly class ConsoleEphemeralValue implements OperationValue {}

final readonly class ConsoleEphemeralOutcome implements EphemeralOutcome {}

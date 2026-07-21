<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Execution\Inline;
use BlackOps\Internal\Console\OperationConsoleCommand;
use BlackOps\Internal\Console\OperationConsoleCommandMetadata;
use BlackOps\Internal\Console\OperationConsoleOptionMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class OperationConsoleCommandTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidInputs(): iterable
    {
        yield 'unknown option' => ['--unknown=value'];
        yield 'position argument' => ['unexpected'];
        yield 'missing option value' => ['--name'];
    }

    #[DataProvider('invalidInputs')]
    public function testCliShapeFailureIsSafeExitTwoBeforeRuntimeComposition(string $arguments): void
    {
        $compositions = 0;
        $command = new OperationConsoleCommand(
            new OperationConsoleCommandMetadata(
                'fixture.command',
                ConsoleCommandFixtureOperation::class,
                ConsoleCommandFixtureValue::class,
                ConsoleCommandFixtureOutcome::class,
                Inline::class,
                'fixture:command',
                '',
                [new OperationConsoleOptionMetadata('name', 'name', 'string', false, true, null)],
            ),
            static function () use (&$compositions): \BlackOps\Internal\Console\OperationConsoleRuntime {
                $compositions++;
                throw new \LogicException('Runtime must not be composed for invalid CLI shape.');
            },
        );
        $output = new BufferedOutput();

        self::assertSame(2, $command->run(new StringInput($arguments), $output));
        self::assertSame(0, $compositions);
        self::assertSame("Rejected [validation:binding.failed].\n", $output->fetch());
    }
}

final readonly class ConsoleCommandFixtureOperation implements \BlackOps\Core\Operation {}

final readonly class ConsoleCommandFixtureValue implements \BlackOps\Core\OperationValue {}

final readonly class ConsoleCommandFixtureOutcome implements \BlackOps\Core\Outcome {}

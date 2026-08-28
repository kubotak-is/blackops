<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Command;

use BlackOps\Application\Application;
use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\Deferred as DeferredAttribute;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Console\ApplicationOperationListCommand;
use BlackOps\Tests\Internal\Application\ApplicationTestDirectories;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ApplicationOperationListCommandTest extends TestCase
{
    use ApplicationTestDirectories;

    protected function setUp(): void
    {
        StrategyProjectionProvider::$definitionsCalls = 0;
        EmptyStrategyProjectionProvider::$definitionsCalls = 0;
    }

    public function testNoFilterPreservesStableTableBytesAndTypeIdOrder(): void
    {
        $output = new BufferedOutput();
        $exitCode = $this->command(new StrategyProjectionProvider())->run(new ArrayInput([]), $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(1, StrategyProjectionProvider::$definitionsCalls);
        self::assertSame(<<<'TABLE'
            +-----------------+----------------------------------------------------------------------------+-----------------------------------------------------------+
            | Type ID         | Definition                                                                 | Execution Strategy                                        |
            +-----------------+----------------------------------------------------------------------------+-----------------------------------------------------------+
            | alpha.inline    | BlackOps\Tests\Internal\Projection\Command\StrategyInlineOperation         | BlackOps\Core\Execution\Inline                            |
            | custom.run      | BlackOps\Tests\Internal\Projection\Command\StrategyCustomOperation         | BlackOps\Tests\Internal\Projection\Command\StrategyCustom |
            | middle.deferred | BlackOps\Tests\Internal\Projection\Command\StrategyMiddleDeferredOperation | BlackOps\Core\Execution\Deferred                          |
            | zeta.deferred   | BlackOps\Tests\Internal\Projection\Command\StrategyDeferredOperation       | BlackOps\Core\Execution\Deferred                          |
            | zeta.inline     | BlackOps\Tests\Internal\Projection\Command\StrategyZetaInlineOperation     | BlackOps\Core\Execution\Inline                            |
            +-----------------+----------------------------------------------------------------------------+-----------------------------------------------------------+

            TABLE, $output->fetch());
    }

    public function testFilterSelectsExactBuiltInStrategyBeforeTypeIdSort(): void
    {
        foreach ([
            'inline' => ['alpha.inline', 'zeta.inline'],
            'deferred' => ['middle.deferred', 'zeta.deferred'],
        ] as $strategy => $expectedTypes) {
            StrategyProjectionProvider::$definitionsCalls = 0;
            $output = new BufferedOutput();
            $exitCode = $this->command(new StrategyProjectionProvider())->run(new ArrayInput([
                '--strategy' => $strategy,
            ]), $output);

            self::assertSame(Command::SUCCESS, $exitCode, $strategy);
            self::assertSame(1, StrategyProjectionProvider::$definitionsCalls, $strategy);
            $listing = $output->fetch();
            foreach ($expectedTypes as $typeId) {
                self::assertStringContainsString($typeId, $listing, $strategy);
            }
            self::assertStringNotContainsString('custom.run', $listing, $strategy);
            if ($strategy === 'inline') {
                self::assertStringNotContainsString('middle.deferred', $listing);
                self::assertStringNotContainsString('zeta.deferred', $listing);
                self::assertLessThan(strpos($listing, 'zeta.inline'), strpos($listing, 'alpha.inline'));
            } else {
                self::assertStringNotContainsString('alpha.inline', $listing);
                self::assertStringNotContainsString('zeta.inline', $listing);
                self::assertLessThan(strpos($listing, 'zeta.deferred'), strpos($listing, 'middle.deferred'));
            }
        }
    }

    public function testZeroRowsStillRenderTheUnchangedHeader(): void
    {
        $output = new BufferedOutput();
        $exitCode = $this->command(new EmptyStrategyProjectionProvider())->run(new ArrayInput([
            '--strategy' => 'inline',
        ]), $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(<<<'TABLE'
            +---------+------------+--------------------+
            | Type ID | Definition | Execution Strategy |
            +---------+------------+--------------------+

            TABLE, $output->fetch());
    }

    public function testInvalidStrategyInputReturnsTwoWithoutProviderWorkOrOutput(): void
    {
        foreach ([
            'missing value' => '--strategy',
            'duplicate occurrence' => '--strategy=inline --strategy=deferred',
            'unknown value' => '--strategy=custom',
            'case variant' => '--strategy=INLINE',
        ] as $label => $tokens) {
            StrategyProjectionProvider::$definitionsCalls = 0;
            $output = new BufferedOutput();
            $exitCode = $this->command(new StrategyProjectionProvider())->run(new StringInput($tokens), $output);

            self::assertSame(Command::INVALID, $exitCode, $label);
            self::assertSame('', $output->fetch(), $label);
            self::assertSame(0, StrategyProjectionProvider::$definitionsCalls, $label);
        }
    }

    public function testProviderExceptionRemainsUnchanged(): void
    {
        $this->expectExceptionObject(new RuntimeException('provider failure'));
        $this->command(new ThrowingStrategyProvider())->run(new ArrayInput([]), new BufferedOutput());
    }

    public function testCompilerExceptionRemainsUnchanged(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException('Operation definition requires OperationType exactly once.'),
        );
        $this->command(new InvalidStrategyProvider())->run(new ArrayInput([]), new BufferedOutput());
    }

    public function testLazyOperationListDefinitionExposesOnlyTheStrategyOption(): void
    {
        $application = Application::configure($this->directory())->create();
        $publicKernel = $application->console();
        $internalKernel = new ReflectionProperty($publicKernel, 'kernel')->getValue($publicKernel);
        $console = new ReflectionProperty($internalKernel, 'application')->getValue($internalKernel);
        $operationList = $console->find('operation:list');
        $strategy = $operationList->getDefinition()->getOption('strategy');

        self::assertTrue($strategy->isArray());
        self::assertTrue($strategy->isValueOptional());
        self::assertFalse($console->find('list')->getDefinition()->hasOption('strategy'));
        self::assertTrue($console->has('ls'));
    }

    private function command(OperationProvider $provider): ApplicationOperationListCommand
    {
        return new ApplicationOperationListCommand(
            new ApplicationConfigurationSnapshot($this->directory(), [], [$provider], [], []),
        );
    }
}

final class StrategyProjectionProvider implements OperationProvider
{
    public static int $definitionsCalls = 0;

    public function definitions(): iterable
    {
        self::$definitionsCalls++;

        return [
            StrategyDeferredOperation::class,
            StrategyInlineOperation::class,
            StrategyMiddleDeferredOperation::class,
            StrategyZetaInlineOperation::class,
            StrategyCustomOperation::class,
        ];
    }
}

final class EmptyStrategyProjectionProvider implements OperationProvider
{
    public static int $definitionsCalls = 0;

    public function definitions(): iterable
    {
        self::$definitionsCalls++;

        return [];
    }
}

final readonly class ThrowingStrategyProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        throw new RuntimeException('provider failure');
    }
}

final readonly class InvalidStrategyProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [IncompleteStrategyOperation::class];
    }
}

#[OperationType('zeta.deferred')]
#[DeferredAttribute]
#[Accepts(StrategyProjectionValue::class)]
#[HandledBy(StrategyProjectionHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class StrategyDeferredOperation implements Operation {}

#[OperationType('alpha.inline')]
#[Accepts(StrategyProjectionValue::class)]
#[HandledBy(StrategyProjectionHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class StrategyInlineOperation implements Operation {}

#[OperationType('middle.deferred')]
#[DeferredAttribute]
#[Accepts(StrategyProjectionValue::class)]
#[HandledBy(StrategyProjectionHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class StrategyMiddleDeferredOperation implements Operation {}

#[OperationType('zeta.inline')]
#[Accepts(StrategyProjectionValue::class)]
#[HandledBy(StrategyProjectionHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class StrategyZetaInlineOperation implements Operation {}

#[OperationType('custom.run')]
#[ExecuteWith(StrategyCustom::class)]
#[Accepts(StrategyProjectionValue::class)]
#[HandledBy(StrategyProjectionHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class StrategyCustomOperation implements Operation {}

final readonly class IncompleteStrategyOperation implements Operation {}

final readonly class StrategyCustom implements ExecutionStrategy {}

final readonly class StrategyProjectionValue implements OperationValue {}

final readonly class StrategyProjectionHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

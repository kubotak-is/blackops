<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Registry;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Internal\Registry\OperationDefinitionFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OperationDefinitionFactoryTest extends TestCase
{
    public function testCreatesOperationDefinitionsFromProviders(): void
    {
        $definitions = new OperationDefinitionFactory()->fromProviders([new FactoryOperationProvider()]);

        self::assertCount(1, $definitions);
        self::assertInstanceOf(FactoryOperation::class, $definitions[0]);
    }

    public function testRejectsOperationDefinitionWithRequiredConstructorArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationDefinitionFactory()->fromProviders([new RequiredArgumentOperationProvider()]);
    }
}

final readonly class FactoryOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [FactoryOperation::class];
    }
}

final readonly class RequiredArgumentOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [RequiredArgumentOperation::class];
    }
}

#[OperationType('factory.operation')]
#[Accepts(FactoryValue::class)]
#[HandledBy(FactoryHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class FactoryOperation implements Operation {}

#[OperationType('factory.required')]
#[Accepts(FactoryValue::class)]
#[HandledBy(FactoryHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class RequiredArgumentOperation implements Operation
{
    public function __construct(
        private string $value,
    ) {}
}

final readonly class FactoryValue implements OperationValue {}

final readonly class FactoryHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

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
use BlackOps\Internal\Registry\OperationProviderCompiler;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OperationProviderCompilerTest extends TestCase
{
    public function testCompilesOperationProvidersIntoRegistry(): void
    {
        $registry = new OperationProviderCompiler()->compile([new RegistryOperationProvider()]);

        self::assertSame(RegistryProviderOperation::class, $registry->findByTypeId('provider.registry')?->definition);
        self::assertSame('provider.registry', $registry->findByDefinition(RegistryProviderOperation::class)?->typeId);
    }

    public function testRejectsInvalidOperationDefinition(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationProviderCompiler()->compile([new InvalidRegistryOperationProvider()]);
    }

    public function testRejectsDuplicateOperationMetadata(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationProviderCompiler()->compile([
            new RegistryOperationProvider(),
            new DuplicateRegistryOperationProvider(),
        ]);
    }
}

final readonly class RegistryOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        yield RegistryProviderOperation::class;
    }
}

final readonly class InvalidRegistryOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        yield IncompleteRegistryProviderOperation::class;
    }
}

final readonly class DuplicateRegistryOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        yield DuplicateRegistryProviderOperation::class;
    }
}

#[OperationType('provider.registry')]
#[Accepts(RegistryProviderValue::class)]
#[HandledBy(RegistryProviderHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class RegistryProviderOperation implements Operation {}

#[OperationType('provider.registry')]
#[Accepts(RegistryProviderValue::class)]
#[HandledBy(RegistryProviderHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class DuplicateRegistryProviderOperation implements Operation {}

final readonly class IncompleteRegistryProviderOperation implements Operation {}

final readonly class RegistryProviderValue implements OperationValue {}

final readonly class RegistryProviderHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

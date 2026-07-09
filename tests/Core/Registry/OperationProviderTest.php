<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Registry;

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
use PHPUnit\Framework\TestCase;

final class OperationProviderTest extends TestCase
{
    public function testOperationProviderCanReturnDefinitionClassNames(): void
    {
        $provider = new PublicOperationProvider();

        self::assertSame([PublicProviderOperation::class], $provider->definitions());
    }
}

final readonly class PublicOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [PublicProviderOperation::class];
    }
}

#[OperationType('provider.public')]
#[Accepts(PublicProviderValue::class)]
#[HandledBy(PublicProviderHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class PublicProviderOperation implements Operation {}

final readonly class PublicProviderValue implements OperationValue {}

final readonly class PublicProviderHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

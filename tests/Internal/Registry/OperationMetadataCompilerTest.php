<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Registry;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OperationMetadataCompilerTest extends TestCase
{
    public function testCompilesRequiredMetadataAndDefaultsToInline(): void
    {
        $metadata = new OperationMetadataCompiler()->compile(MetadataOperationFixture::class);

        self::assertSame('welcome.show', $metadata->typeId);
        self::assertSame(MetadataOperationFixture::class, $metadata->definition);
        self::assertSame(MetadataValueFixture::class, $metadata->value);
        self::assertSame(MetadataHandlerFixture::class, $metadata->handler);
        self::assertSame(EmptyOutcome::class, $metadata->outcome);
        self::assertSame(Inline::class, $metadata->strategy);
    }

    public function testMissingMetadataIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OperationMetadataCompiler()->compile(IncompleteOperationFixture::class);
    }

    public function testExplicitStrategyIsCompiled(): void
    {
        $metadata = new OperationMetadataCompiler()->compile(ExplicitStrategyOperationFixture::class);

        self::assertSame(MetadataStrategyFixture::class, $metadata->strategy);
    }

    public function testInvalidContractClassIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OperationMetadataCompiler()->compile(InvalidValueOperationFixture::class);
    }
}

#[OperationType('welcome.show')]
#[Accepts(MetadataValueFixture::class)]
#[HandledBy(MetadataHandlerFixture::class)]
#[Returns(EmptyOutcome::class)]
final readonly class MetadataOperationFixture implements Operation {}

final readonly class MetadataValueFixture implements OperationValue {}

/** @implements OperationHandler<MetadataValueFixture, EmptyOutcome> */
final readonly class MetadataHandlerFixture implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

final readonly class IncompleteOperationFixture implements Operation {}

final readonly class MetadataStrategyFixture implements ExecutionStrategy {}

#[OperationType('welcome.explicit')]
#[Accepts(MetadataValueFixture::class)]
#[HandledBy(MetadataHandlerFixture::class)]
#[Returns(EmptyOutcome::class)]
#[ExecuteWith(MetadataStrategyFixture::class)]
final readonly class ExplicitStrategyOperationFixture implements Operation {}

#[OperationType('welcome.invalid')]
#[Accepts(\stdClass::class)]
#[HandledBy(MetadataHandlerFixture::class)]
#[Returns(EmptyOutcome::class)]
final readonly class InvalidValueOperationFixture implements Operation {}

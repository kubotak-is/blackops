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
use BlackOps\Core\ExecutionContext;
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

    public function testCompilesSelfHandledOperationWithoutHandledBy(): void
    {
        $metadata = new OperationMetadataCompiler()->compile(SelfHandledOperationFixture::class);

        self::assertSame(SelfHandledOperationFixture::class, $metadata->handler);
        self::assertSame(SelfHandledOperationFixture::class, $metadata->definition);
        self::assertFalse($metadata->typedSelfHandled);
    }

    public function testCompilesTypedSelfHandledValueOnly(): void
    {
        $metadata = new OperationMetadataCompiler()->compile(TypedSelfHandledOperationFixture::class);

        self::assertSame(TypedSelfHandledOperationFixture::class, $metadata->handler);
        self::assertTrue($metadata->typedSelfHandled);
        self::assertFalse($metadata->typedSelfHandledContext);
    }

    public function testCompilesTypedSelfHandledValueAndContext(): void
    {
        $metadata = new OperationMetadataCompiler()->compile(TypedContextSelfHandledOperationFixture::class);

        self::assertTrue($metadata->typedSelfHandled);
        self::assertTrue($metadata->typedSelfHandledContext);
    }

    public function testRejectsSelfHandledOperationWithHandledBy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not declare HandledBy');

        new OperationMetadataCompiler()->compile(AmbiguousHandlerOperationFixture::class);
    }

    public function testRejectsTypedSelfHandledOperationWithHandledBy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Typed self-handled');

        new OperationMetadataCompiler()->compile(AmbiguousTypedHandlerOperationFixture::class);
    }

    public function testRejectsAbstractTypedSelfHandledOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('instantiable');

        new OperationMetadataCompiler()->compile(AbstractTypedSelfHandledOperationFixture::class);
    }

    public function testRejectsOperationWithoutHandler(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a handle method');

        new OperationMetadataCompiler()->compile(MissingHandlerOperationFixture::class);
    }

    public function testInvalidContractClassIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OperationMetadataCompiler()->compile(InvalidValueOperationFixture::class);
    }

    public function testInvalidSeparateHandlerContractIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationMetadataCompiler()->compile(InvalidHandlerOperationFixture::class);
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

#[OperationType('welcome.invalid.handler')]
#[Accepts(MetadataValueFixture::class)]
#[HandledBy(\stdClass::class)]
#[Returns(EmptyOutcome::class)]
final readonly class InvalidHandlerOperationFixture implements Operation {}

#[OperationType('welcome.self.handled')]
#[Accepts(MetadataValueFixture::class)]
#[Returns(EmptyOutcome::class)]
final readonly class SelfHandledOperationFixture implements Operation, OperationHandler
{
    public function __construct(
        private string $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

#[OperationType('welcome.typed')]
#[Accepts(MetadataValueFixture::class)]
#[Returns(EmptyOutcome::class)]
final readonly class TypedSelfHandledOperationFixture implements Operation
{
    public function handle(MetadataValueFixture $value): OperationResult
    {
        return OperationResult::completed();
    }
}

#[OperationType('welcome.typed.context')]
#[Accepts(MetadataValueFixture::class)]
#[Returns(EmptyOutcome::class)]
final readonly class TypedContextSelfHandledOperationFixture implements Operation
{
    public function handle(MetadataValueFixture $value, ExecutionContext $context): OperationResult
    {
        return OperationResult::completed();
    }
}

#[OperationType('welcome.typed.abstract')]
#[Accepts(MetadataValueFixture::class)]
#[Returns(EmptyOutcome::class)]
abstract class AbstractTypedSelfHandledOperationFixture implements Operation
{
    abstract public function handle(MetadataValueFixture $value): OperationResult;
}

#[OperationType('welcome.ambiguous')]
#[Accepts(MetadataValueFixture::class)]
#[HandledBy(MetadataHandlerFixture::class)]
#[Returns(EmptyOutcome::class)]
final readonly class AmbiguousHandlerOperationFixture implements Operation, OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

#[OperationType('welcome.ambiguous.typed')]
#[Accepts(MetadataValueFixture::class)]
#[HandledBy(MetadataHandlerFixture::class)]
#[Returns(EmptyOutcome::class)]
final readonly class AmbiguousTypedHandlerOperationFixture implements Operation
{
    public function handle(MetadataValueFixture $value): OperationResult
    {
        return OperationResult::completed();
    }
}

#[OperationType('welcome.missing.handler')]
#[Accepts(MetadataValueFixture::class)]
#[Returns(EmptyOutcome::class)]
final readonly class MissingHandlerOperationFixture implements Operation {}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Execution\HandlerInvoker;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class HandlerInvokerTest extends TestCase
{
    public function testInvokesTypedValueOnlyHandler(): void
    {
        $handler = new TypedValueInvokeOperation();
        $value = new InvokeValue('typed');

        $result = new HandlerInvoker()->invoke(
            $this->metadata(TypedValueInvokeOperation::class),
            $handler,
            $this->envelope($handler, $value),
        );

        self::assertTrue($result->isCompleted());
        self::assertSame($value, $handler->received);
    }

    public function testInvokesTypedValueAndExplicitContext(): void
    {
        $handler = new TypedContextInvokeOperation();
        $value = new InvokeValue('context');
        $context = $this->context('019f32ab-2be0-7b38-a0a7-1ab2f9687811');
        $metadata = $this->metadata(TypedContextInvokeOperation::class, context: true);

        new HandlerInvoker()->invoke($metadata, $handler, $this->envelope($handler, $value), $context);

        self::assertSame($value, $handler->received);
        self::assertSame($context, $handler->context);
    }

    public function testInvokesLegacyHandlerWithEnvelope(): void
    {
        $handler = new LegacyInvokeOperation();
        $envelope = $this->envelope($handler, new InvokeValue('legacy'));
        $metadata = $this->metadata(LegacyInvokeOperation::class, typed: false);

        new HandlerInvoker()->invoke($metadata, $handler, $envelope);

        self::assertSame($envelope, $handler->received);
    }

    public function testRejectsRuntimeValueMismatch(): void
    {
        $handler = new TypedValueInvokeOperation();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('value');

        new HandlerInvoker()->invoke(
            $this->metadata(TypedValueInvokeOperation::class),
            $handler,
            $this->envelope($handler, new OtherInvokeValue()),
        );
    }

    public function testRejectsRuntimeResultMismatch(): void
    {
        $handler = new InvalidResultInvokeOperation();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('OperationResult');

        new HandlerInvoker()->invoke(
            $this->metadata(InvalidResultInvokeOperation::class),
            $handler,
            $this->envelope($handler, new InvokeValue('invalid')),
        );
    }

    public function testRejectsHandlerClassMismatch(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('metadata');

        new HandlerInvoker()->invoke(
            $this->metadata(TypedValueInvokeOperation::class),
            new InvalidResultInvokeOperation(),
            $this->envelope(new TypedValueInvokeOperation(), new InvokeValue('invalid')),
        );
    }

    public function testRejectsContextFlagWithoutTypedMode(): void
    {
        $handler = new LegacyInvokeOperation();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('invocation metadata');

        new HandlerInvoker()->invoke(
            $this->metadata(LegacyInvokeOperation::class, typed: false, context: true),
            $handler,
            $this->envelope($handler, new InvokeValue('invalid-mode')),
        );
    }

    public function testRejectsTypedSeparateHandlerMetadata(): void
    {
        $handler = new TypedValueInvokeOperation();
        $metadata = new OperationMetadata(
            'invoke.test',
            TypedContextInvokeOperation::class,
            InvokeValue::class,
            TypedValueInvokeOperation::class,
            EmptyOutcome::class,
            Inline::class,
            true,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('self-handled');

        new HandlerInvoker()->invoke($metadata, $handler, $this->envelope($handler, new InvokeValue('separate')));
    }

    public function testRejectsTypedHandlerThatIsNotAnOperation(): void
    {
        $handler = new NonOperationTypedInvokeHandler();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('self-handled');

        new HandlerInvoker()->invoke(
            $this->metadata(NonOperationTypedInvokeHandler::class),
            $handler,
            $this->envelope(new TypedValueInvokeOperation(), new InvokeValue('not-operation')),
        );
    }

    /** @param class-string $handler */
    private function metadata(string $handler, bool $typed = true, bool $context = false): OperationMetadata
    {
        return new OperationMetadata(
            'invoke.test',
            $handler,
            InvokeValue::class,
            $handler,
            EmptyOutcome::class,
            Inline::class,
            $typed,
            $context,
        );
    }

    private function envelope(Operation $definition, OperationValue $value): OperationEnvelope
    {
        return new OperationEnvelope($definition, $value, $this->context(), new Inline());
    }

    private function context(string $id = '019f32ab-2be0-7b38-a0a7-1ab2f9687810'): ExecutionContext
    {
        return new ExecutionContext(
            OperationId::fromString($id),
            new DateTimeImmutable('2026-07-13T00:00:00Z'),
            CorrelationId::fromString($id),
        );
    }
}

final readonly class InvokeValue implements OperationValue
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class OtherInvokeValue implements OperationValue {}

final class TypedValueInvokeOperation implements Operation
{
    public ?InvokeValue $received = null;

    public function handle(InvokeValue $value): OperationResult
    {
        $this->received = $value;

        return OperationResult::completed();
    }
}

final class TypedContextInvokeOperation implements Operation
{
    public ?InvokeValue $received = null;
    public ?ExecutionContext $context = null;

    public function handle(InvokeValue $value, ExecutionContext $context): OperationResult
    {
        $this->received = $value;
        $this->context = $context;

        return OperationResult::completed();
    }
}

final class InvalidResultInvokeOperation implements Operation
{
    public function handle(InvokeValue $value): mixed
    {
        return 'invalid';
    }
}

final class NonOperationTypedInvokeHandler
{
    public function handle(InvokeValue $value): OperationResult
    {
        return OperationResult::completed();
    }
}

final class LegacyInvokeOperation implements Operation, OperationHandler
{
    public ?OperationEnvelope $received = null;

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $this->received = $operation;

        return OperationResult::completed();
    }
}

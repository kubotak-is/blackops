<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Execution\HandlerInvoker;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class HandlerInvokerTest extends TestCase
{
    public function testNormalizesNativeOutcome(): void
    {
        $handler = new NativeOutcomeInvokeOperation();
        $metadata = $this->metadata($handler::class, outcome: InvokeOutcome::class, mode: 'outcome');

        $result = new HandlerInvoker()->invoke(
            $metadata,
            $handler,
            $this->envelope($handler, new InvokeValue('native')),
        );

        self::assertInstanceOf(InvokeOutcome::class, $result->outcome());
    }

    public function testNormalizesNativeVoid(): void
    {
        $handler = new NativeVoidInvokeOperation();
        $metadata = $this->metadata($handler::class, mode: 'void');

        $result = new HandlerInvoker()->invoke($metadata, $handler, $this->envelope($handler, new InvokeValue('void')));

        self::assertInstanceOf(EmptyOutcome::class, $result->outcome());
    }

    public function testNormalizesRejectedException(): void
    {
        $handler = new RejectingNativeInvokeOperation();
        $metadata = $this->metadata($handler::class, outcome: InvokeOutcome::class, mode: 'outcome');

        $result = new HandlerInvoker()->invoke(
            $metadata,
            $handler,
            $this->envelope($handler, new InvokeValue('rejected')),
        );

        self::assertTrue($result->isRejected());
        self::assertSame('invoke.rejected', $result->rejectionReason()->code());
    }

    public function testDoesNotNormalizeOtherThrowable(): void
    {
        $handler = new ThrowingNativeInvokeOperation();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('temporary');

        new HandlerInvoker()->invoke(
            $this->metadata($handler::class, outcome: InvokeOutcome::class, mode: 'outcome'),
            $handler,
            $this->envelope($handler, new InvokeValue('failure')),
        );
    }

    public function testRejectsNativeOutcomeMismatch(): void
    {
        $handler = new NativeOutcomeInvokeOperation();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('outcome');

        new HandlerInvoker()->invoke(
            $this->metadata($handler::class, outcome: EmptyOutcome::class, mode: 'outcome'),
            $handler,
            $this->envelope($handler, new InvokeValue('mismatch')),
        );
    }

    public function testRejectsUnknownTypedModeBeforeHandlerInvocation(): void
    {
        $handler = new CountingVoidInvokeOperation();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('invocation mode');

        try {
            new HandlerInvoker()->invoke(
                $this->metadata($handler::class, mode: 'unknown'),
                $handler,
                $this->envelope($handler, new InvokeValue('unknown-mode')),
            );
        } finally {
            self::assertSame(0, $handler->invocations);
        }
    }

    public function testRejectsVoidModeWithNonEmptyOutcomeBeforeHandlerInvocation(): void
    {
        $handler = new CountingVoidInvokeOperation();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('outcome metadata');

        try {
            new HandlerInvoker()->invoke(
                $this->metadata($handler::class, outcome: InvokeOutcome::class, mode: 'void'),
                $handler,
                $this->envelope($handler, new InvokeValue('void-outcome-mismatch')),
            );
        } finally {
            self::assertSame(0, $handler->invocations);
        }
    }

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
    /** @param class-string<Outcome> $outcome */
    private function metadata(
        string $handler,
        bool $typed = true,
        bool $context = false,
        string $outcome = EmptyOutcome::class,
        ?string $mode = null,
    ): OperationMetadata {
        return new OperationMetadata(
            'invoke.test',
            $handler,
            InvokeValue::class,
            $handler,
            $outcome,
            Inline::class,
            $typed,
            $context,
            $mode,
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

final readonly class InvokeOutcome implements Outcome {}

final readonly class NativeOutcomeInvokeOperation implements Operation
{
    public function handle(InvokeValue $value): InvokeOutcome
    {
        return new InvokeOutcome();
    }
}

final readonly class NativeVoidInvokeOperation implements Operation
{
    public function handle(InvokeValue $value): void {}
}

final class CountingVoidInvokeOperation implements Operation
{
    public int $invocations = 0;

    public function handle(InvokeValue $value): void
    {
        ++$this->invocations;
    }
}

final readonly class RejectingNativeInvokeOperation implements Operation
{
    public function handle(InvokeValue $value): InvokeOutcome
    {
        throw OperationRejectedException::businessRule('invoke.rejected');
    }
}

final readonly class ThrowingNativeInvokeOperation implements Operation
{
    public function handle(InvokeValue $value): InvokeOutcome
    {
        throw new \RuntimeException('temporary');
    }
}

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

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationEnvelopeTest extends TestCase
{
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testEnvelopeIsPublicFinalReadonlyClass(): void
    {
        $reflection = new ReflectionClass(OperationEnvelope::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }

    public function testGettersReturnTheProvidedValues(): void
    {
        $definition = new EnvelopeOperationFixture();
        $value = new EnvelopeValueFixture('welcome');
        $context = $this->context();
        $strategy = new Inline();

        $envelope = new OperationEnvelope($definition, $value, $context, $strategy);

        self::assertSame($definition, $envelope->definition());
        self::assertSame($value, $envelope->value());
        self::assertSame($context, $envelope->context());
        self::assertSame($strategy, $envelope->strategy());
    }

    public function testConvenienceMethodsDelegateToExecutionContext(): void
    {
        $context = $this->context();
        $envelope = new OperationEnvelope(
            new EnvelopeOperationFixture(),
            new EnvelopeValueFixture('welcome'),
            $context,
            new Inline(),
        );

        self::assertSame($context->operationId(), $envelope->id());
        self::assertSame($context->receivedAt(), $envelope->receivedAt());
    }

    public function testEnvelopeDoesNotDuplicateIdentityState(): void
    {
        $properties = array_map(
            static fn(\ReflectionProperty $property): string => $property->getName(),
            new ReflectionClass(OperationEnvelope::class)->getProperties(),
        );

        sort($properties);

        self::assertSame(['context', 'definition', 'strategy', 'value'], $properties);
    }

    public function testEnvelopeDeclaresOperationValueGeneric(): void
    {
        $docComment = new ReflectionClass(OperationEnvelope::class)->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@template-covariant TValue of OperationValue', $docComment);
        self::assertStringContainsString(
            '@param TValue $value',
            (string) new ReflectionClass(OperationEnvelope::class)->getConstructor()?->getDocComment(),
        );
    }

    private function context(): ExecutionContext
    {
        return new ExecutionContext(
            OperationId::fromString(self::OPERATION_ID),
            new DateTimeImmutable('2026-07-06T00:00:00.000000', new DateTimeZone('UTC')),
            CorrelationId::fromString(self::OPERATION_ID),
        );
    }
}

final readonly class EnvelopeOperationFixture implements Operation {}

final readonly class EnvelopeValueFixture implements OperationValue
{
    public function __construct(
        public string $message,
    ) {}
}

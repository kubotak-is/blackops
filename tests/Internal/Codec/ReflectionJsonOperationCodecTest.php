<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Codec;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Codec\OperationCodecException;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReflectionJsonOperationCodecTest extends TestCase
{
    public function testEncodesAndDecodesOperationValueAndExecutionContext(): void
    {
        $codec = new ReflectionJsonOperationCodec();
        $metadata = $this->metadata(CodecReportValue::class);
        $context = new ExecutionContext(
            OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687701'),
            new DateTimeImmutable('2026-07-10T00:00:00.123456+09:00'),
            CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687702'),
            CausationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687703'),
            new AttemptContext(
                AttemptId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687704'),
                2,
                new DateTimeImmutable('2026-07-10T00:01:00.654321+09:00'),
            ),
            new DateTimeImmutable('2026-07-10T01:00:00.000000+09:00'),
        );

        $encoded = $codec->encode($metadata, new CodecReportValue('weekly', 7, true, ['finance']), $context);

        self::assertSame('codec.report', $encoded->operationType());
        self::assertSame(1, $encoded->schemaVersion());
        self::assertSame('{"name":"weekly","priority":7,"notify":true,"tags":["finance"]}', $encoded->encodedPayload());
        self::assertStringContainsString(
            '"operation_id":"019f32ab-2be0-7b38-a0a7-1ab2f9687701"',
            $encoded->encodedContext(),
        );

        $decodedValue = $codec->decodeValue($metadata, $encoded->schemaVersion(), $encoded->encodedPayload());
        $decodedContext = $codec->decodeContext($encoded->schemaVersion(), $encoded->encodedContext());

        self::assertInstanceOf(CodecReportValue::class, $decodedValue);
        self::assertSame('weekly', $decodedValue->name);
        self::assertSame(7, $decodedValue->priority);
        self::assertTrue($decodedValue->notify);
        self::assertSame(['finance'], $decodedValue->tags);
        self::assertSame($context->operationId()->toString(), $decodedContext->operationId()->toString());
        self::assertSame('2026-07-09T15:00:00.123456Z', $decodedContext->receivedAt()->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame($context->correlationId()->toString(), $decodedContext->correlationId()->toString());
        self::assertSame($context->causationId()?->toString(), $decodedContext->causationId()?->toString());
        self::assertSame(2, $decodedContext->attempt()?->number());
        self::assertSame('2026-07-09T16:00:00.000000Z', $decodedContext->deadline()?->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testDecodesConstructorDefaultsAndNullableParameters(): void
    {
        $codec = new ReflectionJsonOperationCodec();
        $metadata = $this->metadata(CodecDefaultValue::class);

        $value = $codec->decodeValue($metadata, 1, '{"name":"monthly"}');

        self::assertInstanceOf(CodecDefaultValue::class, $value);
        self::assertSame('monthly', $value->name);
        self::assertNull($value->note);
        self::assertSame(1, $value->priority);
    }

    public function testRejectsUnsupportedValuePropertyShape(): void
    {
        $codec = new ReflectionJsonOperationCodec();

        $this->expectException(OperationCodecException::class);

        $codec->encode(
            $this->metadata(CodecObjectValue::class),
            new CodecObjectValue(new \stdClass()),
            $this->context(),
        );
    }

    public function testRejectsUnsupportedSchemaVersion(): void
    {
        $codec = new ReflectionJsonOperationCodec();

        $this->expectException(OperationCodecException::class);

        $codec->decodeValue($this->metadata(CodecReportValue::class), 2, '{}');
    }

    public function testRejectsInvalidContextJson(): void
    {
        $codec = new ReflectionJsonOperationCodec();

        $this->expectException(OperationCodecException::class);

        $codec->decodeContext(1, '{"operation_id":null}');
    }

    /**
     * @param class-string<OperationValue> $value
     */
    private function metadata(string $value): OperationMetadata
    {
        return new OperationMetadata(
            'codec.report',
            CodecReportOperation::class,
            $value,
            CodecReportHandler::class,
            EmptyOutcome::class,
            Deferred::class,
        );
    }

    private function context(): ExecutionContext
    {
        return new ExecutionContext(
            OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687705'),
            new DateTimeImmutable('2026-07-10T00:00:00.000000Z'),
            CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687705'),
        );
    }
}

final readonly class CodecReportOperation implements Operation {}

final readonly class CodecReportValue implements OperationValue
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $name,
        public int $priority,
        public bool $notify,
        public array $tags = [],
    ) {}
}

final readonly class CodecDefaultValue implements OperationValue
{
    public function __construct(
        public string $name,
        public ?string $note = null,
        public int $priority = 1,
    ) {}
}

final readonly class CodecObjectValue implements OperationValue
{
    public function __construct(
        public object $unsupported,
    ) {}
}

abstract class CodecReportHandler implements OperationHandler {}

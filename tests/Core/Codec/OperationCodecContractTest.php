<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Codec;

use BlackOps\Core\Codec\EncodedOperationMessage;
use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\Codec\OperationCodecException;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use PHPUnit\Framework\TestCase;

final class OperationCodecContractTest extends TestCase
{
    public function testEncodedMessageKeepsOperationTypeSchemaPayloadAndContext(): void
    {
        $message = new EncodedOperationMessage('report.generate', 1, '{}', '{"operation_id":"id"}');

        self::assertSame('report.generate', $message->operationType());
        self::assertSame(1, $message->schemaVersion());
        self::assertSame('{}', $message->encodedPayload());
        self::assertSame('{"operation_id":"id"}', $message->encodedContext());
    }

    public function testEncodedMessageRejectsInvalidFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EncodedOperationMessage('', 1, '{}', '{}');
    }

    public function testCodecContractIsPublicInterface(): void
    {
        self::assertTrue(interface_exists(OperationCodec::class));
        self::assertTrue(is_subclass_of(CodecContractValue::class, OperationValue::class));
        self::assertTrue(is_subclass_of(CodecContractOperation::class, Operation::class));
        self::assertTrue(is_subclass_of(OperationCodecException::class, \RuntimeException::class));
    }
}

final readonly class CodecContractOperation implements Operation {}

final readonly class CodecContractValue implements OperationValue {}

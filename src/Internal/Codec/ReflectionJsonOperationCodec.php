<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

use BlackOps\Core\Codec\EncodedOperationMessage;
use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\Codec\OperationCodecException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;

final readonly class ReflectionJsonOperationCodec implements OperationCodec
{
    private const SCHEMA_VERSION = 1;

    public function __construct(
        private JsonDocumentCodec $json = new JsonDocumentCodec(),
        private OperationValueNormalizer $values = new OperationValueNormalizer(),
        private OperationValueHydrator $hydrator = new OperationValueHydrator(),
        private ExecutionContextJsonCodec $contexts = new ExecutionContextJsonCodec(),
    ) {}

    public function encode(
        OperationMetadata $metadata,
        OperationValue $value,
        ExecutionContext $context,
    ): EncodedOperationMessage {
        if (!$value instanceof $metadata->value) {
            throw new OperationCodecException('Operation value does not match registered metadata.');
        }

        return new EncodedOperationMessage(
            $metadata->typeId,
            self::SCHEMA_VERSION,
            $this->json->encode($this->values->normalize($value)),
            $this->contexts->encode($context),
        );
    }

    public function decodeValue(OperationMetadata $metadata, int $schemaVersion, string $encodedPayload): OperationValue
    {
        $this->assertSupportedSchema($schemaVersion);

        return $this->hydrator->hydrate($metadata->value, $this->json->decodeObject($encodedPayload));
    }

    public function decodeContext(int $schemaVersion, string $encodedContext): ExecutionContext
    {
        $this->assertSupportedSchema($schemaVersion);

        return $this->contexts->decode($encodedContext);
    }

    private function assertSupportedSchema(int $schemaVersion): void
    {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new OperationCodecException('Operation codec schema version is not supported.');
        }
    }
}

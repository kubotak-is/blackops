<?php

declare(strict_types=1);

namespace BlackOps\Core\Codec;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;

#[PublicApi]
interface OperationCodec
{
    public function encode(
        OperationMetadata $metadata,
        OperationValue $value,
        ExecutionContext $context,
    ): EncodedOperationMessage;

    public function decodeValue(
        OperationMetadata $metadata,
        int $schemaVersion,
        string $encodedPayload,
    ): OperationValue;

    public function decodeContext(int $schemaVersion, string $encodedContext): ExecutionContext;
}

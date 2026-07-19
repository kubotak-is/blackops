<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use RuntimeException;

final class OperationStatusSourceException extends RuntimeException
{
    private function __construct(
        public readonly OperationStatusSourceFailure $failure,
    ) {
        parent::__construct('Operation status source failed.');
    }

    public static function storageFailed(): self
    {
        return new self(OperationStatusSourceFailure::Storage);
    }

    public static function decodeFailed(): self
    {
        return new self(OperationStatusSourceFailure::Decode);
    }

    public static function integrityFailed(): self
    {
        return new self(OperationStatusSourceFailure::Integrity);
    }
}

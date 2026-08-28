<?php

declare(strict_types=1);

namespace BlackOps\Core\Queue;

use RuntimeException;
use Throwable;

final class QueueStatusSourceException extends RuntimeException
{
    private function __construct(
        public readonly QueueStatusFailure $failure,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Queue status source failed.', previous: $previous);
    }

    public static function configurationInvalid(?Throwable $previous = null): self
    {
        return new self(QueueStatusFailure::Configuration, $previous);
    }

    public static function storageUnavailable(?Throwable $previous = null): self
    {
        return new self(QueueStatusFailure::Storage, $previous);
    }

    public static function integrityFailed(?Throwable $previous = null): self
    {
        return new self(QueueStatusFailure::Integrity, $previous);
    }

    public static function configuration(?Throwable $previous = null): self
    {
        return self::configurationInvalid($previous);
    }

    public static function storage(?Throwable $previous = null): self
    {
        return self::storageUnavailable($previous);
    }

    public static function integrity(?Throwable $previous = null): self
    {
        return self::integrityFailed($previous);
    }
}

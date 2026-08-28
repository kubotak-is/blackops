<?php

declare(strict_types=1);

namespace BlackOps\Core\Outbox;

use RuntimeException;
use Throwable;

final class OutboxStatusSourceException extends RuntimeException
{
    private function __construct(
        public readonly OutboxStatusFailure $failure,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Outbox status source failed.', previous: $previous);
    }

    public static function configurationInvalid(?Throwable $previous = null): self
    {
        return new self(OutboxStatusFailure::Configuration, $previous);
    }

    public static function storageUnavailable(?Throwable $previous = null): self
    {
        return new self(OutboxStatusFailure::Storage, $previous);
    }

    public static function integrityFailed(?Throwable $previous = null): self
    {
        return new self(OutboxStatusFailure::Integrity, $previous);
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

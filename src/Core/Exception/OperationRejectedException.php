<?php

declare(strict_types=1);

namespace BlackOps\Core\Exception;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Rejection\RejectionReason;
use RuntimeException;

#[PublicApi]
final class OperationRejectedException extends RuntimeException
{
    private function __construct(
        private readonly RejectionReason $reason,
    ) {
        parent::__construct('Operation was rejected.');
    }

    public static function validation(string $code): self
    {
        return new self(RejectionReason::validation($code));
    }

    public static function unauthorized(string $code): self
    {
        return new self(RejectionReason::unauthorized($code));
    }

    public static function forbidden(string $code): self
    {
        return new self(RejectionReason::forbidden($code));
    }

    public static function notFound(string $code): self
    {
        return new self(RejectionReason::notFound($code));
    }

    public static function conflict(string $code): self
    {
        return new self(RejectionReason::conflict($code));
    }

    public static function businessRule(string $code): self
    {
        return new self(RejectionReason::businessRule($code));
    }

    public function reason(): RejectionReason
    {
        return $this->reason;
    }
}

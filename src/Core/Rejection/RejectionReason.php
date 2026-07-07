<?php

declare(strict_types=1);

namespace BlackOps\Core\Rejection;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class RejectionReason
{
    private function __construct(
        private RejectionCategory $category,
        private string $code,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $code)) {
            throw new InvalidArgumentException('Rejection reason requires a valid stable code.');
        }
    }

    public static function validation(string $code): self
    {
        return new self(RejectionCategory::Validation, $code);
    }

    public static function unauthorized(string $code): self
    {
        return new self(RejectionCategory::Unauthorized, $code);
    }

    public static function forbidden(string $code): self
    {
        return new self(RejectionCategory::Forbidden, $code);
    }

    public static function notFound(string $code): self
    {
        return new self(RejectionCategory::NotFound, $code);
    }

    public static function conflict(string $code): self
    {
        return new self(RejectionCategory::Conflict, $code);
    }

    public static function businessRule(string $code): self
    {
        return new self(RejectionCategory::BusinessRule, $code);
    }

    public function category(): RejectionCategory
    {
        return $this->category;
    }

    public function code(): string
    {
        return $this->code;
    }
}

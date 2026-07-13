<?php

declare(strict_types=1);

namespace BlackOps\Core\Rejection;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Validation\Violation;
use InvalidArgumentException;

#[PublicApi]
final readonly class RejectionReason
{
    /** @var list<Violation> */
    private array $violations;

    private function __construct(
        private RejectionCategory $category,
        private string $code,
        array $violations = [],
    ) {
        if (!preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $code)) {
            throw new InvalidArgumentException('Rejection reason requires a valid stable code.');
        }

        if (!self::isViolationList($violations)) {
            throw new InvalidArgumentException('Rejection reason violations must be a list of validation violations.');
        }

        /** @var list<Violation> $violations */
        $this->violations = $violations;
    }

    /** @param array<array-key, mixed> $violations */
    private static function isViolationList(array $violations): bool
    {
        return (
            array_is_list($violations)
            && !array_any($violations, static fn(mixed $violation): bool => !$violation instanceof Violation)
        );
    }

    /**
     * @param list<Violation> $violations
     */
    public static function validation(string $code, array $violations = []): self
    {
        return new self(RejectionCategory::Validation, $code, $violations);
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

    /**
     * @return list<Violation>
     */
    public function violations(): array
    {
        return $this->violations;
    }
}

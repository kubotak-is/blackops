<?php

declare(strict_types=1);

namespace BlackOps\Core\Identifier;

use BlackOps\Core\Exception\InvalidIdentifierException;

/**
 * Identifier value object 共通実装。5種類の具象ID型が使用し、
 * 小文字RFC 4122形式への正規化とUUID Version 7検証を集約する。
 *
 * このTraitはPHP Public APIではなく、識別子型の内部実装詳細である。
 */
trait IdentifierBehavior
{
    private readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self(self::parse($value));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function parse(string $value): string
    {
        $normalized = strtolower($value);

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $normalized)) {
            throw InvalidIdentifierException::invalidUuidV7(static::class);
        }

        return $normalized;
    }
}

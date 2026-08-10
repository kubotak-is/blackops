<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

use InvalidArgumentException;

final readonly class FrameworkProxyProfile
{
    public const FRAMEWORK = 'framework';

    private function __construct(
        public string $value,
    ) {}

    public static function framework(): self
    {
        return new self(self::FRAMEWORK);
    }

    public static function from(string|self $profile): self
    {
        if ($profile instanceof self) {
            return $profile;
        }

        if ($profile === self::FRAMEWORK) {
            return new self($profile);
        }

        throw new InvalidArgumentException('Framework proxy profile is invalid.');
    }

    public function equals(string|self $profile): bool
    {
        return $this->value === self::from($profile)->value;
    }
}

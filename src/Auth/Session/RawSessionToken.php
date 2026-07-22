<?php

declare(strict_types=1);

namespace BlackOps\Auth\Session;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;
use SensitiveParameter;

#[PublicApi]
final readonly class RawSessionToken
{
    private function __construct(
        #[SensitiveParameter]
        private string $value,
    ) {}

    /**
     * This factory converts entropy already obtained from the framework's internal random source.
     * Applications issue persisted sessions through SessionManager instead of calling it directly.
     */
    public static function fromRandomBytes(#[SensitiveParameter] string $bytes): self
    {
        if (strlen($bytes) !== 32) {
            throw new InvalidArgumentException('Session token entropy must contain exactly 32 bytes.');
        }

        return new self(rtrim(string: strtr(string: base64_encode($bytes), from: '+/', to: '-_'), characters: '='));
    }

    public function reveal(): string
    {
        return $this->value;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[sensitive]'];
    }
}

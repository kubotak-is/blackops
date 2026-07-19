<?php

declare(strict_types=1);

namespace App\Identity;

use InvalidArgumentException;

final readonly class SessionSettings
{
    public const int DEFAULT_TTL_SECONDS = 28_800;

    public int $ttlSeconds;

    public function __construct(int $ttlSeconds = self::DEFAULT_TTL_SECONDS)
    {
        if ($ttlSeconds <= 0) {
            throw new InvalidArgumentException('Session TTL must be a positive number of seconds.');
        }

        $this->ttlSeconds = $ttlSeconds;
    }

    /** @param array<string, string> $environment */
    public static function fromEnvironment(array $environment): self
    {
        $configured = $environment['SESSION_TTL_SECONDS'] ?? (string) self::DEFAULT_TTL_SECONDS;
        if (!preg_match('/\\A[1-9][0-9]*\\z/D', $configured)) {
            throw new InvalidArgumentException('SESSION_TTL_SECONDS must be a positive integer.');
        }

        $seconds = filter_var($configured, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]);
        if (!is_int($seconds)) {
            throw new InvalidArgumentException('SESSION_TTL_SECONDS is outside the supported range.');
        }

        return new self($seconds);
    }
}

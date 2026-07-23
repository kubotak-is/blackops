<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use InvalidArgumentException;

/** A framework-owned response projection safe to replay without caller headers. */
final readonly class IdempotencyResponseSnapshot
{
    public const int VERSION = 1;

    /** @var array<string, string> */
    private array $headers;

    /** @param array<string, string> $headers */
    public function __construct(
        private int $version,
        private int $status,
        array $headers,
        private string $body,
    ) {
        if ($version !== self::VERSION || $status < 100 || $status > 599) {
            throw new InvalidArgumentException('Idempotency response snapshot is invalid.');
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            $name = strtolower(trim($name));
            if (!in_array($name, ['content-type', 'location', 'retry-after'], strict: true)) {
                throw new InvalidArgumentException('Idempotency response snapshot contains an unsafe header.');
            }
            if ($value === '') {
                throw new InvalidArgumentException('Idempotency response snapshot header must not be empty.');
            }
            $normalized[$name] = $value;
        }

        $this->headers = $normalized;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }
}

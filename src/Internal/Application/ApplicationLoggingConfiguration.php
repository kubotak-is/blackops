<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;
use Psr\Log\LogLevel;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class ApplicationLoggingConfiguration
{
    /** @var list<string> */
    private const LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    private function __construct(
        public string $stream,
        public string $channel,
        public string $minimumLevel,
    ) {}

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        /** @var mixed $logging */
        $logging = $configuration['logging'] ?? [];
        if (!is_array($logging) || array_diff(array_keys($logging), ['backend']) !== []) {
            throw new InvalidArgumentException('Application logging configuration is invalid.');
        }
        /** @var mixed $backend */
        $backend = $logging['backend'] ?? [];
        if (!is_array($backend) || array_diff(array_keys($backend), [
            'driver',
            'stream',
            'channel',
            'minimum_level',
        ]) !== []) {
            throw new InvalidArgumentException('Application logging backend configuration is invalid.');
        }

        /** @var mixed $driver */
        $driver = $backend['driver'] ?? 'jsonl';
        /** @var mixed $stream */
        $stream = $backend['stream'] ?? 'php://stderr';
        /** @var mixed $channel */
        $channel = $backend['channel'] ?? 'blackops';
        /** @var mixed $minimumLevel */
        $minimumLevel = $backend['minimum_level'] ?? LogLevel::INFO;
        if ($driver !== 'jsonl' || !is_string($stream) || !self::streamIsAllowed($stream)) {
            throw new InvalidArgumentException('Application logging backend configuration is invalid.');
        }
        if (!is_string($channel) || !self::channelIsAllowed($channel)) {
            throw new InvalidArgumentException('Application logging channel configuration is invalid.');
        }
        if (!is_string($minimumLevel) || !in_array($minimumLevel, self::LEVELS, strict: true)) {
            throw new InvalidArgumentException('Application logging level configuration is invalid.');
        }

        return new self($stream, $channel, $minimumLevel);
    }

    private static function streamIsAllowed(string $stream): bool
    {
        if ($stream === 'php://stderr' || $stream === 'php://stdout') {
            return true;
        }

        return (
            $stream !== ''
            && str_starts_with($stream, '/')
            && !str_contains($stream, "\0")
            && !str_contains($stream, '://')
        );
    }

    private static function channelIsAllowed(string $channel): bool
    {
        return $channel !== '' && trim($channel) === $channel && preg_match('/\p{Cc}/u', $channel) === 0;
    }
}

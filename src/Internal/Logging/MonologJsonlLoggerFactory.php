<?php

declare(strict_types=1);

namespace BlackOps\Internal\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final readonly class MonologJsonlLoggerFactory
{
    public const DEFAULT_CHANNEL = 'blackops';
    public const DEFAULT_LEVEL = LogLevel::INFO;

    /**
     * @param resource|string $stream
     */
    public function create(
        mixed $stream,
        string $channel = self::DEFAULT_CHANNEL,
        string|int $minimumLevel = self::DEFAULT_LEVEL,
    ): LoggerInterface {
        $handler = new StreamHandler($stream, $minimumLevel);
        $handler->setFormatter(new StructuredJsonlFormatter());

        return new Logger($channel, [$handler]);
    }
}

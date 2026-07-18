<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationDiagnosticsViewerConfiguration
{
    private function __construct(
        public bool $enabled,
        public string $bind,
        public int $port,
    ) {}

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        $diagnostics = $configuration['diagnostics'] ?? [];
        /** @var mixed $viewer */
        $viewer = $diagnostics['viewer'] ?? [];
        if (!is_array($viewer)) {
            throw new InvalidArgumentException('diagnostics.viewer must be an array.');
        }

        /** @var mixed $enabled */
        $enabled = $viewer['enabled'] ?? false;
        /** @var mixed $bind */
        $bind = $viewer['bind'] ?? '127.0.0.1';
        /** @var mixed $port */
        $port = $viewer['port'] ?? 8082;
        if (!is_bool($enabled)) {
            throw new InvalidArgumentException('diagnostics.viewer.enabled must be a boolean.');
        }
        if (!is_string($bind) || !in_array($bind, ['127.0.0.1', '::1'], strict: true)) {
            throw new InvalidArgumentException('diagnostics.viewer.bind must be a loopback address.');
        }
        if (!is_int($port) || $port < 1 || $port > 65_535) {
            throw new InvalidArgumentException('diagnostics.viewer.port must be an integer between 1 and 65535.');
        }

        return new self($enabled, $bind, $port);
    }

    public function authority(): string
    {
        return $this->bind === '::1' ? sprintf('[::1]:%d', $this->port) : sprintf('%s:%d', $this->bind, $this->port);
    }

    public function socketAddress(): string
    {
        return 'tcp://' . $this->authority();
    }
}

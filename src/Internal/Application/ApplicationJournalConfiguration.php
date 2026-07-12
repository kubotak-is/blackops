<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Journal\JournalDeliveryPolicy;
use InvalidArgumentException;

final readonly class ApplicationJournalConfiguration
{
    private function __construct(
        public bool $enabled,
        public ?string $path,
        public JournalDeliveryPolicy $delivery,
    ) {}

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        $jsonl = self::jsonl($configuration['journal']['jsonl'] ?? []);

        $enabled = self::enabled($jsonl['enabled'] ?? false);
        if (!$enabled) {
            return new self(false, null, JournalDeliveryPolicy::BestEffort);
        }

        $path = self::path($jsonl['path'] ?? null);

        $parent = dirname($path);
        if (!is_dir($parent) || !is_writable($parent)) {
            throw new InvalidArgumentException(
                'Configuration key "journal.jsonl.path" requires a writable parent directory.',
            );
        }

        $delivery = match ($jsonl['delivery'] ?? null) {
            'best_effort' => JournalDeliveryPolicy::BestEffort,
            'required' => JournalDeliveryPolicy::Required,
            default => throw new InvalidArgumentException(
                'Configuration key "journal.jsonl.delivery" must be best_effort or required.',
            ),
        };

        return new self(true, $path, $delivery);
    }

    /** @return array<array-key, mixed> */
    private static function jsonl(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Configuration key "journal.jsonl" must be an array.');
        }

        return $value;
    }

    private static function enabled(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException('Configuration key "journal.jsonl.enabled" must be boolean.');
        }

        return $value;
    }

    private static function path(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '' || !str_starts_with($value, DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Configuration key "journal.jsonl.path" must be an absolute path.');
        }

        return $value;
    }
}

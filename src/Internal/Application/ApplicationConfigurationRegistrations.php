<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationConfigurationRegistrations
{
    /** @param array<string, array<array-key, mixed>> $configuration */
    public function __construct(
        private array $configuration,
    ) {}

    /** @return list<mixed> */
    public function operations(): array
    {
        $operations = $this->configuration['operations'] ?? [];

        return array_is_list($operations) ? $operations : $this->listFromSection('operations', 'providers');
    }

    /** @return list<mixed> */
    public function services(): array
    {
        return [
            ...$this->listFromSection('app', 'services'),
            ...$this->listFromSection('auth', 'services'),
        ];
    }

    /** @return list<mixed> */
    public function commands(): array
    {
        return $this->listFromSection('app', 'commands');
    }

    /** @return list<mixed> */
    private function listFromSection(string $section, string $key): array
    {
        return $this->listFromValue($this->configuration[$section][$key] ?? [], $section, $key);
    }

    /** @return list<mixed> */
    private function listFromValue(mixed $value, string $section, string $key): array
    {
        if (!is_iterable($value)) {
            throw new InvalidArgumentException(sprintf('Configuration key "%s.%s" must be iterable.', $section, $key));
        }

        return is_array($value) ? array_values($value) : iterator_to_array($value, preserve_keys: false);
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;
use JsonException;

final readonly class InstalledComposerProviderDiscovery
{
    public function __construct(
        private ComposerProviderDiscovery $packages = new ComposerProviderDiscovery(),
    ) {}

    public function discover(string $path): DiscoveredComposerProviders
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Composer installed metadata file does not exist.');
        }

        try {
            return $this->providersFrom($this->decode($path));
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Composer installed metadata file must contain valid JSON.',
                previous: $exception,
            );
        }
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function decode(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException('Composer installed metadata file could not be read.');
        }

        return $this->arrayData(json_decode($contents, associative: true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayData(mixed $data): array
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Composer installed metadata file must contain an object or list.');
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function providersFrom(array $data): DiscoveredComposerProviders
    {
        $operationProviders = [];
        $serviceProviders = [];

        foreach ($this->packageMetadata($data) as $package) {
            $providers = $this->packages->discoverMetadata($package);
            $operationProviders = [...$operationProviders, ...$providers->operationProviders];
            $serviceProviders = [...$serviceProviders, ...$providers->serviceProviders];
        }

        return new DiscoveredComposerProviders($operationProviders, $serviceProviders);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<array<array-key, mixed>>
     */
    private function packageMetadata(array $data): array
    {
        if (array_key_exists('packages', $data)) {
            return $this->packageList($data['packages']);
        }

        if (!array_is_list($data)) {
            throw new InvalidArgumentException('Composer installed metadata file must contain a package list.');
        }

        return $this->packageList($data);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function packageList(mixed $packages): array
    {
        if (!is_array($packages) || !array_is_list($packages)) {
            throw new InvalidArgumentException('Composer installed packages must be a list.');
        }

        return array_map($this->packageEntry(...), $packages);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function packageEntry(mixed $package): array
    {
        if (!is_array($package)) {
            throw new InvalidArgumentException('Composer installed package entry must be an object.');
        }

        return $package;
    }
}

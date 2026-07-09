<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\Registry\OperationProvider;
use InvalidArgumentException;
use JsonException;

final readonly class ComposerProviderDiscovery
{
    public function discover(string $path): DiscoveredComposerProviders
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Composer metadata file does not exist.');
        }

        try {
            return $this->discoverMetadata($this->decode($path));
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Composer metadata file must contain valid JSON.', previous: $exception);
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function discoverMetadata(array $data): DiscoveredComposerProviders
    {
        $extra = $this->extra($data);

        return new DiscoveredComposerProviders(
            $this->providerClasses($extra, 'operation-providers', OperationProvider::class),
            $this->providerClasses($extra, 'service-providers', ServiceProvider::class),
        );
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
            throw new InvalidArgumentException('Composer metadata file could not be read.');
        }

        return $this->arrayData(json_decode($contents, associative: true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayData(mixed $data): array
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Composer metadata file must contain a JSON object.');
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function extra(array $data): array
    {
        if (!array_key_exists('extra', $data)) {
            return [];
        }

        if (!is_array($data['extra'])) {
            throw new InvalidArgumentException('Composer metadata extra section must be an object.');
        }

        $extra = $data['extra'];

        if (!array_key_exists('blackops', $extra)) {
            return [];
        }

        if (!is_array($extra['blackops'])) {
            throw new InvalidArgumentException('Composer metadata blackops section must be an object.');
        }

        return $extra['blackops'];
    }

    /**
     * @template T of object
     *
     * @param array<array-key, mixed> $extra
     * @param class-string<T> $contract
     *
     * @return list<class-string<T>>
     */
    private function providerClasses(array $extra, string $key, string $contract): array
    {
        if (!array_key_exists($key, $extra)) {
            return [];
        }

        if (!is_array($extra[$key])) {
            throw new InvalidArgumentException('Composer provider metadata must be a list.');
        }

        return array_map(fn(mixed $provider): string => $this->providerClass(
            $provider,
            $contract,
        ), array_values($extra[$key]));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $contract
     *
     * @return class-string<T>
     */
    private function providerClass(mixed $provider, string $contract): string
    {
        if (!is_string($provider) || $provider === '') {
            throw new InvalidArgumentException('Composer provider metadata entry must be a class name.');
        }

        if (!is_a($provider, $contract, allow_string: true)) {
            throw new InvalidArgumentException(
                'Composer provider metadata entry must implement its provider contract.',
            );
        }

        return $provider;
    }
}

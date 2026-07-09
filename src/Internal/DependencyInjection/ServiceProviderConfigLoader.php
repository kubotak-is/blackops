<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use InvalidArgumentException;
use ReflectionClass;

final readonly class ServiceProviderConfigLoader
{
    /**
     * @return list<ServiceProvider>
     */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Service provider config file does not exist.');
        }

        return $this->providersFrom($this->requireFile($path));
    }

    private function requireFile(string $path): mixed
    {
        return require $path;
    }

    /**
     * @return list<ServiceProvider>
     */
    private function providersFrom(mixed $value): array
    {
        if ($value instanceof ServiceProvider) {
            return [$value];
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(
                'Service provider config file must return a provider or a provider list.',
            );
        }

        return array_map($this->providerFrom(...), array_values($value));
    }

    private function providerFrom(mixed $entry): ServiceProvider
    {
        if ($entry instanceof ServiceProvider) {
            return $entry;
        }

        if (!is_string($entry)) {
            throw new InvalidArgumentException(
                'Service provider config entry must be a provider instance or class name.',
            );
        }

        if (!is_a($entry, ServiceProvider::class, allow_string: true)) {
            throw new InvalidArgumentException(
                'Service provider config entry must implement the service provider contract.',
            );
        }

        return $this->instantiate($entry);
    }

    /**
     * @param class-string<ServiceProvider> $class
     */
    private function instantiate(string $class): ServiceProvider
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException('Service provider config entry must be instantiable.');
        }

        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new InvalidArgumentException('Service provider config entry must be instantiable without arguments.');
        }

        return $reflection->newInstance();
    }
}

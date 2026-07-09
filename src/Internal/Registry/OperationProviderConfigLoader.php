<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Registry\OperationProvider;
use InvalidArgumentException;
use ReflectionClass;

final readonly class OperationProviderConfigLoader
{
    /**
     * @return list<OperationProvider>
     */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Operation provider config file does not exist.');
        }

        return $this->providersFromValue($this->requireFile($path));
    }

    /**
     * @param iterable<array-key, mixed> $entries
     *
     * @return list<OperationProvider>
     */
    public function fromEntries(iterable $entries): array
    {
        return array_map($this->providerFrom(...), [...$entries]);
    }

    private function requireFile(string $path): mixed
    {
        return require $path;
    }

    /**
     * @return list<OperationProvider>
     */
    private function providersFromValue(mixed $value): array
    {
        if ($value instanceof OperationProvider) {
            return [$value];
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(
                'Operation provider config file must return a provider or a provider list.',
            );
        }

        return $this->fromEntries(array_values($value));
    }

    private function providerFrom(mixed $entry): OperationProvider
    {
        if ($entry instanceof OperationProvider) {
            return $entry;
        }

        if (!is_string($entry)) {
            throw new InvalidArgumentException(
                'Operation provider config entry must be a provider instance or class name.',
            );
        }

        if (!is_a($entry, OperationProvider::class, allow_string: true)) {
            throw new InvalidArgumentException(
                'Operation provider config entry must implement the operation provider contract.',
            );
        }

        return $this->instantiate($entry);
    }

    /**
     * @param class-string<OperationProvider> $class
     */
    private function instantiate(string $class): OperationProvider
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException('Operation provider config entry must be instantiable.');
        }

        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new InvalidArgumentException(
                'Operation provider config entry must be instantiable without arguments.',
            );
        }

        return $reflection->newInstance();
    }
}

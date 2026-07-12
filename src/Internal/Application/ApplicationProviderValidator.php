<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;
use ReflectionClass;

final readonly class ApplicationProviderValidator
{
    /**
     * @template TProvider of object
     * @param iterable<array-key, mixed> $entries
     * @param class-string<TProvider> $contract
     * @return list<TProvider|class-string<TProvider>>
     */
    public function validate(iterable $entries, string $contract, string $kind): array
    {
        $providers = [];
        $identities = [];

        $values = [...$entries];
        array_walk($values, function (mixed $entry) use ($contract, $kind, &$providers, &$identities): void {
            $this->append($entry, $contract, $kind, $providers, $identities);
        });

        return $providers;
    }

    /**
     * @template TProvider of object
     * @param class-string<TProvider> $contract
     * @param list<TProvider|class-string<TProvider>> $providers
     * @param array<class-string<TProvider>, true> $identities
     */
    private function append(mixed $entry, string $contract, string $kind, array &$providers, array &$identities): void
    {
        if (!is_object($entry) && !is_string($entry) || !is_a($entry, $contract, allow_string: true)) {
            throw new InvalidArgumentException(sprintf(
                'Application %s provider must be a provider instance or provider class name.',
                $kind,
            ));
        }

        /** @var TProvider|class-string<TProvider> $entry */
        $class = is_string($entry) ? $entry : $entry::class;
        if (($identities[$class] ?? false) === true) {
            return;
        }

        if (is_string($entry)) {
            $this->assertInstantiable($entry, $kind);
        }

        $identities[$class] = true;
        $providers[] = $entry;
    }

    /** @param class-string $class */
    private function assertInstantiable(string $class, string $kind): void
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (
            !$reflection->isInstantiable()
            || $constructor !== null && $constructor->getNumberOfRequiredParameters() > 0
        ) {
            throw new InvalidArgumentException(sprintf(
                'Application %s provider class must be instantiable without arguments.',
                $kind,
            ));
        }
    }
}

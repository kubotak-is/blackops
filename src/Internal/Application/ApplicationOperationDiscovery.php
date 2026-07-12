<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Core\Operation;
use BlackOps\Internal\Discovery\ComposerAutoloadMetadata;
use BlackOps\Internal\Discovery\OperationSourceDiscovery;
use InvalidArgumentException;

final readonly class ApplicationOperationDiscovery
{
    public function __construct(
        private OperationSourceDiscovery $discovery = new OperationSourceDiscovery(),
    ) {}

    /** @return list<class-string<Operation>> */
    public function discover(ApplicationConfigurationSnapshot $application): array
    {
        $operations = $application->configuration()['operations'] ?? [];
        if (!array_key_exists('discovery', $operations)) {
            return [];
        }

        return $this->discovery->discover(
            $this->roots($operations['discovery']),
            new ComposerAutoloadMetadata($application->basePath(), [], []),
        );
    }

    /** @return list<string> */
    private function roots(mixed $value): array
    {
        if (!is_iterable($value)) {
            throw new InvalidArgumentException('Configuration key "operations.discovery" must be iterable.');
        }

        $paths = [];
        $entries = is_array($value) ? $value : iterator_to_array($value, preserve_keys: false);
        array_walk($entries, static function (mixed $root) use (&$paths): void {
            if (!is_string($root)) {
                throw new InvalidArgumentException('Operation discovery root must be a string.');
            }
            $paths[] = $root;
        });

        return $paths;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationHttpMiddlewareConfiguration
{
    /** @param list<string> $http */
    private function __construct(
        public array $http,
    ) {}

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        /** @var mixed $http */
        $http = $configuration['middleware']['http'] ?? [];

        if (!is_array($http) || !array_is_list($http)) {
            throw new InvalidArgumentException('Application configuration key "middleware.http" must be a list.');
        }

        $entries = array_map(static function (mixed $entry): string {
            if (!is_string($entry) || trim($entry) === '') {
                throw new InvalidArgumentException(
                    'Application configuration key "middleware.http" must contain non-empty service IDs.',
                );
            }

            return trim($entry);
        }, $http);

        if (count(array_unique($entries)) !== count($entries)) {
            throw new InvalidArgumentException(
                'Application configuration key "middleware.http" must not contain duplicate service IDs.',
            );
        }

        return new self($entries);
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationBuildId
{
    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): string
    {
        /** @var mixed $buildId */
        $buildId = $configuration['app']['build']['application_build_id'] ?? null;

        if (!is_string($buildId) || trim($buildId) === '') {
            throw new InvalidArgumentException(
                'Application configuration key "app.build.application_build_id" must be a non-empty string.',
            );
        }

        return $buildId;
    }
}

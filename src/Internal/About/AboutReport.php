<?php

declare(strict_types=1);

namespace BlackOps\Internal\About;

use InvalidArgumentException;

final readonly class AboutReport
{
    public const int SCHEMA_VERSION = 1;

    /** @var list<string> */
    public const array PROJECTION_STATUSES = [
        'available',
        'absent',
        'malformed',
        'unsupported',
        'stale',
        'build_mismatch',
        'configuration_invalid',
        'read_failed',
    ];

    public function __construct(
        public AboutMetadata $metadata,
        public string $projectionBuildSet,
    ) {
        if (!in_array($projectionBuildSet, self::PROJECTION_STATUSES, strict: true)) {
            throw new InvalidArgumentException('About projection status is invalid.');
        }
    }

    /**
     * @return array{package: array{name: string, version: string}, projectionBuildSet: string, runtime: array{phpVersion: string, sapi: string, scope: string}, schemaVersion: int}
     */
    public function toArray(): array
    {
        return [
            'package' => [
                'name' => AboutMetadata::PACKAGE_NAME,
                'version' => $this->metadata->version,
            ],
            'projectionBuildSet' => $this->projectionBuildSet,
            'runtime' => [
                'phpVersion' => $this->metadata->phpVersion,
                'sapi' => $this->metadata->sapi,
                'scope' => AboutMetadata::RUNTIME_SCOPE,
            ],
            'schemaVersion' => self::SCHEMA_VERSION,
        ];
    }
}

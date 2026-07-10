<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpOperationManifestArtifactCodec
{
    public const SCHEMA_VERSION = 2;

    public function __construct(
        private HttpOperationManifestPayloadCodec $payloads = new HttpOperationManifestPayloadCodec(),
    ) {}

    /**
     * @return array{schemaVersion: int, applicationBuildId: string, payload: array<string, mixed>}
     */
    public function encode(HttpOperationManifest $manifest, string $applicationBuildId): array
    {
        $this->assertApplicationBuildId($applicationBuildId);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'applicationBuildId' => $applicationBuildId,
            'payload' => $manifest->toArray(),
        ];
    }

    public function decode(mixed $data): HttpOperationManifestArtifact
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('HTTP manifest file must return a versioned manifest array.');
        }

        $schemaVersion = $this->schemaVersion($data);
        $applicationBuildId = $this->applicationBuildId($data);

        return new HttpOperationManifestArtifact(
            $schemaVersion,
            $applicationBuildId,
            $this->payloads->decode($data['payload'] ?? null),
        );
    }

    /** @param array<array-key, mixed> $data */
    private function schemaVersion(array $data): int
    {
        if (!array_key_exists('schemaVersion', $data) || !is_int($data['schemaVersion'])) {
            throw new InvalidArgumentException('HTTP manifest schema version is missing or invalid.');
        }

        if ($data['schemaVersion'] !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('HTTP manifest schema version is not supported.');
        }

        return $data['schemaVersion'];
    }

    /** @param array<array-key, mixed> $data */
    private function applicationBuildId(array $data): string
    {
        if (!array_key_exists('applicationBuildId', $data) || !is_string($data['applicationBuildId'])) {
            throw new InvalidArgumentException('HTTP manifest application build ID is missing or invalid.');
        }

        $this->assertApplicationBuildId($data['applicationBuildId']);

        return $data['applicationBuildId'];
    }

    private function assertApplicationBuildId(string $applicationBuildId): void
    {
        if (trim($applicationBuildId) === '') {
            throw new InvalidArgumentException('HTTP manifest application build ID must be a non-empty string.');
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Registry\OperationRegistry;
use InvalidArgumentException;
use RuntimeException;

final readonly class OperationManifestFile
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private OperationManifestMetadataCodec $codec = new OperationManifestMetadataCodec(),
    ) {}

    public function write(OperationRegistry $registry, string $path, ?string $applicationBuildId = null): void
    {
        $applicationBuildId ??= 'standalone-' . bin2hex(random_bytes(16));
        $this->assertApplicationBuildId($applicationBuildId);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw new InvalidArgumentException('Operation manifest directory does not exist.');
        }

        $temporary = tempnam(directory: $directory, prefix: 'operation-manifest-');

        if ($temporary === false) {
            throw new RuntimeException('Operation manifest temporary file could not be created.');
        }

        try {
            $this->writeTemporary($registry, $applicationBuildId, $temporary);

            if (!rename($temporary, $path)) {
                throw new RuntimeException('Operation manifest file could not be moved into place.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function load(string $path): OperationRegistry
    {
        return $this->loadArtifact($path)->operations;
    }

    public function loadArtifact(string $path): OperationManifestArtifact
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Operation manifest file does not exist.');
        }

        $data = $this->envelope($this->requireFile($path));

        return new OperationManifestArtifact(
            $data['schemaVersion'],
            $data['applicationBuildId'],
            new OperationRegistry($this->codec->decode($data['payload'])),
        );
    }

    private function writeTemporary(OperationRegistry $registry, string $applicationBuildId, string $temporary): void
    {
        $bytes = file_put_contents($temporary, $this->source($registry, $applicationBuildId));

        if ($bytes === false) {
            throw new RuntimeException('Operation manifest file could not be written.');
        }

        $this->load($temporary);
    }

    private function source(OperationRegistry $registry, string $applicationBuildId): string
    {
        return (
            "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export([
                'schemaVersion' => self::SCHEMA_VERSION,
                'applicationBuildId' => $applicationBuildId,
                'payload' => $this->codec->encode($registry),
            ], return: true)
            . ";\n"
        );
    }

    private function requireFile(string $path): mixed
    {
        return (static fn(string $manifestPath): mixed => require $manifestPath)($path);
    }

    /**
     * @return array{schemaVersion: int, applicationBuildId: string, payload: array<array-key, mixed>}
     */
    private function envelope(mixed $data): array
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Operation manifest file must return a versioned manifest array.');
        }

        if (!array_key_exists('schemaVersion', $data) || !is_int($data['schemaVersion'])) {
            throw new InvalidArgumentException('Operation manifest schema version is missing or invalid.');
        }

        if ($data['schemaVersion'] !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Operation manifest schema version is not supported.');
        }

        if (!array_key_exists('applicationBuildId', $data) || !is_string($data['applicationBuildId'])) {
            throw new InvalidArgumentException('Operation manifest application build ID is missing or invalid.');
        }

        $this->assertApplicationBuildId($data['applicationBuildId']);

        if (!array_key_exists('payload', $data) || !is_array($data['payload'])) {
            throw new InvalidArgumentException('Operation manifest payload is missing or invalid.');
        }

        return [
            'schemaVersion' => $data['schemaVersion'],
            'applicationBuildId' => $data['applicationBuildId'],
            'payload' => $data['payload'],
        ];
    }

    private function assertApplicationBuildId(string $applicationBuildId): void
    {
        if (trim($applicationBuildId) === '') {
            throw new InvalidArgumentException('Operation manifest application build ID must be a non-empty string.');
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;
use RuntimeException;

final readonly class HttpOperationManifestFile
{
    public const SCHEMA_VERSION = HttpOperationManifestArtifactCodec::SCHEMA_VERSION;

    public function __construct(
        private HttpOperationManifestArtifactCodec $codec = new HttpOperationManifestArtifactCodec(),
    ) {}

    public function write(HttpOperationManifest $manifest, string $path, ?string $applicationBuildId = null): void
    {
        $applicationBuildId ??= 'standalone-' . bin2hex(random_bytes(16));
        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw new InvalidArgumentException('HTTP manifest directory does not exist.');
        }

        $temporary = tempnam(directory: $directory, prefix: 'http-manifest-');

        if ($temporary === false) {
            throw new RuntimeException('HTTP manifest temporary file could not be created.');
        }

        try {
            $this->writeTemporary($manifest, $applicationBuildId, $temporary);

            if (!rename($temporary, $path)) {
                throw new RuntimeException('HTTP manifest file could not be moved into place.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function load(string $path): HttpOperationManifest
    {
        return $this->loadArtifact($path)->manifest;
    }

    public function loadArtifact(string $path): HttpOperationManifestArtifact
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('HTTP manifest file does not exist.');
        }

        return $this->codec->decode($this->requireFile($path));
    }

    private function writeTemporary(
        HttpOperationManifest $manifest,
        string $applicationBuildId,
        string $temporary,
    ): void {
        $bytes = file_put_contents($temporary, $this->source($manifest, $applicationBuildId));

        if ($bytes === false) {
            throw new RuntimeException('HTTP manifest file could not be written.');
        }

        $this->load($temporary);
    }

    private function source(HttpOperationManifest $manifest, string $applicationBuildId): string
    {
        return (
            "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export($this->codec->encode($manifest, $applicationBuildId), return: true)
            . ";\n"
        );
    }

    private function requireFile(string $path): mixed
    {
        return (static fn(string $manifestPath): mixed => require $manifestPath)($path);
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

use InvalidArgumentException;
use RuntimeException;

final readonly class FrontendContractManifestFile
{
    public const SCHEMA_VERSION = FrontendContractManifestCodec::SCHEMA_VERSION;

    public function __construct(
        private FrontendContractManifestCodec $codec = new FrontendContractManifestCodec(),
    ) {}

    public function write(FrontendContractManifest $manifest, string $path, string $applicationBuildId): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('Frontend contract manifest directory does not exist.');
        }

        $temporary = tempnam(directory: $directory, prefix: 'frontend-manifest-');
        if ($temporary === false) {
            throw new RuntimeException('Frontend contract manifest temporary file could not be created.');
        }

        try {
            $bytes = file_put_contents($temporary, $this->source($manifest, $applicationBuildId));
            if ($bytes === false) {
                throw new RuntimeException('Frontend contract manifest could not be written.');
            }
            $this->loadArtifact($temporary);

            if (!rename($temporary, $path)) {
                throw new RuntimeException('Frontend contract manifest could not be moved into place.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function load(string $path): FrontendContractManifest
    {
        return $this->loadArtifact($path)->manifest;
    }

    public function loadArtifact(string $path): FrontendContractManifestArtifact
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Frontend contract manifest file does not exist.');
        }

        return $this->codec->decode((static fn(string $file): mixed => require $file)($path));
    }

    private function source(FrontendContractManifest $manifest, string $applicationBuildId): string
    {
        return (
            "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export($this->codec->encode($manifest, $applicationBuildId), return: true)
            . ";\n"
        );
    }
}

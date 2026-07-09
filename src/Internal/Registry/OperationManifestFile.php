<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Registry\OperationRegistry;
use InvalidArgumentException;
use RuntimeException;

final readonly class OperationManifestFile
{
    public function __construct(
        private OperationManifestMetadataCodec $codec = new OperationManifestMetadataCodec(),
    ) {}

    public function write(OperationRegistry $registry, string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw new InvalidArgumentException('Operation manifest directory does not exist.');
        }

        $temporary = tempnam(directory: $directory, prefix: 'operation-manifest-');

        if ($temporary === false) {
            throw new RuntimeException('Operation manifest temporary file could not be created.');
        }

        try {
            $this->writeTemporary($registry, $temporary);

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
        if (!is_file($path)) {
            throw new InvalidArgumentException('Operation manifest file does not exist.');
        }

        return new OperationRegistry($this->codec->decode($this->requireFile($path)));
    }

    private function writeTemporary(OperationRegistry $registry, string $temporary): void
    {
        $bytes = file_put_contents($temporary, $this->source($registry));

        if ($bytes === false) {
            throw new RuntimeException('Operation manifest file could not be written.');
        }

        $this->load($temporary);
    }

    private function source(OperationRegistry $registry): string
    {
        return (
            "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export($this->codec->encode($registry), return: true)
            . ";\n"
        );
    }

    private function requireFile(string $path): mixed
    {
        return (static fn(string $manifestPath): mixed => require $manifestPath)($path);
    }
}

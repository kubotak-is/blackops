<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;
use RuntimeException;

final readonly class HttpOperationManifestFile
{
    public function write(HttpOperationManifest $manifest, string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw new InvalidArgumentException('HTTP manifest directory does not exist.');
        }

        $temporary = tempnam(directory: $directory, prefix: 'http-manifest-');

        if ($temporary === false) {
            throw new RuntimeException('HTTP manifest temporary file could not be created.');
        }

        try {
            $this->writeTemporary($manifest, $temporary);

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
        if (!is_file($path)) {
            throw new InvalidArgumentException('HTTP manifest file does not exist.');
        }

        $data = $this->manifestData($this->requireFile($path));

        return new HttpOperationManifest($data['routes'], $data['operations']);
    }

    private function writeTemporary(HttpOperationManifest $manifest, string $temporary): void
    {
        $bytes = file_put_contents($temporary, $this->source($manifest));

        if ($bytes === false) {
            throw new RuntimeException('HTTP manifest file could not be written.');
        }

        $this->load($temporary);
    }

    private function source(HttpOperationManifest $manifest): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($manifest->toArray(), return: true) . ";\n";
    }

    private function requireFile(string $path): mixed
    {
        return (static fn(string $manifestPath): mixed => require $manifestPath)($path);
    }

    /**
     * @return array{routes: array<string, array<string, string>>, operations: array<string, array<string, string>>}
     */
    private function manifestData(mixed $data): array
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('HTTP manifest file must return a manifest array.');
        }

        return [
            'routes' => $this->section($data, 'routes'),
            'operations' => $this->section($data, 'operations'),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, array<string, string>>
     */
    private function section(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            throw new InvalidArgumentException('HTTP manifest file must return a manifest array.');
        }

        $section = $data[$key];

        return $this->stringMap($section);
    }

    /**
     * @param array<array-key, mixed> $section
     *
     * @return array<string, array<string, string>>
     */
    private function stringMap(array $section): array
    {
        $result = [];

        foreach (array_keys($section) as $outerKey) {
            if (!is_string($outerKey) || !is_array($section[$outerKey])) {
                throw new InvalidArgumentException('HTTP manifest file must return a manifest array.');
            }

            $inner = $section[$outerKey];
            $result[$outerKey] = $this->stringValues($inner);
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<string, string>
     */
    private function stringValues(array $values): array
    {
        $result = [];

        foreach (array_keys($values) as $key) {
            if (!is_string($key) || !is_string($values[$key])) {
                throw new InvalidArgumentException('HTTP manifest file must return a manifest array.');
            }

            $result[$key] = $values[$key];
        }

        return $result;
    }
}

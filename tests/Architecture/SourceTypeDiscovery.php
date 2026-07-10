<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

final readonly class SourceTypeDiscovery
{
    public function __construct(
        private string $sourceDirectory,
        private string $namespacePrefix,
    ) {}

    /**
     * @return list<class-string>
     */
    public function discover(): array
    {
        $directory = rtrim($this->sourceDirectory, DIRECTORY_SEPARATOR);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $sourceFiles = [];

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($directory) + 1, -4);
            $type = trim($this->namespacePrefix, '\\') . '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

            if (!$this->typeExists($type)) {
                throw new RuntimeException(sprintf(
                    'Source file "%s" does not define the PSR-4 type "%s".',
                    $file->getPathname(),
                    $type,
                ));
            }

            $sourcePath = $file->getRealPath();

            if ($sourcePath === false) {
                throw new RuntimeException(sprintf('Source file "%s" cannot be resolved.', $file->getPathname()));
            }

            $sourceFiles[$sourcePath] = true;
        }

        $types = [];
        $declaredTypes = array_unique([
            ...get_declared_classes(),
            ...get_declared_interfaces(),
            ...get_declared_traits(),
        ]);

        foreach ($declaredTypes as $type) {
            $file = new ReflectionClass($type)->getFileName();

            if ($file === false || !isset($sourceFiles[realpath($file)])) {
                continue;
            }

            /** @var class-string $type */
            $types[] = $type;
        }

        sort($types);

        return $types;
    }

    private function typeExists(string $type): bool
    {
        return class_exists($type) || interface_exists($type) || trait_exists($type) || enum_exists($type);
    }
}

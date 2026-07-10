<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;
use ReflectionClass;

final readonly class PhpSourceClassLoader
{
    /**
     * @param array<class-string, string> $candidates
     */
    public function load(array $candidates): void
    {
        $files = [];

        foreach ($candidates as $class => $file) {
            if (class_exists($class, false)) {
                $this->assertLoadedFrom($class, $file);
            }

            $files[$file] = true;
        }

        foreach (array_keys($files) as $file) {
            (static function (string $sourceFile): void {
                require_once $sourceFile;
            })($file);
        }
    }

    /** @param class-string $class */
    private function assertLoadedFrom(string $class, string $candidateFile): void
    {
        $sourceFile = new ReflectionClass($class)->getFileName();
        $resolved = $sourceFile === false ? false : realpath($sourceFile);

        if ($resolved !== $candidateFile) {
            throw new InvalidArgumentException('Operation discovery class is already loaded from a different file.');
        }
    }
}

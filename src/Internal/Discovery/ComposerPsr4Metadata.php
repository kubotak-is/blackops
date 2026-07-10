<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;

final readonly class ComposerPsr4Metadata
{
    /** @var array<string, non-empty-list<string>> */
    private array $prefixes;

    public function __construct(
        mixed $metadata,
        ComposerMetadataPathResolver $paths,
        ComposerPsr4Directories $directoryLists = new ComposerPsr4Directories(),
    ) {
        if (!is_array($metadata)) {
            throw new InvalidArgumentException('Composer PSR-4 metadata must be an array.');
        }

        $prefixes = [];

        foreach (array_keys($metadata) as $prefix) {
            if (!is_string($prefix) || !$this->isPrefix($prefix)) {
                throw new InvalidArgumentException('Composer PSR-4 prefix is invalid.');
            }

            $prefixes[$prefix] = $directoryLists->resolve($metadata[$prefix], $paths);
        }

        $this->prefixes = $prefixes;
    }

    /**
     * @return array<class-string, string>
     */
    public function candidates(DiscoveryRoots $roots): array
    {
        $files = new PhpSourceFileFinder();
        $candidates = [];

        foreach ($this->prefixes as $prefix => $directories) {
            foreach ($directories as $directory) {
                foreach ($files->find($roots, $directory) as $file) {
                    $relative = substr($file, strlen($directory) + 1, -4);
                    $class = $prefix . str_replace(search: DIRECTORY_SEPARATOR, replace: '\\', subject: $relative);

                    if ($this->isClassName($class)) {
                        $candidates[$class] = $file;
                    }
                }
            }
        }

        return $candidates;
    }

    private function isPrefix(string $prefix): bool
    {
        return $prefix === '' || preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)+$/', $prefix) === 1;
    }

    private function isClassName(string $class): bool
    {
        return preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*$/', $class) === 1;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use BlackOps\Database\Seeder;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class SeederSourceDiscovery
{
    public function __construct(
        private PhpTokenClassScanner $tokens = new PhpTokenClassScanner(),
        private PhpSourceFileFinder $files = new PhpSourceFileFinder(),
        private PhpSourceClassLoader $loader = new PhpSourceClassLoader(),
    ) {}

    /**
     * @param non-empty-list<string> $roots
     * @return list<class-string<Seeder>>
     */
    public function discover(array $roots): array
    {
        try {
            return $this->scan(DiscoveryRoots::from($roots));
        } catch (InvalidArgumentException $exception) {
            if (str_starts_with($exception->getMessage(), 'Discovered seeder')) {
                throw $exception;
            }

            throw new InvalidArgumentException('Seeder discovery failed.');
        } catch (Throwable) {
            throw new InvalidArgumentException('Seeder discovery failed.');
        }
    }

    /** @return list<class-string<Seeder>> */
    private function scan(DiscoveryRoots $roots): array
    {
        $candidates = [];
        foreach ($this->files->find($roots) as $file) {
            foreach ($this->tokens->scan($file) as $class) {
                if (array_key_exists($class, $candidates) && $candidates[$class] !== $file) {
                    throw new InvalidArgumentException('Discovered seeder class resolves to multiple files.');
                }

                $candidates[$class] = $file;
            }
        }

        $this->loader->load($candidates);
        $seeders = [];

        foreach ($candidates as $class => $file) {
            if (!class_exists($class, false)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $source = $reflection->getFileName();
            $resolved = $source === false ? false : realpath($source);
            if ($resolved === false || !$roots->contains($resolved) || $resolved !== $file) {
                throw new InvalidArgumentException('Discovered seeder source is invalid.');
            }
            if (
                $reflection->isAbstract()
                || !$reflection->isInstantiable()
                || !$reflection->implementsInterface(Seeder::class)
            ) {
                continue;
            }

            /** @var class-string<Seeder> $seeder */
            $seeder = $reflection->getName();
            $seeders[$seeder] = true;
        }

        $result = array_keys($seeders);
        sort($result);

        return $result;
    }
}

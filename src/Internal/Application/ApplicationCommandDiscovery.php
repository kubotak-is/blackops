<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\ApplicationCommandMetadata;
use BlackOps\Internal\Discovery\DiscoveryRoots;
use BlackOps\Internal\Discovery\PhpSourceClassLoader;
use BlackOps\Internal\Discovery\PhpSourceFileFinder;
use BlackOps\Internal\Discovery\PhpTokenClassScanner;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class ApplicationCommandDiscovery
{
    public function __construct(
        private PhpTokenClassScanner $tokens = new PhpTokenClassScanner(),
        private PhpSourceFileFinder $files = new PhpSourceFileFinder(),
        private PhpSourceClassLoader $loader = new PhpSourceClassLoader(),
    ) {}

    /** @return list<ApplicationCommandMetadata> */
    public function discover(ApplicationConfigurationSnapshot $application): array
    {
        /** @var mixed $configured */
        $configured = $application->configuration()['app']['command_discovery'] ?? null;
        if ($configured === null) {
            return [];
        }
        if (!is_iterable($configured)) {
            throw new InvalidArgumentException('Configuration key "app.command_discovery" must be iterable.');
        }

        $roots = [];
        $entries = is_array($configured)
            ? array_values($configured)
            : iterator_to_array($configured, preserve_keys: false);
        array_walk($entries, static function (mixed $root) use (&$roots): void {
            if (!is_string($root)) {
                throw new InvalidArgumentException('Application command discovery root must be a string.');
            }
            $roots[] = $root;
        });
        if ($roots === []) {
            return [];
        }

        try {
            return $this->scan(DiscoveryRoots::from($roots));
        } catch (InvalidArgumentException $exception) {
            if (str_starts_with($exception->getMessage(), 'Discovered application command')) {
                throw $exception;
            }

            throw new InvalidArgumentException('Application command discovery failed.');
        } catch (Throwable) {
            throw new InvalidArgumentException('Application command discovery failed.');
        }
    }

    /** @return list<ApplicationCommandMetadata> */
    private function scan(DiscoveryRoots $roots): array
    {
        $candidates = [];
        foreach ($this->files->find($roots) as $file) {
            foreach ($this->tokens->scan($file) as $class) {
                if (array_key_exists($class, $candidates) && $candidates[$class] !== $file) {
                    throw new InvalidArgumentException(
                        'Discovered application command class resolves to multiple files.',
                    );
                }
                $candidates[$class] = $file;
            }
        }

        $this->loader->load($candidates);
        $commands = [];
        foreach ($candidates as $class => $file) {
            if (!class_exists($class, false)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(AsCommand::class);
            if ($attributes === []) {
                continue;
            }
            if (count($attributes) !== 1) {
                throw new InvalidArgumentException(
                    'Discovered application command must define exactly one AsCommand attribute.',
                );
            }

            $source = $reflection->getFileName();
            $resolved = $source === false ? false : realpath($source);
            if ($resolved === false || !$roots->contains($resolved) || $resolved !== $file) {
                throw new InvalidArgumentException('Discovered application command source is invalid.');
            }
            if (!$reflection->isSubclassOf(Command::class)) {
                throw new InvalidArgumentException('Discovered application command must extend Symfony Command.');
            }
            if ($reflection->isAbstract() || !$reflection->isInstantiable()) {
                throw new InvalidArgumentException('Discovered application command must be instantiable.');
            }

            $metadata = ApplicationCommandMetadata::fromAttribute($reflection);
            $commands[$metadata->class] = $metadata;
        }

        $result = array_values($commands);
        usort(
            $result,
            static fn(ApplicationCommandMetadata $left, ApplicationCommandMetadata $right): int => (
                [$left->name, $left->class] <=> [$right->name, $right->class]
            ),
        );

        return $result;
    }
}

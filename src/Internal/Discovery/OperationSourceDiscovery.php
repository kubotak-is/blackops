<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use BlackOps\Core\Operation;
use InvalidArgumentException;
use ReflectionClass;

final readonly class OperationSourceDiscovery
{
    public function __construct(
        private PhpTokenClassScanner $tokens = new PhpTokenClassScanner(),
        private PhpSourceFileFinder $files = new PhpSourceFileFinder(),
        private PhpSourceClassLoader $loader = new PhpSourceClassLoader(),
    ) {}

    /**
     * @param iterable<string> $roots
     *
     * @return list<class-string<Operation>>
     */
    public function discover(iterable $roots, ComposerAutoloadMetadata $metadata): array
    {
        $rootSet = DiscoveryRoots::from($roots);
        $candidates = $metadata->candidates($rootSet);
        $tokenCandidates = [];

        foreach ($this->files->find($rootSet) as $file) {
            foreach ($this->tokens->scan($file) as $class) {
                if (array_key_exists($class, $candidates) && $candidates[$class] !== $file) {
                    throw new InvalidArgumentException('Operation discovery class resolves to multiple source files.');
                }

                $candidates[$class] = $file;
                $tokenCandidates[$class] = $file;
            }
        }

        $this->loader->load($tokenCandidates);

        $definitions = [];

        foreach ($candidates as $class => $candidateFile) {
            if (!class_exists($class, false)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $sourceFile = $reflection->getFileName();

            if ($sourceFile === false) {
                throw new InvalidArgumentException('Operation discovery candidate must have a source file.');
            }

            $resolvedSource = realpath($sourceFile);

            if ($resolvedSource === false || !$rootSet->contains($resolvedSource)) {
                throw new InvalidArgumentException('Operation discovery candidate resolves outside configured roots.');
            }

            if ($resolvedSource !== $candidateFile) {
                throw new InvalidArgumentException(
                    'Operation discovery candidate source file does not match metadata.',
                );
            }

            if (
                $reflection->isAbstract()
                || !$reflection->isInstantiable()
                || !$reflection->implementsInterface(Operation::class)
            ) {
                continue;
            }

            /** @var class-string<Operation> $definition */
            $definition = $reflection->getName();
            $definitions[$definition] = true;
        }

        $result = array_keys($definitions);
        sort($result);

        return $result;
    }
}

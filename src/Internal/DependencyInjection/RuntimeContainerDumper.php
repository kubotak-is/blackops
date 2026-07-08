<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

final readonly class RuntimeContainerDumper
{
    public function dump(ContainerBuilder $builder, string $path, string $class, string $namespace = ''): void
    {
        $this->assertIdentifier($class, 'container class');

        if ($namespace !== '') {
            foreach (explode('\\', $namespace) as $part) {
                $this->assertIdentifier($part, 'container namespace');
            }
        }

        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw new InvalidArgumentException('Runtime container dump directory does not exist.');
        }

        $source = new PhpDumper($builder)->dump([
            'class' => $class,
            'namespace' => $namespace,
            'as_files' => false,
        ]);

        if (!is_string($source)) {
            throw new RuntimeException('Runtime container dump must be a single PHP file.');
        }

        $this->write($source, $directory, $path);
    }

    private function write(string $source, string $directory, string $path): void
    {
        $temporary = tempnam(directory: $directory, prefix: 'container-');

        if ($temporary === false) {
            throw new RuntimeException('Runtime container temporary file could not be created.');
        }

        try {
            if (file_put_contents($temporary, $source) === false) {
                throw new RuntimeException('Runtime container dump could not be written.');
            }

            if (!rename($temporary, $path)) {
                throw new RuntimeException('Runtime container dump could not be moved into place.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException('Runtime ' . $label . ' is invalid.');
        }
    }
}

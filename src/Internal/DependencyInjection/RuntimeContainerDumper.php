<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

final readonly class RuntimeContainerDumper
{
    /** @param list<string> $requiredFiles */
    public function dump(
        ContainerBuilder $builder,
        string $path,
        string $class,
        string $namespace = '',
        array $requiredFiles = [],
    ): void {
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

        $source .= $this->requiredFileSource($requiredFiles, $directory);

        $this->write($source, $directory, $path);
    }

    /** @param list<string> $requiredFiles */
    private function requiredFileSource(array $requiredFiles, string $containerDirectory): string
    {
        if ($requiredFiles === []) {
            return '';
        }

        $aopDirectory = $containerDirectory . DIRECTORY_SEPARATOR . 'aop';
        $source = "\n";

        foreach ($requiredFiles as $file) {
            if (dirname($file) !== $aopDirectory || !is_file($file)) {
                throw new InvalidArgumentException('Runtime container required AOP artifact is invalid.');
            }

            $source .= sprintf("require_once __DIR__ . '/aop/%s';\n", basename($file));
        }

        return $source;
    }

    private function write(string $source, string $directory, string $path): void
    {
        $temporary = $directory . DIRECTORY_SEPARATOR . 'container-' . bin2hex(random_bytes(16)) . '.tmp';
        $written = file_put_contents($temporary, $source, LOCK_EX);

        if ($written === false || $written !== strlen($source)) {
            throw new RuntimeException('Runtime container dump could not be written.');
        }

        try {
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

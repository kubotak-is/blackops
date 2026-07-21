<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Throwable;

final readonly class RuntimeContainerArtifactLoader
{
    public function load(string $path, string $class, string $namespace = ''): ContainerInterface
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Runtime container artifact file does not exist.');
        }

        $this->assertIdentifier($class, 'container class');
        if ($namespace !== '') {
            foreach (explode('\\', $namespace) as $part) {
                $this->assertIdentifier($part, 'container namespace');
            }
        }

        try {
            require_once $path;
            $containerClass = $namespace === '' ? $class : $namespace . '\\' . $class;
            if (!class_exists($containerClass)) {
                throw new InvalidArgumentException('Runtime container artifact class does not exist.');
            }
            if (!is_a($containerClass, ContainerInterface::class, allow_string: true)) {
                throw new InvalidArgumentException('Runtime container artifact must implement ContainerInterface.');
            }
            $reflection = new ReflectionClass($containerClass);
            if (!$reflection->isInstantiable()) {
                throw new InvalidArgumentException('Runtime container artifact class must be instantiable.');
            }

            return $reflection->newInstance();
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new InvalidArgumentException('Runtime container artifact could not be loaded.');
        }
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $value) !== 1) {
            throw new InvalidArgumentException('Runtime ' . $label . ' is invalid.');
        }
    }
}

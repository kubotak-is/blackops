<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use Throwable;

final readonly class ApplicationConfigurationLoader
{
    /** @var list<string> */
    private const FILES = [
        'app',
        'database',
        'operations',
        'execution',
        'journal',
        'middleware',
        'retention',
        'diagnostics',
        'logging',
        'frontend',
        'auth',
    ];

    public function resolve(string $directory): string
    {
        $resolved = realpath($directory);

        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException('Application configuration directory must be an existing directory.');
        }

        return $resolved;
    }

    public function resolveOptional(string $directory): ?string
    {
        return is_dir($directory) ? $this->resolve($directory) : null;
    }

    /** @return array<string, array<array-key, mixed>> */
    public function load(string $directory, ?object $environment = null): array
    {
        $resolved = $this->resolve($directory);
        $configuration = [];

        foreach (self::FILES as $name) {
            $path = $resolved . DIRECTORY_SEPARATOR . $name . '.php';

            if (!is_file($path)) {
                continue;
            }

            try {
                $configuration[$name] = $this->arrayFromValue($this->requireFile($path), $name, $environment);
            } catch (Throwable) {
                throw new InvalidArgumentException(sprintf(
                    'Application configuration file "%s.php" could not be evaluated safely.',
                    $name,
                ));
            }
        }

        return $configuration;
    }

    /** @return array<string, array<array-key, mixed>> */
    public function loadOptional(string $directory, ?object $environment = null): array
    {
        return is_dir($directory) ? $this->load($directory, $environment) : [];
    }

    private function requireFile(string $path): mixed
    {
        return (static fn(): mixed => require $path)();
    }

    /** @return array<array-key, mixed> */
    private function arrayFromValue(mixed $value, string $name, ?object $environment): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!$value instanceof Closure) {
            throw new InvalidArgumentException(sprintf(
                'Application configuration file "%s.php" must return an array or Environment closure.',
                $name,
            ));
        }

        $this->assertClosureSignature($value, $name);

        return $this->arrayFromClosureResult($value($environment), $name);
    }

    /** @return array<array-key, mixed> */
    private function arrayFromClosureResult(mixed $configuration, string $name): array
    {
        if (!is_array($configuration)) {
            throw new InvalidArgumentException(sprintf(
                'Application configuration closure "%s.php" must return an array.',
                $name,
            ));
        }

        return $configuration;
    }

    private function assertClosureSignature(Closure $closure, string $name): void
    {
        $reflection = new ReflectionFunction($closure);
        $parameters = $reflection->getParameters();
        $parameter = $parameters[0] ?? null;
        $parameterType = $parameter?->getType();
        $returnType = $reflection->getReturnType();

        if (
            [
                count($parameters),
                $reflection->getNumberOfRequiredParameters(),
                $reflection->isStatic(),
                $parameter?->isPassedByReference(),
                $parameter?->isVariadic(),
                (string) $parameterType,
                (string) $returnType,
            ] !== [1, 1, true, false, false, 'BlackOps\Application\Environment', 'array']
        ) {
            throw new InvalidArgumentException(sprintf(
                'Application configuration closure "%s.php" must accept one Environment and return array.',
                $name,
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\Registry\OperationManifestFile;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;

final readonly class ProductionRuntimeArtifactLoader
{
    public function __construct(
        private OperationManifestFile $operations = new OperationManifestFile(),
        private HttpOperationManifestFile $http = new HttpOperationManifestFile(),
    ) {}

    public function load(
        string $operationManifest,
        string $httpManifest,
        string $container,
        string $containerClass,
        string $containerNamespace = '',
    ): ProductionRuntimeArtifacts {
        $operations = $this->operations->loadArtifact($operationManifest);
        $http = $this->http->loadArtifact($httpManifest);

        if ($operations->applicationBuildId !== $http->applicationBuildId) {
            throw new InvalidArgumentException('Production runtime manifest application build IDs do not match.');
        }

        return new ProductionRuntimeArtifacts(
            $operations->operations,
            $http->manifest,
            $this->container($container, $containerClass, $containerNamespace),
        );
    }

    private function container(string $path, string $class, string $namespace): ContainerInterface
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
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException('Runtime ' . $label . ' is invalid.');
        }
    }
}

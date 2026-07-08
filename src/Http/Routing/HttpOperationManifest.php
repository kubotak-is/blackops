<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use InvalidArgumentException;

final readonly class HttpOperationManifest
{
    /**
     * @param array<string, array<string, string>> $routes
     * @param array<string, array<string, string>> $operations
     */
    public function __construct(
        public array $routes,
        public array $operations,
    ) {}

    /**
     * @return array{routes: array<string, array<string, string>>, operations: array<string, array<string, string>>}
     */
    public function toArray(): array
    {
        return [
            'routes' => $this->routes,
            'operations' => $this->operations,
        ];
    }

    /**
     * @param iterable<Operation> $definitions
     */
    public function toRegistry(iterable $definitions): HttpRouteRegistry
    {
        $byClass = [];

        foreach ($definitions as $definition) {
            $byClass[$definition::class] = $definition;
        }

        $routes = [];

        foreach ($this->routes as $method => $paths) {
            foreach ($paths as $path => $typeId) {
                $operation = $this->operations[$typeId] ?? null;

                if ($operation === null) {
                    throw new InvalidArgumentException('HTTP manifest route references an unknown operation.');
                }

                $definition = $byClass[$operation['definition']] ?? null;

                if (!$definition instanceof Operation) {
                    throw new InvalidArgumentException('HTTP manifest requires operation definition instances.');
                }

                $value = $operation['value'];

                if (!is_a($value, OperationValue::class, allow_string: true)) {
                    throw new InvalidArgumentException('HTTP manifest operation value class is invalid.');
                }

                $routes[] = new HttpOperationRoute($method, $path, $definition, $value);
            }
        }

        return new HttpRouteRegistry($routes);
    }
}

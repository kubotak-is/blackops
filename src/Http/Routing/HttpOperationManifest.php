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
     * @param array{
     *     0: array<string, array<string, string>>,
     *     1: array<string, list<array{
     *         regex: string,
     *         routeMap: array<int, array{0: string, 1: array<string, string>}>
     *     }>>
     * } $dispatcherData
     */
    public function __construct(
        public array $routes,
        public array $operations,
        public array $dispatcherData,
    ) {}

    /**
     * @return array{
     *     routes: array<string, array<string, string>>,
     *     operations: array<string, array<string, string>>,
     *     dispatcherData: array{
     *         0: array<string, array<string, string>>,
     *         1: array<string, list<array{
     *             regex: string,
     *             routeMap: array<int, array{0: string, 1: array<string, string>}>
     *         }>>
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'routes' => $this->routes,
            'operations' => $this->operations,
            'dispatcherData' => $this->dispatcherData,
        ];
    }

    /**
     * @param iterable<Operation> $definitions
     */
    public function toRegistry(iterable $definitions): HttpRouteRegistry
    {
        $byClass = [];
        $instances = [];

        foreach ($definitions as $definition) {
            $byClass[$definition::class] = $definition;
            $instances[] = $definition;
        }

        $routes = [];

        foreach ($this->routes as $method => $paths) {
            foreach ($paths as $path => $typeId) {
                $operation = $this->operations[$typeId] ?? null;

                if ($operation === null) {
                    throw new InvalidArgumentException('HTTP manifest route references an unknown operation.');
                }

                $definitionClass = $operation['definition'];
                /** @var class-string<Operation> $definitionClass */
                $definition = $byClass[$definitionClass] ?? null;

                if (!$definition instanceof Operation) {
                    $definition = $this->closestInstance($instances, $definitionClass);
                }

                if (!$definition instanceof Operation) {
                    throw new InvalidArgumentException('HTTP manifest requires operation definition instances.');
                }

                $value = $operation['value'];

                if (!is_a($value, OperationValue::class, allow_string: true)) {
                    throw new InvalidArgumentException('HTTP manifest operation value class is invalid.');
                }

                $routes[$typeId] = new HttpOperationRoute($method, $path, $definition, $value);
            }
        }

        return new HttpRouteRegistry($routes, $this->dispatcherData);
    }

    /**
     * @param list<Operation> $instances
     * @param class-string<Operation> $expected
     */
    private function closestInstance(array $instances, string $expected): ?Operation
    {
        $candidates = array_values(array_filter(
            $instances,
            static fn(Operation $instance): bool => $instance instanceof $expected,
        ));
        usort(
            $candidates,
            static fn(Operation $left, Operation $right): int => (
                count((array) class_parents($left)) <=> count((array) class_parents($right))
            ),
        );

        return $candidates[0] ?? null;
    }
}

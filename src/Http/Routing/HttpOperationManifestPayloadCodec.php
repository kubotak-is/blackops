<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use BlackOps\Core\Execution\Inline;
use InvalidArgumentException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class HttpOperationManifestPayloadCodec
{
    public function __construct(
        private HttpDispatcherDataCodec $dispatcherData = new HttpDispatcherDataCodec(),
        private HttpManifestRouteHandlerSet $routeHandlers = new HttpManifestRouteHandlerSet(),
    ) {}

    public function decode(mixed $data): HttpOperationManifest
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('HTTP manifest payload is missing or invalid.');
        }

        $routes = $this->routeSection($data, 'routes');
        $operations = $this->operationSection($data, 'operations');
        $routeHandlers = $this->routeHandlers->extract($routes, $operations);
        foreach ($operations as $typeId => $operation) {
            if ($operation['ephemeral'] && !array_key_exists($typeId, $routeHandlers)) {
                throw new InvalidArgumentException('HTTP manifest ephemeral operation requires a route.');
            }
        }

        return new HttpOperationManifest(
            $routes,
            $operations,
            $this->dispatcherData->decode($data['dispatcherData'] ?? null, $routeHandlers),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, array<string, string>>
     */
    private function routeSection(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            throw new InvalidArgumentException('HTTP manifest file must return a manifest array.');
        }

        $result = [];

        foreach (array_keys($data[$key]) as $outerKey) {
            if (!is_string($outerKey) || !is_array($data[$key][$outerKey])) {
                throw new InvalidArgumentException('HTTP manifest file must return a manifest array.');
            }

            $result[$outerKey] = $this->stringValues($data[$key][$outerKey]);
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, array<string, string|bool>>
     */
    private function operationSection(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            throw new InvalidArgumentException('HTTP manifest file must return a manifest array.');
        }

        $result = [];
        foreach (array_keys($data[$key]) as $typeId) {
            if (!is_string($typeId)) {
                throw new InvalidArgumentException('HTTP manifest file must return a manifest array.');
            }
            $result[$typeId] = $this->operation($data[$key][$typeId]);
        }

        return $result;
    }

    /**
     * @return array{
     *     definition: string,
     *     ephemeral: bool,
     *     handler: string,
     *     outcome: string,
     *     strategy: string,
     *     value: string
     * }
     */
    private function operation(mixed $operation): array
    {
        if (!is_array($operation)) {
            throw new InvalidArgumentException('HTTP manifest file must return a manifest array.');
        }
        $expected = ['definition', 'ephemeral', 'handler', 'outcome', 'strategy', 'value'];
        $actual = array_keys($operation);
        sort($actual);
        if ($actual !== $expected || !is_bool($operation['ephemeral'])) {
            throw new InvalidArgumentException('HTTP manifest operation metadata is invalid.');
        }
        foreach (['definition', 'handler', 'outcome', 'strategy', 'value'] as $field) {
            if (!is_string($operation[$field]) || $operation[$field] === '') {
                throw new InvalidArgumentException('HTTP manifest operation metadata is invalid.');
            }
        }
        /**
         * @var array{
         *     definition: string,
         *     ephemeral: bool,
         *     handler: string,
         *     outcome: string,
         *     strategy: string,
         *     value: string
         * } $operation
         */
        if (
            !class_exists($operation['outcome'])
            || !is_subclass_of($operation['outcome'], \BlackOps\Core\Outcome::class)
            || $operation['ephemeral'] !== is_subclass_of($operation['outcome'], \BlackOps\Core\EphemeralOutcome::class)
            || $operation['ephemeral'] && $operation['strategy'] !== Inline::class
        ) {
            throw new InvalidArgumentException('HTTP manifest operation ephemeral metadata is invalid.');
        }

        return $operation;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<string, string>
     */
    private function stringValues(array $values): array
    {
        $result = [];

        foreach (array_keys($values) as $key) {
            if (!is_string($key) || !is_string($values[$key])) {
                throw new InvalidArgumentException('HTTP manifest file must return a manifest array.');
            }

            $result[$key] = $values[$key];
        }

        return $result;
    }
}

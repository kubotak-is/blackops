<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpOperationManifestPayloadCodec
{
    public function decode(mixed $data): HttpOperationManifest
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('HTTP manifest payload is missing or invalid.');
        }

        return new HttpOperationManifest($this->section($data, 'routes'), $this->section($data, 'operations'));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, array<string, string>>
     */
    private function section(array $data, string $key): array
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

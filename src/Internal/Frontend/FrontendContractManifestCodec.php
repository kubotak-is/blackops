<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

use InvalidArgumentException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class FrontendContractManifestCodec
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return array{schemaVersion: int, applicationBuildId: string, payload: array{operations: list<array<string, mixed>>}}
     */
    public function encode(FrontendContractManifest $manifest, string $applicationBuildId): array
    {
        $this->assertBuildId($applicationBuildId);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'applicationBuildId' => $applicationBuildId,
            'payload' => [
                'operations' => array_map($this->encodeOperation(...), $manifest->operations),
            ],
        ];
    }

    public function decode(mixed $data): FrontendContractManifestArtifact
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Frontend contract manifest must return a versioned array.');
        }

        /** @var mixed $schemaVersion */
        $schemaVersion = $data['schemaVersion'] ?? null;
        if (!is_int($schemaVersion) || $schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Frontend contract manifest schema version is not supported.');
        }

        /** @var mixed $applicationBuildId */
        $applicationBuildId = $data['applicationBuildId'] ?? null;
        if (!is_string($applicationBuildId)) {
            throw new InvalidArgumentException('Frontend contract manifest application build ID is invalid.');
        }
        $this->assertBuildId($applicationBuildId);

        /** @var mixed $payload */
        $payload = $data['payload'] ?? null;
        if (
            !is_array($payload)
            || !is_array($payload['operations'] ?? null)
            || !array_is_list($payload['operations'])
        ) {
            throw new InvalidArgumentException('Frontend contract manifest payload is invalid.');
        }

        return new FrontendContractManifestArtifact(
            $schemaVersion,
            $applicationBuildId,
            new FrontendContractManifest(array_map($this->decodeOperation(...), $payload['operations'])),
        );
    }

    /** @return array<string, mixed> */
    private function encodeOperation(FrontendOperationContract $operation): array
    {
        return [
            'typeId' => $operation->typeId,
            'definition' => $operation->definition,
            'exportName' => $operation->exportName,
            'module' => $operation->module,
            'method' => $operation->method,
            'path' => $operation->path,
            'strategy' => $operation->strategy,
            'value' => [
                'class' => $operation->value->class,
                'fields' => array_map(static fn(FrontendValueFieldContract $field): array => [
                    'name' => $field->name,
                    'type' => $field->type,
                    'nullable' => $field->nullable,
                    'required' => $field->required,
                    'source' => $field->source,
                    'transportName' => $field->transportName,
                    'sensitive' => $field->sensitive,
                    'validations' => array_map(static fn(FrontendValidationContract $validation): array => [
                        'rule' => $validation->rule,
                        'code' => $validation->code,
                        'parameters' => $validation->parameters,
                    ], $field->validations),
                ], $operation->value->fields),
            ],
            'outcome' => [
                'class' => $operation->outcome->class,
                'mode' => $operation->outcome->mode,
                'fields' => array_map(static fn(FrontendOutcomeFieldContract $field): array => [
                    'name' => $field->name,
                    'type' => $field->type,
                    'nullable' => $field->nullable,
                ], $operation->outcome->fields),
            ],
        ];
    }

    private function decodeOperation(mixed $data): FrontendOperationContract
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Frontend contract operation entry is invalid.');
        }

        $value = $this->section($data, 'value');
        $outcome = $this->section($data, 'outcome');

        return new FrontendOperationContract(
            $this->string($data, 'typeId'),
            $this->string($data, 'definition'),
            $this->string($data, 'exportName'),
            $this->string($data, 'module'),
            $this->string($data, 'method'),
            $this->string($data, 'path'),
            $this->oneOf($data, 'strategy', ['inline', 'deferred']),
            new FrontendValueContract(
                $this->string($value, 'class'),
                $this->decodeList($value, 'fields', $this->decodeValueField(...)),
            ),
            new FrontendOutcomeContract(
                $this->string($outcome, 'class'),
                $this->oneOf($outcome, 'mode', ['outcome', 'void']),
                $this->decodeList($outcome, 'fields', $this->decodeOutcomeField(...)),
            ),
        );
    }

    private function decodeValueField(mixed $data): FrontendValueFieldContract
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Frontend contract value field is invalid.');
        }

        return new FrontendValueFieldContract(
            $this->string($data, 'name'),
            $this->oneOf($data, 'type', ['string', 'number', 'boolean']),
            $this->boolean($data, 'nullable'),
            $this->boolean($data, 'required'),
            $this->oneOf($data, 'source', ['path', 'query', 'header', 'body']),
            $this->string($data, 'transportName'),
            $this->boolean($data, 'sensitive'),
            $this->decodeList($data, 'validations', $this->decodeValidation(...)),
        );
    }

    private function decodeValidation(mixed $data): FrontendValidationContract
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Frontend contract validation entry is invalid.');
        }

        $parameters = $this->section($data, 'parameters');
        foreach (array_keys($parameters) as $key) {
            /** @var mixed $value */
            $value = $parameters[$key];
            if (!$this->isPublicParameter($value)) {
                throw new InvalidArgumentException('Frontend contract validation parameters are invalid.');
            }
        }

        /** @var array<string, bool|float|int|string|list<bool|float|int|string>> $parameters */
        return new FrontendValidationContract($this->string($data, 'rule'), $this->string($data, 'code'), $parameters);
    }

    private function decodeOutcomeField(mixed $data): FrontendOutcomeFieldContract
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Frontend contract outcome field is invalid.');
        }

        return new FrontendOutcomeFieldContract(
            $this->string($data, 'name'),
            $this->oneOf($data, 'type', ['string', 'number', 'boolean']),
            $this->boolean($data, 'nullable'),
        );
    }

    /** @param array<array-key, mixed> $data @return array<array-key, mixed> */
    private function section(array $data, string $key): array
    {
        if (!is_array($data[$key] ?? null)) {
            throw new InvalidArgumentException('Frontend contract manifest entry is invalid.');
        }

        return $data[$key];
    }

    /** @param array<array-key, mixed> $data */
    private function string(array $data, string $key): string
    {
        if (!is_string($data[$key] ?? null) || $data[$key] === '') {
            throw new InvalidArgumentException('Frontend contract manifest string field is invalid.');
        }

        return $data[$key];
    }

    /** @param array<array-key, mixed> $data @param list<string> $allowed */
    private function oneOf(array $data, string $key, array $allowed): string
    {
        $value = $this->string($data, $key);
        if (!in_array($value, $allowed, strict: true)) {
            throw new InvalidArgumentException('Frontend contract manifest enum field is invalid.');
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    private function boolean(array $data, string $key): bool
    {
        if (!is_bool($data[$key] ?? null)) {
            throw new InvalidArgumentException('Frontend contract manifest boolean field is invalid.');
        }

        return $data[$key];
    }

    /**
     * @template T
     * @param array<array-key, mixed> $data
     * @param callable(mixed): T $decoder
     * @return list<T>
     */
    private function decodeList(array $data, string $key, callable $decoder): array
    {
        /** @var mixed $values */
        $values = $data[$key] ?? null;
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException('Frontend contract manifest list field is invalid.');
        }

        return array_map($decoder, $values);
    }

    private function isPublicParameter(mixed $value): bool
    {
        if (is_bool($value) || is_int($value) || is_string($value)) {
            return true;
        }
        if (is_float($value)) {
            return is_finite($value);
        }
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        return array_all(
            $value,
            static fn(mixed $item): bool => (
                is_bool($item)
                || is_int($item)
                || is_string($item)
                || is_float($item) && is_finite($item)
            ),
        );
    }

    private function assertBuildId(string $applicationBuildId): void
    {
        if (trim($applicationBuildId) === '') {
            throw new InvalidArgumentException('Frontend contract manifest application build ID must not be empty.');
        }
    }
}

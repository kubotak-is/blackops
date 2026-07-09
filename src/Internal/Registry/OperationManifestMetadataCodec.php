<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use InvalidArgumentException;

final readonly class OperationManifestMetadataCodec
{
    /**
     * @return array{operations: list<array<string, string>>}
     */
    public function encode(OperationRegistry $registry): array
    {
        return [
            'operations' => array_map(static fn(OperationMetadata $metadata): array => [
                'typeId' => $metadata->typeId,
                'definition' => $metadata->definition,
                'value' => $metadata->value,
                'handler' => $metadata->handler,
                'outcome' => $metadata->outcome,
                'strategy' => $metadata->strategy,
            ], $registry->all()),
        ];
    }

    /**
     * @return list<OperationMetadata>
     */
    public function decode(mixed $data): array
    {
        if (!is_array($data) || !array_key_exists('operations', $data) || !is_array($data['operations'])) {
            throw new InvalidArgumentException('Operation manifest file must return a manifest array.');
        }

        return array_map($this->metadataFrom(...), array_values($data['operations']));
    }

    private function metadataFrom(mixed $entry): OperationMetadata
    {
        if (!is_array($entry)) {
            throw new InvalidArgumentException('Operation manifest metadata entry is invalid.');
        }

        return new OperationMetadata(
            $this->stringField($entry, 'typeId'),
            $this->classField($entry, 'definition', Operation::class),
            $this->classField($entry, 'value', OperationValue::class),
            $this->classField($entry, 'handler', OperationHandler::class),
            $this->classField($entry, 'outcome', Outcome::class),
            $this->classField($entry, 'strategy', ExecutionStrategy::class),
        );
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function stringField(array $entry, string $key): string
    {
        if (!array_key_exists($key, $entry) || !is_string($entry[$key]) || $entry[$key] === '') {
            throw new InvalidArgumentException('Operation manifest metadata entry is invalid.');
        }

        return $entry[$key];
    }

    /**
     * @template T of object
     *
     * @param array<array-key, mixed> $entry
     * @param class-string<T> $interface
     *
     * @return class-string<T>
     */
    private function classField(array $entry, string $key, string $interface): string
    {
        $class = $this->stringField($entry, $key);

        if (!is_a($class, $interface, allow_string: true)) {
            throw new InvalidArgumentException('Operation manifest metadata entry is invalid.');
        }

        return $class;
    }
}

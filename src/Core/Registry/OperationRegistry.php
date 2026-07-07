<?php

declare(strict_types=1);

namespace BlackOps\Core\Registry;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Operation;
use InvalidArgumentException;

#[PublicApi]
final readonly class OperationRegistry
{
    /** @var list<OperationMetadata> */
    private array $metadata;

    /** @var array<string, OperationMetadata> */
    private array $byTypeId;

    /** @var array<class-string<Operation>, OperationMetadata> */
    private array $byDefinition;

    /** @param iterable<OperationMetadata> $metadata */
    public function __construct(iterable $metadata)
    {
        $all = [];
        $byTypeId = [];
        $byDefinition = [];

        foreach ($metadata as $item) {
            if (array_key_exists($item->typeId, $byTypeId) || array_key_exists($item->definition, $byDefinition)) {
                throw new InvalidArgumentException('Operation registry requires unique metadata indexes.');
            }

            $all[] = $item;
            $byTypeId[$item->typeId] = $item;
            $byDefinition[$item->definition] = $item;
        }

        $this->metadata = $all;
        $this->byTypeId = $byTypeId;
        $this->byDefinition = $byDefinition;
    }

    public function findByTypeId(string $typeId): ?OperationMetadata
    {
        return $this->byTypeId[$typeId] ?? null;
    }

    /** @param class-string<Operation> $definition */
    public function findByDefinition(string $definition): ?OperationMetadata
    {
        return $this->byDefinition[$definition] ?? null;
    }

    /** @return list<OperationMetadata> */
    public function all(): array
    {
        return $this->metadata;
    }
}

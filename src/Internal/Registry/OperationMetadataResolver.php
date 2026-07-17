<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Operation;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;

final readonly class OperationMetadataResolver
{
    public function __construct(
        private OperationRegistry $registry,
    ) {}

    public function resolve(Operation $definition): ?OperationMetadata
    {
        $class = $definition::class;
        $metadata = $this->registry->findByDefinition($class);

        while ($metadata === null && ($parent = get_parent_class($class)) !== false) {
            /** @var class-string<Operation> $parent */
            $metadata = $this->registry->findByDefinition($parent);
            $class = $parent;
        }

        return $metadata;
    }
}

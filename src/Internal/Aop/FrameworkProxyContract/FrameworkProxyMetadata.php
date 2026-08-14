<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

final readonly class FrameworkProxyMetadata
{
    /** @param list<FrameworkProxyMethodMetadata> $methods */
    public function __construct(
        public string $sourceClass,
        public FrameworkProxyProfile $profile,
        public FrameworkProxyOwnership $ownership,
        public bool $classTransactional,
        public ?string $classTransactionalConnection,
        public array $methods,
        public bool $proxyTarget = true,
        public bool $readonlyClass = false,
    ) {}

    public function marker(): FrameworkProxyOwnershipMarker
    {
        return new FrameworkProxyOwnershipMarker(
            $this->sourceClass,
            $this->ownership,
            $this->profile,
            $this->ownership === FrameworkProxyOwnership::OPERATION,
        );
    }

    public function method(string $name): ?FrameworkProxyMethodMetadata
    {
        foreach ($this->methods as $method) {
            if ($method->name === $name) {
                return $method;
            }
        }

        return null;
    }
}

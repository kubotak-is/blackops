<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyGenerator;

use ReflectionClass;

final readonly class FrameworkProxyGenerationTarget
{
    /**
     * @param class-string|ReflectionClass<object> $sourceClass
     * @param array<string,true>|list<string> $connectionNames
     */
    public function __construct(
        public string|ReflectionClass $sourceClass,
        public ?string $serviceId = null,
        public ?string $defaultConnection = null,
        public array $connectionNames = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

final readonly class FrameworkProxyAttributeResolver
{
    /** @mago-expect lint:excessive-parameter-list */
    private function assertUnique(
        int $count,
        string $sourceClass,
        ?string $method,
        string $attribute,
        ?string $serviceId = null,
        ?string $buildId = null,
    ): void {
        if ($count > 1) {
            throw new FrameworkProxyContractException(
                new FrameworkProxyDiagnostic(
                    FrameworkProxyDiagnosticCode::ATTRIBUTE_DUPLICATE,
                    serviceId: $serviceId,
                    sourceClass: $sourceClass,
                    method: $method,
                    attribute: $attribute,
                    buildId: $buildId,
                ),
            );
        }
    }

    /** @return array{transactional:?Transactional,after_commit:?AfterCommit} */
    public function method(ReflectionMethod $method, ?string $serviceId = null, ?string $buildId = null): array
    {
        return [
            'transactional' => $this->transactional($method, $serviceId, $buildId),
            'after_commit' => $this->afterCommit($method, $serviceId, $buildId),
        ];
    }

    /** @return array{transactional:?Transactional,after_commit:?AfterCommit} */
    public function class(ReflectionClass $class, ?string $serviceId = null, ?string $buildId = null): array
    {
        return [
            'transactional' => $this->transactional($class, $serviceId, $buildId),
            'after_commit' => $this->afterCommit($class, $serviceId, $buildId),
        ];
    }

    private function transactional(
        ReflectionClass|ReflectionMethod $reflection,
        ?string $serviceId,
        ?string $buildId,
    ): ?Transactional {
        $attributes = $reflection->getAttributes(Transactional::class, ReflectionAttribute::IS_INSTANCEOF);

        $attribute = $this->instantiate($reflection, $attributes, Transactional::class, $serviceId, $buildId);

        return $attribute instanceof Transactional ? $attribute : null;
    }

    private function afterCommit(
        ReflectionClass|ReflectionMethod $reflection,
        ?string $serviceId,
        ?string $buildId,
    ): ?AfterCommit {
        $attributes = $reflection->getAttributes(AfterCommit::class, ReflectionAttribute::IS_INSTANCEOF);

        $attribute = $this->instantiate($reflection, $attributes, AfterCommit::class, $serviceId, $buildId);

        return $attribute instanceof AfterCommit ? $attribute : null;
    }

    /** @param list<ReflectionAttribute> $attributes @param class-string<Transactional>|class-string<AfterCommit> $attribute */
    private function instantiate(
        ReflectionClass|ReflectionMethod $reflection,
        array $attributes,
        string $attribute,
        ?string $serviceId,
        ?string $buildId,
    ): ?object {
        if ($attributes === []) {
            return null;
        }

        if (count($attributes) !== 1) {
            $this->assertUnique(
                count($attributes),
                $this->className($reflection),
                $reflection instanceof ReflectionMethod ? $reflection->getName() : null,
                $attribute,
                $serviceId,
                $buildId,
            );
        }

        try {
            return $attributes[0]->newInstance();
        } catch (Throwable) {
            throw new FrameworkProxyContractException(
                new FrameworkProxyDiagnostic(
                    FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
                    sourceClass: $this->className($reflection),
                    method: $reflection instanceof ReflectionMethod ? $reflection->getName() : null,
                    attribute: $attribute,
                    buildId: $buildId,
                    serviceId: $serviceId,
                ),
            );
        }
    }

    private function className(ReflectionClass|ReflectionMethod $reflection): string
    {
        return $reflection instanceof ReflectionMethod
            ? $reflection->getDeclaringClass()->getName()
            : $reflection->getName();
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class FrameworkProxyContract
{
    public function __construct(
        private FrameworkProxyAttributeResolver $attributes = new FrameworkProxyAttributeResolver(),
        private FrameworkProxySignatureValidator $signatures = new FrameworkProxySignatureValidator(),
        private FrameworkProxyConnectionGuard $connections = new FrameworkProxyConnectionGuard(),
    ) {}

    /**
     * @param class-string|ReflectionClass<object> $sourceClass
     * @param array<string,true>|list<string> $connectionNames
     * @mago-expect lint:halstead
     * @mago-expect lint:excessive-parameter-list
     */
    public function inspect(
        string|ReflectionClass $sourceClass,
        string|FrameworkProxyProfile $profile = FrameworkProxyProfile::FRAMEWORK,
        ?string $serviceId = null,
        ?string $buildId = null,
        ?string $defaultConnection = null,
        array $connectionNames = [],
    ): FrameworkProxyMetadata {
        $class = $sourceClass instanceof ReflectionClass ? $sourceClass : new ReflectionClass($sourceClass);
        $resolvedProfile = FrameworkProxyProfile::from($profile);
        $connectionNames = $this->normalizeConnectionNames($connectionNames);
        /** @var array<string,true> $connectionNames */
        $classAttributes = $this->attributes->class($class, $serviceId, $buildId);

        $hasTarget =
            $classAttributes['transactional'] instanceof Transactional
            || $classAttributes['after_commit'] instanceof AfterCommit
            || $this->hasMemberTarget($class);

        if (!$hasTarget) {
            return new FrameworkProxyMetadata(
                $class->getName(),
                $resolvedProfile,
                $this->ownership($class),
                false,
                null,
                [],
                false,
                $class->isReadOnly(),
            );
        }

        $classCode = $this->signatures->classDiagnosticCode($class);

        if ($classCode !== null) {
            throw $this->error($classCode, $class, null, null, $serviceId, $buildId);
        }

        if ($classAttributes['after_commit'] instanceof AfterCommit) {
            throw $this->error(
                FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
                $class,
                null,
                AfterCommit::class,
                $serviceId,
                $buildId,
            );
        }

        $this->assertUnsupportedTargets($class, $serviceId, $buildId);

        $classTransactional = $classAttributes['transactional'];
        $classConnection = $classTransactional instanceof Transactional
            ? $this->connection(
                $classTransactional->connection,
                $defaultConnection,
                $connectionNames,
                $class,
                null,
                $serviceId,
                $buildId,
            )
            : null;
        $methods = [];

        foreach ($class->getMethods() as $method) {
            $methodAttributes = $this->attributes->method($method, $serviceId, $buildId);
            $explicitTransactional = $methodAttributes['transactional'];
            $afterCommit = $methodAttributes['after_commit'];
            $classApplies = $classTransactional instanceof Transactional && $this->classCandidate($method);

            if (
                !$classApplies
                && !$explicitTransactional instanceof Transactional
                && !$afterCommit instanceof AfterCommit
            ) {
                continue;
            }

            if ($explicitTransactional instanceof Transactional && $afterCommit instanceof AfterCommit) {
                throw $this->error(
                    FrameworkProxyDiagnosticCode::ATTRIBUTE_CONFLICT,
                    $class,
                    $method,
                    Transactional::class,
                    $serviceId,
                    $buildId,
                );
            }

            $effectiveTransactional = $explicitTransactional instanceof Transactional || $classApplies;
            $code = $this->signatures->diagnosticCode($method, $afterCommit instanceof AfterCommit);

            if ($code === null && $this->signatures->hasInaccessibleDefault($method)) {
                $code = FrameworkProxyDiagnosticCode::SIGNATURE_DEFAULT_VALUE;
            }

            if ($code !== null) {
                throw $this->error(
                    $code,
                    $class,
                    $method,
                    $this->attributeName($effectiveTransactional, $afterCommit),
                    $serviceId,
                    $buildId,
                );
            }

            $connection = $explicitTransactional instanceof Transactional
                ? $this->connection(
                    $explicitTransactional->connection,
                    $defaultConnection,
                    $connectionNames,
                    $class,
                    $method,
                    $serviceId,
                    $buildId,
                )
                : null;

            if ($connection === null && $classApplies && !$explicitTransactional instanceof Transactional) {
                $connection = $classConnection;
            }

            $methods[] = new FrameworkProxyMethodMetadata(
                $method->getName(),
                $method->getDeclaringClass()->getName(),
                $connection,
                $effectiveTransactional,
                $afterCommit instanceof AfterCommit,
                FrameworkProxySignatureClassification::SUPPORT,
                null,
                $this->signature($method),
                $this->parameters($method),
                $this->typeName($method->getReturnType(), $method->getDeclaringClass()),
                $this->unrelatedAttributes($method),
            );
        }

        usort($methods, static fn(
            FrameworkProxyMethodMetadata $left,
            FrameworkProxyMethodMetadata $right,
        ): int => strcmp($left->declaringClass . '::' . $left->name, $right->declaringClass . '::' . $right->name));

        return new FrameworkProxyMetadata(
            $class->getName(),
            $resolvedProfile,
            $this->ownership($class),
            $classTransactional instanceof Transactional,
            $classConnection,
            $methods,
            true,
            $class->isReadOnly(),
        );
    }

    /** @param class-string|ReflectionClass<object> $sourceClass */
    public function validate(
        string|ReflectionClass $sourceClass,
        string|FrameworkProxyProfile $profile = FrameworkProxyProfile::FRAMEWORK,
    ): FrameworkProxyMetadata {
        return $this->inspect($sourceClass, $profile);
    }

    /** @param ReflectionClass<object> $class */
    private function assertUnsupportedTargets(ReflectionClass $class, ?string $serviceId, ?string $buildId): void
    {
        foreach ($class->getProperties() as $property) {
            foreach ([Transactional::class, AfterCommit::class] as $attribute) {
                if ($property->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF) !== []) {
                    throw $this->error(
                        FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
                        $class,
                        null,
                        $attribute,
                        $serviceId,
                        $buildId,
                    );
                }
            }
        }

        foreach ($class->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                foreach ([Transactional::class, AfterCommit::class] as $attribute) {
                    if ($parameter->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF) !== []) {
                        throw $this->error(
                            FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
                            $class,
                            $method,
                            $attribute,
                            $serviceId,
                            $buildId,
                        );
                    }
                }
            }
        }
    }

    /** @param ReflectionClass<object> $class */
    private function hasMemberTarget(ReflectionClass $class): bool
    {
        foreach ($class->getProperties() as $property) {
            if (
                $property->getAttributes(Transactional::class, ReflectionAttribute::IS_INSTANCEOF) !== []
                || $property->getAttributes(AfterCommit::class, ReflectionAttribute::IS_INSTANCEOF) !== []
            ) {
                return true;
            }
        }

        foreach ($class->getMethods() as $method) {
            if (
                $method->getAttributes(Transactional::class, ReflectionAttribute::IS_INSTANCEOF) !== []
                || $method->getAttributes(AfterCommit::class, ReflectionAttribute::IS_INSTANCEOF) !== []
            ) {
                return true;
            }

            foreach ($method->getParameters() as $parameter) {
                if (
                    $parameter->getAttributes(Transactional::class, ReflectionAttribute::IS_INSTANCEOF) !== []
                    || $parameter->getAttributes(AfterCommit::class, ReflectionAttribute::IS_INSTANCEOF) !== []
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function classCandidate(ReflectionMethod $method): bool
    {
        return $method->isPublic() && !$method->isStatic() && !$method->isConstructor() && !$method->isDestructor();
    }

    private function attributeName(bool $transactional, ?AfterCommit $afterCommit): string
    {
        return $afterCommit instanceof AfterCommit ? AfterCommit::class : Transactional::class;
    }

    private function ownership(ReflectionClass $class): FrameworkProxyOwnership
    {
        return is_a($class->getName(), Operation::class, allow_string: true)
            ? FrameworkProxyOwnership::OPERATION
            : FrameworkProxyOwnership::SERVICE;
    }

    private function signature(ReflectionMethod $method): string
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameters[] = implode(':', [
                $parameter->getName(),
                $this->typeName($parameter->getType(), $method->getDeclaringClass()) ?? '',
                $parameter->isPassedByReference() ? '&' : '',
                $parameter->isVariadic() ? '...' : '',
                $this->defaultIdentity($parameter),
            ]);
        }

        return (
            ($method->returnsReference() ? '&' : '')
            . $method->getName()
            . '('
            . implode(',', $parameters)
            . '):'
            . ($this->typeName($method->getReturnType(), $method->getDeclaringClass()) ?? '')
        );
    }

    /** @return list<array{name:string,type:?string,reference:bool,variadic:bool,hasDefault:bool,default:mixed,defaultConstantName:?string}> */
    private function parameters(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $default = $this->defaultValue($parameter);
            $parameters[] = [
                'name' => $parameter->getName(),
                'type' => $this->typeName($parameter->getType(), $method->getDeclaringClass()),
                'reference' => $parameter->isPassedByReference(),
                'variadic' => $parameter->isVariadic(),
                'hasDefault' => $default['hasDefault'],
                'default' => $default['value'],
                'defaultConstantName' => $this->defaultConstantName($parameter),
            ];
        }

        return $parameters;
    }

    /** @return list<string> */
    private function unrelatedAttributes(ReflectionMethod $method): array
    {
        $names = [];

        foreach ($method->getAttributes() as $attribute) {
            if ($attribute->getName() === Transactional::class || $attribute->getName() === AfterCommit::class) {
                continue;
            }

            $names[] = $attribute->getName();
        }

        sort($names);

        return $names;
    }

    private function defaultIdentity(ReflectionParameter $parameter): string
    {
        $default = $this->defaultValue($parameter);

        if (!$default['hasDefault']) {
            return '';
        }

        $constant = $this->defaultConstantName($parameter);

        return $constant ?? serialize($default['value']);
    }

    private function typeName(?\ReflectionType $type, ReflectionClass $class): ?string
    {
        if ($type === null) {
            return null;
        }

        $name = (string) $type;

        if ($name === 'static') {
            return 'static';
        }

        return $type instanceof \ReflectionNamedType ? $this->namedTypeName($name, $class) : $name;
    }

    private function namedTypeName(string $name, ReflectionClass $class): string
    {
        if ($name === $class->getName()) {
            return 'self';
        }

        $parent = $class->getParentClass();

        return $parent !== false && $name === $parent->getName() ? 'parent' : $name;
    }

    private function defaultConstantName(ReflectionParameter $parameter): ?string
    {
        try {
            if (!$parameter->isDefaultValueConstant()) {
                return null;
            }

            $name = $parameter->getDefaultValueConstantName();

            return is_string($name) && $name !== '' ? $name : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{hasDefault:bool,value:mixed} */
    private function defaultValue(ReflectionParameter $parameter): array
    {
        if (!$parameter->isDefaultValueAvailable()) {
            return ['hasDefault' => false, 'value' => null];
        }

        try {
            return ['hasDefault' => true, 'value' => $parameter->getDefaultValue()];
        } catch (Throwable) {
            return ['hasDefault' => false, 'value' => null];
        }
    }

    /** @mago-expect lint:excessive-parameter-list */
    private function error(
        string $code,
        ReflectionClass $class,
        ?ReflectionMethod $method,
        ?string $attribute,
        ?string $serviceId,
        ?string $buildId,
    ): FrameworkProxyContractException {
        return new FrameworkProxyContractException(
            new FrameworkProxyDiagnostic(
                $code,
                $serviceId,
                $class->getName(),
                $method?->getName(),
                $attribute,
                $buildId,
            ),
        );
    }

    /** @param array<string,true>|list<string> $connectionNames @return array<string,true> */
    private function normalizeConnectionNames(array $connectionNames): array
    {
        return array_is_list($connectionNames) ? array_fill_keys($connectionNames, value: true) : $connectionNames;
    }

    /**
     * @param array<string,true> $connectionNames
     * @mago-expect lint:excessive-parameter-list
     */
    private function connection(
        ?string $requested,
        ?string $default,
        array $connectionNames,
        ReflectionClass $class,
        ?ReflectionMethod $method,
        ?string $serviceId,
        ?string $buildId,
    ): ?string {
        if ($default === null && $connectionNames === []) {
            return $requested;
        }

        return $this->connections->resolve(
            $requested,
            $default,
            $connectionNames,
            $class->getName(),
            $method?->getName(),
            $serviceId,
            $buildId,
        );
    }
}

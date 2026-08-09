<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class FrameworkProxySignatureValidator
{
    public function classifyClass(ReflectionClass $class): FrameworkProxySignatureClassification
    {
        if (!$class->isInstantiable() || $class->isInterface() || $class->isTrait()) {
            return FrameworkProxySignatureClassification::NOT_APPLICABLE;
        }

        return $class->isFinal()
            ? FrameworkProxySignatureClassification::REJECT
            : FrameworkProxySignatureClassification::SUPPORT;
    }

    public function classDiagnosticCode(ReflectionClass $class): ?string
    {
        $classification = $this->classifyClass($class);

        if ($classification === FrameworkProxySignatureClassification::REJECT) {
            return FrameworkProxyDiagnosticCode::SIGNATURE_FINAL_CLASS;
        }

        return $classification === FrameworkProxySignatureClassification::NOT_APPLICABLE
            ? FrameworkProxyDiagnosticCode::TARGET_NOT_CONCRETE
            : null;
    }

    /** @mago-expect lint:no-boolean-flag-parameter */
    public function classifyMethod(
        ReflectionMethod $method,
        bool $afterCommit = false,
    ): FrameworkProxySignatureClassification {
        return $this->diagnosticCode($method, $afterCommit) === null
            ? FrameworkProxySignatureClassification::SUPPORT
            : FrameworkProxySignatureClassification::REJECT;
    }

    /** @mago-expect lint:no-boolean-flag-parameter */
    public function diagnosticCode(ReflectionMethod $method, bool $afterCommit = false): ?string
    {
        if ($method->isFinal()) {
            return FrameworkProxyDiagnosticCode::SIGNATURE_FINAL_METHOD;
        }

        if ($method->isConstructor() || $method->isDestructor() || !$method->isPublic()) {
            return FrameworkProxyDiagnosticCode::SIGNATURE_VISIBILITY;
        }

        if ($method->isStatic()) {
            return FrameworkProxyDiagnosticCode::SIGNATURE_STATIC;
        }

        if ($method->isGenerator()) {
            return FrameworkProxyDiagnosticCode::SIGNATURE_GENERATOR;
        }

        if ($method->returnsReference()) {
            return FrameworkProxyDiagnosticCode::SIGNATURE_REFERENCE_RETURN;
        }

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isPassedByReference()) {
                return FrameworkProxyDiagnosticCode::SIGNATURE_REFERENCE_PARAMETER;
            }

            if ($parameter->isVariadic()) {
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                try {
                    if (is_object($parameter->getDefaultValue()) && !$this->hasConstantDefault($parameter)) {
                        return FrameworkProxyDiagnosticCode::SIGNATURE_DEFAULT_VALUE;
                    }
                } catch (Throwable) {
                    return FrameworkProxyDiagnosticCode::SIGNATURE_DEFAULT_VALUE;
                }
            }
        }

        if ($afterCommit) {
            $return = $method->getReturnType();

            if ($return === null || (string) $return !== 'void') {
                return FrameworkProxyDiagnosticCode::SIGNATURE_AFTER_COMMIT_RETURN;
            }
        }

        return null;
    }

    public function hasInaccessibleDefault(ReflectionMethod $method): bool
    {
        $class = $method->getDeclaringClass();

        foreach ($method->getParameters() as $parameter) {
            try {
                if (!$parameter->isDefaultValueConstant()) {
                    continue;
                }

                $name = $parameter->getDefaultValueConstantName();

                if (!is_string($name) || !str_contains($name, '::')) {
                    continue;
                }

                $separator = strrpos($name, needle: '::');

                if ($separator === false) {
                    continue;
                }

                $owner = substr($name, offset: 0, length: $separator);
                $constantOwner = $this->constantOwner($method, $owner);

                if ($constantOwner === null) {
                    continue;
                }

                $constant = $constantOwner->getReflectionConstant(substr($name, offset: $separator + 2));

                if ($constant !== false && $constant->isPrivate()) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
    }

    private function constantOwner(ReflectionMethod $method, string $owner): ?ReflectionClass
    {
        $declaring = $method->getDeclaringClass();

        if ($owner === 'self' || $owner === 'static' || $owner === $declaring->getName()) {
            return $declaring;
        }

        if ($owner === 'parent') {
            $parent = $declaring->getParentClass();

            return $parent === false ? null : $parent;
        }

        if (!class_exists($owner) && !interface_exists($owner)) {
            return null;
        }

        try {
            return new ReflectionClass($owner);
        } catch (\ReflectionException) {
            return null;
        }
    }

    private function hasConstantDefault(\ReflectionParameter $parameter): bool
    {
        try {
            return (
                $parameter->isDefaultValueConstant()
                && is_string($parameter->getDefaultValueConstantName())
                && $parameter->getDefaultValueConstantName() !== ''
            );
        } catch (\Throwable) {
            return false;
        }
    }
}

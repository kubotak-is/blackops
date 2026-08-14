<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyGenerator;

use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyMetadata;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyMethodMetadata;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnership;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use UnitEnum;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class FrameworkProxySourceEmitter
{
    public function emit(ReflectionClass $source, FrameworkProxyMetadata $metadata, string $proxyClass): string
    {
        $namespace = str_contains($proxyClass, '\\') ? substr($proxyClass, 0, (int) strrpos($proxyClass, '\\')) : '';
        $short = str_contains($proxyClass, '\\')
            ? substr($proxyClass, (int) strrpos($proxyClass, '\\') + 1)
            : $proxyClass;
        $sourceName = '\\' . ltrim($source->getName(), '\\');
        /** @var list<string> $lines */
        $lines = ['<?php', '', 'declare(strict_types=1);', ''];

        if ($namespace !== '') {
            $lines[] = 'namespace ' . $namespace . ';';
            $lines[] = '';
        }

        foreach ($this->attributes($source->getAttributes()) as $attribute) {
            $lines[] = $attribute;
        }

        $readonly = $source->isReadOnly() ? 'readonly ' : '';
        $lines[] = $readonly . 'class ' . $short . ' extends ' . $sourceName;
        $lines[] = '{';
        $lines[] = '    public function __blackopsInitialize(\\BlackOps\\Internal\\Aop\\FrameworkProxyGenerator\\FrameworkProxyInvocation $invocation): void';
        $lines[] = '    {';
        $lines[] = '        \\BlackOps\\Internal\\Aop\\FrameworkProxyGenerator\\FrameworkProxyInvocationRegistry::initialize($this, $invocation);';
        $lines[] = '    }';

        foreach ($metadata->methods as $methodMetadata) {
            $method = $source->getMethod($methodMetadata->name);
            $lines[] = '';
            foreach ($this->attributes($method->getAttributes()) as $attribute) {
                $lines[] = '    ' . $attribute;
            }
            $lines[] = $this->method($method, $methodMetadata, $metadata->ownership, $source);
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    /** @mago-expect lint:halstead */
    private function method(
        ReflectionMethod $method,
        FrameworkProxyMethodMetadata $metadata,
        FrameworkProxyOwnership $ownership,
        ReflectionClass $source,
    ): string {
        $parameters = [];
        $variadic = null;

        foreach ($method->getParameters() as $parameter) {
            $parameters[] = $this->parameter($parameter, $source);
            if ($parameter->isVariadic()) {
                $variadic = $parameter->getName();
            }
        }

        $declaring = $method->getDeclaringClass();
        $return = $this->type($method->getReturnType(), $declaring);
        $declaration = '    public function ' . $method->getName() . '(' . implode(', ', $parameters) . ')';
        if ($return !== null) {
            $declaration .= ': ' . $return;
        }
        $declaration .= "\n    {\n        ";

        $declaration .= '$arguments = func_get_args();' . "\n        ";
        if ($variadic !== null) {
            $declaration .=
                'foreach ($'
                . $variadic
                . " as \$key => \$value) {\n            if (is_string(\$key)) {\n                \$arguments[\$key] = \$value;\n            }\n        }\n        ";
        }

        $call = 'parent::' . $method->getName() . '(...$arguments)';
        $direct = $call . ';';
        if ($metadata->afterCommit || $ownership !== FrameworkProxyOwnership::OPERATION && $metadata->transactional) {
            $abi = $metadata->afterCommit ? 'afterCommit' : 'transactional';
            $connection = $metadata->transactional ? var_export($metadata->transactionalConnection, true) : null;
            $declaration .= "\$invocation = \\BlackOps\\Internal\\Aop\\FrameworkProxyGenerator\\FrameworkProxyInvocationRegistry::get(\$this);\n        if (\$invocation === null) {\n            throw new \\LogicException('Framework proxy invocation is not initialized.');\n        }\n        \$proceed = function ()";
            $declaration .= ' use ($arguments)';
            if ($return !== null) {
                $declaration .= ': ' . $return;
            }
            $declaration .= " {\n            ";
            $returnName = $this->returnKind($method->getReturnType());
            $declaration .=
                $returnName !== 'void' && $returnName !== 'never'
                    ? 'return parent::' . $method->getName() . '(...$arguments);'
                    : 'parent::' . $method->getName() . '(...$arguments);';
            $declaration .= "\n        };\n        ";
            $invocationCall =
                '$invocation->'
                . $abi
                . '($this, '
                . var_export($method->getName(), true)
                . ', $arguments, $proceed'
                . ($connection !== null ? ', ' . $connection : '')
                . ')';
            $declaration .= $returnName === 'void'
                ? $invocationCall . ';'
                : (
                    $returnName === 'never'
                        ? $invocationCall
                        . '; throw new \\LogicException(\'Framework proxy invocation returned from a never method.\');'
                        : 'return ' . $invocationCall . ';'
                );
            return $declaration . "\n    }";
        }
        $returnName = $this->returnKind($method->getReturnType());
        if ($returnName !== 'void' && $returnName !== 'never') {
            $declaration .= 'return ' . $call . ';';
        } else {
            $declaration .= $direct;
        }

        return $declaration . "\n    }";
    }

    private function parameter(ReflectionParameter $parameter, ReflectionClass $source): string
    {
        $result = '';
        foreach ($this->attributes($parameter->getAttributes()) as $attribute) {
            $result .= $attribute . ' ';
        }
        $type = $this->type($parameter->getType(), $parameter->getDeclaringClass() ?? $source);
        if ($type !== null) {
            $result .= $type . ' ';
        }
        if ($parameter->isPassedByReference()) {
            $result .= '&';
        }
        if ($parameter->isVariadic()) {
            $result .= '...';
        }
        $result .= '$' . $parameter->getName();
        if ($parameter->isDefaultValueAvailable() && !$parameter->isVariadic()) {
            $result .= ' = ' . $this->default($parameter);
        }

        return $result;
    }

    /** @mago-expect lint:halstead */
    private function type(?ReflectionType $type, ReflectionClass $declaring): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if ($name === 'self') {
                $name = '\\' . ltrim($declaring->getName(), '\\');
            } elseif ($name === 'parent' && ($parent = $declaring->getParentClass()) !== false) {
                $name = '\\' . ltrim($parent->getName(), '\\');
            } elseif ($name !== 'static' && !$type->isBuiltin()) {
                $name = '\\' . ltrim($name, '\\');
            }
            return $type->allowsNull() && $name !== 'mixed' && $name !== 'null' && !str_starts_with($name, '?')
                ? '?' . $name
                : $name;
        }
        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map(
                fn(ReflectionType $part): string => $this->type($part, $declaring) ?? '',
                $type->getTypes(),
            ));
        }
        if ($type instanceof \ReflectionUnionType) {
            $parts = [];
            foreach ($type->getTypes() as $part) {
                $partValue = $this->type($part, $declaring) ?? '';
                $parts[] = $part instanceof \ReflectionIntersectionType ? '(' . $partValue . ')' : $partValue;
            }
            if ($type->allowsNull() && count($parts) === 2 && in_array('null', $parts, true)) {
                $other = $parts[0] === 'null' ? $parts[1] : $parts[0];
                if (!str_contains($other, '&')) {
                    return '?' . $other;
                }
            }
            return implode('|', $parts);
        }
        throw new \LogicException('Unsupported reflection type.');
    }

    private function returnKind(?ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType && ($type->getName() === 'void' || $type->getName() === 'never')) {
            return $type->getName();
        }
        return 'value';
    }

    private function default(ReflectionParameter $parameter): string
    {
        if ($parameter->isDefaultValueConstant()) {
            $name = (string) $parameter->getDefaultValueConstantName();
            $declaring = $parameter->getDeclaringClass();
            if ($declaring === null) {
                throw new \LogicException('Parameter declaring class is unavailable.');
            }
            if (str_starts_with($name, 'self::')) {
                return '\\' . ltrim($declaring->getName(), '\\') . substr($name, 4);
            }
            $parent = $declaring->getParentClass();
            if (str_starts_with($name, 'parent::') && $parent !== false) {
                return '\\' . ltrim($parent->getName(), '\\') . substr($name, 6);
            }
            if (str_contains($name, '::') && !str_starts_with($name, '\\')) {
                return '\\' . $name;
            }
            return $name;
        }

        return var_export($parameter->getDefaultValue(), true);
    }

    /**
     * @param list<ReflectionAttribute<mixed>> $attributes
     * @return list<string>
     */
    private function attributes(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $attribute) {
            if (in_array($attribute->getName(), [Transactional::class, AfterCommit::class], true)) {
                continue;
            }
            $arguments = [];
            foreach ($attribute->getArguments() as $key => $value) {
                $encoded = $this->value($value);
                $arguments[] = is_string($key) ? $key . ': ' . $encoded : $encoded;
            }
            $result[] =
                '#['
                . '\\'
                . ltrim($attribute->getName(), '\\')
                . (count($arguments) > 0 ? '(' . implode(', ', $arguments) . ')' : '')
                . ']';
        }

        return $result;
    }

    private function value(mixed $value): string
    {
        if ($value instanceof UnitEnum) {
            return '\\' . $value::class . '::' . $value->name;
        }
        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[] = var_export($key, true) . ' => ' . $this->value($item);
            }
            return '[' . implode(', ', $items) . ']';
        }

        return var_export($value, true);
    }
}

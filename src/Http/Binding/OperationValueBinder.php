<?php

declare(strict_types=1);

namespace BlackOps\Http\Binding;

use BlackOps\Core\OperationValue;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

final readonly class OperationValueBinder
{
    /**
     * @param class-string<OperationValue> $value
     */
    public function bind(string $value, ServerRequestInterface $request): OperationValue
    {
        $reflection = new ReflectionClass($value);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $this->operationValue($reflection->newInstance());
        }

        $query = $request->getQueryParams();
        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $query) && is_scalar($query[$name])) {
                $arguments[] = (string) $query[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new InvalidArgumentException('HTTP request is missing an operation value field.');
        }

        return $this->operationValue($reflection->newInstanceArgs($arguments));
    }

    private function operationValue(mixed $value): OperationValue
    {
        if (!$value instanceof OperationValue) {
            throw new InvalidArgumentException('Bound value must implement OperationValue.');
        }

        return $value;
    }
}

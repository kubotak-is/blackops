<?php

declare(strict_types=1);

namespace BlackOps\Http\Binding;

use BlackOps\Core\OperationValue;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

final readonly class OperationValueBinder
{
    public function __construct(
        private JsonRequestBody $body = new JsonRequestBody(),
        private HttpParameterBinder $parameters = new HttpParameterBinder(),
    ) {}

    /**
     * @param class-string<OperationValue> $value
     * @param array<string, string> $pathParameters
     */
    public function bind(string $value, ServerRequestInterface $request, array $pathParameters = []): OperationValue
    {
        $reflection = new ReflectionClass($value);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $this->operationValue($reflection->newInstance());
        }

        $query = $request->getQueryParams();
        $body = $this->body->decode($request);
        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $bound = $this->parameters->bind($parameter, $request, $pathParameters, $query, $body);

            if ($bound->found) {
                $arguments[] = $bound->value;
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

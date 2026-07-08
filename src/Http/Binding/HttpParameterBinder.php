<?php

declare(strict_types=1);

namespace BlackOps\Http\Binding;

use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\FromHeader;
use BlackOps\Http\Attribute\FromPath;
use BlackOps\Http\Attribute\FromQuery;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionAttribute;
use ReflectionParameter;

final readonly class HttpParameterBinder
{
    /**
     * @param array<string, string> $pathParameters
     * @param array<array-key, mixed> $query
     * @param array<string, mixed> $body
     */
    public function bind(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $pathParameters,
        array $query,
        array $body,
    ): BoundHttpValue {
        $name = $parameter->getName();

        if (($source = $this->attribute($parameter, FromPath::class)) !== null) {
            return $this->fromArray($pathParameters, $source->name ?? $name);
        }

        if (($source = $this->attribute($parameter, FromQuery::class)) !== null) {
            return $this->fromArray($query, $source->name ?? $name);
        }

        if (($source = $this->attribute($parameter, FromHeader::class)) !== null) {
            return $this->fromHeader($request, $source->name ?? $name);
        }

        if (($source = $this->attribute($parameter, FromBody::class)) !== null) {
            return $this->fromArray($body, $source->name ?? $name);
        }

        return $this->fromArray($body, $name);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return T|null
     */
    private function attribute(ReflectionParameter $parameter, string $attribute): ?object
    {
        $attributes = $parameter->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes === []) {
            return null;
        }

        if (count($attributes) > 1) {
            throw new InvalidArgumentException('HTTP binding attribute must not be repeated.');
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function fromArray(array $values, string $name): BoundHttpValue
    {
        if (!array_key_exists($name, $values)) {
            return BoundHttpValue::missing();
        }

        /** @var mixed $value */
        $value = $values[$name];

        if (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('HTTP bound value must be scalar or null.');
        }

        return BoundHttpValue::found($value);
    }

    private function fromHeader(ServerRequestInterface $request, string $name): BoundHttpValue
    {
        if (!$request->hasHeader($name)) {
            return BoundHttpValue::missing();
        }

        return BoundHttpValue::found($request->getHeaderLine($name));
    }
}

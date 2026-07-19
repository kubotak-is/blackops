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
    public function __construct(
        private HttpBoundScalarDecoder $scalars = new HttpBoundScalarDecoder(),
    ) {}

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
            return $this->fromNonBodyArray($parameter, $pathParameters, $source->name ?? $name);
        }

        if (($source = $this->attribute($parameter, FromQuery::class)) !== null) {
            return $this->fromNonBodyArray($parameter, $query, $source->name ?? $name);
        }

        if (($source = $this->attribute($parameter, FromHeader::class)) !== null) {
            return $this->fromHeader($parameter, $request, $source->name ?? $name);
        }

        if (($source = $this->attribute($parameter, FromBody::class)) !== null) {
            return $this->fromArray($body, $source->name ?? $name, $name);
        }

        return $this->fromArray($body, $name, $name);
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
    private function fromArray(array $values, string $name, string $field): BoundHttpValue
    {
        if (!array_key_exists($name, $values)) {
            return BoundHttpValue::missing();
        }

        /** @var mixed $value */
        $value = $values[$name];

        if (!is_scalar($value) && $value !== null) {
            throw OperationValueBindingException::type($field);
        }

        return BoundHttpValue::found($value);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function fromNonBodyArray(ReflectionParameter $parameter, array $values, string $name): BoundHttpValue
    {
        if (!array_key_exists($name, $values)) {
            return BoundHttpValue::missing();
        }

        /** @var mixed $value */
        $value = $values[$name];

        return BoundHttpValue::found($this->scalars->decode($parameter, $value));
    }

    private function fromHeader(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        string $name,
    ): BoundHttpValue {
        if (!$request->hasHeader($name)) {
            return BoundHttpValue::missing();
        }

        return BoundHttpValue::found($this->scalars->decode($parameter, $request->getHeaderLine($name)));
    }
}

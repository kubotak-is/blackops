<?php

declare(strict_types=1);

namespace BlackOps\Http\Binding;

use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

final readonly class JsonRequestBody
{
    /**
     * @return array<string, mixed>
     */
    public function decode(ServerRequestInterface $request): array
    {
        $body = trim((string) $request->getBody());

        if ($body === '') {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($body, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw HttpProtocolException::malformedJson($exception);
        }

        if (!$decoded instanceof stdClass) {
            throw HttpProtocolException::nonObjectBody();
        }

        $result = [];

        $decodedValues = get_object_vars($decoded);
        foreach (array_keys($decodedValues) as $key) {
            if (!is_string($key)) {
                continue;
            }

            /** @var mixed $value */
            $value = $decodedValues[$key];
            $result[$key] = $value;
        }

        return $result;
    }
}

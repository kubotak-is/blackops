<?php

declare(strict_types=1);

namespace BlackOps\Http\Binding;

use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

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
            $decoded = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('HTTP request body must contain valid JSON.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('HTTP request body must contain a JSON object.');
        }

        $result = [];

        foreach (array_keys($decoded) as $key) {
            if (!is_string($key)) {
                continue;
            }

            /** @var mixed $value */
            $value = $decoded[$key];
            $result[$key] = $value;
        }

        return $result;
    }
}

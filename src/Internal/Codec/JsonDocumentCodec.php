<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

use BlackOps\Core\Codec\OperationCodecException;
use JsonException;

final readonly class JsonDocumentCodec
{
    /**
     * @param array<string, mixed> $payload
     */
    public function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OperationCodecException('Failed to encode operation payload as JSON.', previous: $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeObject(string $json): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OperationCodecException('Failed to decode operation JSON payload.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new OperationCodecException('Encoded operation JSON must be an object.');
        }

        $object = [];

        foreach (array_keys($decoded) as $key) {
            if (!is_string($key)) {
                throw new OperationCodecException('Encoded operation JSON object must use string keys.');
            }

            /** @var mixed $value */
            $value = $decoded[$key];
            $object[$key] = $value;
        }

        return $object;
    }
}

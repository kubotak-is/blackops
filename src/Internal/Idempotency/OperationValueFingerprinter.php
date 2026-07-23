<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use BlackOps\Core\Codec\OperationCodecException;
use BlackOps\Core\OperationValue;
use ReflectionClass;
use ReflectionProperty;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class OperationValueFingerprinter
{
    public const int CODEC_VERSION = 1;

    public function fingerprint(string $operationTypeId, OperationValue $value): OperationFingerprint
    {
        $stream = hash_init('sha256');
        hash_update($stream, data: 'blackops/operation-fingerprint/v1');
        $this->token($stream, 'operation', $operationTypeId);
        $this->token($stream, 'value-type', $value::class);

        foreach (new ReflectionClass($value)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isInitialized($value)) {
                throw new OperationCodecException('Operation value contains an uninitialized public property.');
            }

            $this->token($stream, 'property', $property->getName());
            $declaredType = $property->getType();
            $this->token($stream, 'declared-type', $declaredType === null ? 'untyped' : (string) $declaredType);
            $this->value($stream, $property->getValue($value));
        }

        return new OperationFingerprint(self::CODEC_VERSION, hash_final($stream));
    }

    /** @param resource $stream */
    private function value($stream, mixed $value): void
    {
        if ($value === null) {
            hash_update($stream, data: "null\0");
            return;
        }

        if (is_bool($value)) {
            hash_update($stream, $value ? "bool\1" : "bool\0");
            return;
        }

        if (is_int($value)) {
            $this->token($stream, 'int', (string) $value);
            return;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new OperationCodecException('Operation value contains an unsupported float.');
            }

            $this->token($stream, 'float', sprintf('%.17g', $value));
            return;
        }

        if (is_string($value)) {
            $this->token($stream, 'string', $value);
            return;
        }

        if (is_array($value)) {
            $this->array($stream, $value);
            return;
        }

        throw new OperationCodecException('Operation value property type is not supported by the fingerprint codec.');
    }

    /** @param resource $stream */
    private function array($stream, array $value): void
    {
        $keys = array_keys($value);
        $isList = $keys === [] || $keys === range(0, count($keys) - 1);
        hash_update($stream, $isList ? 'list\0' : 'map\0');

        if (!$isList) {
            usort($keys, static fn(int|string $a, int|string $b): int => strcmp((string) $a, (string) $b));
        }

        foreach ($keys as $key) {
            if (!$isList) {
                $this->token($stream, 'key', (string) $key);
            }

            $this->value($stream, $value[$key]);
        }

        hash_update($stream, data: "array-end\0");
    }

    /** @param resource $stream */
    private function token($stream, string $type, string $value): void
    {
        hash_update($stream, $type);
        hash_update($stream, pack('N', strlen($value)));
        hash_update($stream, $value);
        hash_update($stream, data: "\0");
    }
}

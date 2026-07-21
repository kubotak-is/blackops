<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class OperationConsolePayloadValidator
{
    /** @param array<string, mixed> $payload @mago-expect lint:cyclomatic-complexity */
    public function valid(array $payload): bool
    {
        if (($payload['schemaVersion'] ?? null) !== 1 || !is_string($payload['status'] ?? null)) {
            return false;
        }

        return match ($payload['status']) {
            'completed' => array_keys($payload) === ['schemaVersion', 'status', 'outcome']
                && (is_array($payload['outcome'] ?? null) || ($payload['outcome'] ?? null) instanceof \stdClass),
            'accepted' => array_keys($payload) === ['schemaVersion', 'status', 'operationId', 'acceptedAt']
                && is_string($payload['operationId'] ?? null)
                && is_string($payload['acceptedAt'] ?? null),
            'rejected' => $this->validRejected($payload),
            'error' => array_keys($payload)
                === (
                    array_key_exists('operationId', $payload)
                        ? ['schemaVersion', 'status', 'code', 'operationId']
                        : ['schemaVersion', 'status', 'code']
                )
                && ($payload['code'] ?? null) === 'internal_error'
                && (!array_key_exists('operationId', $payload) || is_string($payload['operationId'])),
            default => false,
        };
    }

    /** @param array<string, mixed> $payload */
    private function validRejected(array $payload): bool
    {
        $expectedKeys = array_key_exists('operationId', $payload)
            ? ['schemaVersion', 'status', 'operationId', 'category', 'code', 'violations']
            : ['schemaVersion', 'status', 'category', 'code', 'violations'];
        if (
            array_keys($payload) !== $expectedKeys
            || !is_string($payload['category'] ?? null)
            || !is_string($payload['code'] ?? null)
            || !is_array($payload['violations'] ?? null)
            || !array_is_list($payload['violations'])
            || array_key_exists('operationId', $payload) && !is_string($payload['operationId'])
        ) {
            return false;
        }

        return !array_any($payload['violations'], $this->invalidViolation(...));
    }

    private function invalidViolation(mixed $violation): bool
    {
        return (
            !is_array($violation)
            || array_keys($violation) !== ['field', 'rule', 'code']
            || !is_string($violation['field'])
            || !is_string($violation['rule'])
            || !is_string($violation['code'])
        );
    }
}

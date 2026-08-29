<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Protection;

use BlackOps\Core\Audit\AuditTypedReference;
use RuntimeException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:block-statement
 */
final class AuditProtectedIngress
{
    private const int MAX_BODY_BYTES = 1_048_576;

    private \SensitiveParameterValue $snapshot;

    private function __construct(
        string $recordId,
        string $partitionId,
        ?AuditTypedReference $tenantBinding,
        int $ingressOrdinal,
        string $idempotencyKeyHash,
        string $contentHash,
        #[\SensitiveParameter]
        string $protectedBody,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'recordId' => $recordId,
            'partitionId' => $partitionId,
            'tenantBinding' => $tenantBinding?->toArray(),
            'ingressOrdinal' => $ingressOrdinal,
            'idempotencyKeyHash' => $idempotencyKeyHash,
            'contentHash' => $contentHash,
            'protectedBody' => $protectedBody,
        ]);
    }

    /** @param array<array-key,mixed> $data */
    public static function fromArray(#[\SensitiveParameter] array $data): self
    {
        try {
            $keys = [
                'recordId',
                'partitionId',
                'tenantBinding',
                'ingressOrdinal',
                'idempotencyKeyHash',
                'contentHash',
                'protectedBody',
            ];
            if (array_is_list($data) || array_keys($data) !== $keys)
                throw new RuntimeException('Malformed audit protected ingress.');
            if (
                !is_string($data['recordId'])
                || !is_string($data['partitionId'])
                || !is_int($data['ingressOrdinal'])
                || !is_string($data['idempotencyKeyHash'])
                || !is_string($data['contentHash'])
                || !is_string($data['protectedBody'])
            )
                throw new RuntimeException('Malformed audit protected ingress.');
            $tenant = self::tenant($data['tenantBinding']);
            self::uuid($data['recordId']);
            self::partition($data['partitionId']);
            if ($data['ingressOrdinal'] < 1 || $data['ingressOrdinal'] > 9_007_199_254_740_991)
                throw new RuntimeException('Malformed audit protected ingress.');
            self::hash($data['idempotencyKeyHash']);
            self::hash($data['contentHash']);
            if (strlen($data['protectedBody']) < 53 || strlen($data['protectedBody']) > self::MAX_BODY_BYTES)
                throw new RuntimeException('Malformed audit protected ingress.');
            return new self(
                $data['recordId'],
                $data['partitionId'],
                $tenant,
                $data['ingressOrdinal'],
                $data['idempotencyKeyHash'],
                $data['contentHash'],
                $data['protectedBody'],
            );
        } catch (\Throwable) {
            throw AuditProtectionException::unavailable();
        }
    }

    /**
     * @return array<string,mixed>
     * @mago-expect lint:inline-variable-return
     */
    public function toArray(): array
    {
        $value = $this->snapshot->getValue();
        if (!is_array($value))
            throw AuditProtectionException::unavailable();
        /** @var array<string,mixed> $copy */
        $copy = self::copy($value);
        return $copy;
    }

    public function protectedBody(): string
    {
        $value = $this->snapshot->getValue();
        if (!is_array($value) || !is_string($value['protectedBody'] ?? null))
            throw AuditProtectionException::unavailable();
        return $value['protectedBody'];
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(array $data): void
    {
        throw AuditProtectionException::unavailable();
    }

    private static function tenant(mixed $value): ?AuditTypedReference
    {
        if ($value === null)
            return null;
        if (
            !is_array($value)
            || array_keys($value) !== ['type', 'id']
            || !is_string($value['type'])
            || !is_string($value['id'])
        )
            throw new RuntimeException('Malformed audit tenant.');
        return new AuditTypedReference($value['type'], $value['id']);
    }

    private static function uuid(string $value): void
    {
        if (
            strlen($value) !== 36
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) !== 1
        )
            throw new RuntimeException('Malformed audit identifier.');
    }

    private static function partition(string $value): void
    {
        if (preg_match('/^(?:system:global|tenant:[a-f0-9]{64})$/D', $value) !== 1)
            throw new RuntimeException('Malformed audit partition.');
    }

    private static function hash(string $value): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1)
            throw new RuntimeException('Malformed audit hash.');
    }

    private static function copy(mixed $value): mixed
    {
        if (!is_array($value))
            return $value;
        $copy = [];
        foreach ($value as $key => $member)
            $copy[$key] = self::copy($member);
        return $copy;
    }
}

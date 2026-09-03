<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Key;

use BlackOps\Audit\AuditProtectionKeyProvider;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\StorageProtection\StorageKey;

final readonly class ApplicationAuditProtectionKeySource implements AuditProtectionKeySource
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        AuditProtectionKeyProvider $provider,
        AuditPartitionIdDeriver $partitions,
        #[\SensitiveParameter]
        StorageKey $opaqueKey,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'provider' => $provider,
            'partitions' => $partitions,
            'opaqueKey' => $opaqueKey,
        ]);
    }

    public function activeKey(
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        try {
            $partition = $this->partitions()->derive($tenant);
            $key = match ($purpose) {
                AuditProtectionPurpose::AuditRecord => $this->provider()->activeRecordKey($partition),
                AuditProtectionPurpose::AuditOutboxRecord => $this->provider()->activeOutboxKey($partition),
            };

            return $this->validated($key);
        } catch (\Throwable) {
            throw AuditProtectionException::unavailable();
        }
    }

    public function key(
        #[\SensitiveParameter]
        string $keyId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        try {
            $partition = $this->partitions()->derive($tenant);
            $key = match ($purpose) {
                AuditProtectionPurpose::AuditRecord => $this->provider()->recordKey($keyId, $partition),
                AuditProtectionPurpose::AuditOutboxRecord => $this->provider()->outboxKey($keyId, $partition),
            };

            return $this->validated($key, $keyId);
        } catch (\Throwable) {
            throw AuditProtectionException::unavailable();
        }
    }

    private function validated(
        #[\SensitiveParameter]
        mixed $key,
        #[\SensitiveParameter]
        ?string $expectedId = null,
    ): StorageKey {
        if (!$key instanceof StorageKey) {
            throw new \RuntimeException();
        }
        if ($expectedId !== null && !hash_equals($expectedId, $key->id())) {
            throw new \RuntimeException();
        }
        $opaqueKey = $this->opaqueKey();
        if (hash_equals($opaqueKey->id(), $key->id()) || hash_equals($opaqueKey->material(), $key->material())) {
            throw new \RuntimeException();
        }

        return $key;
    }

    private function provider(): AuditProtectionKeyProvider
    {
        $provider = $this->value('provider');
        if (!$provider instanceof AuditProtectionKeyProvider) {
            throw new \RuntimeException();
        }

        return $provider;
    }

    private function partitions(): AuditPartitionIdDeriver
    {
        $partitions = $this->value('partitions');
        if (!$partitions instanceof AuditPartitionIdDeriver) {
            throw new \RuntimeException();
        }

        return $partitions;
    }

    private function opaqueKey(): StorageKey
    {
        $key = $this->value('opaqueKey');
        if (!$key instanceof StorageKey) {
            throw new \RuntimeException();
        }

        return $key;
    }

    private function value(#[\SensitiveParameter] string $name): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (\Throwable) {
            throw new \RuntimeException();
        }
        if (!is_array($value) || !array_key_exists($name, $value)) {
            throw new \RuntimeException();
        }

        return $value[$name];
    }
}

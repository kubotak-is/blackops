<?php

declare(strict_types=1);

namespace App\Security;

use BlackOps\Core\TenantRef;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;
use InvalidArgumentException;

final readonly class SampleStorageKeyProvider implements StorageKeyProvider
{
    private const string KEY_ID = 'community-board-local';

    private StorageKey $key;

    public function __construct()
    {
        $encoded = $_ENV['BLACKOPS_STORAGE_KEY'] ?? null;
        if (!is_string($encoded) || $encoded === '') {
            throw new InvalidArgumentException('BLACKOPS_STORAGE_KEY must be strict base64 for exactly 32 bytes.');
        }

        $material = base64_decode($encoded, strict: true);
        if (!is_string($material) || strlen($material) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new InvalidArgumentException('BLACKOPS_STORAGE_KEY must be strict base64 for exactly 32 bytes.');
        }

        $this->key = new StorageKey(self::KEY_ID, $material);
    }

    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return $this->key;
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        if ($keyId !== self::KEY_ID) {
            throw new InvalidArgumentException('Unknown storage key identifier.');
        }

        return $this->key;
    }
}

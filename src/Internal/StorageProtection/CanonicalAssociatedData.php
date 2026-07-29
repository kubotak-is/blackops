<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

use BlackOps\Core\TenantRef;
use RuntimeException;

final readonly class CanonicalAssociatedData
{
    private const int NULL_LENGTH = 0xFFFF_FFFF;

    public function encode(#[\SensitiveParameter] StorageProtectionContext $context, string $keyId): string
    {
        return implode('', [
            $this->string('blackops.storage.v1'),
            $this->string('1'),
            $this->string('1'),
            $this->string($keyId),
            $this->string($context->purpose->value),
            $this->string($context->recordIdentity),
            $this->string($context->operationId),
            $this->string($context->operationType),
            $this->string($context->schemaVersion),
            $this->tenant($context->tenant),
        ]);
    }

    private function string(string $value): string
    {
        $length = strlen($value);
        if ($length > self::NULL_LENGTH) {
            throw new RuntimeException('Storage protection context is too large.');
        }

        return pack('N', $length) . $value;
    }

    private function nullable(?string $value): string
    {
        return $value === null ? pack('N', self::NULL_LENGTH) : $this->string($value);
    }

    private function tenant(?TenantRef $tenant): string
    {
        if ($tenant === null) {
            return $this->string('0') . $this->nullable(null) . $this->nullable(null);
        }

        return $this->string('1') . $this->string($tenant->type()) . $this->string($tenant->id());
    }
}

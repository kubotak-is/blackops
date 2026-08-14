<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use BlackOps\StorageProtection\StoragePurpose;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;

final class StorageProtectionRotationInput
{
    public function scope(InputInterface $input): StorageProtectionRotationScope
    {
        return $this->make($input, StorageProtectionRotationMode::Plan);
    }

    public function confirmedScope(InputInterface $input): StorageProtectionRotationScope
    {
        return $this->make($input, StorageProtectionRotationMode::Confirmed);
    }

    private function make(InputInterface $input, StorageProtectionRotationMode $mode): StorageProtectionRotationScope
    {
        $purpose = StoragePurpose::tryFrom($this->stringOption($input, 'purpose') ?? '');
        if ($purpose === null) {
            throw new InvalidArgumentException('Storage protection purpose is required.');
        }
        $actor = $this->stringOption($input, 'actor');
        $reason = $this->stringOption($input, 'reason');
        return new StorageProtectionRotationScope(
            $purpose,
            $this->tenant($input),
            $this->stringOption($input, 'old-key-id') ?? '',
            $this->stringOption($input, 'new-key-id') ?? '',
            $this->batch($input->getOption('batch')),
            $this->stringOption($input, 'checkpoint') ?? '',
            $actor,
            $reason,
            $mode === StorageProtectionRotationMode::Confirmed,
        );
    }

    private function tenant(InputInterface $input): ?TenantRef
    {
        $type = $this->stringOption($input, 'tenant-type');
        $id = $this->stringOption($input, 'tenant-id');
        if ($type === null && $id === null) {
            return null;
        }
        if ($type === null || $id === null || $type === '' || $id === '') {
            throw new InvalidArgumentException('Tenant type and id must be provided together.');
        }
        return new TenantRef($type, $id);
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        /** @var string|null $value */
        $value = $input->getOption($name);
        return is_string($value) ? $value : null;
    }

    private function batch(mixed $value): int
    {
        if (!is_string($value) || preg_match('/\A[0-9]+\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Rotation batch size must be an unsigned decimal integer.');
        }
        return (int) $value;
    }
}

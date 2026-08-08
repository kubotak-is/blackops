<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

use InvalidArgumentException;

final class StorageProtectionRotationIdentityValidator
{
    public function validate(StorageProtectionRotationScope $scope): void
    {
        foreach ([
            'old key id' => $scope->oldKeyId,
            'new key id' => $scope->newKeyId,
            'checkpoint' => $scope->checkpoint,
        ] as $label => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Rotation %s must not be empty.', $label));
            }
        }
        foreach ([$scope->oldKeyId, $scope->newKeyId] as $keyId) {
            if (strlen($keyId) > 128 || preg_match('/^[A-Za-z0-9]+(?:[._:\/-][A-Za-z0-9]+)*$/D', $keyId) !== 1) {
                throw new InvalidArgumentException('Rotation key identifier is invalid.');
            }
        }
        if ($scope->oldKeyId === $scope->newKeyId) {
            throw new InvalidArgumentException('Rotation old and new key identifiers must differ.');
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

use InvalidArgumentException;

final class StorageProtectionRotationScopeValidator
{
    public function validate(StorageProtectionRotationScope $scope): void
    {
        new StorageProtectionRotationIdentityValidator()->validate($scope);
        $this->checkpoint($scope->checkpoint);
        $this->batch($scope->batchSize);
        new StorageProtectionRotationMetadataValidator()->validate($scope);
    }

    private function checkpoint(string $value): void
    {
        if (strlen($value) > 128 || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $value) !== 1) {
            throw new InvalidArgumentException('Rotation checkpoint is invalid.');
        }
    }

    private function batch(int $value): void
    {
        if ($value < 1 || $value > 1000) {
            throw new InvalidArgumentException('Rotation batch size must be between 1 and 1000.');
        }
    }
}

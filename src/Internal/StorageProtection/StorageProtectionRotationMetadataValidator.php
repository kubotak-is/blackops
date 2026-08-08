<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

use InvalidArgumentException;

final class StorageProtectionRotationMetadataValidator
{
    public function validate(StorageProtectionRotationScope $scope): void
    {
        if (
            $scope->confirmed
            && (
                $scope->actor === null
                || $scope->reason === null
                || trim($scope->actor) === ''
                || trim($scope->reason) === ''
            )
        ) {
            throw new InvalidArgumentException('Rotation actor and reason are required for confirmation.');
        }
        foreach (['actor' => $scope->actor, 'reason' => $scope->reason] as $label => $value) {
            if ($value !== null && (strlen($value) > 256 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1)) {
                throw new InvalidArgumentException(sprintf('Rotation %s is invalid.', $label));
            }
        }
    }
}

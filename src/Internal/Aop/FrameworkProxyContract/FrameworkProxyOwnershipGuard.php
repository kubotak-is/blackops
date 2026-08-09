<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

final readonly class FrameworkProxyOwnershipGuard
{
    public function assertCompatible(FrameworkProxyMetadata $metadata, FrameworkProxyOwnershipMarker $marker): void
    {
        if ($metadata->sourceClass !== $marker->sourceClass || $metadata->ownership !== $marker->ownership) {
            throw $this->error(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $metadata);
        }

        if (!$metadata->profile->equals($marker->profile)) {
            throw $this->error(FrameworkProxyDiagnosticCode::MODE_CONFLICT, $metadata);
        }

        if ($metadata->ownership === FrameworkProxyOwnership::OPERATION && !$marker->lifecycleOwned) {
            throw $this->error(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $metadata);
        }

        if ($metadata->ownership === FrameworkProxyOwnership::SERVICE && $marker->lifecycleOwned) {
            throw $this->error(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $metadata);
        }
    }

    public function assertProfile(
        string|FrameworkProxyProfile $selected,
        string|FrameworkProxyProfile $existing,
        ?FrameworkProxyMetadata $metadata = null,
    ): void {
        if (FrameworkProxyProfile::from($selected)->equals($existing)) {
            return;
        }

        throw new FrameworkProxyContractException(
            new FrameworkProxyDiagnostic(
                FrameworkProxyDiagnosticCode::MODE_CONFLICT,
                sourceClass: $metadata?->sourceClass,
            ),
        );
    }

    public function marker(FrameworkProxyMetadata $metadata): FrameworkProxyOwnershipMarker
    {
        return $metadata->marker();
    }

    private function error(string $code, FrameworkProxyMetadata $metadata): FrameworkProxyContractException
    {
        return new FrameworkProxyContractException(
            new FrameworkProxyDiagnostic($code, sourceClass: $metadata->sourceClass),
        );
    }
}

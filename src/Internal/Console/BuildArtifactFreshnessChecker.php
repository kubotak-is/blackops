<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\Build\BuildArtifactFingerprintGuard;
use BlackOps\Internal\Registry\OperationManifestFile;
use Throwable;

final readonly class BuildArtifactFreshnessChecker
{
    public function __construct(
        private BuildArtifactFingerprintGuard $fingerprints = new BuildArtifactFingerprintGuard(),
        private OperationManifestFile $operations = new OperationManifestFile(),
        private HttpOperationManifestFile $http = new HttpOperationManifestFile(),
    ) {}

    /**
     * @param list<string> $inputs
     * @param list<string> $outputs
     * @param array{operation: string, http: string} $manifests
     */
    public function isFresh(
        ?string $fingerprint,
        array $inputs,
        array $outputs,
        array $manifests,
        string $applicationBuildId,
    ): bool {
        if ($fingerprint === null || !$this->fingerprints->isFresh($fingerprint, $inputs, $outputs)) {
            return false;
        }

        try {
            $operations = $this->operations->loadArtifact($manifests['operation']);
            $http = $this->http->loadArtifact($manifests['http']);

            return (
                $operations->applicationBuildId === $applicationBuildId
                && $http->applicationBuildId === $applicationBuildId
            );
        } catch (Throwable) {
            return false;
        }
    }
}

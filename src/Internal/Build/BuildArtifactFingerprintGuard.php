<?php

declare(strict_types=1);

namespace BlackOps\Internal\Build;

final readonly class BuildArtifactFingerprintGuard
{
    public function __construct(
        private BuildFingerprint $fingerprint = new BuildFingerprint(),
        private BuildFingerprintFile $file = new BuildFingerprintFile(),
    ) {}

    /**
     * @param list<string> $inputs
     * @param list<string> $outputs
     */
    public function isFresh(string $path, array $inputs, array $outputs): bool
    {
        foreach ($outputs as $output) {
            if (!is_file($output)) {
                return false;
            }
        }

        return $this->file->matches($path, $this->fingerprint->hash($inputs));
    }

    /**
     * @param list<string> $inputs
     */
    public function update(string $path, array $inputs): void
    {
        $this->file->write($path, $this->fingerprint->hash($inputs));
    }
}

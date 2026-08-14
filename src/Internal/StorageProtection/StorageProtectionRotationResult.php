<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

final readonly class StorageProtectionRotationResult
{
    /** @param array<string,int> $remainingByKey */
    public function __construct(
        public string $purpose,
        public string $oldKeyId,
        public string $newKeyId,
        public string $checkpoint,
        StorageProtectionRotationCounts $counts,
        public array $remainingByKey,
        public string $state,
    ) {
        $this->selected = $counts->selected;
        $this->rotated = $counts->rotated;
        $this->skipped = $counts->skipped;
        $this->failed = $counts->failed;
    }

    public int $selected;
    public int $rotated;
    public int $skipped;
    public int $failed;

    /** @return array<string,mixed> */
    public function json(): array
    {
        return [
            'schemaVersion' => 1,
            'purpose' => $this->purpose,
            'oldKeyId' => $this->oldKeyId,
            'newKeyId' => $this->newKeyId,
            'checkpoint' => $this->checkpoint,
            'selected' => $this->selected,
            'rotated' => $this->rotated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'remaining' => array_map(static fn(int $count): int => $count, $this->remainingByKey),
            'remainingBoundary' => [
                'measured' => 'database-current-scope',
                'verifySeparately' => ['replica', 'backup', 'dead-letter-scopes', 'retention-window'],
            ],
            'state' => $this->state,
        ];
    }
}

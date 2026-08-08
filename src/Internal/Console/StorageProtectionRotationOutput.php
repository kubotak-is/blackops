<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationResult;
use Symfony\Component\Console\Output\OutputInterface;

final class StorageProtectionRotationOutput
{
    public function json(OutputInterface $output, StorageProtectionRotationResult $result): void
    {
        $output->writeln(json_encode($result->json(), JSON_THROW_ON_ERROR));
    }

    public function human(OutputInterface $output, StorageProtectionRotationResult $result): void
    {
        foreach ($this->lines($result) as $line) {
            $output->writeln($line);
        }
    }

    /** @return list<string> */
    private function lines(StorageProtectionRotationResult $result): array
    {
        $lines = [
            'purpose: ' . $result->purpose,
            'old-key-id: ' . $result->oldKeyId,
            'new-key-id: ' . $result->newKeyId,
            'checkpoint: ' . $result->checkpoint,
            'selected: ' . $result->selected,
            'rotated: ' . $result->rotated,
            'skipped: ' . $result->skipped,
            'failed: ' . $result->failed,
            'remaining: ' . (string) array_sum($result->remainingByKey),
        ];
        foreach ($result->remainingByKey as $keyId => $count) {
            $lines[] = 'remaining-by-key ' . $keyId . ': ' . $count;
        }
        $lines[] = 'remaining-boundary: measured=database-current-scope; verify-separately=replica, backup, dead-letter-scopes, retention-window';
        $lines[] = 'state: ' . $result->state;
        return $lines;
    }
}

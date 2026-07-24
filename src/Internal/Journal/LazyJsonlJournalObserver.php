<?php

declare(strict_types=1);

namespace BlackOps\Internal\Journal;

use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\FlushableJournalObserver;
use BlackOps\Journal\ObservedJournalRecord;
use BlackOps\Logging\JsonlJournalObserver;
use InvalidArgumentException;

final class LazyJsonlJournalObserver implements FlushableJournalObserver
{
    private ?JsonlJournalObserver $observer = null;

    public function __construct(
        private readonly string $path,
    ) {
        if (!str_starts_with($path, DIRECTORY_SEPARATOR) || trim($path) === '') {
            throw new InvalidArgumentException('JSONL observer path must be absolute and non-empty.');
        }
    }

    public function observe(ObservedJournalRecord $record): void
    {
        $this->observer()->observe($record);
    }

    public function flush(): void
    {
        if ($this->observer !== null) {
            $this->observer->flush();
        }
    }

    private function observer(): JsonlJournalObserver
    {
        if ($this->observer !== null) {
            return $this->observer;
        }
        set_error_handler(static fn(): bool => true);
        try {
            $stream = fopen($this->path, mode: 'ab');
        } finally {
            restore_error_handler();
        }
        if (!is_resource($stream)) {
            throw new JournalObservationFailed('Observed journal stream could not be opened.');
        }
        return $this->observer = new JsonlJournalObserver($stream);
    }
}

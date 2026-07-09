<?php

declare(strict_types=1);

namespace BlackOps\Logging;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\FlushableJournalObserver;
use BlackOps\Journal\ObservedJournalRecord;
use InvalidArgumentException;
use JsonException;

#[PublicApi]
final readonly class JsonlJournalObserver implements FlushableJournalObserver
{
    /**
     * @var resource
     */
    private mixed $stream;

    private JsonlJournalRecordEncoder $encoder;

    /**
     * @param resource $stream
     */
    public function __construct(mixed $stream, ?JsonlJournalRecordEncoder $encoder = null)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('JSONL journal observer requires a writable stream resource.');
        }

        $this->stream = $stream;
        $this->encoder = $encoder ?? new JsonlJournalRecordEncoder();
    }

    public function observe(ObservedJournalRecord $record): void
    {
        try {
            $line = $this->encoder->encode($record);
        } catch (JsonException $exception) {
            throw new JournalObservationFailed('Observed journal record could not be encoded as JSON.', 0, $exception);
        }

        $written = fwrite($this->stream, $line);

        if ($written === false || $written !== strlen($line)) {
            throw new JournalObservationFailed('Observed journal record could not be written.');
        }
    }

    public function flush(): void
    {
        if (!fflush($this->stream)) {
            throw new JournalObservationFailed('Observed journal stream could not be flushed.');
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Time\TimeCodec;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalData;
use DateTimeImmutable;
use RuntimeException;

final readonly class PostgreSqlJournalDataCodec
{
    public function __construct(
        private PostgreSqlJournalValueCodec $values = new PostgreSqlJournalValueCodec(),
        private PostgreSqlJson $json = new PostgreSqlJson(),
        private PostgreSqlFailureJournalDataCodec $failures = new PostgreSqlFailureJournalDataCodec(),
        private PostgreSqlRejectionJournalDataCodec $rejections = new PostgreSqlRejectionJournalDataCodec(),
        private TimeCodec $time = new TimeCodec(),
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function encode(JournalData $data): array
    {
        if ($data instanceof EmptyJournalData) {
            return ['class' => EmptyJournalData::class, 'value' => []];
        }

        if ($data instanceof OperationReceivedData) {
            return ['class' => OperationReceivedData::class, 'value' => $this->values->encode($data->value)];
        }

        if ($data instanceof OperationCompletedData) {
            return ['class' => OperationCompletedData::class, 'value' => $this->values->encode($data->outcome)];
        }

        if ($data instanceof AttemptRetryScheduledData) {
            return [
                'class' => AttemptRetryScheduledData::class,
                'value' => [
                    'failed_attempt_id' => $data->failedAttemptId->toString(),
                    'next_attempt_number' => $data->nextAttemptNumber,
                    'scheduled_at' => $this->time->format($data->scheduledAt),
                    'delay_milliseconds' => $data->delayMilliseconds,
                ],
            ];
        }

        if ($data instanceof OperationRejectedData) {
            return $this->rejections->encode($data);
        }

        $failure = $this->failures->encode($data);

        if ($failure !== null) {
            return $failure;
        }

        throw new RuntimeException('Unsupported journal data type.');
    }

    public function decode(array $data): JournalData
    {
        return match ($this->json->string($data, 'class')) {
            EmptyJournalData::class => new EmptyJournalData(),
            OperationReceivedData::class => $this->received($data),
            OperationCompletedData::class => $this->completed($data),
            \BlackOps\Journal\Data\AttemptFailedData::class => $this->failures->attemptFailed($this->json->array(
                $data,
                'value',
            )),
            AttemptRetryScheduledData::class => $this->attemptRetryScheduled($this->json->array($data, 'value')),
            \BlackOps\Journal\Data\OperationFailedData::class => $this->failures->operationFailed($this->json->array(
                $data,
                'value',
            )),
            \BlackOps\Journal\Data\OperationDeadLetteredData::class
                => $this->failures->operationDeadLettered($this->json->array($data, 'value')),
            OperationRejectedData::class => $this->rejections->decode($this->json->array($data, 'value')),
            default => throw new RuntimeException('Unsupported journal data type.'),
        };
    }

    private function received(array $data): OperationReceivedData
    {
        $value = $this->values->decode($this->json->array($data, 'value'));

        if (!$value instanceof OperationValue) {
            throw new RuntimeException('Stored received data value is invalid.');
        }

        return new OperationReceivedData($value);
    }

    private function completed(array $data): OperationCompletedData
    {
        $outcome = $this->values->decode($this->json->array($data, 'value'));

        if (!$outcome instanceof Outcome) {
            throw new RuntimeException('Stored completed data outcome is invalid.');
        }

        return new OperationCompletedData($outcome);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private function attemptRetryScheduled(array $value): AttemptRetryScheduledData
    {
        return new AttemptRetryScheduledData(
            AttemptId::fromString($this->json->string($value, 'failed_attempt_id')),
            $this->json->int($value, 'next_attempt_number'),
            new DateTimeImmutable($this->json->string($value, 'scheduled_at')),
            $this->json->int($value, 'delay_milliseconds'),
        );
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationFailedData;
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

        if ($data instanceof AttemptFailedData) {
            return ['class' => AttemptFailedData::class, 'value' => $this->failure($data)];
        }

        if ($data instanceof AttemptRetryScheduledData) {
            return [
                'class' => AttemptRetryScheduledData::class,
                'value' => [
                    'failed_attempt_id' => $data->failedAttemptId->toString(),
                    'next_attempt_number' => $data->nextAttemptNumber,
                    'scheduled_at' => $data->scheduledAt->format(DATE_ATOM),
                    'delay_milliseconds' => $data->delayMilliseconds,
                ],
            ];
        }

        if ($data instanceof OperationFailedData) {
            return ['class' => OperationFailedData::class, 'value' => $this->failure($data)];
        }

        if ($data instanceof OperationRejectedData) {
            return [
                'class' => OperationRejectedData::class,
                'value' => ['category' => $data->reason->category()->value, 'code' => $data->reason->code()],
            ];
        }

        throw new RuntimeException('Unsupported journal data type.');
    }

    public function decode(array $data): JournalData
    {
        return match ($this->json->string($data, 'class')) {
            EmptyJournalData::class => new EmptyJournalData(),
            OperationReceivedData::class => $this->received($data),
            OperationCompletedData::class => $this->completed($data),
            AttemptFailedData::class => $this->attemptFailed($this->json->array($data, 'value')),
            AttemptRetryScheduledData::class => $this->attemptRetryScheduled($this->json->array($data, 'value')),
            OperationFailedData::class => $this->operationFailed($this->json->array($data, 'value')),
            OperationRejectedData::class => new OperationRejectedData($this->rejection($this->json->array(
                $data,
                'value',
            ))),
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

    private function rejection(array $value): RejectionReason
    {
        $code = $this->json->string($value, 'code');

        return match (RejectionCategory::from($this->json->string($value, 'category'))) {
            RejectionCategory::Validation => RejectionReason::validation($code),
            RejectionCategory::Unauthorized => RejectionReason::unauthorized($code),
            RejectionCategory::Forbidden => RejectionReason::forbidden($code),
            RejectionCategory::NotFound => RejectionReason::notFound($code),
            RejectionCategory::Conflict => RejectionReason::conflict($code),
            RejectionCategory::BusinessRule => RejectionReason::businessRule($code),
        };
    }

    private function attemptFailed(array $value): AttemptFailedData
    {
        return new AttemptFailedData(
            $this->json->string($value, 'error_type'),
            $this->json->string($value, 'error_message'),
            $this->json->bool($value, 'retryable'),
        );
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

    /**
     * @param array<array-key, mixed> $value
     */
    private function operationFailed(array $value): OperationFailedData
    {
        return new OperationFailedData(
            $this->json->string($value, 'error_type'),
            $this->json->string($value, 'error_message'),
            $this->json->bool($value, 'retryable'),
        );
    }

    /**
     * @return array{error_type: string, error_message: string, retryable: bool}
     */
    private function failure(AttemptFailedData|OperationFailedData $data): array
    {
        return [
            'error_type' => $data->errorType,
            'error_message' => $data->errorMessage,
            'retryable' => $data->retryable,
        ];
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\JournalData;
use DateTimeImmutable;
use RuntimeException;

final readonly class PostgreSqlFailureJournalDataCodec
{
    public function __construct(
        private PostgreSqlJson $json = new PostgreSqlJson(),
    ) {}

    /**
     * @return array{class: class-string, value: array<array-key, mixed>}|null
     */
    public function encode(JournalData $data): ?array
    {
        if ($data instanceof AttemptFailedData) {
            return ['class' => AttemptFailedData::class, 'value' => $this->failure($data)];
        }

        if ($data instanceof OperationFailedData) {
            return ['class' => OperationFailedData::class, 'value' => $this->failure($data)];
        }

        if ($data instanceof OperationDeadLetteredData) {
            return [
                'class' => OperationDeadLetteredData::class,
                'value' => [
                    'final_attempt_id' => $data->finalAttemptId?->toString(),
                    'final_attempt_number' => $data->finalAttemptNumber,
                    'reason_type' => $data->reasonType,
                    'reason_message' => $data->reasonMessage,
                    'moved_at' => $data->movedAt->format(DATE_ATOM),
                ],
            ];
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public function attemptFailed(array $value): AttemptFailedData
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
    public function operationFailed(array $value): OperationFailedData
    {
        return new OperationFailedData(
            $this->json->string($value, 'error_type'),
            $this->json->string($value, 'error_message'),
            $this->json->bool($value, 'retryable'),
        );
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public function operationDeadLettered(array $value): OperationDeadLetteredData
    {
        return new OperationDeadLetteredData(
            $this->attemptIdOrNull($value),
            $this->intOrNull($value, 'final_attempt_number'),
            $this->json->string($value, 'reason_type'),
            $this->json->string($value, 'reason_message'),
            new DateTimeImmutable($this->json->string($value, 'moved_at')),
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

    /**
     * @param array<array-key, mixed> $value
     */
    private function attemptIdOrNull(array $value): ?AttemptId
    {
        if (!array_key_exists('final_attempt_id', $value) || $value['final_attempt_id'] === null) {
            return null;
        }

        if (!is_string($value['final_attempt_id'])) {
            throw new RuntimeException("Stored journal field 'final_attempt_id' must be a string or null.");
        }

        return AttemptId::fromString($value['final_attempt_id']);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private function intOrNull(array $value, string $key): ?int
    {
        if (!array_key_exists($key, $value) || $value[$key] === null) {
            return null;
        }

        if (!is_int($value[$key])) {
            throw new RuntimeException("Stored journal field '{$key}' must be an integer or null.");
        }

        return $value[$key];
    }
}

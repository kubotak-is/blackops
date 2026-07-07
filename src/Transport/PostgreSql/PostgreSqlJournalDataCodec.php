<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalData;
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
}

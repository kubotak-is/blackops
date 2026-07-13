<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Validation\Violation;
use BlackOps\Journal\Data\OperationRejectedData;
use RuntimeException;

final readonly class PostgreSqlRejectionJournalDataCodec
{
    public function __construct(
        private PostgreSqlJson $json = new PostgreSqlJson(),
    ) {}

    /** @return array<string, mixed> */
    public function encode(OperationRejectedData $data): array
    {
        $value = ['category' => $data->reason->category()->value, 'code' => $data->reason->code()];
        if ($data->reason->violations() !== []) {
            $value['violations'] = array_map(static fn(Violation $violation): array => [
                'field' => $violation->field,
                'rule' => $violation->rule,
                'code' => $violation->code,
            ], $data->reason->violations());
        }

        return ['class' => OperationRejectedData::class, 'value' => $value];
    }

    /** @param array<array-key, mixed> $value */
    public function decode(array $value): OperationRejectedData
    {
        $code = $this->json->string($value, 'code');
        $reason = match (RejectionCategory::from($this->json->string($value, 'category'))) {
            RejectionCategory::Validation => RejectionReason::validation($code, $this->violations($value)),
            RejectionCategory::Unauthorized => RejectionReason::unauthorized($code),
            RejectionCategory::Forbidden => RejectionReason::forbidden($code),
            RejectionCategory::NotFound => RejectionReason::notFound($code),
            RejectionCategory::Conflict => RejectionReason::conflict($code),
            RejectionCategory::BusinessRule => RejectionReason::businessRule($code),
        };

        return new OperationRejectedData($reason);
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return list<Violation>
     */
    private function violations(array $value): array
    {
        if (!array_key_exists('violations', $value)) {
            return [];
        }

        $encoded = $this->json->array($value, 'violations');
        if (!array_is_list($encoded)) {
            throw new RuntimeException('Stored validation violations are invalid.');
        }

        return array_map($this->violation(...), $encoded);
    }

    private function violation(mixed $value): Violation
    {
        if (!is_array($value)) {
            throw new RuntimeException('Stored validation violation is invalid.');
        }

        return new Violation(
            $this->json->string($value, 'field'),
            $this->json->string($value, 'rule'),
            $this->json->string($value, 'code'),
        );
    }
}

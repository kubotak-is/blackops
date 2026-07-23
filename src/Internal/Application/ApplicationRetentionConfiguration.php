<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use InvalidArgumentException;

final readonly class ApplicationRetentionConfiguration
{
    private function __construct(
        public RetentionPolicy $policy,
        public RetentionPolicyRef $policyRef,
        public RetentionActorRef $actor,
        public int $transportPayloadDays,
        public int $journalDays,
        public int $outcomeDays,
        public int $deadLetterDays,
        public int $idempotencyRecordDays,
    ) {}

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        $retention = $configuration['retention'] ?? null;
        if (!is_array($retention)) {
            throw new InvalidArgumentException('Application configuration key "retention" must be an array.');
        }

        $transport = self::positiveInt($retention, 'transport_payload_days');
        $journal = self::positiveInt($retention, 'journal_days');
        $outcome = self::positiveInt($retention, 'outcome_days');
        $deadLetter = self::positiveInt($retention, 'dead_letter_days');
        $idempotency = array_key_exists('idempotency_record_days', $retention)
            ? self::positiveInt($retention, 'idempotency_record_days')
            : max($transport, $journal, $outcome, $deadLetter);
        $policyRef = self::requiredString($retention, 'policy_ref');
        $actor = self::requiredString($retention, 'actor');

        return new self(
            new RetentionPolicy(
                RetentionPeriod::days($transport),
                RetentionPeriod::days($journal),
                RetentionPeriod::days($outcome),
                RetentionPeriod::days($deadLetter),
                RetentionPeriod::days($idempotency),
            ),
            RetentionPolicyRef::fromString($policyRef),
            RetentionActorRef::fromString($actor),
            $transport,
            $journal,
            $outcome,
            $deadLetter,
            $idempotency,
        );
    }

    /** @param array<array-key, mixed> $retention */
    private static function positiveInt(array $retention, string $key): int
    {
        /** @var mixed $value */
        $value = $retention[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException(sprintf(
                'Application configuration key "retention.%s" must be a positive integer.',
                $key,
            ));
        }

        return $value;
    }

    /** @param array<array-key, mixed> $retention */
    private static function requiredString(array $retention, string $key): string
    {
        /** @var mixed $value */
        $value = $retention[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf(
                'Application configuration key "retention.%s" must be a non-empty string.',
                $key,
            ));
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Outbox\OutboxStatusSnapshot;
use BlackOps\Core\Outbox\OutboxStatusSource;
use BlackOps\Core\Outbox\OutboxStatusSourceException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Throwable;

/**
 * Reads the installation-wide outbox state as one bounded aggregate.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class PostgreSqlOutboxStatusSource implements OutboxStatusSource
{
    /** @var list<string> */
    private const array ALIASES = [
        'pendingReady',
        'pendingDelayed',
        'retryReady',
        'retryDelayed',
        'leasedActive',
        'leasedExpired',
        'deadLettered',
        'oldestReady',
        'oldestLeasedExpired',
        'oldestDeadLettered',
        'retryNullNextAttemptCount',
        'leasedNullLeaseCount',
        'deadLetterInvalidTimestampCount',
    ];

    private PostgreSqlOutboxSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        try {
            $this->schema = new PostgreSqlOutboxSchema($schema);
        } catch (InvalidArgumentException $exception) {
            throw OutboxStatusSourceException::configurationInvalid($exception);
        }
    }

    public function read(DateTimeImmutable $observedAt): OutboxStatusSnapshot
    {
        try {
            $row = $this->connection->fetchAssociative($this->query(), [
                'observed_at' => $observedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP'),
            ]);
        } catch (Throwable $exception) {
            throw OutboxStatusSourceException::storageUnavailable($exception);
        }
        if ($row === false) {
            throw OutboxStatusSourceException::storageUnavailable();
        }

        try {
            $this->validateAliases($row);
            $pendingReady = $this->integer($row, 'pendingReady');
            $pendingDelayed = $this->integer($row, 'pendingDelayed');
            $retryReady = $this->integer($row, 'retryReady');
            $retryDelayed = $this->integer($row, 'retryDelayed');
            $leasedActive = $this->integer($row, 'leasedActive');
            $leasedExpired = $this->integer($row, 'leasedExpired');
            $deadLettered = $this->integer($row, 'deadLettered');
            $retryNullNextAttemptCount = $this->integer($row, 'retryNullNextAttemptCount');
            $leasedNullLeaseCount = $this->integer($row, 'leasedNullLeaseCount');
            $deadLetterInvalidTimestampCount = $this->integer($row, 'deadLetterInvalidTimestampCount');
            if (
                $retryNullNextAttemptCount !== 0
                || $leasedNullLeaseCount !== 0
                || $deadLetterInvalidTimestampCount !== 0
            ) {
                throw new InvalidArgumentException('Outbox aggregate anomaly count is non-zero.');
            }

            return new OutboxStatusSnapshot(
                observedAt: $observedAt,
                pendingReady: $pendingReady,
                pendingDelayed: $pendingDelayed,
                retryReady: $retryReady,
                retryDelayed: $retryDelayed,
                leasedActive: $leasedActive,
                leasedExpired: $leasedExpired,
                deadLettered: $deadLettered,
                oldestReady: $this->age($row, 'oldestReady', $pendingReady + $retryReady, $observedAt),
                oldestLeasedExpired: $this->age($row, 'oldestLeasedExpired', $leasedExpired, $observedAt),
                oldestDeadLettered: $this->age($row, 'oldestDeadLettered', $deadLettered, $observedAt),
            );
        } catch (OutboxStatusSourceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw OutboxStatusSourceException::integrityFailed($exception);
        }
    }

    private function query(): string
    {
        $table = $this->schema->table();

        return <<<SQL
            SELECT
                COUNT(*) FILTER (WHERE state = 'pending' AND COALESCE(next_attempt_at, available_at) <= :observed_at) AS "pendingReady",
                COUNT(*) FILTER (WHERE state = 'pending' AND COALESCE(next_attempt_at, available_at) > :observed_at) AS "pendingDelayed",
                COUNT(*) FILTER (WHERE state = 'retry_scheduled' AND COALESCE(next_attempt_at, available_at) <= :observed_at) AS "retryReady",
                COUNT(*) FILTER (WHERE state = 'retry_scheduled' AND COALESCE(next_attempt_at, available_at) > :observed_at) AS "retryDelayed",
                COUNT(*) FILTER (WHERE state = 'leased' AND lease_expires_at > :observed_at) AS "leasedActive",
                COUNT(*) FILTER (WHERE state = 'leased' AND lease_expires_at <= :observed_at) AS "leasedExpired",
                COUNT(*) FILTER (WHERE state = 'dead_lettered') AS "deadLettered",
                to_char((MIN(COALESCE(next_attempt_at, available_at)) FILTER (
                    WHERE state IN ('pending', 'retry_scheduled')
                    AND COALESCE(next_attempt_at, available_at) <= :observed_at
                )) AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS "oldestReady",
                to_char((MIN(lease_expires_at) FILTER (
                    WHERE state = 'leased' AND lease_expires_at <= :observed_at
                )) AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS "oldestLeasedExpired",
                to_char((MIN(dead_lettered_at) FILTER (
                    WHERE state = 'dead_lettered'
                )) AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS "oldestDeadLettered",
                COUNT(*) FILTER (WHERE state = 'retry_scheduled' AND next_attempt_at IS NULL) AS "retryNullNextAttemptCount",
                COUNT(*) FILTER (WHERE state = 'leased' AND lease_expires_at IS NULL) AS "leasedNullLeaseCount",
                COUNT(*) FILTER (
                    WHERE state = 'dead_lettered'
                    AND (dead_lettered_at IS NULL OR dead_lettered_at > :observed_at)
                ) AS "deadLetterInvalidTimestampCount"
            FROM {$table}
            SQL;
    }

    /** @param array<string, mixed> $row */
    private function validateAliases(array $row): void
    {
        $keys = array_keys($row);
        if (
            count($keys) !== count(self::ALIASES)
            || array_diff(self::ALIASES, $keys) !== []
            || array_diff($keys, self::ALIASES) !== []
        ) {
            throw new InvalidArgumentException('Outbox aggregate columns are invalid.');
        }
    }

    /** @param array<string, mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            $this->safeInteger($value);

            return $value;
        }
        if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Outbox aggregate integer is not decimal.');
        }
        $maximum = (string) OutboxStatusSnapshot::MAX_SAFE_INTEGER;
        if (strlen($value) > strlen($maximum) || strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0) {
            throw new InvalidArgumentException('Outbox aggregate integer is outside its bound.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $row */
    private function age(array $row, string $key, int $count, DateTimeImmutable $observedAt): ?int
    {
        $value = $row[$key] ?? null;
        if ($count === 0) {
            if ($value !== null) {
                throw new InvalidArgumentException('Outbox aggregate age nullability is invalid.');
            }

            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Outbox aggregate timestamp is invalid.');
        }

        $source = $this->timestamp($value);
        $observed = $observedAt->setTimezone(new DateTimeZone('UTC'));
        $seconds = $observed->getTimestamp() - $source->getTimestamp();
        $microseconds = (int) $observed->format('u') - (int) $source->format('u');
        if ($seconds < 0 || $seconds === 0 && $microseconds < 0) {
            throw new InvalidArgumentException('Outbox aggregate timestamp is in the future.');
        }
        $age = $microseconds < 0 ? $seconds - 1 : $seconds;
        if ($age > OutboxStatusSnapshot::MAX_SAFE_INTEGER) {
            throw new InvalidArgumentException('Outbox aggregate age is outside its bound.');
        }

        return $age;
    }

    private function timestamp(string $value): DateTimeImmutable
    {
        if (
            strlen($value) !== 27
            || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}Z\z/D', $value) !== 1
        ) {
            throw new InvalidArgumentException('Outbox aggregate timestamp is invalid.');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$parsed instanceof DateTimeImmutable
            || $errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)
            || $parsed->format('Y-m-d\TH:i:s.u\Z') !== $value
        ) {
            throw new InvalidArgumentException('Outbox aggregate timestamp is invalid.');
        }

        return $parsed;
    }

    private function safeInteger(int $value): void
    {
        if ($value < 0 || $value > OutboxStatusSnapshot::MAX_SAFE_INTEGER) {
            throw new InvalidArgumentException('Outbox aggregate integer is outside its bound.');
        }
    }
}

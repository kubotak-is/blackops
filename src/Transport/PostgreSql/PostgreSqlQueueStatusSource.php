<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Queue\QueueStatusSnapshot;
use BlackOps\Core\Queue\QueueStatusSource;
use BlackOps\Core\Queue\QueueStatusSourceException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class PostgreSqlQueueStatusSource implements QueueStatusSource
{
    private const array ALIASES = [
        'acceptedReady',
        'acceptedDelayed',
        'retryReady',
        'retryDelayed',
        'runningActive',
        'runningExpired',
        'supervising',
        'deadLettered',
        'oldestReady',
        'oldestRunningExpired',
        'oldestSupervising',
        'oldestDeadLettered',
        'runningNullLeaseCount',
    ];

    private PostgreSqlDeferredOperationSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        try {
            $this->schema = new PostgreSqlDeferredOperationSchema($schema);
        } catch (InvalidArgumentException $exception) {
            throw QueueStatusSourceException::configurationInvalid($exception);
        }
    }

    public function read(DateTimeImmutable $observedAt): QueueStatusSnapshot
    {
        try {
            $row = $this->connection->fetchAssociative($this->query(), [
                'observed_at' => $observedAt->format('Y-m-d H:i:s.uP'),
            ]);
        } catch (Throwable $exception) {
            throw QueueStatusSourceException::storageUnavailable($exception);
        }
        if ($row === false) {
            throw QueueStatusSourceException::storageUnavailable();
        }

        try {
            $this->validateAliases($row);
            $acceptedReady = $this->integer($row, 'acceptedReady');
            $acceptedDelayed = $this->integer($row, 'acceptedDelayed');
            $retryReady = $this->integer($row, 'retryReady');
            $retryDelayed = $this->integer($row, 'retryDelayed');
            $runningActive = $this->integer($row, 'runningActive');
            $runningExpired = $this->integer($row, 'runningExpired');
            $supervising = $this->integer($row, 'supervising');
            $deadLettered = $this->integer($row, 'deadLettered');
            if ($this->integer($row, 'runningNullLeaseCount') !== 0) {
                throw new InvalidArgumentException('Queue running rows contain a null lease.');
            }

            return new QueueStatusSnapshot(
                observedAt: $observedAt,
                acceptedReady: $acceptedReady,
                acceptedDelayed: $acceptedDelayed,
                retryReady: $retryReady,
                retryDelayed: $retryDelayed,
                runningActive: $runningActive,
                runningExpired: $runningExpired,
                supervising: $supervising,
                deadLettered: $deadLettered,
                oldestReady: $this->age($row, 'oldestReady', $acceptedReady + $retryReady, $observedAt),
                oldestRunningExpired: $this->age($row, 'oldestRunningExpired', $runningExpired, $observedAt),
                oldestSupervising: $this->age($row, 'oldestSupervising', $supervising, $observedAt),
                oldestDeadLettered: $this->age($row, 'oldestDeadLettered', $deadLettered, $observedAt),
            );
        } catch (QueueStatusSourceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw QueueStatusSourceException::integrityFailed($exception);
        }
    }

    private function query(): string
    {
        $table = $this->schema->operationsTable();

        return <<<SQL
            SELECT
                COUNT(*) FILTER (WHERE state = 'accepted' AND available_at <= :observed_at) AS "acceptedReady",
                COUNT(*) FILTER (WHERE state = 'accepted' AND available_at > :observed_at) AS "acceptedDelayed",
                COUNT(*) FILTER (WHERE state = 'retry_scheduled' AND available_at <= :observed_at) AS "retryReady",
                COUNT(*) FILTER (WHERE state = 'retry_scheduled' AND available_at > :observed_at) AS "retryDelayed",
                COUNT(*) FILTER (WHERE state = 'running' AND lease_expires_at > :observed_at) AS "runningActive",
                COUNT(*) FILTER (WHERE state = 'running' AND lease_expires_at <= :observed_at) AS "runningExpired",
                COUNT(*) FILTER (WHERE state = 'supervising') AS "supervising",
                COUNT(*) FILTER (WHERE state = 'dead_lettered') AS "deadLettered",
                CAST(MIN(available_at) FILTER (
                    WHERE state IN ('accepted', 'retry_scheduled') AND available_at <= :observed_at
                ) AS text) AS "oldestReady",
                CAST(MIN(lease_expires_at) FILTER (
                    WHERE state = 'running' AND lease_expires_at <= :observed_at
                ) AS text) AS "oldestRunningExpired",
                CAST(MIN(updated_at) FILTER (WHERE state = 'supervising') AS text) AS "oldestSupervising",
                CAST(MIN(updated_at) FILTER (WHERE state = 'dead_lettered') AS text) AS "oldestDeadLettered",
                COUNT(*) FILTER (WHERE state = 'running' AND lease_expires_at IS NULL) AS "runningNullLeaseCount"
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
            throw new InvalidArgumentException('Queue aggregate columns are invalid.');
        }
    }

    /** @param array<string, mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            if ($value < 0 || $value > QueueStatusSnapshot::MAX_SAFE_INTEGER) {
                throw new InvalidArgumentException('Queue aggregate integer is outside its bound.');
            }

            return $value;
        }
        if (!is_string($value) || preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) !== 1) {
            throw new InvalidArgumentException('Queue aggregate integer is not decimal.');
        }
        if (
            strlen($value) > strlen((string) QueueStatusSnapshot::MAX_SAFE_INTEGER)
            || strlen($value) === strlen((string) QueueStatusSnapshot::MAX_SAFE_INTEGER)
            && strcmp($value, (string) QueueStatusSnapshot::MAX_SAFE_INTEGER) > 0
        ) {
            throw new InvalidArgumentException('Queue aggregate integer is outside its bound.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $row */
    private function age(array $row, string $key, int $count, DateTimeImmutable $observedAt): ?int
    {
        $value = $row[$key] ?? null;
        if ($count === 0) {
            if ($value !== null) {
                throw new InvalidArgumentException('Queue aggregate age nullability is invalid.');
            }

            return null;
        }
        if ($value === null) {
            throw new InvalidArgumentException('Queue aggregate age is missing.');
        }
        if (!$value instanceof DateTimeInterface && !is_string($value)) {
            throw new InvalidArgumentException('Queue aggregate timestamp is invalid.');
        }

        try {
            $source = $value instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($value)
                : new DateTimeImmutable($value);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Queue aggregate timestamp is invalid.', previous: $exception);
        }

        $observed = $observedAt->setTimezone(new DateTimeZone('UTC'));
        $source = $source->setTimezone(new DateTimeZone('UTC'));
        $microseconds =
            (($observed->getTimestamp() - $source->getTimestamp()) * 1_000_000)
            + ((int) $observed->format('u') - (int) $source->format('u'));
        if ($microseconds < 0) {
            throw new InvalidArgumentException('Queue aggregate timestamp is in the future.');
        }
        $age = intdiv(num1: $microseconds, num2: 1_000_000);
        if ($age > QueueStatusSnapshot::MAX_SAFE_INTEGER) {
            throw new InvalidArgumentException('Queue aggregate age is outside its bound.');
        }

        return $age;
    }
}

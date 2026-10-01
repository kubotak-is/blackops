<?php

declare(strict_types=1);

namespace BlackOps\Internal\Retention;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeResult;
use BlackOps\Core\Retention\RetentionPurgeService;
use BlackOps\Internal\Audit\Retention\RetentionPurgeAuditWriter;
use BlackOps\Transport\PostgreSql\PostgreSqlDeadLetterRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlIdempotencyRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPlanner;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeService;
use BlackOps\Transport\PostgreSql\PostgreSqlSystemClock;
use BlackOps\Transport\PostgreSql\PostgreSqlTransportPayloadTombstoneService;
use BlackOps\Transport\PostgreSql\SymfonyRetentionPurgeAuditIdGenerator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class AuditedRetentionPurgeService implements RetentionPurgeService
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        RetentionPurgeAuditWriter $writer,
        #[\SensitiveParameter]
        LoggerInterface $logger,
        #[\SensitiveParameter]
        string $schema = 'blackops',
        #[\SensitiveParameter]
        ClockInterface $clock = new PostgreSqlSystemClock(),
        #[\SensitiveParameter]
        PostgreSqlRetentionPurgeAuditIdGenerator $ids = new SymfonyRetentionPurgeAuditIdGenerator(),
    ) {
        try {
            $audit = new AuditedRetentionPurgeAuditPort($connection, $writer, $logger, $schema);
            $native = new PostgreSqlRetentionPurgeService(
                new PostgreSqlRetentionPlanner($connection, $schema),
                new PostgreSqlTransportPayloadTombstoneService($connection, $audit, $schema, $clock, $ids),
                new PostgreSqlOutcomeRetentionDeleteService($connection, $audit, $schema, $clock, $ids),
                new PostgreSqlDeadLetterRetentionDeleteService($connection, $audit, $schema, $clock, $ids),
                new PostgreSqlJournalRetentionDeleteService($connection, $audit, $schema, $clock, $ids),
                new PostgreSqlIdempotencyRetentionDeleteService($connection, $audit, $schema, $clock, $ids),
            );
            $this->snapshot = new \SensitiveParameterValue([
                'connection' => $connection,
                'writer' => $writer,
                'native' => $native,
            ]);
        } catch (Throwable) {
            throw self::failure();
        }
    }

    public function purge(
        #[\SensitiveParameter]
        RetentionPolicy $policy,
        #[\SensitiveParameter]
        RetentionPolicyRef $policyRef,
        #[\SensitiveParameter]
        RetentionActorRef $actor,
        #[\SensitiveParameter]
        DateTimeImmutable $now,
    ): RetentionPurgeResult {
        try {
            $values = $this->values();
            $this->assertAdmission($values['connection'], $values['writer']);
            $result = $values['native']->purge($policy, $policyRef, $actor, $now);
            if (!$result instanceof RetentionPurgeResult) {
                throw new \LogicException();
            }

            return $result;
        } catch (Throwable) {
            throw self::failure();
        }
    }

    /** @return array<never, never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<never, never> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw self::failure();
    }

    public function __clone(): void
    {
        throw self::failure();
    }

    /** @return array{connection:Connection,writer:RetentionPurgeAuditWriter,native:PostgreSqlRetentionPurgeService} */
    private function values(): array
    {
        try {
            $value = $this->snapshot->getValue();
            if (
                !is_array($value)
                || array_keys($value) !== ['connection', 'writer', 'native']
                || !$value['connection'] instanceof Connection
                || !$value['writer'] instanceof RetentionPurgeAuditWriter
                || !$value['native'] instanceof PostgreSqlRetentionPurgeService
            ) {
                throw new \LogicException();
            }

            return [
                'connection' => $value['connection'],
                'writer' => $value['writer'],
                'native' => $value['native'],
            ];
        } catch (Throwable) {
            throw self::failure();
        }
    }

    private function assertAdmission(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        RetentionPurgeAuditWriter $writer,
    ): void {
        try {
            $writer->assertConnection($connection);
            if (
                !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw new \RuntimeException();
            }
        } catch (Throwable) {
            throw self::failure();
        }
    }

    private static function failure(): DeferredTransportException
    {
        return new DeferredTransportException('Failed to deliver audited PostgreSQL retention purge.');
    }
}

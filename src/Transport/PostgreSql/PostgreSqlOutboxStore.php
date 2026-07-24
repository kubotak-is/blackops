<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
/** @mago-expect lint:too-many-methods */
final readonly class PostgreSqlOutboxStore
{
    private PostgreSqlOutboxSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        $this->schema = new PostgreSqlOutboxSchema($schema);
    }

    public function migrate(): void
    {
        try {
            foreach ($this->schema->statements() as $statement) {
                $this->connection->executeStatement($statement);
            }
        } catch (Throwable $exception) {
            throw new DeferredTransportException('Failed to migrate PostgreSQL outbox schema.', previous: $exception);
        }
    }

    public function insert(PostgreSqlOutboxRecord $record): void
    {
        $sql = "INSERT INTO {$this->schema->table()} (
            record_id, operation_id, operation_type, schema_version,
            encoded_payload, encoded_context, content_type, encoding, key_id,
            available_at, recorded_at, connection_name, state, state_version
        ) VALUES (
            :record_id, :operation_id, :operation_type, :schema_version,
            convert_to(:encoded_payload, 'UTF8'), convert_to(:encoded_context, 'UTF8'),
            :content_type, :encoding, :key_id, :available_at, :recorded_at,
            :connection_name, 'pending', 1
        )";

        try {
            $this->connection->executeStatement($sql, [
                'record_id' => $record->recordId->toString(),
                'operation_id' => $record->operationId->toString(),
                'operation_type' => $record->operationType,
                'schema_version' => $record->schemaVersion,
                'encoded_payload' => $record->encodedPayload,
                'encoded_context' => $record->encodedContext,
                'content_type' => 'application/vnd.blackops.deferred-operation+json',
                'encoding' => 'utf8',
                'key_id' => null,
                'available_at' => $this->timestamp($record->availableAt),
                'recorded_at' => $this->timestamp($record->recordedAt),
                'connection_name' => $record->connectionName,
            ]);
        } catch (Throwable $exception) {
            throw new DeferredTransportException('Failed to persist PostgreSQL outbox record.', previous: $exception);
        }
    }

    /**
     * @return list<PostgreSqlOutboxClaim>
     * @mago-expect lint:halstead
     */
    public function claimBatch(string $relayId, int $batchSize, DateTimeImmutable $now, int $leaseSeconds): array
    {
        if (trim($relayId) === '' || $batchSize < 1 || $leaseSeconds < 1) {
            throw new DeferredTransportException('Outbox relay claim configuration is invalid.');
        }
        try {
            return $this->connection->transactional(function () use ($relayId, $batchSize, $now, $leaseSeconds): array {
                $rows = $this->connection->fetchAllAssociative(
                    "SELECT record_id::text AS record_id, operation_id::text AS operation_id, operation_type,
                        schema_version, convert_from(encoded_payload, 'UTF8') AS encoded_payload,
                        convert_from(encoded_context, 'UTF8') AS encoded_context, available_at,
                        COALESCE(next_attempt_at, available_at) AS due_at, attempt_count, fencing_token,
                        content_type, encoding, key_id
                     FROM {$this->schema->table()}
                     WHERE (state IN ('pending','retry_scheduled') AND COALESCE(next_attempt_at, available_at) <= :now)
                        OR (state = 'leased' AND lease_expires_at IS NOT NULL AND lease_expires_at <= :now)
                     ORDER BY COALESCE(next_attempt_at, available_at), record_id
                     FOR UPDATE SKIP LOCKED LIMIT {$batchSize}",
                    ['now' => $this->timestamp($now)],
                );
                $claims = [];
                foreach ($rows as $row) {
                    if (
                        $row['content_type'] !== 'application/vnd.blackops.deferred-operation+json'
                        || $row['encoding'] !== 'utf8'
                        || $row['key_id'] !== null
                    ) {
                        throw new DeferredTransportException('Outbox record integrity is invalid.');
                    }
                    $token = (int) $row['fencing_token'] + 1;
                    $attempt = (int) $row['attempt_count'] + 1;
                    $expires = $now->modify('+' . $leaseSeconds . ' seconds');
                    $updated = $this->connection->executeStatement(
                        "UPDATE {$this->schema->table()} SET state='leased', state_version=state_version+1,
                            relay_id=:relay_id, lease_expires_at=:expires, leased_at=:leased_at,
                            fencing_token=:token, attempt_count=:attempt, next_attempt_at=NULL
                         WHERE record_id=:record_id AND (state IN ('pending','retry_scheduled') OR (state='leased' AND lease_expires_at <= :now))",
                        [
                            'relay_id' => $relayId,
                            'expires' => $this->timestamp($expires),
                            'leased_at' => $this->timestamp($now),
                            'token' => $token,
                            'attempt' => $attempt,
                            'record_id' => $row['record_id'],
                            'now' => $this->timestamp($now),
                        ],
                    );
                    if ((int) $updated !== 1) {
                        continue;
                    }
                    $claims[] = new PostgreSqlOutboxClaim(
                        OutboxRecordId::fromString((string) $row['record_id']),
                        new DeferredOperationMessage(
                            OperationId::fromString((string) $row['operation_id']),
                            (string) $row['operation_type'],
                            (int) $row['schema_version'],
                            (string) $row['encoded_payload'],
                            (string) $row['encoded_context'],
                            new DateTimeImmutable((string) $row['available_at']),
                        ),
                        $relayId,
                        $token,
                        $attempt,
                        $expires,
                    );
                }
                return $claims;
            });
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }
            throw new DeferredTransportException('Failed to claim PostgreSQL outbox records.', previous: $exception);
        }
    }

    public function heartbeat(PostgreSqlOutboxClaim $claim, DateTimeImmutable $now, int $leaseSeconds): void
    {
        try {
            $updated = $this->connection->executeStatement(
                "UPDATE {$this->schema->table()} SET lease_expires_at=:expires
                 WHERE record_id=:record_id AND relay_id=:relay_id AND fencing_token=:token AND state='leased'
                   AND lease_expires_at > :now",
                [
                    'expires' => $this->timestamp($now->modify('+' . $leaseSeconds . ' seconds')),
                    'record_id' => $claim->recordId->toString(),
                    'relay_id' => $claim->relayId,
                    'token' => $claim->fencingToken,
                    'now' => $this->timestamp($now),
                ],
            );
            $this->assertOwnership($updated, 'Outbox relay heartbeat claim is stale.');
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }
            throw new DeferredTransportException('Failed to heartbeat PostgreSQL outbox record.', previous: $exception);
        }
    }

    public function markSent(PostgreSqlOutboxClaim $claim): void
    {
        try {
            $updated = $this->connection->executeStatement(
                "UPDATE {$this->schema->table()} SET state='sent', state_version=state_version+1, sent_at=CURRENT_TIMESTAMP,
                    relay_id=NULL, lease_expires_at=NULL, leased_at=NULL, next_attempt_at=NULL
                 WHERE record_id=:record_id AND relay_id=:relay_id AND fencing_token=:token AND state='leased'",
                [
                    'record_id' => $claim->recordId->toString(),
                    'relay_id' => $claim->relayId,
                    'token' => $claim->fencingToken,
                ],
            );
            $this->assertOwnership($updated, 'Outbox relay settlement claim is stale.');
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }
            throw new DeferredTransportException('Failed to mark PostgreSQL outbox record sent.', previous: $exception);
        }
    }

    public function scheduleRetry(
        PostgreSqlOutboxClaim $claim,
        DateTimeImmutable $nextAttemptAt,
        string $fingerprint,
    ): void {
        $this->assertFingerprint($fingerprint);
        try {
            $updated = $this->connection->executeStatement(
                "UPDATE {$this->schema->table()} SET state='retry_scheduled', state_version=state_version+1,
                    relay_id=NULL, lease_expires_at=NULL, leased_at=NULL, next_attempt_at=:next_attempt,
                    failure_fingerprint=:fingerprint, failure_fingerprint_version=1
                 WHERE record_id=:record_id AND relay_id=:relay_id AND fencing_token=:token AND state='leased'",
                [
                    'next_attempt' => $this->timestamp($nextAttemptAt),
                    'fingerprint' => $fingerprint,
                    'record_id' => $claim->recordId->toString(),
                    'relay_id' => $claim->relayId,
                    'token' => $claim->fencingToken,
                ],
            );
            $this->assertOwnership($updated, 'Outbox relay retry claim is stale.');
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }
            throw new DeferredTransportException('Failed to schedule PostgreSQL outbox retry.', previous: $exception);
        }
    }

    public function moveToDeadLetter(PostgreSqlOutboxClaim $claim, string $fingerprint): void
    {
        $this->assertFingerprint($fingerprint);
        try {
            $updated = $this->connection->executeStatement(
                "UPDATE {$this->schema->table()} SET state='dead_lettered', state_version=state_version+1, dead_lettered_at=CURRENT_TIMESTAMP,
                    relay_id=NULL, lease_expires_at=NULL, leased_at=NULL, next_attempt_at=NULL,
                    failure_fingerprint=:fingerprint, failure_fingerprint_version=1
                 WHERE record_id=:record_id AND relay_id=:relay_id AND fencing_token=:token AND state='leased'",
                [
                    'fingerprint' => $fingerprint,
                    'record_id' => $claim->recordId->toString(),
                    'relay_id' => $claim->relayId,
                    'token' => $claim->fencingToken,
                ],
            );
            $this->assertOwnership($updated, 'Outbox relay dead-letter claim is stale.');
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }
            throw new DeferredTransportException(
                'Failed to dead-letter PostgreSQL outbox record.',
                previous: $exception,
            );
        }
    }

    public function retryDeadLetter(
        OutboxRecordId $recordId,
        string $actor,
        string $reason,
        DateTimeImmutable $now,
    ): void {
        if (trim($actor) === '' || trim($reason) === '') {
            throw new DeferredTransportException('Dead-letter retry actor and reason are required.');
        }
        try {
            $this->connection->transactional(function () use ($recordId, $actor, $reason, $now): void {
                $row = $this->connection->fetchAssociative(
                    "SELECT operation_id::text AS operation_id, attempt_count FROM {$this->schema->table()} WHERE record_id=:record_id AND state='dead_lettered' FOR UPDATE",
                    ['record_id' => $recordId->toString()],
                );
                if (!is_array($row)) {
                    throw new DeferredTransportException('Outbox record is not dead-lettered.');
                }
                $this->connection->executeStatement(
                    "INSERT INTO {$this->schema->retryAuditTable()} (audit_id, record_id, operation_id, actor, reason, retried_at, previous_attempt_count) VALUES (:audit_id,:record_id,:operation_id,:actor,:reason,:retried_at,:attempt_count)",
                    [
                        'audit_id' => $this->uuid4(),
                        'record_id' => $recordId->toString(),
                        'operation_id' => $row['operation_id'],
                        'actor' => $actor,
                        'reason' => $reason,
                        'retried_at' => $this->timestamp($now),
                        'attempt_count' => (int) $row['attempt_count'],
                    ],
                );
                $updated = $this->connection->executeStatement(
                    "UPDATE {$this->schema->table()} SET state='retry_scheduled', state_version=state_version+1, next_attempt_at=:next_attempt, failure_fingerprint=NULL, failure_fingerprint_version=NULL, dead_lettered_at=NULL, relay_id=NULL, lease_expires_at=NULL, leased_at=NULL WHERE record_id=:record_id AND state='dead_lettered'",
                    ['next_attempt' => $this->timestamp($now), 'record_id' => $recordId->toString()],
                );
                $this->assertOwnership($updated, 'Outbox dead-letter retry claim is stale.');
            });
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }
            throw new DeferredTransportException('Failed to retry dead-lettered outbox record.', previous: $exception);
        }
    }

    private function assertOwnership(int|string $updated, string $message): void
    {
        if ((int) $updated !== 1) {
            throw new DeferredTransportException($message);
        }
    }

    private function assertFingerprint(string $fingerprint): void
    {
        if (preg_match('/\Av1:[a-f0-9]{64}\z/', $fingerprint) !== 1) {
            throw new DeferredTransportException('Outbox failure fingerprint is invalid.');
        }
    }

    private function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), length: 4));
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }
}

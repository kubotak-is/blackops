<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Internal\Execution\DeferredOperationContextValidator;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\StorageProtection\StoragePurpose;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:kan-defect
 */
final readonly class PostgreSqlOutboxStore
{
    private PostgreSqlOutboxSchema $schema;

    public function __construct(
        private Connection $connection,
        private BopdEnvelopeCodec $protection,
        private OperationCodec $codec,
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
            tenant_type, tenant_id, origin_actor_type, origin_actor_id,
            available_at, recorded_at, connection_name, state, state_version
        ) VALUES (
            :record_id, :operation_id, :operation_type, :schema_version,
            :encoded_payload, :encoded_context,
            :content_type, :encoding, :key_id, :tenant_type, :tenant_id, :origin_actor_type, :origin_actor_id,
            :available_at, :recorded_at,
            :connection_name, 'pending', 1
        )";

        try {
            $params = [
                'record_id' => $record->recordId->toString(),
                'operation_id' => $record->operationId->toString(),
                'operation_type' => $record->operationType,
                'schema_version' => $record->schemaVersion,
                'encoded_payload' => $this->protection->encrypt(
                    $record->encodedPayload,
                    new StorageProtectionContext(
                        StoragePurpose::OutboxPayload,
                        $record->recordId->toString(),
                        $record->operationId->toString(),
                        $record->operationType,
                        $record->schemaVersion,
                        $record->tenant,
                    ),
                ),
                'encoded_context' => $this->protection->encrypt(
                    $record->encodedContext,
                    new StorageProtectionContext(
                        StoragePurpose::OutboxContext,
                        $record->recordId->toString(),
                        $record->operationId->toString(),
                        $record->operationType,
                        $record->schemaVersion,
                        $record->tenant,
                    ),
                ),
                'content_type' => 'application/vnd.blackops.deferred-operation+json',
                'encoding' => 'utf8',
                'key_id' => null,
                'tenant_type' => $record->tenant?->type(),
                'tenant_id' => $record->tenant?->id(),
                'origin_actor_type' => $record->originActor?->type(),
                'origin_actor_id' => $record->originActor?->id(),
                'available_at' => $this->timestamp($record->availableAt),
                'recorded_at' => $this->timestamp($record->recordedAt),
                'connection_name' => $record->connectionName,
            ];
            $this->connection->executeStatement($sql, $params, [
                'encoded_payload' => \Doctrine\DBAL\ParameterType::BINARY,
                'encoded_context' => \Doctrine\DBAL\ParameterType::BINARY,
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
                        schema_version, encoded_payload, encoded_context, available_at,
                        tenant_type, tenant_id, origin_actor_type, origin_actor_id,
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
                    $tenant = $this->tenant($row);
                    $operationId = (string) $row['operation_id'];
                    $operationType = (string) $row['operation_type'];
                    $schemaVersion = (int) $row['schema_version'];
                    $recordId = (string) $row['record_id'];
                    $payload = $this->protection->decrypt(
                        PostgreSqlBytea::string($row['encoded_payload'] ?? null),
                        new StorageProtectionContext(
                            StoragePurpose::OutboxPayload,
                            $recordId,
                            $operationId,
                            $operationType,
                            $schemaVersion,
                            $tenant,
                        ),
                    );
                    $context = $this->protection->decrypt(
                        PostgreSqlBytea::string($row['encoded_context'] ?? null),
                        new StorageProtectionContext(
                            StoragePurpose::OutboxContext,
                            $recordId,
                            $operationId,
                            $operationType,
                            $schemaVersion,
                            $tenant,
                        ),
                    );
                    $message = new DeferredOperationMessage(
                        OperationId::fromString($operationId),
                        $operationType,
                        $schemaVersion,
                        $payload,
                        $context,
                        new DateTimeImmutable((string) $row['available_at']),
                        $tenant,
                        $this->originActor($row),
                    );
                    DeferredOperationContextValidator::assertMatches($message, $this->codec->decodeContext(
                        $schemaVersion,
                        $context,
                    ));
                    $claims[] = new PostgreSqlOutboxClaim(
                        OutboxRecordId::fromString($recordId),
                        $message,
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

    /** @param array<string, mixed> $row */
    private function tenant(array $row): ?\BlackOps\Core\TenantRef
    {
        $type = $this->subjectString($row['tenant_type'] ?? null, 'Outbox row contains a partial tenant subject.');
        $id = $this->subjectString($row['tenant_id'] ?? null, 'Outbox row contains a partial tenant subject.');
        if ($type === null && $id === null) {
            return null;
        }
        if ($type === null || $id === null) {
            throw new DeferredTransportException('Outbox row contains a partial tenant subject.');
        }
        return new \BlackOps\Core\TenantRef($type, $id);
    }

    /** @param array<string, mixed> $row */
    private function originActor(array $row): ?\BlackOps\Core\ActorRef
    {
        $type = $this->subjectString(
            $row['origin_actor_type'] ?? null,
            'Outbox row contains a partial origin actor subject.',
        );
        $id = $this->subjectString(
            $row['origin_actor_id'] ?? null,
            'Outbox row contains a partial origin actor subject.',
        );
        if ($type === null && $id === null) {
            return null;
        }
        if ($type === null || $id === null) {
            throw new DeferredTransportException('Outbox row contains a partial origin actor subject.');
        }
        return new \BlackOps\Core\ActorRef($id, $type);
    }

    private function subjectString(mixed $value, string $message): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new DeferredTransportException($message);
        }
        return $value;
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

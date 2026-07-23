<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Idempotency\IdempotencyKeyHash;
use BlackOps\Transport\PostgreSql\PostgreSqlIdempotencySchema;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeCodec;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class PostgreSqlIdempotencyStore implements IdempotencyStore
{
    private PostgreSqlIdempotencySchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        $this->schema = new PostgreSqlIdempotencySchema($schema);
    }

    public function migrate(): void
    {
        try {
            foreach ($this->schema->statements() as $statement) {
                $this->connection->executeStatement($statement);
            }
        } catch (Throwable $exception) {
            throw new DeferredTransportException(
                'Failed to migrate PostgreSQL idempotency schema.',
                previous: $exception,
            );
        }
    }

    /** @mago-expect lint:excessive-parameter-list */
    public function claim(
        IdempotencyScopeHash $scope,
        IdempotencyKeyHash $key,
        OperationFingerprint $fingerprint,
        OperationId $operationId,
        ExecutionStrategy $strategy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): IdempotencyClaimResult {
        try {
            $table = $this->schema->table();
            $inserted = $this->connection->executeStatement(
                "INSERT INTO {$table} (
                    scope_version, scope_hash, key_version, key_hash,
                    fingerprint_version, fingerprint_hash, operation_id,
                    strategy, state, created_at, expires_at
                ) VALUES (
                    :scope_version, :scope_hash, :key_version, :key_hash,
                    :fingerprint_version, :fingerprint_hash, :operation_id,
                    :strategy, 'processing', :created_at, :expires_at
                ) ON CONFLICT (scope_version, scope_hash) DO NOTHING",
                $this->params($scope, $key, $fingerprint, $operationId, $strategy, $createdAt, $expiresAt),
            );
            if ((int) $inserted === 1) {
                return new IdempotencyClaimResult(
                    IdempotencyClaimStatus::Claimed,
                    new ProcessingRecord($scope, $key, $fingerprint, $operationId, $strategy, $createdAt, $expiresAt),
                );
            }

            $row = $this->connection->fetchAssociative(
                "SELECT * FROM {$table} WHERE scope_version = :scope_version AND scope_hash = :scope_hash",
                ['scope_version' => $scope->version(), 'scope_hash' => $scope->digest()],
            );
            if (!is_array($row)) {
                throw new DeferredTransportException('PostgreSQL idempotency claim row is missing.');
            }
            $existing = $this->record($row);

            return new IdempotencyClaimResult(
                $existing->fingerprint()->equals($fingerprint)
                    ? IdempotencyClaimStatus::ExistingSameFingerprint
                    : IdempotencyClaimStatus::ExistingConflict,
                $existing,
            );
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }
            throw new DeferredTransportException(
                'Failed to claim PostgreSQL idempotency record.',
                previous: $exception,
            );
        }
    }

    public function terminalize(
        OperationId $operationId,
        TerminalRecord $record,
        IdempotencyRecordState $expectedState = IdempotencyRecordState::Processing,
    ): bool {
        if ($expectedState !== IdempotencyRecordState::Processing) {
            return false;
        }
        try {
            $snapshot = $record->response();
            $resultSnapshot = $record->result();
            $result = $resultSnapshot?->isInternalFailure() === true ? null : $resultSnapshot?->result();
            $encodedOutcome = null;
            if ($result?->isCompleted() === true) {
                $encoded = new PostgreSqlOutcomeCodec()->encode($result->outcome());
                $encodedOutcome = [$encoded->type, $encoded->schemaVersion, $encoded->payload];
            }
            $resultKind = null;
            if ($resultSnapshot !== null) {
                $resultKind = match (true) {
                    $resultSnapshot->isInternalFailure() => 'internal_failure',
                    $result?->isCompleted() === true => 'completed',
                    default => 'rejected',
                };
            }
            $updated = $this->connection->executeStatement(
                "UPDATE {$this->schema->table()}
                SET state = 'terminal', state_version = state_version + 1,
                    response_version = :response_version,
                    response_status = :response_status,
                    response_headers = :response_headers,
                    response_body = :response_body,
                    result_kind = :result_kind,
                    result_type = :result_type,
                    result_schema_version = :result_schema_version,
                    result_payload = :result_payload,
                    rejection_category = :rejection_category,
                    rejection_code = :rejection_code,
                    accepted_at = :accepted_at
                WHERE scope_version = :scope_version
                    AND scope_hash = :scope_hash
                    AND operation_id = :operation_id
                    AND fingerprint_version = :fingerprint_version
                    AND fingerprint_hash = :fingerprint_hash
                    AND state = 'processing'",
                [
                    'response_version' => $snapshot?->version(),
                    'response_status' => $snapshot?->status(),
                    'response_headers' => $snapshot === null
                        ? null
                        : json_encode($snapshot->headers(), JSON_THROW_ON_ERROR),
                    'response_body' => $snapshot?->body(),
                    'result_kind' => $resultKind,
                    'result_type' => $encodedOutcome[0] ?? null,
                    'result_schema_version' => $encodedOutcome[1] ?? null,
                    'result_payload' => $encodedOutcome[2] ?? null,
                    'rejection_category' => $result?->isRejected() === true
                        ? $result->rejectionReason()->category()->value
                        : null,
                    'rejection_code' => $result?->isRejected() === true ? $result->rejectionReason()->code() : null,
                    'accepted_at' => $record->acceptedAt()?->format('Y-m-d H:i:s.uP'),
                    'scope_version' => $record->scope()->version(),
                    'scope_hash' => $record->scope()->digest(),
                    'operation_id' => $operationId->toString(),
                    'fingerprint_version' => $record->fingerprint()->version(),
                    'fingerprint_hash' => $record->fingerprint()->digest(),
                ],
            );

            return (int) $updated === 1;
        } catch (Throwable $exception) {
            throw new DeferredTransportException(
                'Failed to terminalize PostgreSQL idempotency record.',
                previous: $exception,
            );
        }
    }

    public function find(IdempotencyScopeHash $scope): ProcessingRecord|TerminalRecord|null
    {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT * FROM {$this->schema->table()} WHERE scope_version = :scope_version AND scope_hash = :scope_hash",
                ['scope_version' => $scope->version(), 'scope_hash' => $scope->digest()],
            );

            return is_array($row) ? $this->record($row) : null;
        } catch (Throwable $exception) {
            throw new DeferredTransportException('Failed to find PostgreSQL idempotency record.', previous: $exception);
        }
    }

    public function attachResponse(OperationId $operationId, IdempotencyResponseSnapshot $snapshot): bool
    {
        try {
            return (int) $this->connection->executeStatement(
                "UPDATE {$this->schema->table()}
                SET response_version = :version, response_status = :status,
                    response_headers = :headers, response_body = :body
                WHERE operation_id = :operation_id AND state = 'terminal'",
                [
                    'version' => $snapshot->version(),
                    'status' => $snapshot->status(),
                    'headers' => json_encode($snapshot->headers(), JSON_THROW_ON_ERROR),
                    'body' => $snapshot->body(),
                    'operation_id' => $operationId->toString(),
                ],
            ) === 1;
        } catch (Throwable $exception) {
            throw new DeferredTransportException(
                'Failed to attach PostgreSQL idempotency response.',
                previous: $exception,
            );
        }
    }

    public function response(OperationId $operationId): ?IdempotencyResponseSnapshot
    {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT response_version, response_status, response_headers, response_body
                FROM {$this->schema->table()} WHERE operation_id = :operation_id AND state = 'terminal'",
                ['operation_id' => $operationId->toString()],
            );
            if (!is_array($row) || $row['response_version'] === null) {
                return null;
            }
            /** @var mixed $decodedHeaders */
            $decodedHeaders = json_decode(
                $this->string($row, 'response_headers'),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );
            $headers = $this->headers($decodedHeaders);

            return new IdempotencyResponseSnapshot(
                $this->integer($row, 'response_version'),
                $this->integer($row, 'response_status'),
                $headers,
                $this->string($row, 'response_body'),
            );
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }
            throw new DeferredTransportException(
                'Failed to read PostgreSQL idempotency response.',
                previous: $exception,
            );
        }
    }

    /**
     * @return array<string, mixed>
     * @mago-expect lint:excessive-parameter-list
     */
    private function params(
        IdempotencyScopeHash $scope,
        IdempotencyKeyHash $key,
        OperationFingerprint $fingerprint,
        OperationId $operationId,
        ExecutionStrategy $strategy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): array {
        return [
            'scope_version' => $scope->version(),
            'scope_hash' => $scope->digest(),
            'key_version' => $key->version(),
            'key_hash' => $key->digest(),
            'fingerprint_version' => $fingerprint->version(),
            'fingerprint_hash' => $fingerprint->digest(),
            'operation_id' => $operationId->toString(),
            'strategy' => $strategy::class,
            'created_at' => $createdAt->format('Y-m-d H:i:s.uP'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s.uP'),
        ];
    }

    /** @param array<string, mixed> $row */
    /** @mago-expect lint:halstead */
    /** @mago-expect lint:no-else-clause */
    private function record(array $row): ProcessingRecord|TerminalRecord
    {
        /** @var array<string, mixed> $row */
        $scope = new IdempotencyScopeHash($this->integer($row, 'scope_version'), $this->string($row, 'scope_hash'));
        $key = new IdempotencyKeyHash($this->integer($row, 'key_version'), $this->string($row, 'key_hash'));
        $fingerprint = new OperationFingerprint(
            $this->integer($row, 'fingerprint_version'),
            $this->string($row, 'fingerprint_hash'),
        );
        $strategy = match ($this->string($row, 'strategy')) {
            Inline::class => new Inline(),
            Deferred::class => new Deferred(),
            default => throw new DeferredTransportException('PostgreSQL idempotency strategy is invalid.'),
        };
        $created = new DateTimeImmutable($this->string($row, 'created_at'));
        $expires = new DateTimeImmutable($this->string($row, 'expires_at'));
        if ($this->string($row, 'state') === IdempotencyRecordState::Processing->value) {
            return new ProcessingRecord(
                $scope,
                $key,
                $fingerprint,
                OperationId::fromString($this->string($row, 'operation_id')),
                $strategy,
                $created,
                $expires,
            );
        }

        $snapshot = null;
        if ($row['response_version'] !== null) {
            /** @var mixed $decodedHeaders */
            $decodedHeaders = json_decode(
                $this->string($row, 'response_headers'),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );
            $headers = $this->headers($decodedHeaders);
            $snapshot = new IdempotencyResponseSnapshot(
                $this->integer($row, 'response_version'),
                $this->integer($row, 'response_status'),
                $headers,
                $this->string($row, 'response_body'),
            );
        }

        $result = null;
        $operationId = OperationId::fromString($this->string($row, 'operation_id'));
        if ($row['result_kind'] === 'completed' && $row['result_type'] !== null && $row['result_payload'] !== null) {
            $result = new IdempotencyResultSnapshot(OperationResult::completed(
                new PostgreSqlOutcomeCodec()->decode(
                    $this->string($row, 'result_type'),
                    $this->integer($row, 'result_schema_version'),
                    $this->string($row, 'result_payload'),
                ),
                $operationId,
            ));
        } elseif ($row['result_kind'] === 'internal_failure') {
            $result = IdempotencyResultSnapshot::internalFailure($operationId);
        } elseif (
            $row['result_kind'] === 'rejected'
            && $row['rejection_category'] !== null
            && $row['rejection_code'] !== null
        ) {
            $code = $this->string($row, 'rejection_code');
            $reason = match (RejectionCategory::from($this->string($row, 'rejection_category'))) {
                RejectionCategory::Validation => \BlackOps\Core\Rejection\RejectionReason::validation($code),
                RejectionCategory::Unauthorized => \BlackOps\Core\Rejection\RejectionReason::unauthorized($code),
                RejectionCategory::Forbidden => \BlackOps\Core\Rejection\RejectionReason::forbidden($code),
                RejectionCategory::NotFound => \BlackOps\Core\Rejection\RejectionReason::notFound($code),
                RejectionCategory::Conflict => \BlackOps\Core\Rejection\RejectionReason::conflict($code),
                RejectionCategory::BusinessRule => \BlackOps\Core\Rejection\RejectionReason::businessRule($code),
            };
            $result = new IdempotencyResultSnapshot(OperationResult::rejected($reason, $operationId));
        }

        $acceptedAt = null;
        if ($row['accepted_at'] !== null) {
            try {
                $acceptedAt = new DateTimeImmutable($this->string($row, 'accepted_at'));
            } catch (Throwable $exception) {
                throw new DeferredTransportException(
                    'PostgreSQL idempotency accepted timestamp is invalid.',
                    previous: $exception,
                );
            }
        }

        return new TerminalRecord(
            $scope,
            $key,
            $fingerprint,
            $operationId,
            $strategy,
            $created,
            $expires,
            $snapshot,
            $result,
            $acceptedAt,
        );
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key]) || $row[$key] === '') {
            throw new DeferredTransportException('PostgreSQL idempotency row is invalid.');
        }
        return $row[$key];
    }

    /** @param array<string, mixed> $row */
    private function integer(array $row, string $key): int
    {
        if (!array_key_exists($key, $row) || !is_int($row[$key]) && !ctype_digit((string) $row[$key])) {
            throw new DeferredTransportException('PostgreSQL idempotency row is invalid.');
        }
        return (int) $row[$key];
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private function headers(mixed $value): array
    {
        if (!is_array($value)) {
            throw new DeferredTransportException('PostgreSQL idempotency response headers are invalid.');
        }
        /** @var array<string, mixed> $value */
        $headers = [];
        foreach (array_keys($value) as $name) {
            if (!is_string($value[$name])) {
                throw new DeferredTransportException('PostgreSQL idempotency response headers are invalid.');
            }
            $headers[$name] = $value[$name];
        }

        return $headers;
    }
}

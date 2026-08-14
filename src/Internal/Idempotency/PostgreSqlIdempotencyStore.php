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
use BlackOps\Core\TenantRef;
use BlackOps\Idempotency\IdempotencyKeyHash;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
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
        private BopdEnvelopeCodec $protection,
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
        string $operationType,
        int $applicationSchemaVersion,
        ?TenantRef $tenant = null,
    ): IdempotencyClaimResult {
        try {
            $table = $this->schema->table();
            $inserted = $this->connection->executeStatement(
                "INSERT INTO {$table} (
                    scope_version, scope_hash, key_version, key_hash,
                    fingerprint_version, fingerprint_hash, operation_id,
                    tenant_type, tenant_id, operation_type, application_schema_version,
                    strategy, state, created_at, expires_at
                ) VALUES (
                    :scope_version, :scope_hash, :key_version, :key_hash,
                    :fingerprint_version, :fingerprint_hash, :operation_id,
                    :tenant_type, :tenant_id, :operation_type, :application_schema_version,
                    :strategy, 'processing', :created_at, :expires_at
                ) ON CONFLICT (scope_version, scope_hash) DO NOTHING",
                $this->params(
                    $scope,
                    $key,
                    $fingerprint,
                    $operationId,
                    $strategy,
                    $createdAt,
                    $expiresAt,
                    $operationType,
                    $applicationSchemaVersion,
                    $tenant,
                ),
            );
            if ((int) $inserted === 1) {
                return new IdempotencyClaimResult(
                    IdempotencyClaimStatus::Claimed,
                    new ProcessingRecord($scope, $key, $fingerprint, $operationId, $strategy, $createdAt, $expiresAt),
                );
            }

            $row = $this->connection->fetchAssociative(
                "SELECT * FROM {$table} WHERE scope_version = :scope_version AND scope_hash = :scope_hash
                        AND tenant_type IS NOT DISTINCT FROM :tenant_type
                        AND tenant_id IS NOT DISTINCT FROM :tenant_id",
                [
                    'scope_version' => $scope->version(),
                    'scope_hash' => $scope->digest(),
                    'tenant_type' => $tenant?->type(),
                    'tenant_id' => $tenant?->id(),
                ],
            );
            if (!is_array($row)) {
                throw new DeferredTransportException('PostgreSQL idempotency claim row is missing.');
            }
            if (
                ($row['operation_type'] ?? null) !== $operationType
                || (int) ($row['application_schema_version'] ?? 0) !== $applicationSchemaVersion
            ) {
                throw new DeferredTransportException('PostgreSQL idempotency row metadata is inconsistent.');
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
            $row = $this->connection->fetchAssociative(
                "SELECT operation_type, application_schema_version, tenant_type, tenant_id
                 FROM {$this->schema->table()} WHERE operation_id = :operation_id",
                ['operation_id' => $operationId->toString()],
            );
            if (!is_array($row)) {
                return false;
            }
            $tenant = $this->tenant($row);
            $snapshot = $record->response();
            $resultSnapshot = $record->result();
            $encodedResponse = $this->encodeResponse($snapshot, $this->context(
                $record->scope(),
                $operationId,
                $row,
                StoragePurpose::IdempotencyResponse,
                $tenant,
            ));
            $encodedResult = $this->encodeResult(
                $resultSnapshot,
                $operationId,
                $this->context($record->scope(), $operationId, $row, StoragePurpose::IdempotencyResult, $tenant),
            );
            $updated = $this->connection->executeStatement(
                "UPDATE {$this->schema->table()}
                SET state = 'terminal', state_version = state_version + 1,
                    encoded_response = :encoded_response,
                    encoded_result = :encoded_result,
                    accepted_at = :accepted_at
                WHERE scope_version = :scope_version
                    AND scope_hash = :scope_hash
                    AND operation_id = :operation_id
                    AND fingerprint_version = :fingerprint_version
                    AND fingerprint_hash = :fingerprint_hash
                    AND state = 'processing'",
                [
                    'encoded_response' => $encodedResponse,
                    'encoded_result' => $encodedResult,
                    'accepted_at' => $record->acceptedAt()?->format('Y-m-d H:i:s.uP'),
                    'scope_version' => $record->scope()->version(),
                    'scope_hash' => $record->scope()->digest(),
                    'operation_id' => $operationId->toString(),
                    'fingerprint_version' => $record->fingerprint()->version(),
                    'fingerprint_hash' => $record->fingerprint()->digest(),
                ],
                [
                    'encoded_response' => \Doctrine\DBAL\ParameterType::BINARY,
                    'encoded_result' => \Doctrine\DBAL\ParameterType::BINARY,
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
            $row = $this->connection->fetchAssociative(
                "SELECT scope_version, scope_hash, operation_type, application_schema_version, tenant_type, tenant_id
                 FROM {$this->schema->table()} WHERE operation_id = :operation_id AND state = 'terminal'",
                ['operation_id' => $operationId->toString()],
            );
            if (!is_array($row)) {
                return false;
            }
            $tenant = $this->tenant($row);
            $encoded = $this->protection->encrypt(
                json_encode([
                    'version' => 1,
                    'status' => $snapshot->status(),
                    'headers' => $snapshot->headers(),
                    'body' => $snapshot->body(),
                ], JSON_THROW_ON_ERROR),
                $this->context(
                    new IdempotencyScopeHash((int) $row['scope_version'], (string) $row['scope_hash']),
                    $operationId,
                    $row,
                    StoragePurpose::IdempotencyResponse,
                    $tenant,
                ),
            );
            return (int) $this->connection->executeStatement(
                "UPDATE {$this->schema->table()}
                SET encoded_response = :encoded_response
                WHERE operation_id = :operation_id AND state = 'terminal'",
                [
                    'encoded_response' => $encoded,
                    'operation_id' => $operationId->toString(),
                ],
                ['encoded_response' => \Doctrine\DBAL\ParameterType::BINARY],
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
                "SELECT encoded_response, scope_version, scope_hash, operation_type, application_schema_version,
                    tenant_type, tenant_id
                FROM {$this->schema->table()} WHERE operation_id = :operation_id AND state = 'terminal'",
                ['operation_id' => $operationId->toString()],
            );
            if (!is_array($row) || $row['encoded_response'] === null) {
                return null;
            }
            $decoded = $this->projection(json_decode(
                $this->protection->decrypt(
                    PostgreSqlBytea::string($row['encoded_response']),
                    $this->context(
                        new IdempotencyScopeHash((int) $row['scope_version'], (string) $row['scope_hash']),
                        $operationId,
                        $row,
                        StoragePurpose::IdempotencyResponse,
                        $this->tenant($row),
                    ),
                ),
                associative: true,
                depth: 512,
                flags: JSON_THROW_ON_ERROR,
            ));
            $headers = $this->headers($decoded['headers'] ?? null);
            $this->projectionVersion($decoded);

            return new IdempotencyResponseSnapshot(
                1,
                (int) ($decoded['status'] ?? 0),
                $headers,
                is_string($decoded['body'] ?? null)
                    ? $decoded['body']
                    : throw new DeferredTransportException('PostgreSQL idempotency response projection is invalid.'),
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
        string $operationType,
        int $applicationSchemaVersion,
        ?TenantRef $tenant,
    ): array {
        return [
            'scope_version' => $scope->version(),
            'scope_hash' => $scope->digest(),
            'key_version' => $key->version(),
            'key_hash' => $key->digest(),
            'fingerprint_version' => $fingerprint->version(),
            'fingerprint_hash' => $fingerprint->digest(),
            'operation_id' => $operationId->toString(),
            'operation_type' => $operationType,
            'application_schema_version' => $applicationSchemaVersion,
            'strategy' => $strategy::class,
            'created_at' => $createdAt->format('Y-m-d H:i:s.uP'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s.uP'),
            'tenant_type' => $tenant?->type(),
            'tenant_id' => $tenant?->id(),
        ];
    }

    /** @param array<string, mixed> $row */
    /** @mago-expect lint:halstead */
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

        $operationId = OperationId::fromString($this->string($row, 'operation_id'));
        $tenant = $this->tenant($row);
        $snapshot = null;
        if ($row['encoded_response'] !== null) {
            $decoded = $this->decode($row, $operationId, StoragePurpose::IdempotencyResponse, $tenant);
            $this->projectionVersion($decoded);
            $snapshot = new IdempotencyResponseSnapshot(
                1,
                (int) ($decoded['status'] ?? 0),
                $this->headers($decoded['headers'] ?? null),
                is_string($decoded['body'] ?? null)
                    ? $decoded['body']
                    : throw new DeferredTransportException('PostgreSQL idempotency response projection is invalid.'),
            );
        }

        $result = null;
        if ($row['encoded_result'] !== null) {
            $decoded = $this->decode($row, $operationId, StoragePurpose::IdempotencyResult, $tenant);
            $this->projectionVersion($decoded);
            $result = match ($decoded['kind'] ?? null) {
                'completed' => new IdempotencyResultSnapshot(OperationResult::completed(
                    new PostgreSqlOutcomeCodec()->decode(
                        is_string($decoded['type'] ?? null)
                            ? $decoded['type']
                            : throw new DeferredTransportException(
                                'PostgreSQL idempotency result projection is invalid.',
                            ),
                        (int) ($decoded['schemaVersion'] ?? 0),
                        is_string($decoded['payload'] ?? null)
                            ? $decoded['payload']
                            : throw new DeferredTransportException(
                                'PostgreSQL idempotency result projection is invalid.',
                            ),
                    ),
                    $operationId,
                )),
                'internal_failure' => IdempotencyResultSnapshot::internalFailure($operationId),
                'rejected' => $this->rejectedSnapshot($decoded, $operationId),
                default => throw new DeferredTransportException('PostgreSQL idempotency result projection is invalid.'),
            };
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
    private function context(
        IdempotencyScopeHash $scope,
        OperationId $operationId,
        array $row,
        StoragePurpose $purpose,
        ?TenantRef $tenant,
    ): StorageProtectionContext {
        return new StorageProtectionContext(
            $purpose,
            $scope->version() . ':' . $scope->digest(),
            $operationId->toString(),
            $this->string($row, 'operation_type'),
            $this->integer($row, 'application_schema_version'),
            $tenant,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row, OperationId $operationId, StoragePurpose $purpose, ?TenantRef $tenant): array
    {
        $scope = new IdempotencyScopeHash($this->integer($row, 'scope_version'), $this->string($row, 'scope_hash'));
        $encoded = PostgreSqlBytea::string(
            $row[$purpose === StoragePurpose::IdempotencyResponse ? 'encoded_response' : 'encoded_result'] ?? null,
        );
        return $this->projection(json_decode(
            $this->protection->decrypt($encoded, $this->context($scope, $operationId, $row, $purpose, $tenant)),
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    /** @return array<string, mixed> */
    private function projection(mixed $value): array
    {
        if (!is_array($value)) {
            throw new DeferredTransportException('PostgreSQL idempotency projection is invalid.');
        }
        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                throw new DeferredTransportException('PostgreSQL idempotency projection is invalid.');
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function encodeResponse(?IdempotencyResponseSnapshot $snapshot, StorageProtectionContext $context): ?string
    {
        if ($snapshot === null) {
            return null;
        }

        return $this->protection->encrypt(json_encode([
            'version' => 1,
            'status' => $snapshot->status(),
            'headers' => $snapshot->headers(),
            'body' => $snapshot->body(),
        ], JSON_THROW_ON_ERROR), $context);
    }

    private function encodeResult(
        ?IdempotencyResultSnapshot $snapshot,
        OperationId $operationId,
        StorageProtectionContext $context,
    ): ?string {
        $value = $this->resultProjection($snapshot, $operationId);
        if ($value === null) {
            return null;
        }

        return $this->protection->encrypt(json_encode($value, JSON_THROW_ON_ERROR), $context);
    }

    /** @return array<string, mixed>|null */
    private function resultProjection(?IdempotencyResultSnapshot $snapshot, OperationId $operationId): ?array
    {
        if ($snapshot?->isInternalFailure() === true) {
            return ['version' => 1, 'kind' => 'internal_failure'];
        }

        $result = $snapshot?->result();
        if ($result?->isCompleted() === true) {
            $encoded = new PostgreSqlOutcomeCodec()->encode($result->outcome());
            return [
                'version' => 1,
                'kind' => 'completed',
                'type' => $encoded->type,
                'schemaVersion' => $encoded->schemaVersion,
                'payload' => $encoded->payload,
            ];
        }

        if ($result?->isRejected() === true) {
            return [
                'version' => 1,
                'kind' => 'rejected',
                'category' => $result->rejectionReason()->category()->value,
                'code' => $result->rejectionReason()->code(),
            ];
        }

        return null;
    }

    /** @param array<string, mixed> $value */
    private function rejectedSnapshot(array $value, OperationId $operationId): IdempotencyResultSnapshot
    {
        $code = $this->projectionString($value['code'] ?? null);
        $category = $this->projectionString($value['category'] ?? null);
        $reason = match (RejectionCategory::from($category)) {
            RejectionCategory::Validation => \BlackOps\Core\Rejection\RejectionReason::validation($code),
            RejectionCategory::Unauthorized => \BlackOps\Core\Rejection\RejectionReason::unauthorized($code),
            RejectionCategory::Forbidden => \BlackOps\Core\Rejection\RejectionReason::forbidden($code),
            RejectionCategory::NotFound => \BlackOps\Core\Rejection\RejectionReason::notFound($code),
            RejectionCategory::Conflict => \BlackOps\Core\Rejection\RejectionReason::conflict($code),
            RejectionCategory::BusinessRule => \BlackOps\Core\Rejection\RejectionReason::businessRule($code),
        };
        return new IdempotencyResultSnapshot(OperationResult::rejected($reason, $operationId));
    }

    /** @param array<string, mixed> $value */
    private function projectionVersion(array $value): void
    {
        if (($value['version'] ?? null) !== 1) {
            throw new DeferredTransportException('PostgreSQL idempotency projection version is invalid.');
        }
    }

    /** @param array<string, mixed> $row */
    private function tenant(array $row): ?TenantRef
    {
        $type = $this->tenantString($row['tenant_type'] ?? null);
        $id = $this->tenantString($row['tenant_id'] ?? null);
        if ($type === null && $id === null) {
            return null;
        }
        if ($type === null || $id === null) {
            throw new DeferredTransportException('PostgreSQL idempotency row contains a partial tenant subject.');
        }
        return new TenantRef($type, $id);
    }

    private function projectionString(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new DeferredTransportException('PostgreSQL idempotency rejection projection is invalid.');
        }
        return $value;
    }

    private function tenantString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new DeferredTransportException('PostgreSQL idempotency row contains a partial tenant subject.');
        }
        return $value;
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

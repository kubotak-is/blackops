<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\StorageProtection\StoragePurpose;
use DateTimeImmutable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class PostgreSqlDeferredOperationMessageCodec
{
    public function __construct(
        private BopdEnvelopeCodec $protection,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public function fromRow(array $row): DeferredOperationMessage
    {
        $tenant = $this->nullableTenant($row);
        $origin = $this->nullableOriginActor($row);

        $operationId = OperationId::fromString($this->string($row, 'operation_id'));
        $operationType = $this->string($row, 'operation_type');
        $schemaVersion = $this->integer($row, 'schema_version');
        $payload = $this->bytea($row, 'encoded_payload');
        $context = $this->bytea($row, 'encoded_context');
        $payload = $this->protection->decrypt(
            $payload,
            new StorageProtectionContext(
                StoragePurpose::DeferredPayload,
                $operationId->toString() . ':payload',
                $operationId->toString(),
                $operationType,
                $schemaVersion,
                $tenant,
            ),
        );
        $context = $this->protection->decrypt(
            $context,
            new StorageProtectionContext(
                StoragePurpose::DeferredContext,
                $operationId->toString() . ':context',
                $operationId->toString(),
                $operationType,
                $schemaVersion,
                $tenant,
            ),
        );

        return new DeferredOperationMessage(
            $operationId,
            $operationType,
            $schemaVersion,
            $payload,
            $context,
            new DateTimeImmutable($this->string($row, 'available_at')),
            $tenant,
            $origin,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key]) || $row[$key] === '') {
            throw new DeferredTransportException('Claimed operation row contains an invalid string field.');
        }

        return $row[$key];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function integer(array $row, string $key): int
    {
        if (!array_key_exists($key, $row) || !is_int($row[$key])) {
            throw new DeferredTransportException('Claimed operation row contains an invalid integer field.');
        }

        return $row[$key];
    }

    /** @param array<string, mixed> $row */
    private function bytea(array $row, string $key): string
    {
        try {
            return PostgreSqlBytea::string($row[$key] ?? null);
        } catch (\Throwable $exception) {
            throw new DeferredTransportException(
                'Claimed operation row contains an unreadable bytea field.',
                previous: $exception,
            );
        }
    }

    /** @param array<string, mixed> $row */
    private function nullableTenant(array $row): ?TenantRef
    {
        $type = $row['tenant_type'] ?? null;
        $id = $row['tenant_id'] ?? null;
        if ($type === null && $id === null) {
            return null;
        }
        if (!is_string($type) || $type === '' || !is_string($id) || $id === '') {
            throw new DeferredTransportException('Claimed operation row contains a partial tenant subject.');
        }
        return new TenantRef($type, $id);
    }

    /** @param array<string, mixed> $row */
    private function nullableOriginActor(array $row): ?ActorRef
    {
        $type = $row['origin_actor_type'] ?? null;
        $id = $row['origin_actor_id'] ?? null;
        if ($type === null && $id === null) {
            return null;
        }
        if (!is_string($type) || $type === '' || !is_string($id) || $id === '') {
            throw new DeferredTransportException('Claimed operation row contains a partial origin actor subject.');
        }
        return new ActorRef($id, $type);
    }
}

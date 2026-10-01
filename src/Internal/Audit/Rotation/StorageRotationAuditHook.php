<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Rotation;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationAuditCompletion;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationCounts;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationPhaseHook;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationRowRequest;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationTarget;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class StorageRotationAuditHook implements PostgreSqlStorageProtectionRotationPhaseHook
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(#[\SensitiveParameter] StorageRotationAuditWriter $writer)
    {
        $this->snapshot = new \SensitiveParameterValue(['writer' => $writer]);
    }

    public function assertEntry(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
    ): void {
        $this->writerForEntry($connection);
        try {
            $this->scope($scope);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function assertRow(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowRequest $request,
    ): void {
        $this->writerForRoot($connection);
        try {
            $this->row($request);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function started(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
    ): void {
        $writer = $this->writerForRoot($connection);
        try {
            $this->scope($scope);
            $this->auditId($auditId);
            $writer->started($auditId, $this->scopeHash($scope), $this->tenant($scope->tenant));
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function interrupted(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
    ): void {
        $writer = $this->writerForRoot($connection);
        try {
            $this->scope($scope);
            $this->auditId($auditId);
            $writer->failed($auditId, $this->scopeHash($scope), $this->tenant($scope->tenant));
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function progressed(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowRequest $request,
        #[\SensitiveParameter]
        string $cursor,
    ): void {
        $writer = $this->writerForRoot($connection);
        try {
            $row = $this->row($request);
            $this->cursor($cursor);
            if ($row['cursor'] !== $cursor) {
                throw new \LogicException();
            }
            $writer->progressed($row['auditId'], $row['scopeHash'], $cursor, $row['tenant']);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function finished(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        StorageProtectionRotationAuditCompletion $completion,
    ): void {
        $writer = $this->writerForRoot($connection);
        try {
            $scope = $completion->scope;
            $this->scope($scope);
            $auditId = $completion->auditId;
            if (!is_string($auditId)) {
                throw new \LogicException();
            }
            $this->auditId($auditId);
            $counts = $completion->counts;
            if (
                !$counts instanceof StorageProtectionRotationCounts
                || !is_int($counts->selected)
                || !is_int($counts->rotated)
                || !is_int($counts->skipped)
                || !is_int($counts->failed)
                || $counts->selected < 0
                || $counts->rotated < 0
                || $counts->skipped < 0
                || $counts->failed < 0
            ) {
                throw new \LogicException();
            }
            $state = $completion->state;
            if (!is_string($state) || !in_array($state, ['complete', 'failed', 'running'], strict: true)) {
                throw new \LogicException();
            }
            $fingerprint = $completion->failureFingerprint;
            if ($state === 'failed') {
                if (
                    $counts->failed < 1
                    || !is_string($fingerprint)
                    || preg_match('/\Av1:[a-f0-9]{64}\z/D', $fingerprint) !== 1
                ) {
                    throw new \LogicException();
                }
                $writer->failed($auditId, $this->scopeHash($scope), $this->tenant($scope->tenant));
                return;
            }
            if ($counts->failed !== 0 || $fingerprint !== null) {
                throw new \LogicException();
            }
            if ($state === 'complete') {
                $writer->succeeded($auditId, $this->scopeHash($scope), $this->tenant($scope->tenant));
            }
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
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
        throw self::failure(AuditDeliveryFailure::StorageUnavailable);
    }

    public function __clone(): void
    {
        throw self::failure(AuditDeliveryFailure::StorageUnavailable);
    }

    private function writerForEntry(#[\SensitiveParameter] Connection $connection): StorageRotationAuditWriter
    {
        $writer = $this->writer();
        try {
            $writer->assertConnection($connection);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }
        try {
            if (
                !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw new \LogicException();
            }
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }

        return $writer;
    }

    private function writerForRoot(#[\SensitiveParameter] Connection $connection): StorageRotationAuditWriter
    {
        $writer = $this->writerForEntryConnection($connection);
        try {
            if (
                !$connection->isAutoCommit()
                || !$connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 1
            ) {
                throw new \LogicException();
            }
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }

        return $writer;
    }

    private function writerForEntryConnection(#[\SensitiveParameter] Connection $connection): StorageRotationAuditWriter
    {
        $writer = $this->writer();
        try {
            $writer->assertConnection($connection);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }

        return $writer;
    }

    /** @return array{auditId:string,scopeHash:string,cursor:string,tenant:?TenantRef} */
    private function row(#[\SensitiveParameter] PostgreSqlStorageProtectionRotationRowRequest $request): array
    {
        if (!$request->mode->writes()) {
            throw new \LogicException();
        }
        $scope = $request->scope;
        $this->scope($scope);
        $auditId = $request->audit->auditId;
        if (!is_string($auditId)) {
            throw new \LogicException();
        }
        $this->auditId($auditId);
        $target = $request->storage->target;
        $this->target($scope, $target);
        $row = $request->row;
        if (!is_array($row)) {
            throw new \LogicException();
        }
        $required = [
            $target->identity,
            'operation_id',
            $target->operationType === '' ? 'operation_type' : $target->operationType,
            $target->schema === '' ? 'schema_version' : $target->schema,
            'tenant_type',
            'tenant_id',
            ...$target->columns,
        ];
        if ($target->hasCompositeIdentity()) {
            $required[] = 'scope_version';
            $required[] = 'scope_hash';
        }
        foreach (array_unique($required) as $key) {
            if (!array_key_exists($key, $row)) {
                throw new \LogicException();
            }
        }
        $tenant = $this->rowTenant($scope, $row);
        $cursor = $this->rowCursor($target, $row);

        return [
            'auditId' => $auditId,
            'scopeHash' => $this->scopeHash($scope),
            'cursor' => $cursor,
            'tenant' => $tenant,
        ];
    }

    private function target(
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationTarget $target,
    ): void {
        $expected = PostgreSqlStorageProtectionRotationTarget::forPurpose($scope->purpose);
        if (
            $target->table !== $expected->table
            || $target->columns !== $expected->columns
            || $target->identity !== $expected->identity
            || $target->operationType !== $expected->operationType
            || $target->schema !== $expected->schema
        ) {
            throw new \LogicException();
        }
    }

    /** @param array<string,mixed> $row */
    private function rowTenant(
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        #[\SensitiveParameter]
        array $row,
    ): ?TenantRef {
        if (!array_key_exists('tenant_type', $row) || !array_key_exists('tenant_id', $row)) {
            throw new \LogicException();
        }
        $type = $row['tenant_type'];
        $id = $row['tenant_id'];
        $tenant = null;
        if ($type !== null || $id !== null) {
            if (!is_string($type) || !is_string($id)) {
                throw new \LogicException();
            }
            $tenant = $this->tenantPair($type, $id);
        }
        $scopeTenant = $this->tenant($scope->tenant);
        if (
            $scopeTenant !== null
            && ($tenant === null || $tenant->type() !== $scopeTenant->type() || $tenant->id() !== $scopeTenant->id())
        ) {
            throw new \LogicException();
        }

        return $tenant;
    }

    /** @param array<string,mixed> $row */
    private function rowCursor(
        #[\SensitiveParameter]
        \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationTarget $target,
        #[\SensitiveParameter]
        array $row,
    ): string {
        if ($target->hasCompositeIdentity()) {
            $version = $row['scope_version'];
            if (!is_int($version) && !is_string($version)) {
                throw new \LogicException();
            }
            $versionText = (string) $version;
            if (preg_match('/\A[1-9][0-9]{0,9}\z/D', $versionText) !== 1 || (int) $versionText > 2_147_483_647) {
                throw new \LogicException();
            }
            $hash = $row['scope_hash'];
            if (!is_string($hash) || preg_match('/\A[a-f0-9]{64}\z/D', $hash) !== 1) {
                throw new \LogicException();
            }
            $cursor = $versionText . ':' . $hash;
            $this->cursor($cursor);

            return $cursor;
        }
        $value = $row[$target->identity];
        if (!is_string($value)) {
            throw new \LogicException();
        }
        $this->uuidCursor($value);

        return $value;
    }

    private function scope(#[\SensitiveParameter] StorageProtectionRotationScope $scope): void
    {
        $tenant = $this->tenant($scope->tenant);
        $rebuilt = new StorageProtectionRotationScope(
            $scope->purpose,
            $tenant,
            $scope->oldKeyId,
            $scope->newKeyId,
            $scope->batchSize,
            $scope->checkpoint,
            $scope->actor,
            $scope->reason,
            $scope->confirmed,
        );
        $hash = $scope->scopeHash();
        $this->scopeHashValue($hash);
        if ($rebuilt->scopeHash() !== $hash) {
            throw new \LogicException();
        }
    }

    private function scopeHash(#[\SensitiveParameter] StorageProtectionRotationScope $scope): string
    {
        $hash = $scope->scopeHash();
        $this->scopeHashValue($hash);

        return $hash;
    }

    private function scopeHashValue(#[\SensitiveParameter] string $scopeHash): void
    {
        AuditValidationException::assertSafeString($scopeHash, 64, 64);
        if (preg_match('/\A[a-f0-9]{64}\z/D', $scopeHash) !== 1) {
            throw new \LogicException();
        }
    }

    private function tenant(#[\SensitiveParameter] ?TenantRef $tenant): ?TenantRef
    {
        if ($tenant === null) {
            return null;
        }

        return $this->tenantPair($tenant->type(), $tenant->id());
    }

    private function tenantPair(#[\SensitiveParameter] string $type, #[\SensitiveParameter] string $id): TenantRef
    {
        if (trim($type) !== $type || trim($id) !== $id) {
            throw new \LogicException();
        }
        AuditValidationException::assertSafeString($type, 1, 256);
        AuditValidationException::assertSafeString($id, 1, 256);
        new AuditTypedReference($type, $id);

        return new TenantRef($type, $id);
    }

    private function auditId(#[\SensitiveParameter] string $auditId): void
    {
        AuditValidationException::assertSafeString($auditId, 32, 32);
        if (preg_match('/\A[a-f0-9]{32}\z/D', $auditId) !== 1) {
            throw new \LogicException();
        }
    }

    private function cursor(#[\SensitiveParameter] string $cursor): void
    {
        AuditValidationException::assertSafeString($cursor, 1, 128);
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $cursor) === 1) {
            $this->uuidCursor($cursor);

            return;
        }
        $this->compositeCursor($cursor);
    }

    private function uuidCursor(#[\SensitiveParameter] string $cursor): void
    {
        AuditValidationException::assertSafeString($cursor, 36, 36);
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $cursor) !== 1) {
            throw new \LogicException();
        }
        try {
            if (Uuid::fromString($cursor)->toRfc4122() !== $cursor) {
                throw new \LogicException();
            }
        } catch (Throwable) {
            throw new \LogicException();
        }
    }

    private function compositeCursor(#[\SensitiveParameter] string $cursor): void
    {
        $match = [];
        if (
            preg_match('/\A([1-9][0-9]{0,9}):([a-f0-9]{64})\z/D', $cursor, $match) !== 1
            && preg_match('/\A(2147483647):([a-f0-9]{64})\z/D', $cursor, $match) !== 1
        ) {
            throw new \LogicException();
        }
        if ((int) $match[1] > 2_147_483_647) {
            throw new \LogicException();
        }
    }

    private function writer(): StorageRotationAuditWriter
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['writer']
            || !$value['writer'] instanceof StorageRotationAuditWriter
        ) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }

        return $value['writer'];
    }

    private static function failure(AuditDeliveryFailure $failure): AuditDeliveryException
    {
        return new AuditDeliveryException($failure);
    }

    private static function remap(#[\SensitiveParameter] AuditDeliveryException $failure): AuditDeliveryException
    {
        try {
            return self::failure($failure->failure());
        } catch (Throwable) {
            return self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
    }
}

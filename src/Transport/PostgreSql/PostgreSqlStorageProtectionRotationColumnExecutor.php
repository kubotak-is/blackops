<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\StorageProtection\StorageProtectionException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class PostgreSqlStorageProtectionRotationColumnExecutor
{
    public function __construct(
        private Connection $connection,
        private BopdEnvelopeCodec $codec,
        private PostgreSqlStorageProtectionRotationQuery $query,
    ) {}

    public function execute(
        PostgreSqlStorageProtectionRotationRowRequest $request,
        string $column,
    ): PostgreSqlStorageProtectionRotationColumnResult {
        $raw = $this->raw($request, $column);
        if ($this->keyId($request, $raw) !== $request->scope->oldKeyId) {
            return new PostgreSqlStorageProtectionRotationColumnResult(0, 0, 1);
        }
        if (!$request->mode->writes()) {
            return new PostgreSqlStorageProtectionRotationColumnResult(1, 0, 0);
        }
        return $this->reencrypt($request, $column, $raw);
    }

    private function reencrypt(
        PostgreSqlStorageProtectionRotationRowRequest $request,
        string $column,
        string $raw,
    ): PostgreSqlStorageProtectionRotationColumnResult {
        try {
            $context = $this->query->context($request->scope, $request->storage->target, $request->row);
            $plain = $this->codec->decrypt($raw, $context);
            $newEnvelope = $this->codec->encrypt($plain, $context);
            if ($this->codec->keyId($newEnvelope) !== $request->scope->newKeyId) {
                throw StorageProtectionException::failure();
            }
            if ($this->update($request, $column, $raw, $newEnvelope) !== 1) {
                return new PostgreSqlStorageProtectionRotationColumnResult(1, 0, 1);
            }
        } catch (\Throwable) {
            return new PostgreSqlStorageProtectionRotationColumnResult(1, 0, 0, true);
        }
        return new PostgreSqlStorageProtectionRotationColumnResult(1, 1, 0);
    }

    private function raw(PostgreSqlStorageProtectionRotationRowRequest $request, string $column): string
    {
        if ($request->mode->writes()) {
            return PostgreSqlBytea::string($request->row[$column]);
        }
        return PostgreSqlBytea::string($request->row[$column . '_header']);
    }

    private function keyId(PostgreSqlStorageProtectionRotationRowRequest $request, string $raw): string
    {
        if ($request->mode->writes()) {
            return $this->codec->header($raw)['keyId'];
        }
        return $this->codec->keyIdFromHeader($raw);
    }

    private function update(
        PostgreSqlStorageProtectionRotationRowRequest $request,
        string $column,
        string $oldEnvelope,
        string $newEnvelope,
    ): int {
        $identity = $this->identity($request);
        return (int) $this->connection->executeStatement(
            "UPDATE {$request->storage->table} SET {$column} = ? WHERE {$identity['where']} AND tenant_type IS NOT DISTINCT FROM ? AND tenant_id IS NOT DISTINCT FROM ? AND {$column} = ?",
            [
                $newEnvelope,
                ...$identity['params'],
                $request->row['tenant_type'] ?? null,
                $request->row['tenant_id'] ?? null,
                $oldEnvelope,
            ],
            [
                ParameterType::BINARY,
                ...$identity['types'],
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::BINARY,
            ],
        );
    }

    /** @return array{where:string,params:list<mixed>,types:list<ParameterType>} */
    private function identity(PostgreSqlStorageProtectionRotationRowRequest $request): array
    {
        $target = $request->storage->target;
        if ($target->hasCompositeIdentity()) {
            return [
                'where' => 'scope_version = ? AND scope_hash = ?',
                'params' => [(int) $request->row['scope_version'], $request->row['scope_hash']],
                'types' => [ParameterType::STRING, ParameterType::STRING],
            ];
        }
        return [
            'where' => $target->identity . ' = ?',
            'params' => [$request->row[$target->identity]],
            'types' => [ParameterType::STRING],
        ];
    }
}

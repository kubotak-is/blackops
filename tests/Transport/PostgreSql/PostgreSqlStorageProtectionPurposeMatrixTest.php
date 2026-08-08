<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

final class PostgreSqlStorageProtectionPurposeMatrixTest extends TestCase
{
    private const string SCHEMA = 'blackops_p20_016g_matrix';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new DatabaseMigrationRunner($this->connection, self::SCHEMA)->migrate();
    }

    public function testEveryProtectedPurposeUsesARealPostgreSqlPlanBoundary(): void
    {
        $this->insertFixtures();
        $rotation = new PostgreSqlStorageProtectionRotation(
            $this->connection,
            new BopdEnvelopeCodec(new MatrixKeyProvider()),
            self::SCHEMA,
        );
        foreach (StoragePurpose::cases() as $purpose) {
            $scope = new StorageProtectionRotationScope(
                $purpose,
                null,
                'old:v1',
                'new:v1',
                1,
                'matrix-' . $purpose->value,
            );
            $before = $this->envelope($purpose);
            $result = $rotation->plan($scope);
            self::assertSame($before, $this->envelope($purpose));
            self::assertSame($purpose->value, $result->purpose);
            self::assertSame(1, $result->selected);
            self::assertSame(0, $result->rotated);
            self::assertSame(0, $result->failed);
            $rotated = $rotation->rotate(
                new StorageProtectionRotationScope(
                    $purpose,
                    null,
                    'old:v1',
                    'new:v1',
                    1,
                    'matrix-' . $purpose->value,
                    'matrix-test',
                    'fixture',
                    true,
                ),
            );
            self::assertSame(1, $rotated->rotated);
            self::assertSame('running', $rotated->state);
            $after = $this->envelope($purpose);
            self::assertNotSame($before, $after);
            self::assertSame('new:v1', new BopdEnvelopeCodec(new MatrixKeyProvider())->keyId($after));
            self::assertSame(
                $this->plain($purpose),
                new BopdEnvelopeCodec(new MatrixKeyProvider())->decrypt($after, $this->context($purpose)),
            );
            self::assertSame(0, $rotated->remainingByKey['old:v1'] ?? 0);
            self::assertSame(1, $rotated->remainingByKey['new:v1'] ?? 0);
            $beforeResume = $after;
            $resumed = $rotation->rotate(
                new StorageProtectionRotationScope(
                    $purpose,
                    null,
                    'old:v1',
                    'new:v1',
                    1,
                    'matrix-' . $purpose->value,
                    'matrix-test',
                    'resume',
                    true,
                ),
            );
            self::assertSame(0, $resumed->rotated);
            self::assertSame('complete', $resumed->state);
            self::assertSame($beforeResume, $this->envelope($purpose));
        }
    }

    private function envelope(StoragePurpose $purpose): string
    {
        [$table, $column] = match ($purpose) {
            StoragePurpose::JournalRecord => ['journal', 'encoded_record'],
            StoragePurpose::DeferredPayload => ['operations', 'encoded_payload'],
            StoragePurpose::DeferredContext => ['operations', 'encoded_context'],
            StoragePurpose::OutcomePayload => ['outcomes', 'encoded_payload'],
            StoragePurpose::OutboxPayload => ['outbox_records', 'encoded_payload'],
            StoragePurpose::OutboxContext => ['outbox_records', 'encoded_context'],
            StoragePurpose::DeadLetterReason => ['dead_letters', 'encoded_reason'],
            StoragePurpose::IdempotencyResponse => ['idempotency_records', 'encoded_response'],
            StoragePurpose::IdempotencyResult => ['idempotency_records', 'encoded_result'],
        };
        $identity =
            $purpose->value === 'idempotency_response' || $purpose->value === 'idempotency_result'
                ? 'scope_hash = :scope_hash'
                : 'operation_id = :operation_id';
        $params = str_contains($identity, 'scope_hash')
            ? ['scope_hash' => hash('sha256', 'matrix')]
            : ['operation_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9687990'];
        return PostgreSqlBytea::string($this->connection->fetchOne(
            'SELECT ' . $column . ' FROM ' . self::SCHEMA . '.' . $table . ' WHERE ' . $identity,
            $params,
        ));
    }

    private function context(StoragePurpose $purpose): StorageProtectionContext
    {
        $operation = '019f32ab-2be0-7b38-a0a7-1ab2f9687990';
        $record = '019f32ab-2be0-7b38-a0a7-1ab2f9687991';
        $identity = match ($purpose) {
            StoragePurpose::JournalRecord => $record,
            StoragePurpose::DeferredPayload => $operation . ':payload',
            StoragePurpose::DeferredContext => $operation . ':context',
            StoragePurpose::OutboxPayload, StoragePurpose::OutboxContext => $record,
            StoragePurpose::IdempotencyResponse, StoragePurpose::IdempotencyResult => '1:' . hash('sha256', 'matrix'),
            default => $operation,
        };
        $schema =
            $purpose === StoragePurpose::IdempotencyResponse || $purpose === StoragePurpose::IdempotencyResult ? 3 : 7;
        return new StorageProtectionContext($purpose, $identity, $operation, 'matrix.operation', $schema);
    }

    private function plain(StoragePurpose $purpose): string
    {
        return match ($purpose) {
            StoragePurpose::JournalRecord => 'journal',
            StoragePurpose::DeferredPayload => 'payload',
            StoragePurpose::DeferredContext => 'context',
            StoragePurpose::OutcomePayload => 'outcome',
            StoragePurpose::OutboxPayload => 'outbox-payload',
            StoragePurpose::OutboxContext => 'outbox-context',
            StoragePurpose::DeadLetterReason => 'reason',
            StoragePurpose::IdempotencyResponse => 'response',
            StoragePurpose::IdempotencyResult => 'result',
        };
    }

    private function insertFixtures(): void
    {
        $old = new BopdEnvelopeCodec(new MatrixOldKeyProvider());
        $tenant = null;
        $operation = '019f32ab-2be0-7b38-a0a7-1ab2f9687990';
        $record = '019f32ab-2be0-7b38-a0a7-1ab2f9687991';
        $operationType = 'matrix.operation';
        $journalContext = new StorageProtectionContext(
            StoragePurpose::JournalRecord,
            $record,
            $operation,
            $operationType,
            7,
            $tenant,
        );
        $payloadContext = new StorageProtectionContext(
            StoragePurpose::DeferredPayload,
            $operation . ':payload',
            $operation,
            $operationType,
            7,
            $tenant,
        );
        $contextContext = new StorageProtectionContext(
            StoragePurpose::DeferredContext,
            $operation . ':context',
            $operation,
            $operationType,
            7,
            $tenant,
        );
        $outcomeContext = new StorageProtectionContext(
            StoragePurpose::OutcomePayload,
            $operation,
            $operation,
            $operationType,
            7,
            $tenant,
        );
        $outboxPayloadContext = new StorageProtectionContext(
            StoragePurpose::OutboxPayload,
            $record,
            $operation,
            $operationType,
            7,
            $tenant,
        );
        $outboxContextContext = new StorageProtectionContext(
            StoragePurpose::OutboxContext,
            $record,
            $operation,
            $operationType,
            7,
            $tenant,
        );
        $deadContext = new StorageProtectionContext(
            StoragePurpose::DeadLetterReason,
            $operation,
            $operation,
            $operationType,
            7,
            $tenant,
        );
        $scopeHash = hash('sha256', 'matrix');
        $idempotencyResponseContext = new StorageProtectionContext(
            StoragePurpose::IdempotencyResponse,
            '1:' . $scopeHash,
            $operation,
            $operationType,
            3,
            $tenant,
        );
        $idempotencyResultContext = new StorageProtectionContext(
            StoragePurpose::IdempotencyResult,
            '1:' . $scopeHash,
            $operation,
            $operationType,
            3,
            $tenant,
        );
        $now = '2026-08-08 00:00:00+00';
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.operations (operation_id, operation_type, schema_version, encoded_payload, encoded_context, content_type, encoding, state, state_version, next_sequence, available_at, accepted_at, created_at, updated_at) VALUES (?, ?, 7, ?, ?, ?, ?, \'accepted\', 1, 1, ?, ?, ?, ?)',
            [
                $operation,
                $operationType,
                $old->encrypt('payload', $payloadContext),
                $old->encrypt('context', $contextContext),
                'application/json',
                'json',
                $now,
                $now,
                $now,
                $now,
            ],
            [
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
            ],
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.journal (record_id, operation_id, operation_type, sequence, event, schema_version, operation_schema_version, occurred_at, encoded_record) VALUES (?, ?, ?, 1, \'received\', 1, 7, ?, ?)',
            [$record, $operation, $operationType, $now, $old->encrypt('journal', $journalContext)],
            [
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::BINARY,
            ],
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.outcomes (operation_id, outcome_type, schema_version, encoded_payload, completed_at) VALUES (?, \'completed\', 99, ?, ?)',
            [$operation, $old->encrypt('outcome', $outcomeContext), $now],
            [ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING],
        );
        $this->connection->executeStatement(
            'INSERT INTO ' . self::SCHEMA . '.dead_letters (operation_id, encoded_reason, moved_at) VALUES (?, ?, ?)',
            [$operation, $old->encrypt('reason', $deadContext), $now],
            [ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING],
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.outbox_records (record_id, operation_id, operation_type, schema_version, encoded_payload, encoded_context, content_type, encoding, available_at, recorded_at, connection_name) VALUES (?, ?, ?, 7, ?, ?, ?, ?, ?, ?, ?)',
            [
                $record,
                $operation,
                $operationType,
                $old->encrypt('outbox-payload', $outboxPayloadContext),
                $old->encrypt('outbox-context', $outboxContextContext),
                'application/json',
                'json',
                $now,
                $now,
                'default',
            ],
            [
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
            ],
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.idempotency_records (scope_version, scope_hash, key_version, key_hash, fingerprint_version, fingerprint_hash, operation_id, strategy, state, created_at, expires_at, operation_type, application_schema_version, encoded_response, encoded_result) VALUES (1, ?, 1, ?, 1, ?, ?, \'sync\', \'terminal\', ?, ?, ?, 3, ?, ?)',
            [
                $scopeHash,
                hash('sha256', 'key'),
                hash('sha256', 'fingerprint'),
                $operation,
                $now,
                '2027-08-08 00:00:00+00',
                $operationType,
                $old->encrypt('response', $idempotencyResponseContext),
                $old->encrypt('result', $idempotencyResultContext),
            ],
            [
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::BINARY,
                ParameterType::BINARY,
            ],
        );
    }
}

final readonly class MatrixKeyProvider implements StorageKeyProvider
{
    public function activeKey(?\BlackOps\Core\TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('new:v1', str_repeat('n', 32));
    }

    public function key(string $keyId, ?\BlackOps\Core\TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat($keyId === 'old:v1' ? 'o' : 'n', 32));
    }
}

final readonly class MatrixOldKeyProvider implements StorageKeyProvider
{
    public function activeKey(?\BlackOps\Core\TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('old:v1', str_repeat('o', 32));
    }

    public function key(string $keyId, ?\BlackOps\Core\TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat($keyId === 'old:v1' ? 'o' : 'n', 32));
    }
}

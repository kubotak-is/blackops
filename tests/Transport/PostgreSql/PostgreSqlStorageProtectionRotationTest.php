<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\TenantRef;
use BlackOps\Internal\Console\StorageProtectionPlanCommand;
use BlackOps\Internal\Console\StorageProtectionRotateCommand;
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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PostgreSqlStorageProtectionRotationTest extends TestCase
{
    private const string SCHEMA = 'blackops_p20_016g_rotation';

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

    public function testPlanIsReadOnlyAndConfirmedRotationUsesCasAndPreservesAad(): void
    {
        $tenant = new TenantRef('org', 'tenant-a');
        $operation = '019f32ab-2be0-7b38-a0a7-1ab2f9687901';
        $record = '019f32ab-2be0-7b38-a0a7-1ab2f9687902';
        $context = new StorageProtectionContext(
            StoragePurpose::JournalRecord,
            $record,
            $operation,
            'fixture.operation',
            7,
            $tenant,
        );
        $oldCodec = new BopdEnvelopeCodec(new RotationTestKeyProvider('old:v1'));
        $envelope = $oldCodec->encrypt('payload-marker', $context);
        $this->connection->insert(
            self::SCHEMA . '.journal',
            [
                'record_id' => $record,
                'operation_id' => $operation,
                'operation_type' => 'fixture.operation',
                'sequence' => 1,
                'event' => 'received',
                'schema_version' => 1,
                'operation_schema_version' => 7,
                'occurred_at' => '2026-08-08 00:00:00+00',
                'encoded_record' => $envelope,
                'tenant_type' => 'org',
                'tenant_id' => 'tenant-a',
            ],
            ['encoded_record' => \Doctrine\DBAL\ParameterType::BINARY],
        );
        $before = PostgreSqlBytea::string($this->connection->fetchOne(
            'SELECT encoded_record FROM ' . self::SCHEMA . '.journal',
        ));
        $rotation = new PostgreSqlStorageProtectionRotation(
            $this->connection,
            new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1')),
            self::SCHEMA,
        );
        self::assertSame('old:v1', new BopdEnvelopeCodec(new RotationTestKeyProvider('old:v1'))->keyId($before));
        $scope = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            $tenant,
            'old:v1',
            'new:v1',
            10,
            'fixture-rotation',
            'actor',
            'reason',
            true,
        );
        $plan = $rotation->plan($scope);
        self::assertSame(['old:v1' => 1], $plan->remainingByKey);
        self::assertSame(0, $plan->failed);
        self::assertSame(0, $plan->skipped);
        self::assertSame(1, $plan->selected);
        self::assertSame(
            $before,
            PostgreSqlBytea::string($this->connection->fetchOne(
                'SELECT encoded_record FROM ' . self::SCHEMA . '.journal',
            )),
        );
        $result = $rotation->rotate($scope);
        self::assertSame(0, $result->failed, json_encode($result->json(), JSON_THROW_ON_ERROR));
        self::assertSame(1, $result->rotated);
        $after = PostgreSqlBytea::string($this->connection->fetchOne(
            'SELECT encoded_record FROM ' . self::SCHEMA . '.journal',
        ));
        self::assertSame('new:v1', new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1'))->keyId($after));
        self::assertSame('payload-marker', new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1'))->decrypt(
            $after,
            $context,
        ));
        self::assertSame('complete', $result->state);
        $resume = $rotation->rotate($scope);
        self::assertSame(0, $resume->rotated, 'A resumed completed checkpoint must not re-encrypt a row.');
        self::assertSame(0, $resume->selected);
        self::assertSame(
            2,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_audits WHERE checkpoint_id = \'fixture-rotation\'',
            ),
        );
    }

    public function testCliDefaultsToDryRunAndUsesStableExitCodesAndRedactedOutput(): void
    {
        $rotation = new PostgreSqlStorageProtectionRotation(
            $this->connection,
            new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1')),
            self::SCHEMA,
        );
        $missing = new CommandTester(new StorageProtectionRotateCommand($rotation));
        self::assertSame(2, $missing->execute([]));
        self::assertStringNotContainsString('payload', $missing->getDisplay());
        self::assertStringNotContainsString('tenant-a', $missing->getDisplay());
        $missingJson = new CommandTester(new StorageProtectionRotateCommand($rotation));
        self::assertSame(2, $missingJson->execute(['--json' => true]));
        self::assertSame(
            ['schemaVersion' => 1, 'status' => 'failed', 'code' => 'input_error'],
            json_decode($missingJson->getDisplay(), true, 512, JSON_THROW_ON_ERROR),
        );

        $dryRun = new CommandTester(new StorageProtectionRotateCommand($rotation));
        self::assertSame(0, $dryRun->execute([
            '--purpose' => StoragePurpose::JournalRecord->value,
            '--old-key-id' => 'old:v1',
            '--new-key-id' => 'new:v1',
            '--batch' => '1',
            '--checkpoint' => 'cli-dry-run',
            '--json' => true,
        ]));
        $json = json_decode($dryRun->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $json['schemaVersion']);
        self::assertArrayHasKey('selected', $json);
        self::assertStringNotContainsString('payload-marker', $dryRun->getDisplay());
    }

    public function testPlanAppliesBatchAfterOldKeyHeaderFilter(): void
    {
        $tenant = new TenantRef('org', 'tenant-a');
        $old = new BopdEnvelopeCodec(new RotationTestKeyProvider('old:v1'));
        $new = new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1'));
        foreach ([
            ['019f32ab-2be0-7b38-a0a7-1ab2f9687910', 'new-first',  $new],
            ['019f32ab-2be0-7b38-a0a7-1ab2f9687911', 'old-second', $old],
        ] as [$record, $payload, $codec]) {
            $operation = str_replace('7', '8', (string) $record);
            $context = new StorageProtectionContext(
                StoragePurpose::JournalRecord,
                $record,
                $operation,
                'fixture.operation',
                1,
                $tenant,
            );
            $this->connection->insert(
                self::SCHEMA . '.journal',
                [
                    'record_id' => $record,
                    'operation_id' => $operation,
                    'operation_type' => 'fixture.operation',
                    'sequence' => 1,
                    'event' => 'received',
                    'schema_version' => 1,
                    'operation_schema_version' => 1,
                    'occurred_at' => '2026-08-08 00:00:00+00',
                    'encoded_record' => $codec->encrypt($payload, $context),
                    'tenant_type' => 'org',
                    'tenant_id' => 'tenant-a',
                ],
                ['encoded_record' => \Doctrine\DBAL\ParameterType::BINARY],
            );
        }
        $rotation = new PostgreSqlStorageProtectionRotation($this->connection, $new, self::SCHEMA);
        $scope = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            $tenant,
            'old:v1',
            'new:v1',
            1,
            'header-filter',
        );
        $plan = $rotation->plan($scope);
        self::assertSame(1, $plan->selected);
        self::assertSame(0, $plan->failed);
        self::assertSame(['new:v1' => 1, 'old:v1' => 1], $plan->remainingByKey);
    }

    public function testConcurrentCasFixtureDoesNotOverwriteCommittedReplacement(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the two-process CAS fixture.');
        }
        $tenant = new TenantRef('org', 'tenant-a');
        $operation = '019f32ab-2be0-7b38-a0a7-1ab2f9687912';
        $record = '019f32ab-2be0-7b38-a0a7-1ab2f9687913';
        $context = new StorageProtectionContext(
            StoragePurpose::JournalRecord,
            $record,
            $operation,
            'fixture.operation',
            1,
            $tenant,
        );
        $old = new BopdEnvelopeCodec(new RotationTestKeyProvider('old:v1'));
        $first = $old->encrypt('first', $context);
        $replacement = new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1'))->encrypt('replacement', $context);
        $this->connection->insert(
            self::SCHEMA . '.journal',
            [
                'record_id' => $record,
                'operation_id' => $operation,
                'operation_type' => 'fixture.operation',
                'sequence' => 1,
                'event' => 'received',
                'schema_version' => 1,
                'operation_schema_version' => 1,
                'occurred_at' => '2026-08-08 00:00:00+00',
                'encoded_record' => $first,
                'tenant_type' => 'org',
                'tenant_id' => 'tenant-a',
            ],
            ['encoded_record' => \Doctrine\DBAL\ParameterType::BINARY],
        );
        $other = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $other->beginTransaction();
        $other->executeStatement(
            'UPDATE ' . self::SCHEMA . '.journal SET encoded_record = ? WHERE record_id = ?',
            [$replacement, $record],
            [\Doctrine\DBAL\ParameterType::BINARY, \Doctrine\DBAL\ParameterType::STRING],
        );
        $ready = tempnam(sys_get_temp_dir(), 'rotation-cas-ready-');
        $resultFile = tempnam(sys_get_temp_dir(), 'rotation-cas-result-');
        @unlink($ready);
        @unlink($resultFile);
        $this->connection->close();
        $pid = pcntl_fork();
        if ($pid === -1) {
            $other->rollBack();
            $other->close();
            self::fail('Unable to fork the CAS fixture process.');
        }
        if ($pid === 0) {
            $child = DriverManager::getConnection([
                'driver' => 'pdo_pgsql',
                'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
                'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
                'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
                'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
                'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
            ]);
            file_put_contents($ready, (string) $child->fetchOne('SELECT pg_backend_pid()'));
            $scope = new StorageProtectionRotationScope(
                StoragePurpose::JournalRecord,
                $tenant,
                'old:v1',
                'new:v1',
                1,
                'cas-process',
                'actor',
                'race',
                true,
            );
            $result = new PostgreSqlStorageProtectionRotation(
                $child,
                new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1')),
                self::SCHEMA,
            )->rotate($scope);
            file_put_contents($resultFile, json_encode($result->json(), JSON_THROW_ON_ERROR));
            exit(0);
        }
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $childFinished = false;
        try {
            $sawLockWait = false;
            for ($attempt = 0; $attempt < 200; $attempt++) {
                $backendPid = trim((string) @file_get_contents($ready));
                if ($backendPid !== '') {
                    $waitEvent = $this->connection->fetchOne('SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?', [
                        (int) $backendPid,
                    ]);
                    if ($waitEvent === 'Lock') {
                        $sawLockWait = true;
                        break;
                    }
                }
                usleep(10_000);
            }
            self::assertTrue(
                $sawLockWait,
                'The child rotation must reach the row-lock wait before releasing the competing update.',
            );
            $other->commit();
            pcntl_waitpid($pid, $status);
            $childFinished = true;
            self::assertTrue(is_file($resultFile));
            $result = json_decode((string) file_get_contents($resultFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(0, $result['rotated']);
            self::assertSame(1, $result['skipped']);
            self::assertSame(
                $replacement,
                PostgreSqlBytea::string($this->connection->fetchOne(
                    'SELECT encoded_record FROM ' . self::SCHEMA . '.journal',
                )),
            );
        } finally {
            if (!$childFinished) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
            }
            if ($other->isTransactionActive()) {
                $other->rollBack();
            }
            $other->close();
            @unlink($ready);
            @unlink($resultFile);
        }
    }

    public function testCrashAfterFirstRowCommitResumesDurablyWithoutReencryptingIt(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the crash fixture.');
        }
        $tenant = new TenantRef('org', 'tenant-a');
        $old = new BopdEnvelopeCodec(new RotationTestKeyProvider('old:v1'));
        $new = new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1'));
        $records = [
            '019f32ab-2be0-7b38-a0a7-1ab2f9687920',
            '019f32ab-2be0-7b38-a0a7-1ab2f9687921',
        ];
        $before = [];
        foreach ($records as $record) {
            $operation = str_replace('7', '8', $record);
            $context = new StorageProtectionContext(
                StoragePurpose::JournalRecord,
                $record,
                $operation,
                'fixture.operation',
                1,
                $tenant,
            );
            $before[$record] = $old->encrypt('crash-' . $record, $context);
            $this->connection->insert(
                self::SCHEMA . '.journal',
                [
                    'record_id' => $record,
                    'operation_id' => $operation,
                    'operation_type' => 'fixture.operation',
                    'sequence' => 1,
                    'event' => 'received',
                    'schema_version' => 1,
                    'operation_schema_version' => 1,
                    'occurred_at' => '2026-08-08 00:00:00+00',
                    'encoded_record' => $before[$record],
                    'tenant_type' => 'org',
                    'tenant_id' => 'tenant-a',
                ],
                ['encoded_record' => \Doctrine\DBAL\ParameterType::BINARY],
            );
        }
        $function = self::SCHEMA . '.rotation_sleep_second()';
        $this->connection->executeStatement(
            'CREATE FUNCTION '
            . $function
            . ' RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.record_id = '
            . $this->connection->quote($records[1])
            . ' THEN PERFORM pg_sleep(10); END IF; RETURN NEW; END $$',
        );
        $this->connection->executeStatement(
            'CREATE TRIGGER rotation_sleep_second BEFORE UPDATE OF encoded_record ON '
            . self::SCHEMA
            . '.journal FOR EACH ROW EXECUTE FUNCTION '
            . $function,
        );
        $scope = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            $tenant,
            'old:v1',
            'new:v1',
            2,
            'crash-resume',
            'actor',
            'crash',
            true,
        );
        $this->connection->close();
        $pid = pcntl_fork();
        if ($pid === -1) {
            self::fail('Unable to fork the crash fixture process.');
        }
        if ($pid === 0) {
            $child = DriverManager::getConnection([
                'driver' => 'pdo_pgsql',
                'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
                'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
                'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
                'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
                'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
            ]);
            new PostgreSqlStorageProtectionRotation($child, $new, self::SCHEMA)->rotate($scope);
            exit(0);
        }
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        try {
            $cursorSeen = false;
            for ($attempt = 0; $attempt < 300; $attempt++) {
                $cursor = $this->connection->fetchOne(
                    'SELECT cursor_value FROM '
                    . self::SCHEMA
                    . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = \'crash-resume\'',
                );
                if ($cursor === $records[0]) {
                    $cursorSeen = true;
                    break;
                }
                usleep(10_000);
            }
            self::assertTrue($cursorSeen, 'The first row checkpoint must commit before the crash.');
            posix_kill($pid, SIGKILL);
            pcntl_waitpid($pid, $status);
            $firstAfterCrash = PostgreSqlBytea::string($this->connection->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$records[0]]));
            $secondBeforeResume = PostgreSqlBytea::string($this->connection->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$records[1]]));
            $this->connection->executeStatement('DROP TRIGGER rotation_sleep_second ON ' . self::SCHEMA . '.journal');
            $this->connection->executeStatement('DROP FUNCTION ' . $function);
            $resumed = new PostgreSqlStorageProtectionRotation($this->connection, $new, self::SCHEMA)->rotate($scope);
            self::assertSame(1, $resumed->rotated);
            self::assertSame('complete', $resumed->state);
            $firstAfterResume = PostgreSqlBytea::string($this->connection->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$records[0]]));
            $secondAfterResume = PostgreSqlBytea::string($this->connection->fetchOne('SELECT encoded_record FROM '
            . self::SCHEMA
            . '.journal WHERE record_id = ?', [$records[1]]));
            self::assertSame($firstAfterCrash, $firstAfterResume);
            self::assertNotSame($secondBeforeResume, $secondAfterResume);
            $secondContext = new StorageProtectionContext(
                StoragePurpose::JournalRecord,
                $records[1],
                str_replace('7', '8', $records[1]),
                'fixture.operation',
                1,
                $tenant,
            );
            self::assertSame('crash-' . $records[1], $new->decrypt($secondAfterResume, $secondContext));
            self::assertSame(1, (int) $this->connection->fetchOne(
                'SELECT count(*) FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_audits WHERE checkpoint_id = \'crash-resume\' AND state = \'failed\' AND rotated_count = 1 AND failure_fingerprint ~ \'^v1:[0-9a-f]{64}$\'',
            ));
        } finally {
            if (isset($status) === false) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
            }
            $this->connection->executeStatement(
                'DROP TRIGGER IF EXISTS rotation_sleep_second ON ' . self::SCHEMA . '.journal',
            );
            $this->connection->executeStatement('DROP FUNCTION IF EXISTS ' . $function);
        }
    }

    public function testTamperedEnvelopeFailsWithFingerprintAndResumesAfterRepair(): void
    {
        $tenant = new TenantRef('org', 'tenant-a');
        $operation = '019f32ab-2be0-7b38-a0a7-1ab2f9687903';
        $record = '019f32ab-2be0-7b38-a0a7-1ab2f9687904';
        $context = new StorageProtectionContext(
            StoragePurpose::JournalRecord,
            $record,
            $operation,
            'fixture.operation',
            1,
            $tenant,
        );
        $old = new BopdEnvelopeCodec(new RotationTestKeyProvider('old:v1'));
        $valid = $old->encrypt('safe-payload', $context);
        $tampered = substr_replace($valid, (string) chr(ord($valid[strlen($valid) - 1]) ^ 1), -1, 1);
        $this->connection->insert(
            self::SCHEMA . '.journal',
            [
                'record_id' => $record,
                'operation_id' => $operation,
                'operation_type' => 'fixture.operation',
                'sequence' => 1,
                'event' => 'received',
                'schema_version' => 1,
                'operation_schema_version' => 1,
                'occurred_at' => '2026-08-08 00:00:00+00',
                'encoded_record' => $tampered,
                'tenant_type' => 'org',
                'tenant_id' => 'tenant-a',
            ],
            ['encoded_record' => \Doctrine\DBAL\ParameterType::BINARY],
        );
        $rotation = new PostgreSqlStorageProtectionRotation(
            $this->connection,
            new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1')),
            self::SCHEMA,
        );
        $scope = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            $tenant,
            'old:v1',
            'new:v1',
            1,
            'tamper-resume',
            'actor',
            'repair',
            true,
        );
        $failed = $rotation->rotate($scope);
        self::assertSame(1, $failed->failed);
        self::assertSame(1, $failed->selected);
        self::assertSame('failed', $failed->state);
        $fingerprint = (string) $this->connection->fetchOne(
            'SELECT failure_fingerprint FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = \'tamper-resume\' ORDER BY started_at DESC LIMIT 1',
        );
        self::assertMatchesRegularExpression('/^v1:[0-9a-f]{64}$/', $fingerprint);
        self::assertStringNotContainsString('safe-payload', $fingerprint);
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.journal SET encoded_record = :bytes WHERE record_id = :record',
            ['bytes' => $valid, 'record' => $record],
            ['bytes' => \Doctrine\DBAL\ParameterType::BINARY],
        );
        $repaired = $rotation->rotate($scope);
        self::assertSame(1, $repaired->rotated);
        self::assertSame('running', $repaired->state);
        self::assertSame('complete', $rotation->rotate($scope)->state);
    }

    public function testUnavailableOldAndWrongNewKeysFailSafelyWithExitOneEquivalent(): void
    {
        $tenant = new TenantRef('org', 'tenant-a');
        $operation = '019f32ab-2be0-7b38-a0a7-1ab2f9687907';
        $record = '019f32ab-2be0-7b38-a0a7-1ab2f9687908';
        $context = new StorageProtectionContext(
            StoragePurpose::JournalRecord,
            $record,
            $operation,
            'fixture.operation',
            1,
            $tenant,
        );
        $envelope = new BopdEnvelopeCodec(new RotationTestKeyProvider('old:v1'))->encrypt('key-payload', $context);
        $this->connection->insert(
            self::SCHEMA . '.journal',
            [
                'record_id' => $record,
                'operation_id' => $operation,
                'operation_type' => 'fixture.operation',
                'sequence' => 1,
                'event' => 'received',
                'schema_version' => 1,
                'operation_schema_version' => 1,
                'occurred_at' => '2026-08-08 00:00:00+00',
                'encoded_record' => $envelope,
                'tenant_type' => 'org',
                'tenant_id' => 'tenant-a',
            ],
            ['encoded_record' => \Doctrine\DBAL\ParameterType::BINARY],
        );
        $scope = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            $tenant,
            'old:v1',
            'new:v1',
            1,
            'key-fail',
            'actor',
            'key',
            true,
        );
        $failed = new PostgreSqlStorageProtectionRotation(
            $this->connection,
            new BopdEnvelopeCodec(new UnavailableOldKeyProvider()),
            self::SCHEMA,
        )->rotate($scope);
        self::assertSame(1, $failed->failed);
        self::assertSame(1, $failed->selected);
        self::assertSame('failed', $failed->state);
        self::assertMatchesRegularExpression(
            '/^v1:[0-9a-f]{64}$/',
            (string) $this->connection->fetchOne(
                'SELECT failure_fingerprint FROM '
                . self::SCHEMA
                . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = \'key-fail\'',
            ),
        );
        $wrongNewScope = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            $tenant,
            'old:v1',
            'new:v1',
            1,
            'wrong-new-key',
            'actor',
            'key',
            true,
        );
        $wrongNew = new PostgreSqlStorageProtectionRotation(
            $this->connection,
            new BopdEnvelopeCodec(new WrongNewKeyProvider()),
            self::SCHEMA,
        )->rotate($wrongNewScope);
        self::assertSame(1, $wrongNew->failed);
        self::assertSame('failed', $wrongNew->state);
    }

    public function testCheckpointOwnershipIsNonBlockingAndReacquirableAfterFailure(): void
    {
        $other = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $other->executeStatement('SELECT pg_advisory_lock(hashtextextended(?, 0))', [self::SCHEMA . ':lock-bound']);
        $rotation = new PostgreSqlStorageProtectionRotation(
            $this->connection,
            new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1')),
            self::SCHEMA,
        );
        $scope = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            null,
            'old:v1',
            'new:v1',
            1,
            'lock-bound',
            'actor',
            'lock',
            true,
        );
        try {
            $this->expectException(\RuntimeException::class);
            $rotation->rotate($scope);
        } finally {
            $other->executeStatement('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [
                self::SCHEMA . ':lock-bound',
            ]);
            $other->close();
        }
    }

    public function testCleanBoundedContinuationFinishesAuditWithoutFalseInterruption(): void
    {
        $tenant = new TenantRef('org', 'tenant-a');
        $old = new BopdEnvelopeCodec(new RotationTestKeyProvider('old:v1'));
        foreach ([
            '019f32ab-2be0-7b38-a0a7-1ab2f9687930',
            '019f32ab-2be0-7b38-a0a7-1ab2f9687931',
        ] as $index => $record) {
            $operation = str_replace('7', '8', $record);
            $context = new StorageProtectionContext(
                StoragePurpose::JournalRecord,
                $record,
                $operation,
                'fixture.operation',
                1,
                $tenant,
            );
            $this->connection->insert(
                self::SCHEMA . '.journal',
                [
                    'record_id' => $record,
                    'operation_id' => $operation,
                    'operation_type' => 'fixture.operation',
                    'sequence' => $index + 1,
                    'event' => 'received',
                    'schema_version' => 1,
                    'operation_schema_version' => 1,
                    'occurred_at' => '2026-08-08 00:00:00+00',
                    'encoded_record' => $old->encrypt('bounded-' . $index, $context),
                    'tenant_type' => 'org',
                    'tenant_id' => 'tenant-a',
                ],
                ['encoded_record' => \Doctrine\DBAL\ParameterType::BINARY],
            );
        }
        $scope = new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            $tenant,
            'old:v1',
            'new:v1',
            1,
            'clean-continuation',
            'actor',
            'bounded',
            true,
        );
        $rotation = new PostgreSqlStorageProtectionRotation(
            $this->connection,
            new BopdEnvelopeCodec(new RotationTestKeyProvider('new:v1')),
            self::SCHEMA,
        );
        self::assertSame('running', $rotation->rotate($scope)->state);
        self::assertSame('complete', $this->connection->fetchOne(
            'SELECT state FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = \'clean-continuation\' ORDER BY started_at DESC LIMIT 1',
        ));
        self::assertSame('running', $rotation->rotate($scope)->state);
        self::assertSame('complete', $this->connection->fetchOne(
            'SELECT state FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = \'clean-continuation\' ORDER BY started_at DESC LIMIT 1',
        ));
        self::assertSame('complete', $rotation->rotate($scope)->state);
        self::assertSame(3, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = \'clean-continuation\' AND state = \'complete\' AND finished_at IS NOT NULL',
        ));
        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT count(*) FROM '
            . self::SCHEMA
            . '.storage_protection_rotation_audits WHERE checkpoint_id = \'clean-continuation\' AND state = \'failed\'',
        ));
    }
}

final readonly class RotationTestKeyProvider implements StorageKeyProvider
{
    public function __construct(
        private string $active,
    ) {}

    public function activeKey(?\BlackOps\Core\TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($this->active, str_repeat($this->active === 'old:v1' ? 'o' : 'n', 32));
    }

    public function key(string $keyId, ?\BlackOps\Core\TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat($keyId === 'old:v1' ? 'o' : 'n', 32));
    }
}

final readonly class UnavailableOldKeyProvider implements StorageKeyProvider
{
    public function activeKey(?\BlackOps\Core\TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('new:v1', str_repeat('n', 32));
    }

    public function key(string $keyId, ?\BlackOps\Core\TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        throw new \RuntimeException('unavailable-key-material');
    }
}

final readonly class WrongNewKeyProvider implements StorageKeyProvider
{
    public function activeKey(?\BlackOps\Core\TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('unexpected:v1', str_repeat('n', 32));
    }

    public function key(string $keyId, ?\BlackOps\Core\TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat('o', 32));
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Retention;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeAuditPort;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Logging\MonologJsonlLoggerFactory;
use BlackOps\Internal\Retention\LoggingRetentionPurgeAuditPort;
use DateTimeImmutable;
use JsonException;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use UnexpectedValueException;

final class LoggingRetentionPurgeAuditPortTest extends TestCase
{
    public function testRecordsPrimaryBeforeOneStructuredSystemLog(): void
    {
        $calls = [];
        $primary = new RecordingRetentionAuditPort($calls);
        $logger = new RecordingRetentionAuditLogger($calls);
        $record = self::record();

        new LoggingRetentionPurgeAuditPort($primary, $logger)->record($record);

        self::assertSame(['primary', 'logger'], $calls);
        self::assertSame([$record], $primary->records);
        self::assertCount(1, $logger->records);
        self::assertSame('info', $logger->records[0]['level']);
        self::assertSame('Retention purge audit recorded.', $logger->records[0]['message']);
        $occurredAt = $logger->records[0]['context']['occurredAt'];
        unset($logger->records[0]['context']['occurredAt']);
        self::assertSame(
            [
                'schemaVersion' => 1,
                'kind' => 'audit',
                'event' => 'retention.purge.completed',
                'data' => [
                    'audit_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9689b01',
                    'operation_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9689b02',
                    'target' => 'journal',
                    'affected_count' => 2,
                    'policy' => 'production-retention-v1',
                    'purged_at' => '2026-07-12T03:04:05.123456Z',
                    'purged_by' => ['id' => '[masked]', 'type' => 'retention'],
                    'tenant' => null,
                ],
            ],
            $logger->records[0]['context'],
        );
        self::assertSame('2026-07-12T03:04:05.123456Z', $occurredAt->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testPrimaryFailureDoesNotCallLoggerAndPropagates(): void
    {
        $primary = new FailingRetentionAuditPort();
        $calls = [];
        $logger = new RecordingRetentionAuditLogger($calls);

        try {
            new LoggingRetentionPurgeAuditPort($primary, $logger)->record(self::record());
            self::fail('Expected primary audit failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('primary unavailable', $exception->getMessage());
        }

        self::assertSame([], $logger->records);
    }

    public function testLoggerFailurePropagatesAfterPrimarySuccess(): void
    {
        $calls = [];
        $primary = new RecordingRetentionAuditPort($calls);
        $failure = new UnexpectedValueException('logger unavailable');

        try {
            new LoggingRetentionPurgeAuditPort($primary, new FailingRetentionAuditLogger($failure))->record(
                self::record(),
            );
            self::fail('Expected system log failure.');
        } catch (UnexpectedValueException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertCount(1, $primary->records);
    }

    public function testAuditTenantIsMasked(): void
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        $calls = [];
        new LoggingRetentionPurgeAuditPort(
            new RecordingRetentionAuditPort($calls),
            new MonologJsonlLoggerFactory()->create($stream, 'retention-audit'),
        )->record(self::record(new TenantRef('account', 'tenant-secret-id')));

        rewind($stream);
        $jsonl = stream_get_contents($stream);
        self::assertIsString($jsonl);
        self::assertStringNotContainsString('tenant-secret-id', $jsonl);
        self::assertStringContainsString('"id":"[masked]"', $jsonl);
    }

    /** @throws JsonException */
    public function testWritesPayloadFreeOneLineJsonThroughMonologBackend(): void
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        $primaryCalls = [];
        $logger = new MonologJsonlLoggerFactory()->create($stream, 'retention-audit');
        new LoggingRetentionPurgeAuditPort(new RecordingRetentionAuditPort($primaryCalls), $logger)->record(
            self::record(),
        );

        rewind($stream);
        $jsonl = stream_get_contents($stream);
        self::assertIsString($jsonl);
        self::assertSame(1, substr_count($jsonl, "\n"));
        self::assertStringEndsWith("\n", $jsonl);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($jsonl, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $decoded['schemaVersion']);
        self::assertSame('audit', $decoded['kind']);
        self::assertSame('2026-07-12T03:04:05.123456Z', $decoded['occurredAt']);
        self::assertSame('retention.purge.completed', $decoded['event']);
        self::assertSame(
            [
                'audit_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9689b01',
                'operation_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9689b02',
                'target' => 'journal',
                'affected_count' => 2,
                'policy' => 'production-retention-v1',
                'purged_at' => '2026-07-12T03:04:05.123456Z',
                'purged_by' => ['id' => '[masked]', 'type' => 'retention'],
                'tenant' => null,
            ],
            $decoded['data'],
        );
        self::assertArrayNotHasKey('message', $decoded);
        self::assertArrayNotHasKey('channel', $decoded);
        self::assertArrayNotHasKey('level', $decoded);
        self::assertArrayNotHasKey('datetime', $decoded);
        self::assertArrayNotHasKey('level_name', $decoded);
        self::assertArrayNotHasKey('extra', $decoded);
        self::assertStringNotContainsString('system:retention', $jsonl);
        foreach (['payload', 'journal_record', 'outcome', 'error', 'credential'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $jsonl);
        }
    }

    private static function record(?TenantRef $tenant = null): RetentionPurgeAuditRecord
    {
        return new RetentionPurgeAuditRecord(
            RetentionPurgeAuditId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9689b01'),
            OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9689b02'),
            RetentionPurgeTarget::Journal,
            2,
            RetentionPolicyRef::fromString('production-retention-v1'),
            new DateTimeImmutable('2026-07-12T12:04:05.123456+09:00'),
            RetentionActorRef::fromString('system:retention'),
            $tenant,
        );
    }
}

final class RecordingRetentionAuditPort implements RetentionPurgeAuditPort
{
    /** @var list<RetentionPurgeAuditRecord> */
    public array $records = [];

    /** @param list<string> $calls */
    public function __construct(
        private array &$calls,
    ) {}

    public function record(RetentionPurgeAuditRecord $record): void
    {
        $this->calls[] = 'primary';
        $this->records[] = $record;
    }
}

final readonly class FailingRetentionAuditPort implements RetentionPurgeAuditPort
{
    public function record(RetentionPurgeAuditRecord $record): void
    {
        throw new RuntimeException('primary unavailable');
    }
}

final class RecordingRetentionAuditLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /** @param list<string> $calls */
    public function __construct(
        private array &$calls,
    ) {}

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->calls[] = 'logger';
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

final class FailingRetentionAuditLogger extends AbstractLogger
{
    public function __construct(
        private UnexpectedValueException $failure,
    ) {}

    public function log($level, string|Stringable $message, array $context = []): void
    {
        throw $this->failure;
    }
}

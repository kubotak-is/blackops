<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\OperationInspectJsonEncoder;
use PHPUnit\Framework\TestCase;

final class OperationInspectJsonEncoderTest extends TestCase
{
    public function testEncodesTheCompleteSafeAggregateAsOneVersionedObject(): void
    {
        $encoded = new OperationInspectJsonEncoder()->encode(OperationInspectFixture::diagnostics());

        self::assertStringEndsWith("\n", $encoded);
        self::assertSame(1, substr_count($encoded, "\n"));
        /** @var array<string, mixed> $document */
        $document = json_decode($encoded, associative: true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $document['schemaVersion']);
        self::assertSame('found', $document['status']);
        self::assertSame(OperationInspectFixture::OPERATION_ID, $document['operation']['operationId']);
        self::assertSame('[masked]', $document['operation']['actors']['origin']['id']);
        self::assertSame('completed', $document['state']['current']);
        self::assertSame('purged', $document['availability']['transportPayload']);
        self::assertSame('2026-07-18T12:00:00.123456Z', $document['timeline'][0]['occurredAt']);
        self::assertSame([], $document['timeline'][1]['data']);
        self::assertSame([2], $document['attempts'][0]['events']);
        self::assertSame('report.generated', $document['outcome']['type']);
        self::assertStringNotContainsString('console-reports@example.com', $encoded);
    }

    public function testControlCharactersRemainValidJsonEscapes(): void
    {
        $encoded = new OperationInspectJsonEncoder()->encode(
            OperationInspectFixture::diagnosticsWithControlCharacters(),
        );
        /** @var array<string, mixed> $document */
        $document = json_decode($encoded, associative: true, flags: JSON_THROW_ON_ERROR);

        self::assertSame("report\nState\n  Current: forged\x1b[31m", $document['operation']['type']);
        self::assertSame("user\nTimeline\x1b[2J\x7f\u{0085}", $document['operation']['actors']['origin']['type']);
        self::assertSame("report.generated\nOperation\x1b[0m", $document['outcome']['type']);
        self::assertStringContainsString('\\nState', $encoded);
        self::assertStringContainsString('\\u001b', $encoded);
        self::assertStringNotContainsString("\x1b", $encoded);
    }
}

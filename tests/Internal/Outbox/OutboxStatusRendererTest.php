<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Outbox;

use BlackOps\Core\Outbox\OutboxStatusSnapshot;
use BlackOps\Internal\Outbox\OutboxStatusRenderer;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OutboxStatusRendererTest extends TestCase
{
    public function testHumanWireIsExactAndAlwaysEndsWithOneFinalLf(): void
    {
        $rendered = new OutboxStatusRenderer()->render($this->snapshot());

        self::assertSame(
            "schemaVersion=1\n"
            . "scope=installation\n"
            . "observedAt=2026-08-27T15:00:00.000000Z\n"
            . "counts.deadLettered=0\n"
            . "counts.leasedActive=0\n"
            . "counts.leasedExpired=0\n"
            . "counts.pendingDelayed=0\n"
            . "counts.pendingReady=1\n"
            . "counts.retryDelayed=0\n"
            . "counts.retryReady=0\n"
            . "agesSeconds.oldestDeadLettered=null\n"
            . "agesSeconds.oldestLeasedExpired=null\n"
            . "agesSeconds.oldestReady=0\n",
            $rendered,
        );
        self::assertSame(13, substr_count($rendered, "\n"));
        self::assertStringNotContainsString("\r", $rendered);
        self::assertStringNotContainsString("\x1B", $rendered);
    }

    public function testJsonWireUsesTheSharedCanonicalCodecAndOneFinalLf(): void
    {
        $renderer = new OutboxStatusRenderer();
        $expected =
            '{"agesSeconds":{"oldestDeadLettered":null,"oldestLeasedExpired":null,"oldestReady":0},'
            . '"counts":{"deadLettered":0,"leasedActive":0,"leasedExpired":0,"pendingDelayed":0,'
            . '"pendingReady":1,"retryDelayed":0,"retryReady":0},'
            . '"observedAt":"2026-08-27T15:00:00.000000Z","schemaVersion":1,"scope":"installation"}'
            . "\n";

        self::assertSame($expected, $renderer->render($this->snapshot(), 'json'));
        self::assertSame($expected, $renderer->render($this->snapshot(), json: true));
        self::assertSame($expected, $renderer->render($this->snapshot(), true));
        self::assertSame(1, substr_count($expected, "\n"));
        self::assertStringNotContainsString("\r", $expected);
        self::assertStringNotContainsString("\x1B", $expected);
    }

    public function testUnsupportedFormatFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OutboxStatusRenderer()->render($this->snapshot(), 'yaml');
    }

    private function snapshot(): OutboxStatusSnapshot
    {
        return new OutboxStatusSnapshot(
            observedAt: new DateTimeImmutable('2026-08-27T15:00:00.000000Z'),
            pendingReady: 1,
            pendingDelayed: 0,
            retryReady: 0,
            retryDelayed: 0,
            leasedActive: 0,
            leasedExpired: 0,
            deadLettered: 0,
            oldestReady: 0,
            oldestLeasedExpired: null,
            oldestDeadLettered: null,
        );
    }
}

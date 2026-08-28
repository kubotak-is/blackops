<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Queue;

use BlackOps\Core\Queue\QueueStatusSnapshot;
use BlackOps\Internal\Queue\QueueStatusRenderer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class QueueStatusRendererTest extends TestCase
{
    public function testHumanWireIsFixedAndEndsWithOneLf(): void
    {
        $bytes = new QueueStatusRenderer()->render($this->snapshot());

        self::assertSame(
            "schemaVersion=1\nscope=installation\nobservedAt=2026-08-27T15:00:00.000000Z\n"
            . "counts.acceptedDelayed=0\ncounts.acceptedReady=1\ncounts.deadLettered=0\n"
            . "counts.retryDelayed=0\ncounts.retryReady=0\ncounts.runningActive=0\n"
            . "counts.runningExpired=0\ncounts.supervising=0\nagesSeconds.oldestDeadLettered=null\n"
            . "agesSeconds.oldestReady=0\nagesSeconds.oldestRunningExpired=null\n"
            . "agesSeconds.oldestSupervising=null\n",
            $bytes,
        );
        self::assertSame(15, substr_count($bytes, "\n"));
        self::assertStringNotContainsString("\x1B", $bytes);
    }

    public function testJsonWireUsesTheSharedCanonicalCodecAndOneLf(): void
    {
        $bytes = new QueueStatusRenderer()->render($this->snapshot(), format: 'json');

        self::assertSame(
            '{"agesSeconds":{"oldestDeadLettered":null,"oldestReady":0,"oldestRunningExpired":null,"oldestSupervising":null},'
            . '"counts":{"acceptedDelayed":0,"acceptedReady":1,"deadLettered":0,"retryDelayed":0,"retryReady":0,"runningActive":0,"runningExpired":0,"supervising":0},'
            . '"observedAt":"2026-08-27T15:00:00.000000Z","schemaVersion":1,"scope":"installation"}'
            . "\n",
            $bytes,
        );
        self::assertSame(1, substr_count($bytes, "\n"));
        self::assertStringNotContainsString("\x1B", $bytes);
    }

    public function testBooleanAndNamedJsonFormsSelectTheSameWire(): void
    {
        $renderer = new QueueStatusRenderer();

        self::assertSame(
            $renderer->render($this->snapshot(), format: 'json'),
            $renderer->render($this->snapshot(), format: true),
        );
        self::assertSame(
            $renderer->render($this->snapshot(), format: 'txt'),
            $renderer->render($this->snapshot(), json: false),
        );
    }

    private function snapshot(): QueueStatusSnapshot
    {
        return new QueueStatusSnapshot(
            observedAt: new DateTimeImmutable('2026-08-27T15:00:00.000000Z'),
            acceptedReady: 1,
            acceptedDelayed: 0,
            retryReady: 0,
            retryDelayed: 0,
            runningActive: 0,
            runningExpired: 0,
            supervising: 0,
            deadLettered: 0,
            oldestReady: 0,
            oldestRunningExpired: null,
            oldestSupervising: null,
            oldestDeadLettered: null,
        );
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Schedule;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use BlackOps\Internal\Projection\Schedule\ScheduleProjectionRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class ScheduleProjectionRendererTest extends TestCase
{
    public function testHumanOutputUsesExactHeadersSortedRowsAndOneFinalLineFeed(): void
    {
        $bytes = new ScheduleProjectionRenderer()->render($this->records());

        self::assertStringContainsString('| Name', $bytes);
        self::assertStringContainsString('Operation Type', $bytes);
        self::assertStringContainsString('Timezone', $bytes);
        self::assertStringContainsString('Strategy', $bytes);
        self::assertStringContainsString('alpha.run', $bytes);
        self::assertStringContainsString('zeta.run', $bytes);
        self::assertLessThan(strpos($bytes, 'zeta.run'), strpos($bytes, 'alpha.run'));
        self::assertStringEndsWith("\n", $bytes);
        self::assertFalse(str_ends_with($bytes, "\n\n"));
        self::assertStringNotContainsString("\r", $bytes);
        self::assertStringNotContainsString("\x1B", $bytes);
    }

    public function testHumanOutputIsIndependentOfOutputDecoration(): void
    {
        $renderer = new ScheduleProjectionRenderer();
        $plain = $renderer->render($this->records());
        $decorated = $renderer->render($this->records());

        self::assertSame($plain, $decorated);
        self::assertStringNotContainsString("\x1B", $plain);

        $output = new BufferedOutput();
        self::assertFalse($output->isDecorated());
    }

    public function testJsonOutputIsClosedJcsShapeWithoutStatusAndWithOneFinalLineFeed(): void
    {
        $bytes = new ScheduleProjectionRenderer()->render($this->records(), json: true);

        self::assertSame(
            [
                'schedules' => [
                    [
                        'cron' => '0 1 * * *',
                        'name' => 'alpha.run',
                        'operationType' => 'alpha.operation',
                        'strategy' => 'inline',
                        'timezone' => 'Asia/Tokyo',
                    ],
                    [
                        'cron' => '*/5 * * * *',
                        'name' => 'zeta.run',
                        'operationType' => 'zeta.operation',
                        'strategy' => 'custom:Vendor\\Queue\\Strategy',
                        'timezone' => 'UTC',
                    ],
                ],
                'schemaVersion' => 1,
            ],
            json_decode($bytes, true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertStringEndsWith("\n", $bytes);
        self::assertFalse(str_ends_with($bytes, "\n\n"));
        self::assertStringNotContainsString('applicationProjectionStatus', $bytes);
        self::assertStringNotContainsString("\r", $bytes);
        self::assertStringNotContainsString("\xEF\xBB\xBF", $bytes);
    }

    public function testBooleanAndJsonArgumentsSelectTheSameJsonWire(): void
    {
        $renderer = new ScheduleProjectionRenderer();

        self::assertSame($renderer->render($this->records(), 'json'), $renderer->render($this->records(), json: true));
        self::assertSame($renderer->render($this->records(), 'txt'), $renderer->render($this->records(), json: false));
    }

    public function testMalformedRowsAndFormatsFailClosed(): void
    {
        try {
            new ScheduleProjectionRenderer()->render([[
                'name' => 'fixture.run',
                'operationType' => 'fixture.run',
                'cron' => '* * * * *',
                'timezone' => 'UTC',
                'strategy' => 'inline',
                'unexpected' => true,
            ]]);
            self::fail('Expected closed row validation.');
        } catch (ProjectionBuildSetValidationException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        new ScheduleProjectionRenderer()->render($this->records(), 'xml');
    }

    /** @return list<array<string, string>> */
    private function records(): array
    {
        return [
            [
                'name' => 'zeta.run',
                'operationType' => 'zeta.operation',
                'cron' => '*/5 * * * *',
                'timezone' => 'UTC',
                'strategy' => 'custom:Vendor\\Queue\\Strategy',
            ],
            [
                'name' => 'alpha.run',
                'operationType' => 'alpha.operation',
                'cron' => '0 1 * * *',
                'timezone' => 'Asia/Tokyo',
                'strategy' => 'inline',
            ],
        ];
    }
}

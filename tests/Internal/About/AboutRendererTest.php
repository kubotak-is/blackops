<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\About;

use BlackOps\Internal\About\AboutMetadata;
use BlackOps\Internal\About\AboutRenderer;
use BlackOps\Internal\About\AboutReport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AboutRendererTest extends TestCase
{
    public function testHumanWireUsesTheFixedOrderAndOneLf(): void
    {
        $bytes = new AboutRenderer()->render($this->report());

        self::assertSame(
            "schemaVersion=1\n"
            . "package.name=blackops/framework\n"
            . "package.version=1.2.0@dev\n"
            . "runtime.scope=current_process\n"
            . "runtime.phpVersion=8.5.0\n"
            . "runtime.sapi=cli\n"
            . "projectionBuildSet=available\n",
            $bytes,
        );
        self::assertSame(7, substr_count($bytes, "\n"));
        self::assertStringNotContainsString("\x1B", $bytes);
        self::assertStringNotContainsString("\r", $bytes);
    }

    public function testJsonWireUsesTheSharedCanonicalCodecAndOneLf(): void
    {
        $bytes = new AboutRenderer()->render($this->report(), format: 'json');

        self::assertSame(
            '{"package":{"name":"blackops/framework","version":"1.2.0@dev"},'
            . '"projectionBuildSet":"available",'
            . '"runtime":{"phpVersion":"8.5.0","sapi":"cli","scope":"current_process"},'
            . '"schemaVersion":1}'
            . "\n",
            $bytes,
        );
        self::assertSame(1, substr_count($bytes, "\n"));
        self::assertStringNotContainsString("\x1B", $bytes);
    }

    public function testBooleanAndNamedJsonFormsSelectTheSameWire(): void
    {
        $renderer = new AboutRenderer();

        self::assertSame(
            $renderer->render($this->report(), format: 'json'),
            $renderer->render($this->report(), format: true),
        );
        self::assertSame(
            $renderer->render($this->report(), format: 'txt'),
            $renderer->render($this->report(), json: false),
        );
    }

    public function testUnsupportedFormatIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AboutRenderer()->render($this->report(), format: 'yaml');
    }

    private function report(): AboutReport
    {
        return new AboutReport(new AboutMetadata('1.2.0@dev', '8.5.0', 'cli'), 'available');
    }
}

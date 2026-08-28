<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Command;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use BlackOps\Internal\Projection\Command\CommandProjectionMarkdownCellEncoder;
use BlackOps\Internal\Projection\Command\CommandProjectionRenderer;
use PHPUnit\Framework\TestCase;

final class CommandProjectionRendererTest extends TestCase
{
    public function testRendersExactXmlGoldenWithStatusAndHiddenRows(): void
    {
        $bytes = new CommandProjectionRenderer()->render(
            $this->xmlRecords(),
            ProjectionBuildSetRecoveryStatus::AVAILABLE,
            'xml',
            includeHidden: true,
        );

        self::assertSame(
            '<blackopsCommandProjection version="1" status="available"><commands><command><name>about</name><aliases/><description>A &amp; &lt;B&gt; &quot;C&quot; &apos;D&apos;</description><origin>framework</origin><hidden>false</hidden></command><command><name>ops:status</name><aliases><alias>os</alias></aliases><description>Quoted &quot;x&quot; &amp; &lt;y&gt;</description><origin>application</origin><hidden>true</hidden></command></commands></blackopsCommandProjection>'
            . "\n",
            $bytes,
        );
        self::assertSame('579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968', hash('sha256', $bytes));
    }

    public function testRendersExactMarkdownGoldenWithHiddenRows(): void
    {
        $bytes = new CommandProjectionRenderer()->render(
            $this->markdownRecords(),
            ProjectionBuildSetRecoveryStatus::AVAILABLE,
            'md',
            includeHidden: true,
        );

        self::assertSame(
            "<!-- blackops-command-projection-v1 status=available -->\n"
            . "| Name | Aliases | Description | Origin | Hidden |\n"
            . "| --- | --- | --- | --- | --- |\n"
            . "| about |  | A \\| B \\\\ C | framework | false |\n"
            . "| ops:status | os | Quoted \"x\" & <y> | application | true |\n",
            $bytes,
        );
        self::assertSame('7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa', hash('sha256', $bytes));
    }

    public function testFormatsShareSortedVisibleRowsAndStatus(): void
    {
        $renderer = new CommandProjectionRenderer();
        $formats = ['txt', 'raw', 'json', 'xml', 'md'];
        foreach ($formats as $format) {
            $bytes = $renderer->render($this->records(), ProjectionBuildSetRecoveryStatus::STALE, $format);
            self::assertStringContainsString('about', $bytes, $format);
            self::assertStringNotContainsString('ops:status', $bytes, $format);
            if ($format !== 'txt' && $format !== 'raw') {
                self::assertStringContainsString('stale', $bytes, $format);
            }
            self::assertStringNotContainsString("\r", $bytes, $format);
            self::assertStringNotContainsString("\xEF\xBB\xBF", $bytes, $format);
            self::assertStringNotContainsString("\x1B", $bytes, $format);
        }
    }

    public function testRawEmptyAliasesAndJsonShortShapeAreClosed(): void
    {
        $renderer = new CommandProjectionRenderer();
        self::assertSame("about\t\n", $renderer->render(
            $this->records(),
            ProjectionBuildSetRecoveryStatus::ABSENT,
            'raw',
        ));

        $json = $renderer->render($this->records(), ProjectionBuildSetRecoveryStatus::ABSENT, 'json');
        self::assertSame(
            [
                'applicationProjectionStatus' => 'absent',
                'commands' => [[
                    'aliases' => [],
                    'description' => 'A | B \\ C',
                    'name' => 'about',
                    'origin' => 'framework',
                ]],
                'schemaVersion' => 1,
            ],
            json_decode($json, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testMalformedRowsAndFormatsFailClosed(): void
    {
        $this->expectExceptionMessage('command description');
        new CommandProjectionRenderer()->render([[
            'name' => 'bad',
            'aliases' => [],
            'description' => "bad\nrow",
            'origin' => 'application',
            'hidden' => false,
        ]], ProjectionBuildSetRecoveryStatus::AVAILABLE);
    }

    public function testUnsupportedFormatFailsClosed(): void
    {
        $this->expectExceptionMessage('format is unsupported');
        new CommandProjectionRenderer()->render($this->records(), ProjectionBuildSetRecoveryStatus::AVAILABLE, 'yaml');
    }

    public function testMarkdownCellEncoderEscapesBoundariesWithoutRescanning(): void
    {
        self::assertSame(
            'A\nB\nC\nD' . '\\\\' . '\\|' . 'E',
            CommandProjectionMarkdownCellEncoder::encode("A\r\nB\rC\nD\\|E"),
        );
    }

    /** @return list<array<string, mixed>> */
    private function records(): array
    {
        return [
            [
                'name' => 'ops:status',
                'aliases' => ['os'],
                'description' => 'Quoted "x" & <y>',
                'origin' => 'application',
                'hidden' => true,
            ],
            [
                'name' => 'about',
                'aliases' => [],
                'description' => 'A | B \\ C',
                'origin' => 'framework',
                'hidden' => false,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function xmlRecords(): array
    {
        return [
            [
                'name' => 'ops:status',
                'aliases' => ['os'],
                'description' => 'Quoted "x" & <y>',
                'origin' => 'application',
                'hidden' => true,
            ],
            [
                'name' => 'about',
                'aliases' => [],
                'description' => 'A & <B> "C" \'D\'',
                'origin' => 'framework',
                'hidden' => false,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function markdownRecords(): array
    {
        return $this->records();
    }
}

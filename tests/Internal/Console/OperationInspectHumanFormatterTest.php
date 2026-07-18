<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\OperationInspectHumanFormatter;
use PHPUnit\Framework\TestCase;

final class OperationInspectHumanFormatterTest extends TestCase
{
    public function testFormatsEverySectionInTheCanonicalOrder(): void
    {
        $formatted = new OperationInspectHumanFormatter()->format(OperationInspectFixture::diagnostics());

        $offset = 0;
        foreach (['Operation', 'State', 'Availability', 'Actors', 'Timeline', 'Attempts', 'Outcome'] as $heading) {
            $position = strpos($formatted, "\n{$heading}\n", $offset);
            if ($heading === 'Operation') {
                $position = strpos($formatted, "{$heading}\n", $offset);
            }
            self::assertIsInt($position, sprintf('Missing or unordered heading %s.', $heading));
            $offset = $position + strlen($heading);
        }

        self::assertStringContainsString('ID: ' . OperationInspectFixture::OPERATION_ID, $formatted);
        self::assertStringContainsString('Authority Source: transport', $formatted);
        self::assertStringContainsString('Origin: [masked] (user)', $formatted);
        self::assertStringContainsString('#2 2026-07-18T12:00:01.123456Z attempt.started', $formatted);
        self::assertStringContainsString('#1 ' . OperationInspectFixture::ATTEMPT_ID, $formatted);
        self::assertStringContainsString('Type: report.generated', $formatted);
        self::assertStringNotContainsString('console-reports@example.com', $formatted);
        self::assertStringEndsWith("\n", $formatted);
    }

    public function testEscapesControlCharactersAndMisleadingTerminalLinesInScalars(): void
    {
        $formatted = new OperationInspectHumanFormatter()->format(
            OperationInspectFixture::diagnosticsWithControlCharacters(),
        );

        self::assertStringContainsString('Type: report\\nState\\n  Current: forged\\u001b[31m', $formatted);
        self::assertStringContainsString('Strategy: deferred\\r\\nOutcome', $formatted);
        self::assertStringContainsString('Causation ID: cause\\t\\"quoted\\"\\\\path', $formatted);
        self::assertStringContainsString('Origin: [masked] (user\\nTimeline\\u001b[2J\\u007f\\u0085)', $formatted);
        self::assertStringContainsString('Authority Source: transport\\nActors', $formatted);
        self::assertStringContainsString('Type: report.generated\\nOperation\\u001b[0m', $formatted);
        self::assertStringContainsString('Source: outcome_store\\r\\nState', $formatted);
        self::assertStringNotContainsString("\x1b", $formatted);
        self::assertStringNotContainsString("\x7f", $formatted);
        self::assertStringNotContainsString("\u{0085}", $formatted);
        self::assertStringNotContainsString("\r", $formatted);
        self::assertSame(1, substr_count($formatted, "\nState\n"));
        self::assertSame(1, substr_count($formatted, "\nActors\n"));
        self::assertSame(1, substr_count($formatted, "\nTimeline\n"));
        self::assertSame(1, substr_count($formatted, "\nOutcome\n"));
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Telemetry;

use BlackOps\Telemetry\TelemetryContext;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TelemetryContextTest extends TestCase
{
    public function testValidContextExposesSafeProjection(): void
    {
        $context = new TelemetryContext('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01', 'vendor=value');
        self::assertSame('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01', $context->traceparent());
        self::assertSame('vendor=value', $context->tracestate());
    }

    #[DataProvider('invalidParents')]
    public function testInvalidParentIsRejected(string $parent): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TelemetryContext($parent);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidParents(): iterable
    {
        yield 'zero trace' => ['00-00000000000000000000000000000000-00f067aa0ba902b7-01'];
        yield 'zero span' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-0000000000000000-01'];
        yield 'uppercase' => ['00-4BF92F3577B34DA6A3CE929D0E0E4736-00f067aa0ba902b7-01'];
        yield 'bad flags' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-zz'];
        yield 'newline flags' => ["00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01\n"];
        yield 'bad version' => ['ff-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'];
        yield 'unsupported future version' => ['01-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'];
    }

    public function testOversizedDuplicateAndTooManyStateMembersAreRejectedWithoutRawValue(): void
    {
        foreach ([
            str_repeat('a', 513),
            'vendor=one,vendor=two',
            "vendor=value\n",
            implode(',', array_map(static fn(int $i): string => 'v' . $i . '=x', range(0, 32))),
        ] as $state) {
            try {
                new TelemetryContext('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-00', $state);
                self::fail('Expected invalid state failure.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringNotContainsString($state, $exception->getMessage());
            }
        }
    }
}

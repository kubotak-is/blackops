<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\OperationConsoleInvocationResult;
use BlackOps\Internal\Console\OperationConsoleOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class OperationConsoleOutputTest extends TestCase
{
    public function testWritesSingleLineJsonInStableKeyOrder(): void
    {
        $output = new BufferedOutput();
        $exit = new OperationConsoleOutput()->write(
            new OperationConsoleInvocationResult([
                'schemaVersion' => 1,
                'status' => 'accepted',
                'operationId' => '019f0000-0000-7000-8000-000000000000',
                'acceptedAt' => '2026-07-22T00:00:00.000000Z',
            ], 0),
            true,
            $output,
        );

        self::assertSame(0, $exit);
        self::assertSame(
            "{\"schemaVersion\":1,\"status\":\"accepted\",\"operationId\":\"019f0000-0000-7000-8000-000000000000\",\"acceptedAt\":\"2026-07-22T00:00:00.000000Z\"}\n",
            $output->fetch(),
        );
    }

    public function testJsonAndHumanEncodingFailureFallsBackToSafeInternalError(): void
    {
        $invalid = "\xB1\x31";
        $result = new OperationConsoleInvocationResult([
            'schemaVersion' => 1,
            'status' => 'completed',
            'outcome' => ['value' => $invalid],
        ], 0);

        $json = new BufferedOutput();
        self::assertSame(1, new OperationConsoleOutput()->write($result, true, $json));
        self::assertSame("{\"schemaVersion\":1,\"status\":\"error\",\"code\":\"internal_error\"}\n", $json->fetch());

        $human = new BufferedOutput();
        self::assertSame(1, new OperationConsoleOutput()->write($result, false, $human));
        self::assertSame("Operation failed [internal_error].\n", $human->fetch());
        self::assertStringNotContainsString($invalid, $json->fetch() . $human->fetch());
    }

    /** @param array<string, mixed> $payload */
    #[\PHPUnit\Framework\Attributes\DataProvider('humanOutputProvider')]
    public function testWritesHumanOutputForEveryResult(array $payload, int $exitCode, string $expected): void
    {
        $output = new BufferedOutput();

        self::assertSame($exitCode, new OperationConsoleOutput()->write(
            new OperationConsoleInvocationResult($payload, $exitCode),
            false,
            $output,
        ));
        self::assertSame($expected, $output->fetch());
    }

    /** @return iterable<string, array{array<string, mixed>, int, string}> */
    public static function humanOutputProvider(): iterable
    {
        yield 'completed empty' => [
            ['schemaVersion' => 1, 'status' => 'completed', 'outcome' => new \stdClass()],
            0,
            "Completed.\n",
        ];
        yield 'accepted' => [
            [
                'schemaVersion' => 1,
                'status' => 'accepted',
                'operationId' => '019f0000-0000-7000-8000-000000000000',
                'acceptedAt' => '2026-07-22T00:00:00.000000Z',
            ],
            0,
            "Accepted operation 019f0000-0000-7000-8000-000000000000.\n",
        ];
        yield 'rejected' => [
            [
                'schemaVersion' => 1,
                'status' => 'rejected',
                'operationId' => '019f0000-0000-7000-8000-000000000000',
                'category' => 'validation',
                'code' => 'validation.failed',
                'violations' => [['field' => 'name', 'rule' => 'not_blank', 'code' => 'validation.not_blank']],
            ],
            2,
            "Rejected [validation:validation.failed] operation 019f0000-0000-7000-8000-000000000000.\n"
                . "  name: not_blank (validation.not_blank)\n",
        ];
        yield 'internal' => [
            ['schemaVersion' => 1, 'status' => 'error', 'code' => 'internal_error'],
            1,
            "Operation failed [internal_error].\n",
        ];
    }

    public function testInvalidPayloadShapeFallsBackToSafeInternalError(): void
    {
        $output = new BufferedOutput();

        self::assertSame(1, new OperationConsoleOutput()->write(
            new OperationConsoleInvocationResult([
                'schemaVersion' => 1,
                'status' => 'completed',
                'outcome' => new \stdClass(),
                'secret' => 'must-not-appear',
            ], 0),
            true,
            $output,
        ));
        self::assertSame("{\"schemaVersion\":1,\"status\":\"error\",\"code\":\"internal_error\"}\n", $output->fetch());
    }
}

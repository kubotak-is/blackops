<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Console\OperationInspectCommand;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsException;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsFound;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsResult;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsUnavailable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class OperationInspectCommandTest extends TestCase
{
    public function testHumanAndJsonUseOneQueryAndWriteOnlyToStdout(): void
    {
        $humanCalls = 0;
        $human = $this->tester(static function (OperationId $id) use (&$humanCalls): OperationDiagnosticsResult {
            ++$humanCalls;
            self::assertSame(OperationInspectFixture::OPERATION_ID, $id->toString());

            return new OperationDiagnosticsFound(OperationInspectFixture::diagnostics());
        });
        self::assertSame(0, $human->execute([
            'operation-id' => OperationInspectFixture::OPERATION_ID,
        ], ['capture_stderr_separately' => true]));
        self::assertSame(1, $humanCalls);
        self::assertStringContainsString("Operation\n", $human->getDisplay());
        self::assertSame('', $human->getErrorOutput());

        $jsonCalls = 0;
        $json = $this->tester(static function (OperationId $id) use (&$jsonCalls): OperationDiagnosticsResult {
            ++$jsonCalls;

            return new OperationDiagnosticsFound(OperationInspectFixture::diagnostics());
        });
        self::assertSame(0, $json->execute([
            'operation-id' => OperationInspectFixture::OPERATION_ID,
            '--json' => true,
        ], ['capture_stderr_separately' => true]));
        self::assertSame(1, $jsonCalls);
        self::assertSame('', $json->getErrorOutput());
        /** @var array<string, mixed> $document */
        $document = json_decode($json->getDisplay(), associative: true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(OperationInspectFixture::OPERATION_ID, $document['operation']['operationId']);
    }

    /** @param array<string, mixed> $input */
    #[DataProvider('invalidInputProvider')]
    public function testInvalidInputIsCommandOwnedAndDoesNotInvokeTheQuery(array $input, bool $json): void
    {
        $calls = 0;
        $tester = $this->tester(static function (OperationId $id) use (&$calls): OperationDiagnosticsResult {
            ++$calls;

            return new OperationDiagnosticsUnavailable();
        });

        self::assertSame(2, $tester->execute($input, ['capture_stderr_separately' => true]));
        self::assertSame(0, $calls);
        self::assertSame('', $tester->getDisplay());
        self::assertSame(
            $json
                ? "{\"schemaVersion\":1,\"status\":\"error\",\"code\":\"operation.invalid_id\"}\n"
                : "operation.invalid_id\n",
            $tester->getErrorOutput(),
        );
    }

    /** @return iterable<string, array{array<string, mixed>, bool}> */
    public static function invalidInputProvider(): iterable
    {
        yield 'missing' => [[], false];
        yield 'malformed' => [['operation-id' => 'not-an-operation-id'], false];
        yield 'leading whitespace' => [['operation-id' => ' ' . OperationInspectFixture::OPERATION_ID], false];
        yield 'trailing whitespace json' => [
            [
                'operation-id' => OperationInspectFixture::OPERATION_ID . ' ',
                '--json' => true,
            ],
            true,
        ];
    }

    public function testUnavailableUsesExitThreeAndSafeJsonStderr(): void
    {
        $tester = $this->tester(
            static fn(OperationId $id): OperationDiagnosticsResult => new OperationDiagnosticsUnavailable(),
        );

        self::assertSame(3, $tester->execute([
            'operation-id' => OperationInspectFixture::OPERATION_ID,
            '--json' => true,
        ], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertSame(
            "{\"schemaVersion\":1,\"status\":\"error\",\"code\":\"operation.unavailable\"}\n",
            $tester->getErrorOutput(),
        );
    }

    #[DataProvider('diagnosticsFailureProvider')]
    public function testDiagnosticsFailuresUseExitFourAndOnlyExposeSafeCodes(
        OperationDiagnosticsException $failure,
        string $code,
    ): void {
        $tester = $this->tester(static function (OperationId $id) use ($failure): OperationDiagnosticsResult {
            throw $failure;
        });

        self::assertSame(4, $tester->execute([
            'operation-id' => OperationInspectFixture::OPERATION_ID,
        ], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertSame($code . "\n", $tester->getErrorOutput());
    }

    /** @return iterable<string, array{OperationDiagnosticsException, string}> */
    public static function diagnosticsFailureProvider(): iterable
    {
        yield 'storage' => [OperationDiagnosticsException::storageFailed(), 'diagnostics.storage_failed'];
        yield 'decode' => [OperationDiagnosticsException::decodeFailed(), 'diagnostics.decode_failed'];
        yield 'integrity' => [OperationDiagnosticsException::integrityFailed(), 'diagnostics.integrity_failed'];
    }

    public function testUnexpectedDatabaseFailureDoesNotExposePreviousDetail(): void
    {
        $tester = $this->tester(static function (OperationId $id): OperationDiagnosticsResult {
            throw new RuntimeException('password=secret host=private-db SQL SELECT encoded_payload');
        });

        self::assertSame(4, $tester->execute([
            'operation-id' => OperationInspectFixture::OPERATION_ID,
            '--json' => true,
        ], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertSame(
            "{\"schemaVersion\":1,\"status\":\"error\",\"code\":\"diagnostics.storage_failed\"}\n",
            $tester->getErrorOutput(),
        );
    }

    public function testDefinitionExposesOnlyTheCanonicalArgumentAndJsonFlag(): void
    {
        $command = new OperationInspectCommand(
            static fn(OperationId $id): OperationDiagnosticsResult => new OperationDiagnosticsUnavailable(),
        );

        self::assertSame('operation:inspect <operation-id> [--json]', $command->getSynopsis());
        self::assertTrue($command->getDefinition()->hasArgument('operation-id'));
        self::assertTrue($command->getDefinition()->hasOption('json'));
        self::assertFalse($command->getDefinition()->hasOption('show-sensitive'));
        self::assertFalse($command->getDefinition()->hasOption('show-error-detail'));
        self::assertSame([], $command->getAliases());
    }

    /** @param callable(OperationId): OperationDiagnosticsResult $finder */
    private function tester(callable $finder): CommandTester
    {
        return new CommandTester(new OperationInspectCommand($finder(...)));
    }
}

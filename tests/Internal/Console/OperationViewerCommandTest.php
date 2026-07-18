<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Application\ApplicationDiagnosticsViewerConfiguration;
use BlackOps\Internal\Console\OperationViewerCommand;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsResult;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsUnavailable;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerRouter;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerTokens;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class OperationViewerCommandTest extends TestCase
{
    public function testDisabledViewerFailsBeforeRouterTokenAndServerComposition(): void
    {
        $routerCalls = 0;
        $serveCalls = 0;
        $tester = new CommandTester(
            new OperationViewerCommand(
                static fn(): ApplicationDiagnosticsViewerConfiguration => self::configuration(false),
                static function () use (&$routerCalls): OperationViewerRouter {
                    ++$routerCalls;
                    throw new RuntimeException('must not compose');
                },
                static function () use (&$serveCalls): void {
                    ++$serveCalls;
                },
            ),
        );

        self::assertSame(1, $tester->execute([], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertSame("viewer.disabled\n", $tester->getErrorOutput());
        self::assertSame(0, $routerCalls);
        self::assertSame(0, $serveCalls);
    }

    public function testEnabledViewerPrintsOneBootstrapUrlOnlyAfterServerStarts(): void
    {
        $started = false;
        $tester = new CommandTester(new OperationViewerCommand(
            static fn(): ApplicationDiagnosticsViewerConfiguration => self::configuration(true),
            static fn(
                ApplicationDiagnosticsViewerConfiguration $config,
                OperationViewerTokens $tokens,
            ): OperationViewerRouter => new OperationViewerRouter(
                $config->authority(),
                $tokens,
                static fn(): OperationDiagnosticsResult => new OperationDiagnosticsUnavailable(),
            ),
            static function ($configuration, $router, $callback) use (&$started): void {
                self::assertFalse($started);
                $started = true;
                $callback();
            },
        ));

        self::assertSame(0, $tester->execute([], ['capture_stderr_separately' => true]));
        self::assertTrue($started);
        self::assertMatchesRegularExpression(
            '#^http://127\.0\.0\.1:8082/\?token=[a-f0-9]{64}\n$#',
            $tester->getDisplay(),
        );
        self::assertSame('', $tester->getErrorOutput());
    }

    public function testInvalidConfigurationAndRuntimeFailuresExposeOnlySafeCodes(): void
    {
        $invalid = new CommandTester(
            new OperationViewerCommand(static function (): ApplicationDiagnosticsViewerConfiguration {
                throw new RuntimeException('credential secret');
            }, static function (): OperationViewerRouter {
                throw new RuntimeException('unused');
            }),
        );
        self::assertSame(1, $invalid->execute([], ['capture_stderr_separately' => true]));
        self::assertSame("viewer.invalid_configuration\n", $invalid->getErrorOutput());

        $runtime = new CommandTester(new OperationViewerCommand(
            static fn(): ApplicationDiagnosticsViewerConfiguration => self::configuration(true),
            static function (): OperationViewerRouter {
                throw new RuntimeException('password=secret SQL details');
            },
        ));
        self::assertSame(1, $runtime->execute([], ['capture_stderr_separately' => true]));
        self::assertSame('', $runtime->getDisplay());
        self::assertSame("viewer.start_failed\n", $runtime->getErrorOutput());
    }

    private static function configuration(bool $enabled): ApplicationDiagnosticsViewerConfiguration
    {
        return ApplicationDiagnosticsViewerConfiguration::fromConfiguration([
            'diagnostics' => ['viewer' => ['enabled' => $enabled]],
        ]);
    }
}

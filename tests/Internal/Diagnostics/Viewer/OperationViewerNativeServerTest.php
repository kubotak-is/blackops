<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Diagnostics\Viewer;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Application\ApplicationDiagnosticsViewerConfiguration;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsResult;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsUnavailable;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerNativeServer;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerRouter;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerServerException;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerTokens;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class OperationViewerNativeServerTest extends TestCase
{
    public function testSignalStopsServerAndRestoresExistingHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            self::markTestSkipped('PCNTL is required.');
        }
        $configuration = $this->configuration($this->availablePort());
        $server = new OperationViewerNativeServer($configuration);
        $beforeTerm = pcntl_signal_get_handler(SIGTERM);
        $beforeInt = pcntl_signal_get_handler(SIGINT);
        $started = 0;

        $server->serve($this->router($configuration), static function () use (&$started): void {
            ++$started;
            posix_kill(getmypid(), SIGTERM);
        });

        self::assertSame(1, $started);
        self::assertSame($beforeTerm, pcntl_signal_get_handler(SIGTERM));
        self::assertSame($beforeInt, pcntl_signal_get_handler(SIGINT));
    }

    public function testPortConflictFailsWithoutCallingStartedCallback(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $wantPeer = false;
        $name = stream_socket_get_name($socket, $wantPeer);
        self::assertIsString($name);
        $port = (int) substr($name, strrpos($name, ':') + 1);
        $started = 0;

        try {
            $this->expectException(OperationViewerServerException::class);
            new OperationViewerNativeServer($this->configuration($port))->serve(
                $this->router($this->configuration($port)),
                static function () use (&$started): void {
                    ++$started;
                },
            );
        } finally {
            fclose($socket);
            self::assertSame(0, $started);
        }
    }

    public function testPeerDisconnectDoesNotLeakWriteWarningAndRestoresErrorHandler(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pair);
        [$writer, $peer] = $pair;
        fclose($peer);
        $warnings = [];
        $externalHandler = static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];

            return true;
        };
        set_error_handler($externalHandler);

        try {
            new ReflectionMethod(OperationViewerNativeServer::class, 'write')->invoke(
                new OperationViewerNativeServer($this->configuration($this->availablePort())),
                $writer,
                str_repeat('x', 1_048_576),
            );
            $probe = static fn(): bool => true;
            $current = set_error_handler($probe);
            restore_error_handler();

            self::assertSame([], $warnings);
            self::assertSame($externalHandler, $current);
        } finally {
            restore_error_handler();
            fclose($writer);
        }
    }

    private function availablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $wantPeer = false;
        $name = stream_socket_get_name($socket, $wantPeer);
        fclose($socket);
        self::assertIsString($name);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function configuration(int $port): ApplicationDiagnosticsViewerConfiguration
    {
        return ApplicationDiagnosticsViewerConfiguration::fromConfiguration([
            'diagnostics' => ['viewer' => ['enabled' => true, 'bind' => '127.0.0.1', 'port' => $port]],
        ]);
    }

    private function router(ApplicationDiagnosticsViewerConfiguration $configuration): OperationViewerRouter
    {
        return new OperationViewerRouter(
            $configuration->authority(),
            OperationViewerTokens::generate(),
            static fn(OperationId $id): OperationDiagnosticsResult => new OperationDiagnosticsUnavailable(),
        );
    }
}

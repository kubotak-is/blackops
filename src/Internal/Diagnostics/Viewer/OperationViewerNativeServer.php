<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics\Viewer;

use BlackOps\Internal\Application\ApplicationDiagnosticsViewerConfiguration;
use BlackOps\Internal\Execution\PcntlSignalSupport;
use Closure;

final class OperationViewerNativeServer
{
    private bool $stop = false;

    public function __construct(
        private readonly ApplicationDiagnosticsViewerConfiguration $configuration,
        private readonly OperationViewerRequestParser $parser = new OperationViewerRequestParser(),
    ) {}

    /** @param Closure(): void $started */
    public function serve(OperationViewerRouter $router, Closure $started): void
    {
        if (!PcntlSignalSupport::available()) {
            throw OperationViewerServerException::unavailable();
        }

        $errorCode = 0;
        $errorMessage = '';
        set_error_handler(static fn(): bool => true);
        try {
            $server = stream_socket_server(
                $this->configuration->socketAddress(),
                $errorCode,
                $errorMessage,
                STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            );
        } finally {
            restore_error_handler();
        }
        if ($server === false) {
            throw OperationViewerServerException::bindFailed();
        }

        $previousAsync = pcntl_async_signals();
        $previousTerm = PcntlSignalSupport::handler(SIGTERM);
        $previousInt = PcntlSignalSupport::handler(SIGINT);
        $this->stop = false;

        try {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, $this->requestStop(...));
            pcntl_signal(SIGINT, $this->requestStop(...));
            $started();

            while (!$this->stop) {
                $connection = $this->accept($server);
                if ($connection === false) {
                    continue;
                }
                try {
                    $head = false;
                    try {
                        $request = $this->parser->parse($connection);
                        $head = $request->method === 'HEAD';
                        $response = $router->route($request);
                    } catch (OperationViewerRequestException) {
                        $response = new OperationViewerResponse(
                            400,
                            [
                                'Cache-Control' => 'no-store',
                                'Referrer-Policy' => 'no-referrer',
                                'X-Content-Type-Options' => 'nosniff',
                                'X-Frame-Options' => 'DENY',
                                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
                                'Content-Type' => 'text/html; charset=UTF-8',
                            ],
                            new OperationViewerRenderer()->badRequest(),
                        );
                    }
                    $this->write($connection, $head ? $response->toHeadHttp() : $response->toHttp());
                } finally {
                    fclose($connection);
                }
            }
        } finally {
            pcntl_signal(SIGTERM, $previousTerm);
            pcntl_signal(SIGINT, $previousInt);
            pcntl_async_signals($previousAsync);
            fclose($server);
        }
    }

    public function requestStop(): void
    {
        $this->stop = true;
    }

    /** @param resource $stream */
    private function write(mixed $stream, string $response): void
    {
        $remaining = $response;
        while ($remaining !== '') {
            $written = $this->writeChunk($stream, $remaining);
            if (!is_int($written) || $written < 1) {
                return;
            }
            $remaining = substr($remaining, $written);
        }
    }

    /** @param resource $stream */
    private function writeChunk(mixed $stream, string $response): int|false
    {
        set_error_handler(static fn(): bool => true);
        try {
            return fwrite($stream, $response);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param resource $server
     * @return resource|false
     */
    private function accept(mixed $server): mixed
    {
        set_error_handler(static fn(): bool => true);
        try {
            return stream_socket_accept($server, timeout: 0.2);
        } finally {
            restore_error_handler();
        }
    }
}

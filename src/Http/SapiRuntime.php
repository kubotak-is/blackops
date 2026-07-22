<?php

declare(strict_types=1);

namespace BlackOps\Http;

use BlackOps\Application\Application;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Internal\Runtime\FrankenPhp\SapiResponseEmitter;
use BlackOps\Internal\Runtime\FrankenPhp\SuperglobalServerRequestFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
#[PublicApi]
final class SapiRuntime
{
    private function __construct() {}

    public static function run(Application $application): void
    {
        $psr17 = null;
        $emitter = null;

        try {
            $psr17 = self::psr17();
            $requests = new SuperglobalServerRequestFactory($psr17, $psr17);
            $emitter = new SapiResponseEmitter();
            $handler = $application->http();
        } catch (Throwable $exception) {
            self::report($exception);

            if ($psr17 !== null && $emitter !== null) {
                self::safeFailure($psr17, $psr17, $emitter, self::requestMethod());
            }

            return;
        }

        try {
            $request = $requests->fromGlobals();
        } catch (Throwable $exception) {
            self::report($exception);
            self::safeFailure($psr17, $psr17, $emitter, self::requestMethod());

            return;
        }

        try {
            $response = $handler->handle($request);
        } catch (Throwable $exception) {
            self::report($exception);
            self::safeFailure($psr17, $psr17, $emitter, $request->getMethod());

            return;
        }

        self::emitResponse($response, $request->getMethod(), $psr17, $psr17, $emitter);
    }

    public static function runWorker(Application $application): void
    {
        $baseline = self::environmentBaseline();

        try {
            $psr17 = self::psr17();
            $requests = new SuperglobalServerRequestFactory($psr17, $psr17);
            $emitter = new SapiResponseEmitter();
            $handler = $application->http();
            $bootId = self::bootEvidence();
        } catch (Throwable $exception) {
            self::report($exception);

            return;
        }

        if (!function_exists('frankenphp_handle_request')) {
            self::report(new RuntimeException('FrankenPHP request loop is unavailable.'));

            return;
        }

        $memoryEvidence = getenv('BLACKOPS_WORKER_MEMORY_EVIDENCE_FILE');
        $sequence = 0;
        $callback = static function () use (
            $baseline,
            $bootId,
            $emitter,
            $handler,
            $memoryEvidence,
            $psr17,
            $requests,
            &$sequence,
        ): void {
            $failed = false;

            try {
                try {
                    $request = $requests->fromGlobals();
                } catch (Throwable $exception) {
                    $failed = true;
                    self::report($exception);
                    self::safeFailure($psr17, $psr17, $emitter, self::requestMethod());

                    return;
                }

                try {
                    $response = $handler->handle($request);
                } catch (Throwable $exception) {
                    $failed = true;
                    self::report($exception);
                    self::safeFailure($psr17, $psr17, $emitter, $request->getMethod());

                    return;
                }

                if (!self::emitResponse($response, $request->getMethod(), $psr17, $psr17, $emitter)) {
                    $failed = true;
                }
            } catch (Throwable $exception) {
                $failed = true;
                self::report($exception);
            } finally {
                $environmentRestored = $_ENV !== $baseline;
                $_ENV = $baseline;
                ++$sequence;
                gc_collect_cycles();
                self::memoryEvidence($memoryEvidence, $bootId, $sequence, $failed, $environmentRestored);
            }
        };

        try {
            while (frankenphp_handle_request($callback)) {
                // The callback owns per-request cleanup and environment restoration.
            }
        } catch (Throwable $exception) {
            self::report($exception);
        }
    }

    private static function psr17(): Psr17Factory
    {
        return new Psr17Factory();
    }

    private static function safeFailure(
        ResponseFactoryInterface $responses,
        StreamFactoryInterface $streams,
        SapiResponseEmitter $emitter,
        string $method,
    ): void {
        if (headers_sent()) {
            return;
        }

        try {
            header_remove();
            $response = $responses
                ->createResponse(500)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($streams->createStream('{"status":"error","code":"internal_error"}'));
            $emitter->emit($response, $method);
        } catch (Throwable $exception) {
            self::report($exception);
        }
    }

    private static function emitResponse(
        ResponseInterface $response,
        string $method,
        ResponseFactoryInterface $responses,
        StreamFactoryInterface $streams,
        SapiResponseEmitter $emitter,
    ): bool {
        try {
            $emitter->emit($response, $method);

            return true;
        } catch (Throwable $exception) {
            self::report($exception);
            self::safeFailure($responses, $streams, $emitter, $method);

            return false;
        }
    }

    private static function requestMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        return is_string($method) ? $method : 'GET';
    }

    /** @return array<string, string> */
    private static function environmentBaseline(): array
    {
        $baseline = [];

        foreach ($_ENV as $name => $value) {
            if (gettype($name) !== 'string' || !is_string($value)) {
                continue;
            }

            $baseline[$name] = $value;
        }

        return $baseline;
    }

    private static function report(Throwable $exception): void
    {
        error_log('BlackOps SAPI runtime failure [' . $exception::class . '].');
    }

    private static function bootEvidence(): string
    {
        $bootId = bin2hex(random_bytes(8));
        $path = getenv('BLACKOPS_WORKER_BOOT_EVIDENCE_FILE');

        if (is_string($path) && $path !== '') {
            file_put_contents($path, $bootId . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        return $bootId;
    }

    private static function memoryEvidence(
        mixed $path,
        string $bootId,
        int $sequence,
        bool $failed,
        bool $environmentRestored,
    ): void {
        if (!is_string($path) || $path === '') {
            return;
        }

        try {
            $evidence = json_encode([
                'bootId' => $bootId,
                'sequence' => $sequence,
                'memoryBytes' => memory_get_usage(true),
                'requestFailed' => $failed,
                'environmentRestored' => $environmentRestored,
            ], JSON_THROW_ON_ERROR);
            file_put_contents($path, $evidence . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $exception) {
            self::report($exception);
        }
    }
}

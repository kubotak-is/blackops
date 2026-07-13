<?php

declare(strict_types=1);

use BlackOps\Application\Application;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Application $application */
$application = require dirname(__DIR__) . '/bootstrap/app.php';
$handler = $application->http();
$psr17 = new Psr17Factory();
$requests = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
$emitter = new SapiEmitter();
$processEnvironment = $_ENV;
$bootId = bin2hex(random_bytes(8));
$requestSequence = 0;

$bootEvidence = getenv('BLACKOPS_WORKER_BOOT_EVIDENCE_FILE');
if (is_string($bootEvidence) && $bootEvidence !== '') {
    file_put_contents($bootEvidence, $bootId . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$memoryEvidence = getenv('BLACKOPS_WORKER_MEMORY_EVIDENCE_FILE');
$handleRequest = static function () use (
    $handler,
    $psr17,
    $requests,
    $emitter,
    $processEnvironment,
    $bootId,
    &$requestSequence,
    $memoryEvidence,
): void {
    $failed = false;

    try {
        $request = $requests->fromGlobals();
        $emitter->emit($handler->handle($request));
    } catch (\Throwable $exception) {
        $failed = true;
        error_log('BlackOps worker request failed with ' . $exception::class . '.');

        if (!headers_sent()) {
            $response = $psr17->createResponse(500)->withHeader('Content-Type', 'application/json');
            $response->getBody()->write('{"status":"error","code":"internal_error"}');
            $emitter->emit($response);
        }
    } finally {
        $environmentRestored = $_ENV !== $processEnvironment;
        $_ENV = $processEnvironment;
        ++$requestSequence;
        gc_collect_cycles();

        if (is_string($memoryEvidence) && $memoryEvidence !== '') {
            $evidence = json_encode([
                'bootId' => $bootId,
                'sequence' => $requestSequence,
                'memoryBytes' => memory_get_usage(true),
                'requestFailed' => $failed,
                'environmentRestored' => $environmentRestored,
            ], JSON_THROW_ON_ERROR);
            file_put_contents($memoryEvidence, $evidence . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
};

while (frankenphp_handle_request($handleRequest)) {
}

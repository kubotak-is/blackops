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

$handleRequest = static function () use ($handler, $psr17, $requests, $emitter, $processEnvironment): void {
    try {
        $request = $requests->fromGlobals();
        $emitter->emit($handler->handle($request));
    } catch (\Throwable $exception) {
        error_log('BlackOps worker request failed with ' . $exception::class . '.');

        if (!headers_sent()) {
            $response = $psr17->createResponse(500)->withHeader('Content-Type', 'application/json');
            $response->getBody()->write('{"status":"error","code":"internal_error"}');
            $emitter->emit($response);
        }
    } finally {
        $_ENV = $processEnvironment;
        gc_collect_cycles();
    }
};

while (frankenphp_handle_request($handleRequest)) {
}

<?php

declare(strict_types=1);

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Server\RequestHandlerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var RequestHandlerInterface $handler */
$handler = require dirname(__DIR__) . '/bootstrap/http.php';
$psr17 = new Psr17Factory();
$request = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17)->fromGlobals();
$response = $handler->handle($request);

new SapiEmitter()->emit($response);

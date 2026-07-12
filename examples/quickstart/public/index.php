<?php

declare(strict_types=1);

use BlackOps\Application\Application;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Application $application */
$application = require dirname(__DIR__) . '/bootstrap/app.php';
$psr17 = new Psr17Factory();
$request = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17)->fromGlobals();
$response = $application->http()->handle($request);

new SapiEmitter()->emit($response);

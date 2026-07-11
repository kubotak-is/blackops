<?php

declare(strict_types=1);

use BlackOps\Internal\Runtime\FrankenPhp\FrankenPhpFrontController;
use BlackOps\Internal\Runtime\FrankenPhp\SapiResponseEmitter;
use BlackOps\Internal\Runtime\FrankenPhp\SuperglobalServerRequestFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$bootstrapPath = getenv('BLACKOPS_APPLICATION_BOOTSTRAP');

if (!is_string($bootstrapPath) || $bootstrapPath === '') {
    throw new RuntimeException('BLACKOPS_APPLICATION_BOOTSTRAP must name an application bootstrap file.');
}

$psr17 = new Psr17Factory();
$request = new SuperglobalServerRequestFactory($psr17, $psr17)->fromGlobals();

new FrankenPhpFrontController(new SapiResponseEmitter())->run($bootstrapPath, $request);

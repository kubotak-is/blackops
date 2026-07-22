<?php

declare(strict_types=1);

use BlackOps\Application\Application;
use BlackOps\Http\SapiRuntime;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Application $application */
$application = require dirname(__DIR__) . '/bootstrap/app.php';
SapiRuntime::run($application);

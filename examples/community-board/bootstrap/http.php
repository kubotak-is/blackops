<?php

declare(strict_types=1);

use App\Http\ApplicationHttpHandlerFactory;
use BlackOps\Application\Application;

/** @var Application $application */
$application = require __DIR__ . '/app.php';

$environment = [];
foreach ($_ENV as $name => $value) {
    if (is_string($name) && is_string($value)) {
        $environment[$name] = $value;
    }
}

return ApplicationHttpHandlerFactory::create($application->http(), $environment);

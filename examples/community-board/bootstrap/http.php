<?php

declare(strict_types=1);

use BlackOps\Application\Application;

/** @var Application $application */
$application = require __DIR__ . '/app.php';

return $application->http();

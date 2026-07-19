<?php

declare(strict_types=1);

use BlackOps\Application\Application;
use Dotenv\Dotenv;

$basePath = dirname(__DIR__);
$processEnvironment = getenv();

foreach ($processEnvironment as $name => $value) {
    if (is_string($name) && is_string($value) && !array_key_exists($name, $_ENV)) {
        $_ENV[$name] = $value;
    }
}

Dotenv::createImmutable($basePath)->safeLoad();

$environment = [];
foreach ($_ENV as $name => $value) {
    if (is_string($name) && is_string($value)) {
        $environment[$name] = $value;
    }
}

return Application::configure($basePath)->withEnvironment($environment)->withConfiguration()->create();

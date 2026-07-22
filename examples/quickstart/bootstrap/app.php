<?php

declare(strict_types=1);

use BlackOps\Application\Application;

$basePath = dirname(__DIR__);

return Application::configure($basePath)->withEnvironmentFile()->withConfiguration()->create();

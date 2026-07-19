<?php

declare(strict_types=1);

use BlackOps\Application\Application;

return Application::configure(dirname(__DIR__))->withConfiguration()->create();

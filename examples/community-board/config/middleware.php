<?php

declare(strict_types=1);

use BlackOps\Http\Authentication\AuthenticationMiddleware;

return [
    'http' => [AuthenticationMiddleware::class],
];

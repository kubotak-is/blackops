<?php

declare(strict_types=1);

$marker = getenv('BLACKOPS_DISCOVERY_SIDE_EFFECT_MARKER');

if (is_string($marker) && $marker !== '') {
    file_put_contents($marker, 'executed');
}

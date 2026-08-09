<?php

declare(strict_types=1);

require '/framework/vendor/autoload.php';

use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQueryFactory;

$callbacks = [];
foreach (OperationalHealthQueryFactory::requiredReadinessCheckCodes() as $code) {
    $callbacks[$code] = static fn(): bool => true;
}

$report = OperationalHealthQueryFactory::fromCallbacks($callbacks)->check(OperationalHealthKind::Readiness);

echo json_encode($report->toArray(), JSON_THROW_ON_ERROR), PHP_EOL;

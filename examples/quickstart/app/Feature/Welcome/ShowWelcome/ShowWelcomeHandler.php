<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowWelcome;

use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use LogicException;

/** @implements OperationHandler<WelcomeValue, WelcomeShown> */
final readonly class ShowWelcomeHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        if (!$operation->value() instanceof WelcomeValue) {
            throw new LogicException('Welcome handler requires WelcomeValue.');
        }

        return OperationResult::completed(new WelcomeShown('Welcome to BlackOps'));
    }
}

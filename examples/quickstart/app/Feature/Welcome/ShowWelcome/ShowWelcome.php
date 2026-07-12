<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowWelcome;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Http\Attribute\Route;
use LogicException;

#[Route(method: 'GET', path: '/welcome')]
#[OperationType('welcome.show')]
#[Accepts(WelcomeValue::class)]
#[Returns(WelcomeShown::class)]
/** @implements OperationHandler<WelcomeValue, WelcomeShown> */
final readonly class ShowWelcome implements Operation, OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        if (!$operation->value() instanceof WelcomeValue) {
            throw new LogicException('Welcome handler requires WelcomeValue.');
        }

        return OperationResult::completed(new WelcomeShown('Welcome to BlackOps'));
    }
}

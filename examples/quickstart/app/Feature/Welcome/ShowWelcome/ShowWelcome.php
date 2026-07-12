<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowWelcome;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'GET', path: '/welcome')]
#[OperationType('welcome.show')]
#[Accepts(WelcomeValue::class)]
#[Returns(WelcomeShown::class)]
final readonly class ShowWelcome implements Operation
{
    public function handle(WelcomeValue $value): OperationResult
    {
        return OperationResult::completed(new WelcomeShown('Welcome to BlackOps'));
    }
}

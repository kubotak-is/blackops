<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowWelcome;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'GET', path: '/welcome')]
#[OperationType('welcome.show')]
#[Accepts(WelcomeValue::class)]
#[HandledBy(ShowWelcomeHandler::class)]
#[Returns(WelcomeShown::class)]
final readonly class ShowWelcome implements Operation {}

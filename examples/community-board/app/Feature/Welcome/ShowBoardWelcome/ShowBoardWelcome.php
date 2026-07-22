<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowBoardWelcome;

use BlackOps\Core\Attribute\ConsoleCommand;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'GET', path: '/welcome')]
#[OperationType('board.welcome.show')]
#[ConsoleCommand('board:welcome', 'Show the Community Board welcome message.')]
final readonly class ShowBoardWelcome implements Operation
{
    public function handle(ShowBoardWelcomeValue $value): BoardWelcomeShown
    {
        return new BoardWelcomeShown(
            message: 'Welcome to BlackOps Board',
            summary: 'A server-rendered reference application powered by BlackOps Operations.',
        );
    }
}

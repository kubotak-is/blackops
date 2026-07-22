<?php

declare(strict_types=1);

namespace App\Feature\Identity\ShowCurrentUser;

use App\Domain\Identity\UserRepository;
use App\Security\AuthenticatedUserPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;
use LogicException;

#[Route(method: 'GET', path: '/me')]
#[OperationType('board.identity.current.user.show')]
#[Authorize(AuthenticatedUserPolicy::class)]
final readonly class ShowCurrentUser implements Operation
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function handle(ShowCurrentUserValue $value, ExecutionContext $context): CurrentUserShown
    {
        $actor = $context->actorContext()?->authorization();
        if ($actor === null || $actor->type() !== 'user') {
            throw new LogicException('Authenticated user context is required.');
        }

        $user = $this->users->findById($actor->id());
        if ($user === null) {
            throw new LogicException('Authenticated user is unavailable.');
        }

        return new CurrentUserShown($user->id, $user->email, $user->displayName);
    }
}

<?php

declare(strict_types=1);

namespace App\Security;

use BlackOps\Core\ExecutionContext;
use LogicException;

final readonly class AuthenticatedUser
{
    public static function id(ExecutionContext $context): string
    {
        $actor = $context->actorContext()?->authorization();
        if ($actor === null || $actor->type() !== 'user') {
            throw new LogicException('Authenticated user context is required.');
        }

        return $actor->id();
    }
}

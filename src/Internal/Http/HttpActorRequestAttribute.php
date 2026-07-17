<?php

declare(strict_types=1);

namespace BlackOps\Internal\Http;

use BlackOps\Core\ActorRef;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HttpActorRequestAttribute
{
    private const string NAME = ActorRef::class;

    public static function attach(ServerRequestInterface $request, ActorRef $actor): ServerRequestInterface
    {
        return $request->withAttribute(self::NAME, $actor);
    }

    public static function actor(ServerRequestInterface $request): ?ActorRef
    {
        /** @var mixed $actor */
        $actor = $request->getAttribute(self::NAME);

        return $actor instanceof ActorRef ? $actor : null;
    }
}

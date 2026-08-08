<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\TenantRef;

/** Ensures clear claim metadata cannot drift from the authenticated context. */
final class DeferredOperationContextValidator
{
    public static function assertMatches(DeferredOperationMessage $message, ExecutionContext $context): void
    {
        if ($context->operationId()->toString() !== $message->operationId()->toString()) {
            throw new DeferredTransportException('Deferred operation context does not match its claim.');
        }

        if (!self::sameTenant($message->tenant(), $context->tenant())) {
            throw new DeferredTransportException('Deferred operation context does not match its tenant claim.');
        }

        if (!self::sameActor($message->originActor(), $context->actorContext()?->origin())) {
            throw new DeferredTransportException('Deferred operation context does not match its origin claim.');
        }
    }

    private static function sameTenant(?TenantRef $expected, ?TenantRef $actual): bool
    {
        return (
            $expected === null
            && $actual === null
            || $expected !== null
            && $actual !== null
            && $expected->type() === $actual->type()
            && $expected->id() === $actual->id()
        );
    }

    private static function sameActor(?ActorRef $expected, ?ActorRef $actual): bool
    {
        return (
            $expected === null
            && $actual === null
            || $expected !== null
            && $actual !== null
            && $expected->type() === $actual->type()
            && $expected->id() === $actual->id()
        );
    }
}

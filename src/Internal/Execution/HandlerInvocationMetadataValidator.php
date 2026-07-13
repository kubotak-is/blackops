<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Operation;
use BlackOps\Core\Registry\OperationMetadata;
use LogicException;

final readonly class HandlerInvocationMetadataValidator
{
    public function validate(OperationMetadata $metadata, object $handler): void
    {
        if (
            $metadata->typedSelfHandled
            && !in_array($metadata->typedSelfHandledMode, [null, 'result', 'outcome', 'void'], strict: true)
        ) {
            throw new LogicException('Operation handler invocation mode is invalid.');
        }

        if (
            $metadata->typedSelfHandledContext && !$metadata->typedSelfHandled
            || $metadata->typedSelfHandledMode !== null && !$metadata->typedSelfHandled
        ) {
            throw new LogicException('Operation handler invocation metadata is invalid.');
        }

        if (
            $metadata->typedSelfHandled
            && ($metadata->definition !== $metadata->handler || !$handler instanceof Operation)
        ) {
            throw new LogicException('Typed self-handled operation metadata is invalid.');
        }

        if ($metadata->typedSelfHandledMode === 'void' && $metadata->outcome !== EmptyOutcome::class) {
            throw new LogicException('Void operation handler outcome metadata is invalid.');
        }

        $handlerClass = $metadata->handler;
        if (!$handler instanceof $handlerClass) {
            throw new LogicException('Resolved handler service does not match operation metadata.');
        }
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\ExecutionContext;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;
use LogicException;

final readonly class HandlerInvoker
{
    public function invoke(
        OperationMetadata $metadata,
        object $handler,
        OperationEnvelope $envelope,
        ?ExecutionContext $typedContext = null,
    ): OperationResult {
        if ($metadata->typedSelfHandledContext && !$metadata->typedSelfHandled) {
            throw new LogicException('Operation handler invocation metadata is invalid.');
        }

        if (
            $metadata->typedSelfHandled
            && ($metadata->definition !== $metadata->handler || !$handler instanceof \BlackOps\Core\Operation)
        ) {
            throw new LogicException('Typed self-handled operation metadata is invalid.');
        }

        $handlerClass = $metadata->handler;
        if (!$handler instanceof $handlerClass) {
            throw new LogicException('Resolved handler service does not match operation metadata.');
        }

        $value = $envelope->value();
        if (!$value instanceof $metadata->value) {
            throw new LogicException('Operation value does not match handler metadata.');
        }

        if ($metadata->typedSelfHandled) {
            return $this->invokeTyped($metadata, $handler, $envelope, $typedContext);
        }

        if (!$handler instanceof OperationHandler) {
            throw new LogicException('Legacy operation handler must implement OperationHandler.');
        }

        return $this->requireResult($handler->handle($envelope));
    }

    private function invokeTyped(
        OperationMetadata $metadata,
        object $handler,
        OperationEnvelope $envelope,
        ?ExecutionContext $typedContext,
    ): OperationResult {
        if ($handler instanceof OperationHandler || !is_callable([$handler, 'handle'])) {
            throw new LogicException('Typed self-handled service is invalid.');
        }

        /** @var callable $callable */
        $callable = [$handler, 'handle'];

        return $this->requireResult(
            $metadata->typedSelfHandledContext
                ? $callable($envelope->value(), $typedContext ?? $envelope->context())
                : $callable($envelope->value()),
        );
    }

    private function requireResult(mixed $result): OperationResult
    {
        if (!$result instanceof OperationResult) {
            throw new LogicException('Operation handler must return OperationResult.');
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;
use LogicException;

final readonly class HandlerInvoker
{
    public function __construct(
        private TypedHandlerResultNormalizer $results = new TypedHandlerResultNormalizer(),
        private HandlerInvocationMetadataValidator $metadataValidator = new HandlerInvocationMetadataValidator(),
    ) {}

    public function invoke(
        OperationMetadata $metadata,
        object $handler,
        OperationEnvelope $envelope,
        ?ExecutionContext $typedContext = null,
    ): OperationResult {
        $this->metadataValidator->validate($metadata, $handler);

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

        return $this->results->normalize($metadata, $handler->handle($envelope));
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

        try {
            return $this->results->normalize(
                $metadata,
                $metadata->typedSelfHandledContext
                    ? $callable($envelope->value(), $typedContext ?? $envelope->context())
                    : $callable($envelope->value()),
            );
        } catch (OperationRejectedException $rejection) {
            return OperationResult::rejected($rejection->reason());
        }
    }
}

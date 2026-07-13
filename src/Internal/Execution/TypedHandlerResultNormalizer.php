<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;
use LogicException;

final readonly class TypedHandlerResultNormalizer
{
    public function normalize(OperationMetadata $metadata, mixed $result): OperationResult
    {
        return match ($metadata->typedSelfHandledMode) {
            null, 'result' => $this->requireResult($result),
            'outcome' => $this->requireOutcome($metadata, $result),
            'void' => $this->requireVoid($result),
            default => throw new LogicException('Operation handler invocation mode is invalid.'),
        };
    }

    private function requireOutcome(OperationMetadata $metadata, mixed $result): OperationResult
    {
        if (!is_object($result) || $result::class !== $metadata->outcome) {
            throw new LogicException('Operation handler outcome does not match operation metadata.');
        }

        return OperationResult::completed($result);
    }

    private function requireVoid(mixed $result): OperationResult
    {
        if ($result !== null) {
            throw new LogicException('Void operation handler must not return a value.');
        }

        return OperationResult::completed();
    }

    private function requireResult(mixed $result): OperationResult
    {
        if (!$result instanceof OperationResult) {
            throw new LogicException('Operation handler must return OperationResult.');
        }

        return $result;
    }
}

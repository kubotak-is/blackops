<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Operation;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use InvalidArgumentException;

final readonly class OperationManifestHandlerDecoder
{
    public function __construct(
        private TypedSelfHandledSignatureValidator $typedSignatures = new TypedSelfHandledSignatureValidator(),
    ) {}

    /**
     * @param array<array-key, mixed> $entry
     * @param class-string<Operation> $definition
     * @param class-string<OperationValue> $value
     * @param class-string $handler
     * @return array{bool, bool}
     */
    public function decode(array $entry, string $definition, string $value, string $handler): array
    {
        $typed = false;
        $context = false;
        if ($definition === $handler && !is_a($definition, OperationHandler::class, allow_string: true)) {
            $typed = true;
            $context = $this->typedSignatures->validate($definition, $value);
        }

        if ($definition !== $handler && !is_a($handler, OperationHandler::class, allow_string: true)) {
            throw new InvalidArgumentException('Operation manifest separate handler is invalid.');
        }

        $encodedTyped = $this->optionalFlag($entry, 'typedSelfHandled');
        $encodedContext = $this->optionalFlag($entry, 'typedSelfHandledContext');
        if (
            $encodedTyped !== null && $encodedTyped !== $typed
            || $encodedContext !== null && $encodedContext !== $context
        ) {
            throw new InvalidArgumentException('Operation manifest handler invocation metadata is invalid.');
        }

        return [$typed, $context];
    }

    /** @param array<array-key, mixed> $entry */
    private function optionalFlag(array $entry, string $key): ?bool
    {
        if (!array_key_exists($key, $entry)) {
            return null;
        }

        if (!is_bool($entry[$key])) {
            throw new InvalidArgumentException('Operation manifest handler invocation metadata is invalid.');
        }

        return $entry[$key];
    }
}

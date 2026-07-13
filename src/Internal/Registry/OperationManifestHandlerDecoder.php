<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Operation;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use InvalidArgumentException;

final readonly class OperationManifestHandlerDecoder
{
    public function __construct(
        private TypedSelfHandledManifestValidator $typedSignatures = new TypedSelfHandledManifestValidator(),
        private OperationManifestInvocationFieldDecoder $fields = new OperationManifestInvocationFieldDecoder(),
    ) {}

    /**
     * @param array<array-key, mixed> $entry
     * @param class-string<Operation> $definition
     * @param class-string<OperationValue> $value
     * @param class-string $handler
     * @param class-string<Outcome> $outcome
     * @return array{bool, bool, null|'result'|'outcome'|'void'}
     */
    public function decode(array $entry, string $definition, string $value, string $handler, string $outcome): array
    {
        $typed = false;
        $context = false;
        $mode = null;
        if ($definition === $handler && !is_a($definition, OperationHandler::class, allow_string: true)) {
            $typed = true;
            $signature = $this->typedSignatures->validate($definition, $value, $outcome);
            $context = $signature['context'];
            $mode = $signature['mode'];
        }

        if ($definition !== $handler && !is_a($handler, OperationHandler::class, allow_string: true)) {
            throw new InvalidArgumentException('Operation manifest separate handler is invalid.');
        }

        $encodedTyped = $this->fields->optionalFlag($entry, 'typedSelfHandled');
        $encodedContext = $this->fields->optionalFlag($entry, 'typedSelfHandledContext');
        $encodedMode = $this->fields->optionalMode($entry);
        if (
            $encodedTyped !== null && $encodedTyped !== $typed
            || $encodedContext !== null && $encodedContext !== $context
            || $encodedMode !== null && $encodedMode !== $mode
        ) {
            throw new InvalidArgumentException('Operation manifest handler invocation metadata is invalid.');
        }

        return [$typed, $context, $mode];
    }
}

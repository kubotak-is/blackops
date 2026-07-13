<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use InvalidArgumentException;

final readonly class OperationManifestInvocationFieldDecoder
{
    /** @param array<array-key, mixed> $entry */
    public function optionalFlag(array $entry, string $key): ?bool
    {
        if (!array_key_exists($key, $entry)) {
            return null;
        }

        if (!is_bool($entry[$key])) {
            throw new InvalidArgumentException('Operation manifest handler invocation metadata is invalid.');
        }

        return $entry[$key];
    }

    /** @param array<array-key, mixed> $entry @return null|'result'|'outcome'|'void' */
    public function optionalMode(array $entry): ?string
    {
        if (!array_key_exists('typedSelfHandledMode', $entry)) {
            return null;
        }

        if (!is_string($entry['typedSelfHandledMode'])) {
            throw new InvalidArgumentException('Operation manifest handler invocation metadata is invalid.');
        }
        $mode = $entry['typedSelfHandledMode'];
        if (!in_array($mode, ['result', 'outcome', 'void'], strict: true)) {
            throw new InvalidArgumentException('Operation manifest handler invocation metadata is invalid.');
        }

        return $mode;
    }
}

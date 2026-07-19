<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend\Generation;

use BlackOps\Internal\Frontend\FrontendContractManifest;
use BlackOps\Internal\Frontend\FrontendContractManifestCodec;
use BlackOps\Internal\Frontend\FrontendOperationContract;
use InvalidArgumentException;
use JsonException;

final readonly class FrontendContractHasher
{
    public function hash(FrontendContractManifest $manifest): string
    {
        $operations = $manifest->operations;
        usort($operations, static fn(FrontendOperationContract $left, FrontendOperationContract $right): int => strcmp(
            $left->module,
            $right->module,
        ));
        $payload = new FrontendContractManifestCodec()->encode(
            new FrontendContractManifest($operations),
            'canonical-contract',
        )['payload'];

        try {
            $canonical = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new InvalidArgumentException('Frontend contract cannot be hashed.');
        }

        return hash('sha256', $canonical);
    }
}

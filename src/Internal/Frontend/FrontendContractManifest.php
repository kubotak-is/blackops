<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

final readonly class FrontendContractManifest
{
    /** @param list<FrontendOperationContract> $operations */
    public function __construct(
        public array $operations,
    ) {}
}

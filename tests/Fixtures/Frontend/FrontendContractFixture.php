<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Frontend;

use BlackOps\Internal\Frontend\FrontendContractManifest;
use BlackOps\Internal\Frontend\FrontendContractManifestArtifact;
use BlackOps\Internal\Frontend\FrontendContractManifestCodec;
use BlackOps\Internal\Frontend\FrontendOperationContract;
use BlackOps\Internal\Frontend\FrontendOutcomeContract;
use BlackOps\Internal\Frontend\FrontendOutcomeFieldContract;
use BlackOps\Internal\Frontend\FrontendOutcomeTypeContract;
use BlackOps\Internal\Frontend\FrontendValueContract;
use BlackOps\Internal\Frontend\FrontendValueFieldContract;

final readonly class FrontendContractFixture
{
    public static function artifact(string $buildId = 'frontend-generation-build'): FrontendContractManifestArtifact
    {
        return new FrontendContractManifestArtifact(
            FrontendContractManifestCodec::SCHEMA_VERSION,
            $buildId,
            self::manifest(),
        );
    }

    public static function manifest(): FrontendContractManifest
    {
        return new FrontendContractManifest([
            new FrontendOperationContract(
                'order.create',
                'App\\Feature\\Order\\CreateOrder\\CreateOrder',
                'CreateOrder',
                'operations/order/create-order.ts',
                'POST',
                '/accounts/{accountId}/orders',
                'inline',
                new FrontendValueContract('App\\Feature\\Order\\CreateOrder\\CreateOrderValue', [
                    new FrontendValueFieldContract('accountId', 'integer', false, true, 'path', 'accountId', false, []),
                    new FrontendValueFieldContract('active', 'boolean', false, true, 'query', 'active', false, []),
                    new FrontendValueFieldContract('filter', 'string', true, false, 'query', 'q', false, []),
                    new FrontendValueFieldContract('quantity', 'float', false, false, 'body', 'amount', false, []),
                    new FrontendValueFieldContract('reference', 'string', false, true, 'body', 'reference', false, []),
                    new FrontendValueFieldContract(
                        'requestToken',
                        'string',
                        false,
                        true,
                        'header',
                        'X-Request-Token',
                        true,
                        [],
                    ),
                ]),
                new FrontendOutcomeContract('App\\Feature\\Order\\OrderCreated', 'outcome', [
                    new FrontendOutcomeFieldContract(
                        'orderId',
                        new FrontendOutcomeTypeContract('scalar', false, 'string'),
                    ),
                ]),
            ),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Feature\Diagnostics\TriggerFailure;

use App\Security\SampleUserAuthorizationPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[Route(method: 'POST', path: '/failures')]
#[OperationType('diagnostics.failure.trigger')]
#[Authorize(SampleUserAuthorizationPolicy::class)]
final readonly class TriggerFailure implements Operation
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(TriggerFailureValue $value): FailureTriggered
    {
        $this->logger->info('Quickstart diagnostics failure requested.', [
            'reference' => $value->reference,
        ]);

        throw new RuntimeException('Intentional quickstart diagnostics failure.');
    }
}

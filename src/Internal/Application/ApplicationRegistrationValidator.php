<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\Registry\OperationProvider;
use Symfony\Component\Console\Command\Command;

final readonly class ApplicationRegistrationValidator
{
    /**
     * @param iterable<array-key, mixed> $entries
     * @return list<OperationProvider|class-string<OperationProvider>>
     */
    public function operationProviders(iterable $entries): array
    {
        return new ApplicationProviderValidator()->validate($entries, OperationProvider::class, 'operation');
    }

    /**
     * @param iterable<array-key, mixed> $entries
     * @return list<ServiceProvider|class-string<ServiceProvider>>
     */
    public function serviceProviders(iterable $entries): array
    {
        return new ApplicationProviderValidator()->validate($entries, ServiceProvider::class, 'service');
    }

    /**
     * @param iterable<array-key, mixed> $entries
     * @return list<Command|class-string<Command>>
     */
    public function commands(iterable $entries): array
    {
        return new ApplicationCommandValidator()->validate($entries);
    }
}

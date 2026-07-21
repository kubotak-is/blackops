<?php

declare(strict_types=1);

namespace App;

use App\Feature\Order\CreateOrder\CreateOrderCommand;
use App\Feature\Order\DoctrineOrderRepository;
use App\Feature\Order\OrderRepository;
use App\Feature\Order\RecordOrderCommit;
use App\Security\SampleOperationStatusAuthorizer;
use App\UserInterface\Http\SampleTokenAuthenticator;
use App\UserInterface\Console\SampleConsoleActorProvider;
use BlackOps\Console\ConsoleActorProvider;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Status\OperationStatusAuthorizer;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(HttpAuthenticator::class, SampleTokenAuthenticator::class);
        $services->autowire(OperationStatusAuthorizer::class, SampleOperationStatusAuthorizer::class);
        $services->autowire(ConsoleActorProvider::class, SampleConsoleActorProvider::class);
        $services->autowire(OrderRepository::class, DoctrineOrderRepository::class);
        $services->autowire(CreateOrderCommand::class);
        $services->autowire(RecordOrderCommit::class);
    }
}

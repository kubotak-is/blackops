<?php

declare(strict_types=1);

namespace BlackOps\Core\DependencyInjection;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface ServiceRegistry
{
    /**
     * @param class-string $id
     * @param class-string|null $class
     */
    public function autowire(string $id, ?string $class = null): void;

    public function set(string $id, object $service): void;
}

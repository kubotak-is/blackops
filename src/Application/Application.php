<?php

declare(strict_types=1);

namespace BlackOps\Application;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use LogicException;
use ReflectionClass;

#[PublicApi]
final readonly class Application
{
    private function __construct(
        private ApplicationConfigurationSnapshot $_configuration,
    ) {}

    public static function configure(string $basePath): ApplicationBuilder
    {
        $reflection = new ReflectionClass(ApplicationBuilder::class);
        $builder = $reflection->newInstanceWithoutConstructor();
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            throw new LogicException('Unable to initialize the application builder.');
        }

        $constructor->invoke($builder, $basePath);

        return $builder;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Application;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationHttpRuntime;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Throwable;

#[PublicApi]
final readonly class Application
{
    private ApplicationHttpRuntime $runtime;

    private function __construct(
        private ApplicationConfigurationSnapshot $_configuration,
    ) {
        $this->runtime = new ApplicationHttpRuntime($this->_configuration);
    }

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

    public function http(): RequestHandlerInterface
    {
        try {
            return $this->runtime->handler();
        } catch (InvalidArgumentException $exception) {
            throw new ApplicationBootstrapException($exception->getMessage(), previous: $exception);
        } catch (Throwable $exception) {
            throw new ApplicationBootstrapException(
                'Application HTTP runtime composition failed.',
                previous: $exception,
            );
        }
    }
}

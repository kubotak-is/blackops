<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime\FrankenPhp;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final readonly class FrankenPhpFrontController
{
    public function __construct(
        private SapiResponseEmitter $emitter,
    ) {}

    public function run(string $bootstrapPath, ServerRequestInterface $request): void
    {
        if (!is_file($bootstrapPath) || !is_readable($bootstrapPath)) {
            throw new RuntimeException('Application bootstrap file is not readable.');
        }

        $handler = $this->assertHandler((static fn(string $path): mixed => require $path)($bootstrapPath));

        $this->emitter->emit($handler->handle($request), $request->getMethod());
    }

    private function assertHandler(mixed $handler): RequestHandlerInterface
    {
        if (!$handler instanceof RequestHandlerInterface) {
            throw new RuntimeException('Application bootstrap must return a PSR-15 request handler.');
        }

        return $handler;
    }
}

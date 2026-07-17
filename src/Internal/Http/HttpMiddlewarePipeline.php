<?php

declare(strict_types=1);

namespace BlackOps\Internal\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class HttpMiddlewarePipeline implements RequestHandlerInterface
{
    private RequestHandlerInterface $pipeline;

    /** @param array<array-key, mixed> $middleware */
    public function __construct(array $middleware, RequestHandlerInterface $handler)
    {
        if (!array_is_list($middleware)) {
            throw new InvalidArgumentException('HTTP middleware pipeline requires a list of PSR-15 middleware.');
        }

        $entries = array_map(static function (mixed $entry): MiddlewareInterface {
            if (!$entry instanceof MiddlewareInterface) {
                throw new InvalidArgumentException('HTTP middleware pipeline requires a list of PSR-15 middleware.');
            }

            return $entry;
        }, $middleware);

        foreach (array_reverse($entries) as $entry) {
            $handler = new readonly class($entry, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $handler,
                ) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->handler);
                }
            };
        }

        $this->pipeline = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->pipeline->handle($request);
    }
}

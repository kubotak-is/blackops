<?php

declare(strict_types=1);

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

return new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() !== 'GET' || $request->getUri()->getPath() !== '/healthz') {
            return new Response(
                404,
                ['Content-Type' => 'application/json'],
                '{"error":"not_found"}',
            );
        }

        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            '{"status":"ok"}',
        );
    }
};

<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime\FrankenPhp;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class SuperglobalServerRequestFactory
{
    public function __construct(
        private ServerRequestFactoryInterface $requests,
        private StreamFactoryInterface $streams,
    ) {}

    public function fromGlobals(): ServerRequestInterface
    {
        $body = file_get_contents('php://input');

        if (!is_string($body)) {
            throw new \RuntimeException('Unable to read the HTTP request body.');
        }

        return $this->create($_SERVER, $_GET, $_COOKIE, $body);
    }

    /**
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $cookies
     */
    public function create(array $server, array $query, array $cookies, string $body): ServerRequestInterface
    {
        $request = $this->requests->createServerRequest(
            SuperglobalRequestMetadata::method($server),
            SuperglobalRequestUri::build($server),
            $server,
        );

        foreach (SuperglobalRequestHeaders::extract($server) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $protocol = SuperglobalRequestMetadata::protocolVersion($server);

        if ($protocol !== null) {
            $request = $request->withProtocolVersion($protocol);
        }

        return $request
            ->withQueryParams($query)
            ->withCookieParams($cookies)
            ->withBody($this->streams->createStream($body));
    }
}

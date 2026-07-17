<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Http;

use BlackOps\Internal\Http\HttpMiddlewarePipeline;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpMiddlewarePipelineTest extends TestCase
{
    public function testRunsMiddlewareInConfiguredOnionOrderAndAllowsRequestAndResponseChanges(): void
    {
        $events = new PipelineEvents();
        $terminal = new PipelineTerminalHandler($events);
        $pipeline = new HttpMiddlewarePipeline([
            new RecordingMiddleware('A', $events),
            new RecordingMiddleware('B', $events),
        ], $terminal);

        $response = $pipeline->handle(new Psr17Factory()->createServerRequest('GET', '/'));

        self::assertSame(['A before', 'B before', 'handler', 'B after', 'A after'], $events->values);
        self::assertSame('A,B', $terminal->request?->getHeaderLine('X-Middleware-Before'));
        self::assertSame('B,A', $response->getHeaderLine('X-Middleware-After'));
    }

    public function testEmptyPipelineDelegatesDirectlyToHandler(): void
    {
        $events = new PipelineEvents();
        $terminal = new PipelineTerminalHandler($events);
        $pipeline = new HttpMiddlewarePipeline([], $terminal);
        $request = new Psr17Factory()->createServerRequest('GET', '/');

        $response = $pipeline->handle($request);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame($request, $terminal->request);
        self::assertSame(['handler'], $events->values);
    }

    public function testReusesPrebuiltChainWithoutSharingRequestState(): void
    {
        $events = new PipelineEvents();
        $terminal = new PipelineTerminalHandler($events);
        $pipeline = new HttpMiddlewarePipeline([
            new RecordingMiddleware('A', $events),
            new RecordingMiddleware('B', $events),
        ], $terminal);
        $requests = new Psr17Factory();

        $first = $pipeline->handle($requests->createServerRequest('GET', '/first')->withHeader('X-Request', 'first'));
        $second = $pipeline->handle($requests->createServerRequest('GET', '/second')->withHeader(
            'X-Request',
            'second',
        ));

        self::assertSame(
            [
                'A before',
                'B before',
                'handler',
                'B after',
                'A after',
                'A before',
                'B before',
                'handler',
                'B after',
                'A after',
            ],
            $events->values,
        );
        self::assertSame('first', $terminal->requests[0]->getHeaderLine('X-Request'));
        self::assertSame('second', $terminal->requests[1]->getHeaderLine('X-Request'));
        self::assertSame('A,B', $terminal->requests[0]->getHeaderLine('X-Middleware-Before'));
        self::assertSame('A,B', $terminal->requests[1]->getHeaderLine('X-Middleware-Before'));
        self::assertSame('B,A', $first->getHeaderLine('X-Middleware-After'));
        self::assertSame('B,A', $second->getHeaderLine('X-Middleware-After'));
    }

    public function testRejectsNonListOrNonMiddlewareEntries(): void
    {
        $handler = new PipelineTerminalHandler(new PipelineEvents());

        try {
            new HttpMiddlewarePipeline(['named' => new \stdClass()], $handler);
            self::fail('Expected invalid pipeline.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);

        new HttpMiddlewarePipeline([new \stdClass()], $handler);
    }
}

final class PipelineEvents
{
    /** @var list<string> */
    public array $values = [];
}

final readonly class RecordingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $name,
        private PipelineEvents $events,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->events->values[] = $this->name . ' before';
        $before = $request->getHeaderLine('X-Middleware-Before');
        $response = $handler->handle($request->withHeader(
            'X-Middleware-Before',
            $before === '' ? $this->name : $before . ',' . $this->name,
        ));
        $this->events->values[] = $this->name . ' after';
        $after = $response->getHeaderLine('X-Middleware-After');

        return $response->withHeader('X-Middleware-After', $after === '' ? $this->name : $after . ',' . $this->name);
    }
}

final class PipelineTerminalHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    /** @var list<ServerRequestInterface> */
    public array $requests = [];

    public function __construct(
        private PipelineEvents $events,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        $this->requests[] = $request;
        $this->events->values[] = 'handler';

        return new Psr17Factory()->createResponse(204);
    }
}

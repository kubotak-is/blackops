<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http\Observability;

use BlackOps\Http\Observability\OperationalHealthJsonResponder;
use BlackOps\Http\Observability\OperationalHealthRequestHandler;
use BlackOps\Internal\Observability\CallbackOperationalHealthCheck;
use BlackOps\Internal\Observability\DefaultOperationalHealthQuery;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQuery;
use BlackOps\Observability\OperationalHealthReport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OperationalHealthRequestHandlerTest extends TestCase
{
    public function testExplicitReadinessHandlerReturnsSafeNoStore503(): void
    {
        $factory = new Psr17Factory();
        $handler = new OperationalHealthRequestHandler(
            new DefaultOperationalHealthQuery([
                new CallbackOperationalHealthCheck('database', static fn(): bool => false),
            ]),
            OperationalHealthKind::Readiness,
            new OperationalHealthJsonResponder($factory, $factory),
        );

        $response = $handler->handle($factory->createServerRequest('GET', '/ready'));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(
            'readiness',
            json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR)['kind'],
        );
    }

    public function testExplicitReadinessHandlerReturns200WhenChecksPass(): void
    {
        $factory = new Psr17Factory();
        $handler = new OperationalHealthRequestHandler(
            new DefaultOperationalHealthQuery([
                new CallbackOperationalHealthCheck('database', static fn(): bool => true),
            ]),
            OperationalHealthKind::Readiness,
            new OperationalHealthJsonResponder($factory, $factory),
        );

        $response = $handler->handle($factory->createServerRequest('GET', '/ready'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            'pass',
            json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR)['status'],
        );
    }

    public function testNonGetIsRejectedWithoutAutomaticRouteRegistration(): void
    {
        $factory = new Psr17Factory();
        $handler = new OperationalHealthRequestHandler(
            new DefaultOperationalHealthQuery(),
            OperationalHealthKind::Liveness,
            new OperationalHealthJsonResponder($factory, $factory),
        );

        $response = $handler->handle($factory->createServerRequest('POST', '/health'));

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET', $response->getHeaderLine('Allow'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testThrowingQueryReturnsSafe503WithoutSensitiveDetails(): void
    {
        $factory = new Psr17Factory();
        $query = new class implements OperationalHealthQuery {
            public function check(OperationalHealthKind $kind): OperationalHealthReport
            {
                throw new RuntimeException('dsn=password-secret');
            }
        };
        $handler = new OperationalHealthRequestHandler(
            $query,
            OperationalHealthKind::Readiness,
            new OperationalHealthJsonResponder($factory, $factory),
        );

        $response = $handler->handle($factory->createServerRequest('GET', '/ready'));
        $payload = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame([['code' => 'query_failed', 'status' => 'fail']], $payload['checks']);
        self::assertStringNotContainsString('password-secret', (string) $response->getBody());
    }
}

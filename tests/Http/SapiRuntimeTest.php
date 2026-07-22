<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http;

use BlackOps\Http\SapiRuntime;
use BlackOps\Internal\Runtime\FrankenPhp\SapiResponseEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class SapiRuntimeTest extends TestCase
{
    public function testSafeFailureEmitsFixedJsonBeforeAnyHeaders(): void
    {
        $status = null;
        $headers = [];
        $body = '';
        $emitter = new SapiResponseEmitter(
            static function (int $value) use (&$status): void {
                $status = $value;
            },
            static function (string $value) use (&$headers): void {
                $headers[] = $value;
            },
            static function (string $value) use (&$body): void {
                $body .= $value;
            },
        );
        $factory = new Psr17Factory();

        $method = new ReflectionMethod(SapiRuntime::class, 'safeFailure');
        $method->invoke(null, $factory, $factory, $emitter, 'GET');

        self::assertSame(500, $status);
        self::assertSame(['Content-Type: application/json'], $headers);
        self::assertSame('{"status":"error","code":"internal_error"}', $body);
    }

    public function testSafeFailureDoesNotLeakEmitterFailure(): void
    {
        $emitter = new SapiResponseEmitter(static function (): never {
            throw new RuntimeException('emitter detail');
        });
        $factory = new Psr17Factory();
        $method = new ReflectionMethod(SapiRuntime::class, 'safeFailure');

        $method->invoke(null, $factory, $factory, $emitter, 'GET');
        self::assertTrue(true);
    }

    public function testNormalEmitterFailureFallsBackToFixedResponse(): void
    {
        $statusCalls = 0;
        $body = '';
        $emitter = new SapiResponseEmitter(
            static function () use (&$statusCalls): void {
                ++$statusCalls;

                if ($statusCalls === 1) {
                    throw new RuntimeException('status detail');
                }
            },
            static function (): void {},
            static function (string $value) use (&$body): void {
                $body .= $value;
            },
        );
        $factory = new Psr17Factory();
        $response = $factory->createResponse(200);
        $method = new ReflectionMethod(SapiRuntime::class, 'emitResponse');

        self::assertFalse($method->invoke(null, $response, 'GET', $factory, $factory, $emitter));
        self::assertSame(2, $statusCalls);
        self::assertSame('{"status":"error","code":"internal_error"}', $body);
    }

    public function testWorkerEnvironmentBaselineContainsOnlyStringPairs(): void
    {
        $original = $_ENV;
        $_ENV = ['APP_ENV' => 'testing', 'NON_STRING' => 123, 42 => 'integer-key'];

        try {
            $method = new ReflectionMethod(SapiRuntime::class, 'environmentBaseline');

            self::assertSame(['APP_ENV' => 'testing'], $method->invoke(null));
        } finally {
            $_ENV = $original;
        }
    }

    public function testRuntimeConstructorIsPrivateAndPublicMethodsAreStable(): void
    {
        $runtime = new ReflectionClass(SapiRuntime::class);

        self::assertTrue($runtime->getConstructor()?->isPrivate());
        self::assertSame(
            ['run', 'runWorker'],
            array_map(
                static fn(ReflectionMethod $method): string => $method->getName(),
                $runtime->getMethods(ReflectionMethod::IS_PUBLIC),
            ),
        );
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Diagnostics;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsAuthorizationRequest;
use BlackOps\Internal\Diagnostics\UnavailableOperationDiagnosticsAuthorizer;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationDiagnosticsAuthorizationTest extends TestCase
{
    public function testRequestRetainsTypedValuesAndNullabilityQuadrants(): void
    {
        $operationId = OperationId::fromString('0194a000-0000-7000-8000-0000000000d2');
        $actor = new ActorRef('diagnostics-canary-actor', 'user');
        $tenant = new TenantRef('account', 'diagnostics-canary-tenant');
        $canary = 'diagnostics-canary';
        foreach ([
            [$actor, $tenant],
            [$actor, null],
            [null, $tenant],
            [null, null],
        ] as [$currentActor, $currentTenant]) {
            $request = new OperationDiagnosticsAuthorizationRequest($operationId, $currentActor, $currentTenant);
            self::assertSame($operationId, $request->operationId());
            self::assertSame($currentActor, $request->currentActor());
            self::assertSame($currentTenant, $request->currentTenant());
            self::assertSame([], $request->__debugInfo());
            self::assertSame([], $request->__serialize());
            $this->assertValueFree($request, $canary);
        }
    }

    public function testRequestSerializationAndMalformedSnapshotAreValueFree(): void
    {
        $request = new OperationDiagnosticsAuthorizationRequest(
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            new ActorRef('diagnostics-canary-actor', 'user'),
            new TenantRef('account', 'diagnostics-canary-tenant'),
        );
        $canary = 'diagnostics-canary';
        try {
            $request->__unserialize([]);
            self::fail('request unserialization was accepted');
        } catch (LogicException $exception) {
            self::assertSame(
                'Operation diagnostics authorization request cannot be unserialized.',
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
            self::assertSame(0, $exception->getCode());
        }

        $reflection = new ReflectionClass($request);
        $reflection->getProperty('snapshot')->setValue($request, new \SensitiveParameterValue([]));
        foreach (['operationId', 'currentActor', 'currentTenant'] as $method) {
            try {
                $request->{$method}();
                self::fail('malformed request snapshot was accepted by ' . $method);
            } catch (LogicException $exception) {
                $this->assertUnavailableValueFree($exception, $canary);
            }
        }

        $valid = [
            'operationId' => OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            'currentActor' => new ActorRef('actor-1', 'user'),
            'currentTenant' => new TenantRef('account', 'tenant-1'),
        ];
        $corruptSnapshots = [
            'missing-operation-id' => [
                'currentActor' => $valid['currentActor'],
                'currentTenant' => $valid['currentTenant'],
            ],
            'missing-actor' => [
                'operationId' => $valid['operationId'],
                'currentTenant' => $valid['currentTenant'],
            ],
            'missing-tenant' => [
                'operationId' => $valid['operationId'],
                'currentActor' => $valid['currentActor'],
            ],
            'extra-key' => [...$valid, 'extra' => 'forbidden'],
            'wrong-operation-id' => [...$valid, 'operationId' => '0194a000-0000-7000-8000-0000000000d2'],
            'wrong-actor' => [...$valid, 'currentActor' => 'actor-1'],
            'wrong-tenant' => [...$valid, 'currentTenant' => 'tenant-1'],
        ];
        foreach ($corruptSnapshots as $name => $snapshot) {
            $reflection->getProperty('snapshot')->setValue($request, new \SensitiveParameterValue($snapshot));
            foreach (['operationId', 'currentActor', 'currentTenant'] as $method) {
                try {
                    $request->{$method}();
                    self::fail($name . ' snapshot was accepted by ' . $method);
                } catch (LogicException $exception) {
                    self::assertSame(
                        'Operation diagnostics authorization request is unavailable.',
                        $exception->getMessage(),
                        $name . ':' . $method,
                    );
                    self::assertNull($exception->getPrevious(), $name . ':' . $method);
                    self::assertSame(0, $exception->getCode(), $name . ':' . $method);
                    $this->assertUnavailableValueFree($exception, 'diagnostics-canary');
                }
            }
        }
    }

    public function testDefaultAuthorizerAlwaysReturnsResourceUnavailableWithoutReadingRequest(): void
    {
        $authorizer = new UnavailableOperationDiagnosticsAuthorizer();
        $request = new OperationDiagnosticsAuthorizationRequest(
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            new ActorRef('actor-1', 'user'),
            new TenantRef('account', 'tenant-1'),
        );
        self::assertSame(AuditReadAuthorizationDecision::ResourceUnavailable, $authorizer->decide($request));
        self::assertSame(AuditReadAuthorizationDecision::ResourceUnavailable, $authorizer->decide($request));
    }

    public function testDefaultAuthorizerDoesNotReadACorruptRequestSnapshot(): void
    {
        $request = new OperationDiagnosticsAuthorizationRequest(
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            new ActorRef('diagnostics-canary-actor', 'user'),
            new TenantRef('account', 'diagnostics-canary-tenant'),
        );
        $reflection = new ReflectionClass(OperationDiagnosticsAuthorizationRequest::class);
        $reflection->getProperty('snapshot')->setValue($request, new \SensitiveParameterValue([
            'unexpected' => 'diagnostics-canary',
        ]));

        self::assertSame(
            AuditReadAuthorizationDecision::ResourceUnavailable,
            new UnavailableOperationDiagnosticsAuthorizer()->decide($request),
        );
        foreach (['operationId', 'currentActor', 'currentTenant'] as $method) {
            try {
                $request->{$method}();
                self::fail('corrupt request was accepted by ' . $method);
            } catch (LogicException $exception) {
                $this->assertUnavailableValueFree($exception, 'diagnostics-canary');
            }
        }
    }

    public function testDefaultCloneSharesOnlyTheOpaqueSnapshot(): void
    {
        $operationId = OperationId::fromString('0194a000-0000-7000-8000-0000000000d2');
        $actor = new ActorRef('diagnostics-canary-actor', 'user');
        $tenant = new TenantRef('account', 'diagnostics-canary-tenant');
        foreach ([
            [$actor, $tenant],
            [$actor, null],
            [null, $tenant],
            [null, null],
        ] as [$currentActor, $currentTenant]) {
            $request = new OperationDiagnosticsAuthorizationRequest($operationId, $currentActor, $currentTenant);
            $clone = clone $request;
            self::assertSame($operationId, $clone->operationId());
            self::assertSame($currentActor, $clone->currentActor());
            self::assertSame($currentTenant, $clone->currentTenant());
            self::assertSame([], $clone->__serialize());
            $this->assertValueFree($clone, 'diagnostics-canary');
        }
    }

    private function assertUnavailableValueFree(\Throwable $exception, string $canary): void
    {
        self::assertSame('Operation diagnostics authorization request is unavailable.', $exception->getMessage());
        self::assertNull($exception->getPrevious());
        self::assertSame(0, $exception->getCode());
        self::assertStringNotContainsString($canary, $exception->getTraceAsString());
        self::assertStringNotContainsString($canary, var_export($exception->getTrace(), true));
        $this->assertValueFree($exception, $canary);
    }

    private function assertValueFree(object $value, string $canary): void
    {
        $surfaces = [
            'print_r' => print_r($value, true),
            'serialize' => $this->captureSurface(static fn(): string => serialize($value)),
            'json' => $this->captureSurface(static fn(): string => (string) json_encode($value, JSON_THROW_ON_ERROR)),
            'var_export' => var_export($value, true),
        ];
        ob_start();
        debug_zval_dump($value);
        $surfaces['debug'] = (string) ob_get_clean();
        foreach ($surfaces as $surface => $output) {
            self::assertIsString($output, $surface);
            self::assertStringNotContainsString($canary, $output, $surface);
        }
    }

    private function captureSurface(\Closure $surface): string
    {
        try {
            return $surface();
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }
}

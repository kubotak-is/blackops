<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Status;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Status\OperationStatusAuditTenantAuthorizer;
use BlackOps\Status\Exception\OperationStatusQueryException;
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class OperationStatusAuditTenantAuthorizerTest extends TestCase
{
    private const string OPERATION_ID = '019f80ab-1000-7000-8000-000000000001';

    /** @return iterable<string, array{TenantRef|null, TenantRef|null}> */
    public static function mismatchedTenants(): iterable
    {
        yield 'current tenant only' => [new TenantRef('account', 'tenant-a'), null];
        yield 'origin tenant only' => [null, new TenantRef('account', 'tenant-a')];
        yield 'different type' => [new TenantRef('account', 'tenant-a'), new TenantRef('customer', 'tenant-a')];
        yield 'different id' => [new TenantRef('account', 'tenant-a'), new TenantRef('account', 'tenant-b')];
    }

    #[DataProvider('mismatchedTenants')]
    public function testMismatchedTenantIsDeniedBeforeApplicationAuthorizer(
        ?TenantRef $currentTenant,
        ?TenantRef $originTenant,
    ): void {
        $authorizer = new StatusTenantAuthorizerSpy(OperationStatusAuthorizationDecision::allow());
        $request = $this->request($currentTenant, $originTenant);

        $decision = new OperationStatusAuditTenantAuthorizer($authorizer)->decide($request);

        self::assertFalse($decision->isAllowed());
        self::assertSame(0, $authorizer->calls);
    }

    public function testEqualNullAndExactTenantValuesDelegateTheOriginalRequestOnce(): void
    {
        $authorizer = new StatusTenantAuthorizerSpy(OperationStatusAuthorizationDecision::allow());
        $guard = new OperationStatusAuditTenantAuthorizer($authorizer);
        $nullRequest = $this->request(null, null);
        $tenant = new TenantRef('account', 'tenant-a');
        $tenantRequest = $this->request($tenant, new TenantRef('account', 'tenant-a'));

        self::assertTrue($guard->decide($nullRequest)->isAllowed());
        self::assertTrue($guard->decide($tenantRequest)->isAllowed());
        self::assertSame(2, $authorizer->calls);
        self::assertSame($tenantRequest, $authorizer->requests[1]);
    }

    public function testApplicationFailureIsReturnedUnchangedToTheDefaultQueryBoundary(): void
    {
        $failure = new RuntimeException('application authorizer sensitive canary');
        $authorizer = new StatusTenantAuthorizerSpy(OperationStatusAuthorizationDecision::allow(), $failure);
        $request = $this->request(null, null);

        try {
            new OperationStatusAuditTenantAuthorizer($authorizer)->decide($request);
            self::fail('The application authorization failure was swallowed.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testSnapshotAndUnserializeSurfacesAreEmptyAndFailClosed(): void
    {
        $canary = 'guard-authorizer-sensitive-canary';
        $guard = new OperationStatusAuditTenantAuthorizer(
            new StatusTenantAuthorizerSpy(OperationStatusAuthorizationDecision::allow(), new RuntimeException($canary)),
        );

        self::assertSame([], $guard->__debugInfo());
        self::assertSame([], $guard->__serialize());
        self::assertStringNotContainsString($canary, print_r($guard, return: true));
        self::assertStringNotContainsString($canary, var_export($guard, return: true));
        self::assertStringNotContainsString($canary, serialize($guard));

        try {
            $guard->__unserialize([]);
            self::fail('Unserialize was accepted.');
        } catch (OperationStatusQueryException $exception) {
            self::assertSame(OperationStatusQueryException::AUTHORIZATION_FAILED, $exception->queryCode());
            self::assertNull($exception->getPrevious());
        }

        $broken = new ReflectionClass(OperationStatusAuditTenantAuthorizer::class)->newInstanceWithoutConstructor();
        new ReflectionClass(OperationStatusAuditTenantAuthorizer::class)
            ->getProperty('snapshot')
            ->setValue($broken, new \SensitiveParameterValue(['wrong' => 'shape']));
        try {
            $broken->decide($this->request(null, null));
            self::fail('A malformed snapshot was accepted.');
        } catch (OperationStatusQueryException $exception) {
            self::assertSame(OperationStatusQueryException::AUTHORIZATION_FAILED, $exception->queryCode());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testDeclarationUsesOnePrivateSensitiveSnapshot(): void
    {
        $reflection = new ReflectionClass(OperationStatusAuditTenantAuthorizer::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame(
            ['snapshot'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );
        self::assertSame('SensitiveParameterValue', $reflection->getProperty('snapshot')->getType()?->getName());
        self::assertCount(
            1,
            $reflection->getMethod('__construct')->getParameters()[0]->getAttributes(\SensitiveParameter::class),
        );
        self::assertCount(
            1,
            $reflection->getMethod('decide')->getParameters()[0]->getAttributes(\SensitiveParameter::class),
        );
    }

    private function request(?TenantRef $currentTenant, ?TenantRef $originTenant): OperationStatusAuthorizationRequest
    {
        return new OperationStatusAuthorizationRequest(
            OperationId::fromString(self::OPERATION_ID),
            'status.read',
            null,
            null,
            $currentTenant,
            $originTenant,
        );
    }
}

final class StatusTenantAuthorizerSpy implements OperationStatusAuthorizer
{
    public int $calls = 0;

    /** @var list<OperationStatusAuthorizationRequest> */
    public array $requests = [];

    public function __construct(
        private readonly OperationStatusAuthorizationDecision $decision,
        private readonly ?RuntimeException $failure = null,
    ) {}

    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        ++$this->calls;
        $this->requests[] = $request;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->decision;
    }
}

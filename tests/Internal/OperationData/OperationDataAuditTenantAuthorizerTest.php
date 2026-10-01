<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\OperationData;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\OperationData\OperationDataAuditTenantAuthorizer;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationDataReadAuthorizationDecision;
use BlackOps\OperationData\OperationDataReadAuthorizationRequest;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\OperationData\OperationDataResource;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationDataAuditTenantAuthorizerTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testGlobalAndEqualTenantScopesDelegateExactlyOnce(): void
    {
        $delegate = new OperationDataGuardDelegate(OperationDataReadAuthorizationDecision::allow());
        $guard = new OperationDataAuditTenantAuthorizer($delegate);
        $tenant = new TenantRef('customer', 'tenant-a');

        $globalDecision = $guard->decide($this->request(null, null));
        $tenantDecision = $guard->decide($this->request($tenant, new TenantRef('customer', 'tenant-a')));

        self::assertSame($delegate->decision, $globalDecision);
        self::assertSame($delegate->decision, $tenantDecision);
        self::assertSame(2, $delegate->calls);
        self::assertSame($tenantDecision, $delegate->lastDecision);
    }

    public function testNullTypeAndIdMismatchesDenyBeforeTheApplicationAuthorizer(): void
    {
        $delegate = new OperationDataGuardDelegate(OperationDataReadAuthorizationDecision::allow());
        $guard = new OperationDataAuditTenantAuthorizer($delegate);
        $tenant = new TenantRef('customer', 'tenant-a');

        foreach ([
            'global to tenant' => [$tenant, null],
            'tenant to global' => [null, $tenant],
            'different type' => [new TenantRef('account', 'tenant-a'), $tenant],
            'different id' => [new TenantRef('customer', 'tenant-b'), $tenant],
        ] as $label => [$current, $origin]) {
            $decision = $guard->decide($this->request($current, $origin));

            self::assertFalse($decision->isAllowed(), $label);
        }

        self::assertSame(0, $delegate->calls);
    }

    public function testCorruptSnapshotAndUnserializeFailWithAValueFreeLogicException(): void
    {
        $reflection = new ReflectionClass(OperationDataAuditTenantAuthorizer::class);
        $guard = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('snapshot')->setValue($guard, new \SensitiveParameterValue([
            'authorizer' => new \stdClass(),
        ]));

        try {
            $guard->decide($this->request(null, null));
            self::fail('A corrupt authorizer snapshot was accepted.');
        } catch (\LogicException $exception) {
            self::assertSame('Operation data audit authorizer is unavailable.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame([], $guard->__debugInfo());
        self::assertSame([], $guard->__serialize());
        try {
            $guard->__unserialize([]);
            self::fail('Authorizer unserialization was accepted.');
        } catch (\LogicException $exception) {
            self::assertSame('Operation data audit authorizer is unavailable.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    private function request(?TenantRef $currentTenant, ?TenantRef $originTenant): OperationDataReadAuthorizationRequest
    {
        return new OperationDataReadAuthorizationRequest(
            OperationDataResource::CanonicalJournal,
            OperationDataPurpose::fromString('application.view'),
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            new ActorRef('current-user', 'user'),
            $currentTenant,
            new ActorRef('origin-user', 'user'),
            $originTenant,
        );
    }
}

final class OperationDataGuardDelegate implements OperationDataReadAuthorizer
{
    public int $calls = 0;

    public ?OperationDataReadAuthorizationDecision $lastDecision = null;

    public readonly OperationDataReadAuthorizationDecision $decision;

    public function __construct(?OperationDataReadAuthorizationDecision $decision = null)
    {
        $this->decision = $decision ?? OperationDataReadAuthorizationDecision::allow();
    }

    public function decide(
        #[\SensitiveParameter]
        OperationDataReadAuthorizationRequest $request,
    ): OperationDataReadAuthorizationDecision {
        ++$this->calls;
        $this->lastDecision = $this->decision;

        return $this->decision;
    }
}

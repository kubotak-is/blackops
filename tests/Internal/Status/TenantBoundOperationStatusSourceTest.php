<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Status;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Status\OperationStatusDetailResult;
use BlackOps\Internal\Status\OperationStatusDetailUnavailable;
use BlackOps\Internal\Status\OperationStatusSourceException;
use BlackOps\Internal\Status\OperationStatusSubject;
use BlackOps\Internal\Status\TenantBoundOperationStatusSource;
use BlackOps\Internal\Status\TenantScopedOperationStatusSource;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TenantBoundOperationStatusSourceTest extends TestCase
{
    public function testDelegatesResultsAndExceptionsWithTheCapturedTenantOnly(): void
    {
        $tenant = new TenantRef('customer', 'tenant-a');
        $operationId = OperationId::fromString('019f70ab-1000-7000-8000-000000000001');
        $subject = new OperationStatusSubject($operationId, 'status.bound', null, $tenant);
        $detail = new OperationStatusDetailUnavailable();
        $source = new BoundTestSource($subject, $detail);
        $bound = new TenantBoundOperationStatusSource($source, $tenant);

        self::assertSame($subject, $bound->findSubject($operationId));
        self::assertSame($detail, $bound->readDetail($subject));
        self::assertSame(
            [
                ['find',   $operationId, $tenant],
                ['detail', $subject,     $tenant],
            ],
            $source->calls,
        );
        self::assertSame(0, $source->legacyCalls);
        self::assertSame(1, $source->constructionCalls);

        $findFailure = OperationStatusSourceException::integrityFailed();
        $source->findFailure = $findFailure;
        try {
            $bound->findSubject($operationId);
            self::fail('The bound find failure was swallowed.');
        } catch (OperationStatusSourceException $exception) {
            self::assertSame($findFailure, $exception);
        }

        $detailFailure = OperationStatusSourceException::decodeFailed();
        $source->detailFailure = $detailFailure;
        try {
            $bound->readDetail($subject);
            self::fail('The bound detail failure was swallowed.');
        } catch (OperationStatusSourceException $exception) {
            self::assertSame($detailFailure, $exception);
        }
        self::assertSame(0, $source->legacyCalls);
    }

    public function testGlobalNullAndTwoAlternatingViewsRemainIndependent(): void
    {
        $operationId = OperationId::fromString('019f70ab-1000-7000-8000-000000000002');
        $globalSubject = new OperationStatusSubject($operationId, 'status.bound', null, null);
        $source = new BoundTestSource($globalSubject, new OperationStatusDetailUnavailable());
        $global = new TenantBoundOperationStatusSource($source, null);
        $tenantA = new TenantRef('customer', 'tenant-a');
        $tenantB = new TenantRef('customer', 'tenant-b');
        $viewA = new TenantBoundOperationStatusSource($source, $tenantA);
        $viewB = new TenantBoundOperationStatusSource($source, $tenantB);

        $global->findSubject($operationId);
        $viewA->findSubject($operationId);
        $viewB->findSubject($operationId);
        $viewA->readDetail($globalSubject);
        $viewB->readDetail($globalSubject);
        $viewA->findSubject($operationId);

        self::assertSame(
            [
                ['find',   $operationId,   null],
                ['find',   $operationId,   $tenantA],
                ['find',   $operationId,   $tenantB],
                ['detail', $globalSubject, $tenantA],
                ['detail', $globalSubject, $tenantB],
                ['find',   $operationId,   $tenantA],
            ],
            $source->calls,
        );
        self::assertSame(0, $source->legacyCalls);

        $clone = clone $viewA;
        $viewB->findSubject($operationId);
        $clone->findSubject($operationId);
        $viewA->findSubject($operationId);
        self::assertSame($tenantB, $source->calls[count($source->calls) - 3][2]);
        self::assertSame($tenantA, $source->calls[count($source->calls) - 2][2]);
        self::assertSame($tenantA, $source->calls[count($source->calls) - 1][2]);
    }

    public function testConstructionAndDebugSerializationDoNotExposeCapturedValues(): void
    {
        $tenant = new TenantRef('customer', 'bound-secret-tenant');
        $subject = new OperationStatusSubject(
            OperationId::fromString('019f70ab-1000-7000-8000-000000000003'),
            'status.bound',
            null,
            $tenant,
        );
        $source = new BoundTestSource($subject, new OperationStatusDetailUnavailable());
        $bound = new TenantBoundOperationStatusSource($source, $tenant);

        self::assertSame([], $bound->__debugInfo());
        self::assertSame([], $bound->__serialize());
        $serialized = $this->capture(static fn(): string => serialize($bound));
        self::assertStringNotContainsString('bound-secret-tenant', $serialized);
        self::assertStringNotContainsString(BoundTestSource::CANARY, $serialized);
        self::assertStringNotContainsString(BoundTestSource::CANARY, print_r($bound, return: true));
        self::assertStringNotContainsString('bound-secret-tenant', var_export($bound, return: true));

        try {
            unserialize($serialized, ['allowed_classes' => true]);
            self::fail('A serialized bound source was accepted.');
        } catch (OperationStatusSourceException $exception) {
            $this->assertSafeStorageFailure($exception);
        }

        try {
            $bound->__unserialize([]);
            self::fail('Manual bound unserialization was accepted.');
        } catch (OperationStatusSourceException $exception) {
            $this->assertSafeStorageFailure($exception);
        }
        self::assertSame(0, $source->legacyCalls);
    }

    public function testMalformedAndUninitializedSnapshotsFailClosedWithoutDelegation(): void
    {
        $operationId = OperationId::fromString('019f70ab-1000-7000-8000-000000000004');
        $subject = new OperationStatusSubject($operationId, 'status.bound', null);
        $source = new BoundTestSource($subject, new OperationStatusDetailUnavailable());

        $malformed = new ReflectionClass(TenantBoundOperationStatusSource::class)->newInstanceWithoutConstructor();
        new ReflectionClass(TenantBoundOperationStatusSource::class)
            ->getProperty('snapshot')
            ->setValue($malformed, new \SensitiveParameterValue(['source' => $source]));
        $this->assertBrokenBound($malformed, $operationId, $subject);

        $wrongTypes = new ReflectionClass(TenantBoundOperationStatusSource::class)->newInstanceWithoutConstructor();
        new ReflectionClass(TenantBoundOperationStatusSource::class)
            ->getProperty('snapshot')
            ->setValue($wrongTypes, new \SensitiveParameterValue([
                'source' => $source,
                'tenant' => 'not-a-tenant',
            ]));
        $this->assertBrokenBound($wrongTypes, $operationId, $subject);

        $uninitialized = new ReflectionClass(TenantBoundOperationStatusSource::class)->newInstanceWithoutConstructor();
        $this->assertBrokenBound($uninitialized, $operationId, $subject);
        self::assertSame(0, $source->legacyCalls);
    }

    private function assertBrokenBound(
        TenantBoundOperationStatusSource $bound,
        OperationId $operationId,
        OperationStatusSubject $subject,
    ): void {
        foreach ([
            static fn() => $bound->findSubject($operationId),
            static fn() => $bound->readDetail($subject),
        ] as $call) {
            try {
                $call();
                self::fail('Malformed bound snapshot was accepted.');
            } catch (OperationStatusSourceException $exception) {
                $this->assertSafeStorageFailure($exception);
            }
        }
    }

    private function assertSafeStorageFailure(OperationStatusSourceException $exception): void
    {
        self::assertSame('Operation status source failed.', $exception->getMessage());
        self::assertSame('Storage', $exception->failure->name);
        self::assertNull($exception->getPrevious());
        self::assertStringNotContainsString(BoundTestSource::CANARY, $exception->getTraceAsString());
    }

    private function capture(\Closure $call): string
    {
        try {
            return $call();
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }
}

final class BoundTestSource implements TenantScopedOperationStatusSource
{
    public const string CANARY = 'bound-source-canary';

    /** @var list<array{0:string,1:OperationId|OperationStatusSubject,2:TenantRef|null}> */
    public array $calls = [];

    public int $legacyCalls = 0;

    public int $constructionCalls = 0;

    public ?OperationStatusSourceException $findFailure = null;

    public ?OperationStatusSourceException $detailFailure = null;

    public function __construct(
        private readonly OperationStatusSubject $subject,
        private readonly OperationStatusDetailResult $detail,
    ) {
        ++$this->constructionCalls;
    }

    public function findSubject(OperationId $operationId): ?OperationStatusSubject
    {
        ++$this->legacyCalls;
        throw new \RuntimeException(self::CANARY);
    }

    public function readDetail(OperationStatusSubject $subject): OperationStatusDetailResult
    {
        ++$this->legacyCalls;
        throw new \RuntimeException(self::CANARY);
    }

    public function findSubjectForTenant(
        #[\SensitiveParameter]
        OperationId $operationId,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): ?OperationStatusSubject {
        $this->calls[] = ['find', $operationId, $tenant];
        if ($this->findFailure !== null) {
            throw $this->findFailure;
        }

        return $this->subject;
    }

    public function readDetailForTenant(
        #[\SensitiveParameter]
        OperationStatusSubject $subject,
        #[\SensitiveParameter]
        ?TenantRef $tenant,
    ): OperationStatusDetailResult {
        $this->calls[] = ['detail', $subject, $tenant];
        if ($this->detailFailure !== null) {
            throw $this->detailFailure;
        }

        return $this->detail;
    }
}

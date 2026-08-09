<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Aop\FrameworkProxyContract;

require_once __DIR__ . '/../../../Fixtures/Aop/FrameworkProxyContract/ContractFixtures.php';

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContract;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContractException;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnership;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnershipGuard;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnershipMarker;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\OperationService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\PrecedenceService;
use PHPUnit\Framework\TestCase;

final class FrameworkProxyOwnershipGuardTest extends TestCase
{
    public function testMatchingMarkerAndProfileAreAccepted(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(OperationService::class);

        new FrameworkProxyOwnershipGuard()->assertCompatible($metadata, $metadata->marker());
        new FrameworkProxyOwnershipGuard()->assertProfile('framework', FrameworkProxyProfile::framework(), $metadata);
        self::assertTrue(true);
    }

    public function testDifferentProfileIsRejectedWithoutRuntimeFallback(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(PrecedenceService::class, 'framework');
        $marker = new FrameworkProxyOwnershipMarker(
            $metadata->sourceClass,
            FrameworkProxyOwnership::SERVICE,
            FrameworkProxyProfile::ray(),
            false,
        );

        try {
            new FrameworkProxyOwnershipGuard()->assertCompatible($metadata, $marker);
            self::fail('Expected profile conflict.');
        } catch (FrameworkProxyContractException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::MODE_CONFLICT, $exception->diagnostic->code);
            self::assertSame(PrecedenceService::class, $exception->diagnostic->sourceClass);
        }
    }

    public function testSourceClassMismatchIsRejected(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(PrecedenceService::class);
        $marker = new FrameworkProxyOwnershipMarker(
            'OtherService',
            FrameworkProxyOwnership::SERVICE,
            FrameworkProxyProfile::framework(),
            false,
        );

        $this->assertConflict($metadata, $marker, FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT);
    }

    public function testOwnershipMismatchIsRejected(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(PrecedenceService::class);
        $marker = new FrameworkProxyOwnershipMarker(
            $metadata->sourceClass,
            FrameworkProxyOwnership::OPERATION,
            FrameworkProxyProfile::framework(),
            true,
        );

        $this->assertConflict($metadata, $marker, FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT);
    }

    public function testOperationWithoutLifecycleOwnershipIsRejected(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(OperationService::class);
        $marker = new FrameworkProxyOwnershipMarker(
            $metadata->sourceClass,
            FrameworkProxyOwnership::OPERATION,
            FrameworkProxyProfile::framework(),
            false,
        );

        $this->assertConflict($metadata, $marker, FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT);
    }

    public function testGeneralServiceCannotClaimLifecycleOwnership(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(PrecedenceService::class);
        $marker = new FrameworkProxyOwnershipMarker(
            $metadata->sourceClass,
            FrameworkProxyOwnership::SERVICE,
            FrameworkProxyProfile::framework(),
            true,
        );

        $this->assertConflict($metadata, $marker, FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT);
    }

    private function assertConflict(
        \BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyMetadata $metadata,
        FrameworkProxyOwnershipMarker $marker,
        string $code,
    ): void {
        try {
            new FrameworkProxyOwnershipGuard()->assertCompatible($metadata, $marker);
            self::fail('Expected ownership conflict.');
        } catch (FrameworkProxyContractException $exception) {
            self::assertSame($code, $exception->diagnostic->code);
        }
    }
}

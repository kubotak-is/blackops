<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

require_once __DIR__ . '/../../Fixtures/Aop/FrameworkProxyRuntime/FrameworkProxyRuntimeFixtures.php';

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContract;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnership;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnershipMarker;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionBinding;
use BlackOps\Internal\Execution\FrameworkProxyOperationOwnershipGuard;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyRuntime\FrameworkRuntimeOperation;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyRuntime\FrameworkRuntimeService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrameworkProxyOperationOwnershipGuardTest extends TestCase
{
    #[DataProvider('operationStrategies')]
    public function testInlineDeferredAndSelfHandledOperationRemainLifecycleOwned(
        string $strategy,
        bool $selfHandled,
    ): void {
        $binding = $this->binding(FrameworkRuntimeOperation::class, FrameworkProxyOwnership::OPERATION);
        $metadata = new OperationMetadata(
            'operation.runtime',
            FrameworkRuntimeOperation::class,
            'RuntimeValue',
            'RuntimeHandler',
            'RuntimeOutcome',
            $strategy,
            typedSelfHandled: $selfHandled,
            transactionConnection: 'app',
        );
        $guard = new FrameworkProxyOperationOwnershipGuard();

        $guard->assertOperationMetadata($binding, $metadata);

        self::assertTrue($guard->lifecycleOwned($binding));
    }

    /** @return iterable<string,array{string,bool}> */
    public static function operationStrategies(): iterable
    {
        yield 'inline' => [Inline::class, false];
        yield 'deferred' => [Deferred::class, false];
        yield 'self handled' => [Inline::class, true];
    }

    public function testGeneralServiceIsNotLifecycleOwned(): void
    {
        $binding = $this->binding(FrameworkRuntimeService::class, FrameworkProxyOwnership::SERVICE);

        self::assertFalse(new FrameworkProxyOperationOwnershipGuard()->lifecycleOwned($binding));
    }

    public function testMetadataForDifferentOperationCannotReuseBinding(): void
    {
        $binding = $this->binding(FrameworkRuntimeOperation::class, FrameworkProxyOwnership::OPERATION);
        $metadata = new OperationMetadata(
            'other.operation',
            'OtherOperation',
            'RuntimeValue',
            'RuntimeHandler',
            'RuntimeOutcome',
            Inline::class,
            transactionConnection: 'app',
        );

        try {
            new FrameworkProxyOperationOwnershipGuard()->assertOperationMetadata($binding, $metadata);
            self::fail('Expected operation ownership conflict.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $exception->getMessage());
        }
    }

    public function testOperationMarkerWithoutLifecycleOwnershipIsRejected(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(
            FrameworkRuntimeOperation::class,
            FrameworkProxyProfile::FRAMEWORK,
            'operation',
            'runtime-test',
            'app',
            ['app'],
        );
        $binding = new FrameworkProxyDefinitionBinding(
            'operation',
            FrameworkRuntimeOperation::class,
            FrameworkRuntimeOperation::class,
            $metadata,
            new FrameworkProxyOwnershipMarker(
                FrameworkRuntimeOperation::class,
                FrameworkProxyOwnership::OPERATION,
                FrameworkProxyProfile::framework(),
                false,
            ),
        );

        try {
            new FrameworkProxyOperationOwnershipGuard()->assertFrameworkBinding($binding);
            self::fail('Expected lifecycle ownership conflict.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $exception->getMessage());
        }
    }

    public function testOperationMetadataConnectionMustMatchResolvedMethodConnection(): void
    {
        $binding = $this->binding(FrameworkRuntimeOperation::class, FrameworkProxyOwnership::OPERATION);
        $metadata = new OperationMetadata(
            'operation.runtime',
            FrameworkRuntimeOperation::class,
            'RuntimeValue',
            'RuntimeHandler',
            'RuntimeOutcome',
            Inline::class,
            transactionConnection: 'analytics',
        );

        try {
            new FrameworkProxyOperationOwnershipGuard()->assertOperationMetadata($binding, $metadata);
            self::fail('Expected resolved connection conflict.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $exception->getMessage());
        }
    }

    public function testBindingSourceClassMustMatchMetadataSource(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(
            FrameworkRuntimeService::class,
            FrameworkProxyProfile::FRAMEWORK,
            'service',
            'runtime-test',
            'app',
            ['app'],
        );
        $binding = new FrameworkProxyDefinitionBinding(
            'service',
            'TamperedSource',
            FrameworkRuntimeService::class,
            $metadata,
            $metadata->marker(),
        );

        try {
            new FrameworkProxyOperationOwnershipGuard()->assertFrameworkBinding($binding);
            self::fail('Expected source ownership conflict.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT, $exception->getMessage());
        }
    }

    private function binding(string $class, FrameworkProxyOwnership $ownership): FrameworkProxyDefinitionBinding
    {
        $metadata = new FrameworkProxyContract()->inspect(
            $class,
            FrameworkProxyProfile::FRAMEWORK,
            'runtime',
            'runtime-test',
            'app',
            ['app'],
        );
        if ($metadata->ownership !== $ownership) {
            self::fail('Fixture ownership does not match the requested binding.');
        }

        return new FrameworkProxyDefinitionBinding('runtime', $class, $class, $metadata, $metadata->marker());
    }
}

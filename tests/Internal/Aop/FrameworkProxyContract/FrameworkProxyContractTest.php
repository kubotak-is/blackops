<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Aop\FrameworkProxyContract;

require_once __DIR__ . '/../../../Fixtures/Aop/FrameworkProxyContract/ContractFixtures.php';

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContract;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContractException;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnership;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\AbstractService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\AfterCommitGeneratorService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\AfterCommitReferenceParameterService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\AfterCommitReferenceReturnService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\AfterCommitReturnService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ClassAfterCommitService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\CollisionDefaultService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ComplexTypeService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ConflictService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ConstructorTargetService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ContractInterface;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ContractTrait;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\DestructorTargetService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\DuplicateAfterCommitAttributeService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\DuplicateAttributeService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\EnumDefaultService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\FinalMethodService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\FinalService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\GeneratorService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\InaccessibleDefaultService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\InheritedTypeService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\NoDefaultService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ObjectDefaultService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\OperationService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ParameterTargetService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\PrecedenceService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\PrivateAfterCommitService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\PrivateDefaultChildService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\PropertyTargetService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ReadonlyService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ReferenceParameterService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ReferenceReturnService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\StaticService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\UnrelatedTargetService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\VisibilityService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrameworkProxyContractTest extends TestCase
{
    public function testAttributePrecedenceAndImmutableOwnershipMetadata(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(
            PrecedenceService::class,
            FrameworkProxyProfile::framework(),
            'service.precedence',
            'build-1',
        );

        self::assertSame(PrecedenceService::class, $metadata->sourceClass);
        self::assertSame(FrameworkProxyOwnership::SERVICE, $metadata->ownership);
        self::assertTrue($metadata->classTransactional);
        self::assertSame('app', $metadata->method('inherited')?->transactionalConnection);
        self::assertSame('analytics', $metadata->method('override')?->transactionalConnection);
        self::assertNull($metadata->method('bareOverride')?->transactionalConnection);
        self::assertSame(
            ['BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyContract\\UnrelatedAttribute'],
            $metadata->method('unrelated')?->unrelatedAttributes,
        );
        self::assertTrue($metadata->method('callback')?->afterCommit);
        self::assertSame('framework', $metadata->profile->value);
    }

    public function testBuildContextResolvesMethodOverrideBeforeClassConnection(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(
            PrecedenceService::class,
            'framework',
            'service.precedence',
            'build-precedence',
            'default',
            ['app', 'analytics', 'default'],
        );

        self::assertSame('app', $metadata->method('inherited')?->transactionalConnection);
        self::assertSame('analytics', $metadata->method('override')?->transactionalConnection);
        self::assertSame('default', $metadata->method('bareOverride')?->transactionalConnection);
    }

    public function testOperationMetadataMarksLifecycleOwnership(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(OperationService::class);

        self::assertSame(FrameworkProxyOwnership::OPERATION, $metadata->ownership);
        self::assertTrue($metadata->marker()->lifecycleOwned);
    }

    public function testSupportedMetadataPreservesComplexTypesAndDefaultPresence(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(ComplexTypeService::class);
        $method = $metadata->method('complex');

        self::assertNotNull($method);
        self::assertSame(
            'BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyContract\\LeftValue|BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyContract\\RightValue',
            $method->parameters[0]['type'],
        );
        self::assertFalse($method->parameters[0]['hasDefault']);
        self::assertTrue($method->parameters[1]['hasDefault']);
        self::assertSame(
            '(BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyContract\\FirstValue&BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyContract\\SecondValue)|null',
            $method->parameters[1]['type'],
        );
        self::assertSame('static', $method->returnType);
        self::assertSame('never', $metadata->method('neverReturns')?->returnType);
        self::assertSame('self', $metadata->method('defaults')?->returnType);
        self::assertTrue(
            new FrameworkProxyContract()
                ->inspect(PrecedenceService::class)
                ->method('inherited')
                ?->parameters[1]['variadic'],
        );
        self::assertSame(3, $metadata->method('defaults')?->parameters[0]['default']);
        self::assertSame(['value'], $metadata->method('defaults')?->parameters[1]['default']);
        self::assertSame('PHP_INT_SIZE', $metadata->method('defaults')?->parameters[2]['defaultConstantName']);
        self::assertSame(
            ['BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyContract\\UnrelatedWideAttribute'],
            new FrameworkProxyContract()->inspect(UnrelatedTargetService::class)->method('run')?->unrelatedAttributes,
        );
        self::assertSame(
            'parent',
            new FrameworkProxyContract()->inspect(InheritedTypeService::class)->method('parentType')?->returnType,
        );
        self::assertSame(
            'BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyContract\\DefaultEnum::VALUE',
            new FrameworkProxyContract()
                ->inspect(EnumDefaultService::class)
                ->method('run')
                ?->parameters[0]['defaultConstantName'],
        );
        self::assertSame(
            'BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyContract\\PublicDefaultOwnerService::DEFAULT',
            new FrameworkProxyContract()
                ->inspect(CollisionDefaultService::class)
                ->method('run')
                ?->parameters[0]['defaultConstantName'],
        );
        $readonly = new FrameworkProxyContract()->inspect(ReadonlyService::class);
        self::assertSame(ReadonlyService::class, $readonly->sourceClass);
        self::assertTrue($readonly->readonlyClass);

        $required = new FrameworkProxyContract()
            ->inspect(NoDefaultService::class)
            ->method('run');
        self::assertNotNull($required);
        self::assertFalse($required->parameters[0]['hasDefault']);
        self::assertTrue($required->parameters[1]['hasDefault']);
    }

    public function testConcreteClassWithoutAttributesIsNotAProxyTarget(): void
    {
        $metadata = new FrameworkProxyContract()->inspect(\stdClass::class);

        self::assertFalse($metadata->proxyTarget);
        self::assertSame([], $metadata->methods);
    }

    public function testAttributeDiagnosticsKeepOnlySafeContext(): void
    {
        try {
            new FrameworkProxyContract()->inspect(
                ClassAfterCommitService::class,
                'framework',
                'service.safe',
                'build-safe',
            );
            self::fail('Expected invalid class attribute.');
        } catch (FrameworkProxyContractException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET, $exception->diagnostic->code);
            self::assertSame(
                [
                    'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
                    'service_id' => 'service.safe',
                    'source_class' => ClassAfterCommitService::class,
                    'attribute' => 'BlackOps\\Database\\Attribute\\AfterCommit',
                    'build_id' => 'build-safe',
                ],
                $exception->diagnostic->toArray(),
            );
        }
    }

    public function testRepeatedAttributeDiagnosticsKeepSafeContext(): void
    {
        try {
            new FrameworkProxyContract()->inspect(
                DuplicateAttributeService::class,
                'framework',
                'service.repeat',
                'build-repeat',
            );
            self::fail('Expected repeated attribute rejection.');
        } catch (FrameworkProxyContractException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::ATTRIBUTE_DUPLICATE, $exception->diagnostic->code);
            self::assertSame('service.repeat', $exception->diagnostic->serviceId);
            self::assertSame('build-repeat', $exception->diagnostic->buildId);
        }
    }

    public function testUnknownConnectionUsesSafeStableCode(): void
    {
        try {
            new FrameworkProxyContract()->inspect(
                PrecedenceService::class,
                'framework',
                'service.db',
                'build-db',
                'missing',
                ['app'],
            );
            self::fail('Expected unknown connection.');
        } catch (FrameworkProxyContractException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::CONNECTION_UNKNOWN, $exception->diagnostic->code);
            self::assertSame('service.db', $exception->diagnostic->serviceId);
            self::assertArrayNotHasKey('connection', $exception->diagnostic->toArray());
        }
    }

    #[DataProvider('rejectProvider')]
    public function testRejectMatrix(string $class, string $code): void
    {
        try {
            new FrameworkProxyContract()->inspect($class);
            self::fail('Expected contract rejection.');
        } catch (FrameworkProxyContractException $exception) {
            self::assertSame($code, $exception->diagnostic->code);
            self::assertSame($class, $exception->diagnostic->sourceClass);
            self::assertNull($exception->diagnostic->buildId);
        }
    }

    /** @return iterable<string,array{class:class-string,code:string}> */
    public static function rejectProvider(): iterable
    {
        yield 'final class' => [
            'class' => FinalService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_FINAL_CLASS,
        ];
        yield 'final method' => [
            'class' => FinalMethodService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_FINAL_METHOD,
        ];
        yield 'visibility' => [
            'class' => VisibilityService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_VISIBILITY,
        ];
        yield 'static' => ['class' => StaticService::class, 'code' => FrameworkProxyDiagnosticCode::SIGNATURE_STATIC];
        yield 'generator' => [
            'class' => GeneratorService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_GENERATOR,
        ];
        yield 'reference return' => [
            'class' => ReferenceReturnService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_REFERENCE_RETURN,
        ];
        yield 'reference parameter' => [
            'class' => ReferenceParameterService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_REFERENCE_PARAMETER,
        ];
        yield 'conflict' => [
            'class' => ConflictService::class,
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_CONFLICT,
        ];
        yield 'duplicate transactional' => [
            'class' => DuplicateAttributeService::class,
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_DUPLICATE,
        ];
        yield 'duplicate after commit' => [
            'class' => DuplicateAfterCommitAttributeService::class,
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_DUPLICATE,
        ];
        yield 'after commit return' => [
            'class' => AfterCommitReturnService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_AFTER_COMMIT_RETURN,
        ];
        yield 'after commit generator' => [
            'class' => AfterCommitGeneratorService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_GENERATOR,
        ];
        yield 'after commit reference return' => [
            'class' => AfterCommitReferenceReturnService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_REFERENCE_RETURN,
        ];
        yield 'after commit reference parameter' => [
            'class' => AfterCommitReferenceParameterService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_REFERENCE_PARAMETER,
        ];
        yield 'constructor' => [
            'class' => ConstructorTargetService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_VISIBILITY,
        ];
        yield 'destructor' => [
            'class' => DestructorTargetService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_VISIBILITY,
        ];
        yield 'private after commit' => [
            'class' => PrivateAfterCommitService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_VISIBILITY,
        ];
        yield 'class after commit' => [
            'class' => ClassAfterCommitService::class,
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
        ];
        yield 'property target' => [
            'class' => PropertyTargetService::class,
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
        ];
        yield 'parameter target' => [
            'class' => ParameterTargetService::class,
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
        ];
        yield 'abstract target' => [
            'class' => AbstractService::class,
            'code' => FrameworkProxyDiagnosticCode::TARGET_NOT_CONCRETE,
        ];
        yield 'interface target' => [
            'class' => ContractInterface::class,
            'code' => FrameworkProxyDiagnosticCode::TARGET_NOT_CONCRETE,
        ];
        yield 'trait target' => [
            'class' => ContractTrait::class,
            'code' => FrameworkProxyDiagnosticCode::TARGET_NOT_CONCRETE,
        ];
        yield 'object default' => [
            'class' => ObjectDefaultService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_DEFAULT_VALUE,
        ];
        yield 'inaccessible default' => [
            'class' => InaccessibleDefaultService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_DEFAULT_VALUE,
        ];
        yield 'inherited inaccessible default' => [
            'class' => PrivateDefaultChildService::class,
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_DEFAULT_VALUE,
        ];
    }
}

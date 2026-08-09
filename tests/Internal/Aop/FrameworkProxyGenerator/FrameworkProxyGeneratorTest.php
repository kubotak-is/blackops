<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Aop\FrameworkProxyGenerator;

require_once __DIR__ . '/../../../Fixtures/Aop/FrameworkProxyContract/ContractFixtures.php';
require_once __DIR__ . '/../../../Fixtures/Aop/FrameworkProxyGenerator/GeneratorFixtures.php';

use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactBuilder;
use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContract;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerationTarget;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerator;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyInvocation;
use BlackOps\Internal\Runtime\FrameworkProxyArtifactLoader;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ComplexTypeService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\DefaultEnum;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\FinalService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\FirstValue;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\InheritedTypeService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\LeftValue;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\OperationService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ParentTypeService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\PrecedenceService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ReadonlyService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\RightValue;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\SecondValue;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\UnrelatedWideAttribute;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyGenerator\EnumDefaultService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyGenerator\GeneratedMemberCollisionService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyGenerator\IntersectionService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyGenerator\OperationAfterCommitService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyGenerator\UnrelatedTargetService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class FrameworkProxyGeneratorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blackops-framework-proxy-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testBatchManifestAndReadonlyInitializer(): void
    {
        $result = new FrameworkProxyGenerator()->generateBatch(
            [ComplexTypeService::class, ReadonlyService::class],
            'build-a',
            $this->root,
        );
        self::assertCount(2, $result->manifest->proxies);
        self::assertStringContainsString('build-a-', $result->directory);
        self::assertFileExists($result->directory . '/manifest.json');
        $manifest = new FrameworkProxyArtifactLoader()->load(
            $result->directory,
            'build-a',
            $result->manifest->manifestHash,
            'framework',
        );
        self::assertSame($result->manifest->manifestHash, $manifest->manifestHash);
    }

    public function testTargetContextChangesImmutableIdentity(): void
    {
        $generator = new FrameworkProxyGenerator();
        $first = $generator->generateBatch(
            [
                new FrameworkProxyGenerationTarget(ComplexTypeService::class, serviceId: 'service.a'),
            ],
            'build-context',
            $this->root,
        );
        $second = $generator->generateBatch(
            [
                new FrameworkProxyGenerationTarget(ComplexTypeService::class, serviceId: 'service.b'),
            ],
            'build-context',
            $this->root,
        );
        self::assertNotSame($first->directory, $second->directory);
        self::assertNotSame($first->classMap[ComplexTypeService::class], $second->classMap[ComplexTypeService::class]);
    }

    public function testExternalInputAndConnectionContextIdentity(): void
    {
        $generator = new FrameworkProxyGenerator();
        $first = $generator->generateBatch(
            [new FrameworkProxyGenerationTarget(
                ComplexTypeService::class,
                defaultConnection: 'app',
                connectionNames: ['app'],
            )],
            'build-input',
            $this->root,
            inputHashes: ['config' => str_repeat('a', 64)],
        );
        $same = $generator->generateBatch(
            [new FrameworkProxyGenerationTarget(ComplexTypeService::class, defaultConnection: 'app', connectionNames: [
                'app' => true,
            ])],
            'build-input',
            $this->root,
            inputHashes: ['config' => str_repeat('a', 64)],
        );
        self::assertSame($first->directory, $same->directory);
        $changed = $generator->generateBatch(
            [new FrameworkProxyGenerationTarget(
                ComplexTypeService::class,
                defaultConnection: 'app',
                connectionNames: ['app'],
            )],
            'build-input',
            $this->root,
            inputHashes: ['config' => str_repeat('b', 64)],
        );
        self::assertNotSame($first->directory, $changed->directory);
    }

    public function testImmutableReuseAndActivePreviousRetention(): void
    {
        $generator = new FrameworkProxyGenerator();
        $a = $generator->generate(ComplexTypeService::class, 'build-a', $this->root);
        $again = $generator->generate(ComplexTypeService::class, 'build-a', $this->root);
        self::assertSame($a->directory, $again->directory);
        $b = $generator->generate(ComplexTypeService::class, 'build-b', $this->root);
        $c = $generator->generate(ComplexTypeService::class, 'build-c', $this->root);
        self::assertDirectoryDoesNotExist($a->directory);
        self::assertDirectoryExists($b->directory);
        self::assertDirectoryExists($c->directory);
        $index = json_decode((string) file_get_contents($this->root . '/index.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(basename($c->directory), $index['active']);
        self::assertSame(basename($b->directory), $index['previous']);
    }

    public function testRollbackReselectsRetainedPreviousUnit(): void
    {
        $generator = new FrameworkProxyGenerator();
        $a = $generator->generate(ComplexTypeService::class, 'rollback-a', $this->root);
        $b = $generator->generate(ComplexTypeService::class, 'rollback-b', $this->root);
        $rollback = $generator->generate(ComplexTypeService::class, 'rollback-a', $this->root);
        self::assertSame($a->directory, $rollback->directory);
        $index = json_decode((string) file_get_contents($this->root . '/index.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(basename($a->directory), $index['active']);
        self::assertSame(basename($b->directory), $index['previous']);
    }

    public function testTamperedExistingIdentityPreservesIndex(): void
    {
        $generator = new FrameworkProxyGenerator();
        $result = $generator->generate(ComplexTypeService::class, 'tampered-existing', $this->root);
        $indexBefore = (string) file_get_contents($this->root . '/index.json');
        $proxyPath = (string) glob($result->directory . '/proxies/*.php')[0];
        file_put_contents($proxyPath, "<?php\n");
        try {
            $generator->generate(ComplexTypeService::class, 'tampered-existing', $this->root);
            self::fail('Expected tampered immutable unit rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('BO_PROXY_ARTIFACT_FILE_HASH', $exception->getMessage());
            self::assertSame($indexBefore, (string) file_get_contents($this->root . '/index.json'));
            self::assertSame([], glob($this->root . '/.staging-*'));
        }
    }

    public function testInvalidNewBuildPreservesLastKnownGoodAndConcurrentStaging(): void
    {
        $generator = new FrameworkProxyGenerator();
        $a = $generator->generate(ComplexTypeService::class, 'failed-a', $this->root);
        $b = $generator->generate(ComplexTypeService::class, 'failed-b', $this->root);
        $indexBefore = (string) file_get_contents($this->root . '/index.json');
        $concurrent = $this->root . '/.staging-concurrent';
        mkdir($concurrent, 0o755, true);
        file_put_contents($concurrent . '/marker', 'keep');
        $reflection = new ReflectionClass(ComplexTypeService::class);
        $metadata = new FrameworkProxyContract()->inspect(
            $reflection,
            FrameworkProxyProfile::framework(),
            null,
            'failed-invalid',
        );
        try {
            new FrameworkProxyArtifactBuilder()->publishBatch(
                $this->root,
                'failed-invalid',
                FrameworkProxyProfile::framework(),
                FrameworkProxyGenerator::GENERATOR_VERSION,
                [[$reflection, $metadata, 'InvalidGeneratedProxy', '<?php invalid']],
            );
            self::fail('Expected invalid generated PHP rejection.');
        } catch (\RuntimeException $exception) {
            self::assertSame(FrameworkProxyArtifactDiagnosticCode::SYNTAX_INVALID, $exception->getMessage());
            self::assertSame($indexBefore, (string) file_get_contents($this->root . '/index.json'));
            self::assertDirectoryExists($a->directory);
            self::assertDirectoryExists($b->directory);
            self::assertFileExists($concurrent . '/marker');
            self::assertSame([], glob($this->root . '/.staging-failed-invalid-*'));
        }
    }

    public function testValidButWrongProxyDeclarationUsesClassMismatchAndPreservesLastKnownGood(): void
    {
        $generator = new FrameworkProxyGenerator();
        $knownGood = $generator->generate(ComplexTypeService::class, 'class-mismatch-good', $this->root);
        $indexBefore = (string) file_get_contents($this->root . '/index.json');
        $reflection = new ReflectionClass(ComplexTypeService::class);
        $metadata = new FrameworkProxyContract()->inspect(
            $reflection,
            FrameworkProxyProfile::framework(),
            null,
            'class-mismatch-bad',
        );

        try {
            new FrameworkProxyArtifactBuilder()->publishBatch(
                $this->root,
                'class-mismatch-bad',
                FrameworkProxyProfile::framework(),
                FrameworkProxyGenerator::GENERATOR_VERSION,
                [[
                    $reflection,
                    $metadata,
                    'ExpectedProxyClass',
                    "<?php\n// class ExpectedProxyClass extends \\BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyContract\\ComplexTypeService {}\nclass ActuallyWrong {}\n",
                ]],
            );
            self::fail('Expected actual class declaration rejection.');
        } catch (\RuntimeException $exception) {
            self::assertSame(FrameworkProxyArtifactDiagnosticCode::CLASS_MISMATCH, $exception->getMessage());
            self::assertSame($indexBefore, (string) file_get_contents($this->root . '/index.json'));
            self::assertDirectoryExists($knownGood->directory);
            self::assertSame([], glob($this->root . '/.staging-class-mismatch-bad-*'));
        }
    }

    public function testInheritedMethodDeclaringFileParticipatesInArtifactIdentity(): void
    {
        $parentPath = $this->root . '/InheritedParent.php';
        $childPath = $this->root . '/InheritedChild.php';
        $parent = "<?php\nnamespace DynamicInherited;\nclass InheritedParent {\n    #[\\BlackOps\\Database\\Attribute\\Transactional]\n    public function run(): string { return 'A'; }\n}\n";
        $child = "<?php\nnamespace DynamicInherited;\nrequire_once __DIR__ . '/InheritedParent.php';\nclass InheritedChild extends InheritedParent {}\n";
        file_put_contents($parentPath, $parent);
        file_put_contents($childPath, $child);
        require_once $childPath;
        $generator = new FrameworkProxyGenerator();
        $first = $generator->generate('DynamicInherited\\InheritedChild', 'declaring-file-a', $this->root);
        $mtime = filemtime($parentPath);
        $size = filesize($parentPath);
        file_put_contents($parentPath, str_replace("return 'A'", "return 'B'", $parent));
        touch($parentPath, $mtime);
        self::assertSame($size, filesize($parentPath));
        self::assertSame($mtime, filemtime($parentPath));
        $second = $generator->generate('DynamicInherited\\InheritedChild', 'declaring-file-a', $this->root);
        self::assertNotSame($first->directory, $second->directory);
        self::assertNotSame(
            $first->classMap['DynamicInherited\\InheritedChild'],
            $second->classMap['DynamicInherited\\InheritedChild'],
        );
        self::assertArrayHasKey('DynamicInherited\\InheritedParent::run', $second->manifest->sourceInputs);
        self::assertArrayNotHasKey('source_path', $second->manifest->sourceInputs);
    }

    public function testSourceContentDriftInvalidatesSameSizeAndMtimeArtifact(): void
    {
        $path = $this->root . '/DynamicDriftService.php';
        $source = "<?php\n#[\\BlackOps\\Database\\Attribute\\Transactional]\nclass DynamicDriftService { public function run(): string { return 'A'; } }\n";
        file_put_contents($path, $source);
        require_once $path;
        $generator = new FrameworkProxyGenerator();
        $first = $generator->generate('DynamicDriftService', 'build-drift', $this->root);
        $mtime = filemtime($path);
        $size = filesize($path);
        file_put_contents($path, str_replace("return 'A'", "return 'B'", $source));
        touch($path, $mtime);
        self::assertSame($size, filesize($path));
        self::assertSame($mtime, filemtime($path));
        $second = $generator->generate('DynamicDriftService', 'build-drift', $this->root);
        self::assertNotSame($first->directory, $second->directory);
        self::assertNotSame($first->classMap['DynamicDriftService'], $second->classMap['DynamicDriftService']);
    }

    public function testManifestTamperingIsRejectedBeforeClassLoad(): void
    {
        $result = new FrameworkProxyGenerator()->generate(ComplexTypeService::class, 'build-b', $this->root);
        file_put_contents(
            $result->directory . '/manifest.json',
            str_replace('framework', 'ray', (string) file_get_contents($result->directory . '/manifest.json')),
        );
        $this->expectException(\InvalidArgumentException::class);
        new FrameworkProxyArtifactLoader()->load(
            $result->directory,
            'build-b',
            $result->manifest->manifestHash,
            'framework',
        );
    }

    public function testContractRejectsFinalTarget(): void
    {
        $this->expectException(\BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContractException::class);
        new FrameworkProxyGenerator()->generate(FinalService::class, 'build-c', $this->root);
    }

    public function testFrameworkGeneratorRejectsRayProfile(): void
    {
        try {
            new FrameworkProxyGenerator()->generate(ComplexTypeService::class, 'build-ray', $this->root, 'ray');
            self::fail('Expected framework generator profile conflict.');
        } catch (\BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContractException $exception) {
            self::assertSame('BO_PROXY_MODE_CONFLICT', $exception->diagnostic->code);
        }
    }

    public function testGeneratorInputAndSourceFailuresUseStableCodes(): void
    {
        try {
            new FrameworkProxyGenerator()->generateBatch(
                [ComplexTypeService::class],
                'build-input-invalid',
                $this->root,
                inputHashes: ['invalid' => 'not-a-hash'],
            );
            self::fail('Expected input hash rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(FrameworkProxyArtifactDiagnosticCode::INPUT_INVALID, $exception->getMessage());
        }
        eval('#[\\BlackOps\\Database\\Attribute\\Transactional] class MissingProxySource { public function run(): void {} }');
        try {
            new FrameworkProxyGenerator()->generate('MissingProxySource', 'build-source-invalid', $this->root);
            self::fail('Expected unavailable source rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(FrameworkProxyArtifactDiagnosticCode::SOURCE_UNAVAILABLE, $exception->getMessage());
        }
    }

    public function testInvocationReceivesOnlyActualArguments(): void
    {
        $result = new FrameworkProxyGenerator()->generate(PrecedenceService::class, 'build-args', $this->root);
        new FrameworkProxyArtifactLoader()->load(
            $result->directory,
            'build-args',
            $result->manifest->manifestHash,
            'framework',
        );
        $proxyClass = $result->classMap[PrecedenceService::class];
        $received = null;
        $connection = null;
        $proxy = new $proxyClass();
        $proxy->__blackopsInitialize(new class($received, $connection) implements FrameworkProxyInvocation {
            public function __construct(
                private mixed &$received,
                private ?string &$connection,
            ) {}

            public function transactional(
                object $proxy,
                string $method,
                array $arguments,
                \Closure $proceed,
                ?string $connection,
            ): mixed {
                $this->received = $arguments;
                $this->connection = $connection;
                return $proceed();
            }

            public function afterCommit(object $proxy, string $method, array $arguments, \Closure $proceed): void
            {
                $proceed();
            }
        });
        self::assertSame(1, $proxy->inherited());
        self::assertSame([], $received);
        self::assertSame('app', $connection);
        self::assertSame('value', $proxy->inherited('value'));
        self::assertSame(['value'], $received);
        self::assertSame(1, $proxy->inherited(extra: 3));
        self::assertSame(['extra' => 3], $received);
        self::assertSame('value', $proxy->inherited('value', 2, 3));
        self::assertSame(['value', 2, 3], $received);
    }

    public function testOperationPassThroughDoesNotRequireInitializer(): void
    {
        $result = new FrameworkProxyGenerator()->generate(OperationService::class, 'build-operation', $this->root);
        new FrameworkProxyArtifactLoader()->load(
            $result->directory,
            'build-operation',
            $result->manifest->manifestHash,
            'framework',
        );
        $proxy = new $result->classMap[OperationService::class]();
        self::assertTrue(method_exists($proxy, 'handle'));
        $proxy->handle();
    }

    public function testReadonlyInitializerIsOneTimeAndTypesRemainCallable(): void
    {
        $result = new FrameworkProxyGenerator()->generate(ReadonlyService::class, 'build-readonly', $this->root);
        new FrameworkProxyArtifactLoader()->load(
            $result->directory,
            'build-readonly',
            $result->manifest->manifestHash,
            'framework',
        );
        $proxy = new $result->classMap[ReadonlyService::class]();
        $invocation = new class implements FrameworkProxyInvocation {
            public function transactional(
                object $proxy,
                string $method,
                array $arguments,
                \Closure $proceed,
                ?string $connection,
            ): mixed {
                return $proceed();
            }

            public function afterCommit(object $proxy, string $method, array $arguments, \Closure $proceed): void
            {
                $proceed();
            }
        };
        $proxy->__blackopsInitialize($invocation);
        self::assertSame($proxy, $proxy->run());
        $this->expectException(\LogicException::class);
        $proxy->__blackopsInitialize($invocation);
    }

    public function testComplexDefaultsUnionsAndInheritedTypesExecute(): void
    {
        $result = new FrameworkProxyGenerator()->generateBatch(
            [
                ComplexTypeService::class,
                InheritedTypeService::class,
                ParentTypeService::class,
                IntersectionService::class,
            ],
            'build-types',
            $this->root,
        );
        new FrameworkProxyArtifactLoader()->load(
            $result->directory,
            'build-types',
            $result->manifest->manifestHash,
            'framework',
        );
        $invocation = new class implements FrameworkProxyInvocation {
            public function transactional(
                object $proxy,
                string $method,
                array $arguments,
                \Closure $proceed,
                ?string $connection,
            ): mixed {
                return $proceed();
            }

            public function afterCommit(object $proxy, string $method, array $arguments, \Closure $proceed): void
            {
                $proceed();
            }
        };
        $complex = new $result->classMap[ComplexTypeService::class]();
        $complex->__blackopsInitialize($invocation);
        self::assertSame($complex, $complex->defaults());
        self::assertSame($complex, $complex->defaults(constant: 5));
        $left = new class implements LeftValue {};
        $dnf = new class implements FirstValue, SecondValue {};
        self::assertSame($complex, $complex->complex($left, $dnf));
        $inherited = new $result->classMap[InheritedTypeService::class]();
        $inherited->__blackopsInitialize($invocation);
        self::assertSame($inherited, $inherited->inherited());
        self::assertSame($inherited, $inherited->parentType());
        $parent = new $result->classMap[ParentTypeService::class]();
        $parent->__blackopsInitialize($invocation);
        self::assertSame($parent, $parent->parentType());
        $intersection = new $result->classMap[IntersectionService::class]();
        $intersection->__blackopsInitialize($invocation);
        $both = new class implements LeftValue, RightValue {};
        self::assertSame($intersection, $intersection->run($both));
        self::expectException(\RuntimeException::class);
        $complex->neverReturns();
    }

    public function testOperationAfterCommitAndGeneratedMemberCollision(): void
    {
        $result = new FrameworkProxyGenerator()->generate(
            OperationAfterCommitService::class,
            'build-after',
            $this->root,
        );
        new FrameworkProxyArtifactLoader()->load(
            $result->directory,
            'build-after',
            $result->manifest->manifestHash,
            'framework',
        );
        $called = false;
        $proxy = new $result->classMap[OperationAfterCommitService::class]();
        $proxy->__blackopsInitialize(new class($called) implements FrameworkProxyInvocation {
            public function __construct(
                private bool &$called,
            ) {}

            public function transactional(
                object $proxy,
                string $method,
                array $arguments,
                \Closure $proceed,
                ?string $connection,
            ): mixed {
                return $proceed();
            }

            public function afterCommit(object $proxy, string $method, array $arguments, \Closure $proceed): void
            {
                $this->called = true;
                $proceed();
            }
        });
        $proxy->callback();
        self::assertTrue($called);
        try {
            new FrameworkProxyGenerator()->generate(
                GeneratedMemberCollisionService::class,
                'build-collision',
                $this->root,
            );
            self::fail('Expected generated member collision.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                FrameworkProxyArtifactDiagnosticCode::GENERATED_MEMBER_COLLISION,
                $exception->getMessage(),
            );
        }
    }

    public function testEnumDefaultsAndUnrelatedAttributesRemainUsable(): void
    {
        $result = new FrameworkProxyGenerator()->generateBatch(
            [
                EnumDefaultService::class,
                UnrelatedTargetService::class,
            ],
            'build-attrs',
            $this->root,
        );
        new FrameworkProxyArtifactLoader()->load(
            $result->directory,
            'build-attrs',
            $result->manifest->manifestHash,
            'framework',
        );
        $invocation = new class implements FrameworkProxyInvocation {
            public function transactional(
                object $proxy,
                string $method,
                array $arguments,
                \Closure $proceed,
                ?string $connection,
            ): mixed {
                return $proceed();
            }

            public function afterCommit(object $proxy, string $method, array $arguments, \Closure $proceed): void
            {
                $proceed();
            }
        };
        $enum = new $result->classMap[EnumDefaultService::class]();
        $enum->__blackopsInitialize($invocation);
        self::assertSame(DefaultEnum::VALUE, $enum->run());
        $unrelated = new $result->classMap[UnrelatedTargetService::class]();
        $unrelated->__blackopsInitialize($invocation);
        self::assertSame('default', $unrelated->run());
        $reflection = new ReflectionClass($unrelated);
        self::assertCount(1, $reflection->getAttributes(UnrelatedWideAttribute::class));
        self::assertCount(1, $reflection->getMethod('run')->getAttributes(UnrelatedWideAttribute::class));
        self::assertCount(
            1,
            $reflection->getMethod('run')->getParameters()[0]->getAttributes(UnrelatedWideAttribute::class),
        );
    }

    #[DataProvider('rejectProvider')]
    public function testGeneratorRejectMatrix(string $class, string $code): void
    {
        try {
            new FrameworkProxyGenerator()->generate($class, 'build-reject', $this->root);
            self::fail('Expected contract rejection.');
        } catch (\BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContractException $exception) {
            self::assertSame($code, $exception->diagnostic->code);
        }
    }

    /** @return iterable<string,array{class:class-string,code:string}> */
    public static function rejectProvider(): iterable
    {
        $base = 'BlackOps\\Tests\\Fixtures\\Aop\\FrameworkProxyContract\\';
        yield 'final class' => [
            'class' => $base . 'FinalService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_FINAL_CLASS,
        ];
        yield 'final method' => [
            'class' => $base . 'FinalMethodService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_FINAL_METHOD,
        ];
        yield 'visibility' => [
            'class' => $base . 'VisibilityService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_VISIBILITY,
        ];
        yield 'static' => [
            'class' => $base . 'StaticService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_STATIC,
        ];
        yield 'generator' => [
            'class' => $base . 'GeneratorService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_GENERATOR,
        ];
        yield 'reference return' => [
            'class' => $base . 'ReferenceReturnService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_REFERENCE_RETURN,
        ];
        yield 'reference parameter' => [
            'class' => $base . 'ReferenceParameterService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_REFERENCE_PARAMETER,
        ];
        yield 'conflict' => [
            'class' => $base . 'ConflictService',
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_CONFLICT,
        ];
        yield 'duplicate transactional' => [
            'class' => $base . 'DuplicateAttributeService',
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_DUPLICATE,
        ];
        yield 'duplicate after commit' => [
            'class' => $base . 'DuplicateAfterCommitAttributeService',
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_DUPLICATE,
        ];
        yield 'after commit return' => [
            'class' => $base . 'AfterCommitReturnService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_AFTER_COMMIT_RETURN,
        ];
        yield 'after commit generator' => [
            'class' => $base . 'AfterCommitGeneratorService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_GENERATOR,
        ];
        yield 'after commit reference return' => [
            'class' => $base . 'AfterCommitReferenceReturnService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_REFERENCE_RETURN,
        ];
        yield 'after commit reference parameter' => [
            'class' => $base . 'AfterCommitReferenceParameterService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_REFERENCE_PARAMETER,
        ];
        yield 'constructor' => [
            'class' => $base . 'ConstructorTargetService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_VISIBILITY,
        ];
        yield 'destructor' => [
            'class' => $base . 'DestructorTargetService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_VISIBILITY,
        ];
        yield 'private after commit' => [
            'class' => $base . 'PrivateAfterCommitService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_VISIBILITY,
        ];
        yield 'class after commit' => [
            'class' => $base . 'ClassAfterCommitService',
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
        ];
        yield 'property target' => [
            'class' => $base . 'PropertyTargetService',
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
        ];
        yield 'parameter target' => [
            'class' => $base . 'ParameterTargetService',
            'code' => FrameworkProxyDiagnosticCode::ATTRIBUTE_TARGET,
        ];
        yield 'abstract target' => [
            'class' => $base . 'AbstractService',
            'code' => FrameworkProxyDiagnosticCode::TARGET_NOT_CONCRETE,
        ];
        yield 'interface target' => [
            'class' => $base . 'ContractInterface',
            'code' => FrameworkProxyDiagnosticCode::TARGET_NOT_CONCRETE,
        ];
        yield 'trait target' => [
            'class' => $base . 'ContractTrait',
            'code' => FrameworkProxyDiagnosticCode::TARGET_NOT_CONCRETE,
        ];
        yield 'object default' => [
            'class' => $base . 'ObjectDefaultService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_DEFAULT_VALUE,
        ];
        yield 'inaccessible default' => [
            'class' => $base . 'InaccessibleDefaultService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_DEFAULT_VALUE,
        ];
        yield 'inherited inaccessible default' => [
            'class' => $base . 'PrivateDefaultChildService',
            'code' => FrameworkProxyDiagnosticCode::SIGNATURE_DEFAULT_VALUE,
        ];
    }

    private function remove(string $path): void
    {
        if (!is_dir($path))
            return;
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..')
                continue;
            $child = $path . '/' . $entry;
            is_dir($child) ? $this->remove($child) : unlink($child);
        }
        rmdir($path);
    }
}

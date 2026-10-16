<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\BinaryOp\GreaterOrEqual;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\BinaryOp\Minus;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:no-insecure-comparison
 */
final class AuditAuthorityHistoryArchitectureTest extends TestCase
{
    private const string HISTORY_NAMESPACE = 'BlackOps\\Internal\\Audit\\Authority\\History';

    /** @var list<string> */
    private const array TAG_UNION = [
        self::HISTORY_NAMESPACE . '\\AuditHistoryTag',
        self::HISTORY_NAMESPACE . '\\AuditHistoryCheckpointTag',
        self::HISTORY_NAMESPACE . '\\AuditHistoryControlTag',
    ];

    public function testHistoryHelpersHaveOnlyTheirClosedStatelessSurfaces(): void
    {
        $expected = $this->helperSignatures();
        $root = $this->sourceRoot();
        $historyOwners = $this->historyOwnerAllowlist();
        $expectedDeclarations = [];
        foreach (array_keys($historyOwners) as $file) {
            $expectedDeclarations[$file] = [
                self::HISTORY_NAMESPACE . '\\' . pathinfo($file, PATHINFO_FILENAME),
            ];
        }
        ksort($expectedDeclarations);
        self::assertSame(
            $expectedDeclarations,
            $this->historyOwnerInventory($this->historySources($root)),
            'History owners and seven helper declarations are finite',
        );
        $expectedFiles = array_keys(array_filter($historyOwners, static fn(string $kind): bool => $kind === 'helper'));
        $allowedHelperFiles = array_map(
            static fn(string $shortName): string => $shortName . '.php',
            array_keys($expected),
        );
        sort($expectedFiles);
        sort($allowedHelperFiles);
        self::assertSame($allowedHelperFiles, $expectedFiles, 'exactly seven helper paths are allowed');
        $extraHelperSource = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit\Authority\History;
            final class AuditHistoryExtraHelper {}
            PHP;
        $mutatedHistorySources = $this->historySources($root);
        $mutatedHistorySources['AuditHistoryExtraHelper.php'] = $extraHelperSource;
        self::assertNotSame(
            $expectedDeclarations,
            $this->historyOwnerInventory($mutatedHistorySources),
            'an eighth helper filename/declaration is rejected',
        );
        foreach ($expected as $shortName => $methods) {
            $relative = 'Internal/Audit/Authority/History/' . $shortName . '.php';
            $class = $this->classDeclaration(
                $this->parseSource($this->source($relative)),
                self::HISTORY_NAMESPACE . '\\' . $shortName,
            );
            self::assertTrue($class->isFinal(), $shortName . ' final');
            self::assertFalse($class->isAbstract(), $shortName . ' abstract');
            self::assertFalse($class->isReadonly(), $shortName . ' readonly');
            self::assertNull($class->extends, $shortName . ' parent');
            self::assertSame([], $class->implements, $shortName . ' interfaces');
            self::assertSame([], $class->getProperties(), $shortName . ' state');
            $constantNames = array_map(
                static fn(ClassConst $constant): string => $constant->consts[0]->name->toString(),
                $class->getConstants(),
            );
            sort($constantNames);
            $expectedConstantNames = $shortName === 'AuditHistoryScalar'
                ? [
                    'INTEGER_BOUNDS',
                    'NULLABLE_ALIASES',
                    'NULLABLE_KINDS',
                    'STRING_KINDS',
                    'STRING_PATTERNS',
                    'STRING_VALUES',
                ]
                : [];
            sort($expectedConstantNames);
            self::assertSame($expectedConstantNames, $constantNames, $shortName . ' finite constants');
            foreach ($class->getConstants() as $constant) {
                self::assertTrue($constant->isPrivate(), $shortName . ' constant visibility');
            }

            $actualMethods = [];
            foreach ($class->getMethods() as $method) {
                $actualMethods[$method->name->toString()] = $method;
            }
            $expectedMethodNames = array_keys($methods);
            $actualMethodNames = array_keys($actualMethods);
            sort($expectedMethodNames);
            sort($actualMethodNames);
            self::assertSame($expectedMethodNames, $actualMethodNames, $shortName . ' methods');
            foreach ($methods as $methodName => $signature) {
                $this->assertMethodSignature(
                    $actualMethods[$methodName],
                    $signature,
                    self::HISTORY_NAMESPACE . '\\' . $shortName . '::' . $methodName,
                );
            }

            $source = file_get_contents($root . '/' . $relative);
            self::assertIsString($source);
            self::assertSame([], $this->dynamicInvocationInventory($source), $shortName . ' dynamic dispatch');
            self::assertSame([], $this->forbiddenDependencyInventory($source), $shortName . ' forbidden dependency');
        }

        $extraMethodSource = preg_replace(
            '/\n}\s*$/',
            "\n    public static function extra(): void {}\n}\n",
            $this->source('Internal/Audit/Authority/History/AuditHistoryScalar.php'),
            1,
        );
        self::assertIsString($extraMethodSource);
        $extraMethodClass = $this->classDeclaration(
            $this->parseSource($extraMethodSource),
            self::HISTORY_NAMESPACE . '\\AuditHistoryScalar',
        );
        $extraMethodNames = array_map(
            static fn(ClassMethod $method): string => $method->name->toString(),
            $extraMethodClass->getMethods(),
        );
        sort($extraMethodNames);
        $expectedMethodNames = array_keys($expected['AuditHistoryScalar']);
        sort($expectedMethodNames);
        self::assertNotSame($expectedMethodNames, $extraMethodNames, 'an eighth operation is rejected');

        $scalar = $this->source('Internal/Audit/Authority/History/AuditHistoryScalar.php');
        $forbidden = $scalar . <<<'PHP'

            final class SyntheticForbiddenHistoryDependency
            {
                private function query(): void
                {
                    \PDO::query('select 1');
                }
            }
            PHP;
        self::assertNotSame([], $this->forbiddenDependencyInventory($forbidden));
        $dynamic = $scalar . <<<'PHP'

            final class SyntheticDynamicHistoryInvocation
            {
                private function invoke(): void
                {
                    ($callable)();
                }
            }
            PHP;
        self::assertNotSame([], $this->dynamicInvocationInventory($dynamic));
        $forbiddenDependency = $scalar . <<<'PHP'

            final class SyntheticForbiddenAuthorityDependency
            {
                private function invoke(): void
                {
                    AuditF2::prepare();
                    $current->trust();
                    AuditHistoryNativeGroup::tag();
                }
            }
            PHP;
        self::assertNotSame([], $this->forbiddenDependencyInventory($forbiddenDependency));
    }

    public function testHistoryHelperCallersAndRelationEdgesAreFinite(): void
    {
        $sources = $this->sourceFiles($this->sourceRoot());
        $expected = [
            'Internal/Audit/Authority/History/AuditHistoryNativeGroup.php::__construct' => [
                self::HISTORY_NAMESPACE . '\\AuditHistoryScalar::admit' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::header' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::externalAnchor' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::partition' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::recordEndpoint' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::stagedBegin' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistorySegmentLifecycle::assertValid' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryClosedSegmentRelations::proof' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryClosedSegmentRelations::binding' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryWorkRelations::presence' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryWorkRelations::reservation' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryWorkRelations::step' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryCheckpointEndRelations::assertValid' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryPublicationRelations::transition' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryPublicationRelations::publication' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryPublicationRelations::end' => 1,
            ],
            'Internal/Audit/Authority/History/AuditHistoryScalar.php::admit' => [
                self::HISTORY_NAMESPACE . '\\AuditHistoryScalar::admitBoolean' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryScalar::admitInteger' => 1,
                self::HISTORY_NAMESPACE . '\\AuditHistoryScalar::admitString' => 1,
            ],
            'Internal/Audit/Authority/History/AuditHistorySegmentLifecycle.php::assertValid' => [
                self::HISTORY_NAMESPACE . '\\AuditHistoryClosedSegmentRelations::version' => 1,
            ],
            'Internal/Audit/Authority/History/AuditHistoryClosedSegmentRelations.php::proof' => [
                self::HISTORY_NAMESPACE . '\\AuditHistoryClosedSegmentRelations::version' => 1,
            ],
        ];
        ksort($expected);
        foreach ($expected as &$calls) {
            ksort($calls);
        }
        unset($calls);
        self::assertSame($expected, $this->helperCallerInventory($sources));

        $alias = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit\Authority\History;
            use BlackOps\Internal\Audit\Authority\History\AuditHistoryScalar as Scalar;
            final class SyntheticAliasCaller
            {
                private function call(): void
                {
                    Scalar::admit('I', $value);
                }
            }
            PHP;
        self::assertSame(
            [self::HISTORY_NAMESPACE . '\\AuditHistoryScalar::admit' => 1],
            $this->helperCallerInventory(['SyntheticAlias.php' => $alias])['SyntheticAlias.php::call'],
        );
        $fqcn = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit\Authority\History;
            final class SyntheticFqcnCaller
            {
                private function call(): void
                {
                    \BlackOps\Internal\Audit\Authority\History\AuditHistoryScalar::admit('I', $value);
                }
            }
            PHP;
        self::assertSame(
            [self::HISTORY_NAMESPACE . '\\AuditHistoryScalar::admit' => 1],
            $this->helperCallerInventory(['SyntheticFqcn.php' => $fqcn])['SyntheticFqcn.php::call'],
        );

        $duplicate = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit\Authority\History;
            final class SyntheticDuplicateCaller
            {
                private function call(): void
                {
                    AuditHistoryScalar::admit('I', $value);
                    AuditHistoryScalar::admit('I', $value);
                }
            }
            PHP;
        self::assertNotSame(
            $expected,
            $this->helperCallerInventory($sources + ['SyntheticDuplicate.php' => $duplicate]),
        );
        $sibling = <<<'PHP'
            <?php
            namespace BlackOps\Internal\Audit\Authority\History;
            final class SyntheticSiblingHelper
            {
                private function call(): void
                {
                    AuditHistoryContextRelations::header($value);
                }
            }
            PHP;
        self::assertNotSame($expected, $this->helperCallerInventory($sources + ['SyntheticSibling.php' => $sibling]));

        $constructors = $this->helperConstructorCallers($sources);
        self::assertSame([], $constructors, 'helpers cannot be instantiated as services');
    }

    public function testNativeGroupOwnsOneClosedSchemaAndAdmitsBeforeRetention(): void
    {
        $nativeSource = $this->source('Internal/Audit/Authority/History/AuditHistoryNativeGroup.php');
        $native = $this->classDeclaration(
            $this->parseSource($nativeSource),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        $schema = $this->classConstant($native, 'SCHEMAS');
        self::assertNotNull($schema);
        self::assertTrue($schema->isPrivate());
        self::assertCount(1, $this->classConstants($native, 'SCHEMAS'));
        self::assertInstanceOf(Array_::class, $schema->consts[0]->value);
        $schemaKeys = $this->arrayKeys($schema->consts[0]->value);
        sort($schemaKeys);
        self::assertSame($this->nativeTagValues(), $schemaKeys, 'NativeGroup is the one complete schema');
        foreach ($schema->consts[0]->value->items as $item) {
            if ($item === null) {
                continue;
            }
            self::assertInstanceOf(Array_::class, $item->value, 'schema rows remain closed arrays');
            self::assertFalse($item->unpack, 'schema rows do not use dynamic spreads');
        }

        $codec = $this->classDeclaration(
            $this->parseSource($this->source('Internal/Audit/Authority/History/AuditHistoryGroupCodec.php')),
            self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
        );
        self::assertSame(
            [],
            array_map(
                static fn(ClassConst $constant): string => $constant->consts[0]->name->toString(),
                $codec->getConstants(),
            ),
        );
        self::assertSame(
            [],
            array_values(array_filter(
                array_map(static fn(ClassMethod $method): string => $method->name->toString(), $codec->getMethods()),
                static fn(string $name): bool => in_array($name, ['schema', 'assertScalars'], true),
            )),
        );

        $constructor = $this->method($native, '__construct');
        self::assertTrue(
            $this->nativeDispatchContract($constructor),
            'literal switch dispatch with grouped cases and no-op default',
        );
        $scalarAdmission = $this->staticCalls($constructor, self::HISTORY_NAMESPACE . '\\AuditHistoryScalar', 'admit');
        self::assertCount(1, $scalarAdmission, 'one scalar descriptor admission loop');
        $retentions = array_values(array_filter(
            new NodeFinder()->findInstanceOf($constructor, New_::class),
            fn(New_ $new): bool => (
                $new->class instanceof Name
                && $this->terminal($new->class->toString()) === 'SensitiveParameterValue'
            ),
        ));
        self::assertCount(1, $retentions, 'one detached sensitive snapshot');
        self::assertLessThan(
            $retentions[0]->getStartFilePos(),
            $scalarAdmission[0]->getStartFilePos(),
            'scalar admission precedes native retention',
        );
        self::assertFalse(
            $this->containsVariable($retentions[0], 'scalars'),
            'raw pre-admission input cannot enter the retained snapshot',
        );

        $relations = [
            self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::header',
            self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::externalAnchor',
            self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::partition',
            self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::recordEndpoint',
            self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::stagedBegin',
            self::HISTORY_NAMESPACE . '\\AuditHistorySegmentLifecycle::assertValid',
            self::HISTORY_NAMESPACE . '\\AuditHistoryClosedSegmentRelations::proof',
            self::HISTORY_NAMESPACE . '\\AuditHistoryClosedSegmentRelations::binding',
            self::HISTORY_NAMESPACE . '\\AuditHistoryWorkRelations::presence',
            self::HISTORY_NAMESPACE . '\\AuditHistoryWorkRelations::reservation',
            self::HISTORY_NAMESPACE . '\\AuditHistoryWorkRelations::step',
            self::HISTORY_NAMESPACE . '\\AuditHistoryCheckpointEndRelations::assertValid',
            self::HISTORY_NAMESPACE . '\\AuditHistoryPublicationRelations::transition',
            self::HISTORY_NAMESPACE . '\\AuditHistoryPublicationRelations::publication',
            self::HISTORY_NAMESPACE . '\\AuditHistoryPublicationRelations::end',
        ];
        foreach ($relations as $target) {
            [$class, $name] = explode('::', $target, 2);
            $calls = $this->staticCalls($constructor, $class, $name);
            self::assertCount(1, $calls, $target . ' dispatch');
            self::assertLessThan(
                $retentions[0]->getStartFilePos(),
                $calls[0]->getStartFilePos(),
                $target . ' before retention',
            );
        }

        $missing = str_replace(
            'AuditHistoryScalar::admit(',
            'AuditHistoryScalar::missing(',
            $this->source('Internal/Audit/Authority/History/AuditHistoryNativeGroup.php'),
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->nativeDispatchDescriptor($this->source(
                'Internal/Audit/Authority/History/AuditHistoryNativeGroup.php',
            )),
            $this->nativeDispatchDescriptor($missing),
        );
        $dynamicDispatch = str_replace(
            'switch ($tag->value) {',
            'switch ($tag->{$dispatchMethod}) {',
            $nativeSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'literal switch mutation fixture');
        $dynamicNative = $this->classDeclaration(
            $this->parseSource($dynamicDispatch),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($dynamicNative, '__construct')),
            'dynamic switch subject is rejected',
        );
        $defaultMutation = str_replace(
            "default:\n                    break;",
            "default:\n                    AuditHistoryContextRelations::header(\$admitted);\n                    break;",
            $nativeSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'default side-effect mutation fixture');
        $defaultNative = $this->classDeclaration(
            $this->parseSource($defaultMutation),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($defaultNative, '__construct')),
            'default side effect is rejected',
        );

        $missingBreak = str_replace(
            "AuditHistoryContextRelations::header(\$admitted);\n                    break;",
            "AuditHistoryContextRelations::header(\$admitted);",
            $nativeSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'relation break removal fixture');
        $missingBreakNative = $this->classDeclaration(
            $this->parseSource($missingBreak),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($missingBreakNative, '__construct')),
            'relation fallthrough is rejected',
        );

        $wrongAnnotation = str_replace(
            '/** @var array{schemaVersion:int,phase:int,installationRef:string,schemaName:string,tenantType:?string,tenantId:?string,partitionId:string,segmentId:?string,workId:?string} $admitted */',
            '/** @var array{schemaVersion:string,phase:int,installationRef:string,schemaName:string,tenantType:?string,tenantId:?string,partitionId:string,segmentId:?string,workId:?string} $admitted */',
            $nativeSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'closed row annotation mutation fixture');
        $wrongAnnotationNative = $this->classDeclaration(
            $this->parseSource($wrongAnnotation),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($wrongAnnotationNative, '__construct')),
            'wrong closed row annotation is rejected',
        );

        $missingAnnotation = str_replace(
            "/** @var array{schemaVersion:int,phase:int,installationRef:string,schemaName:string,tenantType:?string,tenantId:?string,partitionId:string,segmentId:?string,workId:?string} \$admitted */\n                    ",
            '',
            $nativeSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'missing row annotation mutation fixture');
        $missingAnnotationNative = $this->classDeclaration(
            $this->parseSource($missingAnnotation),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($missingAnnotationNative, '__construct')),
            'missing closed row annotation is rejected',
        );

        $movedAnnotation = str_replace(
            "/** @var array{schemaVersion:int,phase:int,installationRef:string,schemaName:string,tenantType:?string,tenantId:?string,partitionId:string,segmentId:?string,workId:?string} \$admitted */\n                    AuditHistoryContextRelations::header(\$admitted);",
            "AuditHistoryContextRelations::header(\$admitted);\n                    /** @var array{schemaVersion:int,phase:int,installationRef:string,schemaName:string,tenantType:?string,tenantId:?string,partitionId:string,segmentId:?string,workId:?string} \$admitted */",
            $nativeSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'moved row annotation mutation fixture');
        $movedAnnotationNative = $this->classDeclaration(
            $this->parseSource($movedAnnotation),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($movedAnnotationNative, '__construct')),
            'annotation after relation call is rejected',
        );

        $preLoopAnnotation = str_replace(
            "/** @var array{schemaVersion:int,phase:int,installationRef:string,schemaName:string,tenantType:?string,tenantId:?string,partitionId:string,segmentId:?string,workId:?string} \$admitted */\n                    AuditHistoryContextRelations::header(\$admitted);",
            'AuditHistoryContextRelations::header($admitted);',
            $nativeSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'pre-loop row annotation removal fixture');
        $preLoopAnnotation = str_replace(
            "            /** @var array<string,scalar|null> \$admitted */\n            \$admitted = [];",
            "            /** @var array<string,scalar|null> \$admitted */\n            /** @var array{schemaVersion:int,phase:int,installationRef:string,schemaName:string,tenantType:?string,tenantId:?string,partitionId:string,segmentId:?string,workId:?string} \$admitted */\n            \$admitted = [];",
            $preLoopAnnotation,
            $replacements,
        );
        self::assertSame(1, $replacements, 'pre-loop row annotation insertion fixture');
        $preLoopAnnotationNative = $this->classDeclaration(
            $this->parseSource($preLoopAnnotation),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($preLoopAnnotationNative, '__construct')),
            'pre-loop closed row annotation is rejected',
        );

        $wrongCase = str_replace('case 1:', 'case 2:', $nativeSource, $replacements);
        self::assertSame(1, $replacements, 'case literal mutation fixture');
        $wrongCaseNative = $this->classDeclaration(
            $this->parseSource($wrongCase),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($wrongCaseNative, '__construct')),
            'wrong relation case is rejected',
        );

        $wrongCallee = str_replace(
            'AuditHistoryContextRelations::header($admitted);',
            'AuditHistoryWorkRelations::header($admitted);',
            $nativeSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'callee mutation fixture');
        $wrongCalleeNative = $this->classDeclaration(
            $this->parseSource($wrongCallee),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($wrongCalleeNative, '__construct')),
            'wrong relation callee is rejected',
        );

        $wrongArgument = str_replace(
            'AuditHistoryContextRelations::header($admitted);',
            'AuditHistoryContextRelations::header($scalars);',
            $nativeSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'relation argument mutation fixture');
        $wrongArgumentNative = $this->classDeclaration(
            $this->parseSource($wrongArgument),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($wrongArgumentNative, '__construct')),
            'relation raw argument is rejected',
        );

        $arbitraryStatement = str_replace(
            "AuditHistoryContextRelations::header(\$admitted);\n                    break;",
            "AuditHistoryContextRelations::header(\$admitted);\n                    \$admitted = [];\n                    break;",
            $nativeSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'arbitrary dispatch statement fixture');
        $arbitraryStatementNative = $this->classDeclaration(
            $this->parseSource($arbitraryStatement),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($arbitraryStatementNative, '__construct')),
            'arbitrary relation statement is rejected',
        );

        $retention = "            \$this->snapshot = new \\SensitiveParameterValue(['tag' => \$tag, 'scalars' => \$admitted]);";
        $earlyRetention = str_replace($retention, '', $nativeSource, $replacements);
        self::assertSame(1, $replacements, 'retention relocation removal fixture');
        $earlyRetention = str_replace(
            "            switch (\$tag->value) {",
            $retention . "\n\n            switch (\$tag->value) {",
            $earlyRetention,
            $replacements,
        );
        self::assertSame(1, $replacements, 'retention relocation insertion fixture');
        $earlyRetentionNative = $this->classDeclaration(
            $this->parseSource($earlyRetention),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        self::assertFalse(
            $this->nativeDispatchContract($this->method($earlyRetentionNative, '__construct')),
            'early retention is rejected',
        );
    }

    public function testNativeAndRecordTagSurfacesUseTheExactThreeWayUnionAndKeepR4(): void
    {
        $native = $this->classDeclaration(
            $this->parseSource($this->source('Internal/Audit/Authority/History/AuditHistoryNativeGroup.php')),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        $record = $this->classDeclaration(
            $this->parseSource($this->source('Internal/Audit/Authority/History/AuditHistoryRecord.php')),
            self::HISTORY_NAMESPACE . '\\AuditHistoryRecord',
        );
        $codec = $this->classDeclaration(
            $this->parseSource($this->source('Internal/Audit/Authority/History/AuditHistoryGroupCodec.php')),
            self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
        );

        foreach ([
            [$native, ['__construct', 'fromNative', 'fromDecoded', 'tag']],
            [$record, ['__construct', 'fromCapture', 'fromRead', 'tag']],
            [$codec, ['decode']],
        ] as [$class, $methods]) {
            foreach ($methods as $methodName) {
                $method = $this->method($class, $methodName);
                $tagTypes = [];
                foreach ([$method->params[0]->type ?? null, $method->returnType] as $type) {
                    if ($type instanceof UnionType && $this->containsTagUnion($type)) {
                        $tagTypes[] = $this->typeParts($type);
                    }
                }
                if (in_array(
                    $methodName,
                    ['__construct', 'fromNative', 'fromDecoded', 'fromCapture', 'fromRead', 'decode'],
                    true,
                )) {
                    self::assertContains(self::TAG_UNION, $tagTypes, $methodName . ' tag union');
                } else {
                    self::assertContains(self::TAG_UNION, $tagTypes, $methodName . ' return union');
                }
            }
        }
        self::assertSame(
            self::TAG_UNION,
            $this->typeParts($this->method($native, 'tag')->returnType),
            'NativeGroup tag return union',
        );
        self::assertSame(
            self::TAG_UNION,
            $this->typeParts($this->method($record, 'tag')->returnType),
            'Record tag return union',
        );

        $this->assertCarrierMethod(
            $native,
            '__construct',
            'private',
            false,
            '',
            [
                ['name' => 'tag', 'type' => implode('|', self::TAG_UNION), 'sensitive' => false],
                ['name' => 'scalars', 'type' => 'array', 'sensitive' => true],
            ],
        );
        foreach (['fromNative', 'fromDecoded'] as $factory) {
            $this->assertCarrierMethod(
                $native,
                $factory,
                'public',
                true,
                'self',
                [
                    ['name' => 'tag', 'type' => implode('|', self::TAG_UNION), 'sensitive' => false],
                    ['name' => 'scalars', 'type' => 'array', 'sensitive' => true],
                ],
            );
        }
        $this->assertCarrierMethod($native, 'tag', 'public', false, implode('|', self::TAG_UNION), []);
        $this->assertCarrierMethod($native, 'scalars', 'public', false, 'array', []);
        $this->assertCarrierMethod(
            $record,
            '__construct',
            'private',
            false,
            '',
            [
                ['name' => 'tag', 'type' => implode('|', self::TAG_UNION), 'sensitive' => false],
                ['name' => 'payload', 'type' => 'string', 'sensitive' => true],
                [
                    'name' => 'native',
                    'type' => '?' . self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
                    'sensitive' => false,
                ],
            ],
        );
        foreach (['fromCapture', 'fromRead'] as $factory) {
            $this->assertCarrierMethod(
                $record,
                $factory,
                'public',
                true,
                'self',
                [
                    ['name' => 'tag', 'type' => implode('|', self::TAG_UNION), 'sensitive' => false],
                    ['name' => 'payload', 'type' => 'string', 'sensitive' => true],
                    [
                        'name' => 'native',
                        'type' => '?' . self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
                        'sensitive' => false,
                    ],
                ],
            );
        }
        $this->assertCarrierMethod($record, 'tag', 'public', false, implode('|', self::TAG_UNION), []);
        $this->assertCarrierMethod($record, 'bytes', 'public', false, 'string', []);
        $this->assertCarrierMethod(
            $record,
            'native',
            'public',
            false,
            '?' . self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
            [],
        );
        $this->assertCarrierMethod(
            $codec,
            'encode',
            'public',
            false,
            'string',
            [
                [
                    'name' => 'group',
                    'type' => self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
                    'sensitive' => true,
                ],
            ],
        );
        $this->assertCarrierMethod(
            $codec,
            'decode',
            'public',
            false,
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
            [
                ['name' => 'tag', 'type' => implode('|', self::TAG_UNION), 'sensitive' => false],
                ['name' => 'bytes', 'type' => 'string', 'sensitive' => true],
            ],
        );

        $this->assertR4Surface($native, self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup', 9, 'snapshot');
        $this->assertR4Surface($record, self::HISTORY_NAMESPACE . '\\AuditHistoryRecord', 10, 'snapshot');
    }

    public function testCodecKeepsFlatPreflightBeforeJcsAndBindsDecodedScalars(): void
    {
        $source = $this->source('Internal/Audit/Authority/History/AuditHistoryGroupCodec.php');
        $codec = $this->classDeclaration(
            $this->parseSource($source),
            self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
        );
        $methods = array_map(static fn(ClassMethod $method): string => $method->name->toString(), $codec->getMethods());
        sort($methods);
        self::assertSame(['decode', 'encode'], $methods, 'Codec has only encode/decode');
        self::assertSame([], $codec->getConstants(), 'Codec has no duplicate schema');

        $encode = $this->method($codec, 'encode');
        $groupScalars = array_values(array_filter(
            new NodeFinder()->findInstanceOf($encode, MethodCall::class),
            static fn(MethodCall $call): bool => (
                $call->name instanceof Identifier
                && $call->name->toString() === 'scalars'
                && $call->var instanceof Variable
                && $call->var->name === 'group'
            ),
        ));
        self::assertCount(1, $groupScalars, 'encode reads scalars exactly once');
        self::assertSame(
            [],
            array_values(array_filter(
                new NodeFinder()->findInstanceOf($encode, MethodCall::class),
                static fn(MethodCall $call): bool => (
                    $call->name instanceof Identifier
                    && $call->name->toString() === 'tag'
                    && $call->var instanceof Variable
                    && $call->var->name === 'group'
                ),
            )),
            'encode does not reread Group.tag',
        );
        $canonicalEncode = $this->namedMethodCalls($encode, 'canonicalJson');
        self::assertCount(1, $canonicalEncode, 'encode canonicalizes once');
        self::assertLessThan($canonicalEncode[0]->getStartFilePos(), $groupScalars[0]->getStartFilePos());
        self::assertCount(1, $this->namedFunctionCalls($encode, 'strlen'));
        self::assertTrue($this->hasPostCanonicalByteBound($encode, $canonicalEncode[0]));
        self::assertTrue($this->hasThrowableBoundary($encode));

        $decode = $this->method($codec, 'decode');
        $jcsDecode = $this->jcsMethodCall($decode, 'decode');
        $toNative = $this->jcsMethodCall($decode, 'toNative');
        self::assertNotNull($jcsDecode);
        self::assertNotNull($toNative);
        $jcsPosition = $jcsDecode->getStartFilePos();
        self::assertNotSame([], $this->namedFunctionCalls($decode, 'preg_replace'));
        self::assertNotSame([], $this->namedFunctionCalls($decode, 'preg_match'));
        self::assertNotSame([], $this->namedFunctionCalls($decode, 'strlen'));
        self::assertSame(2, count($this->namedFunctionCalls($decode, 'str_contains')));
        foreach (['preg_replace', 'preg_match', 'strlen', 'str_contains'] as $name) {
            foreach ($this->namedFunctionCalls($decode, $name) as $call) {
                self::assertLessThan($jcsPosition, $call->getStartFilePos(), $name . ' is pre-JCS');
            }
        }
        self::assertTrue($this->hasPreflightByteBound($decode, $jcsPosition), 'raw bound precedes JCS');
        self::assertTrue($this->hasOriginalBoundaryChecks($decode, $jcsPosition), 'original braces precede JCS');
        self::assertTrue($this->hasRawLineChecks($decode, $jcsPosition), 'raw CR/LF checks precede JCS');
        self::assertTrue($this->hasFlatPcreContract($decode, $jcsPosition), 'PCRE failure is rejected');
        self::assertTrue($this->hasThrowableBoundary($decode));
        self::assertTrue($this->decodedNativeDataFlow($decode), 'decoded array remains the source of the group');
        self::assertSame(
            1,
            count($this->staticCalls($decode, self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup', 'fromDecoded')),
        );
        self::assertSame(
            [],
            array_values(array_filter(
                $this->allStaticTargets($decode),
                static fn(string $target): bool => str_ends_with($target, 'AuditHistoryNativeGroup::fromNative'),
            )),
            'decode cannot substitute the native factory',
        );

        $duplicateSchema = str_replace(
            "final class AuditHistoryGroupCodec\n{",
            "final class AuditHistoryGroupCodec\n{\n    private const array SCHEMAS = [];\n",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $duplicateCodec = $this->classDeclaration(
            $this->parseSource($duplicateSchema),
            self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
        );
        self::assertNotSame([], $duplicateCodec->getConstants(), 'a duplicate Codec schema is rejected');
        $removedBound = str_replace('16_384', '16_383', $source, $replacements);
        self::assertGreaterThan(0, $replacements);
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration(
                    $this->parseSource($removedBound),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'changed raw bound is rejected',
        );
        $substituted = str_replace('fromDecoded($tag, $native)', 'fromDecoded($tag, $decoded)', $source, $replacements);
        self::assertSame(1, $replacements);
        self::assertFalse(
            $this->decodedNativeDataFlow($this->method(
                $this->classDeclaration(
                    $this->parseSource($substituted),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'decoded object substitution is rejected',
        );
        $aliasSource = str_replace(
            '$native = $codec->toNative($decoded);',
            '$native = $codec->toNative($decoded);' . "\n            \$admitted = \$native;",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements, 'decoded alias assignment fixture');
        $aliasSource = str_replace(
            '$canonical = $codec->canonicalJson($native);',
            '$canonical = $codec->canonicalJson($admitted);',
            $aliasSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'decoded alias canonical fixture');
        $aliasSource = str_replace(
            'fromDecoded($tag, $native)',
            'fromDecoded($tag, $admitted)',
            $aliasSource,
            $replacements,
        );
        self::assertSame(1, $replacements, 'decoded alias factory fixture');
        self::assertTrue(
            $this->decodedNativeDataFlow($this->method(
                $this->classDeclaration(
                    $this->parseSource($aliasSource),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'unchanged decoded alias remains permitted',
        );
        $elementOverwrite = str_replace(
            'return AuditHistoryNativeGroup::fromDecoded($tag, $native);',
            "\$native['installationRef'] = 'replacement';\n            return AuditHistoryNativeGroup::fromDecoded(\$tag, \$native);",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements, 'decoded element overwrite mutation fixture');
        self::assertFalse(
            $this->decodedNativeDataFlow($this->method(
                $this->classDeclaration(
                    $this->parseSource($elementOverwrite),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'decoded element overwrite is rejected',
        );
        $wholeOverwrite = str_replace(
            'return AuditHistoryNativeGroup::fromDecoded($tag, $native);',
            "\$native = ['replacement' => 'value'];\n            return AuditHistoryNativeGroup::fromDecoded(\$tag, \$native);",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements, 'decoded whole overwrite mutation fixture');
        self::assertFalse(
            $this->decodedNativeDataFlow($this->method(
                $this->classDeclaration(
                    $this->parseSource($wholeOverwrite),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'decoded whole overwrite is rejected',
        );
        $compoundOverwrite = str_replace(
            'return AuditHistoryNativeGroup::fromDecoded($tag, $native);',
            "\$native['installationRef'] .= '-replacement';\n            return AuditHistoryNativeGroup::fromDecoded(\$tag, \$native);",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements, 'decoded compound element overwrite mutation fixture');
        self::assertFalse(
            $this->decodedNativeDataFlow($this->method(
                $this->classDeclaration(
                    $this->parseSource($compoundOverwrite),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'decoded compound element overwrite is rejected',
        );
        $canonicalOverwrite = str_replace(
            "        if (\$bytes !== \$canonical) {",
            "        \$canonical = \$bytes;\n        if (\$bytes !== \$canonical) {",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements, 'canonical result overwrite mutation fixture');
        self::assertFalse(
            $this->decodedNativeDataFlow($this->method(
                $this->classDeclaration(
                    $this->parseSource($canonicalOverwrite),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'canonical result overwrite is rejected',
        );
        $canonicalConditionWrite = str_replace(
            "        if (\$bytes !== \$canonical) {",
            "        if (\$bytes !== \$canonical || ((\$native['installationRef'] .= '-replacement') === '')) {",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements, 'nested canonical condition write mutation fixture');
        self::assertFalse(
            $this->decodedNativeDataFlow($this->method(
                $this->classDeclaration(
                    $this->parseSource($canonicalConditionWrite),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'nested canonical condition write is rejected',
        );
        $foreachMutation = str_replace(
            'return AuditHistoryNativeGroup::fromDecoded($tag, $native);',
            "foreach (['replacement'] as \$native['installationRef']) {\n            }\n            return AuditHistoryNativeGroup::fromDecoded(\$tag, \$native);",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements, 'decoded foreach binding mutation fixture');
        self::assertFalse(
            $this->decodedNativeDataFlow($this->method(
                $this->classDeclaration(
                    $this->parseSource($foreachMutation),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'decoded foreach binding is rejected',
        );
        $tagRebind = str_replace(
            '$native = $codec->toNative($decoded);',
            "\$tag = AuditHistoryTag::Header;\n            \$native = \$codec->toNative(\$decoded);",
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements, 'pre-origin tag rebinding mutation fixture');
        self::assertFalse(
            $this->decodedNativeDataFlow($this->method(
                $this->classDeclaration(
                    $this->parseSource($tagRebind),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'pre-origin tag rebinding is rejected',
        );
        $inertEquality = str_replace(
            "throw new InvalidArgumentException('History native group is not canonical.');",
            '',
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements, 'inert canonical equality mutation fixture');
        self::assertFalse(
            $this->decodedNativeDataFlow($this->method(
                $this->classDeclaration(
                    $this->parseSource($inertEquality),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'inert canonical equality is rejected',
        );

        $pcreMutation = str_replace('!== 1', '=== 1', $source, $replacements);
        self::assertGreaterThan(0, $replacements, 'PCRE mutation fixture');
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration(
                    $this->parseSource($pcreMutation),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'PCRE failure admission mutation is rejected',
        );
        $conjunctionPreflight = str_replace(' || $length > 16_384', ' && $length > 16_384', $source, $replacements);
        self::assertSame(1, $replacements, 'conjunction preflight mutation fixture');
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration(
                    $this->parseSource($conjunctionPreflight),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'conjunction bypass of a required rejection is rejected',
        );
        $wrappedPreflight = str_replace('$length > 16_384', '($length > 16_384) === 0', $source, $replacements);
        self::assertSame(1, $replacements, 'wrapped preflight predicate mutation fixture');
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration(
                    $this->parseSource($wrappedPreflight),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'wrapped preflight predicate is rejected',
        );
        $negatedPreflight = str_replace(
            '|| $withoutStrings === null',
            '|| !($withoutStrings === null)',
            $source,
            $replacements,
        );
        self::assertSame(1, $replacements, 'negated preflight mutation fixture');
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration(
                    $this->parseSource($negatedPreflight),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'negated null rejection is rejected',
        );
        $boundaryMutation = str_replace(
            ['$bytes[0] !== \'{\'', '$bytes[$length - 1] !== \'}\''],
            ['true', 'true'],
            $source,
            $replacements,
        );
        self::assertSame(2, $replacements, 'original boundary mutation fixture');
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration(
                    $this->parseSource($boundaryMutation),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'original boundary removal is rejected',
        );
        $nestedValueMutation = str_replace('[^{}\\[\\]"]*', '[^{}"]*', $source, $replacements);
        self::assertSame(1, $replacements, 'nested value mutation fixture');
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration(
                    $this->parseSource($nestedValueMutation),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'nested object or array grammar is rejected',
        );
        $wrongPreflightSource = str_replace('subject: $bytes', 'subject: $native', $source, $replacements);
        self::assertSame(1, $replacements, 'preflight source mutation fixture');
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration(
                    $this->parseSource($wrongPreflightSource),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'decode',
            )),
            'preflight must strip the original bytes',
        );
        $nestedPreflight = <<<'PHP'
            <?php
            final class SyntheticHistoryNestedCodec
            {
                public function decode(string $bytes): void
                {
                    if (false) {
                        $length = strlen($bytes);
                        if ($bytes === '' || $length > 16_384 || str_contains($bytes, "\r") || str_contains($bytes, "\n")) {
                            throw new \InvalidArgumentException();
                        }
                        $withoutStrings = preg_replace('/"(?:\\\\.|[^"\\\\])*"/s', '', $bytes);
                        if (
                            $bytes[0] !== '{'
                            || $bytes[$length - 1] !== '}'
                            || $withoutStrings === null
                            || preg_match('/\A\{[^{}\[\]"]*\}\z/D', $withoutStrings) !== 1
                        ) {
                            throw new \InvalidArgumentException();
                        }
                    }
                    $codec = new \BlackOps\Internal\Audit\AuditJcsCodec();
                    $codec->decode($bytes);
                }
            }
            PHP;
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration($this->parseSource($nestedPreflight), 'SyntheticHistoryNestedCodec'),
                'decode',
            )),
            'nested preflight under an ineffective branch is rejected',
        );
        $reorderedPreflight = <<<'PHP'
            <?php
            final class SyntheticHistoryReorderedCodec
            {
                public function decode(string $bytes): void
                {
                    $codec = new \BlackOps\Internal\Audit\AuditJcsCodec();
                    $codec->decode($bytes);
                    $length = strlen($bytes);
                    if ($bytes === '' || $length > 16_384 || str_contains($bytes, "\r") || str_contains($bytes, "\n")) {
                        throw new \InvalidArgumentException();
                    }
                    $withoutStrings = preg_replace('/"(?:\\\\.|[^"\\\\])*"/s', '', $bytes);
                    if (
                        $bytes[0] !== '{'
                        || $bytes[$length - 1] !== '}'
                        || $withoutStrings === null
                        || preg_match('/\A\{[^{}\[\]"]*\}\z/D', $withoutStrings) !== 1
                    ) {
                        throw new \InvalidArgumentException();
                    }
                }
            }
            PHP;
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration($this->parseSource($reorderedPreflight), 'SyntheticHistoryReorderedCodec'),
                'decode',
            )),
            'preflight moved after JCS is rejected',
        );
        $ineffectivePreflight = <<<'PHP'
            <?php
            final class SyntheticHistoryIneffectiveCodec
            {
                public function decode(string $bytes): void
                {
                    $length = strlen($bytes);
                    if ($bytes === '' || $length > 16_384 || str_contains($bytes, "\r") || str_contains($bytes, "\n")) {
                    }
                    $withoutStrings = preg_replace('/"(?:\\\\.|[^"\\\\])*"/s', '', $bytes);
                    if (
                        $bytes[0] !== '{'
                        || $bytes[$length - 1] !== '}'
                        || $withoutStrings === null
                        || preg_match('/\A\{[^{}\[\]"]*\}\z/D', $withoutStrings) !== 1
                    ) {
                    }
                    $codec = new \BlackOps\Internal\Audit\AuditJcsCodec();
                    $codec->decode($bytes);
                }
            }
            PHP;
        self::assertFalse(
            $this->codecPreflightContract($this->method(
                $this->classDeclaration($this->parseSource($ineffectivePreflight), 'SyntheticHistoryIneffectiveCodec'),
                'decode',
            )),
            'preflight without rejection is rejected',
        );
        $tagMutation = str_replace('$group->scalars()', '$group->tag()', $source, $replacements);
        self::assertGreaterThan(0, $replacements, 'tag mutation fixture');
        self::assertFalse(
            $this->codecEncodeContract($this->method(
                $this->classDeclaration(
                    $this->parseSource($tagMutation),
                    self::HISTORY_NAMESPACE . '\\AuditHistoryGroupCodec',
                ),
                'encode',
            )),
            'encode Group.tag mutation is rejected',
        );
        $dynamicMutation = str_replace('->canonicalJson(', '->{$method}(', $source, $replacements);
        self::assertGreaterThan(0, $replacements, 'dynamic dispatch mutation fixture');
        self::assertNotSame(
            [],
            $this->dynamicInvocationInventory($dynamicMutation),
            'dynamic JCS dispatch is rejected',
        );
    }

    /** @return array<string,string> */
    private function historyOwnerAllowlist(): array
    {
        return [
            'AuditCheckpointCapturePhase.php' => 'owner',
            'AuditControlCapturePhase.php' => 'owner',
            'AuditHistoryCheckpointEndRelations.php' => 'helper',
            'AuditHistoryCheckpointTag.php' => 'owner',
            'AuditHistoryClosedSegmentRelations.php' => 'helper',
            'AuditHistoryContextRelations.php' => 'helper',
            'AuditHistoryControlTag.php' => 'owner',
            'AuditHistoryGroupCodec.php' => 'owner',
            'AuditHistoryNativeGroup.php' => 'owner',
            'AuditHistoryPublicationRelations.php' => 'helper',
            'AuditHistoryRecord.php' => 'owner',
            'AuditHistoryScalar.php' => 'helper',
            'AuditHistorySegmentLifecycle.php' => 'helper',
            'AuditHistoryTag.php' => 'owner',
            'AuditHistoryWorkRelations.php' => 'helper',
        ];
    }

    /** @return array<string,array<string,array{visibility:string,static:bool,return:string,params:list<array{name:string,type:string,sensitive:bool}>}>> */
    private function helperSignatures(): array
    {
        $scalar = static fn(string $name, string $type): array => [
            'name' => $name,
            'type' => $type,
            'sensitive' => true,
        ];
        $array = static fn(string $name = 'v'): array => $scalar($name, 'array');
        return [
            'AuditHistoryScalar' => [
                '__construct' => ['visibility' => 'private', 'static' => false, 'return' => 'void', 'params' => []],
                'admit' => [
                    'visibility' => 'public',
                    'static' => true,
                    'return' => 'bool|int|string|null',
                    'params' => [$scalar('kind', 'string'), $scalar('value', 'mixed')],
                ],
                'admitBoolean' => [
                    'visibility' => 'private',
                    'static' => true,
                    'return' => 'bool',
                    'params' => [$scalar('value', 'mixed')],
                ],
                'admitInteger' => [
                    'visibility' => 'private',
                    'static' => true,
                    'return' => 'int',
                    'params' => [$scalar('kind', 'string'), $scalar('value', 'mixed')],
                ],
                'admitString' => [
                    'visibility' => 'private',
                    'static' => true,
                    'return' => 'string',
                    'params' => [$scalar('kind', 'string'), $scalar('value', 'mixed')],
                ],
            ],
            'AuditHistoryContextRelations' => [
                '__construct' => ['visibility' => 'private', 'static' => false, 'return' => 'void', 'params' => []],
                'header' => ['visibility' => 'public', 'static' => true, 'return' => 'void', 'params' => [$array()]],
                'externalAnchor' => [
                    'visibility' => 'public',
                    'static' => true,
                    'return' => 'void',
                    'params' => [$array()],
                ],
                'partition' => ['visibility' => 'public', 'static' => true, 'return' => 'void', 'params' => [$array()]],
                'recordEndpoint' => [
                    'visibility' => 'public',
                    'static' => true,
                    'return' => 'void',
                    'params' => [$array()],
                ],
                'stagedBegin' => [
                    'visibility' => 'public',
                    'static' => true,
                    'return' => 'void',
                    'params' => [$array()],
                ],
            ],
            'AuditHistorySegmentLifecycle' => [
                '__construct' => ['visibility' => 'private', 'static' => false, 'return' => 'void', 'params' => []],
                'assertValid' => [
                    'visibility' => 'public',
                    'static' => true,
                    'return' => 'void',
                    'params' => [$array()],
                ],
            ],
            'AuditHistoryClosedSegmentRelations' => [
                '__construct' => ['visibility' => 'private', 'static' => false, 'return' => 'void', 'params' => []],
                'proof' => ['visibility' => 'public', 'static' => true, 'return' => 'void', 'params' => [$array()]],
                'binding' => ['visibility' => 'public', 'static' => true, 'return' => 'void', 'params' => [$array()]],
                'version' => [
                    'visibility' => 'public',
                    'static' => true,
                    'return' => 'void',
                    'params' => [$scalar('state', 'string'), $scalar('version', 'int')],
                ],
            ],
            'AuditHistoryWorkRelations' => [
                '__construct' => ['visibility' => 'private', 'static' => false, 'return' => 'void', 'params' => []],
                'presence' => ['visibility' => 'public', 'static' => true, 'return' => 'void', 'params' => [$array()]],
                'reservation' => [
                    'visibility' => 'public',
                    'static' => true,
                    'return' => 'void',
                    'params' => [$array()],
                ],
                'step' => ['visibility' => 'public', 'static' => true, 'return' => 'void', 'params' => [$array()]],
            ],
            'AuditHistoryCheckpointEndRelations' => [
                '__construct' => ['visibility' => 'private', 'static' => false, 'return' => 'void', 'params' => []],
                'assertValid' => [
                    'visibility' => 'public',
                    'static' => true,
                    'return' => 'void',
                    'params' => [$array()],
                ],
            ],
            'AuditHistoryPublicationRelations' => [
                '__construct' => ['visibility' => 'private', 'static' => false, 'return' => 'void', 'params' => []],
                'transition' => [
                    'visibility' => 'public',
                    'static' => true,
                    'return' => 'void',
                    'params' => [$array()],
                ],
                'publication' => [
                    'visibility' => 'public',
                    'static' => true,
                    'return' => 'void',
                    'params' => [$array()],
                ],
                'end' => ['visibility' => 'public', 'static' => true, 'return' => 'void', 'params' => [$array()]],
            ],
        ];
    }

    /** @param array{visibility:string,static:bool,return:string,params:list<array{name:string,type:string,sensitive:bool}>} $expected */
    private function assertMethodSignature(ClassMethod $method, array $expected, string $label): void
    {
        self::assertSame(
            $expected['visibility'],
            $method->isPublic() ? 'public' : ($method->isPrivate() ? 'private' : 'protected'),
            $label . ' visibility',
        );
        self::assertSame($expected['static'], $method->isStatic(), $label . ' static');
        $expectedReturn = $method->name->toString() === '__construct' ? '' : $expected['return'];
        self::assertSame($expectedReturn, $this->typeString($method->returnType), $label . ' return');
        self::assertCount(count($expected['params']), $method->params, $label . ' arguments');
        foreach ($expected['params'] as $index => $parameterExpected) {
            $parameter = $method->params[$index];
            self::assertSame($parameterExpected['name'], $parameter->var->name, $label . ' parameter name');
            self::assertSame(
                $parameterExpected['type'],
                $this->typeString($parameter->type),
                $label . ' parameter type',
            );
            self::assertFalse($parameter->variadic, $label . ' variadic');
            self::assertFalse($parameter->byRef, $label . ' reference');
            self::assertSame(
                $parameterExpected['sensitive'],
                $this->hasSensitiveParameter($parameter),
                $label . ' sensitive',
            );
        }
    }

    private function assertR4Surface(Class_ $class, string $fqcn, int $methodCount, string $propertyName): void
    {
        self::assertCount($methodCount, $class->getMethods(), $fqcn . ' method count');
        $properties = [];
        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $item) {
                $properties[] = $item->name->toString();
                self::assertTrue($property->isPrivate(), $fqcn . ' state visibility');
                self::assertFalse($property->isStatic(), $fqcn . ' state static');
            }
        }
        self::assertSame([$propertyName], $properties, $fqcn . ' detached state');
        foreach (['__debugInfo', '__serialize', '__unserialize'] as $name) {
            self::assertTrue($this->method($class, $name)->isPublic(), $fqcn . '::' . $name . ' public');
        }
        self::assertTrue($this->method($class, '__clone')->isPrivate(), $fqcn . '::clone private');
        self::assertSame(
            'void',
            $this->typeString($this->method($class, '__clone')->returnType),
            $fqcn . '::clone return',
        );
        self::assertSame(
            'array',
            $this->typeString($this->method($class, '__debugInfo')->returnType),
            $fqcn . '::debug return',
        );
        self::assertSame(
            'array',
            $this->typeString($this->method($class, '__serialize')->returnType),
            $fqcn . '::serialize return',
        );
        self::assertSame(
            'void',
            $this->typeString($this->method($class, '__unserialize')->returnType),
            $fqcn . '::unserialize return',
        );
    }

    /** @param list<array{name:string,type:string,sensitive:bool}> $parameters */
    private function assertCarrierMethod(
        Class_ $class,
        string $name,
        string $visibility,
        bool $static,
        string $return,
        array $parameters,
    ): void {
        $method = $this->method($class, $name);
        self::assertSame(
            $visibility,
            $method->isPublic() ? 'public' : ($method->isPrivate() ? 'private' : 'protected'),
            $name . ' visibility',
        );
        self::assertSame($static, $method->isStatic(), $name . ' static');
        self::assertSame(
            $return,
            $name === '__construct' ? '' : $this->typeString($method->returnType),
            $name . ' return',
        );
        self::assertCount(count($parameters), $method->params, $name . ' parameters');
        foreach ($parameters as $index => $expected) {
            $parameter = $method->params[$index];
            self::assertSame($expected['name'], $parameter->var->name, $name . ' parameter name');
            self::assertSame($expected['type'], $this->typeString($parameter->type), $name . ' parameter type');
            self::assertSame(
                $expected['sensitive'],
                $this->hasSensitiveParameter($parameter),
                $name . ' parameter sensitivity',
            );
        }
    }

    /** @return list<int> */
    private function nativeTagValues(): array
    {
        return [
            1,
            3,
            5,
            16,
            17,
            18,
            19,
            21,
            23,
            24,
            25,
            26,
            28,
            31,
            33,
            35,
            48,
            49,
            51,
            52,
            53,
            55,
            64,
            65,
            67,
            68,
            72,
            73,
            75,
            76,
            79,
        ];
    }

    /** @return array<string,list<int>> */
    private function nativeDispatchExpected(): array
    {
        return [
            self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::header' => [1],
            self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::externalAnchor' => [3],
            self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::partition' => [16],
            self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::recordEndpoint' => [21],
            self::HISTORY_NAMESPACE . '\\AuditHistoryContextRelations::stagedBegin' => [52],
            self::HISTORY_NAMESPACE . '\\AuditHistorySegmentLifecycle::assertValid' => [17, 18, 19, 23],
            self::HISTORY_NAMESPACE . '\\AuditHistoryClosedSegmentRelations::proof' => [24],
            self::HISTORY_NAMESPACE . '\\AuditHistoryClosedSegmentRelations::binding' => [49, 53],
            self::HISTORY_NAMESPACE . '\\AuditHistoryWorkRelations::presence' => [25],
            self::HISTORY_NAMESPACE . '\\AuditHistoryWorkRelations::reservation' => [26],
            self::HISTORY_NAMESPACE . '\\AuditHistoryWorkRelations::step' => [28],
            self::HISTORY_NAMESPACE . '\\AuditHistoryCheckpointEndRelations::assertValid' => [35],
            self::HISTORY_NAMESPACE . '\\AuditHistoryPublicationRelations::transition' => [65],
            self::HISTORY_NAMESPACE . '\\AuditHistoryPublicationRelations::publication' => [73, 76],
            self::HISTORY_NAMESPACE . '\\AuditHistoryPublicationRelations::end' => [79],
        ];
    }

    /** @return array<string,string> */
    private function nativeDispatchAnnotations(): array
    {
        return [
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryContextRelations::header' => '/** @var array{schemaVersion:int,phase:int,installationRef:string,schemaName:string,tenantType:?string,tenantId:?string,partitionId:string,segmentId:?string,workId:?string} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryContextRelations::externalAnchor' => '/** @var array{document_sha256:string,policy_version:int,root_policy_digest:string,head_digest:string,revision:int,current_segment_id:string,current_first_sequence:int,current_predecessor_record_hash:?string,closed_segment_tail_digest:?string,policy_state_digest:string,transition_tail_digest:string,acknowledged_transition_digest:?string,transition_ack_digest:?string} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryContextRelations::partition' => '/** @var array{partition_id:string,tenant_type:?string,tenant_id:?string,active_segment_id:string,last_sequence:int,head_record_hash:?string,last_assigned_recorded_at:?string,state_version:int} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryContextRelations::recordEndpoint' => '/** @var array{segment_id:string,sequence:int,previous_record_hash:?string,record_hash:string} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryContextRelations::stagedBegin' => '/** @var array{published_tail_digest:?string,staged_tail_digest:?string,actual_current_segment_id:string,actual_current_first_sequence:int,actual_current_predecessor_record_hash:?string} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistorySegmentLifecycle::assertValid' => '/** @var array{partition_id:string,segment_id:string,state:string,first_sequence:int,final_sequence:?int,head_record_hash:?string,predecessor_record_hash:?string,opened_at:string,closed_at:?string,successor_segment_id:?string,close_fencing_token:?string,state_version:int} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryClosedSegmentRelations::proof' => '/** @var array{partitionId:string,segmentId:string,state:string,stateVersion:int,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,predecessorRecordHash:?string,previousSegmentId:?string,successorSegmentId:string,closeFencingToken:string,closedAt:string} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryClosedSegmentRelations::binding' => '/** @var array{installation_ref:string,binding_digest:string,policy_digest:string,partition_id:string,segment_id:string,first_sequence:int,last_sequence:int,successor_segment_id:string,previous_binding_digest:?string} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryWorkRelations::presence' => '/** @var array{reservation_present:bool,preparation_present:bool,complete_present:bool,step_count:int} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryWorkRelations::reservation' => '/** @var array{work_id:string,installation_ref:string,reservation_digest:string,policy_digest:string,artifact_kind:string,partition_id:string,segment_id:string,production_class:string,segment_binding_digest:?string,checkpoint_reservation_digest:null,intent_id:null,receipt_checkpoint_id:null,anchor_reservation_digest:null,anchor_receipt_id:null} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryWorkRelations::step' => '/** @var array{work_id:string,ordinal:int,reservation_digest:string,previous_step_ordinal:?int,previous_step_digest:string,authorizing_policy_digest:string,phase:string,step_digest:string} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryCheckpointEndRelations::assertValid' => '/** @var array{work_id:?string,reservation_digest:?string,last_ordinal:int,last_step_digest:?string,preparation_checkpoint_id:?string,unsigned_sha256:?string,unsigned_length:?int,candidate_sha256:?string,candidate_length:?int,complete_sha256:?string,complete_length:?int,higher_work_ordinal_present:bool} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryPublicationRelations::transition' => '/** @var array{installation_ref:string,transition_id:string,transition_digest:string,authorizing_policy_digest:string,previous_transition_digest:?string,previous_policy_state_digest:?string,new_policy_state_digest:string,cause:string} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryPublicationRelations::publication' => '/** @var array{installation_ref:string,revision:int,head_digest:string,previous_revision:?int,previous_head_digest:?string,policy_digest:string,policy_state_digest:string,closed_segment_tail_digest:?string,transition_tail_digest:string,acknowledged_transition_digest:?string,transition_ack_digest:?string,document_sha256:string} $admitted */',
            self::HISTORY_NAMESPACE
                . '\\AuditHistoryPublicationRelations::end' => '/** @var array{accepted_revision:int,accepted_head_digest:?string,next_revision:int,next_head_digest:?string,next_document_sha256:?string,higher_than_next_present:bool,staged_tip_binding_digest:?string} $admitted */',
        ];
    }

    private function nativeDispatchContract(ClassMethod $constructor): bool
    {
        $switches = new NodeFinder()->findInstanceOf($constructor, Switch_::class);
        if (count($switches) !== 1 || new NodeFinder()->findInstanceOf($constructor, Node\Expr\Match_::class) !== []) {
            return false;
        }
        $switch = $switches[0];
        if (
            !$switch->cond instanceof PropertyFetch
            || !$switch->cond->var instanceof Variable
            || $switch->cond->var->name !== 'tag'
            || !$switch->cond->name instanceof Identifier
            || $switch->cond->name->toString() !== 'value'
        ) {
            return false;
        }

        $expected = $this->nativeDispatchExpected();
        $annotations = $this->nativeDispatchAnnotations();
        $owner = $this->methodOwner($constructor);
        $admitCalls = $this->staticCalls($constructor, self::HISTORY_NAMESPACE . '\\AuditHistoryScalar', 'admit');
        if (count($admitCalls) !== 1) {
            return false;
        }
        $admission = $this->nativeAdmissionLoop($constructor, $admitCalls[0], $owner);
        if ($admission === null) {
            return false;
        }
        $admittedName = $admission['name'];
        $initializations = array_values(array_filter(
            new NodeFinder()->findInstanceOf($constructor, Assign::class),
            static fn(Assign $assign): bool => (
                $assign->var instanceof Variable
                && $assign->var->name === $admittedName
                && $assign->expr instanceof Array_
            ),
        ));
        if (
            count($initializations) !== 1
            || $initializations[0]->getStartFilePos() >= $admission['loop']->getStartFilePos()
            || $admission['loop']->getEndFilePos() >= $switch->getStartFilePos()
        ) {
            return false;
        }
        $retentions = array_values(array_filter(
            new NodeFinder()->findInstanceOf($constructor, New_::class),
            static fn(New_ $new): bool => (
                $new->class instanceof Name
                && ltrim($new->class->toString(), '\\') === 'SensitiveParameterValue'
            ),
        ));
        if (
            count($retentions) !== 1
            || $switch->getStartFilePos() >= $retentions[0]->getStartFilePos()
            || !$this->nativeRetentionUsesVariable($retentions[0], $admittedName)
        ) {
            return false;
        }
        $closedAnnotations = [];
        foreach ($annotations as $annotation) {
            $closedAnnotations[$this->nativeNormalizeDocumentation($annotation)] = true;
        }
        foreach ($this->nativeDocumentation($constructor) as $position => $documentation) {
            if (
                isset($closedAnnotations[$this->nativeNormalizeDocumentation($documentation)])
                && ($position < $switch->getStartFilePos() || $position >= $retentions[0]->getStartFilePos())
            ) {
                return false;
            }
        }
        foreach (new NodeFinder()->findInstanceOf($constructor, Assign::class) as $assign) {
            if (
                $assign->getStartFilePos() > $admission['loop']->getEndFilePos()
                && $assign->getStartFilePos() < $retentions[0]->getStartFilePos()
                && $this->rootVariableName($assign->var) === $admittedName
            ) {
                return false;
            }
        }

        $targetCounts = [];
        foreach ($this->allStaticTargets($constructor) as $target) {
            $targetCounts[$target] = ($targetCounts[$target] ?? 0) + 1;
        }
        $expectedCounts = [
            self::HISTORY_NAMESPACE . '\\AuditHistoryScalar::admit' => 1,
            'ReflectionReference::fromArrayElement' => 1,
        ];
        foreach (array_keys($expected) as $target) {
            $expectedCounts[$target] = 1;
        }
        ksort($targetCounts);
        ksort($expectedCounts);
        if ($targetCounts !== $expectedCounts) {
            return false;
        }

        $actual = [];
        $pendingTags = [];
        $seenTags = [];
        $defaultCount = 0;
        $relationCalls = [];
        foreach ($switch->cases as $index => $case) {
            if (!$case instanceof Case_) {
                return false;
            }
            if ($case->cond === null) {
                if (
                    ++$defaultCount !== 1
                    || $index !== (count($switch->cases) - 1)
                    || $pendingTags !== []
                    || count($case->stmts) !== 1
                    || !$case->stmts[0] instanceof Break_
                    || $case->stmts[0]->num !== null
                    || $this->nativeCaseDocumentation($case) !== []
                ) {
                    return false;
                }
                continue;
            }
            if (!$case->cond instanceof Int_ || in_array($case->cond->value, $seenTags, true)) {
                return false;
            }
            $tag = $case->cond->value;
            $seenTags[] = $tag;
            if ($case->stmts === []) {
                $pendingTags[] = $tag;
                continue;
            }
            $target = $this->nativeCaseBodyContract($case, $owner, $admittedName, $annotations);
            if ($target === null || !array_key_exists($target, $expected)) {
                return false;
            }
            $group = [...$pendingTags, $tag];
            $pendingTags = [];
            $relationCalls[] = $target;
            foreach ($group as $groupTag) {
                $actual[$target][] = $groupTag;
            }
        }
        if ($pendingTags !== [] || $defaultCount !== 1 || count($relationCalls) !== 15) {
            return false;
        }
        foreach ($actual as &$values) {
            sort($values);
        }
        unset($values);
        foreach ($expected as &$values) {
            sort($values);
        }
        unset($values);
        ksort($expected);
        ksort($actual);
        $relationTags = array_merge(...array_values($expected));
        $scalarOnlyTags = array_values(array_diff($this->nativeTagValues(), $relationTags));
        return count($relationTags) === 20 && count($scalarOnlyTags) === 11 && $actual === $expected;
    }

    /** @return array{loop:Foreach_,name:string}|null */
    private function nativeAdmissionLoop(ClassMethod $constructor, StaticCall $admit, string $owner): ?array
    {
        foreach (new NodeFinder()->findInstanceOf($constructor, Foreach_::class) as $loop) {
            if (
                !$loop->expr instanceof Variable
                || $loop->expr->name !== 'schema'
                || !$loop->keyVar instanceof Variable
                || $loop->keyVar->name !== 'key'
                || !$loop->valueVar instanceof Variable
                || $loop->valueVar->name !== 'kind'
                || $loop->byRef
                || count($loop->stmts) !== 1
                || !$loop->stmts[0] instanceof Expression
                || !$loop->stmts[0]->expr instanceof Assign
            ) {
                continue;
            }
            $assignment = $loop->stmts[0]->expr;
            if (
                !$assignment->var instanceof ArrayDimFetch
                || $this->rootVariableName($assignment->var) === null
                || !$assignment->var->dim instanceof Variable
                || $assignment->var->dim->name !== 'key'
                || !$assignment->expr instanceof StaticCall
                || $assignment->expr !== $admit
                || $this->staticTarget($assignment->expr, $owner)
                    !== self::HISTORY_NAMESPACE . '\\AuditHistoryScalar::admit'
                || count($assignment->expr->args) !== 2
                || !$assignment->expr->args[0]->value instanceof Variable
                || $assignment->expr->args[0]->value->name !== 'kind'
                || !$assignment->expr->args[1]->value instanceof ArrayDimFetch
                || !$assignment->expr->args[1]->value->var instanceof Variable
                || $assignment->expr->args[1]->value->var->name !== 'scalars'
                || !$assignment->expr->args[1]->value->dim instanceof Variable
                || $assignment->expr->args[1]->value->dim->name !== 'key'
            ) {
                continue;
            }
            $admittedName = $this->rootVariableName($assignment->var);
            if ($admittedName === null) {
                continue;
            }
            return ['loop' => $loop, 'name' => $admittedName];
        }
        return null;
    }

    private function nativeRetentionUsesVariable(New_ $retention, string $name): bool
    {
        if (count($retention->args) !== 1 || !$retention->args[0]->value instanceof Array_) {
            return false;
        }
        foreach ($retention->args[0]->value->items as $item) {
            if (
                $item !== null
                && $item->key instanceof String_
                && $item->key->value === 'scalars'
                && $item->value instanceof Variable
                && $item->value->name === $name
            ) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int,string> */
    private function nativeCaseDocumentation(Case_ $case): array
    {
        return $this->nativeDocumentation($case);
    }

    /** @return array<int,string> */
    private function nativeDocumentation(Node $node): array
    {
        $comments = [];
        foreach (new NodeFinder()->find(
            $node,
            static fn(Node $node): bool => $node->getDocComment() !== null,
        ) as $node) {
            $comment = $node->getDocComment();
            if ($comment !== null) {
                $comments[$comment->getStartFilePos()] = $comment->getText();
            }
        }
        ksort($comments);
        return $comments;
    }

    /** @param array<string,string> $annotations */
    private function nativeCaseBodyContract(
        Case_ $case,
        string $owner,
        string $admittedName,
        array $annotations,
    ): ?string {
        if (
            count($case->stmts) < 2
            || count($case->stmts) > 3
            || !$case->stmts[count($case->stmts) - 1] instanceof Break_
            || $case->stmts[count($case->stmts) - 1]->num !== null
            || !$case->stmts[count($case->stmts) - 2] instanceof Expression
        ) {
            return null;
        }
        if (count($case->stmts) === 3 && !$case->stmts[0] instanceof Node\Stmt\Nop) {
            return null;
        }
        $expression = $case->stmts[count($case->stmts) - 2];
        if (!$expression instanceof Expression || !$expression->expr instanceof StaticCall) {
            return null;
        }
        $call = $expression->expr;
        if (
            count($call->args) !== 1
            || !$call->args[0]->value instanceof Variable
            || $call->args[0]->value->name !== $admittedName
        ) {
            return null;
        }
        $target = $this->staticTarget($call, $owner);
        if ($target === null || !array_key_exists($target, $annotations)) {
            return null;
        }
        $documentation = $this->nativeCaseDocumentation($case);
        if (count($documentation) !== 1) {
            return null;
        }
        $commentPosition = array_key_first($documentation);
        if (
            !is_int($commentPosition)
            || $commentPosition < $case->getStartFilePos()
            || $commentPosition >= $call->getStartFilePos()
            || $this->nativeNormalizeDocumentation(
                $documentation[$commentPosition],
            ) !== $this->nativeNormalizeDocumentation($annotations[$target])
        ) {
            return null;
        }
        return $target;
    }

    private function nativeNormalizeDocumentation(string $documentation): string
    {
        return str_replace([' ', "\t", "\r", "\n"], '', trim($documentation));
    }

    /** @return array<string,int> */
    private function nativeDispatchDescriptor(string $source): array
    {
        $class = $this->classDeclaration(
            $this->parseSource($source),
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
        );
        $targets = [];
        foreach ($this->allStaticTargets($this->method($class, '__construct')) as $target) {
            if (str_starts_with($target, self::HISTORY_NAMESPACE . '\\AuditHistory')) {
                $targets[$target] = ($targets[$target] ?? 0) + 1;
            }
        }
        ksort($targets);
        return $targets;
    }

    /** @return list<string> */
    private function forbiddenDependencyInventory(string $source): array
    {
        $violations = [];
        foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), ClassMethod::class) as $method) {
            foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
                if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
                    continue;
                }
                $target = $this->resolvedClass($call->class, '') . '::' . $call->name->toString();
                if ($this->isForbiddenTarget($target)) {
                    $violations[] = $target;
                }
            }
            foreach (new NodeFinder()->findInstanceOf($method, MethodCall::class) as $call) {
                if (
                    $call->name instanceof Identifier
                    && in_array(
                        strtolower($call->name->toString()),
                        [
                            'tag',
                            'scalars',
                            'get',
                            'getvalue',
                            'trust',
                            'current',
                            'schema',
                            'query',
                            'prepare',
                            'execute',
                        ],
                        true,
                    )
                ) {
                    $violations[] = 'method:' . $call->name->toString();
                }
            }
            foreach (new NodeFinder()->findInstanceOf($method, New_::class) as $new) {
                if ($new->class instanceof Name && $this->isForbiddenTarget($this->resolvedClass($new->class, ''))) {
                    $violations[] = 'new:' . $this->resolvedClass($new->class, '');
                }
            }
            foreach (new NodeFinder()->findInstanceOf($method, FuncCall::class) as $call) {
                if (
                    $call->name instanceof Name
                    && in_array(
                        strtolower($call->name->toString()),
                        ['call_user_func', 'forward_static_call', 'eval', 'query', 'prepare', 'execute'],
                        true,
                    )
                ) {
                    $violations[] = 'function:' . $call->name->toString();
                }
            }
        }
        sort($violations);
        return $violations;
    }

    private function isForbiddenTarget(string $target): bool
    {
        $value = strtolower($target);
        foreach ([
            'audithistorygroupcodec',
            'audithistorynativegroup',
            'audithistoryrecord',
            'postgresql',
            'pdo',
            'sql',
            'database',
            'connection',
            'trust',
            'f1',
            'f2',
            'currentauthority',
            'currenttrust',
        ] as $fragment) {
            if (str_contains($value, $fragment)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    private function dynamicInvocationInventory(string $source): array
    {
        $violations = [];
        foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), StaticCall::class) as $call) {
            if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
                $violations[] = 'dynamic:static';
            }
        }
        foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), MethodCall::class) as $call) {
            if (!$call->name instanceof Identifier) {
                $violations[] = 'dynamic:method';
            }
        }
        foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), FuncCall::class) as $call) {
            if (!$call->name instanceof Name) {
                $violations[] = 'dynamic:function';
            }
        }
        foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), New_::class) as $new) {
            if (!$new->class instanceof Name) {
                $violations[] = 'dynamic:new';
            }
        }
        sort($violations);
        return $violations;
    }

    /** @return array<string,array<string,int>> */
    private function helperCallerInventory(array $sources): array
    {
        $inventory = [];
        foreach ($sources as $relative => $source) {
            $nodes = $this->parseSource($source);
            foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
                $owner = $this->declarationName($class);
                foreach ($class->getMethods() as $method) {
                    foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
                        $target = $this->staticTarget($call, $owner);
                        if ($target === null || !$this->isHistoryHelperTarget($target)) {
                            continue;
                        }
                        $key = $relative . '::' . $method->name->toString();
                        $inventory[$key][$target] = ($inventory[$key][$target] ?? 0) + 1;
                    }
                }
            }
        }
        ksort($inventory);
        foreach ($inventory as &$calls) {
            ksort($calls);
        }
        unset($calls);
        return $inventory;
    }

    /** @return list<string> */
    private function helperConstructorCallers(array $sources): array
    {
        $calls = [];
        $helpers = array_map(
            fn(string $shortName): string => self::HISTORY_NAMESPACE . '\\' . $shortName,
            array_keys($this->helperSignatures()),
        );
        foreach ($sources as $source) {
            foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), New_::class) as $new) {
                if (!$new->class instanceof Name) {
                    continue;
                }
                $target = $this->resolvedClass($new->class, '');
                if (in_array($target, $helpers, true)) {
                    $calls[] = $target;
                }
            }
        }
        sort($calls);
        return $calls;
    }

    private function isHistoryHelperTarget(string $target): bool
    {
        foreach (array_keys($this->helperSignatures()) as $shortName) {
            if (str_starts_with($target, self::HISTORY_NAMESPACE . '\\' . $shortName . '::')) {
                return true;
            }
        }
        return false;
    }

    /** @return list<StaticCall> */
    private function staticCalls(ClassMethod $method, string $class, string $name): array
    {
        $owner = $this->methodOwner($method);
        return array_values(array_filter(
            new NodeFinder()->findInstanceOf($method, StaticCall::class),
            fn(StaticCall $call): bool => $this->staticTarget($call, $owner) === $class . '::' . $name,
        ));
    }

    /** @return list<string> */
    private function allStaticTargets(ClassMethod $method): array
    {
        $owner = $this->methodOwner($method);
        $targets = [];
        foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
            $target = $this->staticTarget($call, $owner);
            if ($target !== null) {
                $targets[] = $target;
            }
        }
        return $targets;
    }

    /** @return list<MethodCall> */
    private function namedMethodCalls(Node $node, string $name): array
    {
        return array_values(array_filter(
            new NodeFinder()->findInstanceOf($node, MethodCall::class),
            static fn(MethodCall $call): bool => $call->name instanceof Identifier && $call->name->toString() === $name,
        ));
    }

    /** @return list<FuncCall> */
    private function namedFunctionCalls(Node $node, string $name): array
    {
        return array_values(array_filter(
            new NodeFinder()->findInstanceOf($node, FuncCall::class),
            static fn(FuncCall $call): bool => (
                $call->name instanceof Name
                && strtolower($call->name->toString()) === strtolower($name)
            ),
        ));
    }

    private function jcsMethodCall(ClassMethod $method, string $name): ?MethodCall
    {
        foreach ($this->namedMethodCalls($method, $name) as $call) {
            if ($call->var instanceof Variable && $call->var->name === 'this') {
                continue;
            }
            return $call;
        }
        return null;
    }

    private function hasThrowableBoundary(ClassMethod $method): bool
    {
        foreach (new NodeFinder()->findInstanceOf($method, Node\Stmt\TryCatch::class) as $try) {
            foreach ($try->catches as $catch) {
                foreach ($catch->types as $type) {
                    if ($type instanceof Name && $this->terminal($type->toString()) === 'Throwable') {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function directRejectingIf(ClassMethod $method, Node $needle, int $before): ?If_
    {
        foreach ($method->stmts ?? [] as $statement) {
            if (
                !$statement instanceof If_
                || $statement->getStartFilePos() >= $before
                || !$this->isEffectiveOrDisjunct($statement->cond, $needle)
                || $this->containsFalse($statement->cond)
            ) {
                continue;
            }
            foreach ($statement->stmts as $nested) {
                if ($nested instanceof Expression && $nested->expr instanceof Throw_) {
                    return $statement;
                }
            }
        }
        return null;
    }

    private function isEffectiveOrDisjunct(Node $condition, Node $needle): bool
    {
        foreach ($this->orDisjuncts($condition) as $disjunct) {
            if ($disjunct === $needle) {
                return true;
            }
        }
        return false;
    }

    /** @return list<Node> */
    private function orDisjuncts(Node $condition): array
    {
        if (!$condition instanceof BooleanOr && !$condition instanceof LogicalOr) {
            return [$condition];
        }
        return [
            ...$this->orDisjuncts($condition->left),
            ...$this->orDisjuncts($condition->right),
        ];
    }

    private function directTopLevelAssignment(ClassMethod $method, Assign $assignment, int $before): bool
    {
        foreach ($method->stmts ?? [] as $statement) {
            if (
                $statement instanceof Expression
                && $statement->getStartFilePos() < $before
                && $statement->expr === $assignment
            ) {
                return true;
            }
        }
        return false;
    }

    private function containsFalse(Node $node): bool
    {
        foreach (new NodeFinder()->findInstanceOf($node, ConstFetch::class) as $constant) {
            if (strtolower($constant->name->toString()) === 'false') {
                return true;
            }
        }
        return false;
    }

    private function hasPreflightByteBound(ClassMethod $method, int $before): bool
    {
        foreach (new NodeFinder()->findInstanceOf($method, Greater::class) as $comparison) {
            if (
                $comparison->getStartFilePos() >= $before
                || !$comparison->left instanceof Variable
                || $comparison->left->name !== 'length'
                || !$comparison->right instanceof Int_
                || $comparison->right->value !== 16_384
            ) {
                continue;
            }
            return $this->directRejectingIf($method, $comparison, $before) !== null;
        }
        return false;
    }

    private function hasPostCanonicalByteBound(ClassMethod $method, MethodCall $canonical): bool
    {
        $encodedName = null;
        foreach (new NodeFinder()->findInstanceOf($method, Assign::class) as $assignment) {
            if (
                $assignment->expr === $canonical
                && $assignment->var instanceof Variable
                && is_string($assignment->var->name)
            ) {
                $encodedName = $assignment->var->name;
                break;
            }
        }
        if ($encodedName === null) {
            return false;
        }
        $returnPositions = array_values(array_filter(
            array_map(
                static fn(Return_ $return): int => $return->getStartFilePos(),
                new NodeFinder()->findInstanceOf($method, Return_::class),
            ),
            static fn(int $position): bool => $position > $canonical->getStartFilePos(),
        ));
        if ($returnPositions === []) {
            return false;
        }
        $returnPosition = min($returnPositions);
        foreach ([
            ...new NodeFinder()->findInstanceOf($method, Greater::class),
            ...new NodeFinder()->findInstanceOf($method, GreaterOrEqual::class),
        ] as $comparison) {
            if (
                $comparison->getStartFilePos() <= $canonical->getStartFilePos()
                || $comparison->getStartFilePos() >= $returnPosition
                || !$this->containsInteger($comparison, 16_384)
            ) {
                continue;
            }
            $lengthCalls = $this->namedFunctionCalls($comparison, 'strlen');
            if (
                count($lengthCalls) !== 1
                || count($lengthCalls[0]->args) < 1
                || !$lengthCalls[0]->args[0]->value instanceof Variable
                || $lengthCalls[0]->args[0]->value->name !== $encodedName
            ) {
                continue;
            }
            if ($this->directRejectingIf($method, $comparison, $returnPosition) !== null) {
                return true;
            }
        }
        return false;
    }

    private function hasOriginalBoundaryChecks(ClassMethod $method, int $before): bool
    {
        $first = null;
        $last = null;
        foreach (new NodeFinder()->findInstanceOf($method, NotIdentical::class) as $comparison) {
            if (
                $comparison->getStartFilePos() >= $before
                || !$comparison->left instanceof ArrayDimFetch
                || !$comparison->left->var instanceof Variable
                || $comparison->left->var->name !== 'bytes'
                || !$comparison->right instanceof String_
            ) {
                continue;
            }
            $fetch = $comparison->left;
            if ($fetch->dim instanceof Int_ && $fetch->dim->value === 0 && $comparison->right->value === '{') {
                $first = $comparison;
            }
            if (
                $fetch->dim instanceof Minus
                && $fetch->dim->left instanceof Variable
                && $fetch->dim->left->name === 'length'
                && $fetch->dim->right instanceof Int_
                && $fetch->dim->right->value === 1
                && $comparison->right->value === '}'
            ) {
                $last = $comparison;
            }
        }
        if ($first === null || $last === null) {
            return false;
        }
        $firstGuard = $this->directRejectingIf($method, $first, $before);
        $lastGuard = $this->directRejectingIf($method, $last, $before);
        return $firstGuard !== null && $firstGuard === $lastGuard;
    }

    private function hasRawLineChecks(ClassMethod $method, int $before): bool
    {
        $calls = [];
        foreach ($this->namedFunctionCalls($method, 'str_contains') as $call) {
            if (
                $call->getStartFilePos() >= $before
                || count($call->args) < 2
                || !$call->args[0]->value instanceof Variable
                || $call->args[0]->value->name !== 'bytes'
                || !$call->args[1]->value instanceof String_
            ) {
                continue;
            }
            $calls[$call->args[1]->value->value] = $call;
        }
        if (!isset($calls["\n"], $calls["\r"])) {
            return false;
        }
        $newlineGuard = $this->directRejectingIf($method, $calls["\n"], $before);
        $carriageGuard = $this->directRejectingIf($method, $calls["\r"], $before);
        return $newlineGuard !== null && $newlineGuard === $carriageGuard;
    }

    private function hasFlatPcreContract(ClassMethod $method, int $before): bool
    {
        $replace = $this->namedFunctionCalls($method, 'preg_replace')[0] ?? null;
        $match = $this->namedFunctionCalls($method, 'preg_match')[0] ?? null;
        if (
            $replace === null
            || $match === null
            || $replace->getStartFilePos() >= $before
            || $match->getStartFilePos() >= $before
            || count($replace->args) < 3
            || !$replace->args[0]->value instanceof String_
            || $replace->args[0]->value->value !== '/"(?:\\\\.|[^"\\\\])*"/s'
            || !$replace->args[1]->value instanceof String_
            || $replace->args[1]->value->value !== ''
            || !$replace->args[2]->value instanceof Variable
            || $replace->args[2]->value->name !== 'bytes'
            || count($match->args) < 2
            || !$match->args[0]->value instanceof String_
            || $match->args[0]->value->value !== '/\\A\\{[^{}\\[\\]"]*\\}\\z/D'
        ) {
            return false;
        }
        $stripped = null;
        $assignment = null;
        foreach (new NodeFinder()->findInstanceOf($method, Assign::class) as $assign) {
            if ($assign->expr === $replace && $assign->var instanceof Variable && is_string($assign->var->name)) {
                $stripped = $assign->var->name;
                $assignment = $assign;
                break;
            }
        }
        if (
            $stripped === null
            || $assignment === null
            || !$this->directTopLevelAssignment($method, $assignment, $before)
            || !$match->args[1]->value instanceof Variable
            || $match->args[1]->value->name !== $stripped
        ) {
            return false;
        }
        $nullComparison = null;
        foreach (new NodeFinder()->findInstanceOf($method, Identical::class) as $comparison) {
            if (
                $comparison->getStartFilePos() < $before
                && $comparison->left instanceof Variable
                && $comparison->left->name === $stripped
                && $comparison->right instanceof ConstFetch
                && strtolower($comparison->right->name->toString()) === 'null'
            ) {
                $nullComparison = $comparison;
                break;
            }
        }
        $pcreComparison = null;
        foreach (new NodeFinder()->findInstanceOf($method, NotIdentical::class) as $comparison) {
            if (
                $comparison->getStartFilePos() < $before
                && $comparison->left === $match
                && $comparison->right instanceof Int_
                && $comparison->right->value === 1
            ) {
                $pcreComparison = $comparison;
                break;
            }
        }
        if ($nullComparison === null || $pcreComparison === null) {
            return false;
        }
        $nullGuard = $this->directRejectingIf($method, $nullComparison, $before);
        $pcreGuard = $this->directRejectingIf($method, $pcreComparison, $before);
        return (
            $nullGuard !== null
            && $pcreGuard !== null
            && $nullGuard === $pcreGuard
            && !$this->containsFalse($nullGuard->cond)
            && $assignment->getStartFilePos() < $nullComparison->getStartFilePos()
            && $nullComparison->getStartFilePos() < $match->getStartFilePos()
        );
    }

    private function codecPreflightContract(ClassMethod $method): bool
    {
        $jcs = $this->jcsMethodCall($method, 'decode');
        return (
            $jcs !== null
            && $this->hasPreflightByteBound($method, $jcs->getStartFilePos())
            && $this->hasOriginalBoundaryChecks($method, $jcs->getStartFilePos())
            && $this->hasRawLineChecks($method, $jcs->getStartFilePos())
            && $this->hasFlatPcreContract($method, $jcs->getStartFilePos())
        );
    }

    private function codecEncodeContract(ClassMethod $method): bool
    {
        $scalars = array_values(array_filter(
            new NodeFinder()->findInstanceOf($method, MethodCall::class),
            static fn(MethodCall $call): bool => (
                $call->name instanceof Identifier
                && $call->name->toString() === 'scalars'
                && $call->var instanceof Variable
                && $call->var->name === 'group'
            ),
        ));
        $tags = array_values(array_filter(
            new NodeFinder()->findInstanceOf($method, MethodCall::class),
            static fn(MethodCall $call): bool => (
                $call->name instanceof Identifier
                && $call->name->toString() === 'tag'
                && $call->var instanceof Variable
                && $call->var->name === 'group'
            ),
        ));
        $canonical = $this->namedMethodCalls($method, 'canonicalJson');
        return (
            count($scalars) === 1
            && $tags === []
            && count($canonical) === 1
            && $this->hasPostCanonicalByteBound($method, $canonical[0])
            && $this->hasThrowableBoundary($method)
        );
    }

    private function decodedNativeDataFlow(ClassMethod $method): bool
    {
        $toNative = $this->jcsMethodCall($method, 'toNative');
        if ($toNative === null || !$toNative->var instanceof Variable || $toNative->var->name !== 'codec') {
            return false;
        }
        $toNativeAssignments = array_values(array_filter(
            new NodeFinder()->findInstanceOf($method, Assign::class),
            static fn(Assign $assign): bool => (
                $assign->expr === $toNative
                && $assign->var instanceof Variable
                && is_string($assign->var->name)
            ),
        ));
        if (count($toNativeAssignments) !== 1) {
            return false;
        }
        $originAssignment = $toNativeAssignments[0];
        if (!$originAssignment->var instanceof Variable || !is_string($originAssignment->var->name)) {
            return false;
        }
        $nativeName = $originAssignment->var->name;
        if ($nativeName !== 'native') {
            return false;
        }
        $fromDecoded = $this->staticCalls(
            $method,
            self::HISTORY_NAMESPACE . '\\AuditHistoryNativeGroup',
            'fromDecoded',
        );
        if (
            count($fromDecoded) !== 1
            || count($fromDecoded[0]->args) !== 2
            || !$fromDecoded[0]->args[0]->value instanceof Variable
            || $fromDecoded[0]->args[0]->value->name !== 'tag'
            || !$fromDecoded[0]->args[1]->value instanceof Variable
        ) {
            return false;
        }
        $factoryPosition = $fromDecoded[0]->getStartFilePos();
        $canonicalAssignments = [];
        foreach (new NodeFinder()->findInstanceOf($method, Assign::class) as $assign) {
            if (
                $assign->getStartFilePos() <= $originAssignment->getStartFilePos()
                || $assign->getStartFilePos() >= $factoryPosition
                || !$assign->var instanceof Variable
                || !is_string($assign->var->name)
                || !$assign->expr instanceof MethodCall
                || !$assign->expr->var instanceof Variable
                || $assign->expr->var->name !== 'codec'
                || !$assign->expr->name instanceof Identifier
                || $assign->expr->name->toString() !== 'canonicalJson'
                || count($assign->expr->args) !== 1
                || !$assign->expr->args[0]->value instanceof Variable
            ) {
                continue;
            }
            $canonicalAssignments[] = $assign;
        }
        if (count($canonicalAssignments) !== 1) {
            return false;
        }
        $canonicalAssignment = $canonicalAssignments[0];
        if (
            !$canonicalAssignment->expr instanceof MethodCall
            || !$canonicalAssignment->var instanceof Variable
            || !is_string($canonicalAssignment->var->name)
        ) {
            return false;
        }
        $canonicalCall = $canonicalAssignment->expr;
        $canonicalInput = $canonicalCall->args[0]->value;
        if (!$canonicalInput instanceof Variable || !is_string($canonicalInput->name)) {
            return false;
        }
        $canonicalName = $canonicalAssignment->var->name;
        if ($canonicalName !== 'canonical') {
            return false;
        }
        if (!$this->hasFiniteDecodeFlow(
            $method,
            $originAssignment,
            $canonicalAssignment,
            $fromDecoded[0],
            $factoryPosition,
            $nativeName,
            $canonicalName,
            $canonicalInput->name,
        )) {
            return false;
        }
        $canonicalComparison = [];
        foreach (new NodeFinder()->findInstanceOf($method, NotIdentical::class) as $comparison) {
            if (
                $comparison->getStartFilePos() > $canonicalAssignment->getStartFilePos()
                && $comparison->getStartFilePos() < $factoryPosition
                && $comparison->left instanceof Variable
                && $comparison->left->name === 'bytes'
                && $comparison->right instanceof Variable
                && $comparison->right->name === $canonicalAssignment->var->name
            ) {
                $canonicalComparison[] = $comparison;
            }
        }
        if (
            count($canonicalComparison) !== 1
            || $this->directRejectingIf($method, $canonicalComparison[0], $factoryPosition) === null
        ) {
            return false;
        }
        return true;
    }

    private function hasFiniteDecodeFlow(
        ClassMethod $method,
        Assign $originAssignment,
        Assign $canonicalAssignment,
        StaticCall $fromDecoded,
        int $factoryPosition,
        string $nativeName,
        string $canonicalName,
        string $canonicalInput,
    ): bool {
        $statements = $method->stmts ?? [];
        if (count($statements) !== 9) {
            return false;
        }
        $isNativeAssignment = $this->namedAssignmentStatement($statements[0], 'isNative');
        if ($isNativeAssignment === null || !$isNativeAssignment->expr instanceof Node\Expr\Match_) {
            return false;
        }
        $match = $isNativeAssignment->expr;
        if (
            !$match->cond instanceof PropertyFetch
            || !$match->cond->var instanceof Variable
            || $match->cond->var->name !== 'tag'
            || !$match->cond->name instanceof Identifier
            || $match->cond->name->toString() !== 'value'
            || !$this->hasExactNativeMatch($match)
        ) {
            return false;
        }
        if (
            !$statements[1] instanceof If_
            || !$this->hasExactNativeTagIf($statements[1], $method)
            || !$statements[3] instanceof If_
            || !$this->hasExactRawLengthIf($statements[3], $method)
            || !$statements[5] instanceof If_
            || !$this->hasExactFlatBoundaryIf($statements[5], $method)
            || !$statements[7] instanceof If_
            || !$this->hasExactCanonicalIf($statements[7], $method, $canonicalName)
        ) {
            return false;
        }
        $lengthAssignment = $this->namedAssignmentStatement($statements[2], 'length');
        if (
            $lengthAssignment === null
            || !$lengthAssignment->expr instanceof FuncCall
            || !$lengthAssignment->expr->name instanceof Name
            || strtolower($lengthAssignment->expr->name->toString()) !== 'strlen'
            || count($lengthAssignment->expr->args) !== 1
            || $lengthAssignment->expr->args[0]->name !== null
            || !$lengthAssignment->expr->args[0]->value instanceof Variable
            || $lengthAssignment->expr->args[0]->value->name !== 'bytes'
        ) {
            return false;
        }
        $withoutStringsAssignment = $this->namedAssignmentStatement($statements[4], 'withoutStrings');
        if (
            $withoutStringsAssignment === null
            || !$withoutStringsAssignment->expr instanceof FuncCall
            || !$withoutStringsAssignment->expr->name instanceof Name
            || strtolower($withoutStringsAssignment->expr->name->toString()) !== 'preg_replace'
            || count($withoutStringsAssignment->expr->args) !== 3
            || !$withoutStringsAssignment->expr->args[0]->name instanceof Identifier
            || $withoutStringsAssignment->expr->args[0]->name->toString() !== 'pattern'
            || !$withoutStringsAssignment->expr->args[0]->value instanceof String_
            || $withoutStringsAssignment->expr->args[0]->value->value !== '/"(?:\\\\.|[^"\\\\])*"/s'
            || !$withoutStringsAssignment->expr->args[1]->name instanceof Identifier
            || $withoutStringsAssignment->expr->args[1]->name->toString() !== 'replacement'
            || !$this->isStringLiteral($withoutStringsAssignment->expr->args[1]->value, '')
            || !$withoutStringsAssignment->expr->args[2]->name instanceof Identifier
            || $withoutStringsAssignment->expr->args[2]->name->toString() !== 'subject'
            || !$withoutStringsAssignment->expr->args[2]->value instanceof Variable
            || $withoutStringsAssignment->expr->args[2]->value->name !== 'bytes'
        ) {
            return false;
        }
        $try = $statements[6];
        if (
            !$try instanceof Node\Stmt\TryCatch
            || $try->finally !== null
            || count($try->catches) !== 1
            || count($try->stmts) < 5
        ) {
            return false;
        }
        $codecAssignment = $this->namedAssignmentStatement($try->stmts[0], 'codec');
        if (
            $codecAssignment === null
            || !$codecAssignment->expr instanceof New_
            || !$codecAssignment->expr->class instanceof Name
            || $this->terminal($this->resolvedClass($codecAssignment->expr->class, $this->methodOwner($method)))
                !== 'AuditJcsCodec'
            || $codecAssignment->expr->args !== []
        ) {
            return false;
        }
        $decodedAssignment = $this->namedAssignmentStatement($try->stmts[1], 'decoded');
        if (
            $decodedAssignment === null
            || !$decodedAssignment->expr instanceof MethodCall
            || !$decodedAssignment->expr->var instanceof Variable
            || $decodedAssignment->expr->var->name !== 'codec'
            || !$decodedAssignment->expr->name instanceof Identifier
            || $decodedAssignment->expr->name->toString() !== 'decode'
            || count($decodedAssignment->expr->args) !== 1
            || $decodedAssignment->expr->args[0]->name !== null
            || !$decodedAssignment->expr->args[0]->value instanceof Variable
            || $decodedAssignment->expr->args[0]->value->name !== 'bytes'
        ) {
            return false;
        }
        $origin = $this->namedAssignmentStatement($try->stmts[2], $nativeName);
        if ($origin === null || $origin !== $originAssignment || !$origin->expr instanceof MethodCall) {
            return false;
        }
        if (
            !$origin->expr->var instanceof Variable
            || $origin->expr->var->name !== 'codec'
            || !$origin->expr->name instanceof Identifier
            || $origin->expr->name->toString() !== 'toNative'
            || count($origin->expr->args) !== 1
            || $origin->expr->args[0]->name !== null
            || !$origin->expr->args[0]->value instanceof Variable
            || $origin->expr->args[0]->value->name !== 'decoded'
        ) {
            return false;
        }
        $canonicalStatement = $try->stmts[count($try->stmts) - 1];
        $canonical = $this->namedAssignmentStatement($canonicalStatement, $canonicalName);
        if ($canonical === null || $canonical !== $canonicalAssignment || !$canonical->expr instanceof MethodCall) {
            return false;
        }
        if (
            !$canonical->expr->var instanceof Variable
            || $canonical->expr->var->name !== 'codec'
            || !$canonical->expr->name instanceof Identifier
            || $canonical->expr->name->toString() !== 'canonicalJson'
            || count($canonical->expr->args) !== 1
            || $canonical->expr->args[0]->name !== null
            || !$canonical->expr->args[0]->value instanceof Variable
            || $canonical->expr->args[0]->value->name !== $canonicalInput
        ) {
            return false;
        }
        $nativeNames = [$nativeName];
        $allowedAssignments = [
            $isNativeAssignment,
            $lengthAssignment,
            $withoutStringsAssignment,
            $codecAssignment,
            $decodedAssignment,
            $origin,
        ];
        $validationCount = 0;
        for ($index = 3, $last = count($try->stmts) - 1; $index < $last; $index++) {
            $statement = $try->stmts[$index];
            if ($statement instanceof If_) {
                if ($validationCount !== 0 || !$this->hasExactNativeValidationIf($statement, $method)) {
                    return false;
                }
                $validationCount++;
                continue;
            }
            $alias = $this->directExpressionAssignment($statement);
            if (
                $alias === null
                || !$alias->var instanceof Variable
                || !is_string($alias->var->name)
                || in_array($alias->var->name, $nativeNames, true)
                || in_array($alias->var->name, ['bytes', 'tag', 'decoded', 'codec', $canonicalName], true)
                || !$alias->expr instanceof Variable
                || !is_string($alias->expr->name)
                || !in_array($alias->expr->name, $nativeNames, true)
            ) {
                return false;
            }
            $nativeNames[] = $alias->var->name;
            $allowedAssignments[] = $alias;
        }
        if (
            $validationCount !== 1
            || !in_array($canonicalInput, $nativeNames, true)
            || $fromDecoded->args[1]->value->name !== $canonicalInput
            || $canonicalAssignment->getStartFilePos() >= $factoryPosition
            || in_array($canonicalName, $nativeNames, true)
        ) {
            return false;
        }
        $allowedAssignments[] = $canonicalAssignment;
        foreach (new NodeFinder()->findInstanceOf($method, Assign::class) as $assign) {
            if (!in_array($assign, $allowedAssignments, true)) {
                return false;
            }
        }
        $catch = $try->catches[0];
        if (
            count($catch->types) !== 1
            || !$catch->types[0] instanceof Name
            || $this->terminal($this->resolvedClass($catch->types[0], $this->methodOwner($method))) !== 'Throwable'
            || $catch->var !== null
            || count($catch->stmts) !== 1
            || !$this->isDirectInvalidArgumentThrow($catch->stmts[0], $method)
        ) {
            return false;
        }
        if (!$statements[8] instanceof Return_ || $statements[8]->expr !== $fromDecoded) {
            return false;
        }
        return true;
    }

    private function hasExactNativeMatch(Node\Expr\Match_ $match): bool
    {
        if (count($match->arms) !== 2 || $match->arms[0]->conds === null || $match->arms[1]->conds !== null) {
            return false;
        }
        $nativeValues = $this->nativeTagValues();
        if (count($match->arms[0]->conds) !== count($nativeValues)) {
            return false;
        }
        foreach ($nativeValues as $index => $value) {
            if (!$match->arms[0]->conds[$index] instanceof Int_ || $match->arms[0]->conds[$index]->value !== $value) {
                return false;
            }
        }
        return (
            $this->isBooleanConstant($match->arms[0]->body, 'true')
            && $this->isBooleanConstant($match->arms[1]->body, 'false')
        );
    }

    private function hasExactNativeTagIf(If_ $statement, ClassMethod $method): bool
    {
        return (
            $this->hasDirectInvalidArgumentThrowIf($statement, $method)
            && $statement->cond instanceof Node\Expr\BooleanNot
            && $statement->cond->expr instanceof Variable
            && $statement->cond->expr->name === 'isNative'
        );
    }

    private function hasExactRawLengthIf(If_ $statement, ClassMethod $method): bool
    {
        if (!$this->hasDirectInvalidArgumentThrowIf($statement, $method)) {
            return false;
        }
        $terms = $this->orDisjuncts($statement->cond);
        if (count($terms) !== 4) {
            return false;
        }
        if (
            !$terms[0] instanceof Identical
            || !$terms[0]->left instanceof Variable
            || $terms[0]->left->name !== 'bytes'
            || !$this->isStringLiteral($terms[0]->right, '')
            || !$terms[1] instanceof Greater
            || !$terms[1]->left instanceof Variable
            || $terms[1]->left->name !== 'length'
            || !$terms[1]->right instanceof Int_
            || $terms[1]->right->value !== 16_384
        ) {
            return false;
        }
        if (!$this->isExactStrContains($terms[2], "\r") || !$this->isExactStrContains($terms[3], "\n")) {
            return false;
        }
        return true;
    }

    private function hasExactFlatBoundaryIf(If_ $statement, ClassMethod $method): bool
    {
        if (!$this->hasDirectInvalidArgumentThrowIf($statement, $method)) {
            return false;
        }
        $terms = $this->orDisjuncts($statement->cond);
        if (count($terms) !== 4) {
            return false;
        }
        if (
            !$terms[0] instanceof NotIdentical
            || !$this->isExactArrayIndex($terms[0]->left, 'bytes', 0)
            || !$this->isStringLiteral($terms[0]->right, '{')
            || !$terms[1] instanceof NotIdentical
            || !$this->isExactLengthMinusOne($terms[1]->left)
            || !$this->isStringLiteral($terms[1]->right, '}')
            || !$terms[2] instanceof Identical
            || !$this->isVariableNamed($terms[2]->left, 'withoutStrings')
            || !$this->isNullConstant($terms[2]->right)
            || !$terms[3] instanceof NotIdentical
            || !$this->isExactPregMatch($terms[3]->left)
            || !$terms[3]->right instanceof Int_
            || $terms[3]->right->value !== 1
        ) {
            return false;
        }
        return true;
    }

    private function hasExactNativeValidationIf(If_ $statement, ClassMethod $method): bool
    {
        if (!$this->hasDirectInvalidArgumentThrowIf($statement, $method)) {
            return false;
        }
        $terms = $this->orDisjuncts($statement->cond);
        if (count($terms) !== 2 || !$terms[0] instanceof Node\Expr\BooleanNot) {
            return false;
        }
        $isArray = $terms[0]->expr;
        $isList = $terms[1];
        return (
            $this->isExactFunctionCall($isArray, 'is_array', ['native'])
            && $this->isExactFunctionCall($isList, 'array_is_list', ['native'])
        );
    }

    private function hasExactCanonicalIf(If_ $statement, ClassMethod $method, string $canonicalName): bool
    {
        if (!$this->hasDirectInvalidArgumentThrowIf($statement, $method)) {
            return false;
        }
        return (
            $statement->cond instanceof NotIdentical
            && $this->isVariableNamed($statement->cond->left, 'bytes')
            && $this->isVariableNamed($statement->cond->right, $canonicalName)
        );
    }

    private function isExactFunctionCall(Node $node, string $name, array $variableArguments): bool
    {
        if (
            !$node instanceof FuncCall
            || !$node->name instanceof Name
            || strtolower($node->name->toString()) !== strtolower($name)
            || count($node->args) !== count($variableArguments)
        ) {
            return false;
        }
        foreach ($variableArguments as $index => $variableName) {
            if (
                $node->args[$index]->name !== null
                || !$this->isVariableNamed($node->args[$index]->value, $variableName)
            ) {
                return false;
            }
        }
        return true;
    }

    private function isExactStrContains(Node $node, string $needle): bool
    {
        if (
            !$node instanceof FuncCall
            || !$node->name instanceof Name
            || strtolower($node->name->toString()) !== 'str_contains'
            || count($node->args) !== 2
            || $node->args[0]->name !== null
            || $node->args[1]->name !== null
            || !$this->isVariableNamed($node->args[0]->value, 'bytes')
            || !$this->isStringLiteral($node->args[1]->value, $needle)
        ) {
            return false;
        }
        return true;
    }

    private function isExactArrayIndex(Node $node, string $variable, int $index): bool
    {
        return (
            $node instanceof ArrayDimFetch
            && $this->isVariableNamed($node->var, $variable)
            && $node->dim instanceof Int_
            && $node->dim->value === $index
        );
    }

    private function isExactLengthMinusOne(Node $node): bool
    {
        return (
            $node instanceof ArrayDimFetch
            && $this->isVariableNamed($node->var, 'bytes')
            && $node->dim instanceof Minus
            && $this->isVariableNamed($node->dim->left, 'length')
            && $node->dim->right instanceof Int_
            && $node->dim->right->value === 1
        );
    }

    private function isExactPregMatch(Node $node): bool
    {
        return (
            $node instanceof FuncCall
            && $node->name instanceof Name
            && strtolower($node->name->toString()) === 'preg_match'
            && count($node->args) === 2
            && $node->args[0]->name === null
            && $node->args[1]->name === null
            && $this->isStringLiteral($node->args[0]->value, '/\\A\\{[^{}\\[\\]"]*\\}\\z/D')
            && $this->isVariableNamed($node->args[1]->value, 'withoutStrings')
        );
    }

    private function isVariableNamed(Node $node, string $name): bool
    {
        return $node instanceof Variable && $node->name === $name;
    }

    private function isStringLiteral(Node $node, string $value): bool
    {
        return $node instanceof String_ && $node->value === $value;
    }

    private function isNullConstant(Node $node): bool
    {
        return $node instanceof ConstFetch && strtolower($node->name->toString()) === 'null';
    }

    private function isBooleanConstant(Node $node, string $value): bool
    {
        return $node instanceof ConstFetch && strtolower($node->name->toString()) === $value;
    }

    private function directExpressionAssignment(Node $statement): ?Assign
    {
        return $statement instanceof Expression && $statement->expr instanceof Assign ? $statement->expr : null;
    }

    private function namedAssignmentStatement(Node $statement, string $name): ?Assign
    {
        $assignment = $this->directExpressionAssignment($statement);
        return $assignment !== null
        && $assignment->var instanceof Variable
        && is_string($assignment->var->name)
        && $assignment->var->name === $name
            ? $assignment
            : null;
    }

    private function hasDirectInvalidArgumentThrowIf(If_ $statement, ClassMethod $method): bool
    {
        return (
            $statement->elseifs === []
            && $statement->else === null
            && count($statement->stmts) === 1
            && $this->isDirectInvalidArgumentThrow($statement->stmts[0], $method)
        );
    }

    private function isDirectInvalidArgumentThrow(Node $statement, ClassMethod $method): bool
    {
        if (
            !$statement instanceof Expression
            || !$statement->expr instanceof Throw_
            || !$statement->expr->expr instanceof New_
            || !$statement->expr->expr->class instanceof Name
        ) {
            return false;
        }
        return (
            $this->terminal($this->resolvedClass($statement->expr->expr->class, $this->methodOwner($method)))
            === 'InvalidArgumentException'
        );
    }

    private function containsTagUnion(UnionType $type): bool
    {
        return $this->typeParts($type) === self::TAG_UNION;
    }

    /** @return list<string> */
    private function typeParts(?Node $type): array
    {
        if (!$type instanceof UnionType) {
            return [];
        }
        return array_map(fn(Node $part): string => $this->typeString($part), $type->types);
    }

    private function typeString(?Node $type): string
    {
        if ($type === null) {
            return '';
        }
        if ($type instanceof NullableType) {
            return '?' . $this->typeString($type->type);
        }
        if ($type instanceof UnionType) {
            return implode('|', $this->typeParts($type));
        }
        if ($type instanceof Name) {
            $resolved = $type->getAttribute('resolvedName');
            return ltrim($resolved instanceof Name ? $resolved->toString() : $type->toString(), '\\');
        }
        if ($type instanceof Identifier) {
            return $type->toString();
        }
        return $type->getType();
    }

    private function hasSensitiveParameter(Node $parameter): bool
    {
        foreach ($parameter->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($this->terminal($attribute->name->toString()) === 'SensitiveParameter') {
                    return true;
                }
            }
        }
        return false;
    }

    private function containsVariable(Node $node, string $name): bool
    {
        foreach (new NodeFinder()->findInstanceOf($node, Variable::class) as $variable) {
            if ($variable->name === $name) {
                return true;
            }
        }
        return false;
    }

    private function rootVariableName(Node $target): ?string
    {
        while ($target instanceof ArrayDimFetch) {
            $target = $target->var;
        }
        return $target instanceof Variable && is_string($target->name) ? $target->name : null;
    }

    private function containsInteger(Node $node, int $value): bool
    {
        foreach (new NodeFinder()->findInstanceOf($node, Int_::class) as $integer) {
            if ($integer->value === $value) {
                return true;
            }
        }
        return false;
    }

    /** @return list<ClassConst> */
    private function classConstants(Class_ $class, string $name): array
    {
        return array_values(array_filter(
            $class->getConstants(),
            static fn(ClassConst $constant): bool => (
                count($constant->consts) === 1
                && $constant->consts[0]->name->toString() === $name
            ),
        ));
    }

    private function classConstant(Class_ $class, string $name): ?ClassConst
    {
        return $this->classConstants($class, $name)[0] ?? null;
    }

    /** @return list<int|string> */
    private function arrayKeys(Array_ $array): array
    {
        $keys = [];
        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }
            if ($item->key instanceof Int_ || $item->key instanceof String_) {
                $keys[] = $item->key->value;
            }
        }
        return $keys;
    }

    private function method(Class_ $class, string $name): ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() === $name) {
                return $method;
            }
        }
        self::fail('Missing method ' . $name . ' on ' . $this->declarationName($class));
    }

    private function classDeclaration(array $nodes, string $expected): Class_
    {
        $classes = array_values(array_filter(
            new NodeFinder()->findInstanceOf($nodes, Class_::class),
            fn(Class_ $class): bool => $this->declarationName($class) === $expected,
        ));
        self::assertCount(1, $classes, $expected . ' declaration');
        return $classes[0];
    }

    private function declarationName(Node\Stmt\ClassLike $class): string
    {
        return ltrim($class->namespacedName?->toString() ?? $class->name?->toString() ?? '', '\\');
    }

    private function methodOwner(ClassMethod $method): string
    {
        $class = $method->getAttribute('parent');
        return $class instanceof Class_ ? $this->declarationName($class) : self::HISTORY_NAMESPACE;
    }

    private function staticTarget(StaticCall $call, string $owner): ?string
    {
        if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
            return null;
        }
        return $this->resolvedClass($call->class, $owner) . '::' . $call->name->toString();
    }

    private function resolvedClass(Name $name, string $owner): string
    {
        $raw = strtolower($name->toString());
        if (in_array($raw, ['self', 'static'], true)) {
            return $owner;
        }
        $resolved = $name->getAttribute('resolvedName');
        return ltrim($resolved instanceof Name ? $resolved->toString() : $name->toString(), '\\');
    }

    private function terminal(string $name): string
    {
        $parts = explode('\\', trim($name, '\\'));
        return $parts[count($parts) - 1];
    }

    /** @return array<string,string> */
    private function historySources(string $root): array
    {
        $sources = [];
        foreach (glob($root . '/Internal/Audit/Authority/History/*.php') ?: [] as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            $sources[basename($path)] = $source;
        }
        ksort($sources);
        return $sources;
    }

    /** @param array<string,string> $sources @return array<string,list<string>> */
    private function historyOwnerInventory(array $sources): array
    {
        $inventory = [];
        foreach ($sources as $file => $source) {
            $declarations = [];
            foreach (new NodeFinder()->findInstanceOf(
                $this->parseSource($source),
                Node\Stmt\ClassLike::class,
            ) as $declaration) {
                self::assertInstanceOf(Node\Stmt\ClassLike::class, $declaration);
                $declarations[] = $this->declarationName($declaration);
            }
            sort($declarations);
            $inventory[$file] = $declarations;
        }
        ksort($inventory);
        return $inventory;
    }

    /** @return array<string,string> */
    private function sourceFiles(string $root): array
    {
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = realpath($file->getPathname());
            self::assertNotFalse($path);
            $source = file_get_contents($path);
            self::assertIsString($source);
            $sources[str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1))] = $source;
        }
        ksort($sources);
        return $sources;
    }

    private function sourceRoot(): string
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($root);
        return $root;
    }

    private function source(string $relative): string
    {
        $source = file_get_contents($this->sourceRoot() . '/' . $relative);
        self::assertIsString($source, $relative);
        return $source;
    }

    /** @return list<Node> */
    private function parseSource(string $source): array
    {
        $nodes = new ParserFactory()
            ->createForNewestSupportedVersion()
            ->parse($source);
        self::assertIsArray($nodes);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        return $traverser->traverse($nodes);
    }
}

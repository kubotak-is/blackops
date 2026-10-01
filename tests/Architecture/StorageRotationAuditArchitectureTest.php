<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Internal\Audit\Rotation\StorageRotationAuditHook;
use BlackOps\Internal\Audit\Rotation\StorageRotationAuditWriter;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationPhaseHook;
use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

/**
 * Keeps the rotation audit adapter a finite, opt-in source graph.
 *
 * The source checks resolve names before comparing symbols so aliases, fully
 * qualified names and case changes cannot smuggle a forbidden delivery edge.
 */
final class StorageRotationAuditArchitectureTest extends TestCase
{
    private const string INTERFACE = 'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationPhaseHook.php';
    private const string HOOK = 'src/Internal/Audit/Rotation/StorageRotationAuditHook.php';
    private const string WRITER = 'src/Internal/Audit/Rotation/StorageRotationAuditWriter.php';

    public function testFiniteInterfaceAndSoleImplementationAreClosed(): void
    {
        $reflection = new ReflectionClass(PostgreSqlStorageProtectionRotationPhaseHook::class);
        self::assertTrue($reflection->isInterface());
        self::assertSame([], $reflection->getAttributes(PublicApi::class));
        $expected = [
            'assertEntry' => [
                ['connection', Connection::class],
                ['scope',      'BlackOps\\Internal\\StorageProtection\\StorageProtectionRotationScope'],
            ],
            'assertRow' => [
                ['connection', Connection::class],
                ['request',    'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowRequest'],
            ],
            'started' => [
                ['connection', Connection::class],
                ['auditId',    'string'],
                ['scope',      'BlackOps\\Internal\\StorageProtection\\StorageProtectionRotationScope'],
            ],
            'interrupted' => [
                ['connection', Connection::class],
                ['auditId',    'string'],
                ['scope',      'BlackOps\\Internal\\StorageProtection\\StorageProtectionRotationScope'],
            ],
            'progressed' => [
                ['connection', Connection::class],
                ['request',    'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowRequest'],
                ['cursor',     'string'],
            ],
            'finished' => [
                ['connection', Connection::class],
                ['completion', 'BlackOps\\Internal\\StorageProtection\\StorageProtectionRotationAuditCompletion'],
            ],
        ];
        self::assertSame(
            array_keys($expected),
            array_map(static fn(ReflectionMethod $method): string => $method->getName(), $reflection->getMethods()),
        );
        foreach ($expected as $methodName => $parameters) {
            $method = $reflection->getMethod($methodName);
            self::assertSame('void', $this->reflectionTypeName($method->getReturnType()), $methodName);
            self::assertCount(count($parameters), $method->getParameters(), $methodName);
            foreach ($parameters as $index => [$name, $type]) {
                $parameter = $method->getParameters()[$index];
                self::assertSame($name, $parameter->getName(), $methodName . ':' . $index);
                self::assertSame($type, $this->reflectionTypeName($parameter->getType()), $methodName . ':' . $name);
                self::assertCount(1, $parameter->getAttributes(\SensitiveParameter::class), $methodName . ':' . $name);
            }
        }

        $hook = new ReflectionClass(StorageRotationAuditHook::class);
        $writer = new ReflectionClass(StorageRotationAuditWriter::class);
        self::assertTrue($hook->isFinal());
        self::assertTrue($writer->isFinal());
        self::assertTrue($hook->implementsInterface(PostgreSqlStorageProtectionRotationPhaseHook::class));
        self::assertSame([], $writer->getInterfaceNames());
        self::assertSame(
            [
                'src/Internal/Audit/Rotation/StorageRotationAuditHook.php' => StorageRotationAuditHook::class,
            ],
            $this->phaseHookImplementations(),
        );
        self::assertSame(['snapshot'], $this->declaredPropertyNames($hook));
        self::assertSame(['snapshot'], $this->declaredPropertyNames($writer));
    }

    public function testNativePropagationKeepsOneOptionalHookAndNoActivation(): void
    {
        $sources = $this->sources([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php',
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php',
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationBatch.php',
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowExecutor.php',
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php',
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php',
        ]);
        foreach ($sources as $path => $source) {
            $nodes = $this->parse($source);
            self::assertSame([], $this->nativeConstructorViolations($nodes), $path);
            self::assertSame(
                [],
                array_intersect(
                    [StorageRotationAuditHook::class, StorageRotationAuditWriter::class],
                    $this->resolvedConstructors($nodes),
                ),
                $path,
            );
        }
        self::assertContains(
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
            $this->resolvedConstructors($this->parse(
                $sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php'],
            )),
        );
        self::assertSame(
            ['$connection', '$codec', '$schema', '$phaseHook'],
            $this->constructorArguments(
                $this->parse($sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php']),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotation',
            ),
        );
        self::assertContains(
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationAudit',
            $this->resolvedConstructors($this->parse(
                $sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php'],
            )),
        );
        self::assertContains(
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationBatch',
            $this->resolvedConstructors($this->parse(
                $sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php'],
            )),
        );
        self::assertSame(
            ['$connection', '$phaseHook'],
            $this->constructorArguments(
                $this->parse($sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php']),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationAudit',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
            ),
        );
        self::assertSame(
            [
                '$connection',
                '$codec',
                'new:BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationQuery($connection,$codec,$schema)',
                'new:BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationAudit($connection,$phaseHook)',
                '$schema',
                '$phaseHook',
            ],
            $this->constructorArguments(
                $this->parse($sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php']),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationBatch',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
            ),
        );
        self::assertContains(
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowExecutor',
            $this->resolvedConstructors($this->parse(
                $sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationBatch.php'],
            )),
        );
        self::assertSame(
            ['$connection', '$codec', '$query', '$audit', '$phaseHook'],
            $this->constructorArguments(
                $this->parse($sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationBatch.php']),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowExecutor',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationBatch',
            ),
        );
        self::assertContains(
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowTransaction',
            $this->resolvedConstructors($this->parse(
                $sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowExecutor.php'],
            )),
        );
        self::assertSame(
            [
                '$connection',
                '$audit',
                'new:BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationColumnExecutor($connection,$codec,$query)',
                '$phaseHook',
            ],
            $this->constructorArguments(
                $this->parse($sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowExecutor.php']),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowTransaction',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowExecutor',
            ),
        );

        $nullPropagation = str_replace(
            '$connection, $codec, $schema, $phaseHook',
            '$connection, $codec, $schema, null',
            $sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            ['$connection', '$codec', '$schema', '$phaseHook'],
            $this->constructorArguments(
                $this->parse($nullPropagation),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotation',
            ),
            'the entry must propagate its optional hook value unchanged',
        );
        $nestedConnection = str_replace(
            '$connection, $phaseHook',
            '$otherConnection, $phaseHook',
            $sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            ['$connection', '$phaseHook'],
            $this->constructorArguments(
                $this->parse($nestedConnection),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationAudit',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
            ),
            'the Audit edge must retain the native Connection identity',
        );
        $queryConnection = $this->replaceOnce(
            $sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php'],
            'new PostgreSqlStorageProtectionRotationQuery($connection, $codec, $schema)',
            'new PostgreSqlStorageProtectionRotationQuery($otherConnection, $codec, $schema)',
        );
        self::assertNotSame(
            [
                '$connection',
                '$codec',
                'new:BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationQuery($connection,$codec,$schema)',
                'new:BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationAudit($connection,$phaseHook)',
                '$schema',
                '$phaseHook',
            ],
            $this->constructorArguments(
                $this->parse($queryConnection),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationBatch',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
            ),
            'the nested Query constructor must retain the native Connection identity',
        );
        $columnConnection = $this->replaceOnce(
            $sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowExecutor.php'],
            'new PostgreSqlStorageProtectionRotationColumnExecutor($connection, $codec, $query)',
            'new PostgreSqlStorageProtectionRotationColumnExecutor($otherConnection, $codec, $query)',
        );
        self::assertNotSame(
            [
                '$connection',
                '$audit',
                'new:BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationColumnExecutor($connection,$codec,$query)',
                '$phaseHook',
            ],
            $this->constructorArguments(
                $this->parse($columnConnection),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowTransaction',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowExecutor',
            ),
            'the nested ColumnExecutor constructor must retain the native Connection identity',
        );
        $qualifiedEntry = str_replace(
            'new PostgreSqlStorageProtectionRotationEngine(',
            'new \\blackops\\transport\\postgresql\\postgresqlstorageprotectionrotationengine(',
            $sources['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertSame(
            ['$connection', '$codec', '$schema', '$phaseHook'],
            $this->constructorArguments(
                $this->parse($qualifiedEntry),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotation',
            ),
            'FQCN and case changes remain resolved positive controls',
        );

        $allProduction = $this->sourceTree();
        self::assertSame(
            [self::HOOK => [StorageRotationAuditWriter::class]],
            $this->concreteReferenceInventory($allProduction),
        );
        self::assertSame(
            [StorageRotationAuditHook::class => [], StorageRotationAuditWriter::class => []],
            $this->concreteConstructorInventory($allProduction),
        );
        self::assertSame([], $this->concreteActivationViolations($allProduction));
        self::assertSame([], $this->adapterSourceViolations(self::HOOK, $allProduction[self::HOOK]));
        self::assertSame([], $this->adapterSourceViolations(self::WRITER, $allProduction[self::WRITER]));

        $forwardingOutsideConstructor = $allProduction;
        $forwardingOutsideConstructor['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php'] = $this->replaceOnce(
            $forwardingOutsideConstructor['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php'],
            "        \$this->engine = new PostgreSqlStorageProtectionRotationEngine(\$connection, \$codec, \$schema, \$phaseHook);",
            '        // the reviewed forwarding edge must remain in the constructor',
        );
        $forwardingOutsideConstructor['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php'] = $this->replaceOnce(
            $forwardingOutsideConstructor['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php'],
            '        return $this->engine->plan($scope);',
            "        new PostgreSqlStorageProtectionRotationEngine(\$connection, \$codec, \$schema, \$phaseHook);\n"
            . '        return $this->engine->plan($scope);',
        );
        self::assertNotEmpty($this->concreteActivationViolations($forwardingOutsideConstructor));
        self::assertNotSame(
            ['$connection', '$codec', '$schema', '$phaseHook'],
            $this->constructorArguments(
                $this->parse(
                    $forwardingOutsideConstructor['src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php'],
                ),
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotation',
            ),
        );

        $activation = $allProduction;
        $activation['src/External/RotationActivation.php'] = '<?php new \\BlackOps\\Internal\\Audit\\Rotation\\StorageRotationAuditHook($writer);';
        self::assertNotEmpty($this->concreteActivationViolations($activation));
        $activationAlias = $allProduction;
        $activationAlias['src/External/RotationAliasActivation.php'] = <<<'PHP'
            <?php
            use BlackOps\Internal\Audit\Rotation\StorageRotationAuditWriter as RotationWriter;
            new RotationWriter($opaqueIds, $selector, $delivery, $clock, $actor, $correlation, $runtime, $buildId);
            PHP;
        self::assertNotEmpty($this->concreteActivationViolations($activationAlias));
        $activationString = $allProduction;
        $activationString['src/External/RotationStringActivation.php'] = <<<'PHP'
            <?php
            $class = 'BlackOps\Internal\Audit\Rotation\StorageRotationAuditHook';
            class_exists($class);
            PHP;
        self::assertNotEmpty($this->concreteActivationViolations($activationString));
        $activationInsideAdapter = $allProduction;
        $activationInsideAdapter[self::HOOK] = $this->replaceOnce(
            $activationInsideAdapter[self::HOOK],
            "        \$this->snapshot = new \\SensitiveParameterValue(['writer' => \$writer]);",
            "        \$this->snapshot = new \\SensitiveParameterValue(['writer' => \$writer]);\n        new StorageRotationAuditWriter(\$opaqueIds, \$selector, \$delivery, \$clock, \$actor, \$correlation, \$runtime, \$buildId);",
        );
        self::assertNotEmpty($this->concreteActivationViolations($activationInsideAdapter));
        $nativeHookActivation = $allProduction;
        $nativeHookActivation['src/External/RotationNativeHookActivation.php'] = <<<'PHP'
            <?php
            new \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation($connection, $codec, 'blackops', $hook);
            new \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationAudit(
                $connection,
                phaseHook: $hook,
            );
            PHP;
        self::assertNotEmpty($this->concreteActivationViolations($nativeHookActivation));
        self::assertSame(
            [],
            $this->concreteActivationViolations([
                'src/External/RotationNativeNullHook.php' => <<<'PHP'
                    <?php
                    new \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation($connection, $codec, 'blackops', null);
                    new \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationAudit($connection, null);
                    PHP,
            ]),
        );
        self::assertSame(
            [],
            $this->concreteActivationViolations([
                'src/External/RotationReassignedClass.php' => <<<'PHP'
                        <?php
                        $class = 'BlackOps\Internal\Audit\Rotation\StorageRotationAuditHook';
                        $class = 'Unrelated';
                        new $class($writer);
                    PHP,
            ]),
        );
        self::assertNotEmpty($this->concreteActivationViolations([
            'src/External/RotationShadowedClass.php' => <<<'PHP'
                <?php
                $class = 'BlackOps\Internal\Audit\Rotation\StorageRotationAuditHook';
                function unrelated(): void
                {
                    $class = 'Unrelated';
                }
                new $class($writer);
                PHP,
        ]));
        self::assertNotEmpty($this->concreteActivationViolations([
            'src/External/RotationConsumedThenReassigned.php' => <<<'PHP'
                <?php
                $class = 'BlackOps\Internal\Audit\Rotation\StorageRotationAuditHook';
                new $class($writer);
                $class = 'Unrelated';
                PHP,
        ]));
        self::assertNotEmpty($this->concreteActivationViolations([
            'src/External/RotationConsumedClass.php' => <<<'PHP'
                <?php
                $class = 'BlackOps\Internal\Audit\Rotation\StorageRotationAuditHook';
                new $class($writer);
                PHP,
        ]));
        self::assertSame(
            [],
            $this->concreteActivationViolations([
                'src/External/RotationNamedNullHook.php' => <<<'PHP'
                    <?php
                    new \BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotationAudit(
                        $connection,
                        phaseHook: null,
                    );
                    PHP,
            ]),
        );
        self::assertSame(
            [],
            $this->concreteActivationViolations([
                'src/External/RotationUnrelatedDynamic.php' => <<<'PHP'
                    <?php
                    $class = 'Unrelated';
                    new $class($writer);
                    PHP,
            ]),
        );
        self::assertSame(
            [],
            $this->concreteActivationViolations([
                'src/External/RotationAnonymous.php' => <<<'PHP'
                    <?php
                    new class($writer) {};
                    PHP,
            ]),
        );
        self::assertSame(
            [],
            $this->concreteActivationViolations([
                'src/External/RotationInert.php' => "<?php // new \\BlackOps\\Internal\\Audit\\Rotation\\StorageRotationAuditHook(\$writer);\n\"StorageRotationAuditWriter\";",
            ]),
        );
    }

    public function testAdmissionAndPhaseHooksRemainAtNativeRootBoundaries(): void
    {
        $engine = $this->source('src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php');
        self::assertSame([], $this->nativePhaseViolations($this->sourceTree()));
        self::assertSame([], $this->adapterSourceViolations(self::HOOK, $this->source(self::HOOK)));
        self::assertSame([], $this->adapterSourceViolations(self::WRITER, $this->source(self::WRITER)));

        $engineAdmission = str_replace(
            '$this->phaseHook->assertEntry($this->connection, $scope);',
            '// phase hook text in a comment must not satisfy admission\n            $this->phaseHook = null;',
            $engine,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains('entry-helper', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php' => $engineAdmission,
        ]));

        $row = $this->source('src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php');
        $progressCatch = $this->moveProgressedIntoAdvanceTry($row, false);
        self::assertContains('progressed-edge', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php' => $progressCatch,
        ]));

        $audit = $this->source('src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php');
        $interruptedOmission = str_replace(
            '$phaseHook->interrupted($this->connection, $interruptedId, $scope);',
            '// interrupted remains only as inert source text',
            $audit,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertContains('interrupted-edge', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php' => $interruptedOmission,
        ]));

        $interruptedLimit = $this->replaceOnce($audit, 'LIMIT 101', 'LIMIT 100');
        self::assertContains('interrupted-edge', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php' => $interruptedLimit,
        ]));
        $interruptedLimitInert = $this->replaceOnce(
            $interruptedLimit,
            '        $auditIds = $this->interruptedIds($interrupted);',
            "        \$marker = 'LIMIT 101';\n        \$auditIds = \$this->interruptedIds(\$interrupted);",
        );
        self::assertContains('interrupted-edge', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php' => $interruptedLimitInert,
        ]));
        $overflowWithoutRejection = $this->replaceOnce(
            $audit,
            "        if (count(\$interrupted) > 100) {\n            throw new \\RuntimeException('Rotation interruption recovery is too large.');\n        }",
            "        if (count(\$interrupted) > 100) {\n            // the overflow branch must reject the bounded result\n        }",
        );
        self::assertContains('interrupted-edge', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php' => $overflowWithoutRejection,
        ]));

        $beginWithoutCommit = $this->replaceOnce(
            $audit,
            "                \$this->phaseHook->started(\$this->connection, \$auditId, \$scope);\n            }\n            \$this->connection->commit();",
            "                \$this->phaseHook->started(\$this->connection, \$auditId, \$scope);\n            }\n            // the root commit must be represented by the AST oracle",
        );
        self::assertContains('begin-root', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php' => $beginWithoutCommit,
        ]));

        $lateAdmission = $this->replaceOnce(
            $engine,
            "        \$this->assertEntry(\$scope);\n        \$lockKey =",
            "        \$lockKey =",
        );
        $lateAdmission = $this->replaceOnce(
            $lateAdmission,
            "        if (!\$acquired) {",
            "        \$this->assertEntry(\$scope);\n        if (!\$acquired) {",
        );
        self::assertContains('entry-admission', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php' => $lateAdmission,
        ]));

        $startedAfterCommit = $this->replaceOnce(
            $audit,
            "                \$this->phaseHook->started(\$this->connection, \$auditId, \$scope);\n            }\n            \$this->connection->commit();",
            "            }\n            \$this->connection->commit();\n            \$this->phaseHook->started(\$this->connection, \$auditId, \$scope);",
        );
        self::assertContains('begin-phase-order', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php' => $startedAfterCommit,
        ]));

        $finishedAfterCommit = $this->replaceOnce(
            $audit,
            "                \$this->phaseHook->finished(\$this->connection, \$completion);\n            }\n            \$this->connection->commit();",
            "            }\n            \$this->connection->commit();\n            \$this->phaseHook->finished(\$this->connection, \$completion);",
        );
        self::assertContains('finish-phase-order', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php' => $finishedAfterCommit,
        ]));

        $progressedInAdvanceCatch = $this->moveProgressedIntoAdvanceTry($row, true);
        self::assertContains('progressed-edge', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php' =>
                $progressedInAdvanceCatch,
        ]));

        $admissionSwallowed = $this->replaceOnce(
            $row,
            "                    \$this->rollBack();\n                    throw \$exception;",
            "                    \$this->rollBack();\n                    return;",
        );
        self::assertContains('row-admission', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php' => $admissionSwallowed,
        ]));
        $narrowedAdmissionCatch = $this->replaceOnce(
            $row,
            "                } catch (Throwable \$exception) {\n                    \$this->rollBack();\n                    throw \$exception;",
            "                } catch (\\InvalidArgumentException \$exception) {\n                    \$this->rollBack();\n                    throw \$exception;",
        );
        self::assertContains('row-admission', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php' => $narrowedAdmissionCatch,
        ]));
        $admissionRollbackMoved = $this->replaceOnce(
            $row,
            "                try {\n                    \$this->phaseHook->assertRow(\$this->connection, \$request);",
            "                try {\n                    \$this->rollBack();\n                    \$this->phaseHook->assertRow(\$this->connection, \$request);",
        );
        $admissionRollbackMoved = $this->replaceOnce(
            $admissionRollbackMoved,
            "                    \$this->rollBack();\n                    throw \$exception;",
            "                    throw \$exception;",
        );
        self::assertContains('row-admission', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php' => $admissionRollbackMoved,
        ]));
        $progressedSwallowed = $this->replaceOnce(
            $row,
            "                \$this->rollBack();\n                throw \$exception;",
            "                \$this->rollBack();\n                return \$result;",
        );
        self::assertContains('progressed-edge', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php' => $progressedSwallowed,
        ]));
        $narrowedProgressedCatch = $this->replaceOnce(
            $row,
            "            } catch (Throwable \$exception) {\n                \$this->rollBack();\n                throw \$exception;",
            "            } catch (\\InvalidArgumentException \$exception) {\n                \$this->rollBack();\n                throw \$exception;",
        );
        self::assertContains('progressed-edge', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php' =>
                $narrowedProgressedCatch,
        ]));

        $lateRowAdmission = $this->replaceOnce(
            $row,
            '                $this->phaseHook->assertRow($this->connection, $request);',
            '                // row admission text in a comment must not satisfy the root boundary',
        );
        $lateRowAdmission = $this->replaceOnce(
            $lateRowAdmission,
            '        $result = $this->columns($request);',
            "        \$result = \$this->columns(\$request);\n        \$this->phaseHook->assertRow(\$this->connection, \$request);",
        );
        self::assertContains('row-admission', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php' => $lateRowAdmission,
        ]));

        $lateRecovery = $this->replaceOnce(
            $audit,
            "            \$this->recoverInterrupted(\$audit, \$scope, \$now);\n            \$this->insertAudit(\$audit, \$auditId, \$scope, \$now);",
            "            \$this->insertAudit(\$audit, \$auditId, \$scope, \$now);\n            \$this->recoverInterrupted(\$audit, \$scope, \$now);",
        );
        self::assertContains('begin-phase-order', $this->nativePhaseViolations([
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php' => $lateRecovery,
        ]));
    }

    public function testWriterOnlyOwnsCatalogSelectorRequestAndCurrentTransactionDelivery(): void
    {
        $writer = $this->source(self::WRITER);
        $hook = $this->source(self::HOOK);
        $writerReferences = $this->resolvedReferences($writer);
        self::assertContains('BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator', $writerReferences);
        self::assertContains('BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector', $writerReferences);
        self::assertContains('BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest', $writerReferences);
        self::assertContains(
            'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog',
            $writerReferences,
        );

        foreach ([
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
            'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore',
            'BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxStore',
        ] as $forbidden) {
            self::assertNotContains($forbidden, $this->resolvedReferences($hook), $forbidden);
        }
        self::assertContains(
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
            $this->resolvedReferences($hook),
        );
        self::assertContains(
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
            $this->resolvedReferences($hook),
        );
        self::assertContains('deliverInCurrentTransaction', $this->calledMethods($this->parse($writer)));
        self::assertContains('forDraft', $this->calledMethods($this->parse($writer)));
        self::assertContains(
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
            $this->resolvedConstructors($this->parse($writer)),
        );
        self::assertNotContains('deliverInCurrentTransaction', $this->calledMethods($this->parse($hook)));
        self::assertNotContains('forDraft', $this->calledMethods($this->parse($hook)));

        $fixtures = [
            '<?php use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest as Alias; new Alias();' => true,
            '<?php new \\BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest();' => true,
            '<?php new \\blackops\\internal\\audit\\orchestration\\auditdeliveryrequest();' => true,
            '<?php $writer?->deliverInCurrentTransaction($plan, $request);' => true,
            '<?php /* new AuditDeliveryRequest(); */ $value = "AuditDeliveryRequest";' => false,
        ];
        foreach ($fixtures as $fixture => $expected) {
            self::assertSame(
                $expected,
                $this->hasResolvedReference($fixture, 'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest')
                || in_array('deliverInCurrentTransaction', $this->calledMethods($this->parse($fixture)), true),
            );
        }
        $dynamic = '<?php $name = "AuditDeliveryRequest"; new $name();';
        self::assertContains('dynamic', $this->resolvedConstructors($this->parse($dynamic)));
    }

    public function testHookAndWriterSourceGraphsRejectCapabilityDriftWithTheSameOracle(): void
    {
        $hook = $this->source(self::HOOK);
        $writer = $this->source(self::WRITER);
        self::assertSame([], $this->adapterSourceViolations(self::HOOK, $hook));
        self::assertSame([], $this->adapterSourceViolations(self::WRITER, $writer));
        self::assertContains('file-level-executable', $this->adapterSourceViolations(
            self::HOOK,
            $hook . "\ndeclare(ticks=1) { file_get_contents('canary'); }\n",
        ));

        $mutations = [
            'hook function I/O' => [
                self::HOOK,
                $this->replaceOnce(
                    $hook,
                    '        $this->writerForEntry($connection);',
                    "        file_get_contents('hook-canary');\n        \$this->writerForEntry(\$connection);",
                ),
                'function-call-graph',
            ],
            'hook native constructor' => [
                self::HOOK,
                $this->replaceOnce(
                    $hook,
                    "        \$this->snapshot = new \\SensitiveParameterValue(['writer' => \$writer]);",
                    "        \$this->snapshot = new \\SensitiveParameterValue(['writer' => \$writer]);\n        new \\DateTimeImmutable();",
                ),
                'constructor-work',
            ],
            'hook selector capability' => [
                self::HOOK,
                $this->replaceOnce(
                    $hook,
                    'use BlackOps\\Internal\\StorageProtection\\StorageProtectionRotationScope;',
                    "use BlackOps\\Internal\\StorageProtection\\StorageProtectionRotationScope;\nuse BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector;",
                ),
                'forbidden-reference:BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',
            ],
            'hook error catch drift' => [
                self::HOOK,
                $this->replaceOnce(
                    $hook,
                    "            \$this->scope(\$scope);\n        } catch (AuditDeliveryException \$failure)",
                    "            \$this->scope(\$scope);\n        } catch (AuditDeliveryFailure \$failure)",
                ),
                'catch-graph',
            ],
            'hook wrong writer receiver method' => [
                self::HOOK,
                $this->replaceOnce($hook, '$writer->started(', '$writer->deliverInCurrentTransaction('),
                'writer-edge',
            ],
            'hook wrong writer receiver' => [
                self::HOOK,
                $this->replaceOnce($hook, '$writer->started(', '$otherWriter->started('),
                'writer-edge',
            ],
            'hook nullsafe call' => [
                self::HOOK,
                $this->replaceOnce($hook, '$writer->started(', '$writer?->started('),
                'nullsafe-call',
            ],
            'hook dynamic method' => [
                self::HOOK,
                $this->replaceOnce($hook, '$writer->started(', '$writer->{$method}('),
                'dynamic-method',
            ],
            'hook dynamic constructor' => [
                self::HOOK,
                $this->replaceOnce(
                    $hook,
                    "        \$this->snapshot = new \\SensitiveParameterValue(['writer' => \$writer]);",
                    "        \$this->snapshot = new \\SensitiveParameterValue(['writer' => \$writer]);\n        new \$class();",
                ),
                'constructor-work',
            ],
            'writer function I/O' => [
                self::WRITER,
                $this->replaceOnce(
                    $writer,
                    "            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                    "            file_get_contents('writer-canary');\n            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                ),
                'function-call-graph',
            ],
            'writer clock ownership' => [
                self::WRITER,
                $this->replaceOnce(
                    $writer,
                    "            \$this->value('buildId');",
                    "            \$this->value('buildId');\n            \$this->value('clock');",
                ),
                'call-graph',
            ],
            'writer standalone delivery' => [
                self::WRITER,
                $this->replaceOnce($writer, 'deliverInCurrentTransaction(', 'deliverStandalone('),
                'delivery-edge',
            ],
            'writer nullsafe delivery' => [
                self::WRITER,
                $this->replaceOnce(
                    $writer,
                    '$delivery->deliverInCurrentTransaction(',
                    '$delivery?->deliverInCurrentTransaction(',
                ),
                'nullsafe-call',
            ],
            'writer static capability' => [
                self::WRITER,
                $this->replaceOnce(
                    $writer,
                    "            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                    "            AuditDeliverySelector::forDraft(\$draft);\n            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                ),
                'static-call-graph',
            ],
            'writer callback capability' => [
                self::WRITER,
                $this->replaceOnce(
                    $writer,
                    "            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                    "            call_user_func([AuditDeliverySelector::class, 'forDraft'], \$draft);\n            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
                ),
                'function-call-graph',
            ],
            'writer native construction' => [
                self::WRITER,
                $this->replaceOnce(
                    $writer,
                    "            \$this->value('buildId');",
                    "            \$this->value('buildId');\n            new \\DateTimeImmutable();",
                ),
                'helper-construction',
            ],
        ];
        foreach ($mutations as $label => [$path, $source, $violation]) {
            self::assertContains($violation, $this->adapterSourceViolations($path, $source), $label);
        }

        foreach ([
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryPlan',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
            'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore',
            'BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxStore',
        ] as $forbidden) {
            $forbiddenReference = $this->replaceOnce(
                $hook,
                'use BlackOps\\Internal\\StorageProtection\\StorageProtectionRotationScope;',
                "use BlackOps\\Internal\\StorageProtection\\StorageProtectionRotationScope;\nuse {$forbidden};",
            );
            self::assertContains(
                'forbidden-reference:' . $forbidden,
                $this->adapterSourceViolations(self::HOOK, $forbiddenReference),
                $forbidden,
            );
        }

        $writerHmac = $this->replaceOnce(
            $writer,
            "            \$value = (string) \$opaqueIds->derive(\$purpose, \$context);",
            "            \$value = (string) \$opaqueIds->derive(\$purpose, \$context);\n            \$opaqueIds->derive(\$purpose, 'extra');",
        );
        self::assertContains('call-graph', $this->adapterSourceViolations(self::WRITER, $writerHmac));
        $writerSql = $this->replaceOnce(
            $writer,
            "            \$delivery->deliverInCurrentTransaction(\$plan, \$request);",
            "            \$delivery->deliverInCurrentTransaction(\$plan, \$request);\n            \$delivery->connection()->executeStatement('SELECT 1');",
        );
        self::assertContains('call-graph', $this->adapterSourceViolations(self::WRITER, $writerSql));

        $aliases = str_replace('AuditDeliveryFailure', 'FailureAlias', $hook);
        $aliases = str_replace(
            'use BlackOps\\Internal\\Audit\\Orchestration\\FailureAlias;',
            'use BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure as FailureAlias;',
            $aliases,
        );
        self::assertSame([], $this->adapterSourceViolations(self::HOOK, $aliases));
        $qualified = str_replace(
            'FrameworkOperationalAuditEventCatalog::catalog()',
            '\\BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog::CATALOG()',
            $writer,
        );
        self::assertSame([], $this->adapterSourceViolations(self::WRITER, $qualified));
        self::assertSame(
            [],
            $this->adapterSourceViolations(
                self::WRITER,
                $writer . "\n// file_get_contents('inert'); new DateTimeImmutable();\n",
            ),
        );
        self::assertContains('file-level-executable', $this->adapterSourceViolations(
            self::WRITER,
            $writer . "\nfile_get_contents('canary');\n",
        ));
    }

    public function testSensitiveCallerFramesCoverNativeSubjectAndFailureInputs(): void
    {
        $expected = [
            'PostgreSqlStorageProtectionRotation' => ['__construct' => ['connection', 'codec', 'schema', 'phaseHook']],
            'PostgreSqlStorageProtectionRotationEngine' => [
                '__construct' => ['connection', 'codec', 'schema', 'phaseHook'],
                'plan' => ['scope'],
                'rotate' => ['scope'],
                'runInternal' => ['scope'],
            ],
            'PostgreSqlStorageProtectionRotationBatch' => [
                '__construct' => ['connection', 'codec', 'query', 'audit', 'schema', 'phaseHook'],
                'run' => ['scope'],
            ],
            'PostgreSqlStorageProtectionRotationRowExecutor' => [
                '__construct' => ['connection', 'codec', 'query', 'audit', 'phaseHook'],
                'execute' => ['request'],
            ],
            'PostgreSqlStorageProtectionRotationRowTransaction' => [
                '__construct' => ['connection', 'audit', 'columns', 'phaseHook'],
                'execute' => ['request'],
            ],
            'PostgreSqlStorageProtectionRotationAudit' => [
                '__construct' => ['connection', 'phaseHook'],
                'begin' => ['checkpoint', 'audit', 'scope'],
                'advance' => ['table', 'scope', 'cursor'],
                'progress' => ['audit', 'auditId', 'counts'],
                'finish' => ['checkpoint', 'audit', 'completion'],
            ],
        ];
        foreach ($expected as $shortClass => $methods) {
            $class = 'BlackOps\\Transport\\PostgreSql\\' . $shortClass;
            $reflection = new ReflectionClass($class);
            foreach ($methods as $methodName => $parameters) {
                $method = $reflection->getMethod($methodName);
                foreach ($parameters as $parameterName) {
                    $parameter = $method->getParameters();
                    $match = array_values(array_filter(
                        $parameter,
                        static fn(ReflectionParameter $candidate): bool => $candidate->getName() === $parameterName,
                    ));
                    self::assertCount(1, $match, $class . '::' . $methodName . ':' . $parameterName);
                    self::assertNotEmpty($match[0]->getAttributes(\SensitiveParameter::class));
                }
            }
        }
    }

    /** @param list<string> $paths @return array<string,string> */
    private function sources(array $paths): array
    {
        $sources = [];
        foreach ($paths as $path) {
            $sources[$path] = $this->source($path);
        }

        return $sources;
    }

    /** @return array<string,string> */
    private function sourceTree(): array
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertIsString($root);
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $sources['src/' . str_replace($root . '/', '', $path)] = file_get_contents($path);
        }

        return $sources;
    }

    private function source(string $path): string
    {
        $source = file_get_contents(__DIR__ . '/../../' . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    /** @return array<string,class-string> */
    private function phaseHookImplementations(): array
    {
        $implementations = [];
        foreach ($this->sourceTree() as $path => $source) {
            $nodes = $this->parse($source);
            foreach (new NodeFinder()->findInstanceOf($nodes, ClassLike::class) as $class) {
                if (!$class instanceof Node\Stmt\Class_ || $class->implements === []) {
                    continue;
                }
                foreach ($class->implements as $interface) {
                    if (
                        strcasecmp($this->resolvedName($interface), PostgreSqlStorageProtectionRotationPhaseHook::class)
                        === 0
                    ) {
                        $implementations[$path] = $class->namespacedName instanceof Name
                            ? $this->resolvedName($class->namespacedName)
                            : $class->name?->toString() ?? $path;
                    }
                }
            }
        }
        ksort($implementations);

        return $implementations;
    }

    /** @return list<string> */
    private function declaredPropertyNames(ReflectionClass $class): array
    {
        $properties = [];
        foreach ($class->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === $class->getName()) {
                $properties[] = $property->getName();
            }
        }
        sort($properties);

        return $properties;
    }

    private function reflectionTypeName(?ReflectionType $type): string
    {
        if (!$type instanceof ReflectionNamedType) {
            return $type === null ? '' : (string) $type;
        }
        $name = $type->getName();

        return $type->allowsNull() && $name !== 'mixed' && $name !== 'null' ? '?' . $name : $name;
    }

    private function replaceOnce(string $source, string $search, string $replacement): string
    {
        $count = 0;
        $result = str_replace($search, $replacement, $source, $count);
        self::assertSame(1, $count, 'Mutation anchor must be unique: ' . $search);

        return $result;
    }

    private function moveProgressedIntoAdvanceTry(string $source, bool $beforeAdvance): string
    {
        $progressed = <<<'PHP'
                    if ($this->phaseHook !== null) {
                        try {
                            $this->phaseHook->progressed($this->connection, $request, $cursor);
                        } catch (Throwable $exception) {
                            $this->rollBack();
                            throw $exception;
                        }
                    }
            PHP;
        $source = $this->replaceOnce($source, $progressed, '');
        $cursor = '            $cursor = $this->advance($request, $result);';
        if ($beforeAdvance) {
            return $this->replaceOnce($source, $cursor, $progressed . $cursor);
        }

        return $this->replaceOnce(
            $source,
            $cursor . "\n        } catch (Throwable) {",
            $cursor . "\n" . $progressed . '        } catch (Throwable) {',
        );
    }

    /** @param list<Node> $nodes @return list<string> */
    private function nativeConstructorViolations(array $nodes): array
    {
        $violations = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, ClassMethod::class) as $method) {
            if ($method->name->toString() !== '__construct') {
                continue;
            }
            $phaseHook = array_values(array_filter(
                $method->params,
                static fn(Node\Param $parameter): bool => (
                    $parameter->var instanceof Variable
                    && $parameter->var->name === 'phaseHook'
                ),
            ));
            if (count($phaseHook) !== 1) {
                $violations[] = 'phase-hook-parameter';
                continue;
            }
            $parameter = $phaseHook[0];
            if ($method->params[count($method->params) - 1] !== $parameter) {
                $violations[] = 'phase-hook-position';
            }
            if (
                !$parameter->type instanceof NullableType
                || !$parameter->type->type instanceof Name
                || strcasecmp(
                    $this->resolvedName($parameter->type->type),
                    'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationPhaseHook',
                ) !== 0
                || !$parameter->default instanceof ConstFetch
                || strcasecmp($parameter->default->name->toString(), 'null') !== 0
            ) {
                $violations[] = 'phase-hook-shape';
            }
        }
        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function resolvedReferences(string $source): array
    {
        $references = [];
        foreach (new NodeFinder()->findInstanceOf($this->parse($source), Name::class) as $name) {
            $references[] = $this->resolvedName($name);
        }
        $references = array_values(array_unique($references));
        sort($references);

        return $references;
    }

    private function hasResolvedReference(string $source, string $target): bool
    {
        foreach ($this->resolvedReferences($source) as $reference) {
            if (strcasecmp($reference, $target) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function resolvedConstructors(array $nodes): array
    {
        $constructors = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Node\Expr\New_::class) as $new) {
            if (!$new->class instanceof Name) {
                $constructors[] = 'dynamic';
                continue;
            }
            $constructors[] = $this->resolvedName($new->class);
        }
        $constructors = array_values(array_unique($constructors));
        sort($constructors);

        return $constructors;
    }

    /** @param array<string,string> $sources @return array<string,list<string>> */
    private function concreteReferenceInventory(array $sources): array
    {
        $targets = [StorageRotationAuditHook::class, StorageRotationAuditWriter::class];
        $inventory = [self::HOOK => []];
        foreach ($sources as $path => $source) {
            foreach (new NodeFinder()->findInstanceOf($this->parse($source), Name::class) as $name) {
                $resolved = $this->resolvedName($name);
                foreach ($targets as $target) {
                    if (strcasecmp($resolved, $target) !== 0) {
                        continue;
                    }
                    if (
                        $path === self::HOOK && strcasecmp($target, StorageRotationAuditHook::class) === 0
                        || $path === self::WRITER && strcasecmp($target, StorageRotationAuditWriter::class) === 0
                    ) {
                        continue;
                    }
                    $inventory[$path][] = $target;
                }
            }
        }
        foreach ($inventory as &$targetsForPath) {
            $targetsForPath = array_values(array_unique($targetsForPath));
            sort($targetsForPath);
        }
        unset($targetsForPath);
        ksort($inventory);

        return $inventory;
    }

    /** @param array<string,string> $sources @return array<string,list<string>> */
    private function concreteConstructorInventory(array $sources): array
    {
        $inventory = [StorageRotationAuditHook::class => [], StorageRotationAuditWriter::class => []];
        foreach ($sources as $path => $source) {
            foreach (new NodeFinder()->findInstanceOf($this->parse($source), New_::class) as $new) {
                if (!$new->class instanceof Name) {
                    continue;
                }
                $class = $this->resolvedName($new->class);
                foreach (array_keys($inventory) as $target) {
                    if (strcasecmp($class, $target) === 0) {
                        $inventory[$target][] = $path;
                    }
                }
            }
        }
        foreach ($inventory as &$paths) {
            $paths = array_values(array_unique($paths));
            sort($paths);
        }
        unset($paths);

        return $inventory;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function concreteActivationViolations(array $sources): array
    {
        $targets = [StorageRotationAuditHook::class, StorageRotationAuditWriter::class];
        $violations = [];
        foreach ($sources as $path => $source) {
            $nodes = $this->parse($source);
            foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
                $class = $new->class instanceof Name
                    ? $this->resolvedName($new->class)
                    : $this->staticStringValue(
                        $new->class,
                        $this->staticStringAssignmentsBefore($nodes, $new->getStartFilePos()),
                    );
                $hookIndex = $class === null ? null : $this->nativeHookArgumentIndex($class);
                if ($hookIndex !== null) {
                    $hook = $this->nativeHookArgument($new, $hookIndex);
                    if (
                        $hook !== null
                        && !$this->isNullExpression($hook)
                        && !$this->nativeForwardingEdgeAllowed($path, $new, $nodes)
                    ) {
                        $violations[] = 'native-hook-activation:' . $path;
                    }
                }
                if ($class !== null && $this->isTargetClass($class, $targets)) {
                    $violations[] = 'constructor:' . $path;
                }
                if (
                    $class !== null
                    && $this->nativeForwardingTargetExpected($path, $class)
                    && !$this->nativeForwardingEdgeAllowed($path, $new, $nodes)
                ) {
                    $violations[] = 'native-forwarding-edge:' . $path;
                }
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
                $class = $call->class instanceof Name
                    ? $this->resolvedName($call->class)
                    : $this->staticStringValue(
                        $call->class,
                        $this->staticStringAssignmentsBefore($nodes, $call->getStartFilePos()),
                    );
                if ($class !== null && $this->isTargetClass($class, $targets)) {
                    $violations[] = 'static-activation:' . $path;
                }
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, FuncCall::class) as $call) {
                $name = $call->name instanceof Name ? strtolower($call->name->toString()) : 'dynamic';
                if (!in_array(
                    $name,
                    [
                        'class_exists',
                        'interface_exists',
                        'enum_exists',
                        'is_a',
                        'is_subclass_of',
                        'call_user_func',
                        'call_user_func_array',
                    ],
                    true,
                )) {
                    continue;
                }
                $assignments = $this->staticStringAssignmentsBefore($nodes, $call->getStartFilePos());
                foreach ($call->args as $argument) {
                    foreach ($this->staticStringValues($argument->value, $assignments) as $value) {
                        if ($value !== null && $this->isTargetClass($value, $targets)) {
                            $violations[] = 'consumed-class-string:' . $path;
                        }
                    }
                }
            }
            foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
                $dynamicClass = !$new->class instanceof Name;
                $reflectionClass =
                    $new->class instanceof Name
                    && in_array(
                        strtolower($this->resolvedName($new->class)),
                        ['reflectionclass', 'reflectionmethod'],
                        true,
                    );
                $assignments = $dynamicClass || $reflectionClass
                    ? $this->staticStringAssignmentsBefore($nodes, $new->getStartFilePos())
                    : [];
                if (
                    $dynamicClass
                    && ($value = $this->staticStringValue($new->class, $assignments)) !== null
                    && $this->isTargetClass($value, $targets)
                ) {
                    $violations[] = 'dynamic-constructor:' . $path;
                }
                if ($reflectionClass && isset($new->args[0])) {
                    $value = $this->staticStringValue($new->args[0]->value, $assignments);
                    if ($value !== null && $this->isTargetClass($value, $targets)) {
                        $violations[] = 'reflection-activation:' . $path;
                    }
                }
            }
        }

        return array_values(array_unique($violations));
    }

    private function nativeHookArgumentIndex(string $class): ?int
    {
        foreach ([
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotation' => 3,
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine' => 3,
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationBatch' => 5,
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowExecutor' => 4,
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowTransaction' => 3,
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationAudit' => 1,
        ] as $target => $index) {
            if (strcasecmp($class, $target) === 0) {
                return $index;
            }
        }

        return null;
    }

    private function nativeHookArgument(New_ $new, int $index): ?Node
    {
        foreach ($new->args as $argument) {
            if ($argument->name instanceof Identifier && strtolower($argument->name->toString()) === 'phasehook') {
                return $argument->value;
            }
        }

        return $new->args[$index]->value ?? null;
    }

    private function isNullExpression(Node $expression): bool
    {
        return (
            $expression instanceof ConstFetch
            && $expression->name instanceof Name
            && strtolower($expression->name->toString()) === 'null'
        );
    }

    /** @return array<string,array{owner:string,targets:array<string,list<string>>}> */
    private function nativeForwardingContracts(): array
    {
        return [
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php' => [
                'owner' => 'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotation',
                'targets' => [
                    'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine' => [
                        '$connection',
                        '$codec',
                        '$schema',
                        '$phaseHook',
                    ],
                ],
            ],
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php' => [
                'owner' => 'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
                'targets' => [
                    'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationBatch' => [
                        '$connection',
                        '$codec',
                        'new:BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationQuery($connection,$codec,$schema)',
                        'new:BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationAudit($connection,$phaseHook)',
                        '$schema',
                        '$phaseHook',
                    ],
                    'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationQuery' => [
                        '$connection',
                        '$codec',
                        '$schema',
                    ],
                    'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationAudit' => [
                        '$connection',
                        '$phaseHook',
                    ],
                ],
            ],
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationBatch.php' => [
                'owner' => 'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationBatch',
                'targets' => [
                    'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowExecutor' => [
                        '$connection',
                        '$codec',
                        '$query',
                        '$audit',
                        '$phaseHook',
                    ],
                ],
            ],
            'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowExecutor.php' => [
                'owner' => 'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowExecutor',
                'targets' => [
                    'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowTransaction' => [
                        '$connection',
                        '$audit',
                        'new:BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationColumnExecutor($connection,$codec,$query)',
                        '$phaseHook',
                    ],
                    'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationColumnExecutor' => [
                        '$connection',
                        '$codec',
                        '$query',
                    ],
                ],
            ],
        ];
    }

    private function nativeForwardingTargetExpected(string $path, string $target): bool
    {
        foreach ($this->nativeForwardingContracts()[$path]['targets'] ?? [] as $candidate => $_arguments) {
            if (strcasecmp($candidate, $target) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Node> $nodes */
    private function nativeForwardingEdgeAllowed(string $path, New_ $new, array $nodes): bool
    {
        if (!$new->class instanceof Name) {
            return false;
        }
        $contract = $this->nativeForwardingContracts()[$path] ?? null;
        if ($contract === null) {
            return false;
        }
        $target = $this->resolvedName($new->class);
        $arguments = null;
        foreach ($contract['targets'] as $candidate => $expectedArguments) {
            if (strcasecmp($candidate, $target) === 0) {
                $arguments = $expectedArguments;
                break;
            }
        }
        if ($arguments === null) {
            return false;
        }
        $owner = $this->nativeConstructorOwner($nodes, $new);
        if ($owner === null || $owner[1]->name->toString() !== '__construct') {
            return false;
        }
        if (
            !$owner[0]->namespacedName instanceof Name
            || strcasecmp($this->resolvedName($owner[0]->namespacedName), $contract['owner']) !== 0
        ) {
            return false;
        }
        $matches = array_values(array_filter(
            new NodeFinder()->findInstanceOf($owner[1], New_::class),
            fn(New_ $candidate): bool => (
                $candidate->class instanceof Name
                && strcasecmp($this->resolvedName($candidate->class), $target) === 0
            ),
        ));
        if (count($matches) !== 1 || $matches[0] !== $new) {
            return false;
        }
        $actual = array_map(
            fn(Node\Arg $argument): string => (
                ($argument->name instanceof Identifier ? $argument->name->toString() . ':' : '')
                . $this->argumentDescriptor($argument->value)
            ),
            $new->args,
        );

        return $actual === $arguments;
    }

    /** @param list<Node> $nodes @return array{Class_,ClassMethod}|null */
    private function nativeConstructorOwner(array $nodes, New_ $new): ?array
    {
        $owners = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
            foreach ($class->stmts as $statement) {
                if ($statement instanceof ClassMethod && $this->nodeEncloses($statement, $new)) {
                    $owners[] = [$class, $statement];
                }
            }
        }
        usort(
            $owners,
            static fn(array $left, array $right): int => (
                ($left[1]->getEndFilePos() - $left[1]->getStartFilePos())
                <=> ($right[1]->getEndFilePos() - $right[1]->getStartFilePos())
            ),
        );

        return $owners[0] ?? null;
    }

    private function nodeEncloses(Node $outer, Node $inner): bool
    {
        return (
            $outer->getStartFilePos() >= 0
            && $inner->getStartFilePos() >= $outer->getStartFilePos()
            && $inner->getEndFilePos() <= $outer->getEndFilePos()
        );
    }

    /** @param list<Node> $nodes @return array<string,string> */
    private function staticStringAssignmentsBefore(array $nodes, int $position): array
    {
        $assignments = [];
        $scope = $this->lexicalScopeAt($nodes, $position);
        $candidates = new NodeFinder()->findInstanceOf($nodes, Assign::class);
        usort(
            $candidates,
            static fn(Assign $left, Assign $right): int => $left->getStartFilePos() <=> $right->getStartFilePos(),
        );
        foreach ($candidates as $assignment) {
            if ($assignment->getStartFilePos() >= $position) {
                break;
            }
            if ($this->lexicalScopeAt($nodes, $assignment->getStartFilePos()) !== $scope) {
                continue;
            }
            if (!$assignment->var instanceof Variable || !is_string($assignment->var->name)) {
                continue;
            }
            $value = $this->staticStringValue($assignment->expr, $assignments);
            if ($value !== null) {
                $assignments[$assignment->var->name] = $value;
            } else {
                unset($assignments[$assignment->var->name]);
            }
        }

        return $assignments;
    }

    /** @param list<Node> $nodes */
    private function lexicalScopeAt(array $nodes, int $position): ?Node
    {
        $scopes = [
            ...new NodeFinder()->findInstanceOf($nodes, Node\Stmt\Namespace_::class),
            ...new NodeFinder()->findInstanceOf($nodes, Class_::class),
            ...new NodeFinder()->findInstanceOf($nodes, ClassMethod::class),
            ...new NodeFinder()->findInstanceOf($nodes, Node\Stmt\Function_::class),
            ...new NodeFinder()->findInstanceOf($nodes, Node\Expr\Closure::class),
            ...new NodeFinder()->findInstanceOf($nodes, Node\Expr\ArrowFunction::class),
        ];
        $scopes = array_values(array_filter($scopes, fn(Node $scope): bool => $this->positionWithin(
            $scope,
            $position,
        )));
        usort(
            $scopes,
            static fn(Node $left, Node $right): int => (
                ($left->getEndFilePos() - $left->getStartFilePos())
                <=> ($right->getEndFilePos() - $right->getStartFilePos())
            ),
        );

        return $scopes[0] ?? null;
    }

    private function positionWithin(Node $node, int $position): bool
    {
        return (
            $node->getStartFilePos() >= 0
            && $position >= $node->getStartFilePos()
            && $position <= $node->getEndFilePos()
        );
    }

    /** @param array<string,string> $assignments */
    private function staticStringValue(Node $node, array $assignments): ?string
    {
        if ($node instanceof String_) {
            return $node->value;
        }
        if ($node instanceof Variable && is_string($node->name)) {
            return $assignments[$node->name] ?? null;
        }
        if (
            $node instanceof ClassConstFetch
            && $node->name instanceof Identifier
            && strtolower($node->name->toString()) === 'class'
        ) {
            return $node->class instanceof Name ? $this->resolvedName($node->class) : null;
        }
        if ($node instanceof Node\Expr\BinaryOp\Concat) {
            $left = $this->staticStringValue($node->left, $assignments);
            $right = $this->staticStringValue($node->right, $assignments);
            return $left !== null && $right !== null ? $left . $right : null;
        }

        return null;
    }

    /** @param array<string,string> $assignments @return list<string> */
    private function staticStringValues(Node $node, array $assignments): array
    {
        $value = $this->staticStringValue($node, $assignments);
        if ($value !== null) {
            return [$value];
        }
        if ($node instanceof Node\Expr\Array_) {
            $values = [];
            foreach ($node->items as $item) {
                if ($item !== null) {
                    $values = [...$values, ...$this->staticStringValues($item->value, $assignments)];
                }
            }
            return $values;
        }

        return [];
    }

    /** @param list<string> $targets */
    private function isTargetClass(string $value, array $targets): bool
    {
        foreach ($targets as $target) {
            if (strcasecmp(ltrim($value, '\\'), ltrim($target, '\\')) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function adapterSourceViolations(string $path, string $source): array
    {
        $violations = [];
        try {
            $nodes = $this->parse($source);
        } catch (\Throwable) {
            return ['syntax'];
        }
        if ($this->hasExecutableFileLevelStatement($nodes)) {
            return ['file-level-executable'];
        }
        $classes = new NodeFinder()->findInstanceOf($nodes, ClassLike::class);
        if (count($classes) !== 1) {
            return ['declaration-inventory'];
        }
        $class = $classes[0] instanceof Class_ ? $classes[0] : null;
        $expectedClass = $path === self::HOOK ? StorageRotationAuditHook::class : StorageRotationAuditWriter::class;
        if (
            $class === null
            || !$class->isFinal()
            || $class->extends !== null
            || !$class->namespacedName instanceof Name
            || strcasecmp($this->resolvedName($class->namespacedName), $expectedClass) !== 0
            || $path !== self::HOOK && $path !== self::WRITER
        ) {
            $violations[] = 'declaration';
            return $violations;
        }
        if ($path === self::HOOK) {
            if (
                count($class->implements) !== 1
                || !$class->implements[0] instanceof Name
                || strcasecmp(
                    $this->resolvedName($class->implements[0]),
                    PostgreSqlStorageProtectionRotationPhaseHook::class,
                ) !== 0
            ) {
                $violations[] = 'interface';
            }
        } elseif ($class->implements !== []) {
            $violations[] = 'interface';
        }
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (strcasecmp($this->resolvedName($attribute->name), PublicApi::class) === 0) {
                    $violations[] = 'public-api';
                }
            }
        }
        $properties = [];
        foreach ($class->stmts as $statement) {
            if (!$statement instanceof Node\Stmt\Property) {
                continue;
            }
            foreach ($statement->props as $property) {
                $properties[] = '$' . $property->name->toString();
            }
        }
        sort($properties);
        if ($properties !== ['$snapshot']) {
            $violations[] = 'property-inventory';
        }
        $expectedMethods = $path === self::HOOK
            ? [
                '__clone',
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'assertEntry',
                'assertRow',
                'started',
                'interrupted',
                'progressed',
                'finished',
                'writerForEntry',
                'writerForRoot',
                'writerForEntryConnection',
                'row',
                'target',
                'rowTenant',
                'rowCursor',
                'scope',
                'scopeHash',
                'scopeHashValue',
                'tenant',
                'tenantPair',
                'auditId',
                'cursor',
                'uuidCursor',
                'compositeCursor',
                'writer',
                'failure',
                'remap',
            ]
            : [
                '__clone',
                '__construct',
                '__debugInfo',
                '__serialize',
                '__unserialize',
                'assertConnection',
                'started',
                'failed',
                'succeeded',
                'progressed',
                'write',
                'catalog',
                'definition',
                'expected',
                'assertEventCursor',
                'occurredAt',
                'opaqueId',
                'draft',
                'selector',
                'tenantReference',
                'reference',
                'value',
                'assertAuditId',
                'assertScopeHash',
                'assertCursor',
                'failure',
                'remap',
            ];
        $actualMethods = array_map(
            static fn(ClassMethod $method): string => $method->name->toString(),
            array_values(array_filter($class->stmts, static fn(Node $node): bool => $node instanceof ClassMethod)),
        );
        sort($expectedMethods);
        sort($actualMethods);
        if ($actualMethods !== $expectedMethods) {
            $violations[] = 'method-inventory';
        }
        $violations = [...$violations, ...$this->adapterCallGraphViolations($class, $path === self::HOOK)];
        $violations = [...$violations, ...$this->adapterC3Violations($class, $path === self::HOOK, $nodes)];
        $violations = [...$violations, ...$this->adapterCatchGraphViolations($class, $path === self::HOOK)];
        return array_values(array_unique($violations));
    }

    /** @param list<Node> $nodes */
    private function hasExecutableFileLevelStatement(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $statement) {
                    if (!$this->isFileLevelDeclaration($statement)) {
                        return true;
                    }
                }
                continue;
            }
            if (!$this->isFileLevelDeclaration($node)) {
                return true;
            }
        }

        return false;
    }

    private function isFileLevelDeclaration(Node $node): bool
    {
        if ($node instanceof Node\Stmt\Declare_) {
            return (
                $node->stmts === null
                && count($node->declares) === 1
                && $node->declares[0]->key->toString() === 'strict_types'
                && $node->declares[0]->value instanceof Node\Scalar\LNumber
                && $node->declares[0]->value->value === 1
            );
        }

        return (
            $node instanceof Node\Stmt\Use_
            || $node instanceof Node\Stmt\GroupUse
            || $node instanceof Node\Stmt\ClassLike
            || $node instanceof Node\Stmt\Nop
        );
    }

    /** @return list<string> */
    private function adapterCatchGraphViolations(Class_ $class, bool $hook): array
    {
        $expected = $hook
            ? [
                '__construct' => [],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => [],
                '__clone' => [],
                'assertEntry' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException', 'Throwable'],
                'assertRow' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException', 'Throwable'],
                'started' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException', 'Throwable'],
                'interrupted' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException', 'Throwable'],
                'progressed' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException', 'Throwable'],
                'finished' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException', 'Throwable'],
                'writerForEntry' => [
                    'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                    'Throwable',
                    'Throwable',
                ],
                'writerForRoot' => ['Throwable'],
                'writerForEntryConnection' => [
                    'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                    'Throwable',
                ],
                'row' => [],
                'target' => [],
                'rowTenant' => [],
                'rowCursor' => [],
                'scope' => [],
                'scopeHash' => [],
                'scopeHashValue' => [],
                'tenant' => [],
                'tenantPair' => [],
                'auditId' => [],
                'cursor' => [],
                'uuidCursor' => ['Throwable'],
                'compositeCursor' => [],
                'writer' => ['Throwable'],
                'failure' => [],
                'remap' => ['Throwable'],
            ]
            : [
                '__construct' => ['Throwable'],
                'assertConnection' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException', 'Throwable'],
                'started' => [],
                'failed' => [],
                'succeeded' => [],
                'progressed' => [],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => [],
                '__clone' => [],
                'write' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException', 'Throwable'],
                'catalog' => ['Throwable'],
                'definition' => ['Throwable'],
                'expected' => [],
                'assertEventCursor' => [],
                'occurredAt' => ['Throwable'],
                'opaqueId' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException', 'Throwable'],
                'draft' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException', 'Throwable'],
                'selector' => [],
                'tenantReference' => ['Throwable'],
                'reference' => [],
                'value' => ['Throwable', 'Throwable'],
                'assertAuditId' => [],
                'assertScopeHash' => [],
                'assertCursor' => ['Throwable'],
                'failure' => [],
                'remap' => ['Throwable'],
            ];
        $violations = [];
        foreach ($expected as $methodName => $types) {
            $method = $this->classMethodNode($class, $methodName);
            if ($method === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = [];
            foreach (new NodeFinder()->findInstanceOf($method, Catch_::class) as $catch) {
                foreach ($catch->types as $type) {
                    $actual[] = $this->resolvedName($type);
                }
            }
            sort($actual);
            sort($types);
            if ($actual !== $types) {
                $violations[] = 'catch-graph';
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function adapterCallGraphViolations(Class_ $class, bool $hook): array
    {
        $violations = [];
        $expectedCalls = $hook ? $this->hookCallGraph() : $this->writerCallGraph();
        $expectedFunctions = $hook ? $this->hookFunctionGraph() : $this->writerFunctionGraph();
        $expectedStatic = $hook ? $this->hookStaticGraph() : $this->writerStaticGraph();
        $expectedNew = $hook ? $this->hookNewGraph() : $this->writerNewGraph();
        $methodNames = array_keys($expectedCalls);
        sort($methodNames);
        foreach ($expectedCalls as $methodName => $expected) {
            $method = $this->classMethodNode($class, $methodName);
            if ($method === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = $this->methodCallShapes($method);
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'call-graph';
            }
        }
        foreach ($expectedFunctions as $methodName => $expected) {
            $method = $this->classMethodNode($class, $methodName);
            if ($method === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = $this->functionCallShapes($method);
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'function-call-graph';
            }
        }
        foreach ($expectedStatic as $methodName => $expected) {
            $method = $this->classMethodNode($class, $methodName);
            if ($method === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = $this->staticCallShapes($method);
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = 'static-call-graph';
            }
        }
        foreach ($expectedNew as $methodName => $expected) {
            $method = $this->classMethodNode($class, $methodName);
            if ($method === null) {
                $violations[] = 'method-inventory';
                continue;
            }
            $actual = [];
            foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], New_::class) as $new) {
                $actual[] = $new->class instanceof Name ? $this->resolvedName($new->class) : 'dynamic';
            }
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                $violations[] = $methodName === '__construct' ? 'constructor-work' : 'helper-construction';
            }
        }
        if ($hook) {
            $writerCalls = [];
            foreach ($this->allMethodCalls($class) as $call) {
                if ($this->expressionSignature($call->var) === '$writer') {
                    $writerCalls[] = $this->methodCallShape($call);
                }
            }
            $expectedWriterCalls = [
                '$writer->assertconnection#1',
                '$writer->assertconnection#1',
                '$writer->failed#3',
                '$writer->failed#3',
                '$writer->progressed#4',
                '$writer->started#3',
                '$writer->succeeded#3',
            ];
            sort($writerCalls);
            sort($expectedWriterCalls);
            if ($writerCalls !== $expectedWriterCalls) {
                $violations[] = 'writer-edge';
            }
        } else {
            $write = $this->classMethodNode($class, 'write');
            if ($write === null) {
                $violations[] = 'delivery-edge';
            } else {
                $deliveryCalls = array_values(array_filter(
                    $this->allMethodCalls($write),
                    fn(MethodCall|NullsafeMethodCall $call): bool => (
                        $this->expressionSignature($call->var) === '$delivery'
                        && $this->methodNameOf($call) === 'deliverincurrenttransaction'
                    ),
                ));
                $standaloneCalls = array_values(array_filter(
                    $this->allMethodCalls($class),
                    fn(MethodCall|NullsafeMethodCall $call): bool => $this->methodNameOf($call) === 'deliverstandalone',
                ));
                if (count($deliveryCalls) !== 1 || count($standaloneCalls) !== 0) {
                    $violations[] = 'delivery-edge';
                }
            }
        }

        foreach ($this->allMethodCalls($class) as $call) {
            if ($call instanceof NullsafeMethodCall) {
                $violations[] = 'nullsafe-call';
            }
            if (!$call->name instanceof Identifier) {
                $violations[] = 'dynamic-method';
            }
        }
        foreach (new NodeFinder()->findInstanceOf($class, FuncCall::class) as $call) {
            if (!$call->name instanceof Name) {
                $violations[] = 'function-call-graph';
            }
        }
        foreach (new NodeFinder()->findInstanceOf($class, StaticCall::class) as $call) {
            if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
                $violations[] = 'static-call-graph';
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return array<string,list<string>> */
    private function hookCallGraph(): array
    {
        return [
            '__construct' => [],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            '__clone' => [],
            'assertEntry' => ['$this->scope#1', '$this->writerforentry#1'],
            'assertRow' => ['$this->row#1', '$this->writerforroot#1'],
            'started' => [
                '$this->auditid#1',
                '$this->scope#1',
                '$this->scopehash#1',
                '$this->tenant#1',
                '$this->writerforroot#1',
                '$writer->started#3',
            ],
            'interrupted' => [
                '$this->auditid#1',
                '$this->scope#1',
                '$this->scopehash#1',
                '$this->tenant#1',
                '$this->writerforroot#1',
                '$writer->failed#3',
            ],
            'progressed' => ['$this->cursor#1', '$this->row#1', '$this->writerforroot#1', '$writer->progressed#4'],
            'finished' => [
                '$this->auditid#1',
                '$this->scope#1',
                '$this->scopehash#1',
                '$this->scopehash#1',
                '$this->tenant#1',
                '$this->tenant#1',
                '$this->writerforroot#1',
                '$writer->failed#3',
                '$writer->succeeded#3',
            ],
            'writerForEntry' => [
                '$this->writer#0',
                '$writer->assertconnection#1',
                '$connection->isautocommit#0',
                '$connection->istransactionactive#0',
                '$connection->gettransactionnestinglevel#0',
            ],
            'writerForRoot' => [
                '$this->writerforentryconnection#1',
                '$connection->isautocommit#0',
                '$connection->istransactionactive#0',
                '$connection->gettransactionnestinglevel#0',
            ],
            'writerForEntryConnection' => ['$this->writer#0', '$writer->assertconnection#1'],
            'row' => [
                '$request->mode->writes#0',
                '$this->auditid#1',
                '$this->rowcursor#2',
                '$this->rowtenant#2',
                '$this->scope#1',
                '$this->scopehash#1',
                '$this->target#2',
                '$target->hascompositeidentity#0',
            ],
            'target' => [],
            'rowTenant' => [
                '$this->tenant#1',
                '$this->tenantpair#2',
                '$scopeTenant->id#0',
                '$scopeTenant->type#0',
                '$tenant->id#0',
                '$tenant->type#0',
            ],
            'rowCursor' => ['$target->hascompositeidentity#0', '$this->cursor#1', '$this->uuidcursor#1'],
            'scope' => ['$rebuilt->scopehash#0', '$scope->scopehash#0', '$this->scopehashvalue#1', '$this->tenant#1'],
            'scopeHash' => ['$scope->scopehash#0', '$this->scopehashvalue#1'],
            'scopeHashValue' => [],
            'tenant' => ['$tenant->id#0', '$tenant->type#0', '$this->tenantpair#2'],
            'tenantPair' => [],
            'auditId' => [],
            'cursor' => ['$this->compositecursor#1', '$this->uuidcursor#1'],
            'uuidCursor' => ['symfony\\component\\uid\\uuid::fromstring#1->torfc4122#0'],
            'compositeCursor' => [],
            'writer' => ['$this->snapshot->getvalue#0'],
            'failure' => [],
            'remap' => ['$failure->failure#0'],
        ];
    }

    /** @return array<string,list<string>> */
    private function writerCallGraph(): array
    {
        return [
            '__construct' => [
                '$actor->authorization#0',
                '$actor->execution#0',
                '$correlation->toarray#0',
                '$this->reference#1',
                '$this->reference#1',
            ],
            'assertConnection' => ['$this->value#1', '$delivery->usesconnection#1'],
            'started' => ['$this->write#5'],
            'failed' => ['$this->write#5'],
            'succeeded' => ['$this->write#5'],
            'progressed' => ['$this->write#5'],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            '__clone' => [],
            'write' => [
                '$this->assertauditid#1',
                '$this->asserteventcursor#2',
                '$this->assertscopehash#1',
                '$this->catalog#0',
                '$this->definition#2',
                '$this->draft#5',
                '$this->occurredat#0',
                '$this->opaqueid#2',
                '$this->opaqueid#2',
                '$this->selector#0',
                '$this->selector#0->fordraft#1',
                '$this->tenantreference#1',
                '$this->value#1',
                '$this->value#1',
                '$delivery->deliverincurrenttransaction#2',
            ],
            'catalog' => [],
            'definition' => ['$this->expected#1'],
            'expected' => [],
            'assertEventCursor' => ['$this->assertcursor#1'],
            'occurredAt' => [
                '$this->value#1',
                '$clock->now#0',
                '$clock->now#0->settimezone#1',
                '$clock->now#0->settimezone#1->format#1',
            ],
            'opaqueId' => ['$this->value#1', '$opaqueIds->derive#2'],
            'draft' => ['$this->value#1', '$this->value#1', '$this->value#1', '$this->value#1'],
            'selector' => ['$this->value#1'],
            'tenantReference' => ['$tenant->id#0', '$tenant->type#0'],
            'reference' => ['$reference->id#0', '$reference->type#0'],
            'value' => ['$this->snapshot->getvalue#0'],
            'assertAuditId' => [],
            'assertScopeHash' => [],
            'assertCursor' => ['symfony\\component\\uid\\uuid::fromstring#1->torfc4122#0'],
            'failure' => [],
            'remap' => ['$failure->failure#0'],
        ];
    }

    /** @return array<string,list<string>> */
    private function hookFunctionGraph(): array
    {
        return [
            '__construct' => [],
            'assertEntry' => [],
            'assertRow' => [],
            'started' => [],
            'interrupted' => [],
            'progressed' => [],
            'finished' => [
                'in_array()',
                'is_int()',
                'is_int()',
                'is_int()',
                'is_int()',
                'is_string()',
                'is_string()',
                'is_string()',
                'preg_match()',
            ],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            '__clone' => [],
            'writerForEntry' => [],
            'writerForRoot' => [],
            'writerForEntryConnection' => [],
            'row' => ['array_key_exists()', 'array_unique()', 'is_array()', 'is_string()'],
            'target' => [],
            'rowTenant' => ['array_key_exists()', 'array_key_exists()', 'is_string()', 'is_string()'],
            'rowCursor' => ['is_int()', 'is_string()', 'is_string()', 'is_string()', 'preg_match()', 'preg_match()'],
            'scope' => [],
            'scopeHash' => [],
            'scopeHashValue' => ['preg_match()'],
            'tenant' => [],
            'tenantPair' => ['trim()', 'trim()'],
            'auditId' => ['preg_match()'],
            'cursor' => ['preg_match()'],
            'uuidCursor' => ['preg_match()'],
            'compositeCursor' => ['preg_match()', 'preg_match()'],
            'writer' => ['array_keys()', 'is_array()'],
            'failure' => [],
            'remap' => [],
        ];
    }

    /** @return array<string,list<string>> */
    private function writerFunctionGraph(): array
    {
        return [
            '__construct' => [],
            'assertConnection' => [],
            'started' => [],
            'failed' => [],
            'succeeded' => [],
            'progressed' => [],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            '__clone' => [],
            'write' => [],
            'catalog' => [],
            'definition' => ['count()'],
            'expected' => [],
            'assertEventCursor' => [],
            'occurredAt' => ['preg_match()'],
            'opaqueId' => [],
            'draft' => ['is_string()'],
            'selector' => [],
            'tenantReference' => ['trim()', 'trim()'],
            'reference' => [],
            'value' => ['array_key_exists()', 'array_keys()', 'is_array()', 'is_string()'],
            'assertAuditId' => ['preg_match()'],
            'assertScopeHash' => ['preg_match()'],
            'assertCursor' => ['preg_match()', 'preg_match()', 'preg_match()'],
            'failure' => [],
            'remap' => [],
        ];
    }

    /** @return array<string,list<string>> */
    private function hookStaticGraph(): array
    {
        $failure = ['self::failure()'];
        $boundary = ['self::failure()', 'self::remap()'];

        return [
            '__construct' => [],
            'assertEntry' => $boundary,
            'assertRow' => $boundary,
            'started' => $boundary,
            'interrupted' => $boundary,
            'progressed' => $boundary,
            'finished' => $boundary,
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => $failure,
            '__clone' => $failure,
            'writerForEntry' => ['self::failure()', 'self::failure()', 'self::remap()'],
            'writerForRoot' => ['self::failure()'],
            'writerForEntryConnection' => ['self::failure()', 'self::remap()'],
            'row' => [],
            'target' => ['blackops\\transport\\postgresql\\postgresqlstorageprotectionrotationtarget::forpurpose()'],
            'rowTenant' => [],
            'rowCursor' => [],
            'scope' => [],
            'scopeHash' => [],
            'scopeHashValue' => ['blackops\\core\\audit\\auditvalidationexception::assertsafestring()'],
            'tenant' => [],
            'tenantPair' => [
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
            ],
            'auditId' => ['blackops\\core\\audit\\auditvalidationexception::assertsafestring()'],
            'cursor' => ['blackops\\core\\audit\\auditvalidationexception::assertsafestring()'],
            'uuidCursor' => [
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'symfony\\component\\uid\\uuid::fromstring()',
            ],
            'compositeCursor' => [],
            'writer' => ['self::failure()', 'self::failure()'],
            'failure' => [],
            'remap' => ['self::failure()', 'self::failure()'],
        ];
    }

    /** @return array<string,list<string>> */
    private function writerStaticGraph(): array
    {
        return [
            '__construct' => [
                'blackops\\core\\audit\\auditrecordcorrelation::fromarray()',
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'self::failure()',
            ],
            'assertConnection' => ['self::failure()', 'self::failure()', 'self::remap()'],
            'started' => [],
            'failed' => [],
            'succeeded' => [],
            'progressed' => [],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => ['self::failure()'],
            '__clone' => ['self::failure()'],
            'write' => ['self::failure()', 'self::failure()', 'self::remap()'],
            'catalog' => [
                'blackops\\internal\\audit\\catalog\\frameworkoperationalauditeventcatalog::catalog()',
                'self::failure()',
            ],
            'definition' => ['self::failure()', 'self::failure()'],
            'expected' => [],
            'assertEventCursor' => [],
            'occurredAt' => [
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'self::failure()',
                'self::failure()',
            ],
            'opaqueId' => [
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'self::failure()',
                'self::failure()',
            ],
            'draft' => ['blackops\\core\\audit\\auditvalidationexception::assertsafestring()', 'self::failure()'],
            'selector' => ['self::failure()'],
            'tenantReference' => ['self::failure()', 'self::failure()'],
            'reference' => [],
            'value' => [
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'self::failure()',
                'self::failure()',
                'self::failure()',
            ],
            'assertAuditId' => ['blackops\\core\\audit\\auditvalidationexception::assertsafestring()'],
            'assertScopeHash' => ['blackops\\core\\audit\\auditvalidationexception::assertsafestring()'],
            'assertCursor' => [
                'blackops\\core\\audit\\auditvalidationexception::assertsafestring()',
                'symfony\\component\\uid\\uuid::fromstring()',
            ],
            'failure' => [],
            'remap' => ['self::failure()', 'self::failure()'],
        ];
    }

    /** @return array<string,list<string>> */
    private function hookNewGraph(): array
    {
        return [
            '__construct' => ['SensitiveParameterValue'],
            'assertEntry' => [],
            'assertRow' => [],
            'started' => [],
            'interrupted' => [],
            'progressed' => ['LogicException'],
            'finished' => ['LogicException', 'LogicException', 'LogicException', 'LogicException', 'LogicException'],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            '__clone' => [],
            'writerForEntry' => ['LogicException'],
            'writerForRoot' => ['LogicException'],
            'writerForEntryConnection' => [],
            'row' => ['LogicException', 'LogicException', 'LogicException', 'LogicException'],
            'target' => ['LogicException'],
            'rowTenant' => ['LogicException', 'LogicException', 'LogicException'],
            'rowCursor' => ['LogicException', 'LogicException', 'LogicException', 'LogicException'],
            'scope' => ['BlackOps\\Internal\\StorageProtection\\StorageProtectionRotationScope', 'LogicException'],
            'scopeHash' => [],
            'scopeHashValue' => ['LogicException'],
            'tenant' => [],
            'tenantPair' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Core\\TenantRef',
                'LogicException',
            ],
            'auditId' => ['LogicException'],
            'cursor' => [],
            'uuidCursor' => ['LogicException', 'LogicException', 'LogicException'],
            'compositeCursor' => ['LogicException', 'LogicException'],
            'writer' => [],
            'failure' => ['BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException'],
            'remap' => [],
        ];
    }

    /** @return array<string,list<string>> */
    private function writerNewGraph(): array
    {
        return [
            '__construct' => [
                'BlackOps\\Core\\Audit\\AuditRecordActor',
                'LogicException',
                'LogicException',
                'SensitiveParameterValue',
            ],
            'assertConnection' => ['LogicException'],
            'started' => [],
            'failed' => [],
            'succeeded' => [],
            'progressed' => [],
            '__debugInfo' => [],
            '__serialize' => [],
            '__unserialize' => [],
            '__clone' => [],
            'write' => [
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
            ],
            'catalog' => ['LogicException'],
            'definition' => [],
            'expected' => ['LogicException'],
            'assertEventCursor' => ['LogicException', 'LogicException'],
            'occurredAt' => ['DateTimeZone', 'LogicException'],
            'opaqueId' => [],
            'draft' => [
                'BlackOps\\Core\\Audit\\AuditRecordAction',
                'BlackOps\\Core\\Audit\\AuditRecordDraft',
                'BlackOps\\Core\\Audit\\AuditRecordEvent',
                'BlackOps\\Core\\Audit\\AuditRecordEvidence',
                'BlackOps\\Core\\Audit\\AuditRecordOutcome',
                'BlackOps\\Core\\Audit\\AuditRecordPolicy',
                'BlackOps\\Core\\Audit\\AuditRecordResource',
                'BlackOps\\Core\\Audit\\AuditRecordSource',
                'LogicException',
                'LogicException',
            ],
            'selector' => [],
            'tenantReference' => ['BlackOps\\Core\\Audit\\AuditTypedReference'],
            'reference' => ['BlackOps\\Core\\Audit\\AuditTypedReference'],
            'value' => [],
            'assertAuditId' => ['LogicException'],
            'assertScopeHash' => ['LogicException'],
            'assertCursor' => ['LogicException', 'LogicException', 'LogicException', 'LogicException'],
            'failure' => [
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
            ],
            'remap' => [],
        ];
    }

    private function classMethodNode(Class_ $class, string $name): ?ClassMethod
    {
        foreach ($class->stmts as $statement) {
            if ($statement instanceof ClassMethod && $statement->name->toString() === $name) {
                return $statement;
            }
        }

        return null;
    }

    /** @return list<MethodCall|NullsafeMethodCall> */
    private function allMethodCalls(Node $node): array
    {
        return [
            ...new NodeFinder()->findInstanceOf($node, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($node, NullsafeMethodCall::class),
        ];
    }

    /** @return list<string> */
    private function methodCallShapes(Node $node): array
    {
        return array_map(fn(MethodCall|NullsafeMethodCall $call): string => $this->methodCallShape(
            $call,
        ), $this->allMethodCalls($node));
    }

    private function methodCallShape(MethodCall|NullsafeMethodCall $call): string
    {
        $receiver = $this->expressionSignature($call->var) ?? 'dynamic';
        $name = $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';

        return $receiver . '->' . $name . '#' . count($call->args);
    }

    /** @return list<string> */
    private function functionCallShapes(Node $node): array
    {
        $shapes = [];
        foreach (new NodeFinder()->findInstanceOf($node, FuncCall::class) as $call) {
            $name = $call->name instanceof Name ? strtolower(ltrim($call->name->toString(), '\\')) : 'dynamic';
            $shapes[] = $name . '()';
        }

        return $shapes;
    }

    /** @return list<string> */
    private function staticCallShapes(Node $node): array
    {
        $shapes = [];
        foreach (new NodeFinder()->findInstanceOf($node, StaticCall::class) as $call) {
            $class = $call->class instanceof Name
                ? ($this->isSelfName($call->class) ? 'self' : strtolower($this->resolvedName($call->class)))
                : 'dynamic';
            $name = $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';
            $shapes[] = $class . '::' . $name . '()';
        }

        return $shapes;
    }

    private function isSelfName(Name $name): bool
    {
        return in_array(strtolower($name->toString()), ['self', 'static', 'parent'], true);
    }

    private function methodNameOf(MethodCall|NullsafeMethodCall $call): string
    {
        return $call->name instanceof Identifier ? strtolower($call->name->toString()) : 'dynamic';
    }

    private function expressionSignature(Node $expression): ?string
    {
        if ($expression instanceof Variable && is_string($expression->name)) {
            return '$' . $expression->name;
        }
        if ($expression instanceof PropertyFetch) {
            $receiver = $this->expressionSignature($expression->var);
            $name = $expression->name instanceof Identifier ? $expression->name->toString() : 'dynamic';
            return $receiver === null ? null : $receiver . '->' . $name;
        }
        if ($expression instanceof ArrayDimFetch) {
            $receiver = $this->expressionSignature($expression->var);
            if ($receiver === null) {
                return null;
            }
            $dimension = $expression->dim instanceof String_ ? $expression->dim->value : 'dynamic';
            return $receiver . '[' . $dimension . ']';
        }
        if ($expression instanceof MethodCall || $expression instanceof NullsafeMethodCall) {
            $receiver = $this->expressionSignature($expression->var);
            $name = $expression->name instanceof Identifier ? strtolower($expression->name->toString()) : 'dynamic';
            if ($receiver === null) {
                return null;
            }
            return $receiver . '->' . $name . '#' . count($expression->args);
        }
        if ($expression instanceof StaticCall) {
            $class = $expression->class instanceof Name
                ? ($this->isSelfName($expression->class) ? 'self' : strtolower($this->resolvedName($expression->class)))
                : 'dynamic';
            $name = $expression->name instanceof Identifier ? strtolower($expression->name->toString()) : 'dynamic';
            return $class . '::' . $name . '#' . count($expression->args);
        }

        return null;
    }

    /** @param list<Node> $fileNodes @return list<string> */
    private function adapterC3Violations(Class_ $class, bool $hook, array $fileNodes = []): array
    {
        $targets = [
            'BlackOps\\Audit\\AuditOpaqueIdKeyProvider',
            'BlackOps\\Audit\\AuditProtectionKeyProvider',
            'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryPlan',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditMutationTransactionCoordinator',
            'BlackOps\\Internal\\Audit\\Orchestration\\AuditProtectedReadGate',
            'BlackOps\\Internal\\Audit\\Outbox\\PostgreSqlAuditOutboxStore',
            'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditRecordStore',
            'BlackOps\\Internal\\Transaction\\OperationTransactionCoordinator',
            'BlackOps\\Internal\\Audit\\Rotation\\StorageRotationAuditWriter',
        ];
        $references = [];
        $violations = [];
        $names = new NodeFinder()->findInstanceOf($class, Name::class);
        foreach (new NodeFinder()->findInstanceOf($fileNodes, Node\Stmt\UseUse::class) as $use) {
            $names[] = $use->name;
        }
        foreach ($names as $name) {
            $resolved = $this->resolvedName($name);
            foreach ($targets as $target) {
                if (strcasecmp($resolved, $target) === 0) {
                    $references[] = $target;
                }
            }
        }
        $references = array_values(array_unique($references));
        sort($references);
        $expected = $hook
            ? [
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
                'BlackOps\\Internal\\Audit\\Rotation\\StorageRotationAuditWriter',
            ]
            : [
                'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryCoordinator',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryRequest',
                'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliverySelector',
            ];
        sort($expected);
        if ($references !== $expected) {
            $violations[] = $hook ? 'forbidden-reference-set' : 'writer-c3-reference-set';
            $expectedLookup = array_fill_keys(array_map('strtolower', $expected), true);
            foreach ($references as $reference) {
                if (isset($expectedLookup[strtolower($reference)])) {
                    continue;
                }
                $violations[] = 'forbidden-reference:' . $reference;
            }
        }
        if (!$hook) {
            foreach (new NodeFinder()->findInstanceOf($class, Name::class) as $name) {
                if (
                    strcasecmp(
                        $this->resolvedName($name),
                        'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryPlan',
                    ) === 0
                ) {
                    $violations[] = 'forbidden-reference:BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryPlan';
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** @param array<string,string> $sources @return list<string> */
    private function nativePhaseViolations(array $sources): array
    {
        $violations = [];
        $enginePath = 'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php';
        $rowPath = 'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php';
        $auditPath = 'src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php';
        if (isset($sources[$enginePath])) {
            $violations = [...$violations, ...$this->enginePhaseViolations($this->parse($sources[$enginePath]))];
        }
        if (isset($sources[$rowPath])) {
            $violations = [...$violations, ...$this->rowPhaseViolations($this->parse($sources[$rowPath]))];
        }
        if (isset($sources[$auditPath])) {
            $violations = [...$violations, ...$this->auditPhaseViolations($this->parse($sources[$auditPath]))];
        }

        return array_values(array_unique($violations));
    }

    /** @param list<Node> $nodes @return list<string> */
    private function enginePhaseViolations(array $nodes): array
    {
        $violations = [];
        $class = $this->classNodeByResolvedName(
            $nodes,
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationEngine',
        );
        if ($class === null) {
            return ['engine-declaration'];
        }
        $assert = $this->classMethodNode($class, 'assertEntry');
        if (
            $assert === null
            || !$this->hasExactMethodCall($assert, '$this->phaseHook', 'assertentry', ['$this->connection', '$scope'])
        ) {
            $violations[] = 'entry-helper';
        }
        $plan = $this->classMethodNode($class, 'plan');
        $rotate = $this->classMethodNode($class, 'rotate');
        if (
            $plan === null
            || !$this->hasExactMethodCall($plan, '$this', 'assertentry', ['$scope'])
            || $this->firstCallPosition($plan, '$this', 'assertentry') === null
            || $this->firstCallPosition($plan, '$this', 'runinternal') === null
        ) {
            $violations[] = 'entry-admission';
        } elseif (
            $this->firstCallPosition($plan, '$this', 'assertentry') >= $this->firstCallPosition(
                $plan,
                '$this',
                'runinternal',
            )
            || $this->countCalls($plan, '$this', 'assertentry') !== 1
        ) {
            $violations[] = 'entry-admission';
        }
        if ($rotate === null) {
            return [...$violations, 'rotate-inventory'];
        }
        $entry = $this->firstCallPosition($rotate, '$this', 'assertentry');
        $lock = $this->firstCallPosition($rotate, '$this->connection', 'fetchone');
        $confirm = $this->firstNodePosition($rotate, Node\Expr\PropertyFetch::class, 'confirmed');
        if (
            $entry === null
            || $lock === null
            || $entry >= $lock
            || $confirm === null
            || $confirm >= $entry
            || $this->countCalls($rotate, '$this', 'assertentry') !== 1
            || !$this->hasExactMethodCall($rotate, '$this', 'assertentry', ['$scope'])
            || !$this->callsHaveArity($rotate, '$this->connection', 'fetchone', 2)
        ) {
            $violations[] = 'entry-admission';
        }
        if ($lock !== null && !$this->containsStringAfter($rotate, 'pg_try_advisory_lock', $lock)) {
            $violations[] = 'lock-admission';
        }

        return array_values(array_unique($violations));
    }

    /** @param list<Node> $nodes @return list<string> */
    private function rowPhaseViolations(array $nodes): array
    {
        $violations = [];
        $class = $this->classNodeByResolvedName(
            $nodes,
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationRowTransaction',
        );
        if ($class === null) {
            return ['row-declaration'];
        }
        $execute = $this->classMethodNode($class, 'execute');
        $transaction = $this->classMethodNode($class, 'transaction');
        if ($execute === null || $transaction === null) {
            return ['row-method-inventory'];
        }
        $begin = $this->firstCallPosition($execute, '$this->connection', 'begintransaction');
        $admission = $this->findMethodCall($execute, '$this->phaseHook', 'assertrow');
        $admissionRollback = $admission === null ? false : $this->insideRollbackTry($execute, $admission);
        $columns = $this->firstCallPosition($execute, '$this', 'columns');
        if (
            $begin === null
            || $admission === null
            || !$admissionRollback
            || $columns === null
            || $begin >= $admission->getStartFilePos()
            || $admission->getStartFilePos() >= $columns
            || !$this->callArgumentsAre($admission, ['$this->connection', '$request'])
            || $this->countCalls($execute, '$this->phaseHook', 'assertrow') !== 1
        ) {
            $violations[] = 'row-admission';
        }
        $advance = $this->findMethodCall($transaction, '$this', 'advance');
        $progressed = $this->findMethodCall($transaction, '$this->phaseHook', 'progressed');
        $commit = $this->firstCallPosition($transaction, '$this->connection', 'commit');
        if (
            $advance === null
            || $progressed === null
            || $commit === null
            || $advance->getStartFilePos() >= $progressed->getStartFilePos()
            || $progressed->getStartFilePos() >= $commit
            || $this->countCalls($transaction, '$this->phaseHook', 'progressed') !== 1
            || !$this->callArgumentsAre($advance, ['$request', '$result'])
            || !$this->callArgumentsAre($progressed, ['$this->connection', '$request', '$cursor'])
            || !$this->insideRollbackTry($transaction, $progressed)
            || $this->insideTryContaining($transaction, $progressed, $advance)
        ) {
            $violations[] = 'progressed-edge';
        }
        $commitCall = $this->findMethodCall($transaction, '$this->connection', 'commit');
        if (
            $commitCall === null
            || count($commitCall->args) !== 0
            || !$this->insideRollbackTry($transaction, $commitCall)
        ) {
            $violations[] = 'commit-root';
        }

        return array_values(array_unique($violations));
    }

    /** @param list<Node> $nodes @return list<string> */
    private function auditPhaseViolations(array $nodes): array
    {
        $violations = [];
        $class = $this->classNodeByResolvedName(
            $nodes,
            'BlackOps\\Transport\\PostgreSql\\PostgreSqlStorageProtectionRotationAudit',
        );
        if ($class === null) {
            return ['audit-declaration'];
        }
        $begin = $this->classMethodNode($class, 'begin');
        $finish = $this->classMethodNode($class, 'finish');
        $recover = $this->classMethodNode($class, 'recoverInterrupted');
        if ($begin === null || $finish === null || $recover === null) {
            return ['audit-method-inventory'];
        }
        $violations = [...$violations, ...$this->auditSqlViolations($class)];
        $beginOrder = $this->methodCallPositions($begin, [
            'begintransaction',
            'assertcheckpointscope',
            'claimcheckpoint',
            'recoverinterrupted',
            'insertaudit',
            'started',
            'commit',
        ]);
        if (!$this->strictlyIncreasing($beginOrder)) {
            $violations[] = 'begin-phase-order';
        }
        if (
            $this->countCalls($begin, '$this->connection', 'begintransaction') !== 1
            || $this->countCalls($begin, '$this->connection', 'commit') !== 1
        ) {
            $violations[] = 'begin-root';
        }
        if (!$this->hasRollbackCatch($begin)) {
            $violations[] = 'begin-rollback';
        }
        if (!$this->hasExactMethodCall(
            $begin,
            '$this->phaseHook',
            'started',
            ['$this->connection', '$auditId', '$scope'],
        )) {
            $violations[] = 'started-edge';
        }
        $recoverCalls = $this->allMethodCalls($recover);
        $select = $this->firstCallNamed($recoverCalls, 'fetchfirstcolumn');
        $ids = $this->findMethodCall($recover, '$this', 'interruptedids');
        $interrupted = $this->findMethodCall($recover, '$phaseHook', 'interrupted');
        $updates = array_values(array_filter(
            $recoverCalls,
            fn(MethodCall|NullsafeMethodCall $call): bool => $this->methodNameOf($call) === 'executestatement',
        ));
        if ($select === null || $ids === null || $interrupted === null || count($updates) < 2) {
            $violations[] = 'interrupted-edge';
        } else {
            $update = $updates[count($updates) - 1];
            if (
                $select->getStartFilePos() >= $ids->getStartFilePos()
                || $ids->getStartFilePos() >= $update->getStartFilePos()
                || $update->getStartFilePos() >= $interrupted->getStartFilePos()
                || !$this->callArgumentsAre($ids, ['$interrupted'])
                || !$this->callArgumentsAre($interrupted, ['$this->connection', '$interruptedId', '$scope'])
                || !$this->callFirstArgumentContainsString($select, 'SELECT audit_id')
                || !$this->callFirstArgumentContainsString($select, 'LIMIT 101')
                || !$this->callFirstArgumentContainsString($select, 'FOR UPDATE')
                || !$this->hasInterruptedOverflowCheck($recover)
            ) {
                $violations[] = 'interrupted-edge';
            }
        }
        $finishOrder = $this->methodCallPositions($finish, [
            'begintransaction',
            'executestatement',
            'executestatement',
            'finished',
            'commit',
        ]);
        if (!$this->strictlyIncreasing($finishOrder) || !$this->hasRollbackCatch($finish)) {
            $violations[] = 'finish-phase-order';
        }
        if (
            $this->countCalls($finish, '$this->connection', 'begintransaction') !== 1
            || $this->countCalls($finish, '$this->connection', 'commit') !== 1
        ) {
            $violations[] = 'finish-root';
        }
        $finished = $this->findMethodCall($finish, '$this->phaseHook', 'finished');
        if ($finished === null || !$this->callArgumentsAre($finished, ['$this->connection', '$completion'])) {
            $violations[] = 'finished-edge';
        }
        $beginCommit = $this->findMethodCall($begin, '$this->connection', 'commit');
        $finishCommit = $this->findMethodCall($finish, '$this->connection', 'commit');
        if (
            $beginCommit === null
            || count($beginCommit->args) !== 0
            || !$this->insideRollbackTry($begin, $beginCommit)
        ) {
            $violations[] = 'begin-root';
        }
        if (
            $finishCommit === null
            || count($finishCommit->args) !== 0
            || !$this->insideRollbackTry($finish, $finishCommit)
        ) {
            $violations[] = 'finish-root';
        }
        foreach ([
            ['assertCheckpointScope', ['$checkpoint', '$scope']],
            ['claimCheckpoint', ['$checkpoint', '$scope', '$now']],
            ['recoverInterrupted', ['$audit', '$scope', '$now']],
            ['insertAudit', ['$audit', '$auditId', '$scope', '$now']],
        ] as [$name, $arguments]) {
            if (!$this->hasExactMethodCall($begin, '$this', $name, $arguments)) {
                $violations[] = 'begin-edge';
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function auditSqlViolations(Class_ $class): array
    {
        $expected = [
            'assertCheckpointScope' => ['fetchone' => 1, 'needles' => ['SELECT scope_hash']],
            'claimCheckpoint' => ['executeStatement' => 1, 'needles' => ['INSERT INTO', 'ON CONFLICT']],
            'insertAudit' => ['executeStatement' => 1, 'needles' => ['INSERT INTO']],
            'recoverInterrupted' => [
                'fetchfirstcolumn' => 1,
                'executeStatement' => 2,
                'needles' => ['SELECT audit_id', 'UPDATE'],
            ],
            'finish' => ['executeStatement' => 2, 'needles' => ['UPDATE']],
        ];
        $arities = [
            'assertCheckpointScope' => ['fetchone' => 2],
            'claimCheckpoint' => ['executeStatement' => 2],
            'insertAudit' => ['executeStatement' => 2],
            'recoverInterrupted' => ['fetchfirstcolumn' => 2, 'executeStatement' => 2],
            'finish' => ['executeStatement' => 2],
        ];
        $sqlNeedles = [
            'assertCheckpointScope' => ['fetchone' => ['SELECT scope_hash']],
            'claimCheckpoint' => ['executeStatement' => ['INSERT INTO', 'ON CONFLICT']],
            'insertAudit' => ['executeStatement' => ['INSERT INTO']],
            'recoverInterrupted' => [
                'fetchfirstcolumn' => ['SELECT audit_id', 'LIMIT 101', 'FOR UPDATE'],
                'executeStatement' => ['UPDATE'],
            ],
            'finish' => ['executeStatement' => ['UPDATE']],
        ];
        $violations = [];
        foreach ($expected as $methodName => $contract) {
            $method = $this->classMethodNode($class, $methodName);
            if ($method === null) {
                $violations[] = 'sql-method:' . $methodName;
                continue;
            }
            foreach ($contract as $callName => $count) {
                if ($callName === 'needles') {
                    continue;
                }
                if ($this->countCalls($method, '$this->connection', $callName) !== $count) {
                    $violations[] = 'sql-call:' . $methodName . ':' . $callName;
                }
            }
            foreach ($sqlNeedles[$methodName] ?? [] as $callName => $needles) {
                $calls = array_values(array_filter(
                    $this->allMethodCalls($method),
                    fn(MethodCall|NullsafeMethodCall $call): bool => (
                        $this->expressionSignature($call->var) === '$this->connection'
                        && $this->methodNameOf($call) === strtolower($callName)
                    ),
                ));
                foreach ($needles as $needle) {
                    if ($calls === []) {
                        $violations[] = 'sql-text:' . $methodName . ':' . $needle;
                        continue;
                    }
                    foreach ($calls as $call) {
                        if (!$this->callFirstArgumentContainsString($call, $needle)) {
                            $violations[] = 'sql-text:' . $methodName . ':' . $needle;
                            break;
                        }
                    }
                }
            }
            foreach ($arities[$methodName] ?? [] as $callName => $arity) {
                if (!$this->callsHaveArity($method, '$this->connection', $callName, $arity)) {
                    $violations[] = 'sql-arity:' . $methodName . ':' . $callName;
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** @param list<Node> $nodes */
    private function classNodeByResolvedName(array $nodes, string $name): ?Class_
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, Class_::class) as $class) {
            if (
                $class->namespacedName instanceof Name
                && strcasecmp($this->resolvedName($class->namespacedName), $name) === 0
            ) {
                return $class;
            }
        }

        return null;
    }

    private function hasExactMethodCall(ClassMethod $method, string $receiver, string $name, array $arguments): bool
    {
        $name = strtolower($name);
        foreach ($this->allMethodCalls($method) as $call) {
            if (
                $call instanceof MethodCall
                && $this->expressionSignature($call->var) === $receiver
                && $this->methodNameOf($call) === $name
                && $this->callArgumentsAre($call, $arguments)
            ) {
                return true;
            }
        }

        return false;
    }

    private function findMethodCall(ClassMethod $method, string $receiver, string $name): ?MethodCall
    {
        $name = strtolower($name);
        foreach ($this->allMethodCalls($method) as $call) {
            if (
                $call instanceof MethodCall
                && $this->expressionSignature($call->var) === $receiver
                && $this->methodNameOf($call) === $name
            ) {
                return $call;
            }
        }

        return null;
    }

    private function firstCallPosition(ClassMethod $method, string $receiver, string $name): ?int
    {
        $call = $this->findMethodCall($method, $receiver, $name);

        return $call?->getStartFilePos();
    }

    private function countCalls(ClassMethod $method, string $receiver, string $name): int
    {
        $name = strtolower($name);
        return count(array_filter(
            $this->allMethodCalls($method),
            fn(MethodCall|NullsafeMethodCall $call): bool => (
                $this->expressionSignature($call->var) === $receiver
                && $this->methodNameOf($call) === $name
            ),
        ));
    }

    private function firstCallNamed(array $calls, string $name): ?MethodCall
    {
        $name = strtolower($name);
        foreach ($calls as $call) {
            if ($call instanceof MethodCall && $this->methodNameOf($call) === $name) {
                return $call;
            }
        }

        return null;
    }

    private function firstNodePosition(Node $node, string $type, string $property): ?int
    {
        foreach (new NodeFinder()->findInstanceOf($node, $type) as $candidate) {
            if (
                $candidate instanceof PropertyFetch
                && $candidate->name instanceof Identifier
                && $candidate->name->toString() === $property
            ) {
                return $candidate->getStartFilePos();
            }
        }

        return null;
    }

    private function callArgumentsAre(MethodCall|NullsafeMethodCall $call, array $arguments): bool
    {
        if (count($call->args) !== count($arguments)) {
            return false;
        }
        foreach ($call->args as $index => $argument) {
            if ($this->expressionSignature($argument->value) !== $arguments[$index]) {
                return false;
            }
        }

        return true;
    }

    private function insideTryContaining(
        ClassMethod $method,
        MethodCall|NullsafeMethodCall $candidate,
        MethodCall|NullsafeMethodCall $anchor,
    ): bool {
        foreach (new NodeFinder()->findInstanceOf($method, TryCatch::class) as $try) {
            if ($this->nodeContains($try->stmts, $candidate) && $this->nodeContains($try->stmts, $anchor)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Node> $nodes */
    private function nodeContains(array $nodes, Node $needle): bool
    {
        foreach ($nodes as $node) {
            if ($node === $needle) {
                return true;
            }
            if (
                new NodeFinder()->findFirst($node, static fn(Node $candidate): bool => $candidate === $needle) !== null
            ) {
                return true;
            }
        }

        return false;
    }

    private function strictlyIncreasing(array $positions): bool
    {
        if ($positions === []) {
            return false;
        }
        if (count($positions) !== count(array_filter($positions, static fn($position): bool => is_int($position)))) {
            return false;
        }
        for ($index = 1; $index < count($positions); $index++) {
            if ($positions[$index - 1] >= $positions[$index]) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $names @return list<int> */
    private function methodCallPositions(ClassMethod $method, array $names): array
    {
        $calls = $this->allMethodCalls($method);
        usort(
            $calls,
            static fn(Node $left, Node $right): int => $left->getStartFilePos() <=> $right->getStartFilePos(),
        );
        $positions = [];
        $offset = 0;
        foreach ($names as $name) {
            $name = strtolower($name);
            $found = null;
            for ($index = $offset; $index < count($calls); $index++) {
                if ($this->methodNameOf($calls[$index]) === $name) {
                    $found = $calls[$index];
                    $offset = $index + 1;
                    break;
                }
            }
            if ($found === null) {
                return [];
            }
            $positions[] = $found->getStartFilePos();
        }

        return $positions;
    }

    private function hasRollbackCatch(ClassMethod $method): bool
    {
        foreach (new NodeFinder()->findInstanceOf($method, TryCatch::class) as $try) {
            $calls = $this->allMethodCalls($try);
            $names = array_map(fn(MethodCall|NullsafeMethodCall $call): string => $this->methodNameOf($call), $calls);
            if (
                in_array('istransactionactive', $names, true)
                && in_array('rollback', $names, true)
                && new NodeFinder()->findFirst($try, static fn(Node $node): bool => $node instanceof Node\Expr\Throw_)
                    !== null
            ) {
                return true;
            }
        }

        return false;
    }

    private function insideRollbackTry(ClassMethod $method, MethodCall $candidate): bool
    {
        foreach (new NodeFinder()->findInstanceOf($method, TryCatch::class) as $try) {
            if ($this->nodeContains($try->stmts, $candidate) && $this->hasRollbackRethrow($try)) {
                return true;
            }
        }

        return false;
    }

    private function hasRollbackRethrow(TryCatch $try): bool
    {
        foreach ($try->catches as $catch) {
            if (!$this->isExactThrowableCatch($catch)) {
                continue;
            }
            $caught = $catch->var instanceof Variable && is_string($catch->var->name) ? $catch->var->name : null;
            if ($caught === null) {
                continue;
            }
            $rollbacks = array_values(array_filter(
                $this->allMethodCalls($catch),
                fn(MethodCall|NullsafeMethodCall $call): bool => (
                    $call instanceof MethodCall
                    && in_array($this->expressionSignature($call->var), ['$this', '$this->connection'], true)
                    && $this->methodNameOf($call) === 'rollback'
                ),
            ));
            if ($rollbacks === []) {
                continue;
            }
            foreach (new NodeFinder()->findInstanceOf($catch, Node\Expr\Throw_::class) as $throw) {
                if (
                    $throw->expr instanceof Variable
                    && $throw->expr->name === $caught
                    && array_filter(
                        $rollbacks,
                        fn(MethodCall|NullsafeMethodCall $rollback): bool => $rollback->getStartFilePos() < $throw->getStartFilePos(),
                    ) !== []
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isExactThrowableCatch(Catch_ $catch): bool
    {
        foreach ($catch->types as $type) {
            if ($type instanceof Name && strcasecmp($this->resolvedName($type), 'Throwable') === 0) {
                return true;
            }
        }

        return false;
    }

    private function callsHaveArity(ClassMethod $method, string $receiver, string $name, int $arity): bool
    {
        $name = strtolower($name);
        $calls = array_values(array_filter(
            $this->allMethodCalls($method),
            fn(MethodCall|NullsafeMethodCall $call): bool => (
                $this->expressionSignature($call->var) === $receiver
                && $this->methodNameOf($call) === $name
            ),
        ));
        if ($calls === []) {
            return false;
        }
        foreach ($calls as $call) {
            if (count($call->args) !== $arity) {
                return false;
            }
        }

        return true;
    }

    private function containsStringAfter(ClassMethod $method, string $needle, int $position): bool
    {
        foreach ($this->stringLiteralNodes($method) as [$node, $value]) {
            if ($node->getStartFilePos() > $position && str_contains(strtoupper($value), strtoupper($needle))) {
                return true;
            }
        }

        return false;
    }

    private function methodContainsString(ClassMethod $method, string $needle): bool
    {
        foreach ($this->stringLiteralNodes($method) as [, $value]) {
            if (str_contains(strtoupper($value), strtoupper($needle))) {
                return true;
            }
        }

        return false;
    }

    private function nodeContainsString(Node $node, string $needle): bool
    {
        if ($node instanceof String_) {
            return str_contains(strtoupper($node->value), strtoupper($needle));
        }
        if ($node instanceof InterpolatedString) {
            $value = '';
            foreach ($node->parts as $part) {
                if ($part instanceof InterpolatedStringPart) {
                    $value .= $part->value;
                }
            }

            return str_contains(strtoupper($value), strtoupper($needle));
        }
        foreach ($this->stringLiteralNodes($node) as [, $value]) {
            if (str_contains(strtoupper($value), strtoupper($needle))) {
                return true;
            }
        }

        return false;
    }

    private function callFirstArgumentContainsString(MethodCall|NullsafeMethodCall $call, string $needle): bool
    {
        return isset($call->args[0]) && $this->nodeContainsString($call->args[0]->value, $needle);
    }

    /** @return list<array{Node,string}> */
    private function stringLiteralNodes(Node $node): array
    {
        $literals = [];
        foreach (new NodeFinder()->findInstanceOf($node, String_::class) as $string) {
            $literals[] = [$string, $string->value];
        }
        foreach (new NodeFinder()->findInstanceOf($node, InterpolatedString::class) as $string) {
            $value = '';
            foreach ($string->parts as $part) {
                if ($part instanceof InterpolatedStringPart) {
                    $value .= $part->value;
                }
            }
            $literals[] = [$string, $value];
        }

        return $literals;
    }

    private function hasInterruptedOverflowCheck(ClassMethod $method): bool
    {
        foreach (new NodeFinder()->findInstanceOf($method, Node\Stmt\If_::class) as $if) {
            foreach (new NodeFinder()->findInstanceOf($if->cond, Node\Expr\BinaryOp\Greater::class) as $comparison) {
                if (
                    $comparison->left instanceof FuncCall
                    && $comparison->left->name instanceof Name
                    && strtolower($comparison->left->name->toString()) === 'count'
                    && count($comparison->left->args) === 1
                    && $comparison->left->args[0]->value instanceof Variable
                    && $comparison->left->args[0]->value->name === 'interrupted'
                    && $comparison->right instanceof Node\Scalar\LNumber
                    && $comparison->right->value === 100
                    && new NodeFinder()->findFirst(
                        $if->stmts,
                        static fn(Node $node): bool => $node instanceof Node\Expr\Throw_,
                    ) !== null
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<Node> $nodes @return list<string> */
    private function constructorArguments(array $nodes, string $target, string $owner): array
    {
        $matches = array_values(array_filter(
            new NodeFinder()->findInstanceOf($nodes, Node\Expr\New_::class),
            fn(Node\Expr\New_ $new): bool => (
                $new->class instanceof Name
                && strcasecmp($this->resolvedName($new->class), $target) === 0
            ),
        ));
        self::assertCount(1, $matches, 'Constructor target count: ' . $target);
        $new = $matches[0];
        $context = $this->nativeConstructorOwner($nodes, $new);
        if (
            $context === null
            || $context[1]->name->toString() !== '__construct'
            || !$context[0]->namespacedName instanceof Name
            || strcasecmp($this->resolvedName($context[0]->namespacedName), $owner) !== 0
        ) {
            return [];
        }
        $arguments = [];
        foreach ($new->args as $argument) {
            $descriptor = $this->argumentDescriptor($argument->value);
            $arguments[] = $argument->name instanceof Identifier
                ? $argument->name->toString() . ':' . $descriptor
                : $descriptor;
        }

        return $arguments;
    }

    private function argumentDescriptor(Node $value): string
    {
        if ($value instanceof Node\Expr\Variable && is_string($value->name)) {
            return '$' . $value->name;
        }
        if ($value instanceof Node\Expr\New_ && $value->class instanceof Name) {
            return (
                'new:'
                . $this->resolvedName($value->class)
                . '('
                . implode(',', array_map(
                    fn(Node\Arg $argument): string => (
                        ($argument->name instanceof Identifier ? $argument->name->toString() . ':' : '')
                        . $this->argumentDescriptor($argument->value)
                    ),
                    $value->args,
                ))
                . ')'
            );
        }

        return 'expression';
    }

    /** @param list<Node> $nodes @return list<string> */
    private function calledMethods(array $nodes): array
    {
        $methods = [];
        foreach (new NodeFinder()->find(
            $nodes,
            static fn(Node $node): bool => (
                $node instanceof Node\Expr\MethodCall
                || $node instanceof Node\Expr\NullsafeMethodCall
                || $node instanceof Node\Expr\StaticCall
            ),
        ) as $call) {
            $name = $call->name;
            if (!$name instanceof Node\Identifier) {
                throw new \LogicException('Dynamic method form is unsupported.');
            }
            $methods[] = $name->toString();
        }
        $methods = array_values(array_unique($methods));
        sort($methods);

        return $methods;
    }

    private function classMethod(string $source, string $class, string $method): Node\Stmt\ClassMethod
    {
        foreach (new NodeFinder()->findInstanceOf($this->parse($source), Node\Stmt\Class_::class) as $candidate) {
            if ($candidate->name?->toString() !== $class) {
                continue;
            }
            foreach ($candidate->getMethods() as $candidateMethod) {
                if ($candidateMethod->name->toString() === $method) {
                    return $candidateMethod;
                }
            }
        }
        self::fail($class . '::' . $method . ' is missing.');
    }

    /** @return list<Node> */
    private function parse(string $source): array
    {
        $nodes = new ParserFactory()
            ->createForNewestSupportedVersion()
            ->parse($source);
        self::assertIsArray($nodes);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        return $traverser->traverse($nodes);
    }

    private function resolvedName(Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        return ltrim($resolved instanceof Name ? $resolved->toString() : $name->toString(), '\\');
    }
}

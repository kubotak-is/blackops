<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Internal\Status\AuditedOperationStatusQuery;
use BlackOps\Internal\Status\OperationStatusAuditTenantAuthorizer;
use BlackOps\Internal\Status\OperationStatusReadExecutionSource;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;

/**
 * Checks the unactivated status adapter at its Internal boundary.
 *
 * The source oracle intentionally parses candidate strings as PHP. This keeps
 * mutation cases executable while avoiding a text-only allowlist for security
 * and call-site rules.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class AuditedOperationStatusArchitectureTest extends TestCase
{
    public function testExactDeclarationsPropertiesAndSensitiveSurfaces(): void
    {
        $this->assertRuntimeDeclaration(
            new ReflectionClass(AuditedOperationStatusQuery::class),
            [\BlackOps\Status\TenantAwareOperationStatusQuery::class],
            [
                '__construct' => 'public',
                'find' => 'public',
                'findForTenant' => 'public',
                '__debugInfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'assertIdle' => 'private',
                'value' => 'private',
            ],
        );
        $this->assertRuntimeDeclaration(
            new ReflectionClass(OperationStatusAuditTenantAuthorizer::class),
            [\BlackOps\Status\OperationStatusAuthorizer::class],
            [
                '__construct' => 'public',
                'decide' => 'public',
                '__debugInfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'sameTenant' => 'private',
                'value' => 'private',
            ],
        );
        $this->assertRuntimeDeclaration(
            new ReflectionClass(OperationStatusReadExecutionSource::class),
            [\BlackOps\Internal\Audit\Read\OperationReadExecutionSource::class],
            [
                '__construct' => 'public',
                'current' => 'public',
                '__debugInfo' => 'public',
                '__serialize' => 'public',
                '__unserialize' => 'public',
                'value' => 'private',
            ],
        );

        $query = new ReflectionClass(AuditedOperationStatusQuery::class);
        $this->assertMethodContract(
            $query->getMethod('__construct'),
            null,
            [
                ['source',     'TenantScopedOperationStatusSource', true, false],
                ['authorizer', 'OperationStatusAuthorizer',         true, false],
                ['sessions',   'OperationReadAuditSessionFactory',  true, false],
                ['connection', 'Connection',                        true, false],
            ],
        );
        foreach (['find', 'findForTenant'] as $name) {
            $this->assertMethodContract(
                $query->getMethod($name),
                'OperationStatusResult',
                [
                    ['operationId',   'OperationId', true, false],
                    ['currentActor',  '?ActorRef',   true, true],
                    ['currentTenant', '?TenantRef',  true, true],
                ],
            );
        }
        $this->assertMethodContract($query->getMethod('__debugInfo'), 'array', []);
        $this->assertMethodContract($query->getMethod('__serialize'), 'array', []);
        $this->assertMethodContract(
            $query->getMethod('__unserialize'),
            'void',
            [
                ['data', 'array', true, false],
            ],
        );
        $this->assertMethodContract(
            $query->getMethod('assertIdle'),
            'void',
            [
                ['connection', 'Connection', true, false],
            ],
        );
        $this->assertMethodContract(
            $query->getMethod('value'),
            'mixed',
            [
                ['key', 'string', true, false],
            ],
        );

        $authorizer = new ReflectionClass(OperationStatusAuditTenantAuthorizer::class);
        $this->assertMethodContract(
            $authorizer->getMethod('__construct'),
            null,
            [
                ['authorizer', 'OperationStatusAuthorizer', true, false],
            ],
        );
        $this->assertMethodContract(
            $authorizer->getMethod('decide'),
            'OperationStatusAuthorizationDecision',
            [
                ['request', 'OperationStatusAuthorizationRequest', true, false],
            ],
        );
        $this->assertMethodContract($authorizer->getMethod('__debugInfo'), 'array', []);
        $this->assertMethodContract($authorizer->getMethod('__serialize'), 'array', []);
        $this->assertMethodContract(
            $authorizer->getMethod('__unserialize'),
            'void',
            [
                ['data', 'array', true, false],
            ],
        );
        $this->assertMethodContract(
            $authorizer->getMethod('sameTenant'),
            'bool',
            [
                ['current', '?TenantRef', true, false],
                ['origin',  '?TenantRef', true, false],
            ],
        );
        $this->assertMethodContract(
            $authorizer->getMethod('value'),
            'mixed',
            [
                ['key', 'string', true, false],
            ],
        );

        $execution = new ReflectionClass(OperationStatusReadExecutionSource::class);
        $this->assertMethodContract(
            $execution->getMethod('__construct'),
            null,
            [
                ['identifiers', 'Uuidv7Generator', true, false],
            ],
        );
        $this->assertMethodContract($execution->getMethod('current'), 'AuditTypedReference', []);
        $this->assertMethodContract($execution->getMethod('__debugInfo'), 'array', []);
        $this->assertMethodContract($execution->getMethod('__serialize'), 'array', []);
        $this->assertMethodContract(
            $execution->getMethod('__unserialize'),
            'void',
            [
                ['data', 'array', true, false],
            ],
        );
        $this->assertMethodContract(
            $execution->getMethod('value'),
            'mixed',
            [
                ['key', 'string', true, false],
            ],
        );
    }

    public function testTokenAwareBoundariesAndCallFlowsAreExact(): void
    {
        self::assertSame([], $this->architectureViolations($this->adapterSources()));
        self::assertSame([], $this->productionConstructionViolations($this->productionSources()));

        $sources = $this->adapterSources();
        $query = $this->methodNode($sources['src/Internal/Status/AuditedOperationStatusQuery.php'], 'findForTenant');
        self::assertNotNull($query);
        $calls = $this->methodCalls($query);
        self::assertSame(1, $this->countCalls($calls, '$sessions', 'begin', [
            ['class-constant', 'AuditReadPurpose', 'Status'],
            ['variable', 'operationId'],
            ['variable', 'currentActor'],
            ['variable', 'currentTenant'],
        ]));
        self::assertSame(1, $this->countCalls($calls, '$query', 'findForTenant', [
            ['variable', 'operationId'],
            ['variable', 'currentActor'],
            ['variable', 'currentTenant'],
        ]));
        self::assertSame(1, $this->countCalls($calls, '$session', 'accessed', []));
        self::assertSame(1, $this->countCalls($calls, '$session', 'unavailable', []));
        self::assertSame(4, $this->countCalls($calls, '$this', 'assertIdle', [
            ['variable', 'connection'],
        ]));

        $authorizer = $this->methodNode(
            $sources['src/Internal/Status/OperationStatusAuditTenantAuthorizer.php'],
            'decide',
        );
        self::assertNotNull($authorizer);
        $authorizerCalls = $this->methodCalls($authorizer);
        self::assertSame(1, $this->countCalls($authorizerCalls, '$this', 'sameTenant', [
            ['method-call', '$request', 'currentTenant'],
            ['method-call', '$request', 'originTenant'],
        ]));
        self::assertSame(1, $this->countCalls($authorizerCalls, '$authorizer', 'decide', [['variable', 'request']]));
        self::assertSame([], $this->newTypeInventory($authorizer));

        $execution = $this->methodNode(
            $sources['src/Internal/Status/OperationStatusReadExecutionSource.php'],
            'current',
        );
        self::assertNotNull($execution);
        $executionCalls = $this->methodCalls($execution);
        self::assertSame(1, $this->countCalls($executionCalls, '$identifiers', 'generate', []));
        self::assertSame(1, $this->countCalls($executionCalls, '$this', 'value', [['string', 'identifiers']]));

        $queryConstruct = $this->methodNode(
            $sources['src/Internal/Status/AuditedOperationStatusQuery.php'],
            '__construct',
        );
        self::assertNotNull($queryConstruct);
        self::assertSame(
            ['OperationStatusAuditTenantAuthorizer', 'SensitiveParameterValue'],
            $this->newTypeInventory($queryConstruct),
        );
        self::assertSame(1, $this->countNew($queryConstruct, 'OperationStatusAuditTenantAuthorizer', [
            ['variable', 'authorizer'],
        ]));

        $queryBody = $this->methodNode(
            $sources['src/Internal/Status/AuditedOperationStatusQuery.php'],
            'findForTenant',
        );
        self::assertNotNull($queryBody);
        self::assertSame(
            ['DefaultOperationStatusQuery', 'TenantBoundOperationStatusSource'],
            $this->newTypeInventory($queryBody),
        );
        self::assertSame(1, $this->countNew($queryBody, 'TenantBoundOperationStatusSource', [
            ['variable', 'source'],
            ['variable', 'currentTenant'],
        ]));
        $queryValue = $this->methodNode($sources['src/Internal/Status/AuditedOperationStatusQuery.php'], 'value');
        self::assertNotNull($queryValue);
        self::assertSame(1, $this->countInstanceof($queryValue, 'OperationStatusAuditTenantAuthorizer'));
        self::assertSame(1, $this->countNew($queryBody, 'DefaultOperationStatusQuery', [
            ['variable', 'boundSource'],
            ['variable', 'authorizer'],
        ]));

        $executionConstruct = $this->methodNode(
            $sources['src/Internal/Status/OperationStatusReadExecutionSource.php'],
            '__construct',
        );
        self::assertNotNull($executionConstruct);
        self::assertSame(
            ['SensitiveParameterValue', 'ValidatedUuidv7Generator'],
            $this->newTypeInventory($executionConstruct),
        );
        self::assertSame(1, $this->countNew($executionConstruct, 'ValidatedUuidv7Generator', [[
            'variable',
            'identifiers',
        ]]));
        $executionValue = $this->methodNode(
            $sources['src/Internal/Status/OperationStatusReadExecutionSource.php'],
            'value',
        );
        self::assertNotNull($executionValue);
        self::assertSame(1, $this->countInstanceof($executionValue, 'ValidatedUuidv7Generator'));

        $current = $this->methodNode($sources['src/Internal/Status/OperationStatusReadExecutionSource.php'], 'current');
        self::assertNotNull($current);
        self::assertSame(
            ['AuditDeliveryException', 'AuditTypedReference', 'LogicException'],
            $this->newTypeInventory($current),
        );
        self::assertSame(1, $this->countNew($current, 'AuditTypedReference', [
            ['string', 'operation_status_read'],
            ['method-call', '$identifiers', 'generate'],
        ]));
        self::assertTrue($this->hasStringArgumentInStaticConstruction(
            $current,
            'AuditTypedReference',
            'operation_status_read',
        ));
        self::assertSame([], $this->forbiddenCalls($sources));
    }

    public function testSyntaxValidMutationsAreRejectedAndInertTextStaysIgnored(): void
    {
        $sources = $this->adapterSources();
        $inert = $sources;
        $inert['src/Internal/Status/AuditedOperationStatusQuery.php'] .= <<<'PHP'

            // new OperationReadAuditSession($writer); $connection->beginTransaction();
            $inertText = 'new OperationReadAuditSession($writer); $session->open();';
            PHP;
        self::assertSame([], $this->architectureViolations($inert));

        $queryPath = 'src/Internal/Status/AuditedOperationStatusQuery.php';
        $authorizerPath = 'src/Internal/Status/OperationStatusAuditTenantAuthorizer.php';
        $executionPath = 'src/Internal/Status/OperationStatusReadExecutionSource.php';
        $query = $sources[$queryPath];
        $authorizer = $sources[$authorizerPath];
        $execution = $sources[$executionPath];
        $terminalTry = <<<'PHP'
                    try {
                        if ($result instanceof OperationStatusFound || $result instanceof OperationStatusExpired) {
                            $session->accessed();
                        } else {
                            $session->unavailable();
                        }
                    } catch (Throwable) {
                        throw OperationStatusQueryException::storageFailed();
                    }
            PHP;
        $delegateCatches = <<<'PHP'
                    } catch (OperationStatusQueryException $exception) {
                        $failure = $exception;
                    } catch (Throwable) {
                        $failure = OperationStatusQueryException::storageFailed();
                    }
            PHP;
        $reorderedDelegateCatches = <<<'PHP'
                    } catch (Throwable) {
                        $failure = OperationStatusQueryException::storageFailed();
                    } catch (OperationStatusQueryException $exception) {
                        $failure = $exception;
                    }
            PHP;
        $mutations = [
            'constructor retains a bound view' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    "    ) {\n        \$this->snapshot",
                    "    ) {\n        new TenantBoundOperationStatusSource(\$source, \$currentTenant);\n        \$this->snapshot",
                    $query,
                ),
            ],
            'bound view loses request tenant' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    'new TenantBoundOperationStatusSource($source, $currentTenant)',
                    'new TenantBoundOperationStatusSource($source, null)',
                    $query,
                ),
            ],
            'default query receives unscoped source' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    'new DefaultOperationStatusQuery($boundSource, $authorizer)',
                    'new DefaultOperationStatusQuery($source, $authorizer)',
                    $query,
                ),
            ],
            'requested audit is duplicated' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '$session = $sessions->begin(AuditReadPurpose::Status, $operationId, $currentActor, $currentTenant);',
                    "\$session = \$sessions->begin(AuditReadPurpose::Status, \$operationId, \$currentActor, \$currentTenant);\n"
                    . "        \$sessions->begin(AuditReadPurpose::Status, \$operationId, \$currentActor, \$currentTenant);",
                    $query,
                ),
            ],
            'begin body gains an unrelated assignment' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '            $session = $sessions->begin(AuditReadPurpose::Status, $operationId, $currentActor, $currentTenant);',
                    "            \$currentTenant = null;\n            \$session = \$sessions->begin(AuditReadPurpose::Status, \$operationId, \$currentActor, \$currentTenant);",
                    $query,
                ),
            ],
            'begin try gains an unrelated finally' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    "        } catch (Throwable) {\n            throw OperationStatusQueryException::storageFailed();\n        }\n        if (!\$session instanceof OperationReadAuditSession)",
                    "        } catch (Throwable) {\n            throw OperationStatusQueryException::storageFailed();\n        } finally {\n            \$currentTenant = null;\n        }\n\n        if (!\$session instanceof OperationReadAuditSession)",
                    $query,
                ),
            ],
            'terminal fallback is duplicated' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '                $session->unavailable();',
                    "                \$session->unavailable();\n                \$session->unavailable();",
                    $query,
                ),
            ],
            'unknown result is not integrity mapped' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '            $failure = OperationStatusQueryException::integrityFailed();',
                    '            $failure = OperationStatusQueryException::storageFailed();',
                    $query,
                ),
            ],
            'query exception identity is replaced' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '            $failure = $exception;',
                    '            $failure = OperationStatusQueryException::storageFailed();',
                    $query,
                ),
            ],
            'catch all does not create fresh storage' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '            $failure = OperationStatusQueryException::storageFailed();',
                    '            $failure = OperationStatusQueryException::integrityFailed();',
                    $query,
                ),
            ],
            'released query exception is not the same instance' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '            throw $failure;',
                    '            throw OperationStatusQueryException::storageFailed();',
                    $query,
                ),
            ],
            'delegate catch order is changed' => [
                ...$sources,
                $queryPath => $this->replaceOnce($delegateCatches, $reorderedDelegateCatches, $query),
            ],
            'delegate try gains an unrelated finally' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    "        } catch (Throwable) {\n            \$failure = OperationStatusQueryException::storageFailed();\n        }\n\n        \$this->assertIdle(\$connection);",
                    "        } catch (Throwable) {\n            \$failure = OperationStatusQueryException::storageFailed();\n        } finally {\n            \$currentTenant = null;\n        }\n\n        \$this->assertIdle(\$connection);",
                    $query,
                ),
            ],
            'begin and first idle guard are reordered' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '            $session = $sessions->begin(AuditReadPurpose::Status, $operationId, $currentActor, $currentTenant);',
                    "        \$this->assertIdle(\$connection);",
                    $this->replaceNth(
                        '        $this->assertIdle($connection);',
                        1,
                        "        \$sessions->begin(AuditReadPurpose::Status, \$operationId, \$currentActor, \$currentTenant);",
                        $query,
                    ),
                ),
            ],
            'delegate and post-read idle guard are reordered' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '            $candidate = $query->findForTenant($operationId, $currentActor, $currentTenant);',
                    '            $this->assertIdle($connection);',
                    $this->replaceNth(
                        '        $this->assertIdle($connection);',
                        4,
                        '        $query->findForTenant($operationId, $currentActor, $currentTenant);',
                        $query,
                    ),
                ),
            ],
            'early release is moved before terminal' => [
                ...$sources,
                $queryPath => $this->moveOnce(
                    "        \$this->assertIdle(\$connection);\n\n        if (\$failure instanceof OperationStatusQueryException)",
                    "        return \$result;\n\n        \$this->assertIdle(\$connection);\n\n        if (\$failure instanceof OperationStatusQueryException)",
                    '        return $result;',
                    '        $result;',
                    $query,
                ),
            ],
            'terminal failure retries unavailable' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    "                \$session->unavailable();\n            }\n        } catch (Throwable) {",
                    "            }\n        } catch (Throwable) {\n            \$session->unavailable();",
                    $query,
                ),
            ],
            'terminal try is conditionally wrapped' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    $terminalTry,
                    "        if (false) {\n" . $terminalTry . "\n        }",
                    $query,
                ),
            ],
            'terminal try gains an unrelated finally' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    "        } catch (Throwable) {\n            throw OperationStatusQueryException::storageFailed();\n        }\n\n        \$this->assertIdle(\$connection);\n\n        if (\$failure instanceof OperationStatusQueryException)",
                    "        } catch (Throwable) {\n            throw OperationStatusQueryException::storageFailed();\n        } finally {\n            \$result = clone \$result;\n        }\n\n        \$this->assertIdle(\$connection);\n\n        if (\$failure instanceof OperationStatusQueryException)",
                    $query,
                ),
            ],
            'result is cloned before return' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '        return $result;',
                    "        \$result = clone \$result;\n        return \$result;",
                    $query,
                ),
            ],
            'terminal dispatch gains an uppercase duplicate' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '                $session->unavailable();',
                    "                \$session->unavailable();\n                \$session->ACCESSED();",
                    $query,
                ),
            ],
            'terminal dispatch gains a nullsafe duplicate' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '                $session->unavailable();',
                    "                \$session->unavailable();\n                \$session?->unavailable();",
                    $query,
                ),
            ],
            'query opens audit session directly' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    "        \$this->assertIdle(\$connection);\n\n        \$result = null;",
                    "        \$this->assertIdle(\$connection);\n        \$session->open();\n\n        \$result = null;",
                    $query,
                ),
            ],
            'tenant guard is bypassed' => [
                ...$sources,
                $authorizerPath => $this->replaceOnce(
                    'if (!$this->sameTenant($request->currentTenant(), $request->originTenant()))',
                    'if (true || !$this->sameTenant($request->currentTenant(), $request->originTenant()))',
                    $authorizer,
                ),
            ],
            'authorizer delegates before the guard result' => [
                ...$sources,
                $authorizerPath => $this->replaceOnce(
                    'return $authorizer->decide($request);',
                    "\$authorizer->decide(\$request);\n\n        return OperationStatusAuthorizationDecision::deny();",
                    $authorizer,
                ),
            ],
            'tenant comparison loses strict identity' => [
                ...$sources,
                $authorizerPath => $this->replaceOnce(
                    '$current->type() === $origin->type()',
                    '$current->type() == $origin->type()',
                    $authorizer,
                ),
            ],
            'tenant comparison changes conjunction' => [
                ...$sources,
                $authorizerPath => $this->replaceOnce(
                    '$current->type() === $origin->type() && $current->id() === $origin->id()',
                    '$current->type() === $origin->type() || $current->id() === $origin->id()',
                    $authorizer,
                ),
            ],
            'tenant null branch is weakened' => [
                ...$sources,
                $authorizerPath => $this->replaceOnce(
                    'return $current === null && $origin === null;',
                    'return $current === null || $origin === null;',
                    $authorizer,
                ),
            ],
            'tenant null guard gains an elseif' => [
                ...$sources,
                $authorizerPath => $this->replaceOnce(
                    "        if (\$current === null || \$origin === null) {\n            return \$current === null && \$origin === null;\n        }",
                    "        if (\$current === null || \$origin === null) {\n            return \$current === null && \$origin === null;\n        } elseif (true) {\n            return true;\n        }",
                    $authorizer,
                ),
            ],
            'same tenant members are reversed' => [
                ...$sources,
                $authorizerPath => $this->replaceOnce(
                    '$current->type() === $origin->type() && $current->id() === $origin->id()',
                    '$current->id() === $origin->id() && $current->type() === $origin->type()',
                    $authorizer,
                ),
            ],
            'execution type is changed' => [
                ...$sources,
                $executionPath => $this->replaceOnce(
                    "new AuditTypedReference('operation_status_read', \$identifiers->generate())",
                    "new AuditTypedReference('operation_status_read_other', \$identifiers->generate())",
                    $execution,
                ),
            ],
            'execution generator is not validated' => [
                ...$sources,
                $executionPath => $this->replaceOnce(
                    "'identifiers' => new ValidatedUuidv7Generator(\$identifiers)",
                    "'identifiers' => \$identifiers",
                    $execution,
                ),
            ],
            'execution source gains a public callable' => [
                ...$sources,
                $executionPath => $this->insertBeforeFinalBrace(
                    $execution,
                    "\n    public function unexpected(): void {}\n",
                ),
            ],
            'query gains retained mutable state' => [
                ...$sources,
                $queryPath => $this->replaceOnce(
                    '    private \\SensitiveParameterValue $snapshot;',
                    "    private \\SensitiveParameterValue \$snapshot;\n    private string \$unexpected;",
                    $query,
                ),
            ],
        ];

        foreach ($mutations as $label => $candidate) {
            $this->assertSyntaxValid($candidate, $label);
            self::assertNotSame([], $this->architectureViolations($candidate), $label . ' was accepted');
        }

        $outside = $this->productionSources();
        $outside['src/External/UnexpectedStatusCaller.php'] = <<<'PHP'
            <?php

            namespace BlackOps\External;

            final class UnexpectedStatusCaller
            {
                public function construct(): void
                {
                    new \BlackOps\Internal\Status\AuditedOperationStatusQuery($a, $b, $c, $d);
                }
            }
            PHP;
        $this->assertSyntaxValid($outside, 'outside audited query caller');
        self::assertNotEmpty(
            $this->productionConstructionViolations($outside),
            'an external audited query constructor caller was accepted',
        );

        $wrongSessionClass = $sources;
        $wrongSessionClass[$queryPath] = $this->replaceNth(
            '        $this->assertIdle($connection);',
            1,
            "        \\App\\OperationReadAuditSession::OPEN();\n        \$this->assertIdle(\$connection);",
            $query,
        );
        self::assertSame(
            [],
            $this->forbiddenCalls($wrongSessionClass),
            'a same-basename non-session class was rejected',
        );

        $wrongCaseSessionClass = $sources;
        $wrongCaseSessionClass[$queryPath] = $this->replaceNth(
            '        $this->assertIdle($connection);',
            1,
            "        \\BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession::OPEN();\n        \$this->assertIdle(\$connection);",
            $query,
        );
        self::assertNotEmpty($this->forbiddenCalls($wrongCaseSessionClass), 'a case-variant session open was accepted');

        $lowercaseTarget = $this->productionSources();
        $lowercaseTarget['src/External/LowercaseStatusCaller.php'] = <<<'PHP'
            <?php

            namespace BlackOps\External;

            final class LowercaseStatusCaller
            {
                public function construct(): void
                {
                    new \blackops\internal\status\auditedoperationstatusquery($a, $b, $c, $d);
                }
            }
            PHP;
        $this->assertSyntaxValid($lowercaseTarget, 'lowercase target caller');
        self::assertNotEmpty(
            $this->productionConstructionViolations($lowercaseTarget),
            'a case-insensitive audited query constructor caller was accepted',
        );

        $classStringTarget = $this->productionSources();
        $classStringTarget['src/External/StatusReference.php'] = <<<'PHP'
            <?php

            namespace BlackOps\External;

            final class StatusReference
            {
                public function inspect(): void
                {
                    $class = \BlackOps\Internal\Status\AuditedOperationStatusQuery::class;
                    new \ReflectionClass($class);
                    call_user_func([$class, 'find']);
                    new $class($a, $b, $c, $d);
                }
            }
            PHP;
        $this->assertSyntaxValid($classStringTarget, 'class-string target consumer');
        self::assertNotEmpty(
            $this->productionConstructionViolations($classStringTarget),
            'a target class-string consumer was accepted',
        );

        $unrelated = $this->productionSources();
        $unrelated['src/External/UnrelatedStatusCaller.php'] = <<<'PHP'
            <?php

            namespace Vendor\Status;

            final class UnrelatedStatusCaller
            {
                public function construct(): void
                {
                    new \Vendor\AuditedOperationStatusQuery();
                }
            }
            PHP;
        $this->assertSyntaxValid($unrelated, 'unrelated basename caller');
        self::assertSame([], $this->productionConstructionViolations($unrelated));
    }

    /** @param list<string> $interfaces @param array<string,string> $methods */
    private function assertRuntimeDeclaration(ReflectionClass $reflection, array $interfaces, array $methods): void
    {
        self::assertTrue($reflection->isFinal(), $reflection->getName());
        self::assertTrue($reflection->isReadOnly(), $reflection->getName());
        self::assertFalse($reflection->getParentClass(), $reflection->getName());
        foreach ($interfaces as $interface) {
            self::assertTrue($reflection->implementsInterface($interface), $reflection->getName());
        }
        self::assertSame(array_keys($methods), $this->declaredMethodNames($reflection));
        $properties = $reflection->getProperties();
        self::assertCount(1, $properties, $reflection->getName());
        $property = $properties[0];
        self::assertSame('snapshot', $property->getName(), $reflection->getName());
        self::assertTrue($property->isPrivate(), $reflection->getName());
        self::assertFalse($property->isStatic(), $reflection->getName());
        self::assertFalse($property->isPromoted(), $reflection->getName());
        self::assertSame('SensitiveParameterValue', $property->getType()?->getName(), $reflection->getName());

        foreach ($methods as $name => $visibility) {
            $method = $reflection->getMethod($name);
            self::assertSame($visibility, $this->visibility($method), $reflection->getName() . '::' . $name);
        }
    }

    /** @param list<array{string,string,bool,bool}> $parameters */
    private function assertMethodContract(ReflectionMethod $method, ?string $returnType, array $parameters): void
    {
        self::assertSame($returnType, $this->reflectionTypeName($method->getReturnType()), $method->getName());
        $actual = $method->getParameters();
        self::assertCount(count($parameters), $actual, $method->getName());
        foreach ($parameters as $index => [$name, $type, $sensitive, $optional]) {
            $parameter = $actual[$index];
            self::assertSame($name, $parameter->getName(), $method->getName());
            self::assertSame($type, $this->reflectionTypeName($parameter->getType()), $method->getName());
            self::assertSame($optional, $parameter->isDefaultValueAvailable(), $method->getName());
            if ($optional) {
                self::assertNull($parameter->getDefaultValue(), $method->getName());
            }
            self::assertSame(
                $sensitive ? 1 : 0,
                count($parameter->getAttributes(\SensitiveParameter::class)),
                $method->getName() . '::' . $name,
            );
        }
    }

    private function reflectionTypeName(?ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if (!$type instanceof \ReflectionNamedType) {
            return (string) $type;
        }

        return (
            ($type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null' ? '?' : '')
            . basename(str_replace('\\', '/', $type->getName()))
        );
    }

    /** @return list<string> */
    private function declaredMethodNames(ReflectionClass $reflection): array
    {
        return array_values(array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            array_filter(
                $reflection->getMethods(),
                static fn(ReflectionMethod $method): bool => $method
                    ->getDeclaringClass()
                    ->getName() === $reflection->getName(),
            ),
        ));
    }

    private function visibility(ReflectionMethod $method): string
    {
        return $method->isPublic() ? 'public' : ($method->isProtected() ? 'protected' : 'private');
    }

    /** @return array<string,string> */
    private function adapterSources(): array
    {
        $paths = [
            'src/Internal/Status/AuditedOperationStatusQuery.php',
            'src/Internal/Status/OperationStatusAuditTenantAuthorizer.php',
            'src/Internal/Status/OperationStatusReadExecutionSource.php',
        ];
        $sources = [];
        foreach ($paths as $path) {
            $source = file_get_contents(__DIR__ . '/../../' . $path);
            self::assertIsString($source, $path);
            $sources[$path] = $source;
        }

        return $sources;
    }

    /** @return array<string,string> */
    private function productionSources(): array
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertIsString($root);
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source, $file->getPathname());
            $sources['src/' . str_replace($root . '/', '', $file->getPathname())] = $source;
        }

        return $sources;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function architectureViolations(array $sources): array
    {
        $expected = [
            'src/Internal/Status/AuditedOperationStatusQuery.php' => [
                'class' => 'AuditedOperationStatusQuery',
                'interface' => 'TenantAwareOperationStatusQuery',
                'imports' => [
                    'BlackOps\\Core\\ActorRef',
                    'BlackOps\\Core\\Audit\\AuditReadPurpose',
                    'BlackOps\\Core\\Identifier\\OperationId',
                    'BlackOps\\Core\\TenantRef',
                    'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
                    'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSessionFactory',
                    'BlackOps\\Status\\Exception\\OperationStatusQueryException',
                    'BlackOps\\Status\\OperationStatusAuthorizer',
                    'BlackOps\\Status\\OperationStatusExpired',
                    'BlackOps\\Status\\OperationStatusFound',
                    'BlackOps\\Status\\OperationStatusResult',
                    'BlackOps\\Status\\OperationStatusUnavailable',
                    'BlackOps\\Status\\TenantAwareOperationStatusQuery',
                    'Doctrine\\DBAL\\Connection',
                    'Throwable',
                ],
                'methods' => [
                    '__construct' => 'public',
                    'find' => 'public',
                    'findForTenant' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'assertIdle' => 'private',
                    'value' => 'private',
                ],
            ],
            'src/Internal/Status/OperationStatusAuditTenantAuthorizer.php' => [
                'class' => 'OperationStatusAuditTenantAuthorizer',
                'interface' => 'OperationStatusAuthorizer',
                'imports' => [
                    'BlackOps\\Core\\TenantRef',
                    'BlackOps\\Status\\Exception\\OperationStatusQueryException',
                    'BlackOps\\Status\\OperationStatusAuthorizationDecision',
                    'BlackOps\\Status\\OperationStatusAuthorizationRequest',
                    'BlackOps\\Status\\OperationStatusAuthorizer',
                ],
                'methods' => [
                    '__construct' => 'public',
                    'decide' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'sameTenant' => 'private',
                    'value' => 'private',
                ],
            ],
            'src/Internal/Status/OperationStatusReadExecutionSource.php' => [
                'class' => 'OperationStatusReadExecutionSource',
                'interface' => 'OperationReadExecutionSource',
                'imports' => [
                    'BlackOps\\Core\\Audit\\AuditTypedReference',
                    'BlackOps\\Identifier\\Uuidv7Generator',
                    'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryException',
                    'BlackOps\\Internal\\Audit\\Orchestration\\AuditDeliveryFailure',
                    'BlackOps\\Internal\\Audit\\Read\\OperationReadExecutionSource',
                    'BlackOps\\Internal\\Identifier\\ValidatedUuidv7Generator',
                ],
                'methods' => [
                    '__construct' => 'public',
                    'current' => 'public',
                    '__debugInfo' => 'public',
                    '__serialize' => 'public',
                    '__unserialize' => 'public',
                    'value' => 'private',
                ],
            ],
        ];
        $violations = [];
        $expectedPaths = array_keys($expected);
        $actualPaths = array_keys($sources);
        sort($expectedPaths);
        sort($actualPaths);
        if ($actualPaths !== $expectedPaths) {
            $violations[] = 'source-path-inventory';
        }
        foreach ($expected as $path => $contract) {
            $source = $sources[$path] ?? null;
            if (!is_string($source)) {
                $violations[] = 'missing:' . $path;
                continue;
            }
            $class = $this->classNode($source, $contract['class']);
            if ($class === null || count($this->classNodes($source)) !== 1) {
                $violations[] = 'class:' . $path;
                continue;
            }
            if (!$class->isFinal() || !$class->isReadonly() || $class->extends !== null) {
                $violations[] = 'declaration:' . $path;
            }
            if (
                count($class->implements) !== 1
                || $this->terminalName($class->implements[0] ?? null) !== $contract['interface']
            ) {
                $violations[] = 'interface:' . $path;
            }
            if ($this->hasPublicApiAttribute($class) || $this->hasTrait($class)) {
                $violations[] = 'attributes-or-trait:' . $path;
            }
            if ($this->methodInventory($class) !== $contract['methods']) {
                $violations[] = 'methods:' . $path;
            }
            foreach ($this->sourceMethodViolations($class, $path) as $finding) {
                $violations[] = $finding;
            }
            if ($this->imports($source) !== $contract['imports']) {
                $violations[] = 'imports:' . $path;
            }
            if (!$this->propertyContract($class)) {
                $violations[] = 'property:' . $path;
            }
            foreach ($this->sensitiveViolations($class, $path) as $finding) {
                $violations[] = $finding;
            }
        }
        $violations = [...$violations, ...$this->protocolViolations($sources)];

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function sourceMethodViolations(Class_ $class, string $path): array
    {
        $expected = match ($path) {
            'src/Internal/Status/AuditedOperationStatusQuery.php' => [
                '__construct' => [
                    null,
                    [
                        ['source',     'TenantScopedOperationStatusSource', true, false],
                        ['authorizer', 'OperationStatusAuthorizer',         true, false],
                        ['sessions',   'OperationReadAuditSessionFactory',  true, false],
                        ['connection', 'Connection',                        true, false],
                    ],
                ],
                'find' => [
                    'OperationStatusResult',
                    [
                        ['operationId',   'OperationId', true, false],
                        ['currentActor',  '?ActorRef',   true, true],
                        ['currentTenant', '?TenantRef',  true, true],
                    ],
                ],
                'findForTenant' => [
                    'OperationStatusResult',
                    [
                        ['operationId',   'OperationId', true, false],
                        ['currentActor',  '?ActorRef',   true, true],
                        ['currentTenant', '?TenantRef',  true, true],
                    ],
                ],
                '__debugInfo' => ['array', []],
                '__serialize' => ['array', []],
                '__unserialize' => ['void', [['data', 'array', true, false]]],
                'assertIdle' => ['void', [['connection', 'Connection', true, false]]],
                'value' => ['mixed', [['key', 'string', true, false]]],
            ],
            'src/Internal/Status/OperationStatusAuditTenantAuthorizer.php' => [
                '__construct' => [null, [['authorizer', 'OperationStatusAuthorizer', true, false]]],
                'decide' => [
                    'OperationStatusAuthorizationDecision',
                    [['request', 'OperationStatusAuthorizationRequest', true, false]],
                ],
                '__debugInfo' => ['array', []],
                '__serialize' => ['array', []],
                '__unserialize' => ['void', [['data', 'array', true, false]]],
                'sameTenant' => [
                    'bool',
                    [
                        ['current', '?TenantRef', true, false],
                        ['origin',  '?TenantRef', true, false],
                    ],
                ],
                'value' => ['mixed', [['key', 'string', true, false]]],
            ],
            default => [
                '__construct' => [null, [['identifiers', 'Uuidv7Generator', true, false]]],
                'current' => ['AuditTypedReference', []],
                '__debugInfo' => ['array', []],
                '__serialize' => ['array', []],
                '__unserialize' => ['void', [['data', 'array', true, false]]],
                'value' => ['mixed', [['key', 'string', true, false]]],
            ],
        };
        $violations = [];
        foreach ($expected as $name => [$return, $parameters]) {
            $method = $this->methodNodeFromClass($class, $name);
            if ($method === null) {
                $violations[] = 'missing-method:' . $path . ':' . $name;
                continue;
            }
            if ($this->typeLabel($method->returnType) !== $return) {
                $violations[] = 'return-type:' . $path . ':' . $name;
            }
            if (count($method->params) !== count($parameters)) {
                $violations[] = 'parameters:' . $path . ':' . $name;
                continue;
            }
            foreach ($parameters as $index => [$parameterName, $type, $sensitive, $optional]) {
                $parameter = $method->params[$index];
                if (
                    $parameter->var->name !== $parameterName
                    || $this->typeLabel($parameter->type) !== $type
                    || ($parameter->flags ?? 0) !== 0
                    || ($parameter->default !== null) !== $optional
                    || $optional && !$this->isNullDefault($parameter->default)
                    || $this->sensitiveAttributeCount($parameter) !== ($sensitive ? 1 : 0)
                ) {
                    $violations[] = 'parameter-contract:' . $path . ':' . $name . ':' . $parameterName;
                }
            }
        }

        return $violations;
    }

    private function typeLabel(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof NullableType) {
            $inner = $this->typeLabel($type->type);

            return $inner === null ? null : '?' . $inner;
        }
        if ($type instanceof Name || $type instanceof Identifier) {
            return $this->terminalName($type);
        }

        return null;
    }

    private function isNullDefault(?Node $default): bool
    {
        return $default instanceof Node\Expr\ConstFetch && $this->terminalName($default->name) === 'null';
    }

    /** @param array<string,string> $sources @return list<string> */
    private function protocolViolations(array $sources): array
    {
        $queryPath = 'src/Internal/Status/AuditedOperationStatusQuery.php';
        $authorizerPath = 'src/Internal/Status/OperationStatusAuditTenantAuthorizer.php';
        $executionPath = 'src/Internal/Status/OperationStatusReadExecutionSource.php';
        $query = $sources[$queryPath] ?? '';
        $authorizer = $sources[$authorizerPath] ?? '';
        $execution = $sources[$executionPath] ?? '';
        $violations = [];
        $constructor = $this->methodNode($query, '__construct');
        $find = $this->methodNode($query, 'find');
        $findForTenant = $this->methodNode($query, 'findForTenant');
        if ($constructor === null || $find === null || $findForTenant === null) {
            return ['query-methods'];
        }
        if (
            $this->newTypeInventory($constructor) !== [
                'OperationStatusAuditTenantAuthorizer',
                'SensitiveParameterValue',
            ]
        ) {
            $violations[] = 'query-constructor-allocation';
        }
        if (
            $this->countCalls($this->methodCalls($constructor), '$this', 'value', []) !== 0
            || $this->countCalls($this->methodCalls($constructor), '$this', 'assertIdle', []) !== 0
            || $this->methodCalls($constructor) !== []
        ) {
            $violations[] = 'query-constructor-I/O';
        }
        if (
            $this->newTypeInventory($findForTenant) !== [
                'DefaultOperationStatusQuery',
                'TenantBoundOperationStatusSource',
            ]
            || $this->countNew($findForTenant, 'TenantBoundOperationStatusSource', [
                ['variable', 'source'],
                ['variable', 'currentTenant'],
            ]) !== 1
            || $this->countNew($findForTenant, 'DefaultOperationStatusQuery', [
                ['variable', 'boundSource'],
                ['variable', 'authorizer'],
            ]) !== 1
        ) {
            $violations[] = 'query-per-call-binding';
        }
        if ($this->countCalls($this->methodCalls($find), '$this', 'findForTenant', [
            ['variable', 'operationId'],
            ['variable', 'currentActor'],
            ['variable', 'currentTenant'],
        ]) !== 1 || count($this->methodCalls($find)) !== 1) {
            $violations[] = 'query-find-delegation';
        }
        $bodyCalls = $this->methodCalls($findForTenant);
        if ($this->countCalls($bodyCalls, '$this', 'value', [[
            'string',
            'connection',
        ]]) !== 1 || $this->countCalls($bodyCalls, '$this', 'value', [[
            'string',
            'sessions',
        ]]) !== 1 || $this->countCalls($bodyCalls, '$this', 'value', [[
            'string',
            'source',
        ]]) !== 1 || $this->countCalls($bodyCalls, '$this', 'value', [[
            'string',
            'authorizer',
        ]]) !== 1 || $this->countCalls($bodyCalls, '$this', 'assertIdle', [[
            'variable',
            'connection',
        ]]) !== 4 || $this->countCalls($bodyCalls, '$sessions', 'begin', [
            ['class-constant', 'AuditReadPurpose', 'Status'],
            ['variable', 'operationId'],
            ['variable', 'currentActor'],
            ['variable', 'currentTenant'],
        ]) !== 1 || $this->countCalls($bodyCalls, '$query', 'findForTenant', [
            ['variable', 'operationId'],
            ['variable', 'currentActor'],
            ['variable', 'currentTenant'],
        ]) !== 1 || $this->countCalls($bodyCalls, '$session', 'accessed', []) !== 1 || $this->countCalls($bodyCalls, '$session', 'unavailable', []) !== 1 || !$this->beginTryCatchIsExact($findForTenant) || !$this->queryDelegateCatchIsExact($findForTenant) || !$this->queryResultFailureFlowIsExact($findForTenant) || !$this->terminalCatchIsExact($findForTenant) || !$this->failureReleaseIsExact($findForTenant) || !$this->protocolSequenceIsExact($findForTenant) || !$this->terminalMappingIsExact($findForTenant)) {
            $violations[] = 'query-read-terminal-flow';
        }
        if (
            $this->countClassConstantFetches($findForTenant, 'AuditReadPurpose', 'Status') !== 1
            || $this->countInstanceof($findForTenant, 'OperationStatusFound') !== 2
            || $this->countInstanceof($findForTenant, 'OperationStatusExpired') !== 2
            || $this->countInstanceof($findForTenant, 'OperationStatusUnavailable') !== 1
        ) {
            $violations[] = 'query-result-matrix';
        }
        if ($this->forbiddenCalls([$queryPath => $query]) !== []) {
            $violations[] = 'query-direct-audit-or-transaction-call';
        }

        $decide = $this->methodNode($authorizer, 'decide');
        $sameTenant = $this->methodNode($authorizer, 'sameTenant');
        if ($decide === null || $sameTenant === null) {
            return [...$violations, 'authorizer-methods'];
        }
        $decideCalls = $this->methodCalls($decide);
        if ($this->countCalls($decideCalls, '$this', 'sameTenant', [
            ['method-call', '$request', 'currentTenant'],
            ['method-call', '$request', 'originTenant'],
        ]) !== 1 || $this->countCalls($decideCalls, '$this', 'value', [[
            'string',
            'authorizer',
        ]]) !== 1 || $this->countCalls($decideCalls, '$authorizer', 'decide', [[
            'variable',
            'request',
        ]]) !== 1 || !$this->firstStatementIsTenantDeny($decide) || !$this->lastStatementDelegatesAuthorizer($decide) || $this->newTypeInventory($decide) !== [] || $this->newTypeInventory($sameTenant) !== []) {
            $violations[] = 'authorizer-guard-order';
        }
        $sameCalls = $this->methodCalls($sameTenant);
        if (
            $this->countCalls($sameCalls, '$current', 'type', []) !== 1
            || $this->countCalls($sameCalls, '$current', 'id', []) !== 1
            || $this->countCalls($sameCalls, '$origin', 'type', []) !== 1
            || $this->countCalls($sameCalls, '$origin', 'id', []) !== 1
            || !$this->sameTenantComparisonIsExact($sameTenant)
        ) {
            $violations[] = 'authorizer-strict-tenant-comparison';
        }

        $executionConstruct = $this->methodNode($execution, '__construct');
        $current = $this->methodNode($execution, 'current');
        $executionValue = $this->methodNode($execution, 'value');
        if ($executionConstruct === null || $current === null || $executionValue === null) {
            return [...$violations, 'execution-methods'];
        }
        if (
            $this->newTypeInventory($executionConstruct) !== ['SensitiveParameterValue', 'ValidatedUuidv7Generator']
            || $this->countNew($executionConstruct, 'ValidatedUuidv7Generator', [['variable', 'identifiers']]) !== 1
            || $this->methodCalls($executionConstruct) !== []
            || $this->countCalls($this->methodCalls($current), '$identifiers', 'generate', []) !== 1
            || $this->countNew($current, 'AuditTypedReference', [
                ['string', 'operation_status_read'],
                ['method-call', '$identifiers', 'generate'],
            ]) !== 1
            || $this->newTypeInventory($current) !== ['AuditDeliveryException', 'AuditTypedReference', 'LogicException']
            || $this->countCalls($this->methodCalls($executionValue), '$this', 'value', []) !== 0
        ) {
            $violations[] = 'execution-identity-flow';
        }
        if ($this->forbiddenCalls([$executionPath => $execution]) !== []) {
            $violations[] = 'execution-forbidden-call';
        }
        $queryValue = $this->methodNode($query, 'value');
        $executionValue = $this->methodNode($execution, 'value');
        if (
            $queryValue === null
            || $this->countInstanceof($queryValue, 'OperationStatusAuditTenantAuthorizer') !== 1
            || $executionValue === null
            || $this->countInstanceof($executionValue, 'ValidatedUuidv7Generator') !== 1
        ) {
            $violations[] = 'concrete-sensitive-dependencies';
        }

        return $violations;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function forbiddenCalls(array $sources): array
    {
        $forbidden = ['beginTransaction', 'commit', 'rollBack', 'open', 'terminal', 'deliverStandalone'];
        $violations = [];
        foreach ($sources as $path => $source) {
            foreach ($this->methodCallsInSource($source) as $call) {
                $name = $this->methodName($call);
                if ($name !== null && in_array(strtolower($name), array_map('strtolower', $forbidden), true)) {
                    $violations[] = $path . ':' . $name;
                }
            }
            foreach (new NodeFinder()->findInstanceOf($this->ast($source), StaticCall::class) as $call) {
                if (
                    $call->name instanceof Identifier
                    && strcasecmp($call->name->toString(), 'open') === 0
                    && $this->sameResolvedName(
                        $this->resolvedName($call->class),
                        'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
                    )
                ) {
                    $violations[] = $path . ':OperationReadAuditSession::open';
                }
            }
            foreach (new NodeFinder()->findInstanceOf($this->ast($source), FuncCall::class) as $call) {
                if (
                    $call->name instanceof Name
                    && in_array(
                        $this->terminalName($call->name),
                        [
                            'checkdnsrr',
                            'curl_exec',
                            'curl_init',
                            'dns_get_record',
                            'file_get_contents',
                            'fopen',
                            'fsockopen',
                            'getenv',
                            'gethostbyname',
                            'opendir',
                            'random_bytes',
                            'random_int',
                            'stream_socket_client',
                            'uniqid',
                            'uuid_create',
                        ],
                        true,
                    )
                ) {
                    $violations[] = $path . ':forbidden-global:' . $this->terminalName($call->name);
                }
            }
        }

        return $violations;
    }

    /** @param array<string,string> $sources @return list<string> */
    private function productionConstructionViolations(array $sources): array
    {
        $targets = [
            'BlackOps\Internal\Status\AuditedOperationStatusQuery',
            'BlackOps\Internal\Status\OperationStatusAuditTenantAuthorizer',
            'BlackOps\Internal\Status\OperationStatusReadExecutionSource',
            'BlackOps\Internal\Status\TenantBoundOperationStatusSource',
        ];
        $actual = [];
        foreach ($sources as $path => $source) {
            foreach ($this->newNodes($source) as $new) {
                $name = $this->targetClassReference($new->class, $targets);
                if ($name !== null) {
                    $actual[] = [$path, $name, 'new'];
                }
            }
            foreach (new NodeFinder()->findInstanceOf($this->ast($source), ClassConstFetch::class) as $fetch) {
                $name = $this->targetClassReference($fetch->class, $targets);
                if ($name !== null) {
                    $actual[] = [$path, $name, 'class-string'];
                }
            }
        }
        $expected = [
            [
                'src/Internal/Status/AuditedOperationStatusQuery.php',
                'BlackOps\Internal\Status\OperationStatusAuditTenantAuthorizer',
                'new',
            ],
            [
                'src/Internal/Status/AuditedOperationStatusQuery.php',
                'BlackOps\Internal\Status\TenantBoundOperationStatusSource',
                'new',
            ],
        ];
        sort($actual);
        sort($expected);
        if ($actual === $expected) {
            return [];
        }

        return ['production-constructor-inventory'];
    }

    /** @param list<string> $targets */
    private function targetClassReference(?Node $class, array $targets): ?string
    {
        $name = $class instanceof ClassConstFetch
            ? (
                $class->name instanceof Identifier && strcasecmp($class->name->toString(), 'class') === 0
                    ? $this->resolvedName($class->class)
                    : null
            )
            : $this->resolvedName($class);
        if ($name === null) {
            return null;
        }
        foreach ($targets as $target) {
            if ($this->sameResolvedName($name, $target)) {
                return $target;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function sensitiveViolations(Class_ $class, string $path): array
    {
        $expected = match ($path) {
            'src/Internal/Status/AuditedOperationStatusQuery.php' => [
                '__construct' => [1, 1, 1, 1],
                'find' => [1, 1, 1],
                'findForTenant' => [1, 1, 1],
                '__unserialize' => [1],
                'assertIdle' => [1],
                'value' => [1],
            ],
            'src/Internal/Status/OperationStatusAuditTenantAuthorizer.php' => [
                '__construct' => [1],
                'decide' => [1],
                '__unserialize' => [1],
                'sameTenant' => [1, 1],
                'value' => [1],
            ],
            default => [
                '__construct' => [1],
                '__unserialize' => [1],
                'value' => [1],
            ],
        };
        $violations = [];
        foreach ($expected as $name => $attributes) {
            $method = $this->methodNodeFromClass($class, $name);
            if ($method === null) {
                $violations[] = 'missing-method:' . $path . ':' . $name;
                continue;
            }
            $actual = array_map(fn(Param $parameter): int => $this->sensitiveAttributeCount(
                $parameter,
            ), $method->params);
            if ($actual !== $attributes) {
                $violations[] = 'sensitive-parameters:' . $path . ':' . $name;
            }
        }

        return $violations;
    }

    private function propertyContract(Class_ $class): bool
    {
        $properties = array_values(array_filter(
            $class->stmts,
            static fn(Node $node): bool => $node instanceof Property,
        ));
        if (
            count($properties) !== 1
            || count($properties[0]->props) !== 1
            || array_values(array_filter(
                $class->stmts,
                static fn(Node $node): bool => $node instanceof Node\Stmt\ClassConst,
            )) !== []
        ) {
            return false;
        }
        $property = $properties[0];

        return (
            $property->isPrivate()
            && !$property->isStatic()
            && $this->terminalName($property->type) === 'SensitiveParameterValue'
            && $property->props[0]->name->toString() === 'snapshot'
        );
    }

    private function hasPublicApiAttribute(Class_ $class): bool
    {
        foreach (new NodeFinder()->findInstanceOf($class, Node\Attribute::class) as $attribute) {
            if ($this->terminalName($attribute->name) === 'PublicApi') {
                return true;
            }
        }

        return false;
    }

    private function hasTrait(Class_ $class): bool
    {
        return (
            array_values(array_filter($class->stmts, static fn(Node $node): bool => $node instanceof TraitUse)) !== []
        );
    }

    /** @return array<string,string> */
    private function methodInventory(Class_ $class): array
    {
        $inventory = [];
        foreach ($class->stmts as $statement) {
            if (!$statement instanceof ClassMethod) {
                continue;
            }
            $inventory[$statement->name->toString()] = $statement->isPublic()
                ? 'public'
                : ($statement->isProtected() ? 'protected' : 'private');
        }

        return $inventory;
    }

    /** @return list<string> */
    private function imports(string $source): array
    {
        $imports = [];
        foreach (new NodeFinder()->findInstanceOf($this->ast($source), Node\Stmt\UseUse::class) as $use) {
            $name = $use->name->toString();
            if ($use->alias !== null) {
                $name .= ' as ' . $use->alias->toString();
            }
            $imports[] = $name;
        }

        return $imports;
    }

    private function classNode(string $source, string $name): ?Class_
    {
        foreach ($this->classNodes($source) as $class) {
            if ($class->name?->toString() === $name) {
                return $class;
            }
        }

        return null;
    }

    /** @return list<Class_> */
    private function classNodes(string $source): array
    {
        return new NodeFinder()->findInstanceOf($this->ast($source), Class_::class);
    }

    private function methodNode(string $source, string $name): ?ClassMethod
    {
        $class = $this->classNodes($source)[0] ?? null;

        return $class instanceof Class_ ? $this->methodNodeFromClass($class, $name) : null;
    }

    private function methodNodeFromClass(Class_ $class, string $name): ?ClassMethod
    {
        foreach ($class->stmts as $statement) {
            if ($statement instanceof ClassMethod && $statement->name->toString() === $name) {
                return $statement;
            }
        }

        return null;
    }

    /** @return list<MethodCall|NullsafeMethodCall> */
    private function methodCalls(ClassMethod $method): array
    {
        return $this->allMethodCalls($method->stmts ?? []);
    }

    /** @return list<MethodCall|NullsafeMethodCall> */
    private function methodCallsInSource(string $source): array
    {
        return $this->allMethodCalls($this->ast($source));
    }

    /** @param list<Node> $nodes @return list<MethodCall|NullsafeMethodCall> */
    private function allMethodCalls(array $nodes): array
    {
        return [
            ...new NodeFinder()->findInstanceOf($nodes, MethodCall::class),
            ...new NodeFinder()->findInstanceOf($nodes, NullsafeMethodCall::class),
        ];
    }

    /** @return list<New_> */
    private function newNodes(string|ClassMethod $source): array
    {
        $nodes = is_string($source) ? $this->ast($source) : $source->stmts ?? [];

        return new NodeFinder()->findInstanceOf($nodes, New_::class);
    }

    /** @return list<string> */
    private function newTypeInventory(ClassMethod $method): array
    {
        $types = array_map(
            fn(New_ $new): string => (string) $this->terminalName($new->class),
            $this->newNodes($method),
        );
        sort($types);

        return $types;
    }

    /** @param list<array{string,string}|array{string,string,string}> $arguments */
    private function countNew(ClassMethod $method, string $type, array $arguments = []): int
    {
        $count = 0;
        foreach ($this->newNodes($method) as $new) {
            if ($this->terminalName($new->class) !== $type) {
                continue;
            }
            if (!$this->argumentsMatch($new->args, $arguments)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /** @param list<MethodCall|NullsafeMethodCall> $calls @param list<array{string,string}|array{string,string,string}> $arguments */
    private function countCalls(array $calls, string $receiver, string $name, array $arguments = []): int
    {
        $count = 0;
        foreach ($calls as $call) {
            if (!$this->callMatches($call, $receiver, $name, $arguments)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /** @param list<array{string,string}|array{string,string,string}> $arguments */
    private function callMatches(
        MethodCall|NullsafeMethodCall $call,
        string $receiver,
        string $name,
        array $arguments,
    ): bool {
        if ($this->methodName($call) !== $name || $this->expressionLabel($call->var) !== $receiver) {
            return false;
        }

        return $this->argumentsMatch($call->args, $arguments);
    }

    private function methodName(MethodCall|NullsafeMethodCall $call): ?string
    {
        return $call->name instanceof Identifier ? $call->name->toString() : null;
    }

    private function resolvedName(?Node $node): ?string
    {
        if (!$node instanceof Name) {
            return null;
        }
        $resolved = $node->getAttribute('resolvedName');
        $name = $resolved instanceof Name ? $resolved->toString() : $node->toString();

        return ltrim(str_replace('/', '\\', $name), '\\');
    }

    private function sameResolvedName(?string $actual, string $expected): bool
    {
        return $actual !== null && strcasecmp($actual, ltrim($expected, '\\')) === 0;
    }

    /** @param array<int,Node\Arg> $actual @param list<array{string,string}|array{string,string,string}> $expected */
    private function argumentsMatch(array $actual, array $expected): bool
    {
        if (count($actual) !== count($expected)) {
            return false;
        }
        foreach ($expected as $index => $matcher) {
            $argument = $actual[$index]->value;
            if (
                $matcher[0] === 'variable'
                && !($argument instanceof Variable && is_string($argument->name) && $argument->name === $matcher[1])
            ) {
                return false;
            }
            if (
                $matcher[0] === 'string'
                && !($argument instanceof Node\Scalar\String_ && $argument->value === $matcher[1])
            ) {
                return false;
            }
            if (
                $matcher[0] === 'class-constant'
                && !(
                    $argument instanceof ClassConstFetch
                    && $this->terminalName($argument->class) === $matcher[1]
                    && $argument->name instanceof Identifier
                    && $argument->name->toString() === $matcher[2]
                )
            ) {
                return false;
            }
            if ($matcher[0] === 'method-call') {
                if (!($argument instanceof MethodCall || $argument instanceof NullsafeMethodCall)) {
                    return false;
                }
                if (
                    $this->expressionLabel($argument->var) !== $matcher[1]
                    || $this->methodName($argument) !== $matcher[2]
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    private function hasStringArgumentInStaticConstruction(ClassMethod $method, string $class, string $value): bool
    {
        foreach ($this->newNodes($method) as $new) {
            if ($this->terminalName($new->class) !== $class || $new->args === []) {
                continue;
            }
            $first = $new->args[0]->value;
            if ($first instanceof Node\Scalar\String_ && $first->value === $value) {
                return true;
            }
        }

        return false;
    }

    private function countClassConstantFetches(ClassMethod $method, string $class, ?string $constant): int
    {
        $count = 0;
        $statements = $method->stmts ?? [];
        foreach (new NodeFinder()->findInstanceOf($statements, ClassConstFetch::class) as $fetch) {
            if ($this->terminalName($fetch->class) !== $class) {
                continue;
            }
            if ($constant !== null && (!$fetch->name instanceof Identifier || $fetch->name->toString() !== $constant)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    private function countInstanceof(ClassMethod $method, string $class): int
    {
        $count = 0;
        $statements = $method->stmts ?? [];
        foreach (new NodeFinder()->findInstanceOf($statements, Instanceof_::class) as $instanceof) {
            if ($this->terminalName($instanceof->class) === $class) {
                $count++;
            }
        }

        return $count;
    }

    private function firstStatementIsTenantDeny(ClassMethod $method): bool
    {
        $first = $method->stmts[0] ?? null;
        if (!$first instanceof Node\Stmt\If_ || !$first->cond instanceof BooleanNot) {
            return false;
        }
        $condition = $first->cond->expr;
        if (
            !$condition instanceof MethodCall
            || $this->expressionLabel($condition->var) !== '$this'
            || $this->methodName($condition) !== 'sameTenant'
            || count($condition->args) !== 2
            || !$this->argumentsMatch($condition->args, [
                ['method-call', '$request', 'currentTenant'],
                ['method-call', '$request', 'originTenant'],
            ])
        ) {
            return false;
        }
        $return = $first->stmts[0] ?? null;

        return (
            $return instanceof Node\Stmt\Return_
            && $return->expr instanceof StaticCall
            && $this->terminalName($return->expr->class) === 'OperationStatusAuthorizationDecision'
            && $return->expr->name instanceof Identifier
            && $return->expr->name->toString() === 'deny'
        );
    }

    private function lastStatementDelegatesAuthorizer(ClassMethod $method): bool
    {
        $last = $method->stmts === null ? null : $method->stmts[array_key_last($method->stmts)] ?? null;

        return (
            $last instanceof Node\Stmt\Return_
            && $last->expr instanceof MethodCall
            && $this->callMatches($last->expr, '$authorizer', 'decide', [['variable', 'request']])
        );
    }

    private function queryDelegateCatchIsExact(ClassMethod $method): bool
    {
        $tries = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], TryCatch::class) as $try) {
            if ($try->finally !== null) {
                continue;
            }
            $calls = $this->allMethodCalls($try->stmts);
            if ($this->countCalls($calls, '$query', 'findForTenant', [
                ['variable', 'operationId'],
                ['variable', 'currentActor'],
                ['variable', 'currentTenant'],
            ]) === 1) {
                $tries[] = $try;
            }
        }
        if (count($tries) !== 1 || count($tries[0]->catches) !== 2) {
            return false;
        }

        $seen = [];
        foreach ($tries[0]->catches as $catch) {
            if (!$catch instanceof Catch_ || count($catch->types) !== 1) {
                return false;
            }
            $type = $this->resolvedName($catch->types[0]);
            if ($this->sameResolvedName($type, 'BlackOps\\Status\\Exception\\OperationStatusQueryException')) {
                if (
                    $catch->var === null
                    || $catch->var->name !== 'exception'
                    || !$this->catchAssignsVariable($catch, 'failure', 'exception')
                ) {
                    return false;
                }
                $seen[] = 'query';
                continue;
            }
            if ($this->sameResolvedName($type, 'Throwable')) {
                if ($catch->var !== null || !$this->catchAssignsFactory($catch, 'failure', 'storageFailed')) {
                    return false;
                }
                $seen[] = 'throwable';
                continue;
            }

            return false;
        }

        return $seen === ['query', 'throwable'];
    }

    private function queryResultFailureFlowIsExact(ClassMethod $method): bool
    {
        $matches = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], If_::class) as $if) {
            if (!$this->booleanOrInstanceofChainIsExact(
                $if->cond,
                '$candidate',
                [
                    'BlackOps\\Status\\OperationStatusFound',
                    'BlackOps\\Status\\OperationStatusExpired',
                    'BlackOps\\Status\\OperationStatusUnavailable',
                ],
            )) {
                continue;
            }
            $matches[] = $if;
        }
        if (count($matches) !== 1) {
            return false;
        }

        $if = $matches[0];

        return (
            $this->statementsAssignVariable($if->stmts, 'result', 'candidate')
            && $if->else !== null
            && $this->statementsAssignFactory($if->else->stmts, 'failure', 'integrityFailed')
        );
    }

    private function failureReleaseIsExact(ClassMethod $method): bool
    {
        $matches = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], If_::class) as $if) {
            if (
                !$if->cond instanceof Instanceof_
                || $this->expressionLabel($if->cond->expr) !== '$failure'
                || !$this->sameResolvedName(
                    $this->resolvedName($if->cond->class),
                    'BlackOps\\Status\\Exception\\OperationStatusQueryException',
                )
                || $if->else !== null
                || count($if->stmts) !== 1
                || !$if->stmts[0] instanceof Node\Stmt\Expression
            ) {
                continue;
            }
            $expression = $if->stmts[0]->expr;
            if (
                $expression instanceof Throw_
                && $expression->expr instanceof Variable
                && $expression->expr->name === 'failure'
            ) {
                $matches[] = $if;
            }
        }

        return count($matches) === 1;
    }

    private function terminalCatchIsExact(ClassMethod $method): bool
    {
        $matches = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], TryCatch::class) as $try) {
            if ($try->finally !== null) {
                continue;
            }
            $calls = $this->allMethodCalls($try->stmts);
            if (
                $this->countCalls($calls, '$session', 'accessed', []) === 1
                && $this->countCalls($calls, '$session', 'unavailable', []) === 1
            ) {
                $matches[] = $try;
            }
        }
        if (count($matches) !== 1 || count($matches[0]->catches) !== 1) {
            return false;
        }

        $catch = $matches[0]->catches[0];

        return (
            $catch instanceof Catch_
            && count($catch->types) === 1
            && $this->sameResolvedName($this->resolvedName($catch->types[0]), 'Throwable')
            && $this->catchAssignsFactoryAsThrow($catch, 'storageFailed')
            && $this->allMethodCalls($catch->stmts) === []
        );
    }

    private function protocolSequenceIsExact(ClassMethod $method): bool
    {
        $statements = $method->stmts ?? [];
        if (count($statements) !== 17) {
            return false;
        }

        return $this->assignmentMethodStatement($statements[0], 'connection', '$this', 'value', [[
            'string',
            'connection',
        ]]) && $this->ifNotInstanceofThrowsFactory($statements[1], '$connection', 'Doctrine\DBAL\Connection', 'storageFailed') && $this->callStatement($statements[2], '$this', 'assertIdle', [[
            'variable',
            'connection',
        ]]) && $this->assignmentMethodStatement($statements[3], 'sessions', '$this', 'value', [[
            'string',
            'sessions',
        ]]) && $this->ifNotInstanceofThrowsFactory($statements[4], '$sessions', 'BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory', 'storageFailed') && $statements[5] instanceof TryCatch && $this->beginTryCatchNodeIsExact($statements[5]) && $this->ifNotInstanceofThrowsFactory($statements[6], '$session', 'BlackOps\Internal\Audit\Read\OperationReadAuditSession', 'storageFailed') && $this->callStatement($statements[7], '$this', 'assertIdle', [[
            'variable',
            'connection',
        ]]) && $this->assignmentNullStatement($statements[8], 'result') && $this->assignmentNullStatement($statements[9], 'failure') && $statements[10] instanceof TryCatch && $this->delegateTryCatchNodeIsExact($statements[10]) && $this->callStatement($statements[11], '$this', 'assertIdle', [[
            'variable',
            'connection',
        ]]) && $statements[12] instanceof TryCatch && $this->terminalTryCatchNodeIsExact($statements[12]) && $this->callStatement($statements[13], '$this', 'assertIdle', [[
            'variable',
            'connection',
        ]]) && $this->failureReleaseStatementIsExact($statements[14]) && $this->resultFallbackStatementIsExact($statements[15]) && $this->resultReturnStatementIsExact($statements[16]);
    }

    private function assignmentMethodStatement(
        Node $statement,
        string $target,
        string $receiver,
        string $name,
        array $arguments,
    ): bool {
        if (!$statement instanceof Node\Stmt\Expression || !$statement->expr instanceof Assign) {
            return false;
        }
        $assignment = $statement->expr;

        return (
            $assignment->var instanceof Variable
            && $assignment->var->name === $target
            && ($assignment->expr instanceof MethodCall || $assignment->expr instanceof NullsafeMethodCall)
            && $this->callMatches($assignment->expr, $receiver, $name, $arguments)
        );
    }

    private function assignmentNewStatement(Node $statement, string $target, string $class, array $arguments): bool
    {
        if (!$statement instanceof Node\Stmt\Expression || !$statement->expr instanceof Assign) {
            return false;
        }
        $assignment = $statement->expr;

        return (
            $assignment->var instanceof Variable
            && $assignment->var->name === $target
            && $assignment->expr instanceof New_
            && $this->terminalName($assignment->expr->class) === $class
            && $this->argumentsMatch($assignment->expr->args, $arguments)
        );
    }

    private function assignmentNullStatement(Node $statement, string $target): bool
    {
        if (!$statement instanceof Node\Stmt\Expression || !$statement->expr instanceof Assign) {
            return false;
        }
        $assignment = $statement->expr;

        return (
            $assignment->var instanceof Variable
            && $assignment->var->name === $target
            && $assignment->expr instanceof Node\Expr\ConstFetch
            && $this->terminalName($assignment->expr->name) === 'null'
        );
    }

    private function callStatement(Node $statement, string $receiver, string $name, array $arguments): bool
    {
        if (!$statement instanceof Node\Stmt\Expression) {
            return false;
        }
        $expression = $statement->expr;

        return ($expression instanceof MethodCall || $expression instanceof NullsafeMethodCall)
        && $this->callMatches($expression, $receiver, $name, $arguments);
    }

    private function ifNotInstanceofThrowsFactory(
        Node $statement,
        string $variable,
        string $class,
        string $factory,
    ): bool {
        if (
            !$statement instanceof If_
            || $statement->else !== null
            || $statement->elseifs !== []
            || count($statement->stmts) !== 1
            || !$statement->cond instanceof BooleanNot
            || !$statement->cond->expr instanceof Instanceof_
        ) {
            return false;
        }
        $condition = $statement->cond->expr;

        return (
            $this->expressionLabel($condition->expr) === $variable
            && $this->sameResolvedName($this->resolvedName($condition->class), $class)
            && $this->throwFactoryStatement($statement->stmts[0], $factory)
        );
    }

    private function throwFactoryStatement(Node $statement, string $factory): bool
    {
        if (!$statement instanceof Node\Stmt\Expression || !$statement->expr instanceof Throw_) {
            return false;
        }
        $expression = $statement->expr;

        return $expression->expr instanceof StaticCall && $this->staticFactoryMatches($expression->expr, $factory);
    }

    private function beginTryCatchNodeIsExact(TryCatch $try): bool
    {
        if (
            $try->finally !== null
            || count($try->stmts) !== 1
            || !$this->assignmentMethodStatement($try->stmts[0], 'session', '$sessions', 'begin', [
                ['class-constant', 'AuditReadPurpose', 'Status'],
                ['variable', 'operationId'],
                ['variable', 'currentActor'],
                ['variable', 'currentTenant'],
            ])
            || count($try->catches) !== 1
        ) {
            return false;
        }
        $catch = $try->catches[0];

        return (
            $catch instanceof Catch_
            && count($catch->types) === 1
            && $this->sameResolvedName($this->resolvedName($catch->types[0]), 'Throwable')
            && $catch->var === null
            && $this->catchAssignsFactoryAsThrow($catch, 'storageFailed')
        );
    }

    private function delegateTryCatchNodeIsExact(TryCatch $try): bool
    {
        $statements = $try->stmts;
        if (
            $try->finally !== null
            || count($statements) !== 7
            || !$this->assignmentMethodStatement($statements[0], 'source', '$this', 'value', [['string', 'source']])
            || !$this->assignmentMethodStatement($statements[1], 'authorizer', '$this', 'value', [[
                'string',
                'authorizer',
            ]])
            || !$this->sourceAuthorizerGuardIsExact($statements[2])
            || !$this->assignmentNewStatement($statements[3], 'boundSource', 'TenantBoundOperationStatusSource', [
                ['variable', 'source'],
                ['variable', 'currentTenant'],
            ])
            || !$this->assignmentNewStatement($statements[4], 'query', 'DefaultOperationStatusQuery', [
                ['variable', 'boundSource'],
                ['variable', 'authorizer'],
            ])
            || !$this->assignmentMethodStatement($statements[5], 'candidate', '$query', 'findForTenant', [
                ['variable', 'operationId'],
                ['variable', 'currentActor'],
                ['variable', 'currentTenant'],
            ])
            || !$statements[6] instanceof If_
            || !$this->queryResultIfNodeIsExact($statements[6])
            || count($try->catches) !== 2
        ) {
            return false;
        }

        $queryCatch = $try->catches[0];
        $throwableCatch = $try->catches[1];

        return (
            $queryCatch instanceof Catch_
            && count($queryCatch->types) === 1
            && $this->sameResolvedName(
                $this->resolvedName($queryCatch->types[0]),
                'BlackOps\Status\Exception\OperationStatusQueryException',
            )
            && $queryCatch->var?->name === 'exception'
            && $this->catchAssignsVariable($queryCatch, 'failure', 'exception')
            && $throwableCatch instanceof Catch_
            && count($throwableCatch->types) === 1
            && $this->sameResolvedName($this->resolvedName($throwableCatch->types[0]), 'Throwable')
            && $throwableCatch->var === null
            && $this->catchAssignsFactory($throwableCatch, 'failure', 'storageFailed')
        );
    }

    private function sourceAuthorizerGuardIsExact(Node $statement): bool
    {
        if (
            !$statement instanceof If_
            || $statement->else !== null
            || $statement->elseifs !== []
            || count($statement->stmts) !== 1
        ) {
            return false;
        }
        $parts = $this->flattenBooleanOperator($statement->cond, BooleanOr::class);
        $expected = [
            ['$source',     'BlackOps\Internal\Status\TenantScopedOperationStatusSource'],
            ['$authorizer', 'BlackOps\Status\OperationStatusAuthorizer'],
        ];
        if (count($parts) !== count($expected)) {
            return false;
        }
        foreach ($parts as $index => $part) {
            if (!$part instanceof BooleanNot || !$part->expr instanceof Instanceof_) {
                return false;
            }
            if (
                $this->expressionLabel($part->expr->expr) !== $expected[$index][0]
                || !$this->sameResolvedName($this->resolvedName($part->expr->class), $expected[$index][1])
            ) {
                return false;
            }
        }

        return $this->throwFactoryStatement($statement->stmts[0], 'storageFailed');
    }

    private function queryResultIfNodeIsExact(If_ $if): bool
    {
        return (
            $if->elseifs === []
            && $if->else !== null
            && $this->booleanOrInstanceofChainIsExact(
                $if->cond,
                '$candidate',
                [
                    'BlackOps\Status\OperationStatusFound',
                    'BlackOps\Status\OperationStatusExpired',
                    'BlackOps\Status\OperationStatusUnavailable',
                ],
            )
            && $this->statementsAssignVariable($if->stmts, 'result', 'candidate')
            && $this->statementsAssignFactory($if->else->stmts, 'failure', 'integrityFailed')
        );
    }

    private function terminalTryCatchNodeIsExact(TryCatch $try): bool
    {
        if (
            $try->finally !== null
            || count($try->stmts) !== 1
            || !$try->stmts[0] instanceof If_
            || count($try->catches) !== 1
        ) {
            return false;
        }
        $catch = $try->catches[0];

        return (
            $this->terminalMappingNodeIsExact($try->stmts[0])
            && $catch instanceof Catch_
            && count($catch->types) === 1
            && $this->sameResolvedName($this->resolvedName($catch->types[0]), 'Throwable')
            && $catch->var === null
            && $this->catchAssignsFactoryAsThrow($catch, 'storageFailed')
        );
    }

    private function terminalMappingNodeIsExact(If_ $if): bool
    {
        return (
            $if->elseifs === []
            && $if->else !== null
            && $this->booleanOrInstanceofChainIsExact(
                $if->cond,
                '$result',
                [
                    'BlackOps\Status\OperationStatusFound',
                    'BlackOps\Status\OperationStatusExpired',
                ],
            )
            && $this->callStatement($if->stmts[0] ?? new Node\Stmt\Nop(), '$session', 'accessed', [])
            && $this->callStatement($if->else->stmts[0] ?? new Node\Stmt\Nop(), '$session', 'unavailable', [])
            && count($if->stmts) === 1
            && count($if->else->stmts) === 1
        );
    }

    private function failureReleaseStatementIsExact(Node $statement): bool
    {
        if (
            !$statement instanceof If_
            || $statement->elseifs !== []
            || $statement->else !== null
            || count($statement->stmts) !== 1
            || !$statement->cond instanceof Instanceof_
            || $this->expressionLabel($statement->cond->expr) !== '$failure'
            || !$this->sameResolvedName(
                $this->resolvedName($statement->cond->class),
                'BlackOps\Status\Exception\OperationStatusQueryException',
            )
        ) {
            return false;
        }
        $body = $statement->stmts[0];

        return (
            $body instanceof Node\Stmt\Expression
            && $body->expr instanceof Throw_
            && $body->expr->expr instanceof Variable
            && $body->expr->expr->name === 'failure'
        );
    }

    private function resultFallbackStatementIsExact(Node $statement): bool
    {
        if (
            !$statement instanceof If_
            || $statement->elseifs !== []
            || $statement->else !== null
            || count($statement->stmts) !== 1
            || !$statement->cond instanceof BooleanNot
            || !$statement->cond->expr instanceof Instanceof_
        ) {
            return false;
        }
        $condition = $statement->cond->expr;

        return (
            $this->expressionLabel($condition->expr) === '$result'
            && $this->sameResolvedName($this->resolvedName($condition->class), 'BlackOps\Status\OperationStatusResult')
            && $this->throwFactoryStatement($statement->stmts[0], 'integrityFailed')
        );
    }

    private function resultReturnStatementIsExact(Node $statement): bool
    {
        return (
            $statement instanceof Return_
            && $statement->expr instanceof Variable
            && $statement->expr->name === 'result'
        );
    }

    private function sameTenantComparisonIsExact(ClassMethod $method): bool
    {
        $statements = $method->stmts ?? [];
        if (count($statements) !== 2 || !$statements[0] instanceof If_ || !$statements[1] instanceof Return_) {
            return false;
        }
        $nullGuard = $statements[0];
        if (
            $nullGuard->else !== null
            || $nullGuard->elseifs !== []
            || !$this->booleanOrIdentityChainIsExact($nullGuard->cond, [
                ['$current', null],
                ['$origin', null],
            ])
            || count($nullGuard->stmts) !== 1
            || !$nullGuard->stmts[0] instanceof Node\Stmt\Return_
            || !$this->booleanAndIdentityChainIsExact($nullGuard->stmts[0]->expr, [
                ['$current', null],
                ['$origin', null],
            ])
        ) {
            return false;
        }

        return $this->booleanAndIdentityChainIsExact($statements[1]->expr, [
            ['$current->type', '$origin->type'],
            ['$current->id',   '$origin->id'],
        ]);
    }

    private function beginTryCatchIsExact(ClassMethod $method): bool
    {
        $matches = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], TryCatch::class) as $try) {
            if ($try->finally !== null) {
                continue;
            }
            $calls = $this->allMethodCalls($try->stmts);
            if ($this->countCalls($calls, '$sessions', 'begin', [
                ['class-constant', 'AuditReadPurpose', 'Status'],
                ['variable', 'operationId'],
                ['variable', 'currentActor'],
                ['variable', 'currentTenant'],
            ]) === 1) {
                $matches[] = $try;
            }
        }
        if (count($matches) !== 1 || count($matches[0]->catches) !== 1) {
            return false;
        }
        $try = $matches[0];
        $bodyCalls = $this->allMethodCalls($try->stmts);
        if (
            $this->countCalls($bodyCalls, '$query', 'findForTenant', []) !== 0
            || $this->countCalls($bodyCalls, '$session', 'accessed', []) !== 0
            || $this->countCalls($bodyCalls, '$session', 'unavailable', []) !== 0
        ) {
            return false;
        }

        $catch = $try->catches[0];

        return (
            $catch instanceof Catch_
            && count($catch->types) === 1
            && $this->sameResolvedName($this->resolvedName($catch->types[0]), 'Throwable')
            && $this->catchAssignsFactoryAsThrow($catch, 'storageFailed')
        );
    }

    /** @param list<Node> $statements */
    private function statementsAssignVariable(array $statements, string $target, string $value): bool
    {
        if (count($statements) !== 1 || !$statements[0] instanceof Node\Stmt\Expression) {
            return false;
        }
        $expression = $statements[0]->expr;

        return (
            $expression instanceof Assign
            && $expression->var instanceof Variable
            && $expression->var->name === $target
            && $expression->expr instanceof Variable
            && $expression->expr->name === $value
        );
    }

    /** @param list<Node> $statements */
    private function statementsAssignFactory(array $statements, string $target, string $factory): bool
    {
        if (count($statements) !== 1 || !$statements[0] instanceof Node\Stmt\Expression) {
            return false;
        }
        $expression = $statements[0]->expr;

        return (
            $expression instanceof Assign
            && $expression->var instanceof Variable
            && $expression->var->name === $target
            && $expression->expr instanceof StaticCall
            && $this->staticFactoryMatches($expression->expr, $factory)
        );
    }

    private function catchAssignsVariable(Catch_ $catch, string $target, string $value): bool
    {
        return $this->statementsAssignVariable($catch->stmts, $target, $value);
    }

    private function catchAssignsFactory(Catch_ $catch, string $target, string $factory): bool
    {
        return $this->statementsAssignFactory($catch->stmts, $target, $factory);
    }

    private function catchAssignsFactoryAsThrow(Catch_ $catch, string $factory): bool
    {
        if (count($catch->stmts) !== 1 || !$catch->stmts[0] instanceof Node\Stmt\Expression) {
            return false;
        }
        $expression = $catch->stmts[0]->expr;

        return (
            $expression instanceof Throw_
            && $expression->expr instanceof StaticCall
            && $this->staticFactoryMatches($expression->expr, $factory)
        );
    }

    private function staticFactoryMatches(StaticCall $call, string $method): bool
    {
        return (
            $this->sameResolvedName(
                $this->resolvedName($call->class),
                'BlackOps\\Status\\Exception\\OperationStatusQueryException',
            )
            && $call->name instanceof Identifier
            && strcasecmp($call->name->toString(), $method) === 0
            && $call->args === []
        );
    }

    /** @param list<string> $classes */
    private function booleanOrInstanceofChainIsExact(Node $condition, string $variable, array $classes): bool
    {
        $parts = $this->flattenBooleanOr($condition);
        if (count($parts) !== count($classes)) {
            return false;
        }
        foreach ($classes as $index => $class) {
            $part = $parts[$index];
            if (
                !$part instanceof Instanceof_
                || $this->expressionLabel($part->expr) !== $variable
                || !$this->sameResolvedName($this->resolvedName($part->class), $class)
            ) {
                return false;
            }
        }

        return true;
    }

    /** @param list<array{string,?string}> $comparisons */
    private function booleanOrIdentityChainIsExact(Node $condition, array $comparisons): bool
    {
        return $this->identityChainIsExact($condition, $comparisons, BooleanOr::class);
    }

    /** @param list<array{string,?string}> $comparisons */
    private function booleanAndIdentityChainIsExact(Node $condition, array $comparisons): bool
    {
        return $this->identityChainIsExact($condition, $comparisons, BooleanAnd::class);
    }

    /** @param list<array{string,?string}> $comparisons */
    private function identityChainIsExact(Node $condition, array $comparisons, string $operator): bool
    {
        $parts = $this->flattenBooleanOperator($condition, $operator);
        if (count($parts) !== count($comparisons)) {
            return false;
        }
        foreach ($comparisons as $index => [$left, $right]) {
            $part = $parts[$index];
            if (!$part instanceof Identical || $this->expressionLabel($part->left) !== $left) {
                return false;
            }
            if ($right === null) {
                if (
                    !$part->right instanceof Node\Expr\ConstFetch
                    || $this->terminalName($part->right->name) !== 'null'
                ) {
                    return false;
                }
                continue;
            }
            if ($this->expressionLabel($part->right) !== $right) {
                return false;
            }
        }

        return true;
    }

    /** @return list<Node> */
    private function flattenBooleanOr(Node $node): array
    {
        return $this->flattenBooleanOperator($node, BooleanOr::class);
    }

    /** @return list<Node> */
    private function flattenBooleanOperator(Node $node, string $operator): array
    {
        if (!$node instanceof $operator) {
            return [$node];
        }

        return [
            ...$this->flattenBooleanOperator($node->left, $operator),
            ...$this->flattenBooleanOperator($node->right, $operator),
        ];
    }

    private function terminalMappingIsExact(ClassMethod $method): bool
    {
        $matches = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], If_::class) as $if) {
            if ($this->booleanOrInstanceofChainIsExact(
                $if->cond,
                '$result',
                [
                    'BlackOps\\Status\\OperationStatusFound',
                    'BlackOps\\Status\\OperationStatusExpired',
                ],
            )) {
                $matches[] = $if;
            }
        }
        if (count($matches) !== 1 || $matches[0]->else === null) {
            return false;
        }
        $thenCalls = $this->allMethodCalls($matches[0]->stmts);
        $elseCalls = $this->allMethodCalls($matches[0]->else->stmts);

        return (
            count($thenCalls) === 1
            && $this->callMatches($thenCalls[0], '$session', 'accessed', [])
            && count($elseCalls) === 1
            && $this->callMatches($elseCalls[0], '$session', 'unavailable', [])
        );
    }

    private function expressionLabel(Node $expression): ?string
    {
        if ($expression instanceof Variable && is_string($expression->name)) {
            return '$' . $expression->name;
        }
        if (
            ($expression instanceof MethodCall || $expression instanceof NullsafeMethodCall)
            && $this->methodName($expression) !== null
        ) {
            $operator = $expression instanceof NullsafeMethodCall ? '?->' : '->';

            return $this->expressionLabel($expression->var) . $operator . $this->methodName($expression);
        }
        if ($expression instanceof Name) {
            return $this->terminalName($expression);
        }

        return null;
    }

    private function terminalName(?Node $node): ?string
    {
        if (!$node instanceof Name && !$node instanceof Identifier) {
            return null;
        }
        $resolved = $node->getAttribute('resolvedName');
        $name = $resolved instanceof Name ? $resolved->toString() : $node->toString();

        return basename(str_replace('\\', '/', ltrim($name, '\\')));
    }

    private function sensitiveAttributeCount(Param $parameter): int
    {
        $count = 0;
        foreach ($parameter->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($this->terminalName($attribute->name) === 'SensitiveParameter') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /** @return list<Node> */
    private function ast(string $source): array
    {
        try {
            $statements = new ParserFactory()
                ->createForNewestSupportedVersion()
                ->parse($source);
            if ($statements === null) {
                return [];
            }
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));

            return $traverser->traverse($statements);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string,string> $sources */
    private function assertSyntaxValid(array $sources, string $label): void
    {
        foreach ($sources as $path => $source) {
            self::assertNotSame([], $this->ast($source), $label . ':' . $path . ' is not parseable');
        }
    }

    private function replaceOnce(string $search, string $replace, string $subject): string
    {
        self::assertSame(1, substr_count($subject, $search), 'fixture needle count');

        return str_replace($search, $replace, $subject);
    }

    private function replaceNth(string $search, int $occurrence, string $replace, string $subject): string
    {
        self::assertGreaterThanOrEqual(1, $occurrence, 'fixture occurrence must be positive');
        $offset = 0;
        for ($index = 1; $index <= $occurrence; $index++) {
            $position = strpos($subject, $search, $offset);
            self::assertNotFalse($position, 'fixture occurrence was not found');
            if ($index === $occurrence) {
                return substr($subject, 0, $position) . $replace . substr($subject, $position + strlen($search));
            }
            $offset = $position + strlen($search);
        }

        return $subject;
    }

    private function moveOnce(string $anchor, string $replacement, string $from, string $to, string $subject): string
    {
        $subject = $this->replaceOnce($anchor, $replacement, $subject);

        $occurrences = substr_count($subject, $from);
        self::assertGreaterThanOrEqual(1, $occurrences, 'move fixture source was not found');

        return $this->replaceNth($from, $occurrences, $to, $subject);
    }

    private function insertBeforeFinalBrace(string $source, string $insertion): string
    {
        $position = strrpos($source, '}');
        self::assertNotFalse($position);

        return substr($source, 0, $position) . $insertion . substr($source, $position);
    }
}

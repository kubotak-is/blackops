<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Checkpoint\AuditCheckpointPreparation;
use BlackOps\Internal\Audit\Checkpoint\AuditCheckpointPreparationBody;
use BlackOps\Internal\Audit\Checkpoint\AuditCheckpointPreparationRowShape;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointBinding;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointBytes;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointInteger;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointPredecessor;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointPreparationWorkflow;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointRows;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointSchema;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointSegmentBinding;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointStore;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointStoredBinding;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointTransaction;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointTransactionPolicy;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointTrustWorkflow;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentEndpoint;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentGraph;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentNeighbors;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentPages;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentQueries;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentReader;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentRecord;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentRecordProof;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditClosedSegmentRelations;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditPartitionStateParser;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditSegmentLifecycle;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditSegmentRowParser;
use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditSegmentTextFields;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

final class AuditCheckpointArchitectureTest extends TestCase
{
    private const string SOURCE_ROOT = __DIR__ . '/../../src/Internal/Audit/Checkpoint';

    public function testExactTypeInventoryAndCallableSurfaces(): void
    {
        $expected = $this->expectedCallableSignatures();
        self::assertSame(array_keys($expected), $this->checkpointTypesList());
        self::assertSame($this->expectedSourcePaths(), $this->sourcePaths());
        foreach ($expected as $type => $methods) {
            $reflection = new ReflectionClass($type);
            self::assertTrue($reflection->isFinal(), $type . ' must be final');
            self::assertSame([], $reflection->getAttributes(PublicApi::class), $type . ' must stay internal');
            self::assertSame($this->expectedReadonly()[$type], $reflection->isReadOnly(), $type . ' readonly modifier');
            $actual = [];
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() === $type) {
                    $actual[$method->getName()] = $method;
                }
            }
            ksort($actual);
            $methods = $methods;
            ksort($methods);
            self::assertSame(array_keys($methods), array_keys($actual), $type . ' declared callable surface');
            foreach ($methods as $name => $signature) {
                $method = $actual[$name];
                self::assertSame(
                    $signature['visibility'],
                    $method->isPublic() ? 'public' : ($method->isPrivate() ? 'private' : 'protected'),
                    $type . '::' . $name . ' visibility',
                );
                self::assertSame($signature['static'], $method->isStatic(), $type . '::' . $name . ' static');
                self::assertSame($signature['return'], $this->typeName($method), $type . '::' . $name . ' return');
                self::assertSame(
                    $signature['params'],
                    array_map(fn(ReflectionParameter $parameter): string => $this->parameterType(
                        $parameter,
                    ), $method->getParameters()),
                    $type . '::' . $name . ' parameters',
                );
            }
        }
        foreach ($this->expectedSensitivity() as $type => $methods) {
            foreach ($methods as $name => $attributes) {
                $parameters = new ReflectionClass($type)
                    ->getMethod($name)
                    ->getParameters();
                self::assertSame(
                    $attributes,
                    array_map(
                        static fn(ReflectionParameter $parameter): int => count($parameter->getAttributes(\SensitiveParameter::class)),
                        $parameters,
                    ),
                    $type . '::' . $name . ' sensitivity map',
                );
            }
        }

        foreach ($this->sources() as $path => $source) {
            self::assertSame([], $this->checkpointSourceViolations($source), $path);
        }
    }

    public function testClosedSegmentRecordOwnsTheSingleProtectedReadAndTransactionOwnsTheRoot(): void
    {
        $sources = $this->sources();
        $reader = $sources['src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php'];
        $recordPath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecord.php';
        $record = $sources[$recordPath];
        $recordProof = $sources['src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecordProof.php'];
        foreach ($sources as $path => $source) {
            $expected = $path === $recordPath ? 1 : 0;
            self::assertSame($expected, $this->directMethodCallCount($source, 'unprotectRecord'), $path);
        }

        $queries = $sources['src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentQueries.php'];
        $transaction = $sources['src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php'];
        self::assertTrue($this->containsSqlLiteral($transaction, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'));
        $transactionCode = $this->codeWithoutCommentsAndStrings($transaction);
        self::assertSame(1, substr_count($transactionCode, 'beginTransaction('));
        self::assertSame(1, substr_count($transactionCode, '->commit('));
        self::assertSame(1, substr_count($transactionCode, 'rollBack('));
        self::assertSame(1, substr_count($transactionCode, 'close('));
        self::assertStringNotContainsString('public function rollback', $transactionCode);
        self::assertStringNotContainsString('public function mapFailure', $transactionCode);

        $readerCode = $this->codeWithoutCommentsAndStrings($reader);
        self::assertSame(0, substr_count($readerCode, 'pg_advisory_xact_lock('));
        self::assertSame(0, $this->partitionFenceKeyCount($reader));
        self::assertSame(0, substr_count($readerCode, '->protection->unprotectRecord('));
        self::assertSame(0, substr_count($readerCode, '->protectRecord('));
        self::assertSame(0, substr_count($readerCode, '->unprotectOutbox('));
        self::assertSame(0, substr_count($readerCode, 'AuditSegmentCloseResult'));
        self::assertSame(0, substr_count($readerCode, 'CompleteAuditRecord'));
        $proofResultStart = strpos($reader, 'private function proofResult');
        $assertConnectionStart = strpos($reader, 'private function assertConnection');
        self::assertIsInt($proofResultStart);
        self::assertIsInt($assertConnectionStart);
        $proofResultCode = $this->codeWithoutCommentsAndStrings(substr(
            $reader,
            $proofResultStart,
            $assertConnectionStart - $proofResultStart,
        ));
        self::assertSame(0, substr_count($proofResultCode, '->'));
        self::assertSame(0, substr_count($proofResultCode, 'new '));
        $proveStart = strpos($reader, 'public function proveInCurrentTransaction');
        self::assertIsInt($proveStart);
        $proveCode = $this->codeWithoutCommentsAndStrings(substr(
            $reader,
            $proveStart,
            $proofResultStart - $proveStart,
        ));
        $connectionCall = strpos($proveCode, '$this->assertConnection();');
        $identifiersCall = strpos($proveCode, '$this->assertIdentifiers($partitionId, $segmentId);');
        $operationTry = strpos($proveCode, 'try {');
        self::assertIsInt($connectionCall);
        self::assertIsInt($identifiersCall);
        self::assertIsInt($operationTry);
        self::assertLessThan($identifiersCall, $connectionCall);
        self::assertLessThan($operationTry, $identifiersCall);
        $recordCode = $this->codeWithoutCommentsAndStrings($record);
        self::assertSame(1, substr_count($recordCode, '->protection->unprotectRecord('));
        self::assertSame(1, substr_count($recordCode, '->hydrator->hydrate('));
        self::assertSame(0, substr_count($recordCode, '->protectRecord('));
        self::assertSame(0, substr_count($recordCode, '->unprotectOutbox('));
        self::assertSame(0, substr_count($recordCode, 'AuditSegmentCloseResult'));
        self::assertSame(0, substr_count($recordCode, 'CompleteAuditRecord'));
        $recordProofCode = $this->codeWithoutCommentsAndStrings($recordProof);
        self::assertSame(0, substr_count($recordProofCode, '->protection->unprotectRecord('));
        self::assertSame(0, substr_count($recordProofCode, '->hydrator->hydrate('));
        self::assertSame(0, substr_count($recordProofCode, '->protectRecord('));
        self::assertSame(0, substr_count($recordProofCode, '->unprotectOutbox('));
        self::assertSame(0, substr_count($recordProofCode, 'AuditSegmentCloseResult'));
        self::assertSame(0, substr_count($recordProofCode, 'CompleteAuditRecord'));
        self::assertTrue($this->containsSqlLiteral($queries, 'pg_advisory_xact_lock('));
        self::assertSame(1, $this->partitionFenceKeyCount($queries));
        self::assertTrue($this->containsSqlLiteral($queries, 'hashtextextended'));

        $store = $sources['src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStore.php'];
        $storeCode = $this->codeWithoutCommentsAndStrings($store);
        self::assertStringContainsString('new PostgreSqlAuditClosedSegmentReader(', $storeCode);
        self::assertStringContainsString('new PostgreSqlAuditCheckpointTransaction(', $storeCode);
        self::assertStringContainsString('$this->transaction->beginRoot(', $storeCode);
        self::assertStringContainsString('$this->transaction->abort(', $storeCode);
        self::assertSame(
            1,
            count($this->constructorGraphArguments($store, PostgreSqlAuditClosedSegmentReader::class)),
            'store owns the same connection/schema/hydrator/protection/partition graph',
        );
        self::assertSame(
            ['connection', 'b2Schema', 'hydrator', 'protection', 'partitions'],
            $this->constructorGraphArguments($store, PostgreSqlAuditClosedSegmentReader::class)[0],
        );
    }

    public function testCheckpointStateMapIsExactAndPrivate(): void
    {
        $expected = [
            AuditCheckpointPreparation::class => [
                'snapshot' => ['type' => 'SensitiveParameterValue', 'readonly' => true],
            ],
            PostgreSqlAuditCheckpointSchema::class => [
                'schema' => ['type' => PostgreSqlMigrationSchema::class, 'readonly' => true],
            ],
            PostgreSqlAuditClosedSegmentReader::class => [
                'connection' => ['type' => Connection::class, 'readonly' => true],
                'partitions' => ['type' => AuditPartitionIdDeriver::class, 'readonly' => true],
                'queries' => ['type' => PostgreSqlAuditClosedSegmentQueries::class, 'readonly' => true],
                'recordProof' => ['type' => PostgreSqlAuditClosedSegmentRecordProof::class, 'readonly' => true],
            ],
            PostgreSqlAuditCheckpointStore::class => [
                'reader' => ['type' => PostgreSqlAuditClosedSegmentReader::class, 'readonly' => true],
                'transaction' => ['type' => PostgreSqlAuditCheckpointTransaction::class, 'readonly' => true],
                'rows' => ['type' => PostgreSqlAuditCheckpointRows::class, 'readonly' => true],
                'predecessor' => ['type' => PostgreSqlAuditCheckpointPredecessor::class, 'readonly' => true],
                'trustWorkflow' => ['type' => PostgreSqlAuditCheckpointTrustWorkflow::class, 'readonly' => true],
                'partitions' => ['type' => AuditPartitionIdDeriver::class, 'readonly' => true],
                'preparationWorkflow' => [
                    'type' => PostgreSqlAuditCheckpointPreparationWorkflow::class,
                    'readonly' => true,
                ],
            ],
            PostgreSqlAuditCheckpointPreparationWorkflow::class => [
                'reader' => ['type' => PostgreSqlAuditClosedSegmentReader::class, 'readonly' => true],
                'transaction' => ['type' => PostgreSqlAuditCheckpointTransaction::class, 'readonly' => true],
                'rows' => ['type' => PostgreSqlAuditCheckpointRows::class, 'readonly' => true],
                'predecessor' => ['type' => PostgreSqlAuditCheckpointPredecessor::class, 'readonly' => true],
                'trustWorkflow' => ['type' => PostgreSqlAuditCheckpointTrustWorkflow::class, 'readonly' => true],
            ],
            PostgreSqlAuditClosedSegmentQueries::class => [
                'connection' => ['type' => Connection::class, 'readonly' => true],
                'schema' => ['type' => PostgreSqlAuditSchema::class, 'readonly' => true],
            ],
            PostgreSqlAuditClosedSegmentRecordProof::class => [
                'queries' => ['type' => PostgreSqlAuditClosedSegmentQueries::class, 'readonly' => true],
                'pages' => ['type' => PostgreSqlAuditClosedSegmentPages::class, 'readonly' => true],
            ],
            PostgreSqlAuditClosedSegmentPages::class => [
                'queries' => ['type' => PostgreSqlAuditClosedSegmentQueries::class, 'readonly' => true],
                'record' => ['type' => PostgreSqlAuditClosedSegmentRecord::class, 'readonly' => true],
            ],
            PostgreSqlAuditClosedSegmentRecord::class => [
                'hydrator' => ['type' => PostgreSqlAuditStoredRecordHydrator::class, 'readonly' => true],
                'protection' => ['type' => AuditProtectedBodyCodec::class, 'readonly' => true],
            ],
            PostgreSqlAuditCheckpointTransaction::class => [
                'connection' => ['type' => Connection::class, 'readonly' => true],
                'phase' => ['type' => 'string', 'readonly' => false],
                'closeAttempted' => ['type' => 'bool', 'readonly' => false],
            ],
            PostgreSqlAuditCheckpointRows::class => [
                'connection' => ['type' => Connection::class, 'readonly' => true],
                'schema' => ['type' => PostgreSqlAuditCheckpointSchema::class, 'readonly' => true],
                'segments' => ['type' => 'string', 'readonly' => true],
            ],
            PostgreSqlAuditCheckpointPredecessor::class => [
                'rows' => ['type' => PostgreSqlAuditCheckpointRows::class, 'readonly' => true],
            ],
            PostgreSqlAuditCheckpointTrustWorkflow::class => [
                'clock' => ['type' => ClockInterface::class, 'readonly' => true],
                'ids' => ['type' => Uuidv7Generator::class, 'readonly' => true],
            ],
        ];
        foreach ($expected as $type => $properties) {
            $reflection = new ReflectionClass($type);
            $actual = [];
            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $type) {
                    continue;
                }
                $actual[$property->getName()] = [
                    'type' => $this->propertyType($property),
                    'readonly' => $property->isReadOnly(),
                    'private' => $property->isPrivate(),
                    'static' => $property->isStatic(),
                ];
            }
            $expectedActual = [];
            foreach ($properties as $name => $property) {
                $expectedActual[$name] = $property + ['private' => true, 'static' => false];
            }
            ksort($actual);
            ksort($expectedActual);
            self::assertSame($expectedActual, $actual, $type . ' state map');
        }
        foreach ($this->expectedConstants() as $type => $names) {
            $actual = [];
            foreach (new ReflectionClass($type)->getReflectionConstants() as $constant) {
                if ($constant->getDeclaringClass()->getName() === $type) {
                    $actual[$constant->getName()] = [$constant->isPrivate(), $constant->isFinal()];
                }
            }
            sort($names);
            $expectedConstants = array_fill_keys($names, [true, false]);
            ksort($actual);
            self::assertSame($expectedConstants, $actual, $type . ' constants');
        }
        foreach ($this->expectedConstantValues() as $type => $values) {
            $reflection = new ReflectionClass($type);
            foreach ($values as $name => $expected) {
                $constant = $reflection->getReflectionConstant($name);
                if (!$constant instanceof \ReflectionClassConstant) {
                    self::fail($type . '::' . $name . ' constant is missing');
                }
                self::assertSame($expected, $constant->getValue(), $type . '::' . $name . ' value');
            }
        }
        foreach ($this->expectedTypesWithoutState() as $type) {
            self::assertSame([], new ReflectionClass($type)->getProperties(), $type . ' has no state');
        }
    }

    public function testStoreRootSetsRepeatableReadImmediatelyAfterBegin(): void
    {
        $sources = $this->sources();
        $transaction = $sources['src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php'];
        $rootOffset = strpos($transaction, 'public function beginRoot');
        self::assertIsInt($rootOffset);
        $root = substr($transaction, $rootOffset);
        $code = $this->codeWithoutCommentsAndStrings($root);
        $begin = strpos($code, 'beginTransaction(');
        $isolation = strpos($code, 'executeStatement(');
        self::assertIsInt($begin);
        self::assertIsInt($isolation);
        self::assertLessThan($isolation, $begin);
        self::assertSame(1, substr_count($code, 'beginTransaction('));
        self::assertSame(1, substr_count($code, 'executeStatement('));
        self::assertStringContainsString("if (\$this->phase !== 'ready')", $root);

        $store = $sources['src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStore.php'];
        $prepare = $this->codeWithoutCommentsAndStrings(substr($store, 0, strpos($store, 'public function persist')));
        $assertBindingCall = strpos($prepare, '$this->assertBinding(');
        $workflowCall = strpos($prepare, '$this->preparationWorkflow->run(');
        self::assertIsInt($assertBindingCall);
        self::assertIsInt($workflowCall);
        self::assertLessThan($workflowCall, $assertBindingCall);

        $workflow = $sources['src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPreparationWorkflow.php'];
        $run = $this->codeWithoutCommentsAndStrings(substr($workflow, strpos($workflow, 'public function run')));
        $rootCall = strpos($run, '$this->transaction->beginRoot();');
        $try = strpos($run, 'try {');
        $proofCall = strpos($run, '$this->reader->proveInCurrentTransaction(');
        self::assertIsInt($rootCall);
        self::assertIsInt($try);
        self::assertIsInt($proofCall);
        self::assertLessThan($try, $rootCall);
        self::assertLessThan($proofCall, $try);
        $policy = $sources['src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransactionPolicy.php'];
        $policyCode = $this->codeWithoutCommentsAndStrings($policy);
        self::assertSame(0, substr_count($policyCode, 'new PostgreSqlAuditCheckpointTransactionPolicy('));
        $rootPolicyStart = strpos($policy, 'public static function root');
        $ownedPolicyStart = strpos($policy, 'public static function owned');
        $mapPolicyStart = strpos($policy, 'public static function map');
        self::assertIsInt($rootPolicyStart);
        self::assertIsInt($ownedPolicyStart);
        self::assertIsInt($mapPolicyStart);
        $rootPolicy = $this->codeWithoutCommentsAndStrings(substr(
            $policy,
            $rootPolicyStart,
            $ownedPolicyStart - $rootPolicyStart,
        ));
        $ownedPolicy = $this->codeWithoutCommentsAndStrings(substr(
            $policy,
            $ownedPolicyStart,
            $mapPolicyStart - $ownedPolicyStart,
        ));
        $rootAuto = strpos($rootPolicy, 'isAutoCommit(');
        $rootActive = strpos($rootPolicy, 'isTransactionActive(');
        $rootLevel = strpos($rootPolicy, 'getTransactionNestingLevel(');
        $ownedAuto = strpos($ownedPolicy, 'isAutoCommit(');
        $ownedActive = strpos($ownedPolicy, 'isTransactionActive(');
        $ownedLevel = strpos($ownedPolicy, 'getTransactionNestingLevel(');
        foreach ([$rootAuto, $rootActive, $rootLevel, $ownedAuto, $ownedActive, $ownedLevel] as $marker) {
            self::assertIsInt($marker);
        }
        self::assertLessThan($rootActive, $rootAuto);
        self::assertLessThan($rootLevel, $rootActive);
        self::assertLessThan($ownedActive, $ownedAuto);
        self::assertLessThan($ownedLevel, $ownedActive);
        $persist = $this->codeWithoutCommentsAndStrings(substr($store, strpos($store, 'public function persist')));
        self::assertLessThan(
            strpos($persist, '$this->reader->proveInCurrentTransaction('),
            strpos($persist, '$this->transaction->beginRoot();'),
        );
    }

    public function testCheckpointDependencyMapAndConstructorGraphAreExact(): void
    {
        $expected = $this->expectedCheckpointDependencies();
        foreach ($this->sources() as $path => $source) {
            self::assertSame(
                $expected[$path],
                $this->checkpointDependencyReferences($source, $this->checkpointTypeForPath($path)),
                $path . ' dependency map',
            );
        }

        $bodyPath = 'src/Internal/Audit/Checkpoint/AuditCheckpointPreparationBody.php';
        $body = $this->sources()[$bodyPath];
        $mutatedBody = str_replace(
            'AuditCheckpointCodec::signingInput',
            'AuditArtifactSigner::checkpoint',
            $body,
            $replacementCount,
        );
        self::assertSame(1, $replacementCount);
        self::assertNotSame(
            $this->checkpointDependencyReferences($body, AuditCheckpointPreparationBody::class),
            $this->checkpointDependencyReferences($mutatedBody, AuditCheckpointPreparationBody::class),
            'a new F1 signer dependency must change the reviewed map',
        );

        $storePath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStore.php';
        $store = $this->sources()[$storePath];
        self::assertSame(
            [['connection', 'b2Schema', 'hydrator', 'protection', 'partitions']],
            $this->constructorGraphArguments($store, PostgreSqlAuditClosedSegmentReader::class),
            'Store reader constructor graph',
        );
        $mutatedArguments = str_replace(
            "            \$partitions,\n        );",
            "            \$clock,\n        );",
            $store,
            $argumentReplacementCount,
        );
        self::assertSame(1, $argumentReplacementCount);
        self::assertNotSame(
            $this->constructorGraphArguments($store, PostgreSqlAuditClosedSegmentReader::class),
            $this->constructorGraphArguments($mutatedArguments, PostgreSqlAuditClosedSegmentReader::class),
            'reader constructor dependency substitution must be visible',
        );
        $reader = $this->sources()['src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php'];
        self::assertSame(
            [['connection', 'schema']],
            $this->constructorGraphArguments($reader, PostgreSqlAuditClosedSegmentQueries::class),
        );
        self::assertSame(
            [['queries', 'hydrator', 'protection']],
            $this->constructorGraphArguments($reader, PostgreSqlAuditClosedSegmentRecordProof::class),
        );
        $recordProof = $this->sources()['src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecordProof.php'];
        self::assertSame(
            [['hydrator', 'protection']],
            $this->constructorGraphArguments($recordProof, PostgreSqlAuditClosedSegmentRecord::class),
        );
        self::assertSame(
            [['queries', 'record']],
            $this->constructorGraphArguments($recordProof, PostgreSqlAuditClosedSegmentPages::class),
        );
        self::assertSame(
            [['connection']],
            $this->constructorGraphArguments($store, PostgreSqlAuditCheckpointTransaction::class),
        );
        self::assertSame(
            [['connection', 'schema', 'b2Schema']],
            $this->constructorGraphArguments($store, PostgreSqlAuditCheckpointRows::class),
        );
        self::assertSame(
            [['rows']],
            $this->constructorGraphArguments($store, PostgreSqlAuditCheckpointPredecessor::class),
        );
        self::assertSame(
            [['clock', 'ids']],
            $this->constructorGraphArguments($store, PostgreSqlAuditCheckpointTrustWorkflow::class),
        );
        self::assertSame(
            [['reader', 'transaction', 'rows', 'predecessor', 'trustWorkflow']],
            $this->constructorGraphArguments($store, PostgreSqlAuditCheckpointPreparationWorkflow::class),
        );
        $mutatedWorkflow = str_replace(
            "            \$this->trustWorkflow,\n        );",
            "            \$this->reader,\n        );",
            $store,
            $workflowReplacementCount,
        );
        self::assertSame(1, $workflowReplacementCount);
        self::assertNotSame(
            $this->constructorGraphArguments($store, PostgreSqlAuditCheckpointPreparationWorkflow::class),
            $this->constructorGraphArguments($mutatedWorkflow, PostgreSqlAuditCheckpointPreparationWorkflow::class),
            'workflow collaborator substitution must be visible',
        );
        self::assertStringContainsString('new PostgreSqlAuditSchema($schema->schemaName())', $store);
    }

    public function testCheckpointLocalCallerOwnershipIsExactAndMutationVisible(): void
    {
        $expected = $this->expectedCheckpointLocalCalls();
        foreach ($this->sources() as $path => $source) {
            self::assertSame(
                $expected[$path],
                $this->checkpointLocalCallInventory($source, $this->checkpointTypeForPath($path)),
                $path . ' local caller map',
            );
        }

        $storePath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStore.php';
        $store = $this->sources()[$storePath];
        $added = $store . "\nPostgreSqlAuditCheckpointBinding::completeInput('');\n";
        self::assertNotSame($expected[$storePath], $this->checkpointLocalCallInventory(
            $added,
            PostgreSqlAuditCheckpointStore::class,
        ));
        $dynamic = str_replace(
            'PostgreSqlAuditCheckpointBinding::completeInput(',
            '$binding::completeInput(',
            $store,
            $replacementCount,
        );
        self::assertSame(1, $replacementCount);
        self::assertNotSame($expected[$storePath], $this->checkpointLocalCallInventory(
            $dynamic,
            PostgreSqlAuditCheckpointStore::class,
        ));
    }

    public function testCheckpointOwnInstanceCallerOwnershipIsExactAndMutationVisible(): void
    {
        $sources = $this->sources();
        $expected = $this->expectedCheckpointOwnInstanceCalls();
        foreach ($expected as $path => $methods) {
            foreach ($methods as $ownerMethod => $calls) {
                self::assertSame(
                    $calls,
                    $this->checkpointOwnInstanceCallInventory($sources[$path], $ownerMethod),
                    $path . '::' . $ownerMethod . ' own-instance caller map',
                );
            }
        }

        $readerPath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php';
        $reader = $sources[$readerPath];
        $proofCall = '$this->proofResult($target, $successor, $previous, $records);';
        $missingProof = str_replace(
            $proofCall,
            '$this->missingProofResult($target, $successor, $previous, $records);',
            $reader,
            $count,
        );
        self::assertSame(1, $count);
        self::assertNotSame(
            $expected[$readerPath]['proveInCurrentTransaction'],
            $this->checkpointOwnInstanceCallInventory($missingProof, 'proveInCurrentTransaction'),
            'missing Reader proofResult call must be visible',
        );
        $duplicateProof = str_replace(
            '            return ' . $proofCall,
            '            ' . $proofCall . "\n            return " . $proofCall,
            $reader,
            $duplicateCount,
        );
        self::assertSame(1, $duplicateCount);
        self::assertNotSame(
            $expected[$readerPath]['proveInCurrentTransaction'],
            $this->checkpointOwnInstanceCallInventory($duplicateProof, 'proveInCurrentTransaction'),
            'duplicate Reader proofResult call must be visible',
        );
        $identifierCall = '$this->assertIdentifiers($partitionId, $segmentId);';
        $missingIdentifiers = str_replace(
            $identifierCall,
            '$this->missingIdentifiers($partitionId, $segmentId);',
            $reader,
            $missingIdentifierCount,
        );
        self::assertSame(1, $missingIdentifierCount);
        self::assertNotSame(
            $expected[$readerPath]['proveInCurrentTransaction'],
            $this->checkpointOwnInstanceCallInventory($missingIdentifiers, 'proveInCurrentTransaction'),
            'missing Reader assertIdentifiers call must be visible',
        );
        $duplicateIdentifiers = str_replace(
            '        ' . $identifierCall . "\n",
            '        ' . $identifierCall . "\n        " . $identifierCall . "\n",
            $reader,
            $duplicateIdentifierCount,
        );
        self::assertSame(1, $duplicateIdentifierCount);
        self::assertNotSame(
            $expected[$readerPath]['proveInCurrentTransaction'],
            $this->checkpointOwnInstanceCallInventory($duplicateIdentifiers, 'proveInCurrentTransaction'),
            'duplicate Reader assertIdentifiers call must be visible',
        );

        $workflowPath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPreparationWorkflow.php';
        $workflow = $sources[$workflowPath];
        $retryReturn =
            '                return $this->retry($proof, $preparationRow, $previous, $signingKeyRef);' . "\n";
        $duplicateRetry = str_replace(
            $retryReturn,
            '                $this->retry($proof, $preparationRow, $previous, $signingKeyRef);' . "\n" . $retryReturn,
            $workflow,
            $duplicateRetryCount,
        );
        self::assertSame(1, $duplicateRetryCount);
        self::assertNotSame(
            $expected[$workflowPath]['run'],
            $this->checkpointOwnInstanceCallInventory($duplicateRetry, 'run'),
            'duplicate Workflow retry call must be visible',
        );

        $transactionPath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php';
        $transaction = $sources[$transactionPath];
        $missingClose = str_replace(
            '        $this->closeOnce();'
            . "\n"
            . '        return AuditStoreFailure::StorageUnavailable;'
            . "\n"
            . '    }'
            . "\n\n"
            . '    private function rollbackOwned',
            '        return AuditStoreFailure::StorageUnavailable;'
            . "\n"
            . '    }'
            . "\n\n"
            . '    private function rollbackOwned',
            $transaction,
            $missingCloseCount,
        );
        self::assertSame(1, $missingCloseCount);
        self::assertNotSame(
            $expected[$transactionPath]['abort'],
            $this->checkpointOwnInstanceCallInventory($missingClose, 'abort'),
            'missing Transaction abort closeOnce call must be visible',
        );
        $commitSuccess = '            $this->connection->commit();' . "\n" . '            $this->phase = \'ready\';';
        $wrongOwner = str_replace(
            $commitSuccess,
            '            $this->connection->commit();'
            . "\n"
            . '            $this->closeOnce();'
            . "\n"
            . '            $this->phase = \'ready\';',
            $transaction,
            $wrongOwnerCount,
        );
        self::assertSame(1, $wrongOwnerCount);
        self::assertNotSame(
            $expected[$transactionPath]['commit'],
            $this->checkpointOwnInstanceCallInventory($wrongOwner, 'commit'),
            'wrong-owner Transaction closeOnce call must be visible',
        );
    }

    public function testSourceGraphHasNoActivationOrExternalRegistrationEdge(): void
    {
        $root = realpath(__DIR__ . '/../../src');
        self::assertNotFalse($root);
        $ownedPrefix = 'Internal/Audit/Checkpoint/';
        $sources = $this->sourceFiles($root);
        $references = [];
        foreach ($sources as $relative => $source) {
            if (str_starts_with($relative, $ownedPrefix)) {
                continue;
            }
            $references[$relative] = $this->checkpointReferences($source);
        }
        $references = array_filter($references, static fn(array $values): bool => $values !== []);
        self::assertSame([], $references);

        $negative = [
            '<?php \BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointStore::prepare();',
            '<?php new \BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointStore();',
            '<?php use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointStore as Store; Store::prepare();',
            '<?php use BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointStore as Store; new Store();',
            '<?php use BlackOps\Internal\Audit\Checkpoint\{PostgreSqlAuditCheckpointStore as Store}; Store::prepare();',
            '<?php use BlackOps\Internal\Audit\Checkpoint\{PostgreSqlAuditCheckpointStore as Store}; new Store();',
            '<?php $class = \BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointStore::class; class_exists($class);',
            '<?php $class = "BlackOps\\\\Internal\\\\Audit\\\\Checkpoint\\\\PostgreSqlAuditCheckpointStore"; new $class();',
            '<?php $callable = ["BlackOps\\\\Internal\\\\Audit\\\\Checkpoint\\\\PostgreSqlAuditCheckpointStore", "prepare"]; call_user_func($callable);',
            '<?php $callable = "BlackOps\\\\Internal\\\\Audit\\\\Checkpoint\\\\PostgreSqlAuditCheckpointStore::prepare"; $callable();',
            '<?php $callable = "BlackOps\\\\Internal\\\\Audit\\\\Checkpoint\\\\PostgreSqlAuditCheckpointStore::prepare"; is_callable($callable);',
            '<?php $callable = ["BlackOps\\\\Internal\\\\Audit\\\\Checkpoint\\\\PostgreSqlAuditCheckpointStore", "prepare"]; forward_static_call($callable);',
            '<?php $class = "BlackOps\\\\Internal\\\\Audit\\\\Checkpoint\\\\PostgreSqlAuditCheckpointStore"; $reflection = new \ReflectionClass($class);',
            '<?php $class = "BlackOps\\\\Internal\\\\Audit\\\\Checkpoint\\\\PostgreSqlAuditCheckpointStore"; $callable = [$class, "prepare"]; \Closure::fromCallable($callable);',
            '<?php \BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointPreparationWorkflow::run();',
            '<?php \BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointTransactionPolicy::root();',
        ];
        foreach ($negative as $source) {
            self::assertNotSame([], $this->checkpointReferences($source), $source);
        }
        foreach ([
            '<?php /* \BlackOps\Internal\Audit\Checkpoint\PostgreSqlAuditCheckpointStore */',
            '<?php $message = "BlackOps\\\\Internal\\\\Audit\\\\Checkpoint\\\\PostgreSqlAuditCheckpointStore";',
            '<?php // PostgreSqlAuditCheckpointStore::prepare',
        ] as $source) {
            self::assertSame([], $this->checkpointReferences($source), $source);
        }
        foreach (array_keys($this->checkpointTypes()) as $type) {
            $short = substr($type, strrpos($type, '\\') + 1);
            $escaped = str_replace('\\', '\\\\', $type);
            foreach ([
                '<?php \\' . $type . '::class;',
                '<?php use ' . $type . ' as C; C::class;',
                '<?php use ' . $type . '; new ' . $short . '();',
                '<?php $class = "' . $escaped . '"; class_exists($class);',
                '<?php $callable = ["' . $escaped . '", "method"]; call_user_func($callable);',
            ] as $source) {
                self::assertContains($short, $this->checkpointReferences($source), $source);
            }
        }
    }

    public function testCheckpointSourceMutationsAreRejectedAndReadLocksRemainValid(): void
    {
        $sources = $this->sources();
        foreach ($sources as $path => $source) {
            self::assertSame([], $this->checkpointSourceViolations($source), $path . ' baseline');
        }

        $readerPath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php';
        $reader = $sources[$readerPath];
        $recordPath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecord.php';
        $record = $sources[$recordPath];
        $recordProof = $sources['src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecordProof.php'];
        $queriesPath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentQueries.php';
        $queries = $sources[$queriesPath];
        $transactionPath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php';
        $transaction = $sources[$transactionPath];
        $policyPath = 'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransactionPolicy.php';
        $policy = $sources[$policyPath];
        $mutations = [
            'missing protection decode' => str_replace(
                '->unprotectRecord(',
                '->removedProtectionCall(',
                $record,
                $count,
            ),
            'missing record hydration' => str_replace('->hydrate(', '->removedHydrationCall(', $record, $countHydrate),
            'wrong protection decode' => str_replace('->unprotectRecord(', '->unprotectOutbox(', $record, $countWrong),
            'wrong protected read owner' => $recordProof . "\n\$codec->unprotectRecord(\$value);\n",
            'new protection writer' => $record . "\n\$codec->protectRecord(\$value);\n",
            'fake close result' => $record . "\nnew \\BlackOps\\Internal\\Audit\\Storage\\AuditSegmentCloseResult();\n",
            'missing partition fence' => str_replace(
                'pg_advisory_xact_lock(',
                'pg_advisory_xact_lock_removed(',
                $queries,
                $countFence,
            ),
            'changed partition fence' => str_replace(
                'blackops.audit.partition.v1:',
                'blackops.audit.partition.changed.v1:',
                $queries,
                $countFenceKey,
            ),
            'state update' =>
                $queries . "\n\$connection->executeStatement('UPDATE audit_segments SET state = ''sealed''');\n",
            'state delete' => $queries . "\n\$connection->executeStatement('DELETE FROM audit_segments');\n",
            'state upsert' => $queries . "\n\$connection->executeStatement('UPSERT audit_segments');\n",
            'raw seed' => $reader . "\n\$seed = 'private material';\n",
            'provider edge' => $reader . "\n\$provider = \$value;\n",
            'public api marker' => $reader . "\n#[\\BlackOps\\Core\\Attribute\\PublicApi]\n",
            'sign call' => $reader . "\n\$signer->sign(\$value);\n",
            'release call' => $reader . "\n\$release->release(\$value);\n",
            'control edge' => $reader . "\n\$control->dispatch(\$value);\n",
            'registration edge' => $reader . "\nspl_autoload_register(\$callback);\n",
            'missing transaction begin' => str_replace(
                'beginTransaction(',
                'beginTransaction_removed(',
                $transaction,
                $transactionBeginCount,
            ),
            'policy transaction effect' => $policy . "\n\$connection->commit();\n",
        ];
        self::assertSame(1, $count);
        self::assertSame(1, $countHydrate);
        self::assertSame(1, $countWrong);
        self::assertSame(1, $countFence);
        self::assertSame(1, $countFenceKey);
        self::assertSame(1, $transactionBeginCount);
        foreach ($mutations as $name => $mutated) {
            self::assertNotSame([], $this->checkpointSourceViolations($mutated), $name);
        }

        $readLock = "<?php\n\$connection->fetchAssociative('SELECT 1 FOR UPDATE');\n";
        self::assertSame([], $this->sqlMutationViolations($readLock), 'FOR UPDATE is a row lock');
        self::assertSame(
            [],
            $this->sqlMutationViolations(
                "<?php\n// UPDATE is a forbidden state write in this guard.\n\$text = 'DELETE FROM a';\n",
            ),
            'ordinary comments and strings remain inert',
        );
        self::assertSame([], $this->checkpointSourceViolations($queries . <<<'PHP'

            // pg_advisory_xact_lock( and FOR UPDATE are inert comments.
            $text = 'blackops.audit.partition.v1: UPDATE DELETE UPSERT PublicApi seed provider release control';
            PHP), 'ordinary checkpoint marker comments and strings remain inert');
    }

    /** @return list<string> */
    private function checkpointReferences(string $source): array
    {
        $nodes = $this->parseSource($source);
        $targets = $this->checkpointTypes();
        $imports = [];
        foreach (array_merge(
            new NodeFinder()->findInstanceOf($nodes, GroupUse::class),
            new NodeFinder()->findInstanceOf($nodes, Use_::class),
        ) as $node) {
            if ($node instanceof GroupUse) {
                foreach ($node->uses as $use) {
                    $imports[spl_object_id($use->name)] = true;
                }
                $imports[spl_object_id($node->prefix)] = true;
            } elseif ($node instanceof Use_) {
                foreach ($node->uses as $use) {
                    $imports[spl_object_id($use->name)] = true;
                }
            }
        }

        $found = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if (isset($imports[spl_object_id($name)])) {
                continue;
            }
            $resolved = $this->resolvedName($name);
            if (isset($targets[$resolved])) {
                $found[$resolved] = true;
            }
        }

        $values = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Assign::class) as $assign) {
            if ($assign->var instanceof Variable && is_string($assign->var->name)) {
                $value = $this->checkpointValue($assign->expr, $values, $targets);
                if ($value !== null) {
                    $values[$assign->var->name] = $value;
                }
            }
        }
        $sinks = [
            'array_filter',
            'array_map',
            'array_reduce',
            'array_walk',
            'array_walk_recursive',
            'call_user_func',
            'call_user_func_array',
            'class_exists',
            'forward_static_call',
            'forward_static_call_array',
            'interface_exists',
            'is_callable',
            'is_a',
            'is_subclass_of',
            'iterator_apply',
            'preg_filter',
            'preg_replace_callback',
            'register_shutdown_function',
            'register_tick_function',
            'set_error_handler',
            'set_exception_handler',
            'spl_autoload_register',
            'uasort',
            'uksort',
            'usort',
        ];
        foreach (new NodeFinder()->findInstanceOf($nodes, FuncCall::class) as $call) {
            if ($call->name instanceof Variable && is_string($call->name->name)) {
                $value = $values[$call->name->name] ?? null;
                if ($value !== null) {
                    $found[$value] = true;
                }
            }
            if (!$call->name instanceof Name || !in_array(strtolower($call->name->toString()), $sinks, true)) {
                continue;
            }
            foreach ($call->args as $argument) {
                $value = $this->checkpointValue($argument->value, $values, $targets);
                if ($value !== null) {
                    $found[$value] = true;
                }
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            if ($new->class instanceof Variable && is_string($new->class->name)) {
                $value = $values[$new->class->name] ?? null;
                if ($value !== null) {
                    $found[$value] = true;
                }
            }
            foreach ($new->args as $argument) {
                $value = $this->checkpointValue($argument->value, $values, $targets);
                if ($value !== null) {
                    $found[$value] = true;
                }
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
            if ($call->class instanceof Variable && is_string($call->class->name)) {
                $value = $values[$call->class->name] ?? null;
                if ($value !== null) {
                    $found[$value] = true;
                }
            }
            if (
                $call->class instanceof Name
                && $call->name instanceof Identifier
                && $this->resolvedName($call->class) === 'Closure'
                && $call->name->toString() === 'fromCallable'
            ) {
                foreach ($call->args as $argument) {
                    $value = $this->checkpointValue($argument->value, $values, $targets);
                    if ($value !== null) {
                        $found[$value] = true;
                    }
                }
            }
        }

        $result = [];
        foreach (array_keys($found) as $type) {
            $result[] = substr($type, strrpos($type, '\\') + 1);
        }
        sort($result);
        return array_values(array_unique($result));
    }

    /** @return array<string,bool> */
    private function expectedReadonly(): array
    {
        return [
            AuditCheckpointPreparation::class => true,
            AuditCheckpointPreparationBody::class => false,
            AuditCheckpointPreparationRowShape::class => false,
            PostgreSqlAuditCheckpointBinding::class => false,
            PostgreSqlAuditCheckpointBytes::class => false,
            PostgreSqlAuditCheckpointInteger::class => false,
            PostgreSqlAuditCheckpointPredecessor::class => true,
            PostgreSqlAuditCheckpointSegmentBinding::class => false,
            PostgreSqlAuditCheckpointStoredBinding::class => false,
            PostgreSqlAuditCheckpointSchema::class => true,
            PostgreSqlAuditCheckpointRows::class => true,
            PostgreSqlAuditClosedSegmentReader::class => false,
            PostgreSqlAuditCheckpointStore::class => false,
            PostgreSqlAuditCheckpointTransaction::class => false,
            PostgreSqlAuditCheckpointPreparationWorkflow::class => true,
            PostgreSqlAuditCheckpointTransactionPolicy::class => false,
            PostgreSqlAuditCheckpointTrustWorkflow::class => true,
            PostgreSqlAuditClosedSegmentEndpoint::class => false,
            PostgreSqlAuditClosedSegmentGraph::class => false,
            PostgreSqlAuditClosedSegmentNeighbors::class => false,
            PostgreSqlAuditClosedSegmentQueries::class => true,
            PostgreSqlAuditClosedSegmentRecord::class => true,
            PostgreSqlAuditClosedSegmentPages::class => true,
            PostgreSqlAuditClosedSegmentRelations::class => false,
            PostgreSqlAuditPartitionStateParser::class => false,
            PostgreSqlAuditSegmentLifecycle::class => false,
            PostgreSqlAuditClosedSegmentRecordProof::class => true,
            PostgreSqlAuditSegmentRowParser::class => false,
            PostgreSqlAuditSegmentTextFields::class => false,
        ];
    }

    /** @return list<string> */
    private function checkpointTypesList(): array
    {
        return array_keys($this->checkpointTypes());
    }

    /** @return list<string> */
    private function expectedSourcePaths(): array
    {
        return [
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparation.php',
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparationBody.php',
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparationRowShape.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBinding.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBytes.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointInteger.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPredecessor.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPreparationWorkflow.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointRows.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSchema.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSegmentBinding.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStore.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStoredBinding.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransactionPolicy.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTrustWorkflow.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentEndpoint.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentGraph.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentNeighbors.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentPages.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentQueries.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecord.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecordProof.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRelations.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditPartitionStateParser.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentLifecycle.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentRowParser.php',
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentTextFields.php',
        ];
    }

    /** @return array<string,array<string,array{visibility:string,static:bool,return:?string,params:list<string>}>> */
    private function expectedCallableSignatures(): array
    {
        $method = static function (string $visibility, bool $static, ?string $return, array $params): array {
            return [
                'visibility' => $visibility,
                'static' => $static,
                'return' => $return,
                'params' => $params,
            ];
        };

        return [
            AuditCheckpointPreparation::class => [
                '__construct' => $method('private', false, null, ['array', 'array', 'string']),
                'fromRow' => $method('public', true, AuditCheckpointPreparation::class, ['array']),
                'unsigned' => $method('public', false, 'array', []),
                'boundary' => $method('public', false, 'array', []),
                'signingKeyFingerprint' => $method('public', false, 'string', []),
                '__debugInfo' => $method('public', false, 'array', []),
                '__serialize' => $method('public', false, 'array', []),
                '__unserialize' => $method('public', false, 'void', ['array']),
            ],
            AuditCheckpointPreparationBody::class => [
                '__construct' => $method('private', false, null, []),
                'read' => $method('public', true, 'array', ['string']),
            ],
            AuditCheckpointPreparationRowShape::class => [
                '__construct' => $method('private', false, null, []),
                'read' => $method('public', true, 'array', ['array']),
            ],
            PostgreSqlAuditCheckpointBinding::class => [
                '__construct' => $method('private', false, null, []),
                'preparation' => $method('public', true, 'void', ['array', 'array', 'array', '?array']),
                'persistUnsigned' => $method('public', true, 'void', ['array', 'array']),
                'completeInput' => $method('public', true, 'array', ['string']),
                'sealedReplay' => $method('public', true, 'void', ['array', 'array', 'string']),
                'completeReplay' => $method('public', true, 'void', ['array', 'array', 'string', 'string']),
                'unsignedBytes' => $method('public', true, 'string', ['array']),
                'stateVersion' => $method('public', true, 'void', ['string', 'int', 'int']),
                'completeRow' => $method('private', true, 'array', ['array']),
            ],
            PostgreSqlAuditCheckpointBytes::class => [
                '__construct' => $method('private', false, null, []),
                'database' => $method('public', true, 'string', ['mixed']),
                'completeInput' => $method('public', true, 'array', ['string']),
                'unsignedBytes' => $method('public', true, 'string', ['array']),
                'persistUnsigned' => $method('public', true, 'void', ['array', 'array']),
                'bounded' => $method('private', true, 'string', ['string']),
            ],
            PostgreSqlAuditCheckpointInteger::class => [
                '__construct' => $method('private', false, null, []),
                'read' => $method('public', true, 'int', ['mixed', 'int', 'int']),
            ],
            PostgreSqlAuditCheckpointPredecessor::class => [
                '__construct' => $method('public', false, null, [PostgreSqlAuditCheckpointRows::class]),
                'load' => $method('public', false, '?array', ['?string', 'string']),
                'read' => $method('private', false, 'array', ['array', 'array', 'string', 'string']),
                'bytea' => $method('private', false, 'string', ['mixed']),
            ],
            PostgreSqlAuditCheckpointSegmentBinding::class => [
                '__construct' => $method('private', false, null, []),
                'preparation' => $method('public', true, 'void', ['array', 'array', 'array', '?array']),
                'predecessor' => $method('public', true, 'void', ['array', 'array', 'array']),
                'boundary' => $method('private', true, 'void', ['array', 'array']),
                'range' => $method('private', true, 'void', ['array', 'array']),
                'tail' => $method('private', true, 'void', ['array', 'string', 'string']),
            ],
            PostgreSqlAuditCheckpointStoredBinding::class => [
                '__construct' => $method('private', false, null, []),
                'row' => $method('public', true, 'array', ['array']),
                'identity' => $method('public', true, 'void', ['string', 'mixed', 'array', 'string']),
                'stateVersion' => $method('public', true, 'void', ['string', 'int', 'int']),
            ],
            PostgreSqlAuditCheckpointSchema::class => [
                '__construct' => $method('public', false, null, [PostgreSqlMigrationSchema::class]),
                'schemaName' => $method('public', false, 'string', []),
                'preparationsTable' => $method('public', false, 'string', []),
                'checkpointsTable' => $method('public', false, 'string', []),
                'statements' => $method('public', false, 'array', []),
            ],
            PostgreSqlAuditCheckpointRows::class => [
                '__construct' => $method(
                    'public',
                    false,
                    null,
                    [
                        Connection::class,
                        PostgreSqlAuditCheckpointSchema::class,
                        PostgreSqlAuditSchema::class,
                    ],
                ),
                'preparation' => $method('public', false, 'array|false', ['string', 'string']),
                'complete' => $method('public', false, 'array|false', ['string']),
                'checkpointIdExists' => $method('public', false, 'bool', ['string']),
                'insertPreparation' => $method('public', false, 'int', ['array']),
                'insertComplete' => $method('public', false, 'int', ['array']),
                'predecessorSegment' => $method('public', false, 'array|false', ['string', 'string']),
                'predecessorCheckpoint' => $method('public', false, 'array|false', ['string', 'string']),
            ],
            PostgreSqlAuditClosedSegmentReader::class => [
                '__construct' => $method('public', false, null, [
                    Connection::class,
                    PostgreSqlAuditSchema::class,
                    PostgreSqlAuditStoredRecordHydrator::class,
                    AuditProtectedBodyCodec::class,
                    AuditPartitionIdDeriver::class,
                ]),
                'proveInCurrentTransaction' => $method(
                    'public',
                    false,
                    'array',
                    [
                        '?' . AuditTypedReference::class,
                        'string',
                        'string',
                    ],
                ),
                'proofResult' => $method('private', false, 'array', ['array', 'array', '?array', 'array']),
                'assertConnection' => $method('private', false, 'void', []),
                'assertIdentifiers' => $method('private', false, 'void', ['string', 'string']),
                'safeFailure' => $method('private', false, AuditStoreException::class, ['Throwable']),
            ],
            PostgreSqlAuditCheckpointStore::class => [
                '__construct' => $method('public', false, null, [
                    Connection::class,
                    PostgreSqlAuditCheckpointSchema::class,
                    PostgreSqlAuditStoredRecordHydrator::class,
                    AuditProtectedBodyCodec::class,
                    AuditPartitionIdDeriver::class,
                    ClockInterface::class,
                    Uuidv7Generator::class,
                ]),
                'prepare' => $method('public', false, AuditCheckpointPreparation::class, [
                    '?' . AuditTypedReference::class,
                    'string',
                    'string',
                    'string',
                    'array',
                ]),
                'persist' => $method('public', false, 'void', [
                    '?' . AuditTypedReference::class,
                    'string',
                    'string',
                    'string',
                    'array',
                ]),
                'assertBinding' => $method(
                    'private',
                    false,
                    'void',
                    [
                        '?' . AuditTypedReference::class,
                        'string',
                        'string',
                    ],
                ),
            ],
            PostgreSqlAuditCheckpointTransaction::class => [
                '__construct' => $method('public', false, null, [Connection::class]),
                'beginRoot' => $method('public', false, 'void', []),
                'commit' => $method('public', false, 'void', []),
                'abort' => $method('public', false, AuditStoreFailure::class, ['Throwable']),
                'rollbackOwned' => $method('private', false, 'bool', []),
                'closeOnce' => $method('private', false, 'void', []),
            ],
            PostgreSqlAuditCheckpointTrustWorkflow::class => [
                '__construct' => $method('public', false, null, [ClockInterface::class, Uuidv7Generator::class]),
                'newPreparation' => $method('public', false, 'array', ['array', '?array', 'string', 'array']),
                'verifyPersistence' => $method('public', false, 'void', [
                    AuditCheckpointPreparation::class,
                    '?array',
                    'string',
                    'array',
                ]),
                'canonicalNow' => $method('private', false, 'array', ['string']),
                'generatedId' => $method('private', false, 'string', ['DateTimeImmutable']),
            ],
            PostgreSqlAuditCheckpointPreparationWorkflow::class => [
                '__construct' => $method('public', false, null, [
                    PostgreSqlAuditClosedSegmentReader::class,
                    PostgreSqlAuditCheckpointTransaction::class,
                    PostgreSqlAuditCheckpointRows::class,
                    PostgreSqlAuditCheckpointPredecessor::class,
                    PostgreSqlAuditCheckpointTrustWorkflow::class,
                ]),
                'run' => $method('public', false, AuditCheckpointPreparation::class, [
                    '?' . AuditTypedReference::class,
                    'string',
                    'string',
                    'string',
                    'array',
                ]),
                'retry' => $method('private', false, AuditCheckpointPreparation::class, [
                    'array',
                    'array',
                    '?array',
                    'string',
                ]),
                'create' => $method('private', false, AuditCheckpointPreparation::class, [
                    'array',
                    '?array',
                    'string',
                    'array',
                ]),
            ],
            PostgreSqlAuditCheckpointTransactionPolicy::class => [
                '__construct' => $method('private', false, null, []),
                'root' => $method('public', true, 'bool', [Connection::class]),
                'owned' => $method('public', true, 'bool', [Connection::class]),
                'map' => $method('public', true, AuditStoreFailure::class, ['Throwable']),
            ],
            PostgreSqlAuditClosedSegmentEndpoint::class => [
                '__construct' => $method('private', false, null, []),
                'assertMatches' => $method('public', true, 'void', ['array|false', 'array', '?array']),
            ],
            PostgreSqlAuditClosedSegmentGraph::class => [
                '__construct' => $method('private', false, null, []),
                'state' => $method(
                    'public',
                    true,
                    'array',
                    ['array|false', '?' . AuditTypedReference::class, 'string'],
                ),
                'target' => $method('public', true, 'array', ['array|false', 'array', 'string', 'string']),
                'previous' => $method('public', true, '?array', ['array', 'array', 'string', 'string']),
                'successor' => $method('public', true, 'array', ['array', 'array', 'string', 'string']),
                'endpoint' => $method('public', true, 'void', ['array|false', 'array', '?array']),
            ],
            PostgreSqlAuditClosedSegmentNeighbors::class => [
                '__construct' => $method('private', false, null, []),
                'previous' => $method('public', true, '?array', ['array', 'array', 'string', 'string']),
                'successor' => $method('public', true, 'array', ['array', 'array', 'string', 'string']),
            ],
            PostgreSqlAuditClosedSegmentQueries::class => [
                '__construct' => $method('public', false, null, [Connection::class, PostgreSqlAuditSchema::class]),
                'lockPartition' => $method('public', false, 'void', ['string']),
                'state' => $method('public', false, 'array|false', ['?' . AuditTypedReference::class, 'string']),
                'target' => $method('public', false, 'array|false', ['string', 'string']),
                'successor' => $method('public', false, 'array', ['string', 'string']),
                'previous' => $method('public', false, 'array', ['string', 'string']),
                'endpoint' => $method(
                    'public',
                    false,
                    'array|false',
                    ['?' . AuditTypedReference::class, 'string', 'int'],
                ),
                'tenantMismatch' => $method(
                    'public',
                    false,
                    'bool',
                    ['?' . AuditTypedReference::class, 'string', 'string'],
                ),
                'records' => $method('public', false, 'array', [
                    '?' . AuditTypedReference::class,
                    'string',
                    'string',
                    'string',
                ]),
                'tenantPredicate' => $method('private', false, 'string', ['?' . AuditTypedReference::class]),
            ],
            PostgreSqlAuditClosedSegmentPages::class => [
                '__construct' => $method(
                    'public',
                    false,
                    null,
                    [
                        PostgreSqlAuditClosedSegmentQueries::class,
                        PostgreSqlAuditClosedSegmentRecord::class,
                    ],
                ),
                'read' => $method('public', false, 'array', ['?' . AuditTypedReference::class, 'array']),
            ],
            PostgreSqlAuditClosedSegmentRecord::class => [
                '__construct' => $method(
                    'public',
                    false,
                    null,
                    [
                        PostgreSqlAuditStoredRecordHydrator::class,
                        AuditProtectedBodyCodec::class,
                    ],
                ),
                'read' => $method('public', false, 'string', [
                    'array',
                    '?' . AuditTypedReference::class,
                    'array',
                    'int',
                    '?string',
                ]),
            ],
            PostgreSqlAuditClosedSegmentRecordProof::class => [
                '__construct' => $method(
                    'public',
                    false,
                    null,
                    [
                        PostgreSqlAuditClosedSegmentQueries::class,
                        PostgreSqlAuditStoredRecordHydrator::class,
                        AuditProtectedBodyCodec::class,
                    ],
                ),
                'prove' => $method('public', false, 'array', [
                    '?' . AuditTypedReference::class,
                    'string',
                    'string',
                    'array',
                ]),
                'safeFailure' => $method('private', false, AuditStoreException::class, ['Throwable']),
            ],
            PostgreSqlAuditClosedSegmentRelations::class => [
                '__construct' => $method('private', false, null, []),
                'neighborRow' => $method('public', true, 'array', ['array', 'string']),
                'edge' => $method('public', true, 'void', ['array', 'array']),
                'assertToken' => $method('public', true, 'void', ['string', 'array']),
            ],
            PostgreSqlAuditPartitionStateParser::class => [
                '__construct' => $method('private', false, null, []),
                'parse' => $method(
                    'public',
                    true,
                    'array',
                    [
                        'array|false',
                        '?' . AuditTypedReference::class,
                        'string',
                    ],
                ),
            ],
            PostgreSqlAuditSegmentLifecycle::class => [
                '__construct' => $method('private', false, null, []),
                'assertValid' => $method('public', true, 'void', ['array']),
            ],
            PostgreSqlAuditSegmentRowParser::class => [
                '__construct' => $method('private', false, null, []),
                'parse' => $method('public', true, 'array', ['array', 'string', 'string']),
            ],
            PostgreSqlAuditSegmentTextFields::class => [
                '__construct' => $method('private', false, null, []),
                'read' => $method('public', true, 'array', ['array', 'string']),
            ],
        ];
    }

    /** @return array<string,array<string,list<int>>> */
    private function expectedSensitivity(): array
    {
        return [
            AuditCheckpointPreparation::class => [
                '__construct' => [1, 1, 1],
                'fromRow' => [1],
                'unsigned' => [],
                'boundary' => [],
                'signingKeyFingerprint' => [],
                '__debugInfo' => [],
                '__serialize' => [],
                '__unserialize' => [1],
            ],
            AuditCheckpointPreparationBody::class => ['__construct' => [], 'read' => [1]],
            AuditCheckpointPreparationRowShape::class => [
                '__construct' => [],
                'read' => [1],
            ],
            PostgreSqlAuditCheckpointBinding::class => [
                '__construct' => [],
                'preparation' => [1, 1, 1, 1],
                'persistUnsigned' => [1, 1],
                'completeInput' => [1],
                'sealedReplay' => [1, 1, 1],
                'completeReplay' => [1, 1, 1, 1],
                'unsignedBytes' => [1],
                'stateVersion' => [1, 1, 1],
                'completeRow' => [1],
            ],
            PostgreSqlAuditCheckpointBytes::class => [
                '__construct' => [],
                'database' => [1],
                'completeInput' => [1],
                'unsignedBytes' => [1],
                'persistUnsigned' => [1, 1],
                'bounded' => [1],
            ],
            PostgreSqlAuditCheckpointInteger::class => [
                '__construct' => [],
                'read' => [1, 0, 0],
            ],
            PostgreSqlAuditCheckpointPredecessor::class => [
                '__construct' => [0],
                'load' => [1, 1],
                'read' => [1, 1, 1, 1],
                'bytea' => [1],
            ],
            PostgreSqlAuditCheckpointSegmentBinding::class => [
                '__construct' => [],
                'preparation' => [1, 1, 1, 1],
                'predecessor' => [1, 1, 1],
                'boundary' => [1, 1],
                'range' => [1, 1],
                'tail' => [1, 1, 1],
            ],
            PostgreSqlAuditCheckpointStoredBinding::class => [
                '__construct' => [],
                'row' => [1],
                'identity' => [1, 1, 1, 1],
                'stateVersion' => [1, 1, 1],
            ],
            PostgreSqlAuditCheckpointSchema::class => [
                '__construct' => [0],
                'schemaName' => [],
                'preparationsTable' => [],
                'checkpointsTable' => [],
                'statements' => [],
            ],
            PostgreSqlAuditCheckpointRows::class => [
                '__construct' => [0, 0, 0],
                'preparation' => [1, 1],
                'complete' => [1],
                'checkpointIdExists' => [1],
                'insertPreparation' => [1],
                'insertComplete' => [1],
                'predecessorSegment' => [1, 1],
                'predecessorCheckpoint' => [1, 1],
            ],
            PostgreSqlAuditClosedSegmentReader::class => [
                '__construct' => [0, 0, 0, 0, 0],
                'proveInCurrentTransaction' => [1, 1, 1],
                'proofResult' => [1, 1, 1, 1],
                'assertConnection' => [],
                'assertIdentifiers' => [1, 1],
                'safeFailure' => [1],
            ],
            PostgreSqlAuditCheckpointStore::class => [
                '__construct' => [0, 0, 0, 0, 0, 0, 0],
                'prepare' => [1, 1, 1, 1, 1],
                'persist' => [1, 1, 1, 1, 1],
                'assertBinding' => [1, 1, 1],
            ],
            PostgreSqlAuditCheckpointTransaction::class => [
                '__construct' => [0],
                'beginRoot' => [],
                'commit' => [],
                'abort' => [1],
                'rollbackOwned' => [],
                'closeOnce' => [],
            ],
            PostgreSqlAuditCheckpointTrustWorkflow::class => [
                '__construct' => [0, 0],
                'newPreparation' => [1, 1, 1, 1],
                'verifyPersistence' => [1, 1, 1, 1],
                'canonicalNow' => [1],
                'generatedId' => [1],
            ],
            PostgreSqlAuditCheckpointPreparationWorkflow::class => [
                '__construct' => [0, 0, 0, 0, 0],
                'run' => [1, 1, 1, 1, 1],
                'retry' => [1, 1, 1, 1],
                'create' => [1, 1, 1, 1],
            ],
            PostgreSqlAuditCheckpointTransactionPolicy::class => [
                '__construct' => [],
                'root' => [1],
                'owned' => [1],
                'map' => [1],
            ],
            PostgreSqlAuditClosedSegmentEndpoint::class => [
                '__construct' => [],
                'assertMatches' => [1, 1, 1],
            ],
            PostgreSqlAuditClosedSegmentGraph::class => [
                '__construct' => [],
                'state' => [1, 1, 1],
                'target' => [1, 1, 1, 1],
                'previous' => [1, 1, 1, 1],
                'successor' => [1, 1, 1, 1],
                'endpoint' => [1, 1, 1],
            ],
            PostgreSqlAuditClosedSegmentNeighbors::class => [
                '__construct' => [],
                'previous' => [1, 1, 1, 1],
                'successor' => [1, 1, 1, 1],
            ],
            PostgreSqlAuditClosedSegmentQueries::class => [
                '__construct' => [0, 0],
                'lockPartition' => [1],
                'state' => [1, 1],
                'target' => [1, 1],
                'successor' => [1, 1],
                'previous' => [1, 1],
                'endpoint' => [1, 1, 1],
                'tenantMismatch' => [1, 1, 1],
                'records' => [1, 1, 1, 1],
                'tenantPredicate' => [1],
            ],
            PostgreSqlAuditClosedSegmentRecordProof::class => [
                '__construct' => [0, 0, 0],
                'prove' => [1, 1, 1, 1],
                'safeFailure' => [1],
            ],
            PostgreSqlAuditClosedSegmentPages::class => [
                '__construct' => [0, 0],
                'read' => [1, 1],
            ],
            PostgreSqlAuditClosedSegmentRecord::class => [
                '__construct' => [0, 0],
                'read' => [1, 1, 1, 1, 1],
            ],
            PostgreSqlAuditClosedSegmentRelations::class => [
                '__construct' => [],
                'neighborRow' => [1, 1],
                'edge' => [1, 1],
                'assertToken' => [1, 1],
            ],
            PostgreSqlAuditPartitionStateParser::class => [
                '__construct' => [],
                'parse' => [1, 1, 1],
            ],
            PostgreSqlAuditSegmentLifecycle::class => [
                '__construct' => [],
                'assertValid' => [1],
            ],
            PostgreSqlAuditSegmentRowParser::class => [
                '__construct' => [],
                'parse' => [1, 1, 1],
            ],
            PostgreSqlAuditSegmentTextFields::class => [
                '__construct' => [],
                'read' => [1, 1],
            ],
        ];
    }

    /** @return array<string,list<string>> */
    private function expectedConstants(): array
    {
        return [
            AuditCheckpointPreparation::class => [],
            AuditCheckpointPreparationBody::class => [],
            AuditCheckpointPreparationRowShape::class => [
                'UUID_V7',
                'PARTITION',
                'FENCING_TOKEN_PATTERN',
                'FINGERPRINT',
                'PREPARATION_STRING_PATTERNS',
            ],
            PostgreSqlAuditCheckpointBinding::class => [],
            PostgreSqlAuditCheckpointBytes::class => ['MAX_BYTES'],
            PostgreSqlAuditCheckpointInteger::class => [],
            PostgreSqlAuditCheckpointSegmentBinding::class => [
                'BOUNDARY_STRICT_FIELDS',
                'UNSIGNED_RANGE_FIELDS',
            ],
            PostgreSqlAuditCheckpointStoredBinding::class => [
                'UUID_V7',
                'HASH',
                'MAX_SAFE_INTEGER',
                'COMPLETE_STRING_PATTERNS',
            ],
            PostgreSqlAuditCheckpointPredecessor::class => [],
            PostgreSqlAuditCheckpointSchema::class => [],
            PostgreSqlAuditCheckpointRows::class => [],
            PostgreSqlAuditClosedSegmentReader::class => ['UUID_V7', 'PARTITION'],
            PostgreSqlAuditCheckpointStore::class => ['UUID_V7', 'PARTITION'],
            PostgreSqlAuditCheckpointTransaction::class => [],
            PostgreSqlAuditCheckpointTrustWorkflow::class => [],
            PostgreSqlAuditCheckpointPreparationWorkflow::class => ['MAX_SAFE_INTEGER'],
            PostgreSqlAuditCheckpointTransactionPolicy::class => [],
            PostgreSqlAuditClosedSegmentGraph::class => [
                'MAX_SAFE_INTEGER',
            ],
            PostgreSqlAuditClosedSegmentEndpoint::class => ['HASH', 'MAX_SAFE_INTEGER'],
            PostgreSqlAuditClosedSegmentNeighbors::class => [],
            PostgreSqlAuditClosedSegmentQueries::class => [],
            PostgreSqlAuditClosedSegmentPages::class => ['MAX_SAFE_INTEGER'],
            PostgreSqlAuditClosedSegmentRecord::class => [],
            PostgreSqlAuditClosedSegmentRecordProof::class => [],
            PostgreSqlAuditClosedSegmentRelations::class => ['MAX_SAFE_INTEGER'],
            PostgreSqlAuditPartitionStateParser::class => [
                'UUID_V7',
                'HASH',
                'PARTITION',
                'MAX_SAFE_INTEGER',
                'STATE_REQUIRED_PATTERNS',
            ],
            PostgreSqlAuditSegmentLifecycle::class => [],
            PostgreSqlAuditSegmentRowParser::class => [
                'UUID_V7',
                'SEGMENT_REQUIRED_PATTERNS',
            ],
            PostgreSqlAuditSegmentTextFields::class => [
                'UUID_V7',
                'HASH',
                'FENCING_TOKEN_PATTERN',
                'SEGMENT_NULLABLE_PATTERNS',
            ],
        ];
    }

    /** @return array<string,array<string,array<string,string>|list<string>|int>> */
    private function expectedConstantValues(): array
    {
        $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';
        $hash = '/^[0-9a-f]{64}$/D';
        $partition = '/^(?:system:global|tenant:[a-f0-9]{64})$/D';
        $fencingToken = '/^aft:v1:[a-f0-9]{64}$/D';
        $fingerprint = '/^[a-f0-9]{64}$/D';

        return [
            AuditCheckpointPreparationRowShape::class => [
                'PREPARATION_STRING_PATTERNS' => [
                    'checkpoint_id' => $uuid,
                    'partition_id' => $partition,
                    'segment_id' => $uuid,
                    'close_fencing_token' => $fencingToken,
                    'signing_key_fingerprint' => $fingerprint,
                ],
            ],
            PostgreSqlAuditSegmentRowParser::class => [
                'SEGMENT_REQUIRED_PATTERNS' => [
                    'partition_id' => $partition,
                    'segment_id' => $uuid,
                ],
            ],
            PostgreSqlAuditSegmentTextFields::class => [
                'UUID_V7' => $uuid,
                'HASH' => $hash,
                'FENCING_TOKEN_PATTERN' => '/^aft:v1:[0-9a-f]{64}$/D',
                'SEGMENT_NULLABLE_PATTERNS' => [
                    'head_record_hash' => $hash,
                    'predecessor_record_hash' => $hash,
                    'successor_segment_id' => $uuid,
                    'close_fencing_token' => '/^aft:v1:[0-9a-f]{64}$/D',
                ],
            ],
            PostgreSqlAuditPartitionStateParser::class => [
                'UUID_V7' => $uuid,
                'HASH' => $hash,
                'PARTITION' => $partition,
                'MAX_SAFE_INTEGER' => 9_007_199_254_740_991,
                'STATE_REQUIRED_PATTERNS' => [
                    'partition_id' => $partition,
                    'active_segment_id' => $uuid,
                ],
            ],
            PostgreSqlAuditClosedSegmentEndpoint::class => [
                'HASH' => $hash,
                'MAX_SAFE_INTEGER' => 9_007_199_254_740_991,
            ],
            PostgreSqlAuditCheckpointBytes::class => [
                'MAX_BYTES' => 1_048_575,
            ],
            PostgreSqlAuditCheckpointStoredBinding::class => [
                'UUID_V7' => $uuid,
                'HASH' => $hash,
                'COMPLETE_STRING_PATTERNS' => [
                    'checkpoint_id' => $uuid,
                    'checkpoint_hash' => $hash,
                ],
                'MAX_SAFE_INTEGER' => 9_007_199_254_740_991,
            ],
            PostgreSqlAuditCheckpointSegmentBinding::class => [
                'BOUNDARY_STRICT_FIELDS' => ['partitionId', 'segmentId'],
                'UNSIGNED_RANGE_FIELDS' => [
                    'partitionId',
                    'segmentId',
                    'firstSequence',
                    'lastSequence',
                    'recordCount',
                ],
            ],
            PostgreSqlAuditClosedSegmentGraph::class => [
                'MAX_SAFE_INTEGER' => 9_007_199_254_740_991,
            ],
            PostgreSqlAuditClosedSegmentRelations::class => [
                'MAX_SAFE_INTEGER' => 9_007_199_254_740_991,
            ],
            PostgreSqlAuditClosedSegmentPages::class => [
                'MAX_SAFE_INTEGER' => 9_007_199_254_740_991,
            ],
        ];
    }

    /** @return list<string> */
    private function expectedTypesWithoutState(): array
    {
        return [
            AuditCheckpointPreparationBody::class,
            AuditCheckpointPreparationRowShape::class,
            PostgreSqlAuditCheckpointBinding::class,
            PostgreSqlAuditCheckpointBytes::class,
            PostgreSqlAuditCheckpointInteger::class,
            PostgreSqlAuditCheckpointSegmentBinding::class,
            PostgreSqlAuditCheckpointStoredBinding::class,
            PostgreSqlAuditClosedSegmentEndpoint::class,
            PostgreSqlAuditClosedSegmentGraph::class,
            PostgreSqlAuditClosedSegmentNeighbors::class,
            PostgreSqlAuditClosedSegmentRelations::class,
            PostgreSqlAuditCheckpointTransactionPolicy::class,
            PostgreSqlAuditPartitionStateParser::class,
            PostgreSqlAuditSegmentLifecycle::class,
            PostgreSqlAuditSegmentRowParser::class,
            PostgreSqlAuditSegmentTextFields::class,
        ];
    }

    /** @return array<string,true> */
    private function checkpointTypes(): array
    {
        return array_fill_keys([
            AuditCheckpointPreparation::class,
            AuditCheckpointPreparationBody::class,
            AuditCheckpointPreparationRowShape::class,
            PostgreSqlAuditCheckpointBinding::class,
            PostgreSqlAuditCheckpointBytes::class,
            PostgreSqlAuditCheckpointInteger::class,
            PostgreSqlAuditCheckpointPredecessor::class,
            PostgreSqlAuditCheckpointSegmentBinding::class,
            PostgreSqlAuditCheckpointStoredBinding::class,
            PostgreSqlAuditCheckpointSchema::class,
            PostgreSqlAuditCheckpointRows::class,
            PostgreSqlAuditClosedSegmentReader::class,
            PostgreSqlAuditCheckpointStore::class,
            PostgreSqlAuditCheckpointTransaction::class,
            PostgreSqlAuditCheckpointTrustWorkflow::class,
            PostgreSqlAuditCheckpointPreparationWorkflow::class,
            PostgreSqlAuditCheckpointTransactionPolicy::class,
            PostgreSqlAuditClosedSegmentEndpoint::class,
            PostgreSqlAuditClosedSegmentGraph::class,
            PostgreSqlAuditClosedSegmentNeighbors::class,
            PostgreSqlAuditClosedSegmentQueries::class,
            PostgreSqlAuditClosedSegmentPages::class,
            PostgreSqlAuditClosedSegmentRecord::class,
            PostgreSqlAuditClosedSegmentRecordProof::class,
            PostgreSqlAuditClosedSegmentRelations::class,
            PostgreSqlAuditPartitionStateParser::class,
            PostgreSqlAuditSegmentLifecycle::class,
            PostgreSqlAuditSegmentRowParser::class,
            PostgreSqlAuditSegmentTextFields::class,
        ], true);
    }

    /** @return array<string,list<string>> */
    private function expectedCheckpointDependencies(): array
    {
        $expected = [
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparation.php' => [
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'BlackOps\\Internal\\Audit\\Checkpoint\\AuditCheckpointPreparationBody',
                'BlackOps\\Internal\\Audit\\Checkpoint\\AuditCheckpointPreparationRowShape',
                'RuntimeException',
                'SensitiveParameter',
                'SensitiveParameterValue',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparationBody.php' => [
                'BlackOps\\Internal\\Audit\\Artifact\\AuditCheckpointCodec',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparationRowShape.php' => [
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointBytes',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointInteger',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBinding.php' => [
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointBytes',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointSegmentBinding',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointStoredBinding',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBytes.php' => [
                'BlackOps\\Internal\\Audit\\Artifact\\AuditCheckpointCodec',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'BlackOps\\Transport\\PostgreSql\\PostgreSqlBytea',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointInteger.php' => [
                'RuntimeException',
                'SensitiveParameter',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPredecessor.php' => [
                'BlackOps\\Internal\\Audit\\Checkpoint\\AuditCheckpointPreparation',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointBinding',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointBytes',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointRows',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointSegmentBinding',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointStoredBinding',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditSegmentRowParser',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSegmentBinding.php' => [
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointStoredBinding',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStoredBinding.php' => [
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointBytes',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSchema.php' => [
                'BlackOps\\Internal\\Migration\\PostgreSqlMigrationSchema',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentGraph',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentQueries',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentRecordProof',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectedBodyCodec',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectionException',
                'BlackOps\\Internal\\Audit\\Record\\AuditPartitionIdDeriver',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditSchema',
                'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditStoredRecordHydrator',
                'Doctrine\\DBAL\\Connection',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointRows.php' => [
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointSchema',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditSchema',
                'Doctrine\\DBAL\\Connection',
                'Doctrine\\DBAL\\ParameterType',
                'SensitiveParameter',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentGraph.php' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentEndpoint',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentNeighbors',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentRelations',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditPartitionStateParser',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditSegmentRowParser',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentEndpoint.php' => [
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointInteger',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentNeighbors.php' => [
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentRelations',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditSegmentRowParser',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentQueries.php' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditSchema',
                'Doctrine\\DBAL\\Connection',
                'Doctrine\\DBAL\\ParameterType',
                'SensitiveParameter',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecordProof.php' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentPages',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentRecord',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentQueries',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectedBodyCodec',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectionException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditStoredRecordHydrator',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentPages.php' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentQueries',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentRecord',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'SensitiveParameter',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecord.php' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectedBodyCodec',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditStoredRecordHydrator',
                'SensitiveParameter',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRelations.php' => [
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'RuntimeException',
                'SensitiveParameter',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditPartitionStateParser.php' => [
                'BlackOps\\Core\\Audit\\AuditRecordTime',
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointInteger',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStore.php' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Internal\\Audit\\Checkpoint\\AuditCheckpointPreparation',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointBinding',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointPredecessor',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointPreparationWorkflow',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointRows',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointSchema',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointTransaction',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointTrustWorkflow',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentReader',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectedBodyCodec',
                'BlackOps\\Internal\\Audit\\Record\\AuditPartitionIdDeriver',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditSchema',
                'BlackOps\\Internal\\Audit\\Storage\\PostgreSqlAuditStoredRecordHydrator',
                'BlackOps\\Internal\\Identifier\\Uuidv7Generator',
                'Doctrine\\DBAL\\Connection',
                'Psr\\Clock\\ClockInterface',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php' => [
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointTransactionPolicy',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'Doctrine\\DBAL\\Connection',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPreparationWorkflow.php' => [
                'BlackOps\\Core\\Audit\\AuditTypedReference',
                'BlackOps\\Internal\\Audit\\Checkpoint\\AuditCheckpointPreparation',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointBinding',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointPredecessor',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointRows',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointTransaction',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointTrustWorkflow',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditClosedSegmentReader',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransactionPolicy.php' => [
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Internal\\Audit\\Artifact\\AuditArtifactException',
                'BlackOps\\Internal\\Audit\\Protection\\AuditProtectionException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'Doctrine\\DBAL\\Connection',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTrustWorkflow.php' => [
                'BlackOps\\Core\\Audit\\AuditRecordTime',
                'BlackOps\\Internal\\Audit\\Artifact\\AuditArtifactVerifier',
                'BlackOps\\Internal\\Audit\\Artifact\\AuditCheckpointCodec',
                'BlackOps\\Internal\\Audit\\Artifact\\AuditTrustSnapshotCodec',
                'BlackOps\\Internal\\Audit\\Checkpoint\\AuditCheckpointPreparation',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'BlackOps\\Internal\\Identifier\\Uuidv7Generator',
                'DateTimeImmutable',
                'DateTimeZone',
                'Psr\\Clock\\ClockInterface',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentRowParser.php' => [
                'BlackOps\\Core\\Audit\\AuditValidationException',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditCheckpointInteger',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditSegmentLifecycle',
                'BlackOps\\Internal\\Audit\\Checkpoint\\PostgreSqlAuditSegmentTextFields',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreException',
                'BlackOps\\Internal\\Audit\\Storage\\AuditStoreFailure',
                'RuntimeException',
                'SensitiveParameter',
                'Throwable',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentLifecycle.php' => [
                'RuntimeException',
                'SensitiveParameter',
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentTextFields.php' => [
                'BlackOps\\Core\\Audit\\AuditRecordTime',
                'RuntimeException',
                'SensitiveParameter',
            ],
        ];
        foreach ($expected as &$dependencies) {
            sort($dependencies);
        }
        unset($dependencies);

        return $expected;
    }

    /** @return array<string,array<string,int>> */
    private function expectedCheckpointLocalCalls(): array
    {
        $expected = [
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparation.php' => [
                AuditCheckpointPreparationBody::class . '::read' => 1,
                AuditCheckpointPreparationRowShape::class . '::read' => 1,
            ],
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparationBody.php' => [],
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparationRowShape.php' => [
                PostgreSqlAuditCheckpointBytes::class . '::database' => 1,
                PostgreSqlAuditCheckpointInteger::class . '::read' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBinding.php' => [
                PostgreSqlAuditCheckpointBytes::class . '::completeInput' => 1,
                PostgreSqlAuditCheckpointBytes::class . '::persistUnsigned' => 1,
                PostgreSqlAuditCheckpointBytes::class . '::unsignedBytes' => 1,
                PostgreSqlAuditCheckpointBinding::class . '::completeInput' => 1,
                PostgreSqlAuditCheckpointBinding::class . '::completeRow' => 2,
                PostgreSqlAuditCheckpointBinding::class . '::persistUnsigned' => 1,
                PostgreSqlAuditCheckpointSegmentBinding::class . '::preparation' => 1,
                PostgreSqlAuditCheckpointStoredBinding::class . '::identity' => 2,
                PostgreSqlAuditCheckpointStoredBinding::class . '::row' => 1,
                PostgreSqlAuditCheckpointStoredBinding::class . '::stateVersion' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBytes.php' => [
                PostgreSqlAuditCheckpointBytes::class . '::bounded' => 2,
                PostgreSqlAuditCheckpointBytes::class . '::unsignedBytes' => 2,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointInteger.php' => [],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPredecessor.php' => [
                AuditCheckpointPreparation::class . '::fromRow' => 1,
                PostgreSqlAuditCheckpointBinding::class . '::completeInput' => 1,
                PostgreSqlAuditCheckpointBinding::class . '::persistUnsigned' => 1,
                PostgreSqlAuditCheckpointBinding::class . '::stateVersion' => 1,
                PostgreSqlAuditCheckpointBytes::class . '::database' => 1,
                PostgreSqlAuditCheckpointRows::class . '::predecessorCheckpoint' => 1,
                PostgreSqlAuditCheckpointRows::class . '::predecessorSegment' => 1,
                PostgreSqlAuditCheckpointSegmentBinding::class . '::predecessor' => 1,
                PostgreSqlAuditCheckpointStoredBinding::class . '::identity' => 1,
                PostgreSqlAuditSegmentRowParser::class . '::parse' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSegmentBinding.php' => [
                PostgreSqlAuditCheckpointSegmentBinding::class . '::boundary' => 2,
                PostgreSqlAuditCheckpointSegmentBinding::class . '::range' => 2,
                PostgreSqlAuditCheckpointSegmentBinding::class . '::tail' => 2,
                PostgreSqlAuditCheckpointStoredBinding::class . '::stateVersion' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStoredBinding.php' => [
                PostgreSqlAuditCheckpointBytes::class . '::database' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSchema.php' => [],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointRows.php' => [
                PostgreSqlAuditCheckpointSchema::class . '::checkpointsTable' => 3,
                PostgreSqlAuditCheckpointSchema::class . '::preparationsTable' => 5,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStore.php' => [
                AuditCheckpointPreparation::class . '::boundary' => 1,
                AuditCheckpointPreparation::class . '::fromRow' => 1,
                AuditCheckpointPreparation::class . '::unsigned' => 1,
                PostgreSqlAuditCheckpointBinding::class . '::completeInput' => 1,
                PostgreSqlAuditCheckpointBinding::class . '::completeReplay' => 1,
                PostgreSqlAuditCheckpointBinding::class . '::persistUnsigned' => 1,
                PostgreSqlAuditCheckpointBinding::class . '::preparation' => 1,
                PostgreSqlAuditCheckpointPredecessor::class . '::load' => 1,
                PostgreSqlAuditCheckpointRows::class . '::complete' => 1,
                PostgreSqlAuditCheckpointRows::class . '::insertComplete' => 1,
                PostgreSqlAuditCheckpointRows::class . '::preparation' => 1,
                PostgreSqlAuditCheckpointTransaction::class . '::abort' => 1,
                PostgreSqlAuditCheckpointTransaction::class . '::beginRoot' => 1,
                PostgreSqlAuditCheckpointTransaction::class . '::commit' => 2,
                PostgreSqlAuditCheckpointTrustWorkflow::class . '::verifyPersistence' => 1,
                PostgreSqlAuditClosedSegmentReader::class . '::proveInCurrentTransaction' => 1,
                PostgreSqlAuditCheckpointPreparationWorkflow::class . '::run' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php' => [
                PostgreSqlAuditCheckpointTransactionPolicy::class . '::map' => 2,
                PostgreSqlAuditCheckpointTransactionPolicy::class . '::owned' => 1,
                PostgreSqlAuditCheckpointTransactionPolicy::class . '::root' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPreparationWorkflow.php' => [
                AuditCheckpointPreparation::class . '::boundary' => 2,
                AuditCheckpointPreparation::class . '::fromRow' => 2,
                AuditCheckpointPreparation::class . '::unsigned' => 2,
                PostgreSqlAuditCheckpointBinding::class . '::preparation' => 2,
                PostgreSqlAuditCheckpointBinding::class . '::sealedReplay' => 1,
                PostgreSqlAuditCheckpointPredecessor::class . '::load' => 1,
                PostgreSqlAuditCheckpointRows::class . '::checkpointIdExists' => 1,
                PostgreSqlAuditCheckpointRows::class . '::complete' => 1,
                PostgreSqlAuditCheckpointRows::class . '::insertPreparation' => 1,
                PostgreSqlAuditCheckpointRows::class . '::preparation' => 1,
                PostgreSqlAuditCheckpointTransaction::class . '::abort' => 1,
                PostgreSqlAuditCheckpointTransaction::class . '::beginRoot' => 1,
                PostgreSqlAuditCheckpointTransaction::class . '::commit' => 2,
                PostgreSqlAuditCheckpointTrustWorkflow::class . '::newPreparation' => 1,
                PostgreSqlAuditClosedSegmentReader::class . '::proveInCurrentTransaction' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransactionPolicy.php' => [],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTrustWorkflow.php' => [
                AuditCheckpointPreparation::class . '::signingKeyFingerprint' => 1,
                AuditCheckpointPreparation::class . '::unsigned' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentGraph.php' => [
                PostgreSqlAuditClosedSegmentEndpoint::class . '::assertMatches' => 1,
                PostgreSqlAuditClosedSegmentNeighbors::class . '::previous' => 1,
                PostgreSqlAuditClosedSegmentNeighbors::class . '::successor' => 1,
                PostgreSqlAuditClosedSegmentRelations::class . '::assertToken' => 1,
                PostgreSqlAuditPartitionStateParser::class . '::parse' => 1,
                PostgreSqlAuditSegmentRowParser::class . '::parse' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentEndpoint.php' => [
                PostgreSqlAuditCheckpointInteger::class . '::read' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentNeighbors.php' => [
                PostgreSqlAuditClosedSegmentRelations::class . '::assertToken' => 2,
                PostgreSqlAuditClosedSegmentRelations::class . '::edge' => 2,
                PostgreSqlAuditClosedSegmentRelations::class . '::neighborRow' => 2,
                PostgreSqlAuditSegmentRowParser::class . '::parse' => 2,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentQueries.php' => [],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php' => [
                PostgreSqlAuditClosedSegmentGraph::class . '::endpoint' => 1,
                PostgreSqlAuditClosedSegmentGraph::class . '::previous' => 1,
                PostgreSqlAuditClosedSegmentGraph::class . '::state' => 1,
                PostgreSqlAuditClosedSegmentGraph::class . '::successor' => 1,
                PostgreSqlAuditClosedSegmentGraph::class . '::target' => 1,
                PostgreSqlAuditClosedSegmentQueries::class . '::endpoint' => 1,
                PostgreSqlAuditClosedSegmentQueries::class . '::lockPartition' => 1,
                PostgreSqlAuditClosedSegmentQueries::class . '::previous' => 1,
                PostgreSqlAuditClosedSegmentQueries::class . '::state' => 1,
                PostgreSqlAuditClosedSegmentQueries::class . '::successor' => 1,
                PostgreSqlAuditClosedSegmentQueries::class . '::target' => 1,
                PostgreSqlAuditClosedSegmentRecordProof::class . '::prove' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecordProof.php' => [
                PostgreSqlAuditClosedSegmentPages::class . '::read' => 1,
                PostgreSqlAuditClosedSegmentQueries::class . '::tenantMismatch' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentPages.php' => [
                PostgreSqlAuditClosedSegmentQueries::class . '::records' => 1,
                PostgreSqlAuditClosedSegmentRecord::class . '::read' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecord.php' => [
                PostgreSqlAuditStoredRecordHydrator::class . '::hydrate' => 1,
                AuditProtectedBodyCodec::class . '::unprotectRecord' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRelations.php' => [],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditPartitionStateParser.php' => [
                PostgreSqlAuditCheckpointInteger::class . '::read' => 2,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentLifecycle.php' => [],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentRowParser.php' => [
                PostgreSqlAuditCheckpointInteger::class . '::read' => 3,
                PostgreSqlAuditSegmentLifecycle::class . '::assertValid' => 1,
                PostgreSqlAuditSegmentTextFields::class . '::read' => 1,
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentTextFields.php' => [],
        ];
        foreach ($expected as &$calls) {
            ksort($calls);
        }
        unset($calls);

        return $expected;
    }

    /** @return array<string,array<string,array<string,int>>> */
    private function expectedCheckpointOwnInstanceCalls(): array
    {
        return [
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPreparationWorkflow.php' => [
                'run' => [
                    'create' => 1,
                    'retry' => 1,
                ],
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php' => [
                'beginRoot' => [
                    'closeOnce' => 2,
                    'rollbackOwned' => 1,
                ],
                'commit' => [
                    'closeOnce' => 1,
                    'rollbackOwned' => 1,
                ],
                'abort' => [
                    'closeOnce' => 3,
                    'rollbackOwned' => 2,
                ],
            ],
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php' => [
                'proveInCurrentTransaction' => [
                    'assertConnection' => 1,
                    'assertIdentifiers' => 1,
                    'proofResult' => 1,
                    'safeFailure' => 1,
                ],
                'assertIdentifiers' => [],
            ],
        ];
    }

    /** @return list<string> */
    private function checkpointDependencyReferences(string $source, string $owner): array
    {
        $nodes = $this->parseSource($source);
        $known = array_fill_keys($this->checkpointDependencyUniverse(), true);
        $imports = [];
        foreach (array_merge(
            new NodeFinder()->findInstanceOf($nodes, GroupUse::class),
            new NodeFinder()->findInstanceOf($nodes, Use_::class),
        ) as $node) {
            if ($node instanceof GroupUse) {
                foreach ($node->uses as $use) {
                    $imports[spl_object_id($use->name)] = true;
                }
                $imports[spl_object_id($node->prefix)] = true;
            } elseif ($node instanceof Use_) {
                foreach ($node->uses as $use) {
                    $imports[spl_object_id($use->name)] = true;
                }
            }
        }

        $dependencies = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if (isset($imports[spl_object_id($name)])) {
                continue;
            }
            $resolved = $this->resolvedName($name);
            if (isset($known[$resolved]) && $resolved !== $owner) {
                $dependencies[$resolved] = true;
            }
        }
        $result = array_keys($dependencies);
        sort($result);

        return $result;
    }

    /** @return array<string,int> */
    private function checkpointLocalCallInventory(string $source, string $owner): array
    {
        $code = $this->codeWithoutCommentsAndStrings($source);
        $types = [];
        foreach ($this->checkpointTypesList() as $type) {
            $types[substr($type, strrpos($type, '\\') + 1)] = $type;
        }
        $types['self'] = $owner;
        $calls = [];
        if (
            preg_match_all(
                '/\b([A-Za-z_][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)\s*\(/',
                $code,
                $matches,
                PREG_SET_ORDER,
            ) !== false
        ) {
            foreach ($matches as $match) {
                $type = $types[$match[1]] ?? null;
                if ($type === null) {
                    continue;
                }
                $key = $type . '::' . $match[2];
                $calls[$key] = ($calls[$key] ?? 0) + 1;
            }
        }

        $properties = match ($owner) {
            PostgreSqlAuditCheckpointPredecessor::class => ['rows' => PostgreSqlAuditCheckpointRows::class],
            PostgreSqlAuditCheckpointRows::class => ['schema' => PostgreSqlAuditCheckpointSchema::class],
            PostgreSqlAuditCheckpointStore::class => [
                'transaction' => PostgreSqlAuditCheckpointTransaction::class,
                'reader' => PostgreSqlAuditClosedSegmentReader::class,
                'rows' => PostgreSqlAuditCheckpointRows::class,
                'predecessor' => PostgreSqlAuditCheckpointPredecessor::class,
                'trustWorkflow' => PostgreSqlAuditCheckpointTrustWorkflow::class,
                'preparationWorkflow' => PostgreSqlAuditCheckpointPreparationWorkflow::class,
            ],
            PostgreSqlAuditCheckpointPreparationWorkflow::class => [
                'reader' => PostgreSqlAuditClosedSegmentReader::class,
                'transaction' => PostgreSqlAuditCheckpointTransaction::class,
                'rows' => PostgreSqlAuditCheckpointRows::class,
                'predecessor' => PostgreSqlAuditCheckpointPredecessor::class,
                'trustWorkflow' => PostgreSqlAuditCheckpointTrustWorkflow::class,
            ],
            PostgreSqlAuditClosedSegmentReader::class => [
                'queries' => PostgreSqlAuditClosedSegmentQueries::class,
                'recordProof' => PostgreSqlAuditClosedSegmentRecordProof::class,
            ],
            PostgreSqlAuditClosedSegmentRecordProof::class => [
                'queries' => PostgreSqlAuditClosedSegmentQueries::class,
                'pages' => PostgreSqlAuditClosedSegmentPages::class,
            ],
            PostgreSqlAuditClosedSegmentPages::class => [
                'queries' => PostgreSqlAuditClosedSegmentQueries::class,
                'record' => PostgreSqlAuditClosedSegmentRecord::class,
            ],
            PostgreSqlAuditClosedSegmentRecord::class => [
                'hydrator' => PostgreSqlAuditStoredRecordHydrator::class,
                'protection' => AuditProtectedBodyCodec::class,
            ],
            default => [],
        };
        foreach ($properties as $property => $type) {
            $pattern = '/\\$this->' . preg_quote($property, '/') . '->([A-Za-z_][A-Za-z0-9_]*)\s*\(/';
            if (preg_match_all($pattern, $code, $matches, PREG_SET_ORDER) === false) {
                continue;
            }
            foreach ($matches as $match) {
                $key = $type . '::' . $match[1];
                $calls[$key] = ($calls[$key] ?? 0) + 1;
            }
        }
        $variables = match ($owner) {
            PostgreSqlAuditCheckpointStore::class => ['preparation' => AuditCheckpointPreparation::class],
            PostgreSqlAuditCheckpointPreparationWorkflow::class => [
                'preparation' => AuditCheckpointPreparation::class,
            ],
            PostgreSqlAuditCheckpointTrustWorkflow::class => ['preparation' => AuditCheckpointPreparation::class],
            default => [],
        };
        foreach ($variables as $variable => $type) {
            $pattern = '/\\$' . preg_quote($variable, '/') . '->([A-Za-z_][A-Za-z0-9_]*)\s*\(/';
            if (preg_match_all($pattern, $code, $matches, PREG_SET_ORDER) === false) {
                continue;
            }
            foreach ($matches as $match) {
                $key = $type . '::' . $match[1];
                $calls[$key] = ($calls[$key] ?? 0) + 1;
            }
        }
        ksort($calls);

        return $calls;
    }

    /** @return array<string,int> */
    private function checkpointOwnInstanceCallInventory(string $source, string $ownerMethod): array
    {
        $method = null;
        foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), ClassMethod::class) as $candidate) {
            if ($candidate->name->toString() !== $ownerMethod) {
                continue;
            }
            if ($method !== null) {
                throw new \LogicException('Duplicate checkpoint method: ' . $ownerMethod);
            }
            $method = $candidate;
        }
        if (!$method instanceof ClassMethod) {
            throw new \LogicException('Missing checkpoint method: ' . $ownerMethod);
        }

        $calls = [];
        foreach (new NodeFinder()->findInstanceOf($method->stmts ?? [], MethodCall::class) as $call) {
            if (!$call->var instanceof Variable || $call->var->name !== 'this' || !$call->name instanceof Identifier) {
                continue;
            }
            $name = $call->name->toString();
            $calls[$name] = ($calls[$name] ?? 0) + 1;
        }
        ksort($calls);

        return $calls;
    }

    private function checkpointTypeForPath(string $path): string
    {
        return match ($path) {
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparation.php' => AuditCheckpointPreparation::class,
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparationBody.php' => AuditCheckpointPreparationBody::class,
            'src/Internal/Audit/Checkpoint/AuditCheckpointPreparationRowShape.php'
                => AuditCheckpointPreparationRowShape::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBinding.php'
                => PostgreSqlAuditCheckpointBinding::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBytes.php' => PostgreSqlAuditCheckpointBytes::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointInteger.php'
                => PostgreSqlAuditCheckpointInteger::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPredecessor.php'
                => PostgreSqlAuditCheckpointPredecessor::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSegmentBinding.php'
                => PostgreSqlAuditCheckpointSegmentBinding::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStoredBinding.php'
                => PostgreSqlAuditCheckpointStoredBinding::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPreparationWorkflow.php'
                => PostgreSqlAuditCheckpointPreparationWorkflow::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSchema.php'
                => PostgreSqlAuditCheckpointSchema::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointRows.php' => PostgreSqlAuditCheckpointRows::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php'
                => PostgreSqlAuditCheckpointTransaction::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransactionPolicy.php'
                => PostgreSqlAuditCheckpointTransactionPolicy::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTrustWorkflow.php'
                => PostgreSqlAuditCheckpointTrustWorkflow::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentEndpoint.php'
                => PostgreSqlAuditClosedSegmentEndpoint::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentGraph.php'
                => PostgreSqlAuditClosedSegmentGraph::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentNeighbors.php'
                => PostgreSqlAuditClosedSegmentNeighbors::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentPages.php'
                => PostgreSqlAuditClosedSegmentPages::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentQueries.php'
                => PostgreSqlAuditClosedSegmentQueries::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecord.php'
                => PostgreSqlAuditClosedSegmentRecord::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php'
                => PostgreSqlAuditClosedSegmentReader::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecordProof.php'
                => PostgreSqlAuditClosedSegmentRecordProof::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRelations.php'
                => PostgreSqlAuditClosedSegmentRelations::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditPartitionStateParser.php'
                => PostgreSqlAuditPartitionStateParser::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentLifecycle.php'
                => PostgreSqlAuditSegmentLifecycle::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentRowParser.php'
                => PostgreSqlAuditSegmentRowParser::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentTextFields.php'
                => PostgreSqlAuditSegmentTextFields::class,
            'src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStore.php' => PostgreSqlAuditCheckpointStore::class,
            default => throw new \LogicException('Unknown checkpoint source: ' . $path),
        };
    }

    /** @return list<string> */
    private function checkpointDependencyUniverse(): array
    {
        $known = [];
        foreach ($this->expectedCheckpointDependencies() as $dependencies) {
            foreach ($dependencies as $dependency) {
                $known[$dependency] = true;
            }
        }
        foreach ([
            'BlackOps\\Core\\Attribute\\PublicApi',
            'BlackOps\\Internal\\Audit\\Artifact\\AuditArtifactSigner',
            'BlackOps\\Internal\\Audit\\Record\\AuditRecordFinalizer',
            'BlackOps\\Internal\\Audit\\Record\\CompleteAuditRecord',
            'BlackOps\\Internal\\Audit\\Storage\\AuditSegmentCloseResult',
        ] as $forbidden) {
            $known[$forbidden] = true;
        }

        return array_keys($known);
    }

    /** @param array<string,string> $values @param array<string,true> $targets */
    private function checkpointValue(Node $node, array $values, array $targets): ?string
    {
        if ($node instanceof String_) {
            $value = trim($node->value);
            if (isset($targets[$value])) {
                return $value;
            }
            $class = str_contains($value, '::') ? strstr($value, '::', true) : false;
            return is_string($class) && isset($targets[ltrim($class, '\\')]) ? ltrim($class, '\\') : null;
        }
        if ($node instanceof Variable && is_string($node->name)) {
            return $values[$node->name] ?? null;
        }
        if (
            $node instanceof ClassConstFetch
            && $node->class instanceof Name
            && $node->name instanceof Identifier
            && strtolower($node->name->toString()) === 'class'
        ) {
            $resolved = $this->resolvedName($node->class);
            return isset($targets[$resolved]) ? $resolved : null;
        }
        if ($node instanceof Array_ && count($node->items) === 2) {
            $class = $node->items[0] ?? null;
            $method = $node->items[1] ?? null;
            if (
                $class instanceof ArrayItem
                && $method instanceof ArrayItem
                && $class->key === null
                && $method->key === null
                && $method->value instanceof String_
            ) {
                return $this->checkpointValue($class->value, $values, $targets);
            }
        }
        return null;
    }

    /** @return list<string> */
    private function checkpointSourceViolations(string $source): array
    {
        $code = $this->codeWithoutCommentsAndStrings($source);
        $violations = [];
        foreach ([
            'PublicApi',
            'AuditArtifactSigner',
            'AuditSegmentCloseResult',
            'CompleteAuditRecord',
            'AuditRecordFinalizer',
            'sodium_crypto_sign',
            'seed_keypair',
            'Closure::fromCallable',
            'register_shutdown_function',
            'register_tick_function',
            'set_error_handler',
            'set_exception_handler',
            'spl_autoload_register',
            '->append(',
            '::append(',
        ] as $forbidden) {
            if (str_contains($code, $forbidden)) {
                $violations[] = 'forbidden:' . $forbidden;
            }
        }
        if (preg_match('/(?:->|::)\s*(?:append[A-Za-z0-9_]*|sign)\s*\(/', $code) === 1) {
            $violations[] = 'forbidden:write-or-sign-call';
        }
        foreach (['release', 'control', 'provider', 'lookup', 'seed'] as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $code) === 1) {
                $violations[] = 'forbidden-word:' . $word;
            }
        }

        $reader = str_contains($code, 'class PostgreSqlAuditClosedSegmentReader');
        if ($reader) {
            if ($this->directMethodCallCount($source, 'unprotectRecord') !== 0) {
                $violations[] = 'reader-unprotect-owner';
            }
            if (substr_count($code, 'pg_advisory_xact_lock(') !== 0) {
                $violations[] = 'reader-sql-owner';
            }
            if (
                str_contains($code, '->beginTransaction(')
                || str_contains($code, '->commit(')
                || str_contains($code, '->rollBack(')
                || str_contains($code, '->close(')
            ) {
                $violations[] = 'reader-transaction-owner';
            }
            if (str_contains($code, '->protectRecord(') || str_contains($code, '->unprotectOutbox(')) {
                $violations[] = 'reader-protection-direction';
            }
        }
        if ($this->declaresCheckpointClass($source, PostgreSqlAuditClosedSegmentRecordProof::class)) {
            if ($this->directMethodCallCount($source, 'unprotectRecord') !== 0) {
                $violations[] = 'record-proof-unprotect-owner';
            }
            if (substr_count($code, '->protectRecord(') > 0 || substr_count($code, '->unprotectOutbox(') > 0) {
                $violations[] = 'record-proof-protection-direction';
            }
            if (
                str_contains($code, 'CompleteAuditRecord')
                || str_contains($code, 'AuditSegmentCloseResult')
                || str_contains($code, 'AuditRecordFinalizer')
            ) {
                $violations[] = 'record-proof-result-edge';
            }
        }
        if ($this->declaresCheckpointClass($source, PostgreSqlAuditClosedSegmentRecord::class)) {
            if ($this->directMethodCallCount($source, 'unprotectRecord') !== 1) {
                $violations[] = 'record-unprotect-owner';
            }
            if ($this->directMethodCallCount($source, 'hydrate') !== 1) {
                $violations[] = 'record-hydrate-owner';
            }
            if (substr_count($code, '->protectRecord(') > 0 || substr_count($code, '->unprotectOutbox(') > 0) {
                $violations[] = 'record-protection-direction';
            }
            if (
                str_contains($code, 'CompleteAuditRecord')
                || str_contains($code, 'AuditSegmentCloseResult')
                || str_contains($code, 'AuditRecordFinalizer')
            ) {
                $violations[] = 'record-result-edge';
            }
        }
        if (str_contains($code, 'class PostgreSqlAuditClosedSegmentQueries')) {
            if (!$this->containsSqlLiteral($source, 'pg_advisory_xact_lock(')) {
                $violations[] = 'partition-fence-call';
            }
            if ($this->partitionFenceKeyCount($source) !== 1) {
                $violations[] = 'partition-fence-key';
            }
            if (!$this->containsSqlLiteral($source, 'hashtextextended')) {
                $violations[] = 'partition-fence-hash';
            }
        }
        if ($this->declaresCheckpointClass($source, PostgreSqlAuditCheckpointStore::class)) {
            foreach ([
                'new PostgreSqlAuditClosedSegmentReader(',
                'new PostgreSqlAuditCheckpointTransaction(',
                '$this->transaction->beginRoot(',
                '$this->transaction->commit(',
                '$this->transaction->abort(',
            ] as $required) {
                if (!str_contains($code, $required)) {
                    $violations[] = 'store-required:' . $required;
                }
            }
            if (
                str_contains($code, 'beginTransaction(')
                || str_contains($code, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')
            ) {
                $violations[] = 'store-transaction-owner';
            }
        }
        if ($this->declaresCheckpointClass($source, PostgreSqlAuditCheckpointTransaction::class)) {
            foreach ([
                'beginTransaction(',
                '->commit(',
                'rollBack(',
                'close(',
            ] as $required) {
                if (substr_count($code, $required) !== 1) {
                    $violations[] = 'transaction-required:' . $required;
                }
            }
            if (!$this->containsSqlLiteral($source, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')) {
                $violations[] = 'transaction-required:isolation';
            }
            if (preg_match('/public function\s+(?:rollback|rollbackAndClose|mapFailure)\s*\(/', $code) === 1) {
                $violations[] = 'transaction-public-cleanup';
            }
        }
        if ($this->declaresCheckpointClass($source, PostgreSqlAuditCheckpointTransactionPolicy::class)) {
            foreach (['beginTransaction(', '->commit(', '->rollBack(', '->close('] as $forbidden) {
                if (str_contains($code, $forbidden)) {
                    $violations[] = 'policy-transaction-effect:' . $forbidden;
                }
            }
            if ($this->containsSqlLiteral($source, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')) {
                $violations[] = 'policy-transaction-effect:isolation';
            }
        }
        foreach ($this->sqlMutationViolations($source) as $mutation) {
            $violations[] = 'sql-mutation:' . $mutation;
        }
        sort($violations);
        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function sqlMutationViolations(string $source): array
    {
        $tokens = token_get_all($source);
        $violations = [];
        foreach ($tokens as $index => $token) {
            if (
                !is_array($token)
                || !in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_START_HEREDOC, T_ENCAPSED_AND_WHITESPACE], true)
            ) {
                continue;
            }
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $value = str_replace('\\\\', '\\', substr($token[1], 1, -1));
            } elseif ($token[0] === T_START_HEREDOC) {
                $value = '';
                for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                    if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_END_HEREDOC) {
                        break;
                    }
                    $value .= is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor];
                }
            } else {
                $value = $token[1];
            }
            $normalized = strtoupper((string) preg_replace('/\s+/', ' ', $value));
            $normalized = trim((string) preg_replace('/\bFOR\s+UPDATE\b/', '', $normalized));
            $mutation = null;
            if (preg_match('/\bUPDATE\s+[A-Z0-9_".]+/i', $normalized) === 1) {
                $mutation = 'UPDATE';
            } elseif (preg_match('/\bDELETE\s+FROM\b/i', $normalized) === 1) {
                $mutation = 'DELETE';
            } elseif (preg_match('/\bUPSERT\b/i', $normalized) === 1) {
                $mutation = 'UPSERT';
            } elseif (preg_match('/\bINSERT\b.*\bON\s+CONFLICT\b.*\bDO\s+UPDATE\b/is', $normalized) === 1) {
                $mutation = 'INSERT-ON-CONFLICT-UPDATE';
            }
            if ($mutation !== null && $this->isSqlLiteral($tokens, $index)) {
                $violations[] = $mutation;
            }
        }
        sort($violations);
        return array_values(array_unique($violations));
    }

    /** @param list<array{0:int,1:string,2:int}|string> $tokens */
    private function isSqlLiteral(array $tokens, int $index): bool
    {
        $previous = $index - 1;
        while (
            $previous >= 0
            && is_array($tokens[$previous])
            && in_array($tokens[$previous][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ) {
            $previous--;
        }
        if ($previous >= 0 && ($tokens[$previous] === '"' || $tokens[$previous] === "'")) {
            $previous--;
            while (
                $previous >= 0
                && is_array($tokens[$previous])
                && in_array($tokens[$previous][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                $previous--;
            }
        }
        if ($previous >= 0 && $tokens[$previous] === '=') {
            $before = $previous - 1;
            while ($before >= 0 && is_array($tokens[$before]) && $tokens[$before][0] === T_WHITESPACE) {
                $before--;
            }
            return (
                $before >= 0
                && is_array($tokens[$before])
                && $tokens[$before][0] === T_VARIABLE
                && preg_match('/\$(?:sql|query|statement)$/i', $tokens[$before][1]) === 1
            );
        }
        if ($previous < 0 || $tokens[$previous] !== '(' && $tokens[$previous] !== ',') {
            return false;
        }
        $cursor = $previous - 1;
        while ($cursor >= 0 && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
            $cursor--;
        }
        if ($cursor >= 0 && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_STRING) {
            return in_array(
                strtolower($tokens[$cursor][1]),
                [
                    'execute',
                    'executequery',
                    'executestatement',
                    'fetchallassociative',
                    'fetchassociative',
                    'fetchone',
                    'prepare',
                    'query',
                ],
                true,
            );
        }
        return false;
    }

    private function directMethodCallCount(string $source, string $method): int
    {
        $code = $this->codeWithoutCommentsAndStrings($source);
        $count = preg_match_all('/->\s*' . preg_quote($method, '/') . '\s*\(/', $code);
        self::assertIsInt($count);

        return $count;
    }

    private function partitionFenceKeyCount(string $source): int
    {
        $tokens = token_get_all($source);
        $count = 0;
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $value = str_replace('\\\\', '\\', substr($token[1], 1, -1));
            if (!str_contains($value, 'blackops.audit.partition.v1:')) {
                continue;
            }
            $previous = $index - 1;
            while (
                $previous >= 0
                && is_array($tokens[$previous])
                && in_array($tokens[$previous][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                $previous--;
            }
            if ($previous < 0 || !is_array($tokens[$previous]) || $tokens[$previous][0] !== T_DOUBLE_ARROW) {
                continue;
            }
            $key = $previous - 1;
            while (
                $key >= 0
                && is_array($tokens[$key])
                && in_array($tokens[$key][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                $key--;
            }
            if (
                $key >= 0
                && is_array($tokens[$key])
                && $tokens[$key][0] === T_CONSTANT_ENCAPSED_STRING
                && trim(str_replace('\\\\', '\\', substr($tokens[$key][1], 1, -1))) === 'lock_key'
            ) {
                $count++;
            }
        }

        return $count;
    }

    /** @return list<list<string>> */
    private function constructorGraphArguments(string $source, string $target): array
    {
        $arguments = [];
        foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), New_::class) as $new) {
            if (!$new->class instanceof Name || $this->resolvedName($new->class) !== $target) {
                continue;
            }
            $call = [];
            foreach ($new->args as $argument) {
                if ($argument->value instanceof Variable && is_string($argument->value->name)) {
                    $call[] = $argument->value->name;
                    continue;
                }
                if (
                    $argument->value instanceof PropertyFetch
                    && $argument->value->var instanceof Variable
                    && $argument->value->var->name === 'this'
                    && $argument->value->name instanceof Identifier
                ) {
                    $call[] = $argument->value->name->toString();
                    continue;
                }
                $call[] = $argument->value instanceof Node
                    ? get_class($argument->value)
                    : get_debug_type($argument->value);
            }
            $arguments[] = $call;
        }

        return $arguments;
    }

    private function containsSqlLiteral(string $source, string $needle): bool
    {
        $tokens = token_get_all($source);
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_START_HEREDOC], true)) {
                continue;
            }
            $value = '';
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $value = str_replace('\\\\', '\\', substr($token[1], 1, -1));
            } else {
                for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                    if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_END_HEREDOC) {
                        break;
                    }
                    $value .= is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor];
                }
            }
            if (str_contains(strtoupper($value), strtoupper($needle)) && $this->isSqlLiteral($tokens, $index)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,string> */
    private function sourceFiles(string $root): array
    {
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = realpath($file->getPathname());
            self::assertIsString($path);
            $source = file_get_contents($path);
            self::assertIsString($source);
            $sources[str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1))] = $source;
        }
        ksort($sources);
        return $sources;
    }

    /** @return list<Node> */
    private function parseSource(string $source): array
    {
        $nodes = new ParserFactory()
            ->createForNewestSupportedVersion()
            ->parse($source);
        self::assertIsArray($nodes);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $resolved = $traverser->traverse($nodes);
        self::assertIsArray($resolved);
        return $resolved;
    }

    private function declaresCheckpointClass(string $source, string $target): bool
    {
        try {
            foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), Class_::class) as $class) {
                if (
                    $class->namespacedName instanceof Name
                    && $this->resolvedName($class->namespacedName) === ltrim($target, '\\')
                ) {
                    return true;
                }
            }
        } catch (\Throwable) {
            $code = $this->codeWithoutCommentsAndStrings($source);
            if (preg_match('/\\bnamespace\\s+([A-Za-z_][A-Za-z0-9_\\\\]*)\\s*;/i', $code, $namespaceMatch) !== 1) {
                return false;
            }
            $namespace = ltrim($namespaceMatch[1], '\\');
            $short = substr($target, strrpos($target, '\\') + 1);
            return (
                preg_match('/\\bclass\\s+' . preg_quote($short, '/') . '\\b/', $code) === 1
                && $namespace . '\\' . $short === ltrim($target, '\\')
            );
        }

        return false;
    }

    private function resolvedName(Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');
        if ($resolved instanceof Name) {
            return ltrim($resolved->toString(), '\\');
        }
        return ltrim($name->toString(), '\\');
    }

    private function codeWithoutCommentsAndStrings(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (
                is_array($token)
                && in_array(
                    $token[0],
                    [
                        T_COMMENT,
                        T_DOC_COMMENT,
                        T_CONSTANT_ENCAPSED_STRING,
                        T_ENCAPSED_AND_WHITESPACE,
                    ],
                    true,
                )
            ) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }
        return $code;
    }

    /** @return list<string> */
    private function sourcePaths(): array
    {
        $paths = [];
        foreach (glob(self::SOURCE_ROOT . '/*.php') ?: [] as $path) {
            $paths[] = 'src/Internal/Audit/Checkpoint/' . basename($path);
        }
        sort($paths);
        return $paths;
    }

    /** @return array<string,string> */
    private function sources(): array
    {
        $sources = [];
        foreach ($this->sourcePaths() as $relative) {
            $source = file_get_contents(__DIR__ . '/../../src/' . substr($relative, 4));
            self::assertIsString($source);
            $sources[$relative] = $source;
        }
        return $sources;
    }

    private function typeName(ReflectionMethod $method): ?string
    {
        return $this->reflectionTypeName($method->getReturnType());
    }

    private function parameterType(ReflectionParameter $parameter): string
    {
        return $this->reflectionTypeName($parameter->getType()) ?? '';
    }

    private function propertyType(ReflectionProperty $property): string
    {
        return $this->reflectionTypeName($property->getType()) ?? '';
    }

    private function reflectionTypeName(?ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn(ReflectionType $member): string => $this->reflectionTypeName($member) ?? '',
                $type->getTypes(),
            ));
        }
        if (!$type instanceof ReflectionNamedType) {
            return (string) $type;
        }
        $name = $type->getName();
        return ($type->allowsNull() && $name !== 'mixed' ? '?' : '') . $name;
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Expr\ShellExec;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class AuditAnchorStateArchitectureTest extends TestCase
{
    private const string SOURCE_ROOT = __DIR__ . '/../../src';

    private const string ANCHOR_NAMESPACE = 'BlackOps\\Internal\\Audit\\Anchor';

    /** @var list<string> */
    private const array TYPES = [
        'AuditAnchorBinding',
        'AuditAnchorCanonical',
        'AuditAnchorClaims',
        'AuditAnchorConfiguration',
        'AuditAnchorException',
        'AuditAnchorExpectation',
        'AuditAnchorExternalOutcome',
        'AuditAnchorFailure',
        'AuditAnchorFrame',
        'AuditAnchorIntent',
        'AuditAnchorIntentShape',
        'AuditAnchorLeaseOrigin',
        'AuditAnchorPostCallTransition',
        'AuditAnchorPreCallTransition',
        'AuditAnchorPreparedShape',
        'AuditAnchorReceiptBody',
        'AuditAnchorReceiptShape',
        'AuditAnchorReceiptTransition',
        'AuditAnchorReconciliationShape',
        'AuditAnchorScalar',
        'AuditAnchorState',
        'AuditAnchorTime',
        'AuditAnchorTransitionGuard',
    ];

    /** @var array<string,array<string,string>> */
    private const array METHOD_SURFACE = [
        'AuditAnchorBinding' => [
            '__construct' => 'private',
            'fromArray' => 'public static',
            'toArray' => 'public',
            'assertIntentAssociation' => 'public static',
            '__debugInfo' => 'public',
            '__serialize' => 'public',
            '__unserialize' => 'public',
        ],
        'AuditAnchorCanonical' => [
            'stateHash' => 'public static',
            'idempotencyKeyHash' => 'public static',
            'receiptPreimageHash' => 'public static',
            'receiptBytesHash' => 'public static',
            'jcs' => 'public static',
            'digestParts' => 'private static',
            'digest' => 'private static',
            'typedDigest' => 'private static',
        ],
        'AuditAnchorClaims' => [
            '__construct' => 'public',
            'externalSubmission' => 'public',
            'preCallRetry' => 'public',
            'externalReconciliation' => 'public',
            'claim' => 'private',
        ],
        'AuditAnchorConfiguration' => [
            '__construct' => 'public',
            'assertSeconds' => 'private static',
        ],
        'AuditAnchorException' => [
            '__construct' => 'public',
            'failure' => 'public',
            'code' => 'public',
        ],
        'AuditAnchorExpectation' => [
            '__construct' => 'public',
            'toArray' => 'public',
            '__debugInfo' => 'public',
            '__serialize' => 'public',
            '__unserialize' => 'public',
        ],
        'AuditAnchorExternalOutcome' => [
            'result' => 'public static',
            'reconciliation' => 'public static',
            'lookup' => 'public static',
            'nullableInteger' => 'private static',
            'positiveInteger' => 'private static',
        ],
        'AuditAnchorFrame' => [
            '__construct' => 'private',
            'create' => 'public static',
            'restore' => 'public static',
            'intent' => 'public',
            'binding' => 'public',
            'leaseOrigin' => 'public',
            'toArray' => 'public',
            '__debugInfo' => 'public',
            '__serialize' => 'public',
            '__unserialize' => 'public',
        ],
        'AuditAnchorIntent' => [
            '__construct' => 'private',
            'fromArray' => 'public static',
            'toArray' => 'public',
            'state' => 'public',
            'version' => 'public',
            'assertTransitionImmutable' => 'public static',
            '__debugInfo' => 'public',
            '__serialize' => 'public',
            '__unserialize' => 'public',
        ],
        'AuditAnchorIntentShape' => [
            'normalize' => 'public static',
            'assertClosed' => 'public static',
            'assertText' => 'public static',
            'assertHash' => 'public static',
            'assertPartition' => 'public static',
        ],
        'AuditAnchorLeaseOrigin' => [
            '__construct' => 'private',
            'fromArray' => 'public static',
            'fromClaim' => 'public static',
            'positiveInteger' => 'private static',
            'toArray' => 'public',
            'assertAssociation' => 'public static',
            '__debugInfo' => 'public',
            '__serialize' => 'public',
            '__unserialize' => 'public',
        ],
        'AuditAnchorPostCallTransition' => [
            '__construct' => 'public',
            'result' => 'public',
            'reconcile' => 'public',
            'reclaim' => 'public',
            'active' => 'private',
            'accepted' => 'private static',
        ],
        'AuditAnchorPreCallTransition' => [
            '__construct' => 'public',
            'lookup' => 'public',
            'callStart' => 'public',
            'reclaim' => 'public',
            'activePending' => 'private',
            'accepted' => 'private static',
        ],
        'AuditAnchorPreparedShape' => [
            'assert' => 'public static',
            'prepared' => 'private static',
            'rejected' => 'private static',
            'leased' => 'private static',
        ],
        'AuditAnchorReceiptBody' => [
            'build' => 'public static',
            'assertPersistedHashes' => 'public static',
            'assertPublicReceiptHash' => 'public static',
            'unsignedBody' => 'private static',
        ],
        'AuditAnchorReceiptShape' => [
            'assert' => 'public static',
            'assertPublicReceiptHash' => 'public static',
        ],
        'AuditAnchorReceiptTransition' => [
            '__construct' => 'public',
            'inputClaim' => 'public',
            'persistClaim' => 'public',
            'acknowledgementClaim' => 'public',
            'inputPersist' => 'public',
            'persist' => 'public',
            'acknowledge' => 'public',
            'reclaim' => 'public',
            'claim' => 'private',
            'active' => 'private',
        ],
        'AuditAnchorReconciliationShape' => [
            'normalize' => 'public static',
            'assertSegment' => 'private static',
        ],
        'AuditAnchorScalar' => [
            'text' => 'public static',
            'hash' => 'public static',
            'integer' => 'public static',
            'nullableInteger' => 'public static',
            'nullableTimestamp' => 'public static',
            'nullableAllowed' => 'public static',
            'nullableText' => 'public static',
        ],
        'AuditAnchorTime' => [
            'assertTimestamp' => 'public static',
            'assertTransitionTimestamp' => 'public static',
            'addSeconds' => 'public static',
            'compare' => 'public static',
        ],
        'AuditAnchorTransitionGuard' => [
            'assertExpected' => 'public static',
            'advance' => 'public static',
        ],
    ];

    /** @var array<string,list<string>> */
    private const array INTERNAL_DEPENDENCIES = [
        'AuditAnchorState' => [],
        'AuditAnchorFailure' => [],
        'AuditAnchorException' => ['AuditAnchorFailure'],
        'AuditAnchorConfiguration' => ['AuditAnchorException', 'AuditAnchorFailure'],
        'AuditAnchorIntent' => [
            'AuditAnchorException',
            'AuditAnchorFailure',
            'AuditAnchorIntentShape',
            'AuditAnchorReceiptShape',
            'AuditAnchorState',
        ],
        'AuditAnchorIntentShape' => [
            'AuditAnchorCanonical',
            'AuditAnchorException',
            'AuditAnchorFailure',
            'AuditAnchorPreparedShape',
            'AuditAnchorReceiptShape',
            'AuditAnchorScalar',
            'AuditAnchorState',
            'AuditAnchorTime',
        ],
        'AuditAnchorPreparedShape' => ['AuditAnchorTime'],
        'AuditAnchorReceiptShape' => [
            'AuditAnchorIntent',
            'AuditAnchorIntentShape',
            'AuditAnchorReceiptBody',
            'AuditAnchorTime',
        ],
        'AuditAnchorCanonical' => ['AuditAnchorException', 'AuditAnchorFailure'],
        'AuditAnchorTime' => ['AuditAnchorException', 'AuditAnchorFailure'],
        'AuditAnchorBinding' => [
            'AuditAnchorException',
            'AuditAnchorFailure',
            'AuditAnchorIntentShape',
            'AuditAnchorTime',
        ],
        'AuditAnchorExpectation' => ['AuditAnchorException', 'AuditAnchorFailure'],
        'AuditAnchorLeaseOrigin' => [
            'AuditAnchorException',
            'AuditAnchorFailure',
            'AuditAnchorIntent',
            'AuditAnchorIntentShape',
            'AuditAnchorScalar',
        ],
        'AuditAnchorFrame' => [
            'AuditAnchorBinding',
            'AuditAnchorCanonical',
            'AuditAnchorException',
            'AuditAnchorExpectation',
            'AuditAnchorFailure',
            'AuditAnchorIntent',
            'AuditAnchorIntentShape',
            'AuditAnchorLeaseOrigin',
            'AuditAnchorTime',
        ],
        'AuditAnchorTransitionGuard' => [
            'AuditAnchorCanonical',
            'AuditAnchorException',
            'AuditAnchorExpectation',
            'AuditAnchorFailure',
            'AuditAnchorFrame',
            'AuditAnchorIntent',
            'AuditAnchorLeaseOrigin',
            'AuditAnchorTime',
        ],
        'AuditAnchorClaims' => [
            'AuditAnchorConfiguration',
            'AuditAnchorException',
            'AuditAnchorExpectation',
            'AuditAnchorFailure',
            'AuditAnchorFrame',
            'AuditAnchorIntentShape',
            'AuditAnchorTime',
            'AuditAnchorTransitionGuard',
        ],
        'AuditAnchorPreCallTransition' => [
            'AuditAnchorConfiguration',
            'AuditAnchorException',
            'AuditAnchorExpectation',
            'AuditAnchorExternalOutcome',
            'AuditAnchorFailure',
            'AuditAnchorFrame',
            'AuditAnchorTime',
            'AuditAnchorTransitionGuard',
        ],
        'AuditAnchorPostCallTransition' => [
            'AuditAnchorConfiguration',
            'AuditAnchorException',
            'AuditAnchorExpectation',
            'AuditAnchorExternalOutcome',
            'AuditAnchorFailure',
            'AuditAnchorFrame',
            'AuditAnchorTime',
            'AuditAnchorTransitionGuard',
        ],
        'AuditAnchorReceiptTransition' => [
            'AuditAnchorConfiguration',
            'AuditAnchorException',
            'AuditAnchorExpectation',
            'AuditAnchorFailure',
            'AuditAnchorFrame',
            'AuditAnchorIntentShape',
            'AuditAnchorReceiptBody',
            'AuditAnchorTime',
            'AuditAnchorTransitionGuard',
        ],
        'AuditAnchorExternalOutcome' => [
            'AuditAnchorException',
            'AuditAnchorFailure',
            'AuditAnchorIntentShape',
            'AuditAnchorReconciliationShape',
            'AuditAnchorScalar',
            'AuditAnchorTime',
        ],
        'AuditAnchorReconciliationShape' => [
            'AuditAnchorException',
            'AuditAnchorFailure',
            'AuditAnchorIntentShape',
            'AuditAnchorScalar',
            'AuditAnchorTime',
        ],
        'AuditAnchorScalar' => ['AuditAnchorTime'],
        'AuditAnchorReceiptBody' => [
            'AuditAnchorCanonical',
            'AuditAnchorException',
            'AuditAnchorFailure',
            'AuditAnchorIntent',
            'AuditAnchorIntentShape',
            'AuditAnchorTime',
        ],
    ];

    /** @var array<string,list<string>> */
    private const array EXTERNAL_DEPENDENCIES = [
        'AuditAnchorState' => [],
        'AuditAnchorFailure' => [],
        'AuditAnchorException' => ['RuntimeException'],
        'AuditAnchorConfiguration' => [
            'InvalidArgumentException',
            'SensitiveParameter',
            'Throwable',
        ],
        'AuditAnchorIntent' => ['SensitiveParameter', 'SensitiveParameterValue'],
        'AuditAnchorIntentShape' => [
            'AuditJcsCodec',
            'RuntimeException',
            'SensitiveParameter',
            'Throwable',
        ],
        'AuditAnchorPreparedShape' => ['RuntimeException', 'SensitiveParameter'],
        'AuditAnchorReceiptShape' => ['RuntimeException', 'SensitiveParameter'],
        'AuditAnchorCanonical' => [
            'AuditJcsCodec',
            'AuditLengthPrefix',
            'RuntimeException',
            'SensitiveParameter',
            'Throwable',
        ],
        'AuditAnchorTime' => [
            'AuditRecordTime',
            'DateTimeImmutable',
            'DateTimeZone',
            'RuntimeException',
            'SensitiveParameter',
            'Throwable',
        ],
        'AuditAnchorBinding' => [
            'RuntimeException',
            'SensitiveParameter',
            'SensitiveParameterValue',
            'Throwable',
        ],
        'AuditAnchorExpectation' => [
            'AuditJcsCodec',
            'AuditStringValidator',
            'RuntimeException',
            'SensitiveParameter',
            'SensitiveParameterValue',
            'Throwable',
        ],
        'AuditAnchorLeaseOrigin' => [
            'AuditJcsCodec',
            'RuntimeException',
            'SensitiveParameter',
            'SensitiveParameterValue',
            'Throwable',
        ],
        'AuditAnchorFrame' => [
            'AuditJcsCodec',
            'RuntimeException',
            'SensitiveParameter',
            'SensitiveParameterValue',
            'Throwable',
        ],
        'AuditAnchorTransitionGuard' => ['AuditJcsCodec', 'SensitiveParameter', 'Throwable'],
        'AuditAnchorClaims' => ['SensitiveParameter', 'Throwable'],
        'AuditAnchorPreCallTransition' => ['SensitiveParameter'],
        'AuditAnchorPostCallTransition' => ['SensitiveParameter'],
        'AuditAnchorReceiptTransition' => ['SensitiveParameter', 'Throwable'],
        'AuditAnchorExternalOutcome' => [
            'AuditJcsCodec',
            'RuntimeException',
            'SensitiveParameter',
            'Throwable',
        ],
        'AuditAnchorReconciliationShape' => [
            'AuditJcsCodec',
            'RuntimeException',
            'SensitiveParameter',
            'Throwable',
        ],
        'AuditAnchorScalar' => [
            'AuditStringValidator',
            'RuntimeException',
            'SensitiveParameter',
        ],
        'AuditAnchorReceiptBody' => ['AuditAnchorReceiptCodec', 'RuntimeException', 'SensitiveParameter', 'Throwable'],
    ];

    /** @var list<string> */
    private const array INTENT_FIELDS = [
        'schemaVersion',
        'kind',
        'intentId',
        'state',
        'partitionId',
        'checkpointId',
        'checkpointHash',
        'checkpointBytesHash',
        'anchorStoreRef',
        'anchorObjectRef',
        'expectedPredecessorVersion',
        'expectedPredecessorHash',
        'conditionalCreateToken',
        'idempotencyKeyHash',
        'acceptedObjectVersion',
        'acceptedObjectHash',
        'acceptedAnchoredAt',
        'anchorReceiptId',
        'segmentId',
        'previousAnchorReceiptHash',
        'receiptSigningKeyRef',
        'receiptSignedAt',
        'receiptPreimageHash',
        'receiptBytesHash',
        'anchorReceiptHash',
        'externalCallPhase',
        'preCallClaimMode',
        'preCallLookupStatus',
        'leaseFencingToken',
        'leaseExpiresAt',
        'attempt',
        'nextAvailableAt',
        'terminalReasonCode',
        'version',
        'previousStateHash',
        'stateHash',
        'updatedAt',
        'acknowledgedAt',
    ];

    /** @var list<string> */
    private const array RECEIPT_FIELDS = [
        'schemaVersion',
        'kind',
        'anchorReceiptId',
        'partitionId',
        'segmentId',
        'checkpointId',
        'checkpointHash',
        'checkpointBytesHash',
        'anchorStoreRef',
        'anchorObjectRef',
        'anchorObjectVersion',
        'previousAnchorReceiptHash',
        'anchoredAt',
        'signedAt',
        'signatureAlgorithm',
        'signingKeyRef',
    ];

    /** @var list<string> */
    private const array CLAIM_OPERATIONS = [
        'external_submission_claim',
        'pre_call_retry_claim',
        'external_reconciliation_claim',
        'receipt_input_claim',
        'receipt_persist_claim',
        'acknowledgement_claim',
    ];

    /** @var array<string,string> */
    private const array EXTERNAL_SHORT_NAMES = [
        'BlackOps\\Core\\Audit\\AuditRecordTime' => 'AuditRecordTime',
        'BlackOps\\Internal\\Audit\\AuditJcsCodec' => 'AuditJcsCodec',
        'BlackOps\\Internal\\Audit\\AuditLengthPrefix' => 'AuditLengthPrefix',
        'BlackOps\\Internal\\Audit\\AuditStringValidator' => 'AuditStringValidator',
        'BlackOps\\Internal\\Audit\\Artifact\\AuditAnchorReceiptCodec' => 'AuditAnchorReceiptCodec',
        'DateTimeImmutable' => 'DateTimeImmutable',
        'DateTimeZone' => 'DateTimeZone',
        'InvalidArgumentException' => 'InvalidArgumentException',
        'RuntimeException' => 'RuntimeException',
        'SensitiveParameter' => 'SensitiveParameter',
        'SensitiveParameterValue' => 'SensitiveParameterValue',
        'Throwable' => 'Throwable',
    ];

    public function testExactInventoryCallableSurfaceAndMaps(): void
    {
        $sources = $this->anchorSources();
        self::assertSame(
            array_map(static fn(string $type): string => 'Internal/Audit/Anchor/' . $type . '.php', self::TYPES),
            array_keys($sources),
            'the Anchor family must remain exactly 23 files',
        );

        foreach ($sources as $file => $source) {
            $type = $this->typeForFile($file);
            self::assertTrue($this->sourceConforms($file, $source), $file . ' semantic source contract');
            $declaration = $this->declaration($source);
            self::assertSame(
                self::ANCHOR_NAMESPACE . '\\' . basename($file, '.php'),
                $this->declarationName($declaration),
            );
            $surface = $this->methodSurface($declaration);
            self::assertSame(self::METHOD_SURFACE[$type] ?? [], $surface, $file . ' callable surface');
            self::assertSame(
                $this->expectedMethodSignatures()[$type] ?? [],
                $this->methodSignatureInventory($declaration),
                $file . ' exact method signatures',
            );
            self::assertSame(
                $this->expectedPropertyInventory()[$type] ?? [],
                $this->propertyInventory($declaration),
                $file . ' exact property inventory',
            );
            $methodCount = count($surface);
            self::assertLessThanOrEqual(10, $methodCount, $file . ' own method bound');
            foreach ($this->classMethods($declaration) as $method) {
                self::assertLessThanOrEqual(5, count($method->params), $file . ' ordinary argument bound');
            }
            self::assertSame(
                self::INTERNAL_DEPENDENCIES[$type] ?? [],
                $this->dependencyInventory($source, $type, true),
                $file . ' internal dependency map',
            );
            self::assertSame(
                self::EXTERNAL_DEPENDENCIES[$type] ?? [],
                $this->dependencyInventory($source, $type, false),
                $file . ' external dependency map',
            );
        }

        $state = $this->declaration($sources['Internal/Audit/Anchor/AuditAnchorState.php']);
        $failure = $this->declaration($sources['Internal/Audit/Anchor/AuditAnchorFailure.php']);
        self::assertInstanceOf(Enum_::class, $state);
        self::assertInstanceOf(Enum_::class, $failure);
        self::assertSame(
            [
                'Prepared' => 'prepared',
                'ExternallyAccepted' => 'externally_accepted',
                'ExternallyRejected' => 'externally_rejected',
                'ReceiptInputsPersisted' => 'receipt_inputs_persisted',
                'ReceiptPersisted' => 'receipt_persisted',
                'Acknowledged' => 'acknowledged',
            ],
            $this->enumCases($state),
        );
        self::assertSame(
            [
                'ConfigurationInvalid' => 'configuration_invalid',
                'IntegrityInvalid' => 'integrity_invalid',
                'TransitionConflict' => 'transition_conflict',
                'ResourceUnavailable' => 'resource_unavailable',
            ],
            $this->enumCases($failure),
        );
    }

    public function testNamedCallEdgesAndOriginSelectorAreExact(): void
    {
        $sources = $this->anchorSources();
        self::assertSame(
            [
                'Internal/Audit/Anchor/AuditAnchorTime.php' => ['assertTimestamp' => 1],
            ],
            $this->staticCallInventory($sources, 'BlackOps\\Core\\Audit\\AuditRecordTime'),
            'only AnchorTime may call the Core timestamp validator',
        );
        self::assertSame(
            [
                'Internal/Audit/Anchor/AuditAnchorReceiptBody.php' => [
                    'BlackOps\\Internal\\Audit\\Artifact\\AuditAnchorReceiptCodec::signingInput' => 1,
                ],
            ],
            $this->staticCallInventory($sources, 'BlackOps\\Internal\\Audit\\Artifact\\AuditAnchorReceiptCodec'),
            'only ReceiptBody may call the F1 signing-input API once',
        );

        self::assertSame(
            [
                'Internal/Audit/Anchor/AuditAnchorClaims.php' => [
                    'claim' => 1,
                ],
                'Internal/Audit/Anchor/AuditAnchorPostCallTransition.php' => [
                    'result' => 2,
                    'reconcile' => 3,
                    'reclaim' => 1,
                ],
                'Internal/Audit/Anchor/AuditAnchorPreCallTransition.php' => [
                    'lookup' => 3,
                    'callStart' => 1,
                    'reclaim' => 1,
                ],
                'Internal/Audit/Anchor/AuditAnchorReceiptTransition.php' => [
                    'inputPersist' => 1,
                    'persist' => 1,
                    'acknowledge' => 1,
                    'reclaim' => 1,
                    'claim' => 1,
                ],
            ],
            $this->transitionGuardCallInventory($sources),
            'shared revisions must have only named callers',
        );

        self::assertSame(
            [
                'Internal/Audit/Anchor/AuditAnchorFrame.php' => ['restore' => ['fromArray' => 1]],
                'Internal/Audit/Anchor/AuditAnchorTransitionGuard.php' => ['advance' => ['fromClaim' => 1]],
            ],
            $this->originCallInventory($sources),
            'lease origins must be restored or issued only at the bounded edges',
        );
        self::assertSame(
            [
                'Internal/Audit/Anchor/AuditAnchorFrame.php' => ['restore' => ['assertAssociation' => 1]],
                'Internal/Audit/Anchor/AuditAnchorLeaseOrigin.php' => [
                    'fromArray' => ['positiveInteger' => 2],
                    'fromClaim' => ['positiveInteger' => 2],
                ],
            ],
            $this->originSupportCallInventory($sources),
            'origin association and private numeric validation edges remain bounded',
        );
        self::assertSame(
            [
                'Internal/Audit/Anchor/AuditAnchorFrame.php' => [
                    'create' => ['AuditAnchorIntent::fromArray' => 1],
                    'restore' => ['AuditAnchorIntent::fromArray' => 1],
                ],
                'Internal/Audit/Anchor/AuditAnchorReceiptBody.php' => [
                    'build' => ['AuditAnchorReceiptBody::unsignedBody' => 1],
                    'assertPersistedHashes' => ['AuditAnchorReceiptBody::unsignedBody' => 1],
                    'assertPublicReceiptHash' => ['AuditAnchorReceiptBody::build' => 1],
                ],
                'Internal/Audit/Anchor/AuditAnchorReceiptShape.php' => [
                    'assert' => ['AuditAnchorReceiptBody::assertPersistedHashes' => 1],
                    'assertPublicReceiptHash' => ['AuditAnchorReceiptBody::assertPublicReceiptHash' => 1],
                ],
                'Internal/Audit/Anchor/AuditAnchorReceiptTransition.php' => [
                    'inputPersist' => ['AuditAnchorReceiptBody::build' => 1],
                    'persist' => ['AuditAnchorReceiptBody::build' => 1],
                ],
                'Internal/Audit/Anchor/AuditAnchorTransitionGuard.php' => [
                    'advance' => [
                        'AuditAnchorIntent::assertTransitionImmutable' => 1,
                        'AuditAnchorIntent::fromArray' => 1,
                    ],
                ],
            ],
            $this->movedCallableInventory($sources),
            'moved validation and immutable policy edges must remain exact',
        );
        self::assertSame(
            ['AuditAnchorExternalOutcome::reconciliation' => 1],
            $this->reconciliationCallerInventory($sources),
            'reconciliation normalization has one bounded Product caller',
        );
        self::assertSame(
            [
                'AuditAnchorExternalOutcome::positiveInteger' => ['AuditAnchorScalar::integer' => 1],
                'AuditAnchorIntentShape::assertHash' => ['AuditAnchorScalar::hash' => 1],
                'AuditAnchorIntentShape::assertText' => ['AuditAnchorScalar::text' => 1],
                'AuditAnchorIntentShape::normalize' => [
                    'AuditAnchorScalar::integer' => 2,
                    'AuditAnchorScalar::nullableAllowed' => 4,
                    'AuditAnchorScalar::nullableInteger' => 2,
                    'AuditAnchorScalar::nullableText' => 4,
                    'AuditAnchorScalar::nullableTimestamp' => 5,
                ],
                'AuditAnchorLeaseOrigin::positiveInteger' => ['AuditAnchorScalar::integer' => 1],
                'AuditAnchorReconciliationShape::normalize' => ['AuditAnchorScalar::integer' => 1],
                'AuditAnchorScalar::nullableInteger' => ['AuditAnchorScalar::integer' => 1],
                'AuditAnchorScalar::nullableText' => ['AuditAnchorScalar::text' => 1],
            ],
            $this->scalarCallInventory($sources),
            'scalar helpers must retain their complete bounded callers',
        );
        $guard = $sources['Internal/Audit/Anchor/AuditAnchorTransitionGuard.php'];
        $replacements = 0;
        $detachedIntent = $this->replaceFirstLiteral(
            $guard,
            "            \$intent = AuditAnchorIntent::fromArray(\$candidate);\n",
            "            AuditAnchorIntent::fromArray(\$candidate);\n            \$intent = \$frame->intent();\n",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->originCallInventory($sources),
            $this->originCallInventory([
                ...$sources,
                'Internal/Audit/Anchor/AuditAnchorTransitionGuard.php' => $detachedIntent,
            ]),
        );
        $reassignedSelector = $this->replaceFirstLiteral(
            $guard,
            "            if (\$candidate['leaseFencingToken'] !== null) {\n",
            "            if (\$candidate['leaseFencingToken'] !== null) {\n                \$claimOperation = 'receipt_input_claim';\n",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->originCallInventory($sources),
            $this->originCallInventory([
                ...$sources,
                'Internal/Audit/Anchor/AuditAnchorTransitionGuard.php' => $reassignedSelector,
            ]),
        );
        $postCall = $sources['Internal/Audit/Anchor/AuditAnchorPostCallTransition.php'];
        $detachedCandidate = $this->replaceFirstLiteral(
            $postCall,
            "            \$next = array_replace(\$value, [\n",
            "            \$next = array_replace(\$frame->intent()->toArray(), [\n",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->transitionGuardCallInventory($sources),
            $this->transitionGuardCallInventory([
                ...$sources,
                'Internal/Audit/Anchor/AuditAnchorPostCallTransition.php' => $detachedCandidate,
            ]),
        );
        $receiptShape = $sources['Internal/Audit/Anchor/AuditAnchorReceiptShape.php'];
        $wrongMovedArguments = $this->replaceFirstLiteral(
            $receiptShape,
            'AuditAnchorReceiptBody::assertPersistedHashes($value, $signingKeyRef, $acceptedAnchoredAt, $receiptSignedAt);',
            'AuditAnchorReceiptBody::assertPersistedHashes($signingKeyRef, $value, $acceptedAnchoredAt, $receiptSignedAt);',
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->movedCallableInventory($sources),
            $this->movedCallableInventory([
                ...$sources,
                'Internal/Audit/Anchor/AuditAnchorReceiptShape.php' => $wrongMovedArguments,
            ]),
        );
        $wrongGuardFactory = str_replace(
            '            $intent = AuditAnchorIntent::fromArray($candidate);',
            '            $intent = AuditAnchorIntent::fromArray($current);',
            $guard,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->movedCallableInventory($sources),
            $this->movedCallableInventory([
                ...$sources,
                'Internal/Audit/Anchor/AuditAnchorTransitionGuard.php' => $wrongGuardFactory,
            ]),
        );
        $unmappedMovedCaller = $this->replaceFirstLiteral(
            $guard,
            "        \$current = \$frame->intent()->toArray();\n",
            "        AuditAnchorIntent::assertTransitionImmutable(\$current, \$current);\n        \$current = \$frame->intent()->toArray();\n",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->movedCallableInventory($sources),
            $this->movedCallableInventory([
                ...$sources,
                'Internal/Audit/Anchor/AuditAnchorTransitionGuard.php' => $unmappedMovedCaller,
            ]),
        );
        self::assertSame(
            [
                'AuditAnchorClaims::externalSubmission' => 'external_submission_claim',
                'AuditAnchorClaims::preCallRetry' => 'pre_call_retry_claim',
                'AuditAnchorClaims::externalReconciliation' => 'external_reconciliation_claim',
                'AuditAnchorReceiptTransition::inputClaim' => 'receipt_input_claim',
                'AuditAnchorReceiptTransition::persistClaim' => 'receipt_persist_claim',
                'AuditAnchorReceiptTransition::acknowledgementClaim' => 'acknowledgement_claim',
            ],
            $this->claimSelectorInventory($sources),
            'named claim entry points must select fixed lease origins',
        );
        self::assertTrue($this->expectationGuardShape(
            $sources['Internal/Audit/Anchor/AuditAnchorTransitionGuard.php'],
        ));
        self::assertSame([], $this->outsideAnchorReferences());
    }

    public function testClosedWireCarriersAndPrivacyShapesAreExact(): void
    {
        $sources = $this->anchorSources();
        $shape = $this->declaration($sources['Internal/Audit/Anchor/AuditAnchorIntentShape.php']);
        self::assertSame(self::INTENT_FIELDS, $this->constantArray($shape, 'FIELDS'));
        $origin = $this->declaration($sources['Internal/Audit/Anchor/AuditAnchorLeaseOrigin.php']);
        self::assertSame(self::CLAIM_OPERATIONS, $this->constantArray($origin, 'OPERATIONS'));
        $body = $this->declaration($sources['Internal/Audit/Anchor/AuditAnchorReceiptBody.php']);
        self::assertSame(self::RECEIPT_FIELDS, $this->returnedArray($body, 'unsignedBody'));

        self::assertSame(
            [
                'create' => [
                    [
                        'intentId',
                        'partitionId',
                        'checkpointId',
                        'checkpointHash',
                        'checkpointBytesHash',
                        'anchorStoreRef',
                        'anchorObjectRef',
                        'expectedPredecessorVersion',
                        'expectedPredecessorHash',
                        'conditionalCreateToken',
                    ],
                ],
                'restore' => [['intent', 'binding', 'leaseOrigin']],
            ],
            $this->closedKeyLists($this->declaration($sources['Internal/Audit/Anchor/AuditAnchorFrame.php'])),
        );
        self::assertSame(
            [
                'fromArray' => [['segmentId', 'previousAnchorReceiptHash', 'checkpointSignedAt', 'anchorReceiptId']],
            ],
            $this->closedKeyLists($this->declaration($sources['Internal/Audit/Anchor/AuditAnchorBinding.php'])),
        );
        self::assertSame(
            [
                'fromArray' => [[
                    'operation',
                    'intentId',
                    'attempt',
                    'leaseFencingToken',
                    'claimVersion',
                    'claimStateHash',
                ]],
            ],
            $this->closedKeyLists($origin),
        );
        self::assertSame(
            [
                'result' => [[
                    'schemaVersion',
                    'kind',
                    'status',
                    'reasonCode',
                    'acceptedObjectVersion',
                    'acceptedObjectHash',
                    'acceptedAnchoredAt',
                    'attempt',
                ]],
            ],
            $this->closedKeyLists($this->declaration($sources['Internal/Audit/Anchor/AuditAnchorExternalOutcome.php'])),
        );
        self::assertSame(
            [
                'normalize' => [
                    ['schemaVersion', 'kind', 'outcome', 'foundObject', 'reasonCode'],
                    [
                        'anchorObjectRef',
                        'anchorObjectVersion',
                        'acceptedObjectHash',
                        'acceptedAnchoredAt',
                        'segmentId',
                        'previousAnchorReceiptHash',
                    ],
                ],
            ],
            $this->closedKeyLists($this->declaration(
                $sources['Internal/Audit/Anchor/AuditAnchorReconciliationShape.php'],
            )),
        );
        self::assertSame(
            ['segmentId', 'previousAnchorReceiptHash', 'checkpointSignedAt', 'anchorReceiptId'],
            $this->snapshotLiteralKeys($this->declaration($sources['Internal/Audit/Anchor/AuditAnchorBinding.php'])),
        );
        self::assertSame(
            ['version', 'stateHash', 'token'],
            $this->snapshotLiteralKeys($this->declaration(
                $sources['Internal/Audit/Anchor/AuditAnchorExpectation.php'],
            )),
        );
        self::assertSame(
            ['intent', 'binding', 'leaseOrigin'],
            $this->snapshotLiteralKeys($this->declaration($sources['Internal/Audit/Anchor/AuditAnchorFrame.php'])),
        );

        foreach ([
            'AuditAnchorBinding',
            'AuditAnchorExpectation',
            'AuditAnchorFrame',
            'AuditAnchorIntent',
            'AuditAnchorLeaseOrigin',
        ] as $type) {
            $source = $sources['Internal/Audit/Anchor/' . $type . '.php'];
            $declaration = $this->declaration($source);
            self::assertInstanceOf(Class_::class, $declaration);
            self::assertTrue($declaration->isReadonly(), $type . ' must be readonly');
            self::assertSame(['snapshot'], $this->propertyNames($declaration));
            $snapshot = $declaration->getProperties()[0];
            self::assertTrue($snapshot->isPrivate());
            self::assertSame('SensitiveParameterValue', $this->typeName($snapshot->type));
            $this->assertMagicPrivacy($declaration, $type);
        }
        $configuration = $this->declaration($sources['Internal/Audit/Anchor/AuditAnchorConfiguration.php']);
        self::assertInstanceOf(Class_::class, $configuration);
        self::assertTrue($configuration->isReadonly());
        self::assertSame(['leaseDurationSeconds', 'retryDelaySeconds'], $this->propertyNames($configuration));
        foreach ($configuration->getProperties() as $property) {
            self::assertTrue($property->isPublic());
            self::assertSame('int', $this->typeName($property->type));
        }
        foreach ($this->anchorSources() as $file => $source) {
            self::assertSame(
                [],
                $this->parameterSensitivityViolations($this->declaration($source)),
                $file . ' sensitive parameters',
            );
        }
    }

    public function testSemanticMutationControlsRejectWrongEdgesAndKeepInertControlsPositive(): void
    {
        $sources = $this->anchorSources();
        $time = $sources['Internal/Audit/Anchor/AuditAnchorTime.php'];
        $wrongTime = str_replace(
            'AuditRecordTime::assertTimestamp(',
            'AuditRecordTime::parseTimestamp(',
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->staticCallInventory([
                'Internal/Audit/Anchor/AuditAnchorTime.php' => $time,
            ], 'BlackOps\\Core\\Audit\\AuditRecordTime'),
            $this->staticCallInventory([
                'Internal/Audit/Anchor/AuditAnchorTime.php' => $wrongTime,
            ], 'BlackOps\\Core\\Audit\\AuditRecordTime'),
        );

        $receipt = $sources['Internal/Audit/Anchor/AuditAnchorReceiptBody.php'];
        $wrongReceiptMethod = str_replace(
            'AuditAnchorReceiptCodec::signingInput(',
            'AuditAnchorReceiptCodec::decode(',
            $receipt,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->staticCallInventory([
                'Internal/Audit/Anchor/AuditAnchorReceiptBody.php' => $receipt,
            ], 'BlackOps\\Internal\\Audit\\Artifact\\AuditAnchorReceiptCodec'),
            $this->staticCallInventory([
                'Internal/Audit/Anchor/AuditAnchorReceiptBody.php' => $wrongReceiptMethod,
            ], 'BlackOps\\Internal\\Audit\\Artifact\\AuditAnchorReceiptCodec'),
        );
        $extraReceiptCall =
            $receipt . "\n\\BlackOps\\Internal\\Audit\\Artifact\\AuditAnchorReceiptCodec::signingInput([]);\n";
        self::assertNotSame(
            $this->staticCallInventory([
                'Internal/Audit/Anchor/AuditAnchorReceiptBody.php' => $receipt,
            ], 'BlackOps\\Internal\\Audit\\Artifact\\AuditAnchorReceiptCodec'),
            $this->staticCallInventory([
                'Internal/Audit/Anchor/AuditAnchorReceiptBody.php' => $extraReceiptCall,
            ], 'BlackOps\\Internal\\Audit\\Artifact\\AuditAnchorReceiptCodec'),
        );

        $fields = $sources['Internal/Audit/Anchor/AuditAnchorIntentShape.php'];
        $countAfterTraversal = $this->replaceFirstLiteral(
            $fields,
            "        if (count(\$value) !== count(\$keys)) {\n            throw new \\RuntimeException();\n        }\n        \$actual = array_keys(\$value);",
            "        \$actual = array_keys(\$value);\n        if (count(\$value) !== count(\$keys)) {\n            throw new \\RuntimeException();\n        }",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms(
            'Internal/Audit/Anchor/AuditAnchorIntentShape.php',
            $countAfterTraversal,
        ));
        $disabledCountComparison = $this->replaceFirstLiteral(
            $fields,
            "        if (count(\$value) !== count(\$keys)) {",
            "        if (false && count(\$value) !== count(\$keys)) {",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms(
            'Internal/Audit/Anchor/AuditAnchorIntentShape.php',
            $disabledCountComparison,
        ));
        $deadOuterCountComparison = $this->replaceFirstLiteral(
            $fields,
            "        if (count(\$value) !== count(\$keys)) {\n            throw new \\RuntimeException();\n        }",
            "        if (false) {\n            if (count(\$value) !== count(\$keys)) {\n                throw new \\RuntimeException();\n            }\n        }",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms(
            'Internal/Audit/Anchor/AuditAnchorIntentShape.php',
            $deadOuterCountComparison,
        ));
        $returnBeforeCountThrow = $this->replaceFirstLiteral(
            $fields,
            "        if (count(\$value) !== count(\$keys)) {\n            throw new \\RuntimeException();\n        }",
            "        if (count(\$value) !== count(\$keys)) {\n            return;\n            throw new \\RuntimeException();\n        }",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms(
            'Internal/Audit/Anchor/AuditAnchorIntentShape.php',
            $returnBeforeCountThrow,
        ));
        $extraField = str_replace(
            "        'acknowledgedAt',\n",
            "        'acknowledgedAt',\n        'unexpectedWireField',\n",
            $fields,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->constantArray($this->declaration($fields), 'FIELDS'),
            $this->constantArray($this->declaration($extraField), 'FIELDS'),
        );
        $extraReceiptField = str_replace(
            "            'signingKeyRef' => \$signingKeyRef,\n",
            "            'signingKeyRef' => \$signingKeyRef,\n            'unexpectedWireField' => 'unexpected',\n",
            $receipt,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->returnedArray($this->declaration($receipt), 'unsignedBody'),
            $this->returnedArray($this->declaration($extraReceiptField), 'unsignedBody'),
        );

        $claims = $sources['Internal/Audit/Anchor/AuditAnchorClaims.php'];
        $wrongOrigin = str_replace('external_submission_claim', 'wrong_claim', $claims, $replacements);
        self::assertGreaterThan(0, $replacements);
        self::assertNotSame($this->claimOperationInventory($claims), $this->claimOperationInventory($wrongOrigin));
        $dynamicOrigin = str_replace(", 'external_submission_claim');", ', $operation);', $claims, $replacements);
        self::assertSame(1, $replacements);
        $claimSources = $sources;
        $claimSources['Internal/Audit/Anchor/AuditAnchorClaims.php'] = $claims;
        $dynamicClaimSources = $claimSources;
        $dynamicClaimSources['Internal/Audit/Anchor/AuditAnchorClaims.php'] = $dynamicOrigin;
        self::assertNotSame(
            $this->claimSelectorInventory($claimSources),
            $this->claimSelectorInventory($dynamicClaimSources),
        );
        $wrongSelectorOwner = str_replace('external_submission_claim', 'receipt_input_claim', $claims, $replacements);
        self::assertGreaterThan(0, $replacements);
        self::assertNotSame(
            $this->claimSelectorInventory($claimSources),
            $this->claimSelectorInventory([
                ...$sources,
                'Internal/Audit/Anchor/AuditAnchorClaims.php' => $wrongSelectorOwner,
            ]),
        );
        $extraClaim = str_replace(
            "return \$this->claim(\$frame, \$expected, \$newToken, \$trustedNow, 'external_submission_claim');",
            "return \$this->claim(\$frame, \$expected, \$newToken, \$trustedNow, 'external_submission_claim');\n        \$this->claim(\$frame, \$expected, \$newToken, \$trustedNow, 'external_submission_claim');",
            $claims,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->claimSelectorInventory($claimSources),
            $this->claimSelectorInventory([...$sources, 'Internal/Audit/Anchor/AuditAnchorClaims.php' => $extraClaim]),
        );
        $dynamicClaim = str_replace(
            "return \$this->claim(\$frame, \$expected, \$newToken, \$trustedNow, 'external_submission_claim');",
            "\$method = 'claim';\n        return \$this->\$method(\$frame, \$expected, \$newToken, \$trustedNow, 'external_submission_claim');",
            $claims,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->claimSelectorInventory($claimSources),
            $this->claimSelectorInventory([
                ...$sources,
                'Internal/Audit/Anchor/AuditAnchorClaims.php' => $dynamicClaim,
            ]),
        );
        $wrongGuardSelector = str_replace(
            "            \$next['receiptBytesHash'] = \$receipt['receiptBytesHash'];
            \$next['leaseFencingToken'] = null;
            \$next['leaseExpiresAt'] = null;
            return AuditAnchorTransitionGuard::advance(\$frame, \$expected, \$next, \$trustedNow);",
            "            \$next['receiptBytesHash'] = \$receipt['receiptBytesHash'];
            \$next['leaseFencingToken'] = null;
            \$next['leaseExpiresAt'] = null;
            return AuditAnchorTransitionGuard::advance(\$frame, \$expected, \$next, \$trustedNow, 'wrong_selector');",
            $sources['Internal/Audit/Anchor/AuditAnchorReceiptTransition.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame(
            $this->transitionGuardCallInventory($sources),
            $this->transitionGuardCallInventory([
                ...$sources,
                'Internal/Audit/Anchor/AuditAnchorReceiptTransition.php' => $wrongGuardSelector,
            ]),
        );

        $guard = $sources['Internal/Audit/Anchor/AuditAnchorTransitionGuard.php'];
        $disabledExpectation = $this->replaceFirstLiteral(
            $guard,
            '$expectation = $expected->toArray();',
            '$expectation = [];',
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertTrue($this->expectationGuardShape($guard));
        self::assertFalse($this->expectationGuardShape($disabledExpectation));
        $overwrittenExpectation = $this->replaceFirstLiteral(
            $guard,
            '$expectation = $expected->toArray();',
            "\$expectation = \$expected->toArray();\n        \$expectation = [\n            'version' => \$current['version'],\n            'stateHash' => \$current['stateHash'],\n            'token' => \$current['leaseFencingToken'],\n        ];",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($overwrittenExpectation));
        $overwrittenCurrent = $this->replaceFirstLiteral(
            $guard,
            '$expectation = $expected->toArray();',
            "\$expectation = \$expected->toArray();\n        \$current = [\n            'version' => \$expectation['version'],\n            'stateHash' => \$expectation['stateHash'],\n            'leaseFencingToken' => \$expectation['token'],\n        ];",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($overwrittenCurrent));
        $disabledCas = $this->replaceFirstLiteral(
            $guard,
            "        if (\n            [\n                \$expectation['version'],",
            "        if (false &&\n            [\n                \$expectation['version'],",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($disabledCas));
        $casBlock =
            "        if (\n"
            . "            [\n"
            . "                \$expectation['version'],\n"
            . "                \$expectation['stateHash'] !== null,\n"
            . "                hash_equals((string) \$expectation['stateHash'], \$current['stateHash']),\n"
            . "                (\$current['leaseFencingToken'] === null) === (\$expectation['token'] === null),\n"
            . "                hash_equals((string) \$expectation['token'], (string) \$current['leaseFencingToken']),\n"
            . "            ] !== [\$current['version'], true, true, true, true]\n"
            . "        ) {\n"
            . "            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);\n"
            . '        }';
        $deadOuterCas = $this->replaceFirstLiteral(
            $guard,
            $casBlock,
            "        if (false) {\n"
            . "            if (\n"
            . "                [\n"
            . "                    \$expectation['version'],\n"
            . "                    \$expectation['stateHash'] !== null,\n"
            . "                    hash_equals((string) \$expectation['stateHash'], \$current['stateHash']),\n"
            . "                    (\$current['leaseFencingToken'] === null) === (\$expectation['token'] === null),\n"
            . "                    hash_equals((string) \$expectation['token'], (string) \$current['leaseFencingToken']),\n"
            . "                ] !== [\$current['version'], true, true, true, true]\n"
            . "            ) {\n"
            . "                throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);\n"
            . "            }\n"
            . '        }',
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($deadOuterCas));
        $disabledDelegate = $this->replaceFirstLiteral(
            $guard,
            "            self::assertExpected(\$frame, \$expected);\n",
            "            if (false) {\n                self::assertExpected(\$frame, \$expected);\n            }\n",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($disabledDelegate));
        $shortCircuitDelegate = $this->replaceFirstLiteral(
            $guard,
            "            self::assertExpected(\$frame, \$expected);\n",
            "            false && self::assertExpected(\$frame, \$expected);\n",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($shortCircuitDelegate));
        $detachedCas = $this->replaceFirstAnyLiteral(
            [
                "hash_equals((string) \$expectation['stateHash'], \$current['stateHash']),",
                "hash_equals((string) \$expectation['stateHash'], \$currentStateHash),",
            ],
            "hash_equals((string) \$expectation['stateHash'], \$unrelatedHash),",
            $guard,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($detachedCas));
        $reversedCas = $this->replaceFirstLiteral(
            $guard,
            "] !== [\$current['version'], true, true, true, true]\n        ) {",
            "] === [\$current['version'], true, true, true, true]\n        ) {",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($reversedCas));
        $negatedHash = $this->replaceFirstLiteral(
            $guard,
            "                hash_equals((string) \$expectation['stateHash'], \$current['stateHash']),",
            "                !hash_equals((string) \$expectation['stateHash'], \$current['stateHash']),",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($negatedHash));
        $neutralizedVersion = $this->replaceFirstLiteral(
            $guard,
            "                \$expectation['version'],",
            '                true,',
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($neutralizedVersion));
        $nonThrowingConflict = $this->replaceFirstLiteral(
            $guard,
            '            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);',
            '            new AuditAnchorException(AuditAnchorFailure::TransitionConflict);',
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($nonThrowingConflict));
        $deadConflict = $this->replaceFirstLiteral(
            $guard,
            '            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);',
            "            if (false) {\n                throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);\n            }",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($deadConflict));
        $returnBeforeConflict = $this->replaceFirstLiteral(
            $guard,
            '            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);',
            "            return;\n            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);",
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->expectationGuardShape($returnBeforeConflict));

        $marker = str_replace(
            'final class AuditAnchorTime',
            "#[\\BlackOps\\Core\\Attribute\\PublicApi]\nfinal class AuditAnchorTime",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms('Internal/Audit/Anchor/AuditAnchorTime.php', $marker));
        $privacy = str_replace(
            'public static function fromArray(#[\\SensitiveParameter] array $value)',
            'public static function fromArray(array $value)',
            $sources['Internal/Audit/Anchor/AuditAnchorIntent.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms('Internal/Audit/Anchor/AuditAnchorIntent.php', $privacy));
        $configurationPrivacy = str_replace(
            "        #[\\SensitiveParameter]\n        mixed \$leaseDurationSeconds = 300,",
            '        mixed $leaseDurationSeconds = 300,',
            $sources['Internal/Audit/Anchor/AuditAnchorConfiguration.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms(
            'Internal/Audit/Anchor/AuditAnchorConfiguration.php',
            $configurationPrivacy,
        ));
        $removedMagicThrow = str_replace(
            "    public function __unserialize(#[\\SensitiveParameter] array \$data): void\n    {\n        throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);\n    }",
            "    public function __unserialize(#[\\SensitiveParameter] array \$data): void\n    {\n        return;\n    }",
            $sources['Internal/Audit/Anchor/AuditAnchorIntent.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms('Internal/Audit/Anchor/AuditAnchorIntent.php', $removedMagicThrow));
        $renamedParameter = str_replace('string $left', 'string $renamedLeft', $time, $replacements);
        self::assertGreaterThan(0, $replacements);
        self::assertFalse($this->sourceConforms('Internal/Audit/Anchor/AuditAnchorTime.php', $renamedParameter));
        $byReferenceParameter = str_replace(
            'string $left, #[\\SensitiveParameter] string $right',
            'string &$left, #[\\SensitiveParameter] string $right',
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms('Internal/Audit/Anchor/AuditAnchorTime.php', $byReferenceParameter));
        $changedReturn = $this->replaceFirstMethodReturnType($time, 'compare', 'string', $replacements);
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms('Internal/Audit/Anchor/AuditAnchorTime.php', $changedReturn));
        $changedDefault = str_replace(
            'mixed $leaseDurationSeconds = 300',
            'mixed $leaseDurationSeconds = 301',
            $sources['Internal/Audit/Anchor/AuditAnchorConfiguration.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms('Internal/Audit/Anchor/AuditAnchorConfiguration.php', $changedDefault));
        $unpromoted = str_replace(
            'private readonly AuditAnchorFailure $failure,',
            'AuditAnchorFailure $failure,',
            $sources['Internal/Audit/Anchor/AuditAnchorException.php'],
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms('Internal/Audit/Anchor/AuditAnchorException.php', $unpromoted));
        $extraInterface = str_replace(
            'final class AuditAnchorTime',
            "interface ExtraAnchorContract {}\n\nfinal class AuditAnchorTime",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertFalse($this->sourceConforms('Internal/Audit/Anchor/AuditAnchorTime.php', $extraInterface));
        $extraFunction = $time . "\nfunction extraAnchorCallable(): void {}\n";
        self::assertFalse($this->sourceConforms('Internal/Audit/Anchor/AuditAnchorTime.php', $extraFunction));

        $wrongOutside = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace BlackOps\Internal\Audit\Other;
            use BlackOps\Internal\Audit\Anchor\AuditAnchorIntent as Alias;
            final class WrongCaller
            {
                public function call(string $className, string $method): void
                {
                    $value = new Alias([]);
                    $className::fromArray([]);
                    Alias::$method([]);
                }
            }
            PHP;
        self::assertNotSame([], $this->anchorReferences($wrongOutside));
        $dynamicClassString = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace BlackOps\Internal\Audit\Other;
            final class DynamicCaller
            {
                public function call(): void
                {
                    $className = 'BlackOps\Internal\Audit\Anchor\AuditAnchorIntent';
                    $className::fromArray([]);
                }
            }
            PHP;
        self::assertNotSame([], $this->anchorReferences($dynamicClassString));
        $positive = <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace BlackOps\Internal\Audit\Other;
            final class InertText
            {
                public function text(): string
                {
                    // AuditAnchorTime::assertTimestamp and AuditAnchorReceiptCodec::decode
                    return 'AuditAnchorIntent::fromArray';
                }
            }
            PHP;
        self::assertSame([], $this->anchorReferences($positive));
        $inertClassString = <<<'PHP'
            <?php
            $class = 'BlackOps\Internal\Audit\Anchor\AuditAnchorIntent';
            $message = 'inert';
            PHP;
        self::assertSame([], $this->anchorReferences($inertClassString));
        $dynamicSink = <<<'PHP'
            <?php
            $class = 'BlackOps\Internal\Audit\Anchor\AuditAnchorIntent';
            class_exists($class);
            PHP;
        self::assertNotSame([], $this->anchorReferences($dynamicSink));
        $dynamicConcat = <<<'PHP'
            <?php
            $class = 'BlackOps\\Internal\\Audit\\' . 'Anchor\\AuditAnchorIntent';
            class_exists($class);
            PHP;
        self::assertNotSame([], $this->anchorReferences($dynamicConcat));
        $inlineCallable = <<<'PHP'
            <?php
            call_user_func(['BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorIntent', 'fromArray'], []);
            PHP;
        self::assertNotSame([], $this->anchorReferences($inlineCallable));
        $immediateCallable = <<<'PHP'
            <?php
            (['BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorIntent', 'fromArray'])([]);
            PHP;
        self::assertNotSame([], $this->anchorReferences($immediateCallable));
        $immediateStringCallable = <<<'PHP'
            <?php
            ('BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorIntent::fromArray')([]);
            PHP;
        self::assertNotSame([], $this->anchorReferences($immediateStringCallable));
        $literalClassExpression = <<<'PHP'
            <?php
            ('BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorIntent')::fromArray([]);
            PHP;
        self::assertNotSame([], $this->anchorReferences($literalClassExpression));
        $literalNewClassExpression = <<<'PHP'
            <?php
            new ('BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorIntent')();
            PHP;
        self::assertNotSame([], $this->anchorReferences($literalNewClassExpression));
        $literalNewConfiguration = <<<'PHP'
            <?php
            new ('BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorConfiguration')();
            PHP;
        self::assertNotSame([], $this->anchorReferences($literalNewConfiguration));
        $variableCallable = <<<'PHP'
            <?php
            $call = ['BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorIntent', 'fromArray'];
            $call([]);
            PHP;
        self::assertNotSame([], $this->anchorReferences($variableCallable));
        $aliasedCallable = <<<'PHP'
            <?php
            class_alias('BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorIntent', 'Alias');
            Alias::fromArray([]);
            PHP;
        self::assertNotSame([], $this->anchorReferences($aliasedCallable));
        $caseVariantCallable = <<<'PHP'
            <?php
            call_user_func(['blackops\\internal\\audit\\anchor\\auditanchorintent', 'fromArray'], []);
            PHP;
        self::assertNotSame([], $this->anchorReferences($caseVariantCallable));
        $aliasedDynamicSink = <<<'PHP'
            <?php
            use function call_user_func as invoke;
            invoke(['BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorIntent', 'fromArray'], []);
            PHP;
        self::assertNotSame([], $this->anchorReferences($aliasedDynamicSink));
        $dataArgument = <<<'PHP'
            <?php
            call_user_func('strlen', 'BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorIntent');
            PHP;
        self::assertSame([], $this->anchorReferences($dataArgument));
        $unconsumedCallable = <<<'PHP'
            <?php
            $call = ['BlackOps\\Internal\\Audit\\Anchor\\AuditAnchorIntent', 'fromArray'];
            PHP;
        self::assertSame([], $this->anchorReferences($unconsumedCallable));
        self::assertSame([], $this->forbiddenEffects($this->parseSource($positive), 'InertText'));
    }

    public function testForbiddenActivationAndSideEffectMutationsAreRejected(): void
    {
        $sources = $this->anchorSources();
        $time = $sources['Internal/Audit/Anchor/AuditAnchorTime.php'];
        $clock = str_replace(
            "use BlackOps\\Core\\Audit\\AuditRecordTime;\n",
            "use BlackOps\\Core\\Audit\\AuditRecordTime;\nuse Psr\\Clock\\ClockInterface;\n",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($clock), 'AuditAnchorTime'));

        $sideEffect = str_replace(
            "return \$value;\n",
            "new \\PDO('sqlite::memory:');\n        return \$value;\n",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($sideEffect), 'AuditAnchorTime'));

        $dynamic = str_replace(
            'return self::assertTimestamp($value);',
            '$method = \'assertTimestamp\'; return self::$method($value);',
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($dynamic), 'AuditAnchorTime'));

        $forbiddenImports = <<<'PHP'
            use Psr\Clock\ClockInterface;
            use BlackOps\Internal\Audit\Artifact\AuditArtifactSigner;
            use BlackOps\Internal\Audit\Artifact\AuditArtifactVerifier;
            use PDO;
            use Vendor\DatabaseConnection;
            use Vendor\NetworkProvider;
            use Vendor\Container;
            PHP;
        $forbidden = str_replace(
            "use BlackOps\\Core\\Audit\\AuditRecordTime;\n",
            "use BlackOps\\Core\\Audit\\AuditRecordTime;\n" . $forbiddenImports,
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($forbidden), 'AuditAnchorTime'));

        $activation = str_replace(
            "return \$value;\n",
            "\$runtime->activate(); return \$value;\n",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($activation), 'AuditAnchorTime'));

        $append = str_replace(
            "return \$value;\n",
            "\$journal->append(\$value); return \$value;\n",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($append), 'AuditAnchorTime'));

        $staticAppend = str_replace(
            "return \$value;\n",
            "self::append(\$value); return \$value;\n",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($staticAppend), 'AuditAnchorTime'));

        foreach ([
            'native clock' => 'time(); return $value;',
            'native microclock' => 'microtime(true); return $value;',
            'native randomness' => 'random_bytes(16); return $value;',
            'native I/O' => "file_get_contents('anchor'); return \$value;",
            'native write I/O' => "fwrite(\$stream, 'anchor'); return \$value;",
            'ambient DateTime' => 'new \\DateTimeImmutable(); return $value;',
            'unbounded timezone' => "new \\DateTimeZone('America/New_York'); return \$value;",
        ] as $label => $replacement) {
            $mutated = str_replace('return $value;', $replacement, $time, $replacements);
            self::assertSame(1, $replacements, $label . ' fixture');
            self::assertNotSame([], $this->forbiddenEffects($this->parseSource($mutated), 'AuditAnchorTime'), $label);
        }
        $canonicalDateArguments = <<<'PHP'
            '!Y-m-d\\TH:i:s.u\\Z', $value, new \DateTimeZone('UTC')
            PHP;
        $partialDate = str_replace(
            $canonicalDateArguments,
            "'Y', \$value, new \\DateTimeZone('UTC')",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($partialDate), 'AuditAnchorTime'));
        $detachedDate = str_replace(
            $canonicalDateArguments,
            "'!Y-m-d\\\\TH:i:s.u\\\\Z', 'detached', new \\DateTimeZone('UTC')",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($detachedDate), 'AuditAnchorTime'));
        $reassignedDate = str_replace(
            "            \$time = \\DateTimeImmutable::createFromFormat(",
            "            \$value = '2030-01-01T00:00:00.000000Z';\n            \$time = \\DateTimeImmutable::createFromFormat(",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($reassignedDate), 'AuditAnchorTime'));
        $aliasedDate = str_replace(
            "            \$time = \\DateTimeImmutable::createFromFormat(",
            "            \$supplied = 'detached';\n            \$time = \\DateTimeImmutable::createFromFormat(",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        $aliasedDate = str_replace(
            "', \$value, new \\DateTimeZone('UTC'));",
            "', \$supplied, new \\DateTimeZone('UTC'));",
            $aliasedDate,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($aliasedDate), 'AuditAnchorTime'));
        $referenceDate = str_replace(
            "            \$time = \\DateTimeImmutable::createFromFormat('!Y-m-d\\\\TH:i:s.u\\\\Z', \$value, new \\DateTimeZone('UTC'));",
            "            \$replacement = \$value;\n            \$value =& \$replacement;\n            \$time = \\DateTimeImmutable::createFromFormat('!Y-m-d\\\\TH:i:s.u\\\\Z', \$value, new \\DateTimeZone('UTC'));",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertNotSame([], $this->forbiddenEffects($this->parseSource($referenceDate), 'AuditAnchorTime'));
        $inertNativeText = str_replace(
            'return $value;',
            "/* time(); microtime(true); random_bytes(16); file_get_contents('anchor'); fwrite(\$stream, 'anchor'); */ return 'time';",
            $time,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertSame([], $this->forbiddenEffects($this->parseSource($inertNativeText), 'AuditAnchorTime'));
    }

    /** @return array<string,string> */
    private function anchorSources(): array
    {
        $sources = [];
        foreach ($this->sourceFiles(self::SOURCE_ROOT) as $path => $source) {
            if (str_starts_with($path, 'Internal/Audit/Anchor/')) {
                $sources[$path] = $source;
            }
        }
        ksort($sources);
        return $sources;
    }

    /** @return array<string,string> */
    private function sourceFiles(string $root): array
    {
        $sources = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $root,
            \FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $source = file_get_contents($path);
            self::assertIsString($source);
            $relative = substr($path, strlen($root) + 1);
            $sources[str_replace(DIRECTORY_SEPARATOR, '/', $relative)] = $source;
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
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor(new NameResolver());
        $resolved = $traverser->traverse($nodes);
        self::assertIsArray($resolved);
        return $resolved;
    }

    private function declaration(string $source): ClassLike
    {
        $nodes = $this->parseSource($source);
        $declarations = [];
        foreach ([Class_::class, Enum_::class, Node\Stmt\Interface_::class, Node\Stmt\Trait_::class] as $kind) {
            foreach (new NodeFinder()->findInstanceOf($nodes, $kind) as $declaration) {
                if ($declaration instanceof ClassLike) {
                    $declarations[] = $declaration;
                }
            }
        }
        self::assertCount(1, $declarations);
        self::assertSame(
            [],
            new NodeFinder()->findInstanceOf($nodes, Node\Stmt\Function_::class),
            'top-level functions are closed',
        );
        return $declarations[0];
    }

    private function typeForFile(string $file): string
    {
        $name = basename($file, '.php');
        self::assertContains($name, self::TYPES);
        return $name;
    }

    private function declarationName(ClassLike $declaration): string
    {
        return ltrim($declaration->namespacedName?->toString() ?? $declaration->name?->toString() ?? '', '\\');
    }

    /** @return list<ClassMethod> */
    private function classMethods(ClassLike $declaration): array
    {
        return array_values(array_filter(
            $declaration->stmts ?? [],
            static fn(Node $statement): bool => $statement instanceof ClassMethod,
        ));
    }

    /** @return array<string,string> */
    private function methodSurface(ClassLike $declaration): array
    {
        $surface = [];
        foreach ($this->classMethods($declaration) as $method) {
            $visibility = $method->isPublic() ? 'public' : ($method->isPrivate() ? 'private' : 'protected');
            $surface[$method->name->toString()] = $visibility . ($method->isStatic() ? ' static' : '');
        }
        return $surface;
    }

    /** @return array<string,array{visibility:string,static:bool,returnsByRef:bool,return:array{type:?string,allowsNull:bool},params:list<array{name:string,type:?string,allowsNull:bool,sensitive:bool,hasDefault:bool,default:?string,promoted:bool,readonly:bool,byRef:bool,variadic:bool}>}> */
    private function methodSignatureInventory(ClassLike $declaration): array
    {
        $inventory = [];
        $owner = $this->declarationName($declaration);
        foreach ($this->classMethods($declaration) as $method) {
            $params = [];
            foreach ($method->params as $parameter) {
                $attributes = [];
                foreach ($parameter->attrGroups as $group) {
                    foreach ($group->attrs as $attribute) {
                        $attributes[] = $attribute->name->getLast();
                    }
                }
                $params[] = [
                    'name' => is_string($parameter->var->name) ? $parameter->var->name : '',
                    'type' => $this->typeName($parameter->type, $owner),
                    'allowsNull' => $parameter->type instanceof Node\NullableType,
                    'sensitive' => in_array('SensitiveParameter', $attributes, true),
                    'hasDefault' => $parameter->default !== null,
                    'default' => $parameter->default === null ? null : $this->defaultSignature($parameter->default),
                    'promoted' => $this->isPromoted($parameter),
                    'readonly' =>
                        $this->isPromoted($parameter) && ($parameter->flags & Class_::MODIFIER_READONLY) !== 0,
                    'byRef' => $parameter->byRef,
                    'variadic' => $parameter->variadic,
                ];
            }
            $inventory[$method->name->toString()] = [
                'visibility' => $method->isPublic() ? 'public' : ($method->isPrivate() ? 'private' : 'protected'),
                'static' => $method->isStatic(),
                'returnsByRef' => $method->returnsByRef(),
                'return' => [
                    'type' => $this->typeName($method->returnType, $owner),
                    'allowsNull' => $method->returnType instanceof Node\NullableType,
                ],
                'params' => $params,
            ];
        }
        return $inventory;
    }

    private function isPromoted(Node\Param $parameter): bool
    {
        return (
            (
                $parameter->flags
                & (
                    Class_::MODIFIER_PUBLIC
                    | Class_::MODIFIER_PROTECTED
                    | Class_::MODIFIER_PRIVATE
                    | Class_::MODIFIER_READONLY
                )
            )
            !== 0
        );
    }

    private function defaultSignature(Node $default): string
    {
        if ($default instanceof Node\Scalar\String_) {
            return 'string:' . $default->value;
        }
        if ($default instanceof Node\Scalar\LNumber) {
            return 'int:' . $default->value;
        }
        if ($default instanceof Node\Expr\ConstFetch && $default->name instanceof Name) {
            return 'const:' . strtolower($default->name->toString());
        }
        if ($default instanceof Array_ && $default->items === []) {
            return 'array:[]';
        }
        return '@' . $default->getType();
    }

    /** @return array<string,array{visibility:string,static:bool,readonly:bool,promoted:bool,type:?string}> */
    private function propertyInventory(ClassLike $declaration): array
    {
        $inventory = [];
        foreach ($declaration->stmts ?? [] as $statement) {
            if (!$statement instanceof Property) {
                continue;
            }
            foreach ($statement->props as $property) {
                $inventory[$property->name->toString()] = [
                    'visibility' => $statement->isPublic()
                        ? 'public'
                        : ($statement->isPrivate() ? 'private' : 'protected'),
                    'static' => $statement->isStatic(),
                    'readonly' => $statement->isReadonly(),
                    'promoted' => false,
                    'type' => $this->typeName($statement->type, $this->declarationName($declaration)),
                ];
            }
        }
        foreach ($this->classMethods($declaration) as $method) {
            if ($method->name->toString() !== '__construct') {
                continue;
            }
            foreach ($method->params as $parameter) {
                if (!$this->isPromoted($parameter) || !is_string($parameter->var->name)) {
                    continue;
                }
                $inventory[$parameter->var->name] = [
                    'visibility' => ($parameter->flags & Class_::MODIFIER_PUBLIC) !== 0
                        ? 'public'
                        : (($parameter->flags & Class_::MODIFIER_PROTECTED) !== 0 ? 'protected' : 'private'),
                    'static' => false,
                    'readonly' => ($parameter->flags & Class_::MODIFIER_READONLY) !== 0,
                    'promoted' => true,
                    'type' => $this->typeName($parameter->type, $this->declarationName($declaration)),
                ];
            }
        }
        return $inventory;
    }

    /** @return array<string,array<string,array{visibility:string,static:bool,readonly:bool,promoted:bool,type:?string}>> */
    private function expectedPropertyInventory(): array
    {
        $a = static fn(string $type): string => self::ANCHOR_NAMESPACE . '\\' . $type;
        $snapshot = [
            'visibility' => 'private',
            'static' => false,
            'readonly' => false,
            'promoted' => false,
            'type' => 'SensitiveParameterValue',
        ];
        $configuration = [
            'visibility' => 'private',
            'static' => false,
            'readonly' => true,
            'promoted' => true,
            'type' => $a('AuditAnchorConfiguration'),
        ];
        $publicInt = static fn(): array => [
            'visibility' => 'public',
            'static' => false,
            'readonly' => false,
            'promoted' => false,
            'type' => 'int',
        ];
        return [
            'AuditAnchorBinding' => ['snapshot' => $snapshot],
            'AuditAnchorCanonical' => [],
            'AuditAnchorClaims' => ['configuration' => $configuration],
            'AuditAnchorConfiguration' => [
                'leaseDurationSeconds' => $publicInt(),
                'retryDelaySeconds' => $publicInt(),
            ],
            'AuditAnchorException' => ['failure' => [
                'visibility' => 'private',
                'static' => false,
                'readonly' => true,
                'promoted' => true,
                'type' => $a('AuditAnchorFailure'),
            ]],
            'AuditAnchorExpectation' => ['snapshot' => $snapshot],
            'AuditAnchorExternalOutcome' => [],
            'AuditAnchorFailure' => [],
            'AuditAnchorFrame' => ['snapshot' => $snapshot],
            'AuditAnchorIntent' => ['snapshot' => $snapshot],
            'AuditAnchorIntentShape' => [],
            'AuditAnchorLeaseOrigin' => ['snapshot' => $snapshot],
            'AuditAnchorPostCallTransition' => ['configuration' => $configuration],
            'AuditAnchorPreCallTransition' => ['configuration' => $configuration],
            'AuditAnchorPreparedShape' => [],
            'AuditAnchorReceiptBody' => [],
            'AuditAnchorReceiptShape' => [],
            'AuditAnchorReceiptTransition' => ['configuration' => $configuration],
            'AuditAnchorReconciliationShape' => [],
            'AuditAnchorScalar' => [],
            'AuditAnchorState' => [],
            'AuditAnchorTime' => [],
            'AuditAnchorTransitionGuard' => [],
        ];
    }

    /** @return array<string,array<string,array{visibility:string,static:bool,returnsByRef:bool,return:array{type:?string,allowsNull:bool},params:list<array{name:string,type:?string,allowsNull:bool,sensitive:bool,hasDefault:bool,default:?string,promoted:bool,readonly:bool,byRef:bool,variadic:bool}>}>> */
    private function expectedMethodSignatures(): array
    {
        $a = static fn(string $type): string => self::ANCHOR_NAMESPACE . '\\' . $type;
        $p = static function (
            string $name,
            ?string $type,
            bool $allowsNull = false,
            bool $sensitive = false,
            bool $hasDefault = false,
            ?string $default = null,
            bool $promoted = false,
            bool $readonly = false,
        ): array {
            return [
                'name' => $name,
                'type' => $type,
                'allowsNull' => $allowsNull,
                'sensitive' => $sensitive,
                'hasDefault' => $hasDefault,
                'default' => $default,
                'promoted' => $promoted,
                'readonly' => $readonly,
                'byRef' => false,
                'variadic' => false,
            ];
        };
        $m = static function (
            string $visibility,
            bool $static,
            ?string $return,
            bool $allowsNull = false,
            array $params = [],
        ): array {
            return [
                'visibility' => $visibility,
                'static' => $static,
                'returnsByRef' => false,
                'return' => ['type' => $return, 'allowsNull' => $allowsNull],
                'params' => $params,
            ];
        };
        $void = static fn(string $visibility, bool $static, array $params = []): array => $m(
            $visibility,
            $static,
            'void',
            false,
            $params,
        );
        $ctor = static fn(string $visibility, array $params = []): array => $m(
            $visibility,
            false,
            null,
            false,
            $params,
        );
        $array = static fn(string $visibility, bool $static, array $params = []): array => $m(
            $visibility,
            $static,
            'array',
            false,
            $params,
        );
        $string = static fn(string $visibility, bool $static, array $params = []): array => $m(
            $visibility,
            $static,
            'string',
            false,
            $params,
        );
        $selfReturn = static fn(string $type, string $visibility, bool $static, array $params = []): array => $m(
            $visibility,
            $static,
            $a($type),
            false,
            $params,
        );
        $frame = static fn(string $type): string => $a('AuditAnchor' . $type);
        $four = static fn() => [
            $p('frame', $frame('Frame'), false, true),
            $p('expected', $frame('Expectation'), false, true),
            $p('outcome', 'array', false, true),
            $p('trustedNow', 'string', false, true),
        ];
        $claim = static fn() => [
            $p('frame', $frame('Frame'), false, true),
            $p('expected', $frame('Expectation'), false, true),
            $p('newToken', 'string', false, true),
            $p('trustedNow', 'string', false, true),
        ];
        $cas = static fn() => [
            $p('frame', $frame('Frame'), false, true),
            $p('expected', $frame('Expectation'), false, true),
            $p('trustedNow', 'string', false, true),
        ];

        return [
            'AuditAnchorBinding' => [
                '__construct' => $ctor('private', [$p('value', 'array', false, true)]),
                'fromArray' => $selfReturn('AuditAnchorBinding', 'public', true, [$p('value', 'array', false, true)]),
                'toArray' => $array('public', false),
                'assertIntentAssociation' => $void('public', true, [
                    $p('intent', 'array', false, true),
                    $p('binding', 'array', false, true),
                ]),
                '__debugInfo' => $array('public', false),
                '__serialize' => $array('public', false),
                '__unserialize' => $void('public', false, [$p('data', 'array', false, true)]),
            ],
            'AuditAnchorCanonical' => [
                'stateHash' => $string('public', true, [$p('intent', 'array', false, true)]),
                'idempotencyKeyHash' => $string('public', true, [
                    $p('intentId', 'string', false, true),
                    $p('rawKey', 'string', false, true),
                ]),
                'receiptPreimageHash' => $string('public', true, [$p('body', 'array', false, true)]),
                'receiptBytesHash' => $string('public', true, [$p('body', 'array', false, true)]),
                'jcs' => $string('public', true, [$p('value', 'array', false, true)]),
                'digestParts' => $string('private', true, [
                    $p('domain', 'string', false, true),
                    $p('parts', 'array', false, true),
                ]),
                'digest' => $string('private', true, [
                    $p('domain', 'string', false, true),
                    $p('bytes', 'string', false, true),
                ]),
                'typedDigest' => $string('private', true, [
                    $p('domain', 'string', false, true),
                    $p('bytes', 'string', false, true),
                ]),
            ],
            'AuditAnchorClaims' => [
                '__construct' => $ctor('public', [$p(
                    'configuration',
                    $frame('Configuration'),
                    false,
                    false,
                    false,
                    null,
                    true,
                    true,
                )]),
                'externalSubmission' => $selfReturn('AuditAnchorFrame', 'public', false, $claim()),
                'preCallRetry' => $selfReturn('AuditAnchorFrame', 'public', false, $claim()),
                'externalReconciliation' => $selfReturn('AuditAnchorFrame', 'public', false, $claim()),
                'claim' => $selfReturn('AuditAnchorFrame', 'private', false, [...$claim(), $p('operation', 'string')]),
            ],
            'AuditAnchorConfiguration' => [
                '__construct' => $ctor('public', [
                    $p('leaseDurationSeconds', 'mixed', false, true, true, 'int:300'),
                    $p('retryDelaySeconds', 'mixed', false, true, true, 'int:300'),
                ]),
                'assertSeconds' => $m('private', true, 'int', false, [$p('value', 'mixed', false, true)]),
            ],
            'AuditAnchorException' => [
                '__construct' => $ctor('public', [$p(
                    'failure',
                    $frame('Failure'),
                    false,
                    false,
                    false,
                    null,
                    true,
                    true,
                )]),
                'failure' => $m('public', false, $frame('Failure')),
                'code' => $string('public', false),
            ],
            'AuditAnchorExpectation' => [
                '__construct' => $ctor('public', [
                    $p('version', 'int'),
                    $p('stateHash', 'string', true, true),
                    $p('token', 'string', true, true),
                ]),
                'toArray' => $array('public', false),
                '__debugInfo' => $array('public', false),
                '__serialize' => $array('public', false),
                '__unserialize' => $void('public', false, [$p('data', 'array', false, true)]),
            ],
            'AuditAnchorExternalOutcome' => [
                'result' => $array('public', true, [$p('value', 'array', false, true)]),
                'reconciliation' => $array('public', true, [$p('value', 'array', false, true)]),
                'lookup' => $array('public', true, [$p('value', 'array', false, true)]),
                'nullableInteger' => $m('private', true, 'int', true, [$p('value', 'mixed', false, true)]),
                'positiveInteger' => $m('private', true, 'int', false, [$p('value', 'mixed', false, true)]),
            ],
            'AuditAnchorFrame' => [
                '__construct' => $ctor('private', [
                    $p('intent', $frame('Intent'), false, true),
                    $p('binding', $frame('Binding'), false, true),
                    $p('leaseOrigin', $frame('LeaseOrigin'), true, true),
                ]),
                'create' => $selfReturn('AuditAnchorFrame', 'public', true, [
                    $p('request', 'array', false, true),
                    $p('binding', $frame('Binding'), false, true),
                    $p('rawIdempotencyKey', 'string', false, true),
                    $p('expectation', $frame('Expectation'), false, true),
                    $p('trustedNow', 'string', false, true),
                ]),
                'restore' => $selfReturn('AuditAnchorFrame', 'public', true, [$p('value', 'array', false, true)]),
                'intent' => $m('public', false, $frame('Intent')),
                'binding' => $m('public', false, $frame('Binding')),
                'leaseOrigin' => $m('public', false, $frame('LeaseOrigin'), true),
                'toArray' => $array('public', false),
                '__debugInfo' => $array('public', false),
                '__serialize' => $array('public', false),
                '__unserialize' => $void('public', false, [$p('data', 'array', false, true)]),
            ],
            'AuditAnchorIntent' => [
                '__construct' => $ctor('private', [$p('value', 'array', false, true)]),
                'fromArray' => $selfReturn('AuditAnchorIntent', 'public', true, [$p('value', 'array', false, true)]),
                'toArray' => $array('public', false),
                'state' => $m('public', false, $frame('State')),
                'version' => $m('public', false, 'int'),
                'assertTransitionImmutable' => $void('public', true, [
                    $p('current', 'array', false, true),
                    $p('candidate', 'array', false, true),
                ]),
                '__debugInfo' => $array('public', false),
                '__serialize' => $array('public', false),
                '__unserialize' => $void('public', false, [$p('data', 'array', false, true)]),
            ],
            'AuditAnchorIntentShape' => [
                'normalize' => $array('public', true, [$p('value', 'array', false, true)]),
                'assertClosed' => $void('public', true, [
                    $p('value', 'array', false, true),
                    $p('keys', 'array', false, true),
                ]),
                'assertText' => $string('public', true, [
                    $p('value', 'mixed', false, true),
                    $p('minimum', 'int', false, false, true, 'int:1'),
                    $p('maximum', 'int', false, false, true, 'int:256'),
                ]),
                'assertHash' => $m('public', true, 'string', true, [
                    $p('value', 'mixed', false, true),
                    $p('mode', 'string', false, false, true, 'string:required'),
                ]),
                'assertPartition' => $string('public', true, [$p('value', 'mixed', false, true)]),
            ],
            'AuditAnchorLeaseOrigin' => [
                '__construct' => $ctor('private', [$p('value', 'array', false, true)]),
                'fromArray' => $selfReturn('AuditAnchorLeaseOrigin', 'public', true, [$p(
                    'value',
                    'array',
                    false,
                    true,
                )]),
                'fromClaim' => $selfReturn('AuditAnchorLeaseOrigin', 'public', true, [
                    $p('operation', 'string'),
                    $p('intent', $frame('Intent'), false, true),
                ]),
                'positiveInteger' => $m('private', true, 'int', false, [$p('value', 'mixed', false, true)]),
                'toArray' => $array('public', false),
                'assertAssociation' => $void('public', true, [
                    $p('intent', 'array', false, true),
                    $p('origin', 'array', true, true),
                ]),
                '__debugInfo' => $array('public', false),
                '__serialize' => $array('public', false),
                '__unserialize' => $void('public', false, [$p('data', 'array', false, true)]),
            ],
            'AuditAnchorPostCallTransition' => [
                '__construct' => $ctor('public', [$p(
                    'configuration',
                    $frame('Configuration'),
                    false,
                    false,
                    false,
                    null,
                    true,
                    true,
                )]),
                'result' => $selfReturn('AuditAnchorFrame', 'public', false, $four()),
                'reconcile' => $selfReturn('AuditAnchorFrame', 'public', false, $four()),
                'reclaim' => $selfReturn('AuditAnchorFrame', 'public', false, $cas()),
                'active' => $void('private', false, [
                    $p('frame', $frame('Frame'), false, true),
                    $p('value', 'array', false, true),
                    $p('trustedNow', 'string', false, true),
                    $p('leaseState', 'string'),
                    $p('operations', 'array', false, true),
                ]),
                'accepted' => $array('private', true, [
                    $p('value', 'array', false, true),
                    $p('accepted', 'array', false, true),
                    $p('binding', 'array', false, true),
                ]),
            ],
            'AuditAnchorPreCallTransition' => [
                '__construct' => $ctor('public', [$p(
                    'configuration',
                    $frame('Configuration'),
                    false,
                    false,
                    false,
                    null,
                    true,
                    true,
                )]),
                'lookup' => $selfReturn('AuditAnchorFrame', 'public', false, $four()),
                'callStart' => $selfReturn('AuditAnchorFrame', 'public', false, $cas()),
                'reclaim' => $selfReturn('AuditAnchorFrame', 'public', false, $cas()),
                'activePending' => $void('private', false, [
                    $p('frame', $frame('Frame'), false, true),
                    $p('value', 'array', false, true),
                    $p('trustedNow', 'string', false, true),
                    $p('status', 'string', true),
                    $p('operations', 'array', false, true),
                ]),
                'accepted' => $array('private', true, [
                    $p('value', 'array', false, true),
                    $p('found', 'array', false, true),
                    $p('binding', 'array', false, true),
                ]),
            ],
            'AuditAnchorPreparedShape' => [
                'assert' => $void('public', true, [$p('value', 'array', false, true)]),
                'prepared' => $void('private', true, [$p('value', 'array', false, true)]),
                'rejected' => $void('private', true, [$p('value', 'array', false, true)]),
                'leased' => $m('private', true, 'bool', false, [$p('value', 'array', false, true)]),
            ],
            'AuditAnchorReceiptBody' => [
                'build' => $array('public', true, [
                    $p('intent', $frame('Intent'), false, true),
                    $p('signingKeyRef', 'string', false, true),
                    $p('signedAt', 'string', false, true),
                ]),
                'assertPersistedHashes' => $void('public', true, [
                    $p('value', 'array', false, true),
                    $p('signingKeyRef', 'string', false, true),
                    $p('acceptedAnchoredAt', 'string', false, true),
                    $p('receiptSignedAt', 'string', false, true),
                ]),
                'assertPublicReceiptHash' => $void('public', true, [
                    $p('intent', $frame('Intent'), false, true),
                ]),
                'unsignedBody' => $array('private', true, [
                    $p('value', 'array', false, true),
                    $p('signingKeyRef', 'string', false, true),
                    $p('acceptedAnchoredAt', 'string', false, true),
                    $p('receiptSignedAt', 'string', false, true),
                ]),
            ],
            'AuditAnchorReceiptShape' => [
                'assert' => $void('public', true, [$p('value', 'array', false, true)]),
                'assertPublicReceiptHash' => $void('public', true, [$p('intent', $frame('Intent'), false, true)]),
            ],
            'AuditAnchorReceiptTransition' => [
                '__construct' => $ctor('public', [$p(
                    'configuration',
                    $frame('Configuration'),
                    false,
                    false,
                    false,
                    null,
                    true,
                    true,
                )]),
                'inputClaim' => $selfReturn('AuditAnchorFrame', 'public', false, $claim()),
                'persistClaim' => $selfReturn('AuditAnchorFrame', 'public', false, $claim()),
                'acknowledgementClaim' => $selfReturn('AuditAnchorFrame', 'public', false, $claim()),
                'inputPersist' => $selfReturn('AuditAnchorFrame', 'public', false, [
                    $p('frame', $frame('Frame'), false, true),
                    $p('expected', $frame('Expectation'), false, true),
                    $p('signingKeyRef', 'string', false, true),
                    $p('trustedNow', 'string', false, true),
                ]),
                'persist' => $selfReturn('AuditAnchorFrame', 'public', false, [
                    $p('frame', $frame('Frame'), false, true),
                    $p('expected', $frame('Expectation'), false, true),
                    $p('anchorReceiptHash', 'string', false, true),
                    $p('trustedNow', 'string', false, true),
                ]),
                'acknowledge' => $selfReturn('AuditAnchorFrame', 'public', false, $cas()),
                'reclaim' => $selfReturn('AuditAnchorFrame', 'public', false, $cas()),
                'claim' => $selfReturn('AuditAnchorFrame', 'private', false, [...$claim(), $p('operation', 'string')]),
                'active' => $void('private', false, [
                    $p('frame', $frame('Frame'), false, true),
                    $p('expected', $frame('Expectation'), false, true),
                    $p('value', 'array', false, true),
                    $p('trustedNow', 'string', false, true),
                    $p('operation', 'string'),
                ]),
            ],
            'AuditAnchorReconciliationShape' => [
                'normalize' => $array('public', true, [$p('value', 'array', false, true)]),
                'assertSegment' => $void('private', true, [$p('value', 'mixed', false, true)]),
            ],
            'AuditAnchorScalar' => [
                'text' => $string('public', true, [
                    $p('value', 'mixed', false, true),
                    $p('minimum', 'int', false, false, true, 'int:1'),
                    $p('maximum', 'int', false, false, true, 'int:256'),
                ]),
                'hash' => $m('public', true, 'string', true, [
                    $p('value', 'mixed', false, true),
                    $p('mode', 'string', false, false, true, 'string:required'),
                ]),
                'integer' => $m('public', true, 'int', false, [
                    $p('value', 'mixed', false, true),
                    $p('minimum', 'int'),
                    $p('maximum', 'int'),
                ]),
                'nullableInteger' => $m('public', true, 'int', true, [
                    $p('value', 'mixed', false, true),
                    $p('minimum', 'int'),
                    $p('maximum', 'int'),
                ]),
                'nullableTimestamp' => $m('public', true, 'string', true, [$p('value', 'mixed', false, true)]),
                'nullableAllowed' => $void('public', true, [
                    $p('value', 'mixed', false, true),
                    $p('allowed', 'array', false, true),
                ]),
                'nullableText' => $m('public', true, 'string', true, [$p('value', 'mixed', false, true)]),
            ],
            'AuditAnchorTime' => [
                'assertTimestamp' => $string('public', true, [$p('value', 'mixed', false, true)]),
                'assertTransitionTimestamp' => $string('public', true, [$p('value', 'mixed', false, true)]),
                'addSeconds' => $string('public', true, [$p('value', 'string', false, true), $p('seconds', 'int')]),
                'compare' => $m('public', true, 'int', false, [
                    $p('left', 'string', false, true),
                    $p('right', 'string', false, true),
                ]),
            ],
            'AuditAnchorTransitionGuard' => [
                'assertExpected' => $void('public', true, [
                    $p('frame', $frame('Frame'), false, true),
                    $p('expected', $frame('Expectation'), false, true),
                ]),
                'advance' => $selfReturn('AuditAnchorFrame', 'public', true, [
                    $p('frame', $frame('Frame'), false, true),
                    $p('expected', $frame('Expectation'), false, true),
                    $p('candidate', 'array', false, true),
                    $p('trustedNow', 'string', false, true),
                    $p('claimOperation', 'string', true, false, true, 'const:null'),
                ]),
            ],
        ];
    }

    /** @return array<string,string> */
    private function enumCases(ClassLike $declaration): array
    {
        $cases = [];
        foreach ($declaration->stmts ?? [] as $statement) {
            if (!$statement instanceof EnumCase || !$statement->name instanceof Identifier) {
                continue;
            }
            $value = $statement->expr;
            $cases[$statement->name->toString()] = $value instanceof Node\Scalar\String_ ? $value->value : '';
        }
        return $cases;
    }

    /** @return list<string> */
    private function propertyNames(ClassLike $declaration): array
    {
        $names = [];
        foreach ($declaration->stmts ?? [] as $statement) {
            if (!$statement instanceof Property) {
                continue;
            }
            foreach ($statement->props as $property) {
                $names[] = $property->name->toString();
            }
        }
        return $names;
    }

    private function typeName(?Node $type, string $owner = ''): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof Node\NullableType) {
            return $this->typeName($type->type, $owner);
        }
        if ($type instanceof Name) {
            return $this->resolvedName($type, $owner);
        }
        return $type->toString();
    }

    /** @return list<string> */
    private function dependencyInventory(string $source, string $owner, bool $internal): array
    {
        $found = [];
        foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), Name::class) as $name) {
            if (!$name instanceof Name) {
                continue;
            }
            $parent = $name->getAttribute('parent');
            if (
                $parent instanceof FuncCall && $parent->name === $name
                || $parent instanceof Node\Expr\ConstFetch && $parent->name === $name
            ) {
                continue;
            }
            $resolved = $this->resolvedName($name, self::ANCHOR_NAMESPACE . '\\' . $owner);
            if ($resolved === self::ANCHOR_NAMESPACE || $resolved === self::ANCHOR_NAMESPACE . '\\' . $owner) {
                continue;
            }
            $isAnchor = str_starts_with($resolved, self::ANCHOR_NAMESPACE . '\\');
            if ($internal !== $isAnchor) {
                continue;
            }
            if ($internal) {
                $found[] = substr($resolved, strlen(self::ANCHOR_NAMESPACE) + 1);
                continue;
            }
            $found[] = self::EXTERNAL_SHORT_NAMES[$resolved] ?? '@' . $resolved;
        }
        $found = array_values(array_unique($found));
        sort($found);
        return $found;
    }

    private function resolvedName(Name $name, string $owner): string
    {
        $raw = ltrim($name->toString(), '\\');
        if ($raw === 'self' || $raw === 'static') {
            return $owner;
        }
        if ($raw === 'parent') {
            return $owner === self::ANCHOR_NAMESPACE . '\\AuditAnchorException' ? 'RuntimeException' : $raw;
        }
        $resolved = $name->getAttribute('resolvedName');
        if ($resolved instanceof Name) {
            return ltrim($resolved->toString(), '\\');
        }
        $namespaced = $name->getAttribute('namespacedName');
        if ($namespaced instanceof Name) {
            return ltrim($namespaced->toString(), '\\');
        }
        return $raw;
    }

    /** @param array<string,string> $sources @return array<string,array<string,int>> */
    private function staticCallInventory(array $sources, string $target): array
    {
        $inventory = [];
        foreach ($sources as $path => $source) {
            $nodes = $this->parseSource($source);
            $calls = [];
            foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
                if (
                    !$call instanceof StaticCall
                    || !$call->class instanceof Name
                    || !$call->name instanceof Identifier
                ) {
                    continue;
                }
                if ($this->resolvedName($call->class, '') !== $target) {
                    continue;
                }
                $name = $call->name->toString();
                if ($target === 'BlackOps\\Core\\Audit\\AuditRecordTime') {
                    $calls[$name] = ($calls[$name] ?? 0) + 1;
                } else {
                    $calls[$target . '::' . $name] = ($calls[$target . '::' . $name] ?? 0) + 1;
                }
            }
            if ($calls !== []) {
                if ($target === 'BlackOps\\Core\\Audit\\AuditRecordTime') {
                    $inventory[$path] = $calls;
                } else {
                    ksort($calls);
                    $inventory[$path] = $calls;
                }
            }
        }
        ksort($inventory);
        return $inventory;
    }

    /** @param array<string,string> $sources @return array<string,array<string,int>> */
    private function transitionGuardCallInventory(array $sources): array
    {
        $target = self::ANCHOR_NAMESPACE . '\\AuditAnchorTransitionGuard';
        $allowed = [
            'AuditAnchorClaims' => ['claim' => [5, 0, 1]],
            'AuditAnchorPostCallTransition' => [
                'result' => [4, 0, 2],
                'reconcile' => [4, 1, 3],
                'reclaim' => [4, 0, 1],
            ],
            'AuditAnchorPreCallTransition' => ['lookup' => [4, 1, 3], 'callStart' => [4, 0, 1], 'reclaim' => [4, 0, 1]],
            'AuditAnchorReceiptTransition' => [
                'inputPersist' => [4, 0, 1],
                'persist' => [4, 0, 1],
                'acknowledge' => [4, 0, 1],
                'reclaim' => [4, 0, 1],
                'claim' => [5, 0, 1],
            ],
        ];
        $inventory = [];
        foreach ($sources as $path => $source) {
            $declaration = $this->declaration($source);
            $owner = $this->declarationName($declaration);
            $type = basename($path, '.php');
            $calls = [];
            $acceptedCounts = [];
            foreach ($this->classMethods($declaration) as $method) {
                foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
                    if (
                        !$call instanceof StaticCall
                        || !$call->class instanceof Name
                        || !$call->name instanceof Identifier
                    ) {
                        continue;
                    }
                    if (
                        $this->resolvedName($call->class, $owner) !== $target
                        || $call->name->toString() !== 'advance'
                    ) {
                        continue;
                    }
                    $methodName = $method->name->toString();
                    $calls[$methodName] = ($calls[$methodName] ?? 0) + 1;
                    $shape = $allowed[$type][$methodName] ?? null;
                    $valid = $shape !== null && count($call->args) === $shape[0];
                    foreach ([['frame', 0], ['expected', 1], ['trustedNow', 3]] as [$name, $index]) {
                        $valid =
                            $valid
                            && isset($call->args[$index])
                            && $call->args[$index]->value instanceof Variable
                            && $call->args[$index]->value->name === $name;
                    }
                    $candidate = $call->args[2]->value ?? null;
                    $candidateAccepted = $this->directAcceptedCandidate($candidate, $owner);
                    if ($candidateAccepted) {
                        $acceptedCounts[$methodName] = ($acceptedCounts[$methodName] ?? 0) + 1;
                    }
                    $candidateRevision =
                        $candidate instanceof Variable
                        && is_string($candidate->name)
                        && $this->candidateRevisionProvenance($method, $candidate->name, $call);
                    $valid = $valid && ($candidateAccepted || $candidateRevision);
                    if ($valid && $shape[0] === 5) {
                        $valid = $this->operationArgumentValid($method, $call);
                    }
                    if ($shape === null || !$valid) {
                        $calls[$methodName . '::__invalid'] = ($calls[$methodName . '::__invalid'] ?? 0) + 1;
                    }
                }
            }
            foreach ($allowed[$type] ?? [] as $methodName => $shape) {
                $methodCalls = $calls[$methodName] ?? 0;
                if ($methodCalls !== $shape[2] || ($acceptedCounts[$methodName] ?? 0) !== $shape[1]) {
                    $calls[$methodName . '::__invalid'] = ($calls[$methodName . '::__invalid'] ?? 0) + 1;
                }
            }
            if ($calls !== []) {
                $inventory[$path] = $calls;
            }
        }
        ksort($inventory);
        return $inventory;
    }

    private function directAcceptedCandidate(?Node $candidate, string $owner): bool
    {
        return (
            $candidate instanceof StaticCall
            && $candidate->class instanceof Name
            && $this->resolvedName($candidate->class, $owner) === $owner
            && $candidate->name instanceof Identifier
            && $candidate->name->toString() === 'accepted'
            && count($candidate->args) === 3
            && $candidate->args[0]->value instanceof Variable
            && $candidate->args[0]->value->name === 'value'
            && $candidate->args[1]->value instanceof Array_
            && $candidate->args[2]->value instanceof Variable
            && $candidate->args[2]->value->name === 'binding'
        );
    }

    private function candidateRevisionProvenance(ClassMethod $method, string $candidate, StaticCall $call): bool
    {
        $latest = null;
        foreach (new NodeFinder()->findInstanceOf($method, Node\Expr\Assign::class) as $assign) {
            if (
                !$assign->var instanceof Variable
                || $assign->var->name !== $candidate
                || $assign->getStartFilePos() > $call->getStartFilePos()
            ) {
                continue;
            }
            $latest = $assign;
        }
        if (!$latest instanceof Node\Expr\Assign || !$this->revisionSourceExpression($latest->expr, $method, $latest)) {
            return false;
        }
        if ($latest->expr instanceof FuncCall) {
            return true;
        }
        foreach (new NodeFinder()->findInstanceOf($method, Node\Expr\Assign::class) as $assign) {
            if (
                !$assign->var instanceof Node\Expr\ArrayDimFetch
                || !$assign->var->var instanceof Variable
                || $assign->var->var->name !== $candidate
                || $assign->getStartFilePos() < $latest->getStartFilePos()
                || $assign->getStartFilePos() > $call->getStartFilePos()
            ) {
                continue;
            }
            return $assign->var->dim instanceof Node\Scalar\String_;
        }
        return false;
    }

    private function revisionSourceExpression(Node $expression, ClassMethod $method, Node\Expr\Assign $assignment): bool
    {
        if ($expression instanceof Variable && is_string($expression->name)) {
            return $expression->name === 'value' || $this->arraySourceAlias($method, $expression->name, $assignment);
        }
        if (!$expression instanceof FuncCall || !$expression->name instanceof Name) {
            return false;
        }
        $resolved = $expression->name->getAttribute('resolvedName');
        $name = strtolower(ltrim(
            $resolved instanceof Name ? $resolved->toString() : $expression->name->toString(),
            '\\',
        ));
        return (
            $name === 'array_replace'
            && isset($expression->args[0]->value)
            && $expression->args[0]->value instanceof Variable
            && $expression->args[0]->value->name === 'value'
            && isset($expression->args[1]->value)
            && $this->revisionPatchConforms($expression->args[1]->value, $method, $assignment)
        );
    }

    private function revisionPatchConforms(Node $patch, ClassMethod $method, Node\Expr\Assign $assignment): bool
    {
        if ($patch instanceof Array_) {
            return $this->literalRevisionPatchConforms($patch, $method, $assignment);
        }
        if (!$patch instanceof Node\Expr\ArrayDimFetch || !$patch->var instanceof Array_) {
            return false;
        }
        if (!$patch->dim instanceof Variable || $patch->dim->name !== 'status') {
            return false;
        }
        return $this->literalPatchMapConforms($patch->var, [
            'accepted' => [
                'state',
                'acceptedObjectVersion',
                'acceptedObjectHash',
                'acceptedAnchoredAt',
                'anchorReceiptId',
                'segmentId',
                'previousAnchorReceiptHash',
                'externalCallPhase',
                'preCallClaimMode',
                'preCallLookupStatus',
                'leaseFencingToken',
                'leaseExpiresAt',
                'nextAvailableAt',
            ],
            'rejected' => [
                'state',
                'acceptedObjectVersion',
                'acceptedObjectHash',
                'acceptedAnchoredAt',
                'anchorReceiptId',
                'segmentId',
                'previousAnchorReceiptHash',
                'terminalReasonCode',
                'externalCallPhase',
                'preCallClaimMode',
                'preCallLookupStatus',
                'leaseFencingToken',
                'leaseExpiresAt',
                'nextAvailableAt',
            ],
        ]) && $this->selectorAssignmentConforms($method, 'status', 'status', $assignment);
    }

    private function literalRevisionPatchConforms(
        Array_ $patch,
        ClassMethod $method,
        Node\Expr\Assign $assignment,
    ): bool {
        if ($patch->items === []) {
            return false;
        }
        $unpacked = 0;
        foreach ($patch->items as $item) {
            if (!$item instanceof ArrayItem) {
                return false;
            }
            if ($item->unpack) {
                ++$unpacked;
                if (
                    $unpacked > 1
                    || !$item->value instanceof Variable
                    || !is_string($item->value->name)
                    || !$this->boundedPatchAliasConforms($method, $item->value->name, $assignment)
                ) {
                    return false;
                }
                continue;
            }
            if (!$item->key instanceof Node\Scalar\String_) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,list<string>> $expected */
    private function literalPatchMapConforms(Array_ $map, array $expected): bool
    {
        $actual = [];
        foreach ($map->items as $item) {
            if (
                !$item instanceof ArrayItem
                || $item->unpack
                || !$item->key instanceof Node\Scalar\String_
                || !$item->value instanceof Array_
            ) {
                return false;
            }
            $key = $item->key->value;
            if (array_key_exists($key, $actual) || !array_key_exists($key, $expected)) {
                return false;
            }
            $actual[$key] = $this->arrayKeys($item->value);
        }
        return array_keys($actual) === array_keys($expected) && $actual === $expected;
    }

    private function boundedPatchAliasConforms(ClassMethod $method, string $alias, Node\Expr\Assign $assignment): bool
    {
        if ($alias !== 'absenceFields') {
            return false;
        }
        $aliasAssignment = $this->latestVariableAssignment($method, $alias, $assignment->getStartFilePos());
        if (
            !$aliasAssignment instanceof Node\Expr\Assign || !$aliasAssignment->expr instanceof Node\Expr\ArrayDimFetch
        ) {
            return false;
        }
        $selection = $aliasAssignment->expr;
        if (!$selection->var instanceof Array_ || !$selection->dim instanceof Node\Expr\ArrayDimFetch) {
            return false;
        }
        if (!$this->literalPatchMapConforms($selection->var, [
            'external_object_absent' => ['externalCallPhase', 'preCallClaimMode'],
            'lookup_unavailable' => [],
        ])) {
            return false;
        }
        return $this->resultFieldExpressionConforms(
            $method,
            $selection->dim,
            'outcome',
            'reconciliation',
            $aliasAssignment->getStartFilePos(),
        );
    }

    private function selectorAssignmentConforms(
        ClassMethod $method,
        string $selector,
        string $field,
        Node\Expr\Assign $assignment,
    ): bool {
        $selectorAssignment = $this->latestVariableAssignment($method, $selector, $assignment->getStartFilePos());
        if (!$selectorAssignment instanceof Node\Expr\Assign) {
            return false;
        }
        return $this->resultFieldExpressionConforms(
            $method,
            $selectorAssignment->expr,
            $field,
            'result',
            $selectorAssignment->getStartFilePos(),
        );
    }

    private function resultFieldExpressionConforms(
        ClassMethod $method,
        Node $expression,
        string $field,
        string $outcomeMethod,
        int $before,
    ): bool {
        if (
            !$expression instanceof Node\Expr\ArrayDimFetch
            || !$expression->var instanceof Variable
            || $expression->var->name !== 'result'
            || !$expression->dim instanceof Node\Scalar\String_
            || $expression->dim->value !== $field
        ) {
            return false;
        }
        $resultAssignment = $this->latestVariableAssignment($method, 'result', $before);
        if (
            !$resultAssignment instanceof Node\Expr\Assign
            || !$resultAssignment->expr instanceof StaticCall
            || !$resultAssignment->expr->class instanceof Name
            || !$resultAssignment->expr->name instanceof Identifier
            || $this->resolvedName(
                $resultAssignment->expr->class,
                $this->declarationName($this->declarationFromMethod($method)),
            )
                !== self::ANCHOR_NAMESPACE . '\\AuditAnchorExternalOutcome'
            || $resultAssignment->expr->name->toString() !== $outcomeMethod
            || count($resultAssignment->expr->args) !== 1
            || !$resultAssignment->expr->args[0]->value instanceof Variable
            || $resultAssignment->expr->args[0]->value->name !== 'outcome'
        ) {
            return false;
        }
        return true;
    }

    private function latestVariableAssignment(ClassMethod $method, string $name, int $before): ?Node\Expr\Assign
    {
        $latest = null;
        foreach (new NodeFinder()->findInstanceOf($method, Node\Expr\Assign::class) as $assign) {
            if (
                !$assign->var instanceof Variable
                || $assign->var->name !== $name
                || $assign->getStartFilePos() >= $before
            ) {
                continue;
            }
            $latest = $assign;
        }
        return $latest;
    }

    private function declarationFromMethod(ClassMethod $method): ClassLike
    {
        $parent = $method->getAttribute('parent');
        while ($parent instanceof Node && !$parent instanceof ClassLike) {
            $parent = $parent->getAttribute('parent');
        }
        self::assertInstanceOf(ClassLike::class, $parent);
        return $parent;
    }

    private function arraySourceAlias(ClassMethod $method, string $alias, Node\Expr\Assign $assignment): bool
    {
        $latest = null;
        foreach (new NodeFinder()->findInstanceOf($method, Node\Expr\Assign::class) as $assign) {
            if (
                $assign->var instanceof Variable
                && $assign->var->name === $alias
                && $assign->getStartFilePos() < $assignment->getStartFilePos()
            ) {
                $latest = $assign;
            }
        }
        return $latest instanceof Node\Expr\Assign && $this->revisionSourceExpression($latest->expr, $method, $latest);
    }

    private function operationArgumentValid(ClassMethod $method, StaticCall $call): bool
    {
        if (
            !isset($call->args[4]->value)
            || !$call->args[4]->value instanceof Variable
            || $call->args[4]->value->name !== 'operation'
        ) {
            return false;
        }
        foreach ($method->params as $parameter) {
            if ($parameter->var instanceof Variable && $parameter->var->name === 'operation') {
                foreach (new NodeFinder()->findInstanceOf($method, Node\Expr\Assign::class) as $assign) {
                    if (
                        $assign->var instanceof Variable
                        && $assign->var->name === 'operation'
                        && $assign->getStartFilePos() < $call->getStartFilePos()
                    ) {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
    }

    /** @param array<string,string> $sources @return array<string,array<string,array<string,int>>> */
    private function movedCallableInventory(array $sources): array
    {
        $expected = [
            'AuditAnchorFrame' => [
                'create' => ['AuditAnchorIntent::fromArray'],
                'restore' => ['AuditAnchorIntent::fromArray'],
            ],
            'AuditAnchorReceiptBody' => [
                'build' => ['AuditAnchorReceiptBody::unsignedBody'],
                'assertPersistedHashes' => ['AuditAnchorReceiptBody::unsignedBody'],
                'assertPublicReceiptHash' => ['AuditAnchorReceiptBody::build'],
            ],
            'AuditAnchorReceiptShape' => [
                'assert' => ['AuditAnchorReceiptBody::assertPersistedHashes'],
                'assertPublicReceiptHash' => ['AuditAnchorReceiptBody::assertPublicReceiptHash'],
            ],
            'AuditAnchorReceiptTransition' => [
                'inputPersist' => ['AuditAnchorReceiptBody::build'],
                'persist' => ['AuditAnchorReceiptBody::build'],
            ],
            'AuditAnchorTransitionGuard' => [
                'advance' => [
                    'AuditAnchorIntent::assertTransitionImmutable',
                    'AuditAnchorIntent::fromArray',
                ],
            ],
        ];
        $inventory = [];
        $targetTypes = ['AuditAnchorIntent', 'AuditAnchorReceiptBody'];
        foreach ($sources as $path => $source) {
            $type = basename($path, '.php');
            $declaration = $this->declaration($source);
            $owner = $this->declarationName($declaration);
            foreach ($this->classMethods($declaration) as $method) {
                $methodName = $method->name->toString();
                $expectedTargets = $expected[$type][$methodName] ?? [];
                $calls = [];
                foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
                    if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
                        continue;
                    }
                    $targetClass = $this->resolvedName($call->class, $owner);
                    if (!str_starts_with($targetClass, self::ANCHOR_NAMESPACE . '\\')) {
                        continue;
                    }
                    $targetType = substr($targetClass, strlen(self::ANCHOR_NAMESPACE) + 1);
                    if (!in_array($targetType, $targetTypes, true)) {
                        continue;
                    }
                    $target = $targetType . '::' . $call->name->toString();
                    if (!in_array($target, $expectedTargets, true)) {
                        $calls[$target] = ($calls[$target] ?? 0) + 1;
                        $calls[$target . '::__invalid'] = ($calls[$target . '::__invalid'] ?? 0) + 1;
                        continue;
                    }
                    $calls[$target] = ($calls[$target] ?? 0) + 1;
                    if (!$this->movedCallableArgumentsValid($type, $methodName, $target, $call)) {
                        $calls[$target . '::__invalid'] = ($calls[$target . '::__invalid'] ?? 0) + 1;
                    }
                }
                if ($calls !== []) {
                    ksort($calls);
                    $inventory[$path][$methodName] = $calls;
                }
            }
        }
        ksort($inventory);
        return $inventory;
    }

    private function movedCallableArgumentsValid(string $owner, string $method, string $target, StaticCall $call): bool
    {
        $variableArguments = [
            'AuditAnchorFrame::create' => [
                'AuditAnchorIntent::fromArray' => ['intentValue'],
            ],
            'AuditAnchorFrame::restore' => [
                'AuditAnchorIntent::fromArray' => ['intentValue'],
            ],
            'AuditAnchorReceiptBody::build' => [
                'AuditAnchorReceiptBody::unsignedBody' => ['value', 'signingKeyRef', 'acceptedAnchoredAt', 'signedAt'],
            ],
            'AuditAnchorReceiptBody::assertPersistedHashes' => [
                'AuditAnchorReceiptBody::unsignedBody' => [
                    'value',
                    'signingKeyRef',
                    'acceptedAnchoredAt',
                    'receiptSignedAt',
                ],
            ],
            'AuditAnchorReceiptBody::assertPublicReceiptHash' => [
                'AuditAnchorReceiptBody::build' => ['intent', 'signingKeyRef', 'receiptSignedAt'],
            ],
            'AuditAnchorReceiptShape::assert' => [
                'AuditAnchorReceiptBody::assertPersistedHashes' => [
                    'value',
                    'signingKeyRef',
                    'acceptedAnchoredAt',
                    'receiptSignedAt',
                ],
            ],
            'AuditAnchorReceiptShape::assertPublicReceiptHash' => [
                'AuditAnchorReceiptBody::assertPublicReceiptHash' => ['intent'],
            ],
            'AuditAnchorTransitionGuard::advance' => [
                'AuditAnchorIntent::assertTransitionImmutable' => ['current', 'candidate'],
                'AuditAnchorIntent::fromArray' => ['candidate'],
            ],
        ];
        if ($owner === 'AuditAnchorReceiptTransition') {
            $variableArguments = [
                'inputPersist' => [
                    'AuditAnchorReceiptBody::build' => ['frame->intent()', 'signingKeyRef', 'trustedNow'],
                ],
                'persist' => [
                    'AuditAnchorReceiptBody::build' => ['frame->intent()', 'signingKeyRef', 'receiptSignedAt'],
                ],
            ];
        }
        $lookup = $owner === 'AuditAnchorReceiptTransition' ? $method : $owner . '::' . $method;
        $expected = $variableArguments[$lookup][$target] ?? null;
        if (!is_array($expected) || count($call->args) !== count($expected)) {
            return false;
        }
        foreach ($expected as $index => $name) {
            $expression = $call->args[$index]->value ?? null;
            if ($name === 'frame->intent()') {
                if (
                    !$expression instanceof MethodCall
                    || !$expression->var instanceof Variable
                    || $expression->var->name !== 'frame'
                    || !$expression->name instanceof Identifier
                    || $expression->name->toString() !== 'intent'
                    || $expression->args !== []
                ) {
                    return false;
                }
                continue;
            }
            if (!$expression instanceof Variable || $expression->name !== $name) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,string> $sources @return array<string,array<string,int>> */
    private function reconciliationCallerInventory(array $sources): array
    {
        $target = self::ANCHOR_NAMESPACE . '\\AuditAnchorReconciliationShape';
        $inventory = [];
        foreach ($sources as $path => $source) {
            $declaration = $this->declaration($source);
            $owner = $this->declarationName($declaration);
            foreach ($this->classMethods($declaration) as $method) {
                foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
                    if (
                        !$call->class instanceof Name
                        || !$call->name instanceof Identifier
                        || $this->resolvedName($call->class, $owner) !== $target
                        || $call->name->toString() !== 'normalize'
                    ) {
                        continue;
                    }
                    $inventory[$this->shortType($owner) . '::' . $method->name->toString()] =
                        ($inventory[$this->shortType($owner) . '::' . $method->name->toString()] ?? 0) + 1;
                }
            }
        }
        ksort($inventory);
        return $inventory;
    }

    /** @param array<string,string> $sources @return array<string,array<string,int>> */
    private function scalarCallInventory(array $sources): array
    {
        $target = self::ANCHOR_NAMESPACE . '\\AuditAnchorScalar';
        $inventory = [];
        foreach ($sources as $path => $source) {
            $declaration = $this->declaration($source);
            $owner = $this->declarationName($declaration);
            foreach ($this->classMethods($declaration) as $method) {
                foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
                    if (
                        !$call->class instanceof Name
                        || !$call->name instanceof Identifier
                        || $this->resolvedName($call->class, $owner) !== $target
                    ) {
                        continue;
                    }
                    $ownerKey = $this->shortType($owner) . '::' . $method->name->toString();
                    $targetKey = 'AuditAnchorScalar::' . $call->name->toString();
                    $inventory[$ownerKey][$targetKey] = ($inventory[$ownerKey][$targetKey] ?? 0) + 1;
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

    private function shortType(string $resolved): string
    {
        return substr($resolved, strrpos($resolved, '\\') + 1);
    }

    /** @param array<string,string> $sources @return array<string,array<string,array<string,int>>> */
    private function originCallInventory(array $sources): array
    {
        $target = self::ANCHOR_NAMESPACE . '\\AuditAnchorLeaseOrigin';
        $inventory = [];
        foreach ($sources as $path => $source) {
            $declaration = $this->declaration($source);
            $owner = $this->declarationName($declaration);
            foreach ($this->classMethods($declaration) as $method) {
                foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
                    if (
                        !$call instanceof StaticCall
                        || !$call->class instanceof Name
                        || !$call->name instanceof Identifier
                    ) {
                        continue;
                    }
                    if ($this->resolvedName($call->class, $owner) !== $target) {
                        continue;
                    }
                    $methodName = $method->name->toString();
                    $callName = $call->name->toString();
                    if (!in_array($callName, ['fromArray', 'fromClaim'], true)) {
                        continue;
                    }
                    $valid = true;
                    if ($callName === 'fromClaim') {
                        $valid =
                            $owner === self::ANCHOR_NAMESPACE . '\\AuditAnchorTransitionGuard'
                            && $methodName === 'advance'
                            && count($call->args) === 2
                            && $call->args[0]->value instanceof Variable
                            && $call->args[0]->value->name === 'claimOperation'
                            && $call->args[1]->value instanceof Variable
                            && $call->args[1]->value->name === 'intent'
                            && isset($method->params[4])
                            && $method->params[4]->var instanceof Variable
                            && $method->params[4]->var->name === 'claimOperation'
                            && $this->hasIntentFromCandidate($method);
                    } elseif ($callName === 'fromArray') {
                        $valid =
                            $owner === self::ANCHOR_NAMESPACE . '\\AuditAnchorFrame'
                            && $methodName === 'restore'
                            && count($call->args) === 1
                            && $this->isArrayFieldAt($call->args[0]->value, 'value', 'leaseOrigin', $method, $call);
                    } else {
                        $valid = false;
                    }
                    $inventory[$path][$methodName][$callName] = ($inventory[$path][$methodName][$callName] ?? 0) + 1;
                    if (!$valid) {
                        $inventory[$path][$methodName]['__invalid'] =
                            ($inventory[$path][$methodName]['__invalid'] ?? 0) + 1;
                    }
                }
            }
        }
        ksort($inventory);
        return $inventory;
    }

    /** @param array<string,string> $sources @return array<string,array<string,array<string,int>>> */
    private function originSupportCallInventory(array $sources): array
    {
        $target = self::ANCHOR_NAMESPACE . '\\AuditAnchorLeaseOrigin';
        $inventory = [];
        foreach ($sources as $path => $source) {
            $declaration = $this->declaration($source);
            $owner = $this->declarationName($declaration);
            foreach ($this->classMethods($declaration) as $method) {
                foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
                    if (
                        !$call instanceof StaticCall
                        || !$call->class instanceof Name
                        || !$call->name instanceof Identifier
                        || $this->resolvedName($call->class, $owner) !== $target
                    ) {
                        continue;
                    }
                    $callName = $call->name->toString();
                    if (in_array($callName, ['fromArray', 'fromClaim'], true)) {
                        continue;
                    }
                    $methodName = $method->name->toString();
                    $inventory[$path][$methodName][$callName] = ($inventory[$path][$methodName][$callName] ?? 0) + 1;
                }
            }
        }
        foreach ($inventory as &$methods) {
            ksort($methods);
            foreach ($methods as &$calls) {
                ksort($calls);
            }
            unset($calls);
        }
        unset($methods);
        ksort($inventory);
        return $inventory;
    }

    private function isArrayField(Node $expression, string $variable, string $field): bool
    {
        return (
            $expression instanceof Node\Expr\ArrayDimFetch
            && $expression->var instanceof Variable
            && $expression->var->name === $variable
            && $expression->dim instanceof Node\Scalar\String_
            && $expression->dim->value === $field
        );
    }

    private function isArrayFieldAt(
        Node $expression,
        string $variable,
        string $field,
        ClassMethod $method,
        StaticCall $call,
    ): bool {
        if ($this->isArrayField($expression, $variable, $field)) {
            return true;
        }
        if (!$expression instanceof Variable || !is_string($expression->name)) {
            return false;
        }
        $alias = $expression->name;
        $latest = null;
        foreach (new NodeFinder()->findInstanceOf($method, Node\Expr\Assign::class) as $assign) {
            if (
                !$assign->var instanceof Variable
                || $assign->var->name !== $alias
                || $assign->getStartFilePos() > $call->getStartFilePos()
            ) {
                continue;
            }
            $latest = $assign;
        }
        return $latest instanceof Node\Expr\Assign && $this->isArrayField($latest->expr, $variable, $field);
    }

    private function hasIntentFromCandidate(ClassMethod $method): bool
    {
        $intentFactories = [];
        foreach (new NodeFinder()->findInstanceOf($method, Node\Expr\Assign::class) as $assign) {
            if (
                !$assign->var instanceof Variable
                || $assign->var->name !== 'intent'
                || !$assign->expr instanceof StaticCall
                || !$assign->expr->class instanceof Name
                || $this->resolvedName($assign->expr->class, self::ANCHOR_NAMESPACE . '\\AuditAnchorTransitionGuard')
                    !== self::ANCHOR_NAMESPACE . '\\AuditAnchorIntent'
                || !$assign->expr->name instanceof Identifier
                || $assign->expr->name->toString() !== 'fromArray'
                || !isset($assign->expr->args[0])
                || !$assign->expr->args[0]->value instanceof Variable
                || $assign->expr->args[0]->value->name !== 'candidate'
            ) {
                continue;
            }
            $intentFactories[] = $assign;
        }
        if ($intentFactories === []) {
            return false;
        }
        foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
            if (
                !$call->class instanceof Name
                || $this->resolvedName($call->class, self::ANCHOR_NAMESPACE . '\\AuditAnchorTransitionGuard')
                    !== self::ANCHOR_NAMESPACE . '\\AuditAnchorLeaseOrigin'
                || !$call->name instanceof Identifier
                || $call->name->toString() !== 'fromClaim'
                || !isset($call->args[1])
                || !$call->args[1]->value instanceof Variable
                || $call->args[1]->value->name !== 'intent'
            ) {
                continue;
            }
            $callPosition = $call->getStartFilePos();
            $latestIntentAssignment = null;
            foreach (new NodeFinder()->findInstanceOf($method, Node\Expr\Assign::class) as $assign) {
                if (
                    $assign->var instanceof Variable
                    && $assign->var->name === 'intent'
                    && $assign->getStartFilePos() < $callPosition
                ) {
                    $latestIntentAssignment = $assign;
                }
                if (
                    $assign->var instanceof Variable
                    && $assign->var->name === 'claimOperation'
                    && $assign->getStartFilePos() < $callPosition
                ) {
                    return false;
                }
            }
            if (
                $latestIntentAssignment instanceof Node\Expr\Assign
                && $latestIntentAssignment->expr instanceof StaticCall
                && $latestIntentAssignment->expr->class instanceof Name
                && $this->resolvedName(
                    $latestIntentAssignment->expr->class,
                    self::ANCHOR_NAMESPACE . '\\AuditAnchorTransitionGuard',
                )
                    === self::ANCHOR_NAMESPACE . '\\AuditAnchorIntent'
                && $latestIntentAssignment->expr->name instanceof Identifier
                && $latestIntentAssignment->expr->name->toString() === 'fromArray'
                && isset($latestIntentAssignment->expr->args[0])
                && $latestIntentAssignment->expr->args[0]->value instanceof Variable
                && $latestIntentAssignment->expr->args[0]->value->name === 'candidate'
            ) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,string> $sources @return array<string,string> */
    private function claimSelectorInventory(array $sources): array
    {
        $expectedOwners = [
            'AuditAnchorClaims' => [
                'externalSubmission' => 'external_submission_claim',
                'preCallRetry' => 'pre_call_retry_claim',
                'externalReconciliation' => 'external_reconciliation_claim',
            ],
            'AuditAnchorReceiptTransition' => [
                'inputClaim' => 'receipt_input_claim',
                'persistClaim' => 'receipt_persist_claim',
                'acknowledgementClaim' => 'acknowledgement_claim',
            ],
        ];
        $selectors = [];
        foreach ($expectedOwners as $type => $methods) {
            $path = 'Internal/Audit/Anchor/' . $type . '.php';
            $declaration = $this->declaration($sources[$path]);
            foreach ($methods as $methodName => $expectedSelector) {
                $method = $this->method($declaration, $methodName);
                $matches = [];
                $key = $type . '::' . $methodName;
                $invalidDispatch = false;
                foreach (new NodeFinder()->findInstanceOf($method, MethodCall::class) as $call) {
                    if (!$call->var instanceof Variable || $call->var->name !== 'this') {
                        continue;
                    }
                    if ($call->name instanceof Identifier && $call->name->toString() === 'claim') {
                        $matches[] = $call;
                        continue;
                    }
                    $invalidDispatch = true;
                }
                foreach (new NodeFinder()->findInstanceOf($method, FuncCall::class) as $call) {
                    if (
                        !$call->name instanceof Name
                        || strtolower($call->name->toString()) !== 'call_user_func'
                        || !isset($call->args[0]->value)
                        || !$call->args[0]->value instanceof Array_
                    ) {
                        continue;
                    }
                    $receiver = $call->args[0]->value->items[0]->value ?? null;
                    if ($receiver instanceof Variable && $receiver->name === 'this') {
                        $invalidDispatch = true;
                    }
                }
                if ($invalidDispatch) {
                    $selectors[$key] = '<invalid-dispatch>';
                    continue;
                }
                if (count($matches) !== 1) {
                    $selectors[$key] = '<invalid-call-count:' . count($matches) . '>';
                    continue;
                }
                $call = $matches[0];
                $last = $call->args[count($call->args) - 1] ?? null;
                $selector = $last?->value instanceof Node\Scalar\String_ ? $last->value->value : null;
                if (count($call->args) !== 5 || $selector !== $expectedSelector) {
                    $selectors[$key] = '<invalid-selector>';
                    continue;
                }
                $selectors[$key] = $selector;
            }
        }
        return $selectors;
    }

    /** @return array<string,int> */
    private function claimOperationInventory(string $source): array
    {
        $inventory = [];
        foreach (new NodeFinder()->findInstanceOf($this->parseSource($source), Node\Scalar\String_::class) as $string) {
            if (!in_array($string->value, self::CLAIM_OPERATIONS, true)) {
                continue;
            }
            $inventory[$string->value] = ($inventory[$string->value] ?? 0) + 1;
        }
        ksort($inventory);
        return $inventory;
    }

    private function expectationGuardShape(string $source): bool
    {
        try {
            $declaration = $this->declaration($source);
            $methods = $this->classMethods($declaration);
            $casMethods = [];
            $casBranches = [];
            foreach ($methods as $method) {
                foreach (new NodeFinder()->findInstanceOf($method, Node\Stmt\If_::class) as $branch) {
                    if ($this->canonicalExpectationMismatch($branch) && $branch->getAttribute('parent') === $method) {
                        $casMethods[] = $method;
                        $casBranches[] = $branch;
                    }
                }
            }
            if (count($casMethods) !== 1 || count($casBranches) !== 1) {
                return false;
            }
            $casMethod = $casMethods[0];
            $casBranch = $casBranches[0];
            if ($casMethod->name->toString() !== 'assertExpected') {
                return false;
            }
            if (
                !$this->hasFrameCurrentSnapshot($casMethod, $casBranch->getStartFilePos())
                || !$this->hasExpectedSnapshot($casMethod, $casBranch->getStartFilePos())
            ) {
                return false;
            }
            $advance = null;
            foreach ($methods as $method) {
                if ($method->name->toString() === 'advance') {
                    $advance = $method;
                    break;
                }
            }
            if ($advance === null) {
                return false;
            }
            $calls = [];
            foreach (new NodeFinder()->findInstanceOf($advance, StaticCall::class) as $call) {
                if (
                    $call->class instanceof Name
                    && $this->resolvedName(
                        $call->class,
                        $this->declarationName($declaration),
                    ) === $this->declarationName($declaration)
                    && $call->name instanceof Identifier
                    && $call->name->toString() === $casMethod->name->toString()
                ) {
                    $calls[] = $call;
                }
            }
            if (count($calls) !== 1 || count($calls[0]->args) !== 2) {
                return false;
            }
            if (!$this->isStraightLineStaticCall($calls[0], $advance)) {
                return false;
            }
            foreach ([['frame', 0], ['expected', 1]] as [$name, $index]) {
                if (
                    !isset($calls[0]->args[$index])
                    || !$calls[0]->args[$index]->value instanceof Variable
                    || $calls[0]->args[$index]->value->name !== $name
                ) {
                    return false;
                }
            }
            $delegatePosition = $calls[0]->getStartFilePos();
            foreach (new NodeFinder()->findInstanceOf($advance, StaticCall::class) as $call) {
                if ($call->getStartFilePos() >= $delegatePosition || !$call->class instanceof Name) {
                    continue;
                }
                $target = $this->resolvedName($call->class, $this->declarationName($declaration));
                if (in_array(
                    $target,
                    [
                        self::ANCHOR_NAMESPACE . '\\AuditAnchorTime',
                        self::ANCHOR_NAMESPACE . '\\AuditAnchorIntent',
                        self::ANCHOR_NAMESPACE . '\\AuditAnchorCanonical',
                        self::ANCHOR_NAMESPACE . '\\AuditAnchorLeaseOrigin',
                    ],
                    true,
                )) {
                    return false;
                }
            }
            $toArray = 0;
            foreach (new NodeFinder()->findInstanceOf($casMethod, MethodCall::class) as $call) {
                if (
                    $call->var instanceof Variable
                    && $call->var->name === 'expected'
                    && $call->name instanceof Identifier
                    && $call->name->toString() === 'toArray'
                ) {
                    ++$toArray;
                }
            }
            return $toArray === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isStraightLineStaticCall(StaticCall $call, ClassMethod $method): bool
    {
        $statement = $call->getAttribute('parent');
        if (!$statement instanceof Node\Stmt\Expression || $statement->expr !== $call) {
            return false;
        }
        $try = $statement->getAttribute('parent');
        if (!$try instanceof Node\Stmt\TryCatch || ($try->stmts[0] ?? null) !== $statement) {
            return false;
        }
        $parent = $try->getAttribute('parent');
        while ($parent instanceof Node && $parent !== $method) {
            if (
                $parent instanceof Node\Stmt\If_
                || $parent instanceof Node\Stmt\For_
                || $parent instanceof Node\Stmt\Foreach_
                || $parent instanceof Node\Stmt\While_
                || $parent instanceof Node\Stmt\Do_
                || $parent instanceof Node\Stmt\Switch_
            ) {
                return false;
            }
            $parent = $parent->getAttribute('parent');
        }
        return $parent === $method;
    }

    private function hasFrameCurrentSnapshot(ClassMethod $method, int $before): bool
    {
        $count = 0;
        foreach ($this->snapshotAssignments($method) as $assign) {
            if ($this->assignmentTargetName($assign->var ?? null) !== 'current') {
                continue;
            }
            if ($assign->getStartFilePos() >= $before) {
                return false;
            }
            if (
                !$assign instanceof Node\Expr\Assign
                || !$assign->var instanceof Variable
                || $assign->var->name !== 'current'
                || !$assign->expr instanceof MethodCall
                || !$assign->expr->name instanceof Identifier
                || $assign->expr->name->toString() !== 'toArray'
            ) {
                return false;
            }
            $intent = $assign->expr->var;
            if (
                !$intent instanceof MethodCall
                || !$intent->name instanceof Identifier
                || $intent->name->toString() !== 'intent'
                || !$intent->var instanceof Variable
                || $intent->var->name !== 'frame'
            ) {
                return false;
            }
            ++$count;
        }
        return $count === 1;
    }

    private function hasExpectedSnapshot(ClassMethod $method, int $before): bool
    {
        $count = 0;
        foreach ($this->snapshotAssignments($method) as $assign) {
            if ($this->assignmentTargetName($assign->var ?? null) !== 'expectation') {
                continue;
            }
            if ($assign->getStartFilePos() >= $before) {
                return false;
            }
            if (
                !$assign instanceof Node\Expr\Assign
                || !$assign->var instanceof Variable
                || $assign->var->name !== 'expectation'
                || !$assign->expr instanceof MethodCall
                || !$assign->expr->var instanceof Variable
                || $assign->expr->var->name !== 'expected'
                || !$assign->expr->name instanceof Identifier
                || $assign->expr->name->toString() !== 'toArray'
            ) {
                return false;
            }
            ++$count;
        }
        return $count === 1;
    }

    /** @return list<Node\Expr\Assign|Node\Expr\AssignRef|Node\Expr\AssignOp> */
    private function snapshotAssignments(ClassMethod $method): array
    {
        /** @var list<Node\Expr\Assign|Node\Expr\AssignRef|Node\Expr\AssignOp> $assignments */
        $assignments = new NodeFinder()->find(
            $method,
            static fn(Node $node): bool => (
                $node instanceof Node\Expr\Assign
                || $node instanceof Node\Expr\AssignRef
                || $node instanceof Node\Expr\AssignOp
            ),
        );
        return $assignments;
    }

    private function assignmentTargetName(?Node $target): ?string
    {
        if ($target instanceof Variable && is_string($target->name)) {
            return $target->name;
        }
        if ($target instanceof Node\Expr\ArrayDimFetch) {
            return $this->assignmentTargetName($target->var);
        }
        return null;
    }

    private function canonicalExpectationMismatch(Node\Stmt\If_ $branch): bool
    {
        if ($this->disabledCondition($branch->cond) || !$this->throwsTransitionConflict($branch)) {
            return false;
        }
        $condition = $branch->cond;
        if (!$condition instanceof Node\Expr\BinaryOp\NotIdentical) {
            return false;
        }
        if (!$condition->left instanceof Array_ || !$condition->right instanceof Array_) {
            return false;
        }
        $left = $condition->left->items;
        $right = $condition->right->items;
        if (count($left) !== 5 || count($right) !== 5) {
            return false;
        }
        if (
            !$this->arrayItemIsExpectationField($left[0] ?? null, 'version')
            || !$this->arrayItemIsExpectationPresent($left[1] ?? null)
            || !$this->arrayItemIsHashEquals($left[2] ?? null, 'stateHash', 'stateHash', true, false)
            || !$this->arrayItemIsTokenParity($left[3] ?? null)
            || !$this->arrayItemIsHashEquals($left[4] ?? null, 'token', 'leaseFencingToken', true, true)
            || !$this->arrayItemIsCurrentField($right[0] ?? null, 'version')
            || !$this->arrayItemIsTrue($right[1] ?? null)
            || !$this->arrayItemIsTrue($right[2] ?? null)
            || !$this->arrayItemIsTrue($right[3] ?? null)
            || !$this->arrayItemIsTrue($right[4] ?? null)
        ) {
            return false;
        }
        return true;
    }

    private function throwsTransitionConflict(Node\Stmt\If_ $branch): bool
    {
        if (count($branch->stmts) !== 1) {
            return false;
        }
        $statement = $branch->stmts[0] ?? null;
        if (!$statement instanceof Node\Stmt\Expression || !$statement->expr instanceof Throw_) {
            return false;
        }
        $new = $statement->expr->expr;
        if (
            !$new instanceof New_
            || !$new->class instanceof Name
            || $this->resolvedName($new->class, self::ANCHOR_NAMESPACE . '\\AuditAnchorTransitionGuard')
                !== self::ANCHOR_NAMESPACE . '\\AuditAnchorException'
            || count($new->args) !== 1
            || !isset($new->args[0]->value)
            || !$new->args[0]->value instanceof ClassConstFetch
            || !$new->args[0]->value->class instanceof Name
            || $this->resolvedName($new->args[0]->value->class, self::ANCHOR_NAMESPACE . '\\AuditAnchorTransitionGuard')
                !== self::ANCHOR_NAMESPACE . '\\AuditAnchorFailure'
            || !$new->args[0]->value->name instanceof Identifier
        ) {
            return false;
        }
        return $new->args[0]->value->name->toString() === 'TransitionConflict';
    }

    private function arrayItemIsExpectationField(?ArrayItem $item, string $field): bool
    {
        return $item instanceof ArrayItem && $this->isArrayField($item->value, 'expectation', $field);
    }

    private function arrayItemIsExpectationPresent(?ArrayItem $item): bool
    {
        return (
            $item instanceof ArrayItem
            && $item->value instanceof Node\Expr\BinaryOp\NotIdentical
            && $this->isArrayField($item->value->left, 'expectation', 'stateHash')
            && $this->isNullConstFetch($item->value->right)
        );
    }

    private function arrayItemIsHashEquals(
        ?ArrayItem $item,
        string $expected,
        string $current,
        bool $expectedCast,
        bool $currentCast,
    ): bool {
        if (
            !$item instanceof ArrayItem
            || !$item->value instanceof FuncCall
            || !$this->isHashEquals($item->value)
            || count($item->value->args) !== 2
        ) {
            return false;
        }
        return (
            $this->isStringCastField($item->value->args[0]->value ?? null, 'expectation', $expected, $expectedCast)
            && $this->isStringCastField($item->value->args[1]->value ?? null, 'current', $current, $currentCast)
        );
    }

    private function arrayItemIsTokenParity(?ArrayItem $item): bool
    {
        if (!$item instanceof ArrayItem || !$item->value instanceof Node\Expr\BinaryOp\Identical) {
            return false;
        }
        $left = $item->value->left;
        $right = $item->value->right;
        return (
            $left instanceof Node\Expr\BinaryOp\Identical
            && $right instanceof Node\Expr\BinaryOp\Identical
            && $this->isArrayField($left->left, 'current', 'leaseFencingToken')
            && $this->isNullConstFetch($left->right)
            && $this->isArrayField($right->left, 'expectation', 'token')
            && $this->isNullConstFetch($right->right)
        );
    }

    private function arrayItemIsCurrentField(?ArrayItem $item, string $field): bool
    {
        return $item instanceof ArrayItem && $this->isArrayField($item->value, 'current', $field);
    }

    private function arrayItemIsTrue(?ArrayItem $item): bool
    {
        return $item instanceof ArrayItem && $this->isBoolean($item->value, true);
    }

    private function isHashEquals(FuncCall $call): bool
    {
        if (!$call->name instanceof Name) {
            return false;
        }
        $resolved = $call->name->getAttribute('resolvedName');
        $name = $resolved instanceof Name ? $resolved->toString() : $call->name->toString();
        return strtolower(ltrim($name, '\\')) === 'hash_equals';
    }

    private function isStringCastField(?Node $expression, string $variable, string $field, bool $requiredCast): bool
    {
        if ($expression instanceof Node\Expr\Cast\String_) {
            return $requiredCast && $this->isArrayField($expression->expr, $variable, $field);
        }
        return !$requiredCast && $this->isArrayField($expression, $variable, $field);
    }

    private function isNullConstFetch(Node $expression): bool
    {
        return (
            $expression instanceof Node\Expr\ConstFetch
            && $expression->name instanceof Name
            && strtolower($expression->name->toString()) === 'null'
        );
    }

    private function disabledCondition(Node $condition): bool
    {
        if ($condition instanceof Node\Expr\BinaryOp\BooleanAnd) {
            return (
                $this->isBoolean($condition->left, false)
                || $this->isBoolean($condition->right, false)
                || $this->disabledCondition($condition->left)
                || $this->disabledCondition($condition->right)
            );
        }
        if ($condition instanceof Node\Expr\BinaryOp\BooleanOr) {
            return (
                $this->isBoolean($condition->left, true)
                || $this->isBoolean($condition->right, true)
                || $this->disabledCondition($condition->left)
                || $this->disabledCondition($condition->right)
            );
        }
        return false;
    }

    private function isBoolean(Node $expression, bool $value): bool
    {
        return (
            $expression instanceof Node\Expr\ConstFetch
            && $expression->name instanceof Name
            && strtolower($expression->name->toString()) === ($value ? 'true' : 'false')
        );
    }

    /** @return list<string> */
    private function outsideAnchorReferences(): array
    {
        $references = [];
        foreach ($this->sourceFiles(self::SOURCE_ROOT) as $path => $source) {
            if (str_starts_with($path, 'Internal/Audit/Anchor/')) {
                continue;
            }
            $references = [...$references, ...$this->anchorReferences($source)];
        }
        $references = array_values(array_unique($references));
        sort($references);
        return $references;
    }

    /** @return list<string> */
    private function anchorReferences(string $source): array
    {
        $nodes = $this->parseSource($source);
        $references = [];
        $anchorTypes = [];
        foreach (self::TYPES as $type) {
            $canonical = self::ANCHOR_NAMESPACE . '\\' . $type;
            $anchorTypes[strtolower($canonical)] = $canonical;
        }
        $record = static function (?string $candidate) use (&$references, $anchorTypes): void {
            if ($candidate === null) {
                return;
            }
            $canonical = $anchorTypes[strtolower(ltrim($candidate, '\\'))] ?? null;
            if ($canonical !== null) {
                $references[] = $canonical;
            }
        };
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if (!$name instanceof Name) {
                continue;
            }
            $record($this->resolvedName($name, ''));
        }
        $classStringVariables = [];
        $callableVariables = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Node\Expr\Assign::class) as $assign) {
            if (!$assign->var instanceof Variable || !is_string($assign->var->name)) {
                continue;
            }
            $target = $this->classExpressionTarget($assign->expr, $classStringVariables, $anchorTypes);
            if ($target !== null) {
                $classStringVariables[$assign->var->name] = $target;
            }
            $callable = $this->callableExpressionTarget(
                $assign->expr,
                $classStringVariables,
                $callableVariables,
                $anchorTypes,
            );
            if ($callable !== null) {
                $callableVariables[$assign->var->name] = $callable;
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
            $record($this->classExpressionTarget($call->class, $classStringVariables, $anchorTypes));
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            $record($this->classExpressionTarget($new->class, $classStringVariables, $anchorTypes));
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, FuncCall::class) as $call) {
            if ($call->name instanceof Name || $call->name instanceof Variable) {
                continue;
            }
            $record($this->callableExpressionTarget(
                $call->name,
                $classStringVariables,
                $callableVariables,
                $anchorTypes,
            ));
        }
        $dynamicSinks = [
            'call_user_func',
            'call_user_func_array',
            'class_exists',
            'class_alias',
            'forward_static_call',
            'forward_static_call_array',
            'interface_exists',
            'is_a',
            'is_subclass_of',
            'register_shutdown_function',
            'trait_exists',
        ];
        foreach (new NodeFinder()->findInstanceOf($nodes, FuncCall::class) as $call) {
            $resolvedFunction = $call->name instanceof Name ? $call->name->getAttribute('resolvedName') : null;
            $function = strtolower(ltrim(
                $resolvedFunction instanceof Name
                    ? $resolvedFunction->toString()
                    : ($call->name instanceof Name ? $call->name->toString() : ''),
                '\\',
            ));
            if (!in_array($function, $dynamicSinks, true)) {
                continue;
            }
            foreach ($this->dynamicSinkArgumentIndexes($function, count($call->args)) as $index) {
                $argument = $call->args[$index] ?? null;
                if ($argument === null) {
                    continue;
                }
                $record($this->classExpressionTarget($argument->value, $classStringVariables, $anchorTypes));
                $record($this->callableExpressionTarget(
                    $argument->value,
                    $classStringVariables,
                    $callableVariables,
                    $anchorTypes,
                ));
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, FuncCall::class) as $call) {
            if (!$call->name instanceof Variable || !is_string($call->name->name)) {
                continue;
            }
            $record($callableVariables[$call->name->name] ?? null);
        }
        $references = array_values(array_unique($references));
        sort($references);
        return $references;
    }

    /** @return list<int> */
    private function dynamicSinkArgumentIndexes(string $function, int $argumentCount): array
    {
        if ($argumentCount === 0) {
            return [];
        }
        return match ($function) {
            'call_user_func',
            'call_user_func_array',
            'class_exists',
            'forward_static_call',
            'forward_static_call_array',
            'interface_exists',
            'register_shutdown_function',
            'trait_exists',
                => [0],
            'class_alias' => $argumentCount > 1 ? [0, 1] : [0],
            'is_a', 'is_subclass_of' => $argumentCount > 1 ? [0, 1] : [0],
            default => [],
        };
    }

    /** @param array<string,string> $variables @param array<string,string> $anchorTypes */
    private function classExpressionTarget(Node $expression, array $variables, array $anchorTypes): ?string
    {
        $candidate = $this->constantString($expression, $variables);
        if ($candidate === null && $expression instanceof ClassConstFetch && $expression->name instanceof Identifier) {
            $candidate = $this->constantString($expression, $variables);
        }
        if ($candidate === null && $expression instanceof Name) {
            $candidate = $this->resolvedName($expression, '');
        }
        if ($candidate === null) {
            return null;
        }
        return $anchorTypes[strtolower(ltrim($candidate, '\\'))] ?? null;
    }

    /** @param array<string,string> $classVariables @param array<string,string> $callableVariables @param array<string,string> $anchorTypes */
    private function callableExpressionTarget(
        Node $expression,
        array $classVariables,
        array $callableVariables,
        array $anchorTypes,
    ): ?string {
        if ($expression instanceof Variable && is_string($expression->name)) {
            return $callableVariables[$expression->name] ?? $classVariables[$expression->name] ?? null;
        }
        if ($expression instanceof Array_) {
            $first = $expression->items[0]->value ?? null;
            if ($first === null) {
                return null;
            }
            return $this->classExpressionTarget($first, $classVariables, $anchorTypes);
        }
        if ($expression instanceof Node\Scalar\String_ && str_contains($expression->value, '::')) {
            [$class] = explode('::', $expression->value, 2);
            return $anchorTypes[strtolower(ltrim($class, '\\'))] ?? null;
        }
        return null;
    }

    /** @param array<string,string> $variables */
    private function constantString(Node $expression, array $variables): ?string
    {
        if ($expression instanceof Node\Scalar\String_) {
            return $expression->value;
        }
        if ($expression instanceof Variable && is_string($expression->name)) {
            return $variables[$expression->name] ?? null;
        }
        if (
            $expression instanceof ClassConstFetch
            && $expression->name instanceof Identifier
            && strtolower($expression->name->toString()) === 'class'
        ) {
            if ($expression->class instanceof Name) {
                return $this->resolvedName($expression->class, '');
            }
            if ($expression->class instanceof Variable && is_string($expression->class->name)) {
                return $variables[$expression->class->name] ?? null;
            }
        }
        if ($expression instanceof Node\Expr\BinaryOp\Concat) {
            $left = $this->constantString($expression->left, $variables);
            $right = $this->constantString($expression->right, $variables);
            return $left === null || $right === null ? null : $left . $right;
        }
        return null;
    }

    /** @return list<string> */
    private function forbiddenEffects(array $nodes, string $owner): array
    {
        $violations = [];
        $forbiddenTerms = [
            'clock',
            'pdo',
            'dbal',
            'database',
            'connection',
            'signer',
            'verifier',
            'provider',
            'container',
            'network',
            'socket',
            'curl',
            'guzzle',
            'httpclient',
            'transport',
            'frankenphp',
            'kernel',
            'application',
        ];
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if (!$name instanceof Name) {
                continue;
            }
            $resolved = $this->resolvedName($name, self::ANCHOR_NAMESPACE . '\\' . $owner);
            if ($resolved === 'RuntimeException') {
                continue;
            }
            $terminal = strtolower($resolved);
            foreach ($forbiddenTerms as $term) {
                if (str_contains($terminal, $term)) {
                    $violations[] = 'type:' . $resolved;
                    break;
                }
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            if (!$new->class instanceof Name) {
                $violations[] = 'dynamic-new';
                continue;
            }
            $newType = $this->resolvedName($new->class, self::ANCHOR_NAMESPACE . '\\' . $owner);
            if ($newType === 'BlackOps\\Internal\\Audit\\Artifact\\AuditAnchorReceiptCodec') {
                $violations[] = 'external-f1-constructor';
            }
            if (in_array($newType, ['DateTimeImmutable', 'DateTime'], true)) {
                $violations[] = 'ambient-clock-constructor:' . $newType;
            }
            if ($newType === 'DateTimeZone') {
                $utc =
                    isset($new->args[0])
                    && $new->args[0]->value instanceof Node\Scalar\String_
                    && $new->args[0]->value->value === 'UTC';
                if ($owner !== 'AuditAnchorTime' || count($new->args) !== 1 || !$utc) {
                    $violations[] = 'unbounded-timezone-constructor';
                }
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
            if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
                $violations[] = 'dynamic-static-call';
                continue;
            }
            $staticClass = $this->resolvedName($call->class, self::ANCHOR_NAMESPACE . '\\' . $owner);
            if ($staticClass === 'DateTimeImmutable' && !$this->canonicalDateParser($call, $owner)) {
                $violations[] = 'unbounded-date-static-call';
            }
            if (in_array(strtolower($call->name->toString()), $this->forbiddenCallNames(), true)) {
                $violations[] = 'static-method:' . $call->name->toString();
            }
        }
        foreach ([MethodCall::class, NullsafeMethodCall::class] as $kind) {
            foreach (new NodeFinder()->findInstanceOf($nodes, $kind) as $call) {
                if (!$call->name instanceof Identifier) {
                    $violations[] = 'dynamic-method-call';
                    continue;
                }
                if (in_array(strtolower($call->name->toString()), $this->forbiddenCallNames(), true)) {
                    $violations[] = 'method:' . $call->name->toString();
                }
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, FuncCall::class) as $call) {
            if (!$call->name instanceof Name) {
                $violations[] = 'dynamic-function-call';
                continue;
            }
            $resolvedFunction = $call->name->getAttribute('resolvedName');
            $function = strtolower(ltrim(
                $resolvedFunction instanceof Name ? $resolvedFunction->toString() : $call->name->toString(),
                '\\',
            ));
            if (!in_array($function, $this->allowedNativeFunctions(), true)) {
                $violations[] = 'native-function:' . $call->name->toString();
            }
            if (in_array($function, $this->forbiddenCallNames(), true)) {
                $violations[] = 'function:' . $call->name->toString();
            }
        }
        foreach ([Echo_::class, Print_::class, Include_::class, Node\Expr\Eval_::class, ShellExec::class] as $kind) {
            if (new NodeFinder()->findInstanceOf($nodes, $kind) !== []) {
                $violations[] = 'runtime-expression:' . $kind;
            }
        }
        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private function forbiddenCallNames(): array
    {
        return [
            'activate',
            'append',
            'boot',
            'bootstrap',
            'commit',
            'connect',
            'container',
            'dispatch',
            'execute',
            'launch',
            'listen',
            'prepare',
            'provider',
            'query',
            'register',
            'request',
            'rollback',
            'run',
            'send',
            'sign',
            'start',
            'store',
            'verify',
            'write',
            'file_put_contents',
            'fopen',
            'fsockopen',
            'curl_exec',
            'curl_init',
            'proc_open',
            'exec',
            'passthru',
            'shell_exec',
            'system',
        ];
    }

    /** @return list<string> */
    private function allowedNativeFunctions(): array
    {
        return [
            'array_replace',
            'array_diff',
            'array_filter',
            'array_is_list',
            'array_key_exists',
            'array_keys',
            'count',
            'hash',
            'hash_equals',
            'implode',
            'in_array',
            'is_array',
            'is_int',
            'is_string',
            'preg_match',
            'strlen',
        ];
    }

    private function canonicalDateParser(StaticCall $call, string $owner): bool
    {
        if ($owner !== 'AuditAnchorTime' || $call->name->toString() !== 'createFromFormat') {
            return false;
        }
        $method = $this->enclosingMethod($call);
        if ($method === null || $method->name->toString() !== 'addSeconds' || count($call->args) !== 3) {
            return false;
        }
        $format = $call->args[0]->value ?? null;
        $timestamp = $call->args[1]->value ?? null;
        $timezone = $call->args[2]->value ?? null;
        if (
            !$format instanceof Node\Scalar\String_
            || $format->value !== '!Y-m-d\\TH:i:s.u\\Z'
            || !$this->suppliedDateVariable($timestamp, $method, $call)
            || !$timezone instanceof New_
            || !$timezone->class instanceof Name
            || $this->resolvedName($timezone->class, $owner) !== 'DateTimeZone'
            || count($timezone->args) !== 1
        ) {
            return false;
        }
        $zone = $timezone->args[0]->value ?? null;
        return $zone instanceof Node\Scalar\String_ && $zone->value === 'UTC';
    }

    private function suppliedDateVariable(?Node $expression, ClassMethod $method, StaticCall $call): bool
    {
        if (!$expression instanceof Variable || !is_string($expression->name)) {
            return false;
        }
        $assignments = new NodeFinder()->find(
            $method,
            static fn(Node $node): bool => $node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignRef,
        );
        $parameters = [];
        foreach ($method->params as $parameter) {
            if (is_string($parameter->var->name)) {
                $parameters[$parameter->var->name] = true;
            }
        }
        if (isset($parameters[$expression->name])) {
            if ($expression->name !== 'value') {
                return false;
            }
            foreach ($assignments as $assign) {
                if (
                    ($assign instanceof Node\Expr\Assign || $assign instanceof Node\Expr\AssignRef)
                    && $assign->var instanceof Variable
                    && $assign->var->name === 'value'
                    && $assign->getStartFilePos() < $call->getStartFilePos()
                ) {
                    return false;
                }
            }
            return true;
        }
        $latest = null;
        foreach ($assignments as $assign) {
            if (
                !$assign instanceof Node\Expr\Assign
                || $assign instanceof Node\Expr\AssignRef
                || !$assign->var instanceof Variable
                || $assign->var->name !== $expression->name
                || !$assign->expr instanceof Variable
                || !is_string($assign->expr->name)
                || !isset($parameters[$assign->expr->name])
                || $assign->expr->name !== 'value'
                || $assign->getStartFilePos() > $call->getStartFilePos()
            ) {
                continue;
            }
            $latest = $assign;
        }
        if (!$latest instanceof Node\Expr\Assign || $latest instanceof Node\Expr\AssignRef) {
            return false;
        }
        foreach ($assignments as $assign) {
            if (
                ($assign instanceof Node\Expr\Assign || $assign instanceof Node\Expr\AssignRef)
                && $assign->var instanceof Variable
                && $assign->var->name === $expression->name
                && $assign->getStartFilePos() > $latest->getStartFilePos()
                && $assign->getStartFilePos() < $call->getStartFilePos()
            ) {
                return false;
            }
            if (
                ($assign instanceof Node\Expr\Assign || $assign instanceof Node\Expr\AssignRef)
                && $assign->var instanceof Variable
                && $assign->var->name === 'value'
                && $assign->getStartFilePos() < $latest->getStartFilePos()
            ) {
                return false;
            }
        }
        return true;
    }

    private function enclosingMethod(Node $node): ?ClassMethod
    {
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Node) {
            if ($parent instanceof ClassMethod) {
                return $parent;
            }
            $parent = $parent->getAttribute('parent');
        }
        return null;
    }

    private function sourceConforms(string $file, string $source): bool
    {
        try {
            $type = $this->typeForFile($file);
            $declaration = $this->declaration($source);
            if ($this->declarationName($declaration) !== self::ANCHOR_NAMESPACE . '\\' . $type) {
                return false;
            }
            if ($declaration->attrGroups !== []) {
                return false;
            }
            if (!$declaration instanceof Enum_ && (!$declaration instanceof Class_ || !$declaration->isFinal())) {
                return false;
            }
            if ($declaration instanceof Class_) {
                if ($declaration->isAbstract() || $declaration->implements !== []) {
                    return false;
                }
                $parent = $declaration->extends;
                $parentName = $parent instanceof Name
                    ? $this->resolvedName($parent, self::ANCHOR_NAMESPACE . '\\' . $type)
                    : null;
                if ($parentName !== ($type === 'AuditAnchorException' ? 'RuntimeException' : null)) {
                    return false;
                }
                if (new NodeFinder()->findInstanceOf($declaration, Node\Stmt\TraitUse::class) !== []) {
                    return false;
                }
            }
            if ($this->methodSurface($declaration) !== (self::METHOD_SURFACE[$type] ?? [])) {
                return false;
            }
            if ($this->methodSignatureInventory($declaration) !== ($this->expectedMethodSignatures()[$type] ?? [])) {
                return false;
            }
            if ($this->propertyInventory($declaration) !== ($this->expectedPropertyInventory()[$type] ?? [])) {
                return false;
            }
            $expectedProperties = in_array(
                $type,
                [
                    'AuditAnchorBinding',
                    'AuditAnchorExpectation',
                    'AuditAnchorFrame',
                    'AuditAnchorIntent',
                    'AuditAnchorLeaseOrigin',
                ],
                true,
            )
                ? ['snapshot']
                : ($type === 'AuditAnchorConfiguration' ? ['leaseDurationSeconds', 'retryDelaySeconds'] : []);
            if ($this->propertyNames($declaration) !== $expectedProperties) {
                return false;
            }
            $expectedConstants = match ($type) {
                'AuditAnchorIntentShape' => ['FIELDS'],
                'AuditAnchorLeaseOrigin' => ['OPERATIONS'],
                default => [],
            };
            if (array_keys($this->constantArrays($declaration)) !== $expectedConstants) {
                return false;
            }
            if ($this->parameterSensitivityViolations($declaration) !== []) {
                return false;
            }
            if ($type === 'AuditAnchorIntentShape' && !$this->closedCountFirstConforms($declaration)) {
                return false;
            }
            if (
                in_array(
                    $type,
                    [
                        'AuditAnchorBinding',
                        'AuditAnchorExpectation',
                        'AuditAnchorFrame',
                        'AuditAnchorIntent',
                        'AuditAnchorLeaseOrigin',
                    ],
                    true,
                )
                && !$this->magicPrivacyConforms($declaration, $type)
            ) {
                return false;
            }
            if ($type === 'AuditAnchorConfiguration') {
                return $this->configurationAdmissionConforms($declaration, $source);
            }
            if ($this->forbiddenEffects($this->parseSource($source), $type) !== []) {
                return false;
            }
            if ($this->dependencyInventory($source, $type, true) !== (self::INTERNAL_DEPENDENCIES[$type] ?? [])) {
                return false;
            }
            if ($this->dependencyInventory($source, $type, false) !== (self::EXTERNAL_DEPENDENCIES[$type] ?? [])) {
                return false;
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function configurationAdmissionConforms(ClassLike $declaration, string $source): bool
    {
        if (!$declaration instanceof Class_ || !$declaration->isReadonly()) {
            return false;
        }
        $owner = $this->declarationName($declaration);
        $constructor = $this->method($declaration, '__construct');
        $assertSeconds = $this->method($declaration, 'assertSeconds');

        $functions = new NodeFinder()->findInstanceOf($assertSeconds, FuncCall::class);
        if (
            count($functions) !== 1
            || !$functions[0] instanceof FuncCall
            || !$functions[0]->name instanceof Name
            || $functions[0]->name->toString() !== 'is_int'
            || count($functions[0]->args) !== 1
            || !$functions[0]->args[0]->value instanceof Variable
            || $functions[0]->args[0]->value->name !== 'value'
        ) {
            return false;
        }

        $newExpressions = new NodeFinder()->findInstanceOf($declaration, New_::class);
        if (count($newExpressions) !== 2) {
            return false;
        }
        $newTypes = [];
        foreach ($newExpressions as $new) {
            if (!$new instanceof New_ || !$new->class instanceof Name) {
                return false;
            }
            $newType = $this->resolvedName($new->class, $owner);
            $configurationException = self::ANCHOR_NAMESPACE . '\\AuditAnchorException';
            if ($newType === 'InvalidArgumentException' && $new->args !== []) {
                return false;
            }
            if ($newType === $configurationException && count($new->args) !== 1) {
                return false;
            }
            if (!in_array($newType, [$configurationException, 'InvalidArgumentException'], true)) {
                return false;
            }
            $newTypes[] = $newType;
        }
        $expectedNewTypes = [$configurationException, 'InvalidArgumentException'];
        sort($newTypes);
        sort($expectedNewTypes);
        if ($newTypes !== $expectedNewTypes) {
            return false;
        }

        $staticCalls = new NodeFinder()->findInstanceOf($constructor, StaticCall::class);
        if (count($staticCalls) !== 2) {
            return false;
        }
        $assertedParameters = [];
        foreach ($staticCalls as $call) {
            if (
                !$call instanceof StaticCall
                || !$call->class instanceof Name
                || $this->resolvedName($call->class, $owner) !== $owner
                || !$call->name instanceof Identifier
                || $call->name->toString() !== 'assertSeconds'
                || count($call->args) !== 1
                || !$call->args[0]->value instanceof Variable
                || !is_string($call->args[0]->value->name)
            ) {
                return false;
            }
            $assertedParameters[] = $call->args[0]->value->name;
        }
        if ($assertedParameters !== ['leaseDurationSeconds', 'retryDelaySeconds']) {
            return false;
        }

        $smaller = new NodeFinder()->findInstanceOf($assertSeconds, Node\Expr\BinaryOp\Smaller::class);
        $greater = new NodeFinder()->findInstanceOf($assertSeconds, Node\Expr\BinaryOp\Greater::class);
        if (
            count($smaller) !== 1
            || count($greater) !== 1
            || !$smaller[0] instanceof Node\Expr\BinaryOp\Smaller
            || !$greater[0] instanceof Node\Expr\BinaryOp\Greater
            || !$smaller[0]->left instanceof Variable
            || $smaller[0]->left->name !== 'value'
            || !$smaller[0]->right instanceof Node\Scalar\LNumber
            || $smaller[0]->right->value !== 1
            || !$greater[0]->left instanceof Variable
            || $greater[0]->left->name !== 'value'
            || !$greater[0]->right instanceof Node\Scalar\LNumber
            || $greater[0]->right->value !== 86400
        ) {
            return false;
        }

        $throws = new NodeFinder()->findInstanceOf($assertSeconds, Throw_::class);
        $returns = new NodeFinder()->findInstanceOf($assertSeconds, Node\Stmt\Return_::class);
        if (
            count($throws) !== 1
            || !$throws[0] instanceof Throw_
            || !$throws[0]->expr instanceof New_
            || !$throws[0]->expr->class instanceof Name
            || $this->resolvedName($throws[0]->expr->class, $owner) !== 'InvalidArgumentException'
            || $throws[0]->expr->args !== []
            || count($returns) !== 1
            || !$returns[0] instanceof Node\Stmt\Return_
            || !$returns[0]->expr instanceof Variable
            || $returns[0]->expr->name !== 'value'
        ) {
            return false;
        }

        $catches = new NodeFinder()->findInstanceOf($constructor, Node\Stmt\Catch_::class);
        if (
            count($catches) !== 1
            || !$catches[0] instanceof Node\Stmt\Catch_
            || count($catches[0]->types) !== 1
            || $this->resolvedName($catches[0]->types[0], $owner) !== 'Throwable'
        ) {
            return false;
        }
        $catchExceptions = new NodeFinder()->findInstanceOf($catches[0], New_::class);
        if (count($catchExceptions) !== 1 || !$catchExceptions[0] instanceof New_) {
            return false;
        }
        $catchException = $catchExceptions[0];
        $failure = $catchException->args[0]->value ?? null;
        if (
            !$catchException->class instanceof Name
            || $this->resolvedName($catchException->class, $owner) !== self::ANCHOR_NAMESPACE . '\\AuditAnchorException'
            || count($catchException->args) !== 1
            || !$failure instanceof ClassConstFetch
            || !$failure->class instanceof Name
            || $this->resolvedName($failure->class, $owner) !== self::ANCHOR_NAMESPACE . '\\AuditAnchorFailure'
            || !$failure->name instanceof Identifier
            || $failure->name->toString() !== 'ConfigurationInvalid'
        ) {
            return false;
        }

        $assignments = [];
        foreach (new NodeFinder()->findInstanceOf($constructor, Node\Expr\Assign::class) as $assign) {
            if (
                !$assign instanceof Node\Expr\Assign
                || !$assign->var instanceof Node\Expr\PropertyFetch
                || !$assign->var->var instanceof Variable
                || $assign->var->var->name !== 'this'
                || !$assign->var->name instanceof Identifier
                || !$assign->expr instanceof Variable
            ) {
                continue;
            }
            $assignments[$assign->var->name->toString()] = $assign->expr->name;
        }
        if (
            $assignments !== [
                'leaseDurationSeconds' => 'leaseDuration',
                'retryDelaySeconds' => 'retryDelay',
            ]
        ) {
            return false;
        }

        foreach ([MethodCall::class, NullsafeMethodCall::class] as $kind) {
            if (new NodeFinder()->findInstanceOf($declaration, $kind) !== []) {
                return false;
            }
        }
        foreach ($this->forbiddenEffects($this->parseSource($source), 'AuditAnchorConfiguration') as $violation) {
            if ($violation !== 'native-function:is_int') {
                return false;
            }
        }
        return (
            $this->dependencyInventory($source, 'AuditAnchorConfiguration', true)
            === self::INTERNAL_DEPENDENCIES['AuditAnchorConfiguration']
            && $this->dependencyInventory($source, 'AuditAnchorConfiguration', false)
            === self::EXTERNAL_DEPENDENCIES['AuditAnchorConfiguration']
        );
    }

    private function closedCountFirstConforms(ClassLike $declaration): bool
    {
        $method = $this->method($declaration, 'assertClosed');
        $calls = [];
        foreach (new NodeFinder()->findInstanceOf($method, FuncCall::class) as $call) {
            if (!$call instanceof FuncCall || !$call->name instanceof Name) {
                continue;
            }
            $resolved = $call->name->getAttribute('resolvedName');
            $name = strtolower(ltrim(
                $resolved instanceof Name ? $resolved->toString() : $call->name->toString(),
                '\\',
            ));
            $calls[$name][] = $call;
        }
        if (
            count($calls['count'] ?? []) !== 2
            || count($calls['array_keys'] ?? []) !== 1
            || count($calls['array_filter'] ?? []) !== 1
            || count($calls['array_diff'] ?? []) !== 2
            || count($calls['array_is_list'] ?? []) !== 1
        ) {
            return false;
        }
        $countNames = [];
        foreach ($calls['count'] as $call) {
            $argument = $call->args[0]->value ?? null;
            if (!$argument instanceof Variable || !is_string($argument->name)) {
                return false;
            }
            $countNames[] = $argument->name;
        }
        sort($countNames);
        if ($countNames !== ['keys', 'value']) {
            return false;
        }
        $comparisonBranches = [];
        foreach (new NodeFinder()->findInstanceOf($method, Node\Expr\BinaryOp\NotIdentical::class) as $comparison) {
            if (
                !$comparison instanceof Node\Expr\BinaryOp\NotIdentical
                || !$comparison->left instanceof FuncCall
                || !$comparison->right instanceof FuncCall
                || !in_array($comparison->left, $calls['count'], true)
                || !in_array($comparison->right, $calls['count'], true)
            ) {
                continue;
            }
            $leftArgument = $comparison->left->args[0]->value ?? null;
            $rightArgument = $comparison->right->args[0]->value ?? null;
            if (
                !$leftArgument instanceof Variable
                || $leftArgument->name !== 'value'
                || !$rightArgument instanceof Variable
                || $rightArgument->name !== 'keys'
            ) {
                continue;
            }
            $branch = $comparison->getAttribute('parent');
            if (
                !$branch instanceof Node\Stmt\If_
                || $branch->cond !== $comparison
                || $branch->getAttribute('parent') !== $method
                || ($method->stmts[0] ?? null) !== $branch
            ) {
                continue;
            }
            $directThrows = [];
            foreach ($branch->stmts as $statement) {
                if ($statement instanceof Node\Stmt\Expression && $statement->expr instanceof Throw_) {
                    $directThrows[] = $statement->expr;
                }
            }
            if (count($branch->stmts) !== 1 || count($directThrows) !== 1) {
                continue;
            }
            $new = $directThrows[0]->expr;
            if (
                !$new instanceof New_
                || !$new->class instanceof Name
                || $this->resolvedName($new->class, $this->declarationName($declaration)) !== 'RuntimeException'
                || $new->args !== []
            ) {
                continue;
            }
            $comparisonBranches[] = [$comparison, $branch];
        }
        if (count($comparisonBranches) !== 1) {
            return false;
        }
        $comparisonBranch = $comparisonBranches[0][1];
        $firstTraversal = min(array_map(static fn(FuncCall $call): int => $call->getStartFilePos(), [
            ...$calls['array_keys'],
            ...$calls['array_filter'],
            ...$calls['array_diff'],
            ...$calls['array_is_list'],
        ]));
        if (!$comparisonBranch instanceof Node\Stmt\If_ || $comparisonBranch->getStartFilePos() >= $firstTraversal) {
            return false;
        }
        $keysCall = $calls['array_keys'][0];
        if (
            !isset($keysCall->args[0])
            || !$keysCall->args[0]->value instanceof Variable
            || $keysCall->args[0]->value->name !== 'value'
        ) {
            return false;
        }
        foreach ($calls['array_filter'] as $call) {
            if (
                !isset($call->args[0])
                || !$call->args[0]->value instanceof Variable
                || $call->args[0]->value->name !== 'actual'
            ) {
                return false;
            }
        }
        $diffPairs = [];
        foreach ($calls['array_diff'] as $call) {
            if (
                !isset($call->args[0], $call->args[1])
                || !$call->args[0]->value instanceof Variable
                || !$call->args[1]->value instanceof Variable
                || !is_string($call->args[0]->value->name)
                || !is_string($call->args[1]->value->name)
            ) {
                return false;
            }
            $diffPairs[] = [$call->args[0]->value->name, $call->args[1]->value->name];
        }
        sort($diffPairs);
        if ($diffPairs !== [['actual', 'keys'], ['keys', 'actual']]) {
            return false;
        }
        $listCall = $calls['array_is_list'][0];
        if (
            !isset($listCall->args[0])
            || !$listCall->args[0]->value instanceof Variable
            || $listCall->args[0]->value->name !== 'value'
        ) {
            return false;
        }
        $lastCount = max(array_map(static fn(FuncCall $call): int => $call->getStartFilePos(), $calls['count']));
        foreach (['array_keys', 'array_filter', 'array_diff', 'array_is_list'] as $name) {
            foreach ($calls[$name] as $call) {
                if ($call->getStartFilePos() < $lastCount) {
                    return false;
                }
            }
        }
        return true;
    }

    /** @return list<string> */
    private function parameterSensitivityViolations(ClassLike $declaration): array
    {
        $owner = $declaration->name?->toString() ?? '';
        $expected = $this->expectedMethodSignatures()[$owner] ?? [];
        $actual = $this->methodSignatureInventory($declaration);
        $violations = [];
        if (array_keys($actual) !== array_keys($expected)) {
            return ['method-surface'];
        }
        foreach ($expected as $methodName => $signature) {
            $actualParams = $actual[$methodName]['params'] ?? [];
            foreach ($signature['params'] as $index => $parameter) {
                $actualParameter = $actualParams[$index] ?? null;
                if ($actualParameter === null) {
                    $violations[] = $methodName . ':missing:' . $parameter['name'];
                    continue;
                }
                if (
                    $actualParameter['name'] !== $parameter['name']
                    || $actualParameter['sensitive'] !== $parameter['sensitive']
                ) {
                    $violations[] = $methodName . ':' . $parameter['name'];
                }
            }
        }
        return $violations;
    }

    private function magicPrivacyConforms(ClassLike $declaration, string $type): bool
    {
        try {
            $this->assertMagicPrivacy($declaration, $type);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string,list<string>> */
    private function constantArrays(ClassLike $declaration): array
    {
        $arrays = [];
        foreach ($declaration->stmts ?? [] as $statement) {
            if (!$statement instanceof Node\Stmt\ClassConst) {
                continue;
            }
            foreach ($statement->consts as $constant) {
                if ($constant->value instanceof Array_) {
                    $arrays[$constant->name->toString()] = $this->arrayKeys($constant->value);
                }
            }
        }
        return $arrays;
    }

    /** @return list<string> */
    private function constantArray(ClassLike $declaration, string $name): array
    {
        foreach ($declaration->stmts ?? [] as $statement) {
            if (!$statement instanceof Node\Stmt\ClassConst) {
                continue;
            }
            foreach ($statement->consts as $constant) {
                if ($constant->name->toString() !== $name || !$constant->value instanceof Array_) {
                    continue;
                }
                $values = [];
                foreach ($constant->value->items as $item) {
                    if (
                        !$item instanceof ArrayItem
                        || $item->key !== null
                        || !$item->value instanceof Node\Scalar\String_
                    ) {
                        return [];
                    }
                    $values[] = $item->value->value;
                }
                return $values;
            }
        }
        self::fail($this->declarationName($declaration) . ' missing ' . $name);
    }

    /** @return list<string> */
    private function returnedArray(ClassLike $declaration, string $methodName): array
    {
        $method = $this->method($declaration, $methodName);
        foreach (new NodeFinder()->findInstanceOf($method, Node\Stmt\Return_::class) as $return) {
            if ($return->expr instanceof Array_) {
                return $this->arrayKeys($return->expr);
            }
        }
        return [];
    }

    /** @return list<string> */
    private function arrayKeys(Array_ $array): array
    {
        $keys = [];
        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem || !$item->key instanceof Node\Scalar\String_) {
                return [];
            }
            $keys[] = $item->key->value;
        }
        return $keys;
    }

    /** @return list<string> */
    private function arrayListValues(Array_ $array): array
    {
        $values = [];
        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem || $item->key !== null || !$item->value instanceof Node\Scalar\String_) {
                return [];
            }
            $values[] = $item->value->value;
        }
        return $values;
    }

    /** @return array<string,list<list<string>>> */
    private function closedKeyLists(ClassLike $declaration): array
    {
        $owner = $this->declarationName($declaration);
        $lists = [];
        foreach ($this->classMethods($declaration) as $method) {
            foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
                if (
                    !$call->class instanceof Name
                    || !$call->name instanceof Identifier
                    || !isset($call->args[1])
                    || !$call->args[1]->value instanceof Array_
                ) {
                    continue;
                }
                $callName = $call->name->toString();
                $resolvedClass = $this->resolvedName($call->class, $owner);
                $isIntentShapeClosure =
                    $callName === 'assertClosed'
                    && $resolvedClass === self::ANCHOR_NAMESPACE . '\\AuditAnchorIntentShape';
                $isBindingClosure =
                    $callName === 'closed'
                    && $owner === self::ANCHOR_NAMESPACE . '\\AuditAnchorBinding'
                    && $resolvedClass === $owner;
                if (!$isIntentShapeClosure && !$isBindingClosure) {
                    continue;
                }
                $lists[$method->name->toString()][] = $this->arrayListValues($call->args[1]->value);
            }
        }
        return $lists;
    }

    /** @return list<string> */
    private function snapshotLiteralKeys(ClassLike $declaration): array
    {
        $owner = $this->declarationName($declaration);
        $constructor = $this->method($declaration, '__construct');
        $snapshotArgument = null;
        foreach (new NodeFinder()->findInstanceOf($declaration, New_::class) as $new) {
            if (
                !$new->class instanceof Name
                || $this->resolvedName($new->class, $owner) !== 'SensitiveParameterValue'
            ) {
                continue;
            }
            $value = $new->args[0]->value ?? null;
            if ($value instanceof Array_) {
                return $this->arrayKeys($value);
            }
            if (
                $new->getAttribute('parent') instanceof Node\Expr\Assign
                && $new->getAttribute('parent')->var instanceof Node\Expr\PropertyFetch
                && $new->getAttribute('parent')->var->name instanceof Identifier
                && $new->getAttribute('parent')->var->name->toString() === 'snapshot'
                && $value instanceof Variable
                && is_string($value->name)
            ) {
                $snapshotArgument = $value->name;
            }
        }
        if ($snapshotArgument === null) {
            return [];
        }
        $constructorParameter = null;
        foreach ($constructor->params as $parameter) {
            if (is_string($parameter->var->name) && $parameter->var->name === $snapshotArgument) {
                $constructorParameter = $snapshotArgument;
                break;
            }
        }
        if ($constructorParameter === null) {
            return [];
        }
        $literalKeys = [];
        foreach (new NodeFinder()->findInstanceOf($declaration, New_::class) as $new) {
            if (
                !$new->class instanceof Name
                || $this->resolvedName($new->class, $owner) !== $owner
                || !isset($new->args[0])
                || !$new->args[0]->value instanceof Array_
            ) {
                continue;
            }
            $keys = $this->arrayKeys($new->args[0]->value);
            if ($keys !== []) {
                $literalKeys[] = $keys;
            }
        }
        if (count($literalKeys) === 1) {
            return $literalKeys[0];
        }
        return [];
    }

    private function assertMagicPrivacy(ClassLike $declaration, string $type): void
    {
        foreach (['__debugInfo', '__serialize'] as $name) {
            $method = $this->method($declaration, $name);
            $returns = new NodeFinder()->findInstanceOf($method, Node\Stmt\Return_::class);
            self::assertCount(1, $returns, $type . ' ' . $name);
            self::assertInstanceOf(Array_::class, $returns[0]->expr);
            self::assertSame([], $returns[0]->expr->items, $type . ' ' . $name . ' output');
        }
        $unserialize = $this->method($declaration, '__unserialize');
        $throws = new NodeFinder()->findInstanceOf($unserialize, Throw_::class);
        self::assertCount(1, $throws, $type . ' unserialize rejection');
        $news = new NodeFinder()->findInstanceOf($unserialize, New_::class);
        self::assertCount(1, $news, $type . ' fresh exception');
        self::assertInstanceOf(Name::class, $news[0]->class);
        self::assertSame(self::ANCHOR_NAMESPACE . '\\AuditAnchorException', $this->resolvedName(
            $news[0]->class,
            self::ANCHOR_NAMESPACE . '\\' . $type,
        ));
        self::assertCount(1, $news[0]->args);
        self::assertInstanceOf(ClassConstFetch::class, $news[0]->args[0]->value);
        self::assertInstanceOf(Name::class, $news[0]->args[0]->value->class);
        self::assertSame(self::ANCHOR_NAMESPACE . '\\AuditAnchorFailure', $this->resolvedName(
            $news[0]->args[0]->value->class,
            self::ANCHOR_NAMESPACE . '\\' . $type,
        ));
        self::assertInstanceOf(Identifier::class, $news[0]->args[0]->value->name);
        self::assertSame('IntegrityInvalid', $news[0]->args[0]->value->name->toString());
    }

    private function replaceFirstLiteral(
        string $source,
        string $search,
        string $replacement,
        int &$replacements,
    ): string {
        $pattern = '/' . preg_quote($search, '/') . '/';
        $result = preg_replace_callback(
            $pattern,
            static fn(array $matches): string => $replacement,
            $source,
            1,
            $replacements,
        );
        self::assertIsString($result);
        return $result;
    }

    /** @param list<string> $searches */
    private function replaceFirstAnyLiteral(
        array $searches,
        string $replacement,
        string $source,
        int &$replacements,
    ): string {
        foreach ($searches as $search) {
            if (!str_contains($source, $search)) {
                continue;
            }
            return $this->replaceFirstLiteral($source, $search, $replacement, $replacements);
        }
        $replacements = 0;
        return $source;
    }

    private function replaceFirstMethodReturnType(
        string $source,
        string $method,
        string $replacement,
        int &$replacements,
    ): string {
        $pattern = '/(public\s+static\s+function\s+' . preg_quote($method, '/') . '\s*\([^)]*\)\s*:\s*)[A-Za-z_\\?]+/';
        $result = preg_replace_callback(
            $pattern,
            static fn(array $match): string => $match[1] . $replacement,
            $source,
            1,
            $replacements,
        );
        self::assertIsString($result);
        return $result;
    }

    private function method(ClassLike $declaration, string $name): ClassMethod
    {
        foreach ($this->classMethods($declaration) as $method) {
            if ($method->name->toString() === $name) {
                return $method;
            }
        }
        self::fail($this->declarationName($declaration) . ' missing ' . $name);
    }
}

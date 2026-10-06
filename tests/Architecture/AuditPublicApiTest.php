<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Attribute\PublicApi;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class AuditPublicApiTest extends TestCase
{
    /** @var list<class-string> */
    private const array SUPPORTED_CORE_TYPES = [
        'BlackOps\\Core\\Audit\\AuditActionPhase',
        'BlackOps\\Core\\Audit\\AuditDelivery',
        'BlackOps\\Core\\Audit\\AuditEventCategory',
        'BlackOps\\Core\\Audit\\AuditEventDefinition',
        'BlackOps\\Core\\Audit\\AuditEvidenceClassification',
        'BlackOps\\Core\\Audit\\AuditEvidenceFieldDefinition',
        'BlackOps\\Core\\Audit\\AuditEvidenceType',
        'BlackOps\\Core\\Audit\\AuditOutcomeStatus',
        'BlackOps\\Core\\Audit\\AuditPolicyDecision',
        'BlackOps\\Core\\Audit\\AuditSemanticTuple',
        'BlackOps\\Core\\Audit\\AuditValidationException',
        'BlackOps\\Core\\Audit\\AuditValidationFailure',
    ];

    /** @var list<class-string> */
    private const array SUPPORTED_PUBLIC_TYPES = [
        'BlackOps\\Audit\\AuditCatalogDefinition',
        'BlackOps\\Audit\\AuditCatalogProvider',
        'BlackOps\\Audit\\AuditOpaqueIdKeyProvider',
        'BlackOps\\Audit\\AuditProtectionKeyProvider',
        ...self::SUPPORTED_CORE_TYPES,
    ];

    /** @var list<string> */
    private const array CLOSED_PUBLIC_TYPES = [
        'array',
        'bool',
        'int',
        'string',
        'void',
        'BlackOps\\StorageProtection\\StorageKey',
        'BlackOps\\Audit\\AuditCatalogDefinition',
        'BlackOps\\Audit\\AuditCatalogProvider',
        'BlackOps\\Audit\\AuditOpaqueIdKeyProvider',
        'BlackOps\\Audit\\AuditProtectionKeyProvider',
        'BlackOps\\Core\\Audit\\AuditActionPhase',
        'BlackOps\\Core\\Audit\\AuditDelivery',
        'BlackOps\\Core\\Audit\\AuditEventCategory',
        'BlackOps\\Core\\Audit\\AuditEventDefinition',
        'BlackOps\\Core\\Audit\\AuditEvidenceClassification',
        'BlackOps\\Core\\Audit\\AuditEvidenceFieldDefinition',
        'BlackOps\\Core\\Audit\\AuditEvidenceType',
        'BlackOps\\Core\\Audit\\AuditOutcomeStatus',
        'BlackOps\\Core\\Audit\\AuditPolicyDecision',
        'BlackOps\\Core\\Audit\\AuditSemanticTuple',
        'BlackOps\\Core\\Audit\\AuditValidationException',
        'BlackOps\\Core\\Audit\\AuditValidationFailure',
    ];

    public function testSupportedAuditTypesHaveThePublicApiMarker(): void
    {
        foreach (self::SUPPORTED_PUBLIC_TYPES as $class) {
            self::assertCount(1, new \ReflectionClass($class)->getAttributes(PublicApi::class), $class);
        }
    }

    public function testTopLevelAuditPublicSurfaceIsExact(): void
    {
        $marked = [];
        foreach (glob(__DIR__ . '/../../src/Audit/*.php') ?: [] as $path) {
            $class = 'BlackOps\\Audit\\' . basename($path, '.php');
            if (new \ReflectionClass($class)->getAttributes(PublicApi::class) !== []) {
                $marked[] = $class;
            }
        }
        sort($marked);
        $expected = array_values(array_filter(self::SUPPORTED_PUBLIC_TYPES, static fn(string $class): bool => str_starts_with(
            $class,
            'BlackOps\\Audit\\',
        )));
        sort($expected);
        self::assertSame($expected, $marked);
    }

    public function testCoreAuditPublicSurfaceIsExact(): void
    {
        $marked = [];
        foreach (glob(__DIR__ . '/../../src/Core/Audit/*.php') ?: [] as $path) {
            $class = 'BlackOps\\Core\\Audit\\' . basename($path, '.php');
            if (new \ReflectionClass($class)->getAttributes(PublicApi::class) !== []) {
                $marked[] = $class;
            }
        }
        sort($marked);
        $expected = self::SUPPORTED_CORE_TYPES;
        sort($expected);
        self::assertSame($expected, $marked);
    }

    public function testA1TypesHaveNoPublicApiMarker(): void
    {
        foreach ([
            'BlackOps\\Core\\Audit\\AuditCatalogHash',
            'BlackOps\\Core\\Audit\\AuditEventCatalog',
            'BlackOps\\Core\\Audit\\AuditEventCatalogLookup',
            'BlackOps\\Core\\Audit\\AuditOpaqueId',
            'BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose',
            'BlackOps\\Core\\Audit\\AuditReadAuthorizationDecision',
            'BlackOps\\Core\\Audit\\AuditReadContext',
            'BlackOps\\Core\\Audit\\AuditReadPurpose',
            'BlackOps\\Core\\Audit\\AuditReadRequestBasis',
            'BlackOps\\Core\\Audit\\AuditSourceOwner',
            'BlackOps\\Core\\Audit\\AuditTypedReference',
            'BlackOps\\Internal\\Audit\\AuditJcsCodec',
            'BlackOps\\Internal\\Audit\\AuditJcsObject',
            'BlackOps\\Internal\\Audit\\AuditLengthPrefix',
            'BlackOps\\Internal\\Audit\\AuditReadContextCodec',
            'BlackOps\\Internal\\Audit\\AuditStringValidator',
            'BlackOps\\Internal\\Audit\\Catalog\\AuditEventCatalogCodec',
            'BlackOps\\Internal\\Audit\\Catalog\\FrameworkAuditEventCatalog',
            'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog',
            'BlackOps\\Internal\\Audit\\Catalog\\ImmutableAuditEventCatalogLookup',
            'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
            'BlackOps\\Internal\\Audit\\Read\\AuditReadEventWriter',
            'BlackOps\\Internal\\Audit\\Read\\OperationReadNonceSource',
            'BlackOps\\Internal\\Audit\\Read\\RandomOperationReadNonceSource',
            'BlackOps\\Internal\\Audit\\Read\\OperationReadExecutionSource',
            'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSessionFactory',
            'BlackOps\\Internal\\Audit\\Read\\OperationReadAuditSession',
            'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsAuthorizationRequest',
            'BlackOps\\Internal\\Diagnostics\\OperationDiagnosticsAuthorizer',
            'BlackOps\\Internal\\Diagnostics\\UnavailableOperationDiagnosticsAuthorizer',
        ] as $class) {
            self::assertCount(0, new \ReflectionClass($class)->getAttributes(PublicApi::class), $class);
        }
    }

    public function testSupportedPublicSurfacesHaveExactNativeTypesDefaultsAndMethods(): void
    {
        $this->assertClassSurface(
            'BlackOps\\Audit\\AuditCatalogDefinition',
            [
                'catalogId' => ['string', false],
                'catalogVersion' => ['int', false],
                'events' => ['array', false],
            ],
            [
                '__construct' => [
                    ['catalogId',      'string', false, false, null, true],
                    ['catalogVersion', 'int',    false, false, null, false],
                    ['events',         'array',  false, false, null, true],
                ],
            ],
        );
        $this->assertClassSurface('BlackOps\\Audit\\AuditCatalogProvider', [], ['catalogs' => []], 'array');
        $this->assertClassSurface(
            'BlackOps\\Core\\Audit\\AuditEventDefinition',
            [
                'type' => ['string', false],
                'version' => ['int', false],
                'evidenceVersion' => ['int', false],
                'category' => ['BlackOps\\Core\\Audit\\AuditEventCategory', false],
                'actionTypes' => ['array', false],
                'semanticTuples' => ['array', false],
                'evidenceFields' => ['array', false],
                'maxEvidenceBytes' => ['int', false],
                'delivery' => ['BlackOps\\Core\\Audit\\AuditDelivery', false],
            ],
            [
                '__construct' => [
                    ['type',             'string',                                    false, false, null, false],
                    ['version',          'int',                                       false, false, null, false],
                    ['evidenceVersion',  'int',                                       false, false, null, false],
                    ['category',         'BlackOps\\Core\\Audit\\AuditEventCategory', false, false, null, false],
                    ['actionTypes',      'array',                                     false, false, null, false],
                    ['semanticTuples',   'array',                                     false, false, null, false],
                    ['evidenceFields',   'array',                                     false, false, null, false],
                    ['maxEvidenceBytes', 'int',                                       false, false, null, false],
                    ['delivery',         'BlackOps\\Core\\Audit\\AuditDelivery',      false, false, null, false],
                ],
                'toArray' => [],
            ],
        );
        $this->assertClassSurface(
            'BlackOps\\Core\\Audit\\AuditSemanticTuple',
            [
                'actionType' => ['string', false],
                'phase' => ['BlackOps\\Core\\Audit\\AuditActionPhase', false],
                'resourceType' => ['string', false],
                'outcomeStatus' => ['BlackOps\\Core\\Audit\\AuditOutcomeStatus', false],
                'outcomeCode' => ['string', false],
                'policyDecision' => ['BlackOps\\Core\\Audit\\AuditPolicyDecision', false],
                'policyReasonCode' => ['string', false],
            ],
            [
                '__construct' => [
                    ['actionType',       'string',                                     false, false, null, false],
                    ['phase',            'BlackOps\\Core\\Audit\\AuditActionPhase',    false, false, null, false],
                    ['resourceType',     'string',                                     false, false, null, false],
                    ['outcomeStatus',    'BlackOps\\Core\\Audit\\AuditOutcomeStatus',  false, false, null, false],
                    ['outcomeCode',      'string',                                     false, false, null, false],
                    ['policyDecision',   'BlackOps\\Core\\Audit\\AuditPolicyDecision', false, false, null, false],
                    ['policyReasonCode', 'string',                                     false, false, null, false],
                ],
                'toArray' => [],
            ],
        );
        $this->assertClassSurface(
            'BlackOps\\Core\\Audit\\AuditEvidenceFieldDefinition',
            [
                'name' => ['string', false],
                'type' => ['BlackOps\\Core\\Audit\\AuditEvidenceType', false],
                'required' => ['bool', false],
                'nullable' => ['bool', false],
                'maxUtf8Bytes' => ['int', true],
                'classification' => ['BlackOps\\Core\\Audit\\AuditEvidenceClassification', true],
            ],
            [
                '__construct' => [
                    ['name',           'string',                                             false, false, null, false],
                    ['type',           'BlackOps\\Core\\Audit\\AuditEvidenceType',           false, false, null, false],
                    ['required',       'bool',                                               false, false, null, false],
                    ['nullable',       'bool',                                               false, false, null, false],
                    ['maxUtf8Bytes',   'int',                                                true,  true,  null, false],
                    ['classification', 'BlackOps\\Core\\Audit\\AuditEvidenceClassification', true,  true,  null, false],
                ],
                'toArray' => [],
            ],
        );
        $this->assertClassSurface(
            'BlackOps\\Core\\Audit\\AuditValidationException',
            [],
            [
                '__construct' => [[
                    'failure',
                    'BlackOps\\Core\\Audit\\AuditValidationFailure',
                    false,
                    false,
                    null,
                    false,
                ]],
                'failure' => [],
                'code' => [],
                'assertUtf8' => [['value', 'string', false, false, null, true]],
                'assertSafeString' => [
                    ['value',   'string', false, false, null, true],
                    ['minimum', 'int',    false, true,  1,    false],
                    ['maximum', 'int',    false, true,  256,  false],
                ],
                'assertGrammarString' => [
                    ['value',   'string', false, false, null, true],
                    ['pattern', 'string', false, false, null, false],
                    ['maximum', 'int',    false, true,  128,  false],
                ],
            ],
        );
        $exception = new \ReflectionClass('BlackOps\\Core\\Audit\\AuditValidationException');
        self::assertSame('RuntimeException', $exception->getParentClass()?->getName());
        self::assertSame(['MAX_SAFE_INTEGER'], array_keys($exception->getConstants()));
        self::assertTrue($exception->getReflectionConstant('MAX_SAFE_INTEGER')?->isPublic());
        self::assertSame(9_007_199_254_740_991, $exception->getConstant('MAX_SAFE_INTEGER'));
    }

    public function testEveryPublicCoreAuditSignatureIsClosedAndInternalFree(): void
    {
        foreach (self::SUPPORTED_PUBLIC_TYPES as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                foreach ($method->getParameters() as $parameter) {
                    $this->assertClosedType($parameter->getType(), $class . '::' . $method->getName() . ' parameter');
                }
                if ($method->getName() === '__construct') {
                    self::assertNull($method->getReturnType(), $class . '::__construct return');
                } else {
                    $this->assertClosedType(
                        $method->getReturnType(),
                        $class . '::' . $method->getName() . ' return',
                        $method,
                    );
                }
            }
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $this->assertClosedType($property->getType(), $class . '::$' . $property->getName());
            }
        }
    }

    public function testPromotedAndNewEnumsHaveExactCasesAndStandardBehavior(): void
    {
        $this->assertEnumCases('BlackOps\\Core\\Audit\\AuditEventCategory', [
            'Business' => 'business',
            'Security' => 'security',
            'Access' => 'access',
            'Configuration' => 'configuration',
            'Control' => 'control',
            'Retention' => 'retention',
            'Replay' => 'replay',
            'Rotation' => 'rotation',
            'Outbox' => 'outbox',
        ]);
        $this->assertEnumCases('BlackOps\\Core\\Audit\\AuditDelivery', [
            'Required' => 'required',
            'Durable' => 'durable',
        ]);
        $this->assertEnumCases('BlackOps\\Core\\Audit\\AuditActionPhase', [
            'Requested' => 'requested',
            'Started' => 'started',
            'Committed' => 'committed',
            'Succeeded' => 'succeeded',
            'Denied' => 'denied',
            'Failed' => 'failed',
            'Unknown' => 'unknown',
        ]);
        $this->assertEnumCases('BlackOps\\Core\\Audit\\AuditOutcomeStatus', [
            'Succeeded' => 'succeeded',
            'Denied' => 'denied',
            'Failed' => 'failed',
            'Unknown' => 'unknown',
        ]);
        $this->assertEnumCases('BlackOps\\Core\\Audit\\AuditPolicyDecision', [
            'Allow' => 'allow',
            'Deny' => 'deny',
            'NotApplicable' => 'not_applicable',
        ]);
        $this->assertEnumCases('BlackOps\\Core\\Audit\\AuditEvidenceType', [
            'String' => 'string',
            'Integer' => 'integer',
            'Boolean' => 'boolean',
        ]);
        $this->assertEnumCases('BlackOps\\Core\\Audit\\AuditEvidenceClassification', [
            'SafeCode' => 'safe_code',
            'OpaqueId' => 'opaque_id',
        ]);
        $this->assertEnumCases('BlackOps\\Core\\Audit\\AuditValidationFailure', [
            'ShapeInvalid' => 'shape_invalid',
            'Utf8Invalid' => 'utf8_invalid',
            'ControlInvalid' => 'control_invalid',
            'NfcInvalid' => 'nfc_invalid',
            'ByteBoundInvalid' => 'byte_bound_invalid',
            'GrammarInvalid' => 'grammar_invalid',
            'OrderOrUniquenessInvalid' => 'order_or_uniqueness_invalid',
            'SemanticInvalid' => 'semantic_invalid',
            'JcsBoundInvalid' => 'jcs_bound_invalid',
            'HashMismatch' => 'hash_mismatch',
        ]);
    }

    public function testDocumentedCollectionEdgesRemainClosed(): void
    {
        $this->assertDocumentedCollection(
            'BlackOps\\Audit\\AuditCatalogDefinition::__construct',
            'param',
            'events',
            ['BlackOps\\Core\\Audit\\AuditEventDefinition'],
        );
        $this->assertDocumentedCollection(
            'BlackOps\\Audit\\AuditCatalogProvider::catalogs',
            'return',
            null,
            ['BlackOps\\Audit\\AuditCatalogDefinition'],
        );
        $this->assertDocumentedCollection(
            'BlackOps\\Core\\Audit\\AuditEventDefinition::__construct',
            'param',
            'actionTypes',
            ['string'],
        );
        $this->assertDocumentedCollection(
            'BlackOps\\Core\\Audit\\AuditEventDefinition::__construct',
            'param',
            'semanticTuples',
            ['BlackOps\\Core\\Audit\\AuditSemanticTuple'],
        );
        $this->assertDocumentedCollection(
            'BlackOps\\Core\\Audit\\AuditEventDefinition::__construct',
            'param',
            'evidenceFields',
            ['BlackOps\\Core\\Audit\\AuditEvidenceFieldDefinition'],
        );
        $definitionDoc = $this->docComment('BlackOps\\Audit\\AuditCatalogDefinition::__construct');
        $definitionContext = $this->documentedTypeContext('BlackOps\\Audit\\AuditCatalogDefinition');
        $qualifiedDefinitionDoc = $this->replaceOnce(
            $definitionDoc,
            'list<AuditEventDefinition>',
            'list<\\BlackOps\\Core\\Audit\\AuditEventDefinition>',
        );
        self::assertSame(
            ['BlackOps\\Core\\Audit\\AuditEventDefinition'],
            $this->documentedCollectionTypes($qualifiedDefinitionDoc, 'param', 'events', $definitionContext),
            'fully qualified documented collection type',
        );
        $aliasedDefinitionDoc = $this->replaceOnce(
            $definitionDoc,
            'list<AuditEventDefinition>',
            'list<EventDefinitionAlias>',
        );
        $aliasedDefinitionSource = $this->replaceOnce(
            $this->publicSource('BlackOps\\Audit\\AuditCatalogDefinition'),
            'use BlackOps\\Core\\Audit\\AuditEventDefinition;',
            'use BlackOps\\Core\\Audit\\AuditEventDefinition as EventDefinitionAlias;',
        );
        self::assertSame(
            ['BlackOps\\Core\\Audit\\AuditEventDefinition'],
            $this->documentedCollectionTypes(
                $aliasedDefinitionDoc,
                'param',
                'events',
                $this->documentedTypeContext('BlackOps\\Audit\\AuditCatalogDefinition', $aliasedDefinitionSource),
            ),
            'aliased documented collection type',
        );
        $providerDoc = $this->docComment('BlackOps\\Audit\\AuditCatalogProvider::catalogs');
        $providerContext = $this->documentedTypeContext('BlackOps\\Audit\\AuditCatalogProvider');
        $benignDescription = $this->replaceOnce(
            $providerDoc,
            '@return list<AuditCatalogDefinition>',
            '@return list<AuditCatalogDefinition> for application catalogs',
        );
        self::assertSame(
            ['BlackOps\\Audit\\AuditCatalogDefinition'],
            $this->documentedCollectionTypes($benignDescription, 'return', null, $providerContext),
            'benign documented collection description',
        );
        $ambiguousContinuation = $this->replaceOnce(
            $providerDoc,
            '@return list<AuditCatalogDefinition>',
            '@return list<AuditCatalogDefinition> list<AuditCatalogDefinition>',
        );
        self::assertNull(
            $this->documentedCollectionTypes($ambiguousContinuation, 'return', null, $providerContext),
            'ambiguous documented collection continuation',
        );
        $spacedUnsupportedUnion = $this->replaceOnce(
            $providerDoc,
            '@return list<AuditCatalogDefinition>',
            '@return list < AuditCatalogDefinition > | list < \\BlackOps\\Core\\Audit\\AuditEventCatalog >',
        );
        self::assertNull(
            $this->documentedCollectionTypes($spacedUnsupportedUnion, 'return', null, $providerContext),
            'spaced unsupported documented collection union',
        );
        $globalAbsolute = $this->replaceOnce(
            $providerDoc,
            'list<AuditCatalogDefinition>',
            'list < \\AuditCatalogDefinition >',
        );
        self::assertNull(
            $this->documentedCollectionTypes($globalAbsolute, 'return', null, $providerContext),
            'global absolute documented collection type',
        );
        foreach (self::SUPPORTED_PUBLIC_TYPES as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $doc = $method->getDocComment() ?: '';
                self::assertStringNotContainsString('BlackOps\\Internal\\', $doc, $class . '::' . $method->getName());
            }
        }
    }

    public function testParseValidPublicSurfaceMutationsRemainRejected(): void
    {
        $definitionSource = $this->publicSource('BlackOps\\Audit\\AuditCatalogDefinition');
        $definitionSurface = $this->expectedSourceSurface('BlackOps\\Audit\\AuditCatalogDefinition');
        self::assertTrue(
            $this->sourceSurfaceAccepts($definitionSource, $definitionSurface),
            'AuditCatalogDefinition literal source surface',
        );
        $definitionDefault = $this->replaceOnce(
            $definitionSource,
            'public int $catalogVersion,',
            'public int $version = 1,',
        );
        $definitionExtraProperty = $this->replaceOnce(
            $definitionSource,
            "final readonly class AuditCatalogDefinition\n{",
            "final readonly class AuditCatalogDefinition\n{\n    public string \$extra;",
        );
        foreach ([
            'changed constructor parameter/default' => $definitionDefault,
            'extra declaration property' => $definitionExtraProperty,
        ] as $label => $mutation) {
            self::assertNotSame([], $this->sourceSurface($mutation), $label . ' parses');
            self::assertFalse($this->sourceSurfaceAccepts($mutation, $definitionSurface), $label);
        }

        $providerSource = $this->publicSource('BlackOps\\Audit\\AuditCatalogProvider');
        $providerSurface = $this->expectedSourceSurface('BlackOps\\Audit\\AuditCatalogProvider');
        self::assertTrue(
            $this->sourceSurfaceAccepts($providerSource, $providerSurface),
            'AuditCatalogProvider literal source surface',
        );
        $providerExtraMethod = $this->replaceOnce(
            $providerSource,
            'public function catalogs(): array;',
            "public function catalogs(): array;\n    public function extra(): void;",
        );
        self::assertNotSame([], $this->sourceSurface($providerExtraMethod), 'extra provider method parses');
        self::assertFalse($this->sourceSurfaceAccepts($providerExtraMethod, $providerSurface), 'extra provider method');

        $eventSource = $this->publicSource('BlackOps\\Core\\Audit\\AuditEventDefinition');
        $eventSurface = $this->expectedSourceSurface('BlackOps\\Core\\Audit\\AuditEventDefinition');
        self::assertTrue(
            $this->sourceSurfaceAccepts($eventSource, $eventSurface),
            'AuditEventDefinition literal source surface',
        );
        $nullableReturn = $this->replaceOnce(
            $eventSource,
            'public function toArray(): array',
            'public function toArray(): ?array',
        );
        $promotedExtra = $this->replaceOnce(
            $eventSource,
            'public AuditDelivery $delivery,',
            "public AuditDelivery \$delivery,\n        public string \$extra,",
        );
        foreach ([
            'nullable return on promoted type' => $nullableReturn,
            'extra promoted surface' => $promotedExtra,
        ] as $label => $mutation) {
            self::assertNotSame([], $this->sourceSurface($mutation), $label . ' parses');
            self::assertFalse($this->sourceSurfaceAccepts($mutation, $eventSurface), $label);
        }

        $providerDoc = $this->docComment('BlackOps\\Audit\\AuditCatalogProvider::catalogs');
        $unsupportedUnion = $this->replaceOnce(
            $providerDoc,
            '@return list<AuditCatalogDefinition>',
            '@return list<AuditCatalogDefinition>|list<\\BlackOps\\Core\\Audit\\AuditEventCatalog>',
        );
        self::assertNull(
            $this->documentedCollectionTypes(
                $unsupportedUnion,
                'return',
                null,
                $this->documentedTypeContext('BlackOps\\Audit\\AuditCatalogProvider'),
            ),
            'unsupported documented collection union',
        );
    }

    private function assertClassSurface(
        string $class,
        array $properties,
        array $methods,
        ?string $interfaceReturn = null,
    ): void {
        $reflection = new \ReflectionClass($class);
        if ($reflection->isInterface()) {
            self::assertSame([], $reflection->getInterfaceNames(), $class);
        }
        $actualProperties = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $type = $property->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type, $class . '::$' . $property->getName());
            $actualProperties[$property->getName()] = [$type->getName(), $type->allowsNull()];
        }
        self::assertSame($properties, $actualProperties, $class . ' properties');

        $actualMethods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $actualMethods[$method->getName()] = array_map(static fn(\ReflectionParameter $parameter): array => [
                $parameter->getName(),
                $parameter->getType() instanceof \ReflectionNamedType ? $parameter->getType()->getName() : null,
                $parameter->getType()?->allowsNull() ?? false,
                $parameter->isDefaultValueAvailable(),
                $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                count($parameter->getAttributes(\SensitiveParameter::class)) === 1,
            ], $method->getParameters());
            if ($method->getName() === '__construct') {
                self::assertNull($method->getReturnType(), $class . '::__construct return');
            } else {
                $returnType = $method->getReturnType();
                self::assertNotNull($returnType, $class . '::' . $method->getName() . ' return');
                self::assertFalse(
                    $returnType->allowsNull(),
                    $class . '::' . $method->getName() . ' return nullability',
                );
                self::assertSame(
                    $interfaceReturn ?? $this->expectedReturnType($class, $method->getName()),
                    $returnType->getName(),
                    $class . '::' . $method->getName(),
                );
            }
        }
        self::assertSame(array_keys($methods), array_keys($actualMethods), $class . ' methods');
        foreach ($methods as $name => $expectedParameters) {
            self::assertSame($expectedParameters, $actualMethods[$name], $class . '::' . $name . ' parameters');
        }
        foreach ($actualMethods as $name => $_parameters) {
            $method = $reflection->getMethod($name);
            foreach ($method->getParameters() as $parameter) {
                $this->assertClosedType($parameter->getType(), $class . '::' . $name);
            }
            if ($name === '__construct') {
                self::assertNull($method->getReturnType(), $class . '::__construct return');
            } else {
                $this->assertClosedType($method->getReturnType(), $class . '::' . $name, $method);
            }
        }
        foreach ($actualProperties as $name => $_type) {
            $this->assertClosedType($reflection->getProperty($name)->getType(), $class . '::$' . $name);
        }
    }

    private function expectedReturnType(string $class, string $method): ?string
    {
        return match ($class . '::' . $method) {
            'BlackOps\\Core\\Audit\\AuditEventDefinition::toArray',
            'BlackOps\\Core\\Audit\\AuditSemanticTuple::toArray',
            'BlackOps\\Core\\Audit\\AuditEvidenceFieldDefinition::toArray',
                => 'array',
            'BlackOps\\Core\\Audit\\AuditValidationException::failure'
                => 'BlackOps\\Core\\Audit\\AuditValidationFailure',
            'BlackOps\\Core\\Audit\\AuditValidationException::code' => 'string',
            'BlackOps\\Core\\Audit\\AuditValidationException::assertUtf8',
            'BlackOps\\Core\\Audit\\AuditValidationException::assertSafeString',
            'BlackOps\\Core\\Audit\\AuditValidationException::assertGrammarString',
                => 'void',
            default => null,
        };
    }

    private function assertEnumCases(string $class, array $expected): void
    {
        $reflection = new \ReflectionEnum($class);
        self::assertTrue($reflection->isBacked(), $class);
        self::assertSame('string', $reflection->getBackingType()?->getName(), $class);
        $actual = [];
        foreach ($reflection->getCases() as $case) {
            self::assertInstanceOf(\ReflectionEnumBackedCase::class, $case);
            $actual[$case->getName()] = $case->getBackingValue();
        }
        self::assertSame($expected, $actual, $class);
        self::assertContains('UnitEnum', $reflection->getInterfaceNames(), $class);
        self::assertContains('BackedEnum', $reflection->getInterfaceNames(), $class);
        self::assertTrue($reflection->hasMethod('cases'), $class);
        self::assertTrue($reflection->hasMethod('from'), $class);
        self::assertTrue($reflection->hasMethod('tryFrom'), $class);
        $declaredPublicMethods = array_values(array_filter(
            array_map(
                static fn(\ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
            static fn(string $method): bool => !in_array($method, ['cases', 'from', 'tryFrom'], true),
        ));
        self::assertSame([], $declaredPublicMethods, $class);
    }

    private function assertDocumentedCollection(
        string $subject,
        string $tag,
        ?string $parameter,
        array $expectedElementTypes,
    ): void {
        [$class] = explode('::', $subject, 2);
        $doc = $this->docComment($subject);
        $actualElementTypes = $this->documentedCollectionTypes(
            $doc,
            $tag,
            $parameter,
            $this->documentedTypeContext($class),
        );
        self::assertNotNull($actualElementTypes, $subject . ' documented ' . $tag);
        self::assertSame($expectedElementTypes, $actualElementTypes, $subject . ' documented ' . $tag);
    }

    /**
     * @param array{namespace: string, imports: array<string, string>} $context
     * @return list<string>|null
     */
    private function documentedCollectionTypes(string $doc, string $tag, ?string $parameter, array $context): ?array
    {
        preg_match_all(
            '/@(?<tag>param|return)\\b(?<body>[^\\r\\n]*)/',
            $doc,
            $matches,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );
        $annotations = [];
        foreach ($matches as $match) {
            if (($match['tag'] ?? null) !== $tag) {
                continue;
            }
            $body = $match['body'] ?? null;
            if (!is_string($body)) {
                $annotations[] = ['name' => null, 'types' => null];
                continue;
            }
            $offset = 0;
            $types = $this->parseDocumentedCollectionType($body, $offset);
            $name = null;
            $descriptionOffset = $offset;
            if ($types !== null && $tag === 'param') {
                $offset = $this->skipDocumentedWhitespace($body, $offset);
                $remaining = substr($body, $offset);
                if (preg_match('/^\\$([A-Za-z_][A-Za-z0-9_]*)/', $remaining, $parameterMatch) === 1) {
                    $name = $parameterMatch[1];
                    $descriptionOffset = $offset + strlen($parameterMatch[0]);
                } else {
                    $types = null;
                }
            }
            if ($types !== null && !$this->isBenignDocumentedDescription($body, $descriptionOffset)) {
                $types = null;
            }
            $annotations[] = ['name' => $name, 'types' => $types];
        }
        $annotations = array_values(array_filter($annotations, static fn(array $annotation): bool => $parameter === null
            ? ($annotation['name'] ?? null) === null
            : ($annotation['name'] ?? null) === $parameter));
        if (count($annotations) !== 1) {
            return null;
        }
        $types = $annotations[0]['types'] ?? null;
        if (!is_array($types)) {
            return null;
        }
        $actualElementTypes = [];
        foreach ($types as $type) {
            if (!is_string($type)) {
                return null;
            }
            $resolved = $this->resolveDocumentedType($type, $context);
            if ($resolved === null) {
                return null;
            }
            $actualElementTypes[] = $resolved;
        }

        return $actualElementTypes;
    }

    /** @return list<string>|null */
    private function parseDocumentedCollectionType(string $body, int &$offset): ?array
    {
        $types = [];
        while (true) {
            $offset = $this->skipDocumentedWhitespace($body, $offset);
            if (substr($body, $offset, 4) !== 'list') {
                return null;
            }
            $offset += 4;
            $offset = $this->skipDocumentedWhitespace($body, $offset);
            if (($body[$offset] ?? null) !== '<') {
                return null;
            }
            $offset++;
            $offset = $this->skipDocumentedWhitespace($body, $offset);
            $remaining = substr($body, $offset);
            if (
                preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*/', $remaining, $element) !== 1
            ) {
                return null;
            }
            $types[] = $element[0];
            $offset += strlen($element[0]);
            $offset = $this->skipDocumentedWhitespace($body, $offset);
            if (($body[$offset] ?? null) !== '>') {
                return null;
            }
            $offset++;
            $beforeWhitespace = $offset;
            $offset = $this->skipDocumentedWhitespace($body, $offset);
            if (($body[$offset] ?? null) !== '|') {
                $offset = $beforeWhitespace;
                return $types;
            }
            $offset++;
        }
    }

    private function skipDocumentedWhitespace(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length && ctype_space($value[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function isBenignDocumentedDescription(string $body, int $offset): bool
    {
        $description = trim(substr($body, $offset));
        if ($description === '') {
            return true;
        }

        return preg_match('/^(?:[|<>\\\\]|[A-Za-z_][A-Za-z0-9_]*\\s*<)/', $description) !== 1;
    }

    /** @return array{namespace: string, imports: array<string, string>} */
    private function documentedTypeContext(string $class, ?string $source = null): array
    {
        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $nodes = $parser->parse($source ?? $this->publicSource($class));
        if (!is_array($nodes)) {
            throw new \LogicException('The public source did not produce statements.');
        }
        $namespaces = new NodeFinder()->findInstanceOf($nodes, Namespace_::class);
        if (count($namespaces) !== 1) {
            throw new \LogicException('The public source did not produce one namespace.');
        }
        $namespace = $namespaces[0]->name?->toString() ?? '';
        $imports = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Use_::class) as $use) {
            if ($use->type !== Use_::TYPE_NORMAL) {
                continue;
            }
            foreach ($use->uses as $item) {
                if (!$item instanceof UseItem) {
                    continue;
                }
                $alias = $item->alias?->toString() ?? $item->name->getLast();
                $imports[strtolower($alias)] = ltrim($item->name->toString(), '\\');
            }
        }

        return ['namespace' => $namespace, 'imports' => $imports];
    }

    private function docComment(string $subject): string
    {
        [$class, $method] = explode('::', $subject, 2);
        $reflection = new \ReflectionClass($class);
        $doc = $method === '__construct'
            ? $reflection->getConstructor()?->getDocComment()
            : $reflection->getMethod($method)->getDocComment();
        self::assertIsString($doc, $subject);

        return $doc;
    }

    private function publicSource(string $class): string
    {
        $path = match ($class) {
            'BlackOps\\Audit\\AuditCatalogDefinition' => dirname(__DIR__, 2) . '/src/Audit/AuditCatalogDefinition.php',
            'BlackOps\\Audit\\AuditCatalogProvider' => dirname(__DIR__, 2) . '/src/Audit/AuditCatalogProvider.php',
            'BlackOps\\Core\\Audit\\AuditEventDefinition' => dirname(__DIR__, 2)
                . '/src/Core/Audit/AuditEventDefinition.php',
            default => throw new \LogicException('Unsupported public source.'),
        };
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    private function expectedSourceSurface(string $class): array
    {
        return match ($class) {
            'BlackOps\\Audit\\AuditCatalogDefinition' => [
                'properties' => [
                    'catalogId' => ['name' => 'string', 'nullable' => false],
                    'catalogVersion' => ['name' => 'int', 'nullable' => false],
                    'events' => ['name' => 'array', 'nullable' => false],
                ],
                'methods' => [
                    '__construct' => [
                        'return' => ['name' => null, 'nullable' => false],
                        'parameters' => [
                            [
                                'name' => 'catalogId',
                                'type' => ['name' => 'string', 'nullable' => false],
                                'default' => null,
                                'promoted' => true,
                            ],
                            [
                                'name' => 'catalogVersion',
                                'type' => ['name' => 'int', 'nullable' => false],
                                'default' => null,
                                'promoted' => true,
                            ],
                            [
                                'name' => 'events',
                                'type' => ['name' => 'array', 'nullable' => false],
                                'default' => null,
                                'promoted' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'BlackOps\\Audit\\AuditCatalogProvider' => [
                'properties' => [],
                'methods' => [
                    'catalogs' => [
                        'return' => ['name' => 'array', 'nullable' => false],
                        'parameters' => [],
                    ],
                ],
            ],
            'BlackOps\\Core\\Audit\\AuditEventDefinition' => [
                'properties' => [
                    'actionTypes' => ['name' => 'array', 'nullable' => false],
                    'category' => [
                        'name' => 'BlackOps\\Core\\Audit\\AuditEventCategory',
                        'nullable' => false,
                    ],
                    'delivery' => [
                        'name' => 'BlackOps\\Core\\Audit\\AuditDelivery',
                        'nullable' => false,
                    ],
                    'evidenceFields' => ['name' => 'array', 'nullable' => false],
                    'evidenceVersion' => ['name' => 'int', 'nullable' => false],
                    'maxEvidenceBytes' => ['name' => 'int', 'nullable' => false],
                    'semanticTuples' => ['name' => 'array', 'nullable' => false],
                    'type' => ['name' => 'string', 'nullable' => false],
                    'version' => ['name' => 'int', 'nullable' => false],
                ],
                'methods' => [
                    '__construct' => [
                        'return' => ['name' => null, 'nullable' => false],
                        'parameters' => [
                            [
                                'name' => 'type',
                                'type' => ['name' => 'string', 'nullable' => false],
                                'default' => null,
                                'promoted' => true,
                            ],
                            [
                                'name' => 'version',
                                'type' => ['name' => 'int', 'nullable' => false],
                                'default' => null,
                                'promoted' => true,
                            ],
                            [
                                'name' => 'evidenceVersion',
                                'type' => ['name' => 'int', 'nullable' => false],
                                'default' => null,
                                'promoted' => true,
                            ],
                            [
                                'name' => 'category',
                                'type' => [
                                    'name' => 'BlackOps\\Core\\Audit\\AuditEventCategory',
                                    'nullable' => false,
                                ],
                                'default' => null,
                                'promoted' => true,
                            ],
                            [
                                'name' => 'actionTypes',
                                'type' => ['name' => 'array', 'nullable' => false],
                                'default' => null,
                                'promoted' => true,
                            ],
                            [
                                'name' => 'semanticTuples',
                                'type' => ['name' => 'array', 'nullable' => false],
                                'default' => null,
                                'promoted' => true,
                            ],
                            [
                                'name' => 'evidenceFields',
                                'type' => ['name' => 'array', 'nullable' => false],
                                'default' => null,
                                'promoted' => true,
                            ],
                            [
                                'name' => 'maxEvidenceBytes',
                                'type' => ['name' => 'int', 'nullable' => false],
                                'default' => null,
                                'promoted' => true,
                            ],
                            [
                                'name' => 'delivery',
                                'type' => [
                                    'name' => 'BlackOps\\Core\\Audit\\AuditDelivery',
                                    'nullable' => false,
                                ],
                                'default' => null,
                                'promoted' => true,
                            ],
                        ],
                    ],
                    'toArray' => [
                        'return' => ['name' => 'array', 'nullable' => false],
                        'parameters' => [],
                    ],
                ],
            ],
            default => throw new \LogicException('Unsupported source surface.'),
        };
    }

    private function sourceSurface(string $source): array
    {
        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $nodes = $parser->parse($source);
        if (!is_array($nodes)) {
            throw new \LogicException('The source did not produce a statement list.');
        }
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $nodes = $traverser->traverse($nodes);
        $declarations = [
            ...new NodeFinder()->findInstanceOf($nodes, Class_::class),
            ...new NodeFinder()->findInstanceOf($nodes, Interface_::class),
        ];
        if (count($declarations) !== 1) {
            throw new \LogicException('The source did not produce one public declaration.');
        }
        $declaration = $declarations[0];
        $properties = [];
        foreach (new NodeFinder()->findInstanceOf($declaration, Property::class) as $property) {
            if (!$property->isPublic()) {
                continue;
            }
            foreach ($property->props as $propertyNode) {
                $properties[$propertyNode->name->toString()] = $this->sourceType($property->type);
            }
        }
        $methods = [];
        foreach (new NodeFinder()->findInstanceOf($declaration, ClassMethod::class) as $method) {
            if (!$method->isPublic() || !$method->name instanceof Node\Identifier) {
                continue;
            }
            $parameters = [];
            foreach ($method->params as $parameter) {
                if (!$parameter instanceof Param || !$parameter->var instanceof Variable) {
                    continue;
                }
                $parameters[] = [
                    'name' => $parameter->var->name,
                    'type' => $this->sourceType($parameter->type),
                    'default' => $this->sourceDefault($parameter->default),
                    'promoted' => $parameter->isPromoted(),
                ];
                if ($parameter->isPublic()) {
                    $properties[$parameter->var->name] = $this->sourceType($parameter->type);
                }
            }
            $methods[$method->name->toString()] = [
                'return' => $this->sourceType($method->returnType),
                'parameters' => $parameters,
            ];
        }
        ksort($properties);

        return [
            'properties' => $properties,
            'methods' => $methods,
        ];
    }

    private function sourceSurfaceAccepts(string $source, array $expected): bool
    {
        return $this->sourceSurface($source) === $expected;
    }

    private function sourceType(?Node $type): array
    {
        if ($type instanceof NullableType) {
            return ['name' => $this->sourceTypeName($type->type), 'nullable' => true];
        }

        return ['name' => $this->sourceTypeName($type), 'nullable' => false];
    }

    private function sourceDefault(?Node $default): string|int|null
    {
        if ($default === null) {
            return null;
        }
        if ($default instanceof LNumber) {
            return $default->value;
        }

        return $default->getType();
    }

    private function sourceTypeName(?Node $type): ?string
    {
        if ($type instanceof Name) {
            $resolved = $type->getAttribute('namespacedName') ?? $type->getAttribute('resolvedName');
            if ($resolved instanceof Name) {
                return ltrim($resolved->toString(), '\\');
            }

            return ltrim($type->toString(), '\\');
        }
        if ($type instanceof \PhpParser\Node\Identifier) {
            return strtolower($type->toString());
        }

        return $type?->getType();
    }

    private function replaceOnce(string $source, string $search, string $replacement): string
    {
        $result = str_replace($search, $replacement, $source, $count);
        self::assertSame(1, $count, 'Mutation anchor must be unique.');

        return $result;
    }

    /** @param array{namespace: string, imports: array<string, string>} $context */
    private function resolveDocumentedType(string $type, array $context): ?string
    {
        $type = trim($type);
        $lowerType = strtolower($type);
        if (in_array($lowerType, ['array', 'bool', 'float', 'int', 'string', 'void'], true)) {
            return $lowerType;
        }
        if (str_starts_with($type, '\\')) {
            $resolved = ltrim($type, '\\');
        } elseif (in_array($type, self::CLOSED_PUBLIC_TYPES, true)) {
            $resolved = $type;
        } else {
            $parts = explode('\\', $type);
            $head = strtolower($parts[0]);
            if (isset($context['imports'][$head])) {
                $resolved = $context['imports'][$head];
                if (count($parts) > 1) {
                    $resolved .= '\\' . implode('\\', array_slice($parts, 1));
                }
            } else {
                $namespace = trim($context['namespace'], '\\');
                $resolved = $namespace === '' ? $type : $namespace . '\\' . $type;
            }
        }
        $resolved = ltrim($resolved, '\\');

        return in_array($resolved, self::CLOSED_PUBLIC_TYPES, true) ? $resolved : null;
    }

    private function assertClosedType(?\ReflectionType $type, string $subject, ?\ReflectionMethod $method = null): void
    {
        self::assertNotNull($type, $subject);
        $types =
            $type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType
                ? $type->getTypes()
                : [$type];
        foreach ($types as $member) {
            $name = ltrim($member->getName(), '\\');
            if (in_array($name, ['self', 'static'], true)) {
                self::assertTrue($this->isSupportedEnumFactoryReturn($member, $method), $subject);
                $name = $method?->getDeclaringClass()->getName() ?? $name;
            }
            self::assertContains($name, self::CLOSED_PUBLIC_TYPES, $subject);
            self::assertNotSame('mixed', $member->getName(), $subject);
            self::assertNotSame('object', $member->getName(), $subject);
            self::assertNotSame('Throwable', $member->getName(), $subject);
            self::assertFalse(str_starts_with($member->getName(), 'BlackOps\\Internal\\'), $subject);
        }
    }

    private function isSupportedEnumFactoryReturn(\ReflectionType $type, ?\ReflectionMethod $method): bool
    {
        if (
            !$type instanceof \ReflectionNamedType
            || $method === null
            || !in_array($type->getName(), ['self', 'static'], true)
            || !in_array($method->getName(), ['from', 'tryFrom'], true)
            || !$method->isPublic()
            || !$method->isStatic()
            || !$method->isInternal()
        ) {
            return false;
        }
        $declaring = $method->getDeclaringClass();
        $resolved = $declaring->getName();

        return (
            $declaring->isEnum()
            && in_array($resolved, self::SUPPORTED_CORE_TYPES, true)
            && $type->allowsNull() === ($method->getName() === 'tryFrom')
        );
    }
}

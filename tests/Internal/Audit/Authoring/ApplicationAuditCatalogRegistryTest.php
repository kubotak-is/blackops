<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Authoring;

use BlackOps\Audit\AuditCatalogDefinition;
use BlackOps\Audit\AuditCatalogProvider;
use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditEvidenceClassification;
use BlackOps\Core\Audit\AuditEvidenceFieldDefinition;
use BlackOps\Core\Audit\AuditEvidenceType;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Authoring\ApplicationAuditCatalogRegistry;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ApplicationAuditCatalogRegistryTest extends TestCase
{
    private const string APPLICATION_CATALOG_UNSIGNED = '{"catalogId":"application.catalog","catalogVersion":1,"events":[{"actionTypes":["application.event.created"],"category":"business","delivery":"required","evidenceFields":[],"evidenceVersion":1,"maxEvidenceBytes":1024,"semanticTuples":[{"actionType":"application.event.created","outcomeCode":"created","outcomeStatus":"succeeded","phase":"succeeded","policyDecision":"allow","policyReasonCode":"created","resourceType":"record"}],"type":"application.event.created","version":1}],"kind":"audit_event_catalog","owner":"application","schemaVersion":1}';
    private const string APPLICATION_CATALOG_COMPLETE = '{"catalogHash":"c5a41cc46934046adf2decb6578c39900375d634311aee54fe96d549f51b411d","catalogId":"application.catalog","catalogVersion":1,"events":[{"actionTypes":["application.event.created"],"category":"business","delivery":"required","evidenceFields":[],"evidenceVersion":1,"maxEvidenceBytes":1024,"semanticTuples":[{"actionType":"application.event.created","outcomeCode":"created","outcomeStatus":"succeeded","phase":"succeeded","policyDecision":"allow","policyReasonCode":"created","resourceType":"record"}],"type":"application.event.created","version":1}],"kind":"audit_event_catalog","owner":"application","schemaVersion":1}';
    private const string APPLICATION_CATALOG_HASH = 'c5a41cc46934046adf2decb6578c39900375d634311aee54fe96d549f51b411d';

    public function testNullAndEmptyProviderUseOnlyTheTwoSealedFrameworkCatalogs(): void
    {
        $framework = FrameworkAuditEventCatalog::catalog();
        $operational = FrameworkOperationalAuditEventCatalog::catalog();

        $nullLookup = new ApplicationAuditCatalogRegistry(null)->lookup();
        self::assertSame($framework, $nullLookup->find(
            'framework',
            $framework->catalogId,
            $framework->catalogVersion,
            $framework->catalogHash,
        ));
        self::assertSame($operational, $nullLookup->find(
            'framework',
            $operational->catalogId,
            $operational->catalogVersion,
            $operational->catalogHash,
        ));
        self::assertNull($nullLookup->find('application', 'application.catalog', 1, str_repeat('0', 64)));

        $provider = new MutableAuditCatalogProvider([]);
        $emptyLookup = new ApplicationAuditCatalogRegistry($provider)->lookup();
        self::assertSame(1, $provider->calls);
        self::assertSame($framework, $emptyLookup->resolve($framework->catalogHash));
        self::assertSame($operational, $emptyLookup->resolve($operational->catalogHash));
    }

    public function testProviderIsEvaluatedOnceAndLookupOwnsAnImmutableCapture(): void
    {
        $definition = $this->definition();
        $provider = new MutableAuditCatalogProvider([$definition]);
        $registry = new ApplicationAuditCatalogRegistry($provider);
        $lookup = $registry->lookup();
        $catalog = $lookup->find(
            'application',
            $definition->catalogId,
            1,
            $this->applicationHash($lookup, $definition),
        );

        self::assertInstanceOf(AuditEventCatalog::class, $catalog);
        self::assertSame($lookup, $registry->lookup());
        self::assertSame(1, $provider->calls);

        $provider->definitions = [];
        self::assertSame(1, $provider->calls);
        self::assertSame($catalog, $lookup->resolve($catalog->catalogHash));
        self::assertSame($catalog, $lookup->find('application', $definition->catalogId, 1, $catalog->catalogHash));
    }

    public function testProviderListShapeAndCountAreRejectedBeforeAssembly(): void
    {
        $definition = $this->definition();
        $atLimit = new MutableAuditCatalogProvider(array_fill(0, 32, $definition));
        $limitLookup = new ApplicationAuditCatalogRegistry($atLimit)->lookup();
        self::assertSame(1, $atLimit->calls);
        self::assertInstanceOf(AuditEventCatalog::class, $limitLookup->find(
            'application',
            $definition->catalogId,
            $definition->catalogVersion,
            self::APPLICATION_CATALOG_HASH,
        ));

        foreach ([
            [new MutableAuditCatalogProvider([1 => $definition]), AuditValidationFailure::ShapeInvalid],
            [new MutableAuditCatalogProvider(['wrong']), AuditValidationFailure::ShapeInvalid],
            [
                new MutableAuditCatalogProvider([FrameworkAuditEventCatalog::catalog()]),
                AuditValidationFailure::ShapeInvalid,
            ],
            [new MutableAuditCatalogProvider(array_fill(0, 33, $definition)), AuditValidationFailure::SemanticInvalid],
            [new WrongReturnAuditCatalogProvider(), AuditValidationFailure::SemanticInvalid],
            [new ThrowingAuditCatalogProvider('provider-canary'), AuditValidationFailure::SemanticInvalid],
            [new ThrowingValidationAuditCatalogProvider(), AuditValidationFailure::ShapeInvalid],
        ] as [$provider, $failure]) {
            $this->assertRegistryFailure($provider, $failure);
        }
    }

    public function testSealedIdsReservedPrefixesAndConflictingIdentitiesFailClosed(): void
    {
        foreach ([
            FrameworkAuditEventCatalog::catalog()->catalogId,
            FrameworkOperationalAuditEventCatalog::catalog()->catalogId,
        ] as $catalogId) {
            $this->assertRegistryFailure(
                new MutableAuditCatalogProvider([$this->definition($catalogId)]),
                AuditValidationFailure::SemanticInvalid,
            );
        }

        foreach ([
            FrameworkAuditEventCatalog::catalog()->catalogId,
            FrameworkOperationalAuditEventCatalog::catalog()->catalogId,
        ] as $catalogId) {
            $this->assertRegistryFailure(
                new MutableAuditCatalogProvider([$this->definition($catalogId, 2)]),
                AuditValidationFailure::SemanticInvalid,
            );
        }

        foreach ([
            'security.event',
            'access.event',
            'retention.event',
            'storage.event',
            'observer.event',
            'outbox.event',
            'audit.event',
            'configuration.event',
            'control.event',
            'replay.event',
            'rotation.event',
        ] as $eventType) {
            $this->assertRegistryFailure(
                new MutableAuditCatalogProvider([$this->definition('application.reserved', 1, $eventType)]),
                AuditValidationFailure::SemanticInvalid,
            );
        }

        $this->assertRegistryFailure(
            new MutableAuditCatalogProvider([
                $this->definition('application.same', 1, 'application.first'),
                $this->definition('application.same', 1, 'application.second'),
            ]),
            AuditValidationFailure::SemanticInvalid,
        );
    }

    public function testRegistryPassesOrderingAndDuplicateSelectorChecksToTheCodec(): void
    {
        $outOfOrder = new AuditCatalogDefinition('application.out-of-order', 1, [
            $this->event('application.zed'),
            $this->event('application.aaa'),
        ]);
        $this->assertRegistryFailure(
            new MutableAuditCatalogProvider([$outOfOrder]),
            AuditValidationFailure::OrderOrUniquenessInvalid,
        );

        $duplicateSelector = new AuditCatalogDefinition('application.duplicate-selector', 1, [
            $this->event('application.duplicate-selector', AuditDelivery::Required),
            $this->event('application.duplicate-selector', AuditDelivery::Durable),
        ]);
        $this->assertRegistryFailure(
            new MutableAuditCatalogProvider([$duplicateSelector]),
            AuditValidationFailure::OrderOrUniquenessInvalid,
        );
    }

    public function testVersionsCoalesceEqualDeclarationsAndRetainCanonicalFirstIdentity(): void
    {
        $first = $this->definition('application.versioned', 1, 'application.first');
        $equalSecond = $this->definition('application.versioned', 1, 'application.first');
        $second = $this->definition('application.versioned', 2, 'application.second');
        $registry = new ApplicationAuditCatalogRegistry(new MutableAuditCatalogProvider([
            $first,
            $equalSecond,
            $second,
        ]));
        $lookup = $registry->lookup();

        $v1 = $lookup->find('application', 'application.versioned', 1, $this->applicationHash($lookup, $first));
        $v2 = $lookup->find('application', 'application.versioned', 2, $this->applicationHash($lookup, $second));

        self::assertInstanceOf(AuditEventCatalog::class, $v1);
        self::assertInstanceOf(AuditEventCatalog::class, $v2);
        self::assertNotSame($v1, $v2);
        self::assertSame($first->events[0], $v1->events[0]);
        self::assertNotSame($equalSecond->events[0], $v1->events[0]);
        self::assertSame($v1, $lookup->resolve($v1->catalogHash));
        self::assertSame($v2, $lookup->resolve($v2->catalogHash));
        self::assertNull($lookup->find('application', 'application.versioned', 1, str_repeat('0', 64)));
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $plan = $selector->select($v1, $first->events[0], 'required');
        self::assertSame($first->events[0]->type, $plan->eventType());
    }

    public function testSameSelectorIsScopedByCatalogIdentityAndHash(): void
    {
        $left = $this->definition('application.left', 1, 'application.shared.selector');
        $right = $this->definition('application.right', 1, 'application.shared.selector');
        $lookup = new ApplicationAuditCatalogRegistry(new MutableAuditCatalogProvider([$left, $right]))->lookup();
        $leftHash = $this->applicationHash($lookup, $left);
        $rightHash = $this->applicationHash($lookup, $right);
        $leftCatalog = $lookup->find('application', $left->catalogId, 1, $leftHash);
        $rightCatalog = $lookup->find('application', $right->catalogId, 1, $rightHash);

        self::assertInstanceOf(AuditEventCatalog::class, $leftCatalog);
        self::assertInstanceOf(AuditEventCatalog::class, $rightCatalog);
        self::assertNotSame($leftHash, $rightHash);
        self::assertNotSame($leftCatalog, $rightCatalog);
        self::assertSame($leftCatalog, $lookup->resolve($leftHash));
        self::assertSame($rightCatalog, $lookup->resolve($rightHash));
        self::assertNull($lookup->find('application', $left->catalogId, 1, $rightHash));
        self::assertNull($lookup->find('application', $right->catalogId, 1, $leftHash));
    }

    public function testReferencedDeclarationArraysAreRejectedAtEveryRetainedBoundary(): void
    {
        foreach (['events', 'actionTypes', 'semanticTuples', 'evidenceFields'] as $boundary) {
            $this->assertRegistryFailure(
                new MutableAuditCatalogProvider([$this->referencedDefinition($boundary)]),
                AuditValidationFailure::ShapeInvalid,
            );
        }

        static $aliases = [];
        $required = $this->event('application.alias', AuditDelivery::Required);
        $alternate = $this->event('application.alias', AuditDelivery::Durable);
        $external = $required;
        $events = [$external];
        $events[0] = &$external;
        $aliases[] = &$external;
        $definition = new AuditCatalogDefinition('application.alias', 1, $events);
        $external = $alternate;

        self::assertSame(AuditDelivery::Durable, $definition->events[0]->delivery);
        $this->assertRegistryFailure(
            new MutableAuditCatalogProvider([$definition]),
            AuditValidationFailure::ShapeInvalid,
        );
    }

    public function testApplicationOwnerAndDeliveryArePreservedForSelectorIdentityChecks(): void
    {
        $definition = $this->definition('application.selectable', 1, 'application.selectable', AuditDelivery::Durable);
        $lookup = new ApplicationAuditCatalogRegistry(new MutableAuditCatalogProvider([$definition]))->lookup();
        $catalog = $lookup->find(
            'application',
            $definition->catalogId,
            1,
            $this->applicationHash($lookup, $definition),
        );

        self::assertInstanceOf(AuditEventCatalog::class, $catalog);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $plan = $selector->select($catalog, $catalog->events[0], 'durable');
        self::assertSame(AuditDelivery::Durable, $plan->delivery());
        self::assertSame($catalog->events[0]->type, $plan->eventType());
        self::assertSame($definition->events[0], $catalog->events[0]);

        $clone = new AuditEventCatalog(
            $catalog->catalogId,
            $catalog->catalogVersion,
            AuditSourceOwner::Application,
            $catalog->events,
            $catalog->catalogHash,
        );
        try {
            $selector->select($clone, $catalog->events[0], 'durable');
            self::fail('A reconstructed catalog was accepted by the selector.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }
    }

    public function testIndependentLiteralApplicationCatalogExpectationMatchesTheRegistry(): void
    {
        $definition = $this->definition();
        $lookup = new ApplicationAuditCatalogRegistry(new MutableAuditCatalogProvider([$definition]))->lookup();
        $catalog = $lookup->find(
            AuditSourceOwner::Application->value,
            'application.catalog',
            1,
            self::APPLICATION_CATALOG_HASH,
        );

        self::assertInstanceOf(AuditEventCatalog::class, $catalog);
        self::assertSame(AuditSourceOwner::Application, $catalog->owner);
        self::assertSame('application.catalog', $catalog->catalogId);
        self::assertSame(1, $catalog->catalogVersion);
        self::assertSame(self::APPLICATION_CATALOG_HASH, $catalog->catalogHash);
        self::assertSame(
            self::APPLICATION_CATALOG_UNSIGNED,
            new AuditJcsCodec()->canonicalJson($catalog->unsignedArray()),
        );
        self::assertSame(self::APPLICATION_CATALOG_COMPLETE, new AuditJcsCodec()->canonicalJson($catalog->toArray()));
    }

    public function testProviderFailureIsFreshFiniteAndDoesNotExposeRawCause(): void
    {
        $canary = 'provider-secret-canary';
        $previousIgnoreArgs = ini_get('zend.exception_ignore_args');
        self::assertIsString($previousIgnoreArgs);
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        self::assertSame('0', ini_get('zend.exception_ignore_args'));

        try {
            try {
                new ApplicationAuditCatalogRegistry(new ThrowingAuditCatalogProvider($canary));
                self::fail('Throwing provider was accepted.');
            } catch (AuditValidationException $exception) {
                self::assertSame(AuditValidationFailure::SemanticInvalid, $exception->failure());
                self::assertSame('semantic_invalid', $exception->getMessage());
                self::assertSame('semantic_invalid', $exception->code());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString($canary, (string) $exception);
                $this->assertOwnedProviderArgumentsAreProtected($exception, $canary);
            }

            $original = new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            try {
                new ApplicationAuditCatalogRegistry(new ThrowingValidationAuditCatalogProvider($original));
                self::fail('Validation-throwing provider was accepted.');
            } catch (AuditValidationException $exception) {
                self::assertSame(AuditValidationFailure::ShapeInvalid, $exception->failure());
                self::assertNotSame($original, $exception);
                self::assertNull($exception->getPrevious());
                $this->assertOwnedProviderArgumentsAreProtected($exception, '');
            }

            try {
                new ApplicationAuditCatalogRegistry(new InvalidListAuditCatalogProvider('invalid-list-canary'));
                self::fail('An invalid provider list was accepted.');
            } catch (AuditValidationException $exception) {
                self::assertSame(AuditValidationFailure::ShapeInvalid, $exception->failure());
                $this->assertOwnedProviderArgumentsAreProtected($exception, 'invalid-list-canary');
            }
        } finally {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', $previousIgnoreArgs));
            self::assertSame($previousIgnoreArgs, ini_get('zend.exception_ignore_args'));
        }
    }

    public function testServiceProviderBindingSurvivesCompileDumpAndFreshReload(): void
    {
        $root = sys_get_temp_dir() . '/blackops-e1-registry-' . bin2hex(random_bytes(8));
        mkdir($root, 0o755, true);

        try {
            $compiler = new RuntimeContainerCompiler();
            $builder = $compiler->builder();
            $compiler->apply($builder, [new CompiledAuditCatalogServiceProvider()]);
            $compiler->compile($builder);

            $class = 'CompiledAuditCatalogContainer' . bin2hex(random_bytes(8));
            $namespace = __NAMESPACE__ . '\\Generated';
            $path = $root . '/container.php';
            new RuntimeContainerDumper()->dump($builder, $path, $class, $namespace);
            require_once $path;

            $containerClass = $namespace . '\\' . $class;
            $container = new $containerClass();
            $provider = $container->get(AuditCatalogProvider::class);

            self::assertInstanceOf(CompiledAuditCatalogProvider::class, $provider);
            self::assertInstanceOf(ContainerInterface::class, $container);
            $lookup = new ApplicationAuditCatalogRegistry($provider)->lookup();
            self::assertSame(
                FrameworkAuditEventCatalog::catalog(),
                $lookup->resolve(FrameworkAuditEventCatalog::catalog()->catalogHash),
            );
            $catalog = $lookup->find('application', 'application.catalog', 1, self::APPLICATION_CATALOG_HASH);
            self::assertInstanceOf(AuditEventCatalog::class, $catalog);
            self::assertSame(AuditSourceOwner::Application, $catalog->owner);
            self::assertSame(
                self::APPLICATION_CATALOG_UNSIGNED,
                new AuditJcsCodec()->canonicalJson($catalog->unsignedArray()),
            );
        } finally {
            if (is_file($root . '/container.php')) {
                unlink($root . '/container.php');
            }
            if (is_dir($root)) {
                rmdir($root);
            }
        }
    }

    private function assertOwnedProviderArgumentsAreProtected(\Throwable $exception, string $canary): void
    {
        $owned = array_values(array_filter(
            $exception->getTrace(),
            static fn(array $frame): bool => ($frame['class'] ?? null) === ApplicationAuditCatalogRegistry::class
            && in_array($frame['function'] ?? null, ['__construct', 'assemble'], true),
        ));
        self::assertGreaterThanOrEqual(1, count($owned));
        self::assertContains('__construct', array_column($owned, 'function'));
        foreach ($owned as $frame) {
            self::assertArrayHasKey('args', $frame);
            self::assertCount(1, $frame['args']);
            self::assertInstanceOf(\SensitiveParameterValue::class, $frame['args'][0]);
        }

        if ($canary !== '') {
            ob_start();
            var_dump(array_map(static fn(array $frame): array => [
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'] ?? null,
                'args' => $frame['args'] ?? [],
            ], $exception->getTrace()));
            $rendered = (string) ob_get_clean();
            self::assertStringNotContainsString($canary, $rendered);
        }
    }

    private function assertRegistryFailure(AuditCatalogProvider $provider, AuditValidationFailure $failure): void
    {
        try {
            new ApplicationAuditCatalogRegistry($provider);
            self::fail('Invalid provider was accepted.');
        } catch (AuditValidationException $exception) {
            self::assertSame($failure, $exception->failure());
            self::assertSame($failure->value, $exception->getMessage());
            self::assertSame($failure->value, $exception->code());
            self::assertNull($exception->getPrevious());
        }
    }

    private function applicationHash(
        ImmutableAuditEventCatalogLookup $lookup,
        AuditCatalogDefinition $definition,
    ): string {
        $unsigned = new AuditJcsCodec()->canonicalJson([
            'schemaVersion' => 1,
            'kind' => 'audit_event_catalog',
            'catalogId' => $definition->catalogId,
            'catalogVersion' => $definition->catalogVersion,
            'owner' => AuditSourceOwner::Application->value,
            'events' => array_values(array_map(
                static fn(AuditEventDefinition $event): array => $event->toArray(),
                $definition->events,
            )),
        ]);
        $hash = hash(
            'sha256',
            's:'
            . strlen('blackops.audit.catalog.v1')
            . ':blackops.audit.catalog.v1'
            . 'b:'
            . strlen($unsigned)
            . ':'
            . $unsigned,
        );

        self::assertInstanceOf(AuditEventCatalog::class, $lookup->find(
            'application',
            $definition->catalogId,
            $definition->catalogVersion,
            $hash,
        ));

        return $hash;
    }

    private function referencedDefinition(string $boundary): AuditCatalogDefinition
    {
        static $references = [];

        $event = $this->event();
        if ($boundary === 'events') {
            $external = $event;
            $events = [$external];
            $events[0] = &$external;
            $references[] = &$external;
            return new AuditCatalogDefinition('application.referenced', 1, $events);
        }

        if ($boundary === 'actionTypes') {
            $actionTypes = $event->actionTypes;
            $external = $actionTypes[0];
            $actionTypes[0] = &$external;
            $references[] = &$external;
            $event = $this->event($event->type, AuditDelivery::Required, $actionTypes);
        }

        if ($boundary === 'semanticTuples') {
            $semanticTuples = $event->semanticTuples;
            $external = $semanticTuples[0];
            $semanticTuples[0] = &$external;
            $references[] = &$external;
            $event = $this->event($event->type, AuditDelivery::Required, null, $semanticTuples);
        }

        if ($boundary === 'evidenceFields') {
            $evidenceFields = [new AuditEvidenceFieldDefinition(
                'opaque_id',
                AuditEvidenceType::String,
                true,
                false,
                71,
                AuditEvidenceClassification::OpaqueId,
            )];
            $external = $evidenceFields[0];
            $evidenceFields[0] = &$external;
            $references[] = &$external;
            $event = $this->event($event->type, AuditDelivery::Required, null, null, $evidenceFields);
        }

        return new AuditCatalogDefinition('application.referenced', 1, [$event]);
    }

    private function definition(
        string $catalogId = 'application.catalog',
        int $catalogVersion = 1,
        string $eventType = 'application.event.created',
        AuditDelivery $delivery = AuditDelivery::Required,
    ): AuditCatalogDefinition {
        return new AuditCatalogDefinition($catalogId, $catalogVersion, [$this->event($eventType, $delivery)]);
    }

    /** @param list<string>|null $actionTypes @param list<AuditSemanticTuple>|null $semanticTuples @param list<AuditEvidenceFieldDefinition>|null $evidenceFields */
    private function event(
        string $type = 'application.event.created',
        AuditDelivery $delivery = AuditDelivery::Required,
        ?array $actionTypes = null,
        ?array $semanticTuples = null,
        ?array $evidenceFields = null,
    ): AuditEventDefinition {
        return new AuditEventDefinition(
            $type,
            1,
            1,
            AuditEventCategory::Business,
            $actionTypes ?? [$type],
            $semanticTuples ?? [new AuditSemanticTuple(
                $type,
                AuditActionPhase::Succeeded,
                'record',
                AuditOutcomeStatus::Succeeded,
                'created',
                AuditPolicyDecision::Allow,
                'created',
            )],
            $evidenceFields ?? [],
            1024,
            $delivery,
        );
    }
}

final class MutableAuditCatalogProvider implements AuditCatalogProvider
{
    public int $calls = 0;

    /** @var array<array-key,mixed> */
    public array $definitions;

    /** @param array<array-key,mixed> $definitions */
    public function __construct(array $definitions)
    {
        $this->definitions = $definitions;
    }

    public function catalogs(): array
    {
        ++$this->calls;
        return $this->definitions;
    }
}

final class WrongReturnAuditCatalogProvider implements AuditCatalogProvider
{
    public function catalogs(): array
    {
        /** @var array<array-key,mixed> $value */
        $value = unserialize('s:5:"wrong";');
        return $value;
    }
}

final readonly class ThrowingAuditCatalogProvider implements AuditCatalogProvider
{
    public function __construct(
        private string $canary,
    ) {}

    public function catalogs(): array
    {
        throw new RuntimeException($this->canary);
    }
}

final readonly class ThrowingValidationAuditCatalogProvider implements AuditCatalogProvider
{
    public function __construct(
        private AuditValidationException $failure = new AuditValidationException(AuditValidationFailure::ShapeInvalid),
    ) {}

    public function catalogs(): array
    {
        throw $this->failure;
    }
}

final readonly class InvalidListAuditCatalogProvider implements AuditCatalogProvider
{
    public function __construct(
        private string $canary,
    ) {}

    public function catalogs(): array
    {
        return [$this->canary];
    }
}

final readonly class CompiledAuditCatalogServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(AuditCatalogProvider::class, CompiledAuditCatalogProvider::class);
    }
}

final readonly class CompiledAuditCatalogProvider implements AuditCatalogProvider
{
    public function catalogs(): array
    {
        return [new AuditCatalogDefinition('application.catalog', 1, [new AuditEventDefinition(
            'application.event.created',
            1,
            1,
            AuditEventCategory::Business,
            ['application.event.created'],
            [new AuditSemanticTuple(
                'application.event.created',
                AuditActionPhase::Succeeded,
                'record',
                AuditOutcomeStatus::Succeeded,
                'created',
                AuditPolicyDecision::Allow,
                'created',
            )],
            [],
            1024,
            AuditDelivery::Required,
        )])];
    }
}

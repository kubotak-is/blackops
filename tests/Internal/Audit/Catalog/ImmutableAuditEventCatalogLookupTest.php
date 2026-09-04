<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Catalog;

use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use PHPUnit\Framework\TestCase;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class ImmutableAuditEventCatalogLookupTest extends TestCase
{
    public function testResolvesByIdentityAndHashOnly(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        self::assertSame($catalog, $lookup->find('framework', $catalog->catalogId, 1, $catalog->catalogHash));
        self::assertSame($catalog, $lookup->resolve($catalog->catalogHash));
        self::assertNull($lookup->find('framework', $catalog->catalogId, 1, str_repeat('0', 64)));
    }

    public function testOperationalProviderResolvesInAnExclusiveLookup(): void
    {
        $operations = FrameworkOperationalAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$operations]);

        self::assertSame($operations, $lookup->find('framework', $operations->catalogId, 1, $operations->catalogHash));
        self::assertSame($operations, $lookup->resolve($operations->catalogHash));
    }

    public function testResolvesBothFrameworkProvidersByFullIdentityAndHash(): void
    {
        $security = FrameworkAuditEventCatalog::catalog();
        $operations = FrameworkOperationalAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$security, $operations]);

        self::assertSame($security, $lookup->find('framework', $security->catalogId, 1, $security->catalogHash));
        self::assertSame($operations, $lookup->find('framework', $operations->catalogId, 1, $operations->catalogHash));
        self::assertSame($security, $lookup->resolve($security->catalogHash));
        self::assertSame($operations, $lookup->resolve($operations->catalogHash));
        self::assertNull($lookup->find('framework', $operations->catalogId, 1, $security->catalogHash));
        self::assertNull($lookup->resolve(str_repeat('0', 64)));
        self::assertSame($operations, $lookup->resolve($operations->catalogHash));
    }

    public function testTwoApplicationHashesResolveIndependentlyAndUnknownHashIsAbsent(): void
    {
        $codec = new AuditEventCatalogCodec();
        $first = $this->applicationCatalog($codec, 'application.alpha');
        $second = $this->applicationCatalog($codec, 'application.beta');
        $lookup = new ImmutableAuditEventCatalogLookup([$first, $second]);
        self::assertSame($first, $lookup->resolve($first->catalogHash));
        self::assertSame($second, $lookup->resolve($second->catalogHash));
        self::assertNull($lookup->resolve(str_repeat('0', 64)));
    }

    public function testSameIdentityAndBytesIsIdempotentButDifferentBytesConflict(): void
    {
        $codec = new AuditEventCatalogCodec();
        $catalog = $this->applicationCatalog($codec, 'application.same', 'application.event');
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog, $catalog]);
        self::assertSame($catalog, $lookup->find('application', 'application.same', 1, $catalog->catalogHash));
        $different = $this->applicationCatalog($codec, 'application.same', 'application.other');
        $this->expectException(AuditValidationException::class);
        new ImmutableAuditEventCatalogLookup([$catalog, $different]);
    }

    public function testSameIdentityAndUnsignedBodyWithDifferentValidHashFailsAtSemanticStage(): void
    {
        $catalog = $this->applicationCatalog(new AuditEventCatalogCodec(), 'application.mutated');
        $mutated = new AuditEventCatalog(
            $catalog->catalogId,
            $catalog->catalogVersion,
            $catalog->owner,
            $catalog->events,
            str_repeat('a', 64),
        );
        self::assertSame($catalog->unsignedArray(), $mutated->unsignedArray());
        self::assertNotSame($catalog->toArray(), $mutated->toArray());

        try {
            new ImmutableAuditEventCatalogLookup([$catalog, $mutated]);
            self::fail('duplicate identity with a mutated catalog hash was accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testSameHashDifferentBytesAndEveryReservedPrefixFailClosed(): void
    {
        $codec = new AuditEventCatalogCodec();
        $catalog = $this->applicationCatalog($codec, 'application.hash', 'application.event');
        $different = new AuditEventCatalog(
            'application.other',
            $catalog->catalogVersion,
            $catalog->owner,
            $this->applicationCatalog($codec, 'application.other', 'application.other')->events,
            $catalog->catalogHash,
        );
        try {
            new ImmutableAuditEventCatalogLookup([$catalog, $different]);
            self::fail('same hash with different bytes accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('hash_mismatch', $exception->code());
        }
        foreach ([
            'security.',
            'access.',
            'retention.',
            'storage.',
            'observer.',
            'outbox.',
            'audit.',
            'configuration.',
            'control.',
            'replay.',
            'rotation.',
        ] as $prefix) {
            try {
                new ImmutableAuditEventCatalogLookup([$this->applicationCatalog(
                    $codec,
                    'application.prefix',
                    $prefix . 'event',
                )]);
                self::fail('reserved application prefix accepted: ' . $prefix);
            } catch (AuditValidationException $exception) {
                self::assertSame('semantic_invalid', $exception->code());
            }
        }
    }

    public function testCollectionSemanticPassPrecedesEarlierWrongHash(): void
    {
        $codec = new AuditEventCatalogCodec();
        $first = $this->applicationCatalog($codec, 'application.duplicate', 'application.first');
        $wrongHash = new AuditEventCatalog(
            $first->catalogId,
            $first->catalogVersion,
            $first->owner,
            $first->events,
            str_repeat('0', 64),
        );
        $second = $this->applicationCatalog($codec, 'application.duplicate', 'application.second');
        try {
            new ImmutableAuditEventCatalogLookup([$wrongHash, $second]);
            self::fail('duplicate identity was accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }

        $framework = FrameworkAuditEventCatalog::catalog();
        $decoded = new AuditEventCatalogCodec()->decode(new AuditEventCatalogCodec()->encode($framework));
        try {
            new ImmutableAuditEventCatalogLookup([$wrongHash, $decoded]);
            self::fail('provider substitution was accepted after a wrong hash');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testCollectionCapsPassPrecedesEarlierWrongHash(): void
    {
        $codec = new AuditEventCatalogCodec();
        $first = $this->applicationCatalog($codec, 'application.wrong-hash');
        $wrongHash = new AuditEventCatalog(
            $first->catalogId,
            $first->catalogVersion,
            $first->owner,
            $first->events,
            str_repeat('0', 64),
        );
        $later = $this->applicationCatalog($codec, 'application.oversized-later');
        $jcs = new class extends AuditJcsCodec {
            public function canonicalJson(mixed $value): string
            {
                $length = is_array($value) && array_key_exists('catalogHash', $value)
                    ? AuditEventCatalogCodec::COMPLETE_MAX_BYTES + 1
                    : AuditEventCatalogCodec::UNSIGNED_MAX_BYTES + 1;
                $prefix = '{"padding":"';
                $suffix = '"}';
                return $prefix . str_repeat('x', $length - strlen($prefix) - strlen($suffix)) . $suffix;
            }
        };
        try {
            new ImmutableAuditEventCatalogLookup([$wrongHash, $later], $jcs);
            self::fail('later oversized catalog was accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('jcs_bound_invalid', $exception->code());
        }
    }

    public function testWrongHashAloneIsCheckedAfterCollectionCaps(): void
    {
        $codec = new AuditEventCatalogCodec();
        $catalog = $this->applicationCatalog($codec, 'application.wrong-hash-alone');
        $wrongHash = new AuditEventCatalog(
            $catalog->catalogId,
            $catalog->catalogVersion,
            $catalog->owner,
            $catalog->events,
            str_repeat('0', 64),
        );
        try {
            new ImmutableAuditEventCatalogLookup([$wrongHash]);
            self::fail('wrong catalog hash was accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('hash_mismatch', $exception->code());
        }
    }

    public function testFrameworkProviderProvenanceAndReservedApplicationPrefixFailClosed(): void
    {
        $framework = FrameworkAuditEventCatalog::catalog();
        $this->expectException(AuditValidationException::class);
        new ImmutableAuditEventCatalogLookup([new AuditEventCatalog(
            $framework->catalogId,
            $framework->catalogVersion,
            AuditSourceOwner::Framework,
            $framework->events,
            $framework->catalogHash,
        )]);
    }

    public function testBothFrameworkProviderIdentitiesRejectDecodedClonesAndThirdProviders(): void
    {
        $codec = new AuditEventCatalogCodec();
        foreach ([
            FrameworkAuditEventCatalog::catalog(),
            FrameworkOperationalAuditEventCatalog::catalog(),
        ] as $provider) {
            $decoded = $codec->decode($codec->encode($provider));
            self::assertNotSame($provider, $decoded);
            try {
                new ImmutableAuditEventCatalogLookup([$decoded]);
                self::fail('decoded Framework bytes substituted for sealed provider');
            } catch (AuditValidationException $exception) {
                self::assertSame('semantic_invalid', $exception->code());
            }
        }

        $operations = FrameworkOperationalAuditEventCatalog::catalog();
        $third = new AuditEventCatalog(
            'blackops.framework.third',
            $operations->catalogVersion,
            AuditSourceOwner::Framework,
            $operations->events,
            $operations->catalogHash,
        );
        try {
            new ImmutableAuditEventCatalogLookup([$third]);
            self::fail('third Framework provider accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testBothFrameworkProviderIdentitiesRejectSerializedAndReflectedReconstructions(): void
    {
        foreach ([
            FrameworkAuditEventCatalog::catalog(),
            FrameworkOperationalAuditEventCatalog::catalog(),
        ] as $provider) {
            $serialized = serialize($provider);
            $serializedClone = unserialize($serialized, ['allowed_classes' => true]);
            self::assertInstanceOf(AuditEventCatalog::class, $serializedClone);
            self::assertNotSame($provider, $serializedClone);
            self::assertSame($provider->toArray(), $serializedClone->toArray());
            try {
                new ImmutableAuditEventCatalogLookup([$serializedClone]);
                self::fail('serialized Framework bytes substituted for sealed provider');
            } catch (AuditValidationException $exception) {
                self::assertSame('semantic_invalid', $exception->code());
            }

            $reflection = new \ReflectionClass(AuditEventCatalog::class);
            $reflectedClone = $reflection->newInstanceArgs([
                $provider->catalogId,
                $provider->catalogVersion,
                $provider->owner,
                $provider->events,
                $provider->catalogHash,
            ]);
            self::assertNotSame($provider, $reflectedClone);
            self::assertSame($provider->toArray(), $reflectedClone->toArray());
            try {
                new ImmutableAuditEventCatalogLookup([$reflectedClone]);
                self::fail('reflected Framework bytes substituted for sealed provider');
            } catch (AuditValidationException $exception) {
                self::assertSame('semantic_invalid', $exception->code());
            }
        }

        $operations = FrameworkOperationalAuditEventCatalog::catalog();
        $unknown = new AuditEventCatalogCodec()->catalog(
            'blackops.framework.unknown',
            1,
            AuditSourceOwner::Framework,
            $operations->events,
        );
        $lookup = new ImmutableAuditEventCatalogLookup([
            FrameworkAuditEventCatalog::catalog(),
            $operations,
        ]);
        self::assertNull($lookup->find(
            'framework',
            $unknown->catalogId,
            $unknown->catalogVersion,
            $unknown->catalogHash,
        ));
        self::assertNull($lookup->resolve($unknown->catalogHash));
    }

    public function testChangedOperationalIdentityAndValidMutatedHashFailAtSemanticStage(): void
    {
        $codec = new AuditEventCatalogCodec();
        $operations = FrameworkOperationalAuditEventCatalog::catalog();
        $swapped = $codec->catalog(
            'blackops.framework.security_access',
            1,
            AuditSourceOwner::Framework,
            $operations->events,
        );
        try {
            new ImmutableAuditEventCatalogLookup([$swapped]);
            self::fail('operational definitions accepted under the security identity');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }

        $mutatedEvent = $operations->events[0];
        $mutatedTuple = new \BlackOps\Core\Audit\AuditSemanticTuple(
            $mutatedEvent->type,
            $mutatedEvent->semanticTuples[0]->phase,
            $mutatedEvent->semanticTuples[0]->resourceType,
            $mutatedEvent->semanticTuples[0]->outcomeStatus,
            'changed_outcome',
            $mutatedEvent->semanticTuples[0]->policyDecision,
            $mutatedEvent->semanticTuples[0]->policyReasonCode,
        );
        $mutatedEvents = $operations->events;
        $mutatedEvents[0] = new \BlackOps\Core\Audit\AuditEventDefinition(
            $mutatedEvent->type,
            $mutatedEvent->version,
            $mutatedEvent->evidenceVersion,
            $mutatedEvent->category,
            $mutatedEvent->actionTypes,
            [$mutatedTuple],
            $mutatedEvent->evidenceFields,
            $mutatedEvent->maxEvidenceBytes,
            $mutatedEvent->delivery,
        );
        $mutated = $codec->catalog(
            $operations->catalogId,
            $operations->catalogVersion,
            AuditSourceOwner::Framework,
            $mutatedEvents,
        );
        self::assertNotSame($operations->catalogHash, $mutated->catalogHash);
        try {
            new ImmutableAuditEventCatalogLookup([$mutated]);
            self::fail('mutated operational provider accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testSameFrameworkSingletonCanBeRepeatedWithoutMutation(): void
    {
        $operations = FrameworkOperationalAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$operations, $operations]);
        self::assertSame($operations, $lookup->find('framework', $operations->catalogId, 1, $operations->catalogHash));
        self::assertSame($operations, $lookup->resolve($operations->catalogHash));
    }

    public function testDecodedFrameworkBytesDoNotImplySealedProviderProvenance(): void
    {
        $provider = FrameworkAuditEventCatalog::catalog();
        $bytes = new AuditEventCatalogCodec()->encode($provider);
        $decoded = new AuditEventCatalogCodec()->decode($bytes);
        self::assertNotSame($provider, $decoded);
        try {
            new ImmutableAuditEventCatalogLookup([$decoded]);
            self::fail('decoded framework bytes substituted for sealed provider');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testApplicationReservedPrefixCannotBypassProviderBoundary(): void
    {
        $framework = FrameworkAuditEventCatalog::catalog();
        $this->expectException(AuditValidationException::class);
        new ImmutableAuditEventCatalogLookup([new AuditEventCatalog(
            $framework->catalogId,
            $framework->catalogVersion,
            AuditSourceOwner::Application,
            $framework->events,
            $framework->catalogHash,
        )]);
    }

    public function testLookupClosesListAndMemberShapeBeforeNativeAccess(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        foreach ([['bad' => $catalog], [$catalog, 'not-a-catalog']] as $catalogs) {
            try {
                new ImmutableAuditEventCatalogLookup($catalogs);
                self::fail('invalid lookup catalog list accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('shape_invalid', $exception->code());
            }
        }
    }

    public function testLookupChecksJcsCapsBeforeWrongHash(): void
    {
        $catalog = $this->applicationCatalog(new AuditEventCatalogCodec(), 'application.oversized');
        $jcs = new class extends AuditJcsCodec {
            public function canonicalJson(mixed $value): string
            {
                $length = is_array($value) && array_key_exists('catalogHash', $value)
                    ? AuditEventCatalogCodec::COMPLETE_MAX_BYTES + 1
                    : AuditEventCatalogCodec::UNSIGNED_MAX_BYTES + 1;
                $prefix = '{"padding":"';
                $suffix = '"}';
                return $prefix . str_repeat('x', $length - strlen($prefix) - strlen($suffix)) . $suffix;
            }
        };
        try {
            new ImmutableAuditEventCatalogLookup([$catalog], $jcs);
            self::fail('oversized lookup catalog accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('jcs_bound_invalid', $exception->code());
        }
    }

    private function applicationCatalog(
        AuditEventCatalogCodec $codec,
        string $id,
        string $eventType = 'application.event',
    ): AuditEventCatalog {
        $tuple = new \BlackOps\Core\Audit\AuditSemanticTuple(
            $eventType,
            \BlackOps\Core\Audit\AuditActionPhase::Succeeded,
            'resource',
            \BlackOps\Core\Audit\AuditOutcomeStatus::Succeeded,
            'ok',
            \BlackOps\Core\Audit\AuditPolicyDecision::Allow,
            'ok',
        );
        $event = new \BlackOps\Core\Audit\AuditEventDefinition(
            $eventType,
            1,
            1,
            \BlackOps\Core\Audit\AuditEventCategory::Business,
            [$eventType],
            [$tuple],
            [],
            13,
            \BlackOps\Core\Audit\AuditDelivery::Required,
        );
        return $codec->catalog($id, 1, AuditSourceOwner::Application, [$event]);
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Audit;

use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use PHPUnit\Framework\TestCase;

final class AuditEventCatalogTest extends TestCase
{
    public function testFrameworkCatalogIdentityAndThirtyEvents(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        self::assertSame('blackops.framework.security_access', $catalog->catalogId);
        self::assertSame('framework', $catalog->owner->value);
        self::assertCount(30, $catalog->events);
        self::assertSame('cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83', $catalog->catalogHash);
    }

    public function testStoredFrameworkFixtureIsExactCanonicalCatalog(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $bytes = new AuditEventCatalogCodec()->encode($catalog);
        $fixture = file_get_contents(__DIR__ . '/../../Fixtures/Audit/framework-audit-event-catalog-v1.json');
        self::assertIsString($fixture);
        self::assertSame(13_960, strlen($fixture));
        self::assertSame($bytes, $fixture);
        self::assertSame($catalog->catalogHash, new AuditEventCatalogCodec()->decode($fixture)->catalogHash);
    }

    public function testEventCountBoundaryIsInclusive(): void
    {
        $events = [];
        for ($index = 1; $index <= 256; ++$index)
            $events[] = $this->event('application.event.' . str_pad((string) $index, 3, '0', STR_PAD_LEFT));
        $catalog = new \BlackOps\Core\Audit\AuditEventCatalog(
            'application.catalog',
            1,
            AuditSourceOwner::Application,
            $events,
            str_repeat('a', 64),
        );
        self::assertCount(256, $catalog->events);
        try {
            new \BlackOps\Core\Audit\AuditEventCatalog(
                'application.catalog',
                1,
                AuditSourceOwner::Application,
                [...$events, $this->event('application.event.257')],
                str_repeat('a', 64),
            );
            self::fail('257 events accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
        try {
            new \BlackOps\Core\Audit\AuditEventCatalog(
                'application.catalog',
                1,
                AuditSourceOwner::Application,
                [],
                str_repeat('a', 64),
            );
            self::fail('zero events accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testCatalogGrammarPrecedesSemanticCardinality(): void
    {
        try {
            new \BlackOps\Core\Audit\AuditEventCatalog(
                'Bad',
                1,
                AuditSourceOwner::Application,
                [],
                str_repeat('a', 64),
            );
            self::fail('compound-invalid catalog accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('grammar_invalid', $exception->code());
        }
    }

    public function testDirectCatalogRunsAllTextAndByteStagesBeforeGrammarOrSemantic(): void
    {
        $event = FrameworkAuditEventCatalog::catalog()->events[0];
        foreach ([
            ["catalog\xFF", "a\x01" . str_repeat('a', 62), 'utf8_invalid'],
            ["e\u{0301}", "a\x01" . str_repeat('a', 62), 'control_invalid'],
            ["e\u{0301}", str_repeat('a', 63), 'nfc_invalid'],
            [str_repeat('a', 129), 'not-a-hash', 'byte_bound_invalid'],
        ] as [$catalogId, $catalogHash, $expected]) {
            try {
                new AuditEventCatalog($catalogId, 1, AuditSourceOwner::Framework, [$event], $catalogHash);
                self::fail('compound-invalid catalog accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame($expected, $exception->code());
            }
        }
        try {
            new AuditEventCatalog(
                'application.catalog',
                1,
                AuditSourceOwner::Application,
                [$event],
                str_repeat('a', 64),
            );
            self::fail('reserved application namespace accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    private function event(string $type): AuditEventDefinition
    {
        return new AuditEventDefinition(
            $type,
            1,
            1,
            AuditEventCategory::Business,
            [$type],
            [new AuditSemanticTuple(
                $type,
                AuditActionPhase::Succeeded,
                'resource',
                AuditOutcomeStatus::Succeeded,
                'ok',
                AuditPolicyDecision::Allow,
                'ok',
            )],
            [],
            13,
            AuditDelivery::Required,
        );
    }
}

<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Catalog;

use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use PHPUnit\Framework\TestCase;

final class FrameworkOperationalAuditEventCatalogTest extends TestCase
{
    public function testExactCatalogIdentityDefinitionsBytesAndSingleton(): void
    {
        $catalog = FrameworkOperationalAuditEventCatalog::catalog();
        self::assertInstanceOf(AuditEventCatalog::class, $catalog);
        self::assertSame($catalog, FrameworkOperationalAuditEventCatalog::catalog());
        self::assertSame('blackops.framework.operations', $catalog->catalogId);
        self::assertSame(1, $catalog->catalogVersion);
        self::assertSame('framework', $catalog->owner->value);
        self::assertSame(14, count($catalog->events));
        self::assertSame('9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b', $catalog->catalogHash);

        $expected = [
            [
                'outbox.delivery.dead_letter_retry_requested',
                'outbox',
                'requested',
                'outbox_record',
                'unknown',
                'outbox_retry_requested',
                'not_applicable',
                'outbox_retry_requested',
            ],
            [
                'outbox.delivery.dead_lettered',
                'outbox',
                'failed',
                'outbox_record',
                'failed',
                'outbox_dead_lettered',
                'not_applicable',
                'outbox_dead_lettered',
            ],
            [
                'outbox.delivery.retry_scheduled',
                'outbox',
                'committed',
                'outbox_record',
                'unknown',
                'outbox_retry_scheduled',
                'not_applicable',
                'outbox_retry_scheduled',
            ],
            [
                'replay.observer.failed',
                'replay',
                'failed',
                'lifecycle_journal',
                'failed',
                'replay_failed',
                'not_applicable',
                'replay_failed',
            ],
            [
                'replay.observer.progressed',
                'replay',
                'committed',
                'lifecycle_journal',
                'unknown',
                'replay_progressed',
                'not_applicable',
                'replay_progressed',
            ],
            [
                'replay.observer.started',
                'replay',
                'started',
                'lifecycle_journal',
                'unknown',
                'replay_started',
                'not_applicable',
                'replay_started',
            ],
            [
                'replay.observer.succeeded',
                'replay',
                'succeeded',
                'lifecycle_journal',
                'succeeded',
                'replay_succeeded',
                'not_applicable',
                'replay_succeeded',
            ],
            [
                'retention.hold.placed',
                'retention',
                'committed',
                'operation',
                'succeeded',
                'retention_hold_placed',
                'not_applicable',
                'retention_hold_placed',
            ],
            [
                'retention.hold.released',
                'retention',
                'committed',
                'operation',
                'succeeded',
                'retention_hold_released',
                'not_applicable',
                'retention_hold_released',
            ],
            [
                'retention.purge.committed',
                'retention',
                'committed',
                'operation_data',
                'succeeded',
                'retention_purged',
                'not_applicable',
                'retention_purged',
            ],
            [
                'rotation.storage.failed',
                'rotation',
                'failed',
                'protected_storage',
                'failed',
                'rotation_failed',
                'not_applicable',
                'rotation_failed',
            ],
            [
                'rotation.storage.progressed',
                'rotation',
                'committed',
                'protected_storage',
                'unknown',
                'rotation_progressed',
                'not_applicable',
                'rotation_progressed',
            ],
            [
                'rotation.storage.started',
                'rotation',
                'started',
                'protected_storage',
                'unknown',
                'rotation_started',
                'not_applicable',
                'rotation_started',
            ],
            [
                'rotation.storage.succeeded',
                'rotation',
                'succeeded',
                'protected_storage',
                'succeeded',
                'rotation_succeeded',
                'not_applicable',
                'rotation_succeeded',
            ],
        ];
        self::assertSame(
            array_map(static fn(array $event): string => $event[0], $expected),
            array_map(static fn($event): string => $event->type, $catalog->events),
        );
        foreach ($expected as $index => $values) {
            $event = $catalog->events[$index];
            self::assertSame($values[0], $event->type);
            self::assertSame(1, $event->version);
            self::assertSame(1, $event->evidenceVersion);
            self::assertSame($values[1], $event->category->value);
            self::assertSame([$values[0]], $event->actionTypes);
            self::assertSame([], $event->evidenceFields);
            self::assertSame(13, $event->maxEvidenceBytes);
            self::assertSame('durable', $event->delivery->value);
            self::assertCount(1, $event->semanticTuples);
            self::assertSame(
                [
                    'actionType' => $values[0],
                    'phase' => $values[2],
                    'resourceType' => $values[3],
                    'outcomeStatus' => $values[4],
                    'outcomeCode' => $values[5],
                    'policyDecision' => $values[6],
                    'policyReasonCode' => $values[7],
                ],
                $event->semanticTuples[0]->toArray(),
            );
            new AuditEventCatalogCodec()->validateEvidence($event, ['version' => 1]);
        }

        $jcs = new AuditJcsCodec();
        $codec = new AuditEventCatalogCodec($jcs);
        $unsigned = $jcs->canonicalJson($catalog->unsignedArray());
        $complete = $jcs->canonicalJson($catalog->toArray());
        $raw = $codec->encode($catalog);
        $preimage =
            's:'
            . strlen('blackops.audit.catalog.v1')
            . ':blackops.audit.catalog.v1b:'
            . strlen($unsigned)
            . ':'
            . $unsigned;
        self::assertSame(6448, strlen($unsigned));
        self::assertSame(6485, strlen($preimage));
        self::assertSame(6529, strlen($complete));
        self::assertSame(6530, strlen($raw));
        self::assertSame(1, substr_count($raw, "\n"));
        self::assertSame($complete . "\n", $raw);
        self::assertSame($catalog->catalogHash, hash('sha256', $preimage));

        $fixture = file_get_contents(__DIR__
        . '/../../../Fixtures/Audit/framework-operational-audit-event-catalog-v1.json');
        self::assertIsString($fixture);
        self::assertSame($raw, $fixture);
        self::assertSame($catalog->toArray(), $codec->decode($fixture)->toArray());

        try {
            $codec->validateEvidence($catalog->events[0], ['version' => 2]);
            self::fail('an evidence version other than one was accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('semantic_invalid', $exception->code());
        }
    }

    public function testPhpMutationMatrixRejectsEveryFieldAndPreservesAcceptedExact30(): void
    {
        $expected = $this->expectedOperationalCatalog();
        self::assertSame($expected, FrameworkOperationalAuditEventCatalog::catalog()->toArray());
        $mutations = [
            'identity.schemaVersion' => static function (array $value): array {
                $value['schemaVersion'] = 2;
                return $value;
            },
            'identity.kind' => static function (array $value): array {
                $value['kind'] = 'changed_catalog';
                return $value;
            },
            'identity.catalogId' => static function (array $value): array {
                $value['catalogId'] = 'blackops.framework.changed';
                return $value;
            },
            'identity.catalogVersion' => static function (array $value): array {
                $value['catalogVersion'] = 2;
                return $value;
            },
            'identity.owner' => static function (array $value): array {
                $value['owner'] = 'application';
                return $value;
            },
            'identity.catalogHash' => static function (array $value): array {
                $value['catalogHash'] = str_repeat('0', 64);
                return $value;
            },
            'top-level omission' => static function (array $value): array {
                unset($value['catalogHash']);
                return $value;
            },
            'top-level addition' => static function (array $value): array {
                $value['unexpected'] = true;
                return $value;
            },
            'events omission' => static function (array $value): array {
                array_pop($value['events']);
                return $value;
            },
            'events addition' => static function (array $value): array {
                $value['events'][] = $value['events'][0];
                return $value;
            },
            'events duplicate' => static function (array $value): array {
                $value['events'][1] = $value['events'][0];
                return $value;
            },
            'events order' => static function (array $value): array {
                [$value['events'][0], $value['events'][1]] = [$value['events'][1], $value['events'][0]];
                return $value;
            },
            'event.type' => static function (array $value): array {
                $value['events'][0]['type'] = 'outbox.delivery.changed';
                return $value;
            },
            'event.version' => static function (array $value): array {
                $value['events'][0]['version'] = 2;
                return $value;
            },
            'event.evidenceVersion' => static function (array $value): array {
                $value['events'][0]['evidenceVersion'] = 2;
                return $value;
            },
            'event.category' => static function (array $value): array {
                $value['events'][0]['category'] = 'replay';
                return $value;
            },
            'event.actionTypes' => static function (array $value): array {
                $value['events'][0]['actionTypes'] = ['outbox.delivery.changed'];
                return $value;
            },
            'event.semanticTuples' => static function (array $value): array {
                $value['events'][0]['semanticTuples'] = [];
                return $value;
            },
            'event.evidenceFields' => static function (array $value): array {
                $value['events'][0]['evidenceFields'] = [['name' => 'unexpected']];
                return $value;
            },
            'event.maxEvidenceBytes' => static function (array $value): array {
                $value['events'][0]['maxEvidenceBytes'] = 14;
                return $value;
            },
            'event.delivery' => static function (array $value): array {
                $value['events'][0]['delivery'] = 'required';
                return $value;
            },
            'tuple.actionType' => static function (array $value): array {
                $value['events'][0]['semanticTuples'][0]['actionType'] = 'outbox.delivery.changed';
                return $value;
            },
            'tuple.phase' => static function (array $value): array {
                $value['events'][0]['semanticTuples'][0]['phase'] = 'failed';
                return $value;
            },
            'tuple.resourceType' => static function (array $value): array {
                $value['events'][0]['semanticTuples'][0]['resourceType'] = 'operation';
                return $value;
            },
            'tuple.outcomeStatus' => static function (array $value): array {
                $value['events'][0]['semanticTuples'][0]['outcomeStatus'] = 'failed';
                return $value;
            },
            'tuple.outcomeCode' => static function (array $value): array {
                $value['events'][0]['semanticTuples'][0]['outcomeCode'] = 'changed';
                return $value;
            },
            'tuple.policyDecision' => static function (array $value): array {
                $value['events'][0]['semanticTuples'][0]['policyDecision'] = 'deny';
                return $value;
            },
            'tuple.policyReasonCode' => static function (array $value): array {
                $value['events'][0]['semanticTuples'][0]['policyReasonCode'] = 'changed';
                return $value;
            },
        ];
        foreach ($mutations as $label => $mutation) {
            $mutated = $mutation($expected);
            self::assertNotSame($expected, $mutated, $label . ' mutation was vacuous');
            self::assertFalse(
                $this->isExpectedOperationalCatalog($mutated, $expected),
                $label . ' mutation was accepted by the exact PHP oracle',
            );
        }
        $this->assertAcceptedExact30Preserved();
    }

    /** @return list<array{0:string,1:string,2:string,3:string,4:string,5:string,6:string,7:string}> */
    private function operationalEventRows(): array
    {
        return [
            [
                'outbox.delivery.dead_letter_retry_requested',
                'outbox',
                'requested',
                'outbox_record',
                'unknown',
                'outbox_retry_requested',
                'not_applicable',
                'outbox_retry_requested',
            ],
            [
                'outbox.delivery.dead_lettered',
                'outbox',
                'failed',
                'outbox_record',
                'failed',
                'outbox_dead_lettered',
                'not_applicable',
                'outbox_dead_lettered',
            ],
            [
                'outbox.delivery.retry_scheduled',
                'outbox',
                'committed',
                'outbox_record',
                'unknown',
                'outbox_retry_scheduled',
                'not_applicable',
                'outbox_retry_scheduled',
            ],
            [
                'replay.observer.failed',
                'replay',
                'failed',
                'lifecycle_journal',
                'failed',
                'replay_failed',
                'not_applicable',
                'replay_failed',
            ],
            [
                'replay.observer.progressed',
                'replay',
                'committed',
                'lifecycle_journal',
                'unknown',
                'replay_progressed',
                'not_applicable',
                'replay_progressed',
            ],
            [
                'replay.observer.started',
                'replay',
                'started',
                'lifecycle_journal',
                'unknown',
                'replay_started',
                'not_applicable',
                'replay_started',
            ],
            [
                'replay.observer.succeeded',
                'replay',
                'succeeded',
                'lifecycle_journal',
                'succeeded',
                'replay_succeeded',
                'not_applicable',
                'replay_succeeded',
            ],
            [
                'retention.hold.placed',
                'retention',
                'committed',
                'operation',
                'succeeded',
                'retention_hold_placed',
                'not_applicable',
                'retention_hold_placed',
            ],
            [
                'retention.hold.released',
                'retention',
                'committed',
                'operation',
                'succeeded',
                'retention_hold_released',
                'not_applicable',
                'retention_hold_released',
            ],
            [
                'retention.purge.committed',
                'retention',
                'committed',
                'operation_data',
                'succeeded',
                'retention_purged',
                'not_applicable',
                'retention_purged',
            ],
            [
                'rotation.storage.failed',
                'rotation',
                'failed',
                'protected_storage',
                'failed',
                'rotation_failed',
                'not_applicable',
                'rotation_failed',
            ],
            [
                'rotation.storage.progressed',
                'rotation',
                'committed',
                'protected_storage',
                'unknown',
                'rotation_progressed',
                'not_applicable',
                'rotation_progressed',
            ],
            [
                'rotation.storage.started',
                'rotation',
                'started',
                'protected_storage',
                'unknown',
                'rotation_started',
                'not_applicable',
                'rotation_started',
            ],
            [
                'rotation.storage.succeeded',
                'rotation',
                'succeeded',
                'protected_storage',
                'succeeded',
                'rotation_succeeded',
                'not_applicable',
                'rotation_succeeded',
            ],
        ];
    }

    /** @param array{0:string,1:string,2:string,3:string,4:string,5:string,6:string,7:string} $row @return array<string,mixed> */
    private function expectedOperationalEvent(array $row): array
    {
        return [
            'type' => $row[0],
            'version' => 1,
            'evidenceVersion' => 1,
            'category' => $row[1],
            'actionTypes' => [$row[0]],
            'semanticTuples' => [[
                'actionType' => $row[0],
                'phase' => $row[2],
                'resourceType' => $row[3],
                'outcomeStatus' => $row[4],
                'outcomeCode' => $row[5],
                'policyDecision' => $row[6],
                'policyReasonCode' => $row[7],
            ]],
            'evidenceFields' => [],
            'maxEvidenceBytes' => 13,
            'delivery' => 'durable',
        ];
    }

    /** @return array<string,mixed> */
    private function expectedOperationalCatalog(): array
    {
        return [
            'schemaVersion' => 1,
            'kind' => 'audit_event_catalog',
            'catalogId' => 'blackops.framework.operations',
            'catalogVersion' => 1,
            'owner' => 'framework',
            'events' => array_map($this->expectedOperationalEvent(...), $this->operationalEventRows()),
            'catalogHash' => '9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b',
        ];
    }

    private function isExpectedOperationalCatalog(array $candidate, array $expected): bool
    {
        return $candidate === $expected;
    }

    private function assertAcceptedExact30Preserved(): void
    {
        $providerPath = __DIR__ . '/../../../../src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php';
        $fixturePath = __DIR__ . '/../../../Fixtures/Audit/framework-audit-event-catalog-v1.json';
        $goldenPath = __DIR__ . '/../../../Fixtures/Audit/audit-event-catalog-golden.mjs';
        self::assertSame('e54b6cc894768ce92f2b9b8770e24dc29344fe9f919dbcf11f568c3e8be31865', hash_file(
            'sha256',
            $providerPath,
        ));
        self::assertSame('ec019ed4cb76008d45e3829adbaaecc888c72cc74b08e0fd9e52974d14fe7c7d', hash_file(
            'sha256',
            $fixturePath,
        ));
        self::assertSame('a390df5c65954d9d918ac232ce34de29fa398e75fff9fb089f6be097fe2147c3', hash_file(
            'sha256',
            $goldenPath,
        ));
        $catalog = FrameworkAuditEventCatalog::catalog();
        $jcs = new AuditJcsCodec();
        $codec = new AuditEventCatalogCodec($jcs);
        $unsigned = $jcs->canonicalJson($catalog->unsignedArray());
        $complete = $jcs->canonicalJson($catalog->toArray());
        $preimage =
            's:'
            . strlen('blackops.audit.catalog.v1')
            . ':blackops.audit.catalog.v1b:'
            . strlen($unsigned)
            . ':'
            . $unsigned;
        $raw = $codec->encode($catalog);
        self::assertSame('cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83', $catalog->catalogHash);
        self::assertSame(13878, strlen($unsigned));
        self::assertSame(13916, strlen($preimage));
        self::assertSame(13959, strlen($complete));
        self::assertSame(13960, strlen($raw));
        self::assertSame($complete . "\n", $raw);
        $fixture = file_get_contents($fixturePath);
        self::assertIsString($fixture);
        self::assertSame($raw, $fixture);
        self::assertSame($catalog, FrameworkAuditEventCatalog::catalog());
    }
}

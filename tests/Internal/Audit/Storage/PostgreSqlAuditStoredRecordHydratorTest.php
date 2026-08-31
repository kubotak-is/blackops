<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Storage;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use PHPUnit\Framework\TestCase;

final class PostgreSqlAuditStoredRecordHydratorTest extends TestCase
{
    public function testHydratesSystemRowIntoTheDetachedStoredRecordShape(): void
    {
        $row = $this->row();
        $stored = $this->hydrator()->hydrate($row);

        self::assertInstanceOf(AuditStoredRecord::class, $stored);
        self::assertSame(
            [
                'schemaVersion' => 1,
                'kind' => 'audit_stored_record',
                'recordId' => '018f0a00-0000-7000-8000-000000000001',
                'partitionId' => 'system:global',
                'tenantBinding' => null,
                'segmentId' => '018f0a00-0000-7000-8000-000000000002',
                'sequence' => 1,
                'previousRecordHash' => null,
                'contentHash' => str_repeat('a', 64),
                'recordHash' => str_repeat('b', 64),
                'recordedAt' => '2026-08-29T12:34:56.123456Z',
                'eventCategory' => 'business',
                'protectedBody' => "BOPD\0" . str_repeat('p', 48),
            ],
            $stored->toArray(),
        );
    }

    public function testHydratesTenantPairAndCanonicalDecimalStrings(): void
    {
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $row = $this->row();
        $row['partition_id'] = $this->deriver()->derive($tenant);
        $row['tenant_type'] = $tenant->type;
        $row['tenant_id'] = $tenant->id;
        $row['schema_version'] = '1';
        $row['sequence'] = '1';

        $stored = $this->hydrator()->hydrate($row);
        $value = $stored->toArray();

        self::assertSame(['type' => 'account', 'id' => 'tenant-a'], $value['tenantBinding']);
        self::assertSame(1, $value['schemaVersion']);
        self::assertSame(1, $value['sequence']);
    }

    public function testNormalizesAResourceByteaAndPreservesBinaryNul(): void
    {
        $body = "BOPD\0" . str_repeat('q', 48);
        $resource = fopen('php://memory', 'rb+');
        self::assertIsResource($resource);
        fwrite($resource, $body);
        rewind($resource);
        $row = $this->row();
        $row['protected_body'] = $resource;

        $stored = $this->hydrator()->hydrate($row);

        self::assertSame($body, $stored->protectedBody());
        rewind($resource);
        self::assertSame($body, PostgreSqlBytea::string($resource));
        fclose($resource);
    }

    public function testRejectsMissingExtraListAndWrongScalarPhysicalExact14Shapes(): void
    {
        $row = $this->row();

        $missingSchemaVersion = $row;
        unset($missingSchemaVersion['schema_version']);
        $this->assertIntegrityInvalid($missingSchemaVersion);
        $missingKind = $row;
        unset($missingKind['kind']);
        $this->assertIntegrityInvalid($missingKind);
        $missingRecordId = $row;
        unset($missingRecordId['record_id']);
        $this->assertIntegrityInvalid($missingRecordId);
        $missingPartitionId = $row;
        unset($missingPartitionId['partition_id']);
        $this->assertIntegrityInvalid($missingPartitionId);
        $missingTenantType = $row;
        unset($missingTenantType['tenant_type']);
        $this->assertIntegrityInvalid($missingTenantType);
        $missingTenantId = $row;
        unset($missingTenantId['tenant_id']);
        $this->assertIntegrityInvalid($missingTenantId);
        $missingSegmentId = $row;
        unset($missingSegmentId['segment_id']);
        $this->assertIntegrityInvalid($missingSegmentId);
        $missingSequence = $row;
        unset($missingSequence['sequence']);
        $this->assertIntegrityInvalid($missingSequence);
        $missingPreviousRecordHash = $row;
        unset($missingPreviousRecordHash['previous_record_hash']);
        $this->assertIntegrityInvalid($missingPreviousRecordHash);
        $missingContentHash = $row;
        unset($missingContentHash['content_hash']);
        $this->assertIntegrityInvalid($missingContentHash);
        $missingRecordHash = $row;
        unset($missingRecordHash['record_hash']);
        $this->assertIntegrityInvalid($missingRecordHash);
        $missingRecordedAt = $row;
        unset($missingRecordedAt['recorded_at']);
        $this->assertIntegrityInvalid($missingRecordedAt);
        $missingEventCategory = $row;
        unset($missingEventCategory['event_category']);
        $this->assertIntegrityInvalid($missingEventCategory);
        $missingProtectedBody = $row;
        unset($missingProtectedBody['protected_body']);
        $this->assertIntegrityInvalid($missingProtectedBody);

        $extra = [...$row, 'unexpected' => 'physical-extra-canary-5a1c'];
        $this->assertIntegrityInvalid($extra, 'physical-extra-canary-5a1c');
        $actionIndex = [...$row, 'action_type' => 'physical-action-index-canary-6b2d'];
        $this->assertIntegrityInvalid($actionIndex, 'physical-action-index-canary-6b2d');
        $resourceTypeIndex = [...$row, 'resource_type' => 'physical-resource-type-index-canary-7c3e'];
        $this->assertIntegrityInvalid($resourceTypeIndex, 'physical-resource-type-index-canary-7c3e');
        $resourceIdIndex = [...$row, 'resource_id' => 'physical-resource-id-index-canary-8d4f'];
        $this->assertIntegrityInvalid($resourceIdIndex, 'physical-resource-id-index-canary-8d4f');
        $this->assertIntegrityInvalid(array_values($row));

        $wrongKind = $row;
        $wrongKind['kind'] = ['physical-kind-canary-9e5a'];
        $this->assertIntegrityInvalid($wrongKind, 'physical-kind-canary-9e5a');
        $wrongRecordId = $row;
        $wrongRecordId['record_id'] = 42;
        $this->assertIntegrityInvalid($wrongRecordId);
        $wrongTenantType = $row;
        $wrongTenantType['tenant_type'] = ['physical-tenant-type-canary-af6b'];
        $this->assertIntegrityInvalid($wrongTenantType, 'physical-tenant-type-canary-af6b');
        $wrongBody = $row;
        $wrongBody['protected_body'] = new \stdClass();
        $this->assertIntegrityInvalid($wrongBody);

        self::assertSame($row['protected_body'], $this->hydrator()->hydrate($row)->protectedBody());
    }

    public function testRejectsEveryPhysicalExact14FieldMutationWithoutPartialHydration(): void
    {
        $canary = 'physical-exact14-field-canary-b0c7';
        $row = $this->row();

        $schemaVersion = $row;
        $schemaVersion['schema_version'] = $canary;
        $this->assertIntegrityInvalid($schemaVersion, $canary);
        $kind = $row;
        $kind['kind'] = $canary;
        $this->assertIntegrityInvalid($kind, $canary);
        $recordId = $row;
        $recordId['record_id'] = $canary;
        $this->assertIntegrityInvalid($recordId, $canary);
        $partitionId = $row;
        $partitionId['partition_id'] = $canary;
        $this->assertIntegrityInvalid($partitionId, $canary);
        $tenantType = $row;
        $tenantType['tenant_type'] = $canary;
        $this->assertIntegrityInvalid($tenantType, $canary);
        $tenantId = $row;
        $tenantId['tenant_id'] = $canary;
        $this->assertIntegrityInvalid($tenantId, $canary);
        $segmentId = $row;
        $segmentId['segment_id'] = $canary;
        $this->assertIntegrityInvalid($segmentId, $canary);
        $sequence = $row;
        $sequence['sequence'] = $canary;
        $this->assertIntegrityInvalid($sequence, $canary);
        $previousRecordHash = $row;
        $previousRecordHash['previous_record_hash'] = $canary;
        $this->assertIntegrityInvalid($previousRecordHash, $canary);
        $contentHash = $row;
        $contentHash['content_hash'] = $canary;
        $this->assertIntegrityInvalid($contentHash, $canary);
        $recordHash = $row;
        $recordHash['record_hash'] = $canary;
        $this->assertIntegrityInvalid($recordHash, $canary);
        $recordedAt = $row;
        $recordedAt['recorded_at'] = $canary;
        $this->assertIntegrityInvalid($recordedAt, $canary);
        $eventCategory = $row;
        $eventCategory['event_category'] = $canary;
        $this->assertIntegrityInvalid($eventCategory, $canary);
        $protectedBody = $row;
        $protectedBody['protected_body'] = "BOPD\0" . $canary;
        $this->assertIntegrityInvalid($protectedBody, $canary);

        self::assertSame($row['protected_body'], $this->hydrator()->hydrate($row)->protectedBody());
    }

    public function testRejectsInvalidDecimalTimestampTenantPartitionAndBodyRepresentations(): void
    {
        $row = $this->row();

        $schemaVersionLeadingZero = $row;
        $schemaVersionLeadingZero['schema_version'] = '01';
        $this->assertIntegrityInvalid($schemaVersionLeadingZero);
        $sequenceLeadingZero = $row;
        $sequenceLeadingZero['sequence'] = '01';
        $this->assertIntegrityInvalid($sequenceLeadingZero);
        $sequencePlus = $row;
        $sequencePlus['sequence'] = '+1';
        $this->assertIntegrityInvalid($sequencePlus);
        $sequenceSpace = $row;
        $sequenceSpace['sequence'] = ' 1';
        $this->assertIntegrityInvalid($sequenceSpace);
        $sequenceFloatString = $row;
        $sequenceFloatString['sequence'] = '1.0';
        $this->assertIntegrityInvalid($sequenceFloatString);
        $sequenceFloat = $row;
        $sequenceFloat['sequence'] = 1.5;
        $this->assertIntegrityInvalid($sequenceFloat);
        $sequenceNegative = $row;
        $sequenceNegative['sequence'] = '-1';
        $this->assertIntegrityInvalid($sequenceNegative);
        $sequenceOverflow = $row;
        $sequenceOverflow['sequence'] = '9007199254740992';
        $this->assertIntegrityInvalid($sequenceOverflow);

        $offsetTimestamp = $row;
        $offsetTimestamp['recorded_at'] = '2026-08-29T12:34:56.123456+00:00';
        $this->assertIntegrityInvalid($offsetTimestamp);
        $shortFractionTimestamp = $row;
        $shortFractionTimestamp['recorded_at'] = '2026-08-29T12:34:56.12345Z';
        $this->assertIntegrityInvalid($shortFractionTimestamp);
        $infinityTimestamp = $row;
        $infinityTimestamp['recorded_at'] = 'infinity';
        $this->assertIntegrityInvalid($infinityTimestamp);

        $partialTenantType = $row;
        $partialTenantType['tenant_type'] = 'account';
        $this->assertIntegrityInvalid($partialTenantType);
        $partialTenantId = $row;
        $partialTenantId['tenant_id'] = 'tenant-a';
        $this->assertIntegrityInvalid($partialTenantId);
        $tenant = new AuditTypedReference('account', 'tenant-a');
        $tenantRow = $row;
        $tenantRow['partition_id'] = $this->deriver()->derive($tenant);
        $tenantRow['tenant_type'] = $tenant->type;
        $tenantRow['tenant_id'] = $tenant->id;
        $tenantTypeSubstitution = $tenantRow;
        $tenantTypeSubstitution['tenant_type'] = 'account-other';
        $this->assertIntegrityInvalid($tenantTypeSubstitution);
        $tenantIdSubstitution = $tenantRow;
        $tenantIdSubstitution['tenant_id'] = 'tenant-b';
        $this->assertIntegrityInvalid($tenantIdSubstitution);
        $partitionSubstitution = $tenantRow;
        $partitionSubstitution['partition_id'] = 'tenant:' . str_repeat('a', 64);
        $this->assertIntegrityInvalid($partitionSubstitution);

        $shortBody = $row;
        $shortBody['protected_body'] = 'BOPD';
        $this->assertIntegrityInvalid($shortBody);
        $wrongMagicBody = $row;
        $wrongMagicBody['protected_body'] = 'NOPE' . str_repeat('x', 49);
        $this->assertIntegrityInvalid($wrongMagicBody);
        $capPlusOneBody = $row;
        $capPlusOneBody['protected_body'] = "BOPD\0" . str_repeat('x', 1_048_752);
        $this->assertIntegrityInvalid($capPlusOneBody);

        self::assertSame($row['protected_body'], $this->hydrator()->hydrate($row)->protectedBody());
    }

    public function testRejectsBadBinaryEnvelopeAndDoesNotExposeTheRow(): void
    {
        $row = $this->row();
        $row['protected_body'] = 'plain' . str_repeat('x', 48);

        try {
            $this->hydrator()->hydrate($row);
            self::fail('bad binary envelope accepted');
        } catch (\Throwable $exception) {
            self::assertInstanceOf(AuditStoreException::class, $exception);
            self::assertSame('integrity_invalid', $exception->code());
            self::assertSame('Audit store operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        $short = $this->row();
        $short['protected_body'] = 'BOPD';
        $this->assertIntegrityInvalid($short);
    }

    public function testRejectsAnInvalidRowWithoutExposingItsCanaryOnAnyExceptionSurface(): void
    {
        $canary = 'hydrator-invalid-body-canary-8f75b4d1e9c2';
        $row = $this->row();
        $row['protected_body'] = 'plain-' . $canary . str_repeat('x', 64);

        $this->assertIntegrityInvalid($row, $canary);
    }

    /** @param array<array-key,mixed> $row */
    private function assertIntegrityInvalid(
        #[\SensitiveParameter]
        array $row,
        #[\SensitiveParameter]
        ?string $canary = null,
    ): void {
        try {
            $this->hydrator()->hydrate($row);
        } catch (\Throwable $exception) {
            self::assertInstanceOf(AuditStoreException::class, $exception);
            self::assertSame('integrity_invalid', $exception->code());
            self::assertSame('Audit store operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());

            if ($canary !== null) {
                ob_start();
                var_dump($exception);
                $debug = ob_get_clean();
                self::assertIsString($debug);
                $surfaces = [
                    $exception->getMessage(),
                    (string) $exception,
                    $exception->getPrevious()?->getMessage() ?? '',
                    $exception->getTraceAsString(),
                    var_export($exception->getTrace(), true),
                    $debug,
                    json_encode($exception, JSON_THROW_ON_ERROR),
                    var_export($exception, true),
                ];
                try {
                    $surfaces[] = serialize($exception);
                } catch (\Throwable $serializationFailure) {
                    $surfaces[] = $serializationFailure->getMessage();
                }
                foreach ($surfaces as $surface)
                    self::assertStringNotContainsString($canary, (string) $surface);
            }

            return;
        }

        self::fail('Malformed physical exact14 row was accepted.');
    }

    /** @return array<string,mixed> */
    private function row(): array
    {
        return [
            'schema_version' => 1,
            'kind' => 'audit_stored_record',
            'record_id' => '018f0a00-0000-7000-8000-000000000001',
            'partition_id' => 'system:global',
            'tenant_type' => null,
            'tenant_id' => null,
            'segment_id' => '018f0a00-0000-7000-8000-000000000002',
            'sequence' => 1,
            'previous_record_hash' => null,
            'content_hash' => str_repeat('a', 64),
            'record_hash' => str_repeat('b', 64),
            'recorded_at' => '2026-08-29T12:34:56.123456Z',
            'event_category' => 'business',
            'protected_body' => "BOPD\0" . str_repeat('p', 48),
        ];
    }

    private function hydrator(): PostgreSqlAuditStoredRecordHydrator
    {
        return new PostgreSqlAuditStoredRecordHydrator($this->deriver());
    }

    private function deriver(): AuditPartitionIdDeriver
    {
        return new AuditPartitionIdDeriver(new AuditJcsCodec());
    }
}

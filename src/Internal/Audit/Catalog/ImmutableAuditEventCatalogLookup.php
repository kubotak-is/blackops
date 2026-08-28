<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Catalog;

use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventCatalogLookup;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\AuditJcsCodec;

/**
 * @mago-expect lint:block-statement
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ImmutableAuditEventCatalogLookup implements AuditEventCatalogLookup
{
    /** @var array<string,AuditEventCatalog> */
    private array $catalogs = [];

    /** @var array<string,string> */
    private array $bytes = [];

    /** @var array<string,string> */
    private array $hashes = [];

    /** @param list<AuditEventCatalog> $catalogs */
    public function __construct(
        array $catalogs,
        private readonly AuditJcsCodec $jcs = new AuditJcsCodec(),
    ) {
        $this->assertShape($catalogs);
        /** @var list<AuditEventCatalog> $catalogs */
        $this->assertSemantics($catalogs);

        /** @var list<array{catalog:AuditEventCatalog,unsigned:string,bytes:string}> $prepared */
        $prepared = [];
        foreach ($catalogs as $catalog) {
            $canonical = $this->canonicalBytes($catalog);
            $prepared[] = [
                'catalog' => $catalog,
                'unsigned' => $canonical['unsigned'],
                'bytes' => $canonical['complete'],
            ];
        }
        foreach ($prepared as $entry) {
            $catalog = $entry['catalog'];
            if ($this->hash($entry['unsigned']) !== $catalog->catalogHash)
                throw new AuditValidationException(AuditValidationFailure::HashMismatch);
        }
        /** @var array<string,string> $hashBytes */
        $hashBytes = [];
        foreach ($prepared as $entry) {
            $catalog = $entry['catalog'];
            $hash = $catalog->catalogHash;
            if (array_key_exists($hash, $hashBytes) && $hashBytes[$hash] !== $entry['bytes'])
                throw new AuditValidationException(AuditValidationFailure::HashMismatch);
            $hashBytes[$hash] = $entry['bytes'];
        }
        foreach ($prepared as $entry) {
            $catalog = $entry['catalog'];
            $key = $this->identityKey($catalog);
            if (array_key_exists($key, $this->catalogs))
                continue;
            $this->catalogs[$key] = $catalog;
            $this->bytes[$key] = $entry['bytes'];
            $this->hashes[$catalog->catalogHash] = $entry['bytes'];
        }
    }

    public function find(string $owner, string $catalogId, int $catalogVersion, string $catalogHash): ?AuditEventCatalog
    {
        $key = $owner . '|' . $catalogId . '|' . $catalogVersion;
        $catalog = $this->catalogs[$key] ?? null;
        if ($catalog === null || $catalog->catalogHash !== $catalogHash)
            return null;
        return $catalog;
    }

    public function resolve(string $catalogHash): ?AuditEventCatalog
    {
        foreach ($this->catalogs as $catalog)
            if ($catalog->catalogHash === $catalogHash)
                return $catalog;
        return null;
    }

    /** @param array<mixed> $catalogs */
    private function assertShape(array $catalogs): void
    {
        if (!array_is_list($catalogs))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        foreach ($catalogs as $catalog)
            if (!$catalog instanceof AuditEventCatalog)
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
    }

    /** @param list<AuditEventCatalog> $catalogs */
    private function assertSemantics(array $catalogs): void
    {
        /** @var array<string,string> $identities */
        $identities = [];
        $codec = new AuditEventCatalogCodec($this->jcs);
        foreach ($catalogs as $catalog) {
            if ($catalog->owner === AuditSourceOwner::Framework && $catalog !== FrameworkAuditEventCatalog::catalog())
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            if ($catalog->owner === AuditSourceOwner::Application)
                $codec->assertTrustedOwner($catalog, AuditSourceOwner::Application);
            $key = $this->identityKey($catalog);
            $complete = $this->jcs->canonicalJson($catalog->toArray()) . "\n";
            if (array_key_exists($key, $identities) && $identities[$key] !== $complete)
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            $identities[$key] = $complete;
        }
    }

    /** @return array{unsigned:string,complete:string} */
    private function canonicalBytes(AuditEventCatalog $catalog): array
    {
        $unsigned = $this->jcs->canonicalJson($catalog->unsignedArray());
        if (strlen($unsigned) > AuditEventCatalogCodec::UNSIGNED_MAX_BYTES)
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        $complete = $this->jcs->canonicalJson($catalog->toArray());
        if (
            strlen($complete) > AuditEventCatalogCodec::COMPLETE_MAX_BYTES
            || (strlen($complete) + 1) > AuditEventCatalogCodec::RAW_MAX_BYTES
        )
            throw new AuditValidationException(AuditValidationFailure::JcsBoundInvalid);
        return ['unsigned' => $unsigned, 'complete' => $complete . "\n"];
    }

    private function hash(string $unsigned): string
    {
        return hash(
            'sha256',
            's:'
            . strlen('blackops.audit.catalog.v1')
            . ':blackops.audit.catalog.v1'
            . 'b:'
            . strlen($unsigned)
            . ':'
            . $unsigned,
        );
    }

    private function identityKey(AuditEventCatalog $catalog): string
    {
        return $catalog->owner->value . '|' . $catalog->catalogId . '|' . $catalog->catalogVersion;
    }
}

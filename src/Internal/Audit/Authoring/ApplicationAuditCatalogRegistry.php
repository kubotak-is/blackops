<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authoring;

use BlackOps\Audit\AuditCatalogDefinition;
use BlackOps\Audit\AuditCatalogProvider;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\Catalog\AuditEventCatalogCodec;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;

final readonly class ApplicationAuditCatalogRegistry
{
    private ImmutableAuditEventCatalogLookup $catalogs;

    public function __construct(#[\SensitiveParameter] ?AuditCatalogProvider $provider)
    {
        try {
            $this->catalogs = $this->assemble($provider);
        } catch (AuditValidationException $failure) {
            throw new AuditValidationException($failure->failure());
        } catch (\Throwable) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
    }

    public function lookup(): ImmutableAuditEventCatalogLookup
    {
        return $this->catalogs;
    }

    private function assemble(#[\SensitiveParameter] ?AuditCatalogProvider $provider): ImmutableAuditEventCatalogLookup
    {
        $definitions = [];

        if ($provider !== null) {
            $definitions = $provider->catalogs();
            if (!array_is_list($definitions)) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
            if (count($definitions) > 32) {
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            }
            foreach ($definitions as $definition) {
                if (!$definition instanceof AuditCatalogDefinition) {
                    throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
                }
            }
        }

        $framework = FrameworkAuditEventCatalog::catalog();
        $operational = FrameworkOperationalAuditEventCatalog::catalog();
        $codec = new AuditEventCatalogCodec();
        $catalogs = [$framework, $operational];
        foreach ($definitions as $definition) {
            if (
                $definition->catalogId === $framework->catalogId
                || $definition->catalogId === $operational->catalogId
            ) {
                throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
            }

            $this->assertNoReferences($definition->events);
            $catalogs[] = $codec->catalog(
                $definition->catalogId,
                $definition->catalogVersion,
                AuditSourceOwner::Application,
                $definition->events,
            );
        }

        /** @var list<AuditEventCatalog> $catalogs */
        return new ImmutableAuditEventCatalogLookup($catalogs);
    }

    /** @param list<AuditEventDefinition> $events */
    private function assertNoReferences(#[\SensitiveParameter] array $events): void
    {
        foreach ($events as $key => $event) {
            if (\ReflectionReference::fromArrayElement($events, $key) !== null) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }

            $this->assertArrayHasNoReferences($event->actionTypes);
            $this->assertArrayHasNoReferences($event->semanticTuples);
            $this->assertArrayHasNoReferences($event->evidenceFields);
        }
    }

    /** @param array<array-key,mixed> $values */
    private function assertArrayHasNoReferences(#[\SensitiveParameter] array $values): void
    {
        foreach ($values as $key => $_value) {
            if (\ReflectionReference::fromArrayElement($values, $key) !== null) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
        }
    }
}

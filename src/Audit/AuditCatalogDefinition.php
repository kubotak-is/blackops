<?php

declare(strict_types=1);

namespace BlackOps\Audit;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;

#[PublicApi]
final readonly class AuditCatalogDefinition
{
    /** @param list<AuditEventDefinition> $events */
    public function __construct(
        #[\SensitiveParameter]
        public string $catalogId,
        public int $catalogVersion,
        #[\SensitiveParameter]
        public array $events,
    ) {
        if (!array_is_list($events)) {
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        }

        foreach ($events as $event) {
            if (!$event instanceof AuditEventDefinition) {
                throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
            }
        }

        AuditValidationException::assertGrammarString($catalogId, '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', 128);

        if (
            $catalogVersion < 1
            || $catalogVersion > AuditValidationException::MAX_SAFE_INTEGER
            || count($events) < 1
            || count($events) > 256
        ) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }
    }
}

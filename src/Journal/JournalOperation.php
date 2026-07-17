<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use InvalidArgumentException;

#[PublicApi]
final readonly class JournalOperation
{
    public function __construct(
        public OperationId $id,
        public string $type,
        public int $schemaVersion,
        public string $strategy,
        public CorrelationId $correlationId,
        public ?CausationId $causationId = null,
        public ?ActorContext $actorContext = null,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $type)) {
            throw new InvalidArgumentException('Journal operation requires a valid type identifier.');
        }
        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Journal operation schema version must be positive.');
        }
        if (!preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $strategy)) {
            throw new InvalidArgumentException('Journal operation requires a valid strategy name.');
        }
    }
}

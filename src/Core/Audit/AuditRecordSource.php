<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

final readonly class AuditRecordSource
{
    public function __construct(
        public AuditSourceOwner $owner,
        public string $component,
        public AuditSourceRuntime $runtime,
        public string $buildId,
    ) {
        AuditValidationException::assertSafeString($component);
        AuditValidationException::assertSafeString($buildId);
    }

    /** @return array{owner:string,component:string,runtime:string,buildId:string} */
    public function toArray(): array
    {
        return [
            'owner' => $this->owner->value,
            'component' => $this->component,
            'runtime' => $this->runtime->value,
            'buildId' => $this->buildId,
        ];
    }
}

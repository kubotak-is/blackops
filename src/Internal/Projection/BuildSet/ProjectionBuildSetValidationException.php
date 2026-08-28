<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

use Throwable;

final class ProjectionBuildSetValidationException extends ProjectionBuildSetException
{
    public readonly ProjectionBuildSetValidationReason $reason;

    public function __construct(
        string $message,
        ProjectionBuildSetValidationReason $reason = ProjectionBuildSetValidationReason::MALFORMED,
        ?Throwable $previous = null,
    ) {
        $this->reason = $reason;
        parent::__construct($message, previous: $previous);
    }
}

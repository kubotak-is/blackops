<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

use RuntimeException;

final class AuditArtifactException extends RuntimeException
{
    private readonly AuditArtifactFailure $failure;

    public function __construct(AuditArtifactFailure $failure)
    {
        $this->failure = $failure;
        parent::__construct('Audit artifact operation failed.', 0, null);
    }

    public function failure(): AuditArtifactFailure
    {
        return $this->failure;
    }
}

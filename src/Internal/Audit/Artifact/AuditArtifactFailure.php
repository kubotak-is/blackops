<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

enum AuditArtifactFailure: string
{
    case ArtifactInvalid = 'artifact_invalid';
    case TrustInvalid = 'trust_invalid';
    case SignatureInvalid = 'signature_invalid';
    case SigningUnavailable = 'signing_unavailable';
}

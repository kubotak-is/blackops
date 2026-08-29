<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\AuditJcsCodec;

final readonly class AuditPartitionIdDeriver
{
    public function __construct(
        private AuditJcsCodec $jcs,
    ) {}

    public function derive(#[\SensitiveParameter] ?AuditTypedReference $tenant): string
    {
        if ($tenant === null) {
            return 'system:global';
        }

        $tenantBytes = $this->jcs->canonicalJson($tenant->toArray());
        $preimage = $this->string('blackops.audit.partition.tenant.v1') . $this->bytes($tenantBytes);
        $partition = 'tenant:' . hash('sha256', $preimage);
        if (strlen($partition) !== 71 || preg_match('/^tenant:[a-f0-9]{64}$/D', $partition) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::SemanticInvalid);
        }

        return $partition;
    }

    private function string(string $value): string
    {
        AuditValidationException::assertUtf8($value);
        return 's:' . strlen($value) . ':' . $value;
    }

    private function bytes(string $value): string
    {
        return 'b:' . strlen($value) . ':' . $value;
    }
}

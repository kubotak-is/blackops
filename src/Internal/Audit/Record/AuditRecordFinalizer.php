<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Record;

use BlackOps\Core\Audit\AuditRecordDraft;

/**
 * Converts one producer draft and one trusted stamp into a detached logical
 * record. The validator remains the sole owner of logical semantics.
 */
final readonly class AuditRecordFinalizer
{
    public function __construct(
        private AuditRecordValidator $validator,
        private AuditRecordCodec $codec,
    ) {}

    public function finalize(
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
        AuditRecordStamp $stamp,
    ): CanonicalAuditLogicalRecord {
        $draftValue = $draft->toArray();
        $logical = [
            'schemaVersion' => $draftValue['schemaVersion'],
            'kind' => $draftValue['kind'],
            'recordId' => $stamp->recordId,
            'event' => $draftValue['event'],
            'action' => $draftValue['action'],
            'actor' => $draftValue['actor'],
            'resource' => $draftValue['resource'],
            'outcome' => $draftValue['outcome'],
            'policy' => $draftValue['policy'],
            'tenant' => $draftValue['tenant'],
            'time' => [
                'occurredAt' => $draftValue['occurredAt'],
                'recordedAt' => $stamp->recordedAt,
            ],
            'source' => $draftValue['source'],
            'correlation' => $draftValue['correlation'],
            'evidence' => $draftValue['evidence'],
        ];
        $validated = $this->validator->validateLogical(new AuditLogicalRecordCandidate($logical));
        $canonical = new CanonicalAuditLogicalRecord($validated);
        $this->codec->assertFutureCompleteCapacity($canonical);
        return $canonical;
    }
}

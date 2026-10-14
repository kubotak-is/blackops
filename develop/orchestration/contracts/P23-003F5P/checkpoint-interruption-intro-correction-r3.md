# Interruption suite introductory rule — finite correction

Status: Root candidate for affected verification review; no Product/runtime issuance.

This supplement overrides only the universal barrier/kill paragraph at section5,
line148 of frozen checkpoint-verification-correction-r2.md (SHA256
86b4fe0391e310ceb4716d6739773550239e4c81e447ee1d7cfe0939b7158e39).
Its row-specific descriptions and companion JSON ffe9d841 remain unchanged.
It addresses only CVR-02R-01 in independent review66df46a0/inputs82e7c9b2.

All nine bases still enter AuthorityFilesystemCase::interruption(case,variant).
The following seven process-interruption bases use the named successful-syscall
barrier: process-control,two-process-lock-death,anonymous-capture-death,
floor-partial-write-death,floor-post-sync-death,archive-link-death and
archive-directory-sync-death. Each control reaches exactly one selected barrier,
receives one release token and completes. Each exercise reaches its one selected
barrier and receives no release; the supervisor SIGKILLs and reaps only that
registered child. Actual terminal signal/state is separate from a method result.
No killed invocation returns a successful Product value or checked Product close.

The remaining two bases,scratch-unlink-error and scratch-rmdir-error,use their
exact row/JSON observable-error plan and managed in-process cleanup. Both variants
have zero selected process-interruption barriers and zero SIGKILL. Control follows
the successful native namespace path; exercise observes the selected -1/EIO,
preserves the original error and proves the distinct managed cleanup operations.
No history holder,result or payload write escapes that failed creation. Ordinary
bounded setup/control-pipe handshakes remain; they are not post-syscall kill barriers.

The interruption suite remains18 runs: seven process bases times two variants=14,
plus two managed-error bases times two variants=4. The complete qualification
remains27 bases/58 runs,with unchanged owners,case IDs,fixture paths,receipt fields,
timeouts,memory,coverage and cleanup obligations. Aggregation rejects a kill/barrier
receipt substituted for a scratch managed-error exercise,or managed-cleanup success
claimed for a killed invocation. It accepts neither unhit/extra interventions nor
fabricated terminal states,and does not require SIGKILL for the two surviving
observable-error exercises. All predecessor,CI,whole-production/DB and cold-loading
requirements remain unchanged. No test or native qualification has run.

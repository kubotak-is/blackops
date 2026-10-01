# P23-006A: Exact historical guard endpoint recovery

Status: Accepted — exact historical recovery only; parent integration remains open
Parent: [P23-006](P23-006-blackops-1-3-release.md)

## Goal and Scope

Make one bounded attempt to recover the two missing D5H-final architecture guard
payloads needed for the reviewed release commit series. The exact target hashes
already exist in the Accepted D5H final-candidate manifest. Hash equality proves
recovery of those historical bytes; a plausible reconstruction is insufficient.
This is integration preparation, not new Product behavior or historical test runs.

Use commit-separation-map.md and integration-preparation-draft.md under
`/tmp/blackops-1-3-release/`. Reuse that completed inventory; do not repeat the
repository-wide history search. D5P must remain separate from Documentation.
No isolated checkout, Git mutation or import is authorized by this child.

## Ownership and exact files

Root owns this Task/Report, inputs, hashes, review/Acceptance, STATE and later Git.
One Luna Max worker may write only these temporary outputs:

- `/tmp/blackops-guard-recovery-20261001/recovered/AuditRecordArchitectureTest.php`
- `/tmp/blackops-guard-recovery-20261001/recovered/AuditDeliveryArchitectureTest.php`
- `/tmp/blackops-guard-recovery-20261001/recover.py` — deterministic local text reconstruction, optional
- `/tmp/blackops-guard-recovery-20261001/handoff.md`

The readonly `inputs/` copies and `inputs.json` were captured by Root from the
verified D5P endpoints in D5R2 originals. Never edit originals, live repository
guards, O2/F1 files, source/tests elsewhere, package files, dependency/configuration,
public Documentation, authority, Reports, STATE or the Git index/refs. No other
agent may write the reconstruction outputs. No subagent or shared resource use.

| File | D5H exact target SHA256 |
| --- | --- |
| AuditRecordArchitectureTest.php | 1c5cb7f2ed3ea3fafb0ad98eea58193f2fcb91f4ce9fadd724dec38e4c7a3665 |
| AuditDeliveryArchitectureTest.php | b13fb4f18e996ee783fcfc9254d199745dd783d6c9c8e0d20870b524a7c00d92 |

Remove only identifiable D5P-owned additions from the retained D5P payloads;
retain earlier authentication/authorization/hold controls and original formatting.
Use the fixed target SHA as the oracle. No arbitrary weakening, hash substitution,
invented historical evidence, search over machine-wide caches or unbounded effort.
Stop after one coherent reconstruction pass and bounded local refinements, at
most30 minutes of active work. Report unmatched targets honestly with candidate
hash/diff and the exact unresolved attribution. Time is not an acceptance signal.

## Verification and Acceptance

Worker may run local text/hash/manifest comparisons and its own deterministic
recovery script. It must not execute PHP/Node/Docker/Mago/PHPUnit/package/DB/cache,
build/browser/generation, Git mutation or network commands. Preserve literal
comparison commands, input/output SHA256, elapsed time and disposition in handoff.
Root re-hashes both input/output payloads and modes and obtains read-only
attribution/preservation review before any later import. A recovered file must
match its target byte-for-byte; no new full suite is run to manufacture historical
receipts. Final integration architecture/package/local/same-SHA gates remain.

- [x] Bounded attempt records both candidate payloads and exact match/non-match.
- [x] Original payloads/live repository/source/package/index are untouched by recovery.
- [x] Root verifies actual hashes/modes; independent review checks attribution
  and that any claimed exact recovery matches the recorded target.
- [x] Report distinguishes exact recovery from unsuccessful preparation, and
  names the next integration decision. No unmatched candidate is imported.

If either target remains unmatched, retain evidence and return to Root for the
already-documented reviewed series fallback. Do not claim that fallback has been
accepted or silently change commit granularity. Parent006 integration remains open.

## Release Documentation Impact

No public API/capability/command/configuration/release/example change. Only two
temporary historical test artifacts and current orchestration documents are
produced; live public Source/Search/LLM, Release Authority and runtime inputs are
unchanged. No child CI/export/publish/deploy. Parent005/006 retains all release
Documentation and delivery obligations; current Stable remains authoritative.

## Remaining Work and Next Action

Recovery and its independent review are complete. Root accepts the two exact
temporary D5H guard payloads; no primary import or historical test requalification.
The parent reviews the grouped integration-unit choice and freezes its exact
isolated manifest before staging.
Remaining parent work includes O2/F1 repairs and every ledger dependency, actual
integration with published Reel, final Owner preview, CI/export and publication.

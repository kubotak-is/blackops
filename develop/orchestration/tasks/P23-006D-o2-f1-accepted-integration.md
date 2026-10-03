# P23-006D: Accepted Replay audit and signed artifact integration

Status: In Progress — source commits and convergence complete; final Review pending
Parent: [P23-006](P23-006-blackops-1-3-release.md)

## Goal and authority

Import the locally Accepted O2/F1 endpoint into the existing isolated release
branch, retaining attribution and preserving the primary mixed checkout.
Parent006 authorizes the required reviewed local commits. Spec109 governs reuse,
[spec121](../../spec/121-observer-replay-audit-adapter.md)/[D171](../../decisions/171-observer-replay-audit-adapter.md)
and [spec122](../../spec/122-audit-signed-artifact-primitives.md)/[D172](../../decisions/172-audit-signed-artifact-primitives.md)
own the Accepted semantics. No Product implementation or release action is added.
Root owns all Git, exact copying, management, checks, STATE and Acceptance.
Independent review covers issuance and final convergence/preservation; a needed
Product repair returns to a separately bounded Luna task.

## Frozen inputs and exact attribution

Destination `/tmp/blackops-integration-20261001/candidate`, branch
`agent/p23-006-accepted-integration`, starts at clean Accepted006C HEAD
`1d4fb9edf65ca957e54d7761278577c5151af2a6`. Read-only preflight
`/tmp/blackops-integration-o2-f1-20261002/preflight.json`, SHA256
`78a530053018b213587ee8d44bb425526208af042254d9b3ac48a97dee9e3b23`,
records3264 files/modes, current clean index, all source preimages and original
primary HEAD/ref/index-stage/index-flags/cached-patch literal equality.

Adopt the immutable plan `3ceb8a3a2fe736be2fae981ea00ed3b0f5354cb313466bd9065ded8bfb9537ad`
and `/tmp/blackops-integration-20261001/o2-f1-import-manifest.json`
`d06a5e79015b7545592714aeb7c892fc3673860f4c7be7dd527ff3fec7481302`.
Use only its exact final52 payloads and embedded8 Accepted management documents.
Rehash bytes/modes and preimages at each copy; do not recopy live primary files.
The four source/package commits, in order, are:

| Unit | Final non-package paths | Actual package state | Dedicated documents |
| --- | ---: | --- | ---: |
| O2 | 23 |115→118/69 controls |4 |
| F1 |27 |118→125/77 controls |4 |
| O2 helper package |0 |125→137/90 controls |0 |
| F1 helper package and control corrections |0 |137→149/103 controls |0 |

These are newly organized commits pairing final Accepted source with actual
package turns. No intermediate is claimed to reproduce a historical full working
tree or to be independently full-qualified. The137 state already contains F1's
original7 types. Never invent an O2-only137 state. The final52 endpoint alone
must converge to the Accepted common inputs. The exact per-unit path lists and
payload locations/hashes/modes in the pinned manifest are exhaustive.

### Exact union of Product/test/package paths

```text
src/Internal/Audit/Artifact/AuditAnchorBinding.php
src/Internal/Audit/Artifact/AuditAnchorReceiptCodec.php
src/Internal/Audit/Artifact/AuditArtifactCanonical.php
src/Internal/Audit/Artifact/AuditArtifactException.php
src/Internal/Audit/Artifact/AuditArtifactFailure.php
src/Internal/Audit/Artifact/AuditArtifactIntegrity.php
src/Internal/Audit/Artifact/AuditArtifactRelation.php
src/Internal/Audit/Artifact/AuditArtifactScalar.php
src/Internal/Audit/Artifact/AuditArtifactShape.php
src/Internal/Audit/Artifact/AuditArtifactSigner.php
src/Internal/Audit/Artifact/AuditArtifactVerifier.php
src/Internal/Audit/Artifact/AuditArtifactWirePreflight.php
src/Internal/Audit/Artifact/AuditCheckpointCodec.php
src/Internal/Audit/Artifact/AuditTrustEntryRelation.php
src/Internal/Audit/Artifact/AuditTrustEntryShape.php
src/Internal/Audit/Artifact/AuditTrustLookup.php
src/Internal/Audit/Artifact/AuditTrustScalar.php
src/Internal/Audit/Artifact/AuditTrustSnapshotCodec.php
src/Internal/Audit/Artifact/AuditTrustSnapshotShape.php
src/Internal/Audit/Replay/ObserverReplayAuditFailure.php
src/Internal/Audit/Replay/ObserverReplayAuditHook.php
src/Internal/Audit/Replay/ObserverReplayAuditHookGuard.php
src/Internal/Audit/Replay/ObserverReplayAuditHookLineage.php
src/Internal/Audit/Replay/ObserverReplayAuditWriter.php
src/Internal/Audit/Replay/ObserverReplayAuditWriterBinding.php
src/Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php
src/Internal/Audit/Replay/ObserverReplayAuditWriterDefinition.php
src/Internal/Audit/Replay/ObserverReplayAuditWriterEmission.php
src/Internal/Audit/Replay/ObserverReplayAuditWriterPolicy.php
src/Internal/Audit/Replay/ObserverReplayAuditWriterRecord.php
src/Internal/Replay/ObserverReplayRuntime.php
src/Transport/PostgreSql/PostgreSqlObserverReplayAdvanceRoot.php
src/Transport/PostgreSql/PostgreSqlObserverReplayBeginRoot.php
src/Transport/PostgreSql/PostgreSqlObserverReplayBeginTransaction.php
src/Transport/PostgreSql/PostgreSqlObserverReplayPhaseHook.php
src/Transport/PostgreSql/PostgreSqlObserverReplayStore.php
tests/Architecture/AuditArtifactArchitectureTest.php
tests/Architecture/AuditDeliveryArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/ObserverReplayAuditArchitectureTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
tests/Fixtures/Audit/audit-signed-artifacts-golden.mjs
tests/Fixtures/Audit/audit-signed-artifacts-v1.json
tests/Internal/Audit/Artifact/AuditAnchorReceiptCodecTest.php
tests/Internal/Audit/Artifact/AuditArtifactSignerTest.php
tests/Internal/Audit/Artifact/AuditArtifactVerifierTest.php
tests/Internal/Audit/Artifact/AuditCheckpointCodecTest.php
tests/Internal/Audit/Artifact/AuditTrustSnapshotCodecTest.php
tests/Internal/Audit/Replay/ObserverReplayAuditHookTest.php
tests/Internal/Audit/Replay/ObserverReplayAuditWriterTest.php
tests/Transport/PostgreSql/PostgreSqlAuditedObserverReplayTest.php
```

### Exact dedicated management paths

```text
develop/orchestration/tasks/P23-003D5O2-observer-replay-audit-adapter.md
develop/orchestration/reports/P23-003D5O2-observer-replay-audit-adapter.md
develop/spec/121-observer-replay-audit-adapter.md
develop/decisions/171-observer-replay-audit-adapter.md
develop/orchestration/tasks/P23-003F1-audit-signed-artifact-primitives.md
develop/orchestration/reports/P23-003F1-audit-signed-artifact-primitives.md
develop/spec/122-audit-signed-artifact-primitives.md
develop/decisions/172-audit-signed-artifact-primitives.md
```

## Shared management and concurrency

In the destination change only manifest-selected D152 and D154 paragraphs,
the four spec-index rows121/122/D171/D172, and minimal current summaries in
`develop/TODO.md`, parent006 Task/Report, this Task/Report and `develop/STATE.md`.
STATE uses its checkout's state.mjs CAS and may add only the exact new archive;
never edit/remove a previous archive. Commit summaries separately from the four
source/package units. A separate final Acceptance management commit is allowed.
Preserve every published Reel row and through-O1 accepted fact. Do not overlay
primary Documentation or import new Outbox/E1 specs123/124,D173/174 or their Tasks.

Primary Root scope is this Task/Report, parent006 Task/Report, the two active
Outbox/E1 Task/Reports for the explicit concurrency amendment, STATE and new CAS
archives. Primary Git/ref/index buffers and all other files/modes are preserved,
allowing only the already-issued31 Product/test/package paths and governance
owned by the two active workers/Root. Root adds exactly this Task and Report to
the Outbox/E1 evidence helper's governance allowlist, retains original bytes and
records the correction. It does not expand Product scope or invalidate their
existing Product evidence. Integration never writes their primary source paths.

Clarification of the preparation plan's future-E1 sentence: this Task imports
only already-Accepted O2/F1. A later separate Task may import reviewed E1 source
and run committed export BEFORE E1 Acceptance, as E1's frozen contract requires.
No circular requirement that E1 be Accepted before its mandatory export is added.

## Verification and preservation

1. At issuance/copy/closeout verify current destination HEAD/branch/clean/index,
   all exact preimages and immutable payload hashes/modes. Capture each changed
   file, expected staged paths, resulting commit paths and linear parent chain.
   Reject an unexpected path, stale input or concurrent destination writer.
2. Final convergence: exact52 payloads, embedded8 documents, all other destination
   files/modes from preflight unchanged except declared management/new archives.
   Compare all applicable frozen PHP source/test/config/bootstrap/Composer inputs
   with the Accepted O2/F1 repository/runtime maps. Record any legitimate published
   website/tool difference explicitly; do not describe it as PHP input equality.
3. Reuse the matching Accepted common full3756/7934098,285MB,512M unchanged,
   receiptdd66f9d2, logdf57011a, JUnitaece3a17, finalreview12f0d637 and
   Acceptancef7b55475. Retain inherited issue aggregates1/2/13 separately from
   failures. Reuse corresponding static/format/Node/package results only with
   matching selected actual inputs. Committing bytes does not require a new full.
4. Run both final script syntax checks and the inventory script once in the
   isolated destination because its layout changed; confirm149 ordered unique
   paths,89 inventory+14 call controls=103 and exact final export pin. This is
   inventory proof, not a clean committed framework-package-export result.
5. Preserve primary literal HEAD/symbolic-ref/index-stage/index-flags/cached patch,
   including intent-to-add; compare current before/after buffers. Preserve the
  12 unrelated primary0755/destination0644 mode-only differences,32 published
   Reel non-summary files, Release Authority and every existing archive.
6. Run destination/primary diff checks, STATE checks and changed management link
   validation. Record literal argv/cwd/environment/start/elapsed/exit/log, exact
   reused receipts and input differences. No DB/Consumer/cache lease is consumed;
   primary Outbox/E1 execution remains governed by its separate Root lease.
7. Independent final reviewer checks raw convergence,4-turn attribution, exact
   commit path sets/chain, resource/preservation and evidence. Root adopts Green,
   records Acceptance/STATE, commits the final checkpoint and proves clean HEAD.

No PHP full, DB, remote fetch/push/CI/export/deploy is prescribed here. A relevant
source/environment difference requires a bounded amendment or matching affected
gate, never an unsupported reuse claim. Temporary scripts/receipts are owned by
Root under `/tmp/blackops-integration-o2-f1-20261002`.

## Release Documentation Impact

This is integration of Accepted internal capability prerequisites, not a public
release or API activation. Release tuple is preserved verbatim from the isolated
base. The published Reel source/assets/tooling remain identical. All public
Source/Search/LLM artifact changes, final same-SHA CI/export and Documentation
delivery belong to parent006/005; no new Product/public claim is introduced.

## Acceptance Criteria

- [x] Independent issuance Green; exact inputs/preimages and ownership fixed.
- [x] Four source/package commits retain exact attribution and final52 convergence.
- [ ] Eight dedicated documents and minimal shared summaries retain boundaries.
- [x] Input-qualified reuse,149/103 isolated inventory and syntax checks recorded.
- [ ] Primary buffers/unrelated modes, published Reel/release/archive preservation pass.
- [ ] Independent final Review Green; Root Report/STATE and clean committed endpoint.

## Remaining Issues and Next Action

Four exact source/package turns and source convergence are complete.
Management validation and independent final Acceptance remain pending.
Parent release remains active; Outbox/E1 and later mandatory dependencies are
separate. Next Action: validate the management boundary and obtain independent final Review.

Issued by Root at 2026-10-02T17:58:46+09:00.

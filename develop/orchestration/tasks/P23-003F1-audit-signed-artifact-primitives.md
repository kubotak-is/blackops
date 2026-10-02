# P23-003F1: Audit Signed Artifact Primitives

Status: Accepted (local) — frozen common candidate; BlackOps1.3 unreleased

## Goal and Authority

Provide pure canonical checkpoint/anchor-receipt codecs, fixed-domain Ed25519 signing and supplied-trust verification, without activation or persistence.

Accepted A/B/C are semantic prerequisites. Ordinary D/E are not prerequisites; Accepted O1 is the chosen common verification baseline only. Follow spec105/106/109 and D152.
[Most-specific specification](../../spec/122-audit-signed-artifact-primitives.md);
[Decision](../../decisions/172-audit-signed-artifact-primitives.md).

## Exact child scope

Luna Max owns only these 29 corrected paths:

- `src/Internal/Audit/Artifact/AuditArtifactFailure.php`
- `src/Internal/Audit/Artifact/AuditArtifactException.php`
- `src/Internal/Audit/Artifact/AuditCheckpointCodec.php`
- `src/Internal/Audit/Artifact/AuditAnchorReceiptCodec.php`
- `src/Internal/Audit/Artifact/AuditTrustSnapshotCodec.php`
- `src/Internal/Audit/Artifact/AuditArtifactSigner.php`
- `src/Internal/Audit/Artifact/AuditArtifactVerifier.php`
- `src/Internal/Audit/Artifact/AuditAnchorBinding.php`
- `src/Internal/Audit/Artifact/AuditArtifactCanonical.php`
- `src/Internal/Audit/Artifact/AuditArtifactIntegrity.php`
- `src/Internal/Audit/Artifact/AuditArtifactRelation.php`
- `src/Internal/Audit/Artifact/AuditArtifactScalar.php`
- `src/Internal/Audit/Artifact/AuditArtifactShape.php`
- `src/Internal/Audit/Artifact/AuditArtifactWirePreflight.php`
- `src/Internal/Audit/Artifact/AuditTrustEntryRelation.php`
- `src/Internal/Audit/Artifact/AuditTrustEntryShape.php`
- `src/Internal/Audit/Artifact/AuditTrustLookup.php`
- `src/Internal/Audit/Artifact/AuditTrustScalar.php`
- `src/Internal/Audit/Artifact/AuditTrustSnapshotShape.php`
- `tests/Internal/Audit/Artifact/AuditCheckpointCodecTest.php`
- `tests/Internal/Audit/Artifact/AuditAnchorReceiptCodecTest.php`
- `tests/Internal/Audit/Artifact/AuditTrustSnapshotCodecTest.php`
- `tests/Internal/Audit/Artifact/AuditArtifactSignerTest.php`
- `tests/Internal/Audit/Artifact/AuditArtifactVerifierTest.php`
- `tests/Architecture/AuditArtifactArchitectureTest.php`
- `tests/Fixtures/Audit/audit-signed-artifacts-v1.json`
- `tests/Fixtures/Audit/audit-signed-artifacts-golden.mjs`
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/framework-package-required-inventory.sh`

No other source/test/helper/config/dependency/schema/CLI/Documentation edits.
Return evidence to Root before any scope expansion.

## Explicit sibling manifest

[P23-003D5O2-observer-replay-audit-adapter](P23-003D5O2-observer-replay-audit-adapter.md) owns only:

- `src/Transport/PostgreSql/PostgreSqlObserverReplayStore.php`
- `src/Internal/Replay/ObserverReplayRuntime.php`
- `src/Transport/PostgreSql/PostgreSqlObserverReplayPhaseHook.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditHook.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditWriter.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditFailure.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditHookGuard.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditHookLineage.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditWriterBinding.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditWriterEmission.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditWriterPolicy.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditWriterDefinition.php`
- `src/Internal/Audit/Replay/ObserverReplayAuditWriterRecord.php`
- `src/Transport/PostgreSql/PostgreSqlObserverReplayAdvanceRoot.php`
- `src/Transport/PostgreSql/PostgreSqlObserverReplayBeginRoot.php`
- `src/Transport/PostgreSql/PostgreSqlObserverReplayBeginTransaction.php`
- `tests/Internal/Audit/Replay/ObserverReplayAuditHookTest.php`
- `tests/Internal/Audit/Replay/ObserverReplayAuditWriterTest.php`
- `tests/Transport/PostgreSql/PostgreSqlAuditedObserverReplayTest.php`
- `tests/Architecture/ObserverReplayAuditArchitectureTest.php`
- `tests/Architecture/AuditRecordArchitectureTest.php`
- `tests/Architecture/AuditDeliveryArchitectureTest.php`
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/framework-package-required-inventory.sh`

## Explicit correction amendment (2026-10-01)

The initial attempt is preserved, including all28 candidate payloads/modes and
six original authority files: `initial-attempt-preserved/receipt.json` SHA256
ce95205a7517d5e759e24751306b765200ef3b405b70f14802ff46fa8d62d49f.
Initial failed commands and source reviews remain evidence; they are not erased
by this correction. The original O1 baseline and all3668 predecessor identities
remain the common full's lower bound.

The corrected exact scopes are O2 25 paths and F1 29 paths,52 unique paths.
Each adds twelve ordinary one-class-per-file helpers; no multiple Product
declarations per file, public DTO, new dependency, dynamic dispatch, callback
extension, metric suppression, baseline or threshold change is authorized.
The old13/17/28 counts below describe initial issuance only. Corrected helper
surfaces and dependency ownership are in the most-specific specifications.
All new helpers count every declared method, including any constructor, against
unchanged quality rules. Design budgets are not measured gate results.

Root retains the actual current125-path/77-control package endpoint captured by
`f1-package-handoff/receipt.json` SHA256
668f3f3b09b75aa5fd568fa66fbc4e55cc162ad2a0a14659b7e43c28d7c9e6a2.
The77 controls are63 inventory and14 call-contract controls. Correction starts
from those complete bytes, not the historical O2-only118-path endpoint.
O2 exclusively owns the next package turn: preserve all125 ordered paths and77
controls, append its twelve helpers as one fixed ordered group, add each omission
and one reordered-group negative, update the exact export pin, then freeze both
payloads/modes/hashes and hand them to Root. Only after Root's explicit transfer
does F1 own the scripts and append its twelve-helper group and equivalent controls.
Expected arithmetic is125/77 ->137/90 ->149/103 (paths/total controls), ending
at89 inventory plus14 call controls. Actual inspected/executed counts, ordering
and immutable intermediate payloads are required; estimates never replace them.

O2 remains sole owner of AuditRecordArchitectureTest, including the explicit F1
integration repair. The only Artifact A2 reference allowed is
AuditRecordTime::assertTimestamp in AuditArtifactShape::{checkpointUnsigned,
anchorUnsigned}, AuditTrustSnapshotShape::snapshot,
AuditTrustEntryRelation::assert and AuditTrustLookup::assertSignedAt.
No other A2 type, Artifact path or method is authorized. F1's own resolved-name
guard and O2's positive/negative A2 controls enforce these exact five call owners.
The old three-codec allowance in temporary proposals is superseded.

Root owns both Reports and all gates, DB/cache/generation, final common input/case
freeze, STATE and Acceptance. Workers may write only their exact corrected scope
after explicit assignment. F1 may prepare a replacement generator at the separately
assigned temporary path; Root alone reviews/runs it to replace literal goldens.
The full remains one final unchanged512M common run after both meaningful
correction matrices and independent source review are ready. Preserve all new
child cases as separate disjoint subsets and all3668 predecessor cases. No other
implementation is silently admitted to the common candidate.

Root also owns P23-006A-guard-endpoint-recovery Task/Report as parallel management
scope. Its single worker writes only four explicit temporary recovery outputs;
no primary Product/test/package/Documentation or shared resource is changed.
These two governance files are not extra inputs to either Product scope or a
claim of integration qualification; all52 primary candidate paths stay fixed.
Root also owns P23-006B-integration-package-boundary Task/Report as governance.
That worker constructs only the four named temporary outputs from immutable
D5H package inputs; live package ownership and all52 candidate paths stay fixed.

Root also owns P23-006C-accepted-audit-integration Task/Report as parallel
governance. Root may create and commit the separately issued isolated worktree
from immutable through-O1 payloads; primary HEAD/index, all52 candidate files,
public Source/artifacts and Release Authority remain preserved. Its local shell
inventory uses isolated temporary fixtures; no shared DB/cache/generation gate
runs concurrently. This introduces no new primary Product scope or full waiver.

## Corrected proof ownership (2026-10-01)

Root adopts source review `f1-source-review-r2.md`, SHA256
c96df691c3bc843b7b2f6c067eaaaf4df8ec79a4c61bdcf685d19dfc050a8deb.
R1/R2/R7/R8 close at captured inputs; R3/R4/R5/R6 and bounded P3 R9 remain.
No new file/type/dependency or Acceptance waiver. To complete independent proof
work without shared-file writes, Root assigns the following disjoint subsets
of the existing29 paths to two configured Luna Max workers:

- Existing F1 worker: `src/Internal/Audit/Artifact/AuditArtifactScalar.php`,
  the five listed `tests/Internal/Audit/Artifact/*Test.php` files,
  `tests/Fixtures/Audit/audit-signed-artifacts-golden.mjs`, and
  `tests/Consumer/framework-package-required-inventory.sh` only.
- F1 AST worker: `tests/Architecture/AuditArtifactArchitectureTest.php` only.

The sole Product edit rejects scalar byte lengths outside1..256 before Unicode
validation and reuses AuditJcsCodec::MAX_SAFE_INTEGER in the same helper.
Preserve the other18 Product files. Root retains immutable JSON1a7f7d7e and
exportf54e2179 (0755); neither worker may edit them. O2 alone edits its six proof
files including A2/C3; the F1 AST worker does not change those files or Product.
All shared commands, DB/cache/generation, formatting, Reports/STATE and Acceptance
remain Root-owned. At most four agents run; start the AST worker only when a
slot is available. Return exact call-map impact of the scalar change to Root
and the AST owner; do not make concurrent edits to resolve it.

Existing F1 worker completes PHP B1 derivation for all51 literal record rows,
fixes four unit-oracle errors without weakening exact types/bytes, completes
independent closed-wire/time/math/early-bound/privacy matrices, and preserves
the already-proven compound precedence families. F1 AST worker implements R6's
actual resolved per-owner/per-method/type/call/attribute/private inventory and
control ordering, with the required actual-source mutations and inert positives.
Reuse the installed parser and current helpers; no broad class/namespace waiver
or fixed-source text comparison replaces that executable boundary.

Root also adopts its narrow package finding `f1-package-control-review.md`
SHA256145a0388e571a30979eb7df43446ef19044e91344866fe6749d80fd4dc8f029d.
The149/103 handoffe67f8db7 is preserved but not final-qualified. Repair only the
new helper-order/last-helper-omission controls so they retain valid Bash and the
intended inventory shape before rejection; keep all103 controls and earlier
safeguards. The export bytes and modes stay unchanged. Root verifies and runs
the final package scripts after this exact single-file handoff.

## Joint qualification and preservation

Initial issuance evidence follows; its13/17/28 counts and first package turn
are retained as history. The explicit correction amendment above supersedes
only the corrected scope/API/helper edges and next package ownership.
The two original scopes were issued together after locally Accepted D5O1.
Neither child depends on the other's semantics. Root captured the actual common
baseline at 2026-10-01T01:44:32+09:00, with 3350 Git-visible file payloads,
HEAD 8106c3556248dca1f9719b9d55cf1f647255a422 and original index stage/flags/debug snapshots.
`/tmp/blackops-o2-f1-20261001/baseline.json` SHA256
fc0ac6d8c83422f7ee8bd4f49c709952109046da60f5f6c14357fee7fb7f59df.
`starting-inputs.json` records the 28-path candidate union and preserved
post-O1 runtime/repository/config/dependency input maps; SHA256
2266c5504f199b72a0d1f2cbbc742d6ad36e863084fa458a0fff11c8f5a5bc16.
The retained predecessor JUnit has all 3668 Accepted O1 identities, SHA256
4bd0ec57016d66c92c59aa32a3f2315d322fcce79c3e4f2ded886f8cb19e91aa; no failed/error/skipped case.
The same baseline retains complete public Source/artifact maps and file modes.
Accepted O1 Store/Runtime/fixture hashes are
913eb5d73583541826725cd325834ee4f82b60f3f7d61ab8df6ef08f07d9fe30,
f3d7705bdccee363d0ccaa6c33bf629de31476f682d90b9be4a55dfdcbca98bd and
34880c0e0bb9955766e1964a02f9724aa72928bb67faf7d7605e6095edbe0d1e.
`package-baseline.json` confirms identical ordered115 paths and the export pin
by source inspection; SHA256 baa48d164d7d0d0a07601ffdc05f1e6cfca3ddffd4fd051a44983baf53a6feda.
This is actual starting evidence, not the future package handoff or final gate.
Both bounded issued-authority reviews are Green; Root authorizes only the
explicitly assigned child scope and its serialized package turn.

The only shared editable paths are the two package scripts. O2 owns them first,
adds exactly its three new Product paths and controls, then explicitly freezes
and hands both complete payloads/hashes to Root. Only then does F1 acquire them
and add its seven paths and controls, preserving O2's endpoint. Baseline115
ordered paths becomes118 after O2 and125 after F1 if the captured input remains
the inspected one; derive actual counts and retain every prior negative control.
No simultaneous ownership, sibling implementation changes or blanket permission
to alter previous Accepted Product is granted. O2's exact native/A2/C3 additions
are explicit exceptions to predecessor preservation. F1 changes no existing
A/B/C source or architecture guard. Both Reports retain package baseline,
O2 handoff and F1 final payloads/hashes for later separate commits.

Root alone owns PHP/Docker/PostgreSQL/cache/Consumer/generation and all test/static
commands. Workers do not run shared gates, stage/commit or write STATE/Reports.
They return per-child handoffs; Root owns both Reports and authority documents,
the bounded dependency/index/TODO updates, parent006 and STATE/CAS archives.
Specialist reviewers are read-only. At most four agents are active including Root.

Run child-specific focused feedback and independent source/proof review, then
freeze one common candidate/runtime/config/dependency/repository manifest. The
single final full at unchanged phpunit.xml512M must retain every actual Accepted
O1 predecessor case, including all O1 additions, plus the exact unique/disjoint
O2 and F1 new case subsets. The physical common JUnit and each own subset are
recorded in both Reports. The full command is:

```bash
docker compose run --rm -T app php vendor/bin/phpunit --display-all-issues --log-junit .phpunit.cache/o2-f1-full-junit.xml
```

All child-specific real native/C3, mathematical Node, privacy, mutation and
package controls remain mandatory. Shared static/package/format receipts count
for both only for identical selected inputs; do not rerun a successful gate for
a different reviewer or a management-only edit. Changed input, failure or missing
evidence requires the affected check and common full identity to be reassessed.
No worker edits frozen inputs during final commands. Cleanup must preserve all
pre-existing containers/resources and remove only owned temporary resources.

This joint dirty-tree qualification is not an isolated intermediate-commit
qualification. Later separate implementation commits form a reviewed series;
parent006 still owns clean committed same-SHA CI/export/release/delivery.
Neither child is locally Accepted until its own findings and all selected gates
are Green. No memory override, new baseline or metric waiver is authorized.

## Acceptance Criteria

- [x] Root adopts raw32-byte seed, no-LF1048575-byte cap and externally trusted snapshot/time; issued authority/common scope independently Green.
- [x] Corrected19-type/29-path authority independently Green and explicitly assigned.
- [x] Exact nineteen Internal types/29 paths, fixed wire/domain/hash/signature/trust/time contracts, early parser bounds and deterministic bytes are implemented without outside caller.
- [x] Literal PHP/Node goldens include real B1 record-derived vectors; all specified positive/negative/compound failure cases execute, with six named Node tests and exact row/case inventory.
- [x] Owned secret buffers/parameters and fresh bounded failures protect private input; finite public/static/dependency/source guard inventory and mutation controls pass.
- [x] Serialized original/separate-helper package additions preserve every O2 handoff and all prior controls; current thresholds, all child-specific gates, common full and independent final review pass.
- [x] Mixed work/public artifacts/Release Authority are preserved; Root Report/STATE distinguish pure prerequisite Acceptance from parent F and release completion.

## Verification Plan

Change class: transaction/tenant/security for O2 and crypto/security for F1.
The unchanged512M common full and independent specialist review are mandatory.
Each command retains literal argv, owner, environment/input hashes, timestamps,
monotonic elapsed time, exit and physical log/JUnit. No implicit gate waiver.

The exact iteration/final commands and meaningful matrix are in spec122
Sections9-11. This Task adopts them, with the common full JUnit filename above.
Root selects the actual container/tool/Node environment, confirms existing sodium
and freezes it without installing new dependencies. F1 adds no PostgreSQL schema,
migration or new installation Consumer. Its independent Node six-test matrix and
literal fixture rows remain mandatory even though PHP joins the common full.

## Release Documentation Impact

Published authority is the unchanged Experimental Stable1.2.1 tuple in parent006;
roadmap1.3.0 remains unreleased. Development audit-trail internals change only;
no new public API, command, config or activated capability is shipped here.
No public Source/route/old-version occurrence/historical allowlist/Search/LLM
change belongs to this child. Root compares all public maps and records exact
results. Existing source/artifact checks are preserved, not rerun for these
unchanged bytes. Parent005/006 owns integration-facing guide and migration work,
all-public release checks, same-SHA CI/export and delivery. No child deployment.

## Remaining Work and Next Action

This bounded child is locally Accepted after all own source/proof findings,
selected gates, exact3756-case common full, preservation/cleanup and independent
final evidence review. Root retains qualified bytes and shared-file endpoints
for the later isolated commit series. Ordinary D integration, E, remaining
F/control/G,004A,005/Owner preview and006 release remain open. Optional003H/004B
are not release prerequisites. Next Action: issue the separately reviewed
Outbox/E1 Tasks on the actual Accepted baseline.

## Focused package verification delegation (2026-10-02)

After Root run003531137788 exposed an AWK parser failure in the new shape
precondition, the F1 functional Luna owner is limited to the inventory script
for its fourth correction. Five unit tests and Node passed and stay frozen.
The worker now owns the isolated temporary-fixture command
`bash tests/Consumer/framework-package-required-inventory.sh` for this correction,
with exact input hashes, elapsed time, result and log handoff. It may run the
needed Bash/AWK probes. Root runs no package command concurrently and reuses a
matching successful receipt. Root retains PHP/Docker/DB/cache/generation/full,
Reports/STATE and Acceptance. Export/JSON/Product/other tests stay frozen.

## R4 remaining functional proof assignment (2026-10-02)

Root adopts bounded review6219abfe. The two package controls, repaired complete
hashes, direct future window and PHP role/domain mathematics are closed.
Luna functional owner now changes only the five already assigned Artifact unit
tests and `tests/Fixtures/Audit/audit-signed-artifacts-golden.mjs`. Inspect the
finite actual owned sensitive-frame/argument matrix, protected safe debug and
caught native SPV serialization refusal without getValue or unowned-frame claims.
Keep reached facade/sign checks and fixed/fresh/no-cause errors. Node must directly
substitute S for B over actual immutable artifact unsigned bytes, preserving six
test identities and all existing mathematical controls. Product19/JSON/both
package scripts are frozen; AST remains another owner's single-file scope.
Root owns format/focused/Node/full and all shared DB/cache resources. This
iteration may overlap Root's O2 tests, whose exact dependencies stay unchanged;
final common freeze requires all owners stopped and exact common inputs.

## Remaining r4 proof assignment (2026-10-02)

Root adopts AST review9c23e762. The AST worker still owns only
`tests/Architecture/AuditArtifactArchitectureTest.php`. Fix single-occurrence
mutation targets without other signature changes, direct executable cleanup and
scalar predicates/Unicode order, and finite actual callback invocation contexts.
Retain exact six architecture identities, literal inventories, parse-valid
changed mutants and inert/alias/named positives. All19 Product/JSON/packages and
functional six-file handoff are frozen. Root owns execution; no broader source
analysis or gate waiver is authorized. Separate R5 closure uses reviewer6219abfe.

## Remaining r5 proof assignment (2026-10-02)

Only `tests/Architecture/AuditArtifactArchitectureTest.php` is editable.
Complete review23a4d959: existing actual role anchor, connected finite scalar
boolean trees with inverted-condition controls, and public/static callback
targets derived from the fixed literal signature inventory across19 types.
All28 other qualified F1 inputs remain frozen; R5 is independently Green.
Root owns all format/execution/DB/cache/gates and final evidence/Acceptance.

## Final qualification checkpoint (2026-10-02)

All assigned source/proof findings are closed by O2 review40041c3e and F1
review5a59d42b with prior R5/package closure retained. Exact common candidate
82683f8a and3756-case union6964f923 are frozen. Root runs the one512M full;
all implementation owners remain idle and may not change frozen inputs.
Current matching-input gate reuse and final affected checks are recorded in
both Reports. Full completion, cleanup/preservation and final independent
evidence Review precede local Acceptance and dependent Product issuance.

## Local Acceptance — 2026-10-02T17:15:49+09:00

Root adopts independent joint final review `12f0d637ba017f88e54b7dbd140e3963c4bddad0d6c62795b0fb78d76788543d`, P1/P2/P3=0.
Full3756/7934098, exact frozen inputs and all predecessor identities, source/proof,
required quality, cleanup and foreign/public preservation are Green.
The Report owns commands, hashes, retained issue aggregates and limits. This
qualification does not assert clean committed export, same-SHA CI or1.3 release.

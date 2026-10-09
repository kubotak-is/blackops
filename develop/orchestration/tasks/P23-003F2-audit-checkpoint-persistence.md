# P23-003F2: Audit Checkpoint Persistence

Status: Accepted locally — implementation, isolated source commit and committed export; remote checkpoint pending; BlackOps 1.3 unreleased

## Goal and authority

Prove a complete persisted closed segment; durably freeze unsigned checkpoint
inputs; persist exactly matching F1-verified complete bytes. Follow AGENTS,
[spec125](../../spec/125-audit-checkpoint-persistence.md),
[D175](../../decisions/175-audit-checkpoint-persistence.md), spec105/106/109 and
[parent006](P23-006-blackops-1-3-release.md). Accepted B2/C3/F1 are prerequisites;
ordinary D/E are not. The Green preparatory r2 contract is preserved.

No signer/seed/provider, complete-byte retrieval/release, seal transition,
runtime registration, ordinary/control authorization wire or exemption is granted.
The implementation must satisfy spec125 P1–P12. Only the exact §§11–15 helpers and corrections are authorized. No Mago waiver, public
API, existing prerequisite Product edit or further scope expansion may be inferred.

## Actual starting boundary

Root captured /tmp/blackops-f2-20261007/baseline.json,
SHA256 1f9f8174c8b4ccd780197667a5124f007cffa38defaf67a5999a800dd42daaec:
3496 Git-visible files with hashes/modes and six literal Git buffers, on primary
agent/p23-001-blackops-1-3-feasibility, HEAD8106c3556248dca1f9719b9d55cf1f647255a422.
Preserve its inherited staged/index flags/intent-to-add, patches and unrelated work.
Implement in the primary only after explicit ownership issuance; do not restore
whole files from HEAD or copy older Documentation into integration.

The clean, pushed Accepted integration endpoint is ecb82889dd3afaeafe39649784501d30733bfb05
on agent/p23-006-accepted-integration at /tmp/blackops-integration-20261001/candidate.
Existing F2 inputs are byte-identical across roots. Preserve the inherited
community-board-clean-install.sh mode difference (primary0755/integration0644).
No F2 writes to the integration checkout before reviewed attribution/Acceptance.

Starting inputs3c2748c8 match all five qualified O3/E1 maps: runtime1774,
repository2286, modes2301, static2755 and prior candidate38. Predecessor cases
18e155f2 derive exactly3833 unique clean identities from actual JUnit13de48d4,
full receipt3aa6a1f9 (512M budget). This is reuse of prerequisite evidence, not a
new F2 run. Keep inherited issue aggregates disclosed and freeze actual new cases
before Final; no test count is predicted or allowed to disappear.

## Exact allowed implementation files (47)

```text
src/Internal/Audit/Checkpoint/AuditCheckpointPreparation.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSchema.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStore.php
src/Internal/Audit/Checkpoint/AuditCheckpointPreparationRowShape.php
src/Internal/Audit/Checkpoint/AuditCheckpointPreparationBody.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentRowParser.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentQueries.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentGraph.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecordProof.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointRows.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPredecessor.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBinding.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTrustWorkflow.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPreparationWorkflow.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransactionPolicy.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointInteger.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentTextFields.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentLifecycle.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditPartitionStateParser.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRelations.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentNeighbors.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentEndpoint.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBytes.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStoredBinding.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSegmentBinding.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecord.php
src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentPages.php
migrations/postgresql/Version20261001000000.php
tests/Internal/Audit/Checkpoint/AuditCheckpointPreparationTest.php
tests/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSchemaTest.php
tests/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReaderTest.php
tests/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStoreTest.php
tests/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStorePostgreSqlTest.php
tests/Architecture/AuditCheckpointArchitectureTest.php
docs/internal/audit-checkpoint-persistence.md
tests/Architecture/AuditStoreArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditArtifactArchitectureTest.php
tests/Internal/Migration/DatabaseMigrationRunnerTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
tests/Consumer/community-board-clean-install.sh
examples/community-board/README.md
tests/Internal/Console/DatabaseMigrationCommandTest.php
tests/Integration/ApplicationConsoleKernelTest.php
```

The first amended scope was23 new/10 existing; scope-v2.json preserves the original
22-file baseline and adds the eleven absent helper paths. The final two tests
in the original22 are an explicit bounded
addition to preparatory20: migration-count expectations only, identified by
current inventory059beabd/e886f0a4. In Runner preserve all older DDL assertions,
append one version/two tables and change only relevant Framework/combined/legacy
counts. In README change only the migration-count sentence, with no capability
claim. No broad numeric replacement or unrelated historical Stable migration edit.

The reserved new migration is Version20261001000000.php, confirmed absent in both
roots and permanent reservations. Current Framework13 + Application5 =18; after
one addition expect14/19. Actual gates must prove fresh install and predecessor
upgrade, exact constraints/indexes/columns, irreversible down and invalid schema.

Package baseline162 ordered unique paths/120 controls (106 inventory+14 call)
and4 runtime omission controls is captured. Add exactly fifteen Product paths and
the migration (arithmetic178), preserve every predecessor control, and add useful
removal/order/new-call controls. Freeze actual names/counts and reviewed export
hash after implementation. The existing export pin f8f5b5d9 and inventory89bd3c50
are not to be bypassed. Unchanged historical version-baseline remains untouched.

Guard changes are exact per-file target/method/count additions, not namespace
waivers. Preserve F1's19 and B2's8 Product types and callable/constructor maps.
B1/A2 gains exactly one RecordProof.record unprotectRecord call; remove the
superseded Reader owner grant. The full B2/F1 map is fixed by spec125 §11; no new protect/finalizer/
CompleteAuditRecord/lookup/provider/append path. B2 and F1 additions are restricted
to spec125's exact owners. No new C3/D-control call or guard amendment.

## Ownership, resources and handoffs

Root owns this Task/Report, spec125/D175, bounded spec index/TODO/parent006 links,
STATE and automatic CAS archives, independent Review, execution and Acceptance.
Only a subsequent explicit Root assignment starts a configured Luna Max worker.
The current disjoint correction lanes are:

- Functional worker: the four entry types, eleven exact helpers and four original
  non-PostgreSql unit tests (19 files). No migration/native/guard edits.
- Proof/support worker: migration, native PostgreSQL test, internal note, three
  count-support tests, three Consumer scripts and public example README (10 files).
- Guard worker: AuditCheckpointArchitectureTest, AuditStoreArchitectureTest,
  AuditRecordArchitectureTest and AuditArtifactArchitectureTest (4 files).

Workers return handoffs to /tmp/blackops-f2-20261007/functional-handoff.md and
proof-handoff.md respectively; Root alone edits the integrated Report. No worker
commit/stage/push, STATE, sibling writes or further subdelegation is authorized.
Root originally assigned functional8/proof14 on 2026-10-07 after issuance Review
7086ddd6311cafde0e567bbf8d017351141ac74307ae868edba1342cdd67e07f:
functional8 to /root/f2_functional and proof14 to /root/f2_proof. Each may edit
only its listed files and temporary handoff. This assignment grants no runtime
or Git lease. New files use0644; existing modes remain unchanged. Freeze/pause
on Root's runtime request, and report concrete scope/complexity conflicts before
expansion. Workers must coordinate readiness through Root, not share file writes.

Correction ownership (2026-10-07, after both initial handoffs): Root preserves
the initial handoffs and reassigns the four architecture files
AuditCheckpointArchitectureTest.php, AuditStoreArchitectureTest.php,
AuditRecordArchitectureTest.php and AuditArtifactArchitectureTest.php to
/root/f2_guard_worker. The proof worker retains the other ten paths, including
the native test, migration, package scripts, count support and Documentation.
Functional retains its eight paths. Corrected handoffs use functional-r2-handoff.md,
proof-r2-handoff.md and guard-handoff.md. All are source-only; Root retains every
runtime resource. Adding required P1–P12 cases within these owned tests is already
authorized and is not a scope expansion. No required family may be deferred.
Measured format/lint/analyze failures and review TX1–TX10 require correction;
Root now adopts replacement review25047d73 and Green parameter addendum143b715e
as spec125 §11. Functional ownership expands from eight to nineteen files, with
all P2/P3/P4/P5/P6/P9/P11 unit families retained; proof10 and guard4 are unchanged.
Pre-write absence8e71513b and revised scope-v2.json record exactly eleven new
paths,0644. The new direct transaction state machine quarantines failures once;
RecordProof is the sole unprotect owner. No automatic split or quality waiver is
issued. This reissues bounded source implementation after the amendment adoption;
all runtime remains Root-owned. Preserve all earlier failed receipts and handoffs.

Root reissues the bounded existing-file correction on2026-10-08 after actual
r3/r4/native-r7 measurements. Functional19 owns only the first validation pass
qualified by review1f2359bf/spec125 §11 plus the single owned-frame privacy fixture
correction53f20fcf. No conditional new helper or further method/caller expansion.
Proof10 owns the two actually failing native fixtures and the four internal-note
findings from Documentation reviewc7344289; unchanged package scripts stay frozen.
The four guard files are assigned to /root/f2_guard_correction for the12 measured
guard failures. Match the approved exact map and meaningful mutation controls;
return any Product/authority mismatch instead of expanding a caller allowance.
All lanes are source-only. Root alone releases freezes and runs the next gates.

Root adopts the next existing-file table pass on2026-10-08 from reviewb07e1c57,
exactly as spec125 §11. /root/f2_functional may change only RowShape,
SegmentRowParser, Graph and Binding for the nine listed private constants/loops
and Graph's inline return. Preserve existing methods/state/edges/integer leaves;
no conditional new type or method is issued. /root/f2_guard_correction owns its
same four tests for the four actually remaining12:30 guard failures and the
exact nine-constant inventory amendment. All other scope33 files stay frozen.
Source-only lanes remain disjoint from Root's serialized runtime.
The corrected StoreTest unit-r5 is frozen after actual1/21 pass. Return new
handoffs functional-table-r5-handoff.md and guard-r4-handoff.md; any mismatch
with authority remains a finding rather than a broadened allowance.

Root adopts spec125 §12/main proposal2fddcc9b after the table-pass measurements.
Current scope-v3 is35 paths:25new/10existing, functional21/proof10/guard4.
Functional gains only PreparationWorkflow/TransactionPolicy and implements the
exact Store/Transaction/Reader changes. Its four original unit tests may receive
only necessary graph/ownership fixture corrections while retaining all cases.
Other functional files stay frozen. Guard updates exact17-type/property/method/
caller maps and three remaining measured failures in its four files. Proof
updates only the two package scripts and internal responsibility note in this
pass; native/migration/count/README inputs remain frozen. Package arithmetic180
and new omission/order controls must be actually proved; existing140 controls
and four runtime omissions remain. No optional RecordProof/Graph/integer helper
is issued. Workers are source-only; Root alone grants runtime after handoff.

Root adopts spec125 §13 / proposalcc5df7f2 after actual r6 results. Current
scope-v4 has47 paths, functional33/proof10/guard4, with29 exact Product types.
Functional may change only the six listed existing validator classes and twelve
new helpers, plus necessary invariant-focused additions/corrections in its four
original unit files. Other Product files remain frozen pending separate authority.
Guard owns its same four files for exact29-type maps and preserved negative
controls. Proof owns package2/internal note only in this pass; native/migration/
count/README stay frozen. Every worker remains source-only and waits for explicit
Root assignment. Runtime/resources, final acceptance and Git remain Root-owned.

Root additionally adopts §14/reviewcd207273 with no scope expansion. After its
§13 handoff, the same functional owner may change only Transaction.abort's
terminal phase branch and Reader.prove's catches exactly as specified. No Policy
or test rewrite is needed by the authority itself. A separate source handoff
records the two hashes; current owner/method/count maps remain unchanged.

Root adopts §15/review29cbb026 with no file/type expansion. The functional
worker changes only StateParser's duplicated head condition and Reader's exact
private identifier helper/call/placement; the annotation-only4416e1fa remains
its completed preceding handoff. Guard changes only the corresponding Reader
callable/S/own-method maps in Checkpoint guard after its d97cf362 corrections.
Other scope47 files remain frozen. Root reviews the literal delta and runs
affected strict quality/guards; unchanged package/docs/prerequisites are reused.

Root alone serializes all Docker/PostgreSQL/cache/migration/Consumer/test jobs.
Use existing Compose app/PostgreSQL18 and installed root dependencies; no root
dependency installation, shared image rebuild or config/512M change. The required
Community clean-install Consumer separately owns its existing random project
images, fixed-lock dependency installs and disposable generated directories.
Its eleven cleanup targets (.env, vendor, var/build, var/log, var/phpunit, frontend/
node_modules, frontend/src/lib/server/blackops/generated, frontend/.svelte-kit,
frontend/build, frontend/test-results and frontend/playwright-report) were all
absent at issuance. Recheck their absence before executing the
destructive clean-install setup; if any exists, preserve it and return to Root
for isolated routing. Do not delete unrelated/user-owned artifacts.
Freeze current image/container identities
before execution, and prove unchanged/clean task-owned resources afterward.
Native F2 fixture owns schema blackops_audit_checkpoint_f2 (and exact
blackops_audit_checkpoint_f2_alt only for schema-identity negatives); it must drop only its schemas
in finally and close all owned Connections. Reuse existing runner/Consumer cleanup
conventions; freeze their actual resource names before their first execution.
Tests cache/JUnit output is .phpunit.cache/f2/; copy immutable logs/receipts under
/tmp/blackops-f2-20261007. Concurrent source edits do not overlap runtime freezes.

## Verification plan

Recovery checkpoint (2026-10-09): the host restart removed /tmp checkouts and
detailed evidence. Committed integration ecb8288 and primary source/JUnit remain.
Root preserves a fresh 3552-file baseline21f9e603 and exact current47 inputs in
`.phpunit.cache/f2-orchestration/20261009/`, an already ignored workspace location.
This is a new recovery baseline, not a claim that lost original manifests were
recreated. Retained predecessor JUnit13de48d4 and current discovery412d946b are
byte-identical to their recorded hashes. Preserve the primary index/modes/buffers.
Future receipts/preimages are kept in that workspace evidence directory.

Root adopts independent proof review80ecf3e3 T1–T6, reproduced in its
`proof-corrections.md`. Source-only correction ownership is temporarily disjoint:
functional worker owns StoreTest alone; proof worker owns ReaderTest and
PreparationTest alone. All29 Product, SchemaTest/native/guards/package/docs are
frozen. Root records handoffs/review and executes every gate. These are previously
required proof controls, with no additional Product/file scope or waiver.

Root adopts delta review12ce10fa PC1–PC5 on2026-10-09. The next source-only pass
is restricted to StoreTest (/root/f2_functional) and ReaderTest (/root/f2_proof).
Store owns canonical map comparisons, exact Product-owned privacy frames/canaries,
independent Proof-to-Preparation first/head binding, neighbor/predecessor ordering,
and branch-specific immutable replay/no-write observations. Reader owns the new
metadata failure privacy frames and closed-neighbor chronology with valid token.
PreparationTest is frozen; its cap fixture proves rejection, not isolated cap
discrimination or decoder order. Qualify that handoff without another source edit.
Retain all earlier controls/positives and mark old duplicate-material rotation
cases accurately. The review's early mixed-segment warning was withdrawn and
authorizes no fixture change. All other scope47 paths remain frozen. Handoffs go
to the durable evidence directory as store-pc-handoff.md and reader-pc-handoff.md.
Root retains runtime, Report/STATE, independent delta closure and Acceptance.

Review0bd3947f closes the new Store controls and requests only SC1/SC2 preservation:
before replacing stored unsigned bytes, retain each candidate-only first/head
rejection; retain public exception trace-string/serialization marker checks beside
the correct owned-frame print/export checks. Root chooses to restore the original
public test method identity while keeping the accurate duplicate-material labels.
After the current Consumer completes, /root/f2_functional may make exactly these
StoreTest-only corrections. Reader/Preparation and all other inputs remain frozen.

Actual affected-unit run on2026-10-09 executed49 cases with48 passes/1 error in
testStoreEntryAcceptsIndependentRotatedTrustAndBindsExistingPreparationRetries,
at its first real prepare call. Root assigns only this case and necessary local
fixture correction in StoreTest to the same worker. Preserve intended unique-key
positive and retry/material negatives; return any Product/authority issue before
expanding. No other previously passing case is to be removed or weakened.

Change class: SQL/transaction/tenant/security/schema plus a public example count.
During implementation Root executes the relevant named unit/native/guard/migration
selection after a worker handoff; no worker launches a competing runtime process.
Freeze literal commands, relevant bytes/config/dependencies/runtime, named cases,
timestamps/elapsed/exit and log/JUnit. Native P1/P7/P8/P10 must actually run;
unit doubles never substitute for concurrency, real commit response loss, snapshot
recovery or migration upgrade. Independent close-token expectations cannot call
the Product reader. Do not regenerate or weaken unchanged F1 mathematical goldens.

Before Final, independent Transaction/Security review checks source and meaningful
proof, then Root freezes exact predecessor3833 + added case identities and inputs.
Full covers the same-condition focused cases; do not run them again merely for
Final or a new reviewer. One final full on fixed bytes is required. Exact command
selection is below; Root may select narrower iteration checks without omitting it.

```bash
docker compose run --rm --no-deps -T app mago format --check src tests migrations/postgresql/Version20261001000000.php
docker compose run --rm --no-deps -T app composer validate --strict
docker compose run --rm --no-deps -T app mago lint src examples/quickstart/app migrations/postgresql/Version20261001000000.php
docker compose run --rm --no-deps -T app mago lint --verify-baseline src examples/quickstart/app migrations/postgresql/Version20261001000000.php
docker compose run --rm --no-deps -T app mago analyze --reporting-format=count src examples/quickstart/app migrations/postgresql/Version20261001000000.php
docker compose run --rm --no-deps -T app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
docker compose run --rm -T app php vendor/bin/phpunit --display-all-issues --cache-directory .phpunit.cache/f2 --log-junit .phpunit.cache/f2/full.xml
bash -n tests/Consumer/framework-package-export.sh
bash -n tests/Consumer/framework-package-required-inventory.sh
bash -n tests/Consumer/community-board-clean-install.sh
bash tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/community-board-clean-install.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO[.]md:[0-9]+' src tests --glob '*.php'
git diff --check
node develop/orchestration/state.mjs --check
```

Actual lint/analyze CLI help was captured under the F2 directory. Positional paths
override source configuration: retain both current roots and explicitly add the
owned migration. This is the whole configured scope plus one path, not a migration-
only strict-baseline check. Formatter similarly covers src/tests plus migration.
Deptrac remains src-only by design. Existing baseline/thresholds are unchanged;
analyze reports all severities rather than suppressing inherited warnings/helps.
No global Mago/CI change. New failures require correction or a concrete reviewed
amendment; no silent waiver, helper or memory increase.

F1 mathematical evidence reuse requires actual input equality. If a dependency,
fixture, environment or failure affects a gate, rerun only the affected required
portion; preserve failed evidence. Management-only edits do not invalidate Product
results. Freeze new-case ownership, source/fixture/caller/package maps and actual
cleanup evidence before Acceptance; no dirty inventory proves committed export.

## Acceptance criteria

- [x] Issuance delta independently Green and exact worker assignments recorded.
- [x] All47-path ownership/primary buffers/unrelated bytes and modes preserved;
  four entry types/twenty-five helpers/two tables/one migration and exact APIs satisfy spec125.
- [x] Complete segment, tenant-before-decrypt, immutable preparation/complete
  bytes, trust, failure ordering and privacy satisfy all P1–P12 cases.
- [x] Native owned isolation, rollback/commit ambiguity/quarantine, races/fresh-root
  recovery and exact fresh/upgrade schema evidence pass without skipped cases.
- [x] Current guards/package preserve predecessor negatives and reject meaningful
  new mutations; no activation, signer, seed, release or seal path appears.
- [x] Assigned quality/Consumer and one exact full pass at unchanged512M with
  all predecessor/new cases and unchanged input maps; cleanup is proved.
- [x] Independent Transaction/Security and narrow Documentation reviews Green;
  Root records local Acceptance and a separately attributable integration commit.
- [x] Report records actual release impact, commands/results, evidence limits,
  remaining1.3 work and Next Action; STATE CAS passes.

Root records local implementation Acceptance on2026-10-09T15:23:41+09:00 after
independent final evidence review2ccd882b. Final3915/8591759 includes native18/524
and all predecessor identities, with inherited diagnostic issues disclosed. The
delivery/record checkboxes close after the actual isolated source commit and
destination checkpoint; they do not reopen accepted Product inputs. Source commit
9768edf47f200cc28666da2d28a1d98adb6bec6d contains exactly51 reviewed paths, and its
clean committed export passed on2026-10-09. Shared checkpoint/push remain.

## Release Documentation Impact

Authority remains Experimental Stable1.2.1 / roadmap1.3.0 unreleased. Framework
tag1.2.1 direct1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3/source4efee09f13bebedc1639333f79550a36a4e8ca91;
Skeleton direct7a47e344ecba6193628119f0e95cb23dc5e0be83/source87a025380df8abcd0c514abf50e139dbbc1953c7.
F2 is internal proposed Audit persistence, not an active Stable capability. Existing
community-board capability's public example count changes; no Capability ID,
command syntax, configuration, Guide or Website route is added/promoted.

The exact public Source change is examples/community-board/README.md migration
count only; docs/internal/audit-checkpoint-persistence.md explains the architecture
and unreleased boundary. Documentation Reviewer checks both against code/spec.
The original202-source/424-artifact mapdfa74805 was lost with /tmp. Fresh available
baselinefd6a7a1c and final preservation71291178 prove148Guide/Website sources and
424current artifacts unchanged; no missing map is reconstructed. Old versions remain in their existing
current/historical contexts; no version replacement or new historical allowlist.
Positive proof: exact14+5=19 statement and actual clean-install. Negative proof:
no signature-release/Stable-promotion claim and no Guide/route/artifact drift.
Website build/browser are NotRun as outside this unchanged Website boundary.
Final all-public release review, Owner local preview, clean committed-SHA export,
same-SHA CI, immutable publication and delivery/live proof remain parent005/006.

## Bounded integration execution annex — 2026-10-09

Root adopts read-only plan669bd5b3 within this existing F2/parent006 authority;
no additional integration child or Product change is required. The initial /tmp
checkout/evidence paths above are historical and were lost during host restart.
The current independent destination is
`.phpunit.cache/f2-orchestration/accepted-integration`, clean at
ecb82889dd3afaeafe39649784501d30733bfb05 on agent/p23-006-accepted-integration.

Source Acceptance and independent evidence closure precede copy/stage. Root will
freeze a payload manifest for exactly the47 implementation paths plus this Task,
its Report, spec125 and D175 (51paths). New paths use0644; existing destination
modes stay unchanged, including Community clean-install0644 versus primary0755.
No primary index mutation, all-tree overlay, inherited mode change, unrelated
Documentation or older Website projection is permitted. Record actual preimages,
payload hashes/modes, staged paths/diff and source commit before export.

After that source commit, Root runs the literal command
`bash tests/Consumer/framework-package-export.sh` in the clean independent clone
and records its exact committed input SHA, environment, result and cleanup. This
is committed export evidence, separate from primary inventory/static/unit results.
Existing installed runtime/image may be reused; no dependency or quality waiver.

A separate management checkpoint may update only the dedicated four documents
above and these shared paths: develop/spec/README.md, develop/TODO.md,
develop/orchestration/tasks/P23-006-blackops-1-3-release.md,
develop/orchestration/reports/P23-006-blackops-1-3-release.md and develop/STATE.md,
plus the exact archive emitted by each real destination STATE CAS. The index
receives only spec125/D175 entries; TODO receives one bounded F2 result; parent006
receives only current F2 status/result/remaining-work/next-action hunks. Preserve all accepted
history and unrelated primary planning. Do not copy these shared files wholesale.

Root checks destination STATE before/after its own CAS, reviews the exact staged
management delta, commits, verifies clean state, pushes the accepted branch and
checks the exact remote SHA. Any follow-up receipt uses the same path boundary.
Never relabel a source-SHA export as a later management-SHA run. Release same-SHA
CI/export, final local Owner preview, immutable publication and delivery/live
verification remain with parent005/006. Task implementation Acceptance is distinct
from those remaining1.3 gates.

## Remaining work and Next Action

The adopted corrections, fixed-input final gates and independent review passed;
Root accepts the implementation locally. The isolated source commit and its
committed export passed; finish the bounded management checkpoint and push. Later signing/
anchor/full verifier, D-control/G and other parent006 dependencies remain.
No F2 or full1.3 completion is implied by this packet.

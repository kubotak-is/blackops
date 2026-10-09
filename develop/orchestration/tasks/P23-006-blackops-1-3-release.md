# P23-006: BlackOps 1.3 Release

Status: In Progress — dependency delivery; final release candidate not frozen

## Goal and Authorization

On2026-09-29 the Owner requested: 「1.3リリースまであと何が必要？これをゴールに続けて」.
Complete the accepted1.3 scope through implementation, independent Review,
Acceptance, integration, same-SHA qualification, Framework/Skeleton publication
and verified Documentation delivery. Necessary isolated commits, pushes, PRs,
merges and release/deployment actions are authorized by that request after their
gates; the old child packets' local-only boundaries describe those completed
children and do not prohibit this separately authorized release work.

The Owner's2026-09-16 final local Documentation preview requirement remains:
prepare the reviewable final site and URL, obtain the Owner's confirmation, then
publish the release. Do not repeatedly ask for general release permission.
Never replace an immutable published tag or bypass a failed gate.

## Authority and Scope

Specifications105/106 define the accepted proposal and dependency graph;
most-specific child specifications/Decisions override only their declared
boundaries. Specifications46/61/104 govern package/publication/documentation;
109 governs verification and management. Current profiles in `.codex` apply.
This parent coordinates the release and does not authorize unbounded Product
edits: each implementation/integration slice requires an exact Task Packet,
file ownership, meaningful verification and independent specialist Review.

Start: branch `agent/p23-001-blackops-1-3-feasibility`, HEAD
`8106c3556248dca1f9719b9d55cf1f647255a422`, empty cached patch with pre-existing
intent-to-add index entries,3335 Git-visible files and
249 existing dirty entries. Baseline `/tmp/blackops-1-3-release/baseline.json`,
SHA256 `e3150bbb5e52254ab859915419fe6cda8670c37ad2da7ba5260accd832ba6e2e`.
Preserve all accepted/unrelated mixed work. Published Reel main `a419f375` is the
delivery authority; the primary's older Reel/Documentation snapshot must be
reconciled deliberately in integration, never copied over the published result.
Keep D5P and Documentation in separate commits and retain child attribution.

Root's2026-10-01 reviewed integration-unit choice groups D2J+D2X and
D3+D4A+D4B+D5H at their available combined endpoints, followed by separate
exact D5P11, R1 3, R2 17 and Accepted O1 3-path units. The original child
attribution remains explicit; lost standalone guard/package endpoints are not
claimed as recovered or independently qualified. D5H's two recovered guards
match their historical hashes and complete its9-path endpoint. The still-missing
D2X package boundary requires explicitly reviewed construction or placement in
the future integration Task. Independent granularity reviewb1fe7a47 is Green;
the original map19b3cdd1 and addendum0ebd1432 retain the evidence. P23-006B now
owns temporary construction of the missing97-path/48-control package boundary
from exact D5H109/60 inputs. It neither changes primary files nor recovers a
historical D2X hash.006B is now Accepted after inventory and independent review;
manifest e323e929ad558c1290cf70724a7905dac12d65eecaf23cbff846942dc514e79c
has all94 actual payload endpoints (92 retained, two new constructions) over81
paths. P23-006C now issues the exact six isolated units, captured management inputs,
per-unit ownership and gates. The primary O2/F1 candidate remains untouched. This choice neither
qualifies a new candidate nor decides O2/F1's later series, and never combines
D5P with public Documentation.

Root owns this Task/Report, STATE/TODO, dependency decisions, integration,
Acceptance, shared PostgreSQL/cache/consumer/generation resources and remote
release actions. Luna Max workers own bounded Product/Test files in child
packets. Astra Max investigation and independent Transaction/Security Review
are read-only. The configured Documentation Reviewer is read-only.

## Dependency Ledger

| Work | Starting status | Required closure |
| --- | --- | --- |
| Discovery, Stable retention preflight, CLI projections/list/diagnostics/status | Accepted P23-001/001R/002A..E | Preserve and qualify in final candidate |
| Audit Catalog, canonical storage, durable delivery and relay | Accepted P23-003A/B/C | Preserve and integrate through accepted contracts |
| Operation reads, authentication, authorization, hold, purge, rotation adapters | D1/D2/D3/D4/D5H/D5P/D5R1/R2 Accepted; several local only | Integrate, retain independent evidence and separate commits |
| Observer replay and generic Outbox adapters | O1/O2/O3 Accepted locally and imported | Native transaction prerequisites, truthful phase events, same-root proof |
| Ordinary Audit composition | Pending D-integration | Enable approved paths, enforce tenant/privacy/failure contracts; close Inline ambiguous-commit reconciliation and rotation overflow policy |
| Application event authoring/projector | E1 Accepted locally; E projector/journey pending | Explicit versioned API, reference business journey, no inferred business events |
| Checkpoint/anchor signing and reusable verification | Pure F1 and F2 persistence Accepted and committed; remaining F pending | Accepted sealed-record inputs, signing/trust/revocation/freshness, crash-safe external anchor |
| Checkpoint/anchor control events | Pending D-control after F | Consume accepted typed outcomes; no recursive audit cycle |
| Query/export/audit-of-audit/retention/hold | Pending G after F/control | Bounded authorized reads/exports, shared verifier, metadata retention/hold |
| HTTP Worker Mode hardening | Pending004A | Startup/readiness/state/memory/restart/isolation/upgrade/rollback and reviewed Classic migration |
| Documentation | Pending005 after implementation | Commands and Audit concepts, migration/reference journeys, all-public Source/Search/LLM and independent browser Review |
| Release qualification and delivery | Pending006 final candidate | Owner local preview, clean committed candidate, local/same-SHA CI/export, publication and canonical production proof |

E foundation, F and004A can proceed independently of ordinary D when exact file/resource
ownership and review capacity permit. G cannot consume unaccepted F/control.
Future physical deletion003H and optional Extension Worker004B are not1.3 release
dependencies; adding them requires a separate explicit scope decision.

## Acceptance Criteria

- [ ] Every mandatory dependency has implementation and independent Acceptance;
  no unresolved release-blocking finding or implicit contract waiver.
- [ ] Mixed work is preserved, child changes are traceable in separate reviewed
  commits, published main changes are integrated, and the release checkout is clean.
- [ ] A frozen candidate passes complete local quality, all applicable Consumer,
  package/export, deterministic Skeleton, migration/upgrade/rollback and runtime
  gates with unchanged required thresholds and the repository512M test budget.
- [ ] All public Source/routes/Search/LLM/artifacts, versions/capabilities and
  negative release fixtures pass independent Documentation Review.
- [ ] The Owner has confirmed the final local Documentation preview URL.
- [ ] Exact-candidate same-SHA CI and required delivery jobs pass before release;
  Framework/Skeleton publication uses verified source/split and immutable tags.
- [ ] GitHub Releases/Packagist and clean remote install/Quickstart succeed;
  independently reviewed publication-closeout authority refs and current claims
  pass same-SHA Documentation delivery and canonical HTTP/artifact verification.
- [ ] Report/STATE identify final refs, evidence, remaining work and Next Action;
  all owned temporary resources are cleaned up.

## Verification Plan

Dependency delivery uses each frozen child plan; do not repeat successful checks
merely for a new reviewer or management edit. Root alone serializes shared gates.
For this initial management/source-boundary inventory, use read/diff/hash checks,
`git diff --check` and `node develop/orchestration/state.mjs --check`; no Product
test is invalidated by a parent planning document.

Before release qualification, freeze the exact command/environment/fixture
manifest in this packet against the final source and current workflows, review it
against spec61/106 and execute it without omissions. It must include Composer,
Mago format/lint/baseline/analyze, PHPUnit/Deptrac, required Consumers, frontend,
all-public website/browser/release guards, committed-SHA export/Skeleton and all
required remote jobs. Current input-matching child results may supply local
evidence only within their recorded selection; same-SHA CI/export and publication
proof remain separate mandatory gates. Candidate changes invalidate the affected
checks and any same-SHA claim. Publication uses the established ordered workflow
from P23-012; do not fabricate a future tag's own direct/peeled refs in its source.

## Release Documentation Impact

Start authority: Experimental Stable1.2.1; Framework direct
`1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3`, source
`4efee09f13bebedc1639333f79550a36a4e8ca91`; Skeleton direct
`7a47e344ecba6193628119f0e95cb23dc5e0be83`, source
`87a025380df8abcd0c514abf50e139dbbc1953c7`; roadmap1.3.0 unreleased.
This parent start changes management only. Later005/006 must enumerate changed
capability IDs, every public Source/route, old-version classification/historical
allowlist and positive/negative Source/Search/LLM fixtures. Do not promote current
Stable until actual publication is independently verified.

## Remaining Work and Next Action

006E completed its separate source commits, required gates, final independent
Review, Root Acceptance, clean checkpoint and normal remote branch preservation.
[F2 persistence](P23-003F2-audit-checkpoint-persistence.md) is Accepted locally,
committed as9768edf4 and qualified by clean committed export. Checkpointcbec640
is pushed with exact remote proof. Settle remaining-F signing-release and trusted
control provenance with exact caller/guard/file ownership. Worker-startup issuance
still requires its separate current inventory and bounded Stable Reference closure.
Ordinary D composition/Inline ambiguity/rotation overflow, E projector/
journey, remaining F/control/G,004A hardening,005 all-public Documentation/Owner
preview and006 final same-SHA publication/delivery remain. Optional003H/004B
stay excluded. The1.3 release goal is still active.

## Accepted O2/F1 import

[006D](../tasks/P23-006D-o2-f1-accepted-integration.md) owns the next four source/package turns, now
committed through`f1c871c482a7c80b884cb285acfe34a1a642c92c`. Exact52 final source and8 dedicated
documents converge, with source-only reuse of the Accepted common full3756.
Final006D Review is Green and Root accepts the bounded integration locally. No later E1/Outbox
source is imported and no intermediate is independently full-qualified.

006D local Acceptance: final independent review`ca29f790` is Green;
Root records the reviewed final checkpoint and retains parent1.3 remaining gates.

## Committed qualification — 2026-10-07T02:39:33+09:00

006E issued review89417398/manifest5b4a8993 produced separate O3
`a17978181b86b24a5300e3413d4c2a6dd1df746f` and E1
`3baf147d419c5148bf1989a2968bcb396ed0f3b5`. Unit receiptsc71c87b0/70e53055
prove exact23/26 paths, linear parents, clean source and primary-buffer preservation.
Source-applicabilityd176ad3a retains1759 repository runtime bytes/1154 static
inputs equal to qualified full0f211f9c, with15 unchanged primary vendor inputs.
The12 inherited unrelated mode differences are explicitly retained at destination
preflight values. No candidate-environment full or all-repository equality claim.

Actual committed-gates.json SHA256
`afa7645df29925f5d046e3c703aba01ec01bd39d46a7385051884f4e90e68da3`
records all five required gates on source3baf147, with literal commands,
before/after inputs, exit codes, duration and raw logs under
`/tmp/blackops-integration-o3-e1-20261006/`. Syntax2, inventory162 ordered unique
paths/120 controls(106+14) plus4 separate runtime omissions, and complete
version-baseline passed on the original clean committed endpoint.

The initial linked-worktree export failed because Composer included the regular
`.git` pointer; failed fe02d35e/logd6c0caa8 and cause731a9fd8 remain unchanged.
Independent review02908bde approves environment-only routing to a detached
independent clone at the same sourceSHA/tree and identical3350 file hashes/modes.
The unchanged export script passed there on2026-10-07: receipt
`4a6df095dc5bb65c3e83b4b32da08f889e856193996a1cebf7e7a46a0d6299dd`,
logf33dfee2, exit0,6.629485s. Git/Composer each have identical1173 regular-file
inventories, both strict Composer validation and production autoload generation
passed, and actual `/tmp/tmp.uB3Fs0ak08` package roots were removed.
App imageab739e1d is unchanged before/after; original source, clone and primary
buffers remain unchanged. No source/exclusion/guard/threshold change or gate waiver.

All scoped Product/proof/quality/committed gates are now Green. Final independent
Review and Root Acceptance/checkpoint remain pending. Stable1.2.1 and public
Source202/artifact424 stay unchanged; there is no new release CI, Documentation
delivery, Production deploy, subtree-split identity or14-type API-smoke claim.
The latter smoke and all-public final Owner preview/publication remain005/006.

## Root local Acceptance — 2026-10-07T02:47:42+09:00

Root read and adopted final independent review
`7b13cc36fa75e8a1a0562a2c71897ce7b25ced9abe7a44c63aee1967c8ee8210`, with no remaining findings.
O3 and E1 are locally Accepted on their exact reviewed source/proof, full and
committed gates;006E's bounded integration is accepted for the clean management
checkpoint. Source38 is unchanged. The final checkpoint commit/clean proof and
normal branch push/remote identity are the remaining execution steps.
The failed linked-worktree export stays Failed; the separately recorded same-SHA
clone export is the successful mandatory gate. No release or CI claim is added.
Parent1.3 still requires D composition/Inline ambiguity/rotation overflow,
E projector/journey, remaining F/control/G,004A,005 all-public Documentation/
Owner preview and006 final same-SHA qualification/publication/delivery.

## Clean checkpoint and remote preservation — 2026-10-07T02:55:15+09:00

The reviewed management/Acceptance commit is
`f01e16b78da19685000ad57cb9a68fb7a04ba283`, parent3baf147. Actual receipt
`acfc060e8e6574c62630bcb60cbb4e648a53d51d5a98909869df2ba9bb632de3`
proves exact19 paths, clean endpoint, unchanged final38 source, all unrelated
files/modes and original primary buffers. STATE/diff checks passed;396 local
management links resolve. Root closes the final clean-checkpoint criterion.

A normal non-force push created
`origin/agent/p23-006-accepted-integration` at that exactSHA. Actual push and
remote `git ls-remote --heads` evidence is retained in checkpoint-pushed.json,
SHA256 `7f90eaf9b155d95dba19115f2e0f1f09e3e054d92d0c3f5c767f0a5d3dfa6044`.
No main update, tag, final CI, release or deployment occurred. This small
management follow-up records that completed checkpoint; its own final commit
and push identity are captured externally, without changing qualified source.

No006E issue remains. Continue the parent1.3 dependency graph from this Accepted
baseline; final Owner local Documentation preview and same-SHA publication/
delivery remain mandatory.

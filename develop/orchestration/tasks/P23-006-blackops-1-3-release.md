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
| Observer replay and generic Outbox adapters | O1/O2 Accepted locally and imported; Outbox pending | Native transaction prerequisites, truthful phase events, same-root proof |
| Ordinary Audit composition | Pending D-integration | Enable approved paths, enforce tenant/privacy/failure contracts; close Inline ambiguous-commit reconciliation and rotation overflow policy |
| Application event authoring/projector | Pending E | Explicit versioned API, reference business journey, no inferred business events |
| Checkpoint/anchor signing and reusable verification | Pure F1 Accepted locally and imported; remaining F pending | Accepted sealed-record inputs, signing/trust/revocation/freshness, crash-safe external anchor |
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

All pending rows above remain. D5O1 is locally Accepted; the bounded replay
adapter and pure F1 packets are issued and their three Luna owners are correcting source and proof.
Both packets explicitly adopt the exact sibling scopes, serialized package-file
ownership, complete union of required test identities and all child-specific
checks for one shared final qualification. Root verifies the package handoff,
then runs focused feedback/source review and the frozen final gates. This does
not amend or waive D5O1's completed standalone full. Corrected authority now
names O2 25 and F1 29 paths (52 unique); unchanged metrics, exact proof corrections
and immutable O2-first helper package handoffs remain required. Generic Outbox,
E1, F2 and Worker startup preparatory authority is Green; F2 issuance follows
F1 Acceptance.006A is locally Accepted exact historical-guard recovery with no
primary Product import. Root adopted the reviewed grouped early integration units
above; actionable manifest8a8a2b34 identifies92 actual payloads among94 unit/path
endpoints. Accepted006B resolves the two gaps in adopted manifeste323e929.
The separate exact isolated
integration Task follows verified payloads and current remote inputs. This parent is complete
only when1.3 is released and its package/site results are externally verified.

## Accepted O2/F1 import

[006D](../tasks/P23-006D-o2-f1-accepted-integration.md) owns the next four source/package turns, now
committed through`f1c871c482a7c80b884cb285acfe34a1a642c92c`. Exact52 final source and8 dedicated
documents converge, with source-only reuse of the Accepted common full3756.
Final006D Review is Green and Root accepts the bounded integration locally. No later E1/Outbox
source is imported and no intermediate is independently full-qualified.

006D local Acceptance: final independent review`ca29f790` is Green;
Root records the reviewed final checkpoint and retains parent1.3 remaining gates.

# Framework Specification

## P23-003C3 current handoff — Audit Delivery Orchestration and Key Composition

Updated At: 2026-09-02T14:19:47+09:00

Current status: `Ready for independent preflight — Product work not started`.

P23-003C1 and P23-003C2 are Accepted.
The reviewed C2 candidate is committed at
`a92bef70fdb843a4dd2b8b34243fc892b43939cc`; Complete Final Gate, fresh
General／DB／Documentation review, and clean exact-SHA Git／Composer package
export are Green. No push was performed.

D150 remains the parent serial C1→C2→C3→C4 split.
D151 remains the Accepted C2 PostgreSQL Outbox persistence authority.
D152 is the most-specific C3 authority. It fixes exact public key-provider
interfaces, Catalog-bound required／durable plan selection, level0／level1
delivery participation, reuse of the existing Framework transaction root,
protected Operation-read release gating, one Application Audit composition,
failure precedence, no-leak surfaces, exact68 package inventory, and zero
runtime activation.

The C3 Task freezes exact33 Product／Test／configuration paths and exact35
worker scope. It preserves every Accepted C1／C2／B1／B2 Production byte.
The committed management packet must be freshly reviewed by separate General／
Transaction／Documentation lanes before configured Luna Product work.

Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased.
The two key-provider interfaces are unreleased proposal API. No current
Application／Container／CLI／HTTP／worker／scheduler／relay registration, public
Documentation claim, migration, route, release, or deploy is activated.

Remaining: C3 preflight／implementation／gates／Acceptance／commit／package
export, then C4 and parent P23-003C Acceptance.
Next Action: obtain fresh independent preflight Green from the committed clean
packet before Product implementation.

## P23-003B2 accepted handoff — Historical prerequisite

Updated At: 2026-08-31T23:10:13+09:00

Current status: `Accepted — P23-003B2 Audit PostgreSQL Canonical Store / Parent P23-003B Accepted; Candidate Commit and Same-SHA Package Export Green`.

P23-003A1／A2 and parent P23-003A are Accepted at candidate commit
`b7e6837f0d684b3cea35967fadb4c52adb670407`; committed-candidate package export
is Green. D148 splits P23-003B into
serial B1 pure canonical-record／Audit-protection foundation and B2 PostgreSQL
append／segment／migration.

D149 is the most specific B2 authority. It fixes same-Connection transaction
participation, exact three-table DDL and migration, trusted Clock／UUID allocation,
required／durable append, same-ID idempotency, explicit crash-safe segment close,
tenant predicate-before-decrypt, finite failure precedence, and the post-Acceptance
clean-candidate package gate.

B1 implements the pure canonical-record／Audit-protection foundation with exact
PHP／Node bytes, protected hydration, bounded pre-key validation, value-free
failure surfaces, and no runtime activation. Historical preflight and review
findings are retained in D148 and the Task/Report; the final bounded closure is
Green `P1=0/P2=0/P3=0`. The Accepted exact33 ordered aggregate is
`bb6ab89a222a72eafa601f588f46177ee443e554c036bcc00575b211961c8847`.
The Complete Final Gate is Green, including full PHPUnit `2,762 tests / 57,406
assertions`, Deptrac `991 analyzed / 0 violations / 0 uncovered / 0 errors /
5,986 allowed`, and all three Audit Node goldens.

Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased. No
runtime、database、route、CLI、or public Documentation activation occurred.

Reviewed Product candidate `c3b75ca0afe6d8c756caa8404b7e95922987c1da`
is committed, and the clean-candidate Git／Composer package export is Green.
No push was performed.

Earlier preflight and review lanes are Historical. The bounded B2 implementation now provides the
PostgreSQL migration, exact three-table schema, same-Connection append／close transaction boundary,
tenant predicate-before-decrypt lookup, package inventory, and mutation-resistant DB／Architecture
evidence without runtime activation. Accepted Product exact26 is
`ecacafc99cbdf4fd2287b7554b88a34f6524a23d92c9adb3a8bf201a2a1d50bb`.
The Complete Final Gate is Green, including PHPUnit `2,939/85,080`, Deptrac
`999/0/0/6,079`, four Audit Node goldens, and the Community Board clean-install journey.
Fresh DB and General Sol closure reviews separately returned `P1=0/P2=0/P3=0`.
P23-003B2 and parent P23-003B are Accepted. Stable `1.2.0` remains current;
BlackOps `1.3` remains unreleased; no Application、Container、CLI、HTTP、public Documentation、
Audit Outbox、relay、checkpoint、or query activation occurred.

Reviewed B2 candidate `968afe2b0c4bbff86e0013592d69dc7115cf8b4f` is committed,
and `framework-package-export.sh` is Green on that clean exact SHA. The management-only
closeout changes no package-visible source or required inventory, so the candidate-SHA package
evidence remains current. No push was performed.

Remaining within B2 or parent B: none. Next Action: proceed to P23-003C from the
Accepted parent P23-003B boundary.

## P23-003A2 accepted handoff — Historical prerequisite

Updated At: 2026-08-29T01:25:47+09:00

Current status: `Accepted — P23-003A2 Audit Record and Observed Projection / Parent P23-003A Accepted`.

P23-003A1 is Accepted at committed candidate
`1799be017811886d1e9f1ff8ba9a8eb18db51c79`; the Git／Composer package export
is Green. D147 remains the most specific current authority. The A2 packet
consumes A1 without changing its exact48 Product freeze.

A2 owns the typed Draft, Catalog-resolved validation, Logical schema validation,
and exact `observed_v1` projection. It fixes `projection_invalid` as the only
projection failure, emits all projection correlation members with null
convergence, projects only present non-null classified evidence strings, uses
an injected A1 opaque-ID deriver, and adds no PublicApi marker.

A2 defines only an Internal logical-record interface. It creates no trusted
record ID/time implementation, integrity, hash, append, runtime adapter, key
provider, public Documentation, route, or database behavior. P23-003B/C retain
trusted finalization and delivery ownership.

Initial fresh Sol Max preflight returned Changes Requested `P1=1/P2=0/P3=0`.
The corrected packet fixes a single `toArray()` capture, detached reference-free
snapshot plus same-resolution definition binding, a non-caller-input Internal
validated result, and a bytes-only Projector entrypoint.
Fresh Sol Max re-preflight is Green `P1=0/P2=0/P3=0`. Luna Max implementation,
the focused Iteration Gate, exact29 Product freeze, and complete Final Gate are
Green. The A1 exact48 freeze remains unchanged.
Fresh implementation review returned Changes Requested `P1=1/P2=2/P3=0`:
evidence validation precedence, required mutation fixtures, and the disconnected
PHP/Node exact-byte golden must be corrected. Review-before Gate evidence is not
reused after Product changes. Luna High corrected all three roots within A2
exact29. Corrected focused PHPUnit is `41/1341`; corrected full PHPUnit is
`2693/14991`; both Node oracles、Mago、Composer、Deptrac `970/970`、version
baseline、Website release source、management guards、ending hashes are Green.
Corrected exact29 aggregate is
`943a4002dbdb97daa159d7e1602fe9b3f740a6c178b10cf45b58945f62c2f85f`;
Accepted A1 exact48 remains byte-identical.
Fresh implementation re-review returned Changes Requested `P1=3/P2=1/P3=0`:
Correlation omission sentinels、pre-shape recursive Logical detachment、D147
Current Remaining／Next Action、and remaining Required Fixture mutation guards.
Round 2 closes those roots without changing A1. Its exact29 aggregate is
`c2ef33a3d3b47ca9a5de189d324308a79da0a356839d36aa944675a7dba2d8eb`;
focused `46/23595`、Node oracles、full `2698/37246`、Mago、Composer、Deptrac
`970/970`、release guards、management guards、and ending hashes are Green.
Fresh Sol Max implementation re-review returned Changes Requested
`P1=0/P2=2/P3=0`; only the explicit-null correlation projection direct fixture
and stale current inventory remained. The inventory is synchronized here;
Round 3 closes the direct fixture and completes the post-change Gate. Its
exact29 Product aggregate is
`65443eb6ec6c738c61100001d8f3f5d2e38a88e63f585052bf6f4c4b9bd29056`;
focused PHPUnit is `46/23599` and full PHPUnit is `2698/37249`. Fresh Sol Max
implementation re-review is requested.
That review returned Changes Requested `P1=1/P2=0/P3=0`: wide keysets can
reach input-count-proportional `array_keys()` or evidence snapshot allocation
before bounded shape rejection. Round 4 is limited to the Validator and its two
direct test files; prior Product Gate evidence is not reused after that change.

Round 4 correction and focused gate are Green. Bounded member counts run before
wide key extraction／snapshot copy, only Catalog-declared evidence fields are
copied, focused PHPUnit is `48/23683`, and exact29 Product aggregate is
`c019ab09c31a99301bfe9fd22004890cf52a21b20cc7b410df74f08b0ef97ae9`.
Accepted A1 exact48 remains unchanged. The Round 4 complete Final Gate is Green:
full PHPUnit `2700/37333`, Mago error0, Deptrac `970/970`, release／Website／
Node／management guards, staged0, and ending hashes all PASS. Fresh-review Green
is `P1=0/P2=0/P3=0`, with exact38／staged0／all frozen aggregates unchanged.
The Orchestrator accepts P23-003A2 and parent P23-003A. No runtime or release
behavior is activated.

Remaining: none within P23-003A2 or parent P23-003A. The higher Goal still
requires P23-003B..G, P23-004, P23-005, and P23-006.

Next Action: begin bounded P23-003B research and Task Packet drafting from the
Accepted A1/A2 freeze; Production waits for a separate preflight Green.

## P23-003A1 accepted handoff — Historical prerequisite

Updated At: 2026-08-28T18:13:03+09:00

Historical status at A1 Acceptance: `P23-003A1 Accepted — P23-003A2 unblocked`.

P23-002E is Accepted. D147 is the most specific current authority for the
unreleased Audit Catalog／safe-projection boundary. It splits P23-003A into
serial A1 Catalog and A2 Record／Projection packets.

A1 owns Catalog-only RFC 8785 JCS／catalogHash、the closed event/semantic/
evidence model、the exact 30-definition Framework security/access Catalog、
purpose-bound keyed opaque IDs、AuditReadRequestBasis、and AuditReadContext v1.
P23-003C owns the later dedicated opaque-key provider/injection. P23-003B alone owns
AuditRecord logical JCS/hash and chain append. A2 owns the record stages and
exact observed safe projection, without runtime activation.

The initial exact-nine preflight is Historical Changes Requested. Its
executable-gate、request-basis、JCS/error/raw-cap、opaque-key、and empty A1
PublicApi inventory findings are corrected in D147 and the Task. The corrected
exact-nine re-preflight is also Historical Changes Requested: general
`P1=1/P2=1/P3=0` and focused `P1=1/P2=0/P3=0`, separately. The shared raw-cap
precedence root and the general typed-reference boundary root are corrected.
The second corrected exact-nine focused lane was Green `P1=0/P2=0/P3=0`;
the separate general lane returned `P1=0/P2=1/P3=0` for missing direct
byte-preservation and tenant opaque-context fixtures. The Task now fixes that
evidence gap. The third corrected exact-nine management／normative set is
frozen and its fresh general closure review is Green `P1=0/P2=0/P3=0`.
Luna Max implementation、focused／complete Final Gate、and fresh Sol Max
implementation review are Green; the Orchestrator accepts P23-003A1. Stable
`1.2.0` remains current and BlackOps `1.3` remains unreleased. Public
Guide／Website／Search／LLM／route／runtime／database are unchanged.

Remaining: P23-003A1内はなし。A2 and the remaining Phase 23 DAG remain.

Historical Next Action: create the P23-003A2 Task Packet and send it to fresh Sol Max
preflight. Do not commit、stage、activate runtime behavior、or broaden into B-G.

## P23-002E accepted handoff — Historical prerequisite

Updated At: 2026-08-28T04:22:03+09:00

Current status: `Accepted — About and Aggregate Outbox Status`.

P23-002D is Accepted. P23-002E now owns the proposed CLI-only `about` and
aggregate-only `outbox:status` slice.

The current exact contract fixes Composer installed-package version authority,
current-process PHP/SAPI metadata, exact ProjectionBuildSet status, host-
operator／installation Outbox scope, one-row PostgreSQL aggregation, exact
seven counts／three ages／three anomaly checks, fixed Human／JCS／stderr／exit
bytes, no public route, and no capability inference or tenant／row／payload／
identity leakage.

Initial Sol Max preflight returned `P1=0/P2=2/P3=0`. The corrected contract
fixes all three minimum aliases to exact UTC microsecond text and narrows the
About discovery description to package／runtime／Projection status. Production
implementation is complete within the Task Packet.

Fresh Sol Max re-preflight matched exact8 aggregate
`7f01fb7feebfc2f9f3ad429de3d01d1cec157a9d12f18dcfff41cc13f962fb65`
and returned Green `P1=0/P2=0/P3=0`. Both component reviews and shared
integration are Green. The current exact37 Product aggregate is
`8a2a57df0cef023e6e84c55a212da3edec14f53ef0b7d168e0fbac9bdc5395a0`.

About and Outbox activate atomically. On completion, target metadata is
exact35, runtime Framework names exact29, visible list exact34, producer exact38,
and existing reservation names remain exact13.

Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased. Public
Guide／Website、generated Artifact、commit、stage、push、CI、deploy、publication、
tag、release remain unchanged or unexecuted.

The complete Final Gate is Green on the same exact37: format、Composer strict、
lint／baseline、analyze errors `0`、PHPUnit `2564/13206`、Deptrac violations `0`、
version baseline、release-source、management-ID、diff-check、staged0、ending
aggregate一致を確認した。Fresh Sol Max implementation review independently
matched exact37 and management exact8 and returned Green `P1=0/P2=0/P3=0`.
The Orchestrator accepts P23-002E; BlackOps `1.3` remains unreleased.

Remaining: none within P23-002E。
Higher-goal work remains P23-003、P23-004、P23-005、P23-006。

Next Action: begin bounded P23-003 Audit research and Sol Max preflight.

## P23-002D accepted handoff — Historical prerequisite

Updated At: 2026-08-28T02:09:28+09:00

Current status: `Accepted — Diagnostics Check and Aggregate Queue Status`.

P23-002C is Accepted. P23-002D now owns the proposed CLI-only
`diagnostics:check` and aggregate-only `queue:status` slice.

The current exact contract fixes explicit Application-owned readiness query
registration, bounded exact-six normalization after Projection lock release,
host-operator／installation Queue scope, one-row PostgreSQL aggregation, exact
counts／ages／bounds, fixed Human／JCS／stderr／exit bytes, no public route, and no
tenant／row／payload／identity leakage.

Luna Max completed Diagnostics、Queue、and shared Application／Registry
integration. Target metadata is exact33, runtime Framework names exact27, and
reservation names remain exact13.

Initial Sol Max preflight was stopped after confirming two P1 architecture
roots. The correction places Queue contracts in Core and permits exactly the
Application／InternalApplication-to-Observability edges needed by the explicit
Builder authority. Fresh Sol Max re-preflight matched exact8 aggregate
`f29f3deb2896cdb067b58927139f7d3c512bdd8ce20674035b5393ce8f674a34`
and returned Green `P1=0/P2=0/P3=0` before implementation. The initial exact33
candidate and review-before exact34 are Historical. Fresh Sol Max review found
only a missing direct age cap+1 regression fixture (`P1=0/P2=1/P3=0`). Luna Max
added that test without Production changes. Review-before exact34 aggregate
`db18b1c18b116a8afd4e2062c9328edf7b05004c8cb7576916c2e07882c4ae64`
passes focused `136/1463` and the complete Final Gate, including full PHPUnit
`2513/11352` and Deptrac `904/0 violations`. Fresh Sol Max closure review
returned `P1=0/P2=1/P3=0`: Production SQL is finding-free, but the direct real
PostgreSQL fixture does not yet prove aggregation across two tenant canaries.
That freeze/Gate is review-before Historical. Luna Max changed only the real
PostgreSQL test to seed two tenant canaries; focused `136/1465` passes. Current
exact34 aggregate is
`6a4932de04e4c4e848024560f078e8bac1b858e1ae07abdc5140667b1cd4d6c2`,
and the complete Final Gate passes PHPUnit `2513/11354` and Deptrac
`904/0 violations` with the ending aggregate unchanged.
Fresh Sol Max closure review matched the same freeze, confirmed the multi-tenant
counterexample is closed without Production changes, and returned Green
`P1=0/P2=0/P3=0`. The Orchestrator accepts P23-002D.

Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased. Public
Guide／Website、generated Artifact、commit、stage、push、CI、deploy、publication、
tag、release remain unchanged or unexecuted.

Remaining: none within P23-002D. Higher-goal work remains P23-002E、P23-003、
P23-004、P23-005、and P23-006. No commit／stage／push／CI／deploy／publication／tag／
release was performed.

Next Action: begin P23-002E `about`／aggregate-only `outbox:status` research and
Task Packet preflight.

## P23-002C accepted handoff — Historical prerequisite

Updated At: 2026-08-27T23:20:37+09:00

Accepted status: `Accepted — Schedule Projection and Atomic Activation`.
Reviewer: Fresh Sol Max closure review Green `P1=0/P2=0/P3=0`; Orchestrator Accepted.

P23-002A1/A2/B are Accepted and remain non-activated.
P23-002C owns the schedule projection, Production locator, build integration,
and the only all-three activation.
Luna Max completed the Production Source/Test implementation within the
corrected exact 41-file Task scope. The first Orchestrator Final Gate stopped
at canonical `mago lint` with exactly two new cyclomatic-complexity errors:
`ApplicationBuildCompileCommand` complexity 16 over limit 15 at line 56 and
`ApplicationProjectionBuildSetStatusSource` complexity 28 over limit 15 at line
20. The pre-correction exact32 aggregate
`0cce05bb35c46fbe15217e6cf6bcc206834ae429dca5c0c3d7ee937ecbda0ec5` is
Historical. At that checkpoint, no later Final Gate commands were run.

Initial Sol xHigh preflight returned Changes Requested
`P1=2/P2=2/P3=0`.
The current correction makes D146 the exact Production boundary:

- one internal root authority,
  `ApplicationBuildConfiguration::projectionBuildSetRoot`, derived from
  `dirname(app.build.container)`;
- missing config/directory is read-only `absent`, present invalid config/root
  is input-validation failure, and invalid artifact bytes are `malformed`;
- one command-facing `ProjectionBuildSetStatusSource::withStatus()` and one
  Reader `withPublishedStatus()` Production seam, while component exact-three
  hashes remain unchanged;
- target exact31, lazy Framework names exact25, reservations exact13;
- exact schedule Human/JSON/error bytes and shared strategy mapping; and
- one post-existing-artifact Publisher call with root-last,
  Projection-only atomicity and deterministic pre/post-commit failure results.

Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased.
Public Guide/Website, generated artifacts, Release Authority, commit, stage,
push, CI, deploy, publication, tag, and release are unchanged or unexecuted.

Fresh bounded Sol xHigh re-preflight matched exact9 aggregate
`c933fd9f23f85b00f447f72f69a7769bb7ec747b737cf711fb2cdf934beeb347`
and returned Green `P1=0/P2=0/P3=0`.

The narrow correction adds only class-level
`@mago-expect lint:cyclomatic-complexity` DocBlocks to those two allowed
Production files. Correction-focused format and canonical lint pass, as does
the impacted PHPUnit (`15 tests / 118 assertions`). The corrected worker
aggregate is `be0b7c8421e1f6906ce5576b59230ed33dcf505a00618afc09622c52a652d766`.
The Orchestrator refroze it, restarted the complete Final Gate from the first
command, and reproduced the same ending aggregate. Format, Composer strict,
canonical lint/baseline, analyze errors `0`, full PHPUnit `2468/10930`, Deptrac
violations `0`, version baseline, Website release-source, management-ID, diff,
and staged0 are Green.

The pre-correction `0cce05bb...` freeze and lint-stopped Final Gate are
Historical. The corrected `be0b7c...` freeze and its complete Final Gate
are also Historical/superseded after fresh Sol returned Changes Requested
`P1=0/P2=2/P3=1`. The bounded correction changes only the two allowed
integration tests; impacted PHPUnit is `37 tests / 447 assertions` PASS.
The current Orchestrator-frozen exact32 aggregate is
`15e0f074c5302af68753c06d8b9effc0747aff0e0a55902f081bc91834117a33`.
The current complete Final Gate is Green: format, Composer strict, canonical
lint/baseline, analyze errors `0`, PHPUnit `2472/10972`, Deptrac
violations `0`, version baseline, release-source, management-ID, diff,
staged0, and ending exact32 unchanged.
Fresh Sol Max closure review independently matched the exact32 and exact9
freezes and returned Green `P1=0/P2=0/P3=0`. The Orchestrator accepts P23-002C.
Remaining: none within P23-002C. P23-002D/E and the ordered Phase 23 work remain.

Next Action: begin the P23-002D `diagnostics:check`／aggregate-only
`queue:status` preflight.

## Accepted P23-002B handoff — non-activated Route Projection and List

Updated At: 2026-08-27T17:56:07+09:00

P23-002A1/A2 are Accepted and non-activated. P23-002B defines the route
projection producer, framework-fixed Status row, disconnected `route:list` /
`route:ls`, and shared-lock rendering correction. Initial and closure preflight
decisions are recorded in the Task and Report.

Fresh Sol review of the superseded `3ebe655...` freeze returned Changes Requested
`P1=1/P2=2/P3=0`. The bounded correction restores Stable GET `/operations/`
dispatch by separating runtime matching from collision detection, adds the
required producer/input/non-selected/purity fixtures, and synchronizes D146.

Latest correction-focused HTTP/Route PHPUnit is `80 tests / 396 assertions`
PASS and Task Fast Gate is `128 tests / 1221 assertions` PASS. Canonical `mago
lint`, `mago lint --verify-baseline`, and `mago analyze` (errors `0`),
allowed-path format, management-ID, `git diff --check`, and staged-path `0` all
PASS. No baseline or unrelated analyze warning was changed.

The accepted ordered 41-file Source/Test aggregate is
`174bc94c42b733a7611885f2edc4ab78aad1628c02a3bdd4ed5a48eb2d3df6fa`.
The prior Final Gate on `3ebe655...` is Historical/superseded and is not reused.
The current Final Gate is Green: format, Composer strict, lint/baseline, analyze
errors `0`, PHPUnit `2435/10739`, Deptrac violations `0`, version baseline,
Website release-source, management-ID, diff, staged0, and ending freeze all PASS.
Fresh Sol xHigh implementation review matched the exact Source/Test and
management hashes and returned Green `P1=0/P2=0/P3=0`. Orchestrator Acceptance
is complete without activation or partial publication.

Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased. B does not
publish a partial root, choose the Production locator, connect `build:compile`,
or register the new commands. P23-002C remains the sole atomic activation owner.

Remaining: none within P23-002B; the higher atomic slice still requires
P23-002C. Historical Next Action at P23-002B Acceptance was to begin the
P23-002C Task Packet while preserving B as non-activated; the current P23-002C
handoff above supersedes it. No
commit, stage, push, CI, deploy, publication, tag, or release is authorized.

## Accepted P23-002A2 handoff — non-activated Command Projection and List

Updated At: 2026-08-26T21:03:51+09:00

P23-002A1 is Accepted as the non-activated ProjectionBuildSet foundation.
P23-002A2 now defines the command producer, embedded Framework recovery rows,
BlackOps-owned `list`/`ls`, the global reservation registry, Stable input
compatibility, and the bounded future `operation:list --strategy` filter.
Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased.

A2 must not publish a partial root, choose the Production locator, connect
`build:compile`, or activate the replacement list in the Console Kernel.
P23-002C remains the sole final activation owner. The Task requires typed
six-state recovery without exception-message parsing and uses focused tests
during iteration, with one Final Gate after Source freeze.

Initial Sol xHigh preflight returned Changes Requested `P1=3/P2=1/P3=0`.
The corrected Packet fixes one exact 29-row target projection／activation metadata
authority, separate explicit/discovered/Operation producer inputs, mandatory
reservation inside both validator entrypoints, and Fast Gate coverage for all
allowed Source/Test paths. Stable namespace goldens govern resolution,
diagnostics, and exit status; successful rows use the BlackOps safe projection.
The bounded formatter correction is complete inside the Task's Files Allowed
after the Sol xHigh review. The corrected Source/Test freeze and Fast/Final Gate
evidence are current; no new activation or public capability claim is made. A2
remains non-activated and unreleased.

The bounded re-preflight closed those roots and returned one focused
`P1=1/P2=0/P3=0`: current Symfony `list` has aliases `[]`, while the target
projection has `['ls']`. The Packet now treats that as the only explicit future
metadata delta, leaves runtime `ls` unregistered in A2, and requests a focused
confirmation.

Focused Sol confirmation matched the corrected freeze and returned Green
`P1=0/P2=0/P3=0`. Phase 1 implementation is complete inside the Task's Files
Allowed, and Phase 2 adds the pure command producer, exact three-source union,
embedded exact 29 rows, closed-field validation, and actionable collision
diagnostics. Focused PHPUnit `52 tests / 563 assertions`, format,
management-ID, and `git diff --check` are PASS; the ordered 11-file Phase 2
Source/Test aggregate is
`f46934937acb0a075717fac1ea79d016c011ddfb86723945a76ee467cc0f9e24`.
A2 remains non-activated and unreleased.

Current status: `Accepted — non-activated Command Projection and List`.
P1 correction hashes are List
`1293ba223a43c7edfa925606cf3ef34166101155f7ca93c2846131d4d41bf816`, Renderer
`b5921d015a672feef961b906ff5eafd199eab9dd73c0c304c7989cff142b2fb6`, and
ListTest `2c3f8528fe11c41b4134663db00f7530d248e548cb35face9845ce5ae5ab73d8`.
Correction-focused Projection Command is `35 tests / 320 assertions` PASS;
focused lint, format, analyze error0, management-ID, diff, and staged checks PASS.

The exact ordered 30-file Source/Test aggregate is
`69a3bd57cef3dcea92f2edf4bc4d57c50e7655ac8c2ef3bd0cfe22802cf30740`, identical
before and after the gates. Fast Gate is `130 tests / 1166 assertions` PASS.
Final Gate is PASS: format, Composer strict, lint with baseline186 filtered,
current lint baseline, analyze error0, PHPUnit `2408/10398` with nonblocking
Deprecations1, PHPUnit deprecations2, and Notices13, Deptrac `883/883` with
violations0 and Allowed4932, version baseline Stable1.2/historical1.1,
`release:check:source`, management-ID, `git diff --check`, and staged0.

The prior Sol `P1=1/P2=3` review, its `0999e0ab…` and `1c1614e8…` freezes, and
failed gates are Historical only. Stable `1.2.0` remains current and BlackOps
`1.3` remains unreleased. Replacement list/ls remains unregistered and
non-activated, with no partial publish, Production locator, build connection,
public docs, or generated artifact change. Website full test/build, Browser,
Network, CI, deploy, publication, tag, release, commit, and stage are Not
Verified or unapproved.

The bounded Sol xHigh closure review matched the frozen Source/Test and
management hashes and returned Green `P1=0/P2=0/P3=0`. Orchestrator Acceptance
completed at `2026-08-26T21:03:51+09:00`.

Remaining: none within P23-002A2. The higher atomic slice still requires
P23-002B route and P23-002C schedule/final activation.
Historical Next Action at P23-002A2 Acceptance was to begin P23-002B without
partial activation. P23-002B is now Accepted and the current P23-002C handoff
above supersedes it. No commit, stage, push, CI, deploy, publication, tag, or
release is authorized.

## Accepted P23-002A1 handoff

Updated At: 2026-08-26T17:30:18+09:00

P23-002A1 is the bounded ProjectionBuildSet wire/filesystem foundation inside
the unchanged P23-002A/B/C atomic acceptance slice. D146 records an injected
absolute dedicated root and a PHP-implementable trusted-root verified-handle
policy; it does not claim kernel `O_NOFOLLOW`. A1 does not add a public config
key, infer a locator from existing manifests, activate a Console command, or
connect real producers. Stable `1.2.0` remains current and BlackOps `1.3`
remains unreleased.
Readers do not create a missing lock; only the publisher may initialize it
with exclusive-create semantics. GC is bounded before mutation at `256`
directory entries and `32` deletions per publication.
Initial Sol preflight returned Changes Requested `P1=2/P2=2/P3=0`. The
corrected current authority now fixes single-basename confinement, reserved
generation/temp/GC grammar, first-lock slot reservation, existing-target
handling, and a complete key-kind/field-cap table. Fresh Sol preflight returned
Green `P1=0/P2=0/P3=0`; A1 implementation and focused tests are complete within
the allowed paths. Focused Gate is PASS at `32 tests / 167 assertions`, with
focused format, source lint, management-ID guard, and `git diff --check` also
PASS. The frozen Source/Test aggregate is
`604ccf53af4a005c74e454c34bf9ff58f4db30cc629ad9b8cd5712ebda1f72ed`.

Status: `Accepted — non-activated ProjectionBuildSet foundation`。Fresh Sol
implementation review at `2026-08-26T16:44:55+09:00` is Historical Changes
Requested `P1=3/P2=1/P3=0`; the four bounded findings are corrected. Current
Final Local Gate is Green: PHPUnit `39 tests / 202 assertions`, full PHPUnit
`2360 tests / 9757 assertions` exit0, full format, Composer strict, full Mago lint
(baseline186 excluded), verify-baseline, full analyze (errors0, 108 warnings/help),
Deptrac (Allowed4849, all violation categories0), version baseline 1.2.0/1.1.0,
Website release source, management-ID, `git diff --check`, and staged0 all PASS.
The unchanged 15-file Source/Test aggregate is
`fc4b98e429a01489e33db7f7f4fbb60ea5362591da24361a48e987d4d96a0d2b`; the
per-file hash manifest is in the current Task Report. Stable `1.2.0` remains
current, BlackOps `1.3` remains unreleased, and A1 is non-activated.
The prior failed lint-only gate and prior aggregates are Historical and are not reusable.
The bounded post-Gate Sol re-review found no Source/Test defect and returned
`P1=0/P2=1/P3=0` only because the completed Final Gate was still unchecked in
the Task and Report. Both management entries are corrected without changing
Source/Test. Focused Sol confirmation matched the five management hashes,
unchanged Source/Test aggregate, staged-empty state, and `git diff --check`, and
returned Green `P1=0/P2=0/P3=0`. Orchestrator Acceptance is complete.
Historical pre-correction checkpoint: 前回のFinal Local Gateは、Source/Test変更後のexact frozen aggregate
`604ccf53af4a005c74e454c34bf9ff58f4db30cc629ad9b8cd5712ebda1f72ed`でGreenとなった。
Focused PHPUnitは`32 tests / 167 assertions` PASS、full PHPUnitは`2353 tests /
9722 assertions` exit 0（Deprecations 1、PHPUnit Deprecations 2、Notices 13）。
Composer strict validation、full format、full Mago lint（baseline `186` excluded）、
baseline verify、Website release source check、PHP management-ID guard、
`git diff --check`はPASS。Full Mago analyzeはexit 0／errors `0`（`108 warnings/help`）、
DeptracはAllowed `4849`、violations／skipped／uncovered／warnings／errorsすべて `0`、
version baselineはpublished `1.2.0`／historical `1.1.0`でPASS。Final freeze後に
Source／Testの変更はない。Website full test/build/Browser、network、commit、stage、
push、CI、deploy、publication、tag、releaseはscopeまたは指示により未実行。Stable
`1.2.0` remains current、BlackOps `1.3` remains unreleased。Fresh Sol implementation
review（`2026-08-26T16:44:55+09:00`）はChanges Requested `P1=3/P2=1/P3=0`を返したため、
このFinal Local Gate結果はHistoricalであり、correction後には再利用しない。Correctionは
RFC 8785 U+2028/U+2029 literal encodingとcommand description rejection、explicit maxの
bounded directory scan sentinel停止、cleanup fsync／deleted sorted prefix、publish単位の
deletion state reset、およびdirect fixtureに限定する。At that checkpoint, Remaining: correction、Focused Gate、
新Source freeze、Final Gate、fresh Sol implementation review、Orchestrator Acceptance、then
A2/B/C and P23-006. Next Action: 四つのrootだけを補正し、Focused Gate後に新aggregateをfreezeして
Orchestrator Final Gateへ渡す。

## Current P23-002A1 Acceptance Checkpoint

The prior Sol implementation review is Historical Changes Requested
`P1=3/P2=1/P3=0`; all four bounded findings are corrected. Final Local Gate is
Green on PHPUnit `39 tests / 202 assertions` and the full evidence recorded
above. The unchanged 15-file Source/Test aggregate is
`fc4b98e429a01489e33db7f7f4fbb60ea5362591da24361a48e987d4d96a0d2b`; exact
per-file hashes are recorded in the current Task Report. Stable `1.2.0`
remains current, BlackOps `1.3` remains unreleased, and A1 is non-activated.

Remaining: none within P23-002A1. P23-002A2 is now in bounded Sol preflight;
the higher Goal still requires A2/B/C and P23-006. Next Action: complete the
P23-002A2 preflight. Commit, stage,
push, CI, deploy, publication, tag, and release remain
unapproved and unexecuted.

## Current P23-001R handoff — Accepted

Updated At: 2026-08-26T14:14:17+09:00
P23-001R Started At: 2026-08-26T13:00:33+09:00
P23-001R Accepted At: 2026-08-26T14:14:17+09:00

P23-001R is Accepted. The initial and management-sync reviews are Historical Changes Requested `P1=0/P2=1/P3=0`; their aggregate-surface and current-authority roots are closed. Final read-only Sol Documentation re-review returned `P1=0/P2=0/P3=0`, and Orchestrator independent review confirmed the exact scope, hashes, post-correction evidence, and frozen paths. Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased; Retention semantics, AuditRecord, Release Authority, version, tag, Package, Skeleton, and 1.3 capability are unchanged.

Changed hashes: guard script `453e347673f36fd1f12795c448bf1183a4ed4b4d159774adf5942bfecc1dd3a0`; guard test `aede7f9f2f1ea1f3271a0735bd20fe69928f28d16b777dc678e74c086b59ebad`. Frozen Search reader/lock/test and Audit D145/Spec105/Spec106 hashes remain byte-identical. Post-correction evidence retained by hash: focused Retention `5/5`; Source guard; Website check `41` pages with zero errors/warnings/hints; build `42` pages with static artifact PASS and site `41` PASS; Artifact guard; version baseline `published=1.2.0`, `historical=1.1.0`; actual generated HTML/raw/Search/LLM exact-route/segment markers; and `git diff --check` all PASS. PHPUnit `30/192` and Mago are guard-only evidence from before the correction, not post-correction reruns. Existing non-fatal chunk-size and empty-route priority warnings remain.

Remaining: none within P23-001R. Full Website test, Browser, same-SHA CI, deploy, publication, tag, release, commit, and stage are Not Verified／未実行 because they are outside this Task. Higher-goal Remaining is Specification 106's ordered Phase 23 work and P23-006 release gate. Next Action: start the P23-002A Task Packet within the P23-002A/B/C atomic projection slice. No commit, stage, push, CI, deploy, or release is authorized.

## Accepted parent P23-001 handoff — Stable 1.2 current / BlackOps 1.3 unreleased proposal

Updated At: 2026-08-26T10:34:43+09:00
Round30 P23-001 Acceptance Sync Completed At: 2026-08-26T10:34:43+09:00

Current P23-001 status: `P23-001 Accepted — Stable 1.2 current / BlackOps 1.3 unreleased proposal`; Round 21 remains the accepted child fact preserved in historical context, prior Round29 verdict/correction checkpoints and the risk-based Round30 gate are Historical evidence, and the Phase 23 material remains an unreleased proposal. Search and Audit are separate and are not aggregated. Search Sol, Audit, and Parent General Sol decisions are all Accepted with `P1=0/P2=0/P3=0`.

Risk-based gate facts retained exactly: the preceding Full Website run was `147` with `145` pass and `2` fail (`145/147`); the final affected HTML/Search Satteri/JSDOM set was `7/7` PASS; `pnpm check` covered `41` pages; build `42`, static artifact PASS, and site `41` PASS. The `145` unaffected tests were reused through hash-bound delta evidence; final `147/147` was deliberately not rerun after the bounded fix. The separate Fast/Full gate split and generated-fixture reuse remain a performance follow-up and are not P23-001R.

Ending freeze remains PASS for exact22, staged `0`, and `git diff --check`; Search/Audit exact hashes are unchanged. No tests/build/network/commit/stage/push action was taken by this management sync; CI/deploy/release/commit remain unapproved and unexecuted. Remaining: none. Next Action: Specification 106's P23-002A/B/C atomic projection slice, beginning with the P23-002A Task Packet. Stable `1.2` remains current and `1.3` remains unreleased. No Phase 23 capability is released.

## Historical P23-001 Round30 handoff — Search and Audit Slices Accepted / Parent General Sol Review Requested

Historical status: `Round30 Search and Audit Slices Accepted / Parent General Sol Review Requested — parent not Accepted`. This prior slice-acceptance checkpoint is Historical evidence superseded by the current P23-001 Accepted snapshot. Round 21 is the accepted child fact preserved in historical context, prior Round29 verdict/correction checkpoints are Historical evidence, and the Phase 23 material remains an unreleased proposal. Search and Audit were separate and were not aggregated.

Historical evidence retained from that gate: Search review was `P1=0/P2=3/P3=0`; Audit Sol review was Accepted with `P1=0/P2=0/P3=0`. The source-before-final-fix Full Website test ran `147` with `145` pass and `2` fail in `299201.308ms`; the only failing tests were `Search excludes script and style raw bodies opened at physical line endings` and `Search masks incomplete CommonMark HTML blocks with an independent reader oracle`, both sharing the same top-level raw opener continuation root. The bounded Luna correction changed Search exact4. On that snapshot, Node syntax `2/2` PASS; 7 affected HTML/Search Satteri/JSDOM tests `7/7` PASS in `2459.996ms` (including those two tests and the 23-fixture x3=`69`-case matrix); `pnpm check` 41 pages with `0` errors/warnings/hints; the sandbox build failed only at `listen EPERM`, then the approved exact rerun PASSed with 42 pages, static artifact PASS, and site `41` PASS; explicit site/source/artifact release checks PASS; `bash -n` and version baseline PASS (`published=1.2.0`, `historical=1.1.0`); exact22, staged `0`, and `git diff --check` PASS. The full `147/147` Website test was deliberately not rerun after the bounded final fix; risk-based delta reuse was 145 unaffected tests from the immediate predecessor plus the final 7 affected tests plus current check/build/site/release evidence. Frozen Audit exact3/public/PHP/child hashes were unchanged and immediately preceding Green evidence was reused without rerun. Search hashes were patch `d6d3c2f61b7e6e7177cf3d4a422258f28ad7c5f758ea45078572a7d16ec5233c`, lock `77403af7259c1948d56db4f53a05bf6e172aea1090083e28506f2bb84ca2db01`, reader `6dcc3601d3ff70c8ec8ab3a4515d2d28eb684ac0ee2a4866ce51c305396f8fe7`, test `5728c9bc45c7dc150fd378207f3bebeecf6dc4805de95b32dec8d1ad813030fb`, and installed helper `a8c5c0306045f742745adb6919fe5f8c6729673d9ed1b39d55e27f3e8100d397`. No tests/build/network/commit/stage/push action was taken by that management sync. Its remaining work was fresh Search Sol review, Search slice decision, parent General Sol review, and Orchestrator acceptance; its Next Action was Search Sol review.

## Historical P23-001 Round30 Search correction checkpoint — source-change-before Combined Local Gate snapshot

Historical status: `Round30 Search Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. This prior Round30 Pending checkpoint is Historical source-change-before evidence only; it is not current and is not reusable as Green evidence.

The Production Search patch and independent reader now cover three bounded roots: quote-aware/container-stripped multiline script/style raw starts, including initial recognized-tag prefix quote state; separate CommonMark HTML-flow state from DOM visibility so script/style bodies are inert while div/pre/textarea bodies remain reader-visible, including completion-line visible tails; and lazy blockquote/shallower interruption across nested/list/tab/blank-line and LF/CRLF/lone-CR cases. A single focused matrix uses 23 fixtures × 3 line endings = 69 cases, with Satteri rendering plus JSDOM reader text or Satteri `<pre>` location as independent oracles. Current Search hashes are patch `c0bc6cfe13d574f009c98de77678c1bde28514366680855bbd3a925df39616d5`, lock `9c60d60f22f59590375225a9cc79e80d059d29f2614a916fe8621119149f2a11`, reader `02f496e65a7c02421b6dc725dad1aed74ea75017e1411be1ee46c2b53f79450c`, test `4f567e8a0a5dbf51c27d2798abe11ae5305916b6b2de7b82bc787365b2cbcb99`, and installed helper `600dbe8fdd56c1833882069757e108284199c0f69d1150e434a455fcf4c7208f`. The Combined Local Gate is pending for the changed exact22.

Remaining: fresh Combined Local Gate, separate Search Sol re-review, slice decisions, parent General Sol review, and Orchestrator acceptance. The Audit Accepted `0/0/0` record and `374/418` current Access golden remain current; `103/147` is historical target-less negative evidence. No Phase 23 capability is released.

このディレクトリは、本フレームワークで合意済みの仕様をまとめた正本である。

## 文書の規則

- 確定した仕様だけを記載する
- 未決事項は [TODO.md](../TODO.md) で管理する
- 判断の経緯は [decisions/](../decisions/) に残す
- 仕様変更前に新しい設計対話を行う
- 置き換えられたDecisionは削除せず `Superseded` として残す

公開Releaseのcurrent claim、immutable source refs、Capability、historical referenceは、[machine-readable Release Authority](release-authority.json)を正本としてDocumentation Sourceと生成Artifactで共有検証する。

Round 12 through Round 28 wording in the following historical paragraphs is
superseded; the current parent has disjoint Round29 Audit and Round29 Search
correction lanes. Round 21 remains a Historical accepted child prerequisite,
and no earlier parent round is current.

Historical Round 18 was the then-current parent/child checkpoint and returned
child General `P1=0/P2=1/P3=0`; it is now historical Changes Requested. Round
20 is also historical Changes Requested, and Round 24 is historical. Round 25
is historical. The current parent status is `P23-001 Accepted — Stable 1.2 current / BlackOps 1.3 unreleased proposal`; Round 21 child `Accepted` is
preserved as a historical fact, and that parent acceptance is complete. The
P23-001R is Accepted after final Sol `P1=0/P2=0/P3=0` and Orchestrator review.
Stable `1.2` remains current and all Phase 23 capability material remains
unreleased. Prior Changes Requested/Correction In Progress and the earlier
pre-correction Combined Local Gate Green snapshot is Historical and non-reusable because
Search exact4 and Audit exact3 changed. The Search and Audit lanes remain
separate.

Historical Round 24 roots were Application Bootstrap list timing and all public
Source/HTML/raw/Search/LLM guards; complete-set ProjectionBuildSet validation
and current exact wire; Historical parent-management labels; exact
non-circular AuditReadContext and time/nonce-bound AuditAccessContext; Stage 6
finite catalog/graph code and precedence; and current Query/cursor/Outbox/
AnchorIntent wires with acyclic ownership. Round 23 roots are historical:
Application-owned/unreserved worker names with normal Symfony unknown behavior
when unregistered and no Framework Job listing;
shared-lock root plus all-three ProjectionSet validation before selected-row
rendering; canonical Mago/hash evidence; sole-current status; required
operation-read delivery with durable append before response/failure;
genesis-only sequence continuity with `1-42`/`43-50`; metadata-only two-state
retention plus separate AuditHold; and exact SHA-256-wrapped AuditReadContext
requestRef with algorithm-substitution negative. Its review lanes were separate.

Historical Round 25 roots were the exact ProjectionBuildSet kind/origin map and
negative fixtures; self-contained Read/Access requestRef vectors and P23-003F/G
ownership; one total Stage6/7 verifier precedence; conditional AnchorIntent
state/version equality; metadata-only tenant-null/present Outbox AAD bytes;
trusted Framework-clock query-cursor key windows; and sole-current management
synchronization. Round 25 review is historical Changes Requested with General
`P1=1/P2=1/P3=0` and Audit `P1=5/P2=1/P3=0`. Stable `1.2` remains current,
Phase 23/`1.3` remains unreleased, and no external action is authorized.

Historical Round 26 roots were the fixed first-page AuditQuery head and cursor
continuation binding; the closed Outbox pending/leased/relayed/poisoned matrix
with immutable protected body/AAD and fenced retry/sent/dead-letter behavior;
the prepared/submitted/accepted/rejected AnchorIntent matrix with CAS,
idempotency, and recovery; and the closed RetentionState/Hold wire with
`schemaVersion=1`, `kind="audit_retention_state"`, active genesis, exact +1
revision, released terminality, and no re-activation. Existing P23-003B/C/F/G
ownership and positive/negative fixtures remain Historical evidence. The
historical status was `Round29 Audit Re-review Changes Requested / Correction
In Progress; Round29 Search Re-review Changes Requested / Correction In Progress
— parent not Accepted`; the two correction lanes remain separate. Fresh source
corrections, new-candidate gates, fresh separate Sol reviews, slice decisions,
and the remaining parent General review gates remain. Neither lane is Green or
Accepted.

The following compact Stable/Roadmap paragraph preserves a historical Round 16
transition description; its status wording is superseded by Round 17 above.

Round 17 follow-up checkpoint is historical: `2026-08-19T21:59:03+09:00`. The Guide's
class-string/prebuilt-instance paragraph now states explicitly that the
generation/reuse boundary does not change with the selected list format; the
Website marker requires the same wording. Status was Correction Implemented /
Review Requested at that historical checkpoint; Round 18 through Round 25 are
now historical while the proposal remains unreleased.

Historical Round 18 checkpoint: `2026-08-19T22:20:07+09:00`. Closed `details`
expose only the first direct `summary` until `open`; plain closed `dialog`
content is hidden unless `open` or an explicit non-`none` inline display
override makes it visible. Its fresh gate and review lane are historical.

Round 19 through Round 23 are historical. Round 23 historical checkpoint:
`2026-08-20T04:18:00+09:00`. The active Specification 106 DAG treats the
child Task and Report as the Round 21 accepted-child authority; Round 21 and
earlier are historical. The fresh complete Local Gate is PASS after the final
bounded wording cleanup. Fresh parent General/Audit review and parent
Orchestrator acceptance remain; after acceptance, P23-001R and ordered Phase
23 work remain.

Historical Round 24 checkpoint: `2026-08-20T06:18:18+09:00`. Its fresh Local
Gate evidence and review handoff remain historical.

Historical Round 25 checkpoint: `2026-08-20T07:46:09+09:00`. Its source-changing
Phase 1 build and complete Local Gate evidence are retained as historical
records; the later management-only post-gate static closeout did not rerun a
build. Round 26 is historical and its status is no longer current. Round 27
has the fresh Local Gate evidence recorded in the current Task/Report. Stable
`1.2` is current, Phase 23/`1.3` is unreleased, and no external action is
authorized.
Historical Round 25 gate detail: Status was `Round 25
Local Gate Green / Independent Review Requested`; the Orchestrator independently
passed focused/full Website `8/8` and `120/120`, Website check `0/0/0`, build
`42` pages/site check `41`, release source/artifact, version baseline
`published=1.2.0 historical=1.1.0`, focused PHPUnit `30 tests / 250 assertions`,
Mago, PHP management-ID, current-only Node, Docker-PHP goldens/negative
matrices, and exact-16 HEAD/main scope with staged `0` and diff check. Only
existing nonfatal chunk-size and route-priority warnings remain. Full gates
were Orchestrator-run; the worker did not rerun them. This evidence is historical
and does not close Round 27. Stable `1.2` is current, Phase 23/`1.3` is
unreleased, and no external action is authorized.

## Historical P23-001 handoff — Round29 Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-reviews Requested

Updated At: 2026-08-26T01:51:40+09:00
Round29 Combined Local Gate Green Management Sync Started At: 2026-08-26T01:46:59+09:00
Round29 Combined Local Gate Green Management Sync Completed At: 2026-08-26T01:51:40+09:00

Historical status: `Round29 Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-reviews Requested — parent not Accepted`. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate lanes and severity is not aggregated. The parent is not Accepted, Decided, or Released.

Fresh Combined Local Gate evidence is PASS: offline frozen install; Node syntax `4/4`; independent Orchestrator exact probe HTML `22` plus fence `9` PASS; product framing `8/8`; Search focused `6/6`; full Website `146/146` in `277824ms`; `pnpm check` `41` pages with `0` errors/warnings/hints`; the initial sandbox build stopped only at the Astro font listener (`listen EPERM 0.0.0.0`), the approved same-command rerun PASSed with a `42`-page build, static artifact boundary PASS, embedded site check `41` PASS, and existing nonfatal chunk-size/route-priority warnings only; explicit site check `41` PASS; release source/artifact PASS; `bash -n` and version baseline PASS (`published=1.2.0`, `historical=1.1.0`); PHPUnit `30/30` and `250` assertions; Mago format PASS; canonical lint PASS (`186` baseline-filtered/no new issue); verify-baseline PASS; analyze exit `0` with existing `71` issues (`46` warning / `25` help); PHP management-ID guard no matches; exact22/trailing-whitespace/diff/staged `0` PASS. Node/PHP Read/Access goldens match.

Search exact4 remains frozen at patch `81ca291976660fbcdfe84aeaf91fbb3226fa1ac9aee185eb73dd4c37806f2ee2`, lock `ede585914285f744982ddba7b2464a3b1d6dbb41978aa8df40fdc6e4da3b6e05`, reader `eb97a88bebbc3886e333d0336053f914414c251b705c9aec7a381e9002aa247d`, tests `ae02dd1743f2b7621db47796536f56ee23d995ed3dac0680ee150558e3a21779`, installed helper `f3eb2215cb349776f2d2f09636346d7591d8e43f18af6a836490656bd3538bb5`. Audit exact3 remains frozen at D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; Round29 block `71,378` bytes / `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, JSON `7/7`, links `35/11/0`. Management links are `271/259/0` (total/local/missing).

Current/Historical guard: only this first current handoff defines active Round29 status. The earlier Pending handoff is explicitly Historical/source-change-before; no Historical block is current evidence. Fresh separate Sol Search/Audit re-reviews, separate slice decisions, parent General Sol review, and Orchestrator acceptance remain pending. A non-blocking post-P23-001 test-performance follow-up is proposed: split Fast Gate from final Full Gate, isolate the two slow integration cases (`~192s` / `~76s`), and reuse one generated 41-page fixture. No test changes are made in this Green candidate. No Accepted, Decided, Released, CI, deploy, or release claim is made.

## Historical P23-001 current handoff — Round29 Search/Audit Bounded Corrections Implemented / Combined Local Gate Pending — source-change-before Combined Local Gate snapshot

Historical status: `Round29 Search/Audit Bounded Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. This Pending snapshot predates the fresh Green gate and is retained as Historical evidence only; it is not reusable as current evidence.

Updated At: 2026-08-26T00:41:04+09:00
Round29 Fresh Search/Audit Bounded Corrections Started At: 2026-08-26T00:20:39+09:00
Round29 Fresh Search/Audit Bounded Corrections Completed At: 2026-08-26T00:20:39+09:00
Round29 Search Raw-start Boundary Parity Correction Started At: 2026-08-26T00:41:04+09:00
Round29 Search Raw-start Boundary Parity Correction Completed At: 2026-08-26T00:41:04+09:00

Status: `Round29 Search/Audit Bounded Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate lanes and severity is not aggregated. The parent is not Accepted, Decided, or Released.

Search review record: `P1=0/P2=3/P3=0`. Production Search and the independent reader scanner separately correct list-continuation visual-column normalization before same-container Setext `-+`/`=+` detection, block-start empty list markers without inherited paragraphs, and incomplete CommonMark type-3 through type-6 HTML block masking. RawStarter lines without a standalone `>` now treat the first later `>` as opening completion rather than a closing raw tag, matching rendered Satteri/JSDOM visibility. Same-line `<template>` remains inert and multiline `<template` remains visible. Audit review record: `P1=0/P2=1/P3=0`; A5 is technically Green and the active D145 index status is synchronized.

Search exact4 hashes: patch `81ca291976660fbcdfe84aeaf91fbb3226fa1ac9aee185eb73dd4c37806f2ee2`, lock `ede585914285f744982ddba7b2464a3b1d6dbb41978aa8df40fdc6e4da3b6e05`, reader `eb97a88bebbc3886e333d0336053f914414c251b705c9aec7a381e9002aa247d`, tests `ae02dd1743f2b7621db47796536f56ee23d995ed3dac0680ee150558e3a21779`, active helper `f3eb2215cb349776f2d2f09636346d7591d8e43f18af6a836490656bd3538bb5`; focused `7/7`, explicit no-standalone script/style parity `6/6`, syntax `2/2`, offline frozen install and binding PASS.

Audit exact3 is frozen: D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; Round29 block `71,378` bytes, SHA `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, JSON `7/7`, links `35 total / 11 local / 0 missing`.

Current/Historical guard: only this first current handoff defines active Round29 status; a Round29 handoff headed without `Historical` must not carry `Combined Local Gate Green`, while prose references must explicitly say Historical/source-change-before. The previous Green snapshot is retained as Historical source-change-before evidence and is not reusable. Remaining: fresh Combined Local Gate on new exact22; fresh separate Sol Search/Audit reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance. Next Action: freeze final exact22/new exact9 hashes, send separate Search/Audit packets, and run the fresh Combined Local Gate. No Accepted, Decided, Released, CI, deploy, or release claim is made.

## Historical P23-001 current handoff — Round29 Combined Local Gate Green / Fresh Separate Sol Re-reviews Requested — source-change-before Search correction snapshot

Updated At: 2026-08-25T23:15:57+09:00
Round29 Combined Local Gate Green Management Sync Started At: 2026-08-25T23:15:57+09:00
Round29 Combined Local Gate Green Management Sync Completed At: 2026-08-25T23:15:57+09:00

Historical status: `Round29 Combined Local Gate Green / Fresh Separate Sol Re-reviews Requested — parent not Accepted`. This source-change-before snapshot is retained for auditability only and is not reusable as current evidence. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate lanes and severity is not aggregated. The parent is not Accepted, Decided, or Released.

Search exact4 is frozen: patch `4e2c182b35aacae1fa10ce8cff971f01babeaa75d78ad612161ac31163f3557f`, lock `c37b758001203c23706b7d2f7b70ae1d8a5f1515045b2930da1d6e02dbc99377`, reader `1eb569184a1b2cb1391d8721e4712a93658bac130e9b88b6e59c6b565e1241a1`, tests `127680f26bfd6d9d2ca814849af3183857f1657deee3b6b9bd240032410aaef8`, active installed helper `8999af023b9c353b1719ea5c6e0ce0da182e833ae8d09a941a24302822e4897e`; focused `2/2` and syntax/offline install/binding/diff-check PASS.

Audit exact3 is frozen: D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; the current Round29 block is `71,378` bytes, SHA `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, byte-identical exact3, JSON `7/7`, Audit links `35 total / 11 local / 0 missing`.

Combined Local Gate evidence is PASS: offline frozen install; Node syntax `4/4`; product framing `8/8`; Search focused `2/2`; full Website `144/144` in `279725ms`; Website check `41` pages with `0` errors/warnings/hints; the first sandbox build stopped only at the Astro font listener (`listen EPERM 0.0.0.0`), the approved same-command rerun PASSed with a `42`-page build, static artifact boundary PASS, embedded site check `41` PASS, and only existing nonfatal chunk-size/route-priority warnings; explicit site check `41` PASS; release source/artifact PASS; `bash -n` and version baseline PASS (`published=1.2.0`, `historical=1.1.0`); PHPUnit `30/30` and `250` assertions; Mago format PASS; canonical lint PASS (`186` baseline-filtered, no new issue); verify-baseline PASS; analyze exit `0` with existing `71` issues (`46` warning / `25` help); PHP management-ID guard no matches.

Read Node+Docker scope is `150/192` with digest `bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`; Access is `103/147` with digest `1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`; domain/algorithm/nonce negatives PASS. Management links are `271 total / 259 local / 0 missing` before this sync and remain bounded; exact22 hashes PASS, staged `0`, `git diff --check` PASS, and `HEAD=main` is `5ad87557c08e20c3bcc6d77be83804001ba3eb80`.

Remaining: fresh separate Sol Search/Audit read-only reviews, separate slice decisions, parent General Sol review, and Orchestrator acceptance. Next Action: freeze final exact22 and new exact5 hashes and send separate Search/Audit packets. Do not claim parent Accepted, Decided, or Released.

## Historical P23-001 current handoff — Post-review Search/Audit Corrections Implemented / Combined Local Gate Pending snapshot

Historical status: `Round29 Post-review Search/Audit Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. This is the immediately preceding Pending snapshot and its evidence before the current Combined Local Gate Green result; it is management-before evidence, not current and not reusable as Green evidence.

Updated At: 2026-08-25T17:32:04+09:00
Round29 Management Correction-complete / Gate-pending Sync Started At: 2026-08-25T17:32:04+09:00
Round29 Management Correction-complete / Gate-pending Sync Completed At: 2026-08-25T17:32:04+09:00
Round29 Audit Exact3 Block Resync Started At: 2026-08-25T17:50:37+09:00
Round29 Audit Exact3 Block Resync Completed At: 2026-08-25T17:50:37+09:00

Historical status: `Round29 Post-review Search/Audit Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit are separate lanes and severity is not aggregated. Luna corrections and Orchestrator review are complete; the parent is not Accepted, Decided, or Released.

Search final Sol verdict: Changes Requested `P1=0/P2=2/P3=0`; roots are the Setext H2 boundary and script/style tag-name physical-line boundary. Search exact4 is frozen: patch `4e2c182b35aacae1fa10ce8cff971f01babeaa75d78ad612161ac31163f3557f`, lock `c37b758001203c23706b7d2f7b70ae1d8a5f1515045b2930da1d6e02dbc99377`, reader `1eb569184a1b2cb1391d8721e4712a93658bac130e9b88b6e59c6b565e1241a1`, tests `127680f26bfd6d9d2ca814849af3183857f1657deee3b6b9bd240032410aaef8`, active installed helper `8999af023b9c353b1719ea5c6e0ce0da182e833ae8d09a941a24302822e4897e`; focused `2/2`, syntax/offline install/binding/diff-check PASS.

Audit final Sol verdict: Changes Requested `P1=1/P2=0/P3=0`; root is the active attempt32 pre_call/post_call phase conflict. Audit exact3 is frozen: D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; independently checked heading-inclusive Round29 block through the next-H2 boundary (excluding that boundary LF) is `71,378` bytes, byte-identical exact3, SHA `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, JSON `7/7`, links `35 total / 11 local / 0 missing`, phrase guards, and diff-check PASS.

The previous `Round29 Post-correction Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-review Requested` record is Historical source-change-before evidence and is not reusable. The Combined Local Gate for the new exact22 has not run and is Pending; no new Green result is claimed.

Changed scope is exact5 management files only; Search exact4, Audit exact3, and the other17 exact22 paths remain frozen. No CI, Browser, deploy, publication, release, commit, stage, or push is claimed.

Acceptance: Search/Audit corrections and Orchestrator review are complete; fresh Combined Local Gate, fresh separate Sol Search/Audit reviews, separate slice decisions, parent General Sol review, and Orchestrator acceptance remain pending.

Remaining: fresh Combined Local Gate from scratch on new exact22; fresh separate Sol Search/Audit reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance.

Next Action: freeze exact22 and new exact5 hashes, then run the Combined Local Gate from scratch. Do not claim Accepted, Decided, or Released.

## Historical P23-001 handoff — Round29 Post-correction Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-review Requested — source-change-before snapshot

Updated At: 2026-08-25T16:31:07+09:00
Round29 Exact5 Management Sync Started At: 2026-08-25T14:55:51+09:00
Round29 Exact5 Management Sync Completed At: 2026-08-25T14:55:51+09:00
Round29 Post-correction Combined Local Gate Green Management Sync Started At: 2026-08-25T16:31:07+09:00
Round29 Post-correction Combined Local Gate Green Management Sync Completed At: 2026-08-25T16:31:07+09:00

Historical status: `Round29 Post-correction Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-review Requested — parent not Accepted`. This source-change-before snapshot is retained for auditability only and is not reusable for the new exact22.

Fresh Search Sol verdict: Changes Requested `P1=0/P2=2/P3=0`. The roots were CommonMark thematic-break/list and type-6 slash-boundary divergence, plus complete HTML comments and script/style/template content leaking into Search. Search exact4 corrected these roots.

Search exact4 hashes: patch `ba3218266569613f93a0e0275b3f679d34c43efb96f564e5a4674faf3c56c8c7`; lock `634d955259e7250f269af53785d4768155c95895521f14282677ee6bee4578f8`; reader `4d6f9c5b5f7eec7cfee13285a0dec44687be789c62064c2220d2e47b6e45f666`; test `1ba40a38557fa165664b2d4a5d2e81dd885ab2226fcb257ddaf40529b1ab2a9b`. Offline frozen install PASS; syntax PASS; new focused Satteri/reader/DOM `3/3 PASS`; existing Satteri HTML/search `2/2 PASS`; patch-lock-installed helper binding PASS; installed helper SHA `357879303ecb3f2dbeb675174450dc358450674089f3bc2a9a45fe157b1742ce`; exact4 diff-check/staged0 PASS.

Fresh Audit Sol verdict: Changes Requested `P1=1/P2=0/P3=0`. The root was `AuditIngressEnvelope` binding `recordedAt` to nonexistent `logicalRecord.recordedAt` instead of closed-schema `logicalRecord.time.recordedAt`. Audit exact3 corrected this root.

Audit exact3 hashes: D145 `b7903d679f60b5af9d7eb6974e1ff0346042af6dbd8de5077b5f570c3ae2991f`; Specification 105 `b4c09319563cb590161b3fa8c93ff8d99e9cbe42277b2bb0c9d3cc5d54892a4e`; Specification 106 `2934acf7c135664342dc0233e45366ef875c34caa0e58002f9e490892ff86e14`. The current Round29 block is `71,066` bytes in each file, byte-identical, SHA-256 `4128251cd29dfb2e9f103e3a533027cd9314169f98e32dcb0fad8921c6d5c269`; JSON `7/7`; links `35 total / 11 local / 0 missing`; nested-path positive and four negative object-path guards PASS; exact3 staged0/diff-check PASS.

Acceptance: the post-correction Combined Local Gate is Green; Search and Audit corrections and their separate slice evidence are recorded; fresh separate Sol Search/Audit reviews, separate slice decisions, parent General Sol review, and Orchestrator acceptance remain pending.

Remaining: fresh separate Sol Search and Audit reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance.

Historical post-correction Combined Local Gate evidence: offline frozen install PASS; Node syntax `4/4` PASS; product framing focused `8/8` PASS; Search correction focused new Satteri/reader/DOM `3/3` and existing HTML/Search `2/2` PASS; patch-lock-installed helper binding PASS; approved-retry build `42` pages with artifact/site PASS. Audit blocks were `71,066` bytes each, byte-identical, SHA `4128251cd29dfb2e9f103e3a533027cd9314169f98e32dcb0fad8921c6d5c269`; JSON `7/7`; links `35/11/0`; nested path `5/5`; golden parity `311/348` and `374/418`. This evidence belongs only to the source-change-before snapshot and is not current.

Remaining: fresh separate Sol Search and Audit reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance.

Next Action: request fresh separate Sol Search/Audit reviews against this exact22. Do not claim Accepted, Decided, or Released.

## Historical pre-exact5 P23-001 handoff — Round29 Combined Local Gate Green / Fresh Separate Sol Reviews Requested

Updated At: 2026-08-25T13:56:20+09:00

Historical status: `Round29 Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-review Requested — parent not Accepted`. This block records the superseded pre-exact5 source snapshot only. Stable `1.2` remains current and Phase 23/`1.3` remains unreleased.

Separate correction inputs from the immediately prior source snapshot remain
distinct: Search Sol Changes Requested `P1=0/P2=1/P3=0`, rooted in the
CommonMark type-6 incomplete block-tag prefix and type-7 no-whitespace tag-name
boundary; Audit Sol Changes Requested `P1=1/P2=0/P3=0`, rooted in non-unique
`nextAvailableAt` generation for positive-attempt prepared rest rows. Counts are
not aggregated.

Combined Local Gate evidence on the frozen pre-management exact22 is PASS:
offline frozen install; Node syntax `4/4`; product framing `8/8`; Search/
CommonMark focused `2/2`; full Website `140/140` with `0` failures in
`270020.279299ms`; Website check `41` pages with `0` errors/warnings/hints;
fresh build `42` pages with static artifact boundary PASS and embedded site
check `41` PASS; explicit site check `41` PASS; release source/artifact PASS;
`bash -n` and version baseline `published=1.2.0 historical=1.1.0` PASS;
PHPUnit `30/30` and `250` assertions; Mago format PASS; canonical lint PASS
with `186` baseline-filtered and `0` new issues; verify-baseline PASS; analyze
exit `0` with existing `71` issues (`46` warning / `25` help); and PHP
management-ID guard with no matches. Only existing nonfatal chunk-size and
route-priority warnings remain.

Search evidence remains separate: patch-lock-installed binding PASS with patch
`11d1ac9...`, lock patch hash equal, and installed helper SHA `197f23de...`.
Audit evidence remains separate: current exact3 Round29 block `70,561` bytes,
SHA-256 `c60bfad4977987c25c76340475a25192ebe89cdce31b26dd958719132d4d97bb`,
byte-identical; JSON `7/7`; Markdown links `35 total / 11 local / 0 missing`;
Node+Docker PHP scope `311/348` with digest
`d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`; Access
`374/418` with requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`;
and six scheduling branches/external-wire guards PASS. One overly literal Audit
substring assertion failed in a diagnostic harness and the equivalent
12-needle guard then PASSed; this was not a product or gate failure. Earlier
`ERR_PNPM_INVALID_PATCH` and `31/32` reader results remain Historical
diagnostic evidence only.

### Historical prior Local Gate Green evidence (superseded source snapshot)

The following prior evidence is retained for auditability only and cannot be
used as current evidence: offline frozen install PASS;
Node syntax `4/4 PASS`; product-framing focused `8/8 PASS`; changed CommonMark
focused `5/5 PASS`; full Website `140/140 PASS` with `0` failures; Website
check `41` pages, `0` errors/warnings/hints; fresh build `42` pages with static
artifact boundary and embedded site check `41` PASS; explicit site/source/
artifact checks PASS; version baseline `published=1.2.0 historical=1.1.0`
PASS; PHPUnit `30/30` and `250` assertions PASS; Mago format, canonical lint,
verify-baseline, and PHP management-ID guard PASS; analyze exit `0` with
existing `71` issues (`46` warning / `25` help). Only existing nonfatal
chunk-size/route-priority warnings remain.

Audit exact3 inclusive-heading extraction through the LF before the next `##`
is `65,269` byte-identical bytes with SHA-256
`6129ff07ffdf1ae6f50030836a5c5e9e30240cb2a8b0c372786645110b0b951c`; JSON
`7/7`; Markdown links `35 total / 11 local / 0 missing`; Node/Docker PHP parity
and negative matrices PASS. Worker evidence `14d07ea5...` is not reused because
its extraction was shifted by one byte despite the matching byte count.

Freeze evidence: branch `agent/p23-001-blackops-1-3-feasibility`;
`HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`; exact22 paths; staged
`0`; no repository-scoped temporary path; active Blume new patch; and
`git diff --check` PASS. The inline Node link-regex diagnostic was corrected
by an equivalent passing guard, and direct Git checks passed after a separate
aggregate diagnostic hit sandbox `EPERM`; neither is a product or gate failure.

Historical prior exact7 hashes for the superseded Local Gate candidate were:
Search patch `896b653128be21f0698848ee7a58b9b53b7c34a0ba0bd24a36f827f776e46f14`,
lock `088c9b2f46112e101460218d701968796b38fc251b62203445f60f853107d415`,
reader `4f74827dc8b6a66346ed7a2f74633fa600a56cea2f5fbd49116f8ffb1a4cce3d`,
test `e63ee1dc6f4c3a6b16488ec7a3ef17bbc026ba5375f0f8649dbab3fd7e3c1cc9`;
Audit D145 `f376f71dc476972abcf57c25491f49ccfd288cbb36c30961266c9a3fabab37a9`,
Spec105 `e005b49bc2c1d4f24465ac53ed8f084f786b00cca5dddea754ef7a49d9bc86d0`,
Spec106 `8b406ab4aef1c24a11ec1a91370a4b0ede5b53d82ac6f0b513274dbdfdf4e7e2`.

### Historical post-correction exact7 freeze (source-change-before snapshot)

Management owns exact5 (`STATE.md`, `TODO.md`, parent Task, parent Report, and
this README). Search exact4 and Audit exact3 remain disjoint frozen source
slices, and the other17 paths remain byte-for-byte frozen. Current Search hashes
are patch `ba3218266569613f93a0e0275b3f679d34c43efb96f564e5a4674faf3c56c8c7`,
lock `634d955259e7250f269af53785d4768155c95895521f14282677ee6bee4578f8`,
reader `4d6f9c5b5f7eec7cfee13285a0dec44687be789c62064c2220d2e47b6e45f666`,
test `1ba40a38557fa165664b2d4a5d2e81dd885ab2226fcb257ddaf40529b1ab2a9b`;
current Audit hashes are D145 `b7903d679f60b5af9d7eb6974e1ff0346042af6dbd8de5077b5f570c3ae2991f`,
Spec105 `b4c09319563cb590161b3fa8c93ff8d99e9cbe42277b2bb0c9d3cc5d54892a4e`,
Spec106 `2934acf7c135664342dc0233e45366ef875c34caa0e58002f9e490892ff86e14`.

Historical post-correction gate evidence was Green: Website `142/142`, check `41`,
approved-retry build `42` with artifact/site `41`, PHP `30/30` and `250`
assertions, Mago and release/source/artifact guards PASS; Audit `71,066` bytes
each with SHA `4128251cd29dfb2e9f103e3a533027cd9314169f98e32dcb0fad8921c6d5c269`,
JSON `7/7`, links `35/11/0`, and golden parity `311/348` plus `374/418`;
exact22, staged0, HEAD/main, diff-check, and exact5 links `271/259/0` PASS.
No source, test, Browser, CI, commit, stage, push, deploy, publish, tag, or
release action is claimed in this management sync.

Acceptance checklist:

- [x] Fresh separate Sol correction inputs, counts, and ownership are recorded without aggregation.
- [x] Search exact4 correction and focused Search evidence.
- [x] Audit exact3 correction and static Audit evidence.
- [x] Combined source-changing Local Gate for frozen exact22.
- [ ] Fresh separate Sol Search and Audit re-reviews (requested).
- [ ] Separate slice decisions.
- [ ] Parent General review.
- [ ] Orchestrator Acceptance.

Remaining: fresh separate Sol Search and Audit reviews; separate slice decisions;
parent General Sol review; Orchestrator acceptance.

Next Action: Request fresh separate Sol Search/Audit reviews against this exact22.
Do not claim Accepted, Decided, or Released.

## Historical P23-001 prior post-gate Local Gate checkpoint — superseded

Updated At: 2026-08-25T10:19:26+09:00

Status: `Round29 Audit Local Gate Green / Sol Re-review Requested; Round29 Search Local Gate Green / Sol Re-review Requested — parent not Accepted`. Stable `1.2` remains current and Phase 23/`1.3` remains unreleased. Search and Audit remain separate. Prior Changes Requested/Correction In Progress and prior gate snapshots are Historical evidence for their source snapshots and are not reusable; Local Gate Green is an Orchestrator gate result only, and the parent is not Accepted, Decided, or Released.

Fresh separate Sol Search and Audit re-reviews are requested after this new
candidate gate. Prior Sol Changes Requested findings remain Historical
correction inputs and are not aggregated.

Orchestrator evidence (start reference `2026-08-25T10:03:33+09:00`): offline
frozen `pnpm install` PASS; Node syntax `4/4 PASS`; focused product-framing
tests `8/8 PASS`; focused Search/Satteri/legacy regression tests `9/9 PASS`;
full Website test `139/139 PASS`; Website check `0 errors / 0 warnings /
0 hints`; sandbox build first failed only `listen EPERM: operation not permitted
0.0.0.0`, then approved sandbox-outside rerun PASSed with `42` pages, static
artifact boundary PASS, and site check `41` pages PASS; existing nonfatal
chunk/route warnings only; explicit `site:check`, `release:check:source`, and
`release:check:artifact` PASS; `bash -n` and version baseline PASS
(`published=1.2.0 historical=1.1.0`); PHPUnit `30 tests / 250 assertions`
PASS; Mago format PASS; canonical lint PASS with `186` baseline-filtered/no
issues; verify-baseline PASS; analyze exit `0` with existing `71` issues
(`46 warning / 25 help`); PHP management-ID guard no matches.

Audit exact3 evidence: current block `52,679` bytes, SHA-256
`a2734271a864c24fd4f1932e4a0b0b7c2fb133fbd6c54822b2a7a135eb80fa1d`; JSON
`7/7`; Markdown links exact3 union `35 total / 11 local / 0 missing`.
Node and Docker PHP golden parity PASS: scope JCS/LP `311/348`, digest
`d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`; access
JCS/LP `374/418`, requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`.

Freeze evidence: branch `agent/p23-001-blackops-1-3-feasibility`; `HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`; exact22 paths; staged `0`; no repository-scoped temporary path is present beyond the exact22 worktree; pre-existing `/tmp` review evidence remains and was neither deleted nor counted as worktree scope; `git diff --check` PASS. Browser, remote CI, runtime implementation, deploy, publication, and release were not run or claimed.

Management owns exact5 (`STATE.md`, `TODO.md`, parent Task, parent Report, and
this README). Search exact4 and Audit exact3 remain disjoint frozen source
slices, and the other17 paths remain byte-for-byte frozen. Frozen Search hashes
are patch `ae8c375cb95592810d917bf93f18895bedc1e58536b047cb872777b224566543`, lock `dd111150c4a5e4ebda8f8cc268202dd550cf8d812e4a4d1e1372ff4d88f49107`, reader `6e808dde20ba2801e050bec8abeea5223a2f247e50e759535cf94dfc6688463f`, test `6e9ab1cce442122293de6cc2d4de501244b02c603e36cafc51531de62b377924`; frozen Audit hashes are D145 `0f9574b12156ece70762c0bfbcec9cfc8f2a33c0854abb7f8c5eaaa4a09ab8f7`, Spec105 `bc4a184f6539c976fea4580d2d8af8de084913646b662ec9e1e453e3076d5ad8`, Spec106 `5b641bd94416f0418dd258e9595cf05aba012786767e22353bcaa11ac5be0050`.

No source, test, build, Browser, CI, commit, stage, push, deploy, publish, tag,
or release action is claimed in this management sync; the evidence above is
the Orchestrator's recorded gate result.

Acceptance checklist:

- [x] Fresh separate Sol findings, counts, and correction ownership/status are recorded without aggregation.
- [x] Search exact4 source corrections and new Search Local Gate.
- [x] Audit exact3 source corrections and new Audit Local Gate.
- [ ] Fresh separate Sol Search and Audit re-reviews.
- [ ] Separate slice decisions.
- [ ] Parent General review.
- [ ] Orchestrator Acceptance.

Remaining: fresh separate Sol Search/Audit read-only re-reviews; separate slice
decisions; parent General review; Orchestrator Acceptance; and, after
acceptance, P23-001R and ordered Phase 23 work.

Next Action: freeze the new exact5 and exact7 hashes and request fresh separate
Sol Search and Sol Audit read-only reviews; then record separate slice
decisions, request parent General review, and proceed to Orchestrator
Acceptance. Do not claim Accepted, Decided, or Released.

## Historical P23-001 post-gate completion checkpoint — Round29 Local Gate Green / Sol Re-review Requested (superseded)

Historical status at this superseded checkpoint was `Round29 Audit Local Gate Green / Sol Re-review Requested; Round29 Search Local Gate Green / Sol Re-review Requested — parent not Accepted`. Its Orchestrator evidence and hashes remain Historical evidence for the prior source snapshot and are not reused.

Updated At: 2026-08-25T01:31:39+09:00

Status: `Round29 Audit Local Gate Green / Sol Re-review Requested; Round29 Search Local Gate Green / Sol Re-review Requested — parent not Accepted`. Stable `1.2` remains current and Phase 23/`1.3` remains unreleased. Search and Audit remain separate. Local Gate Green is an Orchestrator gate result only; the parent is not Accepted, Decided, or Released.

Search Changes Requested remains `P1=0/P2=2/P3=0`; Audit Changes Requested
remains `P1=6/P2=0/P3=1`. Search P2-1 and Audit P1-1 through P1-6/P3-1
source corrections plus fresh Local Gates are complete on the frozen candidate;
Search P2-2 management status drift is closed. Fresh separate Sol Search/Audit
re-reviews remain pending.

Orchestrator evidence: offline frozen `pnpm install` PASS after the initial
patch hunk failure was corrected; focused Search `4/4 PASS`; full Website
`135/135 PASS`; Website check `41 pages, 0 errors / 0 warnings / 0 hints`;
the first sandbox build failed only with Astro local font server listen
`EPERM`, while an approved out-of-sandbox fresh build PASSed `42` pages with
artifact/site embedded checks PASS for `41` pages; explicit site/source/artifact
guards PASS; `bash -n` and version baseline PASS (`published=1.2.0
historical=1.1.0`); PHPUnit `30 tests / 250 assertions` PASS; Mago format PASS,
lint `186` baseline-filtered/no issues, verify-baseline PASS, analyze exit `0`
with existing `71` issues (`46 warning / 25 help`); PHP management-ID guard no
matches. Audit Round29 exact3 identical block is `47,067` bytes, SHA-256
`f4546c064bacf9fcb387e9d535221a545d5a9b0056d8b6a6c113589222b80556`, JSON
`7/7`, local links `11/11`, and marker/status guards are clean. Exact22,
staged `0`, `HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`, and
`git diff --check` PASS; installed Blume resolves to the current patch hash.

Frozen source hashes before this exact5 edit: Search patch
`3c1038e493dd4eed5f400c88865d55b109d22e8ab059cdfc2cdf62c19b7cc305`, lock
`81b21213af7388ae90c8e8dd577181bddc518ab10499cb377dc140d8beeaf18d`, reader
`67eee43f8f5365507a4f6835dc9e1284ad148fd3bdaf872486cac2e48ba1fa14`, test
`5a8754fd10b21fbdc66dc35983012591737e7830f432965c26b61d3731c321e4`; Audit
D145 `869c39e535b6014f70c42a5ea4a33d7a2c73aeace421fa6be15a0b69ec29c670`,
Spec105 `74c6ecff785deeb536e6ced9155154d7cfc9fc8fc943ed173692a45052784eda`,
Spec106 `b1eb05c5a762a71861dff67345581c0a095d58ba74a210217d7c7e99f30c22bf`.

Management owns exact5 (`STATE.md`, `TODO.md`, parent Task, parent Report,
and this README). Search owns exact4, Audit owns exact3, and the other17 paths
are byte-for-byte frozen. Prior gate and correction/review blocks are
Historical evidence. No Browser, remote CI, commit, stage, push, deploy,
publish, tag, release, test, or build action is claimed in this sync.

Acceptance checklist:

- [x] Search P2-1 source correction and fresh Search Local Gate.
- [x] Audit P1-1 through P1-6/P3-1 source correction and fresh Audit Local Gate.
- [x] Search P2-2 management status drift and exact status synchronization.
- [ ] Fresh separate Sol Search and Audit re-reviews.
- [ ] Separate slice decisions.
- [ ] Parent General review.
- [ ] Orchestrator Acceptance.

Remaining: fresh separate Sol Search/Audit re-reviews; separate slice
decisions; parent General review; Orchestrator Acceptance; and, after
acceptance, P23-001R and ordered Phase 23 work.

Next Action: freeze exact22/current hashes and route separate fresh Sol Search
and Audit reviews, then record slice decisions and request parent General
review. Do not claim parent Accepted, Decided, or Released.

## Historical P23-001 current post-gate correction packet — Round29 Sol re-review Changes Requested (superseded)

Historical status at this superseded checkpoint was `Round29 Audit Sol Re-review Changes Requested / Correction In Progress; Round29 Search Sol Re-review Changes Requested / Correction In Progress — parent not Accepted`.

Updated At: 2026-08-24T19:22:59+09:00

Status: `Round29 Audit Sol Re-review Changes Requested / Correction In Progress; Round29 Search Sol Re-review Changes Requested / Correction In Progress — parent not Accepted`. Stable `1.2` remains current and Phase 23/`1.3` remains unreleased. Search and Audit remain separate. The prior Local Gate Green checkpoint is Historical evidence and is not reused.

Fresh Search verdict is Changes Requested `P1=0/P2=2/P3=0`: P2-1 covers
partial-TAB/list-continuation surplus visual indent and paragraph/block-boundary
state, requiring actual renderer parity across production Search, Raw rewrite,
and reader guard plus non-self-oracle fixtures; P2-2 is management status drift
closed by this correction. Fresh Audit verdict is Changes Requested
`P1=6/P2=0/P3=1`: P1-1 covers attempt-32 external success/response-loss
reconciliation before terminalization; P1-2 finite Query errors and
precedence; P1-3 one Hold domain; P1-4 expired Outbox token and equality
boundary; P1-5 the policy-unavailable strictly-newer/same-version fork; P1-6
volatile status excluded from normative exact3, with management authority and
exact3 status simplified to avoid a hash/gate cycle; P3-1 truncation warning
line removal and guard.

The current management ownership is exact5: `STATE.md`, `TODO.md`, the parent
Task, the parent Report, and this Specification README. Search owns exact4,
Audit owns exact3, and the other17 paths are byte-for-byte frozen. No source
correction completion, new gate Green result, parent acceptance, or external
action is claimed in this management packet.

## Historical P23-001 current checkpoint — Round29 Local Gate Green / Sol Re-review Requested

Updated At: 2026-08-24T18:48:37+09:00
Round29 Correction Completed At: 2026-08-24T18:48:37+09:00

Status: `Round29 Audit Local Gate Green / Sol Re-review Requested; Round29 Search Local Gate Green / Sol Re-review Requested — parent not Accepted`. Stable `1.2` remains current and Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate and are not aggregated. Local Gate Green is an Orchestrator gate result only; the parent is not Accepted, Decided, or Released.

Prior separate Sol findings remain separate: Search Changes Requested
`P1=0/P2=1/P3=0` for one scanner root covering partial-TAB visual columns and
lone-CR physical-line handling; Audit Changes Requested `P1=4/P2=1/P3=0` for
Query precedence, Ingress partition binding, Outbox max-attempt state,
Retention v1 truth table, and Anchor/Retention time bounds. Fresh separate Sol
Search/Audit re-reviews are requested after the new gate.

Orchestrator fresh new-candidate Local Gate evidence: sandbox install first
stopped on an SQLite permission failure; after approval, offline frozen install
PASS. Node syntax `4/4 PASS`; Website tests `133/133 PASS`; Website check
`0 errors/0 warnings/0 hints`; fresh build `42` pages, Artifact boundary PASS,
site check `41` pages PASS; separate site/source/artifact release checks PASS;
`bash -n` and version baseline PASS (`published=1.2.0 historical=1.1.0`);
PHPUnit `30 tests / 250 assertions` PASS; Mago format PASS, canonical lint PASS
(`186` baseline-filtered/no issues), verify-baseline PASS, analyze exit `0`
with existing `71` issues (`46 warning / 25 help`); PHP management-ID no
matches. Only existing nonfatal Vite chunk/route warnings remain.

Audit current-only JSON vectors `7/7`; Markdown links `35 total / 11 local /
0 missing`; Node+Docker PHP goldens PASS (JCS/LP `311/348`, digest
`d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`, access
JCS/LP `374/418`, requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`);
Audit identical-block extraction `43,425` bytes, SHA-256
`e2648fe19625da051e711a88bc30965bd994224569ac47190b2c76dcc10d9f88`.

The full new-candidate Local Gate is Green on this candidate. Prior complete
Website/Audit gates are Historical evidence for older source snapshots and are
not reused. Browser, remote CI, runtime implementation, deploy, publication,
and release were not run or claimed. This management worker did not rerun the
gate.

Final Search hashes: patch
`344035e25063014a138c2b549b63c4c1f12c5bb0ad2f3c1077ab910b149958da`, lock
`1eaff6dd4fa7b1af78820175328cab33a41d4c9264d790f81b08b4bb94d5bced`, reader
`5aa75a842d8969cef1842f2f42d720d25027a2467ed4f1ca0f02868a561b9295`, test
`484ee970cec13740920ec4702a7b50be069484de1c6bb060a81b00ef1429feba`.
Final Audit hashes: D145
`f6d9c2987398f3834485856af1ecb6bc16dc4440a05e3967f0a07102c654db70`, Spec105
`a1c86b19757e178a02b7f10733181a3775c1f4e926f5d451d8bf0ccaaacc48ca`, Spec106
`03df98b3e17ef2931404bb2e3c3970576ab3e77f2ead1c770b14014e4e36d624`.

Final freeze evidence is branch `agent/p23-001-blackops-1-3-feasibility`,
`HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`, staged `0`, exact22,
temporary files absent, and `git diff --check` PASS. Browser, remote CI,
runtime implementation, deploy, publication, and release were not run or
claimed in this phase. This management worker did not rerun the gate and made
no test, build, network, commit, stage, or push action.

Acceptance checklist:

- [x] Search correction and focused Satteri parity evidence checked.
- [x] Audit correction and focused query/link/diff evidence checked.
- [x] Separate Sol verdicts and correction ownership/status checked.
- [x] Full new-candidate Local Gate (Orchestrator evidence recorded above).
- [ ] Fresh separate Sol Search/Audit re-reviews.
- [ ] Slice decisions, parent General review, and Orchestrator Acceptance.

Remaining: fresh separate Sol Search/Audit re-reviews; slice decisions; fresh
parent General review; Orchestrator Acceptance; and, after acceptance,
P23-001R and ordered Phase 23 work.

Next Action: request fresh separate Sol Search/Audit re-reviews against this
gated exact22 snapshot; record separate slice decisions, then request parent
General review and Orchestrator Acceptance. Do not claim parent Accepted,
Decided, or Released.

## Historical P23-001 current correction checkpoints — Round29 fresh Sol Search / Audit

Updated At: 2026-08-23T03:33:10+09:00

Then-current status: `Round29 Audit Re-review Changes Requested / Correction In Progress; Round29 Search Re-review Changes Requested / Correction In Progress — parent not Accepted`. Stable `1.2` remains current and Phase 23/`1.3` remains an unreleased proposal. Search and Audit are separate and are not aggregated. The parent is not Green, Accepted, Decided, or Released.

Fresh Search verdict: Changes Requested `P1=0/P2=2/P3=0`. S1 requires
tab-aware CommonMark visual column/contentColumn handling, where TAB advances
from the current column to the next four-column tab stop across ordered/
unordered nesting, space+TAB, dedent, and real renderer/helper parity. S2
requires one shared Source physical-line/container-fence state for list,
blockquote, nested same-line fences, LF, CRLF, and lone-CR input across title,
fence range, raw image, placeholder, and production Raw rewrite.

Fresh Audit verdict: Changes Requested `P1=6/P2=3/P3=0`. A1 requires a
reconstructible three-page cursor chain with non-circular query-scope digest
and separate head/last/prior-cursor bindings. A2 requires one sole-current
Round29 and active non-Historical base for Access/Outbox/Retention exact wire,
enum, and formula. A3 requires active lease token, expected version, and exact
+1 CAS for `leased -> relayed|poisoned`. A4 requires attempt-32 unavailable
resolution and a total Anchor state/claim/lease/expiry/crash table. A5 requires
deterministic Retention truth-table behavior, clock rollback rejection, policy
unavailable representation, and bounded reversal. A6 synchronizes the exact-
three current status block/header. A7 closes finite query validation/cursor
error wire and precedence. A8 closes binary protectedBody versus logical/DB/
JSON representations and cap/timestamp/key/lease bounds. A9 closes policy/hold
snapshot schema, order, no-hold handling, and domain hash.

Reviewer freeze record: branch `agent/p23-001-blackops-1-3-feasibility`,
`HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`, staged `0`, exact22,
specified source hashes, and `git diff --check` PASS, with no edit before the
review handoff. Search positive evidence is current frozen 40 non-Landing
Source→Raw→Search→HTML→llms-full parity, Stable boundary, and management
separation. Audit positive evidence is A2, normal query caps/key window,
§9.6.1 receipt/hash, hold/deletion separation, Stable boundary, and three-file
block parity.

The previous complete Search/Website Gate is Historical evidence for the prior
source snapshot. Any source correction creates a new candidate and requires the
complete Search/Website Gate again. Runtime, Browser, remote CI, deploy,
publication, and release are unverified and unexecuted. This packet is the
exact12 union: management5, Search exact4, and Audit exact3; other10 paths are
frozen.

Source snapshot hashes: Search exact4 — patch
`e1da3b29c6a033058e018bb7c4b57da8ec6078acd0a1036779c9efc40f506a3c`, lock
`108d627a45fee16934167fef909515eda845c3c3c9bf9e0dcf6949da99fef075`, reader
script `2927257dba0b1efc40df5e1bfa63fc5473074f99f4821a2a39579951b0960292`,
reader test `46c8652cf1adcff9fdff6a14eb7dd4a844632984f433767325d0c3a61890c4a9`;
Audit exact3 — D145
`6c952d4b29337092061cf99b0458ec6523a3c7f6f14afd6c4826a67fa04adca5`, Spec105
`432f83a9c354e970663e0c09a0004c60d4455568c897572ae5cda611c32abc86`, and
Spec106 `867c268bc6e1d5fc89b87f259a44f2363f8812d8e78dd78a661b3128296d796b`.

Management hashes before this edit are STATE `c5ac26f32e2acf80952417442fafed9324f272296c7dd0814778944a94e71aec`, TODO `30bbe0d7539b5def8558bb81142e05b92e9cb1dd53c9b0a46f627354cdb74182`, Task `afc1b564c9d28da408248845125202a7cf6627dd7e8ca10355556891b395d567`, Report `8c3af58313f63de7b02b7a4951ecba4d1dcd6023352abf1d74aab8e5bc8bd172`, and README `f8300f0facb9ef2c42bdf1aea97e381d1fc430a1de7be0825590e6b66ec37930`.

Acceptance checklist:

- [ ] Search S1/S2 correction and fresh complete Search gates.
- [ ] Audit A1–A9 current-only correction and static guards.
- [x] Fresh separate Sol verdict and correction ownership/status recorded.
- [ ] Final management sync and fresh separate Sol re-reviews.
- [ ] Parent General review and Orchestrator acceptance.

No runtime implementation, PHPUnit/Mago/Docker/Browser/remote CI, deployment,
publication, or release claim is made for this phase.

Remaining: exact4/exact3 Luna corrections; Orchestrator new-candidate
gates/static checks; final exact5 sync; fresh separate Sol Search/Audit
re-reviews; slice decisions; parent General review and Acceptance; only after
acceptance P23-001R and ordered downstream work.

Next Action: keep exact22 frozen, complete disjoint Search/Audit corrections,
run new-candidate gates/static checks, then request fresh separate Sol reviews.
Do not claim parent Green, Accepted, Decided, or Released.

## Historical Round 24 proposal-boundary wording

Stable `1.2`が現在のRelease境界であり、FrankenPHP Worker Modeは既存のdefault HTTP runtimeとしてcurrent capabilityである。Symfony-backed `list`もStable currentであり、Phase 23のBlackOps-owned replacementでは`ls`だけが新規名である。Structured Log／OpenTelemetryの既存Operation／Correlation／Trace／Span相関と`ExecutionScopedLogger`／ObservabilityもStable currentである。Phase 23の文書はP23-001 Discoveryで整理した未Release Proposal laneであり、Round 24 Correction Implemented / Review RequestedからAccepted／Releaseへ移行するまで新しい四surface Audit相関契約／Audit Trail、CLI replacement、P23-004AのWorker hardening／Classic removal、P23-004B Extension Worker、same-process Deferredをcurrent capabilityや公開docsの主張として扱わない。P23-002AのStable input互換、embedded framework recovery、XML/Markdown wire、shared reservation registry、Audit ingress／`AuditOutboxRecord` protection、closed schema、AuditEventCatalog（`evidenceVersion`、semanticTuples、category/action/owner/fixed-delivery binding、required `policy.auditDelivery`とcatalog deliveryの一致、有限capsを含む）、normalized tenant partition mapping／trusted time、同一8-key partition start boundary、exact anchor triple、multi-partition verifier/context、trusted freshness snapshot、raw-wire verificationもfuture-only contractである。Audit v1はphysical deletion／signed deletion manifest／sealed bytes・keys deletionを含まず、将来P23-003Hへ延期し、compliance claimをしない。Round 6 independent evidence is separate: full review P1=3／P2=4／P3=1 and Audit review P1=2／P2=6／P3=1; Round 7 Changes Requested is historical (General P1=4／P2=1／P3=1、Audit P1=6／P2=6／P3=0), and Round 8 fresh review is historical Changes Requested (General P1=2／P2=1／P3=0、Audit P1=5／P2=4／P3=1); Round 9 through Round 23 reviews are historical, and none is Green／Accepted／Decided.

Historical Round 10 exact-8 checkpoint was `2026-08-19T09:42:10+09:00`.
The Round 9 review label in the historical counts above is not current;
Round 12 through Round 21 were historical review statuses; Round 22 was the
then-current parent checkpoint and is now historical, with Round 21 as the
accepted child. Round 23 through Round 26 are historical; the current parent
has parallel Round28 Audit and Round27 Search lanes.
That historical Round 10 checkpoint synchronized the ProjectionBuildSet v1 publication/root boundary, six
projection states, safe-code 256-pass/257-fail and opaque-id 71-pass/70/72-fail
evidence reachability with nullable null, exact JSON/raw
and XML/Markdown wires, singular segment checkpoint/receipt endpoints, BOPD
v1 canonical/outbox protection, tenant-union AAD bindings, fenced outbox and
AnchorIntent state machines, Stage 3/4/6/7 verifier precedence, nofollow fd
semantics, and non-recursive control-stream ownership. Its historical status was
Round 10 `Correction Implemented / Review Requested`; exact-8 local management
gates were PASS, no 1.3 capability was delivered, and the fresh Sol review plus
Orchestrator acceptance remained.

Historical Round 11 checkpoint was `2026-08-19T13:38:47+09:00`. It synchronized
the closed ProjectionBuildSet root self-hash and immutable content-addressed
generation publication, exact command/route/schedule projection kinds and
field/sort caps, configured-root-only recovery with no uncompiled-source
comparison claim, the `AuditIngressEnvelope` tenantBinding field, closed
`AuditStoredRecord` transport (1..256-byte record/segment IDs, exact
`system:global|tenant:<64 lowercase hex>` partition grammar, AuditRecord-bounded
tenantBinding, and decrypted category/time equality) with BOPD/AAD, nested `AuditReadContext`, manifest
artifact identity/order and raw-versus-semantic hashes, one verifier Stage 0-7
total function, and `AnchorIntent` anchorReceiptId/lease/hash exactness. Its
status was `Correction Implemented / Review Requested`; it was not
Green/Accepted/Decided. Historical Remaining was fresh Sol xHigh general+Audit
review, Orchestrator acceptance, then P23-001R and the ordered downstream DAG.

Round 12 checkpoint was `2026-08-19T14:51:24+09:00`. It was the then-current
`Correction Implemented / Review Requested` status, not Green,
Accepted, or Decided. Round 11 is historical Changes Requested with General
`P1=2/P2=2/P3=0` and Audit `P1=7/P2=3/P3=1`, separately and without aggregation.
Stable `1.2` remains current and every Phase 23 capability is an unreleased
proposal. The current contract has one `ProjectionBuildSet v1` wire, dedicated
command/route/schedule projections, exact `.blackops-projection-build-set.lock`
flock publication/GC, and the optional single `operation:list
--strategy=inline|deferred` filter. Audit uses partition-global
`AuditSegmentState`, canonical BOPD/AAD as ordered LP(U32BE length + exact
bytes) over domain/purpose/version/algorithm/key and clear identity, with pinned
232-byte
`5930076af5418572a4e575943172884775428ad10d024de7eb9fc419bce04b1c` and
344-byte `f3ef3d96598964f7087ffe654ecde4d2e828caebfa981ecb1daa82859ff7c57f`
goldens, no v1 outbox re-encryption, Stage 0-7 plus external `expectedManifest`,
F/G ownership separation, the four-surface `.unavailable` tuple,
`AuditAccessContext`, metadata-only `AuditRetentionState`, and signed
`{role,artifactId}` identity. P23-001S was a separate non-1.3 repository blocker;
the historical Task Packet narrowed `docs/guide/project-cli.md:10` to
observed behavior, or prove the guarantee with
constructor/lazy-factory default/raw/json/xml/md fixtures. The Guide claim is
not positive evidence; those historical instructions are superseded.
Historical Remaining was exact-8 Local Gate freeze, P23-001S creation/execution,
fresh Sol xHigh General+Audit review, Orchestrator acceptance, then P23-001R
and ordered downstream. Next Action is run the fresh exact-8 gates, return
hashes, then route P23-001S before requesting review.

Round 9のexact-8 local guardsは`2026-08-19T09:10:43+09:00`時点でPASSし、
fresh Orchestrator／Sol re-review is the next action recorded for that
historical checkpoint. Its status was Round 9 Correction Implemented /
Review Requested and no 1.3 capability was delivered.
The pre-review correction fixes multi-segment Stable namespace goldens, the
single Stage 0-7 verifier order, exact ingress-envelope fields, list recovery
exit/status exceptions, and the three non-overlapping AnchorIntent crash
fixtures; it does not authorize Accepted/Decided status.

Round 9 additionally fixes the schedule-only complete-artifact exit matrix,
trusted six-state application projection identity/precedence, exact XML and
Markdown byte fixtures (SHA-256
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968` and
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`),
classification-required typed evidence without a secret-absence claim,
durable-only ingress and exact outbox row/AAD/FIFO/lease/poison semantics,
verifier total-function nullability and full signed endpoint refs, fenced
deterministic AnchorIntent recovery, actor/read and nofollow-root fixtures,
checkpoint control events, metadata-only retention, and split checkpoint vs
anchor P3 ownership. Historical evidence was 44 unique existing local targets,
46 cited ranges, and 270 Markdown link occurrences; historical Remaining was
fresh Sol review, Orchestrator acceptance, then P23-001R/downstream.
Anchor recovery fixtures are exactly before external accept, after external
accept/before local receipt persist, and after local receipt persist/before ack;
all retries reuse the same idempotency/object/version/receipt and create no
N+1.
The required chronology is `checkpoint.sealedAt <= checkpoint.signedAt <=
receipt.anchoredAt <= receipt.signedAt <= freshness.observedAt <= verifiedAt <
validUntil`, with equality and one-microsecond-after fixtures.

P23-001のcanonical deliveryはexplicit `required`またはexplicit `durable`
だけを選択し、missing／omitted／unknownはeffect／response前にfail
closedする。Semantic tuple、trusted external verification context、exact
`AuditVerificationResult v1` shape
`{schemaVersion,kind,mode,status,code,scope,exportId,manifestHash,verifiedAt,contextHash,partitions}`、recordedAt authority、raw-wire capsは同じ未Release contractで
あり、現行Stableの実装済み主張ではない。

## 仕様書

| 文書 | 内容 |
| --- | --- |
| [00-framework-identity.md](00-framework-identity.md) | 正式名称、PHP名前空間、Composerパッケージ名 |
| [01-core-model.md](01-core-model.md) | Operation、Value、Envelope、Context、識別子 |
| [02-lifecycle-and-journal.md](02-lifecycle-and-journal.md) | ライフサイクル、Journal、Schema、セキュリティ |
| [03-execution.md](03-execution.md) | Execution Strategy、Transport、Supervision |
| [04-handler-and-result.md](04-handler-and-result.md) | Binding、Validation、Handler、Outcome |
| [05-http.md](05-http.md) | Route、HTTP Binding、Responder、Manifest |
| [06-auth-and-middleware.md](06-auth-and-middleware.md) | 認証、認可、Middleware、入口別Operation |
| [07-project-structure.md](07-project-structure.md) | 探索ディレクトリと推奨プロジェクト構造 |
| [08-registry-and-manifest.md](08-registry-and-manifest.md) | Operation探索、Provider、Manifest、Runtime Registry |
| [09-runtime-and-di.md](09-runtime-and-di.md) | PHP Runtime、PSR-11、Symfony DI、Container Compile |
| [10-logging-and-traceability.md](10-logging-and-traceability.md) | PSR-3 Logger、自動Context、Journal Log、OTel相関 |
| [11-durable-journal-and-transactions.md](11-durable-journal-and-transactions.md) | Named Connection、Transaction Attribute、After Commit、Outbox |
| [12-mvp-scope.md](12-mvp-scope.md) | 最初のVertical Sliceと完了条件 |
| [13-mvp-technical-stack.md](13-mvp-technical-stack.md) | PostgreSQL、HTTP PSR、FastRoute、UID、Console、Logger、Test |
| [14-package-architecture.md](14-package-architecture.md) | Composer Packageの公開単位 |
| [15-source-layout.md](15-source-layout.md) | 責務別Namespaceと内部API境界 |
| [16-namespace-dependencies.md](16-namespace-dependencies.md) | Namespace間の依存方向とDeptrac |
| [17-core-api.md](17-core-api.md) | Marker Interface、Handler、PHP Public API |
| [18-operation-envelope.md](18-operation-envelope.md) | 不変Envelope、Getter、Context委譲 |
| [19-execution-context-api.md](19-execution-context-api.md) | 不変Context、AttemptContext、内部Factory |
| [20-identifier-value-objects.md](20-identifier-value-objects.md) | UUIDv7 ID型、生成Factory、文字列表現 |
| [21-clock-and-time.md](21-clock-and-time.md) | PSR-20、UTC、時刻文字列、順序との分離 |
| [22-journal-record-schema.md](22-journal-record-schema.md) | Journal Envelope、Event名、Sequence、Data |
| [23-journal-record-api.md](23-journal-record-api.md) | JournalRecord、JournalEvent、JournalData、Factory |
| [24-lifecycle-event-data.md](24-lifecycle-event-data.md) | Canonical Payload、Outcome、Error、Empty Data |
| [25-sensitive-projection.md](25-sensitive-projection.md) | Sensitive Filter、Observer Projection、Canonical Store |
| [26-journal-ports.md](26-journal-ports.md) | Observer、Projection、Writer、Reader、Flush |
| [27-journal-sequence-allocation.md](27-journal-sequence-allocation.md) | Sequence管理、競合、欠番、再配送 |
| [28-mvp-lifecycle-events.md](28-mvp-lifecycle-events.md) | Retry、Accepted、Succeeded、Terminal Event |
| [29-handler-result-contract.md](29-handler-result-contract.md) | Completed、Rejected、EmptyOutcome |
| [30-lifecycle-state-machine.md](30-lifecycle-state-machine.md) | Operation State、遷移、Terminal制約 |
| [31-deferred-claim-and-attempt.md](31-deferred-claim-and-attempt.md) | Attempt番号、Lease、開始境界、Fencing |
| [32-worker-crash-recovery.md](32-worker-crash-recovery.md) | Heartbeat、Crash Recovery、Shutdown |
| [33-execution-transport-contract.md](33-execution-transport-contract.md) | Message、Acknowledgement、Claim、分割Port |
| [34-mvp-database-transport.md](34-mvp-database-transport.md) | PostgreSQL Reference TransportとDocker Compose |
| [35-postgresql-transport-schema.md](35-postgresql-transport-schema.md) | Column型、Claim、Index、Migration |
| [36-postgresql-transaction-boundaries.md](36-postgresql-transaction-boundaries.md) | State、Journal、Outcome、Observer配送 |
| [37-postgresql-table-layout.md](37-postgresql-table-layout.md) | Schema、Journal、Outcome、Dead Letter |
| [38-data-retention-and-deletion.md](38-data-retention-and-deletion.md) | Retention、Tombstone、削除制約、Legal Hold |
| [39-retention-runtime.md](39-retention-runtime.md) | Retention CLI、Scheduler、Hold、Audit |
| [40-mvp-delivery-plan.md](40-mvp-delivery-plan.md) | 実装Phase、最初のVertical Slice、品質Tool |
| [41-developer-experience-roadmap.md](41-developer-experience-roadmap.md) | Installed Example、Composer Skeleton、BlackOps CLI、Documentation Website |
| [42-installed-application-boundary.md](42-installed-application-boundary.md) | 独立Consumer Package、Public Composition、Process Boundary |
| [43-installed-application-layout-and-bootstrap.md](43-installed-application-layout-and-bootstrap.md) | Feature-first Skeleton、Application Bootstrap、Local Runtime |
| [44-public-application-bootstrap-api.md](44-public-application-bootstrap-api.md) | Application Builder、HTTP／Console Composition、Failure Contract |
| [45-phase-7-delivery-plan.md](45-phase-7-delivery-plan.md) | Public BootstrapからQuickstart Consumer E2EまでのTask順序 |
| [46-composer-skeleton-publication.md](46-composer-skeleton-publication.md) | Split Repository、Version、Lock、Post-create、Release Pipeline |
| [47-public-http-runtime-configuration.md](47-public-http-runtime-configuration.md) | Build／Database Config、Inline／Deferred HTTP Composition、Fail-fast |
| [48-public-console-kernel-composition.md](48-public-console-kernel-composition.md) | Public Console Kernel、Application Command Composition、Exit Contract |
| [49-feature-first-quickstart-application.md](49-feature-first-quickstart-application.md) | Install直後と同じFeature-first Quickstart Application |
| [50-operation-authoring-and-build-discovery.md](50-operation-authoring-and-build-discovery.md) | Self-handled Operation、Build-time Discovery、Handler自動DI登録 |
| [51-local-runtime-and-consumer-e2e.md](51-local-runtime-and-consumer-e2e.md) | Quickstart Docker Runtime、JSONL Journal、独立Consumer E2E |
| [52-phase-8-delivery-plan.md](52-phase-8-delivery-plan.md) | Post-createからDistribution PublicationまでのPhase 8 Task順序 |
| [53-typed-self-handled-operation-invocation.md](53-typed-self-handled-operation-invocation.md) | Native Value／Optional ContextによるSelf-handled Invocation |
| [54-native-outcome-and-rejection-exception.md](54-native-outcome-and-rejection-exception.md) | Native Outcome／Void Returnと業務拒否Exception |
| [55-project-generators-and-application-migrations.md](55-project-generators-and-application-migrations.md) | Operation／Migration GeneratorとApplication Migration実行境界 |
| [56-phase-9-delivery-plan.md](56-phase-9-delivery-plan.md) | Generator実装からFramework Update SmokeまでのPhase 9 Task順序 |
| [57-documentation-website-delivery-contract.md](57-documentation-website-delivery-contract.md) | 利用者向けSingle Source WebsiteとCloudflare Pages公開境界 |
| [58-phase-10-delivery-plan.md](58-phase-10-delivery-plan.md) | Documentation RenameからCloudflare Pages CloseoutまでのTask順序 |
| [59-documentation-reader-experience.md](59-documentation-reader-experience.md) | 初見導線、Concept Diagram、Tutorial、Glossary、Security、Referenceの読者体験 |
| [60-post-phase-10-roadmap.md](60-post-phase-10-roadmap.md) | Stable 1.1からMiddleware、DB、Diagnostics、Frontend Bridge、Deferred API以降の実装順序 |
| [61-experimental-release-contract.md](61-experimental-release-contract.md) | Public Readiness前の破壊的Minor Release、Version、Documentation、Publication境界 |
| [62-phase-11-delivery-plan.md](62-phase-11-delivery-plan.md) | Release Surface ResetからFramework／Skeleton 1.1.0公開までのTask順序 |
| [63-phase-12-delivery-plan.md](63-phase-12-delivery-plan.md) | Actor ContextからDeferred再認可とPhase CloseoutまでのTask順序 |
| [64-phase-13-delivery-plan.md](64-phase-13-delivery-plan.md) | Named Connection、Build-time AOP、Transaction、After CommitからPhase CloseoutまでのTask順序 |
| [65-operation-diagnostics.md](65-operation-diagnostics.md) | Operation ID相関、内部Diagnostics Aggregate、CLI、Local Viewer、安全な表示境界 |
| [66-phase-14-delivery-plan.md](66-phase-14-delivery-plan.md) | Failure相関からDiagnostics Consumer ExperienceまでのPhase 14 Task順序 |
| [67-operation-frontend-bridge.md](67-operation-frontend-bridge.md) | Operation Object、Frontend Contract Manifest、Typed Fetch、生成／Drift境界 |
| [68-phase-15-delivery-plan.md](68-phase-15-delivery-plan.md) | Frontend ContractからConsumer ExperienceまでのPhase 15 Task順序 |
| [69-deferred-status-and-outcome-api.md](69-deferred-status-and-outcome-api.md) | Public Status Query、Query認可、Retention、HTTP Resource、Generated Polling境界 |
| [70-phase-16-delivery-plan.md](70-phase-16-delivery-plan.md) | Public Query ContractからConsumer ExperienceまでのPhase 16 Task順序 |
| [71-full-stack-reference-application.md](71-full-stack-reference-application.md) | SvelteKit BFF、認証、投稿、Deferred Digestを統合するReference Application |
| [72-phase-17-delivery-plan.md](72-phase-17-delivery-plan.md) | Full-stack Reference ApplicationのTask順序と受入境界 |
| [73-structured-outcome-contract.md](73-structured-outcome-contract.md) | Readonly Nested DTO、Typed List、HTTP／Persistence／Frontendの共通Outcome Shape |
| [74-application-ergonomics.md](74-application-ergonomics.md) | Typed Environment、Bound Client、Console、Session AuthのApplication責任境界 |
| [75-phase-18-delivery-plan.md](75-phase-18-delivery-plan.md) | Application ErgonomicsのTask順序と受入境界 |
| [76-database-seeding.md](76-database-seeding.md) | Framework-owned Seed Command、Seeder／Runner、Build、Generator境界 |
| [77-phase-18-follow-up-delivery-plan.md](77-phase-18-follow-up-delivery-plan.md) | Seeder CoreからConsumer移行までのPhase 18 Follow-up順序 |
| [78-application-runtime-and-bootstrap.md](78-application-runtime-and-bootstrap.md) | Environment File、Classic／Worker SAPI Runtime、Public UUIDv7 Generator境界 |
| [79-phase-18-runtime-follow-up-delivery-plan.md](79-phase-18-runtime-follow-up-delivery-plan.md) | Runtime BootstrapからDependency CloseoutまでのPhase 18 Follow-up順序 |
| [80-reliability-and-delivery.md](80-reliability-and-delivery.md) | Idempotency、Transactional Outbox、Relay／Replayの契約とFailure Matrix |
| [81-phase-19-delivery-plan.md](81-phase-19-delivery-plan.md) | Idempotency CoreからCommunity Board統合までのPhase 19 Task順序 |
| [82-operation-dispatch-and-deferred-authoring.md](82-operation-dispatch-and-deferred-authoring.md) | `#[Deferred]`、Transactional child Operation dispatch、将来のScheduled入口境界 |
| [83-blume-documentation-experience.md](83-blume-documentation-experience.md) | Blume Website、Operation／Journal／Headless Landing、Experimental Notice、利用者向けNavigation |
| [84-documentation-learning-journey.md](84-documentation-learning-journey.md) | 正確性、全Page Navigation、Stable入門動線、Task-oriented Guide、Site UXの段階的改善 |
| [85-documentation-landing-visual-hierarchy.md](85-documentation-landing-visual-hierarchy.md) | Landingの視覚階層、GitHub導線、非対称Feature、Custom Page Link Integrity |
| [86-documentation-brand-expression-and-active-navigation.md](86-documentation-brand-expression-and-active-navigation.md) | Astro参照のLanding Product Expression、Header GitHub導線、Sidebar現在地表示 |
| [87-documentation-second-review-and-feature-parity.md](87-documentation-second-review-and-feature-parity.md) | Landing Feature均等化、第2回Review退行、Website正本整合 |
| [88-executable-stable-onboarding.md](88-executable-stable-onboarding.md) | Stable 1.1完走導線、main Preview Quickstart、Authentication手順 |
| [89-blume-mermaid-rendering.md](89-blume-mermaid-rendering.md) | Mermaid PageのMDX生成、Blume native描画、Artifact／Browser Contract |
| [90-documentation-third-review-accuracy.md](90-documentation-third-review-accuracy.md) | 第3回ReviewのLanding／Banner、P1正確性、Internal Link H1 Guard |
| [91-mermaid-diagram-legibility.md](91-mermaid-diagram-legibility.md) | Mermaid DiagramのDesktop可読幅、Mobile局所横Scroll、Browser寸法Contract |
| [92-documentation-review-agent.md](92-documentation-review-agent.md) | Read-only Documentation Reviewer、Evidence Hierarchy、Browser Review、Finding Format |
| [93-implicit-inline-ephemeral-outcome.md](93-implicit-inline-ephemeral-outcome.md) | Ephemeral Outcomeの暗黙Inline、Security Guard、ExecuteWith互換境界 |
| [94-journal-documentation.md](94-journal-documentation.md) | Journal独立Guide、Observed JSONL、Security、OpenTelemetry将来構想 |
| [95-task-oriented-operations-documentation.md](95-task-oriented-operations-documentation.md) | Testing、Deployment、ConsoleCommand、Outbox、BlackOps CLIの実行可能な利用者Journey |
| [96-documentation-site-ux.md](96-documentation-site-ux.md) | Blume Callout、Code Copy、Previous／Next、Edit Link、日本語Font、Responsive Article UX |
| [97-documentation-editorial-style.md](97-documentation-editorial-style.md) | 全Public Pageの表記Guideline、一般語の日本語化、Page種別Reader Contract、文章編集Pass |
| [98-scheduled-application-operation.md](98-scheduled-application-operation.md) | ScheduledBy、Schedule Context、Cron／Timezone、Misfire／Overlap、Occurrence、Actor、BlackOps CLI |
| [99-tenant-isolation-and-protected-operation-data.md](99-tenant-isolation-and-protected-operation-data.md) | TenantRef、Tenant伝播、Default-deny Data Read、Encrypted Envelope、Rotation、Breaking Upgrade境界 |
| [100-structured-logging-and-opentelemetry.md](100-structured-logging-and-opentelemetry.md) | Structured Record v1、W3C Trace、OpenTelemetry Trace／Metric、Health／Readiness、Local Collector／Local Grafana LGTM境界 |
| [101-framework-owned-transaction-proxy.md](101-framework-owned-transaction-proxy.md) | Framework-owned Transactional／AfterCommit Proxy contract |
| [102-phase-21-delivery-plan.md](102-phase-21-delivery-plan.md) | Phase 21 proxy replacement delivery order and removal gate |
| [103-stable-1-2-release-plan.md](103-stable-1-2-release-plan.md) | Stable 1.1 preservation and main 1.2.0 Release Candidate baseline |
| [104-documentation-release-lifecycle-and-information-architecture.md](104-documentation-release-lifecycle-and-information-architecture.md) | Release Authority、全公開Source／Artifact guard、目的別Information Architecture、Task／Review契約 |

### Phase 23 Proposal lane（未Release）

| 文書 | 内容 | Status |
| --- | --- | --- |
| [105-blackops-1-3-capability-contract.md](105-blackops-1-3-capability-contract.md) | BlackOps `1.3` CLI、runtime、Audit TrailのCapability Contract | Proposal — P23-001 Accepted; Stable `1.2` current / BlackOps `1.3` unreleased proposal |
| [106-phase-23-delivery-plan.md](106-phase-23-delivery-plan.md) | Phase 23のDAG、Migration、独立Gate、Release境界 | Proposal — P23-001 Accepted; Stable `1.2` current / BlackOps `1.3` unreleased proposal |

## 決定の参照

| Decision | 内容 | Status |
| --- | --- | --- |
| [D001](../decisions/001-operation-definition.md) | Operationの定義 | Decided |
| [D002](../decisions/002-operation-lifecycle.md) | Operationのライフサイクル | Decided |
| [D003](../decisions/003-execution-strategy.md) | Execution Strategyと再現可能なJournal | Decided |
| [D004](../decisions/004-journal-schema-and-security.md) | Journal Schemaとセキュリティ | Decided |
| [D005](../decisions/005-operation-value-and-validation.md) | OperationValueとバリデーション | Decided |
| [D006](../decisions/006-handler-and-outcome.md) | HandlerとOutcome | Decided |
| [D007](../decisions/007-supervision-policy.md) | Supervision Policy | Decided |
| [D008](../decisions/008-http-routing-and-binding.md) | HTTP RoutingとBinding | Decided |
| [D009](../decisions/009-execution-context.md) | ExecutionContext | Decided |
| [D010](../decisions/010-authentication-and-middleware.md) | AuthenticationとMiddleware | Partially Superseded by D095 |
| [D011](../decisions/011-project-structure.md) | Project Structure | Decided |
| [D012](../decisions/012-operation-registry-and-manifest.md) | Operation RegistryとManifest | Decided |
| [D013](../decisions/013-runtime-and-dependency-injection.md) | PHP RuntimeとDependency Injection | Decided |
| [D014](../decisions/014-logging-and-traceability.md) | LoggingとTraceability | Decided |
| [D015](../decisions/015-log-delivery-and-retention.md) | Log DeliveryとRetention | Decided |
| [D016](../decisions/016-durable-journal-transaction.md) | Durable JournalとTransaction | Partially Superseded by D096 |
| [D017](../decisions/017-mvp-scope.md) | MVP Scope | Decided |
| [D018](../decisions/018-mvp-technical-stack.md) | MVP Technical Stack | Decided |
| [D019](../decisions/019-framework-name.md) | Framework Name | Decided |
| [D020](../decisions/020-package-architecture.md) | Package Architecture | Decided |
| [D021](../decisions/021-source-layout.md) | Source Layout | Decided |
| [D022](../decisions/022-namespace-dependencies.md) | Namespace Dependencies | Partially Superseded by D142 |
| [D023](../decisions/023-core-api-shape.md) | Core API Shape | Decided |
| [D024](../decisions/024-operation-envelope-api.md) | Operation Envelope API | Decided |
| [D025](../decisions/025-execution-context-api.md) | ExecutionContext API | Decided |
| [D026](../decisions/026-identifier-value-objects.md) | Identifier Value Objects | Decided |
| [D027](../decisions/027-clock-and-time.md) | Clock and Time | Decided |
| [D028](../decisions/028-journal-record-schema.md) | Journal Record Schema | Decided |
| [D029](../decisions/029-journal-record-api.md) | Journal Record PHP API | Decided |
| [D030](../decisions/030-lifecycle-event-data.md) | Lifecycle Event Data | Decided |
| [D031](../decisions/031-sensitive-projection.md) | Sensitive Projection | Decided |
| [D032](../decisions/032-journal-ports.md) | Journal Ports | Decided |
| [D033](../decisions/033-journal-sequence-allocation.md) | Journal Sequence Allocation | Decided |
| [D034](../decisions/034-mvp-lifecycle-events.md) | MVP Lifecycle Events | Decided |
| [D035](../decisions/035-handler-result-contract.md) | Handler Result Contract | Decided |
| [D036](../decisions/036-lifecycle-state-machine.md) | Lifecycle State Machine | Decided |
| [D037](../decisions/037-deferred-claim-and-attempt.md) | Deferred Claim and Attempt | Decided |
| [D038](../decisions/038-worker-crash-recovery.md) | Worker Crash Recovery | Decided |
| [D039](../decisions/039-execution-transport-contract.md) | Execution Transport Contract | Decided |
| [D040](../decisions/040-mvp-database-transport.md) | MVP Database Transport | Decided |
| [D041](../decisions/041-postgresql-transport-schema.md) | PostgreSQL Transport Schema | Decided |
| [D042](../decisions/042-postgresql-transaction-boundaries.md) | PostgreSQL Transaction Boundaries | Decided |
| [D043](../decisions/043-postgresql-table-layout.md) | PostgreSQL Table Layout | Decided |
| [D044](../decisions/044-data-retention-and-deletion.md) | Data Retention and Deletion | Decided |
| [D045](../decisions/045-retention-mvp-scope.md) | Retention MVP Scope | Decided |
| [D046](../decisions/046-mvp-delivery-plan.md) | MVP Delivery Plan | Decided |
| [D047](../decisions/047-frontend-integration.md) | Frontend Integration | Decided |
| [D048](../decisions/048-implementation-orchestration.md) | Implementation Orchestration | Decided |
| [D049](../decisions/049-identifier-public-api.md) | Identifier Public API | Decided |
| [D050](../decisions/050-execution-context-public-api.md) | ExecutionContext Public API | Decided |
| [D051](../decisions/051-operation-envelope-and-strategy-api.md) | Operation Envelope and Strategy API | Decided |
| [D052](../decisions/052-handler-result-public-api.md) | Handler Result Public API | Decided |
| [D053](../decisions/053-operation-metadata-api.md) | Operation Metadata API | Decided |
| [D054](../decisions/054-runtime-operation-registry-api.md) | Runtime Operation Registry API | Decided |
| [D055](../decisions/055-inline-dispatcher-api.md) | Inline Dispatcher API | Decided |
| [D056](../decisions/056-journal-record-public-api.md) | Journal Record Public API | Decided |
| [D057](../decisions/057-database-access-and-migration-library.md) | Database Access and Migration Library | Decided |
| [D058](../decisions/058-frankenphp-runtime-premise.md) | FrankenPHP Runtime Premise | Decided |
| [D059](../decisions/059-worker-heartbeat-runtime.md) | Worker Heartbeat Runtime | Decided |
| [D060](../decisions/060-typed-outcome-store-contract.md) | Typed Outcome Store Contract | Decided |
| [D061](../decisions/061-retention-operation-reference.md) | Retention Operation Reference | Decided |
| [D062](../decisions/062-retention-audit-log-delivery.md) | Retention Audit Log Delivery | Decided |
| [D063](../decisions/063-developer-experience-roadmap.md) | Developer Experience Roadmap | Decided |
| [D064](../decisions/064-installed-application-layout-and-bootstrap.md) | Installed Application Layout and Bootstrap | Decided |
| [D065](../decisions/065-composer-skeleton-publication.md) | Composer Skeleton Publication | Decided |
| [D066](../decisions/066-development-metadata-layout.md) | Development Metadata Layout | Decided |
| [D067](../decisions/067-legacy-setup-helper-removal.md) | Legacy Setup Helper Removal | Decided |
| [D068](../decisions/068-public-console-kernel-composition.md) | Public Console Kernel Composition | Decided |
| [D069](../decisions/069-skeleton-http-entrypoint-adapters.md) | Skeleton HTTP Entrypoint Adapters | Decided |
| [D070](../decisions/070-quickstart-journal-observer.md) | Quickstart Journal Observer | Decided |
| [D071](../decisions/071-operation-authoring-and-discovery.md) | Operation Authoring and Discovery | Decided |
| [D072](../decisions/072-skeleton-empty-directory-policy.md) | Skeleton Empty Directory Policy | Decided |
| [D073](../decisions/073-skeleton-distribution-publication-boundary.md) | Skeleton Distribution Publication Boundary | Superseded by D076 |
| [D074](../decisions/074-typed-self-handled-operation-signature.md) | Typed Self-handled Operation Signature | Decided |
| [D075](../decisions/075-native-outcome-and-rejection-exception.md) | Native Outcome and Rejection Exception | Decided |
| [D076](../decisions/076-framework-and-skeleton-repository-naming.md) | Framework and Skeleton Repository Naming | Decided |
| [D077](../decisions/077-implementation-worker-model-upgrade.md) | Implementation Worker Model Upgrade | Superseded by D091 |
| [D078](../decisions/078-initial-stable-release-version.md) | Initial Stable Release Version | Partially Superseded by D094 |
| [D079](../decisions/079-immutable-release-publication-recovery.md) | Immutable Release Publication Recovery | Decided |
| [D080](../decisions/080-project-generator-command-contract.md) | Project Generator Command Contract | Decided |
| [D081](../decisions/081-documentation-website-delivery-contract.md) | Documentation Website Delivery Contract | Partially Superseded by D093 |
| [D082](../decisions/082-documentation-reader-experience.md) | Documentation Reader Experience | Decided |
| [D083](../decisions/083-project-root-blackops-entrypoint.md) | Project Root BlackOps Entrypoint | Decided |
| [D084](../decisions/084-documentation-reader-journey-corrections.md) | Documentation Reader Journey Corrections | Decided |
| [D085](../decisions/085-http-configuration-snapshot-lifecycle.md) | HTTP Configuration Snapshot Lifecycle | Decided |
| [D086](../decisions/086-operation-value-validation-runtime.md) | OperationValue Validation Runtime | Decided |
| [D087](../decisions/087-pre-binding-rejection-journal.md) | Pre-binding Rejection Journal | Decided |
| [D088](../decisions/088-validation-backend.md) | Validation Backend | Decided |
| [D089](../decisions/089-validation-rejection-sensitive-journal.md) | Validation Rejection Sensitive Journal | Decided |
| [D090](../decisions/090-documentation-information-architecture.md) | Documentation Information Architecture | Decided |
| [D091](../decisions/091-orchestrator-worker-model-configuration.md) | Orchestrator and Worker Model Configuration | Superseded for Profile selection by D144 |
| [D092](../decisions/092-project-cli-command-names.md) | BlackOps CLI Command Names | Partially Superseded by D094 |
| [D093](../decisions/093-post-phase-10-roadmap.md) | Post Phase 10 Roadmap | Decided |
| [D094](../decisions/094-stable-1-1-release-contract.md) | Stable 1.1 Release Contract | Decided |
| [D095](../decisions/095-phase-12-middleware-and-authorization-runtime.md) | Phase 12 Middleware and Authorization Runtime | Decided |
| [D096](../decisions/096-phase-13-database-and-transaction-runtime.md) | Phase 13 Database and Transaction Runtime | Decided |
| [D097](../decisions/097-phase-14-operation-diagnostics.md) | Phase 14 Operation Diagnostics | Decided |
| [D098](../decisions/098-deferred-acceptance-failure-lifecycle.md) | Deferred Acceptance Failure Lifecycle | Decided |
| [D099](../decisions/099-production-logging-configuration.md) | Production Logging Configuration | Decided |
| [D100](../decisions/100-phase-15-operation-frontend-bridge.md) | Phase 15 Operation Frontend Bridge | Decided |
| [D101](../decisions/101-http-scalar-binding-coercion.md) | HTTP Scalar Binding Coercion | Decided |
| [D102](../decisions/102-phase-16-deferred-status-and-outcome-api.md) | Phase 16 Deferred Status and Outcome API | Decided |
| [D103](../decisions/103-full-stack-reference-application.md) | Full-stack Reference Application | Decided |
| [D104](../decisions/104-structured-outcome-contract.md) | Structured Outcome Contract | Decided |
| [D105](../decisions/105-community-board-deletion-policy.md) | Community Board Deletion Policy | Decided |
| [D106](../decisions/106-community-board-domain-layering.md) | Community Board Domain Layering | Decided |
| [D107](../decisions/107-community-board-deferred-digest.md) | Community Board Deferred Digest | Decided |
| [D108](../decisions/108-ray-aop-upstream-and-phase-order.md) | Ray.Aop Upstream and Phase Order | Decided |
| [D109](../decisions/109-phase-18-idempotency-and-outbox.md) | Phase 19 Idempotency and Outbox | Decided |
| [D110](../decisions/110-application-ergonomics.md) | Application Ergonomics | Decided |
| [D111](../decisions/111-session-auth-package-contract.md) | Session Authentication Contract | Decided |
| [D112](../decisions/112-authentication-credential-response-boundary.md) | Authentication Credential Response Boundary | Partially Superseded by D126 |
| [D113](../decisions/113-database-seeder-contract.md) | Database Seeder Contract | Decided |
| [D114](../decisions/114-application-runtime-and-bootstrap-dependency-boundary.md) | Application Runtime and Bootstrap Dependency Boundary | Decided |
| [D115](../decisions/115-deferred-authoring-and-operation-dispatch.md) | Deferred Authoring and Operation Dispatch | Decided |
| [D116](../decisions/116-blume-documentation-site.md) | Blume Documentation Site | Decided |
| [D117](../decisions/117-documentation-learning-journey.md) | Documentation Learning Journey | Decided |
| [D118](../decisions/118-documentation-landing-visual-hierarchy.md) | Documentation Landing Visual Hierarchy | Decided |
| [D119](../decisions/119-documentation-brand-expression-and-active-navigation.md) | Documentation Brand Expression and Active Navigation | Decided |
| [D120](../decisions/120-documentation-second-review-and-feature-parity.md) | Documentation Second Review and Feature Parity | Decided |
| [D121](../decisions/121-executable-stable-onboarding.md) | Executable Stable Onboarding | Decided |
| [D122](../decisions/122-blume-mermaid-rendering.md) | Blume Mermaid Rendering | Decided |
| [D123](../decisions/123-documentation-third-review-accuracy.md) | Documentation Third Review Accuracy | Decided |
| [D124](../decisions/124-mermaid-diagram-legibility.md) | Mermaid Diagram Legibility | Decided |
| [D125](../decisions/125-documentation-review-agent.md) | Documentation Review Agent | Superseded for Profile selection by D144 |
| [D126](../decisions/126-implicit-inline-ephemeral-outcome.md) | Implicit Inline Ephemeral Outcome | Decided |
| [D127](../decisions/127-journal-documentation.md) | Journal Documentation | Decided |
| [D128](../decisions/128-observed-journal-wire-shape.md) | Observed Journal Wire Shape | Decided |
| [D129](../decisions/129-documentation-website-publication.md) | Documentation Website Publication | Decided |
| [D130](../decisions/130-blume-production-search-verification.md) | Blume Production Search Verification | Decided |
| [D131](../decisions/131-task-oriented-operations-documentation.md) | Task-oriented Operations Documentation | Decided |
| [D132](../decisions/132-documentation-site-ux.md) | Documentation Site UX | Decided |
| [D133](../decisions/133-documentation-editorial-style.md) | Documentation Editorial Style | Decided |
| [D134](../decisions/134-scheduled-application-operation.md) | Scheduled Application Operation | Decided |
| [D135](../decisions/135-tenant-isolation-and-protected-operation-data.md) | Tenant Isolation and Protected Operation Data | Decided |
| [D136](../decisions/136-structured-logging-and-opentelemetry.md) | Structured Logging and OpenTelemetry | Decided |
| [D137](../decisions/137-framework-owned-transaction-proxy.md) | Framework-owned Transaction Proxy Contract | Decided |
| [D138](../decisions/138-local-grafana-lgtm-development-backend.md) | Local Grafana LGTM Development Backend | Decided |
| [D139](../decisions/139-stable-1-2-version-baseline.md) | Stable 1.2 Version Baseline | Decided |
| [D140](../decisions/140-release-quality-tooling-baseline.md) | Release Quality Tooling Baseline | Decided |
| [D141](../decisions/141-release-architecture-and-export-boundary.md) | Release Architecture and Export Boundary | Decided |
| [D142](../decisions/142-public-facade-and-internal-implementation-cycles.md) | Public Facade and Internal Implementation Cycles | Decided |
| [D143](../decisions/143-documentation-release-truth-and-information-architecture.md) | Documentation Release Truth and Information Architecture | Decided |
| [D144](../decisions/144-agent-reasoning-profile-upgrade.md) | Agent Reasoning Profile Upgrade | Decided |

### Phase 23 Proposal decisions（未Release）

| Decision | 内容 | Status |
| --- | --- | --- |
| [D145](../decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md) | BlackOps `1.3` command、FrankenPHP runtime、Audit Trail boundary | Proposed — P23-001 Accepted; Stable `1.2` current / BlackOps `1.3` unreleased proposal |
| [D146](../decisions/146-projection-build-set-php-filesystem-boundary.md) | ProjectionBuildSet PHP filesystem／locator boundary | Proposed — P23-002A1/A2/B/C Accepted; complete projection activation implemented for unreleased 1.3 |
| [D147](../decisions/147-audit-catalog-and-safe-projection-boundary.md) | Audit Catalog、keyed opaque ID、record stages、observed safe-projection boundary | Accepted P23-003A1／A2 and parent P23-003A; P23-003B1／B2 and parent P23-003B Accepted; Stable `1.2` current / BlackOps `1.3` unreleased |
| [D148](../decisions/148-audit-canonical-record-and-protected-store-boundary.md) | Audit canonical record、hash／chain、Audit専用BOPD protection、B1／B2 boundary | P23-003B1／B2 and parent P23-003B Accepted; B2 candidate commit／same-SHA package export Green; Stable `1.2` unchanged / BlackOps `1.3` unreleased |
| [D149](../decisions/149-audit-postgresql-canonical-store-boundary.md) | Audit PostgreSQL canonical append、segment、tenant-aware read、migration boundary | P23-003B2 Accepted; Complete Final Gate and fresh DB／General Sol closure Green; candidate `968afe2b...` package export Green; Stable `1.2` unchanged / BlackOps `1.3` unreleased |
| [D150](../decisions/150-audit-outbox-state-and-delivery-split.md) | P23-003C serial C1／C2／C3／C4 split and pure Audit Outbox state boundary | Proposed — P23-003C1／C2 Accepted with candidate package Green; P23-003C3 packet frozen for preflight; Stable `1.2` current / BlackOps `1.3` unreleased |
| [D151](../decisions/151-audit-postgresql-outbox-persistence-boundary.md) | Audit PostgreSQL Outbox row、idempotent append、FIFO／CAS、transaction boundary | Accepted P23-003C2; candidate `a92bef70...` and same-SHA package export Green; Stable `1.2` current / BlackOps `1.3` unreleased |
| [D152](../decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md) | Catalog-fixed delivery、same-root transaction、protected-read gate、Application Audit key composition | Proposed — P23-003C3 exact packet frozen／fresh preflight pending; Stable `1.2` current / BlackOps `1.3` unreleased |

## Historical P23-001 Round 13 checkpoint

Updated At: 2026-08-19T18:20:04+09:00

Round 13 was the then-current `Correction Implemented / Review Requested`
status. Round 12 is historical Changes Requested (General `P1=5/P2=1/P3=0`,
Audit `P1=6/P2=3/P3=2`) and is not aggregated. Stable `1.2` is current and
Phase 23 `1.3` is unreleased. P23-001S is reopened from historical acceptance
for its DOM visibility correction and remains Review Requested.

Current delivery uses one ProjectionBuildSet v1 wire, exact shared/exclusive
flock publication/GC, atomic P23-002A/B/C ownership, injective custom strategy
wire, partition-global AuditSegmentState, immutable outbox ciphertext, exact
LP/JCS Audit AAD/requestRef, optional evidence presence, trust-window verifier
Stage 0-7 with expectedManifest pin, bounded query/cursor, immutable overlapping
holds, `.requested|.unavailable|.accessed` read events, and signed
`{role,artifactId}` identity without basename claims. F owns signing/reusable
verification; G owns query/export/hosted verification/pin orchestration; D's
control adapter waits for accepted F. Direct Operation Manifest/runtime HTTP
joins are historical-only. No 1.3 capability is public.

Remaining: fresh child General review, fresh parent General/Audit review, and
Orchestrator acceptance; then P23-001R and ordered downstream. Next Action:
freeze the exact-15 Local Gate and route P23-001S for fresh review.

## Historical P23-001 Round 14 checkpoint

Updated At: 2026-08-19T19:56:30+09:00

Round 14 was the then-current `Correction Implemented / Review Requested`
status at that historical checkpoint. Round 13 is historical Changes Requested with General
`P1=1/P2=1/P3=0` and Audit `P1=4/P2=2/P3=0`, separately and without
aggregation. P23-001S was created and historically accepted, then reopened;
it is also Review Requested. Stable `1.2` is current and Phase 23 `1.3` is
unreleased. This is not Green, Accepted, or Decided.

The current contract uses one ProjectionBuildSet v1 wire, dedicated command/
route/schedule projections, build-time route descriptor combination, the
fixed `.blackops-projection-build-set.lock` shared/exclusive flock lifecycle,
and one atomic P23-002A/B/C acceptance slice. It defines the exact optional
`operation:list --strategy=inline|deferred` filter, partition-global segment
sequence, canonical BOPD AAD and 232/344-byte goldens, immutable v1 outbox
ciphertext, Stage 0-7 verifier with external expectedManifest pin and retained
Stage 5 bytes, exact AuditQuery/cursor, AnchorIntent state/hash, three-family
access events, separate AuditHold revisions, and signed `{role,artifactId}`
identity without basename claims. F owns signing/reusable verification; G
owns query/export/hosted verification and pin orchestration; D-control waits
for accepted F. Direct Operation Manifest/runtime HTTP joins are historical
only. No 1.3 capability is public.

Remaining: fresh child General review, fresh parent General/Audit review, and
Orchestrator acceptance; then P23-001R and ordered downstream. Suggested Next
Action: with the Round 14 Local Gate frozen and PASS, request child review, then
request independent parent review. P23-001S must not be recreated.

## Historical P23-001 Round 15 checkpoint

Updated At: 2026-08-19T20:45:20+09:00

Round 15 was the then-current `Correction Implemented / Review Requested`
status, not Green, Accepted, or Decided. Round 14 is historical. The active
contract uses one ProjectionBuildSet v1 wire with dedicated command/route/
schedule projections and the fixed shared/exclusive
`.blackops-projection-build-set.lock` lifecycle; route descriptors are joined
at build time and no direct Operation Manifest/read-time HTTP join is current.
It also used the then-current but invalid requestRef diagnostic (149-byte
`b063819f7388aa541daab07c2e537df29b98648a81917929338bb63e4a990267`); the
correct current vector is a 121-byte JCS/165-byte LP with SHA
`692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`, exact
AuditQuery/cursor and AnchorIntent contracts, and
`.requested|.unavailable|.accessed` read events. P23-001S was created and
historically accepted, then reopened for Round 15 correction and remains
Review Requested. Stable `1.2` is current; Phase 23/`1.3` remains unreleased.

Remaining: fresh child General review, fresh parent General/Audit review, and
Orchestrator acceptance; then P23-001R and ordered downstream. The final
exact-15 Local Gate is PASS. Suggested Next Action: freeze these hashes, route
P23-001S child review,
then request parent review and Orchestrator acceptance.

## Historical P23-001 Round 16 checkpoint

Updated At: 2026-08-19T21:18:38+09:00

Round 16 was the then-current `Local Gate Green / Independent Review Requested`
status, not Accepted or Decided. It is now historical. The Round 15 Local Gate and its
149-byte requestRef diagnostic are historical; the diagnostic was invalid
because shell quoting removed JSON quotes. The current requestRef is the
same LP/JCS formula with a literal 121-byte JCS, 165-byte LP preimage, and
SHA-256 `692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.
Query/cursor, AnchorIntent, ProjectionBuildSet, and Website/Guide corrections
remain unchanged. Stable `1.2` is current and Phase 23/`1.3` remains
unreleased.

The fresh complete exact-15 Local Gate is Green. Remaining: fresh child General
review, parent General/Audit review, and Orchestrator acceptance; then P23-001R
and ordered downstream. Suggested Next Action: freeze the exact-15 hashes and
route the reviews.

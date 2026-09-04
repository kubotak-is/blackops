# Specification 105: BlackOps 1.3 Capability Contract

Status: Proposal — unreleased BlackOps 1.3; workflow/gate status authority is the exact5 management slice (`develop/STATE.md`, `develop/TODO.md`, the parent Task/Report, and `develop/spec/README.md`)

Updated At: 2026-09-04T13:57:41+09:00

This specification is a normative proposal for BlackOps `1.3`. It is not a
Stable `1.2` capability contract and must not be copied into current public
Guide pages until the implementation and release gates in Specification 106
are accepted.

Round 7 Changes Requested is historical: General `P1=4/P2=1/P3=1` and Audit
`P1=6/P2=6/P3=0`; the lanes are not combined. Round 8 fresh review is
historical Changes Requested: General `P1=2/P2=1/P3=0` and Audit
`P1=5/P2=4/P3=1`; the lanes are not combined.
Independent Round 6 evidence remains separate: full review `P1=3/P2=4/P3=1`
and Audit review `P1=2/P2=6/P3=1`; no aggregate is claimed. Round 9 is
superseded Orchestrator pre-review wording; Round 10, Round 11, Round 12,
and Round 13 are historical checkpoints. Round 13 was Changes Requested with
General `P1=1/P2=1/P3=0` and Audit `P1=4/P2=2/P3=0`, separately. Round 14 is
historical. Round 16 through Round 20 are historical; Round 18 was the then-
current Correction Implemented / Review Requested checkpoint and returned
child General `P1=0/P2=1/P3=0`. Round 19 and Round 20 are historical Changes
Requested. P23-001S retains the Historical `Round 21 Accepted` child
prerequisite after fresh child General review `P1=0/P2=0/P3=0`. Round 22 is
historical Changes Requested: parent
General `P1=3/P2=1/P3=0` and Audit `P1=4/P2=0/P3=0` remain separate. Round 23
is historical Changes Requested: parent General `P1=2/P2=2/P3=0` and Audit
`P1=3/P2=1/P3=0` remain separate. Round 24 is historical Changes Requested
with General `P1=1/P2=1/P3=0` and Audit `P1=5/P2=1/P3=0`, separately. Round 25
and Round 26 are historical evidence. This specification is a current technical
proposal only. Workflow and gate status authority is the exact5 management
slice: `develop/STATE.md`, `develop/TODO.md`, the parent Task, the parent
Report, and `develop/spec/README.md`; Search and Audit workflow lanes are not
normative within this specification. Parent acceptance is governed outside
this technical contract.
For an active implementation packet, the exact5 is reconciled at preflight freeze and Acceptance.
Between those boundaries, a packet may freeze Task／Decision／TODO／Spec README as normative input;
only its Report／STATE pair is moving checkpoint authority when the packet says so explicitly.

For every current `ProjectionBuildSet v1` clause in this specification, D146
is the more specific authority. Its component injected locator, C-owned
Production locator, single-basename
namespace, current field table, verified-handle policy, lock initialization
ownership, scan limit `256`, and deletion limit `32` supersede incompatible
path/cap and literal `nofollow`／`no-follow` shorthand in non-historical text.
Explicitly historical checkpoints remain evidence only.

For every current P23-003A Catalog／AuditRecord schema／safe-projection clause,
D147 is the more specific normative authority. It fixes serial A1→A2 delivery,
Catalog-only RFC 8785 JCS/hash ownership, `ENC_STRING(domain) +
ENC_BYTES(unsigned JCS)` hashing, the exact 30-definition Framework
security/access catalog and golden, trusted owner/reserved prefixes, evidence
presence/nullability, keyed opaque IDs and downstream key ownership,
pre-decision `AuditReadRequestBasis` plus post-decision `AuditReadContext`,
two-event protected-read cardinality, exact validation/JCS escape/raw-size
oracles, the raw-cap pre-stage and exact 1..256-byte typed-reference members,
empty A1 PublicApi marker inventory, three record-construction stages,
and the closed observed projection. Any incompatible non-historical shorthand
below is superseded. Historical checkpoints remain evidence only.

For current P23-003B clauses, D148 is the more specific normative authority.
It splits B into serial B1 canonical-record／Audit-protection pure foundation and
B2 PostgreSQL append／segment／migration, fixes exact trusted stamp、partition、
hash、AAD、failure、transport wires, and keeps Stable Public `StoragePurpose`
exact9. Initial B1 preflight returned Changes Requested `P1=4/P2=1/P3=1`.
The corrected-vector re-preflight returned `P1=4/P2=0/P3=0`. D148 now fixes
exact three canonical-chain logical fixtures plus exact two durable-ingress fixtures,
write-path-derived 308／421 and delivery-correct 222／303-byte AAD vectors,
Candidate／Canonical implementation exact2,
persisted-value hydration, and the generic `0xFFFF_FFFF`／`0x1000000B3` caps.
The historical preflight and review findings are closed. Final bounded Sol Max
closure is Green `P1=0/P2=0/P3=0`; the Accepted exact33 aggregate is
`bb6ab89a222a72eafa601f588f46177ee443e554c036bcc00575b211961c8847`,
and the Complete Final Gate is Green. P23-003B1 is Accepted. No runtime、
route、CLI、public Documentation、or release behavior is active;
Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased. Reviewed B1 Product candidate
`c3b75ca0afe6d8c756caa8404b7e95922987c1da` and its clean-candidate package
export are Green. D149 fixes B2's same-Connection transaction、exact DDL／migration、
trusted Clock／UUID、required／durable append、idempotency、explicit close、tenant
predicate-before-decrypt、failure precedence、and post-Acceptance package boundary.
Earlier B2 preflights and review lanes are Historical. Accepted B2 exact26 is
`ecacafc99cbdf4fd2287b7554b88a34f6524a23d92c9adb3a8bf201a2a1d50bb`.
Its Complete Final Gate is Green, and fresh DB／General Sol closure separately returned
`P1=0/P2=0/P3=0`. P23-003B2 and parent P23-003B are Accepted.
The PostgreSQL component and migration exist as an unreleased internal foundation;
no Application composition、Container registration、Audit Outbox／relay、HTTP／CLI surface、
public Documentation capability、or release activation occurred. Reviewed B2 candidate
`968afe2b0c4bbff86e0013592d69dc7115cf8b4f` is committed and its clean
exact-SHA package export is Green; no push was performed.

For current P23-003C delivery clauses, D150 is the serial split and C1
authority, D151 is the Accepted C2 PostgreSQL persistence authority, D152 is
the Accepted C3 delivery／transaction／read-gate／key-composition authority, and
D153 is the most-specific C4 relay／recovery authority. Parent C is the serial
C1→C2→C3→C4 boundary; no child may activate a partial runtime that bypasses a
later child.

P23-003C1 is Accepted at candidate
`bc12a67210d9cb132cf3246f96b00d79fb519656` with clean same-SHA package
export Green. P23-003C2 is Accepted at candidate
`a92bef70fdb843a4dd2b8b34243fc892b43939cc` with the same gate and package
boundary. P23-003C3 is Accepted at candidate
`b7b352a8e4c86a45822351c108772367515654bd`; its Complete Final Gate, fresh
General／Transaction／Documentation review, and clean same-SHA package export
are Green. P23-003C4 and parent P23-003C are Accepted at final commit
`d5ab3476181058c5fb87b7c2c9476b6db76fef2e`; its clean same-SHA Git／Composer
package export is Green. No push was performed.

Accepted C3 adds exactly two unreleased Application-facing provider interfaces
and twelve Internal types. The opaque-ID provider supplies one dedicated
32-byte `StorageKey`; the Audit protection provider keeps record／Outbox and
active／historical lookup separate and receives only canonical partition IDs.
C3 never references or adapts generic `StorageKeyProvider` or changes
`StoragePurpose` exact9. The opaque key reference and material may not be
reused by either protection purpose.

C3 binds `(catalogHash,event.type,event.version,evidence.version,delivery)` in
an immutable plan. Raw policy selection accepts only literal
`required|durable`, requires the exact trusted Catalog definition and equality
with its fixed delivery, and rejects missing, null, unknown, `best_effort`,
mismatch, override, and downgrade before a Store call. Required routes exactly
once to the canonical Store with no idempotency key; durable routes exactly
once to the Outbox Store with one bounded raw key.

Standalone delivery accepts only auto-commit enabled level0 and returns after
the selected Store-owned root commits. Mutation delivery accepts only the
existing active level1 Framework Connection and performs no transaction-control
call. C3 wraps the existing `OperationTransactionCoordinator` completion
callback: a completed business result creates one Audit request and appends
before the existing coordinator commits; request or append failure rolls the
business mutation back. Nontransactional, cross-Connection, rejected, and
non-completed paths perform the fixed zero-work behavior. There is no
cross-database or external exactly-once claim.

C3 supports only the canonical `ApplicationOperationRuntimeComposition`
created by the existing composer, including its cached
`DoctrineDatabaseManager` Connection identity. Manually assembled operation
compositions and alternate managers are outside the C3 production graph.
Because Accepted canonical／Outbox Stores already erase lower causes, every
Store `ResourceUnavailable` maps to C3 `resource_unavailable`; every other
Store failure maps to `storage_unavailable`.

For protected Operation Status／Journal／Outcome／Diagnostics reads, C3 commits
the exact `.requested` required event before authorization or target lookup and
the exact `.accessed|.unavailable` required terminal event before return. An
allow-path failure releases no decision or data. Deny and resource-unavailable
remain the same bounded non-allow result on every gate failure. The gate takes
no response/data callback and cannot release protected bytes.

One uncalled Application composer parses closed `audit.outbox` configuration,
resolves the two provider services, reuses the existing Framework Connection／
Clock／transaction coordinator, and constructs one canonical Store, one Outbox
Store, one opaque deriver, one delivery graph, and one protected-read gate.
It has zero current Production callsites and no Container／Application／CLI／
HTTP／worker／scheduler／relay registration.

C4 consumes that exact Connection, configuration, protection codec, validator,
canonical Store, and Outbox Store graph through one Internal persistence
adapter and one protected-ingress canonicalizer. Its bounded `recoverOne()` and
`relayOne()` entries preserve separately committed claim, canonical append,
and acknowledgement Store roots. Ambient level1 is rejected at the C4
boundary. The runtime owns no Clock, transaction control, loop, daemon,
scheduler, worker, command, route, or registration.

Canonical resource／storage and protection failures retry below max and poison
at max. Idempotency, segment, integrity, logical validation, and committed
binding failures poison immediately. Settlement transition conflict is stale;
settlement resource／storage failure supersedes the initiating sender failure.
Unexpected throwables leave the lease for recovery and contract to one fixed
value-free storage failure.

Crash after canonical append and before acknowledgement converges through the
same canonical `recordId` and `contentHash`. At final-attempt expiry, Accepted
C2 may poison without querying the canonical Store, so poison does not prove
canonical absence. Both `relayed` and `poisoned` acknowledge their ordinal and
release the next FIFO position. Earlier current wording that poison
persistently stops a partition is superseded by D153.

C3 adds no migration or public Documentation claim. C4 adds exactly seven
Internal package types and no public source, migration, route, command, public
Documentation, or current runtime caller. Framework migrations stay 13,
Community Board stays 18, package required inventory grows exact68→75, Stable
`1.2.0` remains current, and BlackOps `1.3` remains unreleased. The Stable
generic Outbox runtime and `outbox:relay:*` commands remain byte-identical and
are not C4 activation.

For current P23-003D clauses, D154 is the most-specific authority. It splits
ordinary D into bounded Catalog, Operation-read, authentication,
authorization, phase-source, and integration children. The checkpoint／anchor
D-control child is a distinct post-P23-003F edge. P23-003D1 adds only the
exact14 `blackops.framework.operations` Catalog and exact two-provider
Framework lookup provenance; it preserves the Accepted security／access
exact30 Catalog byte-for-byte, increases package required inventory exact75→76,
and activates no producer or runtime. Stable `1.2.0` remains current and
BlackOps `1.3` remains unreleased.

## Historical P23-003A Acceptance Catalog and projection contract

Historical status: `Accepted — P23-003A1／A2 and parent P23-003A; P23-003B next`.

P23-002E and P23-003A1／A2 are Accepted; parent P23-003A is Accepted.
P23-003B was the next ordered Task at this Acceptance checkpoint and still required its own
packet and preflight. Neither A child registers an Application catalog, activates an
adapter, writes canonical storage, or emits public JSONL.

D147 defines the complete A1 catalog bytes/hash and A2 projection wire. A1
owns only Catalog JCS/hash and pure AuditReadRequestBasis/AuditReadContext
hashing. P23-003B owns trusted
logical-record finalization, content/record hashes, integrity, encryption,
append, and chain state. Stable `1.2.0` and its public Documentation remain
unchanged; these contracts remain unreleased `1.3` proposal text.

Initial A2 preflight returned Changes Requested `P1=1/P2=0/P3=0`. The
corrected contract fixes one Logical `toArray()` capture, a detached exact
snapshot bound to the same resolved definition, and a bytes-only Projector
entrypoint that internally validates once and never accepts a caller-supplied
validated wrapper.
Fresh Sol Max re-preflight was Green `P1=0/P2=0/P3=0`. Luna Max implementation,
the focused Iteration Gate, exact29 Product freeze, and review-before complete
Final Gate were Green.
That review returned Changes Requested `P1=1/P2=2/P3=0`; A2 corrected
definition-dependent evidence precedence and the two regression-evidence roots.
The corrected exact29 complete Final Gate is Green and fresh Sol Max
implementation re-review returned Changes Requested `P1=3/P2=1/P3=0`.
Round 2 is limited to Correlation omission construction, bounded Logical capture,
remaining direct mutation fixtures, and synchronized D147 current handoff.
Round 2 correction and its complete Final Gate are Green on exact29 aggregate
`c2ef33a3d3b47ca9a5de189d324308a79da0a356839d36aa944675a7dba2d8eb`.
Fresh Sol Max implementation re-review returned Changes Requested
`P1=0/P2=2/P3=0`; Round 3 is limited to the explicit-null correlation projection
direct fixture and current inventory sync.
Round 3 closes both roots. The exact29 Product aggregate is
`65443eb6ec6c738c61100001d8f3f5d2e38a88e63f585052bf6f4c4b9bd29056`;
focused PHPUnit `46/23599` and the complete Final Gate are Green. Fresh Sol Max
implementation re-review returned Changes Requested `P1=1/P2=0/P3=0` for
wide key/evidence allocation before bounded shape rejection. Round 4 is limited
to the Validator and its two direct tests; Product Gate evidence restarts after
that change.
Round 4 correction and focused gate are Green on exact29 aggregate
`c019ab09c31a99301bfe9fd22004890cf52a21b20cc7b410df74f08b0ef97ae9`.
Accepted A1 exact48 remains unchanged. The complete Final Gate is Green with
full PHPUnit `2700/37333`, Mago error0, Deptrac `970/970`, release／Website／
Node／management guards, staged0, and ending hashes PASS. Fresh review／
Acceptance are Green／Accepted: fresh Sol Max implementation re-review returned
`P1=0/P2=0/P3=0`, and the Orchestrator accepts A2 and parent P23-003A.

The first corrected exact-nine re-preflight is Historical Changes Requested:
general `P1=1/P2=1/P3=0` and focused `P1=1/P2=0/P3=0` remain separate. D147
now makes the 1,048,658-byte raw Catalog limit a pre-semantic guard that returns
`jcs_bound_invalid` before decode for every over-cap compound input; its exact
ten-stage precedence applies only after that guard. Each Audit typed-reference
`type` and `id` is valid UTF-8／NFC／no-control and 1..256 UTF-8 bytes, without
trim or silent normalization.

P23-003C owns the future dedicated `audit_opaque_id_v1` key-provider/reference/
availability/injection boundary; A1/A2 do not activate it. A1 adds no
`#[PublicApi]` marker. The initial A1 preflight is Historical Changes Requested,
and the first corrected re-preflight remains Historical Changes Requested.
The second corrected exact-nine focused lane was Green, while the separate
general lane returned `P1=0/P2=1/P3=0` for missing direct preservation and
tenant opaque-context fixtures. The fixture matrix is now explicit; the newly
corrected exact packet required fresh general Sol re-review before Luna.
The fresh general closure review matched that packet and returned Green
`P1=0/P2=0/P3=0`. Luna Max completed the exact A1 implementation and
focused／complete Final Gates. Fresh Sol Max implementation review is Green
`P1=0/P2=0/P3=0`; the Orchestrator accepts P23-003A1 and unblocks P23-003A2.
The A2 Task fixes a single `projection_invalid` failure, exact
correlation/evidence loss rules, an Internal logical-record interface without
a Production concrete constructor, injected Accepted-A1 opaque derivation,
and an exact-zero A2 PublicApi inventory. P23-003B..G and runtime activation
remain blocked through Round 2 correction, complete Final Gate, and fresh
implementation re-review.

## P23-002E current About and Outbox contract

Current status: `Accepted implementation — unreleased BlackOps 1.3`.

P23-002D is Accepted. Section 6.2 is the current exact contract for the
proposed `about` and aggregate-only `outbox:status` slice. Stable `1.2.0`
remains current and BlackOps `1.3` remains unreleased. Both components and
shared integration and the complete Final Gate are Green on exact37. Fresh Sol
Max implementation review is Green `P1=0/P2=0/P3=0`; the Orchestrator accepts
P23-002E. BlackOps `1.3` remains unreleased.

## P23-002D accepted diagnostics and queue prerequisite

Current status: `Accepted — Diagnostics Check and Aggregate Queue Status`.

P23-002C is Accepted. Section 6.1 is the current exact contract for the
proposed `diagnostics:check` and aggregate-only `queue:status` slice.
Fresh Sol Max preflight is Green `P1=0/P2=0/P3=0`; Production implementation
completed within the exact P23-002D Task Packet.
Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased.

Initial preflight confirmed two architecture P1 roots. The corrected Task puts
Queue ports/values/failures in Core and permits exactly Application and
InternalApplication to depend on Observability. Fresh re-preflight matched
exact8 aggregate `f29f3deb2896cdb067b58927139f7d3c512bdd8ce20674035b5393ce8f674a34`
and returned Green.

The initial exact33 candidate and review-before exact34 are Historical. Fresh
Sol Max review found only a missing direct age cap+1 regression fixture
(`P1=0/P2=1/P3=0`). Luna Max added that test without Production changes.
Review-before exact34 aggregate
`db18b1c18b116a8afd4e2062c9328edf7b05004c8cb7576916c2e07882c4ae64`
passes focused `136/1463` and the complete Final Gate, including full PHPUnit
`2513/11352` and Deptrac `904/0 violations`. Fresh Sol Max closure review
returned `P1=0/P2=1/P3=0`: Production is finding-free, and only the required
multi-tenant PostgreSQL fixture remained. Luna Max changed only that test.
Current exact34 aggregate
`6a4932de04e4c4e848024560f078e8bac1b858e1ae07abdc5140667b1cd4d6c2`
passes focused `136/1465` and the complete Final Gate, including PHPUnit
`2513/11354` and Deptrac `904/0 violations`. Fresh Sol Max closure review
returned Green `P1=0/P2=0/P3=0`; the Orchestrator accepts P23-002D. Stable
`1.2.0` remains current and BlackOps `1.3` remains unreleased.

## P23-002C accepted schedule and atomic activation prerequisite

Accepted status: `Accepted — Schedule Projection and Atomic Activation`.
Reviewer: Fresh Sol Max closure review Green `P1=0/P2=0/P3=0`; Orchestrator Accepted.

P23-002A1/A2/B are Accepted as non-activated components.
P23-002C is the sole owner of the Production locator, schedule projection, and
all-three atomic activation.
Fresh bounded Sol xHigh preflight matched exact9 aggregate
`c933fd9f23f85b00f447f72f69a7769bb7ec747b737cf711fb2cdf934beeb347`
and returned Green `P1=0/P2=0/P3=0`.
Implementation is authorized only within the exact P23-002C Task Packet.

Luna Max implementation is complete within the corrected exact 41-file Task
scope. The first Orchestrator Final Gate stopped at canonical `mago lint` with
exactly two new cyclomatic-complexity errors, so the pre-correction exact32
aggregate `0cce05bb35c46fbe15217e6cf6bcc206834ae429dca5c0c3d7ee937ecbda0ec5`
is Historical. The narrow behavior-neutral class-DocBlock correction passes
focused format, canonical lint, and impacted PHPUnit (`15 tests / 118
assertions`). The Orchestrator refroze the corrected exact32 aggregate as
`be0b7c8421e1f6906ce5576b59230ed33dcf505a00618afc09622c52a652d766`,
restarted the complete Final Gate, and reproduced the same ending aggregate.
Format, Composer strict, canonical lint/baseline, analyze errors `0`, full
PHPUnit `2468/10930`, Deptrac violations `0`, version baseline, Website
release-source, management-ID, diff, and staged0 are Green. The corrected
`be0b7c...` exact32 freeze and its Final Gate are Historical/superseded after
the review and fixture correction. The current Orchestrator-frozen exact32
aggregate is `15e0f074c5302af68753c06d8b9effc0747aff0e0a55902f081bc91834117a33`;
its complete Final Gate is Green with PHPUnit `2472/10972`, canonical lint
baseline filtered 186 with no issues, analyze errors `0`, Deptrac violations
`0`, version baseline, release-source, management-ID, diff-check, staged0, and
ending exact32 unchanged. Fresh Sol closure review and Orchestrator Acceptance
are complete: the independent review returned Green `P1=0/P2=0/P3=0`, and
the Orchestrator accepts P23-002C. This proposal remains unreleased.

The Production ProjectionBuildSet root is exactly
`dirname(app.build.container)` for a valid configured absolute container path.
No new public configuration key is added.
`ApplicationBuildConfiguration::projectionBuildSetRoot` is the single
calculation authority used by the Production status source, Kernel, and build
command.
A missing `app.build` section or missing configured build directory returns
the read-only `absent` status without filesystem mutation.
A present but invalid build configuration, invalid build ID, or
symlink/special/unresolvable directory is a configuration-input validation
failure and is not classified as artifact `malformed`.

Commands depend on `ProjectionBuildSetStatusSource::withStatus(callback,
selectedKey)`.
The Production adapter resolves the configured build ID and root, validates the
root and all three published descriptor/file/input-hash bindings under one
shared lock, and keeps that lock until rendering and output write finish.
It does not read Source, Provider, Container, DB, HTTP/Operation/command
manifests, or recalculate expected hashes from working files.
The existing caller-injected exact-three input-hash API remains a component-test
seam only.
The Reader Production seam is
`withPublishedStatus(expectedApplicationBuildId, callback, selectedKey)`;
the existing exact-three `withReadStatus` seam remains unchanged for component
tests.
Production `stale` means a published descriptor/projection input-identity
mismatch; working Source drift is not claimed.

The target metadata registry becomes exact 31 rows by adding canonical
`schedule:list`, alias `schedule:ls`, description
`List compiled application schedules.`, and `hidden=false`.
The lazy Framework command-name inventory remains exact 25 and the global
pre-release reservation registry remains exact 13.
An empty configured Application therefore exposes exact 31 canonical target
rows after C activation; aliases do not become separate canonical rows.

The schedule producer consumes only the already-compiled `OperationRegistry`
and application build ID.
It emits the exact closed
`{name,operationType,cron,timezone,strategy}` rows for metadata with a non-null
schedule.
One pure mapper is shared with route projection generation:
`Inline::class -> inline`, `Deferred::class -> deferred`, and every other
declared strategy -> `custom:<canonical-FQCN-without-leading-backslash>`.
The mapper performs no autoload, reflection, factory, Provider, Container, DB,
handler, clock, or due evaluation.

`build:compile` creates command, route, and schedule projections from the same
in-memory compilation and calls the publisher exactly once after all existing
container/Operation/HTTP/Frontend/command artifact writes succeed.
The fixed root is committed last.
Failure before that commit emits no success line and leaves the prior or absent
projection root current.
This is atomicity of the three ProjectionBuildSet files only.
Already-written runtime container, Operation, HTTP, Frontend, and command
artifacts are not rolled back if projection publication fails.
A reused build ID with an internally valid old root therefore does not imply
working Source drift detection.
A producer or pre-commit publisher failure propagates its existing typed
exception and synthesizes no success or cleanup diagnostic.
A committed root with incomplete cleanup writes
`Build artifacts written.\n` to stdout and
`Projection build-set cleanup is incomplete.\n` to stderr, exits `1`, and does
not claim rollback.

The Kernel activates the BlackOps-owned `list`/`ls`, `route:list`/`route:ls`,
and `schedule:list`/`schedule:ls` families together against that one status
source.
Kernel composition may instantiate configured explicit Command class-strings
and reuses prebuilt instances under the existing Application Bootstrap
contract.
Executing the new list commands after composition performs no additional
factory, Container, Provider, DB, source-discovery, or handler resolution.

## P23-002A1 current ProjectionBuildSet foundation contract

The current root is the closed
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` object with
`schemaVersion=1`, `kind="projection_build_set"`, a 1..128-byte NFC/control-free
`applicationBuildId`, and exactly this non-interchangeable map:

| Root key | Descriptor kind | Descriptor path |
| --- | --- | --- |
| `command` | `blackops_command_projection` | `command-<sha256>.json` |
| `route` | `blackops_route_projection` | `route-<sha256>.json` |
| `schedule` | `blackops_schedule_projection` | `schedule-<sha256>.json` |

Every descriptor is the closed `{kind,inputHash,path,sha256}` object. Both
hashes are lowercase 64-hex; `<sha256>` is exactly the descriptor digest of the
stored projection bytes. `path` is one basename and rejects slash, backslash,
dot segments, prefix/digest mismatch, swapped kind, and duplicate path/kind.
This current single-directory contract supersedes the earlier general
root-relative POSIX-path allowance.

Each projection is the closed
`{schemaVersion,kind,applicationBuildId,inputHash,records}` object with
`schemaVersion=1`, the mapped kind, and root-equal build/input identities. The
`sourceRecords` hash input is exactly the same closed, normalized, sorted array
stored as `records`; it is not a second representation. The typed hash encoding
and formulas are:

- `ENC(string)="s:"||decimal_utf8_byte_length||":"||UTF8(string)`
- `ENC(integer)="i:"||canonical_decimal_digits`, `ENC(null)="n:"`
- `ENC(bytes)="b:"||decimal_byte_length||":"||bytes`
- `buildSetHash=SHA-256(ENC("blackops.projection-build-set.v1")||ENC(JCS(root excluding buildSetHash)))`
- `inputHash=SHA-256(ENC("blackops.projection-input.v1")||ENC(JCS({kind,schemaVersion,applicationBuildId,sourceRecords})))`

Stored root/projection bytes are UTF-8 RFC 8785 JCS plus exactly one LF, with
no BOM, CRLF, or extra whitespace. Root cap is 64 KiB inclusive, each
projection cap is 16 MiB inclusive, and each records array has 0..4,096 rows.
All strings are UTF-8, NFC, and control-free unless stricter below:

| Projection | Exact closed fields and inclusive bounds |
| --- | --- |
| command | `{name,aliases,description,origin,hidden}`; name and each alias 1..256 bytes with no tab/comma/CR/LF or empty colon segment; aliases 0..32 unique unsigned-byte-sorted values with no canonical/alias collision; description 0..256 one-line bytes; origin `framework|application`; hidden Boolean |
| route | `{method,path,strategy,operationType,origin}`; method uppercase ASCII HTTP token 1..16 bytes; path 1..2048 bytes beginning `/`; operationType null or 1..256-byte dot-separated lowercase identifier; strategy null or 1..1024-byte wire; origin `operation_manifest|framework_fixed`; operation rows require non-null type/strategy and fixed rows require both null |
| schedule | `{name,operationType,cron,timezone,strategy}`; name/type are 1..256-byte dot-separated lowercase identifiers; cron is 1..256-byte one-line valid five-field POSIX expression; timezone is `UTC` or an available IANA identifier at 1..128 bytes; strategy is 1..1024 bytes; no origin member |

A dot-separated lowercase identifier is exactly
`^[a-z0-9]+(?:\.[a-z0-9]+)*$`. Command names/aliases first match
`^[^:]+(?::[^:]+)*$`, then apply the stated separator/control exclusions.
Uppercase HTTP tokens use RFC 9110 `tchar`, reject lowercase ASCII, and obey
the 16-byte cap.

Strategy is exactly `inline`, `deferred`, or
`custom:<canonical-FQCN-without-leading-backslash>`. A custom FQCN is one or
more ASCII PHP identifier segments separated by one backslash; each starts
with a letter or underscore and continues with letters, digits, or underscores.
Command sort is
`(name,aliases)` with lexicographic alias-list comparison; route sort is
`(path,method,origin,operationType,strategy)` with null first; schedule sort is
`(name,operationType)`, all by unsigned UTF-8 bytes. Unknown, duplicate,
missing, wrong-type, unsorted, duplicate-record, non-NFC, invalid-grammar,
cross-origin, cap+1, and separator fixtures are hard-coded from this table.

D146 is normative for the exact reserved basename families, verified-handle
protocol, existing-target matrix, first-lock bootstrap, publication commit
point, accepted entry limit `256`, deletion limit `32`, and cleanup result.
Readers require the fixed existing lock and validate root plus all three files
under shared `flock`; publishers accept exact-three typed inputs and hold
exclusive `flock` through root-last publication and bounded GC. P23-002A1 does
not activate this component.

## Historical Round 23 current normative corrections

Every `ProjectionBuildSet v1` reader acquires one shared lock before opening
the root and all three projection files, validates every root/descriptor/file
and root, digest, build, and input identity, then selects and renders rows from
the reader's dedicated projection. Any non-selected missing, malformed,
digest, build, or input mismatch invalidates the complete set. `list`/`ls` maps all six framework-row recovery
states to exit `0`; `route:list`/`schedule:list` retain artifact-failure exit
`2` and operational-read exit `1`. P23-002A/B/C owns all-three-reader
non-selected mismatch fixtures.

`worker:list` and `worker:ls` are neither BlackOps-owned nor reserved. An
Application may own either spelling as a canonical command or alias; an
unregistered spelling follows normal Symfony unknown-command behavior. BlackOps
1.3 provides no Job registry or built-in Job listing. P23-002A owns
Application-canonical/alias positives, unregistered negative, and no-Framework-
Job-row fixtures.

The fixed Status route wire is `/operations/{operationId}`. Placeholder names
are structurally equivalent for collision identity, so `/operations/{id}`
collides with the canonical row while the public wire always renders
`{operationId}`. P23-002B owns exact-row and equivalent-placeholder/static-
segment collision fixtures.

Operation reads use exactly
`access.operation_{status,journal,outcome,diagnostics}.requested|unavailable|accessed`;
every outcome has catalog delivery `required`, and required canonical append is
durably persisted before a read response or failure is released. Service-managed actions use exactly
`audit.access.{query,export,sign,verify}.requested|unavailable|accessed`;
`requested`/`unavailable` are durable and `accessed` is required before a
result or signature is released. P23-003A/D/F/G own the exact tuples and
mismatch fixtures.

Genesis alone starts at sequence `1`; each later segment starts at the prior
final sequence plus one, and
`recordCount=lastSequence-firstSequence+1`. Checkpoint examples `1-42` and
`43-50`, plus gap/overlap negatives, are required.

The framework-controlled signature for exact target
`system:global/audit-control-v1` is a finite control close: its signed
checkpoint/receipt is authoritative and emits no `audit.access.sign.accessed`.
The exact target and framework role derive this exemption; callers cannot
select it, and ordinary signatures remain audited.

`AuditHold v1` has exactly one closed revision object:
`{schemaVersion:1,kind:"audit_hold",holdId,target,reasonCode,state,version,`
`previousStateHash,stateHash,updatedAt,updatedBy,idempotencyKeyHash}`. Target
is immutable `{partitionId,startSequence,endSequence,eventCategory|null}`;
state is `active|released`, only `active -> released` is valid, and hashes are
continuous. Use
`stateHash=H(ENC("blackops.audit.hold-state.v1")||ENC(JCS(object excluding stateHash)))`
and
`idempotencyKeyHash=H(ENC("blackops.audit.hold-idempotency.v1")||ENC(holdId)||ENC(rawKey))`.
Raw keys are 1..256-byte NFC/no-control non-disclosed values; `(holdId,target)`
is immutable CAS identity; same-key same-body is idempotent and same-key
different-body conflicts; the complete JCS cap is 64 KiB inclusive; actor/time
bounds are exact. Overlap, wildcard, and same-snapshot eligibility remain.

`AuditReadRequestBasis v1` includes `requestedAt` and `nonce`; its exact requestRef
is `"oid:v1:" || HEX_LOWER(SHA-256(LP(domain) || LP(JCS(requestBasis))))`, where
`domain="blackops.audit.read.request-ref.v1"`, `requestBasis` is the closed
six-field object in D147, and the LP concatenation is the preimage rather than the final
requestRef. Null/NFC/time/nonce bounds, independent Node/Docker-PHP parity, one
golden, an algorithm-substitution negative, and null/present/substitution/cap+1 fixtures are required. The golden
uses `purpose=status`, `actor=null`, `execution={id:"exec-1",type:"cli"}`,
`tenant=null`, `requestedAt="2026-08-20T03:20:00.123456Z"`, and
`nonce="nonce-1"`; canonical JCS is 150 bytes, the LP preimage is 192 bytes,
and `requestRef=oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
The full trusted `AuditReadContext v1` is constructed only after authorization;
caller input supplies neither its decision nor requestRef.
Every A1 read typed reference is the closed object `{type,id}`. Each member is
valid UTF-8, NFC, free of `Cc|Cf`, and 1..256 UTF-8 bytes inclusive. Empty and
257-byte members fail; no trim, case-fold, silent normalization, UUID, or path
grammar is inferred. Actor and tenant may be null only at the explicitly
nullable positions; execution is required.

## Historical Round28 normative Audit correction (superseded by Round29)

Round28 was the prior parent authority for this specification. It is now
Historical evidence and is superseded by the Round29 correction below. Round27
Search and Round28 Audit remain separate review lanes. Stable 1.2 remains
current and this document remains an unreleased 1.3 proposal.

### Query contract

AuditAccessContext v1 is closed:
{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,
requestedAt,nonce,requestRef,authorizationDecision}. Query requests require
purpose="query" and authorizationDecision="allow" before selection. Its
requestRef binds exactly {purpose,actor,tenant,requestedAt,nonce}; target,
partition, filters, limit, order, cursor, and the decision result are not hash
inputs.

AuditQueryRequest v1 is closed:
{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
limit,order,snapshotHeadSequence,cursor}. partitionId is
system:global|tenant:<64 lowercase hex>. filters is exactly
{eventCategory:null|string,recordIdPrefix:null|string,fromSequence:null|positive,
toSequence:null|positive}; non-null strings are NFC, control-free, and
1..256 UTF-8 bytes, and fromSequence<=toSequence. limit is 1..1000 and
order is exactly sequence_asc. The first page has null snapshot head and
cursor; one trusted canonical partition head is captured, and a continuation
must carry the same positive head, partition, filters, limit, and order.
Selection is bounded by that head and optional toSequence, starts at
fromSequence when present, and a continuation is strictly after lastSequence.

AuditQueryResult v1 is closed:
{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,hasMore,nextCursor,reasonCode}. ok returns a complete
ascending 1..limit record array, positive captured head, hasMore, and a
nextCursor exactly when another bounded record remains. empty returns the
authorized partition/head, records=[], hasMore=false, and null cursor/reason.
unavailable returns null partition/head/records/cursor, hasMore=false, and
resource_unavailable. No partial records or target existence details are
returned; result JCS+LF is capped at 1 MiB.

The cursor is the exact aqc:v1 base64url/JCS/HMAC wire with closed payload
{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,limit,order,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}. The HMAC input is
LP("blackops.audit.query.cursor.v1")||LP(JCS(payload)) and the key is the
32-byte audit-query-cursor role secret. Canonical decode, closed fields,
partition/filter/limit/order/head/last bounds, caps, role, and timestamps are
checked before lookup/decrypt. The key window is exactly
key.notBefore <= issuedAt <= trustedNow < expiresAt <= key.notAfter.
trustedNow is read once from the Framework clock. A prior verification key is
retained exactly through the expiry of its last outstanding valid cursor, then
may be retired; new cursors use only the current active key. P23-003G owns
authorization, head capture, cursor lifecycle, key retention, and all query
positive/negative fixtures.

### Ingress and Outbox contract

AuditIngressEnvelope v1 is the exact closed durable object
{schemaVersion:1,kind:"audit_ingress_envelope",logicalRecord,contentHash,
recordedAt,ingressOrdinal,idempotencyKey,recordId,partitionId,tenantBinding}.
logicalRecord=AuditRecord without integrity; recordId and recordedAt
equal the logical record; contentHash is the lowercase 64-hex domain hash of
the canonical logical JCS; recordedAt is trusted UTC with six fractional
digits; ingressOrdinal is positive JSON-safe and monotonic per partition;
idempotencyKey is protected NFC/control-free text of 1..256 UTF-8 bytes;
recordId/partitionId use the established bounds; tenantBinding is the closed
null|{type,id} union, equals logicalRecord.tenant, and matches partition
derivation. Unknown fields, mismatch, cap+1, and normalized substitutions
fail closed.

AuditOutboxRecord v1 is the exact closed row
{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}. Its plaintext is
JCS(envelope) without LF. LP is U32BE length plus exact bytes and AAD is the
byte-exact sequence
LP("blackops.audit.outbox.aad.v1")||LP("AuditOutboxRecord")||LP("1")||
LP("1")||LP("1")||LP(exactKeyId)||LP(recordId)||LP(partitionId)||
LP(TENANT(tenantBinding))||LP(ASCII(canonicalDecimal(ingressOrdinal)))||
LP(decoded32(idempotencyKeyHash))||LP(decoded32(contentHash)).
TENANT(null)=0x00; a present tenant is
0x01||LP(UTF8(NFC(type)))||LP(UTF8(NFC(id))). The marker, row identity,
partition, type/id, hashes, headers, key, nonce, ciphertext, and tag are
fixed; retry reuses ciphertext. P23-003B owns these bytes.

P23-003C owns retry configuration and state transitions. maxAttempts is
1..32 (default 5), backoffBaseSeconds is 1..3600 (default 1), and
backoffCapSeconds is 1..86400 (default 3600). For claim attempt n the
deterministic delay is min(cap,base*2^(n-1)); v1 has no jitter. Pending
attempt 0 is eligible only at availableAt<=one trusted transaction-clock
reading and only at the minimum unacknowledged ingressOrdinal. A fenced claim
increments once. Transient/expired lease retry returns pending with that
formula; non-retryable or attempt=maxAttempts becomes poisoned. Only the
matching fencing token can relay, retry, or poison; stale CAS and FIFO bypass
fail. C owns config, clock, eligibility, exhaustion, and retry fixtures.

### AnchorIntent contract

AnchorIntent v1 uses one exact crash-safe state model:
prepared|externally_accepted|receipt_inputs_persisted|receipt_persisted|
acknowledged. The canonical state token is
prepared|externally_accepted|receipt_inputs_persisted|receipt_persisted|acknowledged.
submitted, accepted, and rejected are not aliases.
The closed fields include schemaVersion, kind, intentId, state, partitionId,
checkpointId, checkpointHash, checkpointBytesHash, anchorStoreRef,
anchorObjectRef, expectedPredecessorVersion, expectedPredecessorHash,
conditionalCreateToken, idempotencyKeyHash, acceptedObjectVersion,
acceptedObjectHash, acceptedAnchoredAt, anchorReceiptId,
receiptSigningKeyRef, receiptSignedAt, receiptPreimageHash, receiptBytesHash,
anchorReceiptHash, leaseFencingToken, leaseExpiresAt, version,
previousStateHash, stateHash, updatedAt, and acknowledgedAt. No external
credential is stored.

prepared has all acceptance, receipt, and acknowledgement fields null.
externally_accepted adds accepted object version/hash/time and anchorReceiptId.
receipt_inputs_persisted adds receipt signing key/time, preimage hash, and
bytes hash while semantic receipt hash and acknowledgedAt remain null.
receipt_persisted adds semantic receipt hash. acknowledged adds acknowledgedAt
and is terminal. The conditional token and idempotency hash never change.
Genesis is version 1 with previousStateHash null; every later transition is
exactly previous version plus one and previous state hash, and stateHash is
recomputed over the closed object excluding stateHash.

Lease token and expiry are both null at rest or both present for one fenced
claim. A crash after external acceptance retries the same conditional token,
reads the same object, and persists externally_accepted without N+1. Same-body
idempotency returns the same intent; different body or stale version/hash/token
fails. Receipt preimage, bytes/hash, active non-revoked signing key, and
persist order are fixed; receipt is signed only after inputs persist and
acknowledged only after receipt durability. P23-003F owns this codec,
fencing, key lifecycle, recovery, ordering, and crash/no-N+1 fixtures.

### Retention and hold contract

AuditRetentionState v1 is closed metadata only:
{schemaVersion:1,kind:"audit_retention_state",stateId,target,policy,state,
version,previousStateHash,stateHash,updatedAt,idempotencyKeyHash}. Its only
states are retained and eligible_for_future_deletion. Genesis is retained at version
1 with previousStateHash=null. Every later revision is exactly previous
version+1 with matching previousStateHash. Only retained->retained and
retained->eligible_for_future_deletion are allowed; eligible_for_future_deletion is terminal.
Same-body re-evaluation is idempotent without a new revision. AuditHold is a
separate closed append-only object; an embedded-hold is forbidden, no hold or
holdId may be embedded, and hold is not deletion authority. P23-003G owns
policy/owner/snapshot evaluation and
the 1->3 gap, wrong hash, embedded hold, owner, and reactivation negatives.
Physical deletion remains future P23-003H.

## Round29 sole-current Audit correction

This is the sole current Audit technical contract in this proposal. Stable
`1.2` remains current and Phase 23/`1.3` remains an unreleased proposal. This
source correction does not claim runtime implementation, release, or a change
to the Stable boundary. Workflow and gate status authority is
`develop/STATE.md` plus the parent Task/Report; earlier Round28 wording remains
Historical evidence and cannot override this section.

### A1. Authorization target, request binding, replay, and freshness

`AuditQueryScope v1` is the closed, versioned selection object used by every
page:
`{schemaVersion:1,kind:"audit_query_scope",partitionId,tenantBinding,
filters,order,limit}`. It contains only partition identity and query selection;
it contains no page mode, cursor, head, position, or request reference.
`tenantBinding` is `null` or the closed NFC `{type,id}` object. `filters` is
exactly
`{eventCategory:null|string,actionType:null|string,resourceType:null|string,
resourceId:null|string,recordIdPrefix:null|string,fromSequence:null|positive,
toSequence:null|positive,fromTime:null|timestamp,toTime:null|timestamp}`.
Every member is present. `null` explicitly means no restriction and is not a
wildcard or an omitted semantic. Non-null strings are NFC, control-free, and
1..256 UTF-8 bytes; sequence values are JSON-safe positive integers; times are
UTC with exactly six fractional digits; and each lower bound is at most its
upper bound. `order` is exactly `sequence_asc` and `limit` is 1..1000.
The exact non-circular scope digest is
`queryScopeDigest=HEX_LOWER(SHA-256(LP("blackops.audit.query.scope.v1")||
LP(JCS(scope))))`, where `scope` is the complete canonical `AuditQueryScope`
object and no cursor, head, page, position, or request reference is in its
preimage.

The independent first-page global golden is the exact `scope` object
`{schemaVersion:1,kind:"audit_query_scope",partitionId:"system:global",
tenantBinding:null,filters:{eventCategory:null,actionType:null,resourceType:null,
resourceId:null,recordIdPrefix:null,fromSequence:null,toSequence:null,
fromTime:null,toTime:null},order:"sequence_asc",limit:100}`. Its RFC 8785 JCS
is 311 bytes, its LP preimage is 348 bytes, and its
`queryScopeDigest` is
`d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`.
The corresponding first-page target is exactly
`{schemaVersion:1,kind:"audit_access_target",queryScopeDigest:
"d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7",
pageMode:"first",snapshotHeadSequence:null,cursorDigest:null,
continuationPosition:null}`. The target-bearing Access hash input is exactly
`{purpose:"query",actor:null,tenant:null,target:{schemaVersion:1,
kind:"audit_access_target",queryScopeDigest:
"d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7",
pageMode:"first",snapshotHeadSequence:null,cursorDigest:null,
continuationPosition:null},authorizationDecision:"allow",
requestedAt:"2026-08-19T00:00:00.000000Z",nonce:"n0"}`; its RFC 8785 JCS
is 374 bytes, its LP preimage is 418 bytes, and its requestRef is
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`.

`AuditAccessTarget v1` is the closed versioned binding
`{schemaVersion:1,kind:"audit_access_target",queryScopeDigest,pageMode,
snapshotHeadSequence,cursorDigest,continuationPosition}`. `queryScopeDigest`
is always the exact digest of the complete `AuditQueryScope`. `pageMode` is
exactly `first|continuation`; all of `snapshotHeadSequence`, `cursorDigest`,
and `continuationPosition` are null for `first` and all are non-null for
`continuation`. A continuation position equals the authenticated cursor's
`lastSequence`, and the next selection is strictly greater than it. The raw
cursor digest remains a separate binding:
`cursorDigest=HEX_LOWER(SHA-256(LP("blackops.audit.query.cursor.digest.v1")||
LP(ASCII(exact authenticated cursor token bytes))))`, where the token bytes
are the transmitted ASCII `aqc:v1` token including its dot and tag, with no
added LF or normalization.

The only legal partition is `system:global` when `tenantBinding=null`, or
`tenant:<64 lowercase hex>` derived from the exact normalized tenant object
when it is present. An access decision cannot select a different partition.
`AuditAccessContext v1` is the closed object
`{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,target,
authorizationDecision,requestedAt,nonce,requestRef}`. The decision is exactly
`allow|deny|unavailable`; this current Query wire requires `purpose="query"`
and `authorizationDecision="allow"`. Export, sign, and verify must use
separate versioned exact wires owned by P23-003F/G and must not reuse this
query target.
The context request reference is derived only after the decision is made:
`requestRef="oid:v1:"||HEX_LOWER(SHA-256(
LP("blackops.audit.access.request-ref.v1")||LP(JCS({purpose,actor,tenant,
target,authorizationDecision,requestedAt,nonce}))))`. The object inside JCS
is exact and contains no `requestRef` member, so it has no self-reference.
The context is an internal canonical authorization result generated by the
Framework's trusted `AuthorizationEvaluator`, not caller-supplied request
bytes. External input cannot assert `authorizationDecision=allow` or supply
`requestRef`; neither is accepted as authorization. `requestRef` is an
unkeyed digest binding, not authentication proof; trusted evaluator
provenance is established before this context is produced.
`target` is therefore part of the authorization binding, not a later query
hint. There is no active cyclic digest field: the stable scope digest, raw cursor
digest, fixed head, continuation position, and issuer reference digest are
separate bound fields. `requestRefDigest` is
`HEX_LOWER(SHA-256(LP("blackops.audit.request-ref-digest.v1")||
LP(ASCII(requestRef))))`. A cursor's `requestRefDigest` is the digest of the
AccessContext that issued that cursor. A continuation creates a new
AccessContext and nonce: its `target.cursorDigest` binds the exact prior
cursor, but the prior cursor `requestRefDigest` is not compared with the new
requestRef. Any next cursor stores the digest of the current AccessContext.

`AuditQueryRequest v1` remains the closed object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
limit,order,snapshotHeadSequence,cursor}`. Validation has one public
precedence and completes before database read or decryption. Stage 1 first
checks the raw request byte cap, then closed/canonical JCS shape and
first-versus-continuation nullability. Stage 2 validates the trusted
`AuthorizationEvaluator` provenance, decision, requestRef recomputation,
freshness, and nonce replay. Stage 3 derives the legal partition/tenant and
constructs the complete closed `AuditQueryScope` without a `queryScopeDigest`;
it then computes `queryScopeDigest` separately from that scope.
Stage 4, only for continuation, validates raw cursor ASCII/grammar,
no-padding form, raw token cap, and transmitted-token `cursorDigest`; a
raw-token digest mismatch maps to the explicit finite
`cursor_digest_mismatch` code. Stage 5
resolves the cursor key, including key unavailability, then checks key role,
HMAC, canonical base64url/JCS, the closed payload, key window, and expiry. A
bad HMAC returns `cursor_signature_invalid` before any authenticated-payload
time/window check; key resolution and role failures likewise stop the stage.
Stage 6 checks authenticated scope, fixed head, `lastSequence`,
`continuationPosition=payload.lastSequence`, issuer `requestRefDigest`, page
mode, and complete byte/equality binding of normalized request selection to
`access.target`; only then checks authenticated cursor replay in the same
authorization scope. Stage 7 selects the store page. Stages 1–6 stop at one safe
error and perform no lookup, record selection, or decryption; Stage 7 can only
return a valid `AuditQueryResult`. A continuation uses a new AccessContext and
nonce; its target binds the prior cursor, its prior issuer digest is not
compared to the new request reference, and a nonce is never reused.

The service captures `trustedNow` exactly once from the Framework clock before
freshness or replay checks. `requestedAt` is accepted inclusively only in
`trustedNow-300s <= requestedAt <= trustedNow+30s`; `maxAge=300s` and
`futureSkew=30s` are the bounded defaults, and checked UTC arithmetic rejects
overflow. A nonce is ASCII NFC text matching
`[A-Za-z0-9][A-Za-z0-9._:-]{0,127}`, is 1..128 bytes, is not a credential,
and is unique within the exact authorization scope
`{purpose,actor,tenant,target,authorizationDecision}`. Reuse in that scope
fails closed before lookup. P23-003G owns the target equality, partition,
substitution, age/future-boundary, nonce-replay, continuation,
external-allow/requestRef-injection, and requestRef-not-proof fixtures.

### A2. Canonical partition head and empty traversal

The canonical partition head is the committed sequence high-water read under
the same canonical Audit Store read snapshot and partition fence used for page
selection. It is never a clock value. The head is an integer in
`0..9007199254740991` inclusive; a virgin partition has head `0`, while every
stored record sequence is positive. A first-page snapshot captures that head
once, and concurrent appends after capture are excluded from that traversal.
An empty filtered result on a non-empty partition retains its positive
captured head; an empty virgin result returns head `0`. P23-003G owns first,
middle, final, virgin-empty, filtered-empty, concurrent-append, and head-boundary
fixtures.

### A3. Total query result and authenticated cursor

`AuditQueryResult v1` is exactly
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,hasMore,nextCursor,reasonCode}`. `status` is exactly
`ok|empty|unavailable`. `ok` has an authorized non-null partition, a captured
head including `0`, a non-empty ascending canonical record array, and
`hasMore=true` exactly when another matching record remains under the fixed
head and filters. `nextCursor` is non-null exactly when `hasMore=true`, and
`reasonCode=null`. `empty` has the authorized partition, captured head,
`records=[]`, `hasMore=false`, `nextCursor=null`, and `reasonCode=null`.
`unavailable` has `partitionId=null`, `snapshotHeadSequence=null`,
`records=null`, `hasMore=false`, `nextCursor=null`, and
`reasonCode=resource_unavailable` unless a legal record cannot fit, in which
case the closed reason is `record_too_large`; neither unavailable result
contains partition data or a cursor. These three statuses are returned only
after valid authorization and cursor validation; store selection is the final
result-selection stage. Malformed or rejected requests use the separate error
wire below.

`AuditQueryError v1` is the closed public error wire
`{schemaVersion:1,kind:"audit_query_error",code,detail:null}`. `code` is
exactly one of
`malformed_request|request_too_large|authorization_provenance_invalid|
authorization_denied|authorization_unavailable|request_ref_mismatch|
stale_request|future_request|nonce_replay|
malformed_cursor|cursor_too_large|cursor_key_unavailable|cursor_wrong_role|
cursor_not_yet_valid|cursor_expired|cursor_signature_invalid|cursor_digest_mismatch|cursor_replay|
scope_mismatch|head_mismatch|position_mismatch`. `detail` is always null, so no existence,
partition, key, or cryptographic oracle is exposed. Stage 2 is total:
invalid trusted evaluator provenance maps to
`authorization_provenance_invalid`; a deny decision maps to
`authorization_denied`; an unavailable decision maps to
`authorization_unavailable`; a requestRef recomputation or context binding
mismatch maps to `request_ref_mismatch`; an old requestedAt maps to
`stale_request`; a future requestedAt maps to `future_request`; and nonce reuse
maps to `nonce_replay`. Stage 3 is total: any partition derivation, tenant
binding, or query-scope predicate failure maps to `scope_mismatch`; no such
failure falls through to a later code. The one precedence is
closed:
(1) raw request cap, then closed/canonical request shape and nullability; (2)
trusted authorization result/provenance, decision, requestRef recomputation,
freshness, and replay; (3) partition/tenant/query-scope derivation; (4) raw
cursor cap, grammar, and transmitted-token digest, with a digest mismatch
mapping only to `cursor_digest_mismatch`; (5) key
resolution/unavailability, role, HMAC, canonical payload, key window, and
expiry; (6) authenticated scope/head/position/request-selection equality; and
(7) valid store selection/result. Stage 6 emits `cursor_replay` only after the
authenticated cursor binding and before any store read. Within stage 5, a bad HMAC returns
`cursor_signature_invalid` before any authenticated-payload time/window
failure. Stages 1–6 stop at the first safe error code and perform no later
stage, lookup, or decryption. Stage 7 is result selection only: store failure returns
`AuditQueryResult.unavailable` with `reasonCode=resource_unavailable`, and a
legal record that cannot fit returns the same status with
`reasonCode=record_too_large`; neither emits `AuditQueryError`. Reusing a
cursor in the same authorization scope emits `cursor_replay` and performs no
lookup. A compound `nonce_replay+cursor_digest_mismatch` input emits the earlier
Stage 2 `nonce_replay` code; it never reaches cursor validation or lookup. A
normal raw-token digest mismatch emits `cursor_digest_mismatch` at Stage 4.
These fixtures use the same canonical vectors in Node and PHP, assert
byte-identical codes and precedence, and perform zero database reads, record
selection, or decryption. A malformed or oversize request/cursor uses the
error wire.
P23-003G owns compound `malformed+deny`, `wrong-role+bad-HMAC`,
`bad-HMAC+not-yet/expired`, provenance-invalid, requestRef-mismatch,
first/continuation partition-substitution, normal raw-token digest-mismatch,
compound `nonce_replay+cursor_digest_mismatch`, and authenticated-cursor-replay
fixtures; each emits one code by this precedence and performs zero store read,
record selection, or decryption, with the normal and compound vectors checked
for Node/PHP parity.

The selected page is the longest canonical prefix satisfying `limit` and the
inclusive result wire cap. Request `JCS(request)||LF` is at most 64 KiB and
result `JCS(result)||LF` is at most 1 MiB. If one legal canonical record cannot
fit in an otherwise empty result, the service returns `unavailable` with
`record_too_large` and no data. A shorter fitting prefix is valid `ok` output
with a bound cursor when later records remain. Cap arithmetic is checked before
allocation.

The cursor is authenticated clear JCS/HMAC and is never encrypted:
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, with one
literal dot, no padding, ASCII, and a total cap of 2048 bytes. Its closed
payload is
`{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,limit,order,
queryScopeDigest,requestRefDigest,snapshotHeadSequence,lastSequence,
issuedAt,expiresAt,keyId}`. `partitionId`, `filters`, `limit`, and `order` are
the exact canonical projection of `AuditQueryScope`; the payload digest must
equal the exact `queryScopeDigest` for that scope. `requestRefDigest` is the
issuing AccessContext digest described in A1; it is carried through a middle
page without being compared to the new continuation AccessContext. The tag is HMAC-SHA-256 over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))` using the exactly
32-byte key for role `audit-query-cursor` selected by `keyId`. Canonical
base64url/JCS, closed fields, caps, key role, partition/scope/requestRef
digests, fixed head, ordering, last sequence, and expiry are verified before
any database read; record selection and decryption occur only after all
authorization and cursor checks pass. The first-to-middle-to-final cursor
chain must preserve each issuing requestRefDigest and reject prior-cursor,
scope, head, or position substitution. TTL is configurable from
60..3600
seconds inclusive, defaults to 900, and uses overflow-safe
`expiresAt=issuedAt+ttl`; `issuedAt <= trustedNow < expiresAt` is inclusive at
the issue boundary and exclusive at expiry. The key window is
`notBefore <= issuedAt <= trustedNow < expiresAt <= notAfter`. Issuance
requires `expiresAt <= notAfter`; equality is valid and `expiresAt > notAfter`
is rejected before lookup. A verification key remains available until every
cursor it could sign has expired plus the accepted 30-second skew; issuance
uses only the active key. Rotation, replay, substitution, scope/head/position,
first-middle-final ref-chain, expiry-boundary/equality, expiry>notAfter,
key-role, cap, public-error-precedence, and no-decrypt-order fixtures belong to
P23-003G. The required three-page vector is
first→middle→final with normal PASS; C1 swaps scope or the prior raw cursor
and fails; C2 substitutes fixed head or continuation position/lastSequence and
fails. Tampering with the issuer `requestRefDigest` fails as
`cursor_signature_invalid`; a new AccessContext/target mismatch maps to the
applicable `scope_mismatch`, `head_mismatch`, or `position_mismatch` code;
partition/tenant substitution is `scope_mismatch`. A new continuation binds
its prior cursor digest while any
newly emitted cursor stores the current AccessContext requestRefDigest.

### A4. Audit Outbox state, fence, FIFO, and protection

`AuditIngressEnvelope v1` is the exact closed durable-only object
`{schemaVersion:1,kind:"audit_ingress_envelope",logicalRecord,contentHash,
recordedAt,ingressOrdinal,idempotencyKey,recordId,partitionId,tenantBinding}`.
`logicalRecord` is the closed 14-field `AuditRecord v1` body without
`integrity`; its closed `time` object contains `recordedAt`, and it has no
top-level `recordedAt` member. `contentHash` binds that exact canonical body. The
envelope has
four individual bindings: `recordId=logicalRecord.recordId`,
`recordedAt=logicalRecord.time.recordedAt`,
`tenantBinding=logicalRecord.tenant`,
and `partitionId=derivePartition(tenantBinding)` under the active canonical
mapping. It does not compare `partitionId` to the `logicalRecord` object. The
exact hashes are
identical in all three current authority files, using the established
`H=SHA-256` and lowercase-hex encoding:
`idempotencyKeyHash=H(ENC("blackops.audit.outbox.idempotency.v1")||
ENC(partitionId)||ENC(NFC(idempotencyKey)))` and
`contentHash=H(ENC("blackops.audit.content.v1")||
ENC(canonicalLogicalBytes))`, where `canonicalLogicalBytes` is the exact
Section 9.6 RFC 8785 JCS byte string for the canonical logical AuditRecord
field set without
`integrity`. These are the active Section 9.6 formulas. The raw idempotency
key is protected and never a clear row field. `recordedAt` is trusted UTC
with six fractional digits and `ingressOrdinal` is positive JSON-safe and
allocated under the partition fence. The Node/PHP positive fixture proves
that envelope `recordedAt` equals `logicalRecord.time.recordedAt`. Any
substitution, omission, or mismatch of that envelope/nested binding, or an
injected top-level `logicalRecord.recordedAt`, fails closed before enqueue,
decrypt, or canonical append. Every top-level extra member in `logicalRecord`
remains forbidden by its closed 14-field schema.

`AuditOutboxRecord v1` is the exact closed row
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody,version}`. `version`
is a positive JSON-safe integer. Genesis is version `1`; every successful
mutation increments it exactly once. The full state matrix is closed:
`pending` has a positive version, attempt `0..maxAttempts-1`, non-null
`availableAt`, and null lease fields; `leased` has a positive version, attempt
`1..maxAttempts`, null `availableAt`, and non-null token and expiry; `relayed`
and `poisoned` have positive versions, attempt `1..maxAttempts`, and null
scheduling and lease fields. Initial ingress is `pending`, `attempt=0`,
`version=1`, and `availableAt` non-null. A claim is valid only from pending
attempt `n<maxAttempts`; it increments attempt exactly to `n+1` and version
exactly once, and yields `leased` with `availableAt=null` and both token and
expiry non-null. A persisted or decoded `pending` row with
`attempt=maxAttempts` is invalid and fails closed; no claim may create attempt
`maxAttempts+1`. A retry is valid only
when `attempt<maxAttempts`; it uses expected version plus the matching active
token, preserves attempt, increments version once, computes a new checked
`availableAt`, clears both lease fields, and returns to `pending`. A successful
relay `leased -> relayed` and terminal exhaustion/non-retryable failure
`leased -> poisoned` each require the same matching non-expired active token
and expected current version, increment version exactly once, and clear
`availableAt`, token, and expiry. `relayed` and `poisoned` are terminal; a
stale owner, wrong token, version gap, expired-owner terminal settlement, or second terminal
settlement performs no mutation.

The Outbox owner rules are disjoint. A `pending` row has a null stored lease
token; its eligible claim supplies no matching owner token, installs one new
token, increments `attempt` from `n` to `n+1`, and increments `version` by
exactly one in the same expected-version CAS. A `leased` settlement to
`relayed` or `poisoned` requires the matching stored non-expired token and the
expected current version, then increments `version` exactly once. Expired-
lease recovery requires the matching stored token that is expired, preserves
`attempt`, clears the lease, and increments `version` exactly once. A lease is
non-expired only when `trustedNow < leaseExpiresAt`; equality is expired. A
null-token pending claim, matching-unexpired settlement, matching-expired
recovery, exact +1 CAS, and expiry-equality fixture are required; no blanket
rule that every transition matches an active token applies.

Expired-lease recovery is a failed attempt, so a `leased` row is recoverable
only when its expected current row version and matching expired lease token
pass the fenced CAS. When `attempt<maxAttempts`, recovery preserves attempt,
increments version exactly once, clears both lease fields, and returns to
`pending` with a checked backoff `availableAt` from one trusted transition
clock. When `attempt=maxAttempts`, the same fenced CAS increments version,
clears scheduling and lease fields, and returns terminal `poisoned`; it never
returns to `pending`. A pending row is claimable only when `attempt<maxAttempts`.
The next valid claim obtains a new token and increments attempt through an
expected-version CAS. Stale owners, version gaps, and a max+1 claim fail
closed. Every claim, relay, retry, poison, and recovery transition captures
one trusted transition clock for its fence decision. A lease is active exactly
when `trustedNow < leaseExpiresAt`; it is expired exactly when
`trustedNow >= leaseExpiresAt`, so equality is an expired boundary. The same
exclusive rule is used for relay versus recovery and every retry/poison
decision; `expiry-1us`, equality, and `expiry+1us` fixtures use one captured
clock and cannot produce two successful mutations. The
minimum-unacknowledged rule considers only `pending|leased` rows;
`relayed` and `poisoned` are acknowledged terminal positions, so a poisoned
ordinal is preserved for the DLQ/audit and does not block a later ordinal. A
later claim still cannot bypass any lower pending or active leased ordinal.

`maxAttempts` is 1..32 inclusive (default 5), `backoffBaseSeconds` is 1..3600
(default 1), and `backoffCapSeconds` is 1..86400 (default 3600). For failed
claim attempt `n`, define `d(1)=min(backoffBaseSeconds,backoffCapSeconds)`.
For every later attempt, calculate `d(n+1)=backoffCapSeconds` when
`d(n)>floor(backoffCapSeconds/2)`; otherwise calculate `d(n+1)=2*d(n)`.
The comparison occurs before multiplication, so this single saturating
algorithm never overflows and never chooses between rejection and capping for
valid configuration or attempt values. Only invalid bounds reject; jitter is
not part of v1. Every transition uses one trusted clock reading. The
protected plaintext is JCS of the
envelope without LF and the binary body is immutable BOPD v1. With
`LP(x)=U32BE(byte_length(x))||x`, the exact AAD is
`LP("blackops.audit.outbox.aad.v1")||LP("AuditOutboxRecord")||LP("1")||
LP("1")||LP("1")||LP(exactKeyId)||LP(recordId)||LP(partitionId)||
LP(TENANT(tenantBinding))||LP(ASCII(canonicalDecimal(ingressOrdinal)))||
LP(decoded32(idempotencyKeyHash))||LP(decoded32(contentHash))`, where
`TENANT(null)=0x00` and
`TENANT({type,id})=0x01||LP(UTF8(NFC(type)))||LP(UTF8(NFC(id)))`. The first
three `1` values are schema, envelope, and algorithm; `AuditOutboxRecord` is
the protected purpose. Tenant authentication and partition equality happen
before decrypt. The database logical row stores binary immutable BOPD v1
`protectedBody` separately from canonical metadata JCS; a JSON projection,
when explicitly used, represents that binary member only as base64url. The
64 KiB inclusive metadata-row JCS+LF cap excludes the binary protected body.
The protected envelope plaintext and binary body are each at most 1 MiB
inclusive, and BOPD allocation is checked against
`plaintextBytes+52+keyIdUtf8Bytes`; cap+1 fails before allocation. The raw
idempotency key is NFC, control-free UTF-8 text of 1..256 bytes and is never a
clear row member. Every `availableAt` and `leaseExpiresAt` is trusted UTC with
exactly six fractional digits. The exact `leaseDurationSeconds` configuration
is 1..86400 inclusive, default 300; lease expiry is checked overflow-safe from
one trusted claim time. P23-003B/C own state/nullability, stale/expired fence,
attempt=max expired-to-poisoned, stale-version and version-gap, max+1 claim
rejection, poison FIFO, leased-terminal token/version CAS,
attempts/backoff saturation, binary/logical representation, idempotency
normalization, timestamp, nested-time binding, lease-duration, row/body/overhead caps, and
header/AAD/tenant/hash/body substitution fixtures.

### A5. AnchorIntent state, external result, and recovery

The one closed persisted state vocabulary is
`prepared|externally_accepted|externally_rejected|receipt_inputs_persisted|
receipt_persisted|acknowledged`; `submitted`, `accepted`, and `rejected` are
not state aliases. `externally_rejected` is the terminal persisted state for
a permanent external rejection; the external result token `rejected` maps to
that state and is never stored as a state token.
`AnchorIntent v1` is the closed object
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,
checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,
anchorObjectRef,expectedPredecessorVersion,expectedPredecessorHash,
conditionalCreateToken,idempotencyKeyHash,acceptedObjectVersion,
acceptedObjectHash,acceptedAnchoredAt,anchorReceiptId,segmentId,
previousAnchorReceiptHash,receiptSigningKeyRef,receiptSignedAt,
receiptPreimageHash,receiptBytesHash,anchorReceiptHash,externalCallPhase,
preCallClaimMode,
preCallLookupStatus,
leaseFencingToken,
leaseExpiresAt,attempt,nextAvailableAt,terminalReasonCode,version,
previousStateHash,stateHash,updatedAt,acknowledgedAt}`. All IDs/refs/tokens are
bounded NFC/no-control values and hashes are lowercase 64-hex. `version` is a
positive JSON-safe integer. `attempt` is 0..32. `externalCallPhase` is exactly
`pre_call|post_call_uncertain|null`; it is non-null exactly in `prepared`, where
`pre_call` means that no external side effect has been issued for the current
attempt 1..32; the genesis attempt 0 row is the explicit pre-call seed
exception and has no prior external attempt. `post_call_uncertain` means that a
side effect may have been issued and reconciliation is mandatory before another
external call.
`preCallClaimMode` is exactly `new_attempt|retry_same_attempt|null`; it is
non-null exactly when `state=prepared` and `externalCallPhase=pre_call`.
`new_attempt` permits only `external_submission_claim` and increments attempt;
`retry_same_attempt` permits only `pre_call_retry_claim` and preserves attempt.
Genesis sets `new_attempt`; post-call explicit absence below 32 sets
`new_attempt`; pre-call lookup unavailability or expiry/reclaim sets
`retry_same_attempt`. The mode is preserved by a pre-call active lease, is
cleared when entering `post_call_uncertain` or any non-prepared state, and any
other state/phase/mode/status combination is invalid.
`preCallLookupStatus` is exactly `pending|absent|null`. It is null at
`prepared/pre_call` rest before a claim, in `post_call_uncertain`, and in every
non-prepared state. Both external claim operations install an active token and
set `pending`; `external_lookup` is valid only for an active
`prepared/pre_call/pending` row. Found settles acceptance and clears lease,
mode, and status; unavailable clears lease and status and selects
`retry_same_attempt`; explicit absent retains the active token and mode and
sets `absent` with +1. `external_call_start` is valid only for active
`prepared/pre_call/absent`, then clears mode and status before entering
`post_call_uncertain`. An expired pre-call pending or absent lease reclaim
clears lease and status and selects `retry_same_attempt`; no call is valid until
a new pending lookup is followed by an explicit absent result.
`nextAvailableAt` is null at genesis, while a claim is active, and in every
terminal or otherwise non-retryable rest row. Every nonterminal prepared rest
row that schedules another lookup, claim, or reconciliation has a non-null
value computed by the Framework-owned retry rule below.
`terminalReasonCode` is null except in
`externally_rejected`, where it is exactly one of
`predecessor_mismatch|permanent_rejection|attempt_exhausted`. Intent genesis
is `version=1` with `previousStateHash=null`; every claim, external-call
marker, external result, reconciliation result, lease reclaim, and persistence
revision is exactly `version+1`, links `previousStateHash` to the prior
`stateHash`, and recomputes `stateHash` excluding itself.
`conditionalCreateToken` and `idempotencyKeyHash` are non-null in the initial
`prepared` row and immutable thereafter. The `anchorLeaseDurationSeconds`
configuration is `1..86400` seconds inclusive, default `300`. The separate
Framework-owned `anchorRetryDelaySeconds` configuration is a closed integer in
`1..86400` seconds inclusive, default `300`. Each claim captures one trusted
`claimNow` exactly once and computes
`leaseExpiresAt=claimNow+anchorLeaseDurationSeconds` with checked overflow-safe
arithmetic. Every successful operation captures one trusted `transitionNow`
exactly once; it must not precede the prior `updatedAt` and writes
`updatedAt=transitionNow`. For each of the six scheduling branches below, the
Framework validates and captures `anchorRetryDelaySeconds` exactly once and
computes `nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds`
with checked overflow-safe arithmetic, retaining exactly six UTC fractional
digits. v1 has no jitter, provider clock, or provider retry-time authority;
the adapter reports outcome fields only.
Lease expiry, reclaim, and every later operation use expected token/version/
state-hash CAS order; stale owners and clock rollback produce no revision.

The operation categories are disjoint even though the operation name is not a
persisted member: `external_submission_claim`, `pre_call_retry_claim`,
`external_reconciliation_claim`, `external_call_start`, `external_lookup`,
`external_result`, `external_reconciliation`, `receipt_input_claim`,
`receipt_input_persist`, `receipt_persist_claim`, `receipt_persist`,
`acknowledgement_claim`, `acknowledgement_persist`, and `lease_reclaim` each
accept only the state, phase, lease condition, expected version/hash, and
matching token described in the total matrix below. A token accepted for one
operation cannot settle another operation.

Genesis creation uses `expectedVersion=0` and `expectedStateHash=null` to create
the initial `prepared` row at `version=1`, `attempt=0`,
`externalCallPhase=pre_call`, `preCallClaimMode=new_attempt`,
`preCallLookupStatus=null`, with both lease fields null; it installs no token
and cannot overwrite an existing row.
`external_submission_claim` requires `prepared`, `pre_call`,
`preCallClaimMode=new_attempt`, `preCallLookupStatus=null`, a null lease, and
exactly one of these due guards: genesis `attempt=0,nextAvailableAt=null`, which
is immediately claimable, or `attempt=1..31` with non-null
`nextAvailableAt` and `trustedNow>=nextAvailableAt`. It rejects attempt 0 with
`retry_same_attempt`, every positive attempt with null `nextAvailableAt`, and
every future `nextAvailableAt`. It installs one new token, sets
`preCallLookupStatus=pending`, increments attempt exactly once, sets
`nextAvailableAt=null` while active, and writes +1.
`pre_call_retry_claim` requires `prepared`, `pre_call`,
`preCallClaimMode=retry_same_attempt`, `preCallLookupStatus=null`, a null
lease, `attempt=1..32`, and non-null `nextAvailableAt` with
`trustedNow>=nextAvailableAt`; attempt 0, null scheduling, and future scheduling
are invalid. It installs a new token, sets `pending`, preserves attempt, sets
`nextAvailableAt=null` while active, and writes +1.
`external_reconciliation_claim` requires `prepared`,
`post_call_uncertain`, `preCallClaimMode=null`, `preCallLookupStatus=null`, a
null lease, and non-null `nextAvailableAt` with
`trustedNow>=nextAvailableAt`; it installs a new token, sets
`nextAvailableAt=null` while active, and does not change attempt. Claim
acquisition only installs a new token and does not settle a state.

`external_call_start` requires the matching active token on a
`prepared/pre_call/absent` claim, writes +1 while preserving the lease and
attempt, changes the phase to `post_call_uncertain`, and clears
`preCallClaimMode` and `preCallLookupStatus`. This marker is durable before the
external call;
the call is never issued from a post-call-uncertain branch without a fresh
explicit-absence reconciliation. Internal claim operations similarly install
a new token on `externally_accepted`, `receipt_inputs_persisted`, or
`receipt_persisted`, preserve state and attempt, and write +1. State progression
and lease clearing are separate settlement operations that require the matching
active token, expected version, and expected state hash. `lease_reclaim` alone
may use a matching stored token at `trustedNow>=leaseExpiresAt`; it clears the
lease with +1 and preserves the phase/state required by the matrix. Equality is
the expired boundary. Creation, claim, marker, settlement, duplicate-creation,
stale-version, stale-hash, token-ownership, and clock-rollback fixtures are
required and owned by P23-003F.

The total external call result is the closed object
`{schemaVersion:1,kind:"audit_anchor_external_result",status,reasonCode,
acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,attempt}`. `status`
is exactly `accepted|rejected|unavailable`. Every external result carries a
positive `attempt` equal to the active lease attempt and is accepted only for
`prepared/post_call_uncertain` with its matching active external token and
expected version/hash; the persisted state preserves that attempt. An adapter
object containing `nextAvailableAt` is an unknown extra field and fails closed.
Accepted has the three accepted-object fields non-null and `reasonCode=null`,
and the fenced settlement moves to `externally_accepted` with both lease fields
null and `externalCallPhase=null`, `preCallClaimMode=null`, and
`preCallLookupStatus=null`; it does not schedule a retry. Rejected has those
fields null and one permanent `reasonCode` from
`predecessor_mismatch|permanent_rejection`; the fenced settlement moves to
terminal `externally_rejected`, clears the lease, and cannot be retried; it
does not schedule a retry. Unavailable has `reasonCode=external_unavailable`
and an attempt in 1..32. The adapter supplies no schedule. Its fenced
settlement clears the lease, preserves attempt, leaves `state=prepared` and
`externalCallPhase=post_call_uncertain`, `preCallClaimMode=null`, and
`preCallLookupStatus=null`, and, for every attempt 1..32, the Framework captures
`transitionNow` exactly once and computes
`nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds` with
checked overflow-safe arithmetic for future reconciliation. No external result
is valid for `pre_call`, an internal token, or an expired lease. No result can
create attempt 33 or an infinite ambiguous retry.

Prepared initial state has all acceptance/receipt fields null, `segmentId=null`,
`previousAnchorReceiptHash=null`, attempt 0,
`externalCallPhase=pre_call,preCallClaimMode=new_attempt,
preCallLookupStatus=null`,
`nextAvailableAt=null`, terminalReasonCode null, and both lease fields null at
rest. A retryable pre-call prepared state has a positive attempt, phase
`pre_call`, a non-null `nextAvailableAt` computed by the exact Framework retry
rule below, and no terminal reason; its mode is
`new_attempt` for an incrementing claim or `retry_same_attempt` for a
same-attempt retry, and the mode is preserved while its pre-call lease is
active; its rest status is null. An active pre-call claim has
`preCallLookupStatus=pending`; an explicit absent lookup changes only that
status to `absent` and permits `external_call_start`. A
reconciliation-only prepared state has phase `post_call_uncertain`, a positive
attempt, `preCallClaimMode=null`, `preCallLookupStatus=null`, a non-null
`nextAvailableAt` computed by the exact Framework retry rule below, and no
terminal reason; it is never
eligible for an external-submission claim. Externally rejected has all accepted
and receipt fields null, `segmentId=null`, `previousAnchorReceiptHash=null`,
preserves the positive attempt, has `nextAvailableAt=null`, a non-null closed
terminalReasonCode, `externalCallPhase=null`, `preCallClaimMode=null`,
`preCallLookupStatus=null`, and both lease fields null; it is terminal.
Externally accepted has accepted
version/hash/time and
anchorReceiptId non-null, `segmentId` non-null, and
`previousAnchorReceiptHash=null` exactly at authenticated external anchor-chain
genesis or the exact predecessor receipt hash otherwise. Its phase,
`preCallClaimMode`, and `preCallLookupStatus` are null and its lease is null at
rest.
Receipt-inputs-persisted adds
receiptSigningKeyRef, receiptSignedAt, receiptPreimageHash, and receiptBytesHash
while anchorReceiptHash and acknowledgedAt remain null. Receipt-persisted adds
anchorReceiptHash; acknowledged adds acknowledgedAt and is terminal. The
segment and predecessor fields are chain inputs, not fields covered by the
phrase “all receipt fields null”.

The single total state × lease × phase × pre-call-lookup-status × operation
matrix is:

| state | lease | phase | pre-call lookup status | operation | valid transition |
|---|---|---|---|---|---|
| `prepared` | null | `pre_call` | null | `external_submission_claim` | Only with `preCallClaimMode=new_attempt`; genesis `attempt=0,nextAvailableAt=null` is immediately due, while `attempt=1..31` requires non-null `nextAvailableAt` with `trustedNow>=nextAvailableAt`; positive null or future scheduling and attempt 0 retry mode are invalid. Install a new token/expiry, set `preCallLookupStatus=pending`, increment attempt once, set `nextAvailableAt=null` while active, and write version +1. |
| `prepared` | null | `pre_call` | null | `pre_call_retry_claim` | Only with `preCallClaimMode=retry_same_attempt`, `attempt=1..32`, and non-null `nextAvailableAt` with `trustedNow>=nextAvailableAt`; attempt 0, null scheduling, and future scheduling are invalid. Install a new token/expiry, set `pending`, preserve attempt/mode, set `nextAvailableAt=null` while active, and write +1. |
| `prepared` | null | `post_call_uncertain` | null | `external_reconciliation_claim` | Only with `preCallClaimMode=null` and non-null `nextAvailableAt` with `trustedNow>=nextAvailableAt`; install a new reconciliation token/expiry, set `nextAvailableAt=null` while active, preserve attempt/phase, and write +1; an external-submission claim is invalid. |
| `prepared` | active | `pre_call` | pending | `external_lookup` | Only with the matching active external token and non-null mode; the active claim has `nextAvailableAt=null`: found settles to `externally_accepted` and clears lease/mode/status; absent records +1 while retaining token/expiry/mode and sets status `absent`; unavailable clears lease, preserves phase, sets `preCallClaimMode=retry_same_attempt` and status null, captures the existing trusted `transitionNow` exactly once, validates/captures `anchorRetryDelaySeconds` exactly once, computes `nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds` with checked overflow-safe arithmetic retaining exactly six UTC fractional digits, and writes +1. |
| `prepared` | active | `pre_call` | absent | `external_call_start` | Only with the matching active token: write +1, change phase to `post_call_uncertain`, clear mode/status, retain token/expiry and attempt, then issue exactly one external call. This includes attempt 32. |
| `prepared` | active | `post_call_uncertain` | null | `external_result` | Only with `preCallClaimMode=null` and the matching active external token: `accepted` settles to `externally_accepted`, `rejected` settles to terminal `externally_rejected`, and `unavailable` for every attempt 1..32 clears the lease into reconciliation-only prepared, captures the existing trusted `transitionNow` exactly once, validates/captures `anchorRetryDelaySeconds` exactly once, computes `nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds` with checked overflow-safe arithmetic retaining exactly six UTC fractional digits, and writes +1. |
| `prepared` | active | `post_call_uncertain` | null | `external_reconciliation` | With the matching active reconciliation token and null mode: found settles to `externally_accepted` and clears lease; absent at attempt `<32` clears lease, changes phase to `pre_call`, sets `preCallClaimMode=new_attempt`, captures the existing trusted `transitionNow` exactly once, validates/captures `anchorRetryDelaySeconds` exactly once, computes `nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds` with checked overflow-safe arithmetic retaining exactly six UTC fractional digits for the next attempt claim, while absent at attempt `32` settles to `externally_rejected/attempt_exhausted` without a schedule; unavailable clears lease, preserves post-call phase with null status, captures the existing trusted `transitionNow` exactly once, validates/captures `anchorRetryDelaySeconds` exactly once, computes `nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds` with checked overflow-safe arithmetic retaining exactly six UTC fractional digits. Each writes +1. |
| `prepared` | expired | `pre_call` | pending or absent | `lease_reclaim` | With the matching stored expired token, clear lease and status, set `preCallClaimMode=retry_same_attempt`, capture the existing trusted `transitionNow` exactly once, validate/capture `anchorRetryDelaySeconds` exactly once, compute `nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds` with checked overflow-safe arithmetic retaining exactly six UTC fractional digits, and write +1; a new pending claim and explicit absent lookup are required before any call. Equality at `trustedNow=leaseExpiresAt` is expired. |
| `prepared` | expired | `post_call_uncertain` | null | `lease_reclaim` | With the matching stored expired token, clear the lease, preserve null mode/status and post-call phase, capture the existing trusted `transitionNow` exactly once, validate/capture `anchorRetryDelaySeconds` exactly once, compute and persist non-null `nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds` with checked overflow-safe arithmetic retaining exactly six UTC fractional digits before a future `external_reconciliation_claim` can become due, and write +1. Equality at `trustedNow=leaseExpiresAt` is expired. |
| `externally_accepted` | null | null | null | `receipt_input_claim` | Only with `preCallClaimMode=null`; install a new internal token/expiry, preserve state and attempt, and write +1. |
| `externally_accepted` | active | null | null | `receipt_input_persist` | Only with `preCallClaimMode=null` and the matching active token, persist receipt inputs, move to `receipt_inputs_persisted`, clear lease, and write +1. |
| `externally_accepted` | expired | null | null | `lease_reclaim` | With the matching expired token, clear lease, preserve state/attempt, and write +1; the next claim installs a new token. |
| `receipt_inputs_persisted` | null | null | null | `receipt_persist_claim` | Only with `preCallClaimMode=null`; install a new internal token/expiry, preserve state and attempt, and write +1. |
| `receipt_inputs_persisted` | active | null | null | `receipt_persist` | Only with `preCallClaimMode=null` and the matching active token, persist `anchorReceiptHash`, move to `receipt_persisted`, clear lease, and write +1. |
| `receipt_inputs_persisted` | expired | null | null | `lease_reclaim` | With the matching expired token, clear lease, preserve state/attempt, and write +1; the next claim installs a new token. |
| `receipt_persisted` | null | null | null | `acknowledgement_claim` | Only with `preCallClaimMode=null`; install a new internal token/expiry, preserve state and attempt, and write +1. |
| `receipt_persisted` | active | null | null | `acknowledgement_persist` | Only with `preCallClaimMode=null` and the matching active token, persist `acknowledgedAt`, move to terminal `acknowledged`, clear lease, and write +1. |
| `receipt_persisted` | expired | null | null | `lease_reclaim` | With the matching expired token, clear lease, preserve state/attempt, and write +1; the next claim installs a new token. |
| `externally_rejected` or `acknowledged` | null | null | null | any claim/settlement | Terminal and unclaimable; every operation is rejected with no mutation. |

In the matrix, active means `trustedNow < leaseExpiresAt` and requires both
lease fields plus the byte-matching token; expired means
`trustedNow >= leaseExpiresAt` and can be consumed only by `lease_reclaim`.
`preCallClaimMode` is null in every non-prepared state and in every
`post_call_uncertain` row; a result, settlement, or internal persistence row
that would retain a non-null mode is invalid. `preCallLookupStatus` is null in
every rest row, post-call row, and non-prepared row; only an active
`prepared/pre_call` row contains `pending` or `absent`; `pending` is valid only
for `external_lookup` and `absent` is valid only for `external_call_start`.
`nextAvailableAt` is null only for genesis, an active claim, a terminal row, or
another non-retryable rest row. Every other prepared rest row that schedules a
retry, lookup, or reconciliation captures the existing trusted `transitionNow`
exactly once, validates/captures `anchorRetryDelaySeconds` exactly once, and
computes `nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds`
with checked overflow-safe arithmetic retaining exactly six UTC fractional
digits. Claim due means `trustedNow>=nextAvailableAt`, so equality is due and
future scheduling is not.
The sole null-schedule claim is genesis attempt 0; positive null-schedule,
attempt-0 retry, and attempt-0 positive-only operations are invalid.
Every valid row requires the expected current version and state hash, advances
version exactly once, links `previousStateHash` exactly once, recomputes
`stateHash` including
phase/preCallClaimMode/preCallLookupStatus/lease/attempt/nextAvailableAt/updatedAt, and
preserves attempt
except for `external_submission_claim` from 0..31 to 1..32. No row can create
attempt 33, clear a lease with an expired active-settlement token, or reuse an
internal token for another state. Pending-to-absent substitution,
absent-to-pending substitution, duplicate lookup, duplicate call-start, and a
call-start before the pending lookup plus explicit absent result are fail-closed.
Any combination not listed is fail-closed and performs no mutation.

Genesis requires both expected predecessor fields null and accepted object
version 1. Non-genesis requires both predecessor fields non-null, the accepted
version equal to predecessor version+1, and the predecessor hash byte-exact.
In every accepted state `acceptedObjectHash=checkpointBytesHash`. Conditional
create/lookup returns the same intent only for the same idempotency key and
same request body, including the authenticated chain binding; a different
body or stale token/version/hash is a conflict. The deterministic
`AnchorExternalReconciliation v1` result is exactly
`{schemaVersion:1,kind:"audit_anchor_reconciliation",outcome,foundObject,
reasonCode}`. `outcome` is exactly `external_object_found|external_object_absent|
lookup_unavailable`; `foundObject` is non-null only for
`external_object_found` and exactly
`{anchorObjectRef,anchorObjectVersion,acceptedObjectHash,acceptedAnchoredAt,
segmentId,previousAnchorReceiptHash}`, byte-equal to the immutable intent,
checkpoint, and authenticated chain bindings. `reasonCode` is null for found
and explicit absent, and exactly `lookup_unavailable` for that outcome. The
lookup is bound to immutable `intentId`, `conditionalCreateToken`, and
`idempotencyKeyHash`.

Every positive-attempt external-submission claim, including attempt 31, must
perform the same-object lookup before any external side effect. In `pre_call`,
found applies one fenced +1 revision to `externally_accepted` and clears the
lease, mode, and `preCallLookupStatus`; explicit absent applies one +1
revision while retaining the active token/expiry and `preCallClaimMode`, sets
`preCallLookupStatus=absent`, and permits `external_call_start`, including the
32nd call; lookup unavailable clears the lease, preserves `pre_call`, sets
`preCallClaimMode=retry_same_attempt` and `preCallLookupStatus=null`, captures
the existing trusted `transitionNow` exactly once, validates/captures
`anchorRetryDelaySeconds` exactly once, and computes
`nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds` with
checked overflow-safe arithmetic retaining exactly six UTC fractional digits
before scheduling a `pre_call_retry_claim`. A pre-call expiry/reclaim from
either `pending` or `absent` likewise clears only the matching expired lease
and status, sets `retry_same_attempt`, captures the existing trusted
`transitionNow` exactly once, validates/captures `anchorRetryDelaySeconds`
exactly once, and computes `nextAvailableAt = transitionNow +
anchorRetryDelaySeconds seconds` with checked overflow-safe arithmetic
retaining exactly six UTC fractional digits; it leaves the next claim at the
same attempt. A new claim must set `pending` before another lookup.
In `post_call_uncertain`, found applies one fenced +1 revision to
`externally_accepted` and clears the lease/status; explicit absent at attempt `<32`
clears the lease, changes the phase to `pre_call`, sets
`preCallClaimMode=new_attempt` and `preCallLookupStatus=null`, captures the
existing trusted `transitionNow` exactly once, validates/captures
`anchorRetryDelaySeconds` exactly once, and computes
`nextAvailableAt = transitionNow + anchorRetryDelaySeconds seconds` with
checked overflow-safe arithmetic retaining exactly six UTC fractional digits
before permitting the next claim to increment the attempt (so attempt-31
post-call absent reaches an attempt-32 pre-call claim);
explicit absent at attempt `32` alone clears the lease, mode, and status and applies one
fenced +1 revision to terminal `externally_rejected/attempt_exhausted`.
Lookup unavailable at every attempt 1..32 clears the lease, preserves
`post_call_uncertain` with null mode, captures the existing trusted
`transitionNow` exactly once, validates/captures `anchorRetryDelaySeconds`
exactly once, and computes `nextAvailableAt = transitionNow +
anchorRetryDelaySeconds seconds` with checked overflow-safe arithmetic retaining
exactly six UTC fractional digits before scheduling another reconciliation
without a new external call. Thus
attempt-32 pre-call absent still permits the 32nd external call, while only
attempt-32 post-call-uncertain absent terminalizes.

The due boundary is inclusive: `trustedNow=nextAvailableAt` is claimable, while
a future `nextAvailableAt` is not. A positive-attempt retry with null
`nextAvailableAt`, an attempt-0 retry, and an attempt-0 positive-only operation
are invalid. The durable `external_call_start` marker is written before the call and clears
`preCallClaimMode` and `preCallLookupStatus`. A response
loss, process crash, or expired lease after that marker leaves the attempt in
`post_call_uncertain`; a matching expired-token reclaim clears the lease and
persists non-null `nextAvailableAt` by capturing the existing trusted
`transitionNow` exactly once, validating/capturing `anchorRetryDelaySeconds`
exactly once, and computing `nextAvailableAt = transitionNow +
anchorRetryDelaySeconds seconds` with checked overflow-safe arithmetic retaining
exactly six UTC fractional digits before any future
`external_reconciliation_claim` can become due. A new reconciliation claim is
then required before any lookup. A found result recovers acceptance, an
explicit absent result follows the attempt boundary above, and unavailable
remains reconciliation-only. An authenticated external `unavailable` result at
attempt 32 is therefore not terminal by itself; it becomes
`attempt_exhausted` only after reconciliation proves explicit absence. The
external marker, settlement, reconciliation, reclaim, and each internal receipt
claim/persist/acknowledgement step use the matching token, expected version and
state hash, and one +1 revision. An internal crash or expiry never regresses
`externally_accepted`, `receipt_inputs_persisted`, or `receipt_persisted` and
never loses persisted receipt inputs: reclaim clears only the matching expired
lease, and the next internal claim installs a new token. No branch can issue
N+1, increment attempt twice, or mutate with a stale token/version/hash.

P23-003F owns fixtures for all six scheduling branches: pre-call
`external_lookup` unavailable; expired pre-call pending/absent
`lease_reclaim`; post-call `external_result` unavailable at attempts 1, 31, and
32; post-call `external_reconciliation` unavailable; post-call explicit absent
at attempts `<32`; and expired post-call `lease_reclaim`. Each scheduling
fixture proves that the Framework captures the existing trusted `transitionNow`
exactly once, validates/captures `anchorRetryDelaySeconds` exactly once, and
computes exactly `nextAvailableAt = transitionNow + anchorRetryDelaySeconds
seconds` with checked overflow-safe arithmetic and exactly six UTC fractional
digits. The fixture matrix covers `anchorRetryDelaySeconds=1` (minimum), `300`
(default), and `86400` (maximum), plus invalid `0`, `86401`, and non-integer or
otherwise wrong-type configuration. It includes
`transitionNow=2030-01-02T03:04:05.123456Z` with default `300`, which must
produce `nextAvailableAt=2030-01-02T03:09:05.123456Z`, and due equality,
one microsecond before, and one microsecond after. Checked overflow rejection
must leave revision, state, and hash unchanged. The adapter result with an
extra `nextAvailableAt` field fails closed. Node and PHP must produce identical
`nextAvailableAt` and `stateHash` bytes. Genesis attempt 0 with null
`nextAvailableAt` and immediate `external_submission_claim`; attempts 1, 31,
and 32; positive-attempt null scheduling, attempt-0 retry, and attempt-0
positive-only negatives; claim -> pending lookup -> absent -> call-start
ordering; pre-call lookup unavailable/expiry/reclaim to
`retry_same_attempt` with a same-attempt new claim; attempt-31
`post_call_uncertain` explicit absent to `prepared/pre_call/new_attempt`, then
the attempt-32 claim and pre-call pending/found/absent/unavailable branches;
attempt-32 pre-call absent followed by exactly one `external_call_start` and
the post-call result/reconciliation found/unavailable/absent branches, where
only post-call absent terminalizes; stale
mode-substitution, pending/absent substitution, duplicate found/absent/
unavailable outcomes and call-start, and stale token/version/hash negatives;
expiry/reclaim and process restart in every internal receipt state; found ->
`externally_accepted` -> receipt-input claim/persist -> receipt persist ->
acknowledgement; exact +1 CAS,
updatedAt/stateHash continuity, token ownership, same-body idempotency, and
no-N+1 side effects. A receipt signing key must be active and unrevoked at
signing; rotation never rewrites persisted receipt bytes or hashes, and
acknowledgement follows receipt durability. Same-body lookup of
`externally_rejected` is idempotent and does not reopen it.

The public `AuditAnchorReceipt v1` is exactly the active Specification 105
§9.6.1 wire:
`{schemaVersion:1,kind:"audit_anchor_receipt",anchorReceiptId,partitionId,
segmentId,checkpointId,checkpointHash,checkpointBytesHash,anchorReceiptHash,
anchorStoreRef,anchorObjectRef,anchorObjectVersion,
previousAnchorReceiptHash,anchoredAt,signedAt,signatureAlgorithm:"ed25519-v1",
signingKeyRef,signature}`. It is not a second receipt wire. The authenticated
checkpoint supplies `segmentId`, `checkpointHash`, `checkpointBytesHash`, and
the checkpoint chronology; the authenticated anchor chain supplies
`previousAnchorReceiptHash`. The persisted intent must bind those values
byte-for-byte. The mappings are exact:
`anchorObjectVersion=acceptedObjectVersion`,
`anchoredAt=acceptedAnchoredAt`, and
`signingKeyRef=receiptSigningKeyRef`.

The checkpoint digest is the active §9.6.1 formula
`checkpointHash=H(ENC("blackops.audit.checkpoint.hash.v1")||
ENC(JCS(unsignedCheckpointBody)))` and
`checkpointBytesHash=H(ENC("blackops.audit.anchor.checkpoint-bytes.v1")||
ENC(JCS(completeCheckpointIncludingSignature)))`; the complete checkpoint
JCS has no export-file LF. The exact §9.6.1 unsigned anchor receipt body
contains every public receipt field except only `anchorReceiptHash` and
`signature`, so it includes `checkpointHash`, `checkpointBytesHash`,
`segmentId`, and `previousAnchorReceiptHash`. Its public formulas are exactly
`anchorReceiptHash=H(ENC("blackops.audit.anchor.hash.v1")||
ENC(JCS(unsignedAnchorReceiptBody)))` and
`signature=Ed25519.sign(ENC("blackops.audit.anchor.signature.v1")||
ENC(anchorReceiptHashBytes),anchorSigningKey)`. No checkpoint hash or chain
field is removed from that body, and only the named hash field and signature
are excluded.

If the intent retains internal receipt hashes, both use the exact canonical
§9.6.1 `unsignedAnchorReceiptBody` above as JCS bytes without LF and do not
define a public receipt formula:
`receiptPreimageHash=H(ENC("blackops.audit.anchor.intent.receipt-preimage.v1")||
ENC(JCS(unsignedAnchorReceiptBody)))` and
`receiptBytesHash=H(ENC("blackops.audit.anchor.intent.receipt-bytes.v1")||
ENC(JCS(unsignedAnchorReceiptBody)))`. The internal domains are distinct,
all fields are defined by §9.6.1, and neither hash is a substitute for
`checkpointBytesHash` or `anchorReceiptHash`. The exact protected raw key
formula remains
`idempotencyKeyHash=HEX_LOWER(SHA-256(LP("blackops.anchor.idempotency.v1")||
LP(UTF8(intentId))||LP(UTF8(rawIdempotencyKey))))`; the raw key is never
stored. The state formula is
`stateHash=HEX_LOWER(SHA-256(LP("blackops.anchor.state.v1")||
LP(JCS(intent excluding stateHash))))`; `stateHash` is excluded from its own
preimage and `previousStateHash` remains included. P23-003F owns before-call,
attempt31 post-call-absent -> attempt32 pre-call claim/call-start, attempt32
pre-call absent -> one final call, and attempt32 post-call-absent terminal
reconciliation; it also owns attempt31/32 unavailable and expiry,
side-effect/response-loss, persistence-boundary, acknowledgement,
rejected-to-externally-rejected/unavailable, public-receipt equality,
predecessor/version/hash and segment/previous-receipt substitution,
idempotency, lease-reclaim, key-rotation/revocation, and no-N+1 fixtures.

### A6. Retention revision, policy/hold snapshots, and deletion fence

`AuditRetentionState v1` is the exact closed metadata-only object
`{schemaVersion:1,kind:"audit_retention_state",stateId,target,policy,evaluator,
owner,evaluatedAt,policySnapshotRef,policySnapshotHash,policySnapshotVersion,
holdSnapshotRef,holdSnapshotHash,holdSnapshotVersion,evaluationFenceVersion,
result,reasonCode,state,version,previousStateHash,stateHash,updatedAt,
idempotencyKeyHash}`. `target` is exactly
`{partitionId,startSequence,endSequence,eventCategory|null}`; `eventCategory`
is either explicit `null` (the defined wildcard) or one NFC/no-control string
of 1..256 UTF-8 bytes. `policy` is exactly `null|{id,version,retentionUntil}`.
It is null if and only if `state=retained`, `result=retained`, and
`reasonCode=policy_unavailable`; every other result has the object. The object
has `id` as an NFC/no-control string of 1..256 bytes, `version` as a positive
JSON-safe integer, and `retentionUntil` as a trusted UTC timestamp with six
fractional digits. `evaluator` and `owner` are closed
`{type,id}` identities whose members use the same 1..256-byte bound.
`evaluatedAt` is one trusted UTC timestamp. Snapshot references are immutable
NFC/no-control strings of 1..256 bytes with lowercase 64-hex hashes.
`policySnapshotVersion`, `holdSnapshotVersion`, and `evaluationFenceVersion`
are positive JSON-safe integers; an authenticated no-hold snapshot still has
a positive `holdSnapshotVersion`. The state `policySnapshotRef`,
`policySnapshotVersion`, and `policySnapshotHash` equal the authenticated
policy snapshot's `ref`, `version`, and recomputed hash exactly; the
corresponding hold fields equal the authenticated hold snapshot's `ref`,
`version`, and recomputed hash exactly; and `evaluationFenceVersion` equals
the fence in both snapshots. For an available policy snapshot, `state.policy`
is byte-for-byte equal to `snapshot.policy`; a `policy_unavailable` snapshot
has `snapshot.policy=null` and therefore the state policy is null. The
snapshot hashes cover their exact reference, version, and fence metadata.
`result` and
`state` are both exactly `retained|eligible_for_future_deletion` and must be
equal. The reason matrix is closed: `retained` permits only
`retention_not_elapsed|active_hold|policy_unavailable`;
`eligible_for_future_deletion` permits only
`retention_elapsed_no_active_hold`. No wildcard or omitted policy/hold meaning
exists; `policy:null` is only the explicit policy-unavailable tag, while target
`eventCategory:null` is the defined wildcard.

Revision/version 1 has positive `policySnapshotVersion` and
`holdSnapshotVersion`, `evaluationFenceVersion=1`, and null
`previousStateHash`; its state/result/reason is selected by the same four-branch
truth table below, not unconditionally `retained`. Every accepted
re-evaluation appends exactly version+1 with the exact previous hash. The
deterministic truth table is closed: authenticated policy-unavailable snapshot
→ `retained/policy_unavailable`; available policy plus an overlapping active
hold → `retained/active_hold`; no active hold with
`evaluatedAt < retentionUntil` → `retained/retention_not_elapsed`; and no
active hold with `evaluatedAt >= retentionUntil` →
`eligible_for_future_deletion/retention_elapsed_no_active_hold`. Equality at
`evaluatedAt=retentionUntil` is therefore eligible. A trusted `evaluatedAt`
must be greater than or equal to the previous one; clock rollback is rejected.
The evaluator captures one trusted `evaluatedAt` exactly once. Every referenced
policy or hold snapshot has `capturedAt<=evaluatedAt`; a future `capturedAt` or
a rollback of the trusted evaluation time fails closed and creates no revision.
The evaluator authenticates each referenced policy and hold snapshot and their
explicit versions under one trusted evaluation fence. For revision N+1,
`policySnapshotVersion` and `holdSnapshotVersion` may stay equal or increase
but never decrease, while `evaluationFenceVersion` must equal revision N's
value plus exactly 1. With equal snapshots, later trusted time can only keep
`retained` or move it to eligible at the boundary; it cannot move eligible
back to retained. `eligible_for_future_deletion -> retained` is permitted only
for a strictly newer policy version that extends `retentionUntil`, a strictly
newer hold version that introduces an active overlapping hold, or a newly
authenticated policy-unavailable tagged snapshot whose
`policySnapshotVersion` is strictly greater than the prior policy snapshot
version. An available-to-unavailable change at the same policy snapshot
version is a fork and is rejected. Equal snapshots, equal time, and time alone
never reverse eligibility. An equal current snapshot pair
with the same complete body and same idempotency key returns the existing
revision without a new version. The same idempotency key with a different
complete body conflicts. A stale/decreased snapshot version, evaluation-fence
gap, fork, rollback to an earlier snapshot/result, or any unauthenticated
snapshot fails closed and creates no revision. Each revision is immutable and
binds its evaluator, owner, trusted evaluation time, policy snapshot, hold
snapshot, explicit versions, evaluation fence, result, and reason. Old
eligibility alone never authorizes deletion.
The exact active §9.7 hashes are
`stateHash=H(ENC("blackops.audit.retention-state.v1")||
ENC(JCS(object excluding stateHash)))` and
`idempotencyKeyHash=H(ENC("blackops.audit.retention-idempotency.v1")||
ENC(partitionId)||ENC(rawKey))`, represented as lowercase 64-hex values.
Each hash excludes itself from its preimage and the raw key is never stored.
Every string member of `stateId`, target, policy, identity, and snapshot
references is 1..256 UTF-8 bytes; object sizes are not substituted for this
member bound. Sequence, snapshot-version, fence-version, and revision-version
values are positive JSON-safe integers with start<=end where applicable;
`evaluationFenceVersion` advances exactly +1. Timestamps are UTC with six
fractional digits, and the complete revision JCS+LF cap is 64 KiB inclusive.

The authenticated snapshot wires are closed. A policy snapshot is either
`{kind:"policy",ref,version,target,policy,capturedAt,evaluationFenceVersion}`
or `{kind:"policy_unavailable",ref,version,target,policy:null,capturedAt,
evaluationFenceVersion}`; the latter is an authenticated tagged absence and
does not claim that the state `policy` descriptor was loaded. A hold snapshot
is `{kind:"active_hold_set",ref,version,target,holds,capturedAt,
evaluationFenceVersion}`, where `holds` is the complete canonical active
`AuditHold v1` array sorted by `holdId`, or exactly `[]` when no hold is
active. `ref` is immutable NFC/no-control text of 1..256 UTF-8 bytes, versions
and the fence are positive JSON-safe integers, target is the exact retention
target, and `capturedAt` is trusted UTC with six fractional digits and must be
no later than the one trusted `evaluatedAt`. Snapshot member order is the order
shown. The exact hashes are
`policySnapshotHash=H(ENC("blackops.audit.retention.policy-snapshot.v1")||
ENC(JCS(policySnapshot)))` and
`holdSnapshotHash=H(ENC("blackops.audit.retention.hold-snapshot.v1")||
ENC(JCS(holdSnapshot)))`; each full object, target, ref, version, fence,
captured time, tag, policy body, or complete hold set is bound, and the hash
field itself is excluded only from the enclosing stateHash preimage. Snapshot
JCS+LF is capped at 64 KiB inclusive. P23-003G/H own equal/newer/stale/fork,
policy:null and available-policy equality, same-version available-to-unavailable
fork, strictly-newer-unavailable, no-hold `[]`, full-hold-set,
target/ref/version/fence/hash substitution, version-gap, clock-rollback, and
retention truth-table, capturedAt-after-evaluatedAt, and evaluatedAt-rollback
fixtures.

`AuditHold v1` is the separate closed revision object
`{schemaVersion:1,kind:"audit_hold",holdId,target,reasonCode,state,version,
previousStateHash,stateHash,updatedAt,updatedBy,idempotencyKeyHash}`. Its
`target` is immutable `{partitionId,startSequence,endSequence,eventCategory|null}`
and its state is exactly `active|released`. Version 1 must be active with
`previousStateHash=null`; every later revision must use the exact prior version
plus one and the byte-exact prior `stateHash`. Only `active -> released` is
valid, and `released` is terminal. Released genesis, released re-activation,
version gaps, stale predecessor hashes, and substitution of the hold hash
domain are rejected without mutation. The exact state domain is
`blackops.audit.hold-state.v1`, and the idempotency domain is
`blackops.audit.hold-idempotency.v1`; specifically,
`stateHash=H(ENC("blackops.audit.hold-state.v1")||ENC(JCS(object excluding stateHash)))`
and
`idempotencyKeyHash=H(ENC("blackops.audit.hold-idempotency.v1")||ENC(holdId)||ENC(rawKey))`.
Retention snapshot domains cannot authenticate a hold. P23-003G owns active-genesis, +1, release-terminal,
released-genesis, reactivation, version-gap, predecessor-hash, and hash-domain
substitution fixtures.

Future P23-003H physical deletion must, under one atomic current policy+hold
fence, read current authenticated snapshots, persist the corresponding current
eligibility revision, and delete only while that same fence remains valid. A
new hold or policy revision aborts deletion. `AuditHold v1` remains a separate
append-only object and is never embedded in retention state. P23-003G owns
policy/hold snapshot authentication and revision fixtures; P23-003H owns
fresh-check/delete-fence fixtures for new hold after eligibility, policy
change/version, hold release, stale/equal/newer snapshot ordering,
no-hold-version, fence +1/gap, rollback/fork, owner/snapshot/hash/idempotency
substitution, +1, overlap/wildcard, and abort races.

### A7. Current technical authority and ownership

This is the sole current technical contract for the Audit Query, Outbox,
AnchorIntent, and Retention wires in this proposal. It is a `Proposal —
unreleased BlackOps 1.3`; workflow/gate status authority is the exact5
management slice: `develop/STATE.md`, `develop/TODO.md`, the parent Task, the
parent Report, and `develop/spec/README.md`. Workflow status, review labels,
Remaining, and Next Action are not normative members of this technical block. D145,
Specification 105, and Specification 106 contain this technical block
byte-for-byte. P23-003B/C owns ingress, protected body, Outbox, and clock/fence
fixtures, including null-token pending claim/new-token installation, unexpired
settlement, expired-token recovery, exact +1 CAS, and expiry equality;
P23-003F owns AnchorIntent, reconciliation, receipt, and no-N+1
fixtures; P23-003G owns Query, cursor, Retention, Hold, snapshot, and
Node/PHP parity fixtures; and P23-003H owns the future deletion fence. Stable
technical references use these exact filenames and section contracts, not a
review-round label. The exact3-only guard is
`! rg -n '^\+Warning: truncated output|^Total output lines:' <exact3>`, and the
non-Historical stale-status guard is
`! rg -n '^Round 26 current review checkpoint' develop/spec/106-phase-23-delivery-plan.md`.

## Historical Round 26 current normative corrections

Historical Round 26 was the prior parent authority. Round 25 and all earlier
checkpoints, including their Local Gate and review wording, are historical
evidence preserved here. Stable `1.2` remains current; this specification
remains an unreleased `1.3` proposal. Its prior status was `Round 26
Correction Implemented / Review Requested`; the current authority is the
Round 28 correction above, not Green, Accepted, Decided, or Released.

### AuditQuery first-page snapshot and cursor continuation

`AuditQueryRequest v1` is the closed object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}`. The first page requires
`snapshotHeadSequence=null` and `cursor=null`; the service captures one trusted
partition head and returns it as a positive JSON-safe result head. A
continuation requires a strict `aqc:v1` cursor and
`snapshotHeadSequence` exactly equal to its payload head. The authorized
partition and every filter must exactly equal the cursor payload. Selection is
limited to records with sequence `<=` the captured head, and continuation starts
strictly after the prior page's `lastSequence`; `nextCursor` is present only
when another matching record remains. These rules prevent repeat, skip, and
post-capture records.

`AuditQueryResult v1` is exactly
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,nextCursor,reasonCode}`. `ok` has an authorized partition,
positive head, complete ascending `1..limit` records, and a cursor only when
more rows remain. `empty` has the same partition/head, `records=[]`, and null
cursor/reason. `unavailable` has null partition/head/records/cursor and
`reasonCode=resource_unavailable`. Request and result JCS+LF caps are 64 KiB
and 1 MiB, respectively, with cap+1 rejected before allocation.

The cursor wire is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, with closed
payload `{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`, one literal dot,
ASCII/no padding, and a 2048-byte cap. Its tag is HMAC-SHA-256 over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))` using an exactly
32-byte `audit-query-cursor` role secret. P23-003G captures `trustedNow` once
from the injected Framework clock, never cursor input, and requires
`issuedAt <= trustedNow < expiresAt` and
`notBefore <= issuedAt <= trustedNow < min(expiresAt,notAfter)` for an active,
unrevoked key. Strict canonical decode/JCS/closed/cap/partition/filter/head/
last/time/key checks precede constant-time tag comparison and lookup/decrypt.
First/middle/final, head/last, expiry/rotation, substitution, and cap fixtures
are required.

### Local Audit Outbox state matrix and protection boundary

`AuditOutboxRecord v1` is exactly
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}`. Its only wire
states are `pending|leased|relayed|poisoned`; these mean ready/retry,
claimed, sent, and dead-lettered. Retry is `leased -> pending`, not a fifth
state.

| state | attempt/availableAt | leaseFencingToken/leaseExpiresAt | transition |
| --- | --- | --- | --- |
| `pending` | `0..max` / non-null | both null | claim to `leased` at FIFO head |
| `leased` | positive / null | both non-null | fenced `relayed`, `poisoned`, or retry `pending` |
| `relayed` | positive / null | both null | terminal sent |
| `poisoned` | positive / null | both null | terminal dead-letter |

Claim increments `attempt` once and binds one fencing token. Retry or lease
expiry clears both lease fields and sets a trusted future `availableAt`; a
non-retryable or exhausted claim becomes `poisoned`. Only the matching fenced
claim may mark sent/dead-letter, stale CAS fails, and FIFO cannot be bypassed.
Record identity, tenant binding, hashes, key ID, protected body, and ciphertext
are immutable. The body is BOPD v1 over JCS(`AuditIngressEnvelope v1`) without
LF and uses the exact tenant/partition/ordinal/hash LP AAD; retry reuses the
same ciphertext. P23-003B owns row/AAD/protection; P23-003C owns transaction,
idempotency, claim/retry/relay/dead-letter, and fencing. Node/PHP nullability,
transition, FIFO, stale-CAS, tenant/header/AEAD, and cap fixtures are required.

### AnchorIntent prepared/submitted/accepted/rejected matrix

`AnchorIntent v1` keeps the closed object
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,
checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,anchorObjectRef,
expectedPredecessorVersion,expectedPredecessorHash,anchorReceiptId,
idempotencyKey,conditionalCreateToken,acceptedObjectVersion,acceptedObjectHash,
acceptedAnchoredAt,receiptSignedAt,receiptSigningKeyRef,receiptBytesHash,
anchorReceiptHash,leaseFencingToken,leaseExpiresAt,version,updatedAt}`. Its
states are exactly `prepared|submitted|accepted|rejected`. The accepted triplet
`{acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt}` is non-null only
for `accepted`; its hash equals `checkpointBytesHash`, genesis uses version `1`
and null predecessor fields, and non-genesis uses both predecessor fields with
version exactly predecessor plus one.

| state | accepted triplet | receipt fields | transition |
| --- | --- | --- | --- |
| `prepared` | all null | all null | `submitted` |
| `submitted` | all null | all null | `accepted` or `rejected` |
| `accepted` | all non-null and bound | all non-null and consistent | terminal |
| `rejected` | all null | all null | terminal |

The conditional-create token, intent identity, request body, and idempotency
key are immutable. CAS requires expected version/hash and one lease token;
same-key/same-body retries are idempotent and conflicting bodies or stale
versions fail. A crash after submission reuses the token and resolves the same
external result, never creating N+1. P23-003F owns codec, CAS/idempotency,
recovery, receipt binding, verification, and the prepared/submitted/accepted/
rejected plus genesis/gap/stale/retry fixtures.

### Retention and legal-hold current wire

`AuditRetentionState v1` is metadata-only with a closed wire that fixes
`schemaVersion=1` and `kind="audit_retention_state"`:
`{schemaVersion,kind,stateId,target,policy,state,version,previousStateHash,
stateHash,updatedAt,idempotencyKeyHash}`. Its only states are
`retained|eligible_for_future_deletion`; no hold or deletion is embedded.

`AuditHold v1` is a separate append-only revision. Its active genesis is
mandatory: `state=active`, `version=1`, `previousStateHash=null`, and an
immutable owner/partition/category/sequence boundary. Each later revision has
the prior version exactly plus one and the prior state hash. Only
`active -> released` is valid; `released` is terminal, released genesis is
invalid, and re-activation is forbidden. P23-003G owns owner/boundary checks,
trusted time, CAS/idempotency, retention/hold matching in one snapshot, and
active-genesis/+1/released-terminal/released-genesis/re-activation/owner/
boundary fixtures. Physical deletion remains future P23-003H.

## Historical Round 25 normative corrections

Round 25 was the prior current checkpoint. Its projection, requestRef,
verifier, cursor, Outbox, and AnchorIntent text below is retained as historical
evidence and is not current authority.

### ProjectionBuildSet exact kind/origin map

The one closed `ProjectionBuildSet v1` wire has root
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
`schemaVersion=1` and `kind="projection_build_set"`. Its map has exactly one
descriptor at each key and cannot be swapped or duplicated: `command ->
blackops_command_projection`, `route -> blackops_route_projection`, and
`schedule -> blackops_schedule_projection`. Every descriptor is exactly
`{kind,inputHash,path,sha256}`; `inputHash` and `sha256` are lowercase
64-hex values and `sha256` is SHA-256 of the exact raw projection-file bytes.
`applicationBuildId` is 1..128 UTF-8 bytes after NFC/no-control validation and
each root-relative POSIX `path` is 1..256 UTF-8 bytes with no absolute form,
backslash, empty component, `.`, `..`, control, symlink, or special-file
component; descriptor paths are distinct. Each file is exactly
`{schemaVersion,kind,applicationBuildId,inputHash,records}` with
`schemaVersion=1`, file `kind` equal to its descriptor kind,
`applicationBuildId` equal to the root value, and `inputHash` equal to the
descriptor value. The exact typed encoding is
`ENC(string)="s:"||decimal_utf8_byte_length||":"||UTF8(string)`,
`ENC(integer)="i:"||canonical_decimal_digits`, `ENC(null)="n:"`, and
`ENC(bytes)="b:"||decimal_byte_length||":"||bytes`. The hashes are
`buildSetHash=SHA-256(ENC("blackops.projection-build-set.v1")||ENC(JCS(root excluding buildSetHash)))`
and
`inputHash=SHA-256(ENC("blackops.projection-input.v1")||ENC(JCS({kind,schemaVersion,applicationBuildId,sourceRecords})))`.
Root and projection bytes are UTF-8 RFC 8785 JCS followed by exactly one LF,
with no BOM, CRLF, or other whitespace; the root is at most 64 KiB inclusive,
each projection at most 16 MiB inclusive, and each record set is 0..4,096.

Command records are exactly `{name,aliases,description,origin,hidden}` and
`origin` is exactly `framework|application`. Route records are exactly
`{method,path,strategy,operationType,origin}` and `origin` is exactly
`operation_manifest|framework_fixed`. Schedule records are exactly
`{name,operationType,cron,timezone,strategy}` and have no `origin` member.
Command rows sort by canonical unsigned UTF-8 `name`, then `aliases`; route
rows sort by `(path,method,origin,operationType,strategy)` with nulls first;
schedule rows sort by `(name,operationType)` with nulls first. All fields
reject unknown, duplicate, missing, wrong-type, unsorted, non-NFC, control,
and cap+1 values. Readers acquire shared lock before opening and validating
the root and all three same-fd files; a non-selected missing, malformed,
digest, build, or input mismatch invalidates the complete set. The publisher
holds exclusive lock through validation, flush/fsync, atomic generation/root
publication, directory fsync, and generation-safe GC. P23-002A/B/C own positive
mapping plus swapped-kind, duplicate-kind, unknown-origin, and invalid-origin
negatives.

### Self-contained requestRef vectors and ownership

The current `AuditReadRequestBasis v1` is exactly the six-field JCS
object `{actor:null,execution:{id:"exec-1",type:"cli"},nonce:"nonce-1",purpose:"status",requestedAt:"2026-08-20T03:20:00.123456Z",tenant:null}`;
its canonical JCS is 150 bytes, the LP preimage is 192 bytes, and its exact
requestRef is `oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
The current `AuditAccessContext v1` hash input is exactly
`{actor:null,nonce:"n0",purpose:"query",requestedAt:"2026-08-19T00:00:00.000000Z",tenant:null}`;
its canonical JCS is 103 bytes, the LP preimage is 147 bytes, and its exact
digest is `1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`.
Both use their exact domain, LP(U32BE length plus UTF-8 bytes), SHA-256, and
`oid:v1:` lower-hex wrapper; no raw preimage is a requestRef. P23-003F owns
codec/verifier goldens and wrong-domain/algorithm/raw-preimage/self-reference/
cap fixtures; consumers D/G own integration. P23-003G owns Access
cross-purpose/time/nonce/actor/tenant/raw-target/requestRef-reuse fixtures.

### One total verifier function

After Stage 5 signature and file-digest validation, Stage 6 always validates
the authenticated catalog and authenticated record graph/chain/hash/semantic
graph first (`14 authenticated_graph_invalid`), then mode/context/coverage
(`16 mode_coverage_invalid`), then freshness head shape/boundary
(`18 freshness_boundary_unavailable`). This is one total finite precedence
table: simultaneous failures use that order, every global Stage 6 result keeps
top references non-null and `partitions:[]`, and zero listed-file reopen is
permitted. Stage 7 performs only local checkpoint/anchor trust/chronology
(`15 checkpoint_or_anchor_invalid`) and local end-head freshness (`18` when
appropriate), then emits partition entries. Stage 7 has no catalog code 10,
the deprecated scope label, or authenticated-graph code 14. P23-003F/G own simultaneous
failure and Stage 6/7 partition fixtures.

### Query, Outbox, AnchorIntent, and cursor current wires

`AuditQueryRequest v1` is the exact closed UTF-8 JCS object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}`. `access` is the exact nine-field
`AuditAccessContext v1`; `partitionId` is the authorized
`system:global|tenant:<64 lowercase hex>` value; and `filters` is exactly
`{eventCategory:null|string,recordIdPrefix:null|string,fromSequence:null|positive,toSequence:null|positive}`
with null bounds or `fromSequence<=toSequence`. `eventCategory` is null or an
AuditRecord category, and `recordIdPrefix` is null or a non-empty prefix.
Every non-null filter string and every cursor-payload string is NFC,
no-control, and 1..256 UTF-8 bytes; null remains allowed. The head is a
positive JSON-safe integer, `limit` is `1..1000`, and `cursor` is null only on
the first page or a strict `aqc:v1` cursor thereafter. Unknown fields,
partition/filter swaps, and request JCS over 64 KiB fail before lookup.

`AuditQueryResult v1` is exactly
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,nextCursor,reasonCode}`. `ok` has an authorized
non-null partition, positive head, a complete non-empty `1..limit` record
array, and `nextCursor` only when more rows remain; `empty` has the same
partition/head, `records:[]`, null cursor and reason; `unavailable` has null
partition/head/records/cursor and reason `resource_unavailable`. Records are
complete, authorized, integrity-checked, and ascending by partition-global
sequence. Result JCS plus one LF is at most 1 MiB inclusive and cap+1 fails
before allocation.

The cursor wire is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, one literal
dot, no padding, ASCII, and at most 2048 bytes inclusive. Its closed payload
is `{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`. The 32-byte tag
is HMAC-SHA-256 under role `audit-query-cursor` over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`; LP is U32BE length
plus exact UTF-8 bytes. Key IDs are 1..128-byte ASCII NFC/no-control and the
secret is exactly 32 bytes. P23-003G captures trustedNow once from the
injected Framework clock, never cursor input, with six UTC fractional digits;
`issuedAt <= trustedNow < expiresAt` and the active, unrevoked key window
requires `notBefore <= issuedAt <= trustedNow < min(expiresAt,notAfter)`.
Unknown/wrong-role/status/window/key/length fail before lookup/decrypt.
Strict base64url/JCS/closed-field/cap/partition/filter/head/last/time/key
checks precede constant-time tag comparison. Rotation verifies a prior key
only through outstanding expiry and issuance uses the current active key.
Before-expiry, expiry equality/after, key-window, rotation, substitution, and
cap negatives are required.

`AuditOutboxRecord v1` is exactly
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}`. `protectedBody`
is binary BOPD v1 whose plaintext is JCS(`AuditIngressEnvelope v1`) without
LF. With `LP(x)=U32BE(byte_length(x))||x`, its exact AAD is
`LP("blackops.audit.outbox.aad.v1")||LP("AuditOutboxRecord")||LP("1")||
LP("1")||LP("1")||LP(exactKeyId)||LP(recordId)||LP(partitionId)||
LP(TENANT(tenantBinding))||LP(ASCII(canonicalDecimal(ingressOrdinal)))||
LP(decoded32(idempotencyKeyHash))||LP(decoded32(contentHash))`.
`TENANT(null)=0x00`; `TENANT({type,id})=0x01||LP(UTF8(NFC(type)))||
LP(UTF8(NFC(id)))`, with exact type/id bounds, header, equality, and
partition binding. `idempotencyKeyHash` is
`SHA-256(ENC("blackops.audit.outbox.idempotency.v1")||ENC(partitionId)||ENC(NFC(idempotencyKey)))`.
Pending/leased/relayed/poisoned state and lease nullability are closed;
attempt starts at zero and increments once per fenced claim, identity/hash/
protected bytes are immutable, retry reuses ciphertext, and same-key
same-body is idempotent while same-key different-body conflicts. P23-003B/C
own Node/PHP null/present parity plus `0xff` and wrong marker/type/id/header/
tenant/AEAD substitutions and size-cap fixtures.

### AnchorIntent current state and receipt wire

`AnchorIntent v1` is the exact closed object
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,leaseExpiresAt,version,updatedAt}`.
`prepared` has accepted version/hash/time all null and all four receipt fields
null. Only `externally_accepted` and later require
`acceptedObjectHash=checkpointBytesHash`; only then does genesis require
`acceptedObjectVersion=1` with both predecessor fields null, while a
non-genesis predecessor requires both predecessor version/hash non-null and
accepted version predecessor+1. Positive prepared/non-genesis/genesis and
non-null/equality/version negatives are required.

`receiptBytesHash` is exactly
`SHA-256(ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||exact unsigned receipt JCS+LF bytes)`;
the final bytes have no additional `ENC`. `anchorReceiptHash` is the unsigned
receipt semantic hash, not a signed-file raw digest. `receipt_inputs_persisted`
has only its three receipt-input fields non-null and `anchorReceiptHash` null;
`receipt_persisted|acknowledged` has all four receipt fields non-null. Lease
fields are both null at rest or both non-null for one fenced claim, and CAS
prevents stale writes.

For `AuditOutboxRecord v1`, `TENANT(null)=0x00` and
`TENANT({type,id})=0x01||LP(UTF8(NFC(type)))||LP(UTF8(NFC(id)))`; the AAD
contains `LP(TENANT(tenantBinding))` inside the outer U32BE LP fields, with
exact type/id bounds, header, equality, and partition binding. P23-003B/C own
Node/PHP null/present parity and marker/type/id/header/tenant-substitution plus
AEAD-failure fixtures, including `0xff`.

The query cursor keyId is 1..128-byte ASCII NFC/no-control; the HMAC secret is
exactly 32 bytes under role `audit-query-cursor`. P23-003G captures trustedNow
once from the injected Framework clock (never from cursor input) in UTC with
six fractional digits and requires `issuedAt <= trustedNow < expiresAt`.
The trusted key is active, not revoked, and satisfies
`notBefore <= issuedAt <= trustedNow < min(expiresAt,notAfter)`; unknown,
wrong-role/status/window/keyId/secret length fail before lookup/decrypt.
Rotation verifies an old key only through outstanding cursor expiry and issues
with the current active key. Strict decode, canonical JCS, closed fields,
caps, partition/filter/head/last/time/key checks precede constant-time tag
comparison. Before-expiry, equality/after-expiry, key-window, rotation,
substitution, and cap negatives are required.

Status is `Round 25 Local Gate Green / Independent Review Requested`. The
Orchestrator independently completed the full Local Gate; fresh separate Sol
xHigh General/Audit review and parent acceptance remain, followed by P23-001R
and ordered Phase 23 work. The worker did not rerun the full gate.

The Orchestrator evidence is focused/full Website `8/8` and `120/120`, Website
check `0 errors/0 warnings/0 hints`, build `42` pages/site check `41` with only
existing nonfatal chunk-size and route-priority warnings, release source/
artifact guards, version baseline `published=1.2.0 historical=1.1.0`, focused
PHPUnit `30 tests / 250 assertions`, Mago format/lint/baseline PASS and analyze
exit `0` with existing `71 issues` (`46 warning / 25 help`), PHP management-ID,
current-only Node (exact-16, JSON `7/7`, Read `150/192/bfad...df0cf`, Access
`103/147/1c505...e241`, negative matrices, links `315/279/0`, seven roots),
Docker PHP goldens/negative matrices, and HEAD/main
`5ad87557c08e20c3bcc6d77be83804001ba3eb80` with staged `0` and diff check.

## Historical Round 24 current normative corrections

Round 24 was the then-current parent correction authority and is now historical.
Round 23 and all earlier parent checkpoints are historical; P23-001S remains
the accepted child fact, not current parent authority. Stable `1.2` remains
current and all capabilities in this specification remain an unreleased `1.3`
proposal.

### Application Bootstrap list boundary

The public Application Bootstrap contract is format-sensitive. Explicit
class-string Commands are instantiated while the Console Kernel is composed;
prebuilt Command instances are reused. Default and raw discovered rows remain
lazy and do not call their factory. Non-short JSON/XML/Markdown descriptor
paths may resolve a Lazy factory or Compiled Container, and individual Help
may do the same. Guide Source and the Website Source/HTML/raw/Search/LLM
surfaces must contain this timing boundary and reject the former absolute
Manifest-only sentence and unknown duplicate page/surface.

### ProjectionBuildSet v1 complete-set wire

There is one current projection wire. The closed root is exactly
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
`schemaVersion=1`, `kind="projection_build_set"`, and exactly the three
`projections` keys `command`, `route`, and `schedule`. `applicationBuildId` is
1..128 UTF-8 bytes after NFC/no-control validation and `buildSetHash` is
lowercase 64-hex. Each descriptor is exactly
`{kind,inputHash,path,sha256}` with no unknown or duplicate members. Descriptor
`kind` is exactly one of `blackops_command_projection`,
`blackops_route_projection`, or `blackops_schedule_projection`; `inputHash`
and `sha256` are lowercase 64-hex, and `sha256` is SHA-256 of the exact raw
projection-file bytes. `path` is a root-relative POSIX path of 1..256 UTF-8
bytes with no absolute form, backslash, empty component, `.`, `..`, control,
symlink, or special-file component, and each descriptor path is distinct.

The exact typed encoding used by projection hashes is
`ENC(string)="s:"||decimal_utf8_byte_length||":"||UTF8(string)`,
`ENC(integer)="i:"||canonical_decimal_digits`,
`ENC(null)="n:"`, and
`ENC(bytes)="b:"||decimal_byte_length||":"||bytes`. `buildSetHash` is
`SHA-256(ENC("blackops.projection-build-set.v1") || ENC(JCS(root excluding
buildSetHash)))`. Each projection file is exactly
`{schemaVersion,kind,applicationBuildId,inputHash,records}` with
`schemaVersion=1`, file `kind` equal to its descriptor kind,
`applicationBuildId` equal to the root value, and `inputHash` equal to the
descriptor value; `inputHash` is
`SHA-256(ENC("blackops.projection-input.v1") || ENC(JCS({kind,schemaVersion,applicationBuildId,sourceRecords})))`.
The `sourceRecords` are closed, normalized, and sorted by the exact kind sort
below. Every digest is rendered as lowercase hexadecimal.

Command records are exactly `{name,aliases,description,origin,hidden}` and
use the separator-free command grammar; command rows sort by canonical name
then aliases. Route records are exactly
`{method,path,strategy,operationType,origin}` and sort by
`(path,method,origin,operationType,strategy)` with nulls first. Schedule
records are exactly `{name,operationType,cron,timezone,strategy}` and sort by
`(name,operationType)` with nulls first. All comparisons use canonical
unsigned UTF-8 bytes without locale. `origin` is exactly
`operation_manifest|framework_fixed`; route method/path/strategy and schedule
fields use their bounded NFC/no-control grammars, including method 1..16 ASCII
bytes, route path 1..2048 bytes beginning with `/`, and the declared
operation/strategy/timezone caps. Every object rejects unknown, duplicate,
missing, wrong-type, unsorted, non-NFC, control, and cap+1 members.

Stored root and projection bytes are exactly UTF-8 RFC 8785 JCS followed by
one LF, with no BOM, CRLF, or other whitespace. The complete stored root is at
most 64 KiB inclusive; each projection is at most 16 MiB inclusive and has
0..4096 records. The publisher writes immutable generation files through
bounded temporary files, flush/fsync, atomic rename, and parent-directory
fsync; it publishes and fsyncs the root last, never overwrites files referenced
by an existing root, and runs generation-safe GC only for paths unreferenced by
the current root while holding the exclusive lock.

The fixed regular lock is `.blackops-projection-build-set.lock`. D146 refines
the earlier “nofollow” wording to the PHP-implementable verified-handle policy:
inside a caller-owned trusted absolute directory, pre-open `lstat`, one
`fopen`, pre-I/O `fstat`, and a second `lstat` must agree on regular-file type,
device, and inode. This is not a kernel `O_NOFOLLOW` claim. Every reader
validates that lock, obtains shared `flock`, opens and validates the root and
all three files through their verified handles, including every descriptor,
digest, build/input identity, and record set, then selects/renders only its
dedicated rows before releasing the lock. A non-selected mismatch invalidates
the complete set. P23-002A1 receives the absolute locator by injection and
does not add or infer a public config path; P23-002C owns that final integration
choice. A/B may prepare bounded inputs, but only the complete
command/route/schedule set is publishable and only C may activate it.

### Exact audit request contexts

The closed pre-decision `AuditReadRequestBasis v1` is exactly the six-field
object `{purpose,actor,execution,tenant,requestedAt,nonce}`. It has no
`requestRef` or authorization result. `requestBasis` below is that complete
object, and the final derived value is
exactly `"oid:v1:" || HEX_LOWER(SHA-256(LP("blackops.audit.read.request-ref.v1") ||
LP(JCS(requestBasis))))`. LP is only the SHA-256 preimage. Purpose is
`status|journal|outcome|diagnostics`; actor is null or a closed `{type,id}`;
execution is a closed `{type,id}`; tenant is null or `{type,id}`; time is UTC
with six fractional digits; nonce is a 1..128-byte NFC/no-control
noncredential. The golden is JCS `150`, preimage `192`, and
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
Node and Docker-PHP must reproduce it and reject wrong domain, wrong hash
algorithm, raw preimage, self-reference, null/present, substitution, and
cap+1 fixtures.

Only after a trusted decision may the adapter construct the exact ten-field
`AuditReadContext v1` `{schemaVersion:1,kind:"audit_read_context",purpose,
actor,execution,tenant,requestedAt,nonce,requestRef,authorizationDecision}`.
All basis fields and the derived requestRef must equal the validated basis;
decision is `allow|deny|resource_unavailable`. Caller input supplies neither
decision nor requestRef. P23-003D/G capture each runtime nonce once from an
injected CSPRNG as 16 random bytes encoded to 32 lowercase hex; the `nonce-1`
golden remains a pure A1 codec vector. RequestRef is correlation only and does
not grant authorization, replay protection, or idempotency.

The closed `AuditAccessContext v1` is exactly the nine-field object
`{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,
requestedAt,nonce,requestRef,authorizationDecision}`. Actor is null or
`{principal:null|{type,id},execution:{type,id}}`; tenant is null or `{type,id}`;
purpose is `query|export|sign|verify`; decision is
`allow|deny|resource_unavailable`; requestedAt is UTC with six fractional
digits; nonce is a 1..128-byte NFC/no-control noncredential. Its hash input is
exactly `{purpose,actor,tenant,requestedAt,nonce}` under domain
`blackops.audit.access.request-ref.v1`, using LP, SHA-256, lowercase hex, and
the `oid:v1:` prefix. Legacy requester field names do not exist.
The null/null golden is JCS `103`, preimage `147`, and SHA-256
`1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`.

### Stage 6 finite failure contract

After signature authentication, Stage 6 applies one precedence table:
catalog or authenticated record-chain graph/hash/semantic mismatch is code
`14`/`authenticated_graph_invalid`; mode/coverage mismatch is code
`16`/`mode_coverage_invalid`; freshness boundary mismatch is code
`18`/`freshness_boundary_unavailable`. Every global Stage 6 failure keeps
post-signature top references non-null, returns `partitions:[]`, and never
reopens listed files. Simultaneous catalog+mode+freshness fixtures assert that
precedence and the finite safe names.

### Current Query, Outbox, and AnchorIntent wires

`AuditQueryRequest v1` is the closed UTF-8 JCS object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}`. `access` is the exact
`AuditAccessContext v1`; `partitionId` is the non-null authorized
`system:global|tenant:<64 lowercase hex>` partition; and `filters` is exactly
`{eventCategory:null|string,recordIdPrefix:null|string,fromSequence:null|positive,
toSequence:null|positive}` with null bounds or `fromSequence<=toSequence`.
`eventCategory` is null or an AuditRecord event-category value; `recordIdPrefix`
is null or a non-empty prefix. Every non-null filter string and every string in
the cursor payload is NFC, has no control character, and is 1..256 UTF-8 bytes;
null remains allowed.
`snapshotHeadSequence` is positive JSON-safe, `limit` is `1..1000`, and
`cursor` is null only on the first page or a strict `aqc:v1` cursor thereafter.
Unknown fields, cross-tenant partition swaps, invalid filters, and request JCS
over 64 KiB fail before lookup.

`AuditQueryResult v1` is the closed object
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,nextCursor,reasonCode}`. Its exact state matrix is:

| status | partitionId | records | snapshotHeadSequence | nextCursor | reasonCode |
| --- | --- | --- | --- | --- | --- |
| `ok` | authorized non-null partition | non-empty array of complete canonical `AuditRecord v1` projections, length `1..limit` | positive | non-null only when more rows remain; otherwise null | null |
| `empty` | authorized non-null partition | `[]` | positive | null | null |
| `unavailable` | null | null | null | null | `resource_unavailable` |

Records are authorized, decrypted, integrity-checked, complete, and ordered by
ascending partition-global sequence before projection; no partial record or
target/existence detail is returned. Complete result JCS plus one LF is at most
1 MiB inclusive and cap+1 fails before allocation. The exact cursor is
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, with one
literal dot, no padding, ASCII, and total length at most 2048 bytes. Its closed
payload is
`{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`. The 32-byte tag
is HMAC-SHA-256 under the role-bound `audit-query-cursor` key selected by
`keyId` over `LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`, where
LP is U32BE length plus exact UTF-8 bytes. Validation precedence is strict
base64url and canonical JCS, closed fields and caps, authorized partition and
filter/head bounds, positive `lastSequence`, UTC `issuedAt<expiresAt`, key
role/status, and then constant-time tag comparison; lookup and record decrypt
occur only after all checks. HMAC cursor authentication is not record
decryption or integrity verification.

`AuditOutboxRecord v1` is the exact closed row
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}`. Hashes are
lowercase 64-hex, `ingressOrdinal` is positive JSON-safe, `attempt` starts at
zero and increments exactly once per fenced claim, and times have six UTC
fractional digits. `protectedBody` is non-empty binary BOPD v1 whose plaintext
is exact RFC 8785 JCS of `AuditIngressEnvelope v1` without LF. Its exact custom
AAD is:

```text
LP(x)=U32BE(byte_length(x))||x
AAD=LP("blackops.audit.outbox.aad.v1")||LP("AuditOutboxRecord")||LP("1")||
 LP("1")||LP("1")||LP(exactKeyId)||LP(recordId)||LP(partitionId)||
 LP(tenantPresenceAndTypeAndId)||LP(ASCII(canonicalDecimal(ingressOrdinal)))||
 LP(decoded32(idempotencyKeyHash))||LP(decoded32(contentHash))
```

The three `1` values are schema, envelope, and algorithm; the tenant union has
an explicit presence marker and LP type/id. `idempotencyKeyHash` is exactly
`H(ENC("blackops.audit.outbox.idempotency.v1")||ENC(partitionId)||
ENC(NFC(idempotencyKey)))`; the raw key is protected and never a clear field.
`pending` has attempt zero or a retry attempt, non-null `availableAt`, and null
lease fields; `leased` has null `availableAt` and non-null fencing token and
expiry; `relayed|poisoned` are terminal with all scheduling/lease fields null.
Only `pending->leased`, fenced transient/expired `leased->pending`, and
`leased->relayed|poisoned` are valid. Identity, bindings, hashes, and protected
bytes are immutable; retry reuses ciphertext, no ordinal head bypass is
allowed, same-key/same-body is idempotent, and same-key/different-body is a
conflict. The logical body is at most 1 MiB inclusive and BOPD allocation is
bounded by `52+keyIdUtf8Bytes`; cap+1 and AAD/tenant/header substitutions fail.

`AnchorIntent v1` is the exact closed object
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,checkpointId,checkpointHash,
checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,
expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,
acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,
receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,
leaseExpiresAt,version,updatedAt}`. IDs, refs, tokens, and idempotency values
are bounded NFC/no-control noncredential strings; hashes are lowercase 64-hex,
versions are positive, and times are UTC with six fractional digits. Only
`receiptSigningKeyRef` names a signing key; genesis has null predecessor fields
and accepted version `1`, otherwise predecessor version/hash are both present
and accepted version is predecessor plus one. `acceptedObjectHash` equals
`checkpointBytesHash`.

The state/nullability matrix is exact: `prepared` has
`acceptedObjectVersion`, `acceptedObjectHash`, and `acceptedAnchoredAt` all
null and all four receipt fields null; `externally_accepted` has the accepted
triplet non-null and all receipt fields null; `receipt_inputs_persisted` adds
`receiptSignedAt,receiptSigningKeyRef,receiptBytesHash` non-null while
`anchorReceiptHash` remains null; `receipt_persisted|acknowledged` has the
accepted triplet and all receipt fields non-null. `receiptBytesHash` is exactly
`SHA-256(ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||exact unsigned receipt JCS+LF bytes)`;
`anchorReceiptHash` is the unsigned receipt semantic hash, not a signed-file raw digest. Lease fields are both null
at rest or both non-null for one fenced claim; CAS prevents regression/stale
writes and retries preserve ID/key/body/hash/version without N+1. P23-003B
owns outbox row/AAD/identity, P23-003C owns transaction/idempotency/lease/
relay, P23-003F owns AnchorIntent/verification, and P23-003G owns query/
cursor orchestration; this B/C/F/G DAG is acyclic.

## 1. Stable boundary and terminology

Stable `1.2` keeps its current commands, manifests, FrankenPHP Worker Mode as
the default HTTP runtime, lifecycle Journal, Structured Log, OpenTelemetry,
retention audit, replay audit, and transactional outbox behavior. In
particular, this specification does not
retrofit a general Audit Trail into the existing `retention_purge_audits`,
observer replay audit, rotation audit, JSONL `kind=audit`, or Lifecycle Journal
tables.

The following replacement surfaces are 1.3 proposal names/schemas only (the
Stable `list` name itself remains current):

the BlackOps-owned replacement projection for `list`, `ls`, `route:list`, `route:ls`, `schedule:list`, `schedule:ls`,
`diagnostics:check`, aggregate-only `queue:status`, `about`, `outbox:status`,
and any future Audit Trail command.

`operation:list`, `operation:inspect`, `worker:run`, `scheduler:*`,
`retention:*`, `outbox:*`, and `journal:observer:replay` remain existing
Stable commands with their current contracts until a separately accepted
compatibility change says otherwise.

Stable `1.2` already exposes Symfony-backed `list` behavior. P23-002A is a
proposal for a BlackOps-owned replacement projection: `list` itself is the
Stable current name, `ls` is the only new name, and the existing Stable input
remains migration-compatible until the replacement is independently accepted.
The Stable input set is an optional namespace, `--raw`, `--short`, and
`--format=txt|json|xml|md`; every currently accepted combination remains
accepted, and repeated `--format` uses the last occurrence. `--json` and
`--all` are 1.3-only additions and are not part of the Stable input set;
`--json` alone is exactly `--format=json`, while `--json` together with an
explicit `--format` is the only new flag conflict that returns exit `2`.
`--raw` affects txt only and is ignored for non-txt formats. `--short` is an
exact no-op for every format because the replacement has no long help or
definition fields. These inputs are rendered by the BlackOps safe projection,
not by Symfony descriptor fields. A namespace may contain multiple
colon-delimited segments. Nested exact, unique abbreviated, ambiguous, and
unknown namespace behavior and exit status are fixed by Symfony Stable
`1.2.0` behavioral golden fixtures; the replacement must not turn an accepted
Stable case into exit `2`.

“Compiled projection” means a versioned, application-build-bound artifact
whose reader can return safe metadata without source reflection, Provider
execution, container construction, database access, clock evaluation, or
handler resolution. “Effective route” means a route the BlackOps router can
dispatch from its compiled route artifact plus an explicitly described
framework fixed route; it does not include arbitrary web-server middleware.

## 2. Shared projection rules

All complete-projection 1.3 metadata commands, including the explicit
`list`/`ls` recovery reader:

- obtain the installation-owned root and all three projection files under one
  shared lock, validate the complete set, then select and render rows from the
  reader's dedicated compiled projection;
- fail closed on missing, malformed, stale, unsupported, or build-mismatched
  artifacts;
- sort rows deterministically by the documented key;
- projection readers never resolve an application class, command factory,
  Symfony container, database, handler, middleware, Provider, actor, tenant,
  or clock. Console composition follows the public Application Bootstrap
  boundary: explicit class-string Commands may be instantiated, prebuilt
  instances are reused, default/raw discovered rows stay lazy, and non-short
  structured descriptor paths may resolve a Lazy factory or Compiled
  Container;
- never print secrets, payloads, credentials, exception messages, SQL, DSNs,
  stack traces, or arbitrary application configuration; and
- use exit `0` for success, `1` for operational read failure, and `2` for
  invalid input or invalid/stale artifact.

The `list`/`ls` recovery reader is an explicit exception to the common
missing-artifact rule. Its embedded framework projection is always readable
and emits `build:compile`, one canonical `list` row whose aliases include
`ls`, separate canonical `help` and `completion` rows, and every other safe
framework row without an application artifact. Application rows are
merged only from a same-build, current, valid application projection. Missing,
malformed, unsupported, stale, or build-mismatched application metadata therefore returns
the framework rows with exit `0`; it does not resolve application code,
container, or database. Structured formats add the deterministic top-level
`applicationProjectionStatus` value
`available|absent|malformed|unsupported|stale|build_mismatch`.
Human txt and raw output keep the same rows and write only a secret-safe,
deterministic status diagnostic to stderr. `route:list`/`route:ls` require a
complete route projection: valid is exit `0`, an operational read failure is
exit `1`, and missing, malformed, stale, unsupported, or build-mismatched
complete artifacts are exit `2`. `schedule:list` and its exact
`schedule:ls` alias have the same complete-projection rule and the same three
exit classes. This is distinct from list/ls recovery, whose six application
artifact states return framework rows at exit `0`.

Every projection reader receives the installation-owned
`ProjectionBuildSet v1` root, obtains the shared lock before opening the root
and all three same-fd projection files, validates all root/descriptor/file
schemas and identities, and only then selects and renders its dedicated rows. A
non-selected missing, malformed, digest, build, or input mismatch invalidates
the complete build set. The configured installation boundary is a trust
boundary, not a cryptographic-authenticity claim; the reader does not load
source, Provider, container, database, or application code to derive identity.
One decision table applies to root and files: raw cap before parse;
ENOENT is `absent`; other permission/I/O/unreadable input is operational exit
`1`; invalid bytes/JSON/JCS/closed shape/self-hash/digest is `malformed`;
valid unsupported schema/kind is `unsupported`; configured or descriptor
build identity mismatch is `build_mismatch`; projection input identity
mismatch is `stale`; otherwise `available`. Command `list`/`ls` structured
output always carries the top-level status, and its txt/raw output writes
exactly one `applicationProjectionStatus=<status>\n` diagnostic for all six
values. Route and schedule commands use their dedicated success wires and do
not add `applicationProjectionStatus` to successful Human or JSON output.
Fixtures cover each state and combined-precedence pair.

For Production, the only external expectation is the configured application
build ID.
Descriptor `inputHash`, projection `inputHash`, recomputed record hash, file
digest, and root self-hash are checked against one another under the lock.
External exact-three hashes remain available only to component tests and are
not a Production configuration or runtime input.

The command alias is part of the contract, not a presentation detail. The
canonical and alias spellings have identical rows, filters, output formats,
exit codes, and failure behavior.

The application projection input is finite before JSON allocation: its raw
UTF-8 JSON bytes are at most 16 MiB inclusive; it contains 0..4,096 command
records, each with at most 32 aliases; canonical names and aliases are
non-empty NFC-safe command names with no tab, comma, CR, LF, control character,
or empty colon-delimited segment and at most 256 UTF-8 bytes; descriptions are
NFC-safe one-line strings with no control, CR, or LF and at most 256 UTF-8
bytes. Duplicate canonical names, duplicate aliases, canonical/alias
collisions, and a name unsafe for the raw separator grammar fail the build
with an owner/name migration diagnostic. Cap and cap+1 fixtures cover raw
bytes, records, aliases, and every bounded field. A valid recovery artifact
contains one canonical `list` row with alias `ls`; invoking `ls` selects that
same row and never emits a second canonical `ls` row.

## 3. `list` / `ls`

### 3.1 Reserved names and source

One P23-002A/global pre-release reservation registry owns and checks every
canonical name and alias in this exact set:
`list`, `ls`, `help`, `completion`, `_complete`, `route:list`, `route:ls`,
`schedule:list`, `schedule:ls`, `diagnostics:check`, `queue:status`, `about`,
and `outbox:status`. The registry inspects both canonical application command
names and aliases. A collision fails closed with an actionable migration that
names the owner and colliding name and requires a specific application rename;
there is no silent override or last-writer-wins behavior. P23-002B through
P23-002E reuse this same registry and assign every canonical/alias Stable
collision fixture to its owner. `worker:list` and `worker:ls` are neither
BlackOps-owned nor reserved. An Application may own either spelling as a
canonical command or alias; an unregistered spelling follows normal Symfony
unknown-command behavior. There is no built-in Framework Job registry or
worker listing. P23-002A owns Application-canonical/alias positives,
unregistered negative, and no-Job-row fixtures.

BlackOps owns the replacement `list` and exact alias `ls`; Applications cannot
register or override them. Stable collision-free and upgrade-collision
fixtures are required before compatibility acceptance.

The command reads a dedicated compiled command projection. It does not expose
Symfony `ListCommand`, `JsonDescriptor`, XML, Markdown, Help, InputDefinition,
class names, default values, or command factory behavior as a public contract.
Application Console composition keeps the existing Bootstrap behavior:
configured explicit class-string Commands may be instantiated and prebuilt
instances are reused before command execution.
The replacement `list` does not promise to remove that existing composition
work.
It promises that execution after composition does not perform additional
command construction, factory/container/Provider/DB resolution, source
discovery, or handler loading.
Missing/malformed and valid projection fixtures measure this execution delta,
and `build:compile` remains discoverable in the embedded framework projection.

### 3.2 Input

```text
blackops list [<namespace>] [--raw] [--short] [--format=txt|json|xml|md] [--json] [--all]
blackops ls   [<namespace>] [--raw] [--short] [--format=txt|json|xml|md] [--json] [--all]
```

The namespace is an optional colon-delimited canonical-name prefix and may
contain multiple segments, such as `outbox:relay`; it is not limited to one
segment. Nested exact, unique abbreviated, ambiguous, and
unknown namespace diagnostics and exits are exactly the Symfony Stable
`1.2.0` behavioral golden. Namespace matching introduces no blanket new exit
`2`; only a new invalid option/format or new `--json` plus an explicit
`--format` is the replacement's flag-level exit `2` case.
`--format=txt` is the default Human projection; `json`, `xml`, and `md` are
safe BlackOps projections with no Symfony descriptor schema. `--short` is an
exact no-op and `--raw` affects only txt; on json/xml/md it is accepted and
ignored. `--json` is exactly `--format=json` when used alone. `--all` is a
1.3-only request for hidden rows and is not a Stable compatibility input.
Repeated `--format` options use the last value. The explicit `--json` plus
explicit `--format` conflict returns exit `2`; all other Stable-accepted
combinations remain accepted. Namespace matching preserves the Stable 1.2.0
golden behavior, including its diagnostic and exit status. An unknown output
mode or other invalid option returns exit `2`.

### 3.3 Projection record

The internal projection may contain build and reader metadata, but the public
record is restricted to:

| Field | Type | Contract |
| --- | --- | --- |
| `name` | string | canonical command name, unique, non-empty, NFC-safe, no raw separators or empty colon segment, <=256 UTF-8 bytes |
| `aliases` | list<string> | exact aliases in deterministic order; each uses the same safe grammar; `ls` is the alias of `list` |
| `description` | string | strict NFC-safe one-line description, no control/CR/LF, <=256 UTF-8 bytes, no runtime interpolation |
| `origin` | `framework` or `application` | ownership classification |
| `hidden` | bool | emitted by JSON/XML/Markdown only with `--all`; hidden rows are otherwise omitted |

The reader must not load an application command class merely to discover its
description or hidden flag. Those values are compiled metadata. A malformed
record is an artifact error.

### 3.4 Output

Default Human output is a deterministic table with columns `Name`, `Aliases`,
`Description`, and `Origin`. `--raw` emits one LF-delimited record per row in
the form `name<TAB>alias1,alias2`; the alias field is empty when there are no
aliases. No other fields are emitted in raw mode. `--json` emits Schema v1:

```json
{
  "schemaVersion": 1,
  "applicationProjectionStatus": "available",
  "commands": [
    {
      "name": "list",
      "aliases": ["ls"],
      "description": "List BlackOps command metadata.",
      "origin": "framework"
    },
    {
      "name": "operation:list",
      "aliases": [],
      "description": "List operation metadata from the application manifest.",
      "origin": "framework"
    }
  ]
}
```

Without `--all`, hidden rows are omitted. With `--all`, Human and raw select
the same rows as the structured wires (raw keeps its fixed two-column shape),
while JSON adds `hidden`, XML adds the ordered `<hidden>` element, and Markdown
adds the `Hidden` column. The JSON object contains no `help`, `definition`,
`class`, `factory`, `container`, `default`, XML, or Markdown field. Human,
raw, JSON, XML, and Markdown select exactly the same rows. The XML and
Markdown canonical fixtures below are explicitly `--all` fixtures.

JSON v1 is the compact UTF-8 RFC 8785 JCS serialization of its closed object
followed by exactly one LF byte, with no BOM, CRLF, ANSI escape, unknown key,
or whitespace outside the JCS value. Raw v1 is UTF-8 `name<TAB>aliases` rows,
one row per LF including exactly one final LF, with no ANSI bytes; names and
aliases use the safe separator grammar above. Canonical/alias and short/non-
short JSON and raw bytes are identical, and escaping/empty-alias fixtures are
required. XML v1 and Markdown v1 are exact safe-wire projections of those same rows.
XML is one UTF-8 serialization with no XML declaration, no BOM, no
inter-element indentation or whitespace, double-quoted attributes, fixed
element order, lowercase boolean text, and exactly one final LF byte. The
root is `<blackopsCommandProjection version="1" status="STATUS">`, followed
without whitespace by `<commands>`, ordered `<command>` elements, and their
`<name>`, `<aliases>`, ordered `<alias>` children, `<description>`, `<origin>`,
optional `<hidden>`, then the closing elements. Empty aliases are exactly
`<aliases/>`. The predefined XML entities `&amp;`, `&lt;`, `&gt;`, `&quot;`, and
`&apos;` are always used for their respective characters; no other node,
attribute, LF, or byte is permitted. `STATUS` is one of
`available|absent|malformed|unsupported|stale|build_mismatch`.

The canonical XML fixture (the displayed `\\n` at the end denotes the one
final LF byte, not two characters) is the exact byte sequence
`<blackopsCommandProjection version="1" status="available"><commands><command><name>about</name><aliases/><description>A &amp; &lt;B&gt; &quot;C&quot; &apos;D&apos;</description><origin>framework</origin><hidden>false</hidden></command><command><name>ops:status</name><aliases><alias>os</alias></aliases><description>Quoted &quot;x&quot; &amp; &lt;y&gt;</description><origin>application</origin><hidden>true</hidden></command></commands></blackopsCommandProjection>\\n`; its SHA-256 including that final LF is `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`.

Markdown v1 is exactly one marker line, one header line, one delimiter line,
and the rows, with no blank lines or trailing spaces and exactly one final LF.
The marker is the sole non-table preamble line; every header, delimiter, and
row line has a leading and trailing pipe. The marker is
`<!-- blackops-command-projection-v1 status=STATUS -->`; the header columns
are `Name | Aliases | Description | Origin` and, only with `--all`,
`Hidden` appended. Every delimiter cell is exactly `---`; aliases use commas
with no spaces; hidden values are lowercase `true` or `false`. Cell encoding
scans Unicode scalar values once, converting a CRLF pair or standalone CR/LF
to the two bytes backslash+n, a literal backslash to two backslashes, and a
pipe to backslash+pipe, in that order; converted bytes are never rescanned.
The empty aliases cell is empty. A projection description containing CR/LF is
malformed before rendering, so the canonical wire never relies on newline
conversion; the cell rule remains a defensive closed-cell rule for any future
safe field. The canonical fixture is the exact byte
sequence
`<!-- blackops-command-projection-v1 status=available -->\\n| Name | Aliases | Description | Origin | Hidden |\\n| --- | --- | --- | --- | --- |\\n| about |  | A \\| B \\\\ C | framework | false |\\n| ops:status | os | Quoted "x" & <y> | application | true |\\n`; its SHA-256 including the final LF is `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.
In this inline notation, each line-separating `\\n` is one LF byte. The
doubled backslash and escaped pipe are literal output bytes, not Markdown
notation; no source CR/LF occurs inside a valid description.

Canonical `list` and `ls` output and output with or without `--short` are
byte-identical for each selected wire. These fixtures are golden bytes, not
code-fence formatting examples; the serialization rules and hashes govern.

Rows are sorted by canonical `name`, then by `aliases`. Namespace selection is
applied before sorting. Hidden rows are omitted unless `--all` is present;
`--all` does not authorize resolving hidden command code.

## 4. `route:list` / `route:ls`

### 4.1 Input and rows

```text
blackops route:list [--json]
blackops route:ls   [--json]
```

Human is the default; JSON is Schema v1. There is no `--raw` requirement in
the initial route contract. The row shape is:

| Field | Type | Contract |
| --- | --- | --- |
| `method` | string | uppercase HTTP method |
| `path` | string | compiled route path |
| `strategy` | string or null | compiled Operation execution strategy; `null` for a framework-fixed route |
| `operationType` | string or null | safe compiled Operation type for an Operation route |
| `origin` | `operation_manifest` or `framework_fixed` | route owner |

The public `Application::http()` composition always provides the fixed
`GET /operations/{operationId}` row. Placeholder names are structurally
equivalent for collision identity, so `/operations/{id}` collides with the
canonical route. P23-002B owns one dedicated
`blackops_route_projection`; the framework-fixed row is compiled into that
projection, not joined to a second HTTP artifact or read-time descriptor. The
supported public composition always includes that row with
`operationType: null` and `origin: framework_fixed`. The low-level nullable
`ProductionRuntimeComposer` composition is an internal/custom composition and
is not the public effective-route target. If optionality is introduced later,
it requires an explicit versioned enabled/capability field and positive and
negative fixtures; current route:list must not infer optionality from a
nullable low-level dependency. Arbitrary application middleware, web-server
rewrites, authentication providers, and handler classes are excluded.

The JSON shape is:

```json
{
  "schemaVersion": 1,
  "routes": [
    {
      "method": "GET",
      "path": "/operations/{operationId}",
      "strategy": null,
      "operationType": null,
      "origin": "framework_fixed"
    },
    {
      "method": "POST",
      "path": "/reports",
      "strategy": "inline",
      "operationType": "report.generate",
      "origin": "operation_manifest"
    }
  ]
}
```

The successful JSON object is exactly the closed
`{schemaVersion:1,routes:[...]}` shape above; it does not contain
`applicationProjectionStatus`. Human output uses the exact columns
`Method|Path|Strategy|Operation Type|Origin`, renders `null` strategy/type as
an empty cell, escapes every projection-controlled cell for Symfony Table,
and writes the complete bytes with `OUTPUT_RAW`. Decorated and undecorated
Human bytes are identical, ANSI-free, UTF-8/LF, and have exactly one final LF.
JSON is RFC 8785 JCS plus exactly one LF, with no BOM or CRLF. Successful Human
and JSON output has empty stderr. Canonical `route:list` and alias `route:ls`
have identical stdout, stderr, and exit status.

Rows sort by `path`, `method`, `origin`, `operationType`, and `strategy` with
nulls first. The `strategy` value is a safe compiled string such as `inline`
or `deferred`; it is never resolved from a runtime class.
Missing or stale dedicated route projection artifacts, unsupported schema, and
mismatched build ID are exit `2`; an unreadable artifact is exit `1`. No reflection,
container, database, handler, or middleware resolution is permitted.
Every non-available typed artifact status writes exactly
`Route projection is unavailable or invalid.\n` to stderr and no stdout before
exit `2`; an operational read writes exactly
`Projection build-set read failed safely.\n` and no stdout before exit `1`.
Invalid command input writes exactly `Input error.\n`, while invalid configured
expected identities write exactly `Route projection input is invalid.\n`; both
return exit `2` with no stdout. Exception messages and path details are never
forwarded.

## 5. `schedule:list` / `schedule:ls`

### 5.1 Input and rows

```text
blackops schedule:list [--json]
blackops schedule:ls   [--json]
```

The command opens and validates the `ProjectionBuildSet v1` root and all three
projection files under one shared lock, then selects and renders rows from the
dedicated `blackops_schedule_projection` named by that root; it does not
directly read an Operation Manifest. It never evaluates “due”, uses the current clock, claims an
Occurrence, resolves a Provider/container/database, loads an actor or tenant,
or reads Framework maintenance scheduler state.

```json
{
  "schemaVersion": 1,
  "schedules": [
    {
      "name": "reports.daily",
      "operationType": "report.generate",
      "cron": "0 0 * * *",
      "timezone": "UTC",
      "strategy": "deferred"
    }
  ]
}
```

Rows sort by schedule `name`, then `operationType`, using unsigned UTF-8 byte
comparison. Human and JSON select the same rows.
Schedule state, Occurrence state, misfire, overlap, due evaluation,
and maintenance tasks belong to the execution command and are not a metadata
listing concern.

Human output has the exact columns `Name|Operation Type|Cron|Timezone|Strategy`.
Projection-controlled cells are escaped with Symfony `OutputFormatter::escape`
and the complete rendered bytes are written with `OUTPUT_RAW`.
Decorated and undecorated output bytes are identical, contain no ANSI escape,
use UTF-8 LF endings, and end in exactly one LF.
JSON is the closed RFC 8785 JCS object shown above plus exactly one final LF;
it has no BOM, CRLF, unknown member, or `applicationProjectionStatus`.
Success writes no stderr.

Unknown options, unexpected arguments, and a non-Boolean `--json` write exactly
`Input error.\n` to stderr, no stdout, and exit `2`.
Missing, malformed, unsupported, stale, or build-mismatched complete artifacts
write exactly `Schedule projection is unavailable or invalid.\n`, no stdout,
and exit `2`.
An operational read writes exactly
`Projection build-set read failed safely.\n`, no stdout, and exits `1`.
An invalid configured build identity or locator input writes exactly
`Schedule projection input is invalid.\n`, no stdout, and exits `2`.
Canonical and alias invocations have identical stdout, stderr, and exit.

`schedule:list` and its exact `schedule:ls` alias are mutually identical for
both wires and all diagnostics: a valid complete artifact returns exit `0`, an
operational read failure returns exit `1`, and a missing, malformed,
unsupported, stale, or build-mismatched complete artifact returns exit `2`.
This complete-artifact rule is not the list/ls recovery rule: list/ls emits
framework rows and its six application status values at exit `0`.

## 6. Worker and operational command boundary

`worker:list` and `worker:ls` are neither BlackOps-owned nor reserved. An
Application may own either spelling as a canonical command or alias; an
unregistered spelling follows normal Symfony unknown-command behavior. No
Framework `JobDefinition` registry or built-in Job listing exists. Inline and
deferred Operation definitions are selected
by the future bounded `operation:list --strategy=inline|deferred` filter. The
option is case-sensitive, optional, and may occur once: missing value,
duplicate occurrence, unknown value, or case variant returns exit `2` before
Provider/compiler work with no partial output. Absence preserves current
Stable no-filter Human bytes, headers, order, and exit semantics. A valid
filter runs after existing metadata compilation and before existing typeId
unsigned-byte sort, keeps the `Type ID|Definition|Execution Strategy` table
(header-only for zero rows), returns exit `0`, adds no JSON/public machine wire,
DB read, or source fallback, and preserves Provider/compiler exception
behavior. P23-002A implements and independently reviews that filter. It
remains proposal-only and unreleased until
P23-006 accepts its compatibility and release gates. Queued instances first require an
aggregate-only `queue:status`; a bounded `queue:list` may be considered later
only after operation names, tenant scope, pagination, and redaction are
specified. Process capability may be exposed by `about`/`worker:status` only
after a worker registry is explicitly defined.

The command priority after metadata is:

1. `diagnostics:check` and aggregate-only `queue:status`;
2. `about` and `outbox:status`;
3. `dead-letter:list` only after row identity, operation/outbox naming, tenant,
   pagination, and redaction contracts exist.

These names remain proposal-only in this specification.

## 6.1 P23-002D diagnostics and aggregate queue status

P23-002D adds two CLI-only BlackOps `1.3` proposal commands. Neither command
adds a public HTTP route or changes Stable `1.2.0` Documentation.

### Diagnostics input and dependency authority

The only input is `diagnostics:check [--json]`. It has no alias, positional
argument, Operation ID, tenant, filter, or repeatable option. Unknown input or
a non-Boolean `--json` is rejected before any status/query dependency is called:
stdout is empty, stderr is exactly `diagnostics.input_invalid\n`, and exit is
`2`.

`ApplicationBuilder::withOperationalHealthQuery(?OperationalHealthQuery)` is
the sole Production readiness registration. The object is retained in
`ApplicationConfigurationSnapshot`; there is no implicit lookup through the
compiled Container, Service Providers, Database, handlers, or Operation
Runtime. Missing registration is a bounded failure, not a bootstrap failure.
Repeated Builder registration is last-call-wins and `null` clears it; the
default remains null, so existing Builder behavior is unchanged. The explicit
public API requires and owns only the Application-to-Observability and
InternalApplication-to-Observability architecture edges.

For every valid invocation:

1. capture one injected clock value;
2. call ProjectionBuildSet status exactly once and complete its callback under
   the existing shared lock;
3. release the lock;
4. call the registered readiness query exactly once, or contract missing query;
5. normalize and render.

No readiness, Database, Container, provider, or handler call occurs inside the
Projection callback. `list`, `help`, and invalid diagnostics input call neither
dependency.

### Diagnostics bounded report

The Projection family contains exactly one row. Its code is one of
`available|absent|malformed|unsupported|stale|build_mismatch|
configuration_invalid|read_failed`; only `available` has status `pass`.
`ProjectionBuildSetValidationException` contracts to `configuration_invalid`;
`ProjectionBuildSetFilesystemException` contracts to `read_failed`.

The readiness normalizer accepts only:

- `OperationalHealthKind::Readiness`;
- exactly one of each fixed code, in any input order:
  `compiled_artifact`, `runtime_configuration`, `database`,
  `migration_compatibility`, `storage_key_provider`, `runtime_services`;
- no duplicate, unknown, missing, or extra check;
- typed `pass|fail` statuses; and
- an aggregate status equal to the reduction of those six checks.

It checks `count(checks) === 6` before visiting any element. The existing
Application readiness `compiled_artifact` callback is not the
Framework-owned ProjectionBuildSet publication check; both remain separate
rows with separate owners.

It renders the six checks in the order above. It ignores the input report time.
Missing query becomes one readiness row `query_unavailable=fail`; a thrown
query becomes `query_failed=fail`; invalid kind/check set/aggregate becomes
`query_invalid=fail`. These contraction rows replace, rather than accompany,
the six readiness rows. The complete report therefore has at most seven rows.

Overall status is `pass` and exit is `0` only when Projection is `available`
and every readiness check passes. Every other valid invocation writes a safe
overall `fail` report, empty stderr, and exit `1`.

Human is the exact fixed-order UTF-8/LF wire:

```text
schemaVersion=1
status=pass
checkedAt=2026-08-27T15:00:00.000000Z
projectionBuildSet=available
readiness.compiled_artifact=pass
readiness.runtime_configuration=pass
readiness.database=pass
readiness.migration_compatibility=pass
readiness.storage_key_provider=pass
readiness.runtime_services=pass
```

For a contracted readiness failure, the six readiness lines are replaced by
exactly one `readiness.query_unavailable|query_failed|query_invalid=fail` line.

JSON is the closed RFC 8785 JCS object plus one LF:

```json
{"checkedAt":"2026-08-27T15:00:00.000000Z","checks":[{"code":"available","family":"projection_build_set","status":"pass"},{"code":"compiled_artifact","family":"readiness","status":"pass"},{"code":"runtime_configuration","family":"readiness","status":"pass"},{"code":"database","family":"readiness","status":"pass"},{"code":"migration_compatibility","family":"readiness","status":"pass"},{"code":"storage_key_provider","family":"readiness","status":"pass"},{"code":"runtime_services","family":"readiness","status":"pass"}],"schemaVersion":1,"status":"pass"}
```

The captured clock is formatted as UTC with six fractional digits. Human and
JSON are written once with `OUTPUT_RAW`, contain no ANSI/BOM/CRLF, and end in
exactly one LF. Raw exception/configuration/path/build ID/hash/provider/class/
credential/tenant/row/Operation/payload values are never copied to either
stream.

### Queue authority and snapshot

The only input is `queue:status [--json]`. It has no alias, positional argument,
tenant selector, actor, filter, or pagination. Local host or Container CLI
access is the operator authorization boundary. Scope is the fixed string
`installation`; a tenant-facing invocation is unsupported.

The source aggregates the current installation's PostgreSQL `operations`
table. It never selects or emits tenant, actor, Operation ID/type, payload,
reason, attempt, lease owner, or any row identity. It never reads
`outbox_records`. One injected clock is captured exactly once and passed as
`observedAt` to one fixed aggregate query which returns exactly one row.

The closed count fields and predicates are:

| Field | Predicate |
| --- | --- |
| `acceptedReady` | accepted and `available_at <= observedAt` |
| `acceptedDelayed` | accepted and `available_at > observedAt` |
| `retryReady` | retry_scheduled and `available_at <= observedAt` |
| `retryDelayed` | retry_scheduled and `available_at > observedAt` |
| `runningActive` | running and `lease_expires_at > observedAt` |
| `runningExpired` | running and `lease_expires_at <= observedAt` |
| `supervising` | supervising |
| `deadLettered` | dead_lettered |

Completed, rejected, and failed are excluded. A running row with a null lease
is integrity failure. Equality is ready or expired.

The closed age fields are `oldestReady` from the minimum eligible
`available_at`, `oldestRunningExpired` from the minimum expired
`lease_expires_at`, `oldestSupervising` from the minimum supervising
`updated_at`, and `oldestDeadLettered` from the minimum dead-lettered
`updated_at`. Age is the floored nonnegative `observedAt-source` seconds and is
null exactly when its corresponding count is zero.

Every count and non-null age is an exact integer in
`0..9007199254740991`. Missing/extra/nondecimal fields, future ages, cap
overflow, nullability mismatch, and running bucket mismatch are integrity
failure. Missing schema/table is storage unavailable rather than an empty
snapshot. The source uses no `fetchAll`, row loop, or payload decoding. The
current O(N) aggregate scan is accepted for correctness; this capability
defines no performance SLA and adds no schema migration, index, or counter.

The internal aggregate row has exactly 13 aliases: eight counts, four filtered
minimum timestamps, and validation-only `runningNullLeaseCount`. Unknown or
missing aliases fail integrity, and `runningNullLeaseCount` must be zero. It is
never part of either public wire. `QueueStatusSource::read(observedAt)` returns
only a validated `QueueStatusSnapshot`; typed source failure distinguishes
storage unavailable from invalid aggregate integrity.
These port, value, failure enum, and exception types are Core types so the
PostgreSQL Transport implementation has no dependency on Internal. Rendering
and Console orchestration remain Internal.

Human is the exact fixed-order wire:

```text
schemaVersion=1
scope=installation
observedAt=2026-08-27T15:00:00.000000Z
counts.acceptedDelayed=0
counts.acceptedReady=1
counts.deadLettered=0
counts.retryDelayed=0
counts.retryReady=0
counts.runningActive=0
counts.runningExpired=0
counts.supervising=0
agesSeconds.oldestDeadLettered=null
agesSeconds.oldestReady=0
agesSeconds.oldestRunningExpired=null
agesSeconds.oldestSupervising=null
```

JSON is the exact closed JCS object plus one LF:

```json
{"agesSeconds":{"oldestDeadLettered":null,"oldestReady":0,"oldestRunningExpired":null,"oldestSupervising":null},"counts":{"acceptedDelayed":0,"acceptedReady":1,"deadLettered":0,"retryDelayed":0,"retryReady":0,"runningActive":0,"runningExpired":0,"supervising":0},"observedAt":"2026-08-27T15:00:00.000000Z","schemaVersion":1,"scope":"installation"}
```

Queue result precedence is exact:

| Result | stdout | stderr | exit |
| --- | --- | --- | --- |
| success | exact Human or JSON | empty | `0` |
| input invalid | empty | `queue.input_invalid\n` | `2` |
| database/schema configuration invalid | empty | `queue.configuration_invalid\n` | `2` |
| connection/query/missing table | empty | `queue.storage_unavailable\n` | `1` |
| aggregate shape/value invalid | empty | `queue.integrity_failed\n` | `1` |

Success uses `OUTPUT_RAW`, no ANSI/BOM/CRLF, and one final LF. Failure emits no
exception message, SQL, DSN, path, schema, credential, tenant, or row value.

### P23-002D inventory and activation

Target metadata adds exact framework rows:

- `diagnostics:check`, no alias, description
  `Check application readiness and compiled artifacts.`, hidden false;
- `queue:status`, no alias, description
  `Show aggregate deferred-operation queue status.`, hidden false.

The activated target count becomes exact `33`, runtime Framework names exact
`27`, and the existing reservation registry remains exact `13`. Both commands
activate together. Neither creates a Job registry, `worker:list`, `queue:list`,
Outbox aggregate, or public route.
The Kernel registers the two concrete Command objects directly rather than
wrapping them in `LazyFrameworkCommand`. Their constructors execute no
readiness/DB dependency. Each `run()` catches Symfony Console
`ExceptionInterface` and writes its fixed input error before its valid-only
dependency closure can run.

## 6.2 P23-002E About and aggregate Outbox status

P23-002E adds two CLI-only BlackOps `1.3` proposal commands. Neither command
adds a public HTTP route or changes Stable `1.2.0` Documentation. The two
commands activate and are accepted atomically.

### About input and metadata authority

The only input is `about [--json]`. It has no alias, positional argument,
tenant, filter, time override, or repeatable option. Unknown input or a
non-Boolean `--json` is rejected before any metadata or Projection dependency
is called: stdout is empty, stderr is exactly `about.input_invalid\n`, and exit
is `2`.

The Framework version authority is
`Composer\InstalledVersions::getPrettyVersion('blackops/framework')`. The root
package directly requires `composer-runtime-api:^2.0`; a transitive dependency
is not an authority guarantee. Package is the fixed string
`blackops/framework`. The installed pretty version is not reinterpreted as
Semantic Versioning; it must be ASCII 1..128 bytes matching
`^[A-Za-z0-9][A-Za-z0-9._+@/-]{0,127}$`.

Runtime metadata describes only the process that executes the command. Scope
is fixed `current_process`. `PHP_VERSION` must be ASCII 1..64 bytes matching
`^[A-Za-z0-9][A-Za-z0-9._+-]{0,63}$`; `PHP_SAPI` must be lowercase ASCII
1..32 bytes matching `^[a-z0-9][a-z0-9_-]{0,31}$`.

The command calls one existing `ProjectionBuildSetStatusSource` once and
reduces it to exactly one of `available|absent|malformed|unsupported|stale|
build_mismatch|configuration_invalid|read_failed`.
`ProjectionBuildSetValidationException` contracts to `configuration_invalid`
and `ProjectionBuildSetFilesystemException` to `read_failed`. These values
describe installed build state. A non-available value is not a readiness
failure and a valid About report exits `0`; `diagnostics:check` remains the
health/readiness surface.

The command emits no Application build ID/hash, input hash, Composer
reference/install path/dependency inventory, Extension, environment, OS/host,
HTTP deployment mode, FrankenPHP/Caddy version, Deferred process mode, or
capability list. There is no current Worker/runtime capability registry from
which those claims can be derived.

The closed About object has only these fields:

| Field | Type and value |
| --- | --- |
| `schemaVersion` | integer `1` |
| `package.name` | fixed string `blackops/framework` |
| `package.version` | validated installed pretty version |
| `runtime.scope` | fixed string `current_process` |
| `runtime.phpVersion` | validated current `PHP_VERSION` |
| `runtime.sapi` | validated current `PHP_SAPI` |
| `projectionBuildSet` | exact eight status |

Human is the exact fixed-order UTF-8/LF shape below; dynamic values shown are
the canonical test example, not a future-version constant.

```text
schemaVersion=1
package.name=blackops/framework
package.version=1.2.0@dev
runtime.scope=current_process
runtime.phpVersion=8.5.0
runtime.sapi=cli
projectionBuildSet=available
```

JSON is the closed RFC 8785 JCS object plus one LF:

```json
{"package":{"name":"blackops/framework","version":"1.2.0@dev"},"projectionBuildSet":"available","runtime":{"phpVersion":"8.5.0","sapi":"cli","scope":"current_process"},"schemaVersion":1}
```

About result precedence is exact:

| Result | stdout | stderr | exit |
| --- | --- | --- | --- |
| valid report, including non-available build status | exact Human or JSON | empty | `0` |
| input invalid | empty | `about.input_invalid\n` | `2` |
| installed metadata missing/invalid/throwing or unexpected report shape | empty | `about.metadata_unavailable\n` | `1` |

The concrete Command constructor performs no metadata or Projection read.
`list`, `help`, and invalid About input call neither dependency. A valid
invocation calls each once and performs no Database, Container, Provider,
handler, Network, Environment, source discovery, or reflection work. Output is
written once with `OUTPUT_RAW`, contains no ANSI/BOM/CRLF, and ends in exactly
one LF. Failure contains no raw exception or forbidden metadata and writes no
partial stdout.

### Outbox authority and source

The only input is `outbox:status [--json]`. It has no alias, positional
argument, tenant, actor, record/Operation ID, filter, pagination, or time
override. Local host or Container CLI access is the operator authority. Scope
is fixed `installation`; there is no tenant-facing surface.

After input validation, the command captures one injected clock value and
passes that same `observedAt` to one source-factory call and one source read.
The valid-only factory resolves only `ApplicationDatabaseConfiguration`, the
Framework Database connection, and schema. It does not instantiate
`ApplicationOutboxRuntime`, Outbox Store, Operation Runtime, codec, storage
protection/provider, sender, relay, heartbeat, PCNTL, telemetry, or handlers.

The source aggregates only PostgreSQL `outbox_records`. It never reads
`operations`, Operation `dead_letters`, or
`outbox_dead_letter_retry_audits`. It does not select, materialize, or emit
tenant, actor, record/Operation ID/type, payload/context/ciphertext,
fingerprint, relay/fencing/attempt, reason, or connection name.

Due time is `COALESCE(next_attempt_at,available_at)`. The closed public count
fields and predicates are:

| Field | Predicate |
| --- | --- |
| `pendingReady` | pending and due `<= observedAt` |
| `pendingDelayed` | pending and due `> observedAt` |
| `retryReady` | retry_scheduled and due `<= observedAt` |
| `retryDelayed` | retry_scheduled and due `> observedAt` |
| `leasedActive` | leased and `lease_expires_at > observedAt` |
| `leasedExpired` | leased and `lease_expires_at <= observedAt` |
| `deadLettered` | Outbox state dead_lettered |

Equality is ready or expired. `sent` is terminal success and is excluded.
Outbox dead-letter state is not merged with Operation dead-letter state.

The exact age fields are `oldestReady` from minimum ready due time,
`oldestLeasedExpired` from minimum expired `lease_expires_at`, and
`oldestDeadLettered` from minimum `dead_lettered_at`. Age is the floored
nonnegative `observedAt-source` seconds. `oldestReady` is null exactly when
`pendingReady + retryReady` is zero; the other ages are null exactly when
their corresponding count is zero.

Every count and non-null age is an integer in `0..9007199254740991`.
Missing/extra/nondecimal/leading-zero/negative/overflow values, future ages,
count/null mismatch, and an invalid timestamp are integrity failure.

Each minimum alias is either `null` or exactly 27-byte ASCII
`YYYY-MM-DDTHH:MM:SS.ffffffZ`. The SQL expression applies
`AT TIME ZONE 'UTC'` and a fixed format to the filtered minimum, so DBAL returns
only this text and never a session-timezone／DateStyle representation. The
Source accepts only string or null, validates exact grammar plus calendar
semantics, and requires `Y-m-d\TH:i:s.u\Z` parse／format round-trip equality
before calculating age. Offset, timezone abbreviation, fraction omission or
non-six-digit precision, whitespace, nonstring, and invalid calendar values
are integrity failure.

The source executes one fixed aggregate query and calls `fetchAssociative`
once. The exact 13 aliases are seven counts, three filtered minimum timestamps,
and validation-only `retryNullNextAttemptCount`, `leasedNullLeaseCount`, and
`deadLetterInvalidTimestampCount`. The last value counts a dead-letter row
whose timestamp is null or later than `observedAt`; all three anomaly counts
must be zero. None is public output. Missing or extra aliases fail integrity.

Missing schema/table is storage unavailable, not an empty snapshot. No
`fetchAll`, row loop, payload decode, migration, index, counter table, or
performance SLA is added. The current O(N) installation aggregate is accepted
for correctness.

Human is the exact fixed-order wire:

```text
schemaVersion=1
scope=installation
observedAt=2026-08-27T15:00:00.000000Z
counts.deadLettered=0
counts.leasedActive=0
counts.leasedExpired=0
counts.pendingDelayed=0
counts.pendingReady=1
counts.retryDelayed=0
counts.retryReady=0
agesSeconds.oldestDeadLettered=null
agesSeconds.oldestLeasedExpired=null
agesSeconds.oldestReady=0
```

JSON is the closed JCS object plus one LF:

```json
{"agesSeconds":{"oldestDeadLettered":null,"oldestLeasedExpired":null,"oldestReady":0},"counts":{"deadLettered":0,"leasedActive":0,"leasedExpired":0,"pendingDelayed":0,"pendingReady":1,"retryDelayed":0,"retryReady":0},"observedAt":"2026-08-27T15:00:00.000000Z","schemaVersion":1,"scope":"installation"}
```

Outbox result precedence is exact:

| Result | stdout | stderr | exit |
| --- | --- | --- | --- |
| success | exact Human or JSON | empty | `0` |
| input invalid | empty | `outbox.input_invalid\n` | `2` |
| Database/schema configuration invalid or factory wrong type | empty | `outbox.configuration_invalid\n` | `2` |
| clock/connection/query/missing table/untyped source failure | empty | `outbox.storage_unavailable\n` | `1` |
| aggregate shape/value/anomaly/render failure | empty | `outbox.integrity_failed\n` | `1` |

Success uses `OUTPUT_RAW`, no ANSI/BOM/CRLF, and one final LF. Failure emits no
raw exception, SQL, DSN, credential, schema/path, tenant, identity, payload,
context, fingerprint, relay, attempt, reason, or row value.

### P23-002E inventory and activation

Target metadata adds exact Framework rows:

- `about`, no alias, description
  `Show installed BlackOps package, runtime, and projection status.`, hidden false;
- `outbox:status`, no alias, description
  `Show aggregate transactional outbox status.`, hidden false.

The activated target count becomes exact `35`, runtime Framework names exact
`29`, existing reservation names remain exact `13`, default visible list
becomes exact `34`, and the current producer fixture becomes exact `38`.
Both commands activate together. Neither creates a Worker/capability registry,
`dead-letter:list`, row list, tenant surface, or public route.

The Kernel registers both concrete Command objects directly rather than
wrapping them in `LazyFrameworkCommand`. Constructors perform no I/O. Each
`run()` catches Symfony Console `ExceptionInterface` and writes its fixed input
error before any valid-only dependency executes.

## 7. Failure and negative contracts

| Condition | Result | Forbidden fallback |
| --- | --- | --- |
| valid artifact and input | deterministic output, exit `0` | none |
| unreadable artifact or transient read failure | safe operational error, exit `1` | source discovery, reflection, live DB/container |
| missing application projection for `list`/`ls` | framework rows plus structured status, exit `0` | application discovery or runtime resolution |
| missing complete route/schedule artifact | invalid artifact, exit `2` | compile at read time |
| syntactically valid unsupported schema/kind | `applicationProjectionStatus=unsupported` for list/ls at exit `0`; complete route/schedule artifact failure, exit `2` | silent migration |
| oversize/invalid bytes/JSON/duplicate/unknown/missing/type/record shape | `applicationProjectionStatus=malformed` for list/ls at exit `0`; complete route/schedule artifact failure, exit `2` | source discovery |
| stale or mismatched build ID | framework rows/status and exit `0` only for `list`/`ls`; otherwise invalid artifact, exit `2` | read another build or current source |
| Stable namespace (nested exact, unique abbreviated, ambiguous, or unknown) | Symfony Stable `1.2.0` diagnostic and exit golden | changed exit or partial output |
| new invalid option/format, including `--json` plus explicit `--format` | invalid input, exit `2` | partial output |
| application command collision with reserved name | build/configuration failure | override or alias shadowing |
| provider/container/DB/handler unavailable | no row or execution lookup | resolving it to make a list |
| hidden row without `--all` | omitted | leaking it through raw/JSON |
| unregistered `worker:list`/`worker:ls` request | normal Symfony unknown-command diagnostic | claiming a Framework Job row or registry |

No failure output includes a path outside the configured artifact boundary,
credential, exception message, source class, or database detail.

## 8. Runtime contract

### 8.1 Supported arrangement

Stable `1.2` already provides FrankenPHP Worker Mode as the default HTTP
runtime. P23-004A hardens that existing default into the sole supported
production HTTP profile and gates Classic fallback/profile removal and
migration. The proposed Deferred arrangement remains a separate service or
container for the strongest process, resource, lifecycle, and deployment
isolation. Same-container multi-process packaging is an optional operational
convenience: each PHP process retains OS-process crash isolation, but both
processes share the container cgroup, container lifecycle, rollout/rollback,
and resource ceiling. It therefore requires an explicit supervisor and signal
forwarding, and must never be described as independent resource or deployment
isolation. Separate startup, readiness, memory, restart, and deployment
evidence remains required for each process.

The existing Deferred loop retains PCNTL signal handling, heartbeat, lease
recovery, claim settlement, retry, and graceful shutdown. The HTTP Worker
retains request-driven `frankenphp_handle_request()` processing and request
scope cleanup. No command or config in this proposal starts Deferred work from
an HTTP request worker.

### 8.2 Rejected direct transplant

Standard FrankenPHP Worker Mode does not provide an autonomous Deferred loop.
Same-process background work would require a FrankenPHP Extension Worker and a
Go extension; a configurable extension requires a custom Caddy module and a
custom binary built with `xcaddy`. Extension Workers are thread pools within
the FrankenPHP process, not OS-process isolation. Therefore native crash, OOM,
process signal, or startup failures can still affect HTTP serving. A direct
transplant of the CLI/PCNTL Deferred loop is rejected.

An optional falsification spike may test a minimal queue handoff, lifecycle,
heartbeat, memory and crash boundary. It is not a release dependency. A
native crash or OOM that takes HTTP down fails the architecture experiment and
cannot be papered over with retry or a Caddy setting.

### 8.3 Classic migration

Classic removal is independent of the same-process experiment. P23-004A
hardens the existing default FrankenPHP Worker Mode into the sole supported
production HTTP profile, but Classic removal requires separate evidence for
bootstrap failure, safe 500, readiness,
multi-request state reset, memory growth and `max_requests`, upgrade,
same-SHA local/remote CI, rolling deployment, and rollback. `Application::http()`
remains available for custom PSR-15 adapters and tests. No same-process
Deferred guarantee is implied.

## 9. Audit Trail contract

This section is the normative proposal for the future Audit Trail and is
intentionally separate from the command and runtime sections.
The following current-wire handoff is part of this active base section and
defines no alternate implementation. The sole current query wire is
`AuditQueryScope v1` `{schemaVersion:1,kind:"audit_query_scope",partitionId,
tenantBinding,filters,order,limit}` plus
`AuditAccessTarget v1` `{schemaVersion:1,kind:"audit_access_target",queryScopeDigest,
pageMode,snapshotHeadSequence,cursorDigest,continuationPosition}`. The
context is `{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,
target,authorizationDecision,requestedAt,nonce,requestRef}`; this current query
wire fixes `purpose="query"` and `authorizationDecision="allow"`. Export,
sign, and verify use separate versioned exact context/target wires owned by
P23-003F/G and never reuse this query target. Its requestRef
preimage is the exact domain-separated JCS of
`{purpose,actor,tenant,target,authorizationDecision,requestedAt,nonce}`.
The context is generated internally by the Framework's trusted
`AuthorizationEvaluator`; external callers cannot supply an allow result or
requestRef, and requestRef is not authentication proof. P23-003G owns
external-allow/requestRef-injection and requestRef-not-proof negatives.
The current first-page global golden has `queryScopeDigest=
d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7` from
the exact scope JCS/LP lengths `311/348`. Its target is the first-page
`AuditAccessTarget` with that digest and null `snapshotHeadSequence`,
`cursorDigest`, and `continuationPosition`; the target-bearing Access input
has JCS/LP lengths `374/418` and requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`.
P23-003G requires Node and Docker-PHP parity for this scope/target digest
and Access requestRef, plus the historical target-less `old-103` vector as a
negative fixture only; that vector is not current wire data.
The cursor payload carries `queryScopeDigest`, raw cursor digest, fixed head,
last sequence, continuation position through the target, and issuer
`requestRefDigest` as separate bindings. Its key window is exactly
`notBefore <= issuedAt <= trustedNow < expiresAt <= notAfter`; issuance rejects
`expiresAt > notAfter` and accepts equality. A verification key remains until
every cursor expiry plus accepted skew. Rotation, expiry-equality, and
expiry>notAfter negatives belong to P23-003G. Results are exactly `ok|empty|
unavailable`, and malformed/auth/cursor/scope/head/position failures use the
finite `AuditQueryError v1` wire above before lookup or decrypt. Valid store
failure and legal record-cap overflow remain `AuditQueryResult.unavailable`
with `reasonCode=resource_unavailable|record_too_large`.
The current durable outbox row is
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody,version}` with only
`pending|leased|relayed|poisoned`; every mutation is an expected-version CAS
with exact +1 version and, where a lease exists, its matching token. Its active
hashes remain
`H(ENC("blackops.audit.outbox.idempotency.v1")||ENC(partitionId)||
ENC(NFC(idempotencyKey)))` and
`H(ENC("blackops.audit.content.v1")||ENC(canonicalLogicalBytes))`.
AnchorIntent has exactly
`prepared|externally_accepted|externally_rejected|receipt_inputs_persisted|
receipt_persisted|acknowledged`; its public receipt is the active §9.6.1
`AuditAnchorReceipt v1`, not a second wire. Retention has the exact metadata
wire and snapshot fields in the Round29 block, states
`retained|eligible_for_future_deletion`, and reason codes
`retention_not_elapsed|active_hold|policy_unavailable|
retention_elapsed_no_active_hold`. Older summaries in this active section are
descriptive only and cannot define another current wire.

The current handoff uses one total query-error mapping. Stage 2 maps invalid
trusted AuthorizationEvaluator provenance, deny, unavailable, requestRef
recomputation mismatch, stale, future, and nonce replay to
`authorization_provenance_invalid|authorization_denied|
authorization_unavailable|request_ref_mismatch|stale_request|future_request|
nonce_replay`; Stage 3 maps every partition, tenant, or query-scope predicate
failure to `scope_mismatch`. A `nonce_replay+cursor_replay` compound emits only
the earlier Stage 2 code. P23-003G owns provenance-invalid, requestRef-mismatch,
first/continuation partition-substitution, malformed+deny, wrong-role+bad-HMAC,
bad-HMAC+not-yet/expired, and authenticated-cursor-replay fixtures with zero
later read/decrypt. Valid store failure and legal-record cap overflow remain
`AuditQueryResult.unavailable` with `resource_unavailable|record_too_large`.

The Outbox handoff has one trusted transition clock per claim, retry, relay,
poison, and recovery. A lease is active exactly when
`trustedNow < leaseExpiresAt` and expired exactly when
`trustedNow >= leaseExpiresAt`, including equality; expired recovery requires
the stored matching expired token plus expected version in one fenced CAS.
`expiry-1us`, equality, `expiry+1us`, stale token/version, relay-versus-
recovery, and max-attempt 1/32 fixtures are owned by P23-003C.

The AnchorIntent handoff requires deterministic same-object reconciliation
before any new external side effect after pre-persist or post-persist response
loss. Its closed outcomes are `external_object_found|external_object_absent|
lookup_unavailable`; found applies one fenced +1 accepted revision with no N+1.
An explicit absent reconciliation at attempt 31 in `post_call_uncertain`
returns `prepared/pre_call` with `preCallClaimMode=new_attempt`; the next claim
creates attempt 32. At attempt 32, an explicit absent lookup in `pre_call`
permits exactly one `external_call_start` and the final external call. Only an
explicit absent reconciliation at attempt 32 in `post_call_uncertain` applies
the fenced +1 transition to `externally_rejected/attempt_exhausted`.
Pre-call `pending`, lookup `unavailable`, and `found` remain phase-specific
nonterminal branches; post-call `found` recovers acceptance, while post-call
`unavailable` clears the lease into reconciliation-only prepared and never
permits a new external call. Expired attempt32 prepared leases use the same
branches; stale token/version/stateHash is no mutation, and no branch creates
attempt 33 or N+1. P23-003F owns the response-loss, attempt31->32
pre-call-absent/call-start, attempt32 pre-call-absent final-call, and
attempt32 post-call-absent terminal-reconciliation fixtures.

Retention uses the four-branch truth table above. An eligible state reverses
only for a strictly newer policy/hold snapshot or a strictly newer
policy-unavailable policy snapshot; same-version available-to-unavailable is a
fork. Retention state uses only `blackops.audit.retention-state.v1` and
`blackops.audit.retention-idempotency.v1`, while the separate AuditHold uses
only `blackops.audit.hold-state.v1` and `blackops.audit.hold-idempotency.v1`;
cross-domain substitution fails closed. P23-003G owns Node/Docker-PHP golden
parity and equal/newer/stale/fork, cross-domain, and no-hold fixtures; P23-003H
owns the fresh atomic deletion fence.

### 9.1 Four surfaces

| Surface | Canonical data | Owner/reader | Delivery/retention |
| --- | --- | --- | --- |
| Application Log | diagnostic/application messages | Application and operator | application policy |
| Observability | structured logs, low-cardinality metrics, sampled traces | Application telemetry provider and operator | provider policy |
| Lifecycle Journal | Operation lifecycle and replayable framework events | Framework canonical store and bounded readers | Journal policy; no trace sampling |
| Audit Trail | attributable business/security/control events | dedicated authorized AuditRecord readers | category policy and legal hold |

In the 1.3 target, JSONL `kind=audit` is a safe derived projection of a
canonical AuditRecord. It is not the canonical AuditRecord and must not be
used as the sole compliance or integrity store. Current Stable JSONL and the
narrow retention/replay/rotation audit-like records are not retroactively
general AuditRecords. Stable Structured Log/OpenTelemetry already provide
current Operation/Correlation/Trace/Span correlation, and
`ExecutionScopedLogger` is a current safe observability projection. The new
four-surface Audit correlation contract is future-only. Audit Trace/Span IDs
remain optional sampled fields in that future contract; they are not identity,
ordering, integrity, or evidence.

The target is audit-ready-by-default, not automatic compliance or
non-repudiation. OWASP's guidance distinguishes process, audit, transaction,
and security logs and notes that application code has the best context for
actor, action, target, and outcome. BlackOps therefore combines framework
owned events with explicitly authored application events.

### 9.2 Event ownership and authoring

The Framework may auto-record only semantics it owns:

- retention plan/purge, hold, and legal-hold transitions;
- storage-key rotation and checkpoint-backed seal evidence;
- observer replay begin/progress/failure/finish;
- outbox retry, dead-letter, and privileged relay controls; and
- privileged audit, access, configuration, and security boundary changes.

Application business CRUD, refund, approval, consent, and regulated access
must register a versioned event definition and typed projector. The projector
must declare category, action type, resource type, safe evidence fields,
policy/decision/reason codes, and delivery mode. BlackOps must not infer a
business action or resource from handler completion and must not duplicate
every lifecycle event into Audit Trail.

The minimum concrete Framework security/access catalog is owned by P23-003A
as typed event definitions and schema. P23-003D owns the adapters and
independent review against the current AuthenticationMiddleware,
AuthorizationEvaluator, and protected Operation-data read boundaries:

| Candidate `event.type` | Required phase/outcome | Safe reason/evidence boundary | Registered delivery |
| --- | --- | --- | --- |
| `security.authentication.succeeded` | `succeeded` / `succeeded`, policy `allow` | provider-explicit typed authenticator provenance and safe principal type/ID only when supplied; no invented module/method ID, credential, or token | `durable` |
| `security.authentication.invalid` | `denied` / `denied`, policy `deny` | safe provider-reported reason such as `invalid_credentials`, `invalid_token`, `expired_session`, or `malformed_request`; no invented module/method ID, credential, token, or request body | `durable` |
| `security.authorization.allow` | `succeeded` / `succeeded`, policy `allow` | policy ID/version, safe operation/resource type, and decision reason | `durable`; `required` when a privileged state-changing policy requires it |
| `security.authorization.deny` | `denied` / `denied`, policy `deny` | stable deny reason such as `authentication_required`, `forbidden`, or `policy_rejected`; no policy payload or request body | `durable`; `required` when configured by the privileged policy |
| `access.operation_status.requested` / `.unavailable` / `.accessed` | decision-boundary request, safe unavailable, or authorized result | safe operation type, tenant partition, and `resource_unavailable` when unavailable; no existence claim | `required` delivery plus durable persistence |
| `access.operation_journal.requested` / `.unavailable` / `.accessed` | decision-boundary request, safe unavailable, or authorized result | bounded journal-read purpose and tenant partition; no journal payload | `required` delivery plus durable persistence |
| `access.operation_outcome.requested` / `.unavailable` / `.accessed` | decision-boundary request, safe unavailable, or authorized result | bounded outcome-read purpose and tenant partition; no outcome payload | `required` delivery plus durable persistence |
| `access.operation_diagnostics.requested` / `.unavailable` / `.accessed` | decision-boundary request, safe unavailable, or authorized result | bounded diagnostic purpose and tenant partition; no diagnostic payload | `required` delivery plus durable persistence |

The four read selectors above are the exact operation family
`access.operation_{status,journal,outcome,diagnostics}.requested|unavailable|accessed`;
each tuple has catalog delivery `required`, and the canonical append is durably
persisted before a read response or failure is released. Authentication success
and invalid definitions remain durable-only.

For every Status, Journal, Outcome, and Diagnostics read, the framework emits
exactly `.requested` before authorization/lookup and exactly one terminal
`.unavailable` or `.accessed` before releasing a result or failure. Same-target
authorized reads map to `.accessed`; policy rejection, absent, and cross-tenant results
are indistinguishable and map to `.unavailable` with
`action.phase=unknown`, `outcome.status=unknown`,
`outcome.code=resource_unavailable`, `policy.decision=not_applicable`,
`policy.reasonCode=resource_unavailable`, and
`AuditReadContext.authorizationDecision=resource_unavailable`. All three
catalog definitions are `required`; the requested and selected terminal event
are durably persisted before the response or failure is
released. The obsolete not-found reason is not a v1 reason or event. P23-003A
owns the catalog tuple and selector; P23-003D owns the adapter and the complete
same/cross/absent fixture matrix.

The service-managed action family is exactly
`audit.access.{query,export,sign,verify}.requested|unavailable|accessed`.
`requested` and `unavailable` are durably persisted; `accessed` is required and
durably persisted before returning records, an export, a signature, or a
verification result. The access event itself never emits another access event
(recursion depth exactly one). P23-003A owns the catalog, P23-003D/F/G own the
adapters and orchestration, and allow/deny/cross/absent/outage/offline/depth
fixtures are required.

The prior service-action summary is retained only as non-normative historical
description. It defines no wire, requestRef preimage, decision token, or query
fixture; the target-bearing query context above is the only current
AccessContext contract. Legacy requester field names do not exist.

The exact pre-decision `AuditReadRequestBasis v1` is
`{purpose,actor,execution,tenant,requestedAt,nonce}`. `purpose` is
`status|journal|outcome|diagnostics`, `actor` is null or exactly
`{type,id}`, `execution` is required `{type,id}`, and `tenant` is null or
exactly `{type,id}`. Each `type` and `id` is valid UTF-8, NFC, free of
`Cc|Cf`, and 1..256 UTF-8 bytes inclusive; no trim, case-fold, silent
normalization, UUID, or path grammar is inferred. `requestedAt` is
UTC with six fractional digits and `nonce` is a 1..128-byte NFC/no-control
noncredential value. The final requestRef
is exactly `"oid:v1:" || HEX_LOWER(SHA-256(LP("blackops.audit.read.request-ref.v1") ||
LP(JCS(requestBasis))))`; LP is only the SHA-256 preimage, not the final value.
There is no target field or target-derived input. The golden is JCS `150`, LP
preimage `192`, and
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
The exact hash input vector is
`{actor:null,execution:{id:"exec-1",type:"cli"},nonce:"nonce-1",purpose:"status",requestedAt:"2026-08-20T03:20:00.123456Z",tenant:null}`;
its JCS is exactly 150 bytes and its LP preimage is exactly 192 bytes.
Node and Docker-PHP implementations must agree, with null, present,
actor/tenant substitution, cap+1, self-reference, raw-preimage, and
algorithm-substitution fixtures.

After the trusted decision, the exact closed `AuditReadContext v1` is
`{schemaVersion,kind,purpose,actor,execution,tenant,requestedAt,nonce,requestRef,
authorizationDecision}`. Constants are `schemaVersion=1` and
`kind="audit_read_context"`; its basis fields/requestRef are byte-identical to
the validated basis and derived value, and decision is
`allow|deny|resource_unavailable`. Caller input cannot supply decision or ref.
P23-003D owns Operation-read 16-byte CSPRNG nonce capture/lower-hex encoding;
P23-003G owns the equivalent service-access boundary. A basis cannot be reused
for another request; the requested and one terminal event deliberately share
one ref. P23-003A/D owns the contract and P23-003G owns read orchestration.

The framework-controlled signing operation for exact target
`system:global/audit-control-v1` and its derived framework role is the sole
finite control exemption: its signed checkpoint/receipt is authoritative and
emits no `audit.access.sign.accessed`. Callers cannot select the target or role.
All ordinary signatures retain the required `audit.access.sign.accessed`
delivery before release, and P23-003F owns checkpoint/anchor signing and
verification fixtures. Offline verification makes no canonical append or
access-event claim.

An anonymous request is not automatically an authentication event. An
authentication success or invalid result is recorded only when the
authenticator reports that semantic; absence of credentials alone is not an
invalid failure. Credential/session issue and revoke are Application-owned
unless a later Framework authenticator/session module owns that semantic.
Operation, correlation, causation, trace, and span IDs may be absent or
`null`. `best_effort` is not a canonical AuditRecord delivery mode and is
permitted only for a separate observed JSONL projection with no Audit Trail
claim; an explicit `required` event cannot be downgraded when append fails.
The minimum
fixtures are authenticated success, invalid authentication, anonymous
no-event, authorization allow, authorization deny, privileged-read allow and
deny for Status/Journal/Outcome/diagnostic data, absent-ID without disclosure,
and credential/token/request-body non-recording.

### 9.3 AuditRecord v1

Every canonical AuditRecord has exactly these top-level fields in this logical
order: `schemaVersion`, `kind`, `recordId`, `event`, `action`, `actor`,
`resource`, `outcome`, `policy`, `tenant`, `time`, `source`, `correlation`,
`evidence`, and `integrity`. The first fourteen fields are the canonical
logical body; `integrity` is appended only after its hashes are calculated.

| Path | Required | Type and allowed values |
| --- | --- | --- |
| `schemaVersion` | yes | integer, exactly `1` |
| `kind` | yes | string enum, exactly `audit` |
| `recordId` | yes | non-empty opaque string, unique within the canonical store |
| `event.type` | yes | non-empty event-definition string |
| `event.version` | yes | positive integer; version of the event definition |
| `event.category` | yes | enum: `business`, `security`, `access`, `configuration`, `control`, `retention`, `replay`, `rotation`, `outbox` |
| `event.catalogHash` | yes | exactly 64 lowercase hexadecimal characters; domain-separated hash of the closed `AuditEventCatalog v1` defining this event |
| `action.id` | yes | non-empty opaque action string |
| `action.type` | yes | non-empty versioned action string |
| `action.phase` | yes | enum: `requested`, `started`, `committed`, `succeeded`, `denied`, `failed`, `unknown` |
| `actor.principal` | yes | typed object `{type,id}` or JSON `null`; `null` explicitly means invalid, unknown, or unauthenticated principal and never an empty principal |
| `actor.execution` | yes | closed object exactly `{type,id}` with non-empty safe values; required even when principal is `null` |
| `resource` | yes | safe object with non-empty `type` and opaque `id` strings |
| `outcome.status` | yes | enum: `succeeded`, `denied`, `failed`, `unknown` |
| `outcome.code` | yes | stable, non-empty safe code string |
| `policy.id` / `policy.version` | yes | non-empty policy identifier/version strings |
| `policy.decision` | yes | enum: `allow`, `deny`, `not_applicable` |
| `policy.reasonCode` | yes | non-empty safe reason code string |
| `policy.auditDelivery` | yes | enum: `required`, `durable`; assigned by the versioned policy evaluator before effect/response |
| `tenant` | yes | tenant object with non-empty `type`/opaque `id`, or JSON `null` |
| `time.occurredAt` / `time.recordedAt` | yes | UTC RFC3339 strings with exactly six fractional microseconds |
| `source.owner` | yes | enum: `framework` or `application` |
| `source.component` / `source.buildId` | yes | non-empty safe component/build strings |
| `source.runtime` | yes | enum: `http`, `cli`, `worker`, `scheduler`, `relay`, `replay` |
| `correlation` | yes | closed object; optional identity/correlation members may be omitted or `null` |
| `correlation.attempt` | optional | object `{number: positive integer, kind: initial\|retry\|replay}` or `null`; identifiers are bounded safe strings |
| `correlation.sampled` | optional | boolean; applies only to trace/observability correlation |
| `evidence.version` | yes | positive integer exactly equal to the selected catalog definition's `evidenceVersion`; remaining keys are that definition's closed safe values |
| `integrity` | yes | object with exactly the Section 9.6 chain fields, appended after hashing; it never contains checkpoint data |

Every canonical object other than versioned `evidence` is closed
(`additionalProperties=false` equivalent). The exact nested key sets are:
`event={type,version,category,catalogHash}`, `action={id,type,phase}`,
`actor={principal,execution}`, `actor.principal=null` or exactly `{type,id}`,
`actor.execution` exactly `{type,id}`, `resource` exactly `{type,id}`,
`outcome={status,code}`, `policy={id,version,decision,reasonCode,auditDelivery}`,
`tenant=null` or exactly `{type,id}`, `time={occurredAt,recordedAt}`,
`source={owner,component,runtime,buildId}`, and `integrity` exactly the
Section 9.6 fields. `correlation` may contain only
`operationId|correlationId|causationId|attempt|traceId|spanId|sampled`;
`attempt` is `null` or exactly `{number,kind}`. Unknown members in any of
these objects are invalid. `evidence` requires `version` and otherwise has
only the members and types in the registered exact event-definition schema;
that schema is also closed and rejects unknown members. This is a schema
contract, not permission to store arbitrary application data.

`time.recordedAt` is authoritative and producer-overrides are prohibited. For
`required`, the canonical store assigns it once in the mutation transaction.
For `durable`, the trusted local Audit Outbox ingress assigns it once in that
mutation transaction. Each authority/partition applies the trusted-clock
high-water clamp `max(now,lastAssignedRecordedAt)` inside the stamping
transaction. Canonical sequence allocation occurs only at canonical append;
`sequence` is the only chain/order/pagination cursor, while `recordedAt` is
the trusted ingestion/retention timestamp and may be older on a later relay.
Relay and retry keep `recordedAt`, `recordId`, and `contentHash` immutable.
`time.occurredAt` is asserted by the owning adapter or application. The exact temporal rule is
`occurredAt <= recordedAt + 300 seconds`, inclusive at exactly 300 seconds;
one microsecond beyond the bound is rejected. Delayed, retry, and replayed
events remain valid when this rule holds and do not extend retention. Producer
recorded-time override, clock rollback, delay, retry, and replay fixtures are
required.

The complete canonical wire is `JCS(complete AuditRecord including integrity)`
followed by one LF and is at most 1 MiB inclusive before the writer appends or
commits it. The writer enforces this bound for both the required store path and
the durable outbox ingress. Every generic string is NFC-normalized UTF-8 of at
most 256 bytes with no control characters; narrower field bounds take
precedence. Correlation identifiers are also at most 256 UTF-8 bytes; `traceId`
is exactly 32 lowercase hexadecimal characters and `spanId` exactly 16. The
logical and complete-record cap, cap+1, and multibyte Unicode byte-count
fixtures fail before storage or response.

### 9.3.1 AuditEventCatalog v1

D147 is the current normative boundary for Catalog identity, typed
`ENC_STRING`／`ENC_BYTES`, RFC 8785 bytes, `catalogHash`, evidence presence,
the exact minimum Framework security/access catalog, keyed `opaque_id`, read
cardinality, AuditRecord construction stages, and observed safe projection.
P23-003A1 owns Catalog-only JCS/hash; P23-003B alone owns AuditRecord logical
JCS and record/chain hashes. Earlier examples in this section are explanatory
where D147 supplies a more specific closed wire.

`event.catalogHash` binds every record to one closed, versioned catalog
artifact. `AuditEventCatalog v1` has exactly these stored properties:
`schemaVersion: 1`, `kind: audit_event_catalog`, `catalogId`,
`catalogVersion`, `owner: framework|application`, `events`, and the derived
`catalogHash`. `catalogId` is store-wide unique; `catalogVersion` is a
positive integer. `events` is non-empty and has no duplicate
`(type,version,evidenceVersion)` tuple. Each event definition is closed and
has exactly `type`, `version`, `evidenceVersion`, `category`, `actionTypes`,
`semanticTuples`, `evidenceFields`, `maxEvidenceBytes`, and `delivery`.
`evidenceVersion` is a
positive integer and selects the evidence schema for that event version.
`actionTypes` is a bounded non-empty list of at most 32 unique strings;
`semanticTuples` is a non-empty list of at most 32 unique closed objects, each
exactly `{actionType,phase,resourceType,outcomeStatus,outcomeCode,
policyDecision,policyReasonCode}`. Every one of the seven tuple values is
non-null and non-empty, NFC-normalized UTF-8 of at most 128 bytes with no
control characters, and resolves to its corresponding v1 action registry,
phase/status/decision enum, or bounded resource/outcome/reason code. Wildcard
or null tuple values are not allowed. The
distinct `semanticTuples[*].actionType` set must equal `actionTypes`. A writer
and verifier require the complete tuple to equal the record's
`action.type`, `action.phase`, `resource.type`, `outcome.status`,
`outcome.code`, `policy.decision`, and `policy.reasonCode`.
`evidenceFields` is a bounded list of at most 32 closed descriptors, each
exactly `{name,type,required,nullable,maxUtf8Bytes,classification}`. `name`
is a non-empty
NFC-normalized string of at most 128 UTF-8 bytes with no control characters;
the name `version` is reserved and is rejected as a field descriptor;
within one event definition, normalized exact UTF-8 field-name bytes are
unique. Same-name same-type, different-type, and normalization-equivalent
duplicates all fail. `type` is exactly one of
`string|integer|boolean`; `required` and `nullable` are booleans;
`maxUtf8Bytes` is a positive integer at most 256 for `string`, and is `null`
for `integer|boolean`. `classification` is exactly `safe_code|opaque_id` for
strings and exactly `null` for integers and booleans. A `safe_code` is NFC
normalized ASCII lowercase matching `^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$`, is at
most the declared `maxUtf8Bytes`, and has an absolute maximum of 256 UTF-8
bytes. An `opaque_id` is not an arbitrary string: its wire form is exactly
`oid:v1:<64 lowercase hex>` and is exactly 71 UTF-8 bytes; its descriptor must
declare `maxUtf8Bytes:71` and a typed writer/mapper derives it with
the domain/keyed derivation before serialization. The verifier checks only
the wire grammar and declared category; it cannot cryptographically prove
origin or absence of a secret. Typed writers reject or derive raw
credential/token input, while Application review owns semantic mapping;
offline syntax validation alone is not secret detection. `maxEvidenceBytes`
is a positive integer at most 8192. Negative fixtures cover missing or unknown
`classification`, non-null integer/boolean classification, invalid `safe_code`
grammar, and a raw arbitrary opaque token; positive fixtures cover a valid safe
code and a typed derived opaque ID. A safe-code of exactly 256 bytes passes
when the descriptor declares 256 and 257 bytes fails; opaque descriptor/value
lengths 70 and 72 fail while exactly 71 passes. A nullable string `null`
fixture passes without a byte-length check. These null, 256/257, and opaque
70/71/72 boundaries are included in both catalog and record validation.
`delivery` is exactly one of `required|durable`; a catalog has at most
256 event definitions and its unsigned JCS body is at most 1,048,576 bytes.
`event.type` and every `actionTypes` value are non-empty normalized UTF-8
strings of at most 128 bytes with no control characters. `category` is exactly
one value from the AuditRecord category enum. A record's `event.category` must
equal its resolved definition's `category`, `action.type` must be one of that
definition's `actionTypes`, and `source.owner` must equal the catalog's
`owner`; no implicit cross-owner mapping exists. The definition's `delivery`
is the sole writer delivery mode: P23-003C selects its required/durable path
before mutation or response and cannot override it. A conditional event family
registers durable and required definitions under separate selectors. The
versioned policy evaluator must assign `policy.auditDelivery` before any
effect or response: only an explicit `required` audit requirement selects the
required selector, and only an explicit `durable` requirement selects the
durable selector. Missing, omitted, or unknown requirements fail closed; none
is defaulted now that the canonical record field is required. The recorded
`policy.auditDelivery` must exactly equal the resolved definition's `delivery`;
runtime override or downgrade is invalid. Authentication success/invalid
definitions always set `durable`. A different conditional delivery requires a
new event definition `version` and `evidenceVersion`; the offline verifier
resolves delivery from the catalog and checks the same equality.
For a conditional event family, a minimum catalog may register the same
`event.type` with `(version:1,evidenceVersion:1,delivery:durable)` and
`(version:2,evidenceVersion:2,delivery:required)`; these are separate
selectors, not a mutable runtime mode. Authentication success and invalid
families have only their durable definition.
Evidence values are flat primitives only and must fit the declared field
type, nullability, and byte caps; objects, arrays, nested/list fields,
floats, non-finite values, and unknown fields are rejected. The record's
`evidence` object has exactly `version` plus the registered field names for
the selector `(catalogHash,event.type,event.version,evidence.version)`, and
`evidence.version` must equal that definition's `evidenceVersion`.
`maxEvidenceBytes` is measured as the UTF-8 byte length of `JCS(evidence)`,
including the `version` property, not as a post-redaction or pretty-printed
byte count.

The catalog is RFC 8785 JCS encoded with duplicate keys, non-finite/floating
values, integers outside the exact JSON-safe range, invalid Unicode, and
unknown properties rejected. The stored `catalogHash` is excluded from the
unsigned catalog body and is calculated without circularity:

```text
catalogHash = HEX_LOWER(SHA-256(
  ENC_STRING("blackops.audit.catalog.v1") ||
  ENC_BYTES(JCS(unsignedAuditEventCatalogBody))
))
```

`ENC_STRING` and `ENC_BYTES` are the exact typed length encodings in D147.

`catalogHash` is exactly 64 lowercase hexadecimal characters. The immutable
identity selector is `(catalogHash,event.type,event.version,evidence.version)`;
within one catalog, `(type,version,evidenceVersion)` is unique. The same
selector with the same catalog bytes and definition is idempotent; the same
selector with a different schema, or a duplicate `(type,version,evidenceVersion)`
definition in one catalog, fails closed. A catalog file's semantic
`catalogHash` is never treated as its raw file digest.

P23-003A1 fixtures resolve two distinct catalog hashes in memory and validate
each definition only against its own catalog. P23-003F/G later own the positive
export with one resolved `event_catalog` file for each referenced hash and the
missing, extra, unreferenced, duplicate, unresolved, role, path, and artifact
file negatives. A same-selector
redefinition, an `evidence.version`/`evidenceVersion` mismatch,
semantic-tuple mismatch, reserved `version` evidence field, category or
action mismatch, owner mismatch, missing/omitted/unknown/mismatched
`policy.auditDelivery`, delivery override, control character, and over-cap
string are negative fixtures. A registered control definition with
`evidenceVersion:1`, `evidence.version:1`, matching category/action/owner, and
catalog-selected delivery is a positive fixture.

Credentials, tokens, raw payloads, request bodies, throwable/exception data,
ciphertext, nonces, authentication tags, and key material are prohibited
anywhere in the canonical body, including nested objects, not merely in
`evidence` or a derived projection. A registered control event with
`evidence={version,target,affectedCount}` is a positive fixture; an unknown
evidence member, credential/token/raw-payload/throwable/ciphertext/key
fixture is rejected by the typed source mapper. A grammar-valid string that
happens to contain a secret-shaped value can pass schema and verifier checks;
automatic semantic secret detection, secret absence, and compliance are
explicit nonclaims. Application mapper/review owns that semantic boundary.

`correlation.operationId`, `correlation.correlationId`, and
`correlation.causationId` are optional normalized safe strings of at most 256
UTF-8 bytes or `null`; `correlation.traceId` is optional exactly 32 lowercase
hexadecimal characters and `correlation.spanId` is optional exactly 16. They
are never identity or integrity proof.
`tenant: null` is an explicit system/global subject, not an empty tenant: its
chain uses `partitionId: "system:global"` and a non-empty `segmentId`. A tenant
object is closed `{type,id}` with normalized NFC UTF-8 non-empty strings of at
most 256 bytes and no control characters. Its exact partition mapping is:

```text
partitionId = "tenant:" || HEX_LOWER(
  SHA-256(ENC("blackops.audit.partition.tenant.v1") ||
    ENC(JCS({"type": type, "id": id})))
)
```

The writer and verifier use this same formula, and `integrity.partitionId`
must equal it exactly. Empty/untyped values, separator-collision encodings,
Unicode-normalization variants, same-tenant/different-partition results,
different-tenant/same-partition results, and any collision with
`system:global` fail closed.

The current `HttpAuthenticator` interface has no module or method identifier;
the Framework must not invent one. A typed authenticator/provider provenance
field is optional only when the provider explicitly supplies it, and its type
is owned by P23-003A. Credentials, tokens, request bodies, throwable details,
and protected payloads are never evidence. `principal: null` invalid/unknown/
unauthenticated, authenticated success, authorization allow/deny, privileged
read allow/deny, absent-ID, and anonymous no-event are separate fixtures.

Each record validates its phase independently against its catalog semantic
tuple. `action.id` is a correlation value only; v1 does not assert phase
continuity, lifecycle transitions, or cross-record order. A future versioned
transition contract may add those rules. The same `recordId` and the same
`contentHash` is an idempotent duplicate. The same ID with a different content
hash is an integrity failure that stops settlement of that row and produces a
poison operational outcome. It does not install a persistent partition halt;
D153 terminal FIFO semantics release the next ordinal.

The following is a historical illustrative projection, not the normative D147
`observed_v1` closed wire, not the canonical encrypted body, and not a
compliance certificate:

```json
{
  "schemaVersion": 1,
  "kind": "audit",
  "recordId": "audit-record-opaque",
  "event": {"type": "retention", "version": 1, "category": "control", "catalogHash": "9999999999999999999999999999999999999999999999999999999999999999"},
  "action": {"id": "action-opaque", "type": "retention.hold.placed", "phase": "committed"},
  "actor": {"principal": {"type": "operator", "id": "[masked]"}, "execution": {"type": "system", "id": "[masked]"}},
  "resource": {"type": "operation", "id": "[masked]"},
  "outcome": {"status": "succeeded", "code": "hold_placed"},
  "policy": {"id": "retention", "version": "retention-policy-v1", "decision": "allow", "reasonCode": "legal_hold", "auditDelivery": "durable"},
  "tenant": {"type": "account", "id": "[masked]"},
  "time": {"occurredAt": "2026-08-19T00:00:00.123456Z", "recordedAt": "2026-08-19T00:00:00.234567Z"},
  "source": {"owner": "framework", "component": "retention", "runtime": "cli", "buildId": "build-opaque"},
  "correlation": {"operationId": "operation-opaque", "correlationId": "correlation-opaque", "causationId": null, "attempt": {"number": 1, "kind": "initial"}, "traceId": "0123456789abcdef0123456789abcdef", "spanId": "0123456789abcdef", "sampled": true},
  "evidence": {"version": 1, "target": "journal", "affectedCount": 3},
  "integrity": {"partitionId": "tenant:[masked]", "segmentId": "segment-opaque", "sequence": 42, "previousRecordHash": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa", "contentHash": "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb", "recordHash": "cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc", "hashSuite": "audit-sha256-jcs-v1"}
}
```

Raw secrets, credentials, payloads, request bodies, exception objects,
throwable messages/stacks, ciphertext, nonce, tag, key material, and arbitrary
application objects are prohibited from `evidence` and derived projections.

### 9.4 Protection and tenant boundary

The canonical body is protected with a dedicated BOPD storage purpose:

```text
AuditRecord
```

`protectedBody` is non-empty binary BOPD v1 in the exact Specification 99
layout: ASCII magic `BOPD`, envelope version byte `1`, algorithm ID byte `1`,
two-byte big-endian key-ID length, exact key ID bytes, 24-byte nonce, four-byte
big-endian ciphertext length, ciphertext, and 16-byte authentication tag.
Purpose and all associated data are external context, never a stored BOPD
object member. The plaintext for canonical storage is exactly
`RFC8785-JCS(complete AuditRecord including integrity) || 0x0A`.

P23-003B owns this canonical protection. Authorization and the expected
partition/tenant predicate are established before key selection or decrypt.
Its custom domain-separated length-prefixed AAD binds envelopeVersion `1`,
algorithmId `1`, the exact keyId, purpose `AuditRecord`, recordId,
partitionId, segmentId, canonical decimal sequence,
previousRecordHash/`null`, contentHash, recordHash, and the closed tenant
union (presence marker plus, when present, normalized type and id) together
with the derived partition. After decrypt, the complete record and every
recomputed hash must equal these clear/AAD bindings. Malformed header,
unknown key ID, cross-row, cross-tenant, tenant-union, partition, and header
substitution fixtures fail closed before plaintext is exposed. The canonical
AAD is byte-exact: every member is an unsigned U32BE length-prefixed exact
byte string, concatenated as `blackops.audit.record.aad.v1`,
`AuditStoredRecord`, ASCII `1`, envelope version `1`, algorithm `1`, the exact
safe ASCII Spec 99 key ID, recordId, partitionId, tenant union, segmentId,
canonical decimal sequence, previous-hash union, decoded 32-byte
contentHash, decoded 32-byte recordHash, exact UTC six-fraction recordedAt,
and the exact eventCategory enum. A null tenant union is `0x00`, otherwise
`0x01 || LP(type) || LP(id)`; a null previous-hash union is `0x00`, otherwise
`0x01 || decoded32(hash)`. JSON/null stringification is not an encoding.
System/genesis and tenant/non-genesis AAD hex goldens and one-field
substitution fixtures are required.

The exact canonical AAD preimage is the following byte concatenation; no JSON
serialization or textual null is permitted:

```text
LP(x) = U32BE(byte_length(x)) || x
tenantUnionBytes = 0x00 | (0x01 || LP(UTF8(type)) || LP(UTF8(id)))
previousHashUnionBytes = 0x00 | (0x01 || DECODE32(previousHash))
AAD = LP(UTF8("blackops.audit.record.aad.v1")) ||
  LP(UTF8("AuditStoredRecord")) || LP(ASCII("1")) || LP(ASCII("1")) ||
  LP(ASCII("1")) || LP(UTF8(keyId)) || LP(UTF8(recordId)) ||
  LP(UTF8(partitionId)) || LP(tenantUnionBytes) || LP(UTF8(segmentId)) ||
  LP(ASCII(canonicalDecimal(sequence))) || LP(previousHashUnionBytes) ||
  LP(DECODE32(contentHash)) || LP(DECODE32(recordHash)) ||
  LP(ASCII(recordedAt)) || LP(ASCII(eventCategory))
```

The first three ASCII `1` values are schemaVersion, envelopeVersion, and
algorithmId. Two complete golden inputs are required: system/genesis with
null tenant and previous hash, and tenant/non-genesis with both unions
present; implementation tests must record their complete AAD bytes/hash and
each individual-field substitution negative.

The proposal pins these two golden outputs from the exact three-record logical
fixture in D148. PHP reaches them through Draft, Finalizer, and RecordCodec;
the tenant non-genesis previous hash is the actual record hash of the same-
partition tenant genesis fixture. Direct constructor injection of arbitrary
hashes is not a positive path. The former 232／344-byte shorthand and the
intermediate repeated-hash 309／421-byte vectors are invalid B1 positives.

| Golden | AAD byte length | SHA-256 |
| --- | ---: | --- |
| system/genesis (`audit-key-1`,`01941f29-7c00-7000-8000-000000000001`,`system:global`, null tenant, `01941f29-7c00-7000-8000-000000000101`, sequence `1`, null previous hash, content `41b8f7df71f09eb50e78a6f4259b5ee0147efb192ae40c9fe13f1a4526b32441`, record `a4f7fd2ac8a282deb00bd0221edd2f7c14aa559de97db4b83e5a9609227459b0`, `2026-08-19T00:00:00.000000Z`, `access`) | 308 | `c917e26a1a4d124db7701ceb76fc141581a54ce248e8df3783fc5e8500a4cade` |
| tenant/non-genesis (`audit-key-2`,`01941f29-7c00-7000-8000-000000000003`,`tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1`, tenant `{type:account,id:tenant-a}`, `01941f29-7c00-7000-8000-000000000102`, sequence `2`, previous `53f065f0fcd332fb2dc80e6bb2c23ed9d1ee127531550bb4e0a459d09544935e`, content `034a0ef2dc757517a1045660072835fbacfbfc9d77781be9e1db3b168ecf4186`, record `90b6a61cdd0bc6eae188fe7f79f21656cea5a97e49d8b5e6f2656a7a1c99a5cd`, `2026-08-19T00:00:01.000000Z`, `access`) | 421 | `0ab1465ae6e1e908228cafd36cf13fa55a805d4189bbd096d8df885a47452c48` |

The complete continuous AAD bytes, including every U32BE length prefix, are:

```text
system/genesis hex (308 bytes):
0000001c626c61636b6f70732e61756469742e7265636f72642e6161642e763100000011417564697453746f7265645265636f72640000000131000000013100000001310000000b61756469742d6b65792d310000002430313934316632392d376330302d373030302d383030302d3030303030303030303030310000000d73797374656d3a676c6f62616c00000001000000002430313934316632392d376330302d373030302d383030302d303030303030303030313031000000013100000001000000002041b8f7df71f09eb50e78a6f4259b5ee0147efb192ae40c9fe13f1a4526b3244100000020a4f7fd2ac8a282deb00bd0221edd2f7c14aa559de97db4b83e5a9609227459b00000001b323032362d30382d31395430303a30303a30302e3030303030305a00000006616363657373

tenant/non-genesis hex (421 bytes):
0000001c626c61636b6f70732e61756469742e7265636f72642e6161642e763100000011417564697453746f7265645265636f72640000000131000000013100000001310000000b61756469742d6b65792d320000002430313934316632392d376330302d373030302d383030302d3030303030303030303030330000004774656e616e743a353966353862666362343736666363393866653565393563366266383161356638313235613732316632376535336564373062613733343561653634656165310000001801000000076163636f756e740000000874656e616e742d610000002430313934316632392d376330302d373030302d383030302d3030303030303030303130320000000132000000210153f065f0fcd332fb2dc80e6bb2c23ed9d1ee127531550bb4e0a459d09544935e00000020034a0ef2dc757517a1045660072835fbacfbfc9d77781be9e1db3b168ecf41860000002090b6a61cdd0bc6eae188fe7f79f21656cea5a97e49d8b5e6f2656a7a1c99a5cd0000001b323032362d30382d31395430303a30303a30312e3030303030305a00000006616363657373
```

The displayed `\n` after each line is documentation line separation, not an
AAD byte; each golden is one continuous hex string. A read-only independent
recomputation must reproduce both lengths and hashes exactly.

Clear storage contains only bounded indexes needed for an authorized query,
partition ordering, and chain verification. A blind index is permitted only
when an explicit query contract requires it. Authorization and the tenant
predicate run before decrypt. A cross-tenant denial is indistinguishable from
an unknown record. Tenant/type/id mismatch is an integrity failure; the
reader never decrypts a mismatched body.

The default query is deny. Every query is tenant-scoped, bounded by explicit
time/sequence/page limits, and authorized for the requested purpose. Read,
export, signing, writing, and retention roles are separate.

### 9.5 Delivery modes

| Mode | Required behavior | Allowed failure |
| --- | --- | --- |
| `required` | append and business mutation commit in one DB transaction | append/transaction failure fails the privileged mutation closed |
| `durable` | local Audit Outbox identity/bindings/protected envelope are immutable in the mutation transaction; only fenced state-machine fields may change during at-least-once relay | relay may retry; duplicate same contentHash is harmless |
| observed JSONL projection | separate non-canonical surface with no Audit Trail claim | projection loss does not alter the canonical mutation |

For an authentication or protected-read decision with no business mutation,
the dedicated local Audit append/outbox transaction must commit before the
response or protected-data disclosure. If an allow event cannot append, the
response is withheld and the operation fails closed without data disclosure.
If a deny or invalid event cannot append, the result remains a safe bounded
denial and never becomes an allow. Response ordering and an audit-outage
fixture are required. Required events are never silently downgraded.

No cross-database, external, exactly-once, or atomic external delivery claim
is allowed. Required events that cannot append are not silently downgraded to
durable or an observed projection.

### 9.6 Chain, sealing, and verification

Records form a chain per tenant-or-system partition and concrete segment. A
tenant record uses a tenant-scoped partition; `tenant: null` always uses the
explicit `system:global` partition and never an empty tenant. The exact
`integrity` object has only these fields:

P23-003B owns exactly one active segment per partition and the atomic rotate
under the same partition fence and database transaction. A segment state is
exactly `open|closed|sealed`; append is allowed only to the current `open`
segment. Close CAS freezes its exact `finalSequence` and `headHash`, then
atomically installs the next `open` segment before releasing the fence. A
stale segment or fence writer must reload and never append to `closed` or
`sealed`. P23-003F consumes only that frozen `closed` segment, persists one
deterministic checkpoint, and fenced-CAS marks it `sealed`. A crash before
rotate commit changes nothing; after rotate commit before checkpoint resumes
the same closed boundary; after checkpoint before the seal marker reuses the
same checkpoint ID, bytes, and hash. Partition sequence continuity is global,
the new segment predecessor equals the closed head, and exactly one segment is
open. Fixtures cover concurrent append/rotate, stale-fence reload, each crash
point, no-gap/no-fork continuity, and duplicate checkpoint recovery.

| Field | Type and invariant |
| --- | --- |
| `hashSuite` | enum exactly `audit-sha256-jcs-v1` |
| `partitionId` | non-empty canonical tenant or `system:global` partition identifier |
| `segmentId` | non-empty concrete segment identifier; `audit-control-v1` is a stream/family name, not a permanent segment |
| `sequence` | positive integer; starts at `1` only at partition genesis and increments by exactly `1` across every segment rotation |
| `previousRecordHash` | 32 zero bytes in the first-record preimage and represented as `null` only at partition genesis; otherwise exactly 64 lowercase hexadecimal characters, including the first record of a rotated segment |
| `contentHash` | exactly 64 lowercase hexadecimal characters |
| `recordHash` | exactly 64 lowercase hexadecimal characters |

The exact closed `AuditSegmentState v1` object is
`{schemaVersion,kind,partitionId,segmentId,state,firstSequence,finalSequence,
headHash,predecessorRecordHash,nextSegmentId,version,closeFencingToken}`.
`schemaVersion=1`, `kind=audit_segment_state`, state is exactly
`open|closed|sealed`, IDs are bounded normalized safe strings, sequences and
version are positive JSON-safe integers, and hashes are 64 lowercase hex or
null only at the defined boundary. In `open`, `finalSequence` and `headHash`
may both be null only for an empty segment; `predecessorRecordHash` is null
only for partition genesis, and `nextSegmentId`/`closeFencingToken` are null.
`closed` requires non-empty finalSequence/headHash, sets nextSegmentId and a
non-null closeFencingToken atomically, and freezes them; `sealed` preserves
that exact closed boundary/token. `version` is a positive CAS version. A
non-genesis new open has `firstSequence=old.finalSequence+1` and
`predecessorRecordHash=old.headHash`; empty close is rejected. Exactly one
open segment exists per partition. P23-003B owns the state/rotate fence and
P23-003F owns checkpoint/seal CAS; state/crash/concurrency fixtures cover
genesis, rotation, stale writers, and checkpoint replay.

`integrity` is excluded from the canonical logical body and is appended only
after hashing. Canonical logical bytes use RFC 8785 JSON Canonicalization
Scheme (JCS) over the Section 9.3 field set; JCS determines canonical object
member ordering and UTF-8 encoding. Duplicate keys, non-finite numbers,
floating-point values, integers outside the exact
JSON-safe range (greater than 53-bit magnitude), invalid Unicode, and any
non-JCS representation are rejected. The hash encoding is exact:

```text
ENC(string)  = "s:" + decimal_utf8_byte_length + ":" + UTF8(string)
ENC(integer) = "i:" + canonical_decimal_digits
ENC(null)    = "n:"
ENC(bytes)   = "b:" + decimal_byte_length + ":" + bytes
canonicalLogicalBytes = JCS(schemaVersion, kind, recordId, event, action,
  actor, resource, outcome, policy, tenant, time, source, correlation, evidence)
contentHash = H(ENC("blackops.audit.content.v1") || ENC(canonicalLogicalBytes))
recordHash = H(ENC("blackops.audit.record.v1") || ENC(partitionId) ||
  ENC(segmentId) || ENC(sequence) || ENC(previousRecordHashPreimage) ||
  ENC(contentHashBytes) || ENC("audit-sha256-jcs-v1"))
```

`H` is SHA-256 and every digest is lowercase hexadecimal. The partition-genesis
preimage sets `previousRecordHashPreimage` to exactly 32 zero bytes; otherwise
it is the 32 bytes obtained by decoding the 64 lowercase hexadecimal
`previousRecordHash`. `canonicalLogicalBytes` and `contentHashBytes` are byte
strings encoded with `ENC(bytes)`, while `contentHash` and `recordHash` are
their lowercase hexadecimal representations in the record. The record
representation uses `null` for the first-record `previousRecordHash` condition.
Sealing never updates an AuditRecord and never adds checkpoint or receipt
fields.

### 9.6.1 AuditCheckpoint v1 and AuditAnchorReceipt v1

An `AuditCheckpoint` is an independent immutable signed artifact, never an
AuditRecord mutation. The following is a redacted documentation projection:
its 64-hex test digests are shape-valid but its signature placeholder is not a
verified signature. The exact stored v1 shape is:

```json
{
  "schemaVersion": 1,
  "kind": "audit_checkpoint",
  "checkpointId": "checkpoint-opaque",
  "partitionId": "tenant:[masked]",
  "segmentId": "segment-opaque",
  "firstSequence": 1,
  "lastSequence": 42,
  "recordCount": 42,
  "firstRecordHash": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "chainHeadHash": "cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc",
  "checkpointHash": "dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd",
  "previousCheckpointHash": null,
  "sealedAt": "2026-08-19T00:00:00.345678Z",
  "signedAt": "2026-08-19T00:00:00.456789Z",
  "hashSuite": "audit-sha256-jcs-v1",
  "signatureAlgorithm": "ed25519-v1",
  "signingKeyRef": "checkpoint-key-opaque",
  "signature": "base64url-without-padding"
}
```

The checkpoint fields are required with these types: schemaVersion is exactly
`1`; kind is exactly `audit_checkpoint`; IDs and partition/segment/key
references are non-empty safe strings, `segmentId` and `checkpointId` are
store-wide unique, and sequence is unique and gap-free across the complete
partition history, not merely within a concrete segment;
`firstSequence` is positive and equals the partition-genesis sequence `1` only
for the first segment, or the prior segment's finalSequence plus `1`; `lastSequence`
and `recordCount` are positive integers with `recordCount = lastSequence -
firstSequence + 1`; `firstRecordHash`,
`chainHeadHash`, and `checkpointHash` are exactly 64 lowercase hex;
`chainHeadHash` is exactly the `recordHash` of the last covered sequence and
never a Merkle root; `sealedAt` and `signedAt` are UTC RFC3339 timestamps with
six fractional microseconds and `sealedAt <= signedAt`; `hashSuite` is exactly
`audit-sha256-jcs-v1`; `previousCheckpointHash` is `null` only for the
partition genesis checkpoint and otherwise exactly the previous concrete
segment's checkpoint hash; `signatureAlgorithm` is exactly `ed25519-v1`; and
`signature` is RFC 4648 base64url without padding decoding to exactly 64 bytes.

There is exactly one checkpoint for each complete sealed concrete segment.
The partition graph is global: concrete segments have unique IDs, ordered
sequences start at 1, have no gap/fork/overlap, and each non-genesis
checkpoint points to the immediately previous segment checkpoint. The
checkpoint covers every record in its segment, not a selected subset.

The unsigned checkpoint body contains every stored field except the derived
`checkpointHash` and the entire stored property named `signature`, including
`signedAt`. JCS canonicalization
uses RFC 8785; duplicate keys, non-finite numbers, floats, >53-bit integers,
invalid Unicode, and non-JCS input are rejected. The exact preimages are:

```text
checkpointHash = H(ENC("blackops.audit.checkpoint.hash.v1") ||
  ENC(JCS(unsignedCheckpointBody)))
signature = Ed25519.sign(ENC("blackops.audit.checkpoint.signature.v1") ||
  ENC(checkpointHashBytes), checkpointSigningKey)
```

`checkpointHash` is the 64-lowercase-hex representation of the digest and
`checkpointHashBytes` is its decoded 32-byte value. The unsigned body excludes
`checkpointHash` and the entire stored property named `signature`; an unsigned
checkpoint is never canonical.
Pure RFC 8032 Ed25519 is required. A signer failure leaves the segment in
stored `closed` state (an operational projection may display `closed_unsealed`). Checkpoint keys, anchor keys, and export
keys are separate roles; a bundle key is never an authority. The pinned
offline trust store has exact entries
`{keyRef:string, role:checkpoint|anchor|export, publicKey:base64url(32 bytes),
publicKeyFingerprint:64-lowercase-hex, notBefore:UTC, notAfter:UTC,
revokedAt:UTC|null, status:active|revoked|expired}`; the fingerprint is
`SHA-256(decoded publicKey)` and is unique across the complete snapshot. The
same public-key material cannot appear under another key reference or role;
revocation applies to the material, not only an alias.
`keyRef` is unique and `notBefore < notAfter`. Verification requires trusted
verification time, an `active` status, the correct role, and
`notBefore <= signedAt <= verifiedAt < notAfter`. v1 rejects an expired or revoked
checkpoint/anchor key regardless of a self-declared `signedAt`; artifact time
alone cannot establish historical acceptance. A future separately versioned
contract may add an independent trusted timestamp or anchor-time proof, but a
current anchor receipt cannot prove its own anchor-key validity time.
Unknown/role-mismatched keys fail.

P23-003F owns checkpoint/anchor signing and the reusable record/checkpoint/
anchor/freshness verification primitives. Ordinary signatures require the
durable `audit.access.sign.accessed` event before release; append failure denies
the signature. The sole finite exemption is framework-derived exact target/role
`system:global/audit-control-v1`, whose signed checkpoint/receipt is
authoritative and emits no sign-accessed event; callers cannot select it.
P23-003G owns manifest/pin, query/export, and hosted-verification orchestration
and the corresponding `accessed` events before response/result release.

Checkpoint persistence is crash-safe: the writer persists the complete
checkpoint bytes before acknowledging the seal. The same `checkpointId` with
the same complete bytes and `checkpointHash` is an idempotent success; the
same ID with different bytes/hash, or a second checkpoint for the same segment,
is an integrity failure. Crash-before-ack and replay fixtures are required.

An independent immutable `AuditAnchorReceipt v1` binds an external anchor to
the complete signed checkpoint. Its exact stored fields are
`schemaVersion: 1`, `kind: "audit_anchor_receipt"`, `anchorReceiptId`,
`partitionId`, `segmentId`, `checkpointId`, `checkpointHash`,
`checkpointBytesHash`, `anchorReceiptHash`, `anchorStoreRef`,
`anchorObjectRef`, positive `anchorObjectVersion`,
`previousAnchorReceiptHash`, `anchoredAt`, `signedAt`,
`signatureAlgorithm: "ed25519-v1"`, `signingKeyRef`, and a base64url-no-padding
`signature`. IDs/hashes are store-wide unique where applicable; same ID plus
same hash is idempotent and same ID plus different hash is an integrity
failure. `checkpointBytesHash` is exactly the domain-separated SHA-256 of the
complete checkpoint JCS bytes without the export-file LF:

```text
checkpointBytesHash = H(ENC("blackops.audit.anchor.checkpoint-bytes.v1") ||
  ENC(JCS(completeCheckpointIncludingSignature)))
```

`anchorReceiptHash` excludes only the entire stored properties named
`anchorReceiptHash` and `signature` from its unsigned JCS body, and the exact
derived hash and signature preimage are:

```text
anchorReceiptHash = H(ENC("blackops.audit.anchor.hash.v1") ||
  ENC(JCS(unsignedAnchorReceiptBody)))
signature = Ed25519.sign(ENC("blackops.audit.anchor.signature.v1") ||
  ENC(anchorReceiptHashBytes), anchorSigningKey)
```

`anchorReceiptHashBytes` is the decoded 32-byte value of the 64-lowercase-hex
`anchorReceiptHash`. `signature` decodes to 64 bytes and its pinned Ed25519
public key is 32 bytes. `anchorObjectVersion` is positive, starts exactly at
`1`, and increments by exactly `+1` within the exact identity triple
`{partitionId, anchorStoreRef, anchorObjectRef}`; two stores with the same
object reference are separate chains. `previousAnchorReceiptHash` is null only
at that chain's genesis. `anchoredAt`
is external-store acceptance time and `signedAt` is receipt signing time;
both are UTC microseconds, and the ordering is
`checkpoint.signedAt <= anchoredAt <= receipt.signedAt`; reverse times reject.
Key validity uses the receipt `signedAt`. A URL, ETag, or
WORM assertion alone is not an offline-verifiable anchor. The verifier binds
receipt checkpoint ID/hash, complete-byte digest, signature, role, validity,
and revocation; receipt absence or mismatch is
`checkpoint_or_anchor_invalid`. No record or checkpoint is mutated to add a
receipt.

`AuditVerificationContext.freshnessSnapshot` is structurally nullable for both
invocation modes. Stage 0 validates only property presence/type and, when
non-null, its closed shape, caps, semantic hash, timestamp grammar,
head member shapes/counts, and internal `observedAt < validUntil`; it does not compare
mode, manifest coverage, or `verifiedAt` freshness. After Stage 5 signature
and actual file-digest validation, Stage 6 applies the finite precedence
authenticated catalog or
authenticated record-chain graph/hash/semantic mismatch code `14
authenticated_graph_invalid`, mode/coverage mismatch code `16
mode_coverage_invalid`, then freshness boundary mismatch code `18
freshness_boundary_unavailable`. Every global Stage 6 failure keeps
post-signature references non-null, returns `partitions:[]`, and never reopens
listed files. Stage 7 compares each end receipt to its authenticated head and
chronology and returns local `15` for checkpoint/anchor trust/chronology or
local `18` for end-head freshness.
Thus current+null is `18`, as-of+valid-non-null is `16`, and current+expired
is `18`; the open-count and top-reference fixtures fix these outcomes. An
as-of invocation still verifies the complete authenticated included graph and
endpoint snapshot, but never claims currentness or suffix completeness.

The machine result is a closed `AuditVerificationResult v1` object with exact
top-level properties
`{schemaVersion,kind,mode,status,code,scope,exportId,manifestHash,verifiedAt,
contextHash,partitions}`. Every field remains present. Before a valid
invocation mode, `mode` and `scope` are `null`; an invalid or missing mode
returns `status:failed`, code `19`, all four identifiers (`exportId`,
`manifestHash`, `verifiedAt`, `contextHash`) null, and `partitions:[]`.
For a valid mode, `scope` is derived immediately and is non-null. Invalid
external context retains that mode and scope, but leaves `verifiedAt` and
`contextHash` null. Once context is accepted, those two values are non-null.
`exportId` and `manifestHash` remain null until Stage 4 signature
authentication succeeds; after Stage 4 they remain non-null even when a
later global stage fails. `partitions` contains
every parsed manifest partition exactly once, sorted by canonical unsigned-byte
lexical UTF-8 `partitionId` comparison with no locale; each
closed entry has exactly `partitionId`, `status`, `code`, `prefixComplete`,
`suffixComplete`, `startCheckpoint`, `endCheckpoint`, `startReceipt`, and
`endReceipt`. Receipt references include `anchorStoreRef`.
`mode` is `null` only before a valid invocation mode and otherwise exactly
`current|as_of_checkpoint`; `status` is exactly `verified|failed`;
`code=0` is allowed only with `status: verified`, and every
non-zero code has `status: failed`. `scope` is `null` before a valid
invocation mode and otherwise exactly `current_suffix|checkpoint_snapshot`;
`current` uses `current_suffix` and requires the trusted freshness snapshot for
every partition, while `as_of_checkpoint` uses the distinct
`checkpoint_snapshot` scope and never claims currentness or suffix
completeness. A successful `prefixComplete` equals the authenticated
start-boundary `prefixComplete` (true only for genesis). A successful current
entry has `suffixComplete:true` only after its Stage 7 head match; a successful
as-of entry always has `suffixComplete:false`. Every local failure has both
flags false. An entry with code `0`/`verified` has all four references non-null
and computed flags; a partition-local failed entry has non-zero code, both
flags false, and all four references non-null. Overall `verified`/code `0` requires every
entry to verify. Overall failure code is the first non-zero entry in sorted
partition order; if parsing fails before safe partition inventory,
`partitions:[]` and the manifest-level code are returned. One code `18`
therefore fails the overall current result even if other entries verify.

Every emitted partition entry, including a partition-local failure, has the
four non-null closed references: checkpoint references are
`{partitionId,checkpointId,checkpointHash,segmentId,firstSequence,lastSequence}`
and receipt references are
`{partitionId,anchorReceiptId,anchorReceiptHash,anchorStoreRef,anchorObjectRef,anchorObjectVersion}`.
Successful entries require both start and end references and their chain/range
agreement. A current entry without its trusted freshness snapshot returns
`18 freshness_boundary_unavailable`; an as-of entry may succeed without it.
The result exposes no payload, secret, exception, or path detail. A
self-contained bundle without freshness may only return
`verified` with `mode: as_of_checkpoint`, never a current/suffix claim.
Required result fixtures include two sorted valid partitions, one invalid
partition with the deterministic first non-zero overall code, one current
partition with code `18` while another entry remains individually verified,
malformed input before safe partition inventory returning `partitions:[]`, a
missing/mismatched/whole-context-substitution input returning code `19` with
all result references null, a context-valid but unauthenticated manifest with
only `verifiedAt`/`contextHash` non-null, and a manifest-substitution attempt
against the same result endpoint. Global validation precedence is the single
fixed Stage 0-7 table: Stage 0 validates invocation mode plus the standalone
bounded external context and expected-context hash (code `19`, zero
manifest/listed-file opens, null top references, `partitions:[]`); Stage 1
validates malformed raw bytes/JCS/closed shape/bounds as code `10` before
mapping a syntactically valid unsupported version/kind to `17`, and reads
declared lengths only; Stage 2 recomputes the manifest self-hash, then
constant-time compares signed `exportId`/`manifestHash` with pinned
`expectedManifest` (mismatch code `16`, zero listed-file opens, refs null); Stage 3 validates the
trusted export key; Stage 4 verifies Ed25519 with zero listed-file opens; Stage
5, only after signature success, opens root-confined listed files and checks
actual lengths/sums/raw digests (code `13`); Stage 6 validates the
authenticated catalog and record-chain graph/hash/semantic graph first (code
`14 authenticated_graph_invalid`), then invocation mode/coverage (code `16
mode_coverage_invalid`), then freshness boundary (code `18
freshness_boundary_unavailable`). These global failures keep post-signature
references non-null, return `partitions:[]`, and never reopen listed files.
Stage 7 validates only local checkpoint/anchor trust/chronology and each
end-receipt-to-head comparison. A per-partition head mismatch
is local code `18` with the authenticated refs. Stage 0 must not compare
freshness heads to manifest partitions and performs zero manifest reads. Global stages
0-6 return `partitions:[]`; only Stage 7 emits entries. Context-hash plus
bad-manifest returns `19`; extra head plus invalid signature returns `11` with
zero opens; extra head plus valid signature returns `18` with empty
partitions; and an invalid current/as-of context shape returns `19`. No
reverse or alternate order is valid.

Stage 3 never trusts an unverified manifest `signedAt`: it resolves only the
export key reference/role and the active trust snapshot at `verifiedAt`.
Unknown, wrong-role, revoked, expired, or not-yet-valid export keys are global
code `12`. Stage 4 first verifies Ed25519; a bad signature is global code `11`
with `exportId` and `manifestHash` null and zero listed-file opens. Only after
signature success do those two refs become non-null, and the authenticated
signed-time validity check may return code `12` without opening a file. A
checkpoint/anchor key, signature, role, or chronology failure found for an
authenticated partition is local code `15`, not an undefined local code `12`;
authenticated record-graph and mode/coverage failures are global Stage 6 codes
`14` and `16`, never Stage 7, while per-partition head freshness failures are
local `18`. A two-partition key-failure fixture covers global
export-key failure versus local checkpoint/anchor failure.

The top-level null/type matrix is exact:

| Invocation/stage | `mode`/`scope` | `exportId`/`manifestHash` | `verifiedAt`/`contextHash` | `partitions` |
| --- | --- | --- | --- | --- |
| missing/invalid mode | both `null` | both `null` | both `null` | `[]`, code `19` |
| valid mode, invalid context | non-null derived values | both `null` | both `null` | `[]`, code `19` |
| accepted context, Stage 1–3 failure | non-null | both `null` | both non-null | `[]` |
| Stage 4 signature failure | non-null | both `null` | both non-null | `[]`, code `11` |
| Stage 4 success, Stage 5/6 global failure | non-null | both non-null | both non-null | `[]` |
| Stage 7 result | non-null | both non-null | both non-null | one entry per authenticated partition |

Every row retains all top-level fields, `status` is `verified|failed`, and
`code=0` is valid only with `status:verified`; all non-zero codes are failed.

`AuditVerificationContext v1` is the trusted external context bound to every
result. Verifier invocation requires an external `expectedContextHash` (64
lowercase hex) from a trusted configuration/channel; it must never be read
from the bundle or an untrusted context copy. The verifier recomputes the
complete context hash and constant-time compares it with the stored hash, then
constant-time compares it with `expectedContextHash`, before using any trust
entry. Missing, mismatched, or whole-context-substituted input returns code
`19 verification_context_invalid`, `partitions:[]`, and null `exportId`,
`manifestHash`, `verifiedAt`, and `contextHash`.

Stage 2 first recomputes the parsed candidate manifest's self-hash and rejects
an invalid self-hash as code `10`. It then constant-time compares the parsed
candidate manifest's `exportId` and `manifestHash` with the context's required
`expectedManifest`; a mismatch is global code `16`, opens zero listed files,
leaves both export references null, and returns `partitions:[]`. Two otherwise
valid signed manifests under the same trust, freshness, and context inputs
are a required swap fixture; only the pinned one is accepted.

Its exact top-level properties are `schemaVersion:1`,
`kind:audit_verification_context`, `contextId`, `verifiedAt`,
`trustStoreSnapshot`, `freshnessSnapshot`, `expectedManifest`, and
`contextHash`. `expectedManifest` is required and closed as
`{exportId,manifestHash,purpose}`, with bounded IDs, a 64-lowercase-hex hash,
and purpose exactly `audit_export_verification`; it is included in the
contextHash preimage and is pinned by the external expectedContextHash. The trust-store
snapshot is exactly `{ref,hash,observedAt,validUntil,entries}`. Its `entries`
are sorted by unsigned UTF-8 `keyRef` and each entry is exactly
`{keyRef,role,publicKey,publicKeyFingerprint,notBefore,notAfter,revokedAt,status}`, with unique
`keyRef`, role `checkpoint|anchor|export`, base64url-unpadded 32-byte
`publicKey`, 64-lowercase-hex `publicKeyFingerprint`, UTC times, `revokedAt`
UTC or null, and status
`active|revoked|expired`. The trust snapshot semantic hash excludes only the
entire stored property named `hash` and is
`H(ENC("blackops.audit.trust-store-snapshot.v1") ||
ENC(JCS(unsignedTrustStoreSnapshot)))`.
The freshness snapshot is structurally nullable for either mode; when
non-null it is exactly `{ref,hash,observedAt,validUntil,heads}` with bounded
head entries whose individual shapes/hashes are valid. Stage 0 validates only
the array/type/count, closed member shapes, hashes, timestamp grammar, and
internal `observedAt < validUntil`; duplicate or unsorted heads are not a
Stage 0 failure. Each head is exactly
`{partitionId,anchorStoreRef,anchorObjectRef,anchorObjectVersion,anchorReceiptHash}`
with normalized member bounds. Stage 6 enforces exactly one head per manifest
partition, uniqueness, canonical unsigned-byte `partitionId` order, and exact
coverage, mapping violations to code `18 freshness_boundary_unavailable` after
the code `14` and `16` lanes.
Its semantic hash excludes only the entire stored property named `hash` and is
`H(ENC("blackops.audit.freshness-snapshot.v1") ||
ENC(JCS(unsignedFreshnessSnapshot)))`. The context hash excludes only the
entire stored property named `contextHash`:
`contextHash = H(ENC("blackops.audit.verification-context.v1") ||
ENC(JCS(unsignedContext)))`. Snapshot refs and IDs are normalized safe strings
bounded by the export caps; duplicate/order/coverage constraints are checked
only at Stage 6 as described above.
Every mode requires `trustStoreSnapshot.observedAt <= verifiedAt <
trustStoreSnapshot.validUntil` in Stage 0; violation is code `19` before any
manifest or listed-file open. Stage 0 does not compare freshness heads with
manifest coverage. A trust entry accepted for a signature has status `active`,
`revokedAt:null`, and `notBefore <= signedAt <= verifiedAt < notAfter`;
status `revoked` requires non-null `revokedAt <= observedAt` and always
rejects, while status `expired` requires `revokedAt:null` and
`notAfter <= verifiedAt` and rejects. Malformed or internally inconsistent
context, trust, or freshness snapshot shape/hash/time-order is Stage 0 code
`19`; export-key availability/role/status is Stage 3 global code `12`;
authenticated `signedAt` key-window failure is Stage 4 global code `12` with
non-null export refs; checkpoint/anchor key/signature/chronology is Stage 7
local code `15`. Missing/expired/stale current freshness is Stage 6/7 code
`18`; non-null as-of freshness is code `16`. Current null/expired snapshots are
code `18`; a valid non-null as-of snapshot is code `16`. Each current partition
end receipt must equal the trusted head's exact triple, version, and hash, with
`observedAt <= verifiedAt < validUntil`. A stale snapshot cannot claim
current or `suffixComplete`. Trust verification requires active/status and
revocation consistency, and rejects not-yet-valid, current-expired, revoked, or
future-signed artifacts.
The result top level therefore includes `verifiedAt` and `contextHash`, which
must equal this accepted context exactly. A context-valid but unauthenticated
manifest returns non-null `verifiedAt`/`contextHash` with null `exportId`/
`manifestHash` and `partitions:[]`; an authenticated manifest binds all four
non-null result identifiers to that exact manifest endpoint. Context hash or
snapshot substitution, duplicate heads, missing/extra heads, wrong sort,
stale heads, and cross-store head mismatches fail.

The context and both snapshots are closed objects. `contextId`, snapshot refs,
and head IDs are normalized non-empty safe UTF-8 strings of at most 256 bytes;
the context unsigned JCS is at most 1 MiB, the trust snapshot has 1..256
unique entries, and a freshness snapshot has exactly one head for each of the
1..256 manifest partitions. Head `anchorObjectVersion` is positive and every
hash is 64 lowercase hex. Public-key fingerprints are unique snapshot-wide;
same-material aliases, including cross-role aliases, are rejected and
revocation is material-level. The context and snapshot semantic hashes are
recomputed before use; duplicate/missing/extra heads, non-canonical sort,
wrong snapshot hash, context-hash substitution, missing current freshness, and
cross-store head substitutions fail closed.

The following result is a redacted documentation projection, not a canonical
validator fixture: every `tenant:[masked]` value intentionally hides the
canonical `system:global` or domain-hashed `tenant:` partition computed by the
formula above. A validator must reject the placeholder as a partition ID.

```json
{
  "schemaVersion": 1,
  "kind": "audit_verification_result",
  "mode": "current",
  "status": "verified",
  "code": 0,
  "scope": "current_suffix",
  "exportId": "export-opaque",
  "manifestHash": "ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff",
  "verifiedAt": "2026-08-19T23:59:59.567890Z",
  "contextHash": "eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee",
  "partitions": [{
    "partitionId": "tenant:[masked]",
    "status": "verified",
    "code": 0,
    "prefixComplete": true,
    "suffixComplete": true,
    "startCheckpoint": {
      "partitionId": "tenant:[masked]",
      "checkpointId": "checkpoint-start",
      "checkpointHash": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
      "segmentId": "segment-start",
      "firstSequence": 1,
      "lastSequence": 42
    },
    "endCheckpoint": {
      "partitionId": "tenant:[masked]",
      "checkpointId": "checkpoint-start",
      "checkpointHash": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
      "segmentId": "segment-start",
      "firstSequence": 1,
      "lastSequence": 42
    },
    "startReceipt": {
      "partitionId": "tenant:[masked]",
      "anchorReceiptId": "anchor-start",
      "anchorReceiptHash": "cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc",
      "anchorStoreRef": "anchor-store",
      "anchorObjectRef": "anchor-object",
      "anchorObjectVersion": 1
    },
    "endReceipt": {
      "partitionId": "tenant:[masked]",
      "anchorReceiptId": "anchor-start",
      "anchorReceiptHash": "cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc",
      "anchorStoreRef": "anchor-store",
      "anchorObjectRef": "anchor-object",
      "anchorObjectVersion": 1
    }
  }]
}
```

Hashing or AEAD alone is not tamper-proof storage and is not non-repudiation.
Signing keys are not stored in AuditRecord bodies. The writer is append-only;
read/export/sign/retention privileges are separated. Physical deletion,
sealed-byte/key destruction, and signed deletion manifests are not Audit v1
features; they are deferred to separately reviewed future `P23-003H` and are
not compliance evidence.

### 9.7 Retention, hold, export, and audit-of-audit

Audit categories have independent retention policies and legal holds. P23-003G
v1 owns the closed, metadata-only `AuditRetentionState v1` object with exactly
`{schemaVersion:1,kind:"audit_retention_state",stateId,target,policy,evaluator,
owner,evaluatedAt,policySnapshotRef,policySnapshotHash,policySnapshotVersion,
holdSnapshotRef,holdSnapshotHash,holdSnapshotVersion,evaluationFenceVersion,
result,reasonCode,state,version,previousStateHash,stateHash,updatedAt,
idempotencyKeyHash}`. Its state and result are exactly
`retained|eligible_for_future_deletion`; reason codes are
`retention_not_elapsed|active_hold|policy_unavailable|
retention_elapsed_no_active_hold`; no hold is embedded. `target` is the
closed `{partitionId,startSequence,endSequence,eventCategory|null}`; `policy`
is `null|{id,version,retentionUntil}`, with null only for
`retained/policy_unavailable` and otherwise byte-exactly equal to the available
authenticated policy snapshot's `policy`. The authenticated policy snapshot is
the closed `{kind:"policy"|"policy_unavailable",ref,version,target,policy,
capturedAt,evaluationFenceVersion}` wire; the unavailable tag has `policy:null`.
The authenticated hold snapshot is the closed
`{kind:"active_hold_set",ref,version,target,holds,capturedAt,
evaluationFenceVersion}` wire, and no active hold is `holds:[]`. Their exact
hashes are
`policySnapshotHash=H(ENC("blackops.audit.retention.policy-snapshot.v1")||
ENC(JCS(policySnapshot)))` and
`holdSnapshotHash=H(ENC("blackops.audit.retention.hold-snapshot.v1")||
ENC(JCS(holdSnapshot)))`; each JCS body binds the complete target, version,
evaluation fence, capturedAt, and full policy or active-hold set, excluding no
member of that unsigned snapshot body. The state policy/hold snapshot ref,
version, and hash fields equal the authenticated snapshot values exactly, and
`evaluationFenceVersion` equals the authenticated fence in both snapshots.
Member order is the order shown. IDs,
refs, and string members use the shared 1..256-byte NFC/no-control bounds;
snapshot versions and `evaluationFenceVersion` are positive JSON-safe
integers, and every new revision increments the evaluation fence exactly +1.
One trusted `evaluatedAt` is captured per evaluation and every referenced
snapshot `capturedAt` must be no later than it; future snapshot time or clock
rollback creates no revision.
IDs/reason/category fields use the
shared bounded NFC/no-control rules, sequences and versions are positive
JSON-safe integers with `startSequence <= endSequence`, times are trusted UTC
with six fractional microseconds, `previousStateHash` is null iff `version=1`
and otherwise equals the prior stateHash, and both stateHash/idempotencyKeyHash
are 64 lowercase hex. The exact hashes are
`stateHash=H(ENC("blackops.audit.retention-state.v1") ||
ENC(JCS(object excluding stateHash)))` and
`idempotencyKeyHash=H(ENC("blackops.audit.retention-idempotency.v1") ||
ENC(partitionId) || ENC(rawKey))`; rawKey is protected input, 1..256-byte
NFC/no-control text, and never a clear field. The tuple `(stateId,target)` is
the immutable identity. `evaluator` and `owner` are closed `{type,id}`
identities; snapshot refs are bounded NFC/no-control strings with lowercase
64-hex hashes; policy/hold snapshot versions and `evaluationFenceVersion` are
positive JSON-safe integers. The truth table is policy-unavailable ->
`retained/policy_unavailable`; available policy plus overlapping active hold ->
`retained/active_hold`; no hold with `evaluatedAt < retentionUntil` ->
`retained/retention_not_elapsed`; and no hold with
`evaluatedAt >= retentionUntil` ->
`eligible_for_future_deletion/retention_elapsed_no_active_hold`. The equality
boundary is eligible. `evaluatedAt` never decreases, and an eligible state may
return to retained only for a strictly newer policy extension, strictly newer
overlapping hold, or newly authenticated policy-unavailable snapshot whose
policy snapshot version is strictly greater than the prior version; same-version
available-to-unavailable is a fork and is rejected. Equal snapshots/time alone
cannot reverse it. Same snapshots plus later time can
only retain or move retained to eligible. Stale, decreased, gap, fork, or
clock-rollback input creates no revision. The complete state JCS is
<=64 KiB inclusive and cap+1 is rejected. P23-003G applies fenced CAS and
idempotency: same key and bytes are idempotent, while same key and different
bytes returns a conflict before mutation. Matching holds are stored in a
separate append-only `AuditHold v1` collection with `active|released` state.
It has exactly one closed revision object:
`{schemaVersion:1,kind:"audit_hold",holdId,target,reasonCode,state,version,`
`previousStateHash,stateHash,updatedAt,updatedBy,idempotencyKeyHash}`.
The immutable target is exactly
`{partitionId,startSequence,endSequence,eventCategory|null}`; state is exactly
`active|released` and only `active -> released` is valid. Hash continuity uses
`stateHash=H(ENC("blackops.audit.hold-state.v1") ||
ENC(JCS(object excluding stateHash)))` and
`idempotencyKeyHash=H(ENC("blackops.audit.hold-idempotency.v1") ||
ENC(holdId) || ENC(rawKey))`. Raw idempotency keys are 1..256-byte UTF-8
NFC/no-control values and are never disclosed or stored; `(holdId,target)` is
the immutable CAS identity, same-key same-body is idempotent, and same-key
different-body conflicts before mutation. `updatedBy` is `{type,id}` and
`updatedAt` is trusted UTC with six fractional digits. The complete JCS object
is capped at 64 KiB inclusive and cap+1 is rejected. Overlap, wildcard
`eventCategory:null`, and same-snapshot eligibility remain required;
multiple active holds overlap, `category:null` is a wildcard, and a record is
eligible only when retention is eligible and no active hold matches in the
same transaction snapshot. Releasing one hold never releases another.
Eligible never deletes bytes. Query visibility and sealed bytes/keys are
unchanged. P23-003H is future physical
deletion and not a P23-006 dependency; 1.3 claims metadata/eligibility only.
Cutoff/hold race, retry, release, tenant/category/cap, query/export, and
unchanged sealed-byte/key fixtures are required.

`AuditCheckpoint` is the authoritative seal evidence; no `segment.sealed`
AuditRecord is written inside the segment it seals. The distinct
`system:global/audit-control-v1` stream may receive framework
`audit.checkpoint.persisted` and `audit.anchor_receipt.persisted` control
events through P23-003D's `AuditControlEventAdapter`, consuming typed outcomes
from P23-003F and writing through P23-003B. P23-003F never directly appends
AuditRecords. The artifact is immutable and acknowledgement occurs only after
idempotent artifact-ID keyed control-event ingress. Failure leaves orchestration
pending/retry without artifact mutation. Delivery placement, signer failure,
duplicate delivery, and control-stream partition fixtures are required. The
checkpoint and anchor artifacts for `system:global/audit-control-v1` do not
emit further checkpoint/anchor control AuditRecords; their signed
checkpoint/receipt remain authoritative. A non-recursion fixture covers this
control stream boundary.

Audit categories have independent retention policies and legal holds. Queries
and exports are bounded. The following is a redacted documentation projection:
its 64-hex test digests are shape-valid but its signature placeholder is not a
verified artifact. `AuditExportManifest v1` is a separate signed artifact with
exactly this stored shape. Every signed segment entry carries one complete
authenticated `checkpoint` reference and one complete authenticated `receipt`
reference, not scalar IDs. The partition result keeps the four endpoint
fields: `startCheckpoint` and `startReceipt` are copied from the first
included segment, while `endCheckpoint` and `endReceipt` are copied from the
last included segment. A one-segment partition therefore has byte-identical
start/end references. A checkpoint reference is the closed object
`{partitionId,checkpointId,checkpointHash,segmentId,firstSequence,lastSequence}`;
a receipt reference is the closed object
`{partitionId,anchorReceiptId,anchorReceiptHash,anchorStoreRef,anchorObjectRef,anchorObjectVersion}`.
The manifest has no `as_of` property; invocation mode is external and an
unknown `as_of` member is rejected as an unknown manifest field. The manifest
count/hash preimage includes these complete segment objects. Stage 1 rejects
missing, extra, wrong-type, or malformed singular endpoint descriptors
globally. After signature authentication, Stage 7 derives result refs only
from these authenticated manifest objects and cross-checks artifact bodies; a
missing or malformed checkpoint/receipt body returns code `15` with the
non-null manifest refs, never invented values. One-segment same-endpoint,
multi-segment first/last-endpoint, missing/type-bad body, and endpoint
substitution fixtures are required.

```json
{
  "schemaVersion": 1,
  "kind": "audit_export_manifest",
  "exportId": "export-opaque",
  "purpose": "regulated_access_review",
  "filter": null,
  "selectionReason": "complete_segment_boundary",
  "coverageMode": "complete_sealed_segments",
  "segments": [{
    "partitionId": "tenant:[masked]",
    "segmentId": "segment-opaque",
    "firstSequence": 1,
    "lastSequence": 42,
    "recordCount": 42,
    "firstRecordHash": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
    "chainHeadHash": "cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc",
    "checkpointBytesHash": "eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee",
    "checkpoint": {
      "partitionId": "tenant:[masked]",
      "checkpointId": "checkpoint-start",
      "checkpointHash": "dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd",
      "segmentId": "segment-opaque",
      "firstSequence": 1,
      "lastSequence": 42
    },
    "receipt": {
      "partitionId": "tenant:[masked]",
      "anchorReceiptId": "anchor-start",
      "anchorReceiptHash": "ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff",
      "anchorStoreRef": "anchor-store-opaque",
      "anchorObjectRef": "anchor-object-opaque",
      "anchorObjectVersion": 1
    }
  }],
  "startBoundaries": [{
    "partitionId": "tenant:[masked]",
    "mode": "genesis",
    "anchorStoreRef": "anchor-store-opaque",
    "anchorObjectRef": "anchor-object-opaque",
    "previousCheckpointHash": null,
    "previousAnchorReceiptHash": null,
    "previousAnchorObjectVersion": null,
    "prefixComplete": true
  }],
  "recordCount": 42,
  "files": [{
    "role": "records",
    "path": "records/segment-opaque.jsonl",
    "artifactId": "segment-opaque",
    "partitionId": "tenant:[masked]",
    "segmentId": "segment-opaque",
    "recordCount": 42,
    "mediaType": "application/x-ndjson",
    "byteLength": 1234,
    "sha256": "1111111111111111111111111111111111111111111111111111111111111111"
  }, {
    "role": "checkpoint",
    "path": "checkpoints/checkpoint-start.json",
    "artifactId": "checkpoint-start",
    "partitionId": "tenant:[masked]",
    "segmentId": "segment-opaque",
    "recordCount": null,
    "mediaType": "application/json",
    "byteLength": 456,
    "sha256": "3333333333333333333333333333333333333333333333333333333333333333"
  }, {
    "role": "anchor_receipt",
    "path": "anchors/anchor-start.json",
    "artifactId": "anchor-start",
    "partitionId": "tenant:[masked]",
    "segmentId": "segment-opaque",
    "recordCount": null,
    "mediaType": "application/json",
    "byteLength": 321,
    "sha256": "4444444444444444444444444444444444444444444444444444444444444444"
  }, {
    "role": "event_catalog",
    "path": "catalog/event-9999999999999999999999999999999999999999999999999999999999999999.json",
    "artifactId": "9999999999999999999999999999999999999999999999999999999999999999",
    "partitionId": null,
    "segmentId": null,
    "recordCount": null,
    "mediaType": "application/json",
    "byteLength": 234,
    "sha256": "5555555555555555555555555555555555555555555555555555555555555555"
  }],
  "actor": {"principal": {"type": "operator", "id": "[masked]"}, "execution": {"type": "cli", "id": "[masked]"}},
  "time": {"exportedAt": "2026-08-19T23:59:59.123456Z"},
  "source": {"component": "audit-export", "runtime": "cli", "buildId": "build-opaque"},
  "signatureAlgorithm": "ed25519-v1",
  "signingKeyRef": "export-key-opaque",
  "signedAt": "2026-08-19T23:59:59.234567Z",
  "manifestHash": "2222222222222222222222222222222222222222222222222222222222222222",
  "signature": "base64url-without-padding"
}
```

The exact types are: schemaVersion `1`; kind `audit_export_manifest`; all IDs
and purpose safe non-empty store-wide-unique strings; `filter` is exactly
`null` for `complete_sealed_segments`; `selectionReason` is a required
non-empty normalized safe string; coverageMode is exactly
`complete_sealed_segments`; segments are
ordered by each partition's `previousCheckpointHash` chain (never opaque
`segmentId` lexical order) and each has a positive `firstSequence` (genesis 1,
otherwise prior finalSequence+1), `lastSequence`,
`recordCount = lastSequence - firstSequence + 1`, `firstRecordHash`, `chainHeadHash`,
`checkpointBytesHash`, and one complete `checkpoint` plus one complete
`receipt` object with the exact closed shapes above. A partition result derives
its start endpoint from the first segment and end endpoint from the last;
one-segment start/end values are byte-identical.
All hashes are 64 lowercase hex. The manifest's
`recordCount` is the sum of segment counts.

`startBoundaries` has exactly one object for each distinct partition and is a
closed union. Both modes have exactly the same eight keys:
`partitionId`, `mode`, `anchorStoreRef`, `anchorObjectRef`,
`previousCheckpointHash`, `previousAnchorReceiptHash`,
`previousAnchorObjectVersion`, and `prefixComplete`. The mode-specific values
are:

| `mode` | Required boundary values |
| --- | --- |
| `genesis` | non-empty `anchorStoreRef` and `anchorObjectRef` equal to the first included receipt, null predecessor hashes/version, and `prefixComplete:true`; the first receipt has version `1` and a null predecessor |
| `selected_range` | non-empty `anchorStoreRef` and `anchorObjectRef` equal to the first included receipt, 64-hex predecessor hashes, positive `previousAnchorObjectVersion`, and `prefixComplete:false`; the first receipt has version `previousAnchorObjectVersion + 1` and the declared predecessor hash |

The first included checkpoint and anchor receipt must match the declared
boundary. Genesis has null checkpoint/receipt predecessors, receipt version
`1`, and `prefixComplete:true`; it is the only mode that proves the export
starts at the partition genesis. A `selected_range` declares unresolved
predecessor hashes/version, has `prefixComplete:false`, and requires the
first receipt version `previousAnchorObjectVersion + 1`; it verifies only the
included checkpoint/receipt chain from the first included artifact onward. It
does not prove predecessor existence, continuity from that predecessor,
omitted-prefix retention, pre-range history, deletion legitimacy, legal-hold
status, or any physical deletion. In both modes `anchorStoreRef` and
`anchorObjectRef` equal the first included receipt's corresponding values. The
corresponding anchor chain is global per exact
`{partitionId,anchorStoreRef,anchorObjectRef}` triple: version starts exactly
at `1` and each next version is exactly previous plus `1`; its
`previousAnchorReceiptHash` must
equal the immediately previous receipt hash. Missing, skipped, forked, or
duplicate versions fail; a fresh head never waives a missing intermediate
receipt. Checkpoint predecessor continuity must agree with this anchor graph.
Within one partition's included or continued chain, every receipt must retain
the same `anchorStoreRef` and `anchorObjectRef` as the start boundary and use
the exact +1 object-version sequence. A store/object change requires a future
signed bridge/versioned migration contract and fails v1; a cross-store
mid-chain fixture is negative.

Physical deletion, sealed-byte/key destruction, signed deletion manifests, and
their boundary or legal-hold claims are not represented by AuditExportManifest
v1. Audit v1 retains sealed bytes and keys and makes no deletion or compliance
claim. They are deferred to separately reviewed future `P23-003H`; no
`deletionManifestId` or `deletionManifestHash` property is valid in this v1
contract. Genesis, valid selected-range, missing predecessor store/object/hash/
version, deleted-prefix non-claim, and opaque-lexical-order mismatch are
required fixtures. Missing start, fork, gap, overlap, or wrong chain order
fails closed.

Each file has role `records|checkpoint|anchor_receipt|event_catalog`, a unique
root-relative POSIX UTF-8 `path` with no absolute path, backslash, `.`, `..`,
or empty component, `artifactId`, applicable
partition/segment IDs, `recordCount` (the segment count for records and `null`
otherwise), non-empty mediaType, positive `byteLength`, and 64-lowercase-hex
`sha256`. For each distinct `catalogHash` referenced by a covered record,
exactly one `event_catalog` file is required; its `artifactId` is that
`catalogHash`, its partition/segment IDs and recordCount are `null`, and its
bytes are the catalog JCS object followed by exactly one LF. This example has
one distinct catalog and therefore one catalog file; a segment/range may mix
catalog hashes across builds or catalog versions, provided one file exists
for every distinct hash. Missing, extra, unreferenced, duplicate, or
unresolvable catalog files fail closed, while mixed catalog hashes are valid
when every record validates against its own resolved catalog and its
`evidence.version` equals that definition's `evidenceVersion`.
Every segment/checkpoint/receipt/catalog artifact and file occurs exactly once;
duplicate, extra, missing, unlisted, reassigned, or path-traversal entries
fail. Concrete segments continue one partition-global sequence; only the
partition-genesis segment starts at sequence 1. The manifest binds exact checkpoint, receipt, and event-catalog
artifacts by semantic hash and separately verifies their raw file digests.
Artifact identity is only the authenticated signed-manifest mapping
`{role,artifactId}` plus the exact raw digest; a basename or role prefix is not
an identity claim. Paths are sorted by unsigned path bytes. Empty components,
`.`, `..`, backslash, absolute paths, symlink/special entries, and pre-open
namespace races are rejected.

Before allocating buffers, opening files, or streaming records, the parser
must first bound the unsigned manifest body to at most 15 MiB and the complete
stored raw JCS plus LF to at most 16 MiB, then preflight strict v1 bounds and
non-empty invariants:
partitions are 1..256; segments are 1..4096 total
and at least one per partition; there is exactly one start boundary per
partition; distinct catalogs are 1..1024; files equal
`3 * segmentCount + distinctCatalogCount` (one records file, one checkpoint
file, and one anchor-receipt file per segment, plus one file per distinct
catalog) and are at most 13,312; each segment
has 1..100,000 records and total records are 1..10,000,000. Each file
`byteLength` is 1..1 GiB; overflow-safe unsigned JSON-safe byte/count sums are
checked before allocation, are at most 16 GiB, and must equal actual bytes
read. Each `AuditExportRecord` JCS line including its LF is at most 1 MiB
raw bytes before JSON allocation; each checkpoint and anchor receipt raw file
is at most 1 MiB, while each event-catalog raw file including its LF is at most
2 MiB (its unsigned catalog body remains at most 1 MiB). The records file
remains at most 1 GiB. Every stated boundary is inclusive; cap+1 is rejected
by bounded-line streaming. Relative POSIX paths are at most 512 UTF-8 bytes,
each component is at most 128 bytes with no control character; there is no
absolute path, no backslash, no `.`, no `..`, and no empty component. The resolver is
root-relative and no-follow: every component and the final entry rejects
symlinks, magic links, directories, FIFOs, sockets, devices, and every other
special file. It uses `openat2` with `RESOLVE_BENEATH|RESOLVE_NO_SYMLINKS|
RESOLVE_NO_MAGICLINKS`, or a semantically equivalent component-by-component
nofollow walk, then `fstat`s a regular file and uses that same descriptor for
length, digest, and parse. A pre-open/check-to-open namespace replacement is
rejected with zero outside-root reads. After the final regular-file descriptor
is acquired, namespace replacement cannot change the bytes read through that
descriptor; the result is based on that same fd/digest, without an
unimplementable promise to detect every later rename. Fixtures cover an
outside symlink, symlink directory, pre-open swap, post-open-fd stability,
FIFO, device/special file, and zero outside reads. IDs, references, purpose, selection reason, media type,
component, and build ID are non-empty normalized safe strings of at most 256
UTF-8 bytes with no control characters. `actor` is exactly
`{principal,execution}` with principal null or `{type,id}` and execution
`{type,id}`; `time` is exactly `{exportedAt}`; `source` is exactly
`{component,runtime,buildId}`. Zero, cap, cap+1, integer-overflow, oversize,
actual-byte mismatch, and malformed closed-object fixtures fail before
streaming.

`time.exportedAt` and `signedAt` are UTC microsecond times with
`time.exportedAt <= signedAt`;
reverse time order is invalid. `signatureAlgorithm` is exactly `ed25519-v1`,
`signingKeyRef` is non-empty, `manifestHash` is exactly 64 lowercase
hexadecimal, and signature is base64url without padding decoding to 64 bytes.
Signing fields are outside the entire stored property named `signature` but
inside the unsigned signed body. Only the stored properties `manifestHash` and
`signature` are excluded from that body:

```text
manifestHash = H(ENC("blackops.audit.export.hash.v1") ||
  ENC(JCS(unsignedAuditExportManifest)))
signature = Ed25519.sign(ENC("blackops.audit.export.signature.v1") ||
  ENC(manifestHashBytes), exportSigningKey)
```

`manifestHashBytes` is the decoded 32-byte value of the 64-lowercase-hex
`manifestHash`. An authorized records file is `AuditExportRecord v1`: each
line is one complete canonical AuditRecord including `integrity`, encoded as
RFC 8785 JCS followed by exactly LF, in ascending sequence order, with no BOM,
CRLF, extra, duplicate, missing, or reassigned record and one file per
segment (or an equivalently explicit one-segment mapping). Verification uses
the same fixed Stage 0-7 order (no reverse or alternate order): Stage 0
validates invocation mode plus the strict bounded external
`VerificationContext` and `expectedContextHash` syntax/recompute/pin (code
`19`, no manifest reads, all top references null, zero manifest/listed-file
opens, and `partitions:[]`); Stage 1 bounds the raw manifest and validates
strict schema/JCS/count/version and declared lengths only (code `10|17`);
Stage 2 compares `manifestHash`; Stage 3 resolves the trusted export key/role
and current trust status at `verifiedAt` without trusting manifest `signedAt`;
Stage 4 verifies Ed25519 (code `11`, zero listed-file opens), then checks the
authenticated `signedAt` time before any file open; Stage 5, only
after signature success, opens root-confined listed files and checks actual
byte lengths/sums/raw digests (code `13`); Stage 6 validates the authenticated
catalog and authenticated record-chain graph/hash/semantic graph first as
global code `14 authenticated_graph_invalid`, then invocation mode/context/
coverage as `16 mode_coverage_invalid`, then freshness head shape/boundary as
`18 freshness_boundary_unavailable`. Global stages 0-6 return
`partitions:[]`; only Stage 7 emits
entries and every emitted local failure has four non-null endpoint references.
Stage 7 validates only local checkpoint/anchor trust/chronology (`15
checkpoint_or_anchor_invalid`) and local end-head freshness (`18`), never
catalog code 10, graph code 14, the deprecated scope label, or mode/coverage
code 16.
Invalid signature plus bad actual bytes opens zero files and returns code `11`;
valid signature plus a bad actual length reaches code `13`. For every
`event_catalog`,
require semantic equality with the stored catalog hash, every covered record's
`event.catalogHash`, and the file `artifactId`; raw `sha256` is separate.
Purpose-only acceptance, file-digest-only acceptance,
catalog-body-plus-raw-digest acceptance, and any stored-hash substitution are
negative fixtures. A checkpoint, anchor, or event-catalog file is
`JCS(object) || 0x0A`; its `sha256` is
SHA-256 of those exact raw file bytes, including LF. `checkpointBytesHash` is
independently the domain-separated SHA-256 of the complete checkpoint JCS
bytes without LF, `anchorReceiptHash` is independently the domain-separated
hash of the unsigned receipt JCS body, and `catalogHash` is independently the
domain-separated hash of the unsigned catalog JCS body.
The illustrative manifest intentionally uses different shape-valid values for
the checkpoint semantic hash (`e`), raw checkpoint-file digest (`3`), anchor
semantic hash (`f`), and raw anchor-file digest (`4`); the raw file digests must
not be substituted for either semantic hash.
LF versus CRLF substitution, a semantic object change, and a raw-byte-only
change are separate negative fixtures: each must fail its corresponding wire,
raw-digest, or semantic-hash verification without treating the values as
equal. Checkpoint/receipt files are JCS bytes followed by exactly one LF; no
BOM or CRLF is accepted.
Offline verification recomputes the
logical body, contentHash, recordHash, checkpointHash, checkpointBytesHash,
anchorReceiptHash, catalogHash, manifestHash, and chain. Bundle confidentiality
is a separate authorized
transport/at-rest contract. A filtered safe projection is a separate schema
and cannot claim chain completeness. Body modification, record reassignment,
extra/duplicate/missing/unlisted file entries, path traversal, missing required
protected input/key, and bundle-key-as-authority are explicit negative cases;
safe projection filtering never substitutes for canonical protected input.

For v1 key trust, a currently expired or revoked checkpoint, anchor, or export
key fails verification regardless of the artifact's self-declared `signedAt`.
Historical acceptance based only on artifact time is unsupported; a future
separately-versioned contract may supply an independent trusted timestamp or
anchor-time proof. When the role is trusted and active,
`notBefore <= signedAt <= verifiedAt < notAfter` still applies, using the
trusted context schema/version and `verifiedAt`. The verification order above
is the only order: it does not open files or evaluate catalog/partition data
before trusted export-key verification and Ed25519 signature success. It makes no claim
that a signed manifest proves legal compliance, non-repudiation, or completeness
for an arbitrary filtered subset. It returns only finite safe codes: `0`
(`verified`), `10` (`manifest_invalid`), `11` (`signature_invalid`), `12`
(`key_unavailable`), `13` (`digest_mismatch`), `14` (`authenticated_graph_invalid`), `15`
(`checkpoint_or_anchor_invalid`), `16` (`mode_coverage_invalid`), `17`
(`unsupported_version`), `18` (`freshness_boundary_unavailable`), or `19`
(`verification_context_invalid`); every
failure is non-zero and must not expose payloads or secrets. A self-contained
bundle without the trusted freshness snapshot can only use explicit
`mode: as_of_checkpoint`. A JSONL projection is not a signed export.

Audit-of-audit access, export, verification, and signing events use the
`system:global` partition and the `audit-control-v1` stream family, with
concrete segment IDs rotated under the same chain/checkpoint rules. Control
records carry `auditOfAuditDepth: 1` in safe evidence and never generate
another audit-of-audit record, preventing recursive self-logging. The signed
root policy for this stream family (roles, retention, verifier key references,
and current segment) is maintained outside the Audit database; a policy change
appends one control event but does not recursively log that control event
itself. Physical deletion and legal-hold/deletion manifests are deferred to
P23-003H; v1 never deletes sealed bytes or keys and never claims compliance.

## 9.8 Historical Round 8 bounded corrections

The following clauses are the Round 8 correction to the proposal and override
any earlier Round 7 wording that conflicts with them. They remain future-only
until P23-001 independent review, downstream implementation review, and the
Specification 106 release gates are accepted.

### 9.8.1 List compatibility and recovery

The Stable `list` name is current; the 1.3-only change is the BlackOps-owned
replacement projection/schema and the new `ls` alias. The Stable behavioral
golden set covers multi-segment nested exact namespaces, unique abbreviated
namespaces, ambiguous namespaces, and unknown namespaces with the exact
Symfony Stable `1.2.0` behavior, diagnostic, and exit status. The replacement
preserves every Stable-accepted combination
of optional namespace, `--raw`, `--short`, and `--format=txt|json|xml|md`.
Repeated `--format` is last-wins. `--json` and `--all` are not Stable inputs;
`--json` alone is exactly `--format=json`, while `--json` plus an explicit
`--format` returns exit `2`. `--raw` has an effect only for txt and is ignored
for non-txt formats. `--short` is an exact no-op for all formats. No new long
help or definition fields are exposed, so this no-op is fixed by golden tests.

An embedded framework projection is available without any application
artifact and always contains at least `build:compile`, one canonical `list`
row whose aliases include `ls`, `help`,
and `completion`, plus every safe framework row. Application rows are merged
only from a same-build, current, valid application artifact. Missing,
malformed, unsupported, stale, or build-mismatched application artifacts therefore return
the framework rows at exit `0` and do not resolve application code, container,
or database. Structured outputs contain the top-level
`applicationProjectionStatus` enum
`available|absent|malformed|unsupported|stale|build_mismatch`.
Txt and raw retain deterministic rows and emit only a secret-safe status
diagnostic on stderr. Complete `route:list`/`route:ls` and
`schedule:list` and `schedule:ls` projections are not recovery projections:
valid is exit `0`, an operational read is exit `1`, and any missing,
malformed, unsupported, stale, or build-mismatched complete artifact is exit
`2`; list/ls recovery remains framework rows plus status at exit `0`.

Human, raw, JSON, XML, and Markdown all select the same safe projection;
`--short` cannot change it. XML v1 is UTF-8 LF-only with no BOM and root
`blackopsCommandProjection version="1" status="STATUS"`; each command has
ordered `name`, `aliases`, ordered `alias` children, `description`, `origin`,
and optional `hidden` (only with `--all`). Empty aliases are `<aliases/>`.
Only XML predefined entities are used for escaping and no unlisted node or
attribute is allowed. Markdown v1 starts with
`<!-- blackops-command-projection-v1 status=STATUS -->` and a deterministic
table with columns `Name | Aliases | Description | Origin` plus optional
`Hidden`. Rows sort by canonical name and aliases. Cell escaping is ordered:
backslash to `\\`, pipe to `\|`, and CR/LF to the literal two-byte sequence
`\\n`; empty aliases are empty cells. Both formats use the same six status
values and LF-only wire.

### 9.8.2 Reservation registry

The single P23-002A/global pre-release registry reserves and checks both
canonical and alias names for `list`, `ls`, `help`, `completion`, `_complete`,
`route:list`, `route:ls`, `schedule:list`, `schedule:ls`,
`diagnostics:check`, `queue:status`, `about`, and `outbox:status`.
P23-002B/C/D/E reuse that registry. It reports owner and name and supplies an
actionable application rename migration; silent override is forbidden.
`worker:list` and `worker:ls` are neither BlackOps-owned nor reserved. An
Application may own either spelling as a canonical command or alias; an
unregistered spelling follows normal Symfony unknown-command behavior. No
Framework Job registry or built-in Job listing exists.

### 9.8.3 Canonical ingress and outbox wire

`recordedAt` is not a sequence-order claim. The required canonical store stamps
it once during canonical append; the durable local Audit Outbox stamps it once
at ingress. Each authority and partition uses the trusted-clock high-water
clamp `max(now,lastAssignedRecordedAt)` in the stamping transaction. The
canonical chain sequence is allocated only at canonical append and is the sole
chain/order/pagination cursor. `recordedAt` is trusted ingestion/retention
time; a relay may append an older timestamp later and must not change it.
Only a durable-outbox partition has monotonic `ingressOrdinal` and FIFO with
no bypass. Required and durable paths make no total-order claim across one
another.

The exact closed `AuditIngressEnvelope v1` is durable-only:
`{schemaVersion,kind,logicalRecord,contentHash,recordedAt,ingressOrdinal,
idempotencyKey,recordId,partitionId,tenantBinding}`. `kind` is exactly
`audit_ingress_envelope`, `schemaVersion=1`, and `logicalRecord` is the closed
14-field AuditRecord v1 body with `integrity` removed. `recordId` equals
`logicalRecord.recordId`; `recordedAt` equals `logicalRecord.time.recordedAt`;
`tenantBinding` equals `logicalRecord.tenant`; `partitionId` equals
`derivePartition(tenantBinding)` under the active mapping; and `contentHash`
equals the recomputed
`H(ENC("blackops.audit.content.v1") || ENC(JCS(logicalRecord)))`, represented
as 64 lowercase hex. `recordedAt` is a trusted UTC authoritative timestamp
with exactly six fractional microseconds; `ingressOrdinal` is a positive
JSON-safe integer; `idempotencyKey` is non-empty NFC/no-control text at most
256 UTF-8 bytes; and `recordId` and `partitionId` use the AuditRecord/partition
bounds. `tenantBinding` is a closed `null|{type,id}` union with the same
normalized non-empty bounds as `AuditRecord.tenant`; it equals
`logicalRecord.tenant` exactly, and `partitionId` equals the canonical
derivation (`system:global` for null, otherwise the domain-hashed `tenant:`
value). It is a bounded authorization/key-routing index, never raw tenant
evidence. Retry preserves every field and logical byte. The required path
passes the same logical AuditRecord directly to the canonical writer and never
stores this envelope. Envelope creation requires the bound Catalog definition
and `policy.auditDelivery` to both be `durable`; `required` fails with
`semantic_invalid` before envelope bytes, key selection, or persistence.

The exact closed stored row is `AuditOutboxRecord v1` with
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}`. Hashes are
64-lowercase-hex, `attempt` is a non-negative JSON-safe integer starting at
`0` on ingress and incremented exactly once for every fenced claim, and
`ingressOrdinal` is positive JSON-safe. Timestamps use six fractional UTC
microseconds. The exact state/nullability matrix is: `pending` has a
non-null `availableAt` and null lease token/expiry; `leased` has null
`availableAt` and non-null opaque fencing token plus expiry; `relayed` and
`poisoned` have all three scheduling/lease fields null. The allowed transitions
are `pending->leased`, `leased->pending` only for fenced transient retry or
expired-lease recovery, and `leased->relayed|poisoned`; the latter two are
terminal. Identity, bindings, hashes, and protected bytes never mutate; only
the listed state-machine fields mutate by fenced CAS. The terminal row is
retained, not deleted. `protectedBody` is non-empty binary BOPD v1 in the
exact Specification 99 layout; its plaintext is exactly
RFC 8785 JCS of `AuditIngressEnvelope v1` with no LF, and purpose is external
AAD context. Relay role, tenant union, and AAD are validated before decrypt;
cross-tenant and missing-key cases fail closed.

The exact custom outbox AAD is four-byte big-endian length-prefixed and
domain-separated:
`LP("blackops.audit.outbox.aad.v1") || LP("AuditOutboxRecord") || LP("1") ||
LP("1") || LP("1") || LP(exactKeyId) || LP(recordId) || LP(partitionId) ||
LP(tenantPresenceAndTypeAndId) || LP(ASCII(canonicalDecimal(ingressOrdinal))) ||
LP(decoded32(idempotencyKeyHash)) || LP(decoded32(contentHash))`. The three
`1` values are schemaVersion, envelopeVersion, and algorithmId in that order;
the tenant union uses an explicit presence marker and LP type/id when present.
This contract does not inherit `CanonicalAssociatedData`; P23-003B owns the
row, BOPD, and AAD, and P23-003C owns relay delivery. Malformed BOPD magic,
version/algorithm, key-ID length or key ID, cross-row/tenant swaps, and AAD
substitution fail before decrypt.

`tenantBinding` is encoded in the row and AAD as a closed union, never by
ambiguous JSON stringification: a one-byte presence marker followed, when
present, by LP-normalized tenant `type` and `id`; it must equal the logical
record tenant and its canonical partition derivation. Partial pairs,
normalization variants, raw-tenant substitutions, and cross-tenant routing
fail before key selection/decrypt. `idempotencyKeyHash` is exactly
`H(ENC("blackops.audit.outbox.idempotency.v1") || ENC(partitionId) ||
ENC(NFC(idempotencyKey)))` as 64 lowercase hex; the raw key exists only in the
protected envelope and is never a clear index.

The B1 positive AAD vectors originate from D148's exact two durable-ingress
logical fixtures. Both resolve Catalog and policy delivery `durable`; a
`required` record is never a durable-only Envelope positive.
`durableSystemIngress` with record ID `01941f29-7c00-7000-8000-000000000004`
and raw key `idem-null-1` derives idempotency hash
`6e22dd41bce06ff2825a8081b3bd34bbe8fe47acba2f76cf0af2ea62771ebdbc`
and content hash `490621aa8d29022a5e21c4a50d15be1d0e477b5a78d5305d9b610a52e871d3c9`;
its AAD is 222 bytes with SHA-256
`1444d7e8430fdb22bed2b29cb10988684aa8b75c061d943ef43cf1d5ec976f7d`.
`durableTenantIngress` with record ID `01941f29-7c00-7000-8000-000000000005`
and raw key `idem-tenant-2` derives idempotency hash
`1caac6c03c7c676ad2506059eb7b26fdbe959bc68a0aa6790db76aa019a6ff54`
and content hash `bbf044ce19de64a9dae2bf449ac8c8819fa95babf33b9ee4eef53d799c7d34d3`;
its AAD is 303 bytes with SHA-256
`6ad3000f8b1b7fa7d76a266c813f9bf83be8c30340c751e761abc396e9acd9e6`.
Direct arbitrary-hash construction is not a positive path.

Idempotency is unique on `(partitionId,idempotencyKeyHash)`. The same key with
the same record ID, content hash, and logical bytes returns the same row and
ordinal; the same key with any different value fails
`idempotency_conflict` before mutation. The raw key is never clear. Ingress
allocates ordinal under a per-partition lock/CAS starting at `1`, increments
by one with no committed gap, and relay claims only the minimum unacked head.
No ordinal 2 may bypass ordinal 1; a poison record acknowledges that ordinal,
releases the next ordinal, and emits a safe operational state. Lease/fencing
CAS permits one owner and
rejects stale acknowledgement. Canonical append is idempotent by
`recordId+contentHash`; a crash after append and before ack recovers the same
canonical sequence/hash. Ordinary retry reuses the same ciphertext. v1
prohibits AuditOutboxRecord protected-body re-encryption: identity, key ID,
envelope bytes, nonce, ciphertext, and tag are immutable in every state.
Key-provider retention preserves decryptability until a future accepted
terminal-row disposal policy; an unavailable old key fails the partition
closed/operationally. Future rotation requires a new versioned CAS/state/
crash contract and is not claimed here.
At ingress and relay, the actual logical body plus the synthetic maximum
(actual partition ID, 256-byte segment ID, maximum JSON-safe sequence, all
64-hex previous/content/record hashes, fixed `audit-sha256-jcs-v1`) must
produce JCS+LF <=1 MiB inclusive. Before allocating protected bytes, the
plaintext envelope length plus exact BOPD overhead
`52 + keyIdUtf8Bytes` (magic/version/algorithm/lengths/nonce/tag) must remain
within the bounded logical-cap allocation; ciphertext length equals plaintext
length. Fixtures cover same/different idempotency, initial attempt `0`, retry
attempt increment, 2-before-1, parallel relay, append/ack crash, lease expiry,
stale-token rejection, poison, AAD row/tenant swaps, malformed BOPD headers,
and cap/cap+1.

### 9.8.4 Catalog, authority, and evidence

Every semantic tuple value is non-null and non-empty, including
`resourceType`, `outcomeCode`, and `policyReasonCode`; each resolves to a
declared enum/code and maximum bound. Wildcards are not in v1. Evidence string
fields have `maxUtf8Bytes <= 256`; null, exactly-256, and 257-byte fixtures
are required, and a Catalog-valid tuple must be structurally capable of a
Record-valid body; this containment is an explicit fixture/test. String evidence uses only typed projections classified as
`safe_code|opaque_id`; `safe_code` is valid through exactly 256 bytes when
declared at 256, while `opaque_id` is exactly 71 bytes and requires declared
`maxUtf8Bytes:71`; integer and boolean fields have `classification:null`.
The typed writer rejects or derives raw credential/token inputs, but the
wire grammar cannot cryptographically prove origin or secret absence.
Semantic secret detection is not an automatic compliance guarantee; the
application mapper and review own that responsibility. Offline syntax
validation alone is not secret detection. Fixtures cover safe-code 256/257,
opaque 70/71/72, nullable null, and Catalog-valid implies structurally
Record-valid containment.

Framework definitions come only from a sealed internal provider. An
application catalog's `owner=application` is forced from trusted registry/build
identity, never accepted from a self-declared field. The exact reserved
framework event prefixes are `security.`, `access.`, `retention.`, `storage.`,
`observer.`, `outbox.`, `audit.`, `configuration.`, `control.`, `replay.`,
and `rotation.`; application collision with any prefix fails closed. The
same namespace set is used by D145, Specification 106, and all downstream
fixtures.

Each Record is independently Catalog-validated. An `action.id` correlates
records only; v1 does not validate a cross-record lifecycle, transition,
continuity, or ordering contract. Claims about phase-change continuity are
deferred to a future versioned transition contract.

### 9.8.5 Verification, references, time, and access

The verifier has one total-order table:

| Stage | Input and action | Failure/result contract |
| --- | --- | --- |
| 0 | invocation mode plus bounded strict external `VerificationContext`, `expectedContextHash` syntax/recompute/pin; no manifest reads or freshness-head comparison | invalid/missing mode code `19` with mode/scope null; otherwise context failure code `19` with derived mode/scope, `verifiedAt`/`contextHash` null; all top references null, manifest/listed-file open count `0`, and `partitions:[]` |
| 1 | bounded raw manifest, strict v1 schema/JCS/count/version and declared lengths only | code `10` or `17`; `mode/scope/verifiedAt/contextHash` non-null after accepted context, `exportId/manifestHash` null, `partitions:[]` |
| 2 | constant-time `manifestHash` comparison | code `10`; context refs non-null, export/manifest refs null, `partitions:[]` |
| 3 | trusted export-key/role resolution and trust-snapshot status at trusted `verifiedAt`; the untrusted manifest `signedAt` is not used | unknown/wrong-role/currently revoked/expired/not-yet-valid export key is code `12`; context refs non-null, export/manifest refs null, `partitions:[]` |
| 4 | Ed25519 signature verification, then authenticated `signedAt` check against `notBefore <= signedAt <= verifiedAt < notAfter` before any file open | invalid signature is code `11` with export/manifest refs null and listed-file open count zero; signature success makes export/manifest refs non-null; authenticated signed-time failure is code `12` with zero listed-file opens and those refs non-null |
| 5 | after signature only: root-confined open, actual byte lengths/sums, raw digests | code `13`; context/export/manifest refs non-null, no pre-signature file access |
| 6 | authenticated catalog graph and semantic hashes, invocation mode/context-to-manifest coverage binding, and exact freshness-head set shape (one unique head per manifest partition, sorted) | code `10`, missing/extra/duplicate/wrong-sort head set `18`, non-null as-of freshness/scope mismatch `16`; `partitions:[]` |
| 7 | partition, record, checkpoint, anchor, freshness, and scope checks; compare each end receipt to its authenticated head and chronology | first deterministic local code `14|15|16|18`; a partition-local head mismatch is code `18` with full signed refs, while other entries may verify |

Global failures in stages 0-6 always return `partitions:[]`; only partition
failures in stage 7 produce entries. Every emitted partition entry, including
a partition-local failure, has non-null `startCheckpoint`, `endCheckpoint`,
`startReceipt`, and `endReceipt`; failure entries have non-zero code and false
flags. `exportId` and `manifestHash` are null before Stage 4 signature
authentication, while accepted context `verifiedAt` and `contextHash` may
remain non-null. A valid
signature with a bad actual length reaches stage 5/code 13; an invalid
signature with bad actual bytes opens zero files and returns stage 4/code 11.

Chronology is mandatory in every mode through
`checkpoint.sealedAt <= checkpoint.signedAt <= receipt.anchoredAt <=
receipt.signedAt <= verifiedAt`. Current mode additionally requires
`receipt.signedAt <= freshness.observedAt <= verifiedAt < validUntil`; as-of
mode has null freshness and makes no current/suffix claim. Fixtures cover each
boundary, equality, and one microsecond after each permitted boundary.

For an unauthenticated, invalid, or otherwise untrusted actor, the partition
is always `system:global`, `principal` is null, and raw target/tenant values
are not recorded. An authenticated actor with a trusted tenant uses the
requester's partition for same-tenant, cross-tenant, and absent-target cases;
the target tenant is never used. An authenticated actor whose tenant is null
uses `system:global` with a populated principal and never infers a target
tenant. Cross-tenant and absent reads return the same safe
`resource_unavailable` outcome, with a bounded domain-separated opaque request
reference and no existence claim. Status, Journal, Outcome, and diagnostic
reads share the full actor x tenant x same/cross/absent fixture matrix; an
absent target is resolved only after authorization and still emits the same
safe outcome. Future audited diagnostics require the closed
`AuditReadContext v1` `{schemaVersion:1,kind:audit_read_context,purpose,actor,execution,tenant,requestedAt,nonce,requestRef,authorizationDecision}`;
P23-003D/G own that adapter and fixtures. Its requestRef is exactly
`LP("blackops.audit.read.request-ref.v1") || LP(JCS({purpose,actor,execution,tenant,requestedAt,nonce}))`
with no target. Stable current diagnostics remain unchanged and unaudited
until that contract is implemented.

Before any external anchor write, persist the exact closed `AnchorIntent v1`
object with these fields only:
`schemaVersion,kind,intentId,state,partitionId,checkpointId,checkpointHash,
checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,
expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,
acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,
receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,
leaseExpiresAt,version,updatedAt`.
`schemaVersion=1`, `kind="audit_anchor_intent"`, and state is exactly
`prepared|externally_accepted|receipt_inputs_persisted|receipt_persisted|
acknowledged`; all IDs, refs, and tokens are non-empty NFC/no-control bounded
safe strings, hashes are 64 lowercase hex, versions are positive JSON-safe
integers or null only at genesis/state boundaries, and lease/accepted times
are UTC timestamps with exactly six fractional microseconds. `idempotencyKey` and
`conditionalCreateToken` are non-credential opaque idempotency values;
`leaseFencingToken` is an opaque non-credential fencing value; external auth
credentials are never stored in the intent. `receiptSigningKeyRef` is the only
signing-key field. `expectedPredecessorVersion` and
`expectedPredecessorHash` are both null at genesis or both non-null otherwise.
Transitions are monotonic and require owner fencing CAS.

The closed state matrix is: `prepared` has all four accepted fields null;
`externally_accepted` has `acceptedObjectVersion`, `acceptedObjectHash`, and
`acceptedAnchoredAt` non-null but all four receipt fields null;
`receipt_inputs_persisted` has the accepted triplet plus
`receiptSignedAt,receiptSigningKeyRef,receiptBytesHash` non-null and
`anchorReceiptHash` null; `receipt_persisted` and `acknowledged` have all
accepted and receipt fields non-null. Prepared pins
every input before the external write. Conditional create binds the intent
and checkpoint bytes and returns stable read-after-write
`{objectVersion,objectHash,anchoredAt}` for the same idempotency; a conflict
fails closed and a retry never creates N+1. `acceptedObjectHash` equals
`checkpointBytesHash`. The receipt ID and signing key are prederived/pinned;
the v1 receipt's `signedAt` exactly equals stable external `anchoredAt`, its
signature is deterministic over the same bytes, and `anchorReceiptHash` is
the persisted unsigned-receipt semantic hash (not the raw signed-file SHA).
Persist externally accepted
material, then the byte-identical signed receipt, then acknowledge; the key
remains available until terminal state and rotation cannot substitute it.
Closed-object validation fixtures cover every state/nullability/type bound,
sign-before-persist, parallel retry, and read-after-write recovery. The three
non-overlapping crash fixtures are before external accept; after external
accept and before local signed-receipt persist; and after local receipt persist
and before acknowledgement. All retries use the same intent/idempotency/object/
version/receipt bytes/signature and create no N+1.

The unsigned manifest body is at most 15 MiB inclusive and the complete stored
raw JCS plus LF is at most 16 MiB inclusive. Producer and verifier enforce both
caps before allocation, with cap+1 fixtures. For required effects, the exact
underlying connection object and active root transaction identity are verified
before the effect. Matching a connection name is insufficient; same-name /
different-object, different-name, nested/manual, and rollback fixtures fail
closed and no durable fallback is allowed.

### 9.8.6 Historical Round 9 review checkpoint

Round 8 fresh review is historical Changes Requested: General `P1=2/P2=1/P3=0`
and Audit `P1=5/P2=4/P3=1`; the lanes are not combined. Round 9 is historical
Correction Implemented / Review Requested wording. Round 10 is also historical;
Round 11 was the historical Correction Implemented / Review Requested checkpoint. The schedule-only exit split, six-state
projection identity and precedence, canonical XML/Markdown fixture bytes,
classification-required evidence, durable-only ingress and exact outbox row,
verification total function and endpoint refs, fenced AnchorIntent recovery,
actor/read and nofollow resolver boundaries, checkpoint control events, and
metadata-only retention are the current proposal contract. No 1.3 capability
is public or Stable; fresh Sol review and Orchestrator acceptance remain.

## 10. Correlation contract

Current Stable Structured Logging/Telemetry keeps Operation/Correlation IDs
separate from W3C Trace/Span IDs, and `ExecutionScopedLogger` provides the
current safe structured projection. The new four-surface Audit correlation
contract is future-only; P23 adds no replacement current claim. It is:

| Surface | Operation/Correlation | Trace/Span |
| --- | --- | --- |
| Application Log | optional safe IDs when a scope exists | optional sampled projection |
| Observability | safe operation/correlation attributes | W3C trace context and sampled decision |
| Lifecycle Journal | canonical operation/correlation/causation | optional projection, Journal never sampled |
| Audit Trail | optional operation/correlation/causation/attempt | optional sampled correlation only |

Operation IDs are framework lifecycle identities. Trace IDs are distributed
trace identities. They are never converted into each other. A missing trace
does not make an AuditRecord invalid; a missing required AuditRecord does not
get repaired from a trace. HTTP may extract valid W3C `traceparent`; child,
Deferred, retry, and outbox contexts may propagate it under existing protected
storage rules. Invalid or absent context starts/uses no remote parent and does
not emit the raw carrier. Baggage is not an AuditRecord field.

Sampling applies to trace/observability data only. It never samples required
AuditRecord or canonical Journal events. Correlation fields are not proof of
identity, integrity, causation, or non-repudiation.

## 11. Capability gaps and non-claims

Stable `1.2` currently provides FrankenPHP Worker Mode as the default HTTP
runtime, narrow retention, replay, rotation, and outbox audit-like records
plus safe Structured Log projections. It does not provide:

- a general versioned AuditRecord event catalog and application projector;
- a dedicated encrypted tenant-aware append-only AuditRecord store;
- required/durable Audit Outbox transaction semantics;
- per-tenant chain sealing and external signed checkpoints;
- signed export with offline verification;
- audit-of-audit partition and future physical-deletion/deletion-manifest contract; or
- `1.3` list/route/schedule projections or Classic removal.

P23-004A is future hardening of the existing Worker Mode into the sole
supported production HTTP profile plus the Classic migration/removal gate.
P23-004B Extension Worker work and any same-process Deferred arrangement remain
proposal-only and are not Stable capabilities.

These are implementation gaps, not delivered features. No current page may
claim automatic compliance, non-repudiation, a complete business audit trail,
same-process Deferred isolation, or a public machine contract based on
Symfony descriptors.

## 12. Traceability and evidence

Repository evidence is recorded in [D145](../decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md).
The primary existing contracts are Specifications 32, 42, 47, 51, 65, 78,
80, 82, 98, 99, and 100, and Decisions D062 and D136.

Official evidence researched 2026-08-19:

- [FrankenPHP Worker Mode](https://frankenphp.dev/docs/worker/)
- [FrankenPHP Internals](https://frankenphp.dev/docs/internals/)
- [FrankenPHP Extension Workers](https://frankenphp.dev/docs/extension-workers/)
- [Writing PHP extensions in Go](https://frankenphp.dev/docs/extensions/)
- [FrankenPHP Configuration](https://frankenphp.dev/docs/config/)
- [FrankenPHP Performance](https://frankenphp.dev/docs/performance/)
- [PHP PCNTL overview](https://www.php.net/manual/en/book.pcntl.php)
- [PHP pcntl_signal](https://www.php.net/manual/en/function.pcntl-signal.php)
- [PHP pcntl_alarm](https://www.php.net/manual/en/function.pcntl-alarm.php)
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
- [NIST SP 800-53 Rev. 5](https://csrc.nist.gov/pubs/sp/800/53/r5/upd1/final)
  is design evidence for Audit and Accountability controls only, not a
  compliance claim.
- [W3C Trace Context](https://www.w3.org/TR/trace-context/)

## 13. Historical Round 10 bounded correction override

Round 10 was the normative correction for this proposal at that historical
checkpoint. Its exact-8 local management gates were PASS; Stable `1.2`
remains unchanged and no Phase 23 capability is public. The current evidence
population is 44 unique existing local targets, 46 cited ranges, and 270
Markdown link occurrences; Round 8 historical review is General
`P1=2/P2=1/P3=0` and Audit `P1=5/P2=4/P3=1`, while Round 9 has no invented Sol
verdict.
Every clause in this section was historical-at-that-time and is superseded by
the Round 12 contract below; it is not current normative text.

P23-002A's installation-owned `ProjectionBuildSet v1` is the sole expected
identity source for list/route/schedule recovery. It is a closed
`{schemaVersion,applicationBuildId,projections}` object with exact command,
route, and schedule `{kind,inputHash,path,sha256}` descriptors, bounded safe
relative paths, and lowercase 64-hex hashes. `build:compile` creates each
dedicated projection with temp+fsync+atomic rename and atomically publishes
the root last. Readers load only the configured immutable root, verify the
root/projection build, input, and digest bindings, and do not resolve source,
provider, container, or DB. Mixed old/new roots, root-before-artifact, source
drift, swaps, and concurrent partial publication fail closed.

The exact list/ls recovery enum is
`available|absent|malformed|unsupported|stale|build_mismatch`. ENOENT,
operational I/O, malformed bytes/shape, unsupported valid schema/kind, build
mismatch, and input-hash mismatch are evaluated in that order. All six list/ls
states render framework rows at exit 0 with top-level structured
`applicationProjectionStatus` or exact txt/raw stderr
`applicationProjectionStatus=<status>\n`; operational read is exit 1.
Complete route/schedule failures are exit 2, with schedule:list and
schedule:ls identical only to each other. Projection bytes are bounded and
injective: raw JSON <=16 MiB, records 0..4096, aliases 0..32, names/aliases
NFC/no-control <=256 bytes excluding tab/comma/CR/LF and empty colon segments,
and descriptions are one-line NFC/no-control <=256 bytes. Explicit and
discovered registration startup uses lazy metadata and constructor/factory/
container/DB sentinels. Stable nested/unique/ambiguous/unknown namespaces,
all accepted format combinations, repeated format last-wins, raw txt-only,
short exact no-op, JSON/XML/Markdown/raw parity, and hidden `--all` semantics
remain fixed. JSON is compact UTF-8 JCS plus one final LF; XML and Markdown
are continuous canonical bytes with hashes
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968` and
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.

The Audit protection contract uses Spec 99's exact binary BOPD v1 layout for
canonical AuditRecord and `AuditOutboxRecord`; purpose/AAD are external, not
stored object fields. Canonical plaintext is complete AuditRecord JCS+LF and
outbox plaintext is JCS(`AuditIngressEnvelope v1`) without LF. Custom,
length-prefixed, domain-separated AAD binds version, algorithm, exact key ID,
purpose, record/partition, tenant union presence/type/id, and relevant hashes;
cross-row, cross-tenant, header, and key-ID substitutions fail before decrypt.
The envelope is durable-only and binds recordId, recordedAt, contentHash,
logical tenant, and canonical derived partition; raw idempotency keys remain
protected while their normalized partition-bound hash is clear. Outbox
identity/protected bytes are immutable; only fenced state/attempt/availability/
lease fields mutate, with attempt 0 at ingress, minimum-head FIFO, poison
acknowledgement and next-head release, and retry/expiry fencing.

Evidence descriptors are exactly
`{name,type,required,nullable,maxUtf8Bytes,classification}`. `safe_code` is
NFC lowercase grammar with a reachable 256-byte maximum (actual value must be
within its declaration); `opaque_id` is exactly 71-byte
`oid:v1:<64 lowercase hex>` and its declaration must be exactly 71. Integer
and boolean max/classification are null. Missing/unknown classification,
safe-code 257, opaque 70/72, raw opaque token, and non-null scalar
classification fail; safe-code 256, opaque 71, and nullable null fixtures
pass. Wire syntax is not secret detection; typed mapper and Application
review own semantic mapping.

Audit verification is one Stage 0-7 total function: Stage 0 bounded mode/
context/expected hash code 19 and zero reads; Stage 1 manifest schema/JCS/
count/version/declared lengths; Stage 2 manifest hash; Stage 3 trusted export
key role/status without untrusted signedAt; Stage 4 signature code 11 with
zero opens and null export refs on failure, making exportId/manifestHash
non-null only after success; Stage 5 same-fd root-confined actual bytes code
13; Stage 6 catalog/context and head-set shape global code 18/16; Stage 7
partition semantics local code 14/15/16/18. Global failures have empty
partitions; emitted local failures retain all authenticated refs. Each signed
segment has one complete checkpoint and one receipt file; result start refs
come from the first segment and end refs from the last, so one segment has
byte-identical endpoints. The manifest has no `as_of` member; external as-of
verifies the full included graph without current/suffix claim. Prefix/suffix
flags follow the authenticated boundary/head rules.

`AnchorIntent v1` is closed with bounded NFC/no-control identifiers, 64-hex
hashes, positive versions, six-fraction UTC times, noncredential opaque
idempotency/fencing tokens, and a prepared/external/receipt/ack nullability
matrix for accepted object version/hash/time and receipt hash. The three crash
fixtures are before external accept, after accept before receipt persistence,
and after receipt persistence before ack; retries recover identical receipt
bytes/version and no N+1. Nofollow rejects pre-open swaps and special files;
post-open reads use the acquired fd. Checkpoint/anchor control artifacts in
`system:global/audit-control-v1` do not recursively emit control events.

## 14. Historical Round 11 bounded correction override

Round 11 was a historical `Correction Implemented / Review Requested`
checkpoint and is superseded by Round 12. Round 8, Round 9, and Round 10 are
historical and retain their separate review wording; the Round 8 counts remain
General `P1=2/P2=1/P3=0` and Audit `P1=5/P2=4/P3=1`, with no aggregate and no
invented Round 9/10 Sol verdict. Stable `1.2` and every public/current claim
remain unchanged. The current evidence population is 44 unique existing local
targets, 46 cited ranges, and 270 Markdown link occurrences.

### 14.1 ProjectionBuildSet v1

The installation-owned configured root is the only expected-identity boundary;
"trusted" means that configured installation boundary and is not a
cryptographic authenticity claim. Its exact closed root object has only
`schemaVersion`, `kind`, `applicationBuildId`, `buildSetHash`, and
`projections`. `schemaVersion` is `1`, `kind` is exactly
`projection_build_set`, `applicationBuildId` is NFC/no-control UTF-8 text of
1..128 bytes, `buildSetHash` is lowercase 64-hex, and `projections` has exactly
the three keys `command`, `route`, and `schedule`. Each descriptor is the
closed object `{kind,inputHash,path,sha256}` with no unknown or duplicate
members. The exact kinds are `blackops_command_projection`,
`blackops_route_projection`, and `blackops_schedule_projection`; both hashes
are lowercase 64-hex and `path` is a root-relative POSIX safe path of 1..256
UTF-8 bytes with no backslash, empty component, `.`/`..`, control, or symlink
component. The root self-hash preimage is exactly
`SHA-256(ENC("blackops.projection-build-set.v1") ||
ENC(JCS(root excluding buildSetHash)))`, where `ENC` is the existing
length-prefixed typed encoding. A wrong self-hash is `malformed`, not a
cryptographic-authenticity failure. Stored root bytes are exactly UTF-8 RFC
8785 JCS of the complete root followed by one LF byte, with no BOM, CRLF, or
other whitespace; the self-hash preimage omits that storage LF. The 64 KiB cap
applies to complete stored bytes, and missing-LF, extra-LF, BOM, CRLF, cap, and
cap+1 root fixtures are required.

Each projection file is a closed object with exactly
`{schemaVersion,kind,applicationBuildId,inputHash,records}` and the exact
descriptor kind above. `inputHash` is exactly
`SHA-256(ENC("blackops.projection-input.v1") ||
ENC(JCS({kind,schemaVersion,applicationBuildId,sourceRecords})))`, where
`sourceRecords` are closed, normalized, unsigned-UTF-8 sorted source records
for that kind. Command records are exactly
`{name,aliases,description,origin,hidden}` and use the existing
separator-free command grammar; route records are exactly
`{method,path,strategy,operationType,origin}`; schedule records are exactly
`{name,operationType,cron,timezone,strategy}`. Command sort is canonical name
then aliases; route sort is `(path,method,origin,operationType,strategy)` with
nulls first; and schedule sort is `(name,operationType)` with nulls first, all by canonical
unsigned UTF-8 bytes with no locale. Route `method` is an uppercase token of
1..16 ASCII bytes, `path` is NFC/no-control UTF-8 of 1..2048 bytes beginning
with `/`, `strategy` and `operationType` are nullable or bounded NFC/no-control
text (1..256 bytes when non-null), and `origin` is exactly
`operation_manifest|framework_fixed`. Schedule `name` and `operationType` are
1..256-byte NFC/no-control safe text, `cron` is 1..256-byte one-line
NFC/no-control text, `timezone` is a bounded IANA-style NFC/no-control text of
1..128 bytes, and schedule `strategy` is bounded NFC/no-control text of 1..64
bytes. Every object rejects unknown,
duplicate, missing, wrong-type, unsorted, duplicate-key, non-NFC, control, or
over-bound members. Root raw bytes are at most 64 KiB inclusive; each
projection raw UTF-8 JCS+LF is at most 16 MiB inclusive and has 0..4096 rows.
All root, projection, row, alias, and field cap/cap+1 fixtures are required.

`build:compile` writes each immutable content-addressed/generation projection
file to a bounded temporary file, flushes and fsyncs it, atomically renames it
into a new generation path, and fsyncs the parent directory; it never
overwrites a file referenced by an existing root. It then writes, fsyncs,
atomically renames, and parent-fsyncs the root descriptor last. A reader sees
only old-root+old-files or new-root+new-files. Root-before-file, file swap,
mixed generation, concurrent publication, and GC-before-reader-quiescence
fixtures are negative; old generation files are garbage-collected only after
the reader safety boundary.

The executable reader/publisher/GC quiescence protocol is the fixed
installation-owned regular nofollow lock file
`.blackops-projection-build-set.lock` in the configured root. A reader
opens and validates that lock as a regular non-symlink/non-special file,
acquires a shared `flock`, and holds it through root open, all three same-fd
nofollow regular-file opens, root/descriptor validation, row selection, and
rendering before release; kernel lock release covers crash/exit. A publisher
may prepare and fsync unreferenced generation files before acquiring an
exclusive flock, then atomically publishes the root and runs GC under that
exclusive lock. GC removes only paths unreferenced by the now-current root;
bounded orphan temp/generation cleanup is also exclusive. A crash before root
publication leaves current readers intact. Lock replacement, symlink,
directory, FIFO/device, reader/publisher interleaving, and old/new generation
fixtures are required. This flock protocol is the sole meaning of reader
quiescence.

The reader receives only the configured installation root/path, reads no
working source, provider, container, database, or application code, and makes
no claim to detect drift between uncompiled source and a published artifact.
`stale` is reserved for a descriptor/projection build identity or input-hash
mismatch. One decision table applies to all readers: raw cap before parsing;
root/projection ENOENT is `absent`; other permission/I-O/unreadable failure is
operational exit `1`; invalid bytes/JSON/JCS, duplicate/unknown/missing/type
members, wrong closed shape, bad root self-hash, or bad raw digest is
`malformed`; syntactically valid unsupported schema/kind is `unsupported`;
configured/root/projection build identity mismatch is `build_mismatch`;
descriptor/projection input identity mismatch is `stale`; otherwise
`available`. List/ls maps all six artifact states to framework recovery rows,
status, and exit `0`; complete route/schedule maps them to exit `2`, while
operational reads remain exit `1`. No second precedence table or uncompiled-
source failure claim is valid.

### 14.2 Canonical storage, ingress, and reads

B1 uses exactly two Production implementations of the accepted internal
`AuditLogicalRecord` seam: an unvalidated Candidate accepted only by the
Validator, and a detached Canonical value produced only from its validated
result. Candidate construction is limited to Finalizer／RecordCodec and
Canonical construction to Finalizer; the validated result itself does not
implement the seam.

The exact durable-only `AuditIngressEnvelope v1` fields are
`{schemaVersion,kind,logicalRecord,contentHash,recordedAt,ingressOrdinal,
idempotencyKey,recordId,partitionId,tenantBinding}`. `tenantBinding` is the
closed `null|{type,id}` union, equals `logicalRecord.tenant`, and derives the
canonical partition (`system:global` for null). Required delivery passes the
same logical record directly to canonical append and stores no envelope.

The canonical transport row is the exact closed `AuditStoredRecord v1` object
`{schemaVersion,kind,recordId,partitionId,tenantBinding,segmentId,sequence,
previousRecordHash,contentHash,recordHash,recordedAt,eventCategory,
protectedBody}`. It has `schemaVersion=1`, kind `audit_stored_record`.
`recordId` and `segmentId` are 1..256-byte NFC/no-control strings;
`partitionId` is exactly `system:global` or `tenant:<64 lowercase hex>`;
`tenantBinding` member bounds equal the AuditRecord tenant bounds; and
`eventCategory` is exactly the AuditRecord category enum and equals decrypted
`event.category`, while `recordedAt` equals decrypted `time.recordedAt`.
Sequence is a positive JSON-safe integer, and null
`previousRecordHash` only at sequence 1 or 64-lowercase-hex otherwise, 64-hex
content/record hashes, authoritative six-fraction UTC `recordedAt`, and a
non-empty binary BOPD v1 `protectedBody`, with complete allocation bounded by
1 MiB+180 bytes. The immutable append identity is
`(partitionId,segmentId,sequence)`; recordId+contentHash is idempotent and
any identity/body/hash mutation fails closed. Plaintext is the complete
AuditRecord JCS followed by exactly one LF.

`AuditStoredRecord` and `AuditProtectedIngress` each expose one closed
`fromArray()` hydration seam in B1. A B2 stored-row hydrator and a C outbox-row
hydrator call those seams only after their authorization／selection boundary;
missing, extra, list, wrong-type／enum, and over-cap rows fail as
`protection_failed`. A detached persisted row must hydrate and decrypt with a
separate codec instance without re-encryption. Secret-bearing snapshots and
binary parts use `SensitiveParameterValue`-equivalent private storage, not raw
instance string/array properties, so debug, serialization, JSON, var-export,
and exception surfaces expose no canary.

The canonical `AuditRecord` BOPD envelope uses key IDs of 1..128 bytes,
algorithm/envelope version `1`, purpose `AuditRecord`, and the exact overhead
`52 + keyIdUtf8Bytes`; complete plaintext is at most 1 MiB inclusive and the
maximum envelope allocation/read bound is 1 MiB+180 bytes. Its custom
length-prefixed domain-separated AAD binds, in exact order, schema version,
envelope version, algorithm ID, exact key ID, purpose, recordId, partitionId,
the tenant-union presence/type/id, segmentId, canonical decimal sequence,
the previous-hash null marker/hash, contentHash, recordHash, recordedAt, and
eventCategory. Authorization and expected partition/key routing occur before
decrypt; after decrypt, the complete record, tenant, partition, row fields,
content hash, and record hash must equal every clear/AAD binding. Header,
key-ID, cross-row, cross-tenant, tenant-union, and semantic/raw hash
substitution fixtures fail before plaintext is exposed.

The shared generic BOPD wrapper remains a different cap domain: its
plaintext/ciphertext cap is exactly `0xFFFF_FFFF`, and its maximum full
envelope cap is exactly `0x1000000B3` (`0xFFFF_FFFF + 52 + 128`). Audit
record/outbox 1 MiB caps never narrow this Stable generic boundary.

`AuditReadContext v1` is the exact closed object
`{schemaVersion:1,kind:audit_read_context,purpose,actor,execution,tenant,requestedAt,nonce,requestRef,authorizationDecision}`.
`actor` is null or `{type,id}`, `execution` is a required `{type,id}`, `tenant`
is null or `{type,id}`, all IDs are normalized bounded NFC/no-control safe
strings, `purpose` is exactly `status|journal|outcome|diagnostics`,
`authorizationDecision` is exactly `allow|deny|resource_unavailable`,
`requestedAt` is UTC with six fractional digits, and `nonce` is a 1..128-byte
NFC/no-control noncredential. `requestRef` is exactly
`oid:v1:<64 lowercase hex>` from
`LP("blackops.audit.read.request-ref.v1") || LP(JCS({purpose,actor,execution,tenant,requestedAt,nonce}))`;
no raw target is included. Unauthenticated/invalid/no-trusted-actor maps to
`system:global` with null actor; authenticated actor+tenant maps every
same/cross/absent target to the requester tenant partition; authenticated
actor+tenant-null maps to `system:global` with the principal populated and
never infers a target tenant. Cross-tenant and absent targets share
`resource_unavailable` with no existence claim. Future audited diagnostics use
this context; Stable diagnostics remain unchanged and unaudited until its
adapter is implemented.

### 14.3 Manifest identity and verifier total function

Every manifest segment has one singular complete checkpoint and one singular
complete receipt. The records file `artifactId` equals `segmentId`; the
checkpoint file `artifactId` equals `segment.checkpoint.checkpointId`; the
anchor file `artifactId` equals `segment.receipt.anchorReceiptId`; and each
catalog file `artifactId` equals the referenced `catalogHash`. These identities
do not derive from a basename or role prefix. Manifest
`segments` are ordered by unsigned `partitionId`, then checkpoint-chain order;
`startBoundaries` are ordered by unsigned `partitionId`; `files` are ordered
by unsigned canonical path and paths are unique. Raw file SHA-256 covers exact
stored bytes including their specified LF, while checkpointHash and
anchorReceiptHash are independent semantic domain hashes; they are never
substituted for one another. One segment copies the same endpoint object to
start and end; multiple segments copy first/last.

The verifier is a single total function. Stage 0 validates invocation mode and
the standalone bounded context, expected-context-hash syntax/recompute/pin,
and context/trust/freshness snapshot shape, internal consistency, hashes, and
`observedAt <= verifiedAt < validUntil`; malformed context is code `19`, with
mode/scope retained only for a valid mode and zero manifest/listed-file opens.
Stage 1 first rejects malformed raw bytes/JCS/closed shape/bounds as code
`10`, and only then maps a syntactically valid unsupported version/kind to code
`17`; it reads declared lengths only. Stage 2 compares manifestHash. Stage 3
resolves only the trusted export key, role, and current status at verifiedAt;
unknown/wrong-role/revoked/expired/not-yet-valid key is global code `12` with
null export refs and no file opens. Stage 4 first verifies Ed25519; invalid
signature is code `11`, zero file opens, and null `exportId`/`manifestHash`.
Only successful signature authentication makes those two refs non-null; the
authenticated signedAt key-window check can then return code `12` with refs
non-null, still before file open. Stage 5 opens root-confined regular files
through the same fd and checks actual lengths/sums/raw digests (code `13`).
Stage 6 orders authenticated catalog graph code `10`, mode/context coverage
code `16`, and freshness head-set shape code `18`. Stage 7 processes partitions
in canonical order and chooses the first local failure in exact order: record
or chain `14`, checkpoint/anchor/key/signature/chronology `15`, scope/boundary
`16`, then freshness head mismatch `18`. A valid but missing/expired/stale
current head is code `18`; as-of with a non-null freshness snapshot is code
`16`. Global failures return `partitions:[]`; Stage 7 local entries retain all
authenticated endpoint refs. The overall result is the first nonzero sorted
partition entry. `verifiedAt`/`contextHash` become non-null after accepted
context; export refs become non-null only after Stage 4 success. Stage 4
failure plus bad actual bytes opens zero files and keeps export refs null;
valid signature plus bad actual bytes reaches Stage 5/code `13` with refs.

### 14.4 AnchorIntent exact recovery

The closed `AnchorIntent v1` field is `anchorReceiptId`, never `receiptId`.
It has bounded NFC/no-control IDs/refs/tokens, 64-hex hashes, positive
versions, and UTC six-fraction lease/accepted times; conditional-create,
idempotency, and fencing values are non-credential opaque identifiers and no
external auth credential is stored. Genesis acceptedObjectVersion is `1`;
otherwise it is expectedPredecessorVersion+1. `acceptedObjectHash` equals
`checkpointBytesHash`; the signed receipt's anchorReceiptId equals the
intent's anchorReceiptId and its body, partition, checkpoint, store/object,
version, key, and time equal the intent/accepted response. `anchorReceiptHash`
is the domain-separated semantic hash of the unsigned receipt body with
signature fields excluded, never the raw signed-file SHA.

Only an active claim has both `leaseFencingToken` and `leaseExpiresAt` non-null;
an unclaimed or terminal intent has both null. `prepared`,
`externally_accepted`, and `receipt_persisted` may hold a fenced active claim
only as that non-null pair; `acknowledged` is terminal and both are null.
The accepted object version/hash/time are null in `prepared`, non-null after
external acceptance; `anchorReceiptHash` is null until `receipt_persisted`
and non-null in `receipt_persisted`/`acknowledged`. CAS fencing permits only
prepared->externally_accepted->receipt_persisted->acknowledged (with a
fenced lease reclaim, never regression); expired reclaim obtains a new token
and stale owners cannot write. Three crash points, sign-before-persist,
parallel retry, receipt-ID mismatch, semantic/raw hash substitution,
lease-expiry, and read-after-write fixtures must recover one deterministic
receipt/version and never create N+1.

## 15. Historical Round 12 bounded correction checkpoint

Current checkpoint updated: 2026-08-19T14:51:24+09:00

Round 12 was the then-current `Correction Implemented / Review Requested`
checkpoint. Round 8, Round 9, Round 10, Round 11, and Round 12 are now historical review or
correction records with separate counts; Round 11 remains Changes Requested,
General `P1=2/P2=2/P3=0` and Audit `P1=7/P2=3/P3=1`, with no aggregate. This
proposal remains unreleased, not Green, not Accepted, and not Decided. Stable
1.2 behavior is current; every 1.3 surface below is proposal-only.

### 15.1 One projection wire and publication boundary

`ProjectionBuildSet v1` is the only command/route/schedule projection wire.
Its closed root is exactly
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}`, with
`kind=projection_build_set`, exactly three descriptors
`command|route|schedule`, and descriptor shape
`{kind,inputHash,path,sha256}`. The exact kinds are
`blackops_command_projection`, `blackops_route_projection`, and
`blackops_schedule_projection`; root and projection raw bounds, row counts,
NFC/no-control field grammars, route method/path/strategy/operationType/origin,
and schedule name/operationType/cron/timezone/strategy caps are finite with
cap/cap+1 fixtures. Root bytes are UTF-8 RFC8785 JCS of the complete root plus
exactly one LF, no BOM/CRLF/extra whitespace, <=64 KiB inclusive. Its
`buildSetHash` is
`SHA-256(ENC("blackops.projection-build-set.v1") ||
ENC(JCS(root excluding buildSetHash)))`; this configured installation
boundary provides integrity, not cryptographic authenticity. Each projection
file is immutable content-addressed generation data; `inputHash` is the
domain-separated JCS hash of its exact kind/schema/build/source-record set.

`build:compile` writes and fsyncs each temp generation, atomically renames it
without overwriting a file referenced by an existing root, fsyncs its parent,
then fsyncs and atomically publishes the root last. The fixed
installation-owned regular nofollow lock file
`.blackops-projection-build-set.lock` was the sole quiescence protocol: readers validated
and shared-flock it before opening the root and all three projection files,
held the lock through same-fd nofollow validation, row selection, and render,
then released; kernel lock release covered crash/exit. Publishers acquired an
exclusive flock before creating or writing any generation temporary/file and
held it through same-fd validation, root publication, parent-directory fsync,
and GC; GC removed only unreferenced paths of the now-current root. A crash
before root publication leaves current readers intact. Lock replacement,
symlink/special lock, interleaving, root-before-files, mixed-generation, and
post-open fixtures are required. Readers take only the configured root and do
not claim to detect uncompiled source drift. One precedence table maps raw cap,
ENOENT, operational I/O, malformed, unsupported, build mismatch, input
identity stale, and available to the six states. `list`/`ls` recovers all six
states with framework rows at exit 0; complete route/schedule artifacts use
exit 2 for all non-operational failures and exit 1 for operational reads.

P23-002B's fixed framework row is already compiled into the dedicated route
projection; P23-002C consumes only the dedicated schedule projection. There is
no runtime HTTP-artifact join or direct Operation Manifest reader. A current
static guard rejects those legacy active source terms while allowing explicit
historical quotations. P23-001S — Stable list documentation truth correction
was a non-1.3 repository-blocking prerequisite. Its Task and Report were
created and historically accepted, then reopened for bounded corrections; the current child files are the status authority. The
former Guide side-effect-free claim is historical and is not positive
evidence; the current Guide is corrected and remains under review.

The future `operation:list --strategy=inline|deferred` option is optional,
case-sensitive, single occurrence, and missing/duplicate/unknown/case-variant
values exit 2 before Provider/compiler work with no output. Absent preserves
Stable no-filter Human bytes/headers/order/exit. A valid filter runs after
existing compilation and before typeId unsigned-byte sorting, emits the same
`Type ID|Definition|Execution Strategy` table (header-only for zero rows),
exit 0, no JSON/public machine wire, DB read, or fallback, and preserves
Provider/compiler exceptions. Fixtures cover no-filter, both values, zero,
invalid/duplicate/missing, and Provider sentinel paths.

### 15.2 Audit storage, lifecycle, and access

P23-003B owns one per-partition active segment. States are exactly
`open|closed|sealed`; close freezes finalSequence/headHash and atomically
rotates to one new open segment under the same partition fence/DB transaction.
Only the current open segment accepts append; stale/fenced writers reload and
never append to closed/sealed. P23-003F consumes a frozen closed segment,
persists one deterministic checkpoint, and fenced-CAS marks sealed. Crash
before rotate commit changes nothing; after rotate/before checkpoint resumes
the same closed boundary; after checkpoint/before seal reuses checkpoint ID,
bytes, and hash. Global partition sequence and predecessor continuity are
required, with exactly one open segment and no gap/fork/overlap fixtures.

Canonical storage uses exact closed `AuditStoredRecord v1` with plaintext
complete AuditRecord JCS+LF and Spec99 BOPD v1 binary. LP is unsigned U32BE
length plus exact bytes. Its custom AAD is exactly:

```text
LP(x)=U32BE(len(x))||x
tenantUnionBytes=0x00|(0x01||LP(type)||LP(id))
previousHashUnionBytes=0x00|(0x01||DECODE32(previousHash))
AAD=LP("blackops.audit.record.aad.v1")||LP("AuditStoredRecord")||LP("1")||
 LP("1")||LP("1")||LP(keyId)||LP(recordId)||LP(partitionId)||
 LP(tenantUnionBytes)||LP(segmentId)||LP(canonicalDecimal(sequence))||
 LP(previousHashUnionBytes)||LP(DECODE32(contentHash))||LP(DECODE32(recordHash))||
 LP(recordedAt)||LP(eventCategory)
```

The three `1` values are schema, envelope, and algorithm; keyId obeys Spec99
1..128-byte safe ASCII bounds, recordedAt is UTC six-fraction ASCII, and
eventCategory is the exact enum. Authorization/tenant/key routing precedes
decrypt; body, row, and recomputed hashes must equal every clear/AAD binding.
System/genesis and tenant/non-genesis full AAD hex/hash goldens plus every
field substitution are required. Complete plaintext is <=1 MiB inclusive;
BOPD overhead is `52+keyIdUtf8Bytes`, maximum envelope allocation/read is
<=1 MiB+180 bytes with cap/cap+1 and multibyte-key fixtures.

`AuditOutboxRecord v1` is durable-only and stores JCS(envelope) without LF in
immutable BOPD v1 `protectedBody`. Its identity, keyId, envelope bytes,
nonce/ciphertext/tag, tenant binding, hashes, and partition are immutable;
only `state,attempt,availableAt,leaseFencingToken,leaseExpiresAt` mutate by
fenced CAS. v1 prohibits re-encryption/rotation; key retention must preserve
decryptability until a future accepted terminal disposal policy, and an old
key failure closes the partition operationally. The state matrix is
`pending` (attempt 0, availableAt set, lease pair null), `leased` (attempt
incremented, availableAt null, lease pair set), and terminal `relayed|poisoned`
(lease pair/availableAt null); fenced transient retry/expired reclaim returns
to pending, no regression or head bypass. Same-key/same-bytes is idempotent;
different values conflict before mutation. Same ciphertext retry, 2-before-1,
parallel relay, lease expiry/stale token, poison, append/ack crash, and BOPD
header/AAD row-tenant substitution fixtures are required.

Operation reads use exactly
`access.operation_{status,journal,outcome,diagnostics}.requested|unavailable|accessed`;
absent and cross-tenant are the same `.unavailable` tuple with
`unknown/unknown/resource_unavailable`, policy `not_applicable/resource_unavailable`,
and `AuditReadContext` decision `resource_unavailable`. Every operation event
has required delivery and canonical append is durable before response. The
closed `AuditAccessContext v1` is
`{schemaVersion:1,kind:audit_access_context,actor,tenant,purpose,requestRef,authorizationDecision}`
with purposes `query|export|sign|verify` and decisions
`allow|deny|resource_unavailable`. Service actions use exactly
`audit.access.{query,export,sign,verify}.requested|unavailable|accessed`;
requested/unavailable are durable and accessed is required and durable before
records, export, signature, or result release. The exact service requestRef
uses its own access domain; no raw target is included. P23-003A owns catalog
tuples; D/F/G own adapters, signing, and orchestration. The finite control
target/role `system:global/audit-control-v1` emits no sign-accessed event;
ordinary signatures remain audited, offline verification makes no canonical
append claim, and access events do not recurse (depth one). Outage/offline/
depth fixtures are explicit.

`AuditRetentionState v1` is closed metadata only:
`{schemaVersion,kind,stateId,target,policy,state,version,previousStateHash,
stateHash,updatedAt,idempotencyKeyHash}`, with target
`{partitionId,startSequence,endSequence,eventCategory|null}`, policy
`{id,version,retentionUntil}`, state
`retained|eligible_for_future_deletion`; holds are not embedded and are stored
in a separate versioned collection, and
`{holdId,reasonCode,placedAt,placedBy}`. Trusted UTC six-fraction times,
positive sequence/version, exact bounds, 64-hex hashes, and P23-003G fenced CAS
are required. Same key/bytes is idempotent; different bytes conflict. Active
hold wins, eligibility races lose to a hold, release reevaluates policy, and
no state deletes/suppresses bytes, keys, query, or export. P23-003H is future.

### 15.3 Verification and export path

`AuditVerificationContext v1` requires closed
`expectedManifest={exportId,manifestHash,purpose}` with purpose exactly
`audit_export_verification`, bounded IDs, and 64-hex hash. It is part of the
contextHash and external expectedContextHash pin. Stage 0 allows a structurally
nullable freshnessSnapshot and checks only internal shape/hash/time (`observedAt
< validUntil`); Stage 1 checks malformed before unsupported; Stage 2 first
checks manifest self-hash then compares the expected pin (mismatch code 16,
zero opens, refs null); Stage 3 checks trusted export key without untrusted
signedAt; Stage 4 signature failure is code 11 with zero opens and null refs,
success makes export refs non-null before authenticated signedAt window check;
Stage 5 opens root-confined same-fd files and checks actual bytes (13); Stage 6
checks catalog, coverage, as-of+freshness code 16, and current null/expired or
bad head set code 18; Stage 7 checks canonical partitions, local 14/15/16/18,
head/chronology and flags. Global failures have `partitions:[]`; local entries
retain four authenticated endpoint refs. All modes require
`sealedAt <= signedAt <= anchoredAt <= receipt.signedAt <= verifiedAt`;
current additionally requires
`receipt.signedAt <= freshness.observedAt <= verifiedAt < validUntil`.
As-of has null freshness and no current/suffix claim; current+null is 18,
as-of+non-null is 16, and current expired is 18. Stage4 failure with bad bytes opens zero; valid
signature with bad bytes reaches Stage5/code13. Unknown manifest `as_of` is
malformed; mode is external and as-of verifies the full included graph.

Manifest identity is only authenticated `{role,artifactId}` plus raw digest;
basename/role-prefix identity is forbidden. Paths are unique root-relative
POSIX UTF-8 components sorted by unsigned path bytes, rejecting empty, `.`,
`..`, backslash, absolute, symlink/special, and pre-open races. The four
endpoint refs derive from each signed segment's singular complete checkpoint
and receipt; one segment has byte-identical start/end refs, multi-segment uses
first/last, and malformed body returns local 15 with authenticated refs.
`files` uses one records/checkpoint/receipt file per segment plus catalogs.

The schema grammar cannot detect a semantically secret value in an otherwise
valid string. Typed Framework adapters/projectors reject raw credentials/tokens
before construction; Application mapper/review owns semantic mapping. No
automatic verifier secret-absence, compliance, or non-recording claim is made;
offline syntax validation is not secret detection. At that historical
checkpoint, Remaining required an exact-8 Local Gate freeze, then creation and
execution of the separate P23-001S Stable Guide correction, fresh Sol
General/Audit review, and Orchestrator acceptance before final P23-001
Acceptance, followed by P23-001R/downstream.

## 16. Historical Round 13 bounded correction checkpoint

Current checkpoint: 2026-08-19T19:13:27+09:00

Round 13 was the then-current `Correction Implemented / Review Requested`
status. Round 12 is historical Changes Requested with General
`P1=5/P2=1/P3=0` and Audit `P1=6/P2=3/P3=2`; those lanes are separate and
never aggregated. P23-001S is reopened from historical acceptance and is also
Review Requested. Stable `1.2` is current, Phase 23/BlackOps `1.3` is an
unreleased proposal, and this document is not Green, Accepted, or Decided.

### 16.1 ProjectionBuildSet and operation strategy

The only projection wire is the closed `ProjectionBuildSet v1` root
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
`kind=projection_build_set` and exact kinds
`blackops_command_projection`, `blackops_route_projection`, and
`blackops_schedule_projection`. The root self-hash is
`SHA-256(ENC("blackops.projection-build-set.v1") ||
ENC(JCS(root excluding buildSetHash)))`; stored bytes are UTF-8 RFC8785 JCS
plus exactly one final LF, no BOM/CRLF/extra whitespace, and at most 64 KiB.
Every descriptor/file has a closed kind, `inputHash`, relative path, lowercase
64-hex digest, finite caps, and exact source-record hash preimage. Command
rows are `{name,aliases,description,origin,hidden}`; route rows are
`{method,path,strategy,operationType,origin}`; schedule rows are
`{name,operationType,cron,timezone,strategy}`. All fields are NFC/no-control
bounded; names and aliases use the separator-safe command grammar (no tab,
comma, CR/LF, empty colon segment, or ambiguous raw-wire separator), and
unknown/duplicate/unsorted rows fail closed. Root is <=64 KiB, each projection
is <=16 MiB with 0..4096 rows, and cap/cap+1 fixtures cover every field
family. The configured installation root is an integrity boundary, not
cryptographic authenticity, and stale means only published identity/inputHash
mismatch; source drift is not claimed.

P23-002A owns the shared wire, command projection producer/list implementation,
reader, publisher, and fixed regular no-follow lock
`.blackops-projection-build-set.lock`; P23-002B owns route producer/commands;
P23-002C owns schedule producer/commands and final integration activation. A,
B, and C are one atomic acceptance slice: A/B do not publish or activate a
partial root, and C wires all three into the A-owned publisher before the
complete three-file root and list/route/schedule commands are accepted.
Stable Symfony list remains the active recovery surface beforehand. A/B/C
synthetic component, source-partial, and final `build:compile` full-root
fixtures are required. The route projection already includes the fixed
framework descriptor and Operation HTTP rows; schedule projection is generated
at build time. No active direct Operation Manifest reader or read-time HTTP
artifact/descriptor join exists; those terms are allowed only in explicitly
historical text and a static guard rejects them in active slices.

Publisher protocol is executable on Linux/WSL: acquire exclusive flock on the
regular no-follow `.blackops-projection-build-set.lock` before creating,
writing, or renaming any generation temporary/file; hold it through same-fd
validation, root temp fsync+atomic rename, parent-directory fsync, and GC.
Readers acquire shared flock before root and all three same-fd no-follow
regular opens and hold it through validation, selection, and rendering.
Abandoned temporary/unreferenced generations are removed only under exclusive
flock and only when absent from the now-current root. A publisher crash before
root rename leaves the old root intact; only old-complete or new-complete sets
are observable. Lock replacement/symlink/special, preparation barrier,
publish+GC/resume, crash, and old/new interleaving fixtures are required.
The single status precedence is raw-cap-before-parse, ENOENT `absent`, other
I/O `operational` exit 1, malformed bytes/JCS/closed shape/self-hash/digest,
valid unsupported schema/kind, configured build mismatch, descriptor/file
input stale, and available. List/ls maps all six statuses to framework rows
and exit 0; complete route/schedule artifact failure is exit 2.

The command/route/schedule strategy wire reflects the declared canonical class
name with no leading slash: `Inline::class` becomes `inline`,
`Deferred::class` becomes `deferred`, and all other strategies become
`custom:<canonical-FQCN>`. The complete wire cap is 1024 UTF-8 bytes; 1025 is
an actionable build/migration failure. No-filter includes custom strategies;
the future `operation:list --strategy=inline|deferred` filter matches only the
two exact built-ins and is optional, case-sensitive, single-occurrence, and
validated before Provider/compiler work. Built-in/custom namespaces,
same-short-name custom classes, >64, 1024/1025, route/schedule, no-filter,
both-filter, zero-row, duplicate/missing/unknown/case-variant fixtures are
required.

### 16.2 Audit segment, evidence, storage, and access

Evidence descriptor presence is exact: required fields must be present;
optional fields may be absent; a present null is allowed only when nullable;
unknown fields fail. The shared writer/catalog/verifier matrix covers every
required/optional × present/missing/null combination, integer/boolean null,
and string classification. `safe_code` and derived `opaque_id` remain typed;
however, a grammar-valid value can still be semantically secret. Typed
framework adapters reject raw credentials/tokens before construction, while
Application mappers/review own semantic classification; syntax verification
does not detect secrets or prove compliance/non-recording.

`AuditSegmentState v1` is the exact closed object
`{schemaVersion,kind,partitionId,segmentId,state,firstSequence,finalSequence,
headHash,predecessorRecordHash,nextSegmentId,version,closeFencingToken}`.
Stored `state` is only `open|closed|sealed`; `closed_unsealed` is a derived
operational label and never a stored/CAS value. An open segment may have null
final/head and has null next/close token; close requires nonempty records,
freezes final/head, sets nextSegmentId and a close token, and atomically opens
the successor. Sealed preserves the closed boundary. Version is a positive
CAS value; predecessor is null only at partition genesis. Partition sequence
starts at 1 only at genesis and continues +1 across rotation; a new segment's
firstSequence is old final+1 and predecessor is old head. There is exactly one
open segment. P23-003B owns append/rotate; P23-003F consumes a frozen closed
segment, persists one deterministic checkpoint, then seals it. Sign/anchor/
trust/freshness failure persists closed, rejects append, and permits retry.
Empty-close, stale fence, rotate crash, checkpoint-before-seal, and continuity
fixtures are required.

Canonical transport is the closed `AuditStoredRecord v1` row from the earlier
contract. Its plaintext is complete AuditRecord JCS+LF and its BOPD v1 custom
AAD uses unsigned-U32BE `LP` over the exact ordered domain, purpose,
schema/envelope/algorithm/keyId, record/partition/tenant union, segment,
canonical decimal sequence, previous-hash union, content/record hashes,
recordedAt, and eventCategory. The tenant union is `0x00` or
`0x01||LP(type)||LP(id)`; previous-hash union is `0x00` or
`0x01||DECODE32(hash)`. Authorization and expected partition/key routing
precede decrypt; complete body, row, and recomputed hashes must match after
decrypt. System/genesis and tenant/non-genesis full hex/hash goldens plus
each-field substitution fixtures are mandatory. BOPD overhead is
`52+keyIdUtf8Bytes`, logical cap is 1 MiB inclusive, and envelope allocation is
bounded at 1 MiB+180 bytes before read/decrypt.

`AuditOutboxRecord v1` is durable-only. Ingress starts `pending,attempt=0`;
each successful fenced claim increments attempt, retry/reclaim returns to
pending with that nonnegative value, and only leased rows contain both lease
token and expiry. Terminal relayed/poisoned rows have null lease pair; no
head bypass, state regression, stale-token ack, or expired-owner write is
allowed. Identity, key ID, envelope version/algorithm, nonce, ciphertext, tag,
and protected body are immutable. v1 prohibits re-encryption/rotation; key
retention must preserve decryptability until a future accepted disposal
policy. Same-key/same-bytes is idempotent, different value conflicts before
mutation. Retry, two-before-one, parallel, lease, poison, append/ack crash,
and BOPD header/AAD row/tenant substitution fixtures are required.

Operation reads emit exactly
`access.operation_{status,journal,outcome,diagnostics}.requested|unavailable|accessed`.
Absent/cross-tenant is the same durable `resource_unavailable` tuple with
phase/status `unknown`, policy decision `not_applicable`, and no existence
claim. Service actions emit exactly
`audit.access.{query,export,sign,verify}.requested|unavailable|accessed`;
requested/unavailable are durable and accessed is required before release.
The closed AuditReadContext v1 includes requestedAt and nonce, and its only
requestRef preimage is
`LP("blackops.audit.read.request-ref.v1") || LP(JCS({purpose,actor,execution,tenant,requestedAt,nonce}))`
with no target. P23-003A owns catalog tuples; P23-003D owns the operation
adapters; P23-003F owns checkpoint/anchor signing; P23-003G owns query/export/
hosted verify and access events before release. Only ordinary signatures emit
`audit.access.sign.accessed`; framework-derived target/role
`system:global/audit-control-v1` is the finite no-event control close. Offline
verify has no canonical append. Access-event append is depth one and does not
recurse.

`AnchorIntent v1` is a closed object with exactly
`{schemaVersion,kind,intentId,state,partitionId,checkpointId,checkpointHash,
checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,
expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,
acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,
receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,
leaseExpiresAt,version,updatedAt}`. IDs, refs, tokens, and the idempotency key
are NFC/no-control bounded noncredential strings; hashes are lowercase 64-hex;
versions are positive integers; times are UTC with exactly six fractional
digits. `receiptSigningKeyRef` is the only signing-key field and no external
credential is stored. Genesis has null predecessor fields and accepted version
1; otherwise predecessor version/hash are required and accepted version is
predecessor version plus one. `acceptedObjectHash` equals
`checkpointBytesHash`, and the accepted object tuple is stable.

The state/nullability matrix is exact: `prepared` has accepted object version,
hash, time and all four receipt fields null; `externally_accepted` has the
accepted triplet non-null and the four receipt fields null;
`receipt_inputs_persisted` has the accepted triplet plus
`receiptSignedAt,receiptSigningKeyRef,receiptBytesHash` non-null and
`anchorReceiptHash` null; `receipt_persisted` and `acknowledged` have all
accepted and receipt fields non-null. `receiptBytesHash` is the lowercase
SHA-256 of `ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||`
the exact unsigned receipt JCS+LF bytes; `anchorReceiptHash` is the semantic
domain hash of the unsigned receipt and never the signed-file raw digest.
`receipt.signedAt` equals persisted `receiptSignedAt`,
`acceptedAnchoredAt<=receiptSignedAt`, and the pinned ID/key/body/hash remain
byte-identical after retry. The lease fields are both null at rest or both
non-null for one active fenced claim; expired reclaim obtains a new token by
CAS and stale owners cannot write. Version CAS forbids regression. The three
external-accept/inputs-persisted, receipt-persist, and ack crash points,
sign-before-persist, read-after-write, parallel retry, and no-N+1 fixtures are
required.

`AuditQueryRequest v1` is the closed object
`{schemaVersion:1,kind:audit_query_request,accessContext,partitionId,
eventCategory,fromSequence,toSequence,snapshotHeadSequence,limit,cursor}`;
`AuditQueryResult v1` is the closed object
`{schemaVersion:1,kind:audit_query_result,status,partitionId,records,
snapshotHeadSequence,hasMore,nextCursor,reasonCode}`. Bounds are positive
JSON-safe sequences, `fromSequence<=toSequence`, `limit` 1..1000, category enum
or null, and first/continuation nullability is exact. P23-003G owns both
objects and a closed authenticated cursor. The cursor binds the complete
query-scope digest, fixed head, last sequence, continuation position, raw cursor
digest, and issuer requestRefDigest; validation has one total precedence from
raw cap/shape through trusted authorization, partition/scope, raw cursor,
key/HMAC/window, authenticated binding/replay, and finally store selection.
Cross-tenant swap, invalid cursor, decrypt/integrity failure, and unavailable
resource are explicit safe outcomes. `AuditRetentionState v1` remains metadata-only. Its
hold record is the closed object
`{schemaVersion:1,kind:audit_hold,holdId,partitionId,startSequence,endSequence,
eventCategory,reasonCode,placedAt,placedBy,state,version,previousStateHash,
stateHash,idempotencyKeyHash}`; `state` is exactly `active|released` and the
hold identity is immutable. Holds are immutable records keyed by holdId with
`active|released` state;
multiple overlapping holds are allowed, category null is wildcard, and any
matching active hold makes a record ineligible. Releasing one hold never
releases another. P23-003G owns fenced CAS/idempotency, race/retry/release,
overlap, cutoff, category, cap, and sealed-byte/key invariant fixtures;
physical deletion remains future P23-003H.

### 16.3 Verifier and manifest ordering

Every mode Stage 0 validates a bounded `trustStoreSnapshot` and requires
`observedAt <= verifiedAt < validUntil` before any manifest/listed-file open;
violation is code 19. Freshness snapshot shape/hash is Stage 0 only; duplicate,
unsorted, missing, extra, and exact coverage/head-set checks are Stage 6 after
authenticated manifest/catalog, with current null/expired/head mismatch code
18 and as-of non-null freshness code 16. Stage 2 self-hashes the parsed
candidate manifest, then constant-time compares its `exportId` and
`manifestHash` to required external `expectedManifest`; mismatch is global
code 16, zero listed-file opens, refs null. Stage 3 export-key availability/
role/status is global code 12; Stage 4 signature failure is code 11, zero
opens, refs null; only signature success makes export refs non-null, after
which authenticated signedAt window failure is code 12. Stage 5 opens
root-confined same-fd regular files and checks actual lengths/sums/digests;
Stage 6 checks catalog/coverage/head-set; Stage 7 checks each partition in
canonical unsigned-UTF8 order and local 14/15/16/18 precedence. Global failure
has `partitions:[]`; local failure retains signed endpoint refs. All modes
require sealedAt<=checkpoint signedAt<=anchoredAt<=receipt signedAt<=verifiedAt;
current additionally requires receipt signedAt<=freshness observedAt<=verifiedAt
<validUntil. As-of verifies the full authenticated included graph without a
current/suffix claim.

Manifest identity is only the authenticated signed `{role,artifactId}` mapping
and raw digest. Basename or role-prefix identity is forbidden. Paths are unique
root-relative POSIX UTF-8 components sorted by unsigned path bytes, rejecting
empty, `.`, `..`, backslash, absolute, symlink/special, and pre-open races.
Every segment has one complete checkpoint and receipt ref; one segment copies
the same refs to start/end and multiple segments use first/last. Checkpoint/
receipt body substitution or malformed body returns local code 15 with refs
derived only from the signed manifest. No manifest `as_of` property exists;
mode is external and unknown members fail closed. Multi-partition segments and
start boundaries use canonical unsigned UTF-8 partition order, then chain
order within each partition.

Historical Round 22 was the then-current parent `Correction Implemented /
Review Requested` checkpoint, and its fresh complete Round 22 Local Gate was
PASS. Round 21 and earlier are historical review checkpoints; P23-001S is the
accepted child after fresh child General `P1=0/P2=0/P3=0`. Its historical
Remaining was parent General/Audit review and parent Orchestrator acceptance;
only then P23-001R and ordered downstream. Its historical Suggested Next
Action was to preserve the frozen exact-15 scope and request independent
parent reviews. No 1.3 capability was public or released.

## Historical Round 14 bounded correction checkpoint

Current checkpoint: 2026-08-19T19:56:30+09:00

At that historical checkpoint, Round 14 was `Correction Implemented / Review
Requested`. Round 13 is historical Changes Requested with General
`P1=1/P2=1/P3=0` and Audit `P1=4/P2=2/P3=0`, separately. P23-001S was
created and historically accepted, then reopened for bounded corrections;
its current status is Review Requested. Stable `1.2` is current and every
Phase 23/`1.3` capability remains an unreleased proposal. This specification
is not Green, Accepted, or Decided.

### 17.1 One projection wire and reader lifecycle

`ProjectionBuildSet v1` is the only active projection wire. It contains the
closed root `{schemaVersion,kind,applicationBuildId,buildSetHash,projections}`
with `kind=projection_build_set` and exactly
`blackops_command_projection`, `blackops_route_projection`, and
`blackops_schedule_projection`. Each descriptor/file has a closed kind,
relative root-confined path, lowercase 64-hex digest, and descriptor/file
`inputHash`; no standalone projection envelope or read-time join is active.
The route projection combines Operation HTTP rows with its framework-fixed
descriptor at build time. The schedule projection is generated from schedule
metadata at build time. Readers consume only the dedicated projection file
named by the root and never read an Operation Manifest, runtime HTTP artifact,
source, provider, container, database, or clock.

The stored root is UTF-8 RFC8785 JCS plus exactly one final LF, with no BOM,
CRLF, or other whitespace, and is capped at 64 KiB inclusive. The
self-hash omits that storage LF. Projection files are capped at 16 MiB and
4096 rows; every command, route, and schedule field has a bounded NFC,
no-control UTF-8 grammar and rejects unknown, duplicate, unsorted, cap, and
cap+1 input. The command/route/schedule strategy wire is injective: a declared
class name without a leading slash maps to `inline`, `deferred`, or
`custom:<canonical-FQCN>`, with a complete 1024-byte cap. `--strategy=inline|
deferred` matches only exact built-ins; no-filter includes custom values.

The fixed regular no-follow lock is
`.blackops-projection-build-set.lock`. A reader obtains shared `flock` before
opening root and all three same-fd regular files and holds it through
validation, selection, and rendering. A publisher obtains exclusive `flock`
before creating, writing, or renaming any generation temporary/file and holds
it through same-fd validation, root temp fsync plus atomic rename,
parent-directory fsync, and GC. GC removes only paths unreferenced by the
current root and bounded abandoned temporary cleanup under that lock. A
publisher crash before root publication leaves the old complete set. Only an
old complete or new complete set is visible. A/B/C component barriers,
publisher crash, lock replacement/special-file, mixed-generation,
root-before-artifact, and reader/publisher interleaving fixtures are required.

### 17.2 Segment, store, and outbox

`AuditSegmentState v1` is the closed object
`{schemaVersion,kind,partitionId,segmentId,state,firstSequence,finalSequence,
headHash,predecessorRecordHash,nextSegmentId,version,closeFencingToken}`.
Stored `state` is only `open|closed|sealed`; `closed_unsealed` is a derived
display label. There is exactly one open segment per partition. Sequence is
partition-global: it starts at 1 only at genesis, increments across rotation,
and the new segment uses `firstSequence=old.finalSequence+1` and
`predecessorRecordHash=old.headHash`. Open may be empty with null final/head;
close requires a nonempty segment, atomically freezes final/head, sets the
next segment and close fencing token, and opens the successor under the same
partition fence. Sealed preserves the closed boundary. Stale append, empty
close, rotate crash, checkpoint-before-seal, and continuity fixtures are
required; P23-003B owns rotate/fence and P23-003F consumes only closed data.

Canonical transport is the closed `AuditStoredRecord v1` row
`{schemaVersion,kind,recordId,partitionId,tenantBinding,segmentId,sequence,
previousRecordHash,contentHash,recordHash,recordedAt,eventCategory,
protectedBody}`. `recordId` and `segmentId` are 1..256-byte NFC/no-control
strings; `partitionId` is `system:global|tenant:<64 lowercase hex>`;
`eventCategory` equals the decrypted category and `recordedAt` equals the
decrypted recordedAt. Plaintext is complete AuditRecord JCS+LF. The exact
canonical BOPD v1 AAD uses `LP=U32BE(byte_length)||bytes` and is
`LP("blackops.audit.record.aad.v1")||LP("AuditStoredRecord")||LP("1")||
LP("1")||LP("1")||LP(keyId)||LP(recordId)||LP(partitionId)||
LP(tenantUnionBytes)||LP(segmentId)||LP(canonicalDecimal(sequence))||
LP(previousHashUnionBytes)||LP(DECODE32(contentHash))||LP(DECODE32(recordHash))||
LP(recordedAt)||LP(eventCategory)`. `tenantUnionBytes` is `0x00` for null or
`0x01||LP(type)||LP(id)`; `previousHashUnionBytes` is `0x00` or
`0x01||DECODE32(hash)`. Key IDs are 1..128 bytes, logical records are <=1 MiB,
and complete BOPD allocation is <=1 MiB+180 bytes. Authorization, tenant/key
routing, and expected partition precede decrypt; row/body/hash equality is
rechecked after decrypt.

The system/genesis golden uses `k1,r1,system:global,null,s1,1,null,0×32,
1×32,2026-08-19T00:00:00.000000Z,control`: 232 AAD bytes and SHA-256
`5930076af5418572a4e575943172884775428ad10d024de7eb9fc419bce04b1c`.
The tenant/non-genesis golden uses `k2,r2,tenant:` plus 64 `a` hex digits,
`{type:account,id:tenant-a},s2,2,2×32,3×32,4×32,
2026-08-19T00:00:01.000000Z,access`: 344 AAD bytes and SHA-256
`f3ef3d96598964f7087ffe654ecde4d2e828caebfa981ecb1daa82859ff7c57f`.
Every LP field, union marker, key, tenant, sequence, hash, time, and category
substitution is a negative fixture.

`AuditIngressEnvelope v1` is durable-only; required delivery passes the same
logical AuditRecord directly to canonical append. `AuditOutboxRecord v1` has
clear bounded identity/index fields and a binary BOPD v1 protected body whose
plaintext is JCS(envelope) without LF. Its identity, key ID, envelope bytes,
nonce, ciphertext, and tag are immutable in every state. v1 prohibits
re-encryption or rotation; key retention must preserve decryptability until a
future accepted terminal-disposal policy. Ingress is `pending,attempt=0,version=1`;
pending attempt is `0..maxAttempts-1`, while leased/relayed/poisoned attempt is
`1..maxAttempts`; each fenced claim maps `n -> n+1`, and a persisted/decoded
pending row at max is invalid. Only leased rows have a non-null
lease token/expiry pair. Terminal rows have both null. Fenced transient retry
returns pending, no ordinal head bypass or stale ack is allowed, and ordinary
retry reuses the same ciphertext.

### 17.3 Access/query and retention exactness

The active event family is exactly
`audit.access.<purpose>.requested|unavailable|accessed`. For all four
Status/Journal/Outcome/Diagnostics read surfaces, same-target authorization
is `.accessed`, policy rejection is `.unavailable` only when the resource is
absent/cross-tenant, and absent/cross-tenant use the same durable
`resource_unavailable` tuple with `action.phase=unknown`,
`outcome.status=unknown`, `policy.decision=not_applicable`, and no existence
claim. `requested` and `unavailable` are durable; `accessed` is required and
durably persisted before releasing service-managed query/export/sign/hosted-
verify output. Required append failure withholds that output. Former
`resource_not_found` and `.allow|.deny` names are historical only. Offline
verification has no canonical append and no access-event delivery.

`AuditAccessContext v1` is the sole current query context object
`{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,target,
authorizationDecision,requestedAt,nonce,requestRef}`. This wire fixes
`purpose="query"` and `authorizationDecision="allow"`; export, sign, and
verify require separately versioned exact wires and cannot reuse this target.
`target` is the complete target-bearing `AuditAccessTarget v1` above. The
context is generated by the trusted `AuthorizationEvaluator`, not supplied by
the caller. Its requestRef is derived after the decision from the exact
target-bearing JCS fields with domain `blackops.audit.access.request-ref.v1`;
requestRef is a binding digest, not authentication proof. The current global
golden is the target-bearing 311/348 scope and 374/418 Access input with
requestRef `oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`.
Node/Docker-PHP parity, external allow/requestRef injection, target/tenant
substitution, and historical target-less old-103 negative fixtures are owned
by P23-003G.

`AuditQueryRequest v1` is the closed UTF-8 JCS object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
limit,order,snapshotHeadSequence,cursor}`. `access` is the exact target-bearing
`AuditAccessContext v1`; `partitionId` is the only partition derived from its
tenant. `filters`, `order`, and `limit` project the closed `AuditQueryScope v1`;
all members are present, `order="sequence_asc"`, and `limit` is 1..1000.
First/continuation page nullability is exact. Validation order is raw request
cap and shape, trusted authorization provenance/freshness/replay,
partition/query-scope, raw cursor cap/grammar/digest, key resolution/role/HMAC/
payload/window, authenticated scope/head/position/request-selection and cursor
replay, then store selection. Bad HMAC wins over authenticated payload
time/window failures; every earlier error performs zero lookup/decrypt. Unknown
fields, target/partition swaps, invalid filters, and complete request JCS over
64 KiB fail before lookup.

`AuditQueryResult v1` is the closed object
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,hasMore,nextCursor,reasonCode}`. Its complete state
matrix is:

| status | partitionId | snapshotHeadSequence | records | nextCursor | reasonCode |
| --- | --- | --- | --- | --- | --- |
| `ok` | authorized non-null partition | captured head including 0 | non-empty array of 1..limit complete canonical `AuditRecord v1` objects | non-null exactly when more rows remain | null |
| `empty` | authorized non-null partition | captured head including 0 | `[]` | null | null |
| `unavailable` | null | null | null | null | `resource_unavailable|record_too_large` |

The `ok` records are complete closed canonical `AuditRecord v1` projections,
validated and decrypted only after authorization, ordered by ascending
partition-global sequence, and never partially returned. The unavailable row
does not reveal a target partition, existence, or a decryption result.
The complete result JCS plus one LF is at most 1 MiB inclusive; cap+1 is
rejected before allocation. A concurrent append after the captured snapshot
head is excluded from that query and requires a new first page after the
cursor is exhausted.

The cursor wire is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, with one
literal dot, no padding, ASCII, and total length at most 2048 bytes inclusive.
The closed payload is
`{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`; all fields and
filters have the request bounds, `lastSequence` is positive, and
`issuedAt<expiresAt` uses UTC six-fraction timestamps. The tag is exactly
32 bytes of HMAC-SHA-256 under the role-bound `audit-query-cursor` key selected
by `keyId`; the secret key is exactly 32 bytes. The tag is computed over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`, where LP is
unsigned U32BE byte length plus exact UTF-8 bytes. The verifier strictly
decodes canonical base64url, closed JCS, key role, bounds, expiry, and then
compares the tag in constant time before any database lookup or record
decryption. HMAC authentication of a cursor is not record decryption or
integrity verification. First/middle/final/empty/unavailable, tag/payload
malformation, tenant/filter/head/last substitution, expiry, key rotation,
and cap+1 fixtures are required; `nextCursor` is present only for an `ok`
page with more rows.

`AuditRetentionState v1` is
`{schemaVersion:1,kind:"audit_retention_state",stateId,target,policy,evaluator,
owner,evaluatedAt,policySnapshotRef,policySnapshotHash,policySnapshotVersion,
holdSnapshotRef,holdSnapshotHash,holdSnapshotVersion,evaluationFenceVersion,
result,reasonCode,state,version,previousStateHash,stateHash,updatedAt,
idempotencyKeyHash}` with only `retained|eligible_for_future_deletion` and the
closed reason matrix. `stateHash` is
`H(ENC("blackops.audit.retention-state.v1")||ENC(JCS(object excluding
stateHash)))`; previous hash is null iff version 1 and otherwise the prior
state hash. Raw idempotency input is 1..256 NFC/no-control bytes and its clear
hash is `H(ENC("blackops.audit.retention-idempotency.v1")||ENC(partitionId)||
ENC(rawKey))`. `(stateId,target)` is immutable identity and complete JCS is
<=64 KiB. Revision/version 1 uses the same four-branch truth table, not an
unconditional retained result. One trusted `evaluatedAt` is captured; every
policy/hold snapshot `capturedAt` is <= it, and future snapshot time or
evaluation-clock rollback creates no revision. Snapshot refs, versions, hashes,
and evaluation fence must equal the authenticated closed snapshots exactly;
policy is null only for the authenticated policy-unavailable tag. `AuditHold v1` is a separate append-only revision object keyed by
holdId, with exact `active|released` state, version/previous/state hashes and
CAS; multiple active holds overlap, category null is wildcard, and release of
one hold never releases another. Eligibility is retention eligible plus zero
matching active holds in one snapshot. Physical deletion is future P23-003H.

### 17.4 Anchor and verifier total function

`AnchorIntent v1` is the exact closed object
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,
checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,anchorObjectRef,
expectedPredecessorVersion,expectedPredecessorHash,conditionalCreateToken,
idempotencyKeyHash,acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,
anchorReceiptId,segmentId,previousAnchorReceiptHash,receiptSigningKeyRef,
receiptSignedAt,receiptPreimageHash,receiptBytesHash,anchorReceiptHash,
leaseFencingToken,leaseExpiresAt,attempt,nextAvailableAt,terminalReasonCode,
version,previousStateHash,stateHash,updatedAt,acknowledgedAt}`. IDs, refs, tokens, and idempotency values
are bounded NFC/no-control noncredential strings; hashes are lowercase 64-hex,
versions positive, and times UTC with exactly six fractional digits. Only
`receiptSigningKeyRef` names a signing key; external credentials are never
stored. Genesis has null predecessor fields and accepted version 1; otherwise
predecessor version/hash are required and accepted version is predecessor+1.
`acceptedObjectHash` equals `checkpointBytesHash`.

The state/nullability matrix is closed: initial/retryable `prepared` has
accepted/receipt/chain fields null, attempt 0 or positive, and a lease only
during its claim; `externally_rejected` has all accepted/receipt/chain fields
null, preserves positive attempt, has a closed terminal reason, and is
terminal; `externally_accepted` has accepted fields and `anchorReceiptId`
non-null, `segmentId` non-null, and the exact predecessor receipt hash or null
at authenticated chain genesis; `receipt_inputs_persisted` adds signing key,
signed time, and internal receipt hashes; `receipt_persisted` adds
`anchorReceiptHash`; `acknowledged` adds `acknowledgedAt`. Prepared is the only
external submission state. A claim uses expected version/hash and token CAS,
increments version exactly once, increments attempt only for external
submission, and a retryable unavailable result clears the lease while
preserving attempt. At attempt 32 unavailable/reclaim becomes
`externally_rejected/attempt_exhausted`; terminal states are unclaimable.
`receiptBytesHash` is
`SHA-256(ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||`
the exact unsigned receipt JCS+LF bytes); `anchorReceiptHash` is the unsigned
receipt semantic domain hash, not a signed-file raw digest. The receipt
`signedAt` equals persisted `receiptSignedAt`, `acceptedAnchoredAt<=signedAt`,
and ID/key/body/hash remain byte-identical after retry. Lease fields are both
null at rest or both non-null for one active fenced claim; expired reclaim gets
a new fencing token by CAS and stale owners cannot write. Version CAS forbids
regression. Anchor lease duration is `1..86400` seconds inclusive, default
`300`, computed overflow-safe from one trusted claim clock; every successful
revision captures one trusted transition time, rejects rollback, and sets
`updatedAt`. Same-body/idempotency response-loss retry returns the existing
revision without N+1 and preserves version, updatedAt, and stateHash. The
external-accept/inputs-persisted, receipt-persist, ack,
sign-before-persist, read-after-write, parallel retry, and no-N+1 fixtures are
required.

Every invocation Stage 0 validates `mode`, bounded context, expected context
hash, and `trustStoreSnapshot.observedAt<=verifiedAt<validUntil`, before any
manifest/listed-file open (malformed code 19). Stage 1 validates raw manifest
shape/JCS/bounds with malformed code 10 before unsupported code 17. Stage 2
self-hashes the parsed candidate, then compares signed `exportId` and
`manifestHash` to required external `expectedManifest`; mismatch is code 16,
zero listed-file opens, refs null. Stage 3 export-key role/status is global
12. Stage 4 signature failure is code 11 with zero file opens and refs null;
only signature success makes export refs non-null, after which authenticated
signedAt key-window failure is code 12. Stage 5 opens each listed file once
through one root-confined regular fd and retains its bytes for later stages;
actual length/sum/raw digest failure is code 13. Stage 6 never reopens files
and orders global failures as catalog graph missing/extra/hash/semantic
mismatch code 14, mode/coverage code 16, then freshness head-set/current
freshness code 18. Every Stage 6 global failure returns `partitions:[]` with
post-signature refs non-null. Stage 7 uses canonical partition order and
local 14 chain, 15 checkpoint/anchor/key/time, 16 scope, 18 head precedence.
All modes require
`sealedAt<=checkpoint.signedAt<=anchoredAt<=receipt.signedAt<=verifiedAt`;
current additionally requires
`receipt.signedAt<=freshness.observedAt<=verifiedAt<validUntil`. As-of has
null freshness and no current/suffix claim. Manifest identity is only signed
`{role,artifactId}` plus raw digest; basename and role-prefix identity are
forbidden. Paths reject empty, `.`, `..`, backslash, absolute, symlink,
special-file, and pre-open race cases. Stage 5 bytes are not reopened in
Stage 6. Multi-partition arrays use unsigned UTF-8 partition order, then
checkpoint-chain order.

## Historical Round 15 bounded correction checkpoint

Historical status at that checkpoint was `Correction Implemented / Review
Requested`; Round 15 was then-current and is now superseded by Round 16. It
was not Green, Accepted, or Decided. Stable 1.2 is current; every
BlackOps-owned 1.3 capability in this document remains an unreleased
proposal.

The active projection contract has one `ProjectionBuildSet v1` wire and three
dedicated files: `blackops_command_projection`, `blackops_route_projection`,
and `blackops_schedule_projection`. Route fixed descriptors and Operation HTTP
rows are combined at build time; schedule metadata is compiled at build time.
Readers consume only their named projection and never join runtime HTTP data
or read a direct Operation Manifest. Shared reader/publisher quiescence uses
the regular no-follow `.blackops-projection-build-set.lock`: readers hold
shared flock through root/file same-fd validation, selection, and rendering;
publishers acquire exclusive flock before any generation write or rename and
hold it through root fsync/atomic publication, directory fsync, and bounded GC.
Only complete old/new sets are visible. The future
`operation:list --strategy=inline|deferred` filter is one optional,
case-sensitive occurrence; invalid, duplicate, missing, or case-variant input
exits 2 before Provider/compiler work, while no-filter preserves Stable output.

The request reference is the one formula
`oid:v1:HEX_LOWER(SHA-256(LP("blackops.audit.access.request-ref.v1")||
LP(JCS({purpose,requesterActor,requesterTenant,requestedAt,nonce}))))`, with
U32BE length-prefixed exact bytes, UTC six-fraction `requestedAt`, and a
1..128-byte NFC/no-control noncredential nonce. Its independently reproduced
null/null vector is a 121-byte JCS and 165-byte LP preimage with SHA-256
`692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.

`AuditQueryRequest v1` is the closed JCS object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}`. `AuditQueryResult v1` has the exact
`ok|empty|unavailable` matrix: ok has an authorized partition/head, nonempty
complete canonical AuditRecord v1 projections, next cursor only when more
rows remain, and null reason; empty has the same partition/head with empty
records and null next/reason; unavailable has null partition/head/records/next
and `resource_unavailable`. Result JCS+LF is capped at 1 MiB inclusive.
The cursor wire is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, at most 2048
ASCII bytes, with closed payload
`{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}` and 32-byte
role-bound HMAC-SHA-256 under an exactly 32-byte secret key selected by a
1..128-byte NFC/no-control ASCII `keyId`, over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`. Strict decoding,
constant-time comparison, bounds, expiry, and key-role checks precede lookup;
filter strings and cursor payload strings are NFC/no-control and capped at 256
UTF-8 bytes unless a smaller field bound is stated. HMAC cursor authentication
is not record decryption or integrity verification.
First/middle/final/empty/unavailable and substitution/cap fixtures are
required.

`AnchorIntent v1` is the exact closed object
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,
checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,anchorObjectRef,
expectedPredecessorVersion,expectedPredecessorHash,conditionalCreateToken,
idempotencyKeyHash,acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,
anchorReceiptId,segmentId,previousAnchorReceiptHash,receiptSigningKeyRef,
receiptSignedAt,receiptPreimageHash,receiptBytesHash,anchorReceiptHash,
leaseFencingToken,leaseExpiresAt,attempt,nextAvailableAt,terminalReasonCode,
version,previousStateHash,stateHash,updatedAt,acknowledgedAt}` with only
`receiptSigningKeyRef` as signing-key field and states
`prepared|externally_accepted|externally_rejected|receipt_inputs_persisted|
receipt_persisted|acknowledged`. Prepared starts at version 1/attempt 0;
external claims increment attempt exactly once, internal receipt claims preserve
it, and unavailable at attempt 32 becomes externally_rejected/attempt_exhausted.
Every mutation is expected version/hash plus matching-token CAS with exact +1;
terminal states are unclaimable. Accepted object hash equals checkpoint bytes
hash, and same-body/idempotency crash retry preserves version, updatedAt,
stateHash, and receipt bytes without N+1.

P23-001S was created and historically accepted, then reopened for bounded
corrections; its current child packet is `Round 21 Accepted`. The former
side-effect-free Guide sentence is historical and the corrected Guide passed
fresh child General review. All four read families use
`.requested|.unavailable|.accessed` and the shared `resource_unavailable`
tuple; former allow/deny/resource-not-found forms are historical only.

## Current technical scope note

This exact3 source is an unreleased proposal document change. It claims no
runtime implementation and does not change the Stable `1.2` boundary. Workflow
and gate status, remaining work, and next action are maintained only by
`develop/STATE.md` and the parent Task/Report.

## Technical handoff

The active technical contracts above are handed to their P23-003 owners for
implementation and fixture planning; this document does not authorize runtime
or release work.

## Historical Round 26 Remaining Issues

Round 26 was the then-current parent `Correction Implemented / Review Requested`
checkpoint; Round 25 and earlier are historical evidence with separate review
lanes. Parent acceptance is not yet complete. Remaining: Orchestrator must
rerun the complete Local Gate after this source-changing management correction,
then route fresh separate Sol xHigh General/Audit review and parent acceptance;
P23-001R and ordered Phase 23 work follow acceptance. No 1.3 capability is
public or released.

## Historical Round 26 Suggested Next Action

Run the complete Local Gate against this exact Round 26 candidate, then route
fresh separate Sol xHigh General/Audit review and parent acceptance. After
acceptance, route P23-001R and ordered Phase 23 work; no external action is
authorized before review.

## Historical Round 16 bounded correction checkpoint

Updated At: 2026-08-19T21:18:38+09:00

Round 16 was the then-current `Local Gate Green / Independent Review Requested`
checkpoint, not Accepted or Decided, and is now historical. The exact requestRef formula is
unchanged, but the independently recomputed null/null vector is literal JCS
121 UTF-8 bytes, LP preimage 165 bytes, and SHA-256
`692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.
The Round15 149-byte/`b063819f...` result is historical invalid diagnostic
evidence from shell quote loss, not a contract. Query/cursor and closed
AnchorIntent schemas remain exact, and all 1.3 work remains unreleased.

The fresh complete exact-15 Local Gate is Green. Remaining: fresh child General
review, parent General/Audit review, and Orchestrator acceptance. Suggested
Next Action: freeze the exact-15 hashes and route the reviews.

## Historical Round 18 correction checkpoint

Updated At: 2026-08-19T22:20:07+09:00

Round 18 was the then-current `Correction Implemented / Review Requested`
checkpoint and is now historical Changes Requested (`P1=0/P2=1/P3=0`). The proposal's existing inline
HTML reader guard now applies native closed `details`/`dialog` traversal:
closed `details` expose only their first direct `summary` until `open`, plain
closed dialogs are hidden, and an explicit non-`none` inline display override
can expose a closed dialog while `display:none` remains hidden. Paired
primary/alias fixtures and the prior inline visibility, raw/Search/LLM,
Stable `1.2`, and unreleased `1.3` boundaries remain synchronized. Round 17
is historical Changes Requested.

The fresh complete Round 18 Local Gate is historical. Remaining at that
checkpoint was fresh child General re-review, parent General/Audit review, and
Orchestrator acceptance; then P23-001R and ordered downstream work. No 1.3
capability is public or released.

### Suggested Next Action

The historical next action was to freeze the exact-15 Round 18 Local Gate and
route fresh child General review; Round 19 now supersedes that checkpoint.

## Historical Round 17 correction checkpoint

Updated At: 2026-08-19T21:59:03+09:00

Round 17 was the then-current `Correction Implemented / Review Requested`
checkpoint, not Green, Accepted, or Decided, and is now historical. The child correction was
limited to the observed Stable class-string versus prebuilt Command instance
construction boundary and the paired inline HTML `hidden`/`display` guard.
This proposal remains unreleased; the fresh Round 17 Local Gate is PASS and
independent reviews remain. The Guide wording now states the generation/reuse
boundary is unchanged for every selected list format.

# Specification 106: Phase 23 Delivery Plan

Status: Proposal — unreleased BlackOps 1.3; workflow/gate status authority is the exact5 management slice (`develop/STATE.md`, `develop/TODO.md`, the parent Task/Report, and `develop/spec/README.md`)

Updated At: 2026-09-01T04:40:14+09:00

This plan is a delivery DAG for the capability proposal in Specification 105.
It does not make any Phase 23 item a current Stable `1.2` capability. A slice
is not released merely because its code or documentation exists; it requires
its own implementation review and the gates named here.

Round 21 is the Historical accepted child prerequisite; Round 22 is historical Changes
Requested with parent General `P1=3/P2=1/P3=0` and Audit `P1=4/P2=0/P3=0`.
Round 23 is historical Changes Requested: parent General `P1=2/P2=2/P3=0`
and Audit `P1=3/P2=1/P3=0` remain separate. Round 24 is historical Changes
Requested with General `P1=1/P2=1/P3=0` and Audit `P1=5/P2=1/P3=0`, separately.
Round 25 and Round 26 are historical evidence with separate General and Audit
lanes. This plan is a current technical delivery proposal only. Workflow and
gate status authority is the exact5 management slice: `develop/STATE.md`,
`develop/TODO.md`, the parent Task, the parent Report, and
`develop/spec/README.md`; Search and Audit workflow lanes are not normative
within this delivery plan. Stable `1.2` remains current and every Phase 23
item remains an unreleased proposal.
For an active implementation packet, the exact5 is reconciled at preflight freeze and Acceptance.
Between those boundaries, a packet may freeze Task／Decision／TODO／Spec README as normative input;
only its Report／STATE pair is moving checkpoint authority when the packet says so explicitly.
P23-001R is already Accepted; the ordered Phase 23 work remains.

For every current `ProjectionBuildSet v1` delivery clause in this plan, D146
is the more specific authority. Its A1/A2 split, component injected locator,
C-owned Production locator,
single-basename namespace, current field table, verified-handle policy, lock
initialization ownership, scan limit `256`, and deletion limit `32` supersede
incompatible path/cap and literal `nofollow`／`no-follow` shorthand in
non-historical text. Explicitly historical checkpoints remain evidence only.

For P23-003A delivery, D147 is the more specific authority. P23-003A is now
the serial A1 Catalog then A2 Record／Projection acceptance slice. A1 owns
Catalog-only JCS/hash, the exact Framework security/access catalog, evidence
schema, typed opaque-ID derivation, and the pure AuditReadRequestBasis/
AuditReadContext boundary. A2 consumes Accepted A1 and owns the producer draft,
trusted logical schema validation, semantic resolution, and closed observed
projection. P23-003C later owns the dedicated opaque-ID key provider/injection.
Both A children must be Accepted before parent P23-003A and before P23-003B;
neither activates runtime behavior or marks an A1 type Public API.

For P23-003B delivery, D148 is the more specific authority. P23-003B1 first
delivers the pure canonical record／hash／Audit-specific protection／ingress wire
foundation. Only after B1 Acceptance may P23-003B2 deliver PostgreSQL append、
partition-global chain、segment rotation／fence、tenant-aware read、and migration.
Parent B requires both children. Initial B1 fresh Sol Max preflight returned
Changes Requested `P1=4/P2=1/P3=1`. The prior hardened freeze is superseded
because its stored-record AAD positive inputs were invalid at the B1 write seam.
The corrected-vector re-preflight returned `P1=4/P2=0/P3=0`. D148 now fixes
exact three canonical-chain logical fixtures plus exact two durable-ingress fixtures,
write-path-derived 308／421 and delivery-correct 222／303-byte AAD vectors,
Candidate／Canonical implementation exact2,
persisted-value hydration, and generic BOPD caps. The four-root corrected review
returned `P1=2/P2=1/P3=0`; those and later bounded findings are Historical and
closed. Final bounded Sol Max closure is Green `P1=0/P2=0/P3=0`; exact33
aggregate `bb6ab89a222a72eafa601f588f46177ee443e554c036bcc00575b211961c8847`
and the Complete Final Gate are Green. P23-003B1 is Accepted without runtime、
database、route、CLI、public Documentation、or release activation. The reviewed
Product candidate `c3b75ca0afe6d8c756caa8404b7e95922987c1da` and its
clean-candidate package export are Green. Earlier B2 preflights and review lanes are Historical.
Accepted B2 exact26 is
`ecacafc99cbdf4fd2287b7554b88a34f6524a23d92c9adb3a8bf201a2a1d50bb`.
Its Complete Final Gate and fresh DB／General Sol closure are Green
`P1=0/P2=0/P3=0`. P23-003B2 and parent P23-003B are Accepted.
Reviewed B2 candidate `968afe2b0c4bbff86e0013592d69dc7115cf8b4f` is committed,
and its clean exact-SHA package export is Green; no push was performed.
No Application composition、Container registration、Audit Outbox／relay、CLI／HTTP／public
Documentation／release activation occurred. P23-003C is the next serial Audit delivery slice.

For P23-003C delivery, D150 is the more specific split and C1 authority.
Parent C is delivered serially as C1 pure state foundation, C2 PostgreSQL
Outbox persistence, C3 required／durable transaction and key composition, then
C4 relay／recovery runtime. Each child requires the previous child, no partial
runtime is activated, and parent C requires all four Acceptances. C1 contains
no DB, transaction, clock read, UUID generation, key access, encryption,
canonical append, relay, Application composition, or public surface.
The first C1 preflight is Historical. Focused execution exposed a conflict
between required direct `AuditRecordTime` reuse and the Accepted A2
source-registration guard. D150 and the corrected packet permit only that
bounded Architecture-test exception. Corrected preflight, bounded
implementation, Iteration Gate, and Complete Final Gate are Green.
Product／Test exact11 is accepted at
`d0bfa64e35ce8d113909a2aba710c57ed7bb33fd6711c51df207e71d33919fad`;
fresh Orchestrator Sol Max implementation review returned
`P1=0/P2=0/P3=0`. P23-003C1 is Accepted. It changes no B2 Production byte;
only the shared Architecture guard is superseded, while the other Accepted B2
exact25 remain byte-identical. Candidate commit and same-SHA package
verification are closeout only; P23-003C2 is next.

## Historical P23-003A accepted delivery checkpoint

Historical status at that checkpoint: `Accepted — P23-003A1／A2 and parent P23-003A; P23-003B next`.

P23-002E is Accepted. The prior one-packet P23-003A wording is superseded by
the serial A1/A2 boundary in D147. Stable `1.2.0` remains current and all
Audit capability remains an unreleased `1.3` proposal. A1 Production work was
authorized only after the corrected exact P23-003A1 Task Packet received fresh
general Sol re-review Green. The initial review and first corrected
exact-nine re-preflight are Historical Changes Requested; the latter returned
general `P1=1/P2=1/P3=0` and focused `P1=1/P2=0/P3=0`, separately. Its raw-cap
precedence and typed-reference boundary are now fixed in D147 and the Task.
The initial review's uncommitted package-export gate, request-context ordering,
JCS/error oracles, key ownership, and PublicApi/gate scope findings are
corrected in D147 and the Task.
The second corrected exact-nine focused lane was Green `P1=0/P2=0/P3=0`;
the separate general lane returned `P1=0/P2=1/P3=0` for missing direct
typed-reference preservation and tenant opaque-context fixtures. The Task now
owns those fixtures, and only a fresh general re-review was required before
Luna implementation.
The fresh general closure review matched the third corrected exact-nine and
returned Green `P1=0/P2=0/P3=0`. Luna Max completed the exact A1 implementation
and focused／complete Final Gates. Fresh Sol Max implementation review is Green
`P1=0/P2=0/P3=0`; the Orchestrator accepts P23-003A1 and unblocks P23-003A2.
Initial A2 preflight returned Changes Requested `P1=1/P2=0/P3=0`. The A2 Task
now fixes an exactly-once detached Logical snapshot, its same-resolution bound
definition, and a bytes-only Projector entrypoint. The corrected exact packet
passed fresh Sol Max re-preflight `P1=0/P2=0/P3=0`. Luna Max implementation,
the focused Iteration Gate, exact29 Product freeze, and review-before complete
Final Gate were Green. No runtime activation is authorized. A2 and parent
P23-003A are now Accepted; P23-003B is the next ordered Task and P23-003C..G
remain behind their own prerequisites.
The review returned Changes Requested `P1=1/P2=2/P3=0`; Luna High completed the
bounded A2 precedence／fixture／golden correction. The corrected exact29 complete
Final Gate was Green. Fresh Sol Max implementation re-review returned Changes
Requested `P1=3/P2=1/P3=0`; Round 2 is limited to Correlation construction,
bounded Logical capture, remaining direct mutation fixtures, and D147 current
handoff. Round 2 correction and its complete Final Gate are Green on exact29
aggregate `c2ef33a3d3b47ca9a5de189d324308a79da0a356839d36aa944675a7dba2d8eb`.
Fresh Sol Max implementation re-review returned Changes Requested
`P1=0/P2=2/P3=0`; Round 3 is limited to the explicit-null correlation projection
direct fixture and current inventory sync. Round 3 closes both roots on exact29
aggregate `65443eb6ec6c738c61100001d8f3f5d2e38a88e63f585052bf6f4c4b9bd29056`;
focused PHPUnit `46/23599` and the complete Final Gate are Green. Fresh Sol Max
implementation re-review returned Changes Requested `P1=1/P2=0/P3=0` for
wide key/evidence allocation before bounded shape rejection. Round 4 is limited
to the Validator and its two direct tests; Product Gate evidence restarts after
that change. Accepted A1 exact48 remains immutable.
Round 4 correction and focused gate are Green on exact29 aggregate
`c019ab09c31a99301bfe9fd22004890cf52a21b20cc7b410df74f08b0ef97ae9`.
Accepted A1 exact48 remains immutable. The complete Final Gate is Green with
full PHPUnit `2700/37333`, Mago error0, Deptrac `970/970`, release／Website／
Node／management guards, staged0, and ending hashes PASS. Fresh review／
Acceptance are Green／Accepted: fresh Sol Max implementation re-review returned
`P1=0/P2=0/P3=0`, and the Orchestrator accepts A2 and parent P23-003A. P23-003B
is the next ordered node and needs its own Task Packet／preflight.

## P23-002E current delivery gate

Current status: `Accepted implementation — unreleased BlackOps 1.3`.

P23-002D is Accepted. The current P23-002E Task Packet fixes the About and
Outbox aggregate delivery contract in the P23-002E node below. Stable `1.2.0`
remains current and BlackOps `1.3` remains unreleased. Fresh Sol Max
re-preflight is Green `P1=0/P2=0/P3=0`; both components, shared integration,
and the complete Final Gate are Green on exact37. Fresh Sol Max implementation
review is Green `P1=0/P2=0/P3=0`; the Orchestrator accepts P23-002E. BlackOps
`1.3` remains unreleased.

## P23-002D accepted delivery prerequisite

Current status: `Accepted — Diagnostics Check and Aggregate Queue Status`.

P23-002C is Accepted. The current P23-002D Task Packet fixes the Diagnostics
and Queue delivery contract in the P23-002D node below. Fresh Sol Max preflight
is Green `P1=0/P2=0/P3=0`; Production implementation completed within that
exact Task Packet. Stable `1.2.0` remains current and BlackOps `1.3` remains
unreleased.

Initial preflight confirmed and the corrected packet closes two architecture
P1 roots: Queue source contracts move from Internal to Core for Transport
compatibility, and the Files Allowed now includes the two explicit
Application/InternalApplication-to-Observability Deptrac edges. Fresh review
matched exact8 aggregate
`f29f3deb2896cdb067b58927139f7d3c512bdd8ce20674035b5393ce8f674a34`.

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

## P23-002C accepted delivery prerequisite

Accepted status: `Accepted — Schedule Projection and Atomic Activation`.
Reviewer: Fresh Sol Max closure review Green `P1=0/P2=0/P3=0`; Orchestrator Accepted.

P23-002A1/A2/B are Accepted and non-activated.
Fresh bounded Sol xHigh preflight matched exact9 aggregate
`c933fd9f23f85b00f447f72f69a7769bb7ec747b737cf711fb2cdf934beeb347`
and returned Green `P1=0/P2=0/P3=0`.
P23-002C Production implementation is now authorized within its exact Task
Packet.
Its exact Task Packet is
`develop/orchestration/tasks/P23-002C-schedule-projection-and-atomic-activation.md`.

Luna Max implementation is complete within the corrected exact 41-file Task
scope. The first Orchestrator Final Gate stopped at canonical `mago lint` with
exactly two new cyclomatic-complexity errors; the pre-correction exact32
aggregate `0cce05bb35c46fbe15217e6cf6bcc206834ae429dca5c0c3d7ee937ecbda0ec5`
is Historical. A behavior-neutral class-DocBlock correction passes focused
format, canonical lint, and impacted PHPUnit (`15 tests / 118 assertions`).
The Orchestrator refroze the corrected exact32 aggregate as
`be0b7c8421e1f6906ce5576b59230ed33dcf505a00618afc09622c52a652d766`,
restarted the complete Final Gate, and reproduced the same ending aggregate.
That `be0b7c...` freeze and its Final Gate are Historical/superseded
after fresh Sol returned Changes Requested `P1=0/P2=2/P3=1`. The bounded
correction changes only the two allowed integration tests; impacted PHPUnit
passes `37 tests / 447 assertions`. The current Orchestrator-frozen
exact32 aggregate is
`15e0f074c5302af68753c06d8b9effc0747aff0e0a55902f081bc91834117a33`.
The current complete Final Gate is Green: format, Composer strict, canonical
lint/baseline, analyze errors `0`, PHPUnit `2472/10972`, Deptrac
violations `0`, version baseline, release-source, management-ID,
diff-check, staged0, and ending exact32 unchanged. Fresh Sol Max closure review
returned Green `P1=0/P2=0/P3=0`, and the Orchestrator accepts P23-002C.
Phase 23 remains an unreleased proposal.

C adds `ApplicationBuildConfiguration::projectionBuildSetRoot` as the single
internal authority for `dirname(app.build.container)` and adds no public
configuration key.
Missing `app.build` or a missing configured directory maps to read-only
`absent`.
Present invalid configuration/build ID and symlink/special/unresolvable roots
are configuration-input validation failures; artifact bytes alone produce
`malformed`.

The command-facing `ProjectionBuildSetStatusSource::withStatus()` is shared by
list, route, and schedule commands.
The Production adapter uses Reader
`withPublishedStatus(expectedApplicationBuildId, callback, selectedKey)` and
keeps one shared lock until the callback finishes rendering and writing.
It validates the root plus all three published descriptor/file/input-hash
bindings and never recalculates expectations from Source or existing
manifests.
The earlier exact-three `withReadStatus` API remains a component-only seam.

C owns these intentional activation deltas in one Task:

- target metadata exact 30 -> exact 31 by adding canonical `schedule:list`
  with alias `schedule:ls`;
- lazy `FrameworkCommandNames` stays exact 25;
- pre-release reservations stay exact 13;
- a fresh empty configured Kernel exposes exact 31 canonical target names,
  without counting aliases as canonical rows;
- list, route, and schedule commands all use the same Production status source;
  and
- Stable `1.2.0` Symfony list bytes remain unchanged because this is an
  unreleased `1.3` slice.

The schedule producer consumes the already-compiled `OperationRegistry` and
the application build ID only.
It emits the closed schedule row and shares one pure execution-strategy mapper
with route production.
Human output, JSON, input diagnostics, artifact diagnostics, operational
diagnostics, canonical/alias parity, escaping, LF, and exit behavior are exact
in Specification 105.

`build:compile` writes the existing runtime container, Operation, HTTP,
Frontend, and command artifacts first, then produces all three projections
from the same in-memory compilation and calls one Publisher.
The ProjectionBuildSet root is committed last.
This atomicity covers the three projection files only; earlier application
artifact writes are not rolled back after a projection failure.
Producer and pre-commit Publisher failures propagate their typed exception and
write no success/cleanup diagnostic.
A post-commit cleanup failure leaves the root published, writes the exact
success stdout plus cleanup stderr, and exits `1`.
An optional exact-root Publisher constructor seam on the build command makes
the three failure phases deterministic in component tests.

P23-002C iteration runs only the focused test/format/management guards in its
Task Packet.
After the Source/Test freeze it runs one complete Final Gate and then a fresh
Sol xHigh implementation review.
No commit, stage, push, remote CI, deploy, publication, tag, release, or public
Guide/Website activation is part of this Task.

## P23-002A1 current delivery correction

P23-002A1 implements Specification 105's current closed wire and D146's
single-basename filesystem namespace. Its fixtures hard-code the exact
key-kind-path mapping and every current field cap rather than reading
Historical checkpoint prose. The filesystem matrix covers basename slash,
backslash, and near misses; recognized generation/temporary regular, symlink,
and special entries; existing exact/mismatched generation targets; first-lock
absent/present with 255/256/257 entries; 32/33 GC candidates; root-last commit
and post-commit GC failure; projected committed directory count cap/cap+1; and
a no-outside-root read/write sentinel.

A1 remains non-activated and uses synthetic exact-three inputs. A2 owns the
command producer/list surface, B owns route production, and C owns schedule
production, production locator choice, build integration, and the only final
activation. No A1/A2/B partial root is public or publishable.

## Historical Round 23 current normative corrections

Round 22 is preserved below as historical evidence; the Round 25 section below
is also historical evidence and no longer defines current parent authority.

Every `ProjectionBuildSet v1` reader takes one shared lock before opening the
root and all three same-fd projection files, validates the complete root,
descriptors, and files, including every non-selected file, then selects and
renders only its dedicated rows. Any non-selected missing, malformed, digest, build, or
input mismatch invalidates the complete set. `list`/`ls` maps all six
framework-row recovery states to exit `0`; route/schedule artifact failure is
exit `2` and operational read failure is exit `1`. All-three-family fixtures
are required.

`worker:list` and `worker:ls` are neither BlackOps-owned nor reserved. An
Application may own either spelling as a canonical command or alias; an
unregistered spelling follows normal Symfony unknown-command behavior. There
is no Framework Job registry or built-in Job listing.

The fixed public route wire is `/operations/{operationId}`; structurally
equivalent `/operations/{id}` collides with it. Operation reads use exactly
`access.operation_{status,journal,outcome,diagnostics}.requested|unavailable|accessed`
with required catalog delivery for all three outcomes and a canonical append
durably persisted before a response or failure is released. Service actions use
exactly `audit.access.{query,export,sign,verify}.requested|unavailable|accessed`;
requested/unavailable are durable and accessed is required before result or
signature release.

Genesis alone starts sequence `1`; later segments start at the previous final
sequence plus one and `recordCount=lastSequence-firstSequence+1`. The `1-42`
and `43-50` examples plus gap/overlap negatives are required. The finite
control exemption is only framework-derived target
`system:global/audit-control-v1`, whose signed checkpoint/receipt is
authoritative and emits no `audit.access.sign.accessed`; ordinary signatures
remain audited.

`AuditHold v1` is the exact closed object
`{schemaVersion:1,kind:"audit_hold",holdId,target,reasonCode,state,version,previousStateHash,stateHash,updatedAt,updatedBy,idempotencyKeyHash}`
with immutable target, continuous hashes, active-to-released CAS, bounded raw
idempotency key, and inclusive 64 KiB JCS cap. `AuditReadContext v1` includes
requestedAt and nonce, and uses exactly
`requestRef = "oid:v1:" || HEX_LOWER(SHA-256(LP("blackops.audit.read.request-ref.v1") || LP(JCS({purpose,actor,execution,tenant,requestedAt,nonce}))))`
with no target. The LP concatenation is the SHA-256 preimage, not the final
reference; Node/Docker-PHP golden parity, algorithm-substitution negative, and
boundary fixtures are required. The golden uses `purpose=status`, `actor=null`,
`execution={id:"exec-1",type:"cli"}`, `tenant=null`,
`requestedAt="2026-08-20T03:20:00.123456Z"`, and `nonce="nonce-1"`; canonical
JCS is 150 bytes, the LP preimage is 192 bytes, and
`requestRef=oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.

## Historical Round28 delivery correction (superseded by Round29)

Round28 was the prior delivery-plan authority for this proposal. It is now
Historical evidence and is superseded by the Round29 correction below. Round27
Search and Round28 Audit remain separate review lanes. Stable 1.2 remains
current and Phase 23 and 1.3 remain unreleased.

### Query/access and cursor lifecycle

P23-003G owns the closed AuditAccessContext v1:
{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,
requestedAt,nonce,requestRef,authorizationDecision}. A query requires
purpose="query" and authorizationDecision="allow". requestRef hashes exactly
{purpose,actor,tenant,requestedAt,nonce}; partition, filters, limit, order,
cursor, and the decision result are not substituted into that binding.

The closed AuditQueryRequest v1 is
{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
limit,order,snapshotHeadSequence,cursor}. partitionId is system:global or
tenant:<64 lowercase hex>. filters is exactly
{eventCategory:null|string,recordIdPrefix:null|string,fromSequence:null|positive,
toSequence:null|positive}; strings are NFC/control-free and 1..256 UTF-8
bytes, and fromSequence<=toSequence. limit is 1..1000 and order is exactly
sequence_asc. The first page has null snapshotHeadSequence and cursor. The
service captures one trusted canonical partition head; a continuation carries
the same positive head, partition, closed filters, limit, and order, and starts
strictly after lastSequence. Selection never passes the captured head or
toSequence bound.

AuditQueryResult v1 is closed:
{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,hasMore,nextCursor,reasonCode}. ok returns a complete
ascending 1..limit record array, the captured head, hasMore, and nextCursor
exactly when another bounded record remains. empty returns the authorized
partition/head with records=[], hasMore=false, and null cursor/reason.
unavailable returns null partition/head/records/cursor, hasMore=false, and
resource_unavailable; no partial record or existence detail is disclosed.

The cursor payload is closed and includes partitionId, the complete filters,
limit, order, snapshotHeadSequence, lastSequence, issuedAt, expiresAt, and
keyId. Its aqc:v1 HMAC input is
LP("blackops.audit.query.cursor.v1")||LP(JCS(payload)). The exact issuance
window is key.notBefore <= issuedAt <= trustedNow < expiresAt <= key.notAfter,
where trustedNow is read once from the Framework clock. A prior verification
key is retained exactly through the last outstanding valid unexpired cursor
that it can verify, then may be retired; new cursors use the current key.
Canonical/closed/cap/partition/filter/head/time/role/tag failures are fail
closed. P23-003G owns authorization, head capture, result completeness,
cursor codec, key lifecycle, and all positive/negative query fixtures.

### Audit ingress, Outbox, and retry authority

P23-003B owns the exact closed AuditIngressEnvelope v1:
{schemaVersion:1,kind:"audit_ingress_envelope",logicalRecord,contentHash,
recordedAt,ingressOrdinal,idempotencyKey,recordId,partitionId,tenantBinding}.
logicalRecord=AuditRecord without integrity. recordId and recordedAt equal
the logical record; contentHash is the lowercase 64-hex domain hash of its
canonical JCS; recordedAt is trusted UTC with six fractional digits;
ingressOrdinal is positive JSON-safe and monotonic under the partition fence;
idempotencyKey is protected NFC/control-free 1..256-byte text; and
tenantBinding is null or {type,id}, equal to logicalRecord.tenant and the
canonical partition derivation. Unknown fields, equality/bounds violations,
normalization, and cap+1 fail closed.

The sole current `AuditOutboxRecord v1` row is
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody,version}`. Its
protected plaintext is JCS(envelope) without LF. LP(x) is U32BE(byte_length(x))
plus exact UTF-8 bytes, and AAD is byte-exact:
LP("blackops.audit.outbox.aad.v1")||LP("AuditOutboxRecord")||LP("1")||
LP("1")||LP("1")||LP(exactKeyId)||LP(recordId)||LP(partitionId)||
LP(TENANT(tenantBinding))||LP(ASCII(canonicalDecimal(ingressOrdinal)))||
LP(decoded32(idempotencyKeyHash))||LP(decoded32(contentHash)).
TENANT(null)=0x00; a present tenant is
0x01||LP(UTF8(NFC(type)))||LP(UTF8(NFC(id))). The absent/present marker,
row identity, tenant, partition, hashes, key, nonce, ciphertext, and tag are
immutable; retry reuses ciphertext. `version` starts at 1 and every claim,
retry, relay, poison, and recovery transition requires the expected current
version and matching lease token where a lease exists, then writes exactly
version+1. States are exactly pending|leased|relayed|poisoned: pending has
attempt 0..max-1, leased/terminal rows have 1..max, and terminal schedule/
lease fields are null. Pending at max and any max+1 claim are invalid.

P23-003C is the unique retry authority. maxAttempts is 1..32 (default 5),
backoffBaseSeconds is 1..3600 (default 1), and backoffCapSeconds is
1..86400 (default 3600). For claim attempt n, the delay uses one checked
saturating algorithm that compares with the cap before multiplication. Ingress
starts pending at attempt 0; pending is claimable only when availableAt has
arrived and attempt<maxAttempts. Claim increments once and binds a new fencing
token. Transient failure or expired lease preserves attempt and returns
pending; attempt=maxAttempts expiry returns poisoned. A lease is active
exactly when `trustedNow < leaseExpiresAt` and expired exactly when
`trustedNow >= leaseExpiresAt`, including equality. Relay and recovery use one
trusted transition clock and the matching stored token/version CAS; stale,
wrong-token, version-gap, expiry-boundary, and FIFO-bypass mutations fail.
C owns ranges, defaults, backoff, eligibility, exhaustion, and clock fixtures.

### AnchorIntent crash-safe delivery

P23-003F owns the sole current AnchorIntent v1 wire from Round29:
`prepared|externally_accepted|externally_rejected|receipt_inputs_persisted|
receipt_persisted|acknowledged`, with `externally_rejected` the persisted
terminal for permanent rejection or attempt exhaustion. Genesis is
`version=1,previousStateHash=null`; every claim, result, internal receipt
step, acknowledgement, and lease reclaim uses expected version/hash and the
matching token CAS, then writes exactly +1. `conditionalCreateToken` and the
idempotency key/hash are non-null and immutable from initial prepared. The
anchor lease duration is 1..86400 seconds (default 300), claim and transition
clocks are captured once with rollback rejection, and `updatedAt` is written
from the accepted transition clock.

Prepared external submission claims increment attempt exactly once only below
32. Accepted/rejected/unavailable results preserve the active positive attempt;
internal receipt-input, receipt-persist, and acknowledgement claims preserve
it. After any pre-persist or post-persist response loss, deterministic
same-object reconciliation returns exactly found, explicit absent, or lookup
unavailable before another external side effect. Found recovers the accepted
object with one fenced +1 revision and no N+1; explicit absent at attempt 32
alone becomes `externally_rejected/attempt_exhausted`; lookup unavailable
clears the lease and returns retryable prepared, with attempt 32 represented
as reconciliation-only and not externally claimable. Expired attempt-32
prepared leases use the same branches, while accepted/receipt states never
regress or lose data. The active §9.6.1 receipt wire and all no-N+1, response-
loss, stale-fence, attempt31/32, receipt-boundary, and rotation fixtures are
owned here.

### Retention and hold boundary

P23-003G owns the sole current `AuditRetentionState v1` wire from Round29:
the closed object includes target, nullable policy, evaluator/owner, trusted
evaluation time, immutable policy/hold snapshot refs, hashes, versions,
`evaluationFenceVersion`, result/reason, state, revision/hash chain, and
idempotency hash. Its states are exactly
`retained|eligible_for_future_deletion`, with the four-branch truth table and
`retained|policy_unavailable`, `retained|active_hold`,
`retained|retention_not_elapsed`, and
`eligible_for_future_deletion|retention_elapsed_no_active_hold` reasons. An
eligible state reverses only for a strictly newer authenticated policy or hold
snapshot, or a strictly newer policy-unavailable snapshot; same-version
available-to-unavailable is a fork. `AuditHold v1` is a separate
`active|released` append-only object with hold-state and hold-idempotency
domains only; retention domains cannot authenticate a hold. P23-003H remains
the future physical-deletion owner under a fresh atomic policy/hold fence.

### Historical Round28 delivery guards, Remaining, and Next

The DAG is P23-003B envelope/AAD, P23-003C retry, P23-003F anchor receipt,
and P23-003G query/retention, with P23-003H only after the contracts and
review are accepted. No task may claim Stable 1.2, Green, Accepted, Decided,
or Released. Round28 requires exact three-file scope, no staged changes, and
Sol re-review before parent acceptance. Remaining work is Sol xHigh
re-review, Orchestrator combined review, then parent acceptance; Round27
Search review remains pending. Suggested Next Action is to route this exact
three-file candidate for Sol re-review, then independently verify the same
contracts and hashes before any downstream implementation.

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

## Historical Round 26 current delivery corrections

Historical Round 26 was the prior management and delivery-plan checkpoint.
Round 25 and earlier packets, gates, Remaining, and Next Action text are
historical evidence. The parent is not Accepted, Decided, or Released; Stable
`1.2` remains current and Phase 23/`1.3` remains unreleased. The
current delivery authority is the Round 28 correction above.

### Query/cursor acceptance slice

P23-003G owns a first-page snapshot protocol: a first-page request has
`snapshotHeadSequence=null,cursor=null`; the service captures one trusted
partition head and returns it in the exact `AuditQueryResult v1` wire. A
continuation carries a strict `aqc:v1` cursor and the same positive head,
partition, and filters byte-for-byte. Records are selected only through that
head and continuation starts at `lastSequence+1`; a next cursor is emitted only
when another row remains. The result matrix is `ok` (authorized partition,
positive head, complete ordered `1..limit` records), `empty` (same head and
empty records), or `unavailable` (all data fields null and
`resource_unavailable`). The cursor's exact closed payload, HMAC wire, trusted
Framework-clock expiry, active/unrevoked key window, canonical/cap checks, and
head/last binding are required before lookup/decrypt. First/middle/final,
no-repeat/no-skip, expiry/key, substitution, and cap fixtures are owned by G.

### Outbox acceptance slice

P23-003B/C own the exact closed `AuditOutboxRecord v1` row and BOPD/AAD
boundary. The only states are `pending` (ready or retry), `leased` (claimed),
`relayed` (sent), and `poisoned` (dead-letter); retry is a fenced transition
back to `pending`. Pending has non-null `availableAt` and null lease fields;
leased has null `availableAt` and both lease fields non-null; both terminal
states have scheduling and lease fields null. Claim increments attempt once;
only its fencing token can send, retry, or dead-letter, and FIFO cannot be
bypassed. Identity, hashes, protected body, and ciphertext are immutable and
retry reuses ciphertext. Nullability, transition, FIFO, stale-CAS, tenant/AAD,
AEAD, and cap fixtures are required.

### AnchorIntent acceptance slice

P23-003F owns the exact closed `AnchorIntent v1` codec and recovery. Current
states are exactly `prepared|submitted|accepted|rejected`: prepared and
submitted have the accepted triplet and receipt fields null; accepted alone has
the accepted triplet non-null, its hash equal to checkpoint bytes, genesis
version `1`/null predecessors or non-genesis predecessor+1, and complete receipt
fields; rejected is terminal with those fields null. Conditional token,
identity, body, and idempotency are immutable; CAS rejects stale versions and
same-key/different-body conflicts. A crash after submission reuses the same
token and resolves the existing external result without N+1. State, genesis,
gap, rejection, CAS, crash, and retry fixtures are required.

### Retention/hold acceptance slice

P23-003G fixes `AuditRetentionState v1` as metadata-only with
`schemaVersion=1`, `kind="audit_retention_state"`, and states
`retained|eligible_for_future_deletion`; no hold is embedded. `AuditHold v1` is
separate: an active genesis is mandatory at version `1` with null predecessor;
every revision is exactly prior version plus one, only `active -> released` is
valid, released is terminal, released genesis and re-activation fail. Owner,
partition/category/sequence boundary, CAS/idempotency, same-snapshot matching,
and unchanged sealed-byte/key fixtures are required. Physical deletion remains
future P23-003H.

## Historical Round 25 current delivery corrections

Round 25 was the prior current checkpoint. Its gate and exact projection/audit
text below are preserved as historical evidence and no longer define current
management authority.

### Historical exact projection mapping

Orchestrator evidence: focused/full Website `8/8` and `120/120`, Website check
`0 errors/0 warnings/0 hints`, build `42` pages/site check `41` with only
existing nonfatal chunk-size and route-priority warnings, release source/
artifact guards, version baseline `published=1.2.0 historical=1.1.0`, focused
PHPUnit `30 tests / 250 assertions`, Mago format/lint/baseline PASS and analyze
exit `0` with existing `71 issues` (`46 warning / 25 help`), PHP management-ID,
current-only Node exact-16/staged-0/trailing-0/hashes-16 (JSON `7/7`, Read
`150/192/bfad...df0cf`, Access `103/147/1c505...e241`, negative matrices, links
`315 total/279 local/0 missing`, seven roots), Docker PHP goldens/negative
matrices, and HEAD/main `5ad87557c08e20c3bcc6d77be83804001ba3eb80` with staged
`0` and diff check.

### Exact projection mapping

The current root is exactly
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
`schemaVersion=1`, `kind="projection_build_set"`, and exactly one descriptor
per map key: `command` -> `blackops_command_projection`, `route` ->
`blackops_route_projection`, and `schedule` -> `blackops_schedule_projection`;
swapped or duplicate kinds fail. Descriptors are exactly
`{kind,inputHash,path,sha256}`, with lowercase 64-hex `inputHash` and
`sha256` equal to the raw projection-file SHA-256. Each file is exactly
`{schemaVersion,kind,applicationBuildId,inputHash,records}` with
`schemaVersion=1`, file kind/build/input equal to descriptor/root identity.
`applicationBuildId` is 1..128 NFC/no-control UTF-8 bytes; paths are distinct
root-relative POSIX 1..256-byte paths with no absolute form, backslash, empty
component, `.`, `..`, control, symlink, or special-file component.
`ENC(string)="s:"||decimal_utf8_byte_length||":"||UTF8(string)`,
`ENC(integer)="i:"||canonical_decimal_digits`, `ENC(null)="n:"`, and
`ENC(bytes)="b:"||decimal_byte_length||":"||bytes`.
`buildSetHash=SHA-256(ENC("blackops.projection-build-set.v1")||ENC(JCS(root excluding buildSetHash)))`;
`inputHash=SHA-256(ENC("blackops.projection-input.v1")||ENC(JCS({kind,schemaVersion,applicationBuildId,sourceRecords})))`.
Root/projection bytes are UTF-8 RFC 8785 JCS plus exactly one LF, no BOM,
CRLF, or other whitespace; root cap is 64 KiB, projection cap is 16 MiB,
and row cap is 4,096. Command rows are exactly
`{name,aliases,description,origin,hidden}` with `origin=framework|application`;
route rows are exactly `{method,path,strategy,operationType,origin}` with
`origin=operation_manifest|framework_fixed`; schedule rows are exactly
`{name,operationType,cron,timezone,strategy}` and have no origin. Sort keys
are `(name,aliases)`, `(path,method,origin,operationType,strategy)` with nulls
first, and `(name,operationType)` respectively, using unsigned UTF-8 bytes.
Readers hold one shared lock while validating root and all three same-fd files;
any non-selected mismatch invalidates the complete set. Publisher ordering,
flush/fsync, atomic publication, directory fsync, and generation-safe GC are
required. P23-002A/B/C own positive and swapped/duplicate-kind/invalid-origin
fixtures.

### Exact audit contexts and verifier

The Read hash input is exactly
`{actor:null,execution:{id:"exec-1",type:"cli"},nonce:"nonce-1",purpose:"status",requestedAt:"2026-08-20T03:20:00.123456Z",tenant:null}`
with JCS 150, LP 192, and
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
The Access hash input is exactly
`{actor:null,nonce:"n0",purpose:"query",requestedAt:"2026-08-19T00:00:00.000000Z",tenant:null}`
with JCS 103, LP 147, and
`1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`.
Exact domains, LP(U32BE+UTF-8), SHA-256, lowercase hex, and `oid:v1:` are
required. P23-003F owns pure codec/verifier and wrong-domain/algorithm/raw-
preimage/self-reference/cap fixtures; D/G own consumer integration and
Access cross-purpose/time/nonce/actor/tenant/raw-target/requestRef-reuse.

After signature and file-digest validation, the total Stage 6 function first
checks authenticated catalog plus record graph/chain/hash/semantics as code
`14 authenticated_graph_invalid`, then mode/context/coverage as `16
mode_coverage_invalid`, then freshness head shape/boundary as `18
freshness_boundary_unavailable`. Global failures keep refs non-null, return
`partitions:[]`, never reopen files, and simultaneous failures use that order.
Stage 7 only checks local checkpoint/anchor trust/chronology as
`15 checkpoint_or_anchor_invalid` and local end-head freshness as `18`; it has
no catalog code10, the deprecated scope label, or graph code14.

### Query, Outbox, AnchorIntent, and cursor

`AuditQueryRequest v1` is exactly the closed object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}` with exact nine-field `AuditAccessContext`,
authorized `system:global|tenant:<64 lowercase hex>` partition, and filters
`{eventCategory:null|string,recordIdPrefix:null|string,fromSequence:null|positive,toSequence:null|positive}`.
`eventCategory` is null or an AuditRecord category and `recordIdPrefix` is null
or a non-empty prefix. Every non-null filter/cursor string is NFC,
no-control, and 1..256 UTF-8 bytes; the head is positive JSON-safe, limit is
`1..1000`, and cursor is null only on the first page or strict `aqc:v1` later.
`AuditQueryResult v1` is exactly
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,nextCursor,reasonCode}`. `ok` has an authorized
partition/head with complete ordered `1..limit` records and optional next
cursor; `empty` has the same partition/head and empty records/null reason;
`unavailable` has null partition/head/records/cursor and
`resource_unavailable`. Request/result JCS caps are 64 KiB and 1 MiB plus LF.

The exact cursor is
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, one literal
dot, no padding, ASCII, and at most 2048 bytes. Its closed payload is
`{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`. The 32-byte tag
is HMAC-SHA-256 under role `audit-query-cursor` over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`; keyId is 1..128-byte
ASCII NFC/no-control and the secret is exactly 32 bytes. P23-003G captures
trustedNow once from the injected Framework clock, never cursor input, in UTC
with six fractional digits and requires `issuedAt <= trustedNow < expiresAt`;
the active unrevoked key must satisfy
`notBefore <= issuedAt <= trustedNow < min(expiresAt,notAfter)`. Unknown,
wrong-role/status/window/key/length fail before lookup/decrypt. Strict
decode/canonical JCS/closed/caps/partition/filter/head/last/time/key checks
precede constant-time tag comparison. Rotation verifies old keys only through
outstanding expiry and issuance uses the current active key; expiry,
window, rotation, substitution, and cap negatives are required.

The exact `AuditOutboxRecord v1` row is
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}`. Its protected
body is BOPD v1 over JCS(`AuditIngressEnvelope v1`) without LF. With
`LP(x)=U32BE(byte_length(x))||x`, AAD is
`LP("blackops.audit.outbox.aad.v1")||LP("AuditOutboxRecord")||LP("1")||
LP("1")||LP("1")||LP(exactKeyId)||LP(recordId)||LP(partitionId)||
LP(TENANT(tenantBinding))||LP(ASCII(canonicalDecimal(ingressOrdinal)))||
LP(decoded32(idempotencyKeyHash))||LP(decoded32(contentHash))`.
`TENANT(null)=0x00`; `TENANT({type,id})=0x01||LP(UTF8(NFC(type)))||
LP(UTF8(NFC(id)))`, with exact bounds and partition/equality binding. Pending,
leased, relayed, and poisoned state/lease/idempotency/size caps are exact;
identity/body/hash/ciphertext are immutable and retries reuse ciphertext.
P23-003B/C own Node/PHP null/present parity and `0xff`/wrong-marker/type/id/
header/tenant/AEAD substitution negatives.

The exact current `AnchorIntent v1` object has
`schemaVersion:1,kind:"audit_anchor_intent"` and closed fields. `prepared`
has accepted version/hash/time all null and all four receipt fields null. Only
`externally_accepted` and later require `acceptedObjectHash=checkpointBytesHash`;
only then genesis requires version 1 with null predecessor fields, and
non-genesis requires both predecessor fields plus one. Positive and negative
state/equality/version fixtures are required.

`receiptBytesHash` is exactly
`SHA-256(ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||exact unsigned receipt JCS+LF bytes)`;
the final bytes have no additional `ENC`. The receipt-input state has only
`receiptSignedAt`, `receiptSigningKeyRef`, and `receiptBytesHash` non-null with
`anchorReceiptHash` null; final receipt states have all four receipt fields
non-null. Lease fields are both null at rest or both non-null for one fenced
claim, and stale CAS writes fail.

The Outbox AAD defines `TENANT(null)=0x00` and
`TENANT({type,id})=0x01||LP(UTF8(NFC(type)))||LP(UTF8(NFC(id)))`; AAD uses
`LP(TENANT(tenantBinding))` within the outer U32BE LP, with exact bounds,
partition/equality binding, and P23-003B/C Node/PHP null/present parity plus
marker/header/type/id/tenant/AEAD substitutions including `0xff`.

The cursor keyId is 1..128-byte ASCII NFC/no-control, HMAC secret exactly 32
bytes, role exactly `audit-query-cursor`. P23-003G captures trustedNow once
from the injected Framework clock, never cursor input, and requires
`issuedAt <= trustedNow < expiresAt`; the active, unrevoked key must satisfy
`notBefore <= issuedAt <= trustedNow < min(expiresAt,notAfter)`. Unknown,
wrong-role/status/window/keyId/secret length fail before lookup/decrypt;
rotation verifies prior keys only through cursor expiry and issuance uses the
current key. Strict decode/canonical JCS/closed/caps/partition/filter/head/
last/time/key checks precede constant-time tag validation.

## Historical Round 24 current delivery corrections

Round 24 was the then-current parent checkpoint and is now historical. Round 23
and earlier parent packets, completions, gates, Remaining, and Next Action
sections are historical; P23-001S remains the accepted child fact. Stable `1.2`
is current and every Phase 23 item remains an unreleased proposal. Its fresh
complete Local Gate PASS is historical evidence; current parent review and
acceptance are governed by Round 25.

The public Application Bootstrap boundary is part of the delivery contract:
explicit class-string Commands instantiate during Console Kernel composition,
prebuilt instances are reused, default/raw discovered rows stay lazy, and
non-short JSON/XML/Markdown descriptor paths may resolve a Lazy factory or
Compiled Container. The same corrected boundary is required in Guide Source
and every public HTML/raw/Search/LLM surface, with former Manifest-only claims
and unknown duplicate aliases rejected.

### ProjectionBuildSet v1 wire and complete-set reader

P23-002A owns the one current closed root
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

The exact typed encoding is `ENC(string)="s:"||decimal_utf8_byte_length||":"||
UTF8(string)`, `ENC(integer)="i:"||canonical_decimal_digits`,
`ENC(null)="n:"`, and
`ENC(bytes)="b:"||decimal_byte_length||":"||bytes`. `buildSetHash` is
`SHA-256(ENC("blackops.projection-build-set.v1") || ENC(JCS(root excluding
buildSetHash)))`. Each projection file is exactly
`{schemaVersion,kind,applicationBuildId,inputHash,records}` with
`schemaVersion=1`, file `kind` equal to its descriptor kind,
`applicationBuildId` equal to the root value, and `inputHash` equal to the
descriptor value; `inputHash` is
`SHA-256(ENC("blackops.projection-input.v1") || ENC(JCS({kind,schemaVersion,applicationBuildId,sourceRecords})))`.
Command records are exactly `{name,aliases,description,origin,hidden}` and
sort by canonical name then aliases. Route records are exactly
`{method,path,strategy,operationType,origin}` and sort by
`(path,method,origin,operationType,strategy)` with nulls first. Schedule
records are exactly `{name,operationType,cron,timezone,strategy}` and sort by
`(name,operationType)` with nulls first; all comparisons use canonical unsigned
UTF-8 bytes without locale. `origin` is exactly
`operation_manifest|framework_fixed`; all fields reject unknown, duplicate,
missing, wrong-type, unsorted, non-NFC, control, and cap+1 values.
Stored root and projection bytes are UTF-8 RFC 8785 JCS followed by one LF,
with no BOM, CRLF, or other whitespace. Root is at most 64 KiB inclusive; each
projection is at most 16 MiB inclusive with 0..4096 records. P23-002A/B/C
readers acquire shared `flock` on the fixed regular no-follow
`.blackops-projection-build-set.lock` before opening and validating the root
and all three same-fd regular files, including every descriptor, digest,
build/input identity, and record set, then select/render only dedicated rows.
A non-selected mismatch invalidates the complete set. The publisher acquires
exclusive lock before generation writes, holds it through same-fd validation,
flush/fsync, atomic file/root publication, parent-directory fsync, and
generation-safe GC; GC removes only paths unreferenced by the current root.
A/B may prepare bounded inputs but C publishes only the complete set.

### Exact audit context and verifier contract

The closed pre-decision `AuditReadRequestBasis v1` is exactly
`{purpose,actor,execution,tenant,requestedAt,nonce}` and final requestRef is
`"oid:v1:" || HEX_LOWER(SHA-256(LP("blackops.audit.read.request-ref.v1") ||
LP(JCS(requestBasis))))`; LP is the preimage only. The 150-byte JCS, 192-byte
preimage, `bfad862f...df0cf` golden, Node/Docker-PHP parity, and wrong-domain,
wrong-algorithm, raw-preimage, self-reference, substitution, and cap negatives
are required.

Only after a trusted decision is the exact ten-field `AuditReadContext v1`
constructed as `{schemaVersion:1,kind:"audit_read_context",purpose,actor,
execution,tenant,requestedAt,nonce,requestRef,authorizationDecision}`. Its
basis fields/ref equal the validated basis/derived ref and the caller supplies
neither decision nor ref. D/G own one-request 16-byte CSPRNG nonce capture and
32-character lowercase-hex encoding; requestRef remains correlation only.

The closed `AuditAccessContext v1` has exactly nine fields:
`{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,
requestedAt,nonce,requestRef,authorizationDecision}`. Actor is null or
`{principal:null|{type,id},execution:{type,id}}`; tenant is null or `{type,id}`;
purpose is `query|export|sign|verify`; decision is
`allow|deny|resource_unavailable`; time has six UTC fractional digits; nonce
is 1..128-byte NFC/no-control noncredential. Hash input is exactly
`{purpose,actor,tenant,requestedAt,nonce}` with domain
`blackops.audit.access.request-ref.v1`, LP, SHA-256, lowercase hex, and
`oid:v1:`. Legacy requester field names are forbidden. Null/null is JCS
103 bytes, preimage 147 bytes, digest
`1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`.

Stage 6 finite precedence is catalog or authenticated record-chain
graph/hash/semantic mismatch code `14 authenticated_graph_invalid`, then
mode/coverage code `16 mode_coverage_invalid`, then freshness code `18
freshness_boundary_unavailable`. Every global Stage 6 failure keeps
post-signature top refs non-null, returns `partitions:[]`, and never reopens
listed files. Simultaneous fixtures assert the order and safe names.

### Query, cursor, Outbox, and AnchorIntent ownership

P23-003G owns closed `AuditQueryRequest/Result` and `aqc:v1` HMAC cursor.
`AuditQueryRequest v1` is exactly
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}`. `access` is the exact access context;
`partitionId` is authorized `system:global|tenant:<64 lowercase hex>`;
`filters` is exactly
`{eventCategory:null|string,recordIdPrefix:null|string,fromSequence:null|positive,
toSequence:null|positive}` with null bounds or `fromSequence<=toSequence`;
`eventCategory` is null or an AuditRecord event-category value;
`recordIdPrefix` is null or a non-empty prefix. Every non-null filter string
and every string in the cursor payload is NFC, has no control character, and is
1..256 UTF-8 bytes; null remains allowed.
snapshot head is positive JSON-safe; limit is `1..1000`; and cursor is null
only on the first page or strict `aqc:v1` thereafter. Unknown fields, swaps,
invalid filters, and request JCS over 64 KiB fail before lookup.

`AuditQueryResult v1` is exactly
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,nextCursor,reasonCode}`. `ok` has an authorized partition,
positive head, a complete non-empty `1..limit` record array, and nextCursor only
when more rows remain; `empty` has the same partition/head, `records:[]`, and
null nextCursor/reasonCode; `unavailable` has null partition/head/records/
nextCursor and reasonCode `resource_unavailable`. Records are complete,
authorized, integrity-checked, and ascending by partition-global sequence;
result JCS+LF is at most 1 MiB and cap+1 fails before allocation.

The cursor is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, one literal
dot, no padding, ASCII, and at most 2048 bytes. Its closed payload is
`{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`. The 32-byte tag
is HMAC-SHA-256 under the role-bound `audit-query-cursor` key selected by keyId
over `LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`. Strict
base64url/JCS/closed-field/cap, partition/filter/head/last, key role, and
`issuedAt<expiresAt` checks precede constant-time tag comparison; lookup and
decrypt occur only after authentication. First/middle/final/empty/unavailable,
substitution, expiry, key rotation, and cap+1 fixtures are required.

P23-003B owns the exact closed `AuditOutboxRecord v1` row
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}`. Its protected
body is binary BOPD v1 containing JCS(`AuditIngressEnvelope v1`) without LF;
the exact AAD is
`LP("blackops.audit.outbox.aad.v1")||LP("AuditOutboxRecord")||LP("1")||
LP("1")||LP("1")||LP(exactKeyId)||LP(recordId)||LP(partitionId)||
LP(tenantPresenceAndTypeAndId)||LP(ASCII(canonicalDecimal(ingressOrdinal)))||
LP(decoded32(idempotencyKeyHash))||LP(decoded32(contentHash))`, with U32BE LP.
`idempotencyKeyHash` is
`H(ENC("blackops.audit.outbox.idempotency.v1")||ENC(partitionId)||
ENC(NFC(idempotencyKey)))`; raw keys never appear clear. Pending/leased/
relayed/poisoned fields and transitions are exact, identity/body/hash fields
are immutable, logical JCS is at most 1 MiB, and BOPD allocation is bounded by
`52+keyIdUtf8Bytes`; same-key idempotency, head FIFO, fenced lease, and cap
substitution fixtures are required. P23-003C owns transaction, relay,
idempotency, lease, and fencing.

P23-003F owns the exact closed `AnchorIntent v1` object
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,checkpointId,checkpointHash,
checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,
expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,
acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,
receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,
leaseExpiresAt,version,updatedAt}`. Bounded NFC/no-control IDs/refs/tokens,
lowercase 64-hex hashes, positive versions, six-fraction UTC times, and no
external credentials are required; acceptedObjectHash equals checkpointBytesHash.
`prepared` has acceptedObjectVersion, acceptedObjectHash, and
acceptedAnchoredAt all null and all four receipt fields null;
`externally_accepted` has the accepted triplet non-null and receipt fields null;
`receipt_inputs_persisted` adds receiptSignedAt/receiptSigningKeyRef/
receiptBytesHash while anchorReceiptHash remains null; final
`receipt_persisted|acknowledged` states have all accepted and receipt fields
non-null. `receiptBytesHash` is exactly
`SHA-256(ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||exact unsigned receipt JCS+LF bytes)`;
`anchorReceiptHash` is the unsigned receipt semantic hash, not a signed-file
raw digest. Lease fields are both null at rest or both non-null for one fenced
claim. For the outbox state matrix, `pending` has attempt zero or a retry
attempt, non-null `availableAt`, and null lease fields; `leased` has null
`availableAt` and non-null fencing token/expiry; terminal `relayed|poisoned`
has all scheduling and lease fields null. Only pending-to-leased,
fenced-transient-or-expired-leased-to-pending, and leased-to-relayed/poisoned
transitions are valid; immutable identity/body/hash fields and ciphertext are
reused on retry. P23-003G owns query
orchestration; the B/C/F/G ownership DAG is acyclic.

## 1. Release and compatibility boundary

Stable `1.2` remains the current public baseline while Phase 23 is proposed.
Current command names and their documented behavior remain unchanged. New
commands, JSON schemas, route/schedule projections, AuditRecord, signed
export, and Classic removal are not added to Stable docs or generated public
artifacts before release authority is updated.

Stable Structured Log/OpenTelemetry already provide current
Operation/Correlation/Trace/Span correlation, including the safe
`ExecutionScopedLogger` observability projection. The future-only boundary is
the new four-surface Audit correlation contract and its canonical AuditRecord
fields; it does not replace the Stable correlation contracts.

Stable `1.2` already provides FrankenPHP Worker Mode as the default HTTP
runtime. P23-004A hardens that existing default into the sole supported
production HTTP profile and gates Classic fallback/profile removal; P23-004B
Extension Worker work and same-process Deferred remain proposal-only.

Stable `1.2` already provides Symfony-backed `list` behavior. The existing
`operation:list` remains available during migration while P23-002A implements
the separate BlackOps-owned replacement; `list` is Stable current and `ls` is
the only new name. P23-002A accepts every Stable combination of optional
namespace, `--raw`, `--short`, and `--format=txt|json|xml|md`; repeated
`--format` is last-wins. New `--json` and `--all` are outside the Stable input
set; `--json` alone equals `--format=json`, and only `--json` plus explicit
`--format` returns exit `2`. `--raw` affects txt only and is ignored for
non-txt; `--short` is an exact no-op. Nested exact, unique abbreviated, and
ambiguous and unknown namespace diagnostics/exit are fixed by Symfony Stable
`1.2.0` behavioral goldens; a namespace may contain multiple colon-delimited
segments and does not receive a new blanket exit `2`. Only a new invalid
option/format or new `--json` plus explicit `--format` returns exit `2`. The
replacement renders only safe BlackOps projection fields. Missing, malformed,
unsupported, stale, and build-mismatched application projections are the list/ls recovery
exception: framework rows plus status return exit `0`; operational read
failure returns `1`; complete route/schedule artifact failure returns `2`.
The single P23-002A/global reservation registry checks canonical and
alias names `list|ls|help|completion|_complete|route:list|route:ls|
schedule:list|schedule:ls|diagnostics:check|queue:status|about|outbox:status`;
P23-002B/C/D/E reuse it, collisions fail closed with owner/name/actionable
rename. `worker:list` and `worker:ls` are neither BlackOps-owned nor reserved;
an Application may own either as a canonical command or alias, while an
unregistered spelling follows normal Symfony unknown-command behavior. There
is no Framework Job registry or built-in Job listing.
Stable collision-free and upgrade-collision fixtures are owned by P23-002A.
P23-002A also implements and independently reviews the future bounded
`operation:list --strategy=inline|deferred` filter. The wire maps only the
two exact built-in strategies; custom strategies remain visible only when no
filter is supplied.
The filter and replacement remain unreleased until P23-006 and compatibility
acceptance; Stable no-filter Human behavior remains unchanged until then.
Consumers migrate to the versioned v1 machine-wire compatibility note only
after its JSON, raw, XML, and Markdown parity goldens are accepted.
Symfony descriptor XML/Markdown output is never adopted as a compatibility
contract.

The new route and schedule commands read compiled artifacts. Applications
must run the explicit build step before using them. Missing/stale/mismatched
artifacts fail closed; read-time reflection and application-code fallback are
not migration paths.

No `worker:list` compatibility alias is reserved by BlackOps because worker
names are Application-owned when registered; an unregistered spelling follows
normal Symfony unknown-command behavior. Inline and deferred operation
definitions belong to the future `operation:list --strategy=inline|deferred`
filter; queued rows and process capability remain separate future surfaces.

Audit migration does not rename existing retention, replay, rotation, or
outbox audit-like tables into AuditRecord. Each source receives an explicit
versioned adapter and immutable phase-event mapping. Existing history is not
backfilled or represented as a general Audit Trail without a bounded migration
decision, source classification, tenant proof, and verification evidence.

The retention Lazy option defect is a separate bounded Stable preflight task.
It must be fixed and reviewed independently; it is not concealed in the 1.3
Audit or command work.

## 2. DAG overview

```text
P23-001 Discovery / D145 / Spec105 / Spec106 [current technical proposal]
        |
        +--> P23-001R Stable retention Lazy option correction (separate preflight)
        |
        +--> P23-001S Stable list documentation truth correction (Historical Round 21 Accepted child prerequisite)
                |
                +--> P23-002A/B/C atomic projection acceptance slice
                        (A wire/lock/command, B route, C schedule/final activation)
        |       +--> P23-002D diagnostics:check + queue:status
        |               +--> P23-002E about + outbox:status
        |
        +--> P23-003A AuditRecord catalog + typed safe projection
                +--> P23-003B JCS body/hash + encrypted append/chain/idempotency
                        +--> P23-003C required/durable transaction + outbox delivery
                                +--> P23-003D ordinary framework phase-event adapters
                                +--> P23-003E application authoring/projector journey
                                +--> P23-003F checkpoint/anchor signing + reusable verify
                                        +--> P23-003D-control checkpoint/anchor adapter slice
                                                +--> P23-003G query/export/audit-of-audit/retention/hold
                                                        +--> P23-003H future physical deletion/deletion manifest review

P23-001 --> P23-004A existing Worker Mode hardening + Classic migration/removal gates
P23-001 --> P23-004B optional Extension Worker falsification spike

P23-002*, P23-003A..G, and P23-004A accepted implementation evidence (the
documentation task does not gate itself) --> P23-005 documentation IA
P23-001R + P23-002* + P23-003A..G + P23-004A + P23-005 --> P23-006 release gate
P23-004B and future P23-003H are never dependencies of P23-006 unless a
separate accepted scope decision explicitly adds them.
```

P23-003F signing and its ordinary-signature `audit.access.sign.accessed` delivery require
accepted P23-003A/B catalog/storage and P23-003C required/durable paths (the F
edge is not through the application-authoring E slice). The P23-003D-control
slice starts only after accepted F and consumes typed F outcomes. P23-003G
query/export/hosted-verify orchestration and its `accessed` events require accepted
A/B/F and D-control; ordinary D adapters may proceed after C. This ordering is
acyclic.

P23-001S is a non-1.3 repository-blocking prerequisite before final P23-001
acceptance and P23-002A. Its current child Task and Report are the Round 21
Accepted status authority after fresh child General `P1=0/P2=0/P3=0`; prior
reopen evidence is historical and the child is not recreated. It corrects the
Guide to observed Stable behavior; it does not create a 1.3 feature or claim
that Stable `list` is side-effect-free.

Independent Audit and runtime tracks may proceed in parallel after P23-001
review, but P23-006 cannot accept a track with an unreviewed dependency.

## 3. Task slices

The active delivery slices use the sole current Audit wires from the Round29
block. `AuditQueryScope v1` is
`{schemaVersion:1,kind:"audit_query_scope",partitionId,tenantBinding,
filters,order,limit}` and `AuditAccessTarget v1` is
`{schemaVersion:1,kind:"audit_access_target",queryScopeDigest,pageMode,
snapshotHeadSequence,cursorDigest,continuationPosition}`. The query context
is `{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,target,
authorizationDecision,requestedAt,nonce,requestRef}`; its requestRef binds the
target and decision, and cursor fields bind queryScopeDigest, raw cursor,
fixed head, position/lastSequence, and issuer requestRefDigest separately. Its
key window is exactly `notBefore <= issuedAt <= trustedNow < expiresAt <=
notAfter`; issuance rejects `expiresAt > notAfter` and accepts equality. A
verification key remains until every cursor expiry plus accepted skew. Rotation,
expiry-equality, and expiry>notAfter negatives belong to P23-003G.
The current first-page global scope golden has JCS/LP lengths `311/348` and
`queryScopeDigest=d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`;
its first-page target has null head, cursor digest, and continuation position.
The target-bearing Access golden has JCS/LP lengths `374/418` and requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`.
P23-003G owns Node/Docker-PHP parity and the target/digest, target-less
old-golden, and external-allow/requestRef-injection negative fixtures.
`AuditQueryResult v1` is only `ok|empty|unavailable`; valid store failure and
legal record-cap overflow remain `AuditQueryResult.unavailable` with
`resource_unavailable|record_too_large`, while only malformed/auth/cursor/
scope/head/position failures use the finite `AuditQueryError v1` precedence
before lookup/decrypt.
`AuditOutboxRecord v1` is the exact row with `version` and states
`pending|leased|relayed|poisoned`. A pending claim has null stored lease
fields, requires the expected version, installs a new token, and applies one
exact +1 CAS; a leased settlement requires its matching stored unexpired token
and expected version; expired-lease recovery requires its matching stored
expired token and expected version. Its active idempotency/content hash
formulas are the established ENC formulas in Round29. `AnchorIntent v1`
uses exactly `prepared|externally_accepted|externally_rejected|
receipt_inputs_persisted|receipt_persisted|acknowledged` and the active §9.6.1
receipt wire. `AuditRetentionState v1` includes evaluator/owner and the exact
policy/hold snapshot refs, hashes, versions, and `evaluationFenceVersion`; its
`policy` is null only for `retained/policy_unavailable` and otherwise
byte-exactly equals the authenticated policy snapshot. It has only
`retained|eligible_for_future_deletion` and its closed reason matrix. Any
older task-slice summary is delivery context, not another current wire.

### P23-001 — Discovery (historical Round 13 checkpoint)

Deliver D145, Specification 105, this plan, the Task Report, and current-state
guard updates. Confirm the Stable/Roadmap boundary, command and runtime
failure matrices, Audit boundary, official evidence, and capability gaps.
That historical proposal checkpoint was Round 13 Correction Implemented /
Review Requested, not Accepted. Round 12 is historical Changes Requested:
General `P1=5/P2=1/P3=0`
and Audit `P1=6/P2=3/P3=2`; those lanes remain separate. Round 11 is historical
Changes Requested: General `P1=2/P2=2/P3=0` and Audit `P1=7/P2=3/P3=1`; those lanes remain separate. Round 7 Changes
Requested is historical: General `P1=4/P2=1/P3=1`
and Audit `P1=6/P2=6/P3=0`; those lanes are not combined. Round 8 fresh review
is historical Changes Requested: General `P1=2/P2=1/P3=0` and Audit
`P1=5/P2=4/P3=1`; those lanes are not combined. Independent Round 6 evidence remains separate:
full-review `P1=3/P2=4/P3=1` and Audit review `P1=2/P2=6/P3=1`; these counts are
not combined or deduplicated into an aggregate. This checkpoint does not claim
Green, Accepted, or Decided.

Round 8 management contract retains Round 7's canonical AuditEventCatalog
delivery union exactly `required|durable` and adds the following corrections:
`AuditIngressEnvelope v1`, trusted ingress stamping/high-water recordedAt,
`AuditOutboxRecord` ciphertext/AAD protection, complete-record synthetic 1 MiB
cap, non-null semantic tuple values, <=256-byte typed evidence strings,
sealed framework namespace/owner authority, one verifier stage table,
non-null emitted result references, complete freshness chronology,
requester-partition deny/absent mapping, AnchorIntent crash recovery,
15/16 MiB manifest caps, and same underlying connection/root transaction
identity. Round 7 management contract: canonical AuditEventCatalog delivery is exactly
`required|durable`; `best_effort` is only an observed JSONL projection with no
Audit Trail claim. Conditional event families register separate durable and
required selectors, selected before mutation/response: only explicit
`required` selects required and only explicit `durable` selects durable;
missing, omitted, and unknown requirements fail closed before effect or
response and are never defaulted. Strict export
preflight occurs before allocation/read/streaming; the unsigned manifest hash
is recomputed and constant-time compared before signature acceptance. After
trusted signature success and root-confined raw-digest verification, each
unsigned catalog semantic hash is recomputed and matched to record/file
references, independently from raw file SHA-256. Global failures return
`partitions:[]` before any
partition-local result. The exact normalized tenant-to-partition mapping,
closed verification context, evidence-field uniqueness, and trusted
`recordedAt`/`occurredAt` bound are part of this proposal only.

### P23-001R — Stable retention correction preflight

Add the missing `idempotency-record-days` option to both retention Lazy command
definitions, or record a bounded alternative if the current command wrapper
changes. Preserve current retention semantics. Run the focused retention tests,
management checks, and independent Stable review. This task is not a 1.3
feature and must not add AuditRecord.

### P23-001S — Stable list documentation truth correction (repository blocker)

This is a separate non-1.3 repository task required before final P23-001
acceptance and before P23-002A. The former Stable Guide sentence that called
`list` side-effect-free is historical; the current Guide has been corrected
to the behavior actually proven, with constructor and lazy-factory fixtures
for default, raw, JSON, XML, and Markdown invocations. P23-001S is the current
Round 21 Accepted child; its historical reopen evidence is preserved and no
new child packet is created. Explicit command registrations and Symfony
structured descriptors can construct or resolve application code. No 1.3
feature or Stable runtime change is made here.

### P23-002A/B/C — atomic projection acceptance slice

P23-002A owns the shared ProjectionBuildSet v1 wire, command projection producer,
list/ls reader, publisher, and lock. P23-002B owns the route row producer and
route commands; P23-002C owns the schedule row producer, schedule commands, and
final integration activation. Implement the dedicated compiled command projection, Stable input compatibility
(optional multi-segment colon-delimited namespace, `--raw`, `--short`,
`--format=txt|json|xml|md`, and `--json` alias), reserved-name/collision
preflight, exact alias, Human/raw/JSON/XML/Markdown safe projection, hidden
policy, safe fields, deterministic sorting, and migration diagnostics. Stable
namespace exact/unique-abbreviated/ambiguous/unknown diagnostics and exits are
goldened; only new invalid option/format or `--json` plus explicit `--format`
returns exit `2`. Kernel composition retains the existing explicit
class-string construction/prebuilt-instance behavior. Prove that executing the
projection command after composition adds no command factory, container, DB,
Provider, handler, or application-source resolution. A and B may prepare bounded inputs but may not publish or
activate a partial root; C wires all three producers into the A-owned publisher
and only the complete three-file root is accepted. Implement the bounded future
`operation:list --strategy=inline|deferred` filter for the two exact built-in
strategies,
and own its independent implementation review. Stable collision-free and
upgrade-collision fixtures are required. Preserve current Stable no-filter
Human behavior; the replacement and filter remain proposal-only and
unreleased until P23-006 and compatibility acceptance.

P23-002A is delivered as two bounded packets without changing the atomic
acceptance boundary. P23-002A1 owns the shared wire, injected locator,
verified-handle filesystem boundary, lock, reader, publisher, and bounded GC;
it uses synthetic all-three inputs and does not connect `build:compile`, the
Console Kernel, or public configuration. P23-002A2 owns the command producer,
embedded Framework recovery rows, `list`/`ls`, global reservation registry,
Stable input compatibility, and the bounded `operation:list --strategy`
filter. Neither A packet may activate a projection root. B adds the route
component, and C alone chooses the production locator, connects all three real
producers, publishes the complete set, and activates readers. D146 defines the
PHP filesystem boundary.

The single reader input is the installation-owned `ProjectionBuildSet v1`
root. It is the only projection wire: the root is the closed
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` object and
each descriptor/file has its closed `kind`, `inputHash`, relative path, and
SHA-256. A command projection file has the closed header and records
`{name,aliases,description,origin,hidden}`; it is not a standalone
`projectionInputHash` envelope. Without an application projection it still
lists `build:compile`, `list`/`ls`, `help`, `completion`, and every safe
framework row at exit `0`; same-build/current/valid application rows are
merged only when available.
Every P23-002A/B/C reader validates the root and all three projection
descriptors/files under one shared lock before selecting its dedicated rows.
Missing, malformed, digest, build, or input mismatch in a non-selected file
invalidates the complete set; no reader may accept a partial generation.
Command `list`/`ls` structured formats emit the six-state
`applicationProjectionStatus` enum
`available|absent|malformed|unsupported|stale|build_mismatch`. ENOENT is
`absent`; permission/I/O/unreadable is operational exit `1` and has no success
status; oversize/invalid bytes/JSON/duplicate/unknown/missing/type/record
shape is `malformed`; syntactically valid unsupported schema/kind is
`unsupported`; build mismatch is `build_mismatch`; matching build with input
hash mismatch is `stale`; all match is `available`. That order is combined
failure precedence. List/ls returns framework rows plus one deterministic
`applicationProjectionStatus=<status>\n` stderr line for all six states at
exit `0`; route/schedule complete artifacts return exit `2` for artifact
failure and exit `1` only for operational read. Command Human, raw, JSON, XML,
and Markdown rows/aliases are identical and deterministic. XML v1 and Markdown v1
are exact continuous UTF-8/LF/no-BOM safe wires with deterministic order,
escaping, one final LF, and Spec 105 golden hashes
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968` and
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`;
repeated `--format` is last-wins and `--short` is an exact no-op in all
formats.

The injected trusted root contains the fixed regular lock file
`.blackops-projection-build-set.lock`. D146 defines “nofollow” as pre-open
`lstat`, one `fopen`, pre-I/O `fstat`, and second `lstat` agreement on regular
type/device/inode inside that trusted directory; it is not a kernel
`O_NOFOLLOW` claim. A reader takes a shared `flock` before opening the root and
holds it through root validation, all three verified-handle regular-file
opens, row selection, and rendering. A publisher may
not prepare or write any generation file until it has taken exclusive flock;
it holds that lock through same-fd validation, root temp fsync and atomic
rename, parent-directory fsync, and bounded GC. GC removes only files
unreferenced by the now-current root. Lock replacement,
symlinks, special files, interleaved publication, and orphan cleanup beyond
the bound fail closed; a crash before root publication leaves the old complete
set visible and kernel lock release ends reader quiescence.

The future `operation:list --strategy=inline|deferred` option is optional,
case-sensitive, and may occur at most once. Missing value, duplicate, unknown,
or case-variant values return exit `2` before Provider/compiler work and emit
no partial output. Absent option preserves Stable no-filter Human bytes,
headers, order, and exit. A valid value filters after successful compilation
and before the existing unsigned-byte `typeId` sort, emits the unchanged
`Type ID|Definition|Execution Strategy` table (header-only for zero rows),
adds no JSON/DB/source fallback, and preserves Provider/compiler exceptions.
No current `operation:list` filter is Stable 1.2; this is unreleased.

The current static guard rejects active use of the legacy standalone
`projectionInputHash` envelope, direct Operation Manifest schedule readers,
and HTTP artifact plus read-time fixed-descriptor joins. Explicitly labelled
historical sections may quote those superseded terms; they are not current
normative sources.

### P23-002B — route component within the atomic slice

Open and validate the `ProjectionBuildSet v1` root and all three same-fd
projection files under one shared lock before selecting and rendering only the
dedicated `blackops_route_projection` rows. P23-002B's build step combines Operation HTTP rows
with the framework-fixed descriptor before publication; route:list never
joins an HTTP artifact with a fixed descriptor at read time and never uses a
runtime fallback. The public `Application::http()` composition positive
fixture includes `GET /operations/{operationId}` with `strategy: null`,
`operationType: null`, and `origin: framework_fixed`. Validate the closed
descriptor/file `inputHash`, build identity, canonical output and collision
behavior. Placeholder names are structurally equivalent, so
`/operations/{id}` is a collision with the canonical row. P23-002B owns exact
wire and equivalent-placeholder/static-segment collision fixtures, with no
handler, middleware, container, DB, or source resolution.
Future optionality requires an explicit versioned capability field and
positive/negative fixtures.

P23-002B extends the disconnected command target metadata from A2's exact 29
rows to exact 30 by adding canonical `route:list` with exact alias `route:ls`,
description `List compiled HTTP routes.`, and `hidden=false`. The current
25-name runtime `FrameworkCommandNames` remains unchanged; P23-002C later adds
the schedule target as row 31 and performs the sole atomic activation.

Successful route JSON is the closed `{schemaVersion:1,routes:[...]}` object and
does not carry `applicationProjectionStatus`; successful Human output uses
`Method|Path|Strategy|Operation Type|Origin`, empty cells for null values,
formatter-safe literal cells, `OUTPUT_RAW`, empty stderr, and exactly one final
LF. JSON is JCS plus one LF. Artifact states return exit `2` with exactly
`Route projection is unavailable or invalid.\n`; operational read returns
exit `1` with exactly `Projection build-set read failed safely.\n`; invalid
input and invalid expected identities use `Input error.\n` and
`Route projection input is invalid.\n` respectively, with no stdout.

The A1 reader gains a callback-shaped typed-status seam that holds the shared
lock through selected-row rendering and output write. For an identical exact
30-row input, moving the accepted A2 list component to that seam does not
change its bytes or exits. B's intentional 29-to-30-row target expansion adds
exactly one `route:list` row to the command projection and every BlackOps-owned
list available/fallback golden; it does not change the unregistered Stable
Symfony `list` bytes. B route uses the same seam. Returning a typed read result
and rendering after unlock is not accepted.

### P23-002C — schedule component and final activation within the atomic slice

Open and validate the `ProjectionBuildSet v1` root and all three same-fd
projection files under one shared lock before selecting and rendering only the
dedicated `blackops_schedule_projection` rows. P23-002C's build producer has already compiled the
Application Schedule metadata. The reader evaluates no Provider/container/
DB/clock/due/Occurrence/maintenance-scheduler state at list time. The two spellings are mutually
identical: valid complete artifact exit `0`, operational read exit `1`, and
missing, malformed, unsupported, stale, or build-mismatched complete
artifact exit `2`. This remains distinct from list/ls recovery, which returns
framework rows plus all six application status values at exit `0`.

The Production root is the single
`ApplicationBuildConfiguration::projectionBuildSetRoot` value derived from
`dirname(app.build.container)`.
The Kernel registers list, route, and schedule families from one
`ApplicationProjectionBuildSetStatusSource`; none accepts Reader plus
caller-provided exact-three hashes directly.
The Production Reader validates its configured build ID and the complete
published identity graph with `withPublishedStatus`.

The producer takes the already-compiled `OperationRegistry` and emits only
metadata with non-null `schedule` as
`{name,operationType,cron,timezone,strategy}`.
Route and Schedule share the pure `ProjectionExecutionStrategyWire` mapper.
The command target registry becomes exact 31, while
`FrameworkCommandNames::all()` stays exact 25 and the reservation registry
stays exact 13.

Schedule Human columns are exactly
`Name|Operation Type|Cron|Timezone|Strategy`; all cells are formatter-escaped
and complete bytes use `OUTPUT_RAW`.
JSON is exact RFC 8785 JCS
`{schemaVersion:1,schedules:[...]}` plus one LF and has no status member.
Specification 105 owns the exact success, input, artifact, operational, and
configuration-input stderr/exit table.

C connects all three real producers and one Publisher to `build:compile`, then
activates all three reader families in one Kernel change.
The build integration and projection-only atomicity boundary are exactly the
current delivery correction above; no command-only, route-only, or
schedule-only Production root is accepted.

### P23-002D — `diagnostics:check` and aggregate `queue:status`

Implement the exact P23-002D contract in Specification 105 after P23-002C
Acceptance. This is one completion slice: Diagnostics and Queue components may
be built in parallel, but both must be integrated before the slice can be
Accepted.

P23-002D owns:

- the explicit `ApplicationBuilder::withOperationalHealthQuery()` authority;
- exact Application/InternalApplication-to-Observability architecture edges;
- a bounded exact-six readiness normalizer and combined Projection/readiness
  report;
- `diagnostics:check [--json]` exact Human/JCS/error/exit behavior;
- the host-operator, `installation`-scope queue decision;
- one-row PostgreSQL queue aggregate, exact buckets/ages, bounds, typed
  storage/integrity failures;
- `queue:status [--json]` exact Human/JCS/error/exit behavior;
- simultaneous Kernel activation and target exact `33`, Framework names exact
  `27`, reservation exact `13`;
- negative no-route/no-Job/no-row/no-tenant/no-payload evidence.
- Core Queue ports/values/failures with Internal rendering and PostgreSQL
  Transport implementation;

It does not own a tenant-facing surface, `queue:list`, `worker:list`, Outbox
status, schema migration/index/counter, performance SLA, or public HTTP route.
P23-002E continues to own `about` and `outbox:status`.

The delivery is split into three non-overlapping worker packets after one fresh
Sol Max preflight:

1. Luna Max Diagnostics component owns only new Diagnostics Source/direct tests.
2. Luna Max Queue component owns only new Queue/PostgreSQL Source/direct tests.
3. Luna Max Integration starts after both components and alone owns existing
   Application/Registry files and integration/golden tests.

Shared management/spec files are not edited concurrently by component workers.
No worker commits, stages, pushes, or expands Files Allowed before review.

Required executable evidence includes:

- diagnostics exact eight Projection codes, readiness exact-six positive and
  fail/throw/unavailable/invalid contractions, one-clock, lock separation, and
  input-zero-resolution;
- queue empty/each/mixed/equality/exclusion/outbox-only/multitenant aggregates,
  one-clock, integer/null boundaries, running-null-lease, source shape, and
  config/storage/integrity failures;
- exact Human/JCS bytes, stdout/stderr/exit, decorated/undecorated parity, no
  ANSI/BOM/CRLF, one final LF;
- canary rejection for credential, DSN, SQL, path, class, build identity,
  tenant, Operation identity/type, payload, reason, attempt, and lease owner;
- list/help/invalid-input constructor/query/DB/provider/container/handler zero
  resolution;
- concrete direct Command registration and safe Symfony input contraction
  before dependency execution;
- target exact `33`, Framework names `27`, reservation `13`, two new command
  projection rows, collision behavior, no aliases, and no public route/Job
  registry.

Iteration uses focused component tests. After all Source is frozen, run one
complete Final Gate from the beginning and one fresh Sol Max implementation
review. A Source-changing correction invalidates the complete gate; a
management-only status sync does not rerun Product gates.

### P23-002E — `about` and `outbox:status`

Implement the exact P23-002E contract in Specification 105 only after
P23-002D Acceptance. This is one completion slice: About and Outbox components
may be built in parallel, but both must be integrated before Acceptance.

P23-002E owns:

- direct `composer-runtime-api:^2.0` authority and bounded installed pretty
  version metadata for `blackops/framework`;
- bounded current-process PHP/SAPI metadata and exact ProjectionBuildSet status;
- `about [--json]` exact Human/JCS/error/exit behavior with no capability
  registry, build identity, path, dependency, environment, or runtime-profile
  claims;
- host-operator, `installation`-scope Outbox aggregate authority;
- exact seven Outbox buckets, three ages, three anomaly counters, safe integer
  bounds, and one-row PostgreSQL source;
- `outbox:status [--json]` exact Human/JCS/error/exit behavior;
- simultaneous Kernel activation, target exact `35`, Framework names exact
  `29`, reservation exact `13`, visible list exact `34`, producer exact `38`;
- negative no-route/no-row/no-tenant/no-payload/no-capability-inference evidence.

It does not own Outbox row listing, tenant selectors, pagination,
authorization API, `dead-letter:list`, Worker/process capability registry,
HTTP mode discovery, schema/index/counter migration, performance SLA, public
HTTP route, Stable Documentation, or release work.

After one fresh Sol Max preflight, delivery is split into three non-overlapping
Luna Max packets:

1. About component owns only new About Source/direct tests.
2. Outbox component owns only new Outbox/PostgreSQL Source/direct tests.
3. Integration starts after both and alone owns Composer/lock/Deptrac,
   Application/Registry existing files, and shared golden/integration tests.

The first two packets may run in parallel. They do not edit shared management
or existing integration files. No worker commits, stages, pushes, or expands
Files Allowed before review.

Required evidence includes:

- About installed-version/PHP/SAPI grammar boundaries, exact eight Projection
  states, dependency call counts, exact Human/JCS bytes, and no-leak canaries;
- dependency-shaped Composer installed dataset through the real metadata source,
  with global `InstalledVersions` state restored in `finally`;
- Outbox empty/each/mixed/equality/sent exclusion/two-tenant aggregates,
  exact-three anomalies, bounds/nullability, one-clock, one-row SQL, and no
  forbidden table/column/data access;
- exact UTC microsecond minimum text plus offset／abbreviation／precision／type／
  calendar negative fixtures;
- safe input contraction before metadata/Projection/DB work;
- exact stdout/stderr/exit, decorated/undecorated parity, no ANSI/BOM/CRLF,
  one final LF;
- exact `35/29/13/34/38` inventories, collision behavior, no aliases, no
  public route, and no Stable Guide/Artifact occurrence.

Iteration uses focused component tests. After combined Source/Test freeze, run
one complete Final Gate from the beginning and one fresh Sol Max implementation
review. A Source/Test-changing correction invalidates and restarts the complete
gate; a management-only status sync does not rerun Product gates.

`dead-letter:list` remains deferred until row identity, tenant, redaction,
pagination, and authorization are explicitly defined.

### P23-003A — AuditRecord catalog, security/access types, and typed safe projection

D147 splits this node into serial P23-003A1 Catalog foundation and P23-003A2
Record／Projection packets. A2 starts only after A1 Acceptance; P23-003B starts
only after both children and parent P23-003A are Accepted. A1 owns Catalog-only
RFC 8785 JCS, `catalogHash`, the exact Framework catalog, typed `opaque_id`,
`AuditReadRequestBasis`, and `AuditReadContext`. A2 owns draft/logical schema validation and the exact
observed safe projection. Neither child activates runtime behavior.

A1's exact `#[PublicApi]` marker inventory is empty. P23-003C owns the future
dedicated `audit_opaque_id_v1` provider/reference/availability/injection
boundary before P23-003D/E consume keyed derivation. The A1 pre-commit gate
freezes dependency metadata, Source, Test, and fixtures, but defers Git-vs-
Composer package export to an explicitly authorized committed-candidate
parent/release gate.

Define event categories, action type/version, phase, actor/principal,
resource, outcome, policy, tenant, safe evidence schema, owner/component, and
delivery mode as framework-owned core data types, including closed
domain-hashed `AuditEventCatalog v1`, `event.catalogHash`, and bounded flat
primitive evidence. Canonical `policy` is exactly
`{id,version,decision,reasonCode,auditDelivery}`, with
`decision=allow|deny|not_applicable` and `auditDelivery=required|durable`; the
versioned policy evaluator assigns the latter before effect/response, and
missing, omitted, or unknown requirements fail closed before effect/response.
`actor.principal` is a
typed object or null for invalid/unknown/unauthenticated context, while
`actor.execution` is required. The current HttpAuthenticator exposes no
module/method ID; only explicitly typed provider provenance may be added.
Include typed candidate events for authentication success/invalid,
authorization allow/deny, and privileged Operation Status/Journal/Outcome/
diagnostic read allow/deny/unavailable. For each of Status, Journal, Outcome,
and Diagnostics the framework catalog owns `.requested`, `.unavailable`, and
`.accessed` tuples. Absent and cross-tenant reads are `.unavailable` with
`action.phase=unknown`, `outcome.status=unknown`,
`outcome.code=resource_unavailable`, `policy.decision=not_applicable`,
`policy.reasonCode=resource_unavailable`, and
`AuditReadContext.authorizationDecision=resource_unavailable`; all three
outcomes have required catalog delivery, with the canonical append durably
persisted before a response or failure is released. Own the catalog and
safe projection shapes only. P23-003A1 owns Catalog-only RFC 8785 JCS and
`catalogHash`; P23-003B owns AuditRecord JCS logical body/hash and chain
append. Add negative tests for
inferred action/resource, raw payload, throwable, secrets, credentials/tokens,
request bodies, and duplicate lifecycle events. Include authenticated/invalid
actor-null, unauthenticated deny, anonymous no-event, allow/deny,
privileged-read allow/deny, absent-ID, and credential-nonrecording fixtures.
Application registration and projector authoring belongs to P23-003E. Each
event definition has an explicit positive `evidenceVersion`, and a record's
`evidence.version` must equal it. The immutable selector is
`(catalogHash,event.type,event.version,evidence.version)`; within one catalog
`(type,version,evidenceVersion)` is unique, and a same-selector schema change
fails closed. Each event definition also has `semanticTuples`, a 1..32 unique
list of exact objects
`{actionType,phase,resourceType,outcomeStatus,outcomeCode,policyDecision,
policyReasonCode}`. Strings are NFC/no-control and bounded; the three
resource/outcome-code/reason fields are non-null/non-empty bounded values in v1;
every tuple value is non-null and non-empty, and each resolves to its v1
action registry, phase/status/decision enum, or resource/outcome/reason code;
wildcard and null tuple values are forbidden. The distinct tuple
actionType set equals `actionTypes`, and writer/verifier require full tuple
equality with the record. The catalog permits at most 256 events, 32 action
types per event, 32 evidence fields per event, 8,192 evidence bytes per record,
256 UTF-8 bytes per evidence string field, and 1,048,576 unsigned JCS catalog bytes.
Missing/unknown classification, non-null integer/boolean classification,
bad safe-code grammar, and raw opaque-token fixtures fail; valid safe-code and
typed-derived-opaque fixtures pass.
Each field descriptor is exactly
`{name,type,required,nullable,maxUtf8Bytes,classification}`, with type exactly
`string|integer|boolean`; `maxUtf8Bytes` is positive only for strings and
`null` for integer/boolean. String classification is exactly
`safe_code|opaque_id`; integer/boolean classification is exactly `null`;
descriptor name `version` is reserved and rejected. `safe_code` is NFC,
lowercase ASCII matching `^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$` and <=256 bytes;
`opaque_id` is exactly `oid:v1:<64 lowercase hex>` from a typed domain/keyed
mapper, not raw arbitrary input. The verifier checks grammar/category only and
cannot prove secret absence; writers reject/derive credential/token input and
Application review owns semantic mapping. Syntax validation alone is not
secret detection. Evidence field names are NFC-normalized-byte unique and
`maxEvidenceBytes` measures UTF-8 bytes of RFC 8785 `JCS(evidence)` including
version. Unknown, nested, list, object, float, and
non-finite evidence is rejected. Event types and action types are non-empty
normalized UTF-8 strings of at most 128 bytes with no control characters;
category is exactly the AuditRecord category enum. A record category must
match its catalog definition, action.type must be one of its actionTypes, and
source.owner must equal catalog.owner. Definition delivery is the sole writer
mode and is exactly `required|durable`: P23-003C selects it before
mutation/response and cannot override it. A conditional family registers
durable and required definitions under separate selectors; the versioned
policy evaluator assigns required `policy.auditDelivery` before effect or
response, only explicit `required` selects required, and only explicit
`durable` selects durable. Missing, omitted, or unknown requirements fail
closed and are not defaulted;
the recorded value must equal the selected definition's `delivery` exactly;
four explicit/missing/omitted/unknown policy paths are fixtures.
`best_effort` is only a separate observed JSONL projection with no Audit Trail
claim. Another mode requires a new event version/evidenceVersion. Offline
verification resolves delivery from the record's catalog. Authentication
success/invalid always set `durable`. A conditional family may use the same
event type at
`version:1/evidenceVersion:1/delivery:durable` and
`version:2/evidenceVersion:2/delivery:required`; authentication
success/invalid remains durable-only. Add category/action/owner/delivery mismatch and
  over-cap/control-character/semantic-tuple mismatch negatives.
The canonical top-level and nested objects are closed: event, action, actor,
principal, execution, resource, outcome, policy, tenant, time, source,
correlation, attempt, and integrity accept no unknown members. `evidence`
requires its version and only the registered exact event-definition members;
credentials, tokens, raw payload/request bodies, throwables, ciphertext, and
key material are prohibited anywhere in the canonical body. P23-003A owns
these catalog/schema and safe-projection types. It does not own AuditRecord
logical JCS/hash or chain append; the D147 Catalog-only JCS/hash is the
P23-003A1 exception.

### P23-003B — JCS body/hash, encrypted canonical append chain, idempotency, and tenant auth

Add the new BOPD `AuditRecord` purpose, RFC 8785 JCS canonical logical bytes,
the exact contentHash/recordHash formulas, and the partition-global
open/closed/sealed segment chain. P23-003B owns the closed
`AuditSegmentState v1` and one active open segment per partition: close CAS
freezes the final sequence/head hash and atomically installs the next open
segment before releasing the partition fence; stale writers cannot append to
closed/sealed segments. The first sequence is 1 only at partition genesis;
rotation uses old final+1 and old head as predecessor. Crash before rotate
commit changes nothing; after commit resumes the same closed boundary; after
checkpoint reuses the same checkpoint ID/bytes. Own logical-body bytes,
contentHash, recordHash, sequence allocation, same-ID/same-contentHash
idempotency, and same-ID/different-contentHash failure. Add bounded clear indexes,
tenant-aware authorization-before-decrypt and the tenant-aware storage lookup
primitive. P23-003G owns the public bounded default-deny query API,
orchestration, pagination, and export. Same-ID/
different-contentHash must fail closed. P23-003F consumes completed records;
it does not own chain append. P23-003F owns reusable catalog/hash, record,
checkpoint, anchor, freshness, and offline recomputation verification;
P23-003G calls those verifiers and does not reimplement them.
The durable ingress is the exact closed `AuditIngressEnvelope v1` with the
closed 14-field `AuditRecord v1` body without `integrity`; `contentHash` binds
that canonical body. Its closed `time` object contains `recordedAt`, and the
logical record has no top-level `recordedAt`. Its individual bindings are
`recordId=logicalRecord.recordId`,
`recordedAt=logicalRecord.time.recordedAt`,
`tenantBinding=logicalRecord.tenant`, and
`partitionId=derivePartition(tenantBinding)` under the active mapping. A
partition is never compared to the logicalRecord object. Tenant null selects
`system:global`; a present tenant selects only its derived partition. P23-003B
owns tenant-null/present and recordId/nested-time.recordedAt/tenant/partition
substitution fixtures, including Node/Docker-PHP parity. The Node/PHP positive
fixture proves that envelope `recordedAt` equals
`logicalRecord.time.recordedAt`. Any substitution, omission, or mismatch of
that envelope/nested binding, or an injected top-level
`logicalRecord.recordedAt`, fails closed before enqueue, decrypt, or canonical
append. Every top-level extra member in `logicalRecord` remains forbidden by
its closed 14-field schema.
Verify cross-tenant reads are indistinguishable from unknown records. Map
tenant null to `system:global`; map a closed normalized NFC `{type,id}` tenant
to `"tenant:" || HEX_LOWER(SHA-256(ENC("blackops.audit.partition.tenant.v1")
|| ENC(JCS({"type":type,"id":id}))))`, with non-empty <=256-byte strings and
no controls. Writer/verifier and `integrity.partitionId` must use this exact
formula; separator, normalization, and system-collision negatives are required.
The canonical append assigns `recordedAt` from trusted UTC clock authority;
producer overrides fail. Required uses the canonical store and durable uses
trusted local Audit Outbox ingress, each once inside the mutation transaction,
with per-authority/partition high-water `max(now,lastAssignedRecordedAt)`.
Sequence is allocated only at canonical append and is the sole order/
pagination cursor; outbox-only FIFO uses monotonic `ingressOrdinal`. Relay/
retry preserve recordedAt, recordId, contentHash, and logical bytes. Owning
adapter/application asserts `occurredAt`, with
`occurredAt <= recordedAt + 300 seconds` inclusive and +1 microsecond rejected;
delayed/retry/replay events remain valid and retention uses recordedAt. The
complete canonical JCS record plus LF is <=1 MiB inclusive before append;
generic/correlation strings are NFC/no-control <=256 bytes and trace/span are
exact 32/16 hex. Cap, Unicode, rollback, delay, retry, and override fixtures
are required.

Canonical `AuditStoredRecord v1` protection is byte-exact BOPD v1 with custom
AAD owned by P23-003B. `LP(x)` is unsigned U32BE byte length followed by the
exact bytes, and the preimage is
`LP("blackops.audit.record.aad.v1")||LP("AuditStoredRecord")||
LP("1")||LP("1")||LP("1")||LP(keyId)||LP(recordId)||LP(partitionId)||
LP(tenantUnionBytes)||LP(segmentId)||LP(ASCII canonical sequence)||
LP(previousHashUnionBytes)||LP(decoded32(contentHash))||
LP(decoded32(recordHash))||LP(recordedAt)||LP(eventCategory)`. The tenant
union is `0x00` for null or `0x01||LP(type)||LP(id)`; the previous-hash union
is `0x00` or `0x01||decoded32(hash)`. Golden inputs must pass the complete B1
write seam from D148's canonical logical fixtures. The system/genesis fixture
(`audit-key-1`,`01941f29-7c00-7000-8000-000000000001`,`system:global`, null,
`01941f29-7c00-7000-8000-000000000101`, sequence 1, previous null,
content `41b8f7df71f09eb50e78a6f4259b5ee0147efb192ae40c9fe13f1a4526b32441`,
record `a4f7fd2ac8a282deb00bd0221edd2f7c14aa559de97db4b83e5a9609227459b0`,
`2026-08-19T00:00:00.000000Z`, `access`) is exactly 308 AAD bytes with SHA-256
`c917e26a1a4d124db7701ceb76fc141581a54ce248e8df3783fc5e8500a4cade`.
The tenant/non-genesis fixture (`audit-key-2`,
`01941f29-7c00-7000-8000-000000000003`,
`tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1`,
tenant `{type:account,id:tenant-a}`,
`01941f29-7c00-7000-8000-000000000102`, sequence 2,
previous `53f065f0fcd332fb2dc80e6bb2c23ed9d1ee127531550bb4e0a459d09544935e`,
content `034a0ef2dc757517a1045660072835fbacfbfc9d77781be9e1db3b168ecf4186`,
record `90b6a61cdd0bc6eae188fe7f79f21656cea5a97e49d8b5e6f2656a7a1c99a5cd`,
`2026-08-19T00:00:01.000000Z`, `access`) is exactly 421 AAD bytes with SHA-256
`0ab1465ae6e1e908228cafd36cf13fa55a805d4189bbd096d8df885a47452c48`.
The old shorthand and intermediate repeated-hash vectors are not valid B1 positives.
Each field substitution, tenant-union swap, row/tenant swap, key-ID/header
swap, and pre-decrypt authorization failure is a required fixture.

P23-003B also owns the physical/logical boundary of the durable outbox:
`protectedBody` is immutable binary BOPD v1, while the logical DB row and any
JSON projection carry the exact metadata fields and caps in P23-003C. The
metadata JCS plus LF is capped at 64 KiB inclusive and excludes the binary
body; a JSON projection uses base64url for that body. The protected plaintext
and binary body each remain within the inclusive 1 MiB logical/body caps, and
allocation checks plaintext plus `52 + keyIdUtf8Bytes` before allocation.
The raw idempotency key is NFC/no-control UTF-8 of 1..256 bytes and is never a
clear row field. Its exact digest is
`H(ENC("blackops.audit.outbox.idempotency.v1")||ENC(partitionId)||
ENC(NFC(idempotencyKey)))`; `contentHash` is exactly
`H(ENC("blackops.audit.content.v1")||ENC(canonicalLogicalBytes))`.
The D148 `durableSystemIngress` and `durableTenantIngress` authorized durable
write paths resolve Catalog delivery `durable` and yield
222-byte AAD／SHA-256
`1444d7e8430fdb22bed2b29cb10988684aa8b75c061d943ef43cf1d5ec976f7d`
and 303-byte AAD／SHA-256
`6ad3000f8b1b7fa7d76a266c813f9bf83be8c30340c751e761abc396e9acd9e6`.
The `required` canonical-chain fixtures are not durable Envelope positives.
IngressCodec rejects a bound Catalog／policy delivery other than `durable` as
`semantic_invalid` before envelope bytes or key access.
Null/present and one-byte encoded-marker mutation, normalization,
metadata/body cap, header/AAD, tenant, and binary-versus-JSON projection
substitutions are B/C fixtures; no raw-marker decoder API is introduced.

### P23-003C — Required/durable transaction and outbox delivery

P23-003C is delivered as the serial C1→C2→C3→C4 split fixed by D150.
C1 first closes only the six-field lifecycle snapshot and pure transitions;
C2 composes it with immutable protected ingress in the exact persisted row;
C3 owns policy selection／transaction／key composition; C4 owns relay and
recovery. The parent capability remains unreleased and incomplete until all
four children are Accepted.

Implement required append in the mutation transaction and durable Audit Outbox
identity/bindings/protected-envelope append with fenced state-field mutation
and at-least-once relay of the same ID/contentHash. For
authentication/protected-read decisions without a business mutation, commit a
dedicated local append/outbox transaction before response or data disclosure;
allow failure discloses no data and fails closed, while deny/invalid failure
remains denial. Resolve the catalog definition's fixed `required|durable`
delivery mode and required `policy.auditDelivery` before mutation or
response; only explicit required selects required, only explicit durable
selects durable, and missing, omitted, unknown, or mismatched values and runtime override or
downgrade are failures. `best_effort` is only an observed JSONL projection, not a canonical
AuditRecord or Audit Trail claim. Transactional conformance fixtures cover
required, durable, missing, omitted, unknown, projection separation, and
attempted override paths. No
cross-DB or external exactly-once/atomicity claim is made.
Privileged required mutation failure must fail closed.

In v1 the `AuditOutboxRecord` identity, key ID, envelope bytes, nonce,
ciphertext, and tag are immutable in every state; ordinary retry reuses the
same ciphertext and no re-encryption/rotation is allowed. Key retention must
preserve decryptability until a future accepted disposal policy. A future
rotation would require a new versioned CAS/state/crash contract and is not a
current claim.

The exact current `AuditOutboxRecord v1` row is
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,availableAt,leaseFencingToken,leaseExpiresAt,protectedBody,version}`.
`version` starts at 1. The exact state matrix is: `pending` has attempt
`0..maxAttempts-1`, non-null `availableAt`, and null lease fields; `leased`,
`relayed`, and `poisoned` each have attempt `1..maxAttempts`, with leased
carrying a token/expiry and the terminal states carrying null schedule/lease
fields. A pending row is claimable only for attempt `n<maxAttempts`; a claim
increments it exactly to `n+1`. A persisted or decoded pending row at
`attempt=maxAttempts` is invalid and cannot create `maxAttempts+1`.
Pending is claimable only when `attempt < maxAttempts` and its availability has
arrived. A claim is an expected-version CAS that increments version and attempt
exactly once and installs one lease token/expiry. Retry while the lease is
active requires the expected version plus the matching stored unexpired token,
increments version exactly once, preserves attempt, and returns pending with
overflow-safe backoff availability. Expired-lease recovery with
`attempt < maxAttempts` requires the expected version plus the matching stored
expired token, increments version exactly once, preserves attempt, and returns
pending with overflow-safe backoff availability. Equality at
`trustedNow=leaseExpiresAt` is expired. An expired lease at
`attempt=maxAttempts` is a fenced failed attempt and returns poisoned instead;
no claim can produce attempt `maxAttempts+1`. Successful `leased -> relayed`
and terminal `leased -> poisoned` each require the expected current version and
matching unexpired token, add exactly 1 to version, and clear all schedule and
lease fields. Stale owners, expired-owner terminal settlements, wrong tokens,
version gaps, and double-terminal settlements make no mutation. A poisoned ordinal is acknowledged for
FIFO/DLQ and does not block a later ordinal.
Its AAD uses outer `LP(x)=U32BE(byte_length(x))||x` and
`LP(TENANT(tenantBinding))`, where `TENANT(null)=0x00` and
`TENANT({type,id})=0x01||LP(UTF8(NFC(type)))||LP(UTF8(NFC(id)))`. Exact
type/id bounds, partition/equality binding, and state/lease/idempotency/size
caps are closed. P23-003B/C own null/present Node/PHP byte parity,
`0xff`/wrong marker/type/id/header/tenant substitution, and AEAD-failure
fixtures; immutable identity/body/hash fields and retry ciphertext remain.
P23-003C captures trustedNow once per transition; timestamps are UTC with six
fractional digits, `leaseDurationSeconds` is 1..86400 (default 300),
`maxAttempts` is 1..32 (default 5), `backoffBaseSeconds` is 1..3600 (default
1), and `backoffCapSeconds` is 1..86400 (default 3600). Backoff compares the
next multiplication with the cap before multiplying and saturates at the cap;
only invalid bounds reject. The row metadata JCS cap is 64 KiB inclusive and
the protected BOPD body/plaintext cap is 1 MiB inclusive. C owns state-matrix,
stale/expired-fence, poison-FIFO, attempt/cap/backoff, header/AAD/tenant/hash,
binary-body, and JSON-projection fixtures.

For every claim, retry, relay, poison, and recovery transition, P23-003C
captures one trusted transition clock and uses it for both the fence decision
and the resulting timestamps. A lease is active exactly when
`trustedNow < leaseExpiresAt`; it is expired exactly when
`trustedNow >= leaseExpiresAt`, including equality. Expired recovery requires
the stored matching expired token and expected current version in one fenced
CAS. `expiry-1us`, equality, and `expiry+1us` fixtures prove that relay and
recovery cannot both succeed; stale token, stale version, and max-attempt
1/32 fixtures prove no second mutation or attempt 33.

### P23-003D — Framework-owned phase-event migration

Adapt retention/hold, rotation, replay, and outbox retry/dead-letter semantics
as explicit immutable AuditRecord phase events. Also adapt and independently
review the current AuthenticationMiddleware, AuthorizationEvaluator, and
protected Operation-data Status/Journal/Outcome/Diagnostics read boundaries.
Preserve the existing Lifecycle Journal as lifecycle truth and preserve
existing audit-like tables until bounded migration/deletion decisions are
reviewed. Include tenant, purpose, actor, policy, safe reason mapping, and the
auth/access fixtures from P23-003A. Status, Journal, Outcome, and Diagnostics
adapters emit exactly
`access.operation_{status,journal,outcome,diagnostics}.requested|unavailable|accessed`;
each tuple has required delivery and its canonical append is durable before
the response;
the unavailable tuple is the same safe `resource_unavailable` mapping and
makes no existence claim. Its checkpoint/anchor control-event
sub-slice is `P23-003D-control`, consumes only accepted typed outcomes from
P23-003F, and cannot start before F is accepted; ordinary D adapters may depend
on C. This explicit split is acyclic and prevents an unaccepted sibling from
being consumed downstream.

### P23-003E — Application versioned authoring/projector journey

Own the application registration/projector API and provide a reference
application event definition and typed projector for one business action.
Prove that Framework code does not infer business action, resource, approval,
refund, consent, or regulated access from handler success. Verify
required/durable behavior and safe evidence redaction using the P23-003A core
shapes; observed JSONL projection loss is a separate non-canonical surface.

### P23-003F — Checkpoint/anchor signing and reusable verification primitives

Consume only a frozen `closed` segment from P23-003B and persist one
deterministic checkpoint before the fenced-CAS `sealed` transition; never
mutate an AuditRecord. Own the reusable core record, checkpoint, anchor,
freshness verifier, and record/hash recomputation without append: exactly one
complete-segment AuditCheckpoint v1 with `firstSequence: 1` only at partition
genesis, `recordCount: lastSequence-firstSequence+1`, and later segments begin
at the prior segment's final sequence plus one. The `1-42` then `43-50`
checkpoint examples and gap/overlap negatives are required. Partition-global
no-gap/no-fork/no-overlap checkpoint continuity, `checkpointHash`, `signedAt`,
and immutable AuditAnchorReceipt v1 with `anchorReceiptHash`, anchor
object/version chain keyed by exact
`{partitionId,anchorStoreRef,anchorObjectRef}` triple, checkpoint-bytes hash,
and `AuditVerificationContext.freshnessSnapshot` binding.
Use pure RFC 8032 Ed25519 and pinned role-separated trust with JCS preimages;
checkpoint signatures decode to 64 bytes and public keys to 32 bytes. v1
requires trusted verification time, active status, correct role, and
`notBefore <= signedAt <= verifiedAt < notAfter`; expired or revoked
checkpoint/anchor keys
fail regardless of self-declared artifact time. Historical acceptance from
artifact time alone is unsupported; a future separately versioned independent
trusted timestamp/anchor-time proof may add it. Verification recomputes hashes
but never appends or rewrites records. Persist complete checkpoint bytes before
acknowledgement: the same `checkpointId` plus identical complete bytes/hash is
idempotent success; the same ID with different bytes/hash or a second
checkpoint for one segment is an integrity failure. Crash-before-ack/replay
and persist-before-ack fixtures are required.
Signer/anchor/trust/freshness failures leave stored state `closed` (an
operational display may say `closed_unsealed`) or return finite
nonzero verification codes (including `18 freshness_boundary_unavailable`).
The verifier detects update/delete/insert/reorder, truncation, digest,
signature, key validity/revocation/role, checkpoint graph, anchor, catalog,
and freshness failures. Hash/AEAD alone does not pass this slice. P23-003F
owns checkpoint/anchor signing and the reusable record/checkpoint/anchor/
freshness verification primitives and hash recomputation; it never appends,
mutates, implements physical deletion, or owns manifest/pin orchestration.
The P23-003F AnchorIntent is the exact closed state model and field set in the
current A5 block: `prepared|externally_accepted|externally_rejected|
receipt_inputs_persisted|receipt_persisted|acknowledged`, with the persisted
`externalCallPhase=pre_call|post_call_uncertain|null` and
`preCallClaimMode=new_attempt|retry_same_attempt|null` and
`preCallLookupStatus=pending|absent|null` discriminators. Genesis is
`version=1,previousStateHash=null,attempt=0,externalCallPhase=pre_call,
preCallClaimMode=new_attempt,preCallLookupStatus=null,nextAvailableAt=null` and
is immediately claimable only by `external_submission_claim`. Both external
claim operations require status null, install a new lease token, set
`preCallLookupStatus=pending`, and respectively increment or preserve attempt;
only `external_submission_claim` advances attempt from 0..31 to 1..32. For that
claim, attempts 1..31 require non-null `nextAvailableAt` with
`trustedNow>=nextAvailableAt`; `pre_call_retry_claim` requires the same due
non-null scheduling for attempts 1..32. Active claims set
`nextAvailableAt=null`; positive null scheduling, future scheduling, attempt-0
retry, and attempt-0 positive-only operations are rejected.
`external_lookup` is valid only for active `prepared/pre_call/pending`: found
settles acceptance and clears lease/mode/status, absent retains the token/mode
and sets status `absent`, and unavailable clears lease/status and selects
`retry_same_attempt`. `external_call_start` is valid only for active
`prepared/pre_call/absent`, clears mode/status, and writes the durable
post-call-uncertain marker before the external call. Expiry/reclaim from
pending or absent clears lease/status and selects `retry_same_attempt`; a new
pending lookup followed by explicit absent is required before another call.
Post-call, accepted/rejected, and all internal receipt states have null mode
and status. Any stale, mode-substituted, or pending/absent-substituted claim is
rejected. Every state progression or lease clear requires the matching active
token, expected version, and state hash; every valid operation writes exactly
version +1, linked previous hash, updatedAt, and stateHash including both
discriminators. Expiry equality is expired, and only a matching expired token
may be reclaimed.

The deterministic `AnchorExternalReconciliation v1` outcome is exactly
`external_object_found|external_object_absent|lookup_unavailable` and is bound
to the intent ID, conditional-create token, idempotency hash, checkpoint, and
authenticated chain. A `pre_call` lookup found settles acceptance; explicit
absent permits the same claim to call externally, including attempt 32; lookup
unavailable schedules a pre-call retry. A `post_call_uncertain` lookup found
settles acceptance; explicit absent at attempt `<32` changes to pre-call so the
next claim reaches the next attempt, while explicit absent at attempt `32` alone
maps to `externally_rejected/attempt_exhausted`; lookup unavailable preserves
the reconciliation-only phase and makes no external call. An authenticated
external unavailable result at attempt 32 is terminal only after the same
lookup establishes explicit absence. Stale token/version/state-hash and
same-body/different-body idempotency conflicts make no mutation. The public
receipt is exactly active Specification 105 §9.6.1: its
`anchorObjectVersion` equals accepted version, `anchoredAt` equals accepted
time, `signingKeyRef` equals the receipt key ref, and
`segmentId`/`previousAnchorReceiptHash` come from the authenticated
checkpoint/anchor chain. Internal `receiptPreimageHash` and `receiptBytesHash`
use distinct intent domains over that exact unsigned receipt body and do not
override the §9.6.1 checkpoint/receipt formulas. Fixtures cover attempt 0 with
null `nextAvailableAt` and immediate genesis claim, attempts 1, 31, and 32;
retry equality at `trustedNow=nextAvailableAt`, future-not-due,
positive-attempt null scheduling, attempt-0 retry, and attempt-0 positive-only
negatives; claim -> pending lookup -> absent -> call-start ordering;
found/absent/unavailable and call-start duplication; pending/absent
substitution plus stale token/version/state-hash negatives; pre-call lookup
unavailable and expiry/reclaim followed by same-attempt `retry_same_attempt`
claim; attempt-31 post-call absent to attempt-32 pre-call `new_attempt`;
attempt-32 pre-call found/absent/unavailable/rejected and post-call
response-loss; every internal receipt state crash/expiry/reclaim/new claim and
the
found-to-receipt-inputs-to-receipt-persist-to-acknowledgement path; exact +1
CAS, updatedAt/stateHash continuity, token ownership, idempotency/conflict,
predecessor/version/hash substitution, key rotation/revocation, lease-duration
and clock boundaries, and no-N+1 external side effects.
Ordinary signatures require the `audit.access.sign.accessed` event to be
durably persisted before release and append failure denies the signature. The
sole finite exemption is the framework-derived exact target
`system:global/audit-control-v1` and its framework role: its signed
checkpoint/receipt is authoritative and emits no `audit.access.sign.accessed`.
Callers cannot select the target or role. Offline verification performs no
canonical append and makes no access-event claim.

### P23-003G — Query/export/audit-of-audit/retention/legal hold

Own manifest/bundle/start-boundary/file/wire/scope verifier orchestration after
accepted P23-003F and the P23-003D-control slice, by calling the reusable
P23-003F verifier; do not reimplement catalog, record, checkpoint, anchor,
freshness, or hash verification. The service action family is exactly
`audit.access.{query,export,sign,verify}.requested|unavailable|accessed`.
`requested` and `unavailable` are durable; `accessed` is required and durably
persisted before returning data, an export, a signature, or a hosted result;
append failure denies. P23-003A owns the exact catalog tuples and P23-003G
owns query/export/hosted-verification orchestration, including Stage 2
candidate self-hash and the required
`expectedManifest={exportId,manifestHash,purpose}` pin. The access event never
emits another access event (recursion depth exactly one). Offline verification
has no canonical append or access-event claim.

The closed current query `AuditAccessContext v1` is
`{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,target,
authorizationDecision,requestedAt,nonce,requestRef}`. Actor is null or
`{principal:null|{type,id},execution:{type,id}}`; tenant is null or `{type,id}`;
purpose is exactly `query`; decision is
`allow|deny|unavailable`; requestedAt is UTC with six fractional
digits; and nonce is a 1..128-byte NFC/no-control noncredential. `target` is
the exact `AuditAccessTarget v1` above. Export, sign, and verify use separate
versioned exact context/target wires owned by P23-003F/G and never reuse this
query target. The context is generated internally by the Framework's trusted
`AuthorizationEvaluator`; external callers cannot supply an allow result or
requestRef, and requestRef is not authentication proof. Its hash input is exactly
`{purpose,actor,tenant,target,authorizationDecision,requestedAt,nonce}` under
domain `blackops.audit.access.request-ref.v1`. Its requestRef is exactly
`"oid:v1:"||HEX_LOWER(SHA-256(LP("blackops.audit.access.request-ref.v1")||
LP(JCS({purpose,actor,tenant,target,authorizationDecision,requestedAt,nonce}))))`;
the LP bytes are the SHA-256 preimage and there is no self-reference. Legacy
requester field names are not fields. The former target-less `old-103` vector
is not a current wire and is accepted only as a historical old-golden negative
fixture.
The target-bearing first-page global golden is the exact scope with
`queryScopeDigest=d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`,
scope JCS/LP lengths `311/348`, and an `AuditAccessTarget` with
`pageMode="first"` and null `snapshotHeadSequence`, `cursorDigest`, and
`continuationPosition`. Its exact Access input has JCS/LP lengths `374/418`
and requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`.
Node and Docker-PHP must reproduce both target-bearing scope/Access goldens;
the historical target-less `old-103` value is a negative fixture only.
The query target and `requestRefDigest` are never omitted or inferred, and
P23-003G owns target/digest equality, old-golden, external-allow/requestRef-
injection, and requestRef-not-proof negatives.

The closed pre-decision `AuditReadRequestBasis v1` is
`{purpose,actor,execution,tenant,requestedAt,nonce}`. `purpose` is
`status|journal|outcome|diagnostics`; actor is null or `{type,id}`;
execution is required `{type,id}`; tenant is null or `{type,id}`; all IDs are
NFC/no-control bounded. `requestedAt` is UTC with six fractional digits and
`nonce` is a 1..128-byte NFC/no-control noncredential. The final requestRef is
`"oid:v1:" || HEX_LOWER(SHA-256(LP("blackops.audit.read.request-ref.v1") ||
LP(JCS(requestBasis))))`, with no target. LP is the SHA-256 preimage only, not
the final reference. The golden is JCS `150`, preimage `192`, and
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
The exact hash-input vector is
`{actor:null,execution:{id:"exec-1",type:"cli"},nonce:"nonce-1",purpose:"status",requestedAt:"2026-08-20T03:20:00.123456Z",tenant:null}`.
Node and Docker-PHP implementations require null/present, substitution,
wrong-domain/algorithm, raw-preimage, self-reference, and cap+1 fixtures.

Only after the trusted decision is `AuditReadContext v1` constructed as exact
`{schemaVersion:1,kind:audit_read_context,purpose,actor,execution,tenant,
requestedAt,nonce,requestRef,authorizationDecision}`. Basis fields/ref are
byte-identical to the validated basis/derived ref, and caller input supplies
neither decision nor ref. P23-003D/G each own one-request 16-byte CSPRNG nonce
capture/lowercase-hex encoding and the read-boundary orchestration.

The current `aqc:v1` cursor uses keyId 1..128-byte ASCII NFC/no-control,
exactly a 32-byte HMAC secret, and role `audit-query-cursor`. Its payload is
exactly `{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,limit,
order,queryScopeDigest,requestRefDigest,snapshotHeadSequence,lastSequence,
issuedAt,expiresAt,keyId}`. `queryScopeDigest` is the exact digest of the
closed `AuditQueryScope v1`; `requestRefDigest` is the digest of the context
that issued this cursor. P23-003G captures trustedNow once from the injected
Framework clock, never cursor input, with six UTC fractional digits and
requires `issuedAt <= trustedNow < expiresAt`; the active unrevoked key must
satisfy `notBefore <= issuedAt <= trustedNow < expiresAt <= notAfter`.
Issuance rejects `expiresAt > notAfter` and accepts equality; verification-key
retention continues through every cursor expiry plus accepted skew.
Validation has one total order before database read or decrypt: (1) raw request
cap, then closed/canonical shape and nullability; (2) trusted authorization
provenance, decision, requestRef recomputation, freshness, and replay; (3)
partition/tenant and query-scope derivation; (4) raw cursor grammar,
ASCII/no-padding, 2048-byte cap, and raw cursor digest; (5) key
resolution/unavailability, role, HMAC, canonical base64url/JCS, closed payload,
key window, and expiry; (6) scope/head/position/request-selection equality and
authenticated cursor replay; (7) store selection. Within stage 5, bad HMAC
wins over authenticated-payload time/window failures. A single safe code stops
the order and performs zero read, selection, or decrypt. Rotation verifies an old
key through every cursor expiry plus accepted skew and issues with the current
key. Expiry equality and expiry>notAfter negatives are required. Continuation
uses a new AccessContext/nonce, binds the prior raw cursor
digest, and does not compare the prior issuer digest to the new requestRef;
any emitted cursor stores the current requestRefDigest. The first→middle→final
vector is normal; scope/head/position substitution, replay, key-window, and cap
negatives are required. Issuer `requestRefDigest` tampering maps to
`cursor_signature_invalid`; P23-003G owns compound malformed+deny,
wrong-role+bad-HMAC, bad-HMAC+not-yet/expired, and authenticated-cursor-replay
fixtures with one code and zero later reads/decrypts. A new target mismatch maps to the applicable
`scope_mismatch`, `head_mismatch`, or `position_mismatch` code, and
partition/tenant substitution maps to `scope_mismatch`.

The public query union is the exact `AuditQueryResult v1` matrix from Round29:
`ok` has records and a nullable next cursor, `empty` has no records/cursor but
the captured head including zero, and `unavailable` exposes no partition data.
Malformed or oversize request/cursor, authorization, freshness/replay, key,
signature, scope/head/position failures use the closed `AuditQueryError v1`
codes and precedence before lookup/decrypt. Valid store unavailable and
legal-record-too-large use the normal `AuditQueryResult.unavailable` with
`reasonCode=resource_unavailable|record_too_large`; they are not errors. The
error code set is exactly
`malformed_request|request_too_large|authorization_provenance_invalid|
authorization_denied|authorization_unavailable|request_ref_mismatch|
stale_request|future_request|nonce_replay|malformed_cursor|cursor_too_large|
cursor_key_unavailable|cursor_wrong_role|cursor_not_yet_valid|cursor_expired|
cursor_signature_invalid|cursor_digest_mismatch|cursor_replay|scope_mismatch|head_mismatch|
position_mismatch`, with `detail:null` and no existence or crypto oracle.
Stage 2 maps invalid trusted provenance, deny, unavailable, requestRef
recomputation mismatch, stale, future, and nonce reuse respectively to
`authorization_provenance_invalid|authorization_denied|
authorization_unavailable|request_ref_mismatch|stale_request|future_request|
nonce_replay`. Stage 3 maps every partition, tenant, or query-scope predicate
failure to `scope_mismatch`. Stage 4 maps a raw transmitted-token digest
mismatch to `cursor_digest_mismatch`; a normal mismatch and the compound
`nonce_replay+cursor_digest_mismatch` vector use the same canonical Node/PHP
inputs, emit one code by stage precedence, and perform zero store reads or
decryptions. The first failing stage wins and no later stage reads the store;
the compound vector therefore emits only `nonce_replay`. P23-003G owns
provenance-invalid, requestRef-mismatch,
first/continuation partition substitution, malformed+deny, wrong-role+bad-HMAC,
bad-HMAC+not-yet/expired, normal raw-token digest mismatch,
`nonce_replay+cursor_digest_mismatch`, and authenticated-cursor-replay fixtures,
all with one public code and zero later read/decrypt.

Implement bounded default-deny tenant queries, `AuditExportManifest v1` with
`coverageMode: complete_sealed_segments`, `filter: null`, required non-empty
`selectionReason`,
distinct-partition `startBoundaries`, complete partition/segment ranges,
recordCount/chainHeadHash, full authenticated
`startCheckpoint`/`endCheckpoint`/`startReceipt`/`endReceipt` result references
(each signed segment carries one complete `checkpoint` and one complete
`receipt`; start refs come from the first included segment and end refs from
the last; one segment makes both endpoints byte-identical. The closed
checkpoint shape is `{partitionId,checkpointId,checkpointHash,segmentId,
firstSequence,lastSequence}` and receipt shape is
`{partitionId,anchorReceiptId,anchorReceiptHash,anchorStoreRef,anchorObjectRef,
anchorObjectVersion}`), checkpointHash/checkpointBytesHash/anchorReceiptHash
references, one event-catalog reference for each distinct covered
`catalogHash`, exact-once role/path/artifactId/file digest entries, actor/time/
source, JCS `manifestHash`
(excluding only the stored properties `manifestHash` and `signature` from its
preimage), and pure
Ed25519 signature. Stage 1 rejects missing, extra, wrong-type, or malformed
endpoint descriptors globally; after signature, Stage 7 derives refs only from
the authenticated manifest and cross-checks artifact bodies. A missing or
malformed receipt body returns code `15` with non-null manifest refs and never
invents a value. Implement `AuditExportRecord v1` canonical JCS/LF wire for
complete records, checkpoint/receipt/event-catalog bytes, path/reassignment,
raw-byte versus semantic-hash, and start-boundary negatives. The wire binds
the event catalog by semantic `catalogHash` and independently checks its raw
file SHA-256. Mixed catalog hashes across builds or catalog versions are valid
when each covered record resolves and validates against its own catalog and
matching `evidenceVersion`; missing, extra, unreferenced, duplicate, or
unresolvable catalog files fail. Arbitrary filtered subsets are a separate
safe projection contract.
Preflight first bounds unsigned manifest body to <=15 MiB and complete stored
raw JCS plus LF to <=16 MiB before allocation/read/streaming, then enforces
partitions 1..256,
segments 1..4096 total and >=1/partition, one boundary per
partition, catalogs 1..1024, files exactly `3*segments+catalogs` and <=13,312,
records/segment 1..100,000, total records 1..10,000,000, byteLength 1..1 GiB,
unsigned JSON-safe overflow-checked sum <=16 GiB, and exact actual bytes.
Each complete canonical AuditRecord JCS line including LF is <=1 MiB before
writer append/commit or JSON allocation; checkpoint and anchor receipt raw
files are each <=1 MiB, event-catalog raw files including LF are <=2 MiB while
the unsigned catalog body is <=1 MiB, and the records file remains <=1 GiB.
Every cap is inclusive and cap+1 fails under bounded streaming. Relative POSIX
paths are <=512 UTF-8 bytes with <=128-byte components/no controls, no
absolute path, no backslash, no `..`, and no empty component. The resolver is
root-relative and no-follow: symlinks in any component/final entry, magic
links, directories, FIFOs, sockets, devices, and special files are rejected.
Artifact identity is only the authenticated signed-manifest `{role,artifactId}`
mapping plus the independent raw digest; basename or role-prefix naming never
establishes identity. Paths reject `.`, `..`, empty components, absolute forms,
backslashes, symlinks, special files, and races.
Use `openat2` with `RESOLVE_BENEATH|RESOLVE_NO_SYMLINKS|RESOLVE_NO_MAGICLINKS`
or an equivalent component-by-component nofollow walk, `fstat` a regular file,
and use that same descriptor for size, digest, and parse. No path
re-resolution occurs after acquisition; a pre-open/check-to-open swap is
rejected, while a later rename cannot alter bytes read through the acquired
fd and is not promised to be detected. Outside-symlink, symlink-directory,
post-check replacement, FIFO/device, and zero-outside-read fixtures are
required. IDs/refs/purpose/
selection/media/component/build are normalized safe <=256-byte strings. Actor,
time, and source are closed exact objects. `maxEvidenceBytes` is the UTF-8
byte length of `JCS(evidence)` including version. Use one fixed Stage 0-7
verification order (no reverse or alternate order): Stage 0 validates
invocation mode and the standalone strict bounded external
`VerificationContext` plus `expectedContextHash` syntax/recompute/pin (code
`19`; zero manifest/listed-file opens and `partitions:[]`; it must not compare
manifest freshness heads); Stage 1 bounds the raw manifest and validates
strict schema/JCS/count/version and declared lengths only (code `10|17`);
Stage 2 first recomputes and validates the parsed candidate manifest's own
`manifestHash`, then constant-time compares signed `exportId` and
`manifestHash` with pinned `expectedManifest` (mismatch code `16`, zero
listed-file opens, refs null); Stage 3 resolves the trusted export key;
Stage 4 verifies Ed25519 (code `11`, zero listed-file opens); Stage 5, only
after signature success, opens root-confined files and checks actual byte
lengths/sums/raw digests (code `13`); Stage 6 validates the authenticated
catalog and record-chain graph/hash/semantic graph first as code `14
authenticated_graph_invalid`, then mode/context coverage as code `16
mode_coverage_invalid`, then freshness head-set as code `18
freshness_boundary_unavailable`; every global Stage 6 failure keeps
post-signature refs non-null, returns `partitions:[]`, and never reopens a
listed file. Stage 7 validates only local checkpoint/anchor trust/chronology
and local end-head freshness (codes `15|18`); authenticated graph code 14,
mode/coverage code 16, catalog code 10, and the deprecated scope label are never Stage 7
names. Global
stages 0-6 return `partitions:[]`; only Stage 7 emits entries and every
emitted local failure has four non-null endpoint references. Context-hash plus
bad-manifest is code `19`; extra head plus invalid signature is code `11`
with zero opens; extra head plus valid signature is code `18` with empty
partitions; invalid context shape is code `19`.
Invalid signature plus bad actual bytes opens zero files and returns code `11`;
valid signature plus a bad actual length reaches code `13`. Recompute each
`catalogHash` and match stored hash, record event hash, and file `artifactId`,
while raw file SHA-256 is independent.
Zero/cap/cap+1/overflow/oversize, actual-byte, and hash-substitution negatives
are required.
Use separate read/export/sign/checkpoint/anchor/retention roles and a
role-separated offline trust-store validity/revocation/freshness snapshot. The
verifier requires an external `expectedContextHash` and rejects context
substitution before trusting keys. Keep the bounded
`system:global`/`audit-control-v1` stream family with rotated concrete
segments, category retention, and bounded depth-one audit-of-audit. The
manifest has one closed genesis or `selected_range` start boundary per
partition. Both modes use the same exact eight keys:
`partitionId`, `mode`, `anchorStoreRef`, `anchorObjectRef`,
`previousCheckpointHash`, `previousAnchorReceiptHash`,
`previousAnchorObjectVersion`, and `prefixComplete`. Genesis uses non-empty
store/object refs equal to the first receipt, null predecessor hashes/version,
receipt version 1 with null predecessor, and `prefixComplete:true`.
`selected_range` uses non-empty refs equal to the first receipt, 64-hex
predecessor hashes, positive predecessor version, receipt version +1, and
`prefixComplete:false`. The anchor identity is the exact triple
`{partitionId,anchorStoreRef,anchorObjectRef}`; two stores sharing an object
reference are separate chains and cross-store predecessor/freshness bindings
fail. It declares unresolved predecessor values and verifies only the included
chain from its first artifact onward; it does not prove
predecessor existence, continuity from that predecessor, prefix/pre-range/
deletion legitimacy, or legal hold. Partitions/segments follow
predecessor-hash chain order, never opaque lexical IDs; missing predecessor,
gap, fork, overlap, wrong order, anchor version skip, and
fresh-head-with-missing-history fail. `time.exportedAt <= signedAt` and the
full authenticated chronology for every mode is
`checkpoint.sealedAt <= checkpoint.signedAt <= receipt.anchoredAt <=
receipt.signedAt <= verifiedAt`. Current mode additionally requires
`receipt.signedAt <= freshness.observedAt <= verifiedAt < validUntil`; as-of
mode has null freshness and makes no current or suffix claim. Equality and
one-microsecond-after fixtures are explicit. Raw JCS/LF
file digests and semantic hashes are checked independently, and current
expired/revoked export keys fail closed. Offline verification exposes exact
top-level `{schemaVersion,kind,mode,status,code,scope,exportId,manifestHash,verifiedAt,contextHash,partitions}`.
Before a valid invocation mode, mode/scope and exportId/manifestHash/
verifiedAt/contextHash are null; invalid/missing mode is code `19`,
`status:failed`, with `partitions:[]`. A valid mode derives scope immediately;
invalid external
context retains mode/scope but nulls verifiedAt/contextHash. Accepted context
makes those refs non-null, and exportId/manifestHash become non-null only
after Stage 4 signature authentication, remaining non-null on later global
failure. Partitions
contain all manifest partitions exactly once in canonical unsigned-byte lexical
UTF-8 `partitionId` order with no locale; each entry
is exactly `{partitionId,status,code,prefixComplete,suffixComplete,
startCheckpoint,endCheckpoint,startReceipt,endReceipt}`, with receipt refs
including `anchorStoreRef`. Verified entries have code 0, all refs, and
computed flags; partition-local failed entries have nonzero code, false flags,
and all four refs non-null. Global/context failures use `partitions:[]`; a
context-invalid input is code `19 verification_context_invalid` with all result
refs null, while a context-valid unauthenticated manifest retains only
`verifiedAt`/`contextHash`. A different-manifest result substitution fails.
Overall code 0 requires every entry; failure code is the first nonzero sorted
entry, or the manifest code with `partitions:[]` when inventory is unsafe.
Current freshness is required for every partition in current mode, so code 18
is nonzero and fails overall; as-of is the distinct checkpoint snapshot scope
with null freshness and no current/suffix claim. Physical deletion, signed deletion manifests, and sealed
bytes/key destruction are rejected in v1 and deferred to future P23-003H;
there is no compliance claim.
`AuditVerificationContext v1` is exact
`{schemaVersion:1,kind:audit_verification_context,contextId,verifiedAt,
trustStoreSnapshot,freshnessSnapshot,expectedManifest,contextHash}`. The
required closed `expectedManifest` is
`{exportId,manifestHash,purpose}` with bounded IDs, a 64-lowercase-hex hash,
and exact purpose `audit_export_verification`. It is included in the context
hash and pinned by external `expectedContextHash` (64 lowercase hex); it
cannot be read from the bundle/context. Recompute the complete context and
constant-time compare its stored hash, then expectedContextHash, before trust
use. Missing,
mismatched, or whole-context substitution is code `19
verification_context_invalid`, `partitions:[]`, and null result refs.
Trust snapshot is exactly
`{ref,hash,observedAt,validUntil,entries}`; entries are unique and sorted by
unsigned UTF-8 `keyRef`, each exactly
`{keyRef,role,publicKey,publicKeyFingerprint,notBefore,notAfter,revokedAt,status}` with role
`checkpoint|anchor|export`, 32-byte base64url-unpadded public key,
64-lowercase-hex fingerprint `SHA-256(decoded publicKey)`, UTC times,
and status `active|revoked|expired`. Fingerprints are snapshot-wide unique;
same material aliases across key references/roles are rejected and revocation
is material-level. The trust semantic hash excludes only
the stored property named `hash` and uses its domain. Freshness may be
structurally null in either invocation mode. A non-null value is
`{ref,hash,observedAt,validUntil,heads}` with closed bounded heads; member
shape/hash/timestamp grammar is Stage 0, while exact one-head-per-manifest-
partition, duplicate, and canonical order checks are Stage 6. The head array
is sorted by unsigned-byte `partitionId`, and each head is
`{partitionId,anchorStoreRef,anchorObjectRef,
anchorObjectVersion,anchorReceiptHash}`. Its semantic hash also excludes only
its stored `hash` property. Context hash excludes only `contextHash` and uses
its domain. Stage 0 requires `trustStoreSnapshot.observedAt <= verifiedAt <
trustStoreSnapshot.validUntil` for every mode and does not compare freshness
heads with manifest coverage. Stage 6 applies mode/coverage only after catalog/
graph code `14`: a mode mismatch is code `16 mode_coverage_invalid`, while a
current null/invalid freshness boundary is the final code `18
freshness_boundary_unavailable`. Accepted keys are `active` with
`revokedAt:null` and `notBefore <= signedAt <= verifiedAt < notAfter`;
`revoked` requires `revokedAt <= observedAt` and rejects, while `expired`
requires `revokedAt:null` and `notAfter <= verifiedAt` and rejects. Malformed
or internally inconsistent context/trust/freshness shape/hash/time-order is
Stage 0 code `19`; export-key availability/role/status is Stage 3 global code
`12`; authenticated signedAt key-window failure is Stage 4 global code `12`
with non-null export refs; checkpoint/anchor key/signature/chronology is Stage
7 local code `15`. Missing/expired/stale current freshness is Stage 6/7 code
`18`, and non-null as-of freshness is code `16`. Current binds every end
receipt to a head triple/version/hash; stale snapshots cannot claim current/suffix.
Result binds verifiedAt and contextHash exactly. Snapshot caps,
duplicate/sort/hash-substitution and cross-store negatives are required.
Global precedence is the same fixed Stage 0-7 table: Stage 0 validates
invocation mode and standalone external context plus expected-context hash;
invalid mode is code `19` with mode/scope null, otherwise context failure is
code `19` with derived mode/scope, no manifest reads, and zero manifest/
listed-file opens;
Stage 1 raw-manifest schema/JCS/count/version and declared lengths is code
`10|17`; Stage 2 recomputes candidate-manifest self-hash and compares the
candidate `exportId`/`manifestHash` with pinned `expectedManifest` (mismatch
code `16`, refs null, zero listed-file opens); Stage 3 checks the trusted key;
Stage 4 verifies Ed25519 with zero listed-file opens; Stage 5, after signature,
checks root-confined actual lengths/sums/raw digests (code `13`); Stage 6
checks the authenticated catalog and record-chain graph/hash/semantic graph
first (`14 authenticated_graph_invalid`), then mode/context coverage (`16
mode_coverage_invalid`), then freshness head-set (`18
freshness_boundary_unavailable`). Global Stage 6 failures keep post-signature
refs non-null, return `partitions:[]`, and never reopen a listed file. Stage 7
performs per-partition head comparison;
Stage 7 checks partitions. Global stages 0-6 return
`partitions:[]`; only Stage 7 emits entries. The first global failure wins;
otherwise the first nonzero entry in canonical unsigned-byte partition order
wins. Multi-failure fixtures must assert this order. No reverse order is valid.

P23-003G also owns the exact metadata-only `AuditRetentionState v1`:
`{schemaVersion:1,kind:"audit_retention_state",stateId,target,policy,evaluator,
owner,evaluatedAt,policySnapshotRef,policySnapshotHash,policySnapshotVersion,
holdSnapshotRef,holdSnapshotHash,holdSnapshotVersion,evaluationFenceVersion,
result,reasonCode,state,version,previousStateHash,stateHash,updatedAt,
idempotencyKeyHash}`. `target` is
`{partitionId,startSequence,endSequence,eventCategory|null}`; state `policy` is
`null|{id,version,retentionUntil}`, null only for
`retained/policy_unavailable`, and otherwise byte-exactly equals the available
authenticated snapshot's `policy`; `evaluator` and `owner` are `{type,id}`;
`result` and `state` are both `retained|eligible_for_future_deletion` and must
equal, with only `retention_not_elapsed|active_hold|policy_unavailable` for
retained and only `retention_elapsed_no_active_hold` for eligible. Revision/
version 1 uses this same four-branch truth table and is not an unconditional
retained claim. One trusted `evaluatedAt` is captured per evaluation; every
policy/hold `capturedAt` must be `<= evaluatedAt`, and a future snapshot or
evaluation-clock rollback fails without a revision. Policy and
hold snapshots are
authenticated closed wires: policy is
`{kind:"policy"|"policy_unavailable",ref,version,target,policy,capturedAt,
evaluationFenceVersion}` and policy-unavailable has `policy:null`; holds are
`{kind:"active_hold_set",ref,version,target,holds,capturedAt,
evaluationFenceVersion}` with canonical active holds or exactly `holds:[]`.
Their exact hashes are
`H(ENC("blackops.audit.retention.policy-snapshot.v1")||ENC(JCS(policySnapshot)))`
and
`H(ENC("blackops.audit.retention.hold-snapshot.v1")||ENC(JCS(holdSnapshot)))`;
The state policy/hold snapshot ref, version, and hash fields equal the
authenticated snapshot values exactly, and `evaluationFenceVersion` equals
the authenticated fence in both snapshots. Member order is the order shown
and each full target, ref, version, fence,
capturedAt, policy body, or complete hold set is bound. Their refs are
1..256-byte UTF-8 NFC/no-control strings, their versions and
`evaluationFenceVersion` are positive JSON-safe integers, and every revision
advances the fence exactly +1 while snapshot versions and evaluatedAt never
decrease. P23-003G/H own capturedAt-after-evaluatedAt,
evaluation-clock-rollback, equal/newer/stale/fork, and policy substitution
fixtures. The truth table is policy-unavailable ->
`retained/policy_unavailable`; available policy plus overlapping active hold ->
`retained/active_hold`; no hold and `evaluatedAt < retentionUntil` ->
`retained/retention_not_elapsed`; and no hold and
`evaluatedAt >= retentionUntil` ->
`eligible_for_future_deletion/retention_elapsed_no_active_hold`, including the
equality boundary. An eligible state reverses only for a strictly newer policy
extension, strictly newer overlapping hold, or newly authenticated
policy-unavailable snapshot whose policy snapshot version is strictly greater
than the prior version; same-version available-to-unavailable is a fork and is
rejected. Equal snapshots/time alone never reverse it. Same snapshots plus
later time only retain or move retained to eligible. Trusted
`evaluatedAt` never decreases; stale/decreased snapshot versions, evaluation-
fence gaps, forks, clock rollback, and unauthenticated snapshot bodies fail
without a revision. Same key and complete bytes is idempotent. Holds are separate
`AuditHold v1` append-only revisions with exactly one closed object:
`{schemaVersion:1,kind:"audit_hold",holdId,target,reasonCode,state,version,previousStateHash,stateHash,updatedAt,updatedBy,idempotencyKeyHash}`.
Its target is immutable
`{partitionId,startSequence,endSequence,eventCategory|null}`, state is
`active|released`, and only `active -> released` is valid. All IDs/bounds use
the AuditRecord limits, times are UTC six-fraction, `startSequence<=endSequence`,
and version is positive. `previousStateHash` is null iff version 1, otherwise
the prior state hash. For the retention object only, `stateHash` is
`H(ENC("blackops.audit.retention-state.v1")||ENC(JCS(object excluding
stateHash)))`; the raw idempotency key is protected and its clear hash is
`H(ENC("blackops.audit.retention-idempotency.v1")||ENC(partitionId)||
ENC(rawKey))`. For the hold object the exact hashes are
`stateHash=H(ENC("blackops.audit.hold-state.v1")||ENC(JCS(object excluding stateHash)))`
and
`idempotencyKeyHash=H(ENC("blackops.audit.hold-idempotency.v1")||ENC(holdId)||ENC(rawKey))`.
Hold version 1 must be `active` with `previousStateHash=null`; every later
revision is the exact prior version plus one with the byte-exact prior hash.
`released` is terminal and only `active -> released` is valid. Released
genesis, re-activation, version gaps, stale predecessor hashes, and
cross-domain hash substitution are rejected without mutation. P23-003G owns
active-genesis, exact +1, release-terminal, released-genesis, reactivation,
version-gap, predecessor-hash, and hash-domain substitution fixtures.
Retention state and hold state/idempotency domains are not interchangeable;
cross-domain substitution fails closed, and Node/Docker-PHP golden assignments
keep retention and hold hashes separate.
Raw keys are 1..256-byte UTF-8 NFC/no-control values and are never disclosed;
`(holdId,target)` is immutable CAS identity. Same key and bytes is idempotent;
same key and different bytes conflicts before mutation. `updatedBy` is `{type,id}`;
the complete JCS cap is 64 KiB inclusive and cap+1 is rejected. Active
matching active hold wins, overlap and `eventCategory:null` wildcard remain,
eligibility uses the same fenced transaction snapshot, releasing one hold
never releases another, and no state deletes bytes/keys or changes query visibility.
P23-003H future physical deletion is not a P23-006 dependency.

Round 6 also fixes the implementation boundary for the parser and verifier.
The v1 caps first bound unsigned manifest body <=15 MiB and complete stored raw
JCS plus LF <=16 MiB; partitions 1..256; total segments
1..4,096 with at least one per partition; exactly one start boundary per
partition; distinct catalogs 1..1,024; files exactly
`3*segmentCount+catalogCount` and <=13,312; records 1..100,000 per segment
and total 1..10,000,000; each file 1..1 GiB; overflow-safe total bytes <=16
GiB and equal to actual bytes; relative POSIX paths <=512 UTF-8 bytes with
<=128-byte components/no controls and no absolute/backslash/`..`/empty
component; and normalized safe IDs, refs, purpose,
selection, media, component, and build strings <=256 bytes. These bounds are
checked before opening or allocating for a stream. `maxEvidenceBytes` is the
UTF-8 byte length of RFC 8785 JCS of the complete evidence object including
`version`.

`AuditVerificationContext v1` is an exact trusted input with
`{schemaVersion,kind,contextId,verifiedAt,trustStoreSnapshot,
freshnessSnapshot,expectedManifest,contextHash}`.
`expectedManifest` is closed `{exportId,manifestHash,purpose}` with purpose
`audit_export_verification`, bounded IDs, and a lowercase 64-hex hash; it is
included in the context hash and externally pinned. Trust/freshness snapshots carry domain-
separated semantic hashes excluding only their stored `hash` property; trust
entries are sorted exact closed key records and freshness may be null in
either mode; a non-null snapshot contains observed/valid-until times and
bounded anchor heads. The formulas are
`H(ENC("blackops.audit.trust-store-snapshot.v1") ||
ENC(JCS(unsignedTrustStoreSnapshot)))`,
`H(ENC("blackops.audit.freshness-snapshot.v1") ||
ENC(JCS(unsignedFreshnessSnapshot)))`, and
`H(ENC("blackops.audit.verification-context.v1") ||
ENC(JCS(unsignedContext)))`; each corresponding unsigned body excludes only
its own stored hash property. The result
binds `verifiedAt` and `contextHash`. Current mode requires a trusted head
whose exact partition/store/object/version/hash matches each end receipt and
whose `observedAt <= verifiedAt < validUntil`; as-of with non-null freshness
is code `16`; stale snapshots
cannot claim currentness or suffix completeness. Key acceptance uses trusted
verification time and `notBefore <= signedAt <= verifiedAt < notAfter`; a
currently expired, revoked, not-yet-valid, or future-signed checkpoint,
receipt, or export fails closed. Duplicate/sorted/hash-substitution/context
fixtures are required. P23-003F owns reusable record/checkpoint/anchor/
freshness verification and hash recomputation without append; P23-003G owns
manifest/bundle/start-boundary/file/wire/result orchestration by calling F.

### Historical P23-003G-R8 — Cross-slice correction contract (superseded)

Round 8 closed the then-current management/specification boundary without
implementing Production Code. This section is historical-at-that-time and is
superseded by the Round 12 active contract; it is not current normative text:

- Canonical required append and durable `AuditOutboxRecord` ingress each stamp
  `recordedAt` once with per-authority `max(now,lastAssignedRecordedAt)`;
  sequence is allocated only at canonical append, while outbox-only FIFO uses
  monotonic `ingressOrdinal` and makes no cross-mode total-order claim.
  `AuditIngressEnvelope v1` is the closed object with exact `kind` literal
  `audit_ingress_envelope`; its `logicalRecord` is AuditRecord v1 without
  `integrity`, with fixed `recordedAt`/`contentHash` bindings. It requires
  schemaVersion 1, 64-lowercase-hex contentHash, authoritative UTC
  `recordedAt` with exactly six fractional microseconds, positive JSON-safe
  `ingressOrdinal`, non-empty NFC/no-control `idempotencyKey` <=256 UTF-8
  bytes, and recordId/partitionId within their AuditRecord/partition bounds.
  The envelope is durable-only; required delivery passes the same logical
  AuditRecord directly to canonical append and never stores this envelope.
  The outbox BOPD length-prefixed AAD binds schema/version, record ID,
  partition, tenant binding, ordinal, idempotency-key hash, and content hash;
  ciphertext is reused on
  retry; this historical text is superseded by Round 12, which prohibits v1
  re-encryption.
  The actual logical body and partition ID plus maximum segment/sequence/hash fields is used to
  prove a complete JCS/LF record <=1 MiB inclusive at ingress and relay.
- Semantic tuples require every resourceType/outcomeCode/policyReasonCode to
  be non-null/non-empty bounded enum/code values; wildcard is absent. Evidence
  string fields are <=256 UTF-8 bytes with null/256/257 fixtures and typed
  `safe_code|opaque_id` projection; int/bool classification is null. Framework
  definitions are sealed internal-provider data; application owner is forced
  by trusted registry/build identity. Reserved framework event namespaces are
  exactly `security.`, `access.`, `retention.`, `storage.`, `observer.`,
  `outbox.`, `audit.`, `configuration.`, `control.`, `replay.`, and
  `rotation.`. Collision fails closed. Secret detection remains mapper/review
  responsibility and is not an automatic compliance claim.
- Each record is independently Catalog-validated. Same `action.id` is only
  correlation; no cross-record phase transition/continuity/order is checked
  in v1. Future transition semantics require a new versioned contract.
- One verifier table governs all paths: Stage 0 external strict context and
  pinned expectedContextHash (code 19, null refs, zero manifest/listed-file
  opens, empty partitions); Stage 1
  bounded raw manifest/strict schema/JCS/declared lengths (10/17, context refs
  only); Stage 2 manifest hash; Stage 3 trusted export key; Stage 4 Ed25519
  (code 11, zero file opens); Stage 5 root-confined actual lengths/sums/raw
  digests (code 13); Stage 6 catalog or authenticated record-chain graph/hash/
  semantic hash (`14 authenticated_graph_invalid`), then mode/coverage (`16
  mode_coverage_invalid`), then freshness (`18
  freshness_boundary_unavailable`); every global Stage 6 failure keeps
  post-signature refs non-null, never reopens a listed file, and uses
  `partitions:[]`; Stage 7 partition/
  checkpoint/anchor/freshness. Global failures always use `partitions:[]`;
  every emitted partition-local entry has four non-null endpoint references.
  Invalid signature plus bad bytes opens zero files; valid signature plus bad
  actual lengths reaches code 13. Chronology is
  `checkpoint.sealedAt <= checkpoint.signedAt <= receipt.anchoredAt <=
  receipt.signedAt <= freshness.observedAt <= verifiedAt < validUntil`, with
  equality and one-microsecond-after fixtures.
- Unauthenticated/no trusted tenant maps to `system:global` with no raw target
  or tenant. Authenticated same/cross/absent reads use requester partition;
  cross and absent share bounded `resource_unavailable` and an opaque,
  domain-separated request ref with no existence claim. Status/Journal/Outcome/
  diagnostics use one fixture matrix.
- Anchor writes persist deterministic `AnchorIntent` before conditional
  external create, recover exact version/hash after write, persist signed
  receipt before ack. The three non-overlapping fixtures are before external
  accept, after external accept/before local signed-receipt persist, and after
  local receipt persist/before ack; every retry uses the same idempotency key,
  object reference, version, and receipt and creates no N+1. Required effects verify exact connection object and active root
  transaction identity; name-only, nested/manual, and rollback mismatches fail
  closed without durable fallback.
- Producer and verifier enforce unsigned manifest <=15 MiB and complete raw
  JCS/LF <=16 MiB inclusive before allocation, with cap+1 tests.

### Historical P23-003G-R9 — Fresh-review bounded correction (superseded)

Round 9 historically recorded fresh review Changes Requested as General `P1=2/P2=1/P3=0`
and Audit `P1=5/P2=4/P3=1`; the lanes are not combined. This section is
historical-at-that-time and superseded by Round 12. Its former status was
Correction Implemented / Review Requested only; it does not make any 1.3
capability current, Green, Accepted, or Decided.

The command gates use one trusted projection identity and six application
states: `available|absent|malformed|unsupported|stale|build_mismatch`, with
the ordered ENOENT, operational, malformed, unsupported, build, input-hash,
available precedence. List/ls recovers framework rows at exit `0` for every
state and emits deterministic structured status or
`applicationProjectionStatus=<status>\n`; route:list/route:ls and
schedule:list/schedule:ls do
not. `schedule:list`/`schedule:ls` alone are mutually identical with valid,
operational, and complete-artifact exits `0/1/2`. XML/Markdown continuous
canonical bytes and the Spec 105 hashes are the only goldens; aliases and
`--short` are byte-identical.

Audit evidence descriptors are closed and classification-required:
`safe_code` is bounded lowercase grammar with exactly-256-byte positive and
257-byte negative fixtures; `opaque_id` is
`oid:v1:<64 lowercase hex>` with exactly-71-byte positive and 70/72-byte
negative declaration fixtures; nullable null passes, and integer/boolean
classification/cap is null.
Missing/unknown classification, non-null integer/boolean classification, bad
safe-code grammar, and raw opaque-token fixtures fail; valid safe-code and
typed-derived-opaque fixtures pass. Wire grammar cannot prove secret absence;
typed mapper/review owns semantic secret detection. `AuditIngressEnvelope v1`
is durable-only; required writes pass the logical AuditRecord directly.
`AuditOutboxRecord v1` is the exact current closed row
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody,version}`. Its
states are `pending|leased|relayed|poisoned`; version starts at 1, and every
claim, retry, recovery, or terminal mutation requires the expected current
version plus the matching active lease token where a lease exists, then adds
exactly 1. `leased -> relayed|poisoned` clears scheduling and lease fields;
stale, expired, wrong-token, version-gap, and double-terminal mutations do not
write. A max-attempt expired lease becomes poisoned, while poisoned preserves
its acknowledged FIFO position and does not block a later ordinal. The DB
logical row stores immutable binary BOPD `protectedBody` separately from the
canonical metadata JCS projection; a JSON projection uses base64url, and its
64 KiB metadata cap excludes the binary body. Relay role, tenant binding, and
AAD are verified before decrypt; cross-tenant and key-missing cases fail
closed. Its established length-prefixed ENC domain formulas, idempotency
conflict before mutation, per-partition ordinal lock/CAS and head FIFO, lease
fencing, terminal ack, same-ciphertext retry, and canonical append idempotence
remain in force. Complete-record synthetic 1 MiB checks remain ingress/relay
guards.

The verification result is a total function: invalid/missing mode yields
`status:failed`, code 19 with mode/scope and all four identifiers null; valid
mode derives scope;
invalid context retains mode/scope but nulls verifiedAt/contextHash; accepted
context makes them non-null; Stage 4 is the first point at which exportId and
manifestHash become non-null. The only order is Stage 0 mode/context/hash with
zero manifest reads; Stage 1 raw manifest/schema/JCS/count/version/declared
lengths; Stage 2 hash; Stage 3 trusted key; Stage 4 signature with zero file
opens; Stage 5 root-confined actual lengths/sums/digests; Stage 6 authenticated
catalog plus mode/context binding and exact freshness heads; Stage 7 partition
semantics. Global failures keep `partitions:[]`; full authenticated endpoint
refs are required in each emitted entry. Signed manifests carry complete
checkpoint/receipt endpoint refs and Stage 7 cross-checks bodies. AnchorIntent
v1 is closed with fenced states
`prepared|externally_accepted|externally_rejected|receipt_inputs_persisted|
receipt_persisted|acknowledged`. Genesis creation uses
`expectedVersion=0,expectedStateHash=null` to write `prepared,version=1,
attempt=0` with both lease fields null; duplicate creation fails. The first
and every later prepared external claim uses the expected current version and
exact state hash; the first is version `1 -> 2`, attempt `0 -> 1`, and installs
a new token and expiry. External results, internal receipt steps, and
acknowledgements require the matching stored unexpired token; lease reclaim
requires the matching stored expired token, with
`trustedNow=leaseExpiresAt` treated as expired. Every successful operation
writes exactly version+1. Unavailable at attempts 1..31 returns prepared with
bounded retry metadata. Every positive-attempt external-submission claim,
including attempt 31, performs same-object lookup before another side effect:
at attempts 31/32, found recovers acceptance without N+1, explicit absent
alone permits resend below 32 and becomes terminal exhaustion at 32, and lookup
unavailable persists reconciliation-required prepared state (attempt 32 is
reconciliation-only). P23-003F owns creation, first-claim, duplicate-create,
stale version/hash, token-category, and found/absent/unavailable crash/restart
fixtures for attempts 31 and 32. At attempt 32, deterministic same-object reconciliation runs
before any terminal result or new external side effect: a found object
recovers `externally_accepted`, explicit absence alone returns terminal
`externally_rejected/attempt_exhausted`, and lookup unavailable returns the
reconciliation-only prepared representation with no external-submission
claim. A permanent rejection is terminal independently of attempt count;
internal receipt and acknowledgement claims preserve attempt. Terminal states
are unclaimable, and post-acceptance crash/expiry
recovers `externally_accepted|receipt_inputs_persisted|receipt_persisted`
without regression or N+1. The public receipt remains the active §9.6.1 wire;
receipt signedAt is the persisted receiptSignedAt and anchoredAt is no later
than signedAt. The three crash points, sign-before-persist, parallel retry,
and read-after-write fixtures all recover identical bytes/version.

Framework seal evidence is `AuditCheckpoint`; no `segment.sealed` record is
written inside a sealed segment. P23-003D's `AuditControlEventAdapter` emits
`audit.checkpoint.persisted` and `audit.anchor_receipt.persisted` through
`system:global/audit-control-v1`, consuming typed P23-003F outcomes and
writing through P23-003B; P23-003F never directly appends. Ack follows
artifact-ID-keyed idempotent control ingress, otherwise orchestration remains
pending/retry without artifact mutation. P23-003G retention is metadata only
and uses the exact `AuditRetentionState v1` wire above: `policy` is
`null|{id,version,retentionUntil}`, null only for
`retained/policy_unavailable`, and otherwise byte-exactly equals the available
authenticated policy snapshot; policy/hold snapshot refs, versions, hashes,
and `evaluationFenceVersion` equal their authenticated snapshots. Its states
are exactly `retained|eligible_for_future_deletion` with the closed reason
matrix; no hold or `held` state is embedded.
`AuditHold v1` is a separate append-only active/released revision collection;
an active matching hold makes a metadata-eligible record ineligible in one
snapshot, and release re-evaluates versioned policy. No bytes/keys deletion, query/export
suppression, or compliance proof is claimed; P23-003H is future and not a
P23-006 dependency. Unauthenticated/invalid/no-trusted actor maps to
`system:global` with principal null and no raw target/tenant; authenticated
actor with tenant uses requester partition for same/cross/absent; authenticated
actor with null tenant uses `system:global` with principal populated and never
infers target. Cross/absent share `resource_unavailable` without existence
claim. Future audited diagnostics require the exact
`AuditReadContext v1={schemaVersion:1,kind:audit_read_context,purpose,actor,execution,tenant,requestedAt,nonce,requestRef,authorizationDecision}`
owned by P23-003D/G; its hash input is exactly
`{purpose,actor,execution,tenant,requestedAt,nonce}` and its final requestRef
uses the exact `"oid:v1:" || HEX_LOWER(SHA-256(LP("blackops.audit.read.request-ref.v1") ||
LP(JCS(hashInput))))` wrapper. The LP bytes are the preimage, not the final
value; no target is included. Stable diagnostics stay unaudited. Actor/tenant,
requestedAt/nonce, golden parity, nofollow root resolver, TOCTOU, special-file,
and full read-surface fixtures are required.

### P23-003H — Future physical deletion and deletion-manifest boundary

This separately reviewed follow-up may define physical deletion of sealed
bytes/keys, signed deletion manifests, legal-hold authorization, surviving
chain boundaries, and external retention evidence. It is not part of the
AuditRecord v1 wire, has no deletion fields in P23-003G, and is not a current
1.3 capability or P23-006 dependency. Until it is accepted, sealed bytes and
keys remain retained and no deletion/compliance claim is made. When this future
slice is accepted, each delete attempt must capture one trusted current
evaluation time, authenticate policy and complete hold snapshots with
`capturedAt<=evaluatedAt`, persist the corresponding current eligibility
revision, and delete only under the same atomic fence. A new hold or policy,
future snapshot timestamp, or evaluation-clock rollback aborts the operation.
P23-003H owns these fresh-check, delete-fence, and capturedAt/rollback fixtures.

### P23-004A — Existing Worker Mode hardening and Classic migration

Harden the existing Stable default FrankenPHP HTTP Worker Mode into the sole
supported production HTTP profile only after bootstrap/pre-handler failure,
readiness, request state/memory,
`max_requests`, upgrade, same-SHA CI, rolling deploy, and rollback evidence.
Recommend separate HTTP/Deferred services or containers for strongest process,
resource, lifecycle, and deployment isolation. Same-container multiprocess is
optional only with supervision and signal forwarding: PHP crash isolation is
preserved, but cgroup/container lifecycle and rollout/rollback remain shared.
Preserve `Application::http()` as the PSR-15 escape hatch. Remove Classic
profile/guide only at the separately reviewed breaking boundary. This slice
does not claim same-process Deferred.

### P23-004B — Optional Extension Worker falsification spike

Experiment with a minimal Go/Extension Worker handoff only if the Orchestrator
authorizes the optional spike. Test startup, signal, heartbeat, lease,
connection lifecycle, memory, restart, crash/OOM, and HTTP failure isolation.
If native crash/OOM or process-signal coupling can take HTTP down, reject the
architecture. This slice is not a release dependency and does not publish a
FrankenPHP custom binary.

### P23-005 — Documentation information architecture

Run only after implementation slices are accepted. Add a consolidated
Commands chapter, Audit vs Journal/Observability concept, and newcomer value
proposition to the appropriate public/internal documentation in a separate
documentation Task. Keep a clearly separated unreleased Roadmap lane and add
positive/negative Stable guards before any public publication.

### P23-006 — Complete 1.3 release gate

Run the complete local gate, exact-candidate same-SHA remote CI, package/export
gate, documentation build/delivery gate, and authorized production verification
for the accepted candidate. Recheck every public Source, route, Search/LLM
artifact, release authority tuple, generated artifact, and negative Stable
fixture. No changed-pages-only closeout is valid.

## 4. Compatibility and migration matrix

| Existing Stable surface | Phase 23 treatment | Migration gate |
| --- | --- | --- |
| Stable Symfony-backed `list` | P23-002A accepts optional namespace, `--raw`, `--short`, `--format=txt|json|xml|md`, and `--json` alias while replacing descriptor fields with safe BlackOps projection; `ls` is new | Stable compatibility, collision-free, and upgrade-collision review plus P23-006 |
| `operation:list` | preserve no-filter Stable Human behavior; P23-002A implements and independently reviews the future bounded `--strategy=inline|deferred` filter; no current filter claim | P23-002A compatibility review plus P23-006 release gate |
| Symfony descriptor | implementation only; never public machine schema; XML/Markdown are safe projection modes, not descriptor schema | descriptor leakage and conflicting-flag negatives |
| HTTP manifest | preserve version/build validation; add reader projection | same-build route fixture and fail-closed negative tests |
| Operation manifest | preserve version/build validation; read schedule metadata | no due/provider/clock fixture |
| `worker:run` and Deferred loop | preserve separate OS process | runtime isolation and shutdown evidence |
| Classic HTTP profile | existing Worker Mode remains the Stable default; preserve Classic fallback until P23-004A sole-profile gates | independent breaking migration/rollback review |
| `retention:*` | separate P23-001R defect correction | Stable focused review before 1.3 migration |
| retention/replay/rotation/outbox audit-like records | adapt explicitly, do not relabel | source-by-source event and tenant proof |
| JSONL `kind=audit` | future 1.3 derived projection from AuditRecord; current Stable narrow records remain separate | canonical-store and projection negative fixture |
| W3C trace context | optional sampled correlation | trace is not identity/integrity/evidence fixture |

## 5. Independent gates

### Command projection gates

- fresh compiled artifacts are readable without application class autoload;
- missing/stale/mismatched complete-projection artifacts fail closed with
  documented exit codes; list/ls application states use framework recovery
  rows plus status at exit `0`, route/schedule complete failures use exit `2`,
  and operational read failures use exit `1`;
- Human/raw/JSON/XML/Markdown rows and aliases are identical and deterministic;
- Stable-compatible multi-segment namespace/raw/short/format inputs and
  `--json` alias are accepted with Symfony Stable `1.2.0` exact/unique/
  ambiguous/unknown namespace goldens; repeated format is last-wins, short is
  an exact no-op, and only new invalid option/format or `--json` plus explicit
  `--format` fails exit `2`;
- reserved command names and existing application command/alias collisions
  fail closed with actionable owner/name migration diagnostics;
- Stable collision-free and upgrade-collision fixtures pass;
- P23-002A independently reviews the bounded deferred filter, preserves the
  Stable no-filter Human behavior, and keeps the filter unreleased until
  P23-006;
- the supported public `Application::http()` route fixture always includes the
  compiled fixed `GET /operations/{operationId}` descriptor (structurally
  equivalent `/operations/{id}` is a collision), while low-level nullable
  composition is not an effective-route claim;
- no Provider/container/DB/clock/handler/middleware resolution occurs; and
- safe-field and hidden-row negative fixtures pass.

### Audit gates

- event definitions are versioned and typed;
- operation reads use exactly
  `access.operation_{status,journal,outcome,diagnostics}.requested|unavailable|accessed`
  with required catalog delivery and canonical append durable before response;
  service actions use exactly
  `audit.access.{query,export,sign,verify}.requested|unavailable|accessed`,
  with durable requested/unavailable and required accessed before result or
  signature release;
- genesis is the sole sequence-1 segment, later segments begin at the prior
  final sequence plus one, and `recordCount=lastSequence-firstSequence+1`;
  `1-42`/`43-50` positive examples and gap/overlap negatives pass;
- ordinary signatures emit and durably persist `audit.access.sign.accessed`
  before release. The sole finite control exemption is framework-derived exact
  target/role `system:global/audit-control-v1`, whose signed checkpoint/receipt
  is authoritative and emits no sign-accessed event; callers cannot select it;
- `AuditHold v1` is the one closed object with immutable target, continuous
  state hashes, active-to-released CAS, raw-key non-disclosure, same-key
  idempotency/conflict behavior, actor/time bounds, and inclusive 64 KiB JCS
  cap; overlap, wildcard, and same-snapshot eligibility fixtures pass;
- `AuditReadRequestBasis v1` includes requestedAt and nonce; it is exactly
  `{purpose,actor,execution,tenant,requestedAt,nonce}` and the final
  requestRef is `"oid:v1:" || HEX_LOWER(SHA-256(LP("blackops.audit.read.request-ref.v1") ||
  LP(JCS(requestBasis))))`, with no target. The trusted full context is added
  only after the decision and caller input supplies neither decision nor ref.
  Null/present/substitution, wrong
  domain/algorithm, raw-preimage, self-reference, cap+1, and Node/Docker-PHP
  golden parity fixtures pass;
- canonical catalog delivery is exactly `required|durable`; `best_effort` is
  observed JSONL only and has no Audit Trail claim. Conditional definitions
  use separate selectors chosen before mutation/response; explicit policy
  required, explicit durable, and unknown/missing paths have deterministic
  required, durable, and fail-closed outcomes with no runtime override/downgrade;
- `AuditEventCatalog v1` is closed, domain-hashed, bound by required
  `event.catalogHash`, and its bounded flat-primitive evidence schema rejects
  unknown members, `evidenceVersion` mismatch, and same-selector
  redefinition. The finite v1 caps are 256 events, 32 action types, 32
  evidence fields, and 1,048,576 unsigned catalog bytes; string evidence
  descriptors are <=256 UTF-8 bytes and typed `safe_code|opaque_id`, while
  integer/boolean descriptors use `classification:null`; null, 256, and
  257-byte fixtures are required;
- mixed two-catalog coverage is positive when every hash has exactly one
  resolved `event_catalog` file; missing/extra/unreferenced/duplicate files,
  same-selector redefinition, and `evidence.version` mismatch are negative;
- strict verification order is the single Stage 0-7 table: bounded external
  context/expected hash code `19` with zero manifest/listed-file opens; bounded
  raw manifest schema/JCS/count/version/declared lengths code `10|17`;
  manifest hash; trusted export key; Ed25519 signature with zero listed-file
  opens; only then root-confined actual lengths/sums/raw digests code `13`;
  catalog; and partition verification; invalid signature opens zero files and
  valid-signature/bad-catalog reaches catalog;
  `event.catalogHash`/file `artifactId` and raw SHA-256 are checked separately;
  purpose-only, file-digest-only, catalog-body/raw-digest, and stored-hash
  substitution fixtures fail;
- complete canonical AuditRecord JCS/LF lines are capped at 1 MiB before
  append/commit or allocation; checkpoint/anchor files are capped at 1 MiB,
  event-catalog raw+LF files at 2 MiB with unsigned catalog JCS at 1 MiB;
  generic and correlation strings are NFC/no-control <=256 bytes, with
  cap+1/Unicode-byte negatives;
- framework-owned and application-owned events are distinct;
- authentication success/invalid, authorization allow/deny, and privileged
  Status/Journal/Outcome/diagnostic read allow/deny fixtures cover absent IDs
  and never record credentials, tokens, or request bodies;
- event category/action/owner must match the resolved catalog definition and
  its fixed delivery mode; runtime delivery override, control-character,
  over-cap, and mixed-owner mismatches fail closed;
- the current HttpAuthenticator's lack of module/method IDs is preserved;
  invalid/unknown/unauthenticated principal-null, unauthenticated deny,
  anonymous no-event, and provider-explicit typed provenance fixtures are
  present;
- AuditRecord v1 uses one exact top-level schema with explicit enums, tenant
  `null` mapped to `system:global`, and no integrity self-hash;
- every canonical nested object is closed, versioned evidence rejects unknown
  members, and credentials/tokens/raw request data/throwables/ciphertext/key
  material are rejected anywhere in the canonical body;
- canonical body is encrypted with BOPD `AuditRecord`, and tenant auth runs
  before decrypt;
- required/durable transaction semantics and separate observed-projection
  behavior are machine-tested; best_effort is not canonical AuditRecord
  delivery;
- an auth/read decision without a business mutation commits its dedicated local
  Audit append/outbox transaction before response or protected-data disclosure;
  allow append failure discloses no data and fails closed, while deny/invalid
  append failure remains a bounded denial and never becomes allow;
- idempotency, RFC 8785 JCS logical-byte/content-hash/record-hash chain,
  P23-003B-owned append/sequence/idempotency, immutable checkpoint/anchor
  artifacts, role-separated Ed25519 signatures, trust-store validity/
  revocation/freshness, and offline verification detect all specified
  mutations; expired checkpoint, expired anchor receipt, revoked key,
  self-declared backdating, and combined-bundle trust negatives fail closed;
- P23-003F owns reusable catalog/record/checkpoint/anchor/freshness
  verification and hash recomputation without append; P23-003G calls it for
  manifest/bundle/start-boundary/file/wire/scope orchestration without
  reimplementation and binds one event-catalog file exactly once per
  distinct covered `catalogHash`;
- signed export manifests contain scope, partition-chain-ordered ranges,
  distinct-partition genesis or selected-range `startBoundaries`, counts,
  endpoints, roots, checkpoint/anchor/catalog refs, raw file digests,
  actor/time/source, and signature metadata for complete sealed segments, with
  finite safe nonzero failure codes;
- verifier mode/status/scope, `prefixComplete`/`suffixComplete`, exact
  start/end checkpoint/receipt references, current freshness code 18, and
  as-of checkpoint snapshot success are distinct and machine-checked;
- global stages 0-6 of the single Stage 0-7 verifier table emit
  `partitions:[]`: context/expected-hash code `19`, raw-manifest schema/JCS/
  count/version/declared-length code `10|17`, manifest hash, trusted key,
  Ed25519 with zero listed-file opens, actual length/sum/raw-digest code `13`,
  then Stage 6 authenticated catalog and record graph/chain/hash/semantic graph
  code `14 authenticated_graph_invalid`, mode/context/coverage code `16
  mode_coverage_invalid`, and freshness head shape/boundary code `18
  freshness_boundary_unavailable`. Every global Stage 6 failure keeps
  post-signature refs non-null, returns `partitions:[]`, and never reopens a
  listed file. Only Stage 7 emits every canonical-byte-sorted partition entry;
  Stage 7 has only local checkpoint/anchor trust/chronology code `15` and local
  end-head freshness code `18`, never catalog code 10, graph code 14,
  the deprecated scope label, or mode/coverage code 16. `AuditVerificationContext v1`
  binds trusted `verifiedAt` and `contextHash`, current freshness heads, and
  conservative key-time checks; duplicate/sorted/hash-substitution/context and
  simultaneous-failure precedence fixtures are required;
- start-boundary genesis/selected-range/missing-store-object-hash-version and
  deleted-prefix non-claim/opaque-lexical-order negatives, anchor version +1/no-gap/fork negatives,
  semantic-hash versus raw-file-byte negatives, and export-key
  expired/revoked/backdated/time-reversal negatives pass;
- audit-of-audit is bounded to `system:global`/`audit-control-v1` and does not
  recursively self-log;
- query/export/retention/audit-of-audit roles are separated; physical deletion
  and signed deletion-manifest semantics are deferred to P23-003H, while v1
  retains sealed bytes/keys; and
- no compliance, non-repudiation, raw payload, or inferred business action
  claim appears in the public contract.

### Runtime gates

- HTTP Worker startup/pre-handler failure and readiness are safe and bounded;
- multi-request state, connection lifecycle, memory, and max-request restart
  are tested;
- Deferred startup/crash/DB outage/signal/heartbeat/lease/restart remain
  isolated in a separate OS process;
- separate services/containers provide strongest resource/lifecycle/deployment
  isolation; same-container multiprocess evidence must cover shared cgroup,
  lifecycle, supervision, signal forwarding, rollout, and rollback;
- Classic migration preserves `Application::http()` and rollback; and
- optional Extension Worker evidence is kept non-blocking and rejected on
  native crash/OOM HTTP coupling.

### Release/documentation gates

- source, public route, Search/LLM artifacts, release authority, and generated
  artifacts agree at the exact candidate SHA;
- local and same-SHA remote checks pass without reusing evidence from another
  source-changing candidate;
- package/export and documentation delivery checks pass;
- Stable `1.2` positive fixtures remain green and `1.3` negative fixtures are
  absent from current claims; and
- authorized production HTTP/artifact verification is recorded separately from
  local or candidate evidence.

## 6. Acceptance and release boundary

Phase 23 is ready for release consideration only when every accepted slice,
independent gate, compatibility note, and documentation review is green. A
reviewed proposal, a passing local implementation, or an optional spike is
not a release. Until P23-006 is accepted, `1.3` remains Roadmap/Proposal only,
Stable `1.2` public documentation remains current, and no tag, package,
deployment, or publication is authorized by this plan.

## 7. Traceability

- Capability contract: [Specification 105](105-blackops-1-3-capability-contract.md)
- Decision: [D145](../decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md)
- Current runtime: [Application Runtime and Bootstrap](78-application-runtime-and-bootstrap.md)
- Current schedule: [Scheduled Application Operation](98-scheduled-application-operation.md)
- Current tenant/protection: [Tenant Isolation and Protected Operation Data](99-tenant-isolation-and-protected-operation-data.md)
- Current observability: [Structured Logging and OpenTelemetry](100-structured-logging-and-opentelemetry.md)

## 8. Historical Round 10 bounded correction checkpoint

At that historical checkpoint, this section was the normative correction for
the delivery slices. Round 12 supersedes it; it is not current normative text.
The exact-8 local management gates were recorded as PASS. It
is proposal-only and had status `Correction Implemented / Review Requested`;
it does not make any 1.3 capability Stable, Green, Accepted, or Decided.
Round 8 remains historical review `General P1=2/P2=1/P3=0` and `Audit
P1=5/P2=4/P3=1`; Round 9 was a superseded Orchestrator pre-review correction
and has no invented Sol verdict. The historical evidence population was 44
unique existing local evidence targets, 46 cited ranges, and 270 Markdown
link occurrences.

### P23-002A projection root and list recovery

P23-002A owns an installation-owned, versioned `ProjectionBuildSet v1`
locator. “Trusted” here means the configured immutable installation root and
build authority boundary, not a cryptographic trust claim. Its closed shape is
exactly `{schemaVersion:1,applicationBuildId,projections:{command,route,
schedule}}`; every projection value is exactly
`{kind,inputHash,path,sha256}` with the three exact kinds, lowercase 64-hex
hashes, and bounded root-relative POSIX paths. Unknown or duplicate members,
absolute/backslash/parent traversal/empty-component paths, invalid bounds, or
partial tuples fail closed. Each reader obtains the root only from its
caller/configured installation locator, reads the root as data, verifies the
root digest and each projection digest/build/input identity, and never resolves
source code, providers, containers, handlers, or a database.

`build:compile` produces each command, route, and schedule dedicated closed
projection in a bounded temporary file, fsyncs it, atomically renames it, and
atomically publishes the closed root last. A reader therefore observes either
the old root with its old complete set or the new root with its new complete
set; root-before-artifact, artifact swap, mixed old/new, and publication
concurrency fixtures fail closed. The route projection already combines
Operation HTTP rows with the framework-fixed descriptor; route:list consumes
that one artifact and never joins runtime files. Schedule:list likewise
consumes one dedicated schedule artifact. `projectionInputHash` is the
domain-separated JCS hash of `{kind,schemaVersion,applicationBuildId,
sortedClosedSourceRecords}` and the root binds each path plus digest.

List/ls has one canonical `list` row whose aliases include `ls`; it does not
emit a second canonical `ls` row. The recovery enum is exactly
`available|absent|malformed|unsupported|stale|build_mismatch`. ENOENT is
`absent`; permission/I/O/unreadable is operational exit 1; oversize, invalid
bytes/JSON, duplicate/unknown/missing/type/record-shape is `malformed`;
syntactically valid unsupported schema/kind is `unsupported`; a supported
envelope with a different build is `build_mismatch`; a build match with a
different input hash is `stale`; all matches are `available`. Compound errors
use that precedence. List/ls renders framework rows and deterministic status
at exit 0 for all six states; structured output has top-level
`applicationProjectionStatus`, and txt/raw stderr is exactly
`applicationProjectionStatus=<status>\n`. Operational read failure is exit 1.
Route:list/route:ls and schedule:list/schedule:ls require a complete artifact
and use valid exit 0, operational exit 1, and any of the six artifact-failure
states exit 2; schedule:list and schedule:ls are identical only to each other.

The projection bounds are finite and injective: raw artifact UTF-8 JSON is
<=16 MiB inclusive, records are 0..4096, aliases 0..32, names/aliases are
NFC/no-control safe command names excluding tab, comma, CR, LF, and empty
colon segments and <=256 UTF-8 bytes, and descriptions are NFC one-line
no-control values <=256 bytes. Cap+1, separator-injection, unsafe Stable-name
upgrade migration, root missing/malformed/unsupported/mismatch, and
constructor/factory/container/DB sentinel fixtures are required. A
comparison with uncompiled working source is outside this reader's claim; only
descriptor/projection identity mismatches can be `stale`. The
list-specific boot path is a future bounded design across discovered and
explicit registrations, including `ExplicitApplicationCommands`; its
constructor/factory behavior is governed by the corrected Guide fixtures and
must not be described as a current side-effect-free guarantee.

Stable namespace diagnostics and exits remain the Symfony Stable 1.2.0
behavioral goldens for nested exact, unique abbreviation, ambiguous, unknown,
and optional namespaces with any number of colon-delimited segments; no
blanket namespace exit 2 is introduced. Stable `--raw`, `--short`, and every
accepted `--format=txt|json|xml|md` combination remain accepted, repeated
format is last-wins, `--raw` affects txt only, and `--short` is byte-exact
no-op. New `--json` equals explicit JSON, while only its combination with an
explicit `--format` is exit 2. Human/raw/JSON/XML/Markdown all derive one safe
projection. JSON v1 is compact UTF-8 RFC 8785 JCS plus exactly one final LF,
without BOM/CRLF/ANSI; raw is exact UTF-8 rows with one LF per row including
the final row and no ANSI. Without `--all`, hidden rows are omitted; with
`--all`, Human/raw select the same rows and JSON/XML/Markdown additionally
emit lowercase boolean hidden. Canonical XML and Markdown fixtures are
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968` and
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`;
canonical `--all`, alias, and short byte-equality fixtures are mandatory.

### Audit contract corrections

The exact BOPD v1 envelope is the Spec 99 binary layout: magic `BOPD`,
envelope version, algorithm ID, key-ID length and key ID, 24-byte nonce,
ciphertext length and ciphertext, and 16-byte tag. Purpose and AAD are
external context, never stored as a BOPD object. `AuditOutboxRecord` stores a
non-empty binary BOPD envelope whose plaintext is exact RFC 8785 JCS of the
`AuditIngressEnvelope v1` with no LF. Its custom length-prefixed,
domain-separated AAD binds purpose `AuditOutboxRecord`, schema/envelope version
1, algorithm 1, exact key ID, record/partition/tenant union, ordinal,
idempotency-key hash, and content hash; it does not inherit
`CanonicalAssociatedData`. Canonical AuditRecord protection uses the same BOPD
binary format over complete AuditRecord JCS+LF and custom AAD purpose
`AuditRecord` binding version, algorithm, key ID, record/partition/segment/
sequence/previous/content/record hashes, tenant union, and derived partition.
Authorization and expected partition are established before decrypt; the
decrypted complete record and recomputed hashes must equal all clear/AAD
bindings. P23-003B owns this primitive; malformed header/key ID, cross-row,
cross-tenant, tenant-union, and header-substitution fixtures fail before
decrypt.

`AuditIngressEnvelope v1` is durable-only. It is a closed object with exact
kind, positive JSON-safe ordinal, UTC timestamp with six fractional digits,
NFC/no-control non-empty idempotency key <=256 bytes, and bounded recordId and
partitionId. Its recordId and recordedAt equal the logical record, contentHash
is the recomputed domain-separated logical-body hash, logical tenant equals
the closed `tenantBinding` union `null|{type,id}`, and partitionId equals the
canonical derivation (`system:global` for null). AAD encodes tenant presence,
type, and id with length prefixes plus partitionId; tenantBinding is only a
bounded authorization/key-routing index, never raw event evidence. The raw
idempotency key remains protected; its clear hash is the exact
domain-separated normalized-key-plus-partition hash. Required delivery sends
the same logical AuditRecord directly to canonical append and stores no
envelope. Retry leaves all logical bytes, hashes, recordedAt, and identity
unchanged.

An outbox row has immutable identity/bindings/protected envelope and only
fenced-CAS state fields may mutate: `state`, non-negative `attempt` (0 at
ingress, +1 per claim), `availableAt`, `leaseFencingToken`, and
`leaseExpiresAt`. `pending` has an available time and null lease fields;
`leased` has non-null fencing token/expiry; `relayed` and `poisoned` have null
lease fields and are terminal. Transitions are pending->leased,
leased->pending on fenced transient retry/expired recovery, and leased->relayed
or poisoned. Only the minimum unacknowledged ordinal may be claimed; no
ordinal-2 bypasses ordinal 1, poison stops that partition, and stale fencing
cannot ack. Protected allocation is bounded by the logical cap plus exact BOPD
overhead (`52 + keyIdUtf8Bytes`). Fixtures cover initial/retry/expiry/stale
token, two-before-one, parallel relay, append-before-ack crash, poison, AAD
row/tenant swap, and cap+1.

Verification is one total Stage 0-7 order. Invalid/missing mode is code 19,
status failed, mode/scope/exportId/manifestHash/verifiedAt/contextHash null,
and partitions empty. Valid mode derives scope immediately; invalid context
keeps mode/scope but nulls verifiedAt/contextHash; accepted context makes both
non-null. Stage 0 reads no manifest and validates only bounded mode/context/
expected hash. Stage 1 validates raw manifest schema/JCS/count/version and
declared lengths (10/17); Stage 2 hash; Stage 3 trusted export key role/status
at verifiedAt, without trusting unverified signedAt (code 12, refs null);
Stage 4 verifies Ed25519 (code 11, zero file opens and refs null on failure),
then authenticates signedAt time before Stage 5; only successful Stage 4 makes
exportId and manifestHash non-null. Stage 5 opens root-confined files through
the same regular-file fd and validates actual lengths/sums/digests (code 13);
Stage 6 validates catalog, invocation coverage, and head-set shape only (bad
set global code 18, as-of freshness mismatch code 16); Stage 7 validates each
partition and compares its end receipt to the authenticated head (local code
18), with record-chain code 14, checkpoint/anchor body/key/time code 15, and
scope code 16. Stage 3/4 failures open zero listed files; valid signature plus
bad actual bytes is code 13 with non-null export refs. Global failures have
`partitions:[]`; local entries have all four non-null authenticated endpoint
refs. A segment has exactly one complete `checkpoint` and one complete
`receipt` file; result start refs are from the first segment and end refs from
the last, so a one-segment result has byte-identical endpoints. Missing or
malformed receipt body is code 15 but never invents refs. The manifest has no
`as_of` member; external as-of mode verifies the full included graph without a
current/suffix claim. `prefixComplete` equals the authenticated start boundary
(true only genesis); current `suffixComplete` is true only after Stage 7 head
match; as-of and every local failure are false.

Root-relative no-follow resolution rejects symlink components/final entries,
magic links, directories, FIFO/socket/device/special files, and pre-open
check-to-open swaps. After the final regular-file fd is acquired, namespace
replacement cannot alter bytes read; size, digest, and parse use that same fd
and make no promise to detect a later rename. Both pre-open swap rejection and
post-open fd stability fixtures require zero outside-root reads.

`AnchorIntent v1` is a closed object with exact bounded NFC/no-control safe
IDs/refs/tokens, 64-hex hashes, positive versions where non-genesis, and
six-fraction UTC lease/accepted times. Conditional-create, idempotency, and
fencing values are non-credential opaque identifiers; no external auth
credential is stored. It pins partition/checkpoint/hash/bytes hash, store/
object, predecessor version/hash, deterministic receipt ID, only the
`receiptSigningKeyRef` signing-key field, and conditional-create token.
Nullable acceptedObjectVersion, acceptedObjectHash, acceptedAnchoredAt,
receiptSignedAt, receiptSigningKeyRef, receiptBytesHash, and anchorReceiptHash
follow the exact prepared/external/rejected/input-persisted/receipt-persisted/
acknowledged matrix; the accepted object hash equals checkpointBytesHash,
receipt ID/key/time/body/hash are pinned, and receipt hash is the unsigned
semantic hash, not a raw artifact digest. CAS/fencing states are
`prepared|externally_accepted|externally_rejected|receipt_inputs_persisted|
receipt_persisted|acknowledged`; external unavailable at attempt 1..31 returns
prepared, at attempt 32 becomes externally_rejected/attempt_exhausted, and retries
reuse one idempotency/object/version/receipt and make no N+1 across the three
crash points, sign-before-persist, parallel, and read-after-write fixtures.

AuditCheckpoint is authoritative seal evidence; no `segment.sealed` record is
inside a sealed segment. P23-003D's checkpoint/anchor
`AuditControlEventAdapter` consumes accepted typed P23-003F outcomes and
writes through P23-003B to `system:global/audit-control-v1`; this sub-slice
depends on accepted F even though ordinary D adapters may depend on C. Control
artifacts on that stream do not emit further checkpoint/anchor control records;
their signed checkpoint/receipt remains authoritative. Delivery is artifact-ID
idempotent before ack and failures leave orchestration pending/retry. P23-003B
owns storage lookup and authorization-before-decrypt; P23-003G owns public
bounded query/pagination/export. P23-003G retention is only versioned,
non-destructive metadata/eligibility (`retained|eligible_for_future_deletion`)
with active-hold precedence; P23-003H future physical deletion is not a
P23-006 dependency. Remaining action is fresh Sol xHigh general+Audit review,
then Orchestrator acceptance, then P23-001R and downstream slices.

## Historical Round 11 bounded correction checkpoint

Round 11 was a historical `Correction Implemented / Review Requested`
checkpoint. Round 8, Round 9, and Round 10 are historical and their review
counts remain separate; no Green, Accepted, Decided, or invented Sol verdict is
claimed. Stable `1.2` remains current and Phase 23 remains unreleased.

P23-002A owns the exact closed `ProjectionBuildSet v1` root
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
`kind=projection_build_set`, exactly `command|route|schedule` descriptors,
and the domain-separated root self-hash
`SHA-256(ENC("blackops.projection-build-set.v1") ||
ENC(JCS(root excluding buildSetHash)))`. The configured installation root is
an integrity/trust boundary only, not cryptographic authenticity. Stored root
bytes are UTF-8 RFC 8785 JCS plus exactly one final LF, with no BOM/CRLF/other
whitespace; the hash preimage omits that LF, and the <=64 KiB cap applies to
stored bytes. Missing/extra-LF, BOM, CRLF, cap, and cap+1 fixtures are
required. Projection
kinds are exactly `blackops_command_projection`,
`blackops_route_projection`, and `blackops_schedule_projection`; descriptors
and projection headers are closed, input hashes have exact domain-separated
JCS preimages, and records have kind-specific unsigned-byte sort keys.
Command records are `{name,aliases,description,origin,hidden}`; route records
are `{method,path,strategy,operationType,origin}` sorted by
`(path,method,origin,operationType,strategy)` with nulls first; schedule records
are `{name,operationType,cron,timezone,strategy}` sorted by
`(name,operationType)` with nulls first. Root
raw is <=64 KiB, each projection raw is <=16 MiB, rows are 0..4096, and all
command/route/schedule method/path/strategy/operationType/name/cron/timezone/
description fields have explicit NFC/no-control UTF-8 caps, grammar, unknown/
duplicate/sort rejection, and cap+1 fixtures. `build:compile` publishes
immutable content-addressed generation files with temp+fsync+rename+parent
fsync, then publishes the root the same way last; existing referenced files
are never overwritten and GC waits for reader quiescence. Readers consume only
the configured root and do not read source/provider/container/DB; working-
source drift detection is outside the claim and `stale` means only published
identity/input mismatch. The one precedence table is cap, ENOENT, other I/O,
malformed, unsupported, build mismatch, stale, available. List recovery maps
all six states to framework rows/status exit 0; complete route/schedule maps
artifact states to exit 2 and operational reads to exit 1. The route projection
combines Operation HTTP rows with the framework-fixed descriptor; the command
projection does not.

`AuditIngressEnvelope v1` includes `tenantBinding` and is durable-only. P23-003B
also owns the closed `AuditStoredRecord v1` row
`{schemaVersion,kind,recordId,partitionId,tenantBinding,segmentId,sequence,
previousRecordHash,contentHash,recordHash,recordedAt,eventCategory,
protectedBody}`. `recordId` and `segmentId` are 1..256-byte NFC/no-control
strings; `partitionId` is exactly `system:global` or
`tenant:<64 lowercase hex>`; tenantBinding members use the exact AuditRecord
tenant bounds. `eventCategory` is the exact AuditRecord category enum and
equals decrypted `event.category`; `recordedAt` equals decrypted
`time.recordedAt`. Its complete AuditRecord JCS+LF plaintext, clear indexes,
immutability/idempotency identity, and canonical BOPD custom AAD bind the
tenant union and derived partition before decrypt, every clear/hash/time/
category field after it, key IDs 1..128 bytes, 1 MiB logical cap, and
1 MiB+180-byte maximum envelope. `AuditReadContext v1` is closed with nested
actor/execution/tenant types, `status|journal|outcome|diagnostics` purpose,
bounded authorization decision and domain-separated `oid:v1:<64hex>` request
ref that contains no raw target/tenant. Anonymous, invalid, tenant-null,
same/cross, and absent mappings are deterministic and cross/absent share
`resource_unavailable` without an existence claim.

Manifest identity is exact: records artifactId=segmentId, checkpoint
artifactId=checkpoint.checkpointId, receipt artifactId=receipt.anchorReceiptId,
and catalog artifactId=catalogHash. Segments are partition order then chain
order, start boundaries are partition order, and files are unique canonical
path order. Raw file SHA and semantic checkpoint/receipt hashes remain separate.
The verifier is one total function: Stage 0 context shape/hash/time-order code
19; Stage 1 malformed raw/JCS/shape/bounds code 10 before valid unsupported
version/kind code 17; Stage 2 manifest hash; Stage 3 export-key role/status
global 12 with refs null; Stage 4 signature code 11 with refs null/zero opens,
then authenticated signedAt key-window code 12 with refs non-null; Stage 5
same-fd raw bytes code 13; Stage 6 catalog 10, coverage 16, head-set shape 18;
Stage 7 per-partition 14 then 15 then 16 then 18. Overall failure is the
first nonzero canonical partition; global failures have `partitions:[]`, local
failures retain authenticated refs. Missing/expired/stale current freshness
is 18, not `key_unavailable`; as-of freshness is 16. Stage 4 failure plus bad
bytes opens zero files, while valid signature plus bad bytes reaches 13.

`AnchorIntent v1` uses `anchorReceiptId`, not `receiptId`; genesis accepted
version is 1 and later version is predecessor+1, accepted object hash equals
checkpointBytesHash, and the receipt body/ID/hash/time/key exactly matches the
intent. `anchorReceiptHash` is the unsigned receipt semantic domain hash, not
raw signed-file SHA. Only an active claim has both lease fields non-null;
unclaimed/terminal has both null, expired reclaim gets a new fencing token,
state regression is forbidden, and stale owners cannot write. Three crash,
sign-before-persist, parallel, receipt-ID/hash substitution, lease-reclaim,
and no-N+1 fixtures are required. The P23-003D control-adapter sub-slice
depends on accepted P23-003F; ordinary D adapters may remain after C, while G
public query/export follows accepted F plus D-control. P23-003B owns only
tenant-aware storage lookup/auth-before-decrypt; P23-003G owns public bounded
query/pagination/export.

Historical evidence was 44 unique existing local targets, 46 cited ranges, and
270 Markdown link occurrences. Historical Remaining was fresh Sol xHigh
general+Audit review, Orchestrator acceptance, then P23-001R and the ordered
downstream DAG. Historical Suggested Next Action was independent review of the
uncommitted exact-8 Round 11 snapshot before opening any implementation Task
Packet.

## 10. Historical Round 12 bounded correction checkpoint

Current checkpoint updated: 2026-08-19T14:51:24+09:00

Round 12 was the then-current `Correction Implemented / Review Requested`
checkpoint. Round 11 is historical Changes Requested with General
`P1=2/P2=2/P3=0` and Audit `P1=7/P2=3/P3=1`, separately; no verdict lanes are
aggregated and no Green, Accepted, or Decided claim is made. Stable `1.2`
remains current and Phase 23 remains an unreleased proposal.

The active projection contract has one wire only: `ProjectionBuildSet v1`
with descriptor/file `inputHash` values and dedicated command, route, and
schedule projections. The route projection already contains the framework
fixed row and P23-002C consumes its dedicated schedule projection; neither
reader joins runtime HTTP files or reads an Operation Manifest directly. The
configured regular no-follow lock is exactly
`.blackops-projection-build-set.lock`: shared flock covers root open, all
same-fd regular no-follow file opens, validation, row selection, and render;
exclusive flock is acquired before generation preparation and covers every
write/rename, same-fd validation, final root publication, directory fsync, and
bounded GC of unreferenced generation files. Old complete or new complete sets
are the only visible
states. The active static guard rejects standalone `projectionInputHash`,
direct schedule-reader, and HTTP/fixed-descriptor-join terms except inside
explicit historical sections. The optional case-sensitive single
`operation:list --strategy=inline|deferred` filter exits `2` before
Provider/compiler work for missing, duplicate, unknown, or case-variant
values; valid filtering preserves the existing table and unsigned type-ID
sort.

P23-001S was a separate non-1.3 repository-blocking Stable Guide truth
correction before final P23-001 acceptance and P23-002A. Its packet was
created and historically accepted, then reopened for Round 15 corrections;
the current child files are the status authority and now record Round 15
Correction Implemented / Review Requested. The former Guide side-effect-free
`list` sentence is historical and is not positive evidence; the current Guide
is corrected and remains under review.

P23-003B owns partition-global sequence, exact `AuditSegmentState v1`
open/closed/sealed rotation, canonical `AuditStoredRecord` BOPD/AAD and the
232-byte SHA
`5930076af5418572a4e575943172884775428ad10d024de7eb9fc419bce04b1c` plus
344-byte SHA
`f3ef3d96598964f7087ffe654ecde4d2e828caebfa981ecb1daa82859ff7c57f` golden
inputs. P23-003C owns durable outbox state; v1 ciphertext, key ID, nonce,
ciphertext, and tag are immutable and re-encryption/rotation is prohibited.
P23-003F consumes a closed segment, persists a deterministic checkpoint,
seals by fenced CAS, owns checkpoint/anchor signing and reusable verification
primitives, and requires `audit.access.sign.accessed` before signature release.
P23-003A owns unavailable and
`audit.access.<purpose>.requested|unavailable|accessed` catalog tuples and typed
source secret rejection/nonclaim. P23-003D owns durable
Status/Journal/Outcome/Diagnostics adapters. P23-003G owns
`AuditAccessContext`, expectedManifest/manifest-pin Stage orchestration,
query/export/hosted verify and their accessed events before response/result, and
metadata-only `AuditRetentionState` CAS/idempotency. Offline verify has no canonical append
claim; P23-003H physical deletion is future and not a P23-006 dependency.

The DAG is acyclic: F signing depends on accepted A/B/C access-event paths;
the D-control checkpoint/anchor adapter depends on accepted F; G
query/export/hosted verify depends on accepted A/B/F and D-control where
applicable. Ordinary D adapters may proceed after C. P23-001S precedes
P23-002A and final P23-001 acceptance.

Verifier ownership follows one Stage 0-7 total function. Stage 0 validates
mode/context/hash with zero manifest reads; Stage 1 validates raw manifest;
Stage 2 self-hashes the parsed candidate and compares its signed
`exportId`/`manifestHash` with required pinned
`expectedManifest={exportId,manifestHash,purpose}`; Stage 3 resolves trusted
export key; Stage 4 verifies signature and only then makes export refs
non-null; Stage 5 opens root-confined files through the same fd; Stage 6
validates catalog, coverage, and freshness head-set shape; Stage 7 validates
each partition. As-of has null freshness and no current/suffix claim; current
requires the freshness window. Signed identity is only `{role,artifactId}`
plus raw digest, never basename or role-prefix naming. Global failures return
`partitions:[]`; local failures retain authenticated endpoint refs. All four
read surfaces map absent/cross-tenant to durable `.unavailable` with the
same `resource_unavailable` tuple and no existence claim.

Historical Remaining was to freeze and verify that exact-8 Local Gate, create/run the separate
P23-001S Stable Guide correction, obtain fresh Sol xHigh General and Audit
review plus Orchestrator acceptance, then P23-001R and ordered downstream.
Historical Suggested Next Action was to run the fresh exact-8 gates and return
hashes to the Orchestrator, then route P23-001S before requesting fresh review.
That checkpoint did not publish or accept any 1.3 capability.

## 11. Historical Round 13 bounded correction checkpoint

Current checkpoint: 2026-08-19T19:13:27+09:00

Round 13 was the then-current `Correction Implemented / Review Requested`
delivery status. Round 12 is historical Changes Requested with General
`P1=5/P2=1/P3=0` and Audit `P1=6/P2=3/P3=2`; no review lanes are aggregated.
P23-001S is reopened from its historical acceptance and is Review Requested.
Stable `1.2` remains current, Phase 23 `1.3` remains unreleased, and no slice
is Green, Accepted, or Decided.

### 11.1 One atomic projection slice

P23-002A/B/C is one atomic acceptance slice. A owns the closed
`ProjectionBuildSet v1` wire, command projection producer/list implementation,
regular no-follow `.blackops-projection-build-set.lock`, shared reader,
publisher, and GC. B owns route row production and route commands. C owns
schedule row production, schedule commands, and final integration activation.
A/B may prepare bounded component inputs but may not publish or activate a
partial root. C wires all three producers into the A-owned publisher; only a
complete three-file root and the integrated list/route/schedule commands are
accepted. Stable Symfony list remains the active recovery surface before C's
final gate. Synthetic A/B/C and final `build:compile` full-root fixtures are
required.

The root is exactly `{schemaVersion,kind,applicationBuildId,buildSetHash,
projections}`, with `kind=projection_build_set` and exact projection kinds
`blackops_command_projection`, `blackops_route_projection`, and
`blackops_schedule_projection`. Root stored bytes are UTF-8 RFC8785 JCS plus
one final LF, no BOM/CRLF/extra whitespace, <=64 KiB; the self-hash is
`SHA-256(ENC("blackops.projection-build-set.v1")||
ENC(JCS(root excluding buildSetHash)))`. Descriptors bind kind, path, digest,
and descriptor/file `inputHash`; rows are closed, bounded, NFC/no-control,
sorted by canonical unsigned UTF-8 keys, and reject unknown/duplicate fields.
The route projection includes the fixed framework descriptor at build time;
the schedule projection is generated at build time. No direct Operation
Manifest reader, runtime HTTP artifact join, or read-time descriptor join is
current; a static guard permits those terms only in historical quotations.

Publisher acquires exclusive flock on the fixed regular no-follow lock before
creating/writing/renaming any generation temporary/file and holds it through
same-fd validation, root temp fsync+atomic rename, parent-directory fsync, and
GC. Reader shared flock spans root and all three same-fd no-follow regular
opens, validation, selection, and rendering. GC removes only unreferenced
paths under exclusive flock; crash before root publication leaves old root;
only old-complete or new-complete sets are visible. Preparation barrier,
publish+GC/resume, lock replacement/special, crash, and interleaving fixtures
are required. Status precedence is raw cap, ENOENT absent, other I/O
operational exit 1, malformed, unsupported, build mismatch, stale input
identity, available; list/ls maps all six to framework rows exit 0 and complete
route/schedule artifact failure to exit 2.

The shared command/route/schedule strategy wire reflects the canonical class
without a leading slash: Inline -> `inline`, Deferred -> `deferred`, every
other class -> `custom:<canonical-FQCN>`. Complete wire cap is 1024 UTF-8;
1025 fails build with migration guidance. No-filter includes custom; the
optional case-sensitive single `--strategy=inline|deferred` filter matches
only exact built-ins before Provider/compiler work. Built-in/custom namespace,
same-short-name, >64, 1024/1025, route/schedule, no-filter/both-filter and
zero-row fixtures are required.

### 11.2 Audit ownership and dependencies

P23-003B owns one per-partition active `AuditSegmentState v1` with stored
`open|closed|sealed` only; `closed_unsealed` is derived display. B performs
fenced close/rotate atomically, preserving partition-global sequence and
predecessor continuity; F consumes only the frozen closed segment, persists
one deterministic checkpoint, and seals by CAS. Sign/anchor/trust/freshness
failure leaves stored closed and rejects append. B also owns exact canonical
`AuditStoredRecord` BOPD/AAD, tenant/key pre-decrypt routing, and outbox
identity. P23-003C owns outbox state transitions; v1 ciphertext/key ID/nonce/
ciphertext/tag are immutable and re-encryption is prohibited.

F owns checkpoint/anchor signing and reusable verification primitives plus
delivery of required `audit.access.sign.accessed` before signature release;
durability is a separate property. G owns query/export/hosted-verification
orchestration, expectedManifest pin orchestration, bounded
`AuditQueryRequest/Result` and cursor, retention metadata, and their access
events before release. A owns unavailable and `audit.access.<purpose>.
requested|unavailable|accessed` catalog tuples and typed source rejection;
D owns Status/Journal/Outcome/Diagnostics adapters. D's control-event slice
depends on accepted F; ordinary D adapters may proceed after C. Thus F does
not depend on G pin orchestration and G does not reimplement F primitives.

Evidence required/optional presence, requestRef LP/JCS null unions, outbox
attempt/lease CAS, segment rotation, AnchorIntent persisted receipt time/key,
trust-window Stage 0, and immutable overlapping legal holds are shared
fixtures. P23-003A/B/F/G owners are explicit and no dependency consumes an
unaccepted sibling.

All four read surfaces use `.requested|.unavailable|.accessed`; absent/cross
tenant is one durable `resource_unavailable` tuple with no existence claim.
P23-003G's query cursor is bound to authorized partition, filters, snapshot
head, and last sequence with deterministic keyset order and cross-tenant swap
rejection. Hold records are immutable by holdId, active/released, overlap-safe,
category-null wildcard, and any active match wins; physical deletion remains
future P23-003H.

Verifier delivery uses one Stage 0-7 total function: all modes require trusted
`observedAt <= verifiedAt < validUntil` in Stage 0 code 19 before opens; Stage 2
self-hashes the parsed candidate then compares required external
`expectedManifest`; Stage 3 export key status is global 12; Stage 4 signature
failure is 11 with zero opens/null refs and success alone makes export refs
non-null; Stage 5 same-fd actual bytes; Stage 6 catalog/coverage/head-set
shape; Stage 7 canonical partition order/local 14/15/16/18. Current null or
expired freshness is 18, as-of non-null freshness is 16. Manifest identity is
signed `{role,artifactId}` plus raw digest only; no basename/role-prefix
identity. Multi-partition arrays are unsigned UTF-8 partition order, then
checkpoint-chain order. Global failure has `partitions:[]`; local failure
retains authenticated endpoint refs.

### Historical Round 21 child checkpoint

Round 21 was the then-current `Child Accepted / Parent Review Requested`
checkpoint, and its fresh complete Local Gate was PASS. Round 20 and earlier
are historical Changes Requested, including child General `P1=0/P2=1/P3=0`;
fresh Round 21 child General returned `P1=0/P2=0/P3=0`, and the Orchestrator
accepted P23-001S. Historical Remaining: fresh parent General and Audit review
and parent Orchestrator acceptance; then P23-001R and ordered downstream.
Historical Suggested Next Action: preserve the accepted-child freeze and
request independent parent review. No public/released 1.3 capability.

## Historical Round 14 bounded correction checkpoint

Current checkpoint: 2026-08-19T19:56:30+09:00

At that historical checkpoint, Round 14 had `Correction Implemented / Review
Requested` delivery status. Round 13 is historical Changes Requested with General
`P1=1/P2=1/P3=0` and Audit `P1=4/P2=2/P3=0`, separately and without
aggregation. P23-001S was created and historically accepted, then reopened;
its current status is Review Requested. Stable `1.2` remains current and all
Phase 23/`1.3` work remains unreleased. Nothing here is Green, Accepted, or
Decided.

### 12.1 One projection wire and atomic A/B/C delivery

P23-002A/B/C is one atomic acceptance slice. A owns the closed
`ProjectionBuildSet v1` root, exact regular no-follow lock
`.blackops-projection-build-set.lock`, shared reader, publisher, GC, command
projection producer, and list implementation. B owns route rows and route
commands. C owns schedule rows, schedule commands, and final integration
activation. A and B may prepare bounded inputs but cannot publish or activate
a partial root. C wires all three producers into A's publisher, and only a
complete three-file root plus list/route/schedule integration is accepted;
before C, Stable Symfony list remains the active recovery surface.

The root is the only wire and contains exactly
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}`. Its stored
bytes are UTF-8 RFC8785 JCS plus one final LF, no BOM/CRLF/extra whitespace,
and <=64 KiB; projection files are <=16 MiB with 0..4096 rows and finite
NFC/no-control field grammars. Descriptors carry kind, path, digest, and
file `inputHash`. The route producer combines Operation HTTP rows with the
framework-fixed descriptor at build time; the schedule producer compiles
schedule metadata at build time. Readers consume only their dedicated
projection file; they never join runtime HTTP data or read a direct Operation
Manifest.

Publisher acquires exclusive `flock` on the fixed regular no-follow lock
before creating/writing/renaming any generation temporary/file and holds it
through same-fd validation, root temp fsync+atomic rename, parent-directory
fsync, and GC. Reader shared `flock` spans root and all three same-fd regular
opens, validation, selection, and render. GC removes only paths unreferenced
by the current root; a crash before root publication leaves the old complete
set, and only old-complete or new-complete sets are visible. Preparation,
publish/GC/resume, lock replacement/special, root-before-artifact,
mixed-generation, and crash fixtures are required.

The shared strategy wire is the canonical class name without a leading slash:
`inline`, `deferred`, or `custom:<canonical-FQCN>`, capped at 1024 UTF-8
bytes. A custom strategy never matches `--strategy=inline|deferred`; no-filter
includes custom. Duplicate/missing/unknown/case-variant filter values exit 2
before Provider/compiler work, valid filtering preserves the Stable table and
sort, and no-filter preserves Stable output. Current legacy
`projectionInputHash`, direct Operation Manifest reader, and read-time HTTP/
descriptor join terms are rejected by a static guard except in labelled
historical text.

### 12.2 Audit ownership and exact delivery

P23-003B owns partition-global `AuditSegmentState v1` with stored
`open|closed|sealed`, fenced close/rotate, one active open segment, and
predecessor continuity. P23-003F consumes only a frozen closed segment,
persists one deterministic checkpoint, and seals by CAS. P23-003B owns the
canonical `AuditStoredRecord` BOPD transport and exact AAD; P23-003C owns
outbox state, whose v1 key ID/envelope/nonce/ciphertext/tag are immutable and
cannot be re-encrypted or rotated. A canonical AAD uses LP=U32BE length plus
bytes and binds the domain, purpose, version/envelope/algorithm/key ID, all
record/partition/tenant/segment/sequence/hash/time/category fields, with
explicit tenant and previous-hash union markers. The pinned 232-byte and
344-byte golden SHA-256 values are those in Specification 105.

P23-003A owns `.requested|.unavailable|.accessed` catalog tuples and typed
source rejection/nonclaim. P23-003D owns Status/Journal/Outcome/Diagnostics
adapters. P23-003F owns checkpoint/anchor signing and reusable verification
primitives plus required, durably persisted
`audit.access.sign.accessed` before signature release. P23-003G owns
query/export/hosted-verification orchestration, `AuditAccessContext`, exact
`AuditQueryRequest/Result` and HMAC cursor, expectedManifest Stage 2 pin,
and corresponding `accessed` events before release. Offline verification has
no canonical append. Required append failure withholds data, export,
signature, or hosted result; requested/unavailable are durable.

The D checkpoint/anchor control-event sub-slice depends on accepted F; F does
not depend on G, and G query/export/hosted verify depends on accepted A/B/F
and D-control where applicable. Ordinary D adapters may proceed after C.
This DAG has no cycle and no unaccepted sibling is a dependency.

`AuditQueryRequest v1` is the closed UTF-8 JCS object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}`. It uses the exact `AuditAccessContext v1`,
an authorized `system:global|tenant:<64 lowercase hex>` partition, filters
`{eventCategory:null|string,recordIdPrefix:null|string,
fromSequence:null|positive,toSequence:null|positive}`, positive snapshot head,
limit 1..1000, and null cursor only on the first page. The closed
`AuditQueryResult v1` is
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,
snapshotHeadSequence,records,nextCursor,reasonCode}`: `ok` has a non-null
authorized partition/head and non-empty complete canonical AuditRecord v1
records, with next cursor only when more rows remain; `empty` has the same
partition/head with `records:[]` and null next/reason; `unavailable` has null
partition/head/records/next and reason `resource_unavailable`. Complete JCS+LF
is capped at 1 MiB inclusive. P23-003G owns authorization, deterministic
sequence keyset ordering, cursor and record projection; no partial record or
raw target/tenant is exposed.

The cursor wire is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, one literal
dot, no padding, ASCII, and at most 2048 bytes inclusive. Its closed payload
is `{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`. The 32-byte tag
uses an exactly 32-byte role-bound `audit-query-cursor` secret key selected by
the 1..128-byte NFC/no-control ASCII `keyId`, and is HMAC-SHA-256 over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`, with U32BE LP,
strict canonical base64url/JCS/role/bounds/expiry checks, and constant-time
comparison before lookup or decryption. Filter strings and cursor payload
strings are NFC/no-control and capped at 256 UTF-8 bytes unless a smaller
field bound is stated. HMAC cursor authentication is not
record decryption or integrity verification. First/middle/final/empty/
unavailable, malformed/substitution/expiry/key-rotation, and cap+1 fixtures
are required.

`AuditRetentionState v1` is metadata-only with state
`retained|eligible_for_future_deletion` and no embedded hold. P23-003G owns
its fenced CAS/idempotency and the separate append-only `AuditHold v1`
revision collection with `active|released` state, immutable hold ID, wildcard
category, overlapping holds, same-snapshot eligibility, and one-release/no-
other-release fixtures. Physical deletion remains future P23-003H.

### 12.3 Verifier and anchor corrections

`AnchorIntent v1` is the closed object
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,checkpointId,checkpointHash,
checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,
expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,
acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,
receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,
leaseExpiresAt,version,updatedAt}`. IDs/refs/tokens are bounded NFC/no-control
noncredential strings, hashes are lowercase 64-hex, versions positive, and
times UTC with six fractional digits; `receiptSigningKeyRef` is the only
signing-key field. States are
`prepared|externally_accepted|externally_rejected|receipt_inputs_persisted|
receipt_persisted|acknowledged`; prepared has accepted triplet and all receipt
fields null, externally rejected has all accepted/receipt fields null, preserves
positive attempt, and is terminal, externally accepted has accepted triplet non-null and receipt fields null,
receipt inputs persisted has receipt time/key/bytes hash non-null and semantic
hash null, and later states have all four receipt fields non-null. Genesis
accepted version is 1, otherwise predecessor+1; accepted object hash equals
checkpoint bytes hash. `receiptBytesHash` is the domain hash of exact unsigned
receipt JCS+LF bytes, `anchorReceiptHash` is its semantic hash and not signed
file SHA; receipt time, ID, key, bytes and hashes are retry-identical. Lease
fields are both null at rest or both non-null under one fenced claim, with CAS
reclaim and no regression/N+1 fixtures.

The verifier is one Stage 0-7 total function using `trustStoreSnapshot`.
Stage 0 validates mode/context/hash and
`observedAt<=verifiedAt<validUntil` before any opens (code 19). Stage 1
malformed raw/JCS/bounds is code 10 before unsupported code 17. Stage 2
self-hashes the parsed candidate and compares required external
`expectedManifest` (code 16, zero file opens). Stage 3 export-key role/status
is global 12. Stage 4 signature failure is 11 with zero opens and null refs;
success alone makes export refs non-null. Stage 5 opens each listed file once,
retains bytes, and checks actual lengths/sums/digests; Stage 6 never reopens.
Stage 6 global precedence is catalog graph mismatch code 14, coverage/mode
code 16, freshness head/current code 18, always `partitions:[]`; Stage 7
local precedence is chain 14, checkpoint/anchor/key/time 15, scope 16, head
18. Current freshness uses the trusted head; as-of has null freshness and no
current/suffix claim. Signed `{role,artifactId}` plus raw digest is identity;
basename and role-prefix identity are forbidden. Paths reject empty, `.`,
`..`, backslash, absolute, symlink, special files, and pre-open races.

## Historical Round 15 bounded correction checkpoint

Historical status at that checkpoint was `Correction Implemented / Review
Requested`; Round 15 was then-current and is now superseded by Round 16. It
was not Green, Accepted, or Decided. Stable 1.2 is current and all
P23-002/P23-003 capability work remains an unreleased 1.3 proposal.

P23-002A/B/C are one atomic acceptance slice. A owns the single
`ProjectionBuildSet v1` wire, fixed regular no-follow lock, publisher/reader/
GC protocol, and command projection/list implementation. B owns the route row
producer and route command; C owns the schedule producer/command and final
integration activation. A and B may prepare bounded inputs but cannot publish
or activate a partial root. C wires all three producers into A's publisher;
only the complete three-file root is accepted. Route and schedule readers
consume only their dedicated compiled projection, whose build-time producer
already incorporates fixed descriptors/Operation metadata. Legacy standalone
projection envelopes, direct Operation Manifest readers, and read-time HTTP
descriptor joins are rejected by the current static guard except in labelled
historical text.

The fixed lock is `.blackops-projection-build-set.lock`. Readers hold shared
flock from root open through all same-fd no-follow opens, validation, selection,
and render. Publishers acquire exclusive flock before any generation write or
rename, hold it through root fsync/atomic publish, directory fsync, and bounded
GC, and expose only old-complete or new-complete sets. The operation strategy
filter is an optional, case-sensitive, single `--strategy=inline|deferred`
value; invalid/duplicate/missing values exit 2 before Provider/compiler work,
while no-filter preserves Stable output.

P23-001S was created, historically accepted, reopened for bounded corrections,
and accepted after fresh Round 21 child General `P1=0/P2=0/P3=0`. Its child
packet is current `Round 21 Accepted`; the former side-effect-free Guide
sentence is historical and the corrected Guide passed fresh child review. It
remains the non-1.3 prerequisite before final P23-001 acceptance and P23-002A.

P23-003B owns partition-global sequence, segment fencing, canonical storage
AAD, and immutable v1 outbox ciphertext. P23-003F owns checkpoint/anchor
signing and reusable verification primitives plus required
`audit.access.sign.accessed` before signature release. P23-003G owns
query/export/hosted verification, expectedManifest Stage orchestration,
exact `AuditQueryRequest/Result` and HMAC cursor, and accessed events before
release; HMAC cursor authentication is not record decryption. Offline verify
has no canonical append. The exact requestRef vector and AnchorIntent schema/
state matrix are those pinned in Specification 105. All read surfaces use
`.requested|.unavailable|.accessed` and the shared `resource_unavailable`
tuple.

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

Round 26 was the then-current parent checkpoint with status `Round 26 Correction
Implemented / Review Requested`; Round 25 and earlier are historical evidence
with separate review lanes. P23-001S retains the Historical accepted Round 21
child prerequisite and must not be recreated. Parent acceptance is not yet
complete. Remaining:
Orchestrator must rerun the complete Local Gate after this source-changing
management correction, then fresh separate Sol xHigh General/Audit review and
parent acceptance; P23-001R and ordered Phase 23 work follow. No 1.3 capability
is public or released.

## Historical Round 26 Suggested Next Action

Run the complete Local Gate against the exact Round 26 candidate, then route
fresh separate Sol xHigh General/Audit review and parent acceptance. After
acceptance, route P23-001R and ordered Phase 23 work; no external action is
authorized before review.

## Historical Round 16 bounded correction checkpoint

Updated At: 2026-08-19T21:18:38+09:00

Round 16 was the then-current `Local Gate Green / Independent Review Requested`
checkpoint, not Accepted or Decided, and is now historical. Correctly quoted Node and Docker
PHP implementations independently reproduce the requestRef formula's literal
JCS at 121 bytes, the LP preimage at 165 bytes, and SHA-256
`692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.
The Round15 149-byte/`b063819f...` result is historical invalid diagnostic
evidence from shell quote loss. Dedicated ProjectionBuildSet readers,
Query/cursor, AnchorIntent, F/G ownership, and the Stable 1.2/unreleased 1.3
boundary are unchanged.

The fresh complete exact-15 Local Gate is Green. Remaining: fresh child General
review, parent General/Audit review, and Orchestrator acceptance. Suggested
Next Action: freeze the exact-15 hashes and route the reviews.

## Historical Round 17 delivery checkpoint

Updated At: 2026-08-19T21:59:03+09:00

Round 17 was the then-current `Correction Implemented / Review Requested`
checkpoint, not Green, Accepted, or Decided, and is now historical. The child correction preserved
the Stable `1.2` list behavior while documenting class-string versus prebuilt
instance construction and guarding the paired HTML visibility boundary. The
Phase 23 delivery plan remains an unreleased proposal; the fresh Round 17 Local
Gate is PASS, pending fresh child/parent review and Orchestrator acceptance.
The Guide/Website marker wording is synchronized across list formats.

## Historical Round 18 delivery checkpoint

Updated At: 2026-08-19T22:20:07+09:00

Round 18 was the then-current `Correction Implemented / Review Requested`
delivery checkpoint and is now historical Changes Requested (`P1=0/P2=1/P3=0`). The Website inline HTML
reader's native visibility contract is synchronized with paired primary/alias
fixtures: closed `details` expose only the first direct `summary` until
`open`, plain closed `dialog` is hidden, and explicit non-`none` inline display
can expose a closed dialog while `display:none` remains hidden. Round 17 is
historical Changes Requested. The fresh complete Local Gate is PASS.

Historical Remaining: fresh child P23-001S General re-review, fresh parent
General/Audit review, and Orchestrator acceptance; then P23-001R and ordered
downstream. Historical Suggested Next Action was to freeze the exact-15 Round
18 Local Gate and route child review, followed by parent review and acceptance.
No 1.3 capability is public or released.

# D145: BlackOps 1.3 Command, Runtime, and Audit Boundary

Status: Proposal — unreleased BlackOps 1.3; workflow/gate status authority is the exact5 management slice (`develop/STATE.md`, `develop/TODO.md`, the parent Task/Report, and `develop/spec/README.md`)

Updated At: 2026-08-29T01:25:47+09:00

Decision date: 2026-08-19

## Scope and release boundary

This is the discovery decision for the unreleased BlackOps `1.3` proposal. It
does not change Stable `1.2`, its public documentation, release authority,
source inventory, search artifact, LLM artifact, package, or runtime. The
contracts below are a proposal for implementation and independent review. A
future implementation may narrow a contract only by recording a new decision
and updating Specification 105/106 before implementation.

Round 7 Changes Requested is historical: General `P1=4/P2=1/P3=1` and Audit
`P1=6/P2=6/P3=0`; those lanes are not combined. Round 8 fresh review is
historical Changes Requested: General `P1=2/P2=1/P3=0` and Audit
`P1=5/P2=4/P3=1`; those lanes are not combined. Round 9 and Round 10 are
historical, superseded Orchestrator correction wording; Round 11 is historical
Changes Requested: General `P1=2/P2=2/P3=0` and Audit `P1=7/P2=3/P3=1`,
separately. Round 12 and Round 13 are historical Changes Requested
checkpoints. Round 13 was General `P1=1/P2=1/P3=0` and Audit
`P1=4/P2=2/P3=0`, in separate lanes. Round 14 and Round 15 are historical.
Round 16 through Round 20 are historical. Round 18 was the then-current
Correction Implemented / Review Requested checkpoint; its child General review
returned `P1=0/P2=1/P3=0`. Round 19 and Round 20 are historical Changes
Requested. P23-001S retains the Historical `Round 21 Accepted` child
prerequisite after fresh child General review `P1=0/P2=0/P3=0`. Round 22 is
historical Changes Requested: parent
General `P1=3/P2=1/P3=0` and Audit `P1=4/P2=0/P3=0` remain separate review
lanes. Round 23 is historical Changes Requested: parent General
`P1=2/P2=2/P3=0` and Audit `P1=3/P2=1/P3=0` remain separate review lanes.
Round 24 is historical Changes Requested with General `P1=1/P2=1/P3=0` and
Audit `P1=5/P2=1/P3=0`, separately. Round 25 is historical evidence with its
Local Gate/build records preserved below. Round 26 is historical evidence.
This decision is a current technical proposal only. Workflow and gate status
authority is the exact5 management slice: `develop/STATE.md`,
`develop/TODO.md`, the parent Task, the parent Report, and
`develop/spec/README.md`; Search and Audit workflow lanes are not normative
within this decision.
Independent Round 6 evidence remains separate: full review `P1=3/P2=4/P3=1`
and Audit review `P1=2/P2=6/P3=1`; no aggregate is claimed. This decision is
technical proposal text only; it asserts no runtime implementation, release,
or Stable `1.2` change.

For every current `ProjectionBuildSet v1` clause in this decision, D146 is the
more specific authority. Its component injected locator, C-owned Production
locator, single-basename namespace, current field table, verified-handle policy, lock initialization ownership,
scan limit `256`, and deletion limit `32` supersede incompatible path/cap and
literal `nofollow`／`no-follow` shorthand in non-historical text. Explicitly
historical checkpoints remain evidence only.

For every current P23-003A Catalog／AuditRecord schema／safe-projection clause,
D147 is the more specific authority. It fixes the serial A1→A2 delivery,
Catalog-only JCS/hash owner, string-domain＋bytes-body hash encoding, exact
30-definition Framework security/access catalog and golden, trusted owner and
reserved-prefix boundary, evidence presence/nullability matrix, keyed
opaque-ID derivation and downstream key owner, pre-decision
`AuditReadRequestBasis` plus post-decision `AuditReadContext`, requested-then-
terminal read cardinality, exact validation codes/JCS escape and raw-size
oracles, the pre-semantic raw-cap guard, exact 1..256-byte typed-reference
members, empty A1 PublicApi marker inventory, producer draft／trusted logical／
complete record construction stages, and closed observed projection.
Incompatible non-historical shorthand below is superseded; historical review
evidence remains historical.

## P23-003A current contract correction

Current status: `Accepted — P23-003A1／A2 and parent P23-003A; P23-003B next`.

P23-002E is Accepted. P23-003A is split into serial P23-003A1 Catalog and
P23-003A2 Record／Projection packets. Neither child is a runtime activation.
Both must be Accepted before parent P23-003A and before P23-003B starts.

D147 is the byte and authority contract. In particular, A1 owns RFC 8785 JCS
and the domain hash only for `AuditEventCatalog`; B still owns AuditRecord
logical/content/record/chain hashes. Stable `1.2.0` and its public
Documentation remain unchanged; every P23-003 capability remains an
unreleased `1.3` proposal.

The initial A1 preflight and first corrected exact-nine re-preflight are
Historical Changes Requested. The latter returned general
`P1=1/P2=1/P3=0` and focused `P1=1/P2=0/P3=0`, separately. A1 now checks the
1,048,658-byte raw Catalog guard before its exact ten semantic stages, maps
every over-cap compound input to `jcs_bound_invalid` without decoder work,
and fixes both `{type,id}` members to valid UTF-8／NFC／no-control 1..256-byte
strings. A1 also exposes the
six-field request basis separately from the trusted ten-field result context,
adds no PublicApi marker, and has only pure keyed derivation. P23-003C owns the
future dedicated `audit_opaque_id_v1` provider/injection boundary before D/E
consume it. Package export remains a committed-candidate parent/release gate,
not an unreachable uncommitted-worker gate.

The second corrected exact-nine focused lane was Green `P1=0/P2=0/P3=0`;
the separate general lane returned `P1=0/P2=1/P3=0` only because the Task did
not directly fixture byte-preserving mixed-case/space/slash/colon values and
the tenant opaque-context closed path. The Task now names the shared value,
each read-context consumer canary, and the exact tenant JCS/HMAC oracle. A
fresh general re-review was required before Production implementation.
The fresh general closure review matched the third corrected exact-nine and
returned Green `P1=0/P2=0/P3=0`. Luna Max completed the exact A1 implementation
and focused／complete Final Gates. Fresh Sol Max implementation review is Green
`P1=0/P2=0/P3=0`; the Orchestrator accepts P23-003A1 and unblocks P23-003A2.
The A2 Task fixes a single `projection_invalid` failure, exact
correlation/evidence loss rules, an Internal logical-record interface without
a Production concrete constructor, injected Accepted-A1 opaque derivation,
and an exact-zero A2 PublicApi inventory. Luna Max implementation, the focused
Iteration Gate, exact29 Product freeze, and complete Final Gate are Green.
The first bounded A2 correction and corrected complete Final Gate were Green.
Fresh Sol Max implementation re-review returned Changes Requested
`P1=3/P2=1/P3=0`; Round 2 correction is limited to Correlation construction,
bounded Logical capture, direct mutation fixtures, and D147 current handoff.
Round 2 correction and its exact29 complete Final Gate are Green; the Product
aggregate is `c2ef33a3d3b47ca9a5de189d324308a79da0a356839d36aa944675a7dba2d8eb`.
Fresh Sol Max implementation re-review returned Changes Requested
`P1=0/P2=2/P3=0`; only an explicit-null correlation projection direct fixture
and current inventory sync remained. Round 3 closes both roots. Its exact29
Product aggregate is
`65443eb6ec6c738c61100001d8f3f5d2e38a88e63f585052bf6f4c4b9bd29056`;
focused PHPUnit `46/23599` and the complete Final Gate are Green. Fresh Sol Max
implementation re-review returned Changes Requested `P1=1/P2=0/P3=0` for
input-count-proportional key/evidence allocation before bounded shape rejection.
Round 4 is limited to the Validator and its two direct tests; Round 3 Product
Gate evidence is not reused after that change.
Round 4 correction and focused gate are Green on exact29 aggregate
`c019ab09c31a99301bfe9fd22004890cf52a21b20cc7b410df74f08b0ef97ae9`.
Accepted A1 exact48 is unchanged. The complete Final Gate is Green with full
PHPUnit `2700/37333`, Mago error0, Deptrac `970/970`, release／Website／Node／
management guards, staged0, and ending hashes PASS. Fresh review／Acceptance
are Green／Accepted: fresh Sol Max implementation re-review returned
`P1=0/P2=0/P3=0`, and the Orchestrator accepts A2 and parent P23-003A.
Runtime activation remains blocked. P23-003B is now the next ordered Task;
P23-003C..G remain behind their own prerequisites. Accepted A1 exact48 is not
changed.

## P23-002E current About and Outbox decision

Current status: `Accepted implementation — unreleased BlackOps 1.3`.

P23-002D is Accepted. P23-002E fixes the proposed installed-metadata and
installation-wide Outbox aggregate boundary in Section 6.2. Stable `1.2.0`
remains current and BlackOps `1.3` remains unreleased. Fresh Sol Max
re-preflight is Green `P1=0/P2=0/P3=0`; disjoint About／Outbox component work is
authorized. Public Documentation, Artifact, commit, stage, push, CI, deploy,
publication, tag, and release remain unauthorized.

`about` has no inferred capability registry. It reports only Composer-installed
Framework version, the current PHP process, and the contracted ProjectionBuildSet
status. `outbox:status` reports seven counts and three ages from one PostgreSQL
aggregate row. It does not expose tenant, identity, payload, context, or row data.
The three minimum values use exact UTC `YYYY-MM-DDTHH:MM:SS.ffffffZ` text and
never inherit PostgreSQL session timezone or DateStyle.

## P23-002D accepted diagnostics and queue prerequisite

Current status: `Accepted — Diagnostics Check and Aggregate Queue Status`.

P23-002C is Accepted. P23-002D fixes the proposed readiness authority,
host-operator Queue scope, exact bounded values/wires/failures, and disjoint
implementation ownership in Section 6.1. Fresh Sol Max preflight is Green
`P1=0/P2=0/P3=0`; Production implementation completed within the exact Task
Packet. Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased.

Initial Sol Max preflight confirmed two architecture P1 roots before the
superseded review was stopped. Queue source ports/values/failures now belong to
Core so Transport does not depend on Internal. The explicit Builder readiness
authority adds only Application-to-Observability and
InternalApplication-to-Observability dependency edges. Fresh review matched
exact8 aggregate `f29f3deb2896cdb067b58927139f7d3c512bdd8ce20674035b5393ce8f674a34`.

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

## P23-002C accepted command and activation prerequisite

Accepted status: `Accepted — Schedule Projection and Atomic Activation`.
Reviewer: Fresh Sol Max closure review Green `P1=0/P2=0/P3=0`; Orchestrator Accepted.

P23-002A1/A2/B are Accepted and non-activated.
P23-002C alone may add the schedule projection and connect command, route, and
schedule readers to Production.
Fresh bounded Sol xHigh preflight matched exact9 aggregate
`c933fd9f23f85b00f447f72f69a7769bb7ec747b737cf711fb2cdf934beeb347`
and returned Green `P1=0/P2=0/P3=0`.
P23-002C Production implementation is authorized within its exact Task Packet.

Luna Max implementation is complete within the corrected exact 41-file Task
scope. The first Orchestrator Final Gate stopped at canonical `mago lint` with
exactly two new cyclomatic-complexity errors, making pre-correction aggregate
`0cce05bb35c46fbe15217e6cf6bcc206834ae429dca5c0c3d7ee937ecbda0ec5`
Historical. A behavior-neutral class-DocBlock correction passes focused
format, canonical lint, and impacted PHPUnit (`15 tests / 118 assertions`).
The Orchestrator refroze corrected aggregate
`be0b7c8421e1f6906ce5576b59230ed33dcf505a00618afc09622c52a652d766`,
restarted the complete Final Gate, and reproduced the same ending aggregate.
Format, Composer strict, canonical lint/baseline, analyze errors `0`, full
PHPUnit `2468/10930`, Deptrac violations `0`, version baseline, Website
release-source, management-ID, diff, and staged0 are Green. That `be0b7c...`
freeze and its Final Gate are Historical/superseded after fresh Sol returned
Changes Requested `P1=0/P2=2/P3=1` and the bounded fixture correction.
The current Orchestrator-frozen exact32 aggregate is
`15e0f074c5302af68753c06d8b9effc0747aff0e0a55902f081bc91834117a33`; its
complete Final Gate is Green with PHPUnit `2472/10972`, canonical lint
baseline filtered 186 with no issues, analyze errors `0`, Deptrac violations
`0`, version baseline, release-source, management-ID, diff-check, staged0,
and ending exact32 unchanged.
Fresh Sol Max closure review independently matched the exact32 and exact9
freezes, returned Green `P1=0/P2=0/P3=0`, and supports Acceptance. The
Orchestrator accepts P23-002C; BlackOps `1.3` remains unreleased.

C explicitly chooses `dirname(app.build.container)` as the internal
ProjectionBuildSet root.
`ApplicationBuildConfiguration::projectionBuildSetRoot` is the one calculation
authority; no public configuration key is added.
Missing `app.build` or a missing configured directory is read-only `absent`.
Present invalid configuration/build ID or an unsafe existing directory is a
configuration-input validation failure, not artifact `malformed`.

The three command families depend on one
`ApplicationProjectionBuildSetStatusSource` implementing
`ProjectionBuildSetStatusSource::withStatus()`.
Its Reader call is
`withPublishedStatus(expectedApplicationBuildId, callback, selectedKey)` and
holds the shared lock through render/write.
Production validates the published descriptor/file/input identity graph and
does not recalculate exact-three hashes from Source or old manifests.
The older exact-three hash method remains a component-test seam.

Target metadata advances to exact 31 canonical rows with
`schedule:list`/`schedule:ls`.
The lazy Framework command-name set remains exact 25 and the reservation set
remains exact 13.
One pure strategy mapper serves route and schedule producers without autoload
or runtime resolution.

`build:compile` creates all three projections from the same in-memory
compilation and calls the Publisher once after existing artifact writes.
Root-last publication is atomic only for the three projection files; earlier
application artifact writes are not rolled back on projection failure.
Producer and pre-commit Publisher failures propagate without a success line.
Post-commit cleanup failure leaves the root published, emits the exact success
stdout plus cleanup stderr, and exits `1`.

Kernel composition preserves the existing Application Bootstrap behavior:
explicit class-string Commands may be instantiated and prebuilt instances are
reused.
The new command execution path promises no additional factory, Container,
Provider, DB, Source, handler, or clock resolution after composition.
Stable `1.2.0` and public Guide/Website artifacts remain unchanged until the
later release-documentation and release gates.

## P23-002A1 current ProjectionBuildSet foundation correction

D146 is the current Decision for the PHP filesystem boundary. Projection v1
descriptor paths are single basenames with exact key/kind/prefix/raw-digest
binding; nested descriptor paths are no longer allowed. D146 also defines the
reserved immutable-generation and temporary basename families, exact existing-
target reuse/reject matrix, first-lock bootstrap, accepted directory-entry
limit `256`, GC deletion limit `32`, and the post-root cleanup result. These
rules close the intermediate-symlink and ambiguous-GC namespaces before A1
implementation. Stable `1.2` and public configuration remain unchanged.

## Historical Round 23 Current correction rules

Round 23 is a bounded parent-management/specification correction. Round 22 is
historical Changes Requested and its evidence is preserved below in the
historical checkpoints. P23-001S is
the accepted child prerequisite and is not recreated; historical reopen
evidence remains only in explicitly historical checkpoints. Every
`ProjectionBuildSet v1` reader takes one shared lock before opening the root
and all three projection files, validates every root/descriptor/file and all
root, digest, build, and input identities, then selects and renders rows from
the command, route, or schedule projection for that reader. A non-selected
missing, malformed, digest, build, or input mismatch invalidates the complete
set. `list`/`ls` maps all six framework-recovery
states to framework rows at exit `0`; `route:list`/`schedule:list` retain
artifact-failure exit `2` and operational-read exit `1`. P23-002A/B/C owns
the complete-set root/all-three validation and selected-row rendering fixtures
for all three families, including each non-selected mismatch.

`worker:list` and `worker:ls` are neither BlackOps-owned nor reserved. An
Application may own either spelling as a canonical command or alias; an
unregistered spelling follows normal Symfony unknown-command behavior. BlackOps
1.3 provides no Job registry or built-in Job listing. P23-002A owns positive
Application-canonical/alias, unregistered-negative, and no-Framework-Job-row
fixtures.

The fixed Status route is `/operations/{operationId}`. Placeholder names are
structurally equivalent for collision identity, so `/operations/{id}` collides
with the canonical row while the public wire always renders
`{operationId}`; P23-002B owns exact-row and equivalent-placeholder/static-
segment collision fixtures.

Operation reads use exactly
`access.operation_{status,journal,outcome,diagnostics}.requested|unavailable|accessed`;
each outcome has catalog delivery `required`, and required canonical append is
durably persisted before a read response or failure is released. Service-managed actions use exactly
`audit.access.{query,export,sign,verify}.requested|unavailable|accessed`;
`requested`/`unavailable` are durable and `accessed` is required before a
result or signature is released. P23-003A/D/F/G own the catalog, adapters,
signing, and query/export fixtures.

Genesis alone starts a partition at sequence `1`; every later segment starts
at the prior final sequence plus one, and
`recordCount=lastSequence-firstSequence+1`. The parent Report owns the
checkpoint examples `1-42` and `43-50` and their gap/overlap negatives.
Framework-controlled signing for exact target
`system:global/audit-control-v1` is a finite control close: its signed
checkpoint/receipt is authoritative and it emits no
`audit.access.sign.accessed`. The target and framework role derive this
exception; callers cannot select it and ordinary signatures remain audited.

`AuditHold v1` has one closed revision shape:
`{schemaVersion:1,kind:"audit_hold",holdId,target,reasonCode,state,version,`
`previousStateHash,stateHash,updatedAt,updatedBy,idempotencyKeyHash}` with
immutable `target={partitionId,startSequence,endSequence,eventCategory|null}`,
`active|released` state, continuous hashes, and only `active -> released`.
Exact state/idempotency hash domains, raw-key 1..256-byte NFC/no-control
bounds, CAS/idempotency, inclusive 64 KiB cap, and actor/time bounds are
required; overlap, wildcard, and same-snapshot eligibility remain.

`AuditReadRequestBasis v1` includes `requestedAt` and `nonce`; its requestRef is
exactly `"oid:v1:" || HEX_LOWER(SHA-256(LP(domain) || LP(JCS(requestBasis))))`,
where `domain` is `"blackops.audit.read.request-ref.v1"`, `requestBasis` is the
closed six-field object in D147, and the LP concatenation is the preimage rather than the
final requestRef. Null/NFC/time/nonce bounds, an independent Node/Docker-PHP
golden, an algorithm-substitution negative, and null/present/substitution/
cap+1 fixtures are required. The golden uses
`purpose=status`, `actor=null`, `execution={id:"exec-1",type:"cli"}`,
`tenant=null`, `requestedAt="2026-08-20T03:20:00.123456Z"`, and
`nonce="nonce-1"`; its canonical JCS is 150 bytes, its LP preimage is 192
bytes, and its requestRef is
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
The trusted decision and derived ref are added only in the later full
`AuditReadContext v1`; callers supply neither.
Every actor/execution/tenant typed reference in this A1 read boundary is the
closed `{type,id}` object; each member is valid UTF-8, NFC, free of `Cc|Cf`,
and 1..256 UTF-8 bytes inclusive, without trim, case-fold, or silent
normalization.

The existing Stable `1.2` Operation ID, lifecycle Journal, Structured Log, and
OpenTelemetry contracts remain governed by Specifications 65, 94, and 100.
Structured Log/OpenTelemetry already provide current Operation, Correlation,
Trace, and Span correlation, and `ExecutionScopedLogger` is a current safe
observability projection. Only the new four-surface Audit correlation
contract and the 1.3 command/runtime additions are not current capabilities.

## Historical Round28 Audit contract correction (superseded by Round29)

Round28 was the prior Audit authority for this proposal. It is now Historical
evidence and is superseded by the Round29 correction below. Round27 Search and
Round28 Audit remain separate review lanes. Stable 1.2 remains current and all
Phase 23 and 1.3 Audit capability remains an unreleased proposal.

### Query and cursor

AuditAccessContext v1 is the closed object
{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,
requestedAt,nonce,requestRef,authorizationDecision}. A query must carry
purpose="query"; authorizationDecision="allow" is required before a query
is executed. The requestRef hash input is exactly
{purpose,actor,tenant,requestedAt,nonce}; it never includes a target,
partition, filters, limit, order, cursor, or authorization result. The access
context therefore binds the authorization decision to the query purpose while
the query wire binds the authorized target and selection.

AuditQueryRequest v1 is the closed object
{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
limit,order,snapshotHeadSequence,cursor}. partitionId is exactly
system:global or tenant:<64 lowercase hex>. filters is exactly
{eventCategory:null|string,recordIdPrefix:null|string,fromSequence:null|positive,
toSequence:null|positive}; non-null strings are NFC, control-free, and
1..256 UTF-8 bytes, and fromSequence must not exceed toSequence. limit is
1..1000. order is exactly "sequence_asc"; no other order or implicit database
order exists.

The first page has snapshotHeadSequence=null and cursor=null. The service
captures the canonical partition head once from the trusted Framework store
clock, then evaluates the filters against that head. A continuation carries
the positive captured head and a cursor whose partition, filters, limit, and
order are byte-for-byte equal to the request. The effective upper bound is
the captured head and the optional toSequence; fromSequence and the cursor's
lastSequence are inclusive/exclusive as stated by the query: records satisfy
sequence >= fromSequence when present and a continuation satisfies
sequence > lastSequence. No selected record may have sequence greater than
the captured head or the effective upper bound.

AuditQueryResult v1 is exactly
{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,hasMore,nextCursor,reasonCode}. For status ok, the
partition and positive captured head are non-null, records is a complete
ascending array of 1..limit canonical records, hasMore is true exactly when
another matching record remains at or below the same bounds, and nextCursor
is non-null exactly when hasMore is true. For empty, partition and head remain
non-null, records is [], hasMore=false, nextCursor=null, and reasonCode=null.
For unavailable, partitionId, snapshotHeadSequence, and records are null,
hasMore=false, nextCursor=null, and reasonCode="resource_unavailable".
There is no partial record, existence disclosure, or cursor on an unavailable
result. Result JCS plus one LF is capped at 1 MiB inclusive.

The cursor wire is exactly
aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>.
The closed payload is
{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,limit,order,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}. The tag is
HMAC-SHA-256 over LP("blackops.audit.query.cursor.v1") || LP(JCS(payload))
with the 32-byte role-bound audit-query-cursor key selected by keyId. Strict
canonical base64url/JCS, closed-field, partition/filter/limit/order/head/
last-sequence, timestamp, key role, and cap checks precede constant-time tag
comparison, lookup, and decrypt. The key window is exactly
key.notBefore <= issuedAt <= trustedNow < expiresAt <= key.notAfter, with
trustedNow captured once from the Framework clock and never taken from the
cursor. Unknown, revoked, wrong-role, expired, future, and boundary-invalid
keys fail closed.

An old verification key is retained only while it can verify at least one
issued, unexpired cursor whose issuance and expiry fit that key window; it may
be retired immediately after the last such cursor expires. Issuance always
uses the current active key. P23-003G owns query authorization, fixed-head
selection, result completeness, cursor codec, key-window retention, and
first/middle/final/empty/unavailable, no-repeat/no-skip, bound, substitution,
expiry, rotation, and cap fixtures.

### Durable ingress and Outbox

AuditIngressEnvelope v1 is the exact closed durable-only object
{schemaVersion:1,kind:"audit_ingress_envelope",logicalRecord,contentHash,
recordedAt,ingressOrdinal,idempotencyKey,recordId,partitionId,tenantBinding}.
logicalRecord=AuditRecord without integrity (the AuditRecord v1 integrity field
is removed). recordId equals
logicalRecord.recordId; recordedAt is the trusted UTC timestamp with six
fractional digits and equals the logical record's recordedAt; and
contentHash is the lowercase 64-hex
H(ENC("blackops.audit.content.v1") || ENC(JCS(logicalRecord))).
ingressOrdinal is a positive JSON-safe integer allocated monotonically under
the partition ingress fence. idempotencyKey is non-empty NFC/control-free
text of 1..256 UTF-8 bytes and is protected, not a clear credential.
partitionId and tenantBinding use the canonical partition and tenant union;
tenantBinding is null or {type,id}, equals logicalRecord.tenant, and
partitionId equals its canonical derivation. Unknown fields, missing fields,
record/content/timestamp/tenant mismatches, cap+1, and normalized
substitutions fail closed.

AuditOutboxRecord v1 is the exact closed row
{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}. Its protected
plaintext is RFC 8785 JCS of the envelope without LF. LP(x) is
U32BE(byte_length(x)) || exact UTF-8 bytes. The custom AAD is exactly
LP("blackops.audit.outbox.aad.v1") || LP("AuditOutboxRecord") || LP("1") ||
LP("1") || LP("1") || LP(exactKeyId) || LP(recordId) || LP(partitionId) ||
LP(TENANT(tenantBinding)) || LP(ASCII(canonicalDecimal(ingressOrdinal))) ||
LP(decoded32(idempotencyKeyHash)) || LP(decoded32(contentHash)).
TENANT(null)=0x00. TENANT({type,id}) is
0x01 || LP(UTF8(NFC(type))) || LP(UTF8(NFC(id))). The marker is mandatory;
The canonical present-marker token is
0x01||LP(UTF8(NFC(type)))||LP(UTF8(NFC(id))); a wrong hash is rejected.
0xff, absent/present swaps, type/id substitutions, partition swaps, and
header substitutions fail before decrypt. Row identity, hashes, protected
body, key ID, nonce, ciphertext, and tag are immutable; retry reuses the same
ciphertext.

P23-003C owns retry authority. maxAttempts=1..32 is an injected configuration
integer in 1..32, default 5; backoffBaseSeconds is 1..3600, default 1; and
backoffCapSeconds is 1..86400, default 3600. For a failed claim attempt n,
the deterministic delay is min(backoffCapSeconds,
backoffBaseSeconds * 2^(n-1)); jitter is not part of v1. Ingress starts at
attempt=0 and pending. A claim is eligible only when pending,
availableAt <= one trusted transaction clock reading, and the row is the
minimum unacknowledged ingressOrdinal in its partition; claiming increments
attempt exactly once and binds one fencing token. A transient failure or
expired lease returns to pending with availableAt set from that same trusted
clock and the formula above. A non-retryable failure or attempt equal to
maxAttempts becomes poisoned. Only the matching fencing token may relay,
retry, or poison; stale CAS fails, FIFO cannot be bypassed, and terminal rows
retain their metadata. The config range/default/formula, eligibility,
exhaustion, and single-clock rules are owned by C and covered by
initial/retry/exhaustion/clock/lease/FIFO/idempotency negatives.

P23-003B owns the envelope, row, BOPD, tenant union, and AAD bytes. P23-003C
owns durable ingress transaction, idempotency, claim, retry, relay,
dead-letter, fencing, and retry configuration. Required delivery appends the
logical AuditRecord directly and never stores the envelope.

### AnchorIntent recovery

AnchorIntent v1 is one closed, crash-safe model with exactly these states:
prepared | externally_accepted | receipt_inputs_persisted |
receipt_persisted | acknowledged. The canonical state token is
prepared|externally_accepted|receipt_inputs_persisted|receipt_persisted|acknowledged.
submitted, accepted, and rejected are not
v1 states and cannot appear as aliases.

The closed fields are
{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,
checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,
anchorObjectRef,expectedPredecessorVersion,expectedPredecessorHash,
conditionalCreateToken,idempotencyKeyHash,acceptedObjectVersion,
acceptedObjectHash,acceptedAnchoredAt,anchorReceiptId,receiptSigningKeyRef,
receiptSignedAt,receiptPreimageHash,receiptBytesHash,anchorReceiptHash,
leaseFencingToken,leaseExpiresAt,version,previousStateHash,stateHash,
updatedAt,acknowledgedAt}. IDs, refs, token, and hashes have the established
NFC/control-free and lowercase-64-hex bounds; no external credential is
stored.

The nullability matrix is exact. prepared has all external-acceptance,
receipt, and acknowledged fields null; externally_accepted has the accepted
object version/hash/time and anchorReceiptId non-null, with all receipt
input/output and acknowledged fields null; receipt_inputs_persisted keeps
those values and adds receiptSigningKeyRef, receiptSignedAt,
receiptPreimageHash, and receiptBytesHash, while anchorReceiptHash and
acknowledgedAt remain null; receipt_persisted adds anchorReceiptHash and
keeps acknowledgedAt null; acknowledged adds acknowledgedAt and is terminal.
conditionalCreateToken and idempotencyKeyHash are immutable in every state.
At genesis version=1 and previousStateHash=null; every later state has
version=prior.version+1 and previousStateHash=prior.stateHash, with
stateHash recomputed over the closed object excluding stateHash.

Only an active fenced lease may mutate an intent; leaseFencingToken and
leaseExpiresAt are both null at rest or both non-null for one claim, and
acknowledged is terminal with both null. The conditional create uses one
persisted token. If the process crashes after external acceptance, retry reads
the same token/object and persists externally_accepted; it never creates N+1.
The same idempotency key and body return the existing intent, a different body
or stale version/hash/token fails, and all retries preserve external version,
receipt ID, receipt bytes, and hashes. receiptPreimageHash is the hash of the
canonical unsigned receipt preimage; receiptBytesHash is the domain-separated
hash of anchorReceiptId plus exact unsigned JCS+LF receipt bytes; the signed
receipt is persisted only after those inputs and its active, unrevoked
receiptSigningKeyRef are persisted; acknowledged is written only after the
receipt is durable. Key rotation never rewrites receipt bytes or hashes.
P23-003F owns the codec, version/hash CAS, lease fencing, external conditional
create, receipt preimage/bytes/hash, key lifecycle, persist ordering, and
before-accept/after-accept/receipt/ack crash and no-N+1 fixtures.

### Retention eligibility and hold

AuditRetentionState v1 is the closed metadata-only object
{schemaVersion:1,kind:"audit_retention_state",stateId,target,policy,state,
version,previousStateHash,stateHash,updatedAt,idempotencyKeyHash}. Its only
states are retained and eligible_for_future_deletion. Genesis is retained with
version=1 and previousStateHash=null. Every later revision has exactly the
prior version plus one and the exact prior stateHash. The only allowed
re-evaluation transitions are retained -> retained and retained ->
eligible_for_future_deletion; eligible_for_future_deletion is terminal and cannot return to
retained. Same-body idempotent re-evaluation returns the existing revision
without a new version. A hold cannot be embedded in this object.

AuditHold v1 remains a separate append-only object with its own active genesis;
an embedded-hold is forbidden. It has its
continuous hash/CAS, owner, and immutable partition/category/sequence target.
It is not the retention state, does not make a deletion decision, and cannot
be represented by a hold or holdId member in AuditRetentionState. P23-003G
owns retention policy evaluation, owner/snapshot matching, hold separation,
genesis/version/hash/owner negatives, the 1->3 gap, wrong hash/previous hash,
embedded-hold, and reactivation fixtures. Physical deletion remains future
P23-003H.

Round28 is Historical evidence, superseded by the Round29 sole-current
correction below. The current correction changes no Stable 1.2 source, public
Guide, runtime, package, or artifact.

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

## Historical Round 26 Current correction rules

Historical Round 26 was the prior parent correction authority. Round 25 and
all earlier checkpoints, including their review lanes and gate records, are
historical evidence preserved here. P23-001S retains the Historical accepted
Round 21 child prerequisite, but the parent remains unaccepted. Stable `1.2`
remains current and every Phase 23 capability remains an unreleased proposal.
The prior status was `Round 26 Correction Implemented / Review Requested`; the
current authority and status are the Round 28 correction above, with no Green,
Accepted, Decided, or Released claim.

### AuditQuery and cursor snapshot contract

`AuditQueryRequest v1` is the closed object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}`. `snapshotHeadSequence` is `null` only on
the first page and is a positive JSON-safe integer on continuation. The first
page requires `cursor=null`; the service captures the partition head once from
the trusted Framework store clock and returns that positive head in the result.
A continuation requires a strict `aqc:v1` cursor and a request head exactly
equal to the cursor head. Its partition and every filter must byte-for-byte
match the cursor payload. Every selected record has sequence at most the
captured head; a continuation starts strictly after `lastSequence` from the
previous page, so records cannot repeat or be skipped. `nextCursor` is emitted
only when another matching record exists at or below that head.

The result wire is exactly
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,nextCursor,reasonCode}`. `ok` has an authorized partition,
positive captured head, complete ascending records of length `1..limit`, and a
cursor only when more rows remain. `empty` has the same partition/head, an empty
array, and null cursor/reason. `unavailable` has null partition, head, records,
and cursor with `reasonCode=resource_unavailable`. Request JCS plus LF is capped
at 64 KiB; result JCS plus LF is capped at 1 MiB, and cap+1 fails before
allocation.

The cursor is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, where the
closed payload is
`{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`. It has one ASCII
dot, no padding, and a 2048-byte cap. The 32-byte tag is HMAC-SHA-256 over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))` with the exactly
32-byte role-bound `audit-query-cursor` secret. P23-003G captures `trustedNow`
once from the injected Framework clock, never from cursor input, and requires
`issuedAt <= trustedNow < expiresAt` plus
`notBefore <= issuedAt <= trustedNow < min(expiresAt,notAfter)` for the active,
unrevoked key. Strict decoding, canonical JCS, closed fields, partition/filter/
head/last binding, key window, and cap checks precede constant-time tag
comparison and any lookup/decrypt. P23-003G owns first/middle/final/empty,
head/last, expiry/key-rotation, substitution, and cap negatives.

### Local Audit Outbox state and protection contract

`AuditOutboxRecord v1` is exactly
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}`. The only wire
states are `pending`, `leased`, `relayed`, and `poisoned`; `leased` means
claimed, `relayed` means sent, and `poisoned` means dead-lettered. Retry is a
transition back to `pending`, not an additional state.

| state | attempt/availability | lease fields | allowed transition |
| --- | --- | --- | --- |
| `pending` | attempt `0..max`, `availableAt` non-null | both null | `leased` when eligible and FIFO head |
| `leased` | positive attempt, `availableAt` null | fencing token and expiry both non-null | `relayed`, `poisoned`, or fenced retry to `pending` |
| `relayed` | positive attempt, `availableAt` null | both null | terminal |
| `poisoned` | positive attempt, `availableAt` null | both null | terminal dead-letter |

Claim increments `attempt` exactly once and binds one fencing token. A retry or
expired lease clears both lease fields and sets a trusted future
`availableAt`; a non-retryable or exhausted failure moves to `poisoned`. Only a
matching fenced claim may mark `relayed` or `poisoned`; stale CAS writes fail,
and `ingressOrdinal` FIFO cannot be bypassed. Identity, tenant binding,
hashes, key ID, protected body, and ciphertext are immutable. The protected
body is BOPD v1 over JCS(`AuditIngressEnvelope v1`) without LF, and its AAD is
the exact tenant-union/partition/ordinal/hash LP sequence already specified;
retry never re-encrypts it. P23-003B owns the row, AAD, and protected bytes;
P23-003C owns transaction, idempotency, claim/retry/relay/dead-letter, and
fencing. Node/PHP null-versus-present, transition, FIFO, stale-CAS, tenant,
header, AEAD, and cap fixtures are required.

### AnchorIntent state, recovery, and ownership contract

`AnchorIntent v1` keeps the closed field set
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,
checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,anchorObjectRef,
expectedPredecessorVersion,expectedPredecessorHash,anchorReceiptId,
idempotencyKey,conditionalCreateToken,acceptedObjectVersion,acceptedObjectHash,
acceptedAnchoredAt,receiptSignedAt,receiptSigningKeyRef,receiptBytesHash,
anchorReceiptHash,leaseFencingToken,leaseExpiresAt,version,updatedAt}`. Its
only current states are `prepared`, `submitted`, `accepted`, and `rejected`.
The accepted triplet is exactly
`{acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt}` and is
non-null only in `accepted`; in that state the hash equals
`checkpointBytesHash`, genesis uses version `1` with null predecessor fields,
and non-genesis uses both predecessor fields with accepted version exactly
predecessor version plus one.

| state | accepted triplet | receipt fields | transition |
| --- | --- | --- | --- |
| `prepared` | all null | all null | `submitted` |
| `submitted` | all null | all null | `accepted` or `rejected` |
| `accepted` | all non-null and bound to checkpoint bytes | all non-null and internally consistent | terminal |
| `rejected` | all null | all null | terminal |

`conditionalCreateToken`, intent identity, request body, and idempotency key
are immutable. CAS requires the expected version/hash and one lease token;
same-key/same-body retries return the same intent, while a conflicting body or
stale version fails. A crash after submission reuses the same conditional token
and resolves the existing external result; it never creates a second object.
P23-003F owns this state codec, CAS/idempotency/recovery, receipt binding, and
verification. Prepared/submitted/accepted/rejected nullability, genesis/gap,
rejection, crash-after-submit, stale-CAS, and retry/no-N+1 fixtures are
required.

### Retention and hold current wire

`AuditRetentionState v1` is metadata-only and exactly fixes
`schemaVersion=1` and `kind="audit_retention_state"` in its closed wire
`{schemaVersion,kind,stateId,target,policy,state,version,previousStateHash,
stateHash,updatedAt,idempotencyKeyHash}`. Its only states are
`retained|eligible_for_future_deletion`; no hold or deletion is embedded.

`AuditHold v1` is a separate append-only closed revision. An active genesis is
mandatory: `state=active`, `version=1`, `previousStateHash=null`, and an
immutable owner/boundary target. Every later revision has exactly the prior
version plus one and the prior state hash. Only `active -> released` is valid;
`released` is terminal, a released genesis is invalid, and re-activation is
forbidden. P23-003G owns trusted owner/actor, partition/category/sequence
boundary, CAS/idempotency, retention-state and hold matching in one snapshot,
and fixtures for active genesis, +1 continuity, released terminality,
released-genesis/re-activation negatives, owner and boundary substitution, and
unchanged sealed bytes/keys. Physical deletion remains future P23-003H.

## Historical Round 25 Current correction rules

Round 25 was the sole current parent correction authority at its checkpoint. It
is now historical evidence; its status was `Round 25 Local Gate Green /
Independent Review Requested`, and no Round 25 gate result is current authority.

### Historical Round 25 exact wire

### ProjectionBuildSet exact wire and ownership

The closed root is exactly
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
`schemaVersion=1`, `kind="projection_build_set"`, and exactly one descriptor
at each map key. The map is not interchangeable: `command` has kind
`blackops_command_projection`, `route` has kind `blackops_route_projection`,
and `schedule` has kind `blackops_schedule_projection`; swapped or duplicate
kinds fail. Each descriptor is exactly `{kind,inputHash,path,sha256}` with
lowercase 64-hex hashes and `sha256` equal to SHA-256 of the exact raw
projection-file bytes. Each file is exactly
`{schemaVersion,kind,applicationBuildId,inputHash,records}` with
`schemaVersion=1`, file kind equal to descriptor kind, root build ID equal to
file build ID, and file input hash equal to descriptor input hash. The
application build ID is 1..128 NFC/no-control UTF-8 bytes. Each root-relative
POSIX path is 1..256 UTF-8 bytes with no absolute form, backslash, empty
component, `.`, `..`, control, symlink, or special-file component, and paths
are distinct. `ENC(string)="s:"||decimal_utf8_byte_length||":"||UTF8(string)`,
`ENC(integer)="i:"||canonical_decimal_digits`, `ENC(null)="n:"`, and
`ENC(bytes)="b:"||decimal_byte_length||":"||bytes`.
`buildSetHash=SHA-256(ENC("blackops.projection-build-set.v1")||ENC(JCS(root excluding buildSetHash)))`;
`inputHash=SHA-256(ENC("blackops.projection-input.v1")||ENC(JCS({kind,schemaVersion,applicationBuildId,sourceRecords})))`.
Stored root/projection bytes are UTF-8 RFC 8785 JCS plus one LF, no BOM,
CRLF, or other whitespace; root cap is 64 KiB, projection cap is 16 MiB, and
records are 0..4,096. Command records are exactly
`{name,aliases,description,origin,hidden}` with `origin=framework|application`;
route records are exactly `{method,path,strategy,operationType,origin}` with
`origin=operation_manifest|framework_fixed`; schedule records are exactly
`{name,operationType,cron,timezone,strategy}` and have no origin. Command sort
is `(name,aliases)`, route sort is `(path,method,origin,operationType,strategy)`
with nulls first, and schedule sort is `(name,operationType)` with nulls first,
all by canonical unsigned UTF-8 bytes. Shared readers validate all three
same-fd files under one lock before selecting rows; any non-selected mismatch
fails the complete set. Publisher ordering, flush/fsync, atomic generation/root
publication, directory fsync, and generation-safe GC are mandatory. P23-002A/B/C
own positive mapping and swapped/duplicate-kind/invalid-origin negatives.

### Self-contained requestRef and verifier ownership

The Read hash input vector is exactly
`{actor:null,execution:{id:"exec-1",type:"cli"},nonce:"nonce-1",purpose:"status",requestedAt:"2026-08-20T03:20:00.123456Z",tenant:null}`
with JCS 150 bytes, LP preimage 192 bytes, and
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
The Access hash input vector is exactly
`{actor:null,nonce:"n0",purpose:"query",requestedAt:"2026-08-19T00:00:00.000000Z",tenant:null}`
with JCS 103 bytes, LP preimage 147 bytes, and
`1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`.
The exact null/null hash-input vector is
`{actor:null,nonce:"n0",purpose:"query",requestedAt:"2026-08-19T00:00:00.000000Z",tenant:null}`.
Each uses its exact domain, LP(U32BE length plus UTF-8), SHA-256, lowercase
hex, and `oid:v1:` wrapper. P23-003F owns pure codec/verifier goldens and
wrong-domain/algorithm/raw-preimage/self-reference/cap negatives; D/G own
consumer integration, including Access cross-purpose/time/nonce/actor/tenant,
raw-target, and requestRef-reuse negatives.

One total verifier function runs after signature and file-digest validation:
Stage 6 authenticates catalog plus record graph/chain/hash/semantics first as
`14 authenticated_graph_invalid`, then mode/context/coverage as
`16 mode_coverage_invalid`, then freshness head shape/boundary as
`18 freshness_boundary_unavailable`. Global Stage 6 keeps references non-null,
returns `partitions:[]`, never reopens files, and uses this precedence for
simultaneous failures. Stage 7 only validates local checkpoint/anchor
trust/chronology (`15 checkpoint_or_anchor_invalid`) and local end-head
freshness (`18` as appropriate). Catalog code 10, the deprecated scope label,
and graph code 14 are never Stage 7 names; Stage 7 has no partitions in global
results.

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
snapshotHeadSequence,nextCursor,reasonCode}`. `ok` is non-null authorized
partition/head with complete ordered `1..limit` records and optional next
cursor; `empty` has the same partition/head and empty records/null reason;
`unavailable` has null partition/head/records/cursor and
`resource_unavailable`. Request/result JCS caps are 64 KiB and 1 MiB plus LF.

The cursor is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, with closed
payload `{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`, one literal dot,
ASCII/no padding, and a 2048-byte cap. Its 32-byte tag is HMAC-SHA-256 over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))` using the exactly
32-byte role-bound `audit-query-cursor` secret selected by a 1..128-byte ASCII
NFC/no-control keyId. P23-003G captures trustedNow once from the injected
Framework clock, never cursor input, in UTC with six fractional digits and
requires `issuedAt <= trustedNow < expiresAt`; the key must be active,
unrevoked, and satisfy `notBefore <= issuedAt <= trustedNow < min(expiresAt,notAfter)`.
Unknown/wrong-role/status/window/key/length fail before lookup/decrypt.
Strict decode/canonical JCS/closed/cap/partition/filter/head/last/time/key
checks precede constant-time tag comparison. Rotation verifies old keys only
through outstanding expiry and issuance uses the current active key.

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
LP(UTF8(NFC(id)))`, with exact bounds and equality/partition binding. Pending,
leased, relayed, and poisoned states have exact attempt/available/lease
nullability; immutable identity/body/hash/ciphertext and same-key idempotency
are required. B/C own Node/PHP null/present parity and `0xff`, wrong-marker,
type/id/header/tenant substitution, AEAD, and cap negatives.

`AnchorIntent v1` is the closed object with explicit
`schemaVersion:1,kind:"audit_anchor_intent"` and all accepted/receipt fields.
`prepared` has accepted version/hash/time all null and all four receipt fields
null. Only `externally_accepted` and later require
`acceptedObjectHash=checkpointBytesHash`; only then does genesis require
accepted version 1 with null predecessor fields, while non-genesis requires
both predecessor fields and predecessor version+1. Positive prepared/genesis/
non-genesis and non-null/equality/version negatives are required.

`receiptBytesHash` is exactly
`SHA-256(ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||exact unsigned receipt JCS+LF bytes)`;
the final bytes are not wrapped in another `ENC`. The receipt-input state has
only `receiptSignedAt`, `receiptSigningKeyRef`, and `receiptBytesHash` non-null
with `anchorReceiptHash` null; final receipt states have all four receipt
fields non-null. Lease fields are both null at rest or both non-null for one
fenced claim, and stale CAS writes fail.

Outbox AAD defines `TENANT(null)=0x00` and
`TENANT({type,id})=0x01||LP(UTF8(NFC(type)))||LP(UTF8(NFC(id)))`, then uses
`LP(TENANT(tenantBinding))` within the outer U32BE LP concatenation. Exact
type/id bounds, partition/equality bindings, and marker/header substitutions
are part of the wire. P23-003B/C own Node/PHP null/present parity,
`0xff`/wrong-marker/type/id/header/tenant substitution, and AEAD failures.

For `aqc:v1`, keyId is 1..128-byte ASCII NFC/no-control, the HMAC secret is
exactly 32 bytes and role is exactly `audit-query-cursor`. P23-003G captures
trustedNow once from the injected Framework clock, never cursor input, with six
UTC fractional digits and requires `issuedAt <= trustedNow < expiresAt`.
The key must be active/not revoked with
`notBefore <= issuedAt <= trustedNow < min(expiresAt,notAfter)`; unknown,
wrong-role/status/window/keyId/secret length fail before lookup/decrypt.
Rotation verifies prior keys only through outstanding expiry and issues with
the current active key. Strict decode/canonical JCS/closed/caps/partition/
filter/head/last/time/key checks precede constant-time tag comparison; expiry,
window, rotation, substitution, and cap negatives are required.

The Orchestrator independently ran the complete Round25 Local Gate and it is
Green. Focused/full Website passed `8/8` and `120/120`; Website check passed
`0 errors/0 warnings/0 hints`; build passed `42` pages with explicit site check
`41` pages and only existing nonfatal chunk-size and route-priority warnings;
release source/artifact guards and version baseline (`published=1.2.0`,
`historical=1.1.0`) passed; focused PHPUnit passed `30 tests / 250 assertions`;
Mago format/lint/baseline passed and analyze exited `0` with existing `71`
issues (`46 warning / 25 help`); PHP management-ID, current-only Node, Docker
PHP requestRef goldens/negative matrices, exact-16 scope, staged `0`, and
`git diff --check` passed. Node evidence includes JSON `7/7`, Read
`150/192/bfad...df0cf`, Access `103/147/1c505...e241`, links
`315 total/279 local/0 missing`, and seven current roots. The worker did not
rerun these full gates. Parent acceptance is not yet complete.

## Historical Round 24 Current correction rules

Round 24 was the then-current parent correction authority and is now historical.
It preserves the Round 23 review evidence as historical and keeps the separate
General and Audit lanes. Stable `1.2` remains current; every Phase 23 capability
remains an unreleased proposal.

The public Application Bootstrap boundary is format-sensitive. Explicit
class-string Commands are instantiated while the Console Kernel is composed;
prebuilt instances are reused. Default and raw discovered rows remain lazy,
while non-short JSON/XML/Markdown descriptor paths may resolve a Lazy factory
or Compiled Container. This is the observed boundary recorded in the frozen
CLI Guide and is guarded across Guide Source, generated HTML, raw, Search, and
LLM surfaces.

The one current projection wire is `ProjectionBuildSet v1`. Its closed root is
exactly `{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
`schemaVersion=1`, `kind="projection_build_set"`, and exactly `command`,
`route`, and `schedule` projection keys. Each descriptor is exactly
`{kind,inputHash,path,sha256}`; `sha256` is the lowercase SHA-256 of the exact
raw projection bytes, and `path` is a distinct 1..256-byte root-relative POSIX
path with no absolute form, backslash, empty/`.`/`..` component, control,
symlink, or special file. Kinds are exactly
`blackops_command_projection`, `blackops_route_projection`, and
`blackops_schedule_projection`.

`ENC(string)="s:"||decimal_utf8_byte_length||":"||UTF8(string)`,
`ENC(integer)="i:"||canonical_decimal_digits`, `ENC(null)="n:"`, and
`ENC(bytes)="b:"||decimal_byte_length||":"||bytes`. The exact formulas are
`buildSetHash=SHA-256(ENC("blackops.projection-build-set.v1")||ENC(JCS(root
excluding buildSetHash)))` and
`inputHash=SHA-256(ENC("blackops.projection-input.v1")||ENC(JCS({kind,
schemaVersion,applicationBuildId,sourceRecords})))`. Each projection file is
exactly `{schemaVersion,kind,applicationBuildId,inputHash,records}` with
`schemaVersion=1`, file `kind` equal to its descriptor kind,
`applicationBuildId` equal to the root value, and `inputHash` equal to the
descriptor value. Command
records are `{name,aliases,description,origin,hidden}` sorted by name then
aliases; route records are `{method,path,strategy,operationType,origin}` sorted
by `(path,method,origin,operationType,strategy)` with nulls first; schedule
records are `{name,operationType,cron,timezone,strategy}` sorted by
`(name,operationType)` with nulls first. All sorts use unsigned UTF-8 bytes,
all objects reject unknown/duplicate/missing/wrong-type/unsorted/non-NFC/
control/cap+1 members, and `origin` is exactly
`operation_manifest|framework_fixed`.

Root and projection storage is UTF-8 RFC 8785 JCS plus one LF, without BOM,
CRLF, or other whitespace; root is capped at 64 KiB and each projection at
16 MiB/4096 records. The publisher writes immutable generations via bounded
temporary files, flush/fsync, atomic file/root publication, and directory fsync,
holding exclusive lock through validation and generation-safe GC. Readers hold
shared flock on `.blackops-projection-build-set.lock` while opening and
validating the root and all three same-fd files, then select/render dedicated
rows; any non-selected mismatch invalidates the complete set.

The current pre-decision `AuditReadRequestBasis v1` is exactly
`{purpose,actor,execution,tenant,requestedAt,nonce}` and never includes
`requestRef` or an authorization result. The final reference is exactly
`"oid:v1:" || HEX_LOWER(SHA-256(LP("blackops.audit.read.request-ref.v1") ||
LP(JCS(requestBasis))))`; the LP bytes are only the SHA-256 preimage. The golden
is JCS `150` bytes, preimage `192` bytes, and
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
The exact hash-input vector is
`{actor:null,execution:{id:"exec-1",type:"cli"},nonce:"nonce-1",purpose:"status",requestedAt:"2026-08-20T03:20:00.123456Z",tenant:null}`.

Only a trusted post-decision boundary constructs `AuditReadContext v1` as the
exact ten-field object `{schemaVersion:1,kind:"audit_read_context",purpose,
actor,execution,tenant,requestedAt,nonce,requestRef,authorizationDecision}`.
Its basis fields/ref must equal the validated basis/derived ref; decision is
`allow|deny|resource_unavailable`, and caller input supplies neither field.
P23-003D/G runtime adapters each capture one 16-byte CSPRNG nonce and encode it
as 32 lowercase hex; the `nonce-1` vector remains a pure codec golden.

The current `AuditAccessContext v1` is the one closed nine-field object
`{schemaVersion:1,kind:"audit_access_context",actor,tenant,purpose,
requestedAt,nonce,requestRef,authorizationDecision}`. Actor is null or
`{principal:null|{type,id},execution:{type,id}}`; tenant is null or `{type,id}`;
purpose is `query|export|sign|verify`; decision is
`allow|deny|resource_unavailable`; `requestedAt` is UTC with six fractional
digits; and nonce is a 1..128-byte NFC/no-control noncredential. Its hash input
is exactly `{purpose,actor,tenant,requestedAt,nonce}`, with domain
`blackops.audit.access.request-ref.v1` and the same LP/SHA-256/lowercase-hex/
`oid:v1:` wrapper. The null/null golden is JCS `103` bytes, preimage `147`
bytes, and digest
`1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`.
Legacy requester field names are not fields or aliases.

Verifier Stage 6 has one finite precedence: catalog or authenticated
record-chain graph/hash/semantic mismatch is code `14` with safe name
`authenticated_graph_invalid`; mode/coverage mismatch is code `16` with
`mode_coverage_invalid`; freshness boundary mismatch is code `18` with
`freshness_boundary_unavailable`. Every Stage 6 global failure occurs after
signature authentication, keeps post-signature top references non-null,
returns `partitions:[]`, and never reopens a listed file.

The current Query wire is the closed `AuditQueryRequest v1`
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}`. Its `access` is the exact nine-field
AuditAccessContext; `partitionId` is authorized `system:global|tenant:<64
lowercase hex>`; `filters` is exactly
`{eventCategory:null|string,recordIdPrefix:null|string,fromSequence:null|positive,
toSequence:null|positive}` with null bounds or `fromSequence<=toSequence`;
`eventCategory` is null or an AuditRecord event-category value;
`recordIdPrefix` is null or a non-empty prefix. Every non-null filter string
and every string in the cursor payload is NFC, has no control character, and is
1..256 UTF-8 bytes; null remains allowed.
snapshot head is positive JSON-safe, limit is `1..1000`, and cursor is null
only for the first page or strict `aqc:v1` thereafter. Unknown fields, swaps,
invalid filters, and request JCS over 64 KiB fail before lookup.

`AuditQueryResult v1` is exactly
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,nextCursor,reasonCode}`. `ok` has an authorized partition,
positive head, complete non-empty `1..limit` records, and nextCursor only when
more rows remain; `empty` has the same partition/head, `records:[]`, null
nextCursor/reasonCode; `unavailable` has null partition/head/records/nextCursor
and `reasonCode:"resource_unavailable"`. Results are complete and ascending by
partition-global sequence, never partial, with JCS+LF capped at 1 MiB.

The exact cursor is
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, one literal
dot, no padding, ASCII, and at most 2048 bytes. Its closed payload is
`{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`. The 32-byte tag
is HMAC-SHA-256 under the role-bound `audit-query-cursor` key selected by keyId
over `LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`. Canonical
base64url/JCS, closed fields/caps, partition/filter/head/last, key role, and
UTC `issuedAt<expiresAt` validation precede constant-time tag comparison;
lookup/decrypt occurs only afterward. P23-003G owns query/cursor.

The exact closed `AuditOutboxRecord v1` row is
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}`. `protectedBody`
is binary BOPD v1 containing JCS(`AuditIngressEnvelope v1`) without LF, and
its exact AAD is
`LP("blackops.audit.outbox.aad.v1")||LP("AuditOutboxRecord")||LP("1")||
LP("1")||LP("1")||LP(exactKeyId)||LP(recordId)||LP(partitionId)||
LP(tenantPresenceAndTypeAndId)||LP(ASCII(canonicalDecimal(ingressOrdinal)))||
LP(decoded32(idempotencyKeyHash))||LP(decoded32(contentHash))` with U32BE LP.
`idempotencyKeyHash=H(ENC("blackops.audit.outbox.idempotency.v1")||
ENC(partitionId)||ENC(NFC(idempotencyKey)))`; raw key never appears clear.
Pending/leased/relayed/poisoned state and lease nullability are exact, identity
and protected bytes are immutable, retry reuses ciphertext, the logical body
is at most 1 MiB and BOPD allocation is bounded by `52+keyIdUtf8Bytes`, and
same-key conflict/head-FIFO/fencing/cap substitutions fail. P23-003B owns
row/AAD/identity and P23-003C owns transaction/idempotency/lease/relay.

`AnchorIntent v1` is the exact closed object
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,checkpointId,checkpointHash,
checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,
expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,
acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,
receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,
leaseExpiresAt,version,updatedAt}`. IDs/refs/tokens are bounded NFC/no-control
noncredential strings, hashes lowercase 64-hex, versions positive, and times
six-fraction UTC; only receiptSigningKeyRef names a signing key. `prepared` has
acceptedObjectVersion, acceptedObjectHash, and acceptedAnchoredAt all null and
all four receipt fields null; `externally_accepted` has the accepted triplet
non-null and receipt fields null;
receipt_inputs_persisted adds receiptSignedAt/receiptSigningKeyRef/
receiptBytesHash while anchorReceiptHash remains null; final
receipt_persisted|acknowledged states have all accepted and receipt fields
non-null. `receiptBytesHash` is exactly
`SHA-256(ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||exact unsigned receipt JCS+LF bytes)`;
`anchorReceiptHash` is the unsigned receipt semantic hash, not a signed-file
raw digest. Accepted hash equals checkpointBytesHash, receipt bytes/hash are
retry-identical, and fenced lease CAS forbids stale writes. Outbox `pending`
has attempt zero or a retry attempt, non-null availableAt, and null lease
fields; `leased` has null availableAt and non-null fencing token/expiry; terminal
`relayed|poisoned` has all scheduling and lease fields null. Only
pending-to-leased, fenced-transient-or-expired-leased-to-pending, and
leased-to-relayed/poisoned transitions are valid; immutable identity/body/hash
fields and ciphertext are reused on retry. P23-003F owns this
wire and reusable verification; the B/C/F/G ownership DAG is acyclic.

## Decisions

### 1. Proposal only

P23/BlackOps `1.3` remains an unreleased proposal. Stable `1.2` and its public
documentation are unchanged. No `1.3` command, FrankenPHP migration, Audit
Trail, or future value proposition may be described as delivered until its
implementation, independent review, release gates, and publication are
accepted.

### 2. BlackOps-owned command discovery

BlackOps must not expose Symfony's built-in descriptor output as a public
machine contract. Symfony `ListCommand` resolves an application description,
accepts `xml` and `md`, and its `JsonDescriptor` includes help, definition,
class-derived command information, and defaults. Those are implementation
details and can force command factories, containers, or application code to be
resolved.

Stable `1.2` already has Symfony-backed `list` behavior. P23 adopts a
BlackOps-owned replacement projection; `list` is therefore a Stable current
name and `ls` is the only new name. Until P23-002A compatibility acceptance,
the replacement accepts every Stable combination of optional namespace,
`--raw`, `--short`, and `--format=txt|json|xml|md`; repeated `--format` is
last-wins. `--json` and `--all` are 1.3-only inputs. `--json` alone is exactly
`--format=json`; only the new `--json` plus explicit `--format` conflict
returns exit `2`. `--raw` affects txt only and is ignored for non-txt, while
`--short` is an exact no-op in every format. A namespace may contain multiple
colon-delimited segments; nested exact, unique abbreviated, ambiguous, and
unknown namespace diagnostics and exit are fixed by Symfony Stable 1.2.0
golden fixtures, so an accepted Stable input cannot be changed to exit `2`.
These requests still produce BlackOps-safe rows, never Symfony descriptor
fields.

The one P23-002A/global pre-release reservation registry checks both canonical
and alias names for `list`, `ls`, `help`, `completion`, `_complete`,
`route:list`, `route:ls`, `schedule:list`, `schedule:ls`,
`diagnostics:check`, `queue:status`, `about`, and `outbox:status`.
P23-002B/C/D/E reuse it. Any collision fails closed with owner/name and an
actionable application rename migration; silent override is forbidden.
`worker:list` and `worker:ls` are not BlackOps-owned or reserved: an
Application may own either canonical name or alias, while an unregistered
spelling follows normal Symfony unknown-command behavior. The alias is
identical for Human, raw, JSON, XML, and Markdown modes, including filter,
exit, and failure behavior.

The v1 output contract is:

| Mode | Contract |
| --- | --- |
| default Human | deterministic table of `name`, `aliases`, `description`, and `origin` |
| `--raw` | deterministic LF-delimited records containing canonical name and the exact comma-separated alias list; no help, class, definition, defaults, or factory data |
| `--json` | versioned JCS+one-LF UTF-8 object with `schemaVersion: 1` and `commands`; with `--all`, each item also contains `hidden` (XML/Markdown carry the same boolean) |

The optional namespace filter applies to the canonical command name. Results
are sorted by canonical name, then aliases. Hidden entries are omitted by
default and included only with `--all`; with `--all`, JSON/XML/Markdown carry
the `hidden` Boolean while raw retains its fixed two-column wire. For `list`/`ls`, the Stable namespace
diagnostic and exit behavior is exactly the Symfony Stable `1.2.0` golden for
nested exact, unique abbreviated, ambiguous, and unknown namespaces; the
replacement introduces no blanket namespace exit `2`. New invalid options or
formats, and only new `--json` together with an explicit `--format`, return
exit `2`. Missing, malformed, unsupported, stale, or build-mismatched application
projection states return framework rows at exit `0` with their status;
operational read failure returns exit `1`. Complete route/schedule artifact
failures alone retain exit `2`. The same selected rows and aliases must be
represented in every format.

Every projection reader has one shared read transaction: it acquires shared
flock before opening the root and all three same-fd projection files, validates
the complete root and every descriptor/file (including non-selected files),
then selects and renders rows from its dedicated command, route, or schedule
projection. A non-selected missing, malformed,
digest, build, or input mismatch invalidates the complete build set. The list
reader has a framework recovery projection even without an
application artifact. It always emits `build:compile`, one canonical `list`
row whose aliases include `ls`, separate canonical `help` and `completion`
rows, and all safe framework rows; same-build/current/valid application rows
are merged when available. It opens and validates the installation-owned
`ProjectionBuildSet v1` root and all three same-fd command/route/schedule files
under the shared lock, then selects and renders command rows;
there is no standalone `{schemaVersion,kind,applicationBuildId,
projectionInputHash,records}` wire. The six states are
`available|absent|malformed|unsupported|stale|build_mismatch`, with one
precedence table: raw cap, ENOENT, operational I/O, malformed,
unsupported, build mismatch, stale input identity, available. List/ls maps
all six to framework rows/status exit 0; complete route/schedule maps artifact
failure to exit 2 and operational reads to exit 1. XML/Markdown are exact
Spec105 wires and `--short` is a no-op. The fixed framework route is compiled
into the dedicated route projection; route:list and schedule:list select and
render their dedicated rows only after the same complete root/all-three
validation. No runtime HTTP join or direct Operation Manifest reader is
permitted.

The projection reader/publisher uses the regular lock file
`.blackops-projection-build-set.lock` under the injected trusted root. D146
refines the earlier “nofollow” shorthand to pre-open `lstat`, one `fopen`,
pre-I/O `fstat`, and second `lstat` equality for regular-file type, device, and
inode. It is not a portable kernel `O_NOFOLLOW` claim. P23-002A1 owns this
shared wire/filesystem foundation; P23-002A2 owns command/list behavior, and
neither activates a partial root. Readers take shared flock before root and
all three verified-handle file opens and hold it through complete validation,
selection, and rendering. Publishers take exclusive flock before
creating or writing any generation temporary or content-addressed file and
hold it through same-fd validation, root temp+fsync+atomic rename, directory
fsync, and GC. GC removes only paths unreferenced by the now-current root and
bounded abandoned temporary/generation cleanup; no unpublished generation is
GC-visible outside that exclusive lock. A publisher crash before root
publication leaves the old root readable. Lock replacement/symlink/special,
preparation-barrier, publish+GC, resume, and crash fixtures are required.

The reader consumes a dedicated compiled command projection. Its execution
must not resolve a command factory, Symfony container, database, handler,
application class, or source reflection. Console composition keeps the
existing Application Bootstrap behavior: explicit class-string Commands may
be instantiated and prebuilt instances are reused before execution. The
replacement does not claim to remove that existing composition work.
Constructor/factory/container/DB sentinel fixtures measure that executing the
new list command adds no further resolution for missing/malformed or valid
projection states, and `build:compile` remains discoverable. Public JSON must never contain
`help`, `definition`, `class`, `factory`, `container`, `default` values, XML,
or Markdown fields. The existing `operation:list` remains a separate Stable
operation metadata command until the new contract is implemented and released.

Stable Guide truth is a separate repository-blocking prerequisite:
`P23-001S — Stable list documentation truth correction` is currently
`Round 21 Accepted`; it is not a new task and must not be recreated. Historical
reopen evidence remains in the historical checkpoints only. The former Stable
Guide sentence that described
`list` as side-effect-free is historical and was corrected in the current
Guide to the observed constructor/factory behavior. That former sentence is
not positive evidence; explicit commands and Symfony structured descriptors
can construct/resolve code. The corrected Guide passed fresh child General
review before the current parent review and before P23-002A. This proposal does not
make a 1.3 capability public.

### 3. Effective route inventory

`route:list` and its exact `route:ls` alias list one dedicated closed compiled
`blackops_route_projection`, not arbitrary application middleware or a runtime
join. The P23-002B build step compiles Operation HTTP rows and the framework
fixed `GET /operations/{operationId}` row into that single artifact; its descriptor
`inputHash` binds the closed source set and build root.
Its reader nevertheless validates the root and all three projection files under
the shared lock before selecting route rows; a non-selected command or schedule
file mismatch invalidates the complete set.
The public `Application::http()` composition always provides the fixed row.
Each row
contains deterministic method, path, compiled `strategy` string when the route
is an Operation route (or `null` for the fixed route), operation type when
applicable, and an origin (`operation_manifest` or `framework_fixed`). Handler
class, container,
database, middleware instance, credential, and application service data are
never resolved or emitted.

Human output includes `method`, `path`, `strategy`, `operationType`, and
`origin`; JSON v1 uses the same fields and rows. Missing, malformed,
unsupported, stale, or build-mismatched dedicated route projection artifacts
fail closed with exit `2`; an operational artifact read failure returns `1`.
The command does not fall back
to reflection, source discovery, route attributes, or a live container.

The Human columns are exactly `Method|Path|Strategy|Operation Type|Origin`;
null strategy/type values are empty cells, projection-controlled values are
formatter-escaped, and the complete bytes use `OUTPUT_RAW`. Successful Human
and JSON have empty stderr and one final LF; JSON is the closed JCS
`{schemaVersion:1,routes:[...]}` shape and does not add
`applicationProjectionStatus`. Artifact failure writes exactly
`Route projection is unavailable or invalid.\n`, operational read writes
`Projection build-set read failed safely.\n`, invalid input writes
`Input error.\n`, and invalid expected identities write
`Route projection input is invalid.\n`; every failure has empty stdout.

The low-level nullable `ProductionRuntimeComposer` composition is an
internal/custom composition and is not the public effective-route target. If
optionality is introduced later, it requires an explicit versioned
enabled/capability field and positive/negative fixtures. The fixed status route
is a descriptor only; it does not imply that a status query, authorization
provider, or database is available to the listing process.

### 4. Compiled schedule inventory

`schedule:list` and its exact `schedule:ls` alias read one dedicated closed
compiled `blackops_schedule_projection` produced by P23-002C and named by the
ProjectionBuildSet root. Its descriptor `inputHash` binds the Application
Schedule metadata source; every reader opens and validates the root plus all
three projection files under one shared lock, then selects and renders only
the schedule rows dedicated to that reader. It never joins runtime files or
reads an Operation Manifest.
A schedule reader first validates the root and all three projection files under
the same shared lock, including non-selected command and route descriptors,
before selecting schedule rows.
A row contains schedule
name, operation type, cron, timezone, and execution strategy. It does not
resolve a Provider, container, database, clock, actor, tenant, due occurrence,
misfire, overlap, or handler. Framework maintenance scheduler tasks are not
Application Schedules and are never included. Human and JSON v1 results are
deterministically sorted. `schedule:list` and the exact `schedule:ls` alias
are mutually identical: valid complete artifact exit `0`, operational read
exit `1`, and missing, malformed, unsupported, stale, or build-mismatched
complete artifact exit `2`. This is not the list/ls application-recovery rule,
which returns framework rows plus status at exit `0`.

The exact Human columns are
`Name|Operation Type|Cron|Timezone|Strategy`; projection-controlled cells are
formatter-escaped and complete bytes use `OUTPUT_RAW`.
JSON is the closed JCS `{schemaVersion:1,schedules:[...]}` object plus one LF
and has no `applicationProjectionStatus`.
Success, invalid input, invalid configured identity, artifact failure, and
operational failure use the exact stdout/stderr/exit matrix in Specification
105, identically for canonical and alias invocations.

### 5. Worker command ownership boundary

BlackOps has no `JobDefinition`, Job registry, or built-in Job listing.
`worker:list` and `worker:ls` are not BlackOps-owned or reserved. An Application
may own either spelling as a canonical command or alias; an unregistered
spelling follows normal Symfony unknown-command behavior. P23-002A owns
positive Application-canonical/alias, unregistered-negative, and no-Framework-
Job-row fixtures. Deferred
definitions are visible through the future bounded
`operation:list --strategy=inline|deferred` filter. The optional case-sensitive
value occurs at most once; missing, duplicate, unknown, or case-variant input
returns exit `2` before Provider/compiler work and emits no partial output.
Absent preserves Stable no-filter Human bytes, headers, order, and exit. A
valid filter runs after existing compilation and before typeId unsigned-byte
sorting, emits the unchanged `Type ID|Definition|Execution Strategy` table
(header-only for zero rows), exit `0`, no JSON/public machine wire, DB read,
or source fallback, and preserves Provider/compiler exceptions. P23-002A owns
implementation and independent review. It remains proposal-only and
unreleased until P23-006; the current Stable no-filter Human behavior remains
unchanged until that release and compatibility gate accepts the filter. Queued
instances belong first to aggregate-only `queue:status` and only later to a
bounded `queue:list` if operation names, tenant scope,
pagination, and redaction are defined. Process capability belongs to
`about`/`worker:status` only if a later worker registry is explicitly defined.

### 6. Additional command priority

After the three metadata projections, the priority order is:

1. `diagnostics:check` and aggregate-only `queue:status`;
2. `about` and `outbox:status`;
3. `dead-letter:list`, deferred until operation/outbox names, tenant scope,
   pagination, and redaction are defined.

These are proposed names, not Stable or delivered commands. Aggregate status
must not become an unbounded row dump or a secret-bearing health endpoint.

### 6.1 P23-002D diagnostics and queue boundary

P23-002D fixes `diagnostics:check [--json]` and `queue:status [--json]` as
CLI-only BlackOps `1.3` proposal commands. Neither command adds a public HTTP
route. Stable `1.2.0` public Documentation and runtime names remain unchanged
until the `1.3` release gate.

The readiness authority is an explicitly registered public
`OperationalHealthQuery` supplied through
`ApplicationBuilder::withOperationalHealthQuery()`. The Framework does not
search a compiled Container, Service Provider, Database, or Operation Runtime
for a readiness query. Missing registration, query failure, and invalid query
shape become the safe codes `query_unavailable`, `query_failed`, and
`query_invalid`; raw exception, class, provider, configuration, and credential
details are never emitted.
This deliberate public authority makes Application and InternalApplication
depend on Observability; the architecture rule records exactly those two edges.

Diagnostics reads the ProjectionBuildSet status once under its existing shared
lock, reduces it to one of
`available|absent|malformed|unsupported|stale|build_mismatch|
configuration_invalid|read_failed`, releases the lock, and only then calls the
readiness query once. An arbitrary report is not rendered directly. A bounded
normalizer accepts only `readiness`, the exact six existing readiness codes,
unique and complete typed statuses, and a matching aggregate status. Output is
at most one Projection row plus six readiness rows; query contraction replaces
the six readiness rows with one failure row. Projection `available` and all six
readiness passes are required for exit `0`; every valid diagnostic failure is
a safe Report at exit `1`. Input error alone is exit `2`.
The normalizer checks the exact cardinality before traversing the input checks.
Application readiness `compiled_artifact` remains distinct from the
Framework-owned ProjectionBuildSet publication row.

Queue authority is local host or Container CLI access. Scope is exactly
`installation`; there is no tenant selector, Application principal, pagination,
row listing, alias, or web route. All current installation Operation rows are
aggregated, but tenant, actor, Operation ID/type, payload, reason, attempt, and
lease-owner columns are neither selected nor emitted. Outbox is a separate
lifecycle owned by P23-002E and is excluded.

The exact queue count buckets are accepted ready/delayed, retry ready/delayed,
running active/expired, supervising, and dead-lettered. Equality with the one
captured `observedAt` is ready or expired. Completed, rejected, and failed rows
are excluded. Exact ages are oldest ready `available_at`, expired
`lease_expires_at`, supervising `updated_at`, and dead-lettered `updated_at`.
Counts and floored nonnegative ages are JSON-safe integers; a missing running
lease, future age, cap overflow, or count/null mismatch fails integrity.

The PostgreSQL source executes one fixed aggregate query and consumes one row.
It does not fetch or iterate queue rows. Missing schema/table is storage
unavailable, not an empty queue. P23-002D accepts the current O(N) aggregate
scan for correctness and adds no migration, index, counter table, or performance
SLA. A tenant-facing status surface or bounded `queue:list` requires a later
authorization, redaction, pagination, and schema decision.

Both commands use fixed-order Human `key=value` UTF-8/LF and RFC 8785 JCS plus
one LF, write success through `OUTPUT_RAW`, and emit no ANSI/BOM/CRLF. Queue
input/configuration failures are exit `2`; storage/integrity failures are exit
`1`; failure stderr is a fixed safe code and stdout is empty. Specification 105
owns the exact rows, fields, bytes, and precedence.
Their concrete Command objects are registered directly, not wrapped by
`LazyFrameworkCommand`; constructors perform no query/DB work and their
`run()` methods contract Symfony input exceptions before dependency execution.

### 6.2 P23-002E About and Outbox boundary

P23-002E fixes `about [--json]` and `outbox:status [--json]` as CLI-only
BlackOps `1.3` proposal commands. Neither command adds a public HTTP route or
changes Stable `1.2.0` Documentation. Both activate and are accepted together.

`about` uses Composer installed-package metadata as the Framework version
authority. The root package directly requires `composer-runtime-api:^2.0`;
transitive requirements are not authority. It reports the fixed package name
`blackops/framework`, the validated installed pretty version, the current
process's validated `PHP_VERSION` and `PHP_SAPI`, fixed scope
`current_process`, and one contracted ProjectionBuildSet status. Status is one
of `available|absent|malformed|unsupported|stale|build_mismatch|
configuration_invalid|read_failed`. A non-available status remains a valid
exit-`0` description; readiness belongs to `diagnostics:check`.

`about` does not expose build ID/hash, Composer reference/path, dependency or
Extension inventory, environment, OS/host, HTTP deployment profile,
FrankenPHP/Caddy version, Deferred process mode, or a capability list. There
is no Worker/runtime capability registry from which to derive those claims.
Invalid input is exit `2`; missing, invalid, or throwing installed metadata is
a single safe exit-`1` failure. Valid Human and JCS output contain fixed fields
only and no partial output.

`outbox:status` has host-operator authority and fixed `installation` scope.
It reads only PostgreSQL `outbox_records` and never combines Operation Queue,
Operation dead letters, or Outbox retry-audit rows. It neither selects nor
emits tenant, actor, record/Operation identity/type, payload/context,
fingerprint, relay, attempt, connection, or reason fields.

The seven mutually exclusive counts are pending ready/delayed, retry
ready/delayed, leased active/expired, and dead-lettered. Due time is
`COALESCE(next_attempt_at,available_at)` and equality is ready or expired.
`sent` is terminal success and excluded. The three ages are the oldest ready
due time, expired lease, and Outbox dead-letter time. Counts and floored
nonnegative ages are JSON-safe integers; count/age nullability, future values,
bounds, and source shape fail closed.

The PostgreSQL source executes one fixed aggregate query and consumes one row.
Its exact 13 aliases are seven counts, three filtered minima, and validation-
only `retryNullNextAttemptCount`, `leasedNullLeaseCount`, and
`deadLetterInvalidTimestampCount`. All anomaly counts must be zero. Missing
schema/table is storage unavailable, not an empty snapshot. P23-002E accepts
the current O(N) scan for correctness and owns no migration, index, counter,
or performance SLA.

Each filtered minimum is `null` or exactly 27-byte ASCII
`YYYY-MM-DDTHH:MM:SS.ffffffZ`. SQL applies `AT TIME ZONE 'UTC'` and a fixed
format before DBAL returns the value. The Source accepts only string or null,
validates exact grammar and calendar semantics, and requires exact parse／format
round-trip before calculating age. Offset, abbreviation, missing or non-six-
digit fraction, whitespace, nonstring, and invalid calendar values fail
integrity.

`outbox:status` captures one trusted clock value after input validation and
uses it for every predicate and age. Its valid-only factory resolves only the
Framework Database connection and schema; it does not reuse
`ApplicationOutboxRuntime` or compose codec, protection, sender, relay,
heartbeat, PCNTL, Provider, or handler dependencies.

Both commands use fixed-order Human `key=value` UTF-8/LF and RFC 8785 JCS plus
one LF, write with `OUTPUT_RAW`, and emit no ANSI/BOM/CRLF. About metadata
failure and Outbox input/configuration/storage/integrity failures use exact
safe codes owned by Specification 105. Their concrete Command objects are
registered directly, constructors perform no I/O, and Symfony input errors
are contracted before valid-only dependencies run.

P23-002D exact target `33` and Framework names `27` become exact `35` and
`29`; the global reservation registry remains exact `13`. The command
projection producer fixture becomes exact `38` and the default visible list
becomes exact `34`. `dead-letter:list`, row listing, Worker registry, and
public routes remain outside this decision.

### 7. Existing Stable retention defect

The current Lazy definitions for `retention:plan` and `retention:purge` omit the
`idempotency-record-days` option even though the concrete commands and
`ApplicationRetentionCommandFactory` support it. This is a real Stable defect.
It is not fixed by P23-001 and is not hidden inside 1.3 implementation. A
separate bounded Stable correction must add the option to the Lazy definition,
review it independently, and rerun the Stable retention contract before any
1.3 retention-related migration.

### 8. Runtime recommendation and falsification boundary

Stable `1.2` already provides FrankenPHP Worker Mode as the default HTTP
runtime. P23-004A hardens that existing default into the sole supported
production HTTP profile and gates Classic fallback/profile removal and
migration. The proposed Deferred arrangement deploys HTTP Worker and Deferred
as separate services/containers for the strongest process, resource, lifecycle,
and deployment isolation. Same-container multi-process packaging is an optional
operational convenience: each PHP process retains OS-process crash isolation,
but both processes share the cgroup, container lifecycle, rollout/rollback,
and resource ceiling. It requires explicit supervision and signal forwarding
and must never be described as independent resource or deployment isolation.

The standard FrankenPHP HTTP Worker is request-driven: it boots an application
once and calls `frankenphp_handle_request()` for incoming HTTP requests. A
same-process background queue requires a FrankenPHP Extension Worker and a Go
extension. A configurable product requires a custom Caddy module and a custom
`xcaddy`/FrankenPHP binary. The Extension Worker thread pool remains inside the
FrankenPHP process; it does not provide strict isolation from a native crash,
OOM, process signal, or startup failure.

The existing Deferred loop is a CLI/runtime loop with PCNTL signal handling,
heartbeat, lease recovery, and graceful shutdown. A direct transplant into an
HTTP worker is rejected. An experimental Extension Worker falsification spike
may test message handoff, startup, signal, heartbeat, memory, and failure
behavior, but it is optional and not a 1.3 release dependency or delivered
capability. A native crash or OOM that can take HTTP serving down is an
architectural stop condition.

No future FrankenPHP patch version is hardwired into the normative contract.
The official documentation cited below is dated evidence for this discovery,
not a promise about a future dependency version.

### 9. Classic removal is independent

Removing Classic HTTP deployment is independent of whether a same-process
Deferred experiment succeeds. P23-004A hardens the existing default
FrankenPHP HTTP Worker Mode into the sole supported production HTTP profile.
Classic profile/guide removal may occur only after bootstrap and pre-handler
failure, readiness, multi-request state and memory, upgrade, same-SHA CI,
rolling deploy, and rollback gates pass.

`Application::http()` remains the PSR request-handler escape hatch for custom
adapters and tests. The Worker Mode target makes no same-process Deferred claim.

### 10. Four distinct record surfaces

Application Log, Observability, Lifecycle Journal, and Audit Trail are separate
surfaces with separate owners and retention/access policy:

| Surface | Canonical responsibility | Typical owner | Sampling |
| --- | --- | --- | --- |
| Application Log | diagnostic and operational messages | Application/Framework logger | policy-dependent |
| Observability | logs/metrics/traces for operation and runtime health | Application telemetry provider | trace may be sampled |
| Lifecycle Journal | Operation lifecycle and replayable framework history | Framework canonical Journal store | never sampled |
| Audit Trail | attributable security/business/control history | dedicated AuditRecord store | never sampled |

In the 1.3 target, `kind=audit` in JSONL is only a derived safe projection for
an AuditRecord. It is not the canonical Audit Trail. Current Stable JSONL and
narrow retention/replay/rotation audit-like records are not retroactively
general AuditRecords. Trace and Span IDs are optional sampled correlation
values; they are never identity, integrity proof, ordering proof, or evidence
of an action.

### 11. Audit-ready-by-default, not compliance

The target is audit-ready-by-default. It is not automatic regulatory
compliance, non-repudiation, or proof that an application recorded every
business action.

The Framework automatically records only semantics it owns: retention and
legal hold, storage-key rotation, checkpoint-backed seal evidence,
observer replay, outbox retry/dead-letter, and privileged
audit/access/configuration/security boundaries. Applications
must register a versioned event definition and typed projector for business
CRUD, refund, approval, consent, and regulated access. The Framework must not
infer an action or resource from handler completion and must not duplicate
every lifecycle event into the Audit Trail.

The minimum Framework security/access catalog is typed by P23-003A and adapted
and independently reviewed by P23-003D against the current
`AuthenticationMiddleware`, `AuthorizationEvaluator`, and protected
Operation-data read boundaries. Candidate event types are
`security.authentication.succeeded`, `security.authentication.invalid`,
`security.authorization.allow`, `security.authorization.deny`, and
exactly `access.operation_{status,journal,outcome,diagnostics}.requested|unavailable|accessed`.
A request is emitted at
the decision boundary, an unavailable read uses the safe unavailable outcome,
and an authorized result uses the accessed outcome. Safe
reason codes include invalid/expired/malformed authentication,
authentication-required/forbidden/policy-rejected, authorized/denied, and
resource_unavailable. `actor.principal` is a typed `{type,id}` object or `null`
for invalid, unknown, or unauthenticated context; `actor.execution` is always a
typed execution object. The current `HttpAuthenticator` contract has no
module/method identifier, so the Framework must not invent one. A provider may
add typed provenance only when it explicitly supplies it; P23-003A owns that
extension/type. Credentials, tokens, request bodies, policy payloads, and
protected data are never evidence. Anonymous is not automatically an
authentication event; credential/session issue or revoke is Application-owned
unless a later Framework authenticator/session module owns it. Operation and
Trace IDs may be absent. Every operation-read event definition registers
catalog delivery `required`, and the required canonical append is durably
committed before returning the response. Service-managed query/export/sign/
verify actions use exactly
`audit.access.{query,export,sign,verify}.requested|unavailable|accessed`;
`requested` and `unavailable` are durable, while `accessed` is required and
durably committed before releasing a result or signature. They are never
silently downgraded to an observed projection. Fixtures cover authenticated,
invalid actor-null, unauthenticated deny, anonymous no-event, allow/deny,
privileged-read allow/deny, absent-ID, and credential-nonrecording behavior.

### 12. AuditRecord v1

The exact v1 logical schema is the schema in Specification 105: top-level
`schemaVersion: 1`, `kind: "audit"`, `recordId`, `event`, `action`, `actor`,
`resource`, `outcome`, `policy`, `tenant`, `time`, `source`, `correlation`,
`evidence`, and appended `integrity`. `event` contains `type`, positive
integer `version`, and category exactly one of
`business|security|access|configuration|control|retention|replay|rotation|outbox`,
plus required `catalogHash`, the 64-lowercase-hex domain-separated hash of a
closed AuditEventCatalog v1;
`action` contains `id`, `type`, and phase exactly one of
`requested|started|committed|succeeded|denied|failed|unknown`; actor principal
is a typed object or JSON `null` and execution is a required closed `{type,id}`
object;
resource is a safe typed opaque object; outcome status
is exactly `succeeded|denied|failed|unknown` with a safe code; and policy
contains `id`, `version`, decision exactly `allow|deny|not_applicable`, and a
reason code.

`tenant` is a tenant object or JSON `null`. `null` explicitly means the
system/global subject and maps to `integrity.partitionId: "system:global"`; it
never means an empty tenant. A tenant object is normalized NFC UTF-8
`{type,id}` with non-empty values, no controls, and at most 256 bytes each;
its exact partition is
`"tenant:" || HEX_LOWER(SHA-256(ENC("blackops.audit.partition.tenant.v1") ||
ENC(JCS({"type":type,"id":id}))))`, used identically by writer/verifier, and
`integrity.partitionId` must match. `time` contains UTC microsecond timestamps;
`recordedAt` is assigned once by the trusted authority and cannot be
producer-overridden. Required events receive it at canonical append; durable
events receive it once at trusted local Audit Outbox ingress. Each
authority/partition applies `max(now,lastAssignedRecordedAt)` in the stamping
transaction. Canonical sequence is allocated only at canonical append and is
the sole chain/order/pagination cursor; recordedAt is trusted ingestion/
retention time and may be older when a relay appends later. Relay/retry keep
recordedAt, recordId, and contentHash immutable. Durable outbox FIFO alone has
monotonic `ingressOrdinal`; required and durable make no cross-authority total
order claim. `occurredAt`
is owner asserted and must satisfy
`occurredAt <= recordedAt + 300 seconds` inclusive (one microsecond over fails).
Delayed/retry/replay records remain valid and do not extend retention. The
complete canonical `JCS(AuditRecord including integrity)||LF` is capped at
1 MiB inclusive before writer append/commit; generic strings and correlation
IDs are NFC UTF-8 <=256 bytes with no controls, while trace/span remain exact
32/16 lowercase hex. Cap, cap+1, Unicode-byte, producer-override, rollback,
delay, retry, and replay fixtures are required;
`source.owner` is exactly `framework|application`, `source.runtime` is
exactly `http|cli|worker|scheduler|relay|replay`, and `source.buildId` is
required. Correlation fields are optional safe IDs, with `attempt` an object
or `null`; trace/span/sampled are sampled correlation only. `evidence` has a
positive version and event-owned safe fields. Integrity has exactly
`hashSuite: audit-sha256-jcs-v1`, `partitionId`, `segmentId`, positive
`sequence`, `previousRecordHash`, `contentHash`, and `recordHash`; it has no
checkpoint field.

Every canonical object is closed (`additionalProperties=false` equivalent),
except that `evidence` is closed by its registered versioned event-definition
schema. The exact nested keys are `event={type,version,category,catalogHash}`,
`action={id,type,phase}`, `actor={principal,execution}` with principal null or
exactly `{type,id}` and execution exactly `{type,id}`, `resource={type,id}`,
`outcome={status,code}`, `policy={id,version,decision,reasonCode,auditDelivery}`,
`tenant=null|{type,id}`, `time={occurredAt,recordedAt}`,
`source={owner,component,runtime,buildId}`, and `integrity` exactly as above.
`correlation` permits only `operationId|correlationId|causationId|attempt|`
`traceId|spanId|sampled`, with `attempt` null or exactly `{number,kind}`.
`evidence` requires `version` and only its registered event members. Unknown
nested/evidence members fail. Credentials, tokens, raw payloads/request
bodies, throwables, ciphertext, nonces/tags, and key material are prohibited
anywhere in the canonical body, not only evidence or projections. Registered
control evidence is positive; unknown-member and secret-bearing fixtures fail.

`event` is exactly `{type,version,category,catalogHash}`. AuditEventCatalog v1
is a closed artifact with exactly `schemaVersion`, `kind`, `catalogId`,
`catalogVersion`, `owner`, `events`, and derived `catalogHash`. Event
definitions contain exactly `type`, `version`, `evidenceVersion`, `category`,
`actionTypes`, `semanticTuples`, `evidenceFields`, `maxEvidenceBytes`, and
`delivery`.
`evidenceVersion` is a positive integer. `actionTypes` has at most 32 unique
entries. `semanticTuples` has 1..32 unique exact objects
`{actionType,phase,resourceType,outcomeStatus,outcomeCode,policyDecision,
policyReasonCode}`. Every tuple value is non-null and non-empty, NFC-normalized
UTF-8 <=128 bytes with no controls, and resolves to its v1 action registry,
phase/status/decision enum, or bounded resource/outcome/reason code; wildcard
and null tuple values are forbidden.
The distinct tuple actionType set equals actionTypes, and the record's action,
phase, resource, outcome, and policy fields must exactly match one tuple.
`evidenceFields` has at most 32 closed descriptors, each exactly
`{name,type,required,nullable,maxUtf8Bytes,classification}`. Types are exactly
`string|integer|boolean`; string caps are positive and at most 256 UTF-8
bytes, while integer/boolean caps are `null`; string classification is exactly
`safe_code|opaque_id`, integer/boolean classification is exactly `null`, and
descriptor name `version` is reserved and rejected. `safe_code` is NFC,
lowercase ASCII matching `^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$` and <=256 bytes;
`opaque_id` is exactly `oid:v1:<64 lowercase hex>` emitted by a typed
domain/keyed mapper, never an arbitrary raw token. The verifier checks wire
category only and cannot prove origin or secret absence. Typed writers reject
or derive raw credentials/tokens; Application review owns semantic mapping,
and syntax validation alone is not secret detection. `maxEvidenceBytes` is positive
and at most 8192. Missing/unknown classification, non-null integer/boolean
classification, bad safe-code grammar, and raw opaque-token fixtures fail,
while valid safe-code and typed-derived-opaque fixtures pass. A catalog has at most 256 events and an unsigned JCS body
of at most 1,048,576 bytes. Evidence is flat, bounded, safe primitive data;
unknown, nested, list, object, float, and non-finite values fail closed.
`event.type` and every action type are non-empty normalized UTF-8 strings of
at most 128 bytes with no control characters; `category` is exactly the
AuditRecord category enum. The record `event.category` must equal the
definition category, `action.type` must be one of its `actionTypes`, and
`delivery` is exactly `required|durable`. `source.owner` must equal the catalog `owner`. The definition's `delivery` is
the sole writer delivery mode: P23-003C selects it before mutation/response
and cannot override it; a different mode requires a new event
`version`/`evidenceVersion`. Offline verification resolves delivery from the
catalog and requires record `policy.auditDelivery` to equal that definition's
`delivery`. The record `evidence.version` must equal the selected definition's
`evidenceVersion`. The domain-separated formula is
`catalogHash = HEX_LOWER(SHA-256(ENC_STRING("blackops.audit.catalog.v1") ||
ENC_BYTES(JCS(unsignedAuditEventCatalogBody))))`, where D147 defines both
typed encodings and the exact unsigned body. The immutable event selector is
`(catalogHash,event.type,event.version,evidence.version)`; within a catalog
`(type,version,evidenceVersion)` is unique. Same selector/same bytes is
idempotent, while a same-selector schema change or duplicate definition fails
closed. P23-003A1 owns Catalog-only RFC 8785 JCS and `catalogHash`;
P23-003B owns AuditRecord logical JCS and record/chain hashes. P23-003A owns
the catalog/schema and safe projection, P23-003E owns
application registration and projector authoring, P23-003F verifies
catalog/hash artifacts, and P23-003G binds one exact event-catalog file for
each distinct catalogHash referenced by covered records. Mixed catalog hashes
are valid when each record resolves and validates against its own catalog.

Phase changes append a new record; records are never updated in place. The
same `recordId` with the same `contentHash` is idempotent. The same ID with a
different content hash is an integrity failure.

### 13. Protection and tenant authorization

The canonical body uses a new BOPD purpose, `AuditRecord`. Both canonical and
outbox protected bodies are binary BOPD v1 in the exact Spec 99 layout (magic,
version, algorithm, key-ID length/bytes, 24-byte nonce, ciphertext length/
bytes, 16-byte tag); purpose and AAD are external, not stored BOPD object
members. Canonical plaintext is complete AuditRecord JCS+LF and outbox
plaintext is RFC 8785 JCS of `AuditIngressEnvelope v1` with no LF. Durable
outbox records use the independent BOPD purpose `AuditOutboxRecord`; its
custom length-prefixed AAD binds schema/version, envelope version/algorithm,
exact key ID, recordId, partitionId, tenant union, ingressOrdinal,
idempotencyKeyHash, and contentHash. Clear
outbox storage is limited to bounded indexes and the logical body is
ciphertext. Relay role, tenant, and AAD are verified before decrypt; missing
keys and cross-tenant bindings fail closed. Retry reuses the same ciphertext;
v1 prohibits AuditOutboxRecord protected-body re-encryption/rotation; key
retention must preserve old-key decryptability until a future accepted
terminal disposal policy.
Clear canonical storage is
limited to bounded indexes required for authorized query and chain ordering;
blind indexes are used only when an explicit query contract requires them.
Authorization and tenant predicates run before decryption. A cross-tenant
read is denied indistinguishably from an unknown record. Secrets, raw payload,
raw credentials, and throwable details are normatively prohibited from
canonical evidence and projections; the grammar/verifier cannot detect a
semantically secret value in an otherwise valid string. Typed source mappers
reject raw credentials/tokens automatically, while Application mapper/review
owns semantic mapping. This is not an automatic secret-absence or compliance
claim.

Canonical `AuditStoredRecord` AAD is byte-exact LP encoding:
`LP(x)=U32BE(byte_length(x))||x`; concatenate
`LP("blackops.audit.record.aad.v1")||LP("AuditStoredRecord")||LP("1")||
LP("1")||LP("1")||LP(keyId)||LP(recordId)||LP(partitionId)||
LP(tenantUnionBytes)||LP(segmentId)||LP(canonicalDecimal(sequence))||
LP(previousHashUnionBytes)||LP(DECODE32(contentHash))||LP(DECODE32(recordHash))||
LP(recordedAt)||LP(eventCategory)`. Tenant null is `0x00`, otherwise
`0x01||LP(type)||LP(id)`; previous null is `0x00`, otherwise
`0x01||DECODE32(hash)`. The system/genesis golden (`k1`,`r1`,system:global,
null tenant,`s1`,1,null,0×32,1×32,2026-08-19T00:00:00.000000Z,control) is
232 bytes with SHA-256
`5930076af5418572a4e575943172884775428ad10d024de7eb9fc419bce04b1c`; the
tenant/non-genesis golden (`k2`,`r2`,tenant:+a×64,{account,tenant-a},`s2`,2,
2×32,3×32,4×32,2026-08-19T00:00:01.000000Z,access) is 344 bytes with SHA-256
`f3ef3d96598964f7087ffe654ecde4d2e828caebfa981ecb1daa82859ff7c57f`.
Every field substitution is a negative fixture.

The durable-only `AuditIngressEnvelope v1` is not stored on the required path:
required delivery passes the same logical AuditRecord directly to the
canonical writer. The exact durable row is the closed `AuditOutboxRecord v1`
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody,version}`. Its state is
`pending|leased|relayed|poisoned`, with attempt `0` at ingress, +1 per fenced
claim, version genesis `1` and exact +1 per mutation, state-specific lease
nullability, `pending->leased`, fenced transient `leased->pending`, and
`leased->relayed|poisoned`; every mutation requires the expected current
version and, where a lease exists, its matching active lease token. Stale,
wrong-token, expired-owner terminal
settlement, version-gap, and double-terminal writes make no mutation. Only state-machine
fields mutate by fenced CAS and identity/bindings/protected bytes remain
immutable.
Terminal rows are retained. `protectedBody` is the binary BOPD v1 ciphertext
of the logical envelope. Its exact AAD is four-byte-big-endian length-prefixed bytes
for domain `blackops.audit.outbox.aad.v1`, purpose `AuditOutboxRecord`,
schema/version, recordId, partitionId, `LP(TENANT(tenantBinding))`,
canonical-decimal ingressOrdinal, decoded idempotencyKeyHash, and decoded
contentHash. `TENANT(null)=0x00`; for `{type,id}` it is exactly
`0x01||LP(UTF8(NFC(type)))||LP(UTF8(NFC(id)))`. The outer LP is U32BE length
plus exact bytes, with exact type/id bounds and partition/equality binding. It does
not inherit `CanonicalAssociatedData`; P23-003B owns the row/AAD and P23-003C
owns relay. Idempotency is unique by `(partitionId,idempotencyKeyHash)`;
same key plus same recordId/contentHash/logical bytes returns the same row and
ordinal, while any differing value fails `idempotency_conflict` before
mutation. Ordinals start at 1 under per-partition lock/CAS with no committed
gap. Relay claims only the minimum unacked head, cannot bypass ordinal 1 with
ordinal 2, while a poisoned position is acknowledged for FIFO/DLQ purposes
and does not block a later ordinal. Lease/fencing rejects
stale ack; canonical append is idempotent by recordId/contentHash and a crash
after append/before ack obtains the same sequence/hash. Ordinary retry uses
the same ciphertext. v1 prohibits protected-body re-encryption/rotation; key
retention preserves decryptability until a future accepted disposal policy.
Same/different-key, 2-before-1, parallel,
append/ack-crash, lease-fence, poison, AAD row/tenant swap, and cap+1 fixtures
are mandatory. The exact BOPD overhead is `52 + keyIdUtf8Bytes`; allocation
requires plaintext length plus that overhead to remain within the bounded
logical-cap envelope. `tenantBinding` is `null|{type,id}`, equals the logical
tenant, and derives the canonical partition. The exact idempotency digest is
`H(ENC("blackops.audit.outbox.idempotency.v1")||ENC(partitionId)||
ENC(NFC(idempotencyKey)))`; the exact content digest is
`H(ENC("blackops.audit.content.v1")||ENC(canonicalLogicalBytes))`. The raw
key remains protected only.

The current Outbox state matrix fixes pending attempts to `0..maxAttempts-1`
and leased/terminal attempts to `1..maxAttempts`; a pending row at max and any
max+1 claim are invalid. A lease is active exactly when
`trustedNow < leaseExpiresAt` and expired exactly when
`trustedNow >= leaseExpiresAt`, including equality. One trusted transition
clock governs claim, retry, relay, poison, and recovery; expired recovery uses
the stored matching expired token plus expected version in one fenced CAS.
`expiry-1us`, equality, `expiry+1us`, stale token/version, relay-versus-
recovery, and max-attempt 1/32 fixtures belong to P23-003C.

### 14. Required and durable AuditRecord delivery

`required` means the AuditRecord append and the business mutation commit in
the same database transaction. `durable` means an Audit Outbox identity,
bindings, and protected envelope are written in the mutation transaction and
relayed at least once using the
same record ID and `contentHash` digest. For an authentication or protected
read decision with no business mutation, the dedicated local Audit
append/outbox transaction must commit before the response or protected-data
disclosure. If an allow event cannot append, the response is withheld and the
operation fails closed without data disclosure. If a deny or invalid event
cannot append, the result remains a bounded denial and never becomes an allow.
Canonical catalog delivery is exactly `required|durable`; `best_effort` is a
separate observed JSONL projection with no Audit Trail claim. Conditional event
families register durable and required definitions under separate selectors.
The versioned policy evaluator assigns required `policy.auditDelivery` before
effect or response: only explicit `required` selects the required selector and
only explicit `durable` selects the durable selector. Missing, omitted, or
unknown policy requirements fail closed; none is defaulted. The recorded
`policy.auditDelivery` must exactly equal the resolved catalog definition's
`delivery`; runtime override or downgrade is forbidden, and P23-003C verifies
the transactional selector path. Authentication success and invalid
definitions always set `durable`. Missing, omitted, unknown, and catalog-
mismatched `policy.auditDelivery` values are negative fixtures across required,
durable, missing, omitted, and unknown input paths. Evidence
field names are normalized-byte unique and `maxEvidenceBytes` measures the
UTF-8 byte length of RFC 8785 `JCS(evidence)` including `version`.
One minimum catalog may represent that family as the same `event.type` at
`version:1/evidenceVersion:1/delivery:durable` and
`version:2/evidenceVersion:2/delivery:required`; authentication success and
invalid remain durable-only. `best_effort` is only the separate observed JSONL
projection and never a canonical delivery selector.
No cross-database, external, or exactly-once atomicity claim is made.
Privileged state-changing required events fail closed when the append cannot
commit.

### 15. Tamper evidence and roles

The canonical chain is per tenant-or-system partition across concrete
segments. Sequence is partition-global: it starts at `1` only at partition
genesis and increments by exactly `1` across rotation; the first record of a
new segment is `closed.finalSequence+1` and its predecessor is
`closed.headHash`.
The exact `AuditRecord.integrity` fields are `hashSuite` exactly
`audit-sha256-jcs-v1`, `partitionId`, `segmentId`, positive `sequence`,
`previousRecordHash` (`null` only at partition genesis, otherwise 64 lowercase hex),
`contentHash` and `recordHash` (64 lowercase hex). Sequence starts at 1 and
increments by exactly 1. RFC 8785 JCS is required; duplicate keys,
non-finite/float values, >53-bit integers, invalid Unicode, and non-JCS input
fail. The first-record preimage uses 32 zero bytes. The exact unambiguous
encoding and non-circular formulas are:

```text
ENC(string) = "s:" + decimal_utf8_byte_length + ":" + UTF8(string)
ENC(integer) = "i:" + canonical_decimal_digits
ENC(null) = "n:"
ENC(bytes) = "b:" + decimal_byte_length + ":" + bytes
contentHash = H(ENC("blackops.audit.content.v1") || ENC(JCS(logicalBody)))
recordHash = H(ENC("blackops.audit.record.v1") || ENC(partitionId) ||
  ENC(segmentId) || ENC(sequence) || ENC(previousRecordHashPreimage) ||
  ENC(contentHashBytes) || ENC("audit-sha256-jcs-v1"))
```

`previousRecordHashPreimage` is exactly 32 zero bytes only at partition genesis, otherwise
the bytes decoded from the 64 lowercase hexadecimal `previousRecordHash`;
`contentHashBytes` is the decoded 32-byte `contentHash`. The record represents
the first `previousRecordHash` as JSON `null`, but the hash preimage is always
the specified bytes. Sealing never mutates a record.

The exact closed `AuditSegmentState v1` is
`{schemaVersion,kind,partitionId,segmentId,state,firstSequence,finalSequence,
headHash,predecessorRecordHash,nextSegmentId,version,closeFencingToken}`.
State is exactly `open|closed|sealed`; an open segment may have null
finalSequence/headHash only while empty, predecessor is null only at genesis,
and nextSegmentId/closeFencingToken are null. Close requires non-empty final
sequence/head, atomically sets nextSegmentId and closeFencingToken, and freezes
the boundary; sealed preserves it. Version is a positive CAS value. The new
open segment uses old finalSequence+1 and old headHash as predecessor. Empty
close, stale fence append, a second open segment, and rotate/checkpoint crash
fixtures are required. P23-003B owns rotate/fence state; P23-003F consumes the
closed state and performs deterministic checkpoint/seal CAS.

`AuditCheckpoint v1` is an independent immutable artifact with exactly
`schemaVersion`, `kind: audit_checkpoint`, `checkpointId`, `partitionId`,
`segmentId`, `firstSequence`, `lastSequence`, `recordCount`,
`firstRecordHash`, `chainHeadHash`, `checkpointHash`,
`previousCheckpointHash`, `sealedAt`, `signedAt`, `hashSuite`,
`signatureAlgorithm: ed25519-v1`, `signingKeyRef`, and `signature` fields.
All IDs are store-wide unique; sequences and counts are positive integers,
hashes are 64 lowercase hex, and signature is base64url without padding. A
checkpoint covers exactly one complete sealed concrete segment:
`firstSequence=1` only for partition genesis, otherwise prior finalSequence+1;
`recordCount=lastSequence-firstSequence+1`, and `chainHeadHash` is exactly
the last covered `recordHash`, never a Merkle root. The partition graph has no
gap, fork, overlap, duplicate segment, duplicate sequence, or duplicate
checkpoint ID. `previousCheckpointHash` is `null` only for the partition's
genesis checkpoint; every later concrete-segment checkpoint names the
immediately previous segment's checkpoint hash.

The checkpoint unsigned body contains every stored field except the derived
`checkpointHash` and the entire stored property named `signature`, including
`signedAt`. Its JCS bytes are
strict RFC 8785 bytes and reject duplicate keys, non-finite/floating values,
greater-than-53-bit integers, invalid Unicode, and non-JCS input. The exact
non-circular formulas are:

```text
checkpointHash = H(ENC("blackops.audit.checkpoint.hash.v1") ||
  ENC(JCS(unsignedCheckpointBody)))
signature = Ed25519.sign(ENC("blackops.audit.checkpoint.signature.v1") ||
  ENC(checkpointHashBytes), checkpointSigningKey)
```

`checkpointHashBytes` is the decoded 32-byte digest; signature decoding must
yield exactly 64 bytes and the pinned Ed25519 public key exactly 32 bytes.
Signer failure leaves stored state `closed` (an operational display may say
`closed_unsealed`). `signedAt` is the time
the checkpoint signature was produced. v1 requires schemaVersion-1
`AuditVerificationContext`, external expectedContextHash acceptance, trusted
`verifiedAt`, an active correctly role-bound key, and
`notBefore <= signedAt <= verifiedAt < notAfter`; an expired or revoked
checkpoint key fails regardless of self-declared `signedAt`.
Artifact time alone cannot establish historical acceptance. A future
separately versioned trusted timestamp/anchor-time proof may add that
capability; the current receipt cannot prove its own anchor-key time.

Checkpoint persistence is crash-safe: complete checkpoint bytes are persisted
before acknowledgement. The same `checkpointId` with identical complete bytes
and hash is idempotent success; the same ID with different bytes/hash, or a
second checkpoint for one segment, is an integrity failure. Crash-before-ack,
replay, and persist-before-ack fixtures are required.

`AuditAnchorReceipt v1` is an independent immutable artifact signed by a
separate anchor-role key. Its exact stored fields are `schemaVersion: 1`,
`kind: audit_anchor_receipt`, `anchorReceiptId`, `partitionId`, `segmentId`,
`checkpointId`, `checkpointHash`, `checkpointBytesHash`, `anchorReceiptHash`,
`anchorStoreRef`, `anchorObjectRef`, positive `anchorObjectVersion`,
`previousAnchorReceiptHash`, `anchoredAt`, `signedAt`,
`signatureAlgorithm: ed25519-v1`, `signingKeyRef`, and `signature`.
`checkpointBytesHash` is exactly the domain-separated SHA-256 of
`JCS(completeCheckpointIncludingSignature)` without the export-file LF:
`H(ENC("blackops.audit.anchor.checkpoint-bytes.v1") ||
ENC(JCS(completeCheckpointIncludingSignature)))`. The receipt unsigned body
excludes only the entire stored properties named `anchorReceiptHash` and
`signature`; its exact formulas are:

```text
anchorReceiptHash = H(ENC("blackops.audit.anchor.hash.v1") ||
  ENC(JCS(unsignedAnchorReceiptBody)))
signature = Ed25519.sign(ENC("blackops.audit.anchor.signature.v1") ||
  ENC(anchorReceiptHashBytes), anchorSigningKey)
```

Receipt IDs and hashes are store-wide unique; same ID plus same hash is
idempotent, while same ID plus a different hash is an integrity failure.
Within each exact `{partitionId, anchorStoreRef, anchorObjectRef}` chain,
version starts exactly at `1` and increments exactly `+1`; two stores sharing
an object reference are separate chains. Version N must name version N-1's
`previousAnchorReceiptHash` equal to the immediately preceding receipt hash.
There is no gap, skip, fork, or duplicate version; a fresh head never waives
an absent intermediate receipt, and checkpoint predecessor continuity must
agree with this anchor graph. Anchor version `1` has a null predecessor.
`anchoredAt` is external-store acceptance time and `signedAt` is receipt
signing time, both UTC microseconds. All modes require
`checkpoint.sealedAt <= checkpoint.signedAt <= receipt.anchoredAt <=
receipt.signedAt <= verifiedAt`; current additionally requires
`receipt.signedAt <= freshness.observedAt <= verifiedAt < validUntil`.
As-of has null freshness and no current/suffix claim; reverse,
boundary-minus-one, and one-microsecond-after fixtures reject.
A URL, ETag, or WORM assertion alone is not an offline-verifiable anchor. The
`AuditVerificationContext.freshnessSnapshot` is structurally nullable in either
mode and is the v1 current authority only when supplied for current mode. It
is closed `{ref,hash,observedAt,validUntil,heads}`; Stage 0 validates member
shape/hash/time and internal `observedAt < validUntil`, while Stage 6 checks
duplicate/order/exact manifest coverage. Each head is the
exact `{partitionId,anchorStoreRef,anchorObjectRef,anchorObjectVersion,
anchorReceiptHash}` authority. Every current end receipt must match its exact
triple/version/hash and satisfy `observedAt <= verifiedAt < validUntil`;
cross-store or stale bindings fail. Without that snapshot current verification
returns finite nonzero code `18` (`freshness_boundary_unavailable`);
`mode: as_of_checkpoint` may have null freshness, verifies the
authenticated included complete-segment/checkpoint graph and endpoint
snapshot, and never claims currentness or suffix completeness.
An old otherwise valid checkpoint/receipt rollback fixture must not verify as
current. Checkpoint, anchor, and export keys are role-separated; a bundle key
is never authority. No record or checkpoint is mutated to add a receipt.

The closed `AuditVerificationResult v1` has exactly top-level properties
`{schemaVersion,kind,mode,status,code,scope,exportId,manifestHash,verifiedAt,
contextHash,partitions}`. Every field remains present. Before a valid mode,
mode/scope and all four identifiers are null; invalid/missing mode returns
code `19`, `status:failed`, and `partitions:[]`. A valid mode derives a
non-null scope immediately. Invalid external context retains mode/scope but
leaves `verifiedAt`/`contextHash` null. After context acceptance those two
refs are non-null; exportId/manifestHash remain null until Stage 4 signature
authentication and remain null on Stage 4/code-11 signature failure; after
signature success they remain non-null even if a later global stage fails.
`partitions` contains each parsed manifest
partition exactly once, sorted by canonical unsigned-byte lexical UTF-8
`partitionId` comparison with no locale; each entry is closed with
exactly `partitionId`, `status`, `code`, `prefixComplete`, `suffixComplete`,
`startCheckpoint`, `endCheckpoint`, `startReceipt`, and `endReceipt`.
`mode` is `current|as_of_checkpoint`, `status` is `verified|failed`, and
`scope` is `current_suffix|checkpoint_snapshot`; code `0` is allowed only for
`verified`. Checkpoint references are closed
`{partitionId,checkpointId,checkpointHash,segmentId,firstSequence,lastSequence}`
and receipt references are always non-null closed objects
`{partitionId,anchorReceiptId,anchorReceiptHash,anchorStoreRef,anchorObjectRef,anchorObjectVersion}`
for every emitted partition entry, including a partition-local failure. A
each signed segment carries one complete `checkpoint` and one complete
`receipt`; result start refs come from the first included segment and end refs
from the last. Stage 1 rejects a missing, extra, wrong-type, or malformed
endpoint globally, and Stage 7
derives refs only from the authenticated manifest while cross-checking bodies.
A missing or malformed receipt body returns code `15` with the non-null
manifest refs and never invents a value.
verified/code-0 entry has all four references non-null and computed flags;
`prefixComplete` equals the authenticated start-boundary value (true only for
genesis), current `suffixComplete` becomes true only after Stage 7 head match,
and as-of `suffixComplete` is always false. A
partition-local failed entry has a nonzero code, both flags false, and the same
four non-null refs. Overall code 0 requires every entry verified; overall failure code is the
first nonzero entry in sorted partition order. If parsing fails before safe
partition inventory, `partitions:[]` and the manifest code are returned. A
current null/expired freshness is code 18, while as-of with non-null freshness
is code 16; one current code 18 fails the overall result even if other entries verify. An as-of success is the
distinct checkpoint snapshot scope. Required fixtures cover two valid
partitions, one invalid chain, one freshness-18 entry, and malformed input
before inventory. Context-invalid is global code `19 verification_context_invalid`
with `partitions:[]` and all result refs null; a context-valid but
unauthenticated manifest keeps only `verifiedAt`/`contextHash` non-null. A
manifest-authenticated partition-local failure retains all four result refs and
binds them to the exact export endpoint; a different-manifest substitution
fails.
Global precedence is the single fixed Stage 0-7 table: Stage 0 validates
invocation mode plus standalone bounded external context and expected-context
hash (code `19`; mode/scope null only for invalid mode, otherwise derived;
zero manifest/listed-file opens and `partitions:[]`); Stage 1 validates bounded
raw-manifest schema/JCS/count/version and declared lengths only (code `10|17`);
Stage 2 recomputes the parsed candidate self-hash, then compares its
`exportId`/`manifestHash` with the pinned expected manifest (mismatch code 16,
zero opens, refs null); Stage 3 validates the trusted key; Stage 4
verifies Ed25519 with zero listed-file opens; Stage 5, only after signature
success, checks root-confined actual lengths/sums/raw digests (code `13`);
Stage 6, after signature and file-digest validation, validates the authenticated
catalog and record-chain graph/hash/semantic binding first, then invocation
mode/coverage, and then freshness-head set. Its finite precedence is
catalog/graph mismatch code `14 authenticated_graph_invalid`, mode/coverage
mismatch code `16 mode_coverage_invalid`, then freshness boundary mismatch code
`18 freshness_boundary_unavailable`; all global Stage 6
failures retain non-null post-signature top references, return
`partitions:[]`, and never reopen listed files. Stage 7
validates only local checkpoint/anchor trust/chronology and compares each end
receipt to its corresponding authenticated head. A local trust/chronology
failure is code `15`; a per-partition head mismatch is local code `18` with
full refs. Stage 7 never emits global graph code 14, mode/coverage code 16,
catalog code 10, or the deprecated scope label; Stage 0 performs no manifest read or head
comparison.
Global stages 0-6 return `partitions:[]`; only Stage 7 emits entries.
Context-hash plus bad manifest returns `19`; extra head plus invalid signature
returns `11` with zero opens; extra head plus valid signature returns `18`
with empty partitions; invalid context shape returns `19`. No reverse order is
valid.

`AuditVerificationContext v1` binds trusted external state to the result with
exact top-level `{schemaVersion:1,kind:audit_verification_context,contextId,
verifiedAt,trustStoreSnapshot,freshnessSnapshot,expectedManifest,contextHash}`.
`expectedManifest` is closed `{exportId,manifestHash,purpose}` with purpose
exactly `audit_export_verification`, bounded IDs, and 64-hex hash; it is in
contextHash and pinned by expectedContextHash. Verifier
invocation requires external `expectedContextHash` (64 lowercase hex) from a
trusted configuration/channel; it cannot be obtained from the bundle or the
context. Recompute the complete context and constant-time compare its stored
hash, then compare with expectedContextHash, before using trust. Missing,
mismatched, or substituted context is global code `19
verification_context_invalid`, with null result refs and `partitions:[]`.
Trust snapshot
is exactly `{ref,hash,observedAt,validUntil,entries}`. Entries are unique and
sorted by unsigned UTF-8 `keyRef`; each is exactly
`{keyRef,role,publicKey,publicKeyFingerprint,notBefore,notAfter,revokedAt,status}` with role
`checkpoint|anchor|export`, base64url-unpadded 32-byte public key,
64-lowercase-hex fingerprint `SHA-256(decoded publicKey)`, UTC times, and
status `active|revoked|expired`. Fingerprints are unique snapshot-wide; the
same material cannot alias across key references or roles and revocation is
material-level. Its semantic hash excludes only the
stored property named `hash`:
`H(ENC("blackops.audit.trust-store-snapshot.v1") ||
ENC(JCS(unsignedTrustStoreSnapshot)))`. Freshness is structurally nullable in
either mode; when non-null it is exactly `{ref,hash,observedAt,validUntil,heads}`
with individual head shape/hash validated at Stage 0. Duplicate/order/exact
coverage (one head per manifest partition, unique partition IDs, canonical
unsigned-byte sort) is the final Stage 6 freshness lane, code `18
freshness_boundary_unavailable` after catalog/graph `14` and mode/coverage
`16`. Heads are exactly
`{partitionId,anchorStoreRef,anchorObjectRef,anchorObjectVersion,anchorReceiptHash}`.
Its semantic hash excludes only its stored `hash` property and uses the freshness domain. The context hash excludes
only its stored `contextHash` property and uses the verification-context
domain. Stage 0 requires only internal `observedAt < validUntil`; current
additionally requires `observedAt <= verifiedAt < validUntil` at Stage 6/7.
These are non-circular: each unsigned JCS body excludes only its own stored
hash property; the freshness formula is
`H(ENC("blackops.audit.freshness-snapshot.v1") ||
ENC(JCS(unsignedFreshnessSnapshot)))`, and the context formula is
`H(ENC("blackops.audit.verification-context.v1") ||
ENC(JCS(unsignedContext)))`.
Accepted keys have status `active`, `revokedAt:null`, and
`notBefore <= signedAt <= verifiedAt < notAfter`; `revoked` requires non-null
`revokedAt <= observedAt` and rejects, while `expired` requires
`revokedAt:null` and `notAfter <= verifiedAt` and rejects. Malformed or
internally inconsistent context/trust/freshness shape/hash/time-order is Stage
0 code `19`; export-key availability/role/status is Stage 3 global code `12`;
authenticated signedAt key-window failure is Stage 4 global code `12` with
non-null export refs; checkpoint/anchor key/signature/chronology is Stage 7
local code `15`. Missing/expired/stale current freshness is Stage 6/7 code
`18`; non-null as-of freshness is code `16`. Current null/expired freshness is
code `18`, and each current end receipt must equal the trusted head
triple/version/hash; stale heads cannot claim current/suffix. Result binds
`verifiedAt` and `contextHash` exactly;
context/snapshot hash substitution, duplicate/unsorted heads, and cross-store
mismatch are negative fixtures.

The context and snapshots are closed and bounded: normalized safe context,
snapshot, and head IDs/refs are non-empty and <=256 UTF-8 bytes; the unsigned
context JCS is <=1 MiB; the trust snapshot has 1..256 unique entries and a
freshness snapshot has 1..256 unique canonical-byte-sorted heads. Every key or
head version is positive and every semantic hash is 64 lowercase hex. Hashes
are recomputed before use, and duplicate, wrong-sort, missing-current-
freshness, context/snapshot substitution, and cross-store-head fixtures fail.

Writers append only. Read, export, signing, and retention roles are separate.
Hashing or AEAD alone is not tamper-proof storage and is not non-repudiation.

### 16. Query, export, and retention controls

Queries are default-deny, tenant-scoped, bounded, and authorization-first.
The following is the current wire handoff for this active base section; it
defines no second implementation. The sole current query wire is
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
finite `AuditQueryError v1` wire above before lookup or decrypt. Its total
precedence is request cap/shape, trusted authorization provenance and replay,
partition/query-scope, raw cursor cap/grammar/digest, key resolution/role/HMAC/
payload/window, authenticated scope/head/position/request-selection and cursor
replay, then store selection; bad HMAC wins over authenticated payload time or
window failures. P23-003G owns malformed+deny, wrong-role+bad-HMAC,
bad-HMAC+not-yet/expired, and cursor-replay compound fixtures with zero later
read/decrypt. Stage 2 is total: invalid trusted provenance, deny, unavailable,
requestRef mismatch, stale, future, and nonce replay map respectively to
`authorization_provenance_invalid|authorization_denied|
authorization_unavailable|request_ref_mismatch|stale_request|future_request|
nonce_replay`; Stage 3 maps every partition, tenant, or query-scope predicate
failure to `scope_mismatch`. A `nonce_replay+cursor_replay` compound emits only
the earlier Stage 2 code. Provenance-invalid, requestRef-mismatch, and
first/continuation partition-substitution fixtures are owned by P23-003G.
Valid store
failure and legal record-cap overflow remain `AuditQueryResult.unavailable`
with `reasonCode=resource_unavailable|record_too_large`.
The current durable outbox row is
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody,version}` with only
`pending|leased|relayed|poisoned`; pending attempt is `0..maxAttempts-1`,
leased/relayed/poisoned attempt is `1..maxAttempts`, and a claim maps
`n -> n+1`; persisted or decoded pending at max is invalid. Every mutation is matching-token and
expected-version CAS with exact +1 version. Its active hashes remain
`H(ENC("blackops.audit.outbox.idempotency.v1")||ENC(partitionId)||
ENC(NFC(idempotencyKey)))` and
`H(ENC("blackops.audit.content.v1")||ENC(canonicalLogicalBytes))`.
Pending is claimable only below maxAttempts and after availableAt. For every
claim, retry, relay, poison, and recovery, one trusted transition clock is
captured. A lease is active exactly when `trustedNow < leaseExpiresAt` and
expired exactly when `trustedNow >= leaseExpiresAt`, including equality;
expired recovery requires the stored matching expired token plus expected
version in one fenced CAS. `expiry-1us`, equality, `expiry+1us`, stale token,
stale version, relay-versus-recovery, and max-attempt 1/32 fixtures are owned
by P23-003C.
AnchorIntent has exactly
`prepared|externally_accepted|externally_rejected|receipt_inputs_persisted|
receipt_persisted|acknowledged`; its public receipt is the active §9.6.1
`AuditAnchorReceipt v1`, not a second wire. Retention has the exact metadata
wire and snapshot fields in the Round29 block; `policy` is null only for
`retained/policy_unavailable` and otherwise is the byte-exact available
snapshot policy, while every state snapshot ref, version, hash, and evaluation
fence equals its authenticated snapshot. It has states
`retained|eligible_for_future_deletion`, and reason codes
`retention_not_elapsed|active_hold|policy_unavailable|
retention_elapsed_no_active_hold`. Revision/version 1 uses the same four-branch
truth table, not an unconditional retained result; one trusted `evaluatedAt` is
captured and every snapshot `capturedAt` is <= it, while future snapshot time or
evaluation-clock rollback creates no revision. Older summaries in this active section are
descriptive only and cannot define another current wire. Anchor genesis is
`version=1,previousStateHash=null`; every claim/result/reclaim and persistence
boundary is expected-version plus expected-stateHash and matching-token CAS
with exact +1. Prepared claims increment attempt once; accepted, rejected, and
unavailable persist that attempt. An explicit absent reconciliation at attempt
31 in `post_call_uncertain` returns `prepared/pre_call` with
`preCallClaimMode=new_attempt`; the next claim creates attempt 32. At attempt
32, an explicit absent lookup in `pre_call` records pre-call absence and permits
exactly one `external_call_start`, which durably enters `post_call_uncertain`
before the final external call. Only an explicit absent reconciliation at
attempt 32 in `post_call_uncertain` applies the fenced +1 transition to
`externally_rejected/attempt_exhausted`. Pre-call `pending`, lookup
`unavailable`, and `found` remain their phase-specific nonterminal branches;
post-call `found` recovers the accepted object with one fenced +1 and no N+1,
while post-call `unavailable` clears the lease into reconciliation-only
prepared and never permits a new external call. Pre-persist and post-persist
response loss use these same outcomes before any new external side effect.
Expired attempt32 prepared leases use the same branches. Receipt and
acknowledgement claims preserve attempt; terminal states are unclaimable,
stale token/version/stateHash produces no mutation, and no branch creates
attempt 33 or N+1.
The prior service-action summary is retained only as non-normative historical
description. It defines no wire, requestRef preimage, decision token, or query
fixture; the target-bearing query context above is the only current
AccessContext contract.
The catalog owner
P23-003A defines exactly
`audit.access.{query,export,sign,verify}.requested|unavailable|accessed`.
P23-003F owns checkpoint/anchor signing and reusable verification primitives;
ordinary service-managed signing emits required
`audit.access.sign.accessed` before releasing a signature. The exact
framework-controlled target `system:global/audit-control-v1` is a finite
control close: its signed checkpoint/receipt is authoritative and emits no
`audit.access.sign.accessed`; the target and framework role derive that
exception and callers cannot select it. P23-003G owns query/export/hosted-
verify orchestration, expectedManifest pinning, and the corresponding
`accessed` event before releasing a result. `requested` and `unavailable` are
durable; `accessed` is required and durably persisted. Any required append
failure withholds the response, export, signature, or hosted verification
result. Offline verification has no canonical append or access-event claim.
Internal append is depth-one and does not recursively emit another access
event.

`AuditReadRequestBasis v1` is the closed pre-decision operation-read object
`{purpose,actor,execution,tenant,requestedAt,nonce}`. Purpose is exactly
`status|journal|outcome|diagnostics`; actor is null or a closed `{type,id}`
object, execution is a required closed `{type,id}`, tenant is null or the
bounded `{type,id}` tenant. Each typed-reference member is valid UTF-8, NFC,
free of `Cc|Cf`, and 1..256 UTF-8 bytes inclusive, without trim, case-fold,
silent normalization, UUID, or path grammar. requestedAt is UTC with exactly six fractional
digits, and nonce is a 1..128-byte NFC/no-control noncredential string. Its
requestRef is
exactly `"oid:v1:" || HEX_LOWER(SHA-256(LP("blackops.audit.read.request-ref.v1") ||
LP(JCS(requestBasis))))`. The LP bytes are the
SHA-256 preimage, never the final value, and no target data is included. The
golden is JCS `150` bytes, LP preimage `192` bytes, and
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
A null/present matrix, Unicode/substitution/cap+1 fixture, algorithm-
substitution negative, and independent Node/Docker-PHP golden are required.

After the trusted decision, `AuditReadContext v1` is exactly
`{schemaVersion:1,kind:audit_read_context,purpose,actor,execution,tenant,
requestedAt,nonce,requestRef,authorizationDecision}`. Its basis fields/ref are
byte-identical to the validated basis/derived ref; decision is
`allow|deny|resource_unavailable`, and caller input supplies neither decision
nor ref. D/G own one-request 16-byte CSPRNG nonce capture and lowercase-hex
encoding; the pure A1 codec vector may retain `nonce-1`.

For Status, Journal, Outcome, and Diagnostics reads, the framework catalog
owns exactly `access.operation_{status,journal,outcome,diagnostics}.
requested|unavailable|accessed`. Delivery is `required` for all three outcomes
and each request emits `.requested` before authorization/lookup plus exactly
one terminal `.accessed|.unavailable`; both required appends are durably
persisted before a response or failure is released. Absent and cross-tenant are
indistinguishable `.unavailable` with `action.phase=unknown`,
`outcome.status=unknown`, `outcome.code=resource_unavailable`,
`policy.decision=not_applicable`, `policy.reasonCode=resource_unavailable`,
and `AuditReadContext.authorizationDecision=resource_unavailable`. No
`resource_not_found` or legacy `.allow`/`.deny` read event is valid in the
current contract and no existence claim is emitted.

`AuditExportManifest v1` is signed and covers only complete sealed segments
(`coverageMode: complete_sealed_segments`). Its exact graph includes export
purpose, `filter: null`, required non-empty `selectionReason`, distinct-partition `startBoundaries`,
complete partition/segment ranges, `recordCount`, `chainHeadHash`,
`checkpointHash`, `checkpointBytesHash`, `anchorReceiptHash`, checkpoint/
receipt references, files, actor/time/source, `signatureAlgorithm`,
`signingKeyRef`, `signedAt`, `manifestHash`, and `signature`. Segments are
ordered by each partition's `previousCheckpointHash` chain, never opaque
`segmentId` lexical order. Each file has role
`records|checkpoint|anchor_receipt|event_catalog`, unique root-relative POSIX
UTF-8 path with no absolute path, backslash, `.`, `..`, or empty component,
artifact ID,
partition/segment where applicable, record count for records, media type, byte
length positive, and raw-file SHA-256 digest. Every artifact/file is listed exactly
once; duplicates, extras, missing entries, absolute paths, backslashes, `.`,
and `..` segments fail. Every signed segment entry carries one complete
authenticated `checkpoint` and one complete authenticated `receipt`. A
partition result derives its four start/end fields from the first/last segment,
so a one-segment partition has byte-identical endpoints. The manifest has no
`as_of` property; invocation mode is external and an unknown member is
rejected. Its count/hash preimage includes the singular segment objects.
Artifact identity is only the authenticated signed-manifest `{role,artifactId}`
mapping plus the exact raw digest; basename or role-prefix identity is not
claimed. Files are sorted by unsigned path bytes and root-relative no-follow
resolution rejects symlink/special/race substitutions.
The parser first bounds the unsigned manifest body to <=15 MiB and the
complete stored raw JCS plus LF to <=16 MiB before allocation/read/streaming,
then preflights: partitions 1..256;
segments 1..4096 total and >=1/partition;
exactly one start boundary/partition; catalogs 1..1024; files exactly
`3*segments+catalogs` and <=13,312; records/segment 1..100,000; total records
1..10,000,000; each byteLength 1..1 GiB; unsigned JSON-safe overflow-checked
sum <=16 GiB and equal actual bytes. Each complete canonical AuditRecord JCS
line including LF is <=1 MiB raw bytes before writer append/commit or JSON
allocation; checkpoint and anchor receipt raw files are each <=1 MiB,
event-catalog raw files including LF are <=2 MiB while the unsigned catalog
body is <=1 MiB, and the records file remains <=1 GiB. Every cap is inclusive
and cap+1 fails by bounded-line streaming. Relative POSIX paths are <=512
UTF-8 bytes, components <=128 bytes with no controls, no absolute path, no
backslash, no `.`, no `..`, and no empty component. The resolver is root-relative and
no-follow: every component/final entry rejects symlinks, magic links,
directories, FIFOs, sockets, devices, and other special files. It uses
`openat2` with `RESOLVE_BENEATH|RESOLVE_NO_SYMLINKS|RESOLVE_NO_MAGICLINKS`, or
an equivalent component-by-component nofollow walk, then `fstat`s a regular
file and uses that same descriptor for size, digest, and parse. A pre-open/
check-to-open namespace replacement is rejected with zero outside-root reads.
After final regular-file descriptor acquisition, namespace replacement cannot
change bytes read through that descriptor; the result is based on that same
fd/digest, without promising to detect every later rename. Outside-symlink,
symlink-directory, pre-open swap, post-open-fd stability, FIFO/device, and
zero-outside-read fixtures are required;
IDs/refs/purpose/selection/media/component/build are
normalized safe strings <=256 bytes. Actor/time/source are closed exact
`{principal,execution}`, `{exportedAt}`, and `{component,runtime,buildId}`.
Zero, cap, cap+1, overflow, oversize, actual-byte mismatch, and closed-object
negatives fail before streaming.
For every distinct `catalogHash` referenced by covered records, exactly one
`event_catalog` file is required, identified by that semantic hash; missing,
extra, unreferenced, duplicate, or unresolved catalog files fail. Mixed
catalog hashes across builds or catalog versions are valid when each record
validates against its own catalog and matching `evidenceVersion`.
Fixtures include mixed two-catalog positive coverage with all category/action/
owner/delivery semantics resolved per record, missing/extra/
unreferenced/duplicate catalog negatives, same-selector redefinition, and
`evidence.version`, category, action, owner, delivery, control-character, and
over-cap string mismatches.

Each `startBoundaries` entry is one of two closed forms with the same exact
eight keys: `partitionId`, `mode`, `anchorStoreRef`, `anchorObjectRef`,
`previousCheckpointHash`, `previousAnchorReceiptHash`,
`previousAnchorObjectVersion`, and `prefixComplete`. Genesis requires
non-empty store/object references equal to the first included receipt, null
predecessor hashes/version, receipt version `1` with a null predecessor, and
`prefixComplete:true`. `selected_range` requires non-empty references equal to
the first included receipt, 64-hex predecessor hashes, positive predecessor
version, receipt version `previousAnchorObjectVersion + 1`, and
`prefixComplete:false`. It declares unresolved predecessor values and
verifies only the included checkpoint/receipt chain from its first artifact;
it does not prove predecessor existence, continuity from that predecessor,
prefix or pre-range completeness, deletion legitimacy, legal hold, or
physical deletion. Missing start, fork, gap, overlap, wrong chain order, or
missing boundary object/hash/version fails.

Physical deletion, sealed-byte/key destruction, signed deletion manifests, and
their boundary/legal-hold claims are absent from AuditExportManifest v1. Audit
v1 retains sealed bytes and keys and makes no compliance claim. A separately
reviewed future `P23-003H` owns that contract; v1 rejects deletion fields.

P23-003G owns metadata-only `AuditRetentionState v1`, a closed object
`{schemaVersion:1,kind:"audit_retention_state",stateId,target,policy,evaluator,
owner,evaluatedAt,policySnapshotRef,policySnapshotHash,policySnapshotVersion,
holdSnapshotRef,holdSnapshotHash,holdSnapshotVersion,evaluationFenceVersion,
result,reasonCode,state,version,previousStateHash,stateHash,updatedAt,
idempotencyKeyHash}`. `target` is
`{partitionId,startSequence,endSequence,eventCategory|null}`; `policy` is
`null|{id,version,retentionUntil}`, null only when
`state=retained`, `result=retained`, and `reasonCode=policy_unavailable`, and
otherwise byte-exactly equals the available authenticated policy snapshot;
`state` and `result` are exactly
`retained|eligible_for_future_deletion`, with reason codes
`retention_not_elapsed|active_hold|policy_unavailable|
retention_elapsed_no_active_hold`. The authenticated policy snapshot is the
closed `{kind:"policy"|"policy_unavailable",ref,version,target,policy,
capturedAt,evaluationFenceVersion}` wire; unavailable uses the explicit tag and
`policy:null`. The authenticated hold snapshot is the closed
`{kind:"active_hold_set",ref,version,target,holds,capturedAt,
evaluationFenceVersion}` wire, with canonical active holds or exactly
`holds:[]`. Their hashes are exactly
`H(ENC("blackops.audit.retention.policy-snapshot.v1")||ENC(JCS(policySnapshot)))`
and
`H(ENC("blackops.audit.retention.hold-snapshot.v1")||ENC(JCS(holdSnapshot)))`;
each preimage binds the full target, version, fence, capturedAt, policy or
complete hold set. The state policy/hold snapshot ref, version, and hash fields
equal the authenticated snapshot values exactly, and
`evaluationFenceVersion` equals the authenticated fence in both snapshots.
Member order is the order shown; refs are 1..256-byte
NFC/no-control strings, versions/fence are positive JSON-safe integers, and
every revision advances `evaluationFenceVersion` exactly +1. There is no
embedded `hold` field,
`held` state, or hold transition. Bounds are the exact AuditRecord bounds,
times are UTC six-fraction, sequences are positive with start <= end, and
version is positive. `(stateId,target)` is immutable identity.
The truth table is policy-unavailable -> `retained/policy_unavailable`;
available policy plus overlapping active hold -> `retained/active_hold`; no
hold with `evaluatedAt < retentionUntil` -> `retained/retention_not_elapsed`;
and no hold with `evaluatedAt >= retentionUntil` ->
`eligible_for_future_deletion/retention_elapsed_no_active_hold`, including the
equality boundary. `evaluatedAt` never decreases. An eligible state reverses
only for a strictly newer extending policy, a strictly newer overlapping hold,
or a newly authenticated policy-unavailable snapshot whose policy snapshot
version is strictly greater than the prior version; same-version
available-to-unavailable is a fork and is rejected. Equal snapshots/time alone
never reverse it. Stale/decreased versions, fence gaps, forks, and clock
rollback create no revision.
`previousStateHash` is null iff version 1 and otherwise the prior state hash;
`stateHash=H(ENC("blackops.audit.retention-state.v1")||
ENC(JCS(object excluding stateHash)))`. The raw idempotency key is protected;
its clear hash is
`H(ENC("blackops.audit.retention-idempotency.v1")||ENC(partitionId)||
ENC(rawKey))`. Same key and bytes is idempotent, same key and different bytes
is a conflict. `AuditHold v1` is the separate append-only revision object
defined above; overlap, release, and same-snapshot eligibility use its active
holds. No retention state deletes bytes/keys or changes canonical query/export
visibility. P23-003H future physical deletion is not a P23-006 dependency.

The separate `AuditHold v1` collection has exactly one closed revision shape:
`{schemaVersion:1,kind:"audit_hold",holdId,target,reasonCode,state,version,
previousStateHash,stateHash,updatedAt,updatedBy,idempotencyKeyHash}`. Its
immutable target is exactly
`{partitionId,startSequence,endSequence,eventCategory|null}`; `state` is
`active|released`, only `active -> released` is valid, and each later revision
has `version=previous.version+1` plus the exact previous `stateHash`.
`stateHash=H(ENC("blackops.audit.hold-state.v1")||
ENC(JCS(object excluding stateHash)))`; it includes every other stored member.
The raw idempotency key is never stored and its hash is exactly
`idempotencyKeyHash=H(ENC("blackops.audit.hold-idempotency.v1")||
ENC(holdId)||ENC(rawKey))`. The raw key is 1..256 UTF-8 bytes, NFC, and has no
controls; same key and same body is idempotent, while same key and different
body is a conflict. `(holdId,target)` is immutable CAS identity, the complete
revision JCS cap is 64 KiB inclusive, `updatedBy` is a bounded `{type,id}`
actor and `updatedAt` is trusted UTC with six fractional digits. Overlapping
holds, `eventCategory:null` wildcard matching, and same-transaction snapshot
eligibility remain; releasing one hold never releases another. Exact hash,
bound, CAS, overlap, wildcard, race, and cap+1 fixtures are required.

Only the stored properties `manifestHash` and `signature` are excluded from
the unsigned JCS body; signing metadata and `signedAt` are included. The exact
preimages are:

```text
manifestHash = H(ENC("blackops.audit.export.hash.v1") ||
  ENC(JCS(unsignedAuditExportManifest)))
signature = Ed25519.sign(ENC("blackops.audit.export.signature.v1") ||
  ENC(manifestHashBytes), exportSigningKey)
```

`manifestHashBytes` is the decoded 32-byte digest. `time.exportedAt` and
`signedAt` are UTC microsecond times with `exportedAt <= signedAt`; reverse
time order fails. `signature` is the entire stored property excluded from the
signature preimage, not merely a byte-range convention.
After strict bounds, recompute `manifestHash` and constant-time compare it,
resolve the trusted export key from the external context, and verify Ed25519
before opening any bundle file. Then open only root-confined paths and verify
raw digests, recompute each catalog semantic hash, and finally verify
partitions. Purpose-only, file-digest-only, catalog-body/raw-digest, and
stored-hash substitution are negative fixtures; invalid signature plus a
maximal graph opens zero files, while valid signature plus bad catalog reaches
the catalog stage.
An authorized records file is `AuditExportRecord v1`: complete canonical
AuditRecord objects including integrity, one RFC 8785 JCS object per line in
sequence order with LF, no BOM/CRLF, and one file per segment. Checkpoint and
receipt and event-catalog files are `JCS(object) || 0x0A`; raw-file SHA-256 and
semantic `checkpointBytesHash`/`anchorReceiptHash`/`catalogHash` are verified
independently. LF versus
CRLF substitution, semantic object changes, and raw-byte changes each fail the
corresponding wire/digest check; the raw file digest is never equated with a
semantic hash. Bundle confidentiality is separate; a filtered safe projection
is a distinct schema and never claims chain completeness. Body modification,
reassignment, extra/duplicate/missing/unlisted file entries, path traversal,
missing protected input/key, and bundle-key authority are explicit negatives.

For each checkpoint, receipt, or event-catalog file, `files[].sha256` is exactly
`SHA-256(JCS(storedArtifact) || 0x0A)` over the raw bytes. It is distinct from
the domain-separated `checkpointBytesHash` (complete checkpoint JCS without
LF) and `anchorReceiptHash` (domain plus unsigned receipt JCS); semantic hash
and raw-file digest values are never required to be equal.

For checkpoint, anchor, and export role trust, v1 fails closed when the key is
currently expired or revoked regardless of self-declared `signedAt`; no
historical backdating acceptance exists without a future separately-versioned
trusted timestamp/anchor proof contract. The verifier exposes finite nonzero code `18`
for unavailable freshness boundary, and rejects `time.exportedAt > signedAt`
or any reverse checkpoint/anchor time ordering.

The verifier uses one fixed Stage 0-7 order (no reverse or alternate order).
Stage 0 validates invocation mode plus a strict bounded external
`VerificationContext`, and `expectedContextHash` syntax/recompute/pin; failure
is code `19` with zero manifest/listed-file opens, no manifest freshness-head
comparison, and `partitions:[]`. Invalid mode leaves mode/scope and all four
identifiers null; a valid mode derives scope before context validation.
Stage 1 bounds the raw manifest and validates strict schema/JCS/count/version
and declared lengths only (code `10` or `17`). Stage 2 first recomputes and
validates the parsed candidate manifest self-hash, then constant-time compares
its signed `exportId` and `manifestHash` with the required pinned
`expectedManifest` (mismatch code `16`, zero listed-file opens, refs null);
Stage 3 resolves only the
trusted export key/role and current trust status at `verifiedAt` (code `12`),
never the untrusted manifest `signedAt`; Stage 4 verifies Ed25519 (code `11`,
with zero listed-file opens), then checks authenticated signedAt validity
before any file open; Stage 5, only after signature success, performs
root-confined actual byte length/sum/raw-digest checks (code `13`); Stage 6
checks the authenticated catalog and record graph/chain/hash/semantic graph
first as code `14 authenticated_graph_invalid`, then invocation mode/context/
coverage as `16 mode_coverage_invalid`, then freshness head shape/boundary as
`18 freshness_boundary_unavailable`. Global stages 0-6 return
`partitions:[]`; only Stage 7 emits entries, and every emitted local failure
has four non-null endpoint references. Stage 7 checks only local
checkpoint/anchor trust/chronology (`15`) and end-head freshness (`18`), never
catalog code 10, graph code 14, the deprecated scope label, or mode/coverage code 16. An invalid
signature plus bad actual bytes opens zero files and returns code `11` with
null export refs; a valid signature plus a bad actual length reaches code `13`
with non-null refs. Checkpoint/anchor key, signature, role, or chronology
failures found per authenticated partition map to local code `15`; graph is
global Stage 6 `14`, mode/coverage is global Stage 6 `16`, and per-partition
head freshness is local `18`. It exposes only finite safe codes (`0` verified;
`10` manifest invalid; `11` signature invalid; `12`
key unavailable; `13` digest mismatch; `14` authenticated graph invalid; `15`
checkpoint/anchor invalid; `16` mode/coverage invalid; `17` unsupported version; `18`
freshness boundary unavailable; `19` verification context invalid), with every
failure non-zero. Audit-of-audit access uses the `system:global` partition and
`audit-control-v1` stream family with rotated concrete segment IDs. Ordinary
service-managed signatures remain audited; only the framework-controlled
signature whose exact target is `system:global/audit-control-v1` and whose role
is derived by the framework closes the control stream without emitting
`audit.access.sign.accessed`. Callers cannot select that target/role pair, and
the signed checkpoint/receipt is authoritative evidence. Depth is bounded to
one and control events never recursively self-log. Event categories have independent retention and
legal hold. Physical deletion and signed deletion manifests are deferred to
P23-003H; v1 never deletes sealed bytes or keys, edits an existing record, or
rewrites a chain silently.

### 16.1 Historical Round 8 exact verifier and runtime-boundary corrections

Round 8 fixes the following exact boundaries across D145, Specifications 105
and 106, and the P23-001 management documents.

- `AuditIngressEnvelope v1` is the closed object
  `{schemaVersion,kind,logicalRecord,contentHash,recordedAt,ingressOrdinal,
  idempotencyKey,recordId,partitionId,tenantBinding}`. `kind` is exactly
  `audit_ingress_envelope`; `schemaVersion=1`; `logicalRecord` is exactly
  AuditRecord v1 without `integrity`, with fixed `recordedAt` and `contentHash`
  bindings; `contentHash` is 64 lowercase hex; `recordedAt` is an authoritative
  UTC timestamp with exactly six fractional microseconds;
  `ingressOrdinal` is a positive JSON-safe integer; `idempotencyKey` is
  non-empty NFC text, has no controls, and is at most 256 UTF-8 bytes; and
  `recordId`/`partitionId` match AuditRecord/partition bounds. This envelope is
  durable-only; required canonical delivery passes the same logical record
  directly to append and never stores it. Required canonical append and
  durable local outbox ingress stamp `recordedAt` once,
  using the per-authority/partition clamp `max(now,lastAssignedRecordedAt)`.
  Sequence is allocated only at canonical append and is the sole
  chain/order/pagination cursor; durable FIFO alone has monotonic
  `ingressOrdinal` and no cross-mode total order is claimed. Retry and relay
  preserve every field and logical byte.
- The independent BOPD purpose is `AuditOutboxRecord`. Its length-prefixed AAD
  binds schema/version, envelope version `1`, algorithm `1`, exact key ID,
  record ID, partition ID, tenant binding, ingress ordinal, idempotency-key
  hash, and content hash; indexes are clear but the
  logical body is ciphertext. Relay role/tenant/AAD is
  verified before decrypt, cross-tenant/key-missing fails closed, and ordinary
  retry reuses ciphertext. v1 prohibits protected-body re-encryption/rotation;
  key retention preserves decryptability until a future accepted disposal
  policy.
- The closed stored `AuditOutboxRecord v1` row is
  `{schemaVersion,kind,recordId,partitionId,tenantBinding,ingressOrdinal,
  idempotencyKeyHash,contentHash,state,attempt,availableAt,leaseFencingToken,
  leaseExpiresAt,protectedBody}` with states `pending|leased|relayed|poisoned`,
  fenced lease expiry, minimum-head FIFO/no bypass, idempotency conflict before
  mutation, and length-prefixed/domain-separated AAD. Its owner is P23-003B/C;
  it does not inherit `CanonicalAssociatedData`.
- Complete-record poison prevention constructs the synthetic maximum from the
  actual logical body and partition ID, maximum 256-byte UTF-8 segment ID,
  maximum JSON-safe sequence,
  64-hex previous/content/record hashes, and fixed hashSuite. JCS plus LF is
  <=1 MiB inclusive at ingress and relay; cap and cap+1 fixtures are required.
- All semantic tuple values, including resource type, outcome code, and policy
  reason code, are non-null/non-empty declared enum/code values with bounds;
  wildcard is absent from v1. Evidence strings are <=256 UTF-8 bytes with
  null/256/257 fixtures. String projections are typed as `safe_code|opaque_id`,
  integer/boolean classification is null, and mapper/review secret detection
  is not claimed to be automatic compliance.
- Framework definitions come from a sealed internal provider. Application
  owner is forced from trusted registry/build identity, not self-declared.
  The exact reserved framework namespaces are `security.`, `access.`,
  `retention.`, `storage.`, `observer.`, `outbox.`, `audit.`,
  `configuration.`, `control.`, `replay.`, and `rotation.`; collision fails
  closed. Each record validates its phase independently; action ID is
  correlation only and no cross-record phase transition/continuity/order is
  asserted in v1.
- The verifier uses one total order: Stage 0 external strict context and
  expected hash (code 19, all refs null, manifest/listed-file open count 0), Stage 1 bounded raw manifest/schema/
  JCS/declared lengths (code 10/17, context refs only), Stage 2 manifest hash,
  Stage 3 trusted export key, Stage 4 Ed25519 (code 11 and zero file opens),
  Stage 5 root-confined actual lengths/sums/raw digests (code 13), Stage 6
  catalog graph/semantic hash, then Stage 7 partition/checkpoint/anchor/
  freshness/scope. Global failures return `partitions:[]`; only Stage 7 emits
  entries, and every emitted entry including local failure has four non-null
  endpoint refs. Invalid signature plus bad actual length opens zero files and
  returns the signature code; valid signature plus bad length returns code 13.
- Freshness requires
  `checkpoint.sealedAt <= checkpoint.signedAt <= receipt.anchoredAt <=
  receipt.signedAt <= freshness.observedAt <= verifiedAt < validUntil`, with
  boundary-equality and one-microsecond-after fixtures. Unsigned manifest body
  is <=15 MiB and stored raw JCS/LF <=16 MiB, inclusive, producer and verifier
  both checking cap+1.
- Unauthenticated access without a trusted tenant maps to `system:global` and
  records no raw target/tenant. Authenticated same, cross, and absent reads use
  the requester partition, never the target tenant; cross and absent share
  `resource_unavailable` and a bounded domain-separated opaque request ref
  without an existence claim. Status/Journal/Outcome/diagnostics share this
  fixture matrix.
- Before external anchor write, persist deterministic closed `AnchorIntent v1`
  `{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,
  checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,anchorObjectRef,
  expectedPredecessorVersion,expectedPredecessorHash,anchorReceiptId,
  idempotencyKey,conditionalCreateToken,acceptedObjectVersion,acceptedObjectHash,
  acceptedAnchoredAt,receiptSignedAt,receiptSigningKeyRef,receiptBytesHash,
  anchorReceiptHash,leaseFencingToken,leaseExpiresAt,version,updatedAt}`; only
  `receiptSigningKeyRef` names a signing key. Perform conditional create,
  read-after-write exact version/hash, persist the signed receipt, then
  acknowledge. States are `prepared|externally_accepted|
  receipt_inputs_persisted|receipt_persisted|acknowledged`; every transition
  is owner-fenced CAS. The three non-overlapping
  crash points are before external accept, after accept/before local signed-
  receipt persist, and after local receipt persist/before ack. Every retry uses
  the same idempotency key, object reference, version, receipt bytes, and
  deterministic signature and cannot create N+1. For required effects, exact underlying connection object
  and active root transaction identity are checked before effect; name-only,
  nested/manual, and rollback mismatches fail closed with no durable fallback.

### 16.2 Historical Round 9 bounded correction

Round 9 incorporates the fresh review as Changes Requested and narrows the
contract without implementing code. General findings are `P1=2/P2=1/P3=0`
and Audit findings are `P1=5/P2=4/P3=1`; these lanes remain separate. The
historical status was Round 9 Correction Implemented / Review Requested, never Green,
Accepted, or Decided.

The six application projection states and trusted artifact identity are
identical across D145 and Specifications 105/106. `list`/`ls` recovery emits
framework rows at exit `0` for every state; operational read is exit `1` and
only complete route/schedule artifact failure is exit `2`. `schedule:list` and
`schedule:ls` are mutually identical, with valid/operational/artifact exits
`0/1/2`; they do not inherit list recovery. XML and Markdown are continuous,
single-final-LF canonical wires; the Spec 105 XML and Markdown fixture hashes
are the golden bytes, including empty aliases, hidden values, and special
escaping. All four formats and short/non-short are byte-identical projections.

Audit evidence descriptors are closed with required classification:
`safe_code` uses the bounded lowercase grammar and `opaque_id` is the typed
`oid:v1:<64 lowercase hex>` wire. Syntax/category validation cannot prove
secret absence; typed mapper/review owns semantic detection. Missing/unknown
classification, non-null integer/boolean classification, bad safe-code grammar,
and raw opaque-token fixtures fail, while safe-code exactly 256 bytes,
opaque-id exactly 71 bytes, and nullable null fixtures pass; safe-code 257 and
opaque-id 70/72 fixtures fail. The durable-only
`AuditIngressEnvelope v1` is never stored by required delivery. Durable rows
are the exact stateful `AuditOutboxRecord v1` with length-prefixed
`AuditOutboxRecord` AAD, protected logical envelope, idempotency conflict
semantics, per-partition ordinal head/FIFO, lease fencing, poison stop, and
same-ciphertext retry.

Verification is one Stage 0-7 total function. Stage 0 validates mode and
standalone bounded context/hash with zero manifest reads (code 19); Stage 1
validates raw manifest/schema/JCS/count/version/declared lengths (10/17);
Stage 2 hash; Stage 3 trusted key; Stage 4 signature (11, zero file opens);
Stage 5 root-confined actual bytes (13); Stage 6 authenticated catalog plus
mode/context binding and exact freshness heads (18/16); Stage 7 partition
semantics. Result nullability follows valid invocation mode and Stage 4,
  global failures keep `partitions:[]`, and endpoint refs always derive from
  full signed manifest refs. AnchorIntent v1 uses the exact closed states
  `prepared|externally_accepted|externally_rejected|receipt_inputs_persisted|
  receipt_persisted|acknowledged`, deterministic receipt bytes, persisted
  receiptSignedAt, and anchoredAt<=receiptSignedAt; external unavailable below
  attempt 32 returns retryable prepared. At attempt 32, same-object
  reconciliation must first establish the outcome: a found object recovers
  the accepted state, explicit absence alone returns
  `externally_rejected/attempt_exhausted`, and lookup unavailable remains
  reconciliation-only prepared with no new external claim. The
three crash points are distinct and retry creates no N+1. AuditCheckpoint is
seal evidence; `AuditControlEventAdapter` owned by P23-003D emits
`audit.checkpoint.persisted` and `audit.anchor_receipt.persisted` through
`system:global/audit-control-v1` using typed P23-003F outcomes via P23-003B;
retention
is metadata/eligibility only, with physical deletion deferred to P23-003H.
Actor/tenant mapping is exact: unauthenticated/invalid/no-trusted actor uses
`system:global` with principal null and no raw target/tenant; authenticated
tenant uses requester partition for same/cross/absent; authenticated actor
with null tenant uses `system:global` with principal populated. All four read
surfaces share `resource_unavailable` for cross/absent without existence
claims. Future audited diagnostics require closed
`AuditReadContext={actor,tenant,purpose,requestRef,authorizationDecision}`
owned by P23-003D; Stable diagnostics remain unaudited until implemented.
Actor/tenant and root-confined nofollow fixtures are mandatory.

### 16.3 Historical Round 10 bounded correction

Round 10 is `Correction Implemented / Review Requested`; its exact-8 local
management gates are PASS (scope/staged-none, diff/whitespace, release source,
version baseline, content, Mago, PHP management-ID, JSON fences, and fixture
hashes). It supersedes the
Round 9 pre-review wording without inventing a Round 9 Sol verdict. Round 8
fresh review remains historical General `P1=2/P2=1/P3=0` and Audit
`P1=5/P2=4/P3=1`, separately. This proposal remains unreleased and is not
Green, Accepted, or Decided.

The list recovery status is the exact six-value enum
`available|absent|malformed|unsupported|stale|build_mismatch`, including
`unsupported` in every recovery enumeration. It emits one canonical `list`
row whose aliases include `ls`, not a second `ls` row. P23-002A owns a closed
`ProjectionBuildSet v1` from an installation-owned immutable locator/trusted
root (a data-integrity boundary, not a cryptographic trust claim):
`{schemaVersion,applicationBuildId,projections:{command,route,schedule}}`,
where each projection member is `{kind,inputHash,path,sha256}` with exact kind,
64-lowercase-hex hashes, root-relative safe paths, and no unknown/duplicate
members. `build:compile` writes each dedicated closed projection to a bounded
temporary file, fsyncs and atomically renames it, then atomically publishes
the root. Each projection input hash covers kind, schema, build ID, and sorted
closed source records. The reader loads only this trusted root data, verifies
root and artifact digests, and never resolves source/provider/container.
Root/artifact swaps, root-before-artifacts, malformed/unsupported/mismatched
root, partial publish, and old-root/new-root atomic-concurrency fixtures fail
closed; only old complete or new complete sets are visible. Root ENOENT is
`absent` for list recovery/exit `0` and complete route/schedule exit `2`, root
permission/I/O is exit `1`, and other six-state precedence is unchanged.
List startup covers explicit as well as discovered registrations with lazy
compiled metadata, constructor/factory/container/DB sentinels, and
`build:compile` discovery. Route and schedule each consume one dedicated
projection (route build combines Operation and fixed rows before publication).

Command records have finite bounds: raw UTF-8 JSON <=16 MiB, 0..4,096
records, <=32 aliases, separator-safe NFC names/aliases with no empty colon
segment and <=256 bytes, and one-line NFC descriptions with no control/CR/LF
and <=256 bytes; cap+1 and unsafe-separator migration fixtures are required.
JSON is compact JCS plus one LF, raw has one LF per row including the final row,
and XML/Markdown are the exact no-BOM/LF wires. The XML golden remains
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`; the
Markdown golden is
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa` after
removing the unreachable CR/LF description fixture. `--all` is explicit in
structured hidden-field fixtures; JSON/XML/Markdown carry `hidden`, raw keeps
its fixed shape, and alias/short bytes remain identical.

The canonical and durable bodies use binary BOPD v1, never a BOPD object:
magic/version/algorithm/key-ID length+bytes/24-byte nonce/ciphertext length+
ciphertext/16-byte tag. Canonical plaintext is complete AuditRecord JCS+LF;
outbox plaintext is JCS(`AuditIngressEnvelope v1`) with no LF. Canonical
`AuditRecord` AAD is a custom domain-separated length-prefixed preimage binding
envelope version, algorithm, exact key ID, purpose, record/partition/segment/
sequence/hash fields, tenant union, and derived partition; authorization and
expected partition precede decrypt and post-decrypt hashes must match.
`AuditIngressEnvelope` requires recordId/recordedAt/contentHash equality to its
logical record and recomputed domain hash. `tenantBinding` is exactly
`null|{type,id}`, equals logical `tenant`, and derives `system:global` or the
canonical domain-hashed partition; it is a bounded key-routing index, never raw
evidence. Outbox idempotency hash is domain-separated normalized-key plus
partition and the raw key remains protected.

`AuditOutboxRecord v1` has attempt `0` at ingress and +1 per fenced claim.
Pending has non-null availableAt and null lease fields; leased has null
availableAt and non-null fencing token/expiry; relayed and poisoned have all
lease fields null. Only fenced state fields mutate; identities, bindings,
hashes, and protected bytes are immutable. Transient/expired leased retry is
fenced `leased->pending`, terminal transitions are `leased->relayed|poisoned`,
and minimum-head FIFO/no-bypass remains mandatory. BOPD allocation includes
exact overhead `52 + keyIdUtf8Bytes`; malformed header/key-ID, row/tenant swap,
stale token, expiry, poison, and cap+1 fixtures are required.

Verifier Stage 3 resolves export key role/status at trusted `verifiedAt` and
does not trust manifest `signedAt`; global invalid key is code `12`. Stage 4
signature failure is code `11`, zero listed-file opens, and null export refs;
success makes refs non-null and then checks authenticated signedAt. Later
global failures retain refs and `partitions:[]`; local checkpoint/anchor
key/signature/time failures are code `15`, chain `14`, scope `16`, and local
head freshness `18`. Stage 6 validates head-set shape only; Stage 7 compares
each partition end receipt to its authenticated head, allowing one local code
18 while another partition verifies. The exact mode/scope/ref null matrix,
prefix/suffix flag matrix, singular manifest checkpoint/receipt refs, and
one-segment same-endpoint/multi-segment first-last/body-substitution fixtures
are required. The manifest has no `as_of` property; as-of mode verifies the
authenticated included graph without a current/suffix claim.

`AnchorIntent v1` is a closed object with bounded NFC/no-control IDs/refs/
tokens, 64-hex hashes, positive/null predecessor versions, six-fraction UTC
times, and accepted fields `acceptedObjectVersion`, `acceptedObjectHash`,
`acceptedAnchoredAt`, `anchorReceiptHash`. Prepared has all accepted fields
null; externally accepted has the accepted triplet non-null and receipt hash
null; receipt-persisted and acknowledged have all four non-null. Accepted
object hash equals checkpointBytesHash, receipt signing is pinned and
signedAt equals stable anchoredAt, and the persisted receipt hash is bound.
The three crash points remain before accept, after accept/before receipt
persist, and after receipt persist/before ack, with same bytes/version and no
N+1. Control artifacts for `system:global/audit-control-v1` never emit further
checkpoint/anchor control records; signed control checkpoint/receipt remain
authoritative. After final fd acquisition, namespace replacement cannot alter
bytes read through that fd; pre-open swaps reject and post-open stability is
tested.

## Evidence and rationale

### Repository evidence

- `src/Internal/Application/ApplicationConsoleKernel.php:47-89,102-109,214-242`
  shows the Symfony Application composition, Application LazyCommand loading,
  and the retention Lazy option omission.
- `src/Internal/Console/LazyFrameworkCommand.php:12-52` and
  `src/Internal/Console/FrameworkCommandNames.php:7-39` show the current
  framework-owned lazy wrapper and collision-name list.
- `src/Internal/Console/ApplicationOperationListCommand.php:19-60` compiles
  Provider/Discovery metadata and emits a table, so it is not a side-effect
  free compiled projection reader.
- `src/Internal/Registry/OperationManifestFile.php:11-65,100-128` and
  `src/Internal/Runtime/ProductionRuntimeArtifactLoader.php:19-37` show
  versioned artifacts, build IDs, and fail-closed mismatch checks.
- `src/Http/Routing/HttpRouteCompiler.php:36-75,94-97`,
  `src/Http/OperationRequestHandler.php:39-61`, and
  `src/Http/Status/OperationStatusRequestHandler.php:17-39` show compiled
  Operation routes plus a separately matched fixed status route.
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php:31-124` always
  composes the public `Application::http()` status query and passes it to the
  runtime; `src/Internal/Runtime/ProductionRuntimeComposer.php:32-111` keeps a
  nullable low-level status dependency, which is not the public route target.
- `src/Http/Authentication/AuthenticationMiddleware.php:20-86`,
  `src/Http/Authentication/HttpAuthenticator.php:10-14`,
  `src/Internal/Authorization/AuthorizationEvaluator.php:13-45`,
  `src/Internal/Http/OperationStatusAuthorizerResolver.php:13-38`, and
  `src/Internal/OperationData/OperationDataReadAuthorizerResolver.php:10-36`
  establish the authentication, authorization, status, and protected
  Operation-data read boundaries for P23-003D adapter review.
- `src/Internal/Logging/ExecutionScopedLogger.php:21-177` and
  `develop/spec/100-structured-logging-and-opentelemetry.md` establish the
  current Structured Log/OpenTelemetry Operation/Correlation/Trace/Span
  positive; they are not the future four-surface Audit correlation contract.
- `src/Internal/Execution/DeferredWorkerLoop.php:29-87`,
  `src/Internal/Execution/PcntlSignalHeartbeat.php:56-167`, and
  `src/Internal/Application/ApplicationWorkerComposer.php:44-170` show the
  current CLI Deferred loop, PCNTL heartbeat/signals, recovery, DB, Journal,
  Outbox, and compiled-artifact composition.
- `develop/spec/32-worker-crash-recovery.md`, `42-installed-application-boundary.md`,
  `47-public-http-runtime-configuration.md`, `78-application-runtime-and-bootstrap.md`,
  and `82-operation-dispatch-and-deferred-authoring.md` establish lease,
  process, artifact, HTTP, and transactional child-dispatch boundaries.
- `develop/spec/98-scheduled-application-operation.md`,
  `99-tenant-isolation-and-protected-operation-data.md`, and
  `100-structured-logging-and-opentelemetry.md` establish schedule metadata,
  tenant-before-decrypt, BOPD, safe projections, and W3C propagation.
- `src/Core/Retention/RetentionPurgeAuditRecord.php:15-71`,
  `src/Transport/PostgreSql/PostgreSqlRetentionPurgeAuditStore.php:13-73`,
  `src/Internal/Replay/ObserverReplayRuntime.php:25-108`,
  `src/Transport/PostgreSql/PostgreSqlObserverReplayAuditWriter.php:10-41`,
  `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php:14-145`,
  `src/Internal/Outbox/TransactionalOutboxRuntime.php:32-149`, and
  `src/Transport/PostgreSql/PostgreSqlOutboxStore.php:317-414` show the
  existing narrow audit-like records, replay/rotation checkpoints,
  transaction-bound outbox registration, and PostgreSQL retry/dead-letter
  transaction path; none is a general AuditRecord canonical store.
- `develop/decisions/062-retention-audit-log-delivery.md` and
  `develop/decisions/136-structured-logging-and-opentelemetry.md` keep
  retention audit delivery and observability distinct from the proposed
  Audit Trail.

### Official evidence researched 2026-08-19

- [FrankenPHP Worker Mode](https://frankenphp.dev/docs/worker/) describes
  request-driven `frankenphp_handle_request()`, in-process state persistence,
  max-request restart, and worker failure backoff.
- [FrankenPHP Internals](https://frankenphp.dev/docs/internals/) describes
  POSIX PHP threads, Go/C state management, worker restart, and thread-based
  rather than process-based execution.
- [FrankenPHP Extension Workers](https://frankenphp.dev/docs/extension-workers/)
  describes dedicated PHP thread pools for background tasks, Go registration,
  and configurable Caddy modules.
- [Writing PHP extensions in Go](https://frankenphp.dev/docs/extensions/)
  documents generated Go/C extensions and `xcaddy build` integration.
- [FrankenPHP Configuration](https://frankenphp.dev/docs/config/) documents
  worker thread counts and `max_consecutive_failures`.
- [FrankenPHP Performance](https://frankenphp.dev/docs/performance/) warns
  that worker mode requires memory-safe long-running code and recommends
  `num_threads * memory_limit < available_memory`.
- [PHP PCNTL overview](https://www.php.net/manual/en/book.pcntl.php), [PHP
  `pcntl_signal`](https://www.php.net/manual/en/function.pcntl-signal.php), and
  [PHP `pcntl_alarm`](https://www.php.net/manual/en/function.pcntl-alarm.php)
  document the signal/timer primitives used by the current heartbeat design.
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
  distinguishes process, audit, transaction, and security logs and notes that
  application code owns the context for action, target, actor, and outcome.
- [NIST SP 800-53 Rev. 5](https://csrc.nist.gov/pubs/sp/800/53/r5/upd1/final)
  provides Audit and Accountability controls as design evidence only; it is
  not a claim of compliance.
- [W3C Trace Context](https://www.w3.org/TR/trace-context/) defines the
  32-hex trace ID, 16-hex parent ID, and sampled flag. It does not make a trace
  an identity, integrity proof, or audit record.

## Historical Round 11 bounded correction checkpoint

Round 11 was a historical `Correction Implemented / Review Requested`
checkpoint for D145. Round 8, Round 9, and Round 10 are historical; their review
lanes and counts are not combined and no Sol verdict is invented. Stable `1.2`
remains current, the Phase 23 capability remains unreleased, and the current
evidence population is 44 unique existing local targets, 46 cited ranges, and
270 Markdown link occurrences.

The exact `ProjectionBuildSet v1` root is the closed five-member object
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with kind
`projection_build_set`, exactly command/route/schedule descriptors, and
`buildSetHash = SHA-256(ENC("blackops.projection-build-set.v1") ||
ENC(JCS(root excluding buildSetHash)))`. Its configured installation root is
an integrity boundary, not cryptographic authenticity. Stored root bytes are
UTF-8 RFC 8785 JCS plus exactly one final LF, with no BOM/CRLF/other
whitespace; the hash preimage omits that storage LF, and the <=64 KiB cap
applies to those bytes with missing/extra-LF/BOM/CRLF/cap+1 fixtures. Exact
projection kinds
are `blackops_command_projection`, `blackops_route_projection`, and
`blackops_schedule_projection`; each file has a closed header and bounded
records, exact sort key, and domain-separated `inputHash`. Command records are
`{name,aliases,description,origin,hidden}`; route records are
`{method,path,strategy,operationType,origin}` sorted by
`(path,method,origin,operationType,strategy)` with nulls first; schedule records
are `{name,operationType,cron,timezone,strategy}` sorted by
`(name,operationType)` with nulls first. Root raw is <=64
KiB, each projection raw is <=16 MiB, rows are 0..4096, and command, route,
and schedule fields have explicit NFC/no-control byte caps and cap+1 fixtures.
Generation/content-addressed files are immutable: temp+fsync+rename+parent
fsync happens for each file before the same sequence for the root last; an old
root's files are never overwritten and GC waits for reader quiescence. Readers
use only the configured root and do not claim to compare working source. The
single precedence table is raw-cap, ENOENT, other I/O, malformed, unsupported,
build mismatch, stale input identity, available. List/ls maps artifact states
to recovery exit 0, while complete route/schedule failures remain exit 2.
The route projection, not the command projection, combines Operation HTTP rows
with the framework-fixed descriptor.

The durable-only `AuditIngressEnvelope v1` exact fields include
`tenantBinding`; canonical storage additionally uses the closed
`AuditStoredRecord v1` row with `recordId` and `segmentId` each 1..256-byte
NFC/no-control strings, `partitionId` exactly `system:global` or
`tenant:<64 lowercase hex>`, and `tenantBinding` members bounded exactly as
the AuditRecord tenant. Its `eventCategory` is exactly the AuditRecord
category enum and equals decrypted `event.category`; its `recordedAt` equals
decrypted `time.recordedAt`. The row has a BOPD protected complete AuditRecord
JCS+LF body with complete allocation bounded by 1 MiB+180 bytes. Canonical
and outbox key IDs are 1..128 bytes; complete logical records are <=1 MiB.
Canonical AAD binds the
version, algorithm, key ID, purpose, every clear identity/hash/time/category,
and the unambiguous tenant-union plus derived partition. Authorization and key
routing precede decrypt, and row/body/hash equality follows it. The closed
`AuditReadContext v1` fixes nested actor/execution/tenant types, four read
purposes, authorization decision enum, and a domain-separated `oid:v1` request
ref with no raw target/tenant; unauthenticated, tenant-null, same, cross, and
absent mappings are explicit.

Manifest records, checkpoint files, receipt files, and catalog files bind
artifact IDs exactly to segmentId, checkpointId, anchorReceiptId, and
catalogHash. Segments are partition-then-chain ordered, boundaries are
partition ordered, and files are unique canonical-path ordered. Raw file SHA
is distinct from semantic checkpoint/receipt hashes. The verifier total
function rejects Stage 1 malformed input code 10 before valid-unsupported code
17; Stage 6 orders catalog code 10, context coverage code 16, and head-set
shape code 18; Stage 7 orders local 14/15/16/18. Stage 3 key availability is
global code 12 with null export refs, Stage 4 signature failure is code 11
with null refs and zero opens, and only signature success makes export refs
non-null before authenticated signedAt and Stage 5. Missing/expired/stale
current freshness maps to the specified Stage 6/7 code 18, not
`key_unavailable`; malformed context/freshness shape/hash/time order is Stage 0
code 19. `AnchorIntent` uses `anchorReceiptId`, exact semantic unsigned
receipt hash (not raw signed-file SHA), genesis version 1 or predecessor+1,
accepted hash equal to checkpointBytesHash, and a fenced lease pair that is
both present only for an active claim. State regression and stale-owner writes
fail closed; every crash/lease/hash/ID fixture is required.

Round 11 local management gates remain required and uncommitted. Remaining is
fresh Sol xHigh general+Audit review, Orchestrator acceptance, then P23-001R
and ordered downstream; Suggested Next Action is independent review of this
exact eight-file snapshot. This decision is not Green, Accepted, or Decided.

## Historical Round 12 bounded correction checkpoint

Current checkpoint updated: 2026-08-19T14:51:24+09:00

Round 12 is a historical `Correction Implemented / Review Requested`
checkpoint for D145. Round 11 remains historical Changes Requested with
General `P1=2/P2=2/P3=0` and Audit `P1=7/P2=3/P3=1`, separately; no lanes are
aggregated and no Green, Accepted, or Decided claim is made. Stable `1.2`
remains current and every Phase 23 capability remains an unreleased proposal.

At that historical checkpoint, the only projection source was `ProjectionBuildSet v1` with dedicated
`blackops_command_projection`, `blackops_route_projection`, and
`blackops_schedule_projection` files and descriptor/file `inputHash` values.
The route file already combined Operation HTTP rows with the framework-fixed
row; schedule:list read its dedicated compiled file and never read an
Operation Manifest directly. The configured regular no-follow lock file was
exactly `.blackops-projection-build-set.lock`: readers held shared flock
through root validation, all three same-fd no-follow opens, selection, and
rendering; publishers acquired exclusive flock before preparing any
generation file and held it through root publication, directory fsync, and
bounded GC. Old complete or new complete sets were the only visible states.
The current guard rejects active standalone `projectionInputHash` envelopes,
runtime HTTP joins, and direct schedule readers. `operation:list` accepts the
optional case-sensitive single `--strategy=inline|deferred` filter; invalid,
duplicate, missing, or case-variant values exit `2` before Provider/compiler
work, while valid filtering preserves the existing table and type-ID sort.

P23-001S is a separate non-1.3 repository-blocking Stable Guide truth
correction before final P23-001 acceptance and P23-002A. It was created and
accepted in a historical checkpoint, then reopened for the Round 13 visibility
correction; the current child Task and Report are the only status authority.
The former Guide side-effect-free list sentence is historical and is not
positive evidence; the current Guide is corrected and remains under review.

Audit ownership is partitioned: P23-003B owns partition-global sequence and
closed `AuditSegmentState v1` open/close/rotate fencing, canonical
`AuditStoredRecord` BOPD/AAD and the pinned 232-byte
`5930076af5418572a4e575943172884775428ad10d024de7eb9fc419bce04b1c` and
344-byte `f3ef3d96598964f7087ffe654ecde4d2e828caebfa981ecb1daa82859ff7c57f`
AAD goldens, with tenant/key authorization before decrypt. P23-003C owns
durable outbox state; v1 ciphertext, key ID, nonce, ciphertext, and tag are
immutable and re-encryption/rotation is prohibited. P23-003F consumes only a
closed segment, persists one deterministic checkpoint, seals by fenced CAS,
owns checkpoint/anchor signing and reusable verification primitives, and
requires `audit.access.sign.accessed` before signature release.
P23-003A owns unavailable and `audit.access.<purpose>.requested|unavailable|accessed`
catalog tuples and typed source rejection; P23-003D owns durable
Status/Journal/Outcome/Diagnostics adapters; P23-003G owns
`AuditAccessContext`, expectedManifest/manifest-pin Stage orchestration,
query/export/hosted verify and their accessed events before response/result, and
metadata-only `AuditRetentionState` CAS/idempotency. Offline verify has no canonical append
claim, and P23-003H physical deletion remains future/nondependency.

The verifier is one Stage 0-7 total function. Stage 0 validates invocation
mode and strict bounded context/hash with zero manifest reads; Stage 1 parses
raw manifest; Stage 2 self-hashes the parsed candidate and compares its
`exportId`/`manifestHash` to the required pinned
`expectedManifest={exportId,manifestHash,purpose}`; Stage 3 resolves the
trusted export key; Stage 4 verifies signature and only then makes export
references non-null; Stage 5 opens root-confined files through one fd; Stage 6
checks catalog, mode coverage, and head-set shape; Stage 7 checks each
partition. As-of has null freshness and no current/suffix claim; current
requires the freshness window. Export identity is only signed `{role,
artifactId}` plus raw digest, never basename or role-prefix naming. Global
failures have `partitions:[]`; local failures retain authenticated endpoint
references. All four read surfaces map absent/cross-tenant to the same durable
`.unavailable` `resource_unavailable` tuple without an existence claim.

Historical Remaining was to freeze and verify that exact-8 Local Gate, create/run the separate
P23-001S Stable Guide correction, obtain fresh Sol xHigh General and Audit
review plus Orchestrator acceptance, then P23-001R and ordered downstream
slices. Historical Suggested Next Action was to run the fresh exact-8 gates and
return hashes to the Orchestrator, then route P23-001S before requesting fresh
review. That checkpoint did not publish or accept any 1.3 capability.

## Consequences

- 1.3 implementation can read bounded compiled projections without invoking
  application code, containers, databases, or clocks.
- Deferred processing retains process and failure isolation; an optional
  extension-worker experiment cannot silently become a production dependency.
- A future Audit Trail has an explicit canonical store, delivery semantics,
  tenant/security boundary, and verification model instead of overloading
  Journal or JSONL.
- Audit v1 deliberately retains sealed bytes and keys; physical deletion and
  signed deletion-manifest/legal-hold proof are a separate future P23-003H
  review and are not compliance evidence.
- More implementation slices and independent release gates are required. This
  is intentional: no discovery decision alone changes the Stable `1.2`
  capability boundary.

## Traceability

- Proposal contract: [Specification 105](../spec/105-blackops-1-3-capability-contract.md)
- Delivery DAG: [Specification 106](../spec/106-phase-23-delivery-plan.md)
- Stable retention audit: [D062](062-retention-audit-log-delivery.md)
- Stable observability: [D136](136-structured-logging-and-opentelemetry.md)

## Historical Round 13 bounded correction checkpoint

Current checkpoint: 2026-08-19T19:13:27+09:00

Round 13 was the then-current `Correction Implemented / Review Requested`
checkpoint. Round 12 is historical Changes Requested with General
`P1=5/P2=1/P3=0` and Audit `P1=6/P2=3/P3=2`, in separate lanes and without
aggregation. P23-001S is also reopened from its historical acceptance and is
currently Review Requested. Stable `1.2` remains current; every Phase 23
capability remains an unreleased proposal. This decision is not Green,
Accepted, or Decided.

### ProjectionBuildSet ownership and publication

There is one executable projection wire and one acceptance slice. The closed
`ProjectionBuildSet v1` root contains exactly
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
`kind=projection_build_set`; its self-hash is
`SHA-256(ENC("blackops.projection-build-set.v1") ||
ENC(JCS(root excluding buildSetHash)))`. Stored root bytes are UTF-8 JCS plus
one final LF and are capped at 64 KiB. Projection kinds are exactly
`blackops_command_projection`, `blackops_route_projection`, and
`blackops_schedule_projection`, each with closed headers, descriptor/file
`inputHash`, bounded rows, canonical unsigned-UTF-8 sort, and no unknown or
duplicate fields. A root is an installation-owned integrity boundary, not a
cryptographic authenticity claim.

P23-002A owns the shared wire, the regular no-follow lock
`.blackops-projection-build-set.lock`, the reader, publisher, command
projection producer, and list implementation. P23-002B owns route rows and
route commands. P23-002C owns schedule rows, schedule commands, and final
integration activation. A, B, and C form one atomic acceptance slice: A and B
may prepare bounded in-memory/generation inputs but neither may publish or
activate a partial root. C wires all three into the A-owned publisher and only
the complete three-file root is accepted; Stable Symfony list remains the
active recovery surface until that gate passes. Synthetic A/B/C component and
final `build:compile` full-root fixtures are required.

The publisher acquires exclusive flock before creating, writing, or renaming
any generation temporary/content-addressed file and holds it through
same-fd validation, root temp fsync plus atomic rename, parent-directory
fsync, and GC. A reader acquires shared flock before opening the root and all
three same-fd no-follow regular files and holds it through validation, row
selection, and rendering. GC runs only under exclusive flock and removes only
paths not referenced by the now-current root; bounded abandoned temp cleanup
also runs only there. A preparation barrier, B publish+GC, A resume, publisher
crash-before-root, lock replacement/symlink/special, and old-root/new-root
interleaving fixtures define the sole reader-quiescence meaning.

The single precedence table is raw cap before parse; ENOENT `absent`; other
I/O `operational` exit 1; invalid JSON/JCS/closed shape/self-hash/digest
`malformed`; syntactically valid unsupported schema/kind `unsupported`;
configured identity mismatch `build_mismatch`; descriptor/file input identity
mismatch `stale`; otherwise `available`. List/ls maps all six states to
framework rows and exit 0; complete route/schedule maps artifact failure to
exit 2 and operational failure to exit 1. Readers never inspect source,
provider, container, DB, or a runtime HTTP/Operation Manifest file.

The canonical strategy wire is injective and shared by command, route, and
schedule projections: reflect the declared class name without a leading slash;
`Inline::class` is `inline`, `Deferred::class` is `deferred`, and every other
strategy is `custom:<canonical-FQCN>`. A complete wire is at most 1024 UTF-8
bytes; 1025 is an actionable build failure. No-filter includes custom values;
`--strategy=inline|deferred` matches only the two exact built-ins. Two custom
classes with one short name, 64-byte/>64-byte, 1024/1025, route/schedule,
no-filter, and both-filter fixtures are required.

### Audit correction boundaries

Evidence descriptors are closed. `required=true` requires presence;
`required=false` may be absent. A present null is valid only when
`nullable=true`; unknown fields are rejected. The shared matrix covers
required/optional × present/missing/null, including integer/boolean null
rejection and typed `safe_code|opaque_id` grammar. A grammar-valid string is
not evidence of semantic secret absence: typed framework adapters reject raw
credentials/tokens before construction, while Application mapper/review owns
semantic classification and the verifier makes no automatic secret/compliance
claim.

`AuditAccessContext v1` request references use one byte-exact formula:
`oid:v1:` plus the lowercase SHA-256 of
`LP("blackops.audit.access.request-ref.v1") ||
LP(JCS({purpose,requesterActor,requesterTenant,requestedAt,nonce}))`, where
`LP(x)` is unsigned U32BE byte length followed by exact UTF-8 bytes. The
actor/tenant null unions are explicit closed values inside that one JCS
object. Actor, tenant, purpose, decision, requestedAt, and nonce have bounded
NFC/no-control shapes; the raw target is never included. The null/null vector
is independently pinned in Specification 105 as a 121-byte JCS and 165-byte
LP preimage with SHA-256
`692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.
Null/present four-combination, Unicode, and each-field substitution goldens
are required.

`AuditOutboxRecord v1` starts `pending` with `attempt=0`; each fenced claim
increments the non-negative attempt. Retry/reclaim returns to pending with
that incremented value, while only `leased` has a non-null lease token and
expiry; pending and terminal `relayed|poisoned` rows have both lease fields
null. Minimum-head/no-bypass, stale-token, expiry, poison, and append/ack
crash fixtures are required. Identity, key ID, envelope version/algorithm,
nonce, ciphertext, and tag are immutable: v1 prohibits protected-body
re-encryption or key rotation. Old-key unavailability closes the partition
operationally; a future versioned CAS contract would be required for rotation.

`AuditSegmentState v1` stores only `open|closed|sealed`; `closed_unsealed` is
an operational display derived from stored `closed`, never a stored/CAS value.
There is one active open segment. Partition sequence is global: sequence 1
and a null predecessor occur only at genesis; rotation sets the new first
sequence to old final+1 and predecessor to old head hash. P23-003B closes by
fenced CAS and atomically opens the successor; P23-003F consumes only the
frozen closed segment and marks it sealed after deterministic checkpoint
persistence. Sign/anchor/trust/freshness failure leaves `closed`, rejects
append, and allows the specified retry. Empty-close, stale-fence, rotate
crash, checkpoint-before-seal, shuffled/reversed/Unicode partition order, and
continuity fixtures are required.

After external anchor acceptance, AnchorIntent persists trusted
`receiptSignedAt`, the selected key ID, deterministic receipt bytes/hash, and
the pinned `anchorReceiptId` before signing. The receipt `signedAt` equals
that persisted value and `anchoredAt <= signedAt`; key-window checks use the
persisted signing time. A crash after this persistence reuses byte-identical
receipt/signature and does not create N+1. Delay, not-before/revocation,
parallel retry, and lease fixtures are required.

Every verification mode requires a bounded `trustStoreSnapshot` and Stage 0 checks
`observedAt <= verifiedAt < validUntil` with code 19 before any manifest/file
open. Freshness is separate: malformed snapshot shape/hash is Stage 0 code
19; current null/expired/coverage/head mismatch maps Stage 6/7 code 18; as-of
with non-null freshness maps code 16. The required external
`expectedManifest={exportId,manifestHash,purpose}` is included in contextHash;
Stage 2 self-hashes the parsed candidate before constant-time pin comparison,
and a pin mismatch is code 16 with zero listed-file opens and null refs.

P23-003G owns bounded `AuditQueryRequest v1`, `AuditQueryResult v1`, and a
domain-authenticated keyset cursor containing authorized partition, normalized
filters, snapshot head sequence, and last sequence. Results are deterministic
sequence order with bounded limit and first/next/empty cases; cross-tenant or
cursor swaps, decrypt/integrity failures, and unavailable resources fail
closed. Access events occur at the decision boundary. P23-003G also owns
immutable hold records keyed by holdId with `active|released` state; overlapping
holds are allowed, category null is wildcard, and any matching active hold
makes a record ineligible. Releasing one hold never releases another. CAS,
idempotency, overlap, cutoff race, and sealed-byte/key invariants are required.

All four Status/Journal/Outcome/Diagnostics read families use
`.requested|.unavailable|.accessed`; absent/cross-tenant produce the same
durable `resource_unavailable` tuple with `action.phase=unknown`,
`outcome.status=unknown`, `policy.decision=not_applicable`, and no existence
claim. Legacy `resource_not_found` and the former `.allow|.deny` read forms
are historical only. Multi-partition arrays use unsigned UTF-8 byte order,
then chain order within each partition.

Historical Round 21 was the then-current `Child Accepted / Parent Review
Requested` checkpoint, and its fresh complete Round 21 Local Gate was PASS.
Round 20 and earlier are historical Changes Requested, including child General
`P1=0/P2=1/P3=0`; fresh Round 21 child General returned `P1=0/P2=0/P3=0`, and
the Orchestrator accepted P23-001S. Its historical Remaining was fresh parent
General and Audit review and parent Orchestrator acceptance; only then P23-001R
and ordered downstream. Its historical Suggested Next Action was to preserve
the accepted-child freeze and request the independent parent reviews. No 1.3
feature was public or released.

## Historical Round 14 bounded correction checkpoint

Current checkpoint: 2026-08-19T19:56:30+09:00

At that historical checkpoint, Round 14 had `Correction Implemented / Review
Requested` status. Round 13 is historical Changes Requested with General
`P1=1/P2=1/P3=0` and Audit `P1=4/P2=2/P3=0`, in separate lanes and without
aggregation. P23-001S was created and historically accepted, then reopened;
its current child status is also Correction Implemented / Review Requested.
Stable `1.2` remains current, Phase 23/BlackOps `1.3` remains an unreleased
proposal, and no work is Green, Accepted, or Decided.

### Projection reader and acceptance slice

The active projection contract has one wire: the closed `ProjectionBuildSet
v1` root with dedicated `blackops_command_projection`,
`blackops_route_projection`, and `blackops_schedule_projection` files. A
route producer combines Operation HTTP rows and the framework-fixed
descriptor at build time; a schedule producer compiles schedule metadata at
build time. `route:list` and `schedule:list` consume only their dedicated
projection files. No runtime HTTP join, read-time descriptor join, direct
Operation Manifest reader, standalone `projectionInputHash` envelope, source
scan, provider lookup, container lookup, or database read is permitted.

P23-002A owns the shared wire, command producer, list reader, lock, publisher,
and GC. P23-002B owns route row production and route commands. P23-002C owns
schedule row production, schedule commands, and final integration activation.
A/B/C are one atomic acceptance slice: A and B may prepare bounded inputs but
cannot publish or activate a partial root; C wires all three producers into
the A-owned publisher and only the complete three-file root is accepted.
Stable Symfony list remains the active recovery surface before that gate.

The configured lock is the fixed regular no-follow file
`.blackops-projection-build-set.lock`. A reader takes shared `flock` before
opening the root and all three same-fd no-follow regular files and holds it
through validation, selection, and rendering. A publisher takes exclusive
`flock` before creating, writing, or renaming any generation temporary or
content-addressed file and holds it through same-fd validation, root temp
fsync plus atomic rename, parent-directory fsync, and GC. GC removes only
paths unreferenced by the now-current root and bounded abandoned temporary
cleanup under that exclusive lock. A crash before root publication leaves the
old root intact; readers see only an old complete set or a new complete set.
Preparation-barrier, publish/GC/resume, crash, lock replacement/symlink/
special-file, mixed-generation, and root-before-artifact fixtures are
required. This lock lifecycle is the sole meaning of reader quiescence.

The shared strategy wire reflects the declared class without a leading slash:
`Inline::class` is `inline`, `Deferred::class` is `deferred`, and every other
strategy is `custom:<canonical-FQCN>`. The complete strategy wire is capped at
1024 UTF-8 bytes; 1025 is an actionable build failure. No-filter includes
custom strategies, and `--strategy=inline|deferred` matches only the exact two
built-ins. Same-short-name custom classes, long valid custom values,
1024/1025 boundaries, route/schedule rows, and both filters are fixtures.

### Access, query, and retention

The closed service access family is exactly
`audit.access.<purpose>.requested|unavailable|accessed` for purposes
`query|export|sign|verify`. `requested` and `unavailable` are durable;
`accessed` is required and durably persisted at the decision boundary. A
required append failure withholds the query result, export, signature, or
hosted verification result. Former `.allow`, `.deny`, `sign.allow`, and
`resource_not_found` forms are historical/non-normative only. Offline
verification has no canonical append claim. An access append is depth one and
does not recursively emit another access event.

`AuditQueryRequest v1` is the closed UTF-8 JCS object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}`. `access` is the exact
`AuditAccessContext v1`; `partitionId` is the non-null authorized
`system:global|tenant:<64 lowercase hex>` partition; `filters` is exactly
`{eventCategory:null|string,recordIdPrefix:null|string,
fromSequence:null|positive,toSequence:null|positive}` with null bounds or
`fromSequence<=toSequence`; `snapshotHeadSequence` is positive JSON-safe;
`limit` is 1..1000; and `cursor` is null only on the first page or a strict
`aqc:v1` cursor on later pages. Unknown fields, cross-tenant swaps, invalid
filters, and complete request JCS over 64 KiB fail before lookup.

`AuditQueryResult v1` is the closed object
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,nextCursor,reasonCode}`. Its exact state matrix is:
`ok` has an authorized non-null partition, positive snapshot head, a
non-empty 1..limit array of complete canonical `AuditRecord v1` projections,
`nextCursor` only when more rows remain, and null reason; `empty` has the same
authorized partition/head, `records:[]`, null cursor, and null reason;
`unavailable` has `partitionId:null`, `snapshotHeadSequence:null`,
`records:null`, `nextCursor:null`, and `reasonCode:"resource_unavailable"`.
There is no partial record. Records are authorized/decrypted and integrity
checked before projection, ascending by partition-global sequence, and a
concurrent append after the captured head is excluded. Complete result JCS+LF
is at most 1 MiB inclusive; cap+1 fails before allocation. First/middle/final,
empty/unavailable, decrypt/integrity/resource failure, and cross-tenant swap
fixtures are required.

The cursor wire is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`: one literal
dot, no padding, ASCII, and total length at most 2048 bytes inclusive. The
closed payload is `{schemaVersion:1,kind:"audit_query_cursor",partitionId,
filters,snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}` with
request bounds, positive `lastSequence`, and UTC six-fraction
`issuedAt<expiresAt`. The tag is exactly 32-byte HMAC-SHA-256 under the
role-bound `audit-query-cursor` key selected by `keyId` over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`; LP is U32BE length
plus exact bytes. Strict base64url/JCS/closed-field/role/bounds/expiry checks
and constant-time tag comparison occur before lookup or record decrypt. HMAC
cursor authentication is not record decryption or integrity verification.
First/middle/final/empty/unavailable, malformed payload/tag, partition/filter/
head/last substitution, expiry, key rotation, and cap+1 fixtures are required.

`AuditAccessContext v1` has exact nested actor/tenant unions and
`requestRef=oid:v1:<lowercase SHA-256 hex>` where the digest is over the
injective preimage
`LP("blackops.audit.access.request-ref.v1")||LP(JCS({purpose,
requesterActor,requesterTenant,requestedAt,nonce}))`. Actor and tenant are
closed NFC/no-control objects or null; `requestedAt` is UTC with exactly six
fractional digits; `nonce` is a noncredential NFC/no-control string of 1..128
UTF-8 bytes. A null-actor/null-tenant golden and present, Unicode,
substitution, and cap+1 fixtures are required. This request reference has no
raw target or untrusted tenant.

`AuditRetentionState v1` is metadata only and is the closed object
`{schemaVersion:1,kind:"audit_retention_state",stateId,target,policy,state,
version,previousStateHash,stateHash,updatedAt,idempotencyKeyHash}`. Its state
is only `retained|eligible_for_future_deletion`; it contains no embedded hold.
`target={partitionId,startSequence,endSequence,eventCategory|null}` and
`policy={id,version,retentionUntil}` have bounded exact fields, with
`startSequence<=endSequence`, trusted six-fraction UTC, and a complete JCS
cap of 64 KiB. `previousStateHash` is null iff version 1 and otherwise the
prior state hash. `stateHash=H(ENC("blackops.audit.retention-state.v1")||
ENC(JCS(object excluding stateHash)))`. The protected input key is 1..256
NFC/no-control bytes and
`idempotencyKeyHash=H(ENC("blackops.audit.retention-idempotency.v1")||
ENC(partitionId)||ENC(rawKey))`; raw key is never clear. `(stateId,target)`
is immutable identity, and P23-003G owns fenced CAS/idempotency.

`AuditHold v1` is a separate versioned append-only revision collection keyed
by immutable `holdId`: `{schemaVersion:1,kind:"audit_hold",holdId,target,
category,reasonCode,state,version,previousStateHash,stateHash,updatedAt,
placedBy}`. `state` is `active|released`; target and category bounds are
closed, `category:null` is wildcard, `placedBy={type,id}` is exact, and the
same previous/state hash and CAS rules apply. Multiple active holds may
overlap. A record is eligible only when retention state is eligible and zero
active holds match sequence/time/category in the same transaction snapshot.
Releasing one hold never releases another; two-active, one-release,
wildcard+specific, eligibility/hold race, retry/idempotency, and cap+1
fixtures are required. Physical deletion remains future P23-003H.

### Anchor and verification

`AnchorIntent v1` is the closed object with exactly
`{schemaVersion,kind,intentId,state,partitionId,checkpointId,checkpointHash,
checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,
expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,
acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,
receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,
leaseExpiresAt,version,updatedAt}`. IDs, refs, tokens, and idempotency values
are bounded NFC/no-control noncredential strings; hashes are lowercase 64-hex,
versions positive, and timestamps UTC with exactly six fractional digits. Only
`receiptSigningKeyRef` names a signing key; no external credential is stored.
Genesis has null predecessor fields and accepted version 1; otherwise the
predecessor version/hash are required and accepted version is predecessor plus
one. `acceptedObjectHash` equals `checkpointBytesHash` and the accepted object
tuple is stable.

The state/nullability matrix is exact: `prepared` has accepted object
version/hash/time and all four receipt fields null; `externally_accepted` has
the accepted triplet non-null and all four receipt fields null;
`receipt_inputs_persisted` has the accepted triplet plus
`receiptSignedAt,receiptSigningKeyRef,receiptBytesHash` non-null and
`anchorReceiptHash` null; `receipt_persisted` and `acknowledged` have all
accepted and receipt fields non-null. `receiptBytesHash` is the lowercase
SHA-256 of `ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||`
the exact unsigned receipt JCS+LF bytes; `anchorReceiptHash` is the unsigned
receipt semantic domain hash, not a signed-file raw digest. The receipt
`signedAt` equals persisted `receiptSignedAt`, `acceptedAnchoredAt<=signedAt`,
and ID/key/body/hash remain byte-identical on retry. Lease fields are both null
at rest or both non-null for one active fenced claim; expired reclaim obtains a
new token by CAS and stale owners cannot write. Version CAS forbids regression.
The external-accept/inputs-persisted, receipt-persist, and ack crash points,
sign-before-persist, read-after-write, parallel retry, and no-N+1 fixtures are
required.

The verifier uses one total function and spells the external trust input
`trustStoreSnapshot`. Stage 0 validates invocation mode, bounded context,
expected hash, and `trustStoreSnapshot` structure plus
`observedAt<=verifiedAt<validUntil` for every mode before any manifest or
listed-file open; malformed context or trust snapshot is code 19. Stage 1
validates bounded raw manifest/JCS/shape/bounds (10 before valid unsupported
17). Stage 2 self-hashes the parsed candidate and compares its export ID and
manifest hash with the externally pinned `expectedManifest` (mismatch code
16, zero file opens). Stage 3 checks export-key role/status (global 12,
refs null). Stage 4 verifies the signature (11, zero opens, refs null on
failure); only success makes export refs non-null and permits authenticated
signedAt checks. Stage 5 opens each listed root-confined file exactly once,
retains its bytes, and validates actual lengths/sums/digests (13); Stage 6
never reopens them. Stage 6 orders global checks as catalog graph
missing/extra/hash/semantic mismatch code 14, mode/coverage code 16, then
freshness head-set/current freshness code 18. Every Stage 6 global failure
returns `partitions:[]` with post-signature refs non-null. Stage 7 runs local
14 chain, 15 checkpoint/anchor/key/time, 16 scope, then 18 head chronology;
the first canonical partition failure wins. Current freshness is checked
against the authenticated head; as-of has null freshness and no current or
suffix claim. No manifest basename or role prefix is identity: signed
`{role,artifactId}` mapping and raw digest are the only identity.

## Historical Round 15 bounded correction checkpoint

Historical status at that checkpoint was `Correction Implemented / Review
Requested`; Round 15 was then-current and is now superseded by Round 16. It
was not Green, Accepted, or Decided. Stable 1.2 remains
the current release boundary; all BlackOps-owned projection and Audit changes
remain unreleased 1.3 proposal work.

The only projection wire is `ProjectionBuildSet v1` with dedicated
`blackops_command_projection`, `blackops_route_projection`, and
`blackops_schedule_projection` files and descriptor/file `inputHash` values.
Route fixed metadata is combined by the build producer, schedule metadata is
compiled by its build producer, and readers consume one dedicated file only;
there is no read-time HTTP join, direct Operation Manifest schedule reader, or
standalone legacy projection envelope. A reader takes shared flock on the
fixed regular no-follow `.blackops-projection-build-set.lock` before opening
the root and all three same-fd files and holds it through validation,
selection, and rendering. A publisher takes exclusive flock before creating
or renaming any generation, holds it through fsync/root atomic publication
and directory fsync, then performs bounded GC. Only old-complete or
new-complete sets are visible. The optional operation strategy filter is one
case-sensitive `--strategy=inline|deferred` value, at most once, with invalid
values rejected before Provider/compiler work; no-filter preserves Stable
output.

P23-001S was created and historically accepted, then reopened for bounded
corrections. Its current status is `Round 21 Accepted`; the former
side-effect-free Guide sentence is historical and the corrected Guide passed
fresh child General review. It is a non-1.3 prerequisite before P23-001
acceptance and P23-002A, not a new or uncreated task.

The historical Round15 diagnostic incorrectly reported a 149-byte preimage and
`b063819f7388aa541daab07c2e537df29b98648a81917929338bb63e4a990267` after
shell quote loss; that value is invalid and is not a contract. The canonical
request reference is
`oid:v1:HEX_LOWER(SHA-256(LP("blackops.audit.access.request-ref.v1")||
LP(JCS({purpose,requesterActor,requesterTenant,requestedAt,nonce}))))`, with
U32BE length-prefixed exact bytes. The independent null/null vector is pinned
as a 121-byte JCS and 165-byte LP preimage with SHA-256
`692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c` and
must be independently reproduced.

`AuditQueryRequest v1` and `AuditQueryResult v1` are closed JCS objects with
authorized partition, normalized filters, positive snapshot head, limit
1..1000, and the exact `ok|empty|unavailable` null matrix in Specification
105. The cursor is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, ASCII and
2048 bytes inclusive, with closed payload
`{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}` and a 32-byte
role-bound HMAC-SHA-256 tag under an exactly 32-byte secret key selected by a
1..128-byte NFC/no-control ASCII `keyId`, over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`. Strict decoding,
constant-time tag comparison, bounds, expiry, and key-role checks precede
lookup. Filter strings and cursor payload strings are NFC/no-control and capped
at 256 UTF-8 bytes unless a smaller bound is stated. HMAC cursor authentication
is not record decryption or integrity verification.

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
receipt_persisted|acknowledged`. Prepared starts with version 1, attempt 0,
and null accepted/receipt/chain fields; retryable prepared preserves a positive
attempt and bounded next availability. External submission claims increment
attempt exactly once, accepted/rejected/unavailable results preserve it, and
unavailable at attempt 32 becomes `externally_rejected/attempt_exhausted`.
Receipt and acknowledgement claims preserve attempt. Every mutation uses
expected version/hash and matching token CAS with exact +1; terminal states are
unclaimable, and post-acceptance crash/reclaim never regresses or loses the
active §9.6.1 receipt. The accepted object hash equals checkpoint bytes hash,
and same-body/idempotency response-loss retry preserves version, updatedAt,
and stateHash without N+1.

P23-003F owns checkpoint/anchor signing, reusable verification primitives,
and required `audit.access.sign.accessed` before signature release. P23-003G
owns query/export/hosted verification orchestration, expectedManifest pin
orchestration, and corresponding accessed events before release. Offline
verification has no canonical append. All four read families use
`.requested|.unavailable|.accessed` and `resource_unavailable`; former
allow/deny/resource-not-found forms are historical only.

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
checkpoint; Round 25 and earlier are historical evidence with separate General
and Audit review lanes. Parent acceptance is not yet complete. Remaining:
Orchestrator must rerun the complete Local Gate after this source-changing
management correction, then route fresh separate Sol xHigh General/Audit review
and parent acceptance; P23-001R and ordered Phase 23 work follow acceptance. No
1.3 capability is public or released.

## Historical Round 26 Suggested Next Action

Run the complete Local Gate against this exact Round 26 candidate, then route
fresh separate Sol xHigh General/Audit reviews and parent acceptance. After
acceptance, route P23-001R and ordered Phase 23 work; no external action is
authorized before review.

## Historical Round 16 bounded correction checkpoint

Updated At: 2026-08-19T21:18:38+09:00

Round 16 was the then-current `Local Gate Green / Independent Review Requested`
checkpoint, not Accepted or Decided, and is now historical. The requestRef formula remains
the exact LP/JCS construction; correctly quoted Node and Docker PHP recompute
the null/null vector as literal JCS 121 bytes, LP preimage 165 bytes, and
SHA-256 `692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.
The Round15 149-byte/`b063819f...` result is a historical invalid diagnostic
caused by shell quote loss. Query/cursor, AnchorIntent, ProjectionBuildSet,
Website, and Stable/unreleased boundaries remain unchanged.

The fresh complete exact-15 Local Gate is Green. Remaining: fresh child General
review, parent General/Audit review, and Orchestrator acceptance; then P23-001R
and ordered downstream. Suggested Next Action: freeze the exact-15 hashes and
route the reviews.

## Historical Round 17 correction checkpoint

Updated At: 2026-08-19T21:59:03+09:00

Round 17 was the then-current `Correction Implemented / Review Requested`
checkpoint, not Green, Accepted, or Decided, and is now historical. It closes the child General
class-string/prebuilt-instance and inline HTML visibility findings while
keeping the Stable `1.2` boundary and unreleased `1.3` proposal unchanged.
The fresh Round 17 Local Gate is PASS; child General re-review, parent
General/Audit review, and Orchestrator acceptance remain. The exact 15-path
union is unchanged and no commit, stage, push, CI, deploy, publication, or
release action occurred. The follow-up wording is the exact
format-independent generation/reuse boundary.

## Historical Round 18 correction checkpoint

Updated At: 2026-08-19T22:20:07+09:00

Round 18 was the then-current `Correction Implemented / Review Requested`
checkpoint and is now historical Changes Requested (`P1=0/P2=1/P3=0`). The existing Website guard then
models native closed `details`/`dialog` visibility: closed `details` expose
only the first direct `summary` until `open`, plain closed dialogs are hidden,
and an explicit non-`none` inline display override can expose a closed dialog;
`display:none` remains hidden. Paired primary/alias fixtures cover required
markers, hidden/open old claims, first-summary claims, visibility overrides,
and display-none cases. Round 17 is historical Changes Requested.

The fresh Round 18 Local Gate is historical. Remaining at that checkpoint was
fresh child P23-001S General re-review, then parent General/Audit review and
Orchestrator acceptance; after acceptance P23-001R and the ordered downstream
DAG remained.
Stable `1.2` is current, Phase 23 `1.3` remains unreleased, and no external
action occurred.

### Suggested Next Action

The historical next action was to freeze the exact-15 Round 18 Local Gate and
route fresh child General review; Round 19 now supersedes that checkpoint.

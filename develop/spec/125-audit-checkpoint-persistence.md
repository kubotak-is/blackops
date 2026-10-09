# Specification 125: Audit Checkpoint Persistence

Status: Decided — bounded implementation Accepted and committed; BlackOps 1.3 unreleased

This is the most-specific persistence-only contract for
[P23-003F2](../orchestration/tasks/P23-003F2-audit-checkpoint-persistence.md)
and [D175](../decisions/175-audit-checkpoint-persistence.md).
Accepted B2/C3 and [F1](122-audit-signed-artifact-primitives.md) are prerequisites;
ordinary D/E are not new dependencies. Follow specifications 105/106/109.
The Green r2 preparatory contract is adopted with its three closed findings.
The Task owns the current47-file scope as amended in sections11–15, migration
reservation and verification. The Report records local implementation Acceptance
on2026-10-09; signing/release/seal and the later1.3 gates remain outside this slice.

## 1. Boundary and adopted Root choices

F2 proves a complete closed PostgreSQL segment, commits crash-stable unsigned
checkpoint inputs, and persists exactly matching F1-verified complete signed
bytes. It does not sign, retrieve/release complete bytes, seal a segment,
select a seed/provider, authorize a caller or activate a service. Tests may
use F1 to sign the prepared body between the two persistence operations.

| Choice | Adopted contract |
| --- | --- |
| R1: child boundary | Persistence only. Ordinary/control signing and external release require the later separately reviewed F integration contract. A supplied Draft, boolean, callback or SQL row cannot satisfy it. |
| R2: seal ordering | F2 never writes sealed. Later F5 performs the final fenced closed-to-sealed CAS only after the matching durable checkpoint, accepted matching durable anchor receipt and mandatory current trust/freshness proof. Failure leaves closed. |
| R3: identity/time | Generate one UUIDv7 for each new committed preparation using the existing internal generator. This is an implementation subset of F1's generic ID wire. sealedAt is the frozen B2 closed_at boundary, not the instant the state column becomes sealed. signedAt is the once-captured trusted signing-input preparation time, not each physical Ed25519 call's time. Reject clock time below closed_at; do not synthesize a future time. |
| R4: key/trust | Caller supplies the selected checkpoint key ref and already authenticated snapshot. F1 validates shape/material/time; F2 pins ref and raw-public-key fingerprint. F2 never acquires a private seed or authenticates snapshot provenance. The later caller must authenticate the complete trusted context. |
| R5: immutable recovery | After preparation commit, ID, unsigned bytes, token/version and fingerprint never change. Unavailable/revoked/expired keys cannot cause silent replacement. No discard/supersede/cancel API. A failure before commit reserves no durable identity. |
| R6: roots/resources | Two additive tables and one irreversible migration. Standalone owned REPEATABLE READ roots only; no ambient join/savepoint. Immediately after begin execute literal SET TRANSACTION ISOLATION LEVEL REPEATABLE READ before data/lock SQL. Complete proof uses fixed 32-row keyset pages and constant accumulated state, without a record-count cap. |
| R7: errors/results | Reuse AuditStoreFailure/Exception with the mappings below. prepare returns unsigned inputs only; persist returns void after commit. Neither is authorization, seal acknowledgement, freshness proof or G verification. |
| R8: paths | Four entry types below and the exact eleven internal helpers in §11. Root reserves Version20261001000000.php on 2026-10-07 after confirming absence and the actual 13-Framework/5-Application baseline. Exact 33 files and owners are in the Task; no scope is inferred from this table. |

R1–R8 are adopted. R9 in section 7 is a recommendation for mandatory later F
authority; its proposed wire and caller map are not executable F2 authority.
The Task's two count-support additions and explicit migration static coverage
do not change this technical contract or weaken any existing quality gate.

## 2. Fixed requirements inherited unchanged

| Fixed existing authority | F2 obligation |
| --- | --- |
| spec105 §9.6 / D149 | B2 alone closes and installs the successor under its partition fence. F consumes only the persisted boundary. Closed/sealed preserve final sequence, head, successor and close token. No AuditRecord mutation, new B2 API or automatic close policy. |
| spec105 §9.6.1 / F1 | Exact 18-field complete checkpoint, exact 16-field unsigned body, existing canonical JCS, typed encoding/hash domains, pure Ed25519, bounded strings/times and safe integers. F2 must not add a wire field, alternate domain, wrapper signature, whitespace or LF to these bytes. |
| spec105:4110-4134 | Complete segment, partition-global ranges, `recordCount=last-first+1`, genesis previous checkpoint null, non-genesis immediate predecessor checkpoint hash, no gap/fork/overlap. First/head hashes come from verified records. |
| spec105:4178-4183 / spec106:2964-2969 | Complete bytes durable before seal acknowledgement; same ID + identical complete bytes/hash is idempotent, same ID + different bytes/hash or another checkpoint for that segment is an integrity failure. Crash-before-ack/replay proof is required. |
| F1 §§1,6-8 | Trusted snapshot/time provenance is an external caller precondition, not established by semantic self-hash. Selected role is checkpoint; current active key, material uniqueness, validity and no backdating expired/revoked keys. F1 owns canonical validation and math. |
| spec105:4170-4176 / spec106:3046-3052 | Ordinary signatures require `audit.access.sign.accessed` durably committed before release; failure withholds bytes. Sole exemption is framework-derived exact `system:global/audit-control-v1` target/role; caller choice cannot establish it. |
| accepted C3 / actual Catalog | `sign.accessed` is **Required**, routed through the accepted canonical append path; sign requested/unavailable are Durable. “Durable before release” does not authorize changing accessed to an Outbox event. |
| spec105:4616-4628 / D154 | Checkpoint/receipt artifact outcomes feed later D-control idempotent ingress; F does not directly append checkpoint-control AuditRecords. D-control waits for Accepted F, so F2 must not depend on an implemented D-control adapter or call it. |

## 3. Exact internal callable inventory

The four entry types and eleven helpers in §11 live under `src/Internal/Audit/Checkpoint/`, namespace
`BlackOps\Internal\Audit\Checkpoint`. None has `PublicApi`, public data
properties, dynamic dispatch, a plugin/provider interface or runtime registration.
No source outside the exact owned internal graph calls them; the migration uses
only the schema. Direct tests may construct the graph.

| Type | Exact public surface |
| --- | --- |
| `PostgreSqlAuditCheckpointSchema` (final readonly) | `__construct(PostgreSqlMigrationSchema schema)`; `schemaName(): string`; `preparationsTable(): string`; `checkpointsTable(): string`; `statements(): list<string>` |
| `PostgreSqlAuditClosedSegmentReader` (final) | `__construct(Connection connection, PostgreSqlAuditSchema schema, PostgreSqlAuditStoredRecordHydrator hydrator, AuditProtectedBodyCodec protection, AuditPartitionIdDeriver partitions)`; `proveInCurrentTransaction(?AuditTypedReference expectedTenant, string partitionId, string segmentId): array` with exact proof shape below. It never returns record bytes or records. |
| `AuditCheckpointPreparation` (final readonly) | Private constructor; `static fromRow(array row): self`; `unsigned(): array` (exact F1 unsigned checkpoint); `boundary(): array{partitionId:string,segmentId:string,closedStateVersion:int,closeFencingToken:string}`; `signingKeyFingerprint(): string`; `__debugInfo(): array` and `__serialize(): array` return `[]`; `__unserialize(array): void` always throws a fresh IntegrityInvalid. |
| `PostgreSqlAuditCheckpointStore` (final) | `__construct(Connection connection, PostgreSqlAuditCheckpointSchema schema, PostgreSqlAuditStoredRecordHydrator hydrator, AuditProtectedBodyCodec protection, AuditPartitionIdDeriver partitions, ClockInterface clock, Uuidv7Generator ids)`; `prepare(?AuditTypedReference expectedTenant, string partitionId, string segmentId, string signingKeyRef, array trustedSnapshot): AuditCheckpointPreparation`; `persist(?AuditTypedReference expectedTenant, string partitionId, string segmentId, string completeBytes, array trustedSnapshot): void` |

Use `Psr\Clock\ClockInterface` and the existing internal
`BlackOps\Internal\Identifier\Uuidv7Generator::generate(DateTimeImmutable)`.
The store constructs exactly one reader from its own identical Connection,
hydrator/protection/partition helpers and a B2 schema using its validated schema
name. This prevents accepting a reader on another Connection/schema without
adding identity getters or a general transaction wrapper API. Its construction
and the reader's call sites are fixed by the new guard.

Each of these fifteen types has at most ten own declared methods including
private helpers; constructors stay below the unchanged nine-parameter threshold.
The exact internal graph, direct transaction operations, signatures and state in
§11 replace the initial four-file decomposition. Operation callbacks and variable
invocation remain prohibited. Preserve indirect-call guards for each exact owner.
No Mago expectation, baseline, threshold, dependency or extra helper/type beyond
§11 is authorized. Measure the amended code with unchanged settings; further
scope requires a concrete measured amendment, never an automatic additional split.

All owned methods carrying tenant, IDs, hashes, rows, snapshots, bytes or times
use SensitiveParameter. Preparation retains only one private
SensitiveParameterValue snapshot; raw bytes/identities must be absent from
debug/serialize/JSON/var_export/exception trace surfaces. A caller's array or
constructed Preparation is never persistence authority: persist accepts bytes,
then reloads the authoritative row and proves the database boundary itself.

## 4. Closed-segment proof seam

`proveInCurrentTransaction()` accepts only auto-commit enabled, active exact
nesting level 1; it does not begin/commit/rollback. The store itself admits only
inactive level 0 and owns that root. Reject other active/level combinations and
auto-commit false before SQL, Clock/UUID, key lookup, hydration or decryption;
never repair the Connection using setAutoCommit.

Before a read, derive the canonical partition from expectedTenant with the
existing helper and require exact equality. Validate canonical UUIDv7 segment ID.
Use the existing D149 partition advisory lock, not a new near-equivalent key:

`blackops.audit.partition.v1:<validatedSchemaName>:<canonicalPartitionId>`

Then lock the matching partition row and target segment row in that order.
The partition row query includes the exact tenant-pair predicate. System uses
both columns `IS NULL`; tenant uses both equal bound values. Missing/cross-tenant
state yields the same finite failure with zero hydration/decryption.

The exact proof result is the following non-wire calculation array:

`{partitionId,segmentId,state,stateVersion,firstSequence,lastSequence,recordCount,
firstRecordHash,chainHeadHash,predecessorRecordHash,previousSegmentId,
successorSegmentId,closeFencingToken,closedAt}`.

It has no signature/key/authorization claim. The reader obtains all fields from
the current database and complete decoded record chain, never from a supplied
AuditSegmentCloseResult or arbitrary CompleteAuditRecord object.

Required proof, all under the same root/fence:

1. Validate exact persisted partition/segment scalar shapes, canonical DB integer
   grammar, closed/sealed lifecycle, version at least 2, nonempty range/head,
   timestamp grammar, closedAt >= openedAt and target != active segment. Captured
   closed versions are 2..9007199254740990 inclusive, reserving the seal increment.
   Open/empty/unknown is not a
   sealable candidate. A sealed row is readable solely for an already persisted
   exact checkpoint retry; it cannot create a new preparation or checkpoint.
2. Read exactly one same-partition successor: successor ID differs, first equals
   target final + 1, predecessor equals target head, and successor opened_at
   equals target closed_at. It may already be closed/sealed after later rotations;
   do not require the immediate successor to remain the active open segment.
   Each closed/sealed neighboring row used in this proof has version at least 2
   and its own closedAt >= openedAt; an open successor retains the B2 open-state
   shape. This local proof does not traverse the whole segment history.
3. Genesis has first 1, null record predecessor and no incoming segment edge.
   Otherwise read exactly one immediate prior segment by its successor edge;
   prior final + 1 equals first, prior head equals target predecessor, and prior
   closedAt equals target openedAt. Also verify
   the prior endpoint record at first - 1 (same partition, different segment).
   No prior/next row substitution or self-edge is accepted.
4. Recompute the existing `aft:v1:` close token using D149's typed ENC formula
   and exact persisted partition/segment/final/head/successor/closedAt. There is
   no reusable public token helper today; implement that small existing formula
   inside its Graph.closeToken helper (§11), with independent expected-vector proof. Do not substitute
   U32BE AuditLengthPrefix or edit the B2 store to expose a general helper.
5. Before protected reads, reject any target-segment record with a different
   persisted tenant pair using a value-free existence predicate. Fetch records
   with partition + segment + exact tenant predicate, sequence ascending,
   keyset pagination and fixed `LIMIT 32`. Do not filter to the declared first/
   final range: a stored record outside it must be detected, not silently hidden.
   No page OFFSET, accumulated record list or externally selectable page size.
6. Reuse D149 exact17 projection (physical exact14 plus action/resource index
   triplet), its UTC to_char projection and PostgreSqlBytea normalization.
   Hydrate exact14, unprotect once per record, and compare the three physical
   index values to the decoded logical action/resource. Existing unprotection
   calls AuditRecordCodec::decode, so it recomputes canonical content/record hashes
   and validates AAD, tenant and Catalog semantics. Do not trust clear hash columns
   or construct CompleteAuditRecord as proof. Retain only rolling sequence/head,
   first hash and count; release each page before fetching the next. Each decoded
   record's recordedAt must be at most the frozen closedAt; do not require it to
   be at least segment openedAt because older durable ingress may be appended.
7. Require every sequence from first through final exactly once, exact prior hash
   at each edge, exact segment/partition identity, final count and head equality.
   Missing, extra, re-ordered, duplicate, cross-segment, invalid canonical body,
   protected-body corruption and any chain divergence fail. Empty result fails.
   Arithmetic never exceeds JSON-safe bounds; continuation uses last seen value
   rather than incrementing MAX. This bounds memory, not the permitted total
   number of segment records or execution time.

Runtime Catalog provenance stays unchanged. The injected protection/record
codec uses the existing concrete ImmutableAuditEventCatalogLookup, including
trusted Framework provider identity checks. F2 does not decode arbitrary export
Catalog bytes into a runtime lookup, alter A2, relax Framework-owner checks or
implement F6's authenticated offline Catalog seam. Missing historical Catalog
availability fails the proof; self-hash or `owner=framework` is not provenance.

## 5. Preparation and checkpoint transactions

### Prepare

1. Enforce standalone/identity preconditions, begin the owned root, execute
   `SET TRANSACTION ISOLATION LEVEL REPEATABLE READ` immediately, then obtain the
   full proof. Validate all row/time/chain invariants before Clock/UUID work.
2. Load preparation by exact partition + segment. If present, hydrate its exact
   row, compare closed token/boundary and every record-derived unsigned field.
   Apply the same immediate predecessor checkpoint structural/binding comparison
   as step 3; no replacement predecessor hash is allowed on retry.
   signingKeyRef must equal the stored choice. Return the same unsigned bytes
   after commit; zero Clock/UUID calls and no selection from a replacement
   snapshot. This is a storage replay, not current trust validation. If the
   segment is sealed, require the already complete matching checkpoint and the
   one expected later seal version increment described below.
3. For new preparation require state closed and version 2..9007199254740990.
   Genesis has no predecessor checkpoint. A later segment requires the immediate
   predecessor's already persisted complete checkpoint: decode its exact F1 bytes,
   recompute hash and compare partition/segment/range/head to the proved prior
   segment and stored hash. Do not search an arbitrary latest checkpoint or permit
   a gap. Its previous hash becomes this body's previousCheckpointHash.
   This structural lookup alone does not re-establish the predecessor's current
   key validity; its signature is checked after the Clock/trust step below.
4. Read the trusted Clock exactly once; canonicalize to UTC six microseconds.
   Reject invalid/unavailable time or now < closedAt as ResourceUnavailable.
   Validate the complete supplied trusted snapshot at this time and select its
   checkpoint-role entry for signingKeyRef using F1. Verify the immediate
   predecessor checkpoint, if any, against that snapshot/time using F1 before
   reserving the new inputs. Full historical graph/freshness proof remains F6.
   Generate one UUIDv7 with that exact time; malformed/colliding ID fails without
   automatic regeneration.
5. Build the F1 unsigned body from the proof, predecessor checkpoint hash, generated
   ID, fixed sealedAt/signedAt choice, and selected key ref. F1 signingInput validates
   the closed body and canonicalizes it. Store its exact unsigned `bytes`, along
   with the selected fingerprint and closed token/version. No seed or signature
   generation occurs. INSERT exactly one immutable preparation; commit before
   returning a Preparation. A commit exception returns no result.

### Persist

1. Enforce standalone/identity preconditions and bounded complete F1 codec parsing
   before SQL; enter the root, immediately execute the same root-local isolation
   SQL, and obtain the full proof again. A close-result
   object from a previous call cannot replace this proof.
2. Load and hydrate the preparation with exact tenant/partition/segment binding.
   Require its ID and every unsigned body byte to equal the supplied complete
   checkpoint after removing only checkpointHash/signature. Require the stored
   boundary to match current proof/range/first/head/predecessor again. Reload the
   immediate predecessor checkpoint as in prepare step 3 and require its validated
   hash still equals previousCheckpointHash; no replacement hash is allowed.
   Current selected-key material is checked only after full F1 trust validation
   in step 4. No new body or identity is constructed on this path. Missing
   preparation or changed close boundary fails.
3. If a complete checkpoint already exists, require identical ID, complete bytes
   and recomputed/stored checkpointHash. Identical is storage idempotent success;
   any different bytes/hash is IntegrityInvalid. This branch makes zero Clock/
   UUID calls and does not claim current trust or release eligibility.
   For state closed require the captured version; for state sealed allow only
   captured version + 1, unchanged close token/boundary, and the existing exact
   complete row. A sealed row with no complete checkpoint is invalid. F2 writes
   no seal, and reading this future-compatible state does not claim F5 acceptance.
4. For a first complete insert require state closed at the exact captured version
   and token. Read trusted Clock exactly once and verify the complete checkpoint
   and its immediate predecessor, if any, with F1 against the supplied already
   trusted snapshot at that current time.
   The selected checkpoint entry's ref/fingerprint must match the preparation.
   Validity/revocation is checked now, not merely at preparation time. F1's
   signature-before-authenticated-signedAt ordering remains intact.
5. INSERT the exact no-LF bytes/hash, joined/fenced to the same immutable preparation
   and current closed version/token. An affected-row count other than exactly one
   fails and rolls back the owned root. No UPSERT replacement, UPDATE or DELETE.
   Commit before void success. No row in B2, C3 or an anchor table is changed.

Use binary DBAL binding for bytea and normal bound values elsewhere. Do not use
DB CURRENT_TIMESTAMP or session timezone as the trusted clock. Do not store
trust snapshots, private key material or duplicate mutable timestamp columns;
the canonical unsigned body is the frozen input authority.

The two-table construction gives one preparation and hence at most one complete
checkpoint per store-wide segment. Proved cross-partition generated-ID collisions
and visible different immutable identities fail as IntegrityInvalid; an invisible
concurrent winner or serialization failure is StorageUnavailable as defined below.
There is no ID retry or existing-row replacement.
One successful prepare and one successful persist are separate roots. No
transaction, native lock or incomplete acknowledgement spans signing/external I/O.

Transaction failures are tested as actual native outcomes, not only mocked
exceptions. On owned begin/commit/rollback ambiguity, discard returned results,
attempt only the appropriate owned cleanup, close/quarantine the failed Connection
exactly once and return a fresh safe storage failure. Never reuse an unknown
native transaction state. Recovery uses a new Connection/store, reloads persisted
state and compares immutable identity. A genuine commit followed by response loss
may have persisted the row; it must not be reported as known rollback or cause
new inputs/bytes on retry. No caller-owned transaction is touched.

One call makes exactly one root attempt. The advisory-lock SELECT can establish a
REPEATABLE READ snapshot before waiting. If a concurrent winner then commits an
F2 row, the losing root can remain unable to observe it even after obtaining the
fence: F2 does not update the locked B2 rows. A resulting uniqueness/serialization
failure proves neither changed bytes nor a different identity. Return a fresh
StorageUnavailable after owned cleanup and close; do not query for the winner in
the stale/failed root or silently retry. Recovery is an explicit new Connection,
store and root, which reads and compares the immutable committed winner. Only a
visible proved conflicting ID/bytes/hash or other immutable binding is
IntegrityInvalid. No caught-message inference or durable input regeneration.

The literal SET TRANSACTION statement changes the current owned root only. Do not
use DBAL setTransactionIsolation here: its PostgreSQL SET SESSION CHARACTERISTICS
statement does not establish the current root's isolation after begin and changes
the session default. Assert the actual native isolation as well as SQL ordering.

## 6. Exact DDL

PostgreSqlAuditCheckpointSchema validates/quotes schema exclusively through the
existing PostgreSqlMigrationSchema. No alteration of the B2 three-table schema,
original migrations, protected-body format, tenant index triplet or StoragePurpose.
Two CREATE TABLE statements, in the order below, own all names and no extra
index/trigger/function/default. Automatic indexes of PK/UNIQUE are included in
the expected live inventory; no CHECK is presented as cryptographic validation.

`audit_checkpoint_preparations` exact seven columns, in this order:

| Column | SQL declaration |
| --- | --- |
| checkpoint_id | uuid NOT NULL |
| partition_id | varchar(71) NOT NULL |
| segment_id | uuid NOT NULL |
| closed_state_version | bigint NOT NULL |
| close_fencing_token | varchar(71) NOT NULL |
| unsigned_checkpoint | bytea NOT NULL |
| signing_key_fingerprint | char(64) NOT NULL |

Named constraints:

- `audit_checkpoint_preparations_pk`: PRIMARY KEY(checkpoint_id).
- `audit_checkpoint_preparations_segment_uq`: UNIQUE(segment_id).
- `audit_checkpoint_preparations_segment_fk`: immediate, non-cascading
  FOREIGN KEY(partition_id,segment_id) REFERENCES audit_segments(partition_id,segment_id).
- `audit_checkpoint_preparations_identity_check`: canonical partition regex and
  canonical UUIDv7 regex on both ID `::text` values (existing B1/B2 grammar).
- `audit_checkpoint_preparations_version_check`: closed_state_version between
  2 and 9007199254740990 inclusive, reserving the one eventual seal increment.
- `audit_checkpoint_preparations_token_check`: close_fencing_token matches
  `^aft:v1:[0-9a-f]{64}$`.
- `audit_checkpoint_preparations_bytes_check`: octet_length(unsigned_checkpoint)
  between 1 and 1048575 inclusive. Application F1 validation is stricter about
  completing the representation within its bound.
- `audit_checkpoint_preparations_key_check`: fingerprint matches `^[0-9a-f]{64}$`.

`audit_checkpoints` exact three columns, in this order:

| Column | SQL declaration |
| --- | --- |
| checkpoint_id | uuid NOT NULL |
| checkpoint_hash | char(64) NOT NULL |
| complete_bytes | bytea NOT NULL |

Named constraints:

- `audit_checkpoints_pk`: PRIMARY KEY(checkpoint_id).
- `audit_checkpoints_preparation_fk`: immediate, non-cascading
  FOREIGN KEY(checkpoint_id) REFERENCES audit_checkpoint_preparations(checkpoint_id).
- `audit_checkpoints_hash_uq`: UNIQUE(checkpoint_hash).
- `audit_checkpoints_hash_check`: `^[0-9a-f]{64}$`.
- `audit_checkpoints_bytes_check`: octet_length(complete_bytes) between 1 and
  1048575 inclusive; neither LF nor an unsigned body is a legal Product insert.

Join through preparation for every partition/segment checkpoint read. The
preparation's unique segment and the checkpoint's FK+PK establish one checkpoint
per segment without duplicating mutable partition/segment columns in both tables.
In-Product immutability is INSERT-only, exact matching retries and absence of
update/delete APIs. This is not a claim that an administrator cannot tamper with
the database; read/proof/crypto negatives must detect such tampering.

Preparation row hydration requires exact key order/shape, PostgreSqlBytea for
bytea, D149 canonical integer parsing and F1 scalar/body validation. For the
stored unsigned flat object: raw cap first, bounded native JSON decode with
maximum depth 2, exact scalar fields via F1 signingInput, then exact equality of
stored bytes to returned canonical unsigned bytes. This rejects nesting, duplicate
keys, alternate numbers/escaping, normalization and trailing data without passing
deep input to the reused recursive decoder. It is not a second general JCS codec.

Reserved migration path: `migrations/postgresql/Version20261001000000.php`.
It follows the existing configurable constructor and schema-statements pattern;
down is irreversible. Root reserved the version at Task issuance on 2026-10-07. Unit/live PG
proof compares exact columns, types, defaults, constraints, index names, FK
deferrability and action; missing and unexpected names fail. Fresh install and
upgrade from all currently applied predecessor migrations are both mandatory.

## 7. Ordinary signature release and control exemption: hard handoff

F2 does not have a sign function, a complete-bytes getter, export method, success
response callback, authorization boolean, optional audit flag or caller-selected
control role. `prepare().unsigned()` contains no signature. `persist()` receives
already computed bytes and returns no bytes. Its internal durable storage is not
external signature release or final seal acknowledgement. This makes F2 useful
without accepting the currently undefined sign service contract.

Before any later ordinary caller may release/pass checkpoint or receipt bytes to
an external anchor, file/response/export consumer, its separately reviewed F
orchestration must:

1. Establish the exact trusted sign target/context and evaluator provenance;
   current Query and Operation-read context wires cannot be reused.
2. Bind artifact kind, identity and bytes/hash to the same authorization and
   sign-access identity, then build the fixed trusted Catalog Draft. Root must
   specify that exact wire and binding; this proposal deliberately assigns none.
3. Use accepted AuditDeliverySelector/Coordinator with the exact Required
   `audit.access.sign.accessed` definition and null idempotency key; successful
   standalone return means the canonical Store-owned root committed. Append,
   policy, binding, transaction or ambiguous commit failure releases zero bytes.
   Never call appendRequired directly from F or downgrade to durable Outbox.
4. Only then perform the authorized release; retries/restarts must re-establish
   authorization and current trust under that later contract. Required delivery
   is not falsely advertised as exactly-once because its key must be null.

The trusted control exemption needs separate closed provenance: exact framework
control target/role derived from an accepted internal control producer/path.
`tenant=null`, `partitionId=system:global`, a segment ID, a signed artifact,
`owner=framework`, a caller boolean or a resource label alone cannot prove the
`audit-control-v1` stream family. B2's segment row has no such provenance field.
F2 treats every segment uniformly and grants **no** exemption. A later internal
control path must prove provenance without accepting a caller selector or making
D-control implementation a prerequisite of Accepted F. The two recursion
exemptions (sign-access and checkpoint/receipt control ingress) stay distinct.

### R9: recommended later authorization decision, for Root to adopt and review

Name this mandatory remaining work **F signing-release authorization and trusted
control provenance**, integrated with F5/final F qualification. It is not an
optional follow-up, a new artificial child, or a request for external permission.
Root can settle the following bounded implementation choices in its later exact
authority. They are recommendations here, not additions to F1/F2 or existing wire.

Recommended separate ordinary sign target:

```text
{schemaVersion:1,kind:"audit_sign_target",artifactKind,partitionId,segmentId,
 artifactId,artifactHash}
```

artifactKind is exactly `audit_checkpoint|audit_anchor_receipt`. artifactId is
the corresponding checkpointId/anchorReceiptId; artifactHash is exactly F1's
unsigned-body checkpointHash/anchorReceiptHash. The target's partition/segment/
ID/hash must be recomputed/compared to the same artifact; unsigned hash already
binds signedAt and signingKeyRef and, for a receipt, its checkpoint-byte digest.
This permits authorization before mathematics without a circular signature hash.
There is no generic resource map, caller-selected algorithm, new hash domain or
reuse of the Query target. Validate all scalar grammar/caps through F1.

Recommended context (separately versioned from Query):

```text
{schemaVersion:1,kind:"audit_sign_context",actor,tenant,purpose:"sign",target,
 authorizationDecision,requestedAt,nonce,requestRef}
```

actor/tenant are the existing null-or-exact-typed-reference shapes, validated with
existing typed-reference rules. The target partition must equal the partition
derived from the same context tenant; context substitution cannot redirect it.
Decision is `allow|deny|unavailable`; requestedAt is trusted UTC microseconds. The trusted
Framework operation captures a fresh 16-byte CSPRNG nonce and encodes 32 lowercase
hex bytes. Reuse the existing service-access request-ref formula with purpose
sign and this exact target, rather than creating another algorithm:

`oid:v1:` + lowerhex SHA-256 of
`LP("blackops.audit.access.request-ref.v1") || LP(JCS({purpose,actor,tenant,
target,authorizationDecision,requestedAt,nonce}))`.

Only the actual trusted AuthorizationEvaluator integration produces this context
after making its decision. Private construction/closed factory ownership and
architecture caller maps must establish provenance; a valid hash, serialized
array, boolean allow or user-selected actor/tenant does not. Requested and
unavailable use the Catalog's Durable path and their later-frozen idempotency
identity; accessed uses Required/null key. The precise trusted actor/execution
source, authorization policy, requested-ingress timing, Durable idempotency
identity, denial/unavailability precedence and Draft field mapping must be
adopted and tested with the actual integration. In particular, do not infer
Operation-read requested-before-authorization ordering or a request key from this
post-decision context alone. These are exact remaining Root choices; a wire alone
cannot establish them.

Recommended control provenance is a distinct, final internal Framework control
issuer entry with fixed target/family `system:global/audit-control-v1`. Its accepted
Framework maintenance caller creates a private control-origin capability;
neither target/family/role, actor, tenant nor a serialized capability is accepted
from an ordinary request. It selects the closed system segment internally and
permits only the F1 checkpoint/anchor artifact kinds. Ordinary signing of the
same system partition remains ordinary. The exemption follows the trusted
invocation's origin and exact fixed target, not an inference from record contents
or a new `isControl` flag. Cryptographic roles remain checkpoint/anchor, never a
new F1 role named audit-control-v1.

Root must confirm that invocation-origin interpretation of the control family,
then freeze the concrete issuer, private capability factory and exact Framework
caller map before implementation. If a persisted stream-origin claim is instead
required, current B2 rows cannot prove it; Root must explicitly define its trusted
producer and durable binding. Do not classify every system segment as exempt.
Both choices can be decided by Root; neither requires asking the owner merely
because the detail was previously reserved. Full ordinary/control proof includes
same unsigned artifact with substituted actor/tenant/ID/hash/nonce, forged context,
wrong entry origin, attempted control downgrade, Required append failure and
commit response loss (zero bytes), legitimate control no-recursion, and restart
recovery with current trust. Final F Acceptance cannot omit these cases.

## 8. Seal/crash sequence across remaining F

| Stage | Durable state / acknowledgement limit |
| --- | --- |
| B2 close committed | Exact closed boundary plus successor; no checkpoint or seal claim. |
| F2 preparation committed | Immutable ID/body/time/key binding; segment remains closed. Crash resumes those inputs. |
| Pure signing / first persist failure | No replacement inputs; segment closed. A failed persist returns no bytes/result. |
| F2 complete checkpoint committed | Immutable signed checkpoint durable; segment still closed. Lost response retries same complete bytes/hash. |
| Later authorized release and AnchorIntent | Ordinary accessed-before-release or accepted derived control exemption; committed intent/call markers and A5 state contract. No external call from F2. |
| Later external accepted receipt, locally durable | Matching complete checkpoint and receipt, acknowledgement/reconciliation rules satisfied; still closed until all final seal conditions hold. |
| Later F5 final CAS | Revalidate authoritative checkpoint/receipt binding, accepted external outcome, required trusted context/freshness and current signature/key validity. Under B2 partition fence, CAS exact closed version/token/boundary to sealed, version +1, preserving all boundary columns. No unconditional SQL UPDATE, no rollback to open. |
| Later D-control / G closeout | Typed immutable F outcomes feed artifact-ID keyed D-control ingress before applicable final orchestration acknowledgement; no circular prerequisite on that adapter for F's local primitive Acceptance. |

Any required condition failing **before** the final CAS keeps closed. A key
revoked or snapshot expired after a previously valid sealed transition causes
later verification failure; it does not mutate history back to closed. There is
no promise that persistence alone authenticates present freshness or that F2
void success means the whole sequence completed.

F5 must include the exact current-context/freshness validation it needs, or consume
an already Accepted primitive, before enabling that CAS. It cannot wait on a
later F6 aggregate Acceptance that itself depends on F5. Root must place that
implementation in the later F authority without adding an implicit D/G edge or
an artificial child; F6's complete offline graph/integration closure can follow.

## 9. Failure mapping and meaningful verification matrix

F2 storage errors retain the accepted fixed message/code/no-cause behavior:

| Condition | Existing finite failure |
| --- | --- |
| Invalid shape/body/hash/signature/trust/material; directly observed hydration/row corruption; complete same-ID different bytes/hash; visible second candidate for segment; inconsistent predecessor or fence; independently proved graph/index/metadata divergence after successful protected decode | IntegrityInvalid |
| Well-formed absent/open/empty target or request for new state against an already sealed target | SegmentConflict (without distinguishing cross-tenant absence) |
| Preparation retry asks to change its signing key ref | IdempotencyConflict; no state change |
| Valid capacity exhausted; unavailable/invalid/backward trusted Clock; failed/malformed UUID generation; every opaque AuditProtectionException from protected record reading | ResourceUnavailable |
| Invalid Connection state, SQL/network/unclassified constraint failure, RR-invisible concurrent winner or serialization failure, owned transaction/commit/cleanup ambiguity | StorageUnavailable |

An actually visible proved ID/segment/hash immutable conflict maps to
IntegrityInvalid; a caught unique violation alone is not that proof. No silent
new generation, in-root winner lookup or retry is allowed. Protected reads reuse
AuditProtectedBodyCodec, whose opaque AuditProtectionException covers unavailable
keys/provider, envelope/AEAD, canonical decode and logical binding failures. Map
all of that family to a fresh AuditStoreException(ResourceUnavailable), matching
B2; do not infer the hidden cause from a message or discarded previous exception.
Direct shape/hydration failures and independently observed graph/index/metadata
divergence after successful decode remain IntegrityInvalid. Identity/policy input
errors precede SQL; current trust/crypto checks do not run on a storage-only exact
complete retry. G's 14/15/16/18 result/stage semantics are not assigned by these
storage codes. Every failure is freshly constructed with no SQL, key, record,
tenant, schema, bytes, trust snapshot or lower Throwable as its previous cause.

Required case families (freeze actual named/data-provider identities before the
final run; the rows below are obligations, not fabricated executed test counts):

| Proof | Positive and discriminating negatives |
| --- | --- |
| P1 full native segment | Actual B2 appends and close for system and a derived tenant; ranges 1-42 then 43-50, with first segment's checkpoint persisted before preparing second. Include one record and more than 32 records crossing a page boundary; all hashes originate from decoded records, not literal self-consistent metadata alone. |
| P2 completeness | Delete middle/first/last; extra record below/above closed range; inserted or repeated sequence; reordered page; altered content/body with stale hash; recomputed local body/hash with broken subsequent edge; wrong first/head/predecessor; successor substitution; mismatched physical index triplet. Closed version1, target/neighbor close-before-open and predecessor closedAt != target openedAt reject before Clock/UUID/insert. Preserve acceptance of older recordedAt ingress. Pair unavailable-key and corrupt-protected-body cases: both ResourceUnavailable; successfully decrypted graph/index corruption is IntegrityInvalid. Reaching the intended layer requires valid preceding shape/AEAD where applicable. |
| P3 isolation/provenance | Wrong/missing tenant and partition rejected before key/decrypt; exact nullable SQL pair; cross-tenant row metadata failure without raw disclosure; fake Framework Catalog with valid self-hash remains rejected by existing lookup; no new catalog decoder-to-runtime constructor path. |
| P4 durable preparation | New branch exactly one Clock and UUID; committed retry zero and byte-identical across new process/Connection; no-row-on-before-commit rollback; malformed/colliding generated ID; clock equal/above/below close boundary; invalid time/throw; key role/material/time/alias negatives; attempted key replacement keeps original row. Direct preparation hydration and native corrupt-boundary controls reject closed version1 and invalid chronology before Clock/UUID/preparation insertion; sealed exact retry requires captured+1 and the existing matching complete row. |
| P5 immutable bytes | F1-sign preparation, persist and inspect exact raw DB bytes/hash. Identical retry; same ID different signed bytes/hash; another ID for same segment; wrong preparation/partition/segment/fingerprint; missing predecessor checkpoint; gap/overlap/fork; LF/canonical/cap violations. Different cases re-sign/recompute where needed to test persistence binding instead of only math rejection. |
| P6 current trust | Preparation valid at t0, first persist at t1 with expired/revoked/rotated material; denied with closed state and no complete row. Same-ID byte-identical already-persisted replay is a storage success without any fresh-trust claim. No seed ever enters F2/schema/trace. |
| P7 native transactions | Owned begin failure; pre-insert and post-insert rollback; deferred failure at commit; real successful native commit followed by response-loss exception; rollback failure/quarantine; new Connection recovery. Prove literal owned begin -> SET TRANSACTION ISOLATION LEVEL REPEATABLE READ -> first lock/data query order, actual native repeatable-read isolation and unchanged session default. Assert actual row contents, native transaction status and no leaked result, not merely method counts. Auto-commit false/inactive nonzero/active level1/level2 entry rejects without SQL/Clock/UUID or changing caller markers. |
| P8 races | Two actual connections concurrent prepare same segment and persist same bytes: deliberately establish the losing RR snapshot while the winner holds the fence, commit the winner, then assert the loser's finite StorageUnavailable, owned cleanup/close, zero leaked result and exact final rows. An explicit new Connection/store/root must recover the winner's exact immutable bytes; no test object state may supply them. Retain actual conflicting-byte and cross-partition generated-ID collision controls, plus B2 successor append versus proof lock. Observe blocking/commit order; bound waits and clean all task-owned schemas/connections. |
| P9 no activation/seal | Both successful and every failed F2 branch leave B2 rows/records unchanged, exact one open successor retained. Artificial pre-existing sealed exact-byte replay is read-only; missing/different complete row rejects. Architecture mutations adding sealed UPDATE, append, signer, release getter/callback, key provider, control boolean or runtime registration fail. |
| P10 DDL and migration | Live exact pg_constraint/pg_indexes/column inventory, constraint violations including closed version1 and +1 version capacity, canonical UUID/partition, hash/token, caps, duplicate segment/ID/hash, missing FK; all-predecessor upgrade and fresh migration journey; irreversible down and invalid schema before SQL. |
| P11 privacy and ordering | Canaries in bytea, tenant/key ref, snapshot, SQL and failures absent from messages, causes and representation/trace surfaces; no raw argument survives a reconstructed exception. Valid shape failures and later DB/crypto faults are paired to prove first-applicable ordering. |
| P12 guards/package | Exact type/callable/state/dependency/constructor maps, direct/alias/group/class-string/indirect outside callers, fake close-result injection, missing protection decode, missing/changed partition fence, UPDATE/DELETE/UPSERT, raw seed/provider and public marker negatives; ordinary strings/comments positive. Each new required package path removal/order mutation fails while all old controls remain. |

Unit doubles prove exact order and forbidden calls, but cannot replace native
P1/P7/P8/P10. Avoid catches that also catch PHPUnit assertion failures, swallowed
rollback/cleanup failures, or successful log lines without case evidence.

## 10. Ownership, verification and release boundary

The Task lists exact source/test/documentation ownership and issued commands.
All P1–P12 families above are required. Unit doubles do not replace native
transactions/concurrency, actual schema inventory, fresh install or upgrade.
The final frozen candidate must include every predecessor and new test identity,
unchanged 512M memory budget, strict quality, relevant Consumer and independent
Transaction/Security and Documentation review. No current result is claimed here.

Normal signing/release, authenticated context/seed ownership, anchor state,
full verification, control events and G remain mandatory later work. The package
export/same-SHA CI/publication/delivery boundary stays with parent P23-006.
F2 adds no public API/capability claim or Stable release authority change.

## 11. Adopted internal decomposition and transaction ownership

Root adopted the bounded replacement and parameter correction on 2026-10-07.
The four entry APIs, DDL, failure map and P1–P12 remain unchanged. This is
implementation authority, not a claim that the future code passes quality gates.


The eleven additional paths are exactly the filenames listed below under
`src/Internal/Audit/Checkpoint/`, with the existing namespace. No extra file,
enum, DTO, interface, trait, registration or public marker is authorized.
Every new file is0644. Each listed method counts toward the ten-own-method limit.
All constructors remain below nine parameters. All other existing quality rules
remain unchanged.

In signatures below, `S` means the actual PHP attribute
`#[\SensitiveParameter]` on that parameter; it is not a type or new helper.
All unqualified library types mean the already installed/imported types used by
the original four-file contract. Native collection types are `array`; the named
shapes below are PHPDoc contracts, not additional classes. Any assertion that
narrows an array must follow runtime checks establishing the asserted shape,
including the closed F1 validation postcondition where applicable.

Fixed shapes:

- `RawRow = array<array-key,mixed>` and `RawRows = list<RawRow>` are untrusted DBAL
  results. A SQL method returning them does not establish field validity.
- `Unsigned` and `Complete` are the exact existing F1 sixteen/eighteen fields and
  scalar types. `PreparationBoundary` and `Proof` are exactly spec125 §3/§4.
- `Segment` has exactly partitionId:string, segmentId:string,
  state:'open'|'closed'|'sealed', firstSequence:int, finalSequence:?int,
  headRecordHash:?string, predecessorRecordHash:?string, openedAt:string,
  closedAt:?string, successorSegmentId:?string, closeFencingToken:?string and
  stateVersion:int. The physical input is the exact twelve-column B2 projection. `ClosedSegment` narrows state to
  closed/sealed and finalSequence/headRecordHash/closedAt/successorSegmentId/
  closeFencingToken to their non-null types after runtime validation.
- `PartitionState` has exactly partitionId:string, tenantType:?string,
  tenantId:?string, activeSegmentId:string, lastSequence:int, headRecordHash:?string,
  lastAssignedRecordedAt:string and stateVersion:int.
- `Previous = array{bytes:string,hash:string,value:Complete}`. It is immediate
  predecessor verification data confined to this internal graph, not a generic
  complete-byte retrieval/release API.

#### 1. AuditCheckpointPreparationRowShape.php — final class, no properties

Three methods, all static except the private unreachable constructor:

```text
private __construct()
public static read(S array $row): array
private static integer(S mixed $value, int $minimum, int $maximum): int
```

`read` returns exactly checkpointId:string, partitionId:string, segmentId:string,
closedStateVersion:int, closeFencingToken:string, unsignedBytes:string,
signingKeyFingerprint:string. It owns the seven-key row order, bytea conversion,
canonical IDs/partition/token/fingerprint/version validation; it never decodes
JSON. All validation failures become fresh IntegrityInvalid.

#### 2. AuditCheckpointPreparationBody.php — final class, no properties

```text
private __construct()
public static read(S string $bytes): array  // Unsigned
```

Raw nonempty/cap/no-LF -> native depth2 JSON decode -> flat array -> F1
signingInput -> exact canonical unsigned-byte equality. No new direct call to an
F1 internal Shape/Canonical helper or recursive unsigned decoder is authorized.
Return the validated exact unsigned value; its PHP key order is never identity.

#### 3. PostgreSqlAuditSegmentRowParser.php — final class, no properties

```text
private __construct()
public static parse(S array $row, S string $partitionId, S string $segmentId): array  // Segment
private static integer(S mixed $value, int $minimum, int $maximum): int
```

Validate the exact twelve physical keys including partition_id; canonical
integers, all IDs/hash/token/time scalar grammar; null-pair/lifecycle shape and
chronology. A populated open segment has final/head, arbitrary valid incremented
version, and null close fields. Closed/sealed rows require final/head/close fields.
Do not cast an unvalidated neighbor ID into a string. Candidate/conflict decisions
belong to Graph; corruption must not be recategorized as a normal open candidate.

#### 4. PostgreSqlAuditClosedSegmentQueries.php — final readonly class

Private state is exactly Connection connection and PostgreSqlAuditSchema schema.
Ten own methods; only the predicate is private:

```text
__construct(Connection $connection, PostgreSqlAuditSchema $schema)
lockPartition(S string $partitionId): void
state(S ?AuditTypedReference $tenant, S string $partitionId): array|false
target(S string $partitionId, S string $segmentId): array|false
successor(S string $partitionId, S string $segmentId): array
previous(S string $partitionId, S string $segmentId): array
endpoint(S ?AuditTypedReference $tenant, S string $partitionId, S int $sequence): array|false
tenantMismatch(S ?AuditTypedReference $tenant, S string $partitionId, S string $segmentId): bool
records(S ?AuditTypedReference $tenant, S string $partitionId, S string $segmentId, S string $after): array
private tenantPredicate(S ?AuditTypedReference $tenant): string
```

state/target/endpoint return RawRow|false; successor/previous/records return
RawRows. This is SQL only: existing projections, exact D149 fence, nullable
tenant predicates, row locks and LIMIT32 keyset reads. No parsing/decrypt/catch
that reclassifies a DBAL failure. Only RecordProof calls tenantMismatch/records;
only Reader calls the other public methods.

#### 5. PostgreSqlAuditClosedSegmentGraph.php — final class, no properties

No Queries object or constructor injection. Eight methods:

```text
private __construct()
public static state(S array|false $row, S ?AuditTypedReference $tenant, S string $partitionId): array  // PartitionState
public static target(S array|false $row, S array $state, S string $partitionId, S string $segmentId): array  // ClosedSegment
public static previous(S array $rows, S array $target, S string $partitionId, S string $segmentId): ?array  // ?ClosedSegment
public static successor(S array $rows, S array $target, S string $partitionId, S string $segmentId): array  // Segment
public static endpoint(S array|false $row, S array $target, S ?array $previous): void
private static closeToken(S string $partitionId, S array $segment): string
private static integer(S mixed $value, int $minimum, int $maximum): int
```

Missing state/target is SegmentConflict, without cross-tenant disclosure. Direct
row/chronology/closed-version1 corruption is IntegrityInvalid. `target` uses the
validated PartitionState to reject target==active. It owns candidate semantics,
not SQL. Neighbors retain exact same-partition edge/chronology/token checks,
canonical IDs and no substitution/self-edge. Endpoint must equal the proven
immediate predecessor ID, exact first-1 sequence and predecessor/head hash. The
private integer parser handles state and endpoint integers without widening the
shared RowParser's public surface. No unsafe MAX+1 arithmetic is allowed.

#### 6. PostgreSqlAuditClosedSegmentRecordProof.php — final readonly class

Private state is exactly Queries queries, StoredRecordHydrator hydrator and
AuditProtectedBodyCodec protection. Four methods:

```text
__construct(PostgreSqlAuditClosedSegmentQueries $queries, PostgreSqlAuditStoredRecordHydrator $hydrator, AuditProtectedBodyCodec $protection)
prove(S ?AuditTypedReference $tenant, S string $partitionId, S string $segmentId, S array $target): array
private record(S array $row, S ?AuditTypedReference $tenant, S array $target, S int $expected, S ?string $headHash): string
private safeFailure(S \Throwable $failure): AuditStoreException
```

`prove` returns exactly recordCount:int, firstRecordHash:string, chainHeadHash:string.
Its target is validated ClosedSegment. Before protected reads, prove binds that
same target to its explicit partition/segment arguments. An inconsistent target
is IntegrityInvalid. The target supplies record's partitionId, segmentId and
closedAt; no fabricated subset or unvalidated cast replaces the Graph result.
`record` returns only the validated record
hash, never a row or CompleteAuditRecord. It owns the exact17/exact14 conversion,
one hydrate and one unprotect, physical-index binding, expected identity/sequence/
previous-hash equality and recordedAt<=closedAt. Opaque protection errors always
become fresh ResourceUnavailable; local shape/decrypted binding corruption is
IntegrityInvalid; SQL failures remain StorageUnavailable at the safe boundary.

Before any hydration/key/decrypt, prove calls the value-free tenantMismatch query.
It starts the rolling head at target.predecessorRecordHash. Save the page's length
and last-seen continuation; release row/per-record values and the entire page
before the next fetch. No record-count cap, OFFSET, range-filtered query, retained
record list or MAX increment. Out-of-range extra records remain detectable.

#### 7. PostgreSqlAuditCheckpointTransaction.php — final class

Private state is exactly one readonly Connection; one phase string with the
closed values specified below; and one monotonic `bool closeAttempted=false`.
No rows, schema names, identities, snapshots, bytes or Throwable are fields.
Seven methods:

```text
__construct(Connection $connection)
beginRoot(): void
commit(): void
abort(S \Throwable $failure): AuditStoreFailure
private rollbackOwned(): bool
private closeOnce(): void
private mapFailure(S \Throwable $failure): AuditStoreFailure
```

The state machine below is mandatory. Public rollback, rollbackAndClose and
mapFailure are removed; no caller-selected cleanup mode or boolean is accepted.

#### 8. PostgreSqlAuditCheckpointRows.php — final readonly class

Constructor uses the same typed B2 schema, not a caller-supplied SQL identifier:

```text
__construct(Connection $connection, PostgreSqlAuditCheckpointSchema $schema, PostgreSqlAuditSchema $b2Schema)
preparation(S string $partitionId, S string $segmentId): array|false
complete(S string $checkpointId): array|false
checkpointIdExists(S string $checkpointId): bool
insertPreparation(S array $parameters): int
insertComplete(S array $parameters): int
predecessorSegment(S string $partitionId, S string $segmentId): array|false
predecessorCheckpoint(S string $partitionId, S string $segmentId): array|false
```

Private state: Connection connection, CheckpointSchema schema, string segments.
`segments` is obtained once from the constructor's B2 schema segmentsTable();
Store alone constructs that B2 schema from CheckpointSchema.schemaName().
No raw schema/identifier constructor parameter or alternate graph remains.

All reads are RawRow|false. predecessorSegment uses the exact **twelve-column**
projection including partition_id, matching RowParser; predecessorCheckpoint
uses the exact existing nine-column preparation/complete join. Other projections,
binary bindings, immutable joins and affected-row counts remain fixed. SQL errors
propagate for safe transaction mapping. No UPDATE/DELETE/UPSERT is introduced;
SELECT FOR UPDATE remains required. Insert parameter arrays have exact documented
key/type shapes, constructed only by Store from validated inputs, not a generic
caller-controlled SQL map.

#### 9. PostgreSqlAuditCheckpointPredecessor.php — final readonly class

Private state: CheckpointRows rows. Four methods:

```text
__construct(PostgreSqlAuditCheckpointRows $rows)
load(S ?string $segmentId, S string $partitionId): ?array  // ?Previous
private read(S array $segment, S array $row, S string $partitionId, S string $segmentId): array  // Previous
private bytea(S mixed $value): string
```

Only Store calls load, with the prior ID obtained from its new full Reader proof.
Null means genesis. load performs the two Rows reads and rejects absent/bad raw
shapes; the private read performs validation without SQL. DBAL failures must not
be caught as row corruption. Segment validation uses RowParser's full projection.
Hydrate the exact preparation; decode the complete bytes through Binding's
completeInput; compare stored/recomputed hash, identity/range/head/sealedAt, token
and closed-equality/sealed+1 version. Compare canonical unsigned bytes, never map
order. No arbitrary latest search or recursive checkpoint-history traversal.
The former assertInteger is unnecessary once the shared segment parser is used.

#### 10. PostgreSqlAuditCheckpointBinding.php — final class, no properties

Nine methods; static except the unreachable private constructor:

```text
private __construct()
public static preparation(S array $boundary, S array $proof, S array $unsigned, S ?array $previous): void
public static persistUnsigned(S array $supplied, S array $prepared): void
public static completeInput(S string $bytes): array  // Complete
public static sealedReplay(S array $existing, S array $unsigned, S string $expectedId): void
public static completeReplay(S array $existing, S array $complete, S string $completeBytes, S string $expectedId): void
public static unsignedBytes(S array $unsigned): string
public static stateVersion(S string $state, S int $captured, S int $current): void
private static completeRow(S array $existing): array
```

completeRow validates the exact three-column row and bytea/raw bounds, returning
exactly checkpointId:string, checkpointHash:string, bytes:string. completeInput
performs raw cap/no-LF followed by the existing complete F1 codec; no trusted
snapshot, Clock, private seed or signature verification is involved in this
structural operation. All these validation failures become fresh IntegrityInvalid.

preparation checks the entire Proof/body/boundary binding, **including sealedAt**,
token, ranges/count/first/head, partition/segment and immediate predecessor hash.
stateVersion permits closed==captured; sealed==captured+1 only, with captured
2..MAX_SAFE-1. persistUnsigned compares the two canonical unsigned strings; Store
calls preparation separately for the full binding. sealedReplay decodes the stored
complete row and checks its hash/ID and canonical unsigned bytes. completeReplay
checks stored ID/hash/bytes against the supplied already-validated complete input.
Both storage replays perform no current trust check. They cannot grant seal or
release eligibility. Cryptographic strings/bytes use hash_equals; typed numeric
and finite state values use strict equality. No generic sameSecret wrapper is
needed. Only Predecessor additionally calls stateVersion/unsignedBytes/completeInput.

#### 11. PostgreSqlAuditCheckpointTrustWorkflow.php — final readonly class

Private state is exactly ClockInterface clock and Uuidv7Generator ids. Five methods:

```text
__construct(ClockInterface $clock, Uuidv7Generator $ids)
newPreparation(S array $proof, S ?array $previous, S string $signingKeyRef, S array $trustedSnapshot): array
verifyPersistence(S AuditCheckpointPreparation $preparation, S ?array $previous, S string $completeBytes, S array $trustedSnapshot): void
private canonicalNow(S string $closedAt): array
private generatedId(S \DateTimeImmutable $now): string
```

newPreparation returns exactly unsigned:Unsigned, unsignedBytes:string,
signingKeyFingerprint:string. canonicalNow returns exactly
array{0:DateTimeImmutable,1:string}. One Clock capture -> valid UTC microseconds
not below closedAt -> full F1 current trust/key -> immediate predecessor signature
verification -> one UUIDv7 generation -> F1 unsigned bytes. Both thrown and
malformed generated IDs are ResourceUnavailable; the visible collision decision
belongs to Store/Rows and remains IntegrityInvalid. No regeneration.

verifyPersistence receives the authoritative Preparation just reloaded and
hydrated by Store, after fresh Reader proof, Binding.preparation and canonical
Binding.persistUnsigned checks. Those checks establish the complete immutable
boundary, sealedAt==proof.closedAt and equality of every unsigned body byte to
completeBytes. This workflow runs only for first insert at the exact closed
captured boundary; an exact complete replay performs zero Clock/UUID/current-trust
work. It reads Preparation.unsigned and signingKeyFingerprint into local values,
captures Clock exactly once through canonicalNow with the bound unsigned sealedAt
as its close-time floor, validates the full current F1 snapshot/key for unsigned
signingKeyRef/checkpoint role and matching fingerprint, then uses the F1 verifier
on completeBytes followed by the immediate predecessor with the same time/snapshot.
F1's signature-before-authenticated-signedAt order remains unchanged. No supplied
signedAt becomes trusted current time. No UUID, new decoder, reconstructed body,
trust provenance claim or retained raw state is introduced. The readonly object
alone is not persistence authority: Store reload/proof/binding and exact callers
remain mandatory. Neither workflow retains input snapshot/time/bytes in fields.

### Existing four types and exact construction/call graph

The original public constructors/methods and final/readonly modifiers remain
unchanged. No new public method or property is added to them. Preparation still
has one private SensitiveParameterValue snapshot; Schema and its exact two-table
DDL remain unchanged. Reader owns only readonly Connection, PartitionIdDeriver,
Queries and RecordProof references, with __construct/prove/assertConnection/
safeFailure as its four methods. Store owns only readonly Reader, Transaction,
Rows, Predecessor, TrustWorkflow and PartitionIdDeriver references, with
__construct/prepare/persist/assertBinding as its four methods.

Exact construction:

- Store.__construct creates one B2 Schema from its validated checkpoint schema
  name; one Reader from the original same Connection, B2 Schema, hydrator,
  protection and partition helper; one Transaction on that Connection; one Rows
  on that Connection and the same two schemas; one Predecessor from that Rows;
  and one TrustWorkflow from the supplied Clock/UUID generator.
- Reader.__construct creates one Queries from its own Connection/B2 Schema and
  one RecordProof from that Queries and its original hydrator/protection.
- No other production construction of these helpers exists. Static utility
  constructors are private and never called. No cloned/injected Reader or alternate
  connection/schema appears. Direct tests are outside production caller maps.

In this appendix Reader.prove abbreviates the unchanged public method
proveInCurrentTransaction; no prove method is added. Preparation remains eight
methods, Schema five.

Exact new helper edges (constructors above are additional fixed edges):

| Caller | Allowed helper methods |
| --- | --- |
| Preparation.fromRow | RowShape.read; Body.read, separately and in that order |
| Reader.prove | Queries.lockPartition/state/target/successor/previous/endpoint; Graph.state/target/successor/previous/endpoint; RecordProof.prove |
| Graph.target/previous/successor | RowParser.parse |
| RecordProof.prove | Queries.tenantMismatch/records; its own private record/safeFailure |
| Store.prepare | Transaction.beginRoot/commit/abort; Reader.prove; Rows.preparation/complete/checkpointIdExists/insertPreparation; Predecessor.load; Binding.preparation/sealedReplay; TrustWorkflow.newPreparation; Preparation.fromRow and its specified getters |
| Store.persist | Binding.completeInput; Transaction.beginRoot/commit/abort; Reader.prove; Rows.preparation/complete/insertComplete; Predecessor.load; Binding.preparation/persistUnsigned/completeReplay; TrustWorkflow.verifyPersistence; Preparation.fromRow and its specified getters |
| TrustWorkflow.verifyPersistence | Preparation.unsigned/signingKeyFingerprint only |
| Predecessor.load | Rows.predecessorSegment/predecessorCheckpoint; private read |
| Predecessor.read | RowParser.parse; Preparation.fromRow and specified getters; Binding.completeInput/unsignedBytes/stateVersion |
| Binding.preparation | own stateVersion |
| Binding.persistUnsigned | own unsignedBytes for both inputs |
| Binding.sealedReplay | own completeRow/completeInput/unsignedBytes |
| Binding.completeReplay | own completeRow |

Store's preparation retry never invokes TrustWorkflow. It commits only after
all immutable/predecessor/key-ref checks and any required sealed complete replay.
New prepare requires closed state and seal-capable version before Clock/UUID.
Construct/validate the returned Preparation before commit, retain it locally,
and return it only after acknowledged commit; a commit exception leaks no result.
First persist and exact replay both commit before void success. The Store uses
one operation catch covering its owned work; it reconstructs an exception from
Transaction.abort's finite enum rather than rethrowing the original Throwable.

Reader ordering remains: entry markers -> canonical input/derived partition ->
advisory fence -> exact-tenant state lock -> target lock -> successor/previous
row locks and graph checks -> prior endpoint -> RecordProof. RecordProof then
checks tenant mismatch before its first record page. It never owns transaction
completion or closes a connection.

### Exact changed B2/A2/F1 owner edges

These replace superseded Reader/Store/Preparation direct call owners; they are
not namespace exemptions. Constructor/type references must be inventoried in
addition to executable calls by the actual guard implementation.

| External seam | Authorized new owner/method |
| --- | --- |
| B2 Schema.__construct | Store.__construct only, one instance |
| B2 Schema.schemaName | Queries.lockPartition |
| B2 Schema.partitionStateTable | Queries.state |
| B2 Schema.segmentsTable | Queries.target/successor/previous; Rows.__construct |
| B2 Schema.recordsTable | Queries.endpoint/tenantMismatch/records |
| B2 StoredRecordHydrator.hydrate | RecordProof.record only, once per decoded record |
| A2 AuditProtectedBodyCodec.unprotectRecord | RecordProof.record only, one lexical call; delete Reader's old owner grant |
| F1 AuditCheckpointCodec.signingInput | Body.read; Binding.unsignedBytes; TrustWorkflow.newPreparation |
| F1 AuditCheckpointCodec.decode | Binding.completeInput only |
| F1 AuditTrustSnapshotCodec.key | TrustWorkflow.newPreparation/verifyPersistence |
| F1 AuditArtifactVerifier.checkpoint | TrustWorkflow.newPreparation for the prior checkpoint; verifyPersistence for supplied complete then prior |
| F1 AuditArtifactException type test | Transaction.mapFailure only when classifying F1 failures; not a new throw/decode/signer edge |

Retain original PartitionIdDeriver.derive owners Store.assertBinding and
Reader.prove. RowShape.read, Binding.completeRow and Predecessor.bytea normalize
bytea through the existing PostgreSqlBytea helper. RowParser/Graph/TrustWorkflow
reuse AuditRecordTime where validating persisted/clock timestamps. Ordinary
finite AuditStoreFailure/Exception use remains allowed in these exact files.
No new CompleteAuditRecord construction, protect/finalizer, Catalog decoder or
provider, append, D-control, private-key source or signer call is granted. The
record returned by the existing unprotect operation is local to RecordProof.record;
only its verified hash leaves that private operation.

### Transaction protocol: precise ownership and terminal recovery

Phases are exactly ready (initial), beginning, isolating, owned, committing,
quarantined and quarantine_close_failed. `closeAttempted` is monotonic for the
helper lifetime and never reset. Quarantine is terminal for the original Store
graph. Recovery is an explicit new Connection/Store/root; closing DBAL must not
be treated as permission to reconnect and retry through the failed Store.

| Operation/phase | Required transition and outcome |
| --- | --- |
| beginRoot in any non-ready phase | Fresh StorageUnavailable; no BEGIN, rollback, close or other SQL |
| beginRoot entry inspection | Read auto-commit/active/nesting markers before ownership. Getter failure or anything other than true/false/0 rejects with fresh StorageUnavailable and leaves caller markers/root/connection untouched |
| valid entry | Set beginning immediately before the one beginTransaction attempt. After acknowledged begin set isolating; literal SET TRANSACTION ISOLATION LEVEL REPEATABLE READ is the first subsequent SQL. Only acknowledged SET changes phase to owned |
| begin ambiguity | No claim that a root began or rolled back. CloseOnce, terminal quarantine, fresh StorageUnavailable; no retry |
| isolation failure after begin | Attempt the appropriate owned rollback, then CloseOnce regardless of rollback outcome; terminal fresh StorageUnavailable |
| commit entry | Require owned; change to committing before exactly one commit call |
| acknowledged commit | Set ready, with no close. Only then may Store return its already validated Preparation or void success |
| commit failure/response loss | Keep ambiguity: appropriate owned rollback only if markers still describe that root; CloseOnce regardless. Fresh StorageUnavailable, no returned result and no assertion of rollback or durability |
| abort in owned | Map the original failure to the finite enum, attempt one owned rollback. If rollback is successful and the mapped failure is a domain failure, set ready and return that enum. StorageUnavailable or any rollback ambiguity always CloseOnce and return StorageUnavailable |
| abort in beginning/isolating/committing | Apply the corresponding ambiguous-stage cleanup above and return StorageUnavailable regardless of the thrown exception's apparent domain class |
| abort in either terminal phase | Return StorageUnavailable with no second rollback/close/SQL; this is the Store outer catch after a helper already quarantined itself |
| abort in ready | No cleanup or state mutation; return a freshly classified finite code. Store places beginRoot outside its operation try, so entry rejection does not normally reach abort |

rollbackOwned is private and only operates on a root entered by this helper.
Before a rollback after commit ambiguity, markers must still be auto-commit true,
active, level1; level0 may coexist with an unknown native outcome and is handled
by close, never by beginning another root. Invalid/throwing markers or a rollback
throw indicate cleanup ambiguity and return false; no caller root is repaired.
Neither rollbackOwned nor mapFailure performs close or throws a lower exception
out of the boundary; their caller determines the fixed StorageUnavailable outcome.

closeOnce marks closeAttempted=true and phase=quarantined **before** calling
Connection.close(). If close throws, its catch sets the terminal
quarantine_close_failed phase and retains no Throwable. This is meaningful terminal
failure handling, not an empty catch or token unset. Reentry in either terminal
phase cannot call close again. A test must observe close failure and continued
unusability; a successful top-level failure mapping does not hide cleanup failure.

mapFailure is private: existing AuditStoreException contributes only its finite
enum; AuditProtectionException -> ResourceUnavailable; AuditValidationException
and AuditArtifactException -> IntegrityInvalid; other failures -> StorageUnavailable.
The stage override above takes precedence for begin/isolation/commit/cleanup
ambiguity. Store always creates a new AuditStoreException without cause, raw SQL
or lower traces. No callback, callable operation, externally selected cleanup
boolean, session isolation setter, savepoint, ambient join, looped retry or stale
root winner lookup is authorized.

### Verification/guard/package qualifications

Adopt Root's complete verification-ownership-matrix unchanged: functional owns
the four unit files; proof owns its ten native/support paths; guard owns the four
architecture files; Root serializes every runtime and owns Acceptance. Functional
owns19 paths (eight original plus eleven helpers). Total scope33 is23 new/10
existing; required-package arithmetic is178 (162+5+11), not an executed count or
a passing control total. Root retains the pre-write absence and revised scope.

The four unit paths must cover every assigned P2/P3/P4/P5/P6/P9/P11 family, not
just TX regressions. Native P1/P7/P8/P10 cannot be replaced by these units. The
transaction protocol requires native begin/isolation/rollback/commit-response-loss,
stale-RR prepare and persist races, failed rollback/close, fresh recovery and
unchanged caller/session/B2 state, alongside exact order/double-catch unit proof.
Review proof on its captured fixed inputs before runtime acceptance.

Retain these discriminators in particular: canonical unsigned bytes despite
different PHP key order; second segment's true predecessor; populated open and
later closed successor; sealed missing +1 versus exactly +1; close exactly once
after stale-RR insert failure; validly re-signed sealedAt mismatch; predecessor
preparation token mismatch; endpoint belonging to another segment; malformed UUID
ResourceUnavailable versus visible collision IntegrityInvalid and corrupt closed
version1; page/per-record release before the next query. Later negatives must
reach their intended layer after the valid-path blockers are fixed.

Exact helper method/modifier/property/SensitiveParameter maps and constructor/data
flow must be guarded, including direct/alias/group/class-string/indirect callers.
Remove old decrypt/codec caller grants as the new owners replace them. Preserve
all predecessor120 package controls, initial F2 controls and four omission
controls; add each new path's omission/order control and every meaningful new
call mutation. Guard SELECT FOR UPDATE as allowed locking, not forbidden mutation.
Maintain positive ordinary-string/comment controls. No namespace or whole-folder
waiver is allowed.

DDL remains exactly spec125 §6. LF is rejected by codecs/application hydration;
there is no new LF SQL CHECK. Preparation.unsigned/boundary/signingKeyFingerprint
intentionally return their specified data. Privacy tests must retain their exact
authorized contents while excluding canaries from exception/debug/serialize/
JSON/var_export/traces and unintended object retention. No seed enters this graph.

### Reviewed validation deduplication — 2026-10-08

Root adopts the first in-place pass qualified by review1f2359bf. The exact fifteen
types, methods, signatures, state, construction/caller edges and P1–P12 stay fixed.
Getters may rely on Preparation's private readonly snapshot established only by
RowShape, Body and fromRow identity binding. Body may rely on F1's checked exact
unsigned postcondition after raw bounds/depth and canonical-byte equality. These
facts support precise annotations, never unvalidated casts or nullable defaults.

Graph may omit Parser-established scalar/lifecycle duplicates after retaining
closed/sealed discrimination. Raw neighbor admission, target-to-successor identity,
self-edge exclusions, closed version >=2, pre-addition sequence ceilings,
adjacency/hash/chronology and token recomputation remain. Parser's broad valid
Segment union alone does not establish those stricter semantic conditions.
RecordProof may rely on Graph's ClosedSegment shape but still binds its explicit
partition/segment arguments before tenant/protected reads. Its raw record shape,
decode, index, chain, page bounds/release and failure ordering remain unchanged.

Predecessor may rely on Store's already validated supplied identities and Parser's
raw segment validation, retaining its own requested-ID binding, closed/sealed and
version rules, raw checkpoint-row admission and all immutable-byte comparisons.
It may reuse the same local prepared unsigned map instead of a duplicate getter.
Binding may rely on the validated Preparation/Proof/Previous shapes but retains
all cross-object equalities. Previous absent requires a null unsigned prior hash;
Previous present requires a non-null hash before hash_equals. Complete-row raw
validation and exact replay byte/hash checks remain. Trust may omit only the
already-proved closedAt/sealedAt/key-reference string checks; all Clock/current
trust/fingerprint/signature/predecessor/UUID checks remain.

Store/Reader admission order, raw row validators, transaction ownership and
privacy never follow from PHPDoc alone. No new helper, method, caller, dependency,
threshold, baseline or waiver is adopted. Guard fixtures must preserve meaningful
negatives and measure exact approved edges; duplicate call elimination is recorded
explicitly. The plan's conditional further decomposition is unapproved. Actual
unchanged quality gates determine residual work after this bounded pass.

### Fixed validation tables — 2026-10-08

Root adopts only the four-file first pass from reviewb07e1c57. The following
nine private literal constants are added; methods, properties, constructors,
SensitiveParameter maps and external caller/dependency edges remain unchanged.

| Existing type | Exact constants and ordered contents |
| --- | --- |
| AuditCheckpointPreparationRowShape | PREPARATION_STRING_PATTERNS: checkpoint_id/UUID_V7, partition_id/PARTITION, segment_id/UUID_V7, close_fencing_token/FENCING_TOKEN_PATTERN, signing_key_fingerprint/FINGERPRINT |
| PostgreSqlAuditSegmentRowParser | SEGMENT_REQUIRED_PATTERNS: partition_id/existing partition pattern, segment_id/UUID_V7; SEGMENT_NULLABLE_PATTERNS: head_record_hash/HASH, predecessor_record_hash/HASH, successor_segment_id/UUID_V7, close_fencing_token/FENCING_TOKEN_PATTERN |
| PostgreSqlAuditClosedSegmentGraph | STATE_REQUIRED_PATTERNS: partition_id/PARTITION, active_segment_id/UUID_V7; ENDPOINT_REQUIRED_PATTERNS: segment_id/UUID_V7, record_hash/HASH |
| PostgreSqlAuditCheckpointBinding | BOUNDARY_STRICT_FIELDS: partitionId, segmentId; UNSIGNED_STRICT_FIELDS: partitionId, segmentId, firstSequence, lastSequence, recordCount; UNSIGNED_HASH_PAIRS: firstRecordHash/firstRecordHash, chainHeadHash/chainHeadHash, sealedAt/closedAt; COMPLETE_STRING_PATTERNS: checkpoint_id/UUID_V7, checkpoint_hash/HASH |

Grammar loops prove native type/nullability and the named fixed pattern before
use. Equality loops compare only the named validated maps. No callback, dynamic
invocation, boolean-result table, generic validation protocol, normalization,
string cast or nullable fallback is added. Keep all existing private bounded
integer algorithms; no shared-integer type is adopted. PHPDoc may describe only
runtime-established facts.

Exact key/list admission precedes all raw reads. RowShape retains version2..MAX-1,
bytea bounds/no-LF and explicit return mapping. Parser retains requested identity,
state enum, its three integer fields, explicit timestamp string admission and
AuditRecordTime calls, self-edge and all lifecycle/range/chronology conditions.
Graph retains tenant pair/expected identity, head/sequence relation, timestamp,
all target/neighbor/version/MAX/token rules and endpoint identity/sequence/hash.
Its measured successor inline-return warning may be corrected without changing
the returned Segment or semantic checks.

Binding order is Boundary strict loop, explicit fencing hash, Unsigned strict
loop, Unsigned hash pairs, stateVersion, then Previous absent/present binding.
All eleven cross-value equalities remain. CompleteRow retains its three-key
admission before patterns and bytea bounds afterward. All other Binding methods,
RecordProof and Predecessor comparisons remain unchanged in this pass.

Within a pure scalar/grammar group, field-by-field type-then-pattern evaluation
may replace all-types-then-all-patterns: these predicates have no effects and
map to the same fresh IntegrityInvalid. No effectful/raw/F1/graph/protected/trust/
Clock/UUID/transaction boundary may move or be evaluated eagerly. Existing
integer, timestamp and bytea stages retain their order. Guard constant inventories
must be exact; no caller/dependency waiver accompanies this change. Conditional
new helper/private-method proposals remain unadopted. Actual residual measurement
is required; this table pass is not a predicted quality pass.

## 12. Preparation workflow and transaction policy

Root adopts the main proposal2fddcc9b after reviewing its exact maps. The
optional RecordProof.readPages appendix is excluded. Add exactly two0644 paths
under src/Internal/Audit/Checkpoint: PostgreSqlAuditCheckpointPreparationWorkflow.php
and PostgreSqlAuditCheckpointTransactionPolicy.php. Product grows15→17; Task
scope33→35 (25new/10existing), functional21/proof10/guard4. Preserve prior
semantics, shapes, P1–P12 and all quality rules. No release/public API change.

S below means SensitiveParameter. Methods have no defaults, variadics, reference
parameters/returns, individual final or abstract modifiers. Existing precise
Proof/ClosedSegment/Segment/Unsigned/Boundary/Previous shapes remain as above.

PreparationWorkflow is final readonly. Exactly five private readonly properties
are reader:PostgreSqlAuditClosedSegmentReader, transaction:PostgreSqlAuditCheckpointTransaction,
rows:PostgreSqlAuditCheckpointRows, predecessor:PostgreSqlAuditCheckpointPredecessor,
trustWorkflow:PostgreSqlAuditCheckpointTrustWorkflow. Its only private constant
MAX_SAFE_INTEGER is9007199254740991. Exactly four instance methods:

| Visibility | Signature |
| --- | --- |
| public | __construct(the five typed properties above in that order); no S, no declared return |
| public | run(S ?AuditTypedReference expectedTenant, S string partitionId, S string segmentId, S string signingKeyRef, S array trustedSnapshot): AuditCheckpointPreparation |
| private | retry(S array proof, S array preparationRow, S ?array previous, S string signingKeyRef): AuditCheckpointPreparation |
| private | create(S array proof, S ?array previous, S string signingKeyRef, S array trustedSnapshot): AuditCheckpointPreparation |

preparationRow remains raw array<array-key,mixed>; trustedSnapshot remains
array<string,mixed>. No inputs/results/bytes/Throwable are retained as state.
Only Store constructs Workflow, passing the same five existing collaborators;
only Store.prepare calls run. The constructor constructs nothing.

run calls beginRoot before its sole operation try. Inside it, proveInCurrentTransaction
→ Rows.preparation → Predecessor.load occur once each in that order. A present
row selects retry, otherwise create. One Throwable catch calls abort once and
throws a fresh finite AuditStoreException; no finally or second Store catch.
retry preserves fromRow→boundary/unsigned→Binding.preparation→key comparison
(IdempotencyConflict), then for sealed only requires complete/sealedReplay
(missing is IntegrityInvalid), then commit→return. No Clock/UUID/current trust.
create preserves the original state/version fence and precedence before
newPreparation: non-closed is SegmentConflict, otherwise >=MAX ResourceUnavailable.
Build the same ordered seven-key row, using the already-bound Proof identities;
fromRow→boundary/unsigned→Binding.preparation→checkpointIdExists→insertPreparation
→commit→return remains. Collision is IntegrityInvalid; count other than1 is
StorageUnavailable. No result escapes failed/ambiguous commit.

Workflow's exact type dependencies are those five collaborators, Preparation,
Binding, Core AuditTypedReference, Storage AuditStoreException/AuditStoreFailure,
SensitiveParameter and Throwable. No direct Connection/schema/hydrator/protection/
codec/verifier/key/Clock/UUID reference is added.

Store retains its modifier, seven-parameter constructor, four methods/signatures/
S maps and existing six readonly properties. Add only private readonly
preparationWorkflow:PreparationWorkflow after constructing the existing graph.
Remove its now-unused MAX_SAFE_INTEGER; UUID_V7/PARTITION remain. prepare keeps
assertBinding then returns run with all five unchanged arguments. persist and
assertBinding stay textually unchanged apart from formatting. No duplicate
Reader/Transaction/Rows/Connection, catch or admission is introduced.

The exact lexical owner transfers remove the old Store.prepare grants:

| Edge | Workflow owner/count | Retained Store.persist |
| --- | --- | --- |
| beginRoot, proveInCurrentTransaction, Rows.preparation, Predecessor.load | run1 each | 1 each |
| Preparation.fromRow/boundary/unsigned, Binding.preparation | retry1/create1 each | 1 each |
| Rows.complete | retry1 | 1 |
| Binding.sealedReplay | retry1 | 0 |
| newPreparation, checkpointIdExists, insertPreparation | create1 each | 0 |
| Transaction.commit | retry1/create1 | 2 |
| Transaction.abort | run1 | 1 |

All other owners persist, including Predecessor and Store.persist's completeInput/
persistUnsigned/completeReplay/verifyPersistence/insertComplete. Store's type
dependencies add only Workflow. No F1/protected-read executable edge moves.

TransactionPolicy is final, without state/constants; exactly private __construct()
(no return) and public static root(S Connection):bool, owned(S Connection):bool,
map(S Throwable):AuditStoreFailure. No instance is constructed. root reads
isAutoCommit→isTransactionActive→getTransactionNestingLevel unconditionally,
then compares true/false/0; a thrown read returns false and stops later reads.
owned compares true/true/1 with the original short circuit; thrown reads return
false. Do not combine these different orders through an eager array or mode.
map preserves AuditStoreException's enum first, protection→ResourceUnavailable,
validation/artifact→IntegrityInvalid, otherwise StorageUnavailable, with no
retained/copying Throwable. Exact dependencies: Connection, Throwable,
SensitiveParameter, AuditValidationException, AuditArtifactException,
AuditProtectionException, AuditStoreException and AuditStoreFailure.
Sole callers: Transaction.beginRoot→root1, rollbackOwned→owned1, abort→map2.
Only three observers occur once each lexically in each marker method.

Transaction retains Connection/phase/closeAttempted, initial values and all
completion/quarantine IO. Remove only mapFailure, leaving six existing methods
with unchanged signatures/S maps. beginRoot retains phase-ready rejection before
Policy.root; false maps freshly to StorageUnavailable. rollbackOwned keeps its
try/catch and invokes rollBack only after Policy.owned true. commit/closeOnce
are unchanged. abort replaces its two classification calls; owned rollback still
occurs before comparing the enum, even when already StorageUnavailable.
Only its two pure phase ORs become strict in_array checks over exact families
[quarantined,quarantine_close_failed] and [isolating,committing], in place.
No new phase/protocol/dispatch/eager effectful check follows.
Direct effects remain beginRoot.beginTransaction1/SET1, commit.commit1,
rollbackOwned.rollBack1, closeOnce.close1. closeOnce callers remain beginRoot2,
commit1, abort3; rollbackOwned callers remain beginRoot1, commit1, abort2.
Dependencies become Connection, Policy, storage exception/failure and Throwable;
classification exception references move to Policy. Only Transaction owns phase,
once-close and actual root completion/quarantine.

Reader adds only private instance proofResult(S array target, S array successor,
S ?array previous, S array records):array, giving five methods. Inputs are
ClosedSegment, Segment, ?ClosedSegment and
{recordCount:int,firstRecordHash:string,chainHeadHash:string}. Sole caller is own
prove after endpoint and RecordProof. It only assembles the same ordered14-field
Proof: target identities; previous.segmentId or null; successor.segmentId; three
record aggregate fields; other fields from target, with lastSequence from
finalSequence. No query/catch/validation/state. Constructor, four readonly
properties, constants, dependencies, failure mapping and query order stay fixed.
External prove callers become Store.persist1 and Workflow.run1.

Guard exact17 type/method/S/property/modifier/constant/dependency maps, same
collaborator wiring and every owner transfer. Preserve all old controls and
direct/alias/group/class-string/indirect outside-caller negatives. Add new-seam
negatives; assert Policy marker ordering, Transaction-only effects and pure
Reader assembly. Move only classification type references to Policy; add only
Workflow's AuditTypedReference/storage exceptions. No path/namespace waiver.

Both package inventories add Workflow then Policy after TrustWorkflow and before
Storage/AuditStoreFailure, preserving order. Arithmetic180 requires actual proof;
retain140 old mutations/four runtime omissions and add both omission/order
controls. Review/update the exact export pin without bypassing it. Internal note
updates only the responsibility map; public Source/count is unchanged.
Existing new/retry/sealed/commit ambiguity, marker false/throw ordering, owned
cleanup/quarantine, canonical persistence, proof/privacy and native P1–P12
evidence must cover the new graph. Other measured classes remain unresolved;
no further helper/private method or quality success is inferred.

## 13. Validator ownership and shared proof boundaries

Root adopts the exact twelve-helper/six-existing-class proposal cc5df7f2 after
reviewing runtime provenance, comparison ordering and actual r6 residuals.
The six existing classes are RowShape, SegmentRowParser, Graph, Binding,
Predecessor and RecordProof. The new paths are exactly:

```text
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
```

These supersede only the corresponding §§11–12 inventories and owner maps.
Product17 becomes29; Task35 becomes47 (37new/10existing), functional33/proof10/guard4.
No optional helper, threshold, baseline, dependency, public API or release change.
Transaction/Policy/Reader residuals are separate and are not changed by this
amendment. Their actual r6 findings remain open.

The six removable decoded predicates are action/resource native arrays,
action.type/resource.type/resource.id native strings, and recordedAt native
string. They follow the actual final Hydrator→unprotectRecord→RecordCodec.decode
→RecordValidator execution. CompleteAuditRecord constructor/type alone does not
prove them. All independent physical/logical/index/target/time/edge comparisons
remain. Predecessor's decoded complete ID/hash native checks follow successful
F1 decode, while the stored raw hash admission remains at its original stage.

Endpoint's pure identity/hash comparison regrouping, replay identity conjunction
regrouping, predecessor sealedAt hash equality and lifecycle implications below
are expressly adopted. No effectful boundary or failure-class precedence moves.

### 13.1. Common metadata and exact shapes

Every type below is internal, `final`, with no public API attribute. All
stateless classes are non-readonly, have no properties and exactly one private
empty non-static `__construct()` with no parameters or declared return.
Their other listed methods are static. Only Record, Pages and the existing
RecordProof/Predecessor are `final readonly` instance classes, as specified
below. No inheritance, interfaces, magic methods, invokable callbacks,
additional constructors, retained inputs/results/bytes/errors, defaults,
variadics, by-reference parameters/returns, or method-level final/abstract
modifiers are authorized.

`S` means an actual `#[\SensitiveParameter]` attribute. Every non-constructor
argument is S except Integer's minimum/maximum. No collaborator constructor
argument is S. There are no methods with more than five non-constructor
parameters. Constructor parameter names/order below are exact.

Use native arrays and full concrete PHPDoc shapes, not new value-object types:

- RawRow is `array<array-key,mixed>`; RawRows is the current DBAL row-list
  contract, with runtime count/list/row checks retained where specified.
- Unsigned16, Complete18, Boundary4, Proof14, PartitionState8, Segment12,
  ClosedSegment12 and Previous3 are exactly spec125 §§3/4/11. No field or
  nullable relation changes. Complete remains supplied only by successful F1
  decoding and Previous remains private internal verification data.
- ScalarSegment has Segment's exact twelve scalar fields and finite state
  enum, but is explicitly **not yet lifecycle-validated**. Only Parser forms
  it after identity, integer, native text and timestamp admission; Lifecycle
  checks it before Parser can return a Segment.
- TextFields is exactly `{headRecordHash:?string,predecessorRecordHash:?string,
  openedAt:string,closedAt:?string,successorSegmentId:?string,
  closeFencingToken:?string}` in that order.
- NeighborRow is exactly `{row:RawRow,segmentId:string}`. Its returned row has
  a runtime-admitted string segment_id equal to segmentId and different from
  the requested target. Other raw fields are not blessed by this shape.
- StoredComplete is exactly `{checkpointId:string,checkpointHash:string,
  bytes:string}`; complete-row admission establishes UUID/hash grammar and
  byte limits. No complete decode is implied.
- RangeBinding is exactly `{partitionId:string,segmentId:string,
  firstSequence:int,lastSequence:int,recordCount:int}`. It is a local projection
  of a proven current Proof or parsed predecessor segment, never a fabricated
  full Proof or claim that predecessor records were re-read in this step.
- SegmentIdentity is the shared projection `{partitionId:string,
  segmentId:string,closeFencingToken:string}` of an actual Proof/ClosedSegment.
- PageAggregate is exactly `{recordCount:int,nextSequence:int,
  firstRecordHash:?string,chainHeadHash:?string}` in that order. It is confined
  to RecordProof and does not escape before final aggregate validation.
- RecordProof's result remains its original three non-null aggregate fields.

Raw ordered-key gates remain exact: RowShape7; Parser12; State8; Endpoint4;
protected Record17 with exact17→14 projection; complete stored row3;
Predecessor joined row9. Keep every existing physical field and its order.
The exact nonempty fixed string-key comparisons already exclude every list,
so only the redundant `array_is_list` alternative in RowShape/Parser may be
removed; the exact array_keys gate remains before all raw reads.

### 13.2. Scalar and segment validators

#### PostgreSqlAuditCheckpointInteger

Methods: private constructor; public static
`read(S mixed value, int minimum, int maximum): int`. No constants/properties.
Dependencies exactly RuntimeException and SensitiveParameter.

Move the existing identical integer algorithm unchanged in meaning: native
integer min/max check; otherwise native string plus canonical unsigned decimal
grammar; compare length and same-length lexicographic value to maximum before
cast; then minimum. Preserve booleans/floats/signs/leading zero/whitespace/
exponent rejection and min0 support. Do not coerce before grammar/upper bounds.
Failures are empty RuntimeException, caught at existing row/graph boundaries.

Exact callers/counts: RowShape.read1 (2..MAX-1); Parser.parse3 (first/final/version,
with final's existing null branch); StateParser.parse2 (last0..MAX/version1..MAX);
Endpoint.assertMatches1 (sequence1..MAX). Remove the three old private integer
methods and grants. No B2/F1/Core API changes or new external scalar call.

#### PostgreSqlAuditSegmentTextFields

Methods: private constructor; public static
`read(S array row, S string segmentId): array` returning TextFields.
Constants: private string UUID_V7, HASH, FENCING_TOKEN_PATTERN and private array
SEGMENT_NULLABLE_PATTERNS, with exactly Parser's current values and field order
head_record_hash/predecessor_record_hash/successor_segment_id/close_fencing_token.
Dependencies exactly AuditRecordTime, RuntimeException, SensitiveParameter.

Sole caller Parser.parse1, after all three integer conversions and before
lifecycle. The row has passed the twelve-key gate but its text values remain
raw. Keep opened native string/closed native nullable-string admission, all
four nullable grammar checks, self-successor rejection, then opened timestamp
and conditional closed timestamp in that order. The self check may be
`$successor === $segmentId`: the expected ID is a native non-null string, so
the former explicit successor-not-null conjunct changes no outcome. Return
only the six admitted fields. No catch: Parser's existing fresh IntegrityInvalid
boundary remains responsible for unexpected/time failures.

#### PostgreSqlAuditSegmentLifecycle

Methods: private constructor; public static
`assertValid(S array segment): void`, taking ScalarSegment. No constants/state.
Dependencies exactly RuntimeException and SensitiveParameter.
Sole caller Parser.parse1 after TextFields.read, before return.

Use these explicit invariants, not a boolean array/filter/table:

1. Reject `($first === 1) !== ($predecessor === null)` or
   `($final === null) !== ($head === null)`.
2. Let `$open = $state === 'open'`. Reject each mismatch between `$open` and
   nullness of closedAt, successorSegmentId and closeFencingToken, using three
   explicit comparisons in their existing field order.
3. Reject `!$open && $final === null`.
4. Retain conditional final>=first and closedAt>=openedAt comparisons in
   their current order; compare timestamps only when closedAt is non-null.

Equivalence is conditional on already-admitted first>=1 and finite
open/closed/sealed state. The original first>1 branch is exactly !genesis.
Open requires all close fields null; closed/sealed requires all present and
final non-null. Together these imply the old final-null→all-close-null clause,
so deleting that redundant clause does not admit empty closed segments or
reject a populated open segment. Final/head correlation remains independent.
All of this remains after actual timestamp validation; no old ingress lower
time bound is added.

#### Existing Parser after extraction

Exactly constructor and public parse with its original three S parameters and
Segment return. Constants only UUID_V7 and SEGMENT_REQUIRED_PATTERNS with their
current exact values/order. No properties. Remove private integer and transfer
HASH/FENCING_TOKEN_PATTERN/SEGMENT_NULLABLE_PATTERNS to TextFields.

Order: exact12 keys → strict requested partition/segment identity and strict
finite state enum → two required patterns → first/final/version Integer calls
→ TextFields.read → assemble ScalarSegment → Lifecycle.assertValid → return
that same twelve-field Segment. Keep the one outer Throwable catch producing
fresh IntegrityInvalid. Native type checks for the two raw IDs are redundant
only after their strict equality to typed requested IDs; state is a native
string by strict membership in the literal string enum. Remove exactly those
three duplicates; keep required grammar checks, including malformed requested
identities. Local narrowing annotations follow these concrete predicates.

### 13.3. Partition, graph and endpoint responsibilities

#### PostgreSqlAuditPartitionStateParser

Methods: private constructor; public static
`parse(S array|false row, S ?AuditTypedReference tenant, S string partitionId): array`
returning PartitionState. Constants: current Graph UUID_V7/HASH/PARTITION,
MAX_SAFE_INTEGER=AuditValidationException::MAX_SAFE_INTEGER, and exact two-entry
STATE_REQUIRED_PATTERNS. No state.
Dependencies: Integer, AuditTypedReference, AuditRecordTime,
AuditValidationException, AuditStoreException/Failure, RuntimeException,
Throwable and SensitiveParameter.

Sole caller Graph.state1. Preserve missing row→SegmentConflict, exact8 keys,
all raw admission before integers and timestamp, and the two existing catches
(finite store enum preserved; otherwise fresh IntegrityInvalid).

After the key gate, construct the actual expected tenant **value pair**:
`$tenant === null ? [null, null] : [$tenant->type, $tenant->id]`.
Strictly compare `[row.tenant_type,row.tenant_id]` with that pair. This is the
same two-column identity used by the SQL fence, not an array of booleans to
hide branch count. Core AuditTypedReference's final readonly typed string
properties (source lines10–16) establish the native scalar types. The exact
pair comparison simultaneously proves both raw primitive/null admissions,
the null-pair correlation and expected-tenant equality. Do not cast or call a
new tenant decoder. Keep strict row.partition_id===requested partition,
the required partition/active UUID pattern loop, nullable head native/hash
grammar and recordedAt native string check. Their pure shape/identity grouping
can be rearranged only within the original pre-integer admission stage.

Convert last_sequence and state_version with Integer. Then replace the two
head/genesis branches by `($lastSequence === 0) !== ($head === null)`; last is
already a bounded nonnegative integer. Call AuditRecordTime only after this
correlation, as before. Return the original eight-field order.

#### PostgreSqlAuditClosedSegmentRelations

Methods: private constructor and exactly three public static methods:

| Method | Return/contract |
| --- | --- |
| neighborRow(S array rows, S string targetId) | array, NeighborRow |
| edge(S array left, S array right) | void; ClosedSegment left, Segment right |
| assertToken(S string partitionId, S array segment) | void; ClosedSegment |

Only constant: private MAX_SAFE_INTEGER=AuditValidationException::MAX_SAFE_INTEGER.
Dependencies: AuditValidationException, RuntimeException, SensitiveParameter.
There is no Parser, SQL, F1 or protection dependency.

neighborRow retains count===1, actual array at index0, native string
segment_id (including the current safe default read), and non-self identity
before returning the raw row plus admitted ID. Other row values remain raw.
Callers: Neighbors.previous1/successor1.

edge is the same directed rotation relation for both neighbors: left.final<MAX
before addition; right.first==left.final+1; right predecessor non-null and
hash-equal to left head; left successor ID==right ID; left.closedAt==right.openedAt.
Keep this exact comparison order. Previous uses left=previous/right=target;
successor uses left=target/right=successor. The successor's early raw ID binding
still occurs before Parser, so this shared relation intentionally repeats the
already-proven successor-ID equality on that path. It is required for the prior
path, and does not replace or move the early raw binding. Callers one each.

assertToken retains the exact typed ENC preimage and domain literal
`blackops.audit.segment.close.v1`, hex2bin false guard, `aft:v1:` SHA-256 and
constant-time comparison with the stored token. It returns nothing. Do not use
a different encoder or expose B2's private helper. Callers Graph.target1,
Neighbors.previous1/successor1. Failures from all three methods are empty
RuntimeException and reach the existing finite outer boundaries.

#### PostgreSqlAuditClosedSegmentNeighbors

Methods: private constructor; public static previous and successor with exactly
the current Graph signatures `(S array rows, S array target, S string partitionId,
S string segmentId)` and ?ClosedSegment/Segment returns respectively. No
constants/state. Dependencies: Relations, Parser, AuditStoreException/Failure,
RuntimeException, Throwable, SensitiveParameter.

Sole callers Graph.previous1/successor1. Both preserve their two current catches.
previous preserves genesis/no-incoming-row first, then neighborRow → Parser →
closed/sealed admission → version>=2 → edge(previous,target) → assertToken →
return. Genesis retains both the empty-row check and null return. successor
preserves neighborRow → raw ID===target.successorSegmentId before Parser →
Parser → edge(target,successor) → only for closed/sealed, version>=2 then
assertToken → return. Open successors remain valid; no active-open requirement
is introduced. Version/MAX/chronology failures remain IntegrityInvalid.

#### PostgreSqlAuditClosedSegmentEndpoint

Methods: private constructor; public static
`assertMatches(S array|false row, S array target, S ?array previous): void`.
Only constants HASH (current lowercase64 grammar) and
MAX_SAFE_INTEGER=AuditValidationException::MAX_SAFE_INTEGER.
Dependencies: Integer, AuditValidationException, AuditStoreException/Failure,
RuntimeException, Throwable, SensitiveParameter. Sole caller Graph.endpoint1.

Target is an actual Graph.target result; previous is an actual Graph.previous
result from the same Reader path. Preserve genesis row===false/previous===null,
non-genesis non-false row/non-null previous, and exact4 ordered keys.
The extra genesis target-predecessor-null test is redundant by Parser's checked
first/hash correlation. In the non-genesis branch the same correlation proves
target.predecessorRecordHash is a non-null canonical hash; any narrow annotation
must cite this executed provenance, not an arbitrary ClosedSegment PHPDoc.

At the raw endpoint grammar stage, require the raw segment_id to be strictly
equal to the already-validated previous.segmentId. This single check proves
its native type, UUID grammar and correct endpoint identity. Require native
string record_hash and hash_equals with target.predecessorRecordHash; equality
to that canonical hash establishes its grammar and expected value together.
Keep nullable previous_record_hash native/hash grammar fully intact even though
it is not used as the expected target edge. Then Integer-convert sequence and
require target.first-1. Preserve both finite catches.

This explicitly folds the former late ID/hash equalities into their corresponding
early pure grammar positions; no SQL, hydrate, bytea, time, key, codec or trust
call crosses that boundary. Wrong raw identity/hash still gives the same fresh
IntegrityInvalid before protected reads. Integer remains after native/hash
admission, not before it. This ordering change must be adopted as stated;
leaving all old grammar checks plus the relocated comparisons would recreate
the known oversized method. Keep raw malformed endpoint tests at this boundary
and malformed target lifecycle tests at Parser; do not delete either family.

#### Existing Graph after extraction

Exactly private constructor and existing public state/target/previous/successor/
endpoint methods/signatures/S maps. Only constant MAX_SAFE_INTEGER remains.
state/previous/successor return their respective helper result; endpoint forwards
without a result. Their helper owns the former finite catch boundary; do not
add a duplicate Graph catch around those delegations.

target keeps its original catch pair and order: missing row SegmentConflict →
Parser → closed/sealed SegmentConflict → version>=2 IntegrityInvalid → target
different from active SegmentConflict → final<MAX ResourceUnavailable →
Relations.assertToken → return ClosedSegment. Remove private closeToken/integer
and their old constants/grants. No Reader/query-order change follows.

### 13.4. Checkpoint byte and binding reuse

#### PostgreSqlAuditCheckpointBytes

Methods: private constructor; four public static methods and one private static:

| Visibility | Method | Return |
| --- | --- | --- |
| public | database(S mixed value) | string |
| public | completeInput(S string bytes) | array, Complete |
| public | unsignedBytes(S array unsigned) | string |
| public | persistUnsigned(S array supplied, S array prepared) | void |
| private | bounded(S string bytes) | string |

Only constant private MAX_BYTES=1048575. Dependencies: AuditCheckpointCodec,
PostgreSqlBytea, AuditStoreException/Failure, RuntimeException, Throwable,
SensitiveParameter. No key/signer/time/trust dependency.

bounded keeps nonempty/cap/no-LF in the present order and returns the same string.
database does one PostgreSqlBytea::string then bounded, catching Throwable as
fresh IntegrityInvalid. completeInput does bounded then one F1 decode with its
current fresh boundary. unsignedBytes does exactly one F1 signingInput and
returns its bytes, with the current fresh boundary. persistUnsigned performs
hash_equals of supplied then prepared unsignedBytes and preserves its current
catch pair. No new complete decode is concealed in canonical comparison.

Exact callers: RowShape.read→database1; StoredBinding.row→database1;
Predecessor.bytea→database1; Binding.completeInput→completeInput1;
Binding.unsignedBytes→unsignedBytes1; Binding.persistUnsigned→persistUnsigned1.
Own calls: database→bounded1, completeInput→bounded1,
persistUnsigned→unsignedBytes2. The existing one lexical F1 decode and signingInput
move from Binding to this class; no second F1 owner remains in Binding.

#### PostgreSqlAuditCheckpointStoredBinding

Methods: private constructor; exactly three public static methods:

| Method | Return |
| --- | --- |
| row(S array existing) | array, StoredComplete |
| identity(S string storedId, S mixed storedHash, S array complete, S string expectedId) | void |
| stateVersion(S string state, S int captured, S int current) | void |

Constants: private UUID_V7/HASH/COMPLETE_STRING_PATTERNS with current Binding
values/order, and private MAX_SAFE_INTEGER=9007199254740991. Dependencies:
Bytes, AuditStoreException/Failure, RuntimeException, Throwable,
SensitiveParameter. No F1 codec dependency; Complete is already decoded.

row owns the current exact3 keys, UUID/hash native pattern loop, then database
byte normalization/limits, then the same three output fields. Keep its fresh
Throwable→IntegrityInvalid catch. Sole caller Binding.completeRow1.

identity is a genuinely shared persisted ID/hash binding used by sealedReplay,
completeReplay and predecessor. First admit storedHash as native lowercase64
string, then hash-compare it to complete.checkpointHash, complete.checkpointId
to expectedId, and complete.checkpointId to storedId, in that order. All four
arguments are S; the first/last IDs are already native strings from earlier
gates, and Complete has the concrete F1 decode provenance. Throw a fresh
IntegrityInvalid directly for failure. The raw hash admission repeats an earlier
row check on replay paths because predecessor's joined row remains raw at this
later stage; it cannot move earlier in the predecessor flow.
In Predecessor, any string narrowing for the mixed storedHash follows this
actual successful call; do not annotate the raw hash as valid before it runs.

For replay this is logically the same conjunction as storedId==expectedId,
completeId==expectedId, storedHash==completeHash, over already-typed strings.
Only pure comparisons within that one failure stage are regrouped; canonical
bytes follow identity and are not evaluated eagerly. Callers exactly
Binding.sealedReplay1, Binding.completeReplay1 and Predecessor.read1.

stateVersion keeps captured2..MAX-1 first, then strict closed/sealed membership,
then equality with `state === 'closed' ? captured : captured+1`. The addition
occurs only after safe bounds and enum admission. This is the current five-way
condition written as its actual version rule, not a lookup/boolean-array trick.
Direct failures remain fresh IntegrityInvalid. Callers Binding.stateVersion1
and SegmentBinding.preparation1.

#### PostgreSqlAuditCheckpointSegmentBinding

Methods: private constructor; public static preparation with the original four
S Boundary/Proof/Unsigned/?Previous arguments and void return; public static
`predecessor(S array boundary, S array segment, S array unsigned): void`;
three private static methods:

| Method | Contract |
| --- | --- |
| boundary(S array boundary, S array identity): void | Boundary and SegmentIdentity |
| range(S array unsigned, S array reference): void | Unsigned and RangeBinding |
| tail(S array unsigned, S string headHash, S string sealedAt): void | exact head/time equality |

Constants: private BOUNDARY_STRICT_FIELDS=['partitionId','segmentId']; private
UNSIGNED_RANGE_FIELDS=['partitionId','segmentId','firstSequence','lastSequence',
'recordCount']. No other constants/state. Dependencies: StoredBinding,
AuditStoreException/Failure, RuntimeException, Throwable, SensitiveParameter.

boundary performs the existing ordered strict identity loop then token
hash_equals. range performs the existing ordered five strict comparisons.
tail hash-compares chainHeadHash then sealedAt. All references are explicit
small value projections of already-validated objects; no configurable field
map, mode, callback or fabricated full Proof is introduced. The private helpers
throw empty RuntimeException; preparation retains the original finite catches,
and predecessor is enclosed by the existing Predecessor.load boundary.

preparation order remains boundary → range from the actual Proof (including its
actual proven recordCount) → firstRecordHash comparison → tail(head,closedAt)
→ StoredBinding.stateVersion → previous checkpoint hash relation. Replace only
the latter's redundant four-way null branching by an absence-correlation check
`($previous === null) !== ($previousHash === null)`, then, if previous is present,
hash_equals the already-proven non-null previousHash with previous.hash. Keep
the explicit previous-present guard for hash evaluation. This preserves absent,
present/null, present/different and present/equal outcomes without eager hashing.

predecessor performs boundary against parsed segment identity, then range with
last=segment.finalSequence and count=final-first+1, then tail(head,closedAt).
The old predecessor sealedAt strict string comparison and hash_equals have the
same equality result for these validated strings; adopt the latter explicitly
to reuse this actual binding. It does not invent a firstRecordHash proof or
check an unprovided previous-checkpoint relation. Its requested identities were
already strictly bound by Parser and the joined-row admission before decode.

Callers: Binding.preparation→preparation1; Predecessor.read→predecessor1.
Own calls: each public method→boundary1/range1/tail1. The current three-element
UNSIGNED_HASH_PAIRS table is removed: first hash stays explicit in preparation;
the genuinely shared head/time pair belongs to tail. This is an exact table
authority change, not permission to drop any comparison.

#### Existing Binding, RowShape and Predecessor

Binding retains all nine current methods/signatures/S maps and no properties;
all its old constants move to the owners above or are replaced as stated.
preparation/persistUnsigned/completeInput/unsignedBytes/stateVersion delegate
once to their matching helper, without extra catch/codec work. Private
completeRow delegates to StoredBinding.row. sealedReplay and completeReplay
keep their current outer catch pairs and order of staged work:

- sealedReplay: completeRow → completeInput once → remove only hash/signature
  from local Complete → StoredBinding.identity → persistUnsigned → return.
- completeReplay: completeRow → StoredBinding.identity → stored bytes
  hash_equals supplied completeBytes → return. No decode or fresh trust check.

RowShape retains constructor/read, its five current constants and exact7
output fields. Remove private integer; the old version stage calls Integer
before the old byte stage calls Bytes.database. The outer fresh catch remains.

Predecessor retains final readonly modifier, sole private rows property and
four existing methods/constructor/signatures/S maps. load's two SQL reads stay
outside its try, in current order; absence/genesis/error handling is unchanged.
read keeps the joined exact9 keys before Parser, closed/sealed admission,
version>=2 and early requested raw partition/segment/checkpoint-ID admission.
Keep the exact7 preparation projection and factory. Then:

`fromRow → bytea → Binding.completeInput → local unsigned projection → boundary
→ prepared unsigned → Binding.stateVersion → StoredBinding.identity
→ Binding.persistUnsigned → SegmentBinding.predecessor → Previous return`.

private bytea now calls Bytes.database. This performs the same normalization
and byte limits before the same F1 decode; it does not perform a second decode.
The two complete ID/hash native-type checks at current112–113 are removed only
on the concrete successful F1 decode provenance. The existing canonical byte comparison
is replaced at exactly its current position by the existing persistUnsigned
semantic operation (now delegated to Bytes): same supplied then prepared F1
canonical calls, same finite result, zero complete decoding or trust. This is
an explicitly adopted new caller, not silent use of sealedReplay or a later
combined check. Return remains `{bytes:completeBytes,hash:validatedStoredHash,
value:complete}`. No DB row is cached and no latest/predecessor lookup changes.

### 13.5. Protected record and bounded page ownership

#### PostgreSqlAuditClosedSegmentRecord

Final readonly, exactly two private readonly promoted collaborators,
`hydrator:PostgreSqlAuditStoredRecordHydrator`,
`protection:AuditProtectedBodyCodec`, in that constructor order; no constants.
Exactly public constructor and public instance
`read(S array row, S ?AuditTypedReference tenant, S array target,
S int expected, S ?string headHash): string`. Dependencies exactly those
collaborators, AuditTypedReference, AuditStoreException/Failure and
SensitiveParameter. No CompleteAuditRecord constructor/type/callback edge.

Move the existing private RecordProof.record here, preserving exact17 keys,
17→14 projection, one hydration, one unprotect, snapshot/integrity access,
physical index equality, decoded and physical partition/segment binding,
sequence, closedAt upper bound, nullable predecessor hash relation and local
disposal. Apply only the six post-decode predicate removals qualified below and
their associated default/conditional reads. Direct action/resource/time fields
are justified by the successful concrete final decode chain, not by the
CompleteAuditRecord type or a PHPDoc-only assertion. Its public constructor can
store arbitrary arrays; it is not the provenance being relied upon.

All ten independent remaining equality/time/edge predicates stay. No local
catch is added: RecordProof's finite classification boundary still distinguishes
protected-decode ResourceUnavailable from successfully decoded corruption's
IntegrityInvalid. Every new raw-data frame is S. Successful execution unsets
the decoded/stored/physical/logical/integrity/action/resource/time values before
returning only recordHash. Sole caller Pages.read1, once per actual record.

#### PostgreSqlAuditClosedSegmentPages

Final readonly, exactly two private readonly promoted properties:
queries:PostgreSqlAuditClosedSegmentQueries and record:PostgreSqlAuditClosedSegmentRecord,
in that constructor order; no S constructor arguments. Exactly constructor and
public instance `read(S ?AuditTypedReference tenant, S array target): array`,
returning PageAggregate. Only constant MAX_SAFE_INTEGER=AuditValidationException::MAX_SAFE_INTEGER.
Dependencies: those two collaborators, AuditTypedReference,
AuditValidationException, AuditStoreException/Failure, SensitiveParameter.

Sole caller RecordProof.prove1, after target identity and tenantMismatch.
Move only the existing fixed32 keyset loop: after='0', expected=first,
head=target.predecessor, firstHash=null, count=0. Keep actual list/row checks,
Record.read, first-hash capture, rolling head, after=(string)expected, count++,
MAX check before expected++, and page length===32 continuation. Release page,
row and last local record hash before the next query. Keep no accumulated
record list, configurable size, OFFSET, range filter or total-record cap.
Return only count/next expected/first hash/head. No catches, callbacks,
generators, long-lived scan cursor or page/result state are introduced.

Queries.records' sole lexical owner moves from RecordProof.prove to Pages.read.
Use target's partition/segment, strictly bound by RecordProof before entry; the
query values are therefore identical to the former explicit parameters.

#### Existing RecordProof after extraction

Constructor signature/order stays `(Queries queries, Hydrator hydrator,
AuditProtectedBodyCodec protection)`, no S. Class remains final readonly.
Final properties are exactly private readonly queries and pages:Pages; remove
retained hydrator/protection properties and the old MAX constant. Construct
one local Record from the original hydrator/protection, then one Pages from
the same Queries instance and that Record. RecordProof keeps no direct Record
property. Reader's constructor and arguments remain unchanged.

Exactly three methods remain: constructor, existing prove with four S arguments,
and existing private safeFailure(S Throwable):AuditStoreException. Remove record.
prove keeps requested target identity then tenantMismatch before Pages.read.
After the returned aggregate, use a dedicated count===0→SegmentConflict check,
then the original nonempty count/range/next/first/head/hash conjunction as
IntegrityInvalid. Splitting the old conditional exception removes only its
redundant count ternary: it preserves empty versus other failure precedence.
Retain explicit nextSequence, count, nullable first/head and final head checks;
do not replace them with a returned PHPDoc assertion. Return the same three
validated non-null fields. Preserve the existing three catches and safeFailure
logic exactly. No result escapes an incomplete scan.

### 13.6. Exact dependency and owner changes

Besides the new exact dependency sets above, the six changed dependency sets
become the following (ordinary native RuntimeException/Throwable/
SensitiveParameter names are included where actually used):

| Existing class | Final type dependencies |
| --- | --- |
| RowShape | Integer, Bytes, AuditStoreException/Failure, RuntimeException, Throwable, SensitiveParameter |
| Parser | Integer, TextFields, Lifecycle, AuditValidationException, AuditStoreException/Failure, RuntimeException, Throwable, SensitiveParameter |
| Graph | StateParser, Parser, Neighbors, Endpoint, Relations, AuditTypedReference, AuditValidationException, AuditStoreException/Failure, Throwable, SensitiveParameter |
| Binding | Bytes, StoredBinding, SegmentBinding, AuditStoreException/Failure, RuntimeException, Throwable, SensitiveParameter |
| Predecessor | Rows, Parser, Preparation, Binding, Bytes, StoredBinding, SegmentBinding, AuditStoreException/Failure, RuntimeException, Throwable, SensitiveParameter |
| RecordProof | Queries, Pages, Record, Hydrator, AuditProtectedBodyCodec, AuditProtectionException, AuditTypedReference, AuditValidationException, AuditStoreException/Failure, Throwable, SensitiveParameter |

Graph.target no longer needs RuntimeException directly: assertToken owns its
old only direct raw-runtime throw. Binding sealedReplay's temporary Unsigned
projection remains local; no new public getter or retained bytes follow.

Unchanged outside callers remain exact: Reader→Graph's five methods and
RecordProof.prove; Store/PreparationWorkflow→Binding/Predecessor as currently
adopted. No caller gets a broader class/namespace allowlist. All new helpers
have only the callers enumerated in this amendment.

Required external-owner transfers (delete superseded grants, never allow both):

| Existing edge | Final owner/count |
| --- | --- |
| F1 AuditCheckpointCodec.decode in Binding.completeInput | Bytes.completeInput1 |
| F1 AuditCheckpointCodec.signingInput in Binding.unsignedBytes | Bytes.unsignedBytes1 |
| B2 Hydrator.hydrate / A2 Codec.unprotectRecord in RecordProof.record | Record.read1 each |
| Queries.records in RecordProof.prove | Pages.read1 |
| Queries.tenantMismatch | RecordProof.prove1 unchanged |
| AuditRecordTime.assertTimestamp in Parser | TextFields.read2 |
| AuditRecordTime.assertTimestamp in Graph.state | StateParser.parse1 |
| PostgreSqlBytea.string in RowShape/Binding/Predecessor | Bytes.database1 |

AuditRecord/AuditStore/AuditArtifact guards must add only actual type/method
edges above. All classifier, trust, Catalog and runtime provenance boundaries
stay unchanged. No new A2 constructor/append/finalizer or F1 signer/private key/
shape-decoder edge is authorized. Integer remains private to Checkpoint rather
than widening B2 or creating an F1 scalar dependency.

Construction graph: the only new objects are RecordProof.__construct→Record1
and Pages1 with the same supplied collaborator instances. All other new types
are stateless/nonconstructible. Pages stores Queries/Record; Record stores
Hydrator/Protection. None owns Connection, transaction markers, DB roots,
Clock, UUID, trustedSnapshot or accumulated raw/decoded data.


### 13.7. Guard, package and proof obligations

Guards must capture every exact path, signature, S attribute, property, constant,
dependency and caller above. Remove superseded owner grants; preserve exact
declaration and own-instance call/count scanning and all earlier mutations.
RecordProof constructs Record/Pages from the same original collaborators.
No namespace exemption or new signer/protect/finalizer/append owner is granted.

Append the twelve new package paths in the order above after Workflow/Policy and
before Storage/AuditStoreFailure. Arithmetic192 is not evidence. Retain all143
existing controls and four runtime omissions, adding omission/order controls
for every new path. Internal note changes only its responsibility map; public
Source/Search/LLM/release boundaries remain unchanged.

Retain all P1–P12 and verify actual raw integer grammar/min/max; partial-null or
wrong-type tenant identity; empty/populated open and independent closed-field
nullability; prior/next ID/edge/time/version/MAX/token; endpoint raw grammar;
physical/logical mismatch versus protected-decode failure; predecessor raw keys
and stage ordering; exact replay; >32 pages, empty/missing/extra/reordered pages,
MAX continuation, local disposal and privacy through every new S frame.
New cases must test these invariants rather than helper names.

Source complexity estimates are not a quality pass. Actual formatter, strict
lint/analyzer and required final gates determine readiness. Return any further
method/type/scope requirement to Root without automatic expansion.

## 14. Existing workflow failure consolidation

Root adopts source reviewcd207273's exact two-file correction. Product29 and
Task47 remain; no Policy/type/method/signature/S/dependency/caller or package
change. This qualifies §12 only as follows.

Transaction.abort replaces its separate quarantine and beginning branches with
one strict phase family ['beginning','quarantined','quarantine_close_failed']:
call existing closeOnce, then return StorageUnavailable. Keep preceding ready,
following isolating/committing and owned-tail paths unchanged. closeAttempted is
monotonic; both quarantine values can only be assigned after it becomes true.
Therefore closeOnce is an immediate no-op for reachable quarantined states,
without Connection access, phase mutation, another close attempt or exception.
The beginning phase already used the same call/result. Entry rejection, rollback
attempt before enum decision, commit ambiguity and reuse prohibition are unchanged.
No reflection-created impossible state expands the runtime contract.

Reader.proveInCurrentTransaction retains its operation try body and pre-try
admission textually. Replace its three catches with the existing final
Throwable→safeFailure catch alone. safeFailure is unchanged and already maps
Store enum first, protection→ResourceUnavailable, validation→IntegrityInvalid,
other→StorageUnavailable, returning fresh exceptions without causes. Its Throwable
argument remains S. No SQL/decode/Clock/UUID/trust/effect ordering moves.

Both files retain all constants/properties/constructors/methods and lexical
own-call counts: Transaction.beginRoot closeOnce2/rollbackOwned1; commit1/1;
abort3/2; Reader.prove assertConnection1/proofResult1/safeFailure1. Policy calls
and all actual completion effects retain their existing exact owners/counts.

Implement this only after the functional owner's §13 handoff, retaining that
separate evidence. Existing repeated-abort, throwing-close, marker, rollback,
commit-loss, fresh-exception and privacy cases remain required. Complexity16
and difficulty12.5 are the r6 measurements; improvement must be measured. No
new helper or quality exception is authorized if another residual remains.

## 15. Final bounded grammar clarification

Root adopts review29cbb026 after the actual r7 metrics. Product29/Task47, all
public signatures and dependencies stay unchanged. No new type, threshold or
baseline is granted. Only the following runtime delta is authorized.

PartitionStateParser.parse consolidates its two repeated non-null head guards
into `$head !== null && (!is_string($head) || preg_match(self::HASH, $head) !== 1)`
at the same raw-admission position. This keeps null admissible at that stage,
rejects a non-string before preg_match, and validates string grammar before
integers/time. No field/value, failure class or effectful boundary changes.

Reader adds exactly one private, non-static, non-final method:
`assertIdentifiers(S string partitionId, S string segmentId): void`. It has no
defaults, references, variadics, state or construction. S is SensitiveParameter
on both inputs. Move only the existing two-regex grammar if/IntegrityInvalid
throw from proveInCurrentTransaction into this helper, unchanged. The prove
method calls it once immediately after assertConnection and before the existing
operation try. Connection→grammar→derive→lock/graph/records→result and the current
finite catch mapping stay in that order; no helper exception crosses a new catch.
Place the helper after assertConnection and before safeFailure, leaving the
existing proofResult→assertConnection source-purity window intact.

The exact new callable/S entries reflect this two-string void method. Reader
prove's own-method map becomes assertConnection1, assertIdentifiers1, proofResult1,
safeFailure1. The new helper has an explicit empty own-instance call map. All
other class/method/collaborator/caller maps remain. Existing missing/duplicate/
wrong-owner mutation controls and identifiers/privacy behavior remain required.

The already-assigned PHPDoc layout correction places each unchanged tag on a
separate line so the analyzer recognizes exact shapes. Removing redundant
property readonly keywords from final readonly RecordProof preserves effective
readonly reflection; no state contract changes. Actual formatted gates must
prove these corrections. CurrentCC16/difficulty12.14 are measured failures;
estimated improvement is not a result or permission for another extraction.

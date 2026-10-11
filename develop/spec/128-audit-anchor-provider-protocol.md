# Specification 128: Audit Anchor Provider Protocol

Status: Adopted provider contract; Product issuance/native qualification pending;1.3 unreleased

## Authority and boundary

Root adopts this reviewed provider portion of
[specification127](127-audit-anchor-durable-external.md) on2026-10-12.
[D178](../decisions/178-audit-anchor-provider-protocol.md) records the choice.
The [F4 Task](../orchestration/tasks/P23-003F4-audit-anchor-durable-external.md)
still requires exact implementation ownership, all local fourteen operations and
real F5 authenticated provenance. This specification issues no Product file,
migration installation, deployment, public capability or implementation Acceptance.

## Identity and SQL admission

Use PostgreSQL18, an independently administered primary and existing DBAL4.4.3.
Use fixed private schema `blackops_anchor_provider_v1`; install separately from
application migrations. Protocol version1 and an operator-installed UUIDv7
provider ID are stored in an immutable one-row installation table. The adapter
pins that ID and the verified TLS endpoint/CA/hostname. A second Connection alone
does not establish independent administration/storage or credential custody.
A replica, wrong installation or changed protocol is unavailable, never absence.

There are exactly two granted functions: `lookup_v1` and `create_v1`.
Their first15 positional parameters follow this exact semantic order:

| Parameter | SQL representation | Provider admission |
| --- | --- | --- |
| intentId | bytea |1..256 exact bytes|
| partitionId | bytea |ASCII system:global or tenant: plus64 lowercase hex|
| checkpointId | bytea |1..256 exact bytes|
| checkpointHash | bytea |32 bytes|
| checkpointBytesHash | bytea |32 bytes|
| anchorStoreRef | bytea |1..256 exact bytes|
| anchorObjectRef | bytea |1..256 exact bytes|
| expectedPredecessorVersion | bigint nullable |null or1..9007199254740990|
| expectedPredecessorHash | bytea nullable |null or32 bytes|
| conditionalCreateToken | bytea |1..256 exact bytes|
| segmentId | bytea |36 ASCII bytes, exact lowercase UUIDv7 grammar|
| previousAnchorReceiptHash | bytea nullable |null or32 bytes|
| checkpointSignedAt | text |27 ASCII characters, canonical valid UTC six-fraction timestamp|
| anchorReceiptId | bytea |1..256 exact bytes|
| idempotencyKeyHash | bytea |32 bytes|

`create_v1` adds parameter16 `completeBytes bytea`, exactly1..1048575 bytes.
Both functions are VOLATILE and PARALLEL UNSAFE. They are CALLED ON NULL INPUT, never STRICT: null predecessor fields
are meaningful and every other null is rejected. No defaults, variadic fields,
JSON/jsonb record input, dynamic SQL or caller-selected schema/identifier exists.

The predecessor version/hash and previous receipt hash are all null at chain
genesis, all non-null otherwise. The exact intended slot is N=1 or checked
predecessor+1, never inferred from the current head after conflict. Attempt,
lease, local revision and retry time are not provider identity.

The PHP boundary reuses F1/F3 admission for native types, exact15 fields, NFC,
Cc/Cf rejection and opaque ID semantics; no array/object references are retained.
SQL separately validates its native types/null pairing, byte counts, partition,
segment, canonical calendar timestamp, numeric limits and received-byte digest.
Generic SQL bytea refs are deliberately opaque; SQL does not claim to implement
Unicode/F3 admission, Ed25519 verification, JCS decoding or current trust.
Arbitrary malformed client metadata cannot alias an existing request or rewrite
its body. The operator trusts the actual F1/F5 caller to bind metadata semantics
to the admitted checkpoint. Hashes use raw32 SQL bytes and exact lowerhex PHP
conversion; this is private storage encoding, not another artifact wire.

## Exact keys, immutable rows and byte binding

`chains` is keyed by exact `(partition_id,anchor_store_ref,anchor_object_ref)`.
Each key uses bytea; maximum payload71+256+256=583 bytes. Its mutable fields are
head_version0..9007199254740991 and head_object_hash(null exactly at0).
`objects` is keyed by that triple plus positive bigint version, FK to `chains`.
It stores the15 immutable request members, original accepted_at timestamptz(6)
and complete_bytes. Store checkpointSignedAt in its original canonical text as
well as using a validated parsed value for chronology; do not normalize input.
The provider installation row, objects and their metadata expose no update/delete.
Only create updates the locked chain head by one after an inserted next object.
All tables are ordinary logged tables; no unlogged/temp storage or deferred
uniqueness. Maximum-width key execution is mandatory on the supported8192-byte
block configuration. No new chain/request digest or collision policy is needed.
Compare every request member exactly, with NULL-aware equality, on replay.

The server recomputes checkpointBytesHash using the existing typed ENC domain
`blackops.audit.anchor.checkpoint-bytes.v1`, exact received B and UTF-8 domain
byte length: `s:<domain length>:<domain>b:<B length>:<B>`, then built-in sha256.
No LF, text conversion of B, JSONB output, ETag or bare body hash substitutes.
Lookup returns the stored bytes; the adapter recomputes this same digest and
compares every stored request field to the immutable local association before
making the closed A5 found result. Generic request digest is deliberately absent;
existing hash plus exact original columns/bytes supplies replay association.

## Serialization and actual provider time

The adapter requires Connection auto-commit enabled, no active transaction and
nesting0; it owns a fresh READ COMMITTED transaction, never an application/F4
local-intent transaction. Require server fsync/full_page_writes on and a primary,
then SET LOCAL synchronous_commit=on, lock_timeout=2s, statement_timeout=5s.
Check the effective synchronous setting immediately before its sole COMMIT.
The SQL functions reject an incompatible effective environment rather than
silently selecting another backend or weakening guarantees.

Create inserts an initial chain row on exact-triple conflict DO NOTHING and then
locks that chain row. Read N first. If N exists, return its original values only
if all15 request fields and completeBytes match; otherwise conflict. Later chain
progress does not invalidate an identical retry of an earlier occupied slot.
If N is absent, the stored head and its actual predecessor object/hash must
match the supplied predecessor. A skipped version or mismatched predecessor
creates nothing. An empty head is valid only for genesis N1.

For a new object, sample provider clock_timestamp exactly once after successful
serialized admission/predecessor checks, immediately before insertion. The
actual value must be a supported canonical calendar time and >=checkpointSignedAt;
otherwise return unavailable without an object or head advancement. Persist this
value and unchanged body with the single head advance. It is the provider's
acceptance-decision instant, not transaction start or physical COMMIT time.
Retry returns its original time; no clamp or local timestamp substitutes.

INSERT/RETURNING and function return are buffered provisional data. Every result
that can drive an A5 decision, including permanent create rejection and explicit
absence, follows the adapter sequence below. No function-side WAL wait occurs.
After the function read completes, the adapter captures pg_current_wal_insert_lsn
exactly once with a fixed query in its still-owned root. It then checks the
synchronous setting and performs its sole normal standalone COMMIT. Only after
COMMIT does it apply the saved-LSN barrier on the same physical primary session.
No result escapes before both normal commit and the barrier succeed. In
particular, an occupied slot/head underlying a permanent rejection is covered,
not just a matching found row. A SQL unavailable/error result grants no durable
fact; roll back the open root, sanitize it and discard the session on uncertainty.

Failed/uncertain COMMIT or barrier yields external_unavailable and discards the
session. Recovery starts a new authenticated lookup for exactly N, never another
slot. Neither a held result nor its captured LSN can resume on a replacement
connection. Direct primary connections are required for this initial contract;
transparent reconnect/pooling/failover inside an attempt is unsupported. Verify
physical backend identity across the post-commit phase, in addition to TLS and
provider installation identity; any change is unavailable.
No acceptedAnchoredAt<=local settlement-now rule is added; F3 later enforces its
existing receiptSignedAt chronology using a genuine framework Clock sample.

## Durable lookup and asynchronous writer edge

A completed authorized lookup for N with no row is the only explicit absence.
Permissions, parser/shape failures, timeout, identity mismatch or unknown commit
cannot become absence. A concurrent delayed create can still occupy N later;
the subsequent conditional create remains bound to N and cannot allocate N+1.

Durability needs more than session defaults. Another holder of execute rights
can set synchronous_commit=off before its own COMMIT. SQL visibility and a
reader's read-only COMMIT therefore do not by themselves prove stored durability.
The adapter waits only after its own normal COMMIT: the same primary's
pg_current_wal_flush_lsn must reach the one insert LSN captured after the result
read. A create replay's row-lock WAL cannot hold up its own COMMIT, because no
barrier runs inside either granted function or before COMMIT. Do not recapture
a moving end, substitute writeLSN for flushLSN or cross physical connections.

The earlier SET LOCAL statement_timeout expires at COMMIT. Before the separate
wait phase set the private session's statement_timeout to250ms and enforce a
1-second monotonic adapter deadline, at most100 probes and at most10ms between
unready probes. Check the deadline before every probe and before exposing data.
A timed-out query, malformed/false-at-deadline result, transport failure or
backend change yields unavailable and closes the private connection. Connection/
transport timeouts remain required separately; the monotonic loop bound is not
presented as a hard wall-clock guarantee under a stalled network. Resource-limit
monotonic time never supplies A5 lease/transition or provider acceptance time.
Keep the tighter session timeout within this private connection or close it;
never return it to an unrelated caller/pool.

This conservative saved-LSN barrier can include unrelated uncommitted WAL and
may time out; it makes no availability guarantee under such concurrency. A
successful barrier proves the required durable prefix under the stated primary/
logged-storage assumptions. A new insert additionally depends on its actual
successful synchronous COMMIT; flushing its insertion record never commits it.
No result/LSN column, new public field, provider grant or digest is added.

This barrier is an independently reviewed conditional contract; real native
evidence is still required and no PostgreSQL implementation is qualified. Prove a genuinely visible
asynchronously committed matching or conflicting row with captured insertLSN>flushLSN
cannot escape before flush; prove an otherwise idle identical replay reaches
COMMIT before waiting for WAL caused by its own logged row lock; a test that never observes that precondition cannot qualify the negative.
Superuser/OS/storage hardware honesty and whole-provider rollback remain outside
the promised protection; trusted durable storage cannot be proved by SQL flags.

## Fixed private result and finite mapping

Both functions return exactly one flat21-column row: `protocol_version,provider_id,
status`, the15 request members in the table order with fixed snake_case column
names, then `object_version,accepted_at,complete_bytes`. No composite/JSON decoder
is required in DBAL. Status is
`found|absent|predecessor_mismatch|request_conflict|unavailable` for lookup/create
as follows: lookup allows found/absent/request_conflict/unavailable; create allows
found/predecessor_mismatch/request_conflict/unavailable, never absent. Found has
exact original request (including its valid genesis nulls), positive object
version, original timestamp and complete bytes; all other statuses have all18
request/object columns null. Protocol/provider identity
are always non-null and compared before interpreting any status. Cardinality0/2,
extra/missing columns, unknown status or invalid types are malformed/unavailable.
The flat result is private SQL data; no JSON artifact or A5 field is added.

Lookup found converts to the existing closed6 foundObject only after full byte/
identity validation. Lookup request_conflict or corrupt stored association is a
finite integrity failure with no local revision, not absence or an invented
terminal lookup outcome. Create predecessor_mismatch maps to A5 rejected with
that exact reason; request_conflict maps to rejected/permanent_rejection only
when proven against an existing incompatible immutable slot/request. Operational
SQL failure always maps to unavailable. Client-side invalid admission fails
before I/O. The actual active attempt is supplied only by the local durable owner
when constructing external_result; neither SQL nor provider counters choose it.
No provider scheduling/retry hints are accepted. All diagnostics are sanitized,
with sensitive parameters/bytes and no SQL/DSN/causal exception leakage.

## Privileges, release boundary and qualification

One NOLOGIN owner owns the fixed schema/tables/functions. Application credentials
are a separate login role, not owner/member/superuser/CREATEDB/CREATEROLE/BYPASSRLS.
Grant only CONNECT, schema USAGE and exact EXECUTE on the
2 function signatures. Revoke PUBLIC execution and CREATE in the same install
transaction. Definer functions use fixed `pg_catalog,blackops_anchor_provider_v1,
pg_temp` search_path and qualified objects, with no untrusted operator resolution.
No direct table access, role change, DDL or function replacement is granted.

Passing completeBytes as a SQL parameter is signature release. Ordinary F5 must
have performed actual current authorization/trust/Required commit for these exact
held bytes before the fixed create continuation. Lookup needs only15 metadata
members, never an early upload of bytes. Unregistered F4 primitive fixtures are
explicitly pre-admitted/released bytes, not a production authorization substitute.
No Runtime composer/provider configuration or control exemption is issued here.

Required native proof covers real roles/TLS wrong host/CA/identity, default PUBLIC
and temp-shadow attacks, table/function/role privilege denial, native bounds at
1/256/maximum and256+1, malformed/null/pair/overflow admission, byte digest vectors,
full15-member replay substitution, different byte bodies, concurrent same/different
create, stale replay after later versions, real rollback/commit response loss,
fresh connection/process, original-time retention, same-second microsecond and
clock-behind no insert, authoritative absence, async visibility/durability barrier,
exact max-width keys and noN+1. Gate files/resources/cleanup still need an issued
packet; no runtime or native test has been run for this proposal.

## Research evidence and remaining qualification

Official PostgreSQL18 docs were read on2026-10-10. Binary octet operations avoid
locale-sensitive identity: https://www.postgresql.org/docs/18/datatype-binary.html .
Definer search_path and atomic PUBLIC revocation follow
https://www.postgresql.org/docs/18/sql-createfunction.html .
Durability choices and the per-transaction setting at actual commit follow
https://www.postgresql.org/docs/18/runtime-config-wal.html ; the storage/hardware
limit remains https://www.postgresql.org/docs/18/wal-reliability.html .
The visibility barrier is Root's reviewed inference using the documented insert/
write/flush distinction at https://www.postgresql.org/docs/18/functions-admin.html ;
its native proof remains mandatory; it is not a claim directly established by
the documentation. Transaction and TLS contracts use
https://www.postgresql.org/docs/18/transaction-iso.html and
https://www.postgresql.org/docs/18/libpq-ssl.html .

Before Product issuance, adopt finite F5 pre-genesis reservation and whole-carrier
provenance; finish the local14-operation/history/receipt contract; freeze exact
SQL/PHP/test/package ownership and Root native resources, and review that complete
issuance packet. F3 Acceptance remains a prerequisite. This contract alone grants
no complete F4/F5, signing/seal or release qualification.

Independent provider review r1 identified F4-EP-01. Correction review r2 closes it
at contract level: normal standalone COMMIT precedes the saved-LSN same-session
barrier, including permanent-rejection/absence decisions and separate timeouts.
The native cases and physical-session/durability conditions above remain mandatory.
The [decision](../decisions/178-audit-anchor-provider-protocol.md) and
[F4 Report](../orchestration/reports/P23-003F4-audit-anchor-durable-external.md)
retain the proposal/review hashes and adoption comparison. No native proof is
claimed by moving the reviewed text into permanent authority.

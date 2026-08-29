# D147: Audit Catalog and Safe Projection Boundary

Status: Accepted P23-003A1／A2／parent A and P23-003B1 — P23-003B2 next; BlackOps 1.3 unreleased

Updated At: 2026-08-29T16:15:29+09:00

## P23-003B1 current handoff

Current status: `Accepted — Complete Final Gate Green; candidate commit/package export next`.

D148 is the more specific current authority for canonical logical／complete
record bytes、trusted stamp、content／record hash、tenant partition、Audit-specific
BOPD AAD／protected body、durable ingress、and the serial B1／B2 boundary.
Accepted A1 exact48 and A2 exact28 remain byte-identical. The A2 Architecture test
alone is an authorized B1 delta that admits Candidate／Canonical logical-record
implementations exact2 and constrains their separate construction callsets. The
initial and corrected-vector preflights are Historical Changes Requested. D148
pins the canonical-chain and durable-ingress fixtures, protected hydration,
capacity, no-leak, and BOPD contracts. The final bounded closure is Green
`P1=0/P2=0/P3=0`; the Accepted exact33 aggregate is
`bb6ab89a222a72eafa601f588f46177ee443e554c036bcc00575b211961c8847`,
and the Complete Final Gate is Green. B1 changes no Stable `1.2` or public
behavior and activates no runtime、database、route、or CLI surface.

Remaining: reviewed candidate commit／same-SHA package export, then B2 and
ordered downstream. Next Action: commit and package-verify B1, then draft the
bounded P23-003B2 packet.

## Context

P23-002E is Accepted. Stable `1.2.0` remains current and BlackOps `1.3`
remains unreleased.

P23-003A must define the Audit event catalog, AuditRecord schema boundary,
security/access event types, and observed safe projection before encrypted
append, delivery transaction, adapters, Application authoring, verification,
query, or export can depend on them.

The prior proposal fixes useful field sets and bounds, but it does not yet
produce one implementation result:

- `ENC(JCS(...))` does not say whether JCS is encoded as a string or bytes;
- the minimum Framework catalog does not fix every stored field or its hash;
- `opaque_id` fixes only its 71-byte wire and not its keyed derivation;
- evidence optionality and nullability are not one closed matrix;
- the read family does not decide whether `.requested` precedes a terminal event;
- the illustrated JSON projection is not a normative closed schema; and
- a producer draft, trusted logical record, and integrity-complete record are
  not separated by construction authority.

These are contract gaps. A Production worker must not choose among them.

## Decision

### 1. Delivery slices and activation boundary

P23-003A is delivered as two serial child packets:

1. `P23-003A1` owns the closed catalog types, catalog-only RFC 8785 JCS and
   `catalogHash`, evidence descriptors, semantic tuple validation, sealed
   Framework security/access catalog, typed opaque-ID derivation, and
   `AuditReadContext v1` hash contract.
2. `P23-003A2` consumes Accepted A1 and owns the producer draft schema, trusted
   logical-record schema, catalog-resolved semantic/evidence validation, and
   the exact observed safe projection.

A1 and A2 are not parallel. Neither child registers Application events,
activates runtime adapters, writes an Audit store, emits JSONL, or changes a
public route. Parent P23-003A becomes Accepted only after both children are
Accepted. P23-003B cannot start before that parent Acceptance.

P23-003B owns canonical AuditRecord logical bytes, trusted record stamping,
content/record hashes, integrity construction, encrypted append, and chain
state. P23-003C owns required/durable transaction and relay behavior.
P23-003D owns Framework runtime adapters. P23-003E owns Application
registration and projector authoring. P23-003F/G own artifact verification,
query, and export.

### 2. UTF-8, normalization, validation order, and JCS

Before the semantic validator runs, the raw complete Catalog input has one
resource-safety guard: more than 1,048,658 bytes returns
`jcs_bound_invalid` without invoking the JSON decoder or making an
input-proportional allocation. This raw-envelope guard has precedence over
every defect inside the bytes, including malformed JSON, duplicate raw keys,
and invalid UTF-8. The ten-stage order below applies only after the raw input
passes that guard. Reusing `jcs_bound_invalid` here does not claim that the
oversized input was decoded or that its syntax was otherwise valid.

Audit strings must already be valid UTF-8 and NFC. The validator does not
silently normalize input. Forbidden control characters are every Unicode
General Category `Cc` or `Cf` code point; invalid UTF-8 surrogate encodings
fail at the UTF-8 step. Private-use `Co` values are not rejected merely for
their category, but field grammar may still reject them. The fixed validation
order is:

1. closed shape and scalar type;
2. valid UTF-8;
3. forbidden control characters;
4. NFC equality;
5. UTF-8 byte bound;
6. field grammar;
7. normalized-byte uniqueness and required sort order;
8. semantic relationships;
9. aggregate JCS byte cap; and
10. hash equality.

Compound-invalid input reports the first applicable safe failure category in
that order. Exceptions and validation results never echo the rejected value.
Existing Stable `ActorRef`, `TenantRef`, Authentication, and Authorization
types are not strengthened or changed by A1/A2; Audit uses dedicated snapshot
types.

The ten stages expose only these matching safe failure codes, in the same
order:

```text
shape_invalid
utf8_invalid
control_invalid
nfc_invalid
byte_bound_invalid
grammar_invalid
order_or_uniqueness_invalid
semantic_invalid
jcs_bound_invalid
hash_mismatch
```

JSON syntax, scalar type, closed-member, and duplicate raw-key decoding errors
are `shape_invalid`, except that a successfully decoded complete object whose
member/list order or canonical re-encoding differs is
`order_or_uniqueness_invalid`. Adjacent-stage compound-invalid fixtures must
prove the first-code precedence and no rejected value may appear in an error,
exception, or log.

The Audit canonical JSON encoder accepts only JSON objects, lists, strings,
JSON-safe integers, booleans, and null. Floats, non-finite numbers, integers
outside `[-9007199254740991,9007199254740991]`, invalid Unicode, duplicate raw
object keys, and unknown properties fail. Object member names are ordered by
RFC 8785 UTF-16 code units. Stored Catalog bytes are RFC 8785 JCS followed by
exactly one LF, with no BOM, CRLF, or other whitespace. A raw Catalog decoder
must re-encode the complete object and require byte equality, so duplicate
keys or non-canonical input cannot survive `json_decode` key collapse.

JCS emits literal UTF-8 for `U+2028` and `U+2029`, leaves `/` unescaped, and
uses the RFC 8785 escape form for quote and backslash. Raw `\/`, `\u2028`, and
`\u2029` spellings therefore fail canonical byte equality even when their
decoded values are equivalent. PHP and an independent Node oracle must prove
these bytes directly; the ASCII-only Framework Catalog golden is not a
sufficient escape oracle.

The raw complete Catalog input cap is 1,048,658 bytes inclusive: unsigned JCS
at most 1,048,576 bytes, the exact 81-byte `catalogHash` member addition, and
one terminal LF. Complete stored JCS is at most 1,048,657 bytes. The raw cap is
the pre-stage guard above; the unsigned JCS cap is checked again at semantic
stage 9 after closed decoding and before hash acceptance. A 1,048,659-byte
input always returns `jcs_bound_invalid` with zero decoder calls, while an
at-cap malformed input reaches stage 1 and returns `shape_invalid`.

P23-003A1 directly requires `symfony/polyfill-intl-normalizer:^1.38` rather
than relying on its transitive presence. The supported image does not require
`ext-intl` to be installed.

### 3. Typed hash encoding and Catalog wire

The encoding is byte-exact:

```text
ENC_STRING(x) = UTF8("s:" || decimal_utf8_byte_length(x) || ":") || UTF8(x)
ENC_BYTES(x)  = UTF8("b:" || decimal_byte_length(x) || ":") || x
```

`AuditEventCatalog v1` has exactly these stored members:

```text
schemaVersion, kind, catalogId, catalogVersion, owner, events, catalogHash
```

`schemaVersion=1`, `kind="audit_event_catalog"`, and
`owner=framework|application`. `catalogId` is an NFC/control-free lower
identifier of 1..128 UTF-8 bytes matching
`^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$`. `catalogVersion` is a positive
JSON-safe integer.

`catalogId` names a lineage. `(owner,catalogId,catalogVersion)` is its unique
version identity. The same identity and same complete bytes is idempotent;
the same identity with different bytes is a conflict. Multiple positive
versions may coexist. This slice does not claim a runtime monotonic activation
policy.

The unsigned body is the complete stored object without `catalogHash`.
`catalogCanonicalBytes` is its JCS with no LF. The only hash formula is:

```text
catalogHash = HEX_LOWER(SHA-256(
  ENC_STRING("blackops.audit.catalog.v1") ||
  ENC_BYTES(catalogCanonicalBytes)
))
```

The full stored Catalog is JCS of the complete object including the lowercase
64-hex `catalogHash`, followed by one LF. The unsigned JCS cap is 1,048,576
bytes inclusive. The LF and stored `catalogHash` are not part of the semantic
hash preimage. String encoding, raw JCS hashing, domain omission, stored-hash
inclusion, and raw-file SHA-256 substitution are invalid.

P23-003A1 owns this Catalog-only JCS/hash generation and validation.
P23-003B owns AuditRecord content/record hashes; the phrase “A does not own
JCS/hash” applies only to AuditRecord logical and chain hashes.

### 4. Catalog order and event-definition validation

The `events` list is sorted by event `type` using unsigned UTF-16 code-unit
order, then numeric `version`, then numeric `evidenceVersion`. A decoded list
in another order is invalid. `actionTypes` is sorted by the same string order.
`semanticTuples` is sorted lexicographically over its seven fields in stored
field order. `evidenceFields` is sorted by normalized field-name bytes.

Every event definition has exactly:

```text
type, version, evidenceVersion, category, actionTypes, semanticTuples,
evidenceFields, maxEvidenceBytes, delivery
```

The existing inclusive bounds remain: 1..256 events; 1..32 action types;
1..32 semantic tuples; 0..32 evidence fields; 1..8192 evidence bytes; and
1..256 bytes for a declared string field. Event/action/resource identifiers
match `^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$` within 128 bytes. Outcome and
policy reason codes use the `safe_code` grammar. `event.version` selects the
action semantics; there is no `action.version` member in AuditRecord v1.

The phrase “resolves to the v1 action registry” means membership in that
event definition's closed `actionTypes` plus the complete seven-field
`semanticTuples`. It does not introduce an unstated global action registry.

### 5. Evidence presence, nullability, and size

Every evidence object contains positive integer `version`. The remaining
members follow this one matrix:

| Descriptor | Missing | Present null | Present non-null |
| --- | --- | --- | --- |
| `required=true, nullable=false` | invalid | invalid | validate declared type |
| `required=true, nullable=true` | invalid | valid | validate declared type |
| `required=false, nullable=false` | valid | invalid | validate declared type |
| `required=false, nullable=true` | valid | valid | validate declared type |

`nullable` applies to string, integer, and boolean descriptors. A null value
does not run a string byte-length check. Evidence contains exactly `version`,
every required field, and any present optional field; unknown fields fail.
Nested objects, arrays, lists, floats, and non-finite values fail.
`maxEvidenceBytes` measures UTF-8 bytes of RFC 8785 JCS of the actual evidence
object, including `version`.

`safe_code` is lowercase ASCII matching
`^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$`. `opaque_id` is exactly
`oid:v1:<64 lowercase hex>` and its descriptor declares exactly 71 bytes.
The verifier proves grammar and descriptor membership only; it does not prove
the origin of the identifier or semantic absence of a secret.

### 6. Typed opaque-ID derivation

The v1 derivation uses HMAC-SHA-256 and a dedicated 32-byte key with role
`audit_opaque_id_v1`. That key must not be reused for BOPD encryption,
checkpoint/export signatures, cursor authentication, or idempotency.

`LP(x)` is unsigned U32BE byte length followed by exact bytes. The formula is:

```text
opaqueId = "oid:v1:" || HEX_LOWER(HMAC-SHA-256(
  key,
  LP("blackops.audit.opaque-id.v1") || LP(purpose) || LP(contextBytes)
))
```

Identity purposes are exactly
`record|action|principal|execution|resource|tenant|operation|correlation|
causation|trace|span|evidence`.

For a scalar identity, `contextBytes` is the already validated NFC UTF-8 raw
identifier. Tenant context is JCS of exact `{type,id}`. Evidence context is
JCS of exact
`{catalogHash,eventType,eventVersion,evidenceVersion,fieldName,value}`.
This prevents the same raw value from being substituted across purposes,
catalog selectors, or evidence fields.

The raw value and key are never serialized, logged, included in exceptions,
or retained by the pure result value. The pure derivation method marks the key
as sensitive input. Application semantic review still owns the decision that
an input is an identifier rather than credential/token material; grammar
cannot make that decision automatically.

The A1 golden uses key bytes `00..1f`, purpose `resource`, and context
`operation-123`. The preimage is 60 bytes and the result is:

```text
oid:v1:5272d9f8fc05768f7e4bd0cc0ef86729e2855fafaa00c2d5af6626e417b57f5b
```

Wrong key, algorithm, purpose, context, field, selector, normalization, and
raw-input serialization are negative fixtures. Key injection, persistence,
retention, and a future rotation protocol are not activated in A1/A2. V1 does
not permit silent key rotation while cross-record projection correlation is
required.

P23-003C owns the dedicated downstream key-provider boundary for role
`audit_opaque_id_v1`, including key-reference configuration, exact 32-byte key
availability validation, injection into consumers, and fail-closed
unavailable/malformed handling before P23-003D/E can derive identifiers. It
must not adapt or reuse `StorageKeyProvider`, BOPD material, signing keys,
cursor keys, or idempotency keys. V1 has no silent rotation; a future rotation
protocol requires a new reviewed version and explicit correlation migration.

### 7. Sealed Framework authority and reserved namespaces

Framework definitions come only from a sealed internal provider. Trusted
registration provenance, not the self-declared stored field, determines the
expected owner. An Application catalog is forced to `owner=application` by
the later P23-003E registry and cannot claim Framework provenance.

The exact reserved Framework event prefixes are:

```text
security. access. retention. storage. observer. outbox. audit.
configuration. control. replay. rotation.
```

An Application definition using any reserved prefix fails. A Framework
catalog whose stored owner is not `framework`, an Application catalog whose
stored owner is not `application`, or a provider/provenance substitution
fails. Catalog artifacts are not independently signed; runtime registration
uses trusted provider/build provenance, while P23-003F/G later authenticate
export catalog files through the signed manifest and separately verify raw
digest and semantic `catalogHash`.

### 8. Exact minimum Framework security/access catalog

The A1 catalog has:

```text
catalogId       = "blackops.framework.security_access"
catalogVersion  = 1
owner           = "framework"
events          = 30 definitions
```

Every definition has `actionTypes=[event.type]`, one semantic tuple whose
`actionType=event.type`, `evidenceFields=[]`, and `maxEvidenceBytes=13`.
Therefore its only valid evidence object is `{"version":N}`, where `N` is
the definition's `evidenceVersion`.

| Family | version / evidenceVersion | category | phase | resourceType | outcomeStatus / outcomeCode | policyDecision / reasonCode | delivery |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `security.authentication.succeeded` | `1 / 1` | security | succeeded | authentication | succeeded / authenticated | allow / authenticated | durable |
| `security.authentication.invalid` | `1 / 1` | security | denied | authentication | denied / authentication_invalid | deny / authentication_invalid | durable |
| `security.authorization.allow` | `1 / 1` | security | succeeded | operation | succeeded / authorization_allowed | allow / authorization_allowed | durable |
| `security.authorization.allow` | `2 / 2` | security | succeeded | operation | succeeded / authorization_allowed | allow / authorization_allowed | required |
| `security.authorization.deny` | `1 / 1` | security | denied | operation | denied / authorization_denied | deny / authorization_denied | durable |
| `security.authorization.deny` | `2 / 2` | security | denied | operation | denied / authorization_denied | deny / authorization_denied | required |
| `access.operation_{status,journal,outcome,diagnostics}.requested` | `1 / 1` | access | requested | operation | unknown / read_requested | not_applicable / read_requested | required |
| `access.operation_{status,journal,outcome,diagnostics}.unavailable` | `1 / 1` | access | unknown | operation | unknown / resource_unavailable | not_applicable / resource_unavailable | required |
| `access.operation_{status,journal,outcome,diagnostics}.accessed` | `1 / 1` | access | succeeded | operation | succeeded / read_accessed | allow / authorization_allowed | required |
| `audit.access.{query,export,sign,verify}.requested` | `1 / 1` | access | requested | audit_trail | unknown / audit_access_requested | not_applicable / audit_access_requested | durable |
| `audit.access.{query,export,sign,verify}.unavailable` | `1 / 1` | access | unknown | audit_trail | unknown / resource_unavailable | not_applicable / resource_unavailable | durable |
| `audit.access.{query,export,sign,verify}.accessed` | `1 / 1` | access | succeeded | audit_trail | succeeded / audit_accessed | allow / authorization_allowed | required |

Brace families expand in the listed order and the final events array is then
sorted by the rule in Section 4. The authoritative independent oracle yields:

```text
event count                   30
unsigned catalog JCS bytes    13878
hash preimage bytes           13916
stored complete JCS bytes     13959
stored bytes including LF     13960
catalogHash                   cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83
```

Any change to metadata, order, tuple, evidence rule, delivery, JCS bytes, or
domain changes the hash and requires a new reviewed catalog version/golden.
This is the minimum security/access catalog, not a claim that all later
retention, rotation, replay, outbox, checkpoint, or anchor definitions are
already registered. Their owning downstream slice must add a separately
reviewed Framework catalog version or lineage through the A1 contract.

### 9. AuditReadRequestBasis, AuditReadContext, and read event cardinality

`AuditTypedReference v1` is the exact closed object `{type,id}`. Both members
are strings of 1..256 UTF-8 bytes inclusive, must already be valid UTF-8 and
NFC, and must contain no `Cc` or `Cf` code point. Empty strings and 257-byte
values fail; the validator does not trim, case-fold, or normalize either
member. A1 adds no UUID, path, or other implicit identifier grammar beyond
these rules. The same value contract applies wherever A1 accepts actor,
execution, tenant, or tenant opaque-context typed references. Actor and tenant
may be null only where their containing wire explicitly permits null;
execution remains required.

The shared preservation canary is
`{type:" Type/V1 ",id:"ID:AbC/01"}`. It is valid and its bytes are retained
exactly. The original, trimmed, and case-folded variants are three distinct
typed references and produce distinct requestRef／tenant opaque-ID inputs. A1
tests the full member matrix once in the shared value type, one preservation
canary in each actor／execution／tenant consumer slot, and the complete tenant
opaque-context closed-shape/JCS/HMAC path. Unknown, missing, or non-string
tenant-context members fail rather than being ignored.

`AuditReadRequestBasis v1` is the exact six-field pre-decision object:

```text
purpose, actor, execution, tenant, requestedAt, nonce
```

It contains neither `requestRef` nor an authorization result. A1 derives the
requestRef only from RFC 8785 JCS of this basis:

```text
requestRef = "oid:v1:" || HEX_LOWER(SHA-256(
  LP("blackops.audit.read.request-ref.v1") || LP(JCS(requestBasis))
))
```

The existing null vector is a basis golden: JCS 150 bytes, LP preimage 192
bytes, and
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
It uses `nonce="nonce-1"` as a pure codec vector. This requestRef uses unkeyed
SHA-256 and is distinct from the keyed opaque-ID derivation even though both
use the `oid:v1:` wrapper.

Only after a trusted decision may the adapter construct the exact ten-field
`AuditReadContext v1`:

```text
schemaVersion, kind, purpose, actor, execution, tenant,
requestedAt, nonce, requestRef, authorizationDecision
```

`schemaVersion=1`, `kind="audit_read_context"`, and the remaining basis fields
and requestRef must equal the validated basis and derived value byte-for-byte.
`authorizationDecision=allow|deny|resource_unavailable`. Caller input cannot
supply the trusted decision or requestRef.

P23-003D owns an injected CSPRNG nonce source for Operation reads and P23-003G
owns one for service access. Each runtime request captures exactly 16 random
bytes once and lower-hex encodes them to 32 ASCII characters; external callers
do not supply a nonce. A1 remains a pure deterministic codec: the same basis
produces the same requestRef and a changed nonce produces a different one.
RequestRef is correlation only, not authorization, authentication, replay
protection, or idempotency. D/G must not reuse one basis for another request.
The requested and one terminal event for the same request deliberately share
one basis/reference; duplicate references never grant access or collapse
records.

For every protected Operation Status, Journal, Outcome, or Diagnostics read,
the future adapter emits exactly two events with the same `action.id` equal to
the validated requestRef:

1. `.requested` is built from the validated basis and derived requestRef and
   must be committed through its required path before authorization or target
   lookup;
2. exactly one `.accessed` or `.unavailable` must be committed before a
   response, denial, or failure is released; that terminal event uses the full
   post-decision context.

Same-tenant authorized results use `.accessed`. Policy deny, absent target,
and cross-tenant target are indistinguishable `.unavailable` results. The
resource ID is a keyed `opaque_id` with purpose `resource`; no raw target or
existence detail is recorded. Requested or terminal append failure stops the
read at the delivery boundary owned by P23-003C/D.

The service-managed `audit.access` family also uses requested-then-terminal
cardinality. Its recursion-depth-one and framework signing exemption remain
as defined in Specification 105/106. A1 fixes catalog tuples only; adapters
and transaction behavior remain downstream.

Anonymous credential absence alone emits no authentication event.
Authentication success/invalid is emitted only from an explicit authenticator
result. The current authenticator has no provider module/method identifier, so
A1 does not invent one.

### 10. AuditRecord construction stages

P23-003A2 defines, but does not activate, these stages:

1. `AuditRecordDraft v1` is the exact closed object
   `{schemaVersion,kind,event,action,actor,resource,outcome,policy,tenant,
   occurredAt,source,correlation,evidence}`. It has no `recordId`,
   `recordedAt`, `time`, or `integrity` member.
2. `AuditLogicalRecord v1` is the canonical closed 14-field body
   `{schemaVersion,kind,recordId,event,action,actor,resource,outcome,policy,
   tenant,time,source,correlation,evidence}`. Its `time` is exactly
   `{occurredAt,recordedAt}`. Only the trusted B/C finalization boundary may
   create it from a valid draft by assigning record ID and recorded time.
3. A complete `AuditRecord v1` is the logical body plus `integrity`. P23-003B
   owns integrity construction and complete-record creation.

An Application or Framework source mapper can create only a draft. It cannot
provide producer `recordedAt`, `recordId`, or `integrity`. A2 may define
internal value/validation types for all stages, but it does not expose an
untrusted public constructor for the trusted stages and does not calculate
record/chain hashes.

A2 represents the trusted stage with an Internal read-only
`AuditLogicalRecord` interface. It defines no Production concrete
implementation, factory, assembler, clock, or record-ID generator. P23-003B/C
later own the trusted concrete implementation and finalization path. A2 tests
use a test-local implementation only; implementing or validating the interface
does not grant append, integrity, or construction authority.

The interface has exactly one `toArray(): array` method. The A2 validator has
exact `validateDraft(AuditRecordDraft): void` and
`validateLogical(AuditLogicalRecord): ValidatedAuditLogicalRecord` entrypoints.
Logical validation invokes `toArray()` exactly once, rebuilds a detached closed
scalar/array snapshot without retaining the source object, source array, or
nested references, and binds that snapshot to the exact readonly event
definition resolved in the same lookup operation. Before any generic recursion
or input-proportional copy, it checks the exact14 top-level key set and the
fixed-depth known nested shape. Unknown／recursive subtrees fail closed; the
validator then rebuilds only known fields and does not use a generic recursive
detach over untrusted input. It does not re-read the interface or lookup after
validation.

Evidence member validity depends on the selected event definition. A2 therefore
uses one lookup resolution for a staged precedence adapter and the final result.
After basic record shape is known, it resolves a candidate without reporting a
missing selector before the semantic stage. For a resolved definition, unknown
evidence members and declared types participate in stage 1; evidence strings
participate with record strings in UTF-8, control, and NFC stages 2..4;
descriptor byte caps participate in stage 5; and `safe_code|opaque_id` grammar
participates in stage 6. Record selector／tuple／owner／delivery semantics remain
stage 8. The Accepted-A1 `AuditEventCatalogCodec::validateEvidence()` is then
called exactly once on the same definition as the authoritative evidence
semantic and complete-JCS-cap check, so stage 9 never precedes a record stage-8
failure. The adapter does not create Catalog/JCS/hash bytes and does not modify
the Accepted A1 Product.

`ValidatedAuditLogicalRecord` is a final readonly Internal result. It does not
implement `AuditLogicalRecord`, is not a Production concrete logical record or
authority token, and contains only the detached exact 14-field snapshot and its
bound event definition. Its only constructor/public members are
`__construct(public readonly array $snapshot, public readonly AuditEventDefinition $definition)`;
it has no other public method. PHP namespace visibility is not treated as a
security boundary: even if a caller constructs this result, the Projector has
no API that accepts it. The Projector never accepts this result from a caller.

Draft correlation preserves omitted versus explicit-null members. Logical
validation accepts both forms allowed by the canonical schema. It validates
both timestamps and the inclusive
`occurredAt <= recordedAt + 300 seconds` rule but never creates, normalizes,
or clamps either timestamp. `AuditRecordCorrelation` has a private constructor
and sole public `fromArray(array): self` factory: `[]` means all members omitted,
while a present key with `null` means explicit null. No public `false` or
empty-string omission sentinel exists, and contract-external values fail closed.
A2 creates no complete-record or integrity type.

### 11. Exact observed safe projection

The Projector has exactly one output entrypoint,
`project(AuditLogicalRecord): string`. It calls the injected validator's
`validateLogical()` exactly once and uses only that returned detached snapshot
and bound definition for opaque derivation, the local projection object, JCS,
and the byte guard. It does not re-read the input or lookup, does not accept a
caller-supplied `ValidatedAuditLogicalRecord`, and exposes no separate
projection-object return method. The returned string is the final JCS plus LF
wire only.

The A2 projection is a closed, lossy, non-authoritative view. Its exact
top-level members are:

```text
schemaVersion, kind, projection, recordRef, event, action, actor, resource,
outcome, policy, tenantRef, time, source, correlation, evidence
```

Constants are `schemaVersion=1`, `kind="audit"`, and
`projection="observed_v1"`. Nested objects are exactly:

```text
event       = {type,version,category,catalogHash}
action      = {ref,type,phase}
actor       = {principalRef,executionRef}
resource    = {type,ref}
outcome     = {status,code}
policy      = {decision,reasonCode,auditDelivery}
time        = {occurredAt,recordedAt}
source      = {owner,runtime}
correlation = {operationRef,correlationRef,causationRef,attempt,traceRef,
               spanRef,sampled}
```

`recordRef`, `action.ref`, `actor.*Ref`, `resource.ref`, `tenantRef`, and all
correlation `*Ref` members are their purpose-bound keyed opaque IDs or null as
allowed by the canonical field. `executionRef` and resource/action/record refs
are required. `attempt` is null or exact `{number,kind}`. `sampled` is Boolean
or null. Evidence copies only the already catalog-validated `version` plus
registered `safe_code|opaque_id` values.

The projection always emits all seven correlation members. A canonical
omission and an explicit null both project to null. Evidence always emits
`version`; it copies only present, non-null string members whose registered
classification is `safe_code|opaque_id`. Missing members, present nulls, and
integer/boolean evidence are omitted from the projection. This is an
intentional lossy mapping, not a change to the canonical evidence object.

The projection omits canonical raw IDs and types not fixed by the catalog,
policy ID/version, source component/build ID, and every integrity/chain field.
It never contains credentials, tokens, request/payload bodies, policy payload,
throwables, ciphertext, nonce, tag, key material, or arbitrary Application
objects. Unknown input fails projection before bytes are returned.

The wire is RFC 8785 JCS plus one LF and is at most 65,536 bytes inclusive.
Projection failure returns no partial object or bytes and a fixed safe failure
category without embedding source data. A later observed JSONL sink may treat
delivery as best effort, but projection failure cannot roll back, replace, or
prove a canonical append. The projection is not an Audit Trail completeness,
integrity, compliance, or non-repudiation certificate.

The one failure category is exactly `projection_invalid`. Every input,
Catalog, semantic, evidence, opaque-derivation, JCS, and byte-cap failure maps
to that value with no previous exception or rejected source value. The
projector consumes an injected Accepted-A1 `HmacAuditOpaqueIdDeriver`; it does
not accept a raw key and adds no default key, environment/configuration read,
provider, key reference, persistence, or rotation. Tests may construct the
deriver directly with fixed key bytes. P23-003C retains Production key-provider
and injection ownership.

A2 adds no `#[PublicApi]` marker. P23-003E remains the only owner of a later
Application authoring API and matching public Documentation.

P23-003A2 must add one complete exact-byte golden and per-source-field canaries.
The earlier masked JSON remains explanatory only and does not override this
wire.

## Consequences

- Stable `1.2.0` Public API and public Documentation remain unchanged.
- P23-003A1 adds no `#[PublicApi]` marker in `Core\\Audit` or
  `Internal\\Audit`. Its exact marker allowlist is empty. P23-003E owns any
  later Application authoring API and the matching public inventory/docs.
- A1 can be reviewed independently because it has one exact Framework Catalog
  and two independent cryptographic goldens.
- A2 cannot change A1 Catalog bytes/hash. A change restarts A1 review.
- No Application authoring, runtime adapter, storage, query, export, Website,
  deployment, tag, or release capability is implied by these types.
- `best_effort` remains observed projection delivery only and is never a
  canonical AuditRecord delivery mode.

## Rejected alternatives

- Letting the worker choose string-versus-bytes Catalog hashing was rejected
  because it creates incompatible identities.
- Reusing the existing ProjectionBuildSet codec was rejected because its
  internal scope and comparator/number/normalization contract are not the
  Audit RFC 8785 contract.
- Copying the canonical AuditRecord and replacing selected values with the
  literal `[masked]` was rejected because the field-level leak boundary is not
  closed.
- Unkeyed hashing of raw identifiers was rejected because low-entropy IDs are
  dictionary-testable and substitute across fields without a purpose domain.
- Implementing A1 and A2 in parallel was rejected because A2 validation and
  projection depend on the exact Accepted A1 catalog/schema/hash contract.

## Historical P23-003A Remaining

The initial P23-003A1 preflight is Historical Changes Requested. The broad Sol
review returned `P1=1/P2=3/P3=1`; the focused packaging/architecture review
returned `P1=2/P2=2/P3=0`. The counts are separate and are not aggregated.
The first corrected exact-nine re-preflight is also Historical Changes
Requested: the general lane returned `P1=1/P2=1/P3=0` and the focused lane
returned `P1=1/P2=0/P3=0`. Both identified the raw-cap precedence root; the
general lane additionally identified the typed-reference member boundary.
Those roots are corrected above without Production changes.
The second corrected exact-nine review is Historical: the focused lane was
Green `P1=0/P2=0/P3=0`, while the separate general lane returned
`P1=0/P2=1/P3=0` because the Task did not directly fixture byte preservation
and the tenant opaque-context consumer path. The normative contract was
already closed; the Task fixture matrix now closes that regression-evidence
gap.
Fresh general closure review of the third corrected exact-nine is Green
`P1=0/P2=0/P3=0`. Luna Max then completed the exact A1 implementation and the
focused／complete Final Gates. The fixed Product48 aggregate is
`9e9ba592e5203c17d814c3f0cc8772b2fc95e7ddc75b67df6a5c16c9a7030fed`.
Fresh Sol Max implementation review is Green `P1=0/P2=0/P3=0`, and the
Orchestrator accepts P23-003A1. This does not activate runtime behavior or
release BlackOps `1.3`.

The initial P23-003A2 preflight returned Changes Requested
`P1=1/P2=0/P3=0` for an undefined Validator-to-Projector snapshot boundary.
The Task and this decision now fix the exactly-once detached snapshot, bound
definition, non-injectable Internal result, and bytes-only Projector entrypoint.
The corrected P23-003A2 Task Packet fixes the remaining implementation
choices for the single projection failure, correlation/evidence loss,
logical-record input seam, opaque deriver injection, and empty A2 PublicApi
inventory. Fresh Sol Max re-preflight is Green `P1=0/P2=0/P3=0`. Luna Max
implementation, the focused Iteration Gate, exact29 Product freeze, and
complete Final Gate are Green. Fresh Sol Max implementation review is
requested.
Fresh Sol Max implementation review returned Changes Requested
`P1=1/P2=2/P3=0`. A2 must integrate definition-dependent evidence stages 1..6
before record semantic stage 8, leave stage 9 after every semantic check, connect
the PHP and Node exact-byte golden to one fixture, and complete the required
mutation matrix. Accepted A1 exact48 remains unchanged.
Luna High corrected those roots and the corrected complete Final Gate was Green.
Fresh implementation re-review then returned Changes Requested
`P1=3/P2=1/P3=0`: Correlation construction must not expose false／empty-string
omission sentinels; Logical capture must reject unknown／recursive input before
bounded field copy; remaining direct mutation fixtures must close the exact Task
matrix; and this Current handoff must supersede the stale prior Next Action.
Accepted A1 exact48 remains unchanged.

Round 2 closes those roots. Correlation construction is private and accepts
only the closed `fromArray()` input; Logical capture rejects unknown shape before
fixed-depth field snapshot; the direct mutation matrix covers cap crossing,
all nullability quadrants, recursive/deep unknown input, real leak canaries,
and existing registration surfaces. The exact29 Product aggregate is
`c2ef33a3d3b47ca9a5de189d324308a79da0a356839d36aa944675a7dba2d8eb`,
and its complete Final Gate is Green.

Fresh Sol Max re-review returned Changes Requested `P1=0/P2=2/P3=0`. Product
source is correct. Round 3 adds the explicit-null correlation projection direct
fixture and synchronizes the current inventory. Round 3 closes both roots. Its
exact29 Product aggregate is
`65443eb6ec6c738c61100001d8f3f5d2e38a88e63f585052bf6f4c4b9bd29056`;
focused PHPUnit `46/23599` and the complete Final Gate are Green.
Fresh Sol Max implementation re-review returned Changes Requested
`P1=1/P2=0/P3=0`: wide keysets can reach input-count-proportional
`array_keys()` or evidence snapshot allocation before bounded shape rejection.
Round 4 is limited to the Validator and its two direct test files.

Round 4 closes that root. The exact3 correction applies O(1) member caps before
key extraction／snapshot allocation and copies only Catalog-declared evidence
fields. Focused PHPUnit is `48/23683`, the exact29 Product aggregate is
`c019ab09c31a99301bfe9fd22004890cf52a21b20cc7b410df74f08b0ef97ae9`, and
Accepted A1 exact48 is unchanged. The complete Final Gate is Green: full
PHPUnit `2700/37333`, Mago error0, Deptrac `970/970`, release／Website／Node／
management guards, staged0, and ending hashes all PASS. Fresh review is Green
`P1=0/P2=0/P3=0`; the ending exact38／staged0／all aggregates match. The
Orchestrator accepts P23-003A2 and parent P23-003A without runtime activation.

1. preserve Accepted A1/A2 freezes and review evidence;
2. draft and preflight P23-003B as a separate bounded Task;
3. keep runtime activation with downstream owners; and
4. continue the remaining Phase 23 DAG only through its ordered gates.

## Historical P23-003A Next Action

Orchestrator begins bounded P23-003B research and Task Packet drafting from the
Accepted A1/A2 freeze. Do not commit, stage, or activate runtime behavior in this
Acceptance sync; P23-003B Production waits for separate preflight Green.

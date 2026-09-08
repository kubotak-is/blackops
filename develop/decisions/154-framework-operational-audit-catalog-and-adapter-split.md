# D154: Framework Operational Audit Catalog and Adapter Split

Status: Parent D incomplete — D1/D2/D2S1 Accepted; adapters remain incomplete; BlackOps 1.3 unreleased

Updated At: 2026-09-08T11:20:30+09:00

## Current Handoff

D1/D2 and the D2S1 source prerequisite are Accepted. The D2S adapter still
requires a revised, independently reviewed packet before implementation.
Current delivery evidence and next actions belong to [STATE](../STATE.md) and
[D2S1 Report](../orchestration/reports/P23-003D2S1-tenant-scoped-status-source.md).
Parent D, runtime activation and release remain incomplete.

## Historical Handoff — before D2 implementation

The following checkpoint predates D2 Acceptance; it is not the current next action.

P23-003C1 through P23-003C4 and parent P23-003C are Accepted. P23-003D1 is
Accepted at commit `35afacd9741272d4e1207d3a76829d85653e4f6f`; its final
tree is clean and staged0, and the Git／Composer package export is Green from
that same SHA. No push was performed.

P23-003D is not one implementation packet. Ordinary D work may start after
Accepted C, while the checkpoint／anchor control adapter remains blocked on
Accepted P23-003F. D1 completed the first bounded ordinary child by adding one
sealed Framework operational Catalog and its trusted lookup lineage without a
producer or runtime activation. D155 now defines the D2 Operation-read
request／session foundation. Its Product work remains stopped until the
authority packet is reviewed, committed, cleanly re-frozen, and freshly
preflighted.

Stable `1.2.0` remains current. BlackOps `1.3`, the operational Catalog, and
all Audit Trail runtime behavior remain unreleased.

## Context

D147 deliberately fixes `blackops.framework.security_access` version 1 as an
exact 30-definition minimum Catalog. Retention, rotation, replay, Outbox,
checkpoint, and anchor definitions were left to a separately reviewed
Framework Catalog lineage. Mutating the Accepted exact30 bytes would change
its hash and invalidate every Accepted record and fixture that names it.

The current `ImmutableAuditEventCatalogLookup` also accepts only the exact
`FrameworkAuditEventCatalog::catalog()` object for owner `framework`. A second
Framework Catalog therefore cannot be registered by merely constructing valid
Catalog bytes. The trusted provider identity and the new bytes must be added
together.

The remaining ordinary D work crosses different public, transaction, and
failure boundaries. Authentication releases an HTTP response, authorization
currently collapses no-policy and allow, Operation readers look up targets
before the future Audit request, and retention／rotation／replay／generic Outbox
have different transaction shapes. Combining those changes would let one
failure invalidate unrelated evidence and would require a worker to invent
policy, idempotency, and failure behavior.

## Decision

### 1. P23-003D delivery graph

P23-003D is split into these bounded ownership nodes.

1. `P23-003D1`: Framework operational Catalog and trusted lookup lineage.
2. `P23-003D2`: Operation-read request/session foundation, followed by
   separately bounded Status, Journal／Outcome, and Diagnostics integrations.
3. `P23-003D3`: Authentication result adapter and HTTP response boundary.
4. `P23-003D4`: typed Authorization evaluation, delivery selection, and
   same-root integration for privileged mutation.
5. `P23-003D5`: existing operational phase adapters, split at least into
   retention／hold, storage rotation, observer replay, and Stable generic
   Outbox retry／dead-letter children.
6. `P23-003D-integration`: ordinary Framework composition and explicit
   activation／nonactivation closure after D1 through D5 are Accepted.
7. `P23-003D-control`: checkpoint／anchor control events. This node may start
   only after P23-003F is Accepted and consumes only F's typed outcomes.

The dependency graph is:

```text
Accepted C -> D1 -> D5 source children -> D-integration
           -> D2 foundation -> D2 reader children -> D-integration
           -> D3 ---------------------------------> D-integration
           -> D4 ---------------------------------> D-integration

Accepted C -> F -> D-control -> G
```

After Accepted C, ordinary D and F may proceed independently. D-control must
not consume an unaccepted F shape. G must not bypass D-control where the
checkpoint／anchor event is applicable. Parent P23-003D is not Accepted until
all ordinary nodes and D-control are Accepted. A child requiring an unlisted
sibling must stop for a DAG correction rather than create an implicit edge.

### 2. Accepted Catalog preservation

The following Accepted identity and bytes remain exact:

```text
catalogId=blackops.framework.security_access
catalogVersion=1
owner=framework
eventCount=30
catalogHash=cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83
unsignedJcsBytes=13878
hashPreimageBytes=13916
completeJcsBytes=13959
rawBytesWithLf=13960
```

`FrameworkAuditEventCatalog.php` and its fixture are immutable in D1.
No event is appended to, removed from, or re-versioned inside that Catalog.
Existing records continue resolving it by the same identity and hash.

### 3. New operational Catalog identity

D1 adds one final Internal provider named
`BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog`.
It has a private constructor and exactly one public static
`catalog(): AuditEventCatalog` method. It carries no `#[PublicApi]` marker.

Its Catalog identity is exact:

```text
schemaVersion=1
kind=audit_event_catalog
catalogId=blackops.framework.operations
catalogVersion=1
owner=framework
eventCount=14
catalogHash=9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b
unsignedJcsBytes=6448
hashPreimageBytes=6485
completeJcsBytes=6529
rawBytesWithLf=6530
```

The provider constructs the Catalog through the Accepted
`AuditEventCatalogCodec::catalog()` path. Its singleton identity is stable
within one process. It does not read configuration, clock, UUID, database,
filesystem, environment, container, network, or Application input.

### 4. Exact operational event definitions

Every definition has `version=1`, `evidenceVersion=1`,
`actionTypes=[event.type]`, exactly one semantic tuple,
`evidenceFields=[]`, `maxEvidenceBytes=13`, and `delivery=durable`.
The only valid evidence value is `{"version":1}`.

The Catalog's canonical UTF-16 event order and tuples are:

| event.type | category | phase | resourceType | outcomeStatus / outcomeCode | policyDecision / reasonCode |
| --- | --- | --- | --- | --- | --- |
| `outbox.delivery.dead_letter_retry_requested` | outbox | requested | outbox_record | unknown / outbox_retry_requested | not_applicable / outbox_retry_requested |
| `outbox.delivery.dead_lettered` | outbox | failed | outbox_record | failed / outbox_dead_lettered | not_applicable / outbox_dead_lettered |
| `outbox.delivery.retry_scheduled` | outbox | committed | outbox_record | unknown / outbox_retry_scheduled | not_applicable / outbox_retry_scheduled |
| `replay.observer.failed` | replay | failed | lifecycle_journal | failed / replay_failed | not_applicable / replay_failed |
| `replay.observer.progressed` | replay | committed | lifecycle_journal | unknown / replay_progressed | not_applicable / replay_progressed |
| `replay.observer.started` | replay | started | lifecycle_journal | unknown / replay_started | not_applicable / replay_started |
| `replay.observer.succeeded` | replay | succeeded | lifecycle_journal | succeeded / replay_succeeded | not_applicable / replay_succeeded |
| `retention.hold.placed` | retention | committed | operation | succeeded / retention_hold_placed | not_applicable / retention_hold_placed |
| `retention.hold.released` | retention | committed | operation | succeeded / retention_hold_released | not_applicable / retention_hold_released |
| `retention.purge.committed` | retention | committed | operation_data | succeeded / retention_purged | not_applicable / retention_purged |
| `rotation.storage.failed` | rotation | failed | protected_storage | failed / rotation_failed | not_applicable / rotation_failed |
| `rotation.storage.progressed` | rotation | committed | protected_storage | unknown / rotation_progressed | not_applicable / rotation_progressed |
| `rotation.storage.started` | rotation | started | protected_storage | unknown / rotation_started | not_applicable / rotation_started |
| `rotation.storage.succeeded` | rotation | succeeded | protected_storage | succeeded / rotation_succeeded | not_applicable / rotation_succeeded |

These definitions describe only Framework-owned state changes already named by
the ordinary D scope. They do not claim application business actions,
authentication, authorization, protected reads, Audit Outbox relay, checkpoint,
anchor, physical deletion, or compliance.

The `progressed` definitions mean that a bounded persisted cursor/count
checkpoint advanced. They do not claim that the complete rotation or replay
succeeded. `outbox.delivery.dead_letter_retry_requested` is the existing
operator-request boundary; automatic retry and dead-letter state changes use
the other two definitions.

### 5. Trusted Framework Catalog lookup

`ImmutableAuditEventCatalogLookup` accepts owner `framework` only when the
object is identical to one of these two providers:

```text
FrameworkAuditEventCatalog::catalog()
FrameworkOperationalAuditEventCatalog::catalog()
```

Both may be present in one lookup and resolve independently by full identity
and by hash. A decoded clone, reflected reconstruction, changed identity,
changed hash, changed event, or third Framework Catalog fails
`semantic_invalid`, even when its serialized bytes happen to match.
Application Catalog trust, reserved-prefix rejection, duplicate identity,
same-hash/different-bytes behavior, and validation precedence remain exactly
as Accepted.

D-control must add its own separately reviewed provider or Catalog version;
D1 does not pre-authorize checkpoint or anchor definitions.

### 6. D1 nonactivation boundary

D1 changes only the new Catalog provider, the existing immutable lookup,
their focused／Architecture evidence, and package inventory. It adds no draft
factory, delivery call, Store call, configuration key, migration, Application
runtime member, Container registration, command, route, middleware, reader,
worker, scheduler, or public Documentation.

Production references to the new provider are exact one: the trusted identity
check in `ImmutableAuditEventCatalogLookup`. There is no Production call that
constructs a lookup containing the new Catalog. Package required inventory
increases from exact75 to exact76 solely for the new Internal provider.

### 7. Downstream source-adapter rules

D1 does not authorize a source adapter. Each D2 through D5 child requires a
new most-specific Decision and exact Task before Product work. That Decision
must fix, for every emitted event:

- the trusted actor, execution, tenant, source runtime/component/build, raw
  source identifier, and the source-specific resource/action projection,
  including whether it is keyed or uses an Accepted derived reference;
- the exact Clock-capture and delivery-plan selection owners, plus the exact
  owner or explicit absence of UUID allocation and idempotency-key generation;
- whether a source mutation exists and, when applicable, whether that mutation
  and a durable Outbox append share the exact same Framework level-1 root;
- crash-before/after-append, duplicate retry, rollback, and append-failure
  precedence;
- value-free failure and no-secret behavior; and
- exact Production caller, Connection identity, activation default, and
  public response／exit compatibility.

D2 specializes those conditional rules for Operation reads: it uses the
Accepted unkeyed `AuditReadRequestBasis::requestRef()` for action identity, one
keyed Resource opaque ID, Store-owned record／segment UUID allocation, a null
required-append idempotency key, one factory-owned Clock capture, the Accepted
writer／selector-owned delivery plan, two separate Store-owned standalone
roots, and no durable Outbox append. Those are not exceptions inferred by an
implementation; they are fixed by D155. Later children must make their own
choices explicit instead of copying D2 or assuming that every source owns a
mutation, UUID, idempotency key, or Outbox write.

When an existing state change already owns an atomic database root, its phase
event must join that exact root. A child may not append after commit and call
that atomic. A failed event may be emitted only with a corresponding persisted
failure state; an exception whose transaction rolled back does not create a
false committed-failure AuditRecord. If the current API cannot satisfy these
rules, the child stops and explicitly expands its authority.

The Stable generic Outbox and the C4 Audit Outbox remain distinct. D5's generic
Outbox adapter must not call or relabel the C4 relay runtime.

### 8. Authentication, authorization, and read boundaries

D2 must create each Operation-read basis outside the existing reader, capture
one injected 16-byte CSPRNG nonce as 32 lowercase hexadecimal characters, and
commit `.requested` before authorization or lookup. It must define a trusted
execution source, opaque resource projection, query failure mapping, terminal
append precedence, and a Diagnostics authorization facade before modifying a
reader.

D155 is the most-specific D2 foundation authority. It fixes a dedicated nonce
port, strict execution-source port, one factory, one private-construction
one-terminal session, a typed default-unavailable Diagnostics authorization
facade, and `AuditProtectedReadGate` as the sole writer implementation. The
factory creates requested／accessed／unavailable drafts before the requested
append, materializes the raw Operation ID only for one keyed Resource
derivation, and retains no raw target. Requested and terminal are separate
Store-owned standalone transactions.

D155 supersedes only D152's bounded non-Allow terminal-failure suppression.
For every decision, terminal binding／transaction／Store failure now throws and
withholds the result; the decision is returned only after the terminal record
commits. The D2 foundation has no current reader or Application-composer
caller. D2S, D2J, and D2X later own Status, Journal／Outcome, and Diagnostics
integration and their exact trusted execution sources.

D3 must distinguish explicit authenticated success and explicit invalid
authentication from credential absence. Anonymous credential absence emits no
authentication event. Provider provenance is recorded only when supplied by a
typed provider result; credentials, tokens, request bodies, and throwable
details never enter a draft.

D4 must distinguish no-policy, allow, and deny and must carry a trusted policy
ID/version, safe reason, and fixed durable／required selection. Privileged
required delivery must join the exact Framework mutation root. Neither D3 nor
D4 may infer semantics from a handler result or expose request／operation
payloads to an Audit producer.

`ApplicationAuditRuntimeComposer` remains uncalled until D-integration fixes
the first canonical caller, provider/config availability, same-Connection
composition, and backward-compatible failure behavior. A child must not
activate a partial graph to make a focused fixture pass.

### 9. Release and Documentation boundary

Stable `1.2.0` and its public Guide／Website remain unchanged. The candidate is
BlackOps `1.3.0` unreleased. D1 adds no public API, route, CLI, configuration,
migration, example, release note, or browser behavior. The operational Catalog
is an Internal package artifact, not an advertised Stable capability.

The current Framework migration count remains `13`; Community Board remains
`5`, for total `18`. The Stable generic Outbox runtime and commands remain
byte-identical. Generated Documentation, Search, raw Markdown, LLM artifacts,
and browser behavior remain Not Verified when not generated or exercised.

## Rejected Alternatives

### Append operational definitions to the Accepted exact30 Catalog

This changes its hash and invalidates an Accepted identity. A new trusted
Catalog preserves old records and makes the new lineage explicit.

### Accept any owner=framework Catalog with a valid self-hash

A self-hash proves bytes, not Framework provenance. The lookup continues to
trust exact provider object identity.

### Wire existing runtimes while adding the Catalog

Catalog construction has no transaction or public failure boundary. Mixing it
with source adapters would make the D1 evidence depend on unresolved runtime
choices.

### Reuse the C4 Audit Outbox relay for Stable generic Outbox events

The two stores, payloads, state machines, commands, and release contracts are
different. D5 must adapt the Stable generic source explicitly.

## Consequences

- Accepted security/access Catalog bytes and records remain valid.
- D1 can be implemented and reviewed without database or runtime activation.
- Future Framework Catalog additions remain closed by exact provider identity.
- Ordinary D work is reviewable by public/transaction boundary rather than as
  one cross-cutting packet.
- P23-003D remains incomplete after D1; later children still own adapters,
  integration, and D-control after F.
- No Stable capability or compliance claim is created.

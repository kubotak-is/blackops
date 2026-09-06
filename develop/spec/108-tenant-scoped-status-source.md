# Tenant-scoped Status Source

Status: Authority reviewed Green — Product progress is recorded in the D2S1 Report/STATE; no activation.

## Scope

P23-003D2S1 is the source prerequisite for the D2S Audit adapter. This is its
most-specific contract; D157 records rationale. Specification99's tenant SQL
and before-decode boundary remains authoritative. D2 is Accepted at
`0cd699e5e1d031228bc4a05767164a8eb268a004`. No Audit event, session, Clock,
UUID, nonce, source mutation, schema change or runtime activation is added.
Stable1.2.0 and existing public API remain unchanged; this is latent1.3 work.

## Exact Source API

Add Internal interface `TenantScopedOperationStatusSource` in
`BlackOps\Internal\Status`, extending the existing `OperationStatusSource`:

```php
public function findSubjectForTenant(
    #[\SensitiveParameter] OperationId $operationId,
    #[\SensitiveParameter] ?TenantRef $tenant,
): ?OperationStatusSubject;
public function readDetailForTenant(
    #[\SensitiveParameter] OperationStatusSubject $subject,
    #[\SensitiveParameter] ?TenantRef $tenant,
): OperationStatusDetailResult;
```

There is no optional tenant argument. Null explicitly means the global scope,
never all tenants. `PostgreSqlOperationStatusSource` implements this interface
and preserves its existing constructor, `findSubject()` and `readDetail()`
signatures and behavior. The existing interface, Default Query, HTTP handler,
public Status types, registry and composers remain unchanged.

Add final readonly `TenantBoundOperationStatusSource` implementing existing
`OperationStatusSource`. Constructor accepts exactly
`TenantScopedOperationStatusSource $source, ?TenantRef $tenant`. It captures
that nullable tenant once and exposes exactly `findSubject(OperationId)` and
`readDetail(OperationStatusSubject)` as source methods, delegating once to the
corresponding scoped method with the captured tenant. No fallback, rebind,
authorization, detail interpretation, transaction, SQL or Audit dependency.
Two bound views over one source cannot alter each other's scope. Production
construction/callers of this bound view remain zero in D2S1.

The bound view stores source/tenant inside a SensitiveParameterValue; private
snapshot plus empty `__debugInfo()`/`__serialize()`, and always-failing
`__unserialize()` throwing value-free OperationStatusSourceException Storage.
Defensive snapshot access must fail closed, never invoke
an unscoped method. Neither new type receives PublicApi.

The interface declares only the two scoped methods above (plus inherited
legacy source methods). It has no constructor, constants or extra method.
The bound view has exactly one declared property:
`private \SensitiveParameterValue $snapshot`; no promoted property, static,
trait or superclass. Its exact public signatures are:

```php
public function __construct(
    #[\SensitiveParameter] TenantScopedOperationStatusSource $source,
    #[\SensitiveParameter] ?TenantRef $tenant,
);
public function findSubject(#[\SensitiveParameter] OperationId $operationId): ?OperationStatusSubject;
public function readDetail(#[\SensitiveParameter] OperationStatusSubject $subject): OperationStatusDetailResult;
/** @return array<never, never> */
public function __debugInfo(): array;
/** @return array<never, never> */
public function __serialize(): array;
/** @param array<never, never> $data */
public function __unserialize(#[\SensitiveParameter] array $data): void;
```

The constructor's shown semicolon describes its declaration, not an abstract
method. It has no return type. There are no other public/protected methods.
The only private helper is `values(): array`, with exact return DocBlock
`array{source: TenantScopedOperationStatusSource, tenant: TenantRef|null}`.
It validates the snapshot's exact two keys and value types and throws safe
Storage on malformed/uninitialized state. No declared `__clone()`; default
cloning performs no I/O and shares only the same immutable snapshot, not
mutable per-call state. Tests prove original/clone scope cannot diverge.

SensitiveParameter placement above is exact for the new interface/view.
Both parameters of the two new scoped source methods and each of the four new
reader methods are also SensitiveParameter. Existing legacy public signatures
and existing reader `findSubjectForTenant()` declarations are unchanged.
On newly introduced or scope-modified private helpers, all operation/subject/
tenant/scope parameters receive SensitiveParameter; unrelated unchanged helpers
do not need annotation-only churn.

## Scoped PostgreSQL Selectors

Add exactly these public methods to `PostgreSqlStatusReader`:

```php
public function deferredStateForTenant(OperationId $operationId, ?TenantRef $tenant): ?PostgreSqlStatusDeferredState;
public function outcomeExistsForTenant(OperationId $operationId, ?TenantRef $tenant): bool;
public function deadLetterExistsForTenant(OperationId $operationId, ?TenantRef $tenant): bool;
/** @return list<RetentionPurgeTarget> */
public function purgeTargetsForTenant(OperationId $operationId, ?TenantRef $tenant): array;
```

Reuse its existing `findSubjectForTenant()` and closed scope builder. Each new
selector qualifies Operation ID, tenant type and tenant ID in the same SQL
predicate. Both tenant members use `IS NOT DISTINCT FROM` parameter comparison;
global scope passes both parameters as null. No caller value is interpolated
into SQL. Existing table-name/schema validation remains unchanged.

Keep the existing selected columns, state parsing, safe failure mappings and
purge ordering `purged_at ASC, audit_id ASC`. Existence queries keep the same
boolean semantics. No SELECT-star, encoded payload/record selection or JSON
projection is added to these clear-metadata selectors.

Every scoped query, including nested integrity/existence selectors, includes
the same identity predicates. Never retry, count, probe or fetch by Operation
ID alone to discover a foreign row. Foreign rows are indistinguishable from
no matching scoped row. This path is not a global corruption scanner.
The existing unscoped methods remain supported for their unchanged legacy
callers; no new scoped method can reach their unqualified SQL branch.

## Scope Threading and Classification Reuse

Do not copy the lifecycle/state/result algorithm. Reuse the existing private
snapshot/classification path with an explicit per-call scope marker. The
closed marker is `array{tenant: TenantRef|null}`; null marker means legacy
unscoped only, and `['tenant' => null]` means scoped-global. Scope must never
be inferred from `$tenant !== null` or a subject's stored tenant alone.
No current-tenant mutable property, static scope or ambient scope is allowed.

Thread the marker through `readDetailSnapshot`, `deferred`, `deferredOutcome`
and every storage helper: deferredState, records, purgeTargets, outcomeExists,
deadLetterExists and outcome. The completed deferred Outcome read reached
through `deferredOutcome()` must not accidentally call unscoped `find()`.
Pure validators/classifiers remain shared. Legacy entrypoints choose only the
legacy marker; scoped entrypoints always choose the closed non-null marker.

For scoped Journal use the existing `recordsForTenant()` and consume its
generator inside the detail transaction. For scoped Outcome use existing
`findForTenant()`, retaining tenant predicates on both Outcome and joined
Operation rows. Those stores/codecs are immutable in this Task. Use the new
scoped clear-metadata selectors for all other reads. Do not decrypt foreign
data to decide whether to reject it.

## Entry and Snapshot Protocol

New scoped subject/detail entrypoints first require the source's own DBAL
Connection to have auto-commit true, active false and nesting0. Inspection
failure or any other state yields value-free Storage without beginning,
committing or rolling back a foreign transaction. Legacy entry behavior is
not strengthened by this prerequisite.

Scoped subject lookup uses the existing scoped clear-metadata reader and the
same domain conversion/error mapping as the legacy source. It performs no
authorization, protected decode or transaction creation. A foreign/absent
subject returns null. After lookup, require the same level0 state; no repair.

Scoped detail protocol:

1. Validate level0. Compare the supplied subject's tenant with the explicitly
   bound tenant by null presence, type and id strict equality. Mismatch returns
   DetailUnavailable with zero lookup/decode/transaction work.
2. Preserve the existing EphemeralOutcome short-circuit: DetailUnavailable,
   with zero protected decode. No subject existence claim is returned.
3. Open exactly one same-Connection REPEATABLE READ READ ONLY root. Verify both
   SHOW values as in the existing source. Do not join ambient work.
4. Re-read the scoped subject inside this snapshot, before any protected fetch
   or decode. Use the internal reader/conversion path, not the public level0
   scoped entrypoint. No row means DetailUnavailable after successful commit.
5. If a row remains, compare Operation ID, Operation type, origin Actor's null
   presence/type/id and Tenant null presence/type/id with the pre-authorization
   supplied subject. A same-scope identity drift yields Integrity before decode.
6. Perform the shared detail projection using only the bound scope marker.
   Commit this read-only root, verify auto-commit/inactive/nesting0, then return
   the result. There is no Audit append or result release callback here.

Track a local owned-root flag, initially false. Acquire ownership only after
beginTransaction returns and auto-commit true/active/nesting1 are confirmed.
Clear ownership immediately after commit returns, BEFORE inspecting poststate.
On failure, roll back at most once only while ownership remains true and the
Connection is still auto-commit true/active/nesting1. No rollback after a
successful commit, begin-then-throw, failed ownership inspection, nested/drifted
or otherwise ambiguous state. Rollback failure or failure to restore the entry
state maps to Storage. Never repair a pre-existing or ambiguously owned root.
Preserve existing Source Storage/Decode/Integrity classification for eligible
data; DB/driver failures are Storage, invalid same-scope identities/state are
Integrity, supported codec failures retain the existing mapping. No previous
cause, input value, SQL, key/provider text or decoded payload is exposed in the
Source exception. Commit-then-throw withholds the result and does not retry.

Snapshot consistency is relative to PostgreSQL's snapshot: changes committed
before the snapshot are observed by its first subject read; later commits do
not change that snapshot. No serializable/locking/later-commit visibility is
claimed. Scope is not re-resolved during the query.

## Absence and Corruption Matrix

| Condition | Result |
| --- | --- |
| No initial scoped subject, including other-tenant-only rows | null subject |
| Supplied subject tenant differs from bound scope | DetailUnavailable; zero I/O |
| Subject disappeared or moved fully out of scope before snapshot | DetailUnavailable; zero protected decode |
| Same-scope subject type/origin actor identity changed after a detail snapshot is opened | Integrity before protected decode |
| Same-scope malformed metadata or contradictory identity | existing safe Integrity |
| Foreign detail rows only | treated exactly as absent scoped rows; never decoded |
| Incomplete/conflicting eligible lifecycle rows | existing retention/integrity result |
| Valid same-scope tombstones and authorized detail | unchanged Expired/Detail projection |

Filtering foreign rows does not prove they are corrupt or absent globally.
The Ephemeral shortcut takes precedence over snapshot identity-drift checks:
it returns DetailUnavailable without opening a snapshot or protected reading.
Some remaining eligible rows can still form a valid legacy projection; others
can fail lifecycle validation. Tests assert this scoped view, not detection of
every foreign row. No unscoped retry is allowed to distinguish the cases.

## Required Evidence

Bound-view tests: exact delegation/result/exception identity, explicit global,
two views alternating tenants, no unscoped call, no constructor I/O, snapshot
misuse fails closed and debug/serialization expose no retained scope.

Real PostgreSQL reader tests exercise all five scoped selector families
(subject plus four new methods). Prove named tenant A/B, same ID different
tenant type, scoped-null vs legacy-unscoped, both presence mismatches, absent,
order/malformed metadata, and Operation ID separation. Capture executed SQL
and bound parameters, not only source substrings, to prove each predicate.

Real PostgreSQL source tests use Default Query with a bound source only in
tests; existing Production composition is untouched. Cover same/global,
cross/absent/deny with no protected decode; inline/deferred states, Found,
Expired, ephemeral, tombstones, malformed/incomplete eligible lifecycle data,
and separate Journal/Outcome/transport/dead-letter/purge foreign-row fixtures.
The malicious foreign ciphertext/key canary must not reach codec/key provider.

Use a second Connection to change subject after initial authorization but
before the detail snapshot: full tenant move/removal -> unavailable/no decode;
same-scope type/origin actor drift -> Integrity/no decode. Prove decode occurs
only within the source-owned RR/READ ONLY snapshot on the same Connection,
and record entry/exit transaction state. Add a second barrier after the
in-snapshot subject recheck and before the first protected fetch. Commit a
same-scope identity/data mutation through Connection B at that barrier; prove
Connection A retains the original identity/data through its repeatable-read
snapshot, and a later independent snapshot sees the new committed state.
Test ambient, auto-commit false,
failed begin/commit/rollback and poststate anomalies with controlled fixtures;
no foreign transaction completion, no retry and no result release on failure.

Architecture tests must exercise the actual oracle with positives and mutation
negatives: scoped-null replaced by unscoped, missing either tenant predicate,
Operation-ID predicate omission, scoped Journal/Outcome fallback, hidden
deferredOutcome scope drop, snapshot subject recheck omission/after-decode,
extra Production bound-view/scoped-entry caller and mutable scope. Keep
positive controls for legacy unscoped methods and inert comment/string text.
The actual oracle must also fix declarations, interface inheritance, final/
readonly, exact property and callable inventories, signatures and the explicit
SensitiveParameter matrix. Reject missing/extra methods or properties, wrong
visibility/type/attribute, added trait/superclass, constructor I/O and a declared
clone override, using syntax-valid mutations plus matching positive controls.
No baseline/config/dependency-rule relaxation or accepted Audit-source change.

## Package and Nonactivation

Add only the two new Internal types to the required package inventory, in
interface/bound-view order immediately after
`src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php`.
All previous85 paths retain relative order: exact87 unique. Both independent
inventories must agree, retain all31 existing mutation controls and additionally
reject omitted/reversed/duplicated new entries. Update the whole-export SHA
pin only after independently checking both inventories. No export from an
uncommitted tree; final Git/Composer same-SHA proof follows Acceptance/commit.

No Production constructor caller for the bound view and no external Production
caller of the new scoped Status-source methods is added except the exact bound
view's one findSubjectForTenant and one readDetailForTenant delegation site.
The source's scoped branches call its reader's five scoped families and the
existing Journal recordsForTenant/Outcome findForTenant; reader scoped wrappers
call only their same-file private SQL helpers. These are the allowed scoped
read edges, with inherited legacy edges retained. No external executable use,
constructor caller or alternate unscoped delegation by the view is permitted.
No route/CLI,
Container, ApplicationHttpRuntimeComposer, Default Query, Audit factory/Gate,
Journal/Outcome codec/store, schema/migration or public Documentation change.
Framework migrations13, Community Application5 and StoragePurpose9 remain.

## Documentation and Release

Capability `status.tenant_scoped_source.v1` is Internal/unreleased. Public
source/route/config/API inventory is empty. This spec is the implementation
contract; runnable user documentation waits for separately Accepted
composition. No old-version occurrence changes or new historical allowlist.
Browser/generated HTML/Search/raw Markdown/LLM/CI/Documentation delivery stay
Not Verified unless independently exercised; no Production deployment.

# D173: Generic Outbox Audit Adapter

Status: Decided — initial and finite metric amendment authority Green; correction pending Acceptance; BlackOps1.3 unreleased

## Decision

Keep exactly the three existing generic-Outbox events and a default-null finite
hook. Actual UPDATE RETURNING metadata supplies subject/tenant/resulting full
BIGINT version; a constructible claim's message never supplies trusted provenance.
Current ActorContext/correlation/build and fixed Relay/Cli bindings come from
future trusted composition. C3 alone owns partition and current-root ingress.

Adopt owned close after every enabled failure once an admitted begin was attempted,
following at most one eligible owned rollback. Never close/rollback foreign entry
or pre-begin scalar rejection. Logical DBAL idle after commit failure does not
prove physical reuse safety. Preserve actual committed effects, no exception-based
outcome inference, retry or reconnect inside the failing method. Controlled native
close/reconnect and Relay continuation proof is required; arbitrary driver-close
recovery is not guaranteed. Disabled legacy behavior is unchanged.

Adopt the five-method hook and exact writer signatures, component/policy
blackops.outbox.delivery v1, closed fresh native message table and finite internal
failure reasons. Reject generic callbacks, claim-derived tenant/actors, duplicated
UUIDs, safe-integer truncation and C4 relay reuse. Normal activation and unrelated
poison/expiry/attempt hardening remain outside the19-path amended source contract.

The [specification](../spec/123-generic-outbox-audit-adapter.md) owns the exact source contract;
the [Task](../orchestration/tasks/P23-003D5O3-generic-outbox-audit-adapter.md) owns actual baseline, paths,
resource allocation, proof and gates. Root adopted the already independently
Green preparatory authority, then explicitly selected the new Outbox/E1 pair
after actual O2/F1 Acceptance. This creates no new semantic dependency between
the two children and does not join the completed O2/F1 full.

## Joint qualification choice

The amended exact19/22 scopes share A2 and two package scripts only, union38. O3 owns
all three first; Root retains BO, then E1 owns the final merge and Root retains
BE. Preserve Accepted149/103, amended O3+10/+12 and E1+3/+5 plus separate3 runtime omission
cases, including the final F1 helper block. One new frozen full preserves all3756
actual predecessor identities and both exact disjoint new subsets. Every own
native/private/public-API proof remains mandatory and independently reviewed.

Root may allocate bounded iteration leases and separate outputs; it owns serial
shared resources, Reports, final gates, integration and Acceptance. No identical
successful gate repeats merely for another reviewer. E1 requires actual clean
committed framework export before local Acceptance; only the additional14-type
API autoload/minimal graph smoke belongs to parent006's final release manifest.
Same-SHA CI, Owner local preview, publication and delivery remain parent005/006.


## Finite metric amendment — 2026-10-04T18:29:50+09:00

The first unchanged-threshold checks failed: Hook CC40/methods17 and Writer
CC87/Kan2.42/methods23 exceed the existing15/1.6/10 limits. Root adopts the
seven stateless, finite helpers specified in spec123 section3 and listed in
section8; no waiver, suppression, dependency or eighth helper is authorized.
The hook retains10 methods, Writer9 public plus one private emission method.
Native transaction/SQL/entropy/cleanup stays in Store; current-root delivery
stays in Writer. Existing public signatures, runtime and privacy rules remain.

The raw DBAL carrier is truthfully annotated array<string,mixed>, with the exact
four-field runtime contract documented separately. Only checked Row hydration
returns a closed typed shape. This resolves an invalid static precondition,
not a widening of accepted runtime rows. Full key/value/tenant/BIGINT rejection
and before-effects ordering remain. No unchecked shape cast or Internal Store
dependency may substitute for validation.

Three concrete corrections accompany the split: each Writer phase independently
checks its stored Relay/Cli runtime before clock/HMAC/delivery; manual Hook rows
validate the complete returned version even though the phase uses retryAuditId;
and the existing package reverse-three control must mutate nonterminal lines
without breaking shell/extraction shape. Meaningful direct/native/guard proof
and the actual Relay continuation/counter cases remain required.

Initial152/107 was an unqualified draft. Corrected BO targets159/115, and E1's
unchanged +3/+5 yields BE162/120, with its three separate runtime omissions.
These are plans, not passed results. The original3427-file baseline,3756 cases,
all prior evidence, shared3 identities and O3-first ownership remain. Independent
amendment review precedes correction assignment; actual scoped metrics precede
completion of the larger proof matrix. The common full and E1 committed export
are unchanged requirements.

### Metadata declaration inventory ownership — 2026-10-05T13:03:05+09:00

Independent authority review c3-metadata-inventory-review.md
SHA2563b97b9bba48dde520279c34a44797c4e0f2686d68620069125b229c018475f57
is Green. C3's18 owned declarations remain exact. Exclude exactly
`src/Audit/AuditCatalogDefinition.php` and `src/Audit/AuditCatalogProvider.php`
from the C3-owned declaration inventory only; E1 guards own their declarations.
Retain both files in all-source C3 reference/call/constructor/dependency/security
scans, without adding any approved C3 edge. All other unknown src/Audit paths
remain rejected. Prove a parsed unlisted declaration fails; a parsed forbidden
C3 edge in each excluded file fails for that edge; and the unchanged18 declaration
checks/structural negatives work with these two metadata files present or absent.
This ownership clarification changes no Product/public contract, scopes19/22/38
or shared3. Only the existing O3 guard owner implements the correction.

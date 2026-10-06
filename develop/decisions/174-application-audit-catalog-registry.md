# D174: Application Audit Catalog Registry

Status: Decided — issued authority Green; implementation pending Acceptance; BlackOps1.3 unreleased

## Decision

Promote exactly twelve existing Core metadata/error types by attribute/import
only, and expose AuditCatalogDefinition/AuditCatalogProvider. The existing
exception's public constant and helpers are intentionally supported API. Reuse
one metadata/codec model and the existing ServiceProvider class-binding route.
Internal registry requires one explicit nullable provider with no default;
null/valid empty means the two sealed Framework catalogs, never fallback on error.

Adopt32 declarations counted before coalescing, existing canonical ordering,
exact sealed-ID collision rejection at every version, forced Application owner,
canonical first-instance identity and fresh finite validation exceptions.
Reference rejection covers declaration.events and each event's actionTypes,
semanticTuples and evidenceFields using native ReflectionReference before any
Application catalog construction. Readonly/value-copy alone does not detach refs.
The twelve existing type bodies remain unchanged.

Capture provider once and retain only the immutable lookup. This is trusted build
metadata assembly, not a PHP sandbox, activation, persisted cross-deployment
history check or complete business Audit journey. Version/history/activation and
projector evidence remain later E/005/006 work. Public API impact is nonzero while
current Stable1.2.1 claims and public source remain unchanged.

The [specification](../spec/124-application-audit-catalog-registry.md) owns the exact source contract;
the [Task](../orchestration/tasks/P23-003E1-application-audit-catalog-registry.md) owns actual baseline, paths,
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

## Sibling metric amendment — 2026-10-04T18:29:50+09:00

O3 adds only the seven stateless helpers and bounded corrections in [D173](173-generic-outbox-audit-adapter.md). E1 remains22 paths/19 exclusive, with unchanged declaration semantics and own export requirement. Planned BO159/115 and BE162/120 replace the unqualified152/107 and155/112 plans; the shared3 transfer remains pending.

# D166: Retention Purge Audit Adapter

Status: Decided — D5P Accepted (local); ordinary composition and release pending

## Decision

Adopt [spec117](../spec/117-retention-purge-audit-adapter.md) for D5P, the remaining
purge part of D154/D165's retention source. D5H stays Accepted and unchanged.
Explicitly add Accepted D4B as a prerequisite solely for C3's Connection identity
predicate, alongside Accepted C/D1. No dependency on D4 evaluation or D5H Product
helpers is introduced; other ordinary-D and post-F edges remain unchanged.

Privately compose the existing native purge graph from three final Internal
types: facade, concrete native audit hook and SQL-free Audit writer. The facade
rejects wrong Connection/ambient/non-autocommit use before planning and does not
wrap the invocation in a new transaction. Native category roots remain the owners
of actual commits; each positive-effect audit callback is the insertion point.

Select C3 delivery first, then the private native audit store and existing System
Log decorator, all within that native category root. A precommit failure rolls
back that category's database work; an already successful external log can remain.
Earlier category commits survive later failure. Actual commit-then-throw remains
a failed response with committed rows and no blind retry. No invocation-wide
atomicity, exception-identity or exactly-once recovery claim is added.

Preserve canonical PostgreSQL schema as a prerequisite. Native exactly-one delete
checks rely on its unique/non-null identity constraints; this child does not
repair or certify databases where those constraints were removed. Native hold,
cutoff, planner and category-count semantics remain unchanged. Journal's many
deleted rows yield one positive-effect record, not one event per deleted row.

The payload tombstone's native record currently omits tenant. In the explicit
new hook, recover it by one strict read of the surviving mutated Operations row
inside its owned root, and use a corrected native record for both audit surfaces.
The row is already write-locked by the mutation. The other four targets use the
tenant proved by their DELETE predicates and positive effect; their source rows
need not survive or have an Operations parent. No payload decoding or arbitrary
source callback is introduced. This audits the actual mutated identity while
preserving administrative cross-tenant semantics. It does not add a planned-tenant
UPDATE predicate or stronger hold concurrency/authorization guarantees.

Use only the existing `retention.purge.committed` operational definition and
fixed durable policy/evidence. Native-generated purge-audit ID provides the keyed
action input; target plus operation ID provides the keyed operation_data resource.
Both database records share that native identity at commit; no extra allocator.
The writer receives explicit trusted actor/execution/correlation/runtime/build
binding, validates before one writer clock capture and appends in the existing
root. Legacy actor/policy/cutoff/count data remain on their legacy surface.

## Alternatives not selected

- Append after the final purge result: too late to share any native deletion root.
- Wrap all native categories in one root: changes public partial-progress behavior
  and produces nested roots incompatible with C3's level1 requirement.
- Accept arbitrary supplied native service/audit Port: hides Connection ownership.
- Rewrite native tombstone tenant predicates or unexpected-count branches here:
  feasible, but changes native behavior and needs a separately bounded authority.
  The selected same-root subject hydration/canonical-schema contract needs none.
- Treat a native null transport tenant as global or look up Operations for every
  target: misattributes tenant payload purges or rejects valid Inline/deleted sources.
- Replace legacy audit tables/logs, expand Catalog evidence or activate commands:
  outside this internal source child and the preserved Stable compatibility boundary.

## Consequences

Three explicit Internal responsibilities preserve Core/native interfaces and
layering. Architecture/package allowance grows only for that graph; package
required inventory109→112 and the operational provider's exact two callers gain
only the new writer helper. Public API/composition and D5H implementation stay exact.

The [Task](../orchestration/tasks/P23-003D5P-retention-purge-audit-adapter.md) fixes
eleven Product/Test files, independent review, serial verification, native failure
evidence and full predecessor preservation. Preparation Green permits the next
implementation stage; it is not D5P Acceptance or release evidence. Parent D,
ordinary composition, remaining D5 children, E/F/G, post-F control and release
remain incomplete. Final local documentation user review remains required.

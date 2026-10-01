# D165: Retention Hold Audit Adapter

Status: Decided — D5H Accepted (local); canonical integration and BlackOps 1.3 remain pending

## Decision

Split D154's retention/hold source into D5H hold place/release and a subsequent
purge child. Their mutation/transaction units differ, and a combined callback
would incorrectly imply that a complete purge invocation has one commit.
Adopt [spec116](../spec/116-retention-hold-audit-adapter.md) for D5H only.

Use two Internal concrete types: a root-owning RetentionHoldPort adapter and a
C3-facing durable writer. The adapter privately constructs the unchanged native
Store with its exact Connection/schema, reads the persisted subject within that
root and returns only after hold mutation plus durable ingress commit together.
This avoids a new Transport→Internal dependency, Deptrac exception, native public
helper API or duplicate native mutation implementation. One bounded subject SELECT
inside the adapter is explicitly authorized; it uses the shared schema helper.

The writer receives explicit trusted actor/execution/correlation/runtime/build
binding from its composition caller. Legacy actor/reason strings do not authenticate
that binding. Caller authorization remains required; no new authorization decision
or execution ID is inferred from the held operation. Action identity is the keyed
persisted hold ID plus phase, with no extra UUID allocator.

Reject an ambient/non-autocommit/wrong-Connection graph before work, own one level1
root and append within it. No-policy fallback is inapplicable: these operational
Catalog events are always durable. An unconfigured native Store remains unchanged.
Confirmed rollback, partial failure and committed-then-throw are distinct outcomes;
do not invent a failed phase or blindly replay an ambiguous mutation.

Add an explicit DAG prerequisite from Accepted D4B to D5H for the existing C3
`usesConnection()` predicate. This is the only new sibling prerequisite; D5H does
not consume D4 policy/evaluation types. D1 and Accepted C remain prerequisites.
Other D5 children and ordinary composition retain the parent DAG. D-control still
waits Accepted F. No canonical composition is activated by this child.

The operational Catalog reference allowance grows only from its existing lookup
reference to that lookup plus one exact writer helper. Provider bytes, lookup
lineage and all other reference/negative controls remain unchanged.

## Consequences

- Hold mutation and its event have one actual commit boundary in this explicit
  internal adapter, with default native behavior and the public Port preserved.
- Trusted per-invocation binding remains a named integration obligation. Typed
  values alone do not prove caller authorization or correct service lifetime.
- Fresh place after ambiguity may create another hold; repeated release fails.
  No exactly-once recovery or stronger hold-versus-purge locking claim is added.
- D5H acceptance will not complete retention purge, parent D or BlackOps1.3.
  Public documentation and release wait their own gates, including local user review.

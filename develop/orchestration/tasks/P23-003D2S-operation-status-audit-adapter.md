# P23-003D2S: Operation Status Audit Adapter

Status: Authority revision next — D2S1 Accepted; no adapter Product work.

## Current Boundary

The tenant-scoped source prerequisite is Accepted in [D2S1 Report](../reports/P23-003D2S1-tenant-scoped-status-source.md).
Revise the initial adapter candidate around spec108's scoped source and obtain
independent authority review before dispatching adapter implementation. The
old three-type scope and package counts below remain incomplete proposal data.

## Goal

Define and independently review the bounded Status reader integration after
Accepted P23-003D2, preserving the existing public API and keeping runtime
activation in P23-003D-integration. Seal the exact source, tenant, transaction,
terminal-result and failure contracts before dispatching a configured Luna
worker. D2J, D2X, D3/D4/D5, D-control and release are outside this Task.

## Prerequisite

P23-003D2 is Accepted at
`0cd699e5e1d031228bc4a05767164a8eb268a004`. The prior handoff confirmed clean
same-SHA Git/Composer export and staged0; this turn confirmed that HEAD and a
clean tree. The earlier D2 authority-only checkpoints in immutable documents
are historical workflow snapshots, not current Product status.

## Current Authority Scope

Only Orchestrator management work is currently permitted:

```text
develop/orchestration/tasks/P23-003D2S-operation-status-audit-adapter.md
develop/orchestration/reports/P23-003D2S-operation-status-audit-adapter.md
develop/STATE.md
develop/spec/107-operation-status-audit-adapter.md
develop/decisions/156-operation-status-audit-adapter.md
```

No Production/Test edit, commit, push, deployment or activation is permitted
under this research checkpoint. The spec/Decision preserve an explicitly
incomplete candidate and its source blocker; they do not authorize implementation.
Before Product work, replace this draft with
the reviewed exact Product/Test scope, most-specific Decision/specification,
immutable prerequisite freeze, full verification commands and Acceptance
criteria. A worker must not infer these from this draft.

## Required Reading

Read AGENTS, STATE, this Task, spec/README, Specifications 99/105/106 and
Decisions 147/154/155; then the current Status reader, HTTP handler/composer,
authorization/result/exception types and their focused tests. Repository
model profiles remain authoritative.

## Authority Questions To Resolve

1. Preserve the legacy `DefaultOperationStatusQuery` and use a separate,
   unactivated audited graph; fix its exact construction and sole entry.
2. Enforce D147/D155 cross-tenant unavailable without silently preserving the
   legacy permissive-authorizer cross-tenant behavior.
3. Fix a strict trusted Status execution source; the existing HTTP Status
   path has no request execution identity and must not infer one from target,
   actor, headers, or a nullable Operation execution scope.
4. Fix Found/Expired/Unavailable terminal selection and contracted-exception
   identity preservation, with terminal failure taking precedence.
5. Preserve the source-owned read-only transaction between the two separate
   Audit roots; define supported Connection identity and invalid-state guards.
6. Limit Architecture/package changes to the exact new audited graph, retain
   all Accepted D2 negative oracles, and prove no current runtime activation.

## Authority Completion Criteria

- Current-source findings and all six choices recorded with evidence.
- Exact Decision/spec/Task and test matrix independently reviewed.
- Report/STATE distinguish authority readiness from implementation and release.
- No Product work before the reviewed authority and clean preflight.

## Next Action

Revise this adapter candidate around the Accepted D2S1 source, seal its exact
scope and test contract, and obtain independent review before implementation.

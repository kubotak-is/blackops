# P23-003D2S: Operation Status Audit Adapter Report

Status: Authority revision next — D2S1 Accepted; no adapter Product work.

Updated At: 2026-09-08T11:20:30+09:00

## Current Handoff

[D2S1](P23-003D2S1-tenant-scoped-status-source.md) is Accepted. Its scoped source
resolves the prerequisite identified below; the initial adapter proposal still
needs revision and independent review. No adapter was implemented or activated.
The following research is the historical pre-D2S1 source analysis.

## Historical Summary

The user's continuation starts D2S authority preparation after Accepted D2.
No D2S packet existed. Status is the first independent reader child; no D2J
or D2X dependency is introduced.

## Historical Changed Files

Only this Report, the new D2S Task, STATE, proposed Specification107 and
proposed D156: exact5 management paths. No Production/Test changes.

## Historical Decisions and Assumptions

- D2 final SHA is `0cd699e5e1d031228bc4a05767164a8eb268a004`; previous-turn
  final same-SHA export passed. This turn confirmed HEAD and clean tree before
  management edits, not a new export run.
- D154 requires a most-specific Decision and exact Task before Product work.
- `DefaultOperationStatusQuery` looks up restricted subject metadata before
  authorizing, then reads detail only after Allow. An outer Audit boundary can
  precede lookup but cannot alone fix tenant-scoped SQL for subject/detail.
- The legacy tenant matrix deliberately permits cross-tenant Found when the
  Application authorizer allows it. D147/D155 require cross-tenant unavailable
  for audited reads. The audited graph must explicitly reconcile this.
- Expired is a 410 response after authorized detail access, not a 404
  unavailable result; the new authority must select its terminal explicitly.
- `PostgreSqlOperationStatusSource::readDetail()` owns a repeatable-read,
  read-only transaction. Level0 at reader entry/exit is not a claim that the
  whole detail read runs without a transaction.

## Historical Commands and Results

Read-only AGENTS/STATE/Task/spec/Decision/source/test inspection; Git HEAD
and clean status verified. Production tests, new gates, package export, CI,
browser and deployment have not run in this task.

## Historical Release Documentation Impact

Stable `1.2.0`; candidate `1.3.0-unreleased`; proposed capability
`audit.operation_status_adapter.v1`. Public Source/route inventory: empty at
this management-only stage. No public API/config/command/version occurrence
or generated artifact changed. Browser/generated HTML/Search/raw Markdown/
LLM/CI/Documentation delivery are Not Verified. No Production deployment.
Guide/internal runtime documentation impact will be decided with the exact
adapter graph; no reader activation or Stable capability is claimed.

## Historical Acceptance Criteria

Source research identified a concrete prerequisite; the initial adapter-only
proposal is explicitly incomplete and not accepted. Exact Production scope,
tests and independent authority reviews remain pending. No worker dispatched.

## Historical Confirmed Source Blocker

Specification99:125 requires tenant_type/tenant_id and Operation identity in
the same SQL predicate, not fetch-by-ID followed by PHP tenant comparison.
Source inspection confirms:

- `DefaultOperationStatusQueryTest.php:115-145` intentionally returns Found
  for none/same/cross when the Application authorizer allows it. D147:480-483
  requires cross-tenant unavailable for audited reads. An audit-only mandatory
  tenant guard is necessary, but not sufficient.
- `PostgreSqlOperationStatusSource.php:60-74` calls unscoped `findSubject()`.
  `PostgreSqlStatusReader.php:76-110` already has a scoped subject method, but
  the current source does not select it.
- The source's detail helpers at531-603 call unscoped deferredState,
  purgeTargets, Journal records, outcomeExists, deadLetterExists and Outcome
  find. The source does not pass the subject's tenant into those lookups.
- `PostgreSqlStatusReader.php:113-181` shows operation-ID-only predicates for
  deferred state and retention. Existing Journal `recordsForTenant` and Outcome
  `findForTenant` can be reused, but scoped equivalents for all Status detail
  sources and snapshot/race invariants still need a reviewed contract.

These are source-level contract findings, not a claim of a demonstrated live
cross-tenant exploit. No live exposure test was performed. The initial
three-type spec107/D156 proposal remains visibly blocked; its API/path counts
must not be copied into a worker dispatch.

Independent Transaction research returned Changes Requested P1=1/P2=0/P3=0
for this incomplete adapter-only boundary. Independent General research also
confirmed the complete scoped-source prerequisite. Neither is an Acceptance
review of a corrected implementation; no Product tests were rerun.
The prerequisite must recheck the tenant-scoped subject inside the detail
snapshot before protected decode, not trust an earlier subject indefinitely.

## Historical Proposed Safe Split

1. Separately authorize and review a tenant-scoped Status-source prerequisite:
   exact subject/detail SQL, before-decode isolation, source-owned snapshot,
   null/same/cross and subject/detail race evidence; preserve current public
   API and explicitly decide additive latent paths versus legacy behavior.
2. Implement that exact packet through the configured Luna worker; independent
   General/Transaction reviews and full gates before Acceptance.
3. Revise D2S's audit adapter around the Accepted source, re-derive its exact
   file/package/Architecture inventory, then review and implement it.
4. Keep current Application/HTTP activation in D-integration, not either child.

## Historical Remaining Issues

The source prerequisite exceeded the initial adapter-only proposal; the user
subsequently approved it by continuation. P23-003D2S1 now owns its authority
and implementation. The remaining adapter authority questions must also close.
Parent D remains incomplete; D-control still requires Accepted F.

## Historical Suggested Next Action

Complete the authorized P23-003D2S1 prerequisite packet and its review; do not
dispatch the adapter worker before that prerequisite is Accepted.

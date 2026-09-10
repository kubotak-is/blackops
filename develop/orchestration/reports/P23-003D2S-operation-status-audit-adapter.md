# P23-003D2S: Operation Status Audit Adapter Report

Status: Authority Accepted; Runtime and Architecture implementation slices next.

Updated At: 2026-09-11T01:47:07+09:00

## Summary

P23-013 synchronized published1.2.1 into development at clean merge
7d90753485c8bf9e63578e8ce83cc1762234c083 and passed same-SHA Git/Composer export.
D2/D2S1 foundations are already Accepted. Revised spec107/D156/Task now use a
fresh tenant-bound source per read, strict authorizer guard, server-owned Status
read UUID and requested/read-only-snapshot/terminal three-root ordering.
No adapter has been implemented or activated; focused independent authority
re-review and Root General Review are Green. Published Stable remains1.2.1; this work is unreleased1.3.

## Changed Files

Root: spec107/D156 and this Task/Report; minimal D155/spec108/D157 forward
references; spec index, current TODO and rotated STATE. No Product/Test changes.
P23-013 completion receipts are administrative handoff, outside D2S Product scope.

## Decisions and Assumptions

- Scoped SQL is supplied by Accepted D2S1. The guard supplements SQL, and one
  reused query binds its nullable tenant separately on every read.
- Allocate trusted `operation_status_read` UUIDv7 independently of D2 nonce,
  Clock and Store UUIDs. It is not a replacement for missing caller identity.
- Keep three same-Connection roots; terminal failure overrides pending result
  or safe exception, with no retry/fallback/foreign transaction repair.
- Independent Transaction/Documentation review identified inherited exception
  trace exposure. D2S now explicitly permits only14 SensitiveParameter additions
  to Default Query and necessary formatting; algorithm and exception identity
  remain unchanged. Do not narrow the guarantee to hide the inherited gap.
- The sealed Default makes defensive unknown-result/catch-all rows unreachable
  through normal fixtures. Actual AST/mutation controls cover these rows; no
  mock delegate seam or impossible end-to-end unit gate is demanded.
- Package inventory grows87 to90 with old35 controls plus4 new controls. Final
  full PHPUnit subsumes matching focused iteration coverage per spec109.

## Commands and Results

Read-only source and authority investigation is complete. No D2S Product test
or implementation command has run. Authority candidates/review context are
under `/tmp/blackops-d2s-20260911/authority`.

PHP8.5.7 preflight:
`docker compose run --rm --no-deps -T --volume /tmp/blackops-d2s-20260911:/probe app php -d zend.exception_ignore_args=0 /probe/trace-probe.php`
passed exit0 / tool0.911s. It intentionally confirms the baseline exposure using
dummy values: Operation ID/actor/tenant visible=true; previousIsNull=true;
queryCode=status_query.storage_failed. No DB, source mutation or real secrets.
Receipt: `/tmp/blackops-d2s-20260911/trace-probe-result.json`. This is a confirmed
preflight defect requiring the narrow annotation correction, not final safety
proof. The worker must prove all four codes safe with argument capture enabled.

## Reviews and Acceptance Criteria

First Transaction lane: P1=0/P2=2/P3=0 (trace boundary; unreachable unit gate).
First Documentation lane: P1=0/P2=1/P3=0 (same trace boundary).
Root General agrees and revised those contracts before implementation. Focused
Transaction and Documentation rechecks are Green with P1/P2/P3=0. Reviewed
spec107 content hash before acceptance-only status wording:
912cd4a9ef7e9a3c5990c1d3f71c554b01007619137348bdfce74585d2703125.
Root General confirms exactly14 declared Default parameters, unchanged scoped
source protocol and the actual-oracle coverage assignment. Authority Accepted.
For independent implementation work, the13 paths are split into Runtime8 and
Architecture/package5; PHP/DB ownership is explicit in the Task. No contract or
required command coverage is reduced by this ownership split.
Authority acceptance and Product acceptance are separate; neither is inferred
from the accepted source prerequisite or the1.2.1 release.

## Release Documentation Impact

Current release tuple is exactly1.2.1 in release-authority.json. Internal
capability audit.operation_status_adapter.v1 remains unreleased. Public source/
route/API/config inventory empty; no old-version or historical allowlist change.
Spec107 supplies implementer guidance; runnable usage docs await activation.
No generated/Search/LLM/browser/CI/delivery/deployment claim in this Task.

## Remaining Issues

Luna implementation, required verification union, three implementation review lanes,
Acceptance and clean final-SHA export. Other Audit adapters/API/signature/query/
export/runtime/docs/release tasks remain outside this bounded adapter.

## Suggested Next Action

Root checkpoints accepted authority and records immutable inputs, then assigns
Runtime8 and Architecture/package5 to the two configured Luna/max workers.

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

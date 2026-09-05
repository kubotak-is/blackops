# P23-003D2: Operation Read Audit Session Foundation Report

Status: Authority Review Green — clean post-commit implementation preflight required; Product work not started

Updated At: 2026-09-05T12:20:14+09:00

## Summary

D155 and the D2 Task now isolate the Operation-read foundation from all four
reader integrations. The proposed boundary adds a dedicated 16-byte nonce
source, trusted execution-source port, fixed draft factory, private one-shot
session, strict writer port, and typed default-unavailable Diagnostics
authorization facade.

The first fresh authority review is Historical. Transaction／Security returned
Green `P1=0/P2=0/P3=0`. General／Architecture and Documentation／Release
returned Changes Requested `P1=2/P2=7/P3=0` and `P1=0/P2=3/P3=0`,
respectively, on the original draft. Those roots were corrected before the
second review.

The second three-lane review is now complete on ordered exact9 aggregate
`dd434b7693067d59c64ab4ebe432293014b8343f14e0bf8befc54e1b0718f45d`.
General／Architecture was Green `P1=0/P2=0/P3=0`. Transaction／Security
returned Changes Requested `P1=0/P2=1/P3=0`; Documentation／Release returned
Changes Requested `P1=1/P2=1/P3=0`. Their three roots were an unpinned
session-construction boundary inside `open()`, one nonexistent D148 path, and
one ambiguous migration tuple. The current correction fixes all three without
moving a Product／Test path and produced the first bounded closure below.

The next bounded closure used exact9 aggregate
`4622476cfa8e47e5bbee38fbc100f445a0b136cae987c8ac1dbf5125fc6e4edf`.
General／Architecture and Transaction／Security were both Green
`P1=0/P2=0/P3=0`. Documentation／Release returned Changes Requested
`P1=0/P2=1/P3=0` solely because the current Next Action repeated the already-
completed correction-freeze step. The current management text removes that
stale step. A final closure review must now use the newly supplied unchanged
exact9 and verify it again at the end.

The final unchanged closure used exact9 aggregate
`b53910358f186672748888ac04f1c7f085dcd91733004656e36eb63f20725b80`.
General／Architecture, Transaction／Security, and Documentation／Release were
separately Green `P1=0/P2=0/P3=0`; every lane reproduced the same ending
freeze. Authority review is complete. Product work remains stopped until this
reviewed management packet is committed, cleanly frozen, and fresh
implementation preflight is Green.

The authority review found one Accepted C3 behavior that must be changed in
this bounded packet: `AuditProtectedReadGate::terminal()` currently suppresses
non-Allow append failures. D155 explicitly supersedes only that rule and
requires every terminal branch to fail closed before result release. No other
C3 behavior is reopened.

P23-003D1 is Accepted at commit
`35afacd9741272d4e1207d3a76829d85653e4f6f`, with a clean same-SHA
Git／Composer package export Green. No push occurred. Stable `1.2.0` remains
current; BlackOps `1.3` and all D2 behavior remain unreleased.

## Changed Files

Current authority-draft management scope:

```text
develop/TODO.md
develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md
develop/decisions/155-operation-read-audit-session-foundation.md
develop/orchestration/tasks/P23-003D2-operation-read-audit-session-foundation.md
develop/orchestration/reports/P23-003D2-operation-read-audit-session-foundation.md
develop/spec/105-blackops-1-3-capability-contract.md
develop/spec/106-phase-23-delivery-plan.md
develop/spec/README.md
develop/STATE.md
```

No Product／Test path has changed. Product work is not authorized until the
authority packet is independently reviewed, committed, cleanly re-frozen, and
fresh preflight lanes are Green.

## Decisions and Assumptions

- D2 is foundation-only. D2S Status, D2J Journal／Outcome, and D2X Diagnostics
  integrations remain separate Tasks.
- `AuditProtectedReadGate` is the sole Production implementation of the new
  writer port.
- Non-Allow terminal failures no longer release their decision. This is the
  one explicit D152 supersession.
- The 24-byte Storage Protection nonce source is not reused. D2 owns one
  16-byte source whose CSPRNG implementation calls `random_bytes(16)` once.
- The factory obtains execution identity through a strict port with no default
  or fallback implementation.
- Raw Operation ID is used only in one HMAC Resource derivation and is not
  retained or recorded.
- All three drafts are built before requested delivery; the session permits
  one terminal attempt and no retry.
- `OperationReadAuditSession::open()` completes its local object and opaque
  snapshot before requested delivery, then performs only a pure return. A
  local construction failure performs zero writer／Store work.
- Requested and terminal are separate Store-owned standalone transactions.
- Diagnostics receives only a typed default-unavailable authorization seam;
  its query, CLI, viewer, and data access remain unchanged.
- Package required inventory is exact76 before D2 and exact85 after the nine
  new Internal source paths. Canonical insertion anchors and exact85
  count／uniqueness are fixed; the mutation suite becomes exact31
  (`17 inventory + 14 call-contract`).
- The default Diagnostics implementation is named
  `UnavailableOperationDiagnosticsAuthorizer`; it does not claim a Deny
  decision while returning ResourceUnavailable.
- Factory, session, request, Gate, and Architecture contracts now enumerate
  magic methods, private helpers, direct dependencies, call counts, build-ID
  grammar, corrupt-state behavior, and exact existing `AuditDeliveryFailure`
  cases. No new failure enum is authorized.
- D154's adapter rules are conditional. D2 specifically owns one unkeyed
  requestRef action, one keyed Resource ID, Store-owned UUIDs, null
  idempotency, separate required Store roots, and no Outbox append.
- Stable `1.2.0`, migration `13 + 5 = 18`, public Guide／Website, routes,
  commands, and runtime activation remain unchanged.

## Commands and Results

Historical — pre-draft authority research used read-only repository inspection:

```text
git status --short --branch
git rev-parse HEAD
git status --porcelain=v1 -uall
sed／rg inspection of AGENTS, STATE, D145／D147／D152／D154, Specifications
92／99／105／106, Accepted C3／D1 Tasks and Reports, current reader／Audit／
Architecture／package source
node independent HMAC and requestRef vector reconstruction
```

Historical — pre-draft starting state:

```text
branch=agent/p23-001-blackops-1-3-feasibility
HEAD=35afacd9741272d4e1207d3a76829d85653e4f6f
worktree=clean
staged=0
```

Historical — first-review authority-draft freeze:

```text
branch=agent/p23-001-blackops-1-3-feasibility
HEAD=35afacd9741272d4e1207d3a76829d85653e4f6f
staged=0
status=exact9 management paths
orderedExact9Aggregate=be285148ef0cb4c7bb0fdafea79e795a50c439693c1b6e260e0de5503d51ca0f
gitDiffCheck=PASS
ProductTestPackageDeptracDelta=0
```

Historical — second-review correction freeze:

```text
branch=agent/p23-001-blackops-1-3-feasibility
HEAD=35afacd9741272d4e1207d3a76829d85653e4f6f
staged=0
status=exact9 management paths
orderedExact9Aggregate=dd434b7693067d59c64ab4ebe432293014b8343f14e0bf8befc54e1b0718f45d
gitDiffCheck=PASS
GeneralArchitecture=P1=0/P2=0/P3=0 Green
TransactionSecurity=P1=0/P2=1/P3=0 Changes Requested
DocumentationRelease=P1=1/P2=1/P3=0 Changes Requested
ProductTestPackageDeptracDelta=0
```

Historical — first bounded-closure freeze:

```text
branch=agent/p23-001-blackops-1-3-feasibility
HEAD=35afacd9741272d4e1207d3a76829d85653e4f6f
staged=0
status=exact9 management paths
orderedExact9Aggregate=4622476cfa8e47e5bbee38fbc100f445a0b136cae987c8ac1dbf5125fc6e4edf
gitDiffCheck=PASS
GeneralArchitecture=P1=0/P2=0/P3=0 Green
TransactionSecurity=P1=0/P2=0/P3=0 Green
DocumentationRelease=P1=0/P2=1/P3=0 Changes Requested
ProductTestPackageDeptracDelta=0
```

Historical — final authority-closure freeze:

```text
branch=agent/p23-001-blackops-1-3-feasibility
HEAD=35afacd9741272d4e1207d3a76829d85653e4f6f
staged=0
status=exact9 management paths
orderedExact9Aggregate=b53910358f186672748888ac04f1c7f085dcd91733004656e36eb63f20725b80
gitDiffCheck=PASS
GeneralArchitecture=P1=0/P2=0/P3=0 Green
TransactionSecurity=P1=0/P2=0/P3=0 Green
DocumentationRelease=P1=0/P2=0/P3=0 Green
ProductTestPackageDeptracDelta=0
```

The following independent Node reconstruction was run literally; it does not
copy PHP output:

```bash
node --input-type=module <<'NODE'
import { createHash, createHmac } from 'node:crypto';
const lp = (value) => {
  const bytes = Buffer.from(value, 'utf8');
  const size = Buffer.alloc(4);
  size.writeUInt32BE(bytes.length);
  return Buffer.concat([size, bytes]);
};
const key = Buffer.from('0123456789abcdef0123456789abcdef', 'utf8');
const operationId = '0194a000-0000-7000-8000-0000000000d2';
const opaquePreimage = Buffer.concat([
  lp('blackops.audit.opaque-id.v1'),
  lp('resource'),
  lp(operationId),
]);
console.log(`opaque ${opaquePreimage.length} oid:v1:${createHmac('sha256', key).update(opaquePreimage).digest('hex')}`);
for (const purpose of ['status', 'journal', 'outcome', 'diagnostics']) {
  const basis = {
    actor: { id: 'actor-1', type: 'user' },
    execution: { id: 'blackops-cli', type: 'process' },
    nonce: '00112233445566778899aabbccddeeff',
    purpose,
    requestedAt: '2026-09-05T02:00:00.123456Z',
    tenant: { id: 'tenant-1', type: 'account' },
  };
  const json = JSON.stringify(basis);
  const preimage = Buffer.concat([lp('blackops.audit.read.request-ref.v1'), lp(json)]);
  console.log(`${purpose} ${Buffer.byteLength(json)} ${preimage.length} oid:v1:${createHash('sha256').update(preimage).digest('hex')}`);
}
NODE
```

Result, exit `0`:

```text
opaque 83 oid:v1:e697b9f4332f3532bd84ba2bbe553152c5d223663d3bdec36e8b38e420f1d115
status 241 283 oid:v1:5a83343aa8fcf1e3baba2147571a92fa35c5424507c0b27407a0ca19766a3b3a
journal 242 284 oid:v1:2726a66731f81f741ccb83a471637381cd80b944687aa9889b6975f100eb5096
outcome 242 284 oid:v1:3ac6231d80a97591552b92f933f4a1e50efd69e93c149961da65855f1c4b2e67
diagnostics 246 288 oid:v1:aa763c94292e005ea5dc420909a1b90372602265d3fe9318ae053b1b214fca4c
```

The correction freeze is computed with these literal read-only commands in
the exact Changed Files order:

```bash
git branch --show-current
git rev-parse HEAD
git diff --cached --name-only
git status --porcelain=v1 -uall
sha256sum develop/TODO.md develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md develop/decisions/155-operation-read-audit-session-foundation.md develop/orchestration/tasks/P23-003D2-operation-read-audit-session-foundation.md develop/orchestration/reports/P23-003D2-operation-read-audit-session-foundation.md develop/spec/105-blackops-1-3-capability-contract.md develop/spec/106-phase-23-delivery-plan.md develop/spec/README.md develop/STATE.md
sha256sum develop/TODO.md develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md develop/decisions/155-operation-read-audit-session-foundation.md develop/orchestration/tasks/P23-003D2-operation-read-audit-session-foundation.md develop/orchestration/reports/P23-003D2-operation-read-audit-session-foundation.md develop/spec/105-blackops-1-3-capability-contract.md develop/spec/106-phase-23-delivery-plan.md develop/spec/README.md develop/STATE.md | sha256sum
git diff --check
```

The correction-inclusive Report hash and ordered exact9 aggregate cannot be
embedded into this Report without changing the bytes being hashed. They are
computed after the moving management set settles and supplied out-of-band to
each closure reviewer. The same rule applies to the post-commit immutable
exact14 freeze supplied to implementation preflight.

Tests, build, Docker, package export, browser, network, CI, release, deploy,
and runtime activation were Not Run／Not Verified during authority drafting.

## Acceptance Criteria

- [x] first General／Architecture authority review completed; Historical Changes Requested `P1=2/P2=7/P3=0`
- [x] first Transaction／Security authority review Green `P1=0/P2=0/P3=0`
- [x] first Documentation／Release authority review completed; Historical Changes Requested `P1=0/P2=3/P3=0`
- [x] second General／Architecture authority review Green `P1=0/P2=0/P3=0`
- [x] second Transaction／Security authority review completed; Historical Changes Requested `P1=0/P2=1/P3=0`
- [x] second Documentation／Release authority review completed; Historical Changes Requested `P1=1/P2=1/P3=0`
- [x] second-review bounded corrections applied
- [x] first bounded-closure General／Architecture and Transaction／Security reviews Green `P1=0/P2=0/P3=0`
- [x] first bounded-closure Documentation／Release review completed; Historical Changes Requested `P1=0/P2=1/P3=0`
- [x] stale current Next Action corrected
- [x] final unchanged exact9 three-lane authority closure review Green `P1=0/P2=0/P3=0`
- [ ] management-only authority commit and clean immutable freeze
- [ ] fresh implementation preflight lanes separately Green
- [ ] configured Luna exact24 implementation
- [ ] exact22 Iteration Gate Green
- [ ] fresh implementation reviews and unchanged refreeze Green
- [ ] Complete Final Gate Green from command 1
- [ ] Orchestrator P23-003D2 foundation Acceptance
- [ ] reviewed candidate commit and clean same-SHA package export Green

## Remaining Issues

Authority review is Green and no authority finding remains. Product work stays
stopped until the reviewed management packet is committed, the resulting tree
is cleanly frozen, and fresh implementation preflight is separately Green.

After D2 foundation Acceptance, D2S／D2J／D2X integrations, D3, D4, D5,
D-integration, and post-F D-control remain. Parent P23-003D therefore remains
incomplete.

## Suggested Next Action

Use this reviewed exact9 as the management-only commit input. From the
resulting clean exact commit, compute the immutable exact14 freeze and run
fresh three-lane implementation preflight. Do not dispatch the Production
worker before every preflight lane is Green, and do not push.

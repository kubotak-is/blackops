# P23-003F4: Durable Audit Anchor and External Effect

Status: Draft — reservation/frame and provider contracts adopted; Product work not issued

## Goal

Complete the mandatory durable authenticated AnchorIntent, lease/recovery,
receipt persistence and real external effect boundary for1.3 under current105/106
and [specification127](../../spec/127-audit-anchor-durable-external.md).
All fourteen local operations and actual provider/trust integration remain in scope.
The bounded adoption in [D177](../../decisions/177-audit-anchor-reservation-and-frame-bound.md)
settles reservation identity and frame admission; it does not complete this task.

## Ownership and permitted work

Root owns this management preparation, specifications127/128, D177/D178, its Report, bounded
specification index/TODO references and STATE. Preserve both checkouts' unrelated
history. The accepted integration checkout is the future implementation base;
the mixed primary Product/index and its older Website are not implementation inputs.
No worker Product file, API, schema, migration, runtime or shared DB lease is issued
by this draft. Full exact ownership must be frozen before Product implementation.
F3 continues separately under its existing38/3 ownership and acceptance criteria.

## Adopted choices

- One immutable reservation per checkpoint in an installed local store/schema,
  native checkpoint uniqueness and checkpoint-first authenticated recovery before
  selecting new destination/identity/key/predecessor inputs.
- Exact-C chain coordination and current-v1 same-C partition continuation;
  original request/receipt and attempt/terminal behavior survive retries/restart.
- Inclusive8192-byte canonical whole-frame admission, with exact closed structure
  and authenticated association; other envelopes/artifacts retain separate bounds.
- The independently reviewed [provider protocol](../../spec/128-audit-anchor-provider-protocol.md):
  exact conditional request/result, separate primary/TLS/privileges and normal
  COMMIT followed by the same-session saved-LSN barrier before exposing decisions.
  [D178](../../decisions/178-audit-anchor-provider-protocol.md) records adoption;
  actual SQL/adapter implementation and native preconditions remain unqualified.

## Required issuance and Acceptance

- [ ] Complete exact file/API and local fourteen-operation matrix; no omitted row.
- [ ] SQL/schema/migration, exact joins/uniqueness, chain lock ordering/isolation,
  root ownership and ambiguity/fresh-root recovery contracts.
- [ ] Real current/historical trust and finite reservation/revision/receipt
  provenance dependencies, with atomic genesis and crash-safe immutable inputs.
- [ ] Actual external provider request/lookup/conditional-write and commit/flush
  ordering, preserving every original A5 no-N+1/reconciliation/attempt rule.
- [ ] Exact parser/canonical frame, history streaming and resource contracts;
  byte-bound arithmetic is not parser/native proof.
- [ ] F3 accepted dependency and real authenticated association; no fake signer,
  unsigned row flag or hypothetical port used as final Acceptance evidence.
- [ ] Assigned native concurrent/visibility/crash/lease/history/tamper/receipt/
  external cases, static/architecture/package/full gates and independent Review.
- [ ] Root Acceptance, isolated commit, clean committed export and remote proof.
  All parent same-SHA/release/Documentation delivery gates remain additional.

## Current Verification Plan

This issuance preparation changes management only. Root checks targeted current
authority against independent review729a27c6, the SF-01 qualification, exact links,
checkout-specific index preservation, scope/diff and STATE via state.mjs.
For provider promotion, compare specification128 with reviewed proposalf00ed501
and r1/r2 findings, preserving all native/release boundaries. Check current inputs,
exact cross-links and separate seven-path management ownership before commit.
No PHP/Node Product test, DB, browser, CI, export or deploy is selected for this
management edit. The complete executable Product/native verification plan and
resource owners must be frozen before Product issuance, not inferred from this list.

## Release Documentation Impact

Stable Experimental1.2.1 and unreleased1.3.0 retain release-authority.json's tuple;
no Capability ID, public Source/route, API, command, configuration, old-version
classification or generated Search/LLM input changes. The Report records the
actual authority read and retained-source evidence. Full public-source/artifact
review, final local Owner confirmation and same-SHA delivery remain parent005/006.

## Remaining Issues and Next Action

Freeze the remaining complete issuance contract and real F5/provider dependency
order after F3 Acceptance; obtain independent contract review before assigning
Product files. The whole F4 goal above remains mandatory.
[Report](../reports/P23-003F4-audit-anchor-durable-external.md).

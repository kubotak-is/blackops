# D178: Audit Anchor Provider Protocol

Status: Decided — reviewed provider contract adopted; implementation/native proof pending

## Context

An external anchor result must retain the original request, occupied version,
complete checkpoint bytes and provider acceptance time across retry and recovery.
SQL visibility or a session default alone does not establish the durable decision
required by A5. The external provider also has distinct privilege, installation
identity, physical-session and release-authorization boundaries.

## Decision

Root adopts [specification128](../spec/128-audit-anchor-provider-protocol.md) on
2026-10-12 after provider review r1 and its bounded r2 correction. Select a separately
administered PostgreSQL18 primary with verified TLS/pinned installation identity,
two exact granted functions,15/16 fixed inputs and one flat21-column result.
Exact-C conditional writes retain the intended N and all original association;
no conflict/recovery path allocates N+1 or treats an unavailable result as absence.

Buffer every A5-consumed result. Capture one insert LSN after its read, perform
the owned normal standalone COMMIT, then verify the saved-position flush barrier
on the same physical primary session before exposing the result. This applies to
found, explicit absence and permanent create rejection. The separate post-COMMIT
deadline, timeout/probe limits and session-disposal rules remain exact. No function
or pre-COMMIT path waits for WAL caused by its own still-open transaction.

This is the reviewed conditional durability argument under honest primary/logged
storage assumptions. It neither proves hardware behavior nor qualifies an actual
provider. Native async matching/conflicting-row visibility, idle replay/own-WAL
ordering and changed-session/timeout cases are required with their real
preconditions. No test double or unobserved precondition qualifies those cases.

The adapter's held create bytes require the real F5 current authorization/trust/
Required-release gate before upload. The provider primitive remains unregistered
until that production composition is accepted. SQL bytea validation does not
claim Unicode, JCS, Ed25519 or current-trust verification.

## Review evidence and limits

Reviewed proposal f00ed501d684419a359ac7ccf4d2cce9473022287477b84377134e6e14b895c9
is retained in E3/f4-provider-exact-proposal.md. E3 is
`.phpunit.cache/f3-orchestration/20261009`.
Review r1 SHA3ab73b776e7bbc0cdcedf728f6e44ff76806803030c43a590c1210e140276547
requested one ordering correction. Review r2 SHAd8441b4a4a529779a73f2d3dfe08679e28d24086565547bfaa0da861d7ae6dfa
closes F4-EP-01 and is Green for that correction; its input receipte9cb4f7217584c19f4ac192cb5da1025d6c2383f1c0b40bde0ecd6a0b4ab1497
reuses the unchanged r1 protocol/identity/privilege evidence.

Root's permanent promotion changes authority/status/cross-links and future-work
wording, preserving the reviewed protocol and qualification conditions. Since r2,
F3 specification126 added its bounded internal responsibility decomposition;
its provider-relevant choices, A5 semantics, admission and failure sections are
unchanged. The adoption Report records this comparison rather than claiming the
whole specification hash is unchanged.

## Remaining work

[Specification127](../spec/127-audit-anchor-durable-external.md) and the
[F4 Task](../orchestration/tasks/P23-003F4-audit-anchor-durable-external.md) still
require complete local14/API/SQL/lock/history/parser resources, actual F5 authority/
provenance, frozen Product ownership, native tests and independent Acceptance.
F3 remains active and must be accepted before F4 consumes it. This adoption adds
no public API/capability, runtime installation or change to Stable1.2.1 authority.
F4/F5/F6, D-control/G and every parent1.3 release/delivery obligation remain.

# D162: HTTP Authentication Result Audit Boundary

Status: Decided — Product progress in D3 Report; BlackOps 1.3 unreleased

## Context

D154 selects a bounded Authentication result adapter before parent composition.
The existing AuthenticationMiddleware already distinguishes explicit success,
Invalid, and credential absence, and returns Invalid401 before Operation identity
allocation. AuthenticationResult carries Actor/Tenant and safe code, but no typed
provider provenance or trusted HTTP execution ID.

The existing Session authenticator may update last_used_at in its own transaction
before its identity provider returns. Generated login/register/logout Operations
are Application sources with separate credential/session mutations. Combining all
of those with a result adapter would require new transaction and authoring
contracts that are not necessary to audit the current HttpAuthenticator result.

## Decision

[Specification113](../spec/113-http-authentication-audit-adapter.md) is the
most-specific D3 authority. Three latent Internal types decorate the existing
HttpAuthenticator seam and build/deliver the two Accepted Catalog definitions.
The existing middleware remains the HTTP boundary. No public signature or current
runtime registration changes, and anonymous absence emits nothing.
The new namespace is Internal\\Audit\\Authentication, within the existing Internal
layer that can depend on HTTP and Audit delivery. It does not widen InternalAuth's
restricted dependency rules or add a Deptrac allowance.

Clarify D154's conditional mutation rule for this source: the D3 event is the
explicit credential-check result observed after the provider returns. Its own
source has no mutation. A provider's earlier Session touch is outside the result
event's atomic claim and is not undone on Audit outage. D3 does not emit a Session
mutation event, assert login/logout completion, or change D154's same-root rule
for adapters that actually describe mutations. Two-Connection fixtures must show
this boundary, including an already-committed Session touch when Audit fails.

A new server UUIDv7 identifies each authentication evaluation. Action and Resource
use the existing purpose-separated HMAC derivations; no credential or request value
feeds them. The writer owns one Clock capture and a non-null per-evaluation durable
idempotency key. Accepted Stores own their independent record identities and root.
No provider provenance is recorded because the current typed result supplies none.

Successful authentication is withheld until local Audit Outbox commit. Invalid
remains the existing401 when Audit append fails and returns idle. A leaked active
transaction still triggers the outer Application lifecycle's close and safe500;
D3 does not override that existing recovery guard. Both are denial. This preserves the parent
authentication denial rule; D2's stronger terminal-error rule applies only to
Operation reads and is not copied here. Anonymous produces no Audit work.

Inside the new decorator, unexpected provider errors still mean server failure,
never Invalid credentials, but are reconstructed as a fresh bounded exception.
This deliberately removes inherited frames that can carry raw requests/tokens.
The undecorated public middleware's throwable identity behavior remains unchanged.

## Rejected Alternatives and Consequences

- An after-handler middleware would observe business output and could disclose
  data before Audit commit. A decorator gates the already-typed auth result first.
- Auditing anonymous login Operation success would infer application semantics
  from a handler result, contrary to the D3 boundary and application authoring lane.
- Reusing Operation IDs or request headers would invent a missing trusted scope.
- Adding provider fields to the immutable Catalog would invalidate accepted bytes.
- Propagating original provider failures through the new adapter would retain raw
  credential frames. Fresh errors preserve server failure without those frames.

The adapter is local, Internal and unactivated. Parent D integration still owns
first canonical caller, exact same-Connection wiring, configuration/providers and
public documentation. Stable1.2.1 and the unreleased1.3 boundary remain unchanged.

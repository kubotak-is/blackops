# D163: Authorization Audit Evaluation Foundation

Status: Accepted — D4A local; D4B pending; BlackOps1.3 unreleased

## Decision

Split D154's D4 into serial D4A typed evaluation and D4B event delivery/same-root
adapter. D4 is not Accepted when only A is complete. D4B needs its own reviewed
most-specific authority and exact Task, and runtime activation still belongs to
ordinary D-integration. [Specification114](../spec/114-authorization-audit-evaluation-foundation.md)
is the exact D4A contract. This Decision authorizes no partial runtime activation.

The current evaluator returns null for both no-policy and allow, while current
Authorize/OperationMetadata carry only the configured policy class. A class name
cannot supply a policy ID/version or decide whether Audit delivery is privileged.
Four latent Internal types bind canonical metadata to explicit versioned policy
configuration and retain no-policy/allow/deny distinctly. Reuse AuditRecordPolicy
and RejectionReason; do not add a second public policy API or provider interface.

An immutable exact binding registry is preferable to an inferred/default version
or payload-selected delivery. Binding is checked before evaluation, even when an
Actor is missing. No-policy remains event-free and performs no policy resolution.
The registry cannot establish the trust of arbitrary caller-supplied metadata;
future composition must use the canonical compiled metadata and explicit policy
configuration. Backend outages remain failures, never denials. Fresh exceptions
protect this new wrapper while the old evaluator remains unchanged.

Immediate persistence inside an evaluator decorator is insufficient: Inline and
Worker evaluate before the business root, while Deferred acceptance evaluates
inside its acceptance root. The existing mutation coordinator runs its completion
callback only for completed results and cannot persist a denial through it.
Separating typed evaluation from delivery avoids moving policy evaluation,
evaluating keyed calls twice, or claiming atomicity after commit. D4A emits nothing;
D4B must explicitly settle the remaining transaction/failure matrix in spec114.

Correct spec06's general Deferred ordering sentence to current acceptance code:
evaluate before normal Received/Accepted/enqueue, with Received/Rejected on deny.
This is a source-backed specification correction, not a behavior change.

## Preserved Boundaries

D147 exact30 Catalog, public auth contracts, existing Runtime/composition, SQL,
dependencies, PHPUnit512M and release authority stay unchanged. Stable1.2.1 stays
current and1.3 is unreleased. No commit/push/tag/CI/export/deploy is selected.
The local Task/Report own acceptance evidence; D4A alone does not complete D4,
parent D, the1.3 goal or documentation delivery.

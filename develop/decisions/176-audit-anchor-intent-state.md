# D176: Audit AnchorIntent State Boundary

Status: Decided — pure F3 contract adopted; implementation Acceptance pending

## Context

Accepted F1 supplies signed-artifact mathematics and F2 supplies immutable
checkpoint persistence. The complete current AnchorIntent A5 state machine is
still required for1.3. Its two post-call active-token purposes cannot be recovered
from its38-field snapshot alone, and its prepared wire deliberately leaves the
segment/previous-receipt fields null. Adding fields or inferring origin from those
nulls would change the current contract.

## Decision

[Specification126](../spec/126-audit-anchor-intent-state.md) separates the exact
pure A5 intent from immutable prepared-chain/receipt-ID binding and actual claim
origin. Named claims derive the origin; transitions preserve or clear it together
with the lease. The future trusted storage caller authenticates this complete
association on restore. Shape/hash checks in F3 are explicitly insufficient to
authenticate history. Receipt ID is selected once before genesis, without adding
it prematurely to the A5 wire or regenerating it on retry.

This is the next mandatory F prerequisite, not a reduced release scope. F4 must
prove durable CAS, authenticated recovery and real external effects; F5 must
complete signing-release/control provenance/current-trust/seal; F6 must close
the complete reusable verifier. F -> D-control -> G and all release gates remain.
An internal primitive can be tested before deployment/key policy is activated,
provided those later responsibilities remain explicit and no caller is enabled.

## Review and adoption

Root adopts the exact APIs/files/proofs on2026-10-09 after independent authority
review r1 and bounded r2. AR1-01 is closed; r2 is Green/Ready with no remaining
authority finding. Review hashes and boundaries are retained in the F3 Report.
No existing F1/F2 acceptance, public artifact bytes or Stable authority is changed.
Product implementation, independent Review and Acceptance remain separate work.

## Bounded decomposition amendment — 2026-10-11

Native diagnostics exposed duplicated scalar/receipt/immutability validation over
the unchanged complexity budget. Root's one-scalar draft would still leave the
corrected ExternalOutcome over budget. Independent review5552a990 recommends the
exact Scalar and ReconciliationShape pair, plus existing-owner moves, as the
smallest measured resolution. Root adopts that resolution in specification126 §10:
23 Product types and41 owned paths, split38 main/three guard files. No field-group
or origin-association helper, threshold change or new public surface is adopted.

The correction uses direct strict outcome admission for native type refinement.
Public receipt-hash validation owns fresh finite remapping because its caller is
outside normalize's catch. Both are required behavior, not merely style changes.
The earlier Root draft's DC-01..03 are resolved in this amended contract; actual
implementation and native evidence must still close them. The review was Changes
Required on the old draft and is not reported as a Green implementation review.
Complete A5, privacy, authenticated-restore limits and later F4/F5/F6 obligations
remain unchanged. The F3 Task/Report retain exact ownership, evidence and gates.

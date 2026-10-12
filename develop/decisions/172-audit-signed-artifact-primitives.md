# D172: Audit Signed Artifact Primitives

Status: Decided — bounded implementation Accepted (local); BlackOps1.3 unreleased

## Decision

Adopt the complete source contract's three choices: raw32-byte Ed25519 seed
only; complete canonical JCS without LF capped at1048575 bytes, reserving the
final exported LF within1MiB; externally trusted snapshot and trusted-time input
as preconditions. No alternative secret format, invented expectedTrustHash wire,
key provider, signing permission or new dependency is introduced.

Keep seven Internal boundary types: five stateless static services and the
finite failure enum/exception, plus the twelve bounded correction helpers below. Reuse existing JCS/string/time/native sodium helpers. Bound
complete flat input before recursive JCS decode; private seed,
keypair and secret key never enter JCS, while public material remains in the
normative trust snapshot hash. Fixed role/domain, material alias rejection,
current/revoked/time rules and explicit failure order are unchanged parent wires.

Pure mathematical verification does not authenticate snapshot provenance or
prove persisted sealing, authorization, external anchor, global graph or freshness.
Those remain later F/D-control/G responsibilities. This split follows the accepted
acyclic graph and does not require ordinary D/E implementation first.

The [specification](../spec/122-audit-signed-artifact-primitives.md) owns the exact contract;
the [Task](../orchestration/tasks/P23-003F1-audit-signed-artifact-primitives.md) owns file/resources/gates.
The O2/F1 pair explicitly adopts disjoint source scopes and serialized package
script ownership with one common final frozen full. This shares identical-input
qualification, not semantics, and waives no child-specific proof. O1 keeps its
separate already-required native full. Parent006 retains final clean same-SHA
CI/export and immutable publication. Stable1.2.1 remains current.

## Bounded correction after initial feedback

The initial seven-type implementation failed unchanged class metrics and static
analysis and had semantic/proof defects. Keep the original seven boundary
surfaces and add only twelve finite stateless PSR-4 helpers (nineteen types and
29 paths total). Spec122 owns exact callable/shape/privacy responsibilities.
No dynamic validation framework, new dependency, metric waiver, suppression or
optional further helper is approved. Root measures the unchanged gates.

Complete decodes revalidate unsigned shape AND all local relations. The receipt
binding uses exactly its unsigned fields. Trust validation caps raw/scalar input
before traversal/decode, returns detached eight-field entries, compares derived
digests with hash_equals, and independently rejects non-current entries from
standalone assertSignedAt as well as key lookup. A shared private assertCurrent
may implement that exact obligation; it never assumes prior lookup success.
Valid unused revoked/expired/future entries remain allowed in whole snapshots.

O2 is the sole editor of the existing A2 guard for five exact timestamp call
owners across four helpers; no Artifact directory exemption. Both scopes expand
explicitly, retain initial payloads/failed receipts and every predecessor test,
and serialize helper package extensions from the immutable current125/77 endpoint.
Independent corrected authority precedes assignment; initial source findings,
real B1 lineage/chronology, mathematical Node proof and privacy/mutations remain
required before local Acceptance. This does not advance Release Authority.


## Adopted later-caller authentication refinement — 2026-10-12

[D179](179-audit-signing-authority-and-production-provenance.md) and [129](../spec/129-audit-online-authority-and-seed-custody.md) separate
online F acquisition from G's complete manifest-bound VerificationContext.
The online route uses an independently installed root,whole signed payload,
current phase acquisition,durable floor and fixed seed custody. Export/hosted/
offline verification still requires the complete external context pin and manifest.
No F1 API,artifact wire,crypto role or mathematical validation changes. This
refinement is operative under adopted D179; Product callers remain unissued.

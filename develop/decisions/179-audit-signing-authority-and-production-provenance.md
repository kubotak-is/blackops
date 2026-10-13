# D179: Audit Signing Authority and Production Provenance

Status: Decided — authority direction adopted; exact Product implementation remains unissued

## Context

Accepted F1 verifies fixed artifact mathematics,F2 durably prepares/persists
checkpoints,and F3 implements pure A5. None authenticates the actual origin of
the snapshots or saved work. F4 requires real checkpoint reservation and persisted
outcome before atomic anchor genesis. Its provider and frame choices are already
adopted by specifications127/128 and D177/D178.

## Decision

Promote the independently reviewed Online,Control,Ordinary and Anchor directions
to specifications129–132 without adding a generic signing service or changing
the F1/A5/G public wires:

- Online acquisition uses an independently installed verification key,a signed
  whole payload,a maximum300-second lease,a durable external floor and fixed raw32
  F1 seed custody. It is a distinct accepted-context route from manifest-bound G.
- A root policy grants one installed provenance delegate exactly nine finite
  permissions. Root/delegate history,live compromise restrictions,first-use and
  pending-publication state,stopped rotation and indefinite bounded-memory history
  lookup remain required from the initial implementation.
- Signed ordinary/control reservations,candidates and persisted outcomes establish
  origin; actual F2 data alone does not. The two construction entries are distinct.
  Ordinary scheduler identity grants preparation before the real exact-target
  Operation/policy decision; actual Required delivery gates every byte handoff.
- Atomic anchor reservation/genesis/head and authenticated named revisions bind
  the complete frame and original claim history. Receipt reservations bind the
  already selected receipt ID and anchor reservation,with no new A5 members.

The shared prerequisite must supply real authority and attributed checkpoint
production before F4 native qualification. Then complete F4 local14 and provider
integration,finish F5 ordinary/control release and current-trust seal,and F6.
F -> D-control -> G,final Owner preview and parent006 release gates remain.
No child codec or fixture-only signer can stand in for that complete prerequisite.

## Boundaries and consequences

This amendment explicitly separates online authentication from G's required
complete VerificationContext/expectedManifest. F1 remains pure and cannot accept
its own key/source as trust. F2 APIs,Clock/UUID ownership and immutable replay
remain unchanged; its future admission projection/callers require an exact packet.

Trusted filesystem custody and retained PostgreSQL history are installation
preconditions. The contract detects the specified tampering,rollback floors and
corruption; it does not prove resistance to coordinated restoration of all trusted
files,DB,configuration and Clock. Such recovery remains stopped and independently
established. No instantaneous global revocation or external per-revision witness
is claimed. The required current phase checks and full history proof are retained.

The public signing/control/ordinary configuration is an unreleased1.3 change.
Implementation requires guide/internal documentation,source/artifact fixtures
and final review. This management promotion adds no Stable capability or runtime.
Exact Product files/APIs/SQL/resource ownership and qualification remain unissued.


## Adoption evidence — 2026-10-12T19:15:49+09:00

Root adopts this direction after independent review
`694c79b50189e8bc5776c43228b40836faf333f3acc5446917d1c605e79533e5` (input record
`3575b55cfb337589bf857325c9f56509dc2a83eaf78f9dfbd340d1d232be206c`), Green with no blocking
finding. The reviewed29-file candidate is82ffe51b and its bounded existing-authority
diff isab0d7a11. Only adoption/status/bookkeeping text changes after that review.
F5 AC1 closes; AC2–6 remain open. In particular, SQL history capture and current
historical-issuer revalidation must be concretely joined without SQL under the
control-floor lock, unbounded memory or a lifetime history limit before issuance.
No concrete capture mechanism is adopted here. Product/native qualification,
ordinary/control composition, F4/F6 and parent release gates remain mandatory.

## Concrete composition refinement — 2026-10-13

The actual C3 store closes a failed connection but does not make the whole cached
F graph permanently unusable. Root adopts a shared monotone state at the F
composition boundary. Both actual requested/unavailable delivery call edges
invalidate it on escaping Throwable before propagating the original finite
failure; no later delivery/provider/selection can run on that graph. Healthy
committed denial/pending is separate. Recovery requires a fresh full graph.

Bounded independent review24c15adb50c2d665549f50ddf9ae371b207a1d0162b3f7f0a429dfda68c8bd4c
closes CS-01 against correctionf8ec05b7;19 selected inputs remained unchanged.
The earlier composition review504e0f9d found no additional DDL/selector/lazy
blocker. Root also clarifies131's exact composer call to the finite same-graph
checkpoint seam and keeps ordinary compose and retention plan/purge isolation.
These are explicit authority refinements,not implemented behavior or native
Acceptance. The full exact packet and its tests remain required before Luna.

## Supported native custody refinement — 2026-10-13

Actual PHP8.5.7 calibration showed that public fclose can hide native close/fclose
errors and invalidate its PHP resource even when a deliberately replaced close
retains the descriptor. An all-native-close-error detection promise cannot be
implemented by the admitted core APIs. Root adopts the explicit supported
Linux/glibc, exclusively owned descriptor contract in129 section2.1 and130 rather
than claiming that PHP reports the hidden status. Checked prior file/directory
durability, currentness and permissions remain mandatory; close is no durability
barrier and is never retried. No helper/extension/process/FFI is added.

Independent correction review79530b88355c2c5a5a2ff8bd644733171e8f103e30e66f1ee8441700ada58d37
closed NC-01 against proposal847ca0bd after the r1 findingfd2d9124. Its20 selected
inputs stayed unchanged. Three deliberately non-consuming descriptor-residual
cases and six consumed-descriptor masked errors remain distinct evidence; none
is relabelled detectable-negative Product PASS. Actual owner/CI-image native
qualification and public installation/support documentation remain required.
This adopts only the reviewed API/platform correction. Full custody/capture/type
issuance and F5 Acceptance remain open; no successful Product runtime is inferred.

## Finite historical checkpoint replay refinement — 2026-10-13

Root adopts the bounded three-codec exception and pre-floor runtime prerequisite
in130 sections9.1–9.3. Original captured bytes must satisfy existing F1 data rules;
duplicating them would create a second parser, while trusting native projections
would not bind the original evidence. The three data-only calls grant no current
trust, artifact signature validity or phase permission. Every live check, one-use
phase and control-floor time ordering remains required.

The existing Normalizer fallback loads Unicode tables lazily. The two actual
assembly entries must therefore resolve the finite codec graph and verify a
nonempty FORM_C normalization before either floor. This grants no general loader,
new dependency or artifact invocation during initialization. Purpose-specific
native holders retain all four runtime representation controls, explicit disposal,
file/directory durability and physical EOF within the existing method budgets.

Independent r2 review16e69b9b23fdd890dd42eb847ae0d2be447abbb508bd9d63e88f35c9052c13d2
and inputse6e361b8c30e559243d5f43f01a9a3af54a028bfc45d09660edd3e77dfdee632
are Green against candidatea2dbcfec; all53 review inputs stayed unchanged. It
explicitly reconciles Preflight, the fifth replay dependency, descriptor surfaces,
and the installation composer's exact Composition return. The separate installation
protocol and final crypto/custody imports still require their own combined review.
This is a complete bounded specification unit, not Product/native Acceptance.

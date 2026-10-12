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

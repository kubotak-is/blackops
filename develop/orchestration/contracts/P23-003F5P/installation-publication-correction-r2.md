# Installation issuance: exact review corrections

Status: Root's bounded correction for the combined implementation packet;
Product remains unissued. Frozen r1 and its review stay byte-for-byte unchanged.

Basis: installation-publication-issuance-r1.md65c2e485 and independent review
b593fb5adacb6550bc49445d64b82b241a49f18eb182eac7e4c2707191149fcf,
inputsf05c87e57f3a28a278580637c6f10fb4d09f8fb132296e8d4380b3ee804cab7e.
The reviewer and original author agreed these exact corrections; no protocol
redesign or public API change is proposed.

## IPR-01: one PublicationCandidate native caller

Add this path to r1's candidateCallerOrEndpointDeltaPaths and its additive
implementation/caller inventory:

```text
src/Internal/Audit/Control/AuditControlPublicationCandidate.php
```

It is already a proposed type in the frozen history map, not an implemented C
source or a newly added type in this correction. Add exactly one literal call:

```text
AuditControlPendingPublication::fromInstallation
  -> AuditControlPublicationCandidate::native()     count1
```

The factory reads only revision, document_sha256 and policy_digest from the
existing exact Publication12 result to compare the authenticated candidate's
selection binding. Retain the Candidate's9 methods, original factory origin and
private representation controls. No new accessor or direct documentBytes/schemaName
caller, candidate construction, signing, current permission or SQL projection
is granted. All other Candidate getter callers stay fixed.

AuditProvenanceArchitectureTest and AuditControlInstallationArchitectureTest pin
this exact owner/method/count and reject an alternate caller or getter, extra call,
dynamic/aliased bypass and a substituted field. The same phase14 locked replay
still binds the original requested digest to the authenticated signed policy
before any publication write; this data read does not replace that proof.

## IPR-02: two literal inventory corrections

CommandProjectionTargetRegistry declares9 methods: framework, defaults, all,
frameworkNames, target, description, routeTarget, scheduleTarget and makeTarget.
The new command changes one registry row, not its methods. Replace r1's10 count
with9 in both prose and the combined machine inventory. The global10-method
ceiling is unchanged; no artificial helper is added to reach it.

The actual proposed History owner method is:

```text
AuditControlHistoryReplay::replay(
  AuditCapturedControlHistory,
  AuditAuthenticatedControlHead
): AuditVerifiedControlEndpoints
```

The phase14 original-requested-digest comparison belongs inside that existing
replay method. Replace only r1's verify spelling for this owner; add or rename no
method. Subordinate semantic replay owners keep their own verify methods.

## Review and execution boundary

Root checked these changes directly against the evidence-backed minimum fixes
in the independent review. The complete assembled packet must include the same
literal corrections in its file/type/call manifest and undergo its required
combined review. No Product, authority, tests, migration, native or CI execution
is implied; no existing test success is relabelled for future code.

# Complete checkpoint prerequisite — independent authority review r1

Status: **Changes Required**. Two concrete online/delegate import joins remain unclosed. The independently collated literal inventory also needs Root's finite completion. The reviewed whole-trust, floor, custody and phase7 protocol directions remain compatible with the adopted authority; this does not approve an incomplete worker packet or Product implementation.

Reviewer: existing `/root/f3_authority_review`, assigned Orchestrator Astra/max. Read-only review under the current F5 Task's Complete prerequisite review assignment. P is `/home/kubotak/projects/blackops`; C is `P/.phpunit.cache/f2-orchestration/accepted-integration`; E13 is `P/.phpunit.cache/f5-orchestration/20261013`. Root-reported C base `ba026679c258a48414e5dd39d3551d9c1913cb51` was not queried through Git. Only this receipt and its companion selected-input JSON are written.

## Reviewed input and precedence

The combined candidate `checkpoint-combined-issuance-candidate-r1.md` is exactly `cbf6641e46cdf5da4f5e42b764993305ca07e9cd53567313ceefc5b1fde6d6fb`; its input record is `b9bc799d843fd87e4b6b8eefb741c12923c6c3c61c840281db696d3c52e32aca`. The new online/delegate map is `4b3d173075ccc83bd462dc09e60a65954c5c40cc1fa6be06acb7d57dddff1c4b`, companion `aabefa2aa1f429e33cb376c79ab2377dbffac5e56cda220a8eee43791ef7abf5`.

Applied only the combined packet's nine named maps and explicit overrides: checkpoint composition; history; pure r1/r2; installation r1 and Root r2 correction; online/delegate; verification resource packet; test/CI binder. There is no generic latest-text or namespace permission. The irreversible migration `down` correction governs the resource draft; no Product migration or native-resource action was performed.

Reused unchanged independent receipts: SQL `64f9b74f`, filesystem/history r2 `75cd36c1`, CS-01 r2 `24c15adb`, native custody adoption `f97994ce`, pure r2 `16e69b9b` and pure adoption r2 `eddff5b3`. Installation review `b593fb5a` is read with its exact Root correction `6263747d`: the missing Candidate.native edge, Registry count and replay name are explicitly supplied. The selected installation composition return remains `ApplicationAuditControlInstallationComposition`.

## CIR-01 — Blocking: online constructor and settings joins contradict the exact caller table

Evidence: online map lines43,175–184,255–265; companion `candidateTypes` entries for `AuditOnlineConfiguration`, `AuditOnlineAuthoritySettings`, `AuditOnlineDocument`, `AuditOnlineFloorCodec`, `AuditOnlineAcquisition` and `AuditSigningFiles`.

The concrete conflicts are:

- `Signing/AuditOnlineDocument::__construct(OnlineConfiguration,DocumentCodec,DocumentVerifier)` has “Only OnlineAcquisition constructs/calls” in its exact table. Section9 instead constructs it in `ApplicationRetentionRuntime::signingTasks` and passes it into Acquisition. Those cannot both be the constructor allowlist.
- `Signing/AuditOnlineAcquisition::__construct(OnlineConfiguration,ClockInterface,SigningFiles,OnlineDocument,OnlinePayload)` has exactly five arguments. It must create `AuditOnlineFloorCodec(OnlineConfiguration,AuditAuthorityJsonPreflight)` from the common concrete Preflight, but none of those five declared inputs exposes that Preflight and no such accessor/construction edge is assigned. The separate shared Preflight constructed by signingTasks does not become available merely by being in the same graph.
- Configuration's caller row assigns `settings()` to OnlineAcquisition, while Document, Payload and FloorCodec require Settings.identity, FloorCodec requires minimumEpoch, and SigningFiles requires Settings.paths for its source/floor openers. Their declared constructors receive Configuration, not Settings. The exact Configuration-to-Settings reads are therefore incomplete. This is not permission to move dependencies into the scalar settings record.

Required correction: choose and record the actual Document constructor owner, a concrete pre-floor Preflight-to-FloorCodec construction route within the fixed argument/method ceilings, and the literal Configuration.settings/Settings accessor readers used by those leaves. Update both prose and companion/guard caller inventory. Keep actual operations Clock, one shared graph, pure bounded Preflight, fixed source/floor openers and the existing initialize order. Do not use a service lookup, generic dependency bag, public resource accessor or broad namespace allowance to bridge the missing edge. Normalize the section8.2 `SignatureFiles` spelling to the concrete `AuditSigningFiles` identity in that same finite map correction.

Closure evidence should include matching constructor/caller inventories and source guards proving the fixed construction and initialization occur before either floor. Cold/native execution remains future implementation qualification.

## CIR-02 — Blocking: native observations lack the declared byte-reading and endpoint-identity handoff

Evidence: online map lines99–113,133–137,194–198,223–239; companion exact NativeObservation/Grant surfaces. Affected proposed paths are:

- `src/Internal/Audit/Provenance/AuditCheckpointNativeObservation.php`
- `src/Internal/Audit/Provenance/AuditControlNativeObservation.php`
- `src/Internal/Audit/Authority/History/AuditVerifiedCheckpointEndpoints.php`
- `src/Internal/Audit/Authority/History/AuditVerifiedControlEndpoints.php`
- `src/Internal/Audit/Authority/AuditControlPhaseGrant.php`

Both observations retain only Connection, the actual transaction owner and their VerifiedEndpoints object. Nevertheless the Checkpoint endpoint's `comparison()` readers are limited to MutationAdmission/history replay/Grant.fromAcquisition (line105), and the Control endpoint's readers to Grant.fromAcquisition/PublicationWriter.recheck (line237). Neither NativeObservation can legally read the retained comparison. No copied native-row/candidate argument or alternative getter exists. Thus `candidateFor(PreparedCheckpointWork,PhaseAuthority)` cannot obtain the promised actual ordinal2 candidate bytes, and `factsFor(Grant)` has no assigned route to its actual bounded native row/proof group.

The identity join also needs a closed declaration. Grant keeps its original exact endpoint object private; `facts()` explicitly contains scope/policy/delegate/now/authenticated checkpoint choice, excluding that endpoint identity. `claim(NativeObservation)` promises an exact original-endpoint comparison, but `factsFor` is described only as “exact native endpoint facts.” The packet has not fixed the concrete endpoint identity in that result and the actual comparison against Grant's retained object. A matching scope, hash, recreated equal object or caller origin is not the required same-object check. For phase7 `candidateFor`, the same-authority/target/endpoint relation likewise needs an explicit permitted path, without consuming the main Grant.

Required correction: freeze only the necessary named bounded comparison readers and closed result/identity relation for these existing types. The observation must read the actual retained post-comparison bytes, check the same currently owned root, and supply the identity which Grant checks against its private original endpoint, with exact scope and consumed/one-use state. Bind the candidate observation to the same phase7 authority/preparation and real nullable candidate. Update exact signatures/results/caller edges and tests together; merely asserting “native proof” or widening a namespace is insufficient.

Preserve checked history closure before observation creation; no live SQL/capture handle or unbounded history list in a result; pre-seed comparison root commits before seed access; main authority remains unconsumed during its one comparison permit; final recorder consumes once and performs its separate real native reread. Keep actual full candidate/F2 bytes, no caller presence boolean, stale projection or digest-only substitute.

Discriminating proof obligations already required by this correction are: actual ordinal2 absent/present candidate with original bytes; a distinct equal-valued endpoint object and a different scope/authority rejected; a consumed observation/grant/permit rejected on reuse; wrong Connection/ended or disposed root rejected; closure/commit failure yields neither usable observation nor seed access; and drift between the phase7 roots blocks the final append. Grant.claim must check consumed state and exact endpoint identity through the declared route, not infer either from a private constructor.

## Preserved protocol conclusions

The new map supplies a real production route rather than a codec-only result: installed ordinary identity and actual metadata/evaluator/requested/handler admission; current control acquisition and attributed reservation; unchanged F2 preparation; real F1 signature or authenticated recovery of original candidate bytes; unchanged F2 persist; then an actual signed ordinal3 outcome. The missing later continuation remains a held unavailable result.

Whole online source authentication precedes its floor. Actual operations Clock is sampled once under the floor, complete trust/scope is validated before target eligibility, equal epoch binds exact document hash, time rollback is rejected, and a usable result follows required durability/unlock/close. Current online validation is separate from the adopted three pure control-floor codec entries. No G-manifest or fabricated publisher-time trust check is introduced. Accepted `AuditTrustEntryRelation`'s expired-entry condition depends on actual verifiedAt, so the proposed unused-expired-entry case discriminates the correct Clock join.

The five new F1 direct sites have real accepted APIs: Payload.snapshots -> TrustSnapshotCodec.validate; Checks.key -> key and assertSignedAt; Checks.complete -> ArtifactVerifier.checkpoint; CandidateSigner.signPrepared -> ArtifactSigner.checkpoint. Accepted Signer.checkpoint lines13–33 and Signer.sign lines70–102 retain current key/role/time checks, derived-public-key equality and secret/keypair cleanup; Verifier.checkpoint lines10–27 retains actual complete-byte signature and signedAt checks. No additional anchor signer, arbitrary sign(payload), duplicate F1 codec or application trust input is approved.

Phase7's selected order is coherent: one same-authority comparison permit, actual fenced reread and checked history close, commit before any seed, fresh current online check, original-candidate recovery without signing or exact mapped seed/F1 signing, same-authority SignedCandidate, then one main consume and a separate final native reread. CIR-02 is the concrete data/caller join needed to implement that order, not a reason to replace it.

Delegate signing remains behind the actual consumed current Grant and fixed root observation, fixed purpose frames, exact permission checks and derived installed/current-key equality. Five purpose-specific custody operations and at most three publication signatures preserve actual row commitment before floor/source publication. There is no producer for ingress_ack/anchor_reservation/anchor_revision in this prerequisite, no F4 independently committing signer and no fake D acknowledgement. Same-graph poison/ambiguity behavior and healthy terminal distinctions remain the adopted CS-01 contract.

The two replay initialize sites and single additional online initialize site are distinct. Nonempty Normalizer warmup, purpose-split native holders, positive partial-write completion, checked observable failure handling, exclusive descriptor ownership, and consuming-close support limitations remain the adopted pure/native contract. No native result is inferred from this source review.

## Separately collated issuance closure

Received the frozen mechanical inventory from `/root/f4_shared_source_inventory`: Markdown `ff20b23aa9705a0b826839deb6d1546f8c0a9c7a24a163d308d6d3b87102f3d5`, JSON `83d0b5d813189f0b5455024202170bc2cfd54f6704b09f1867d20af9c00d11c5`. Its sections1–5 and issue records were read; the collation is reused, not independently repeated or treated as semantic adoption.

It records333 exact referenced paths:271 candidate/reader-impact plus62 references which are not edit grants. Its PI-01 requires the exact35 new PHP paths (28 plus seven disjoint online tests), including literal partition admission/count correction. PI-02 lists23 missing named type entries; PI-03 identifies two History facts whose paths are resolved but exact surfaces are incomplete; PI-04 retains the concrete ApplicationAuditConfiguration parser/property/method integration gap. PI-05 is a documentation scope boundary, not permission for an unnamed guide. Root must close those literal inventories without inventing a directory grant. They remain separate from CIR-01/02 and from the other reviewer's execution/CI qualifications.

## Evidence and remaining work

The companion records91 before/after selected input hashes. All89 non-STATE inputs were unchanged, including the nine maps, new import, current authority, accepted source support, reused reviews and received union. P/C STATE advanced during this review and is recorded as two identified Root bookkeeping checkpoints, not a frozen semantic input or reviewer edit. The author's earlier P STATE/Task pins already preceded the current assigned-review checkpoint; the actual Task used here remained unchanged. No authority drift is hidden as an all-input stability claim.

Actions were bounded text/source/JSON reads, comparisons and hashing. No Product/test/governance edits, PHP/Node/DB/container/native experiment, network, Git action or subagent was used. No source implementation, branch-cap pass, cold preload pass, runtime/SQL/fault pass, CI/export or Acceptance is claimed.

残り工程: Root's exact CIR-01/02 correction and finite PI inventory/parser/test-list closure; bounded successor reviews including the independent verification lane; durable complete child Task/resource ownership before Luna issuance; then real implementation and every assigned strict/native/full/CI/export gate. Actual F4/Required/D acknowledgement/full F5/1.3 release work remains open.

Next Action: the import author freezes only the corrected constructor/settings/Preflight and observation/Grant identity joins with matching declared callers and proof cases. Root supplies the remaining literal type/parser/test union, then requests a bounded delta review. Product implementation stays unissued until the complete packet is closed.

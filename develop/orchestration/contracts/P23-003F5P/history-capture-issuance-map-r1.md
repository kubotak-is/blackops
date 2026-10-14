# F5 private history capture — exact candidate issuance map r1

Status: source-only recommendation, consolidated with the composition investigator, for Root's assembled-packet review. This file does not issue Product work, alter authority, create a native lease, or prove a runtime result.

Owner: /root/f4_shared_source_inventory. Date: 2026-10-13. C is .phpunit.cache/f2-orchestration/accepted-integration; Root supplied base 35df513e65d687086d47517df9dca6f3b5081457. Git was not invoked. Only this file and history-capture-issuance-map-r1-inputs.json are owned outputs. The companion checkpoint-issuance-map-r1.md is owned by /root/f5_native_contract_plan.

## 1. Inputs, scope and fixed decisions

Use the reviewed filesystem/history protocol r2 cc0314f9aee0ee038f61f75e9237e77dcc35b03951764cde85f8cb902f172127 and its bounded Green correction review 75cd36c1abc9f7996951bd7f4f1ef9206f0b1e1e52d4f165c1f21bb730036a34. Reuse SQL d3dc8e4b, Green SQL review 64f9b74f, selector/lazy f2391720, adopted 129/130 and the existing parser/spool/environment inventories. The companion manifest records exact input bytes. Historical source-inventory recommendations are not promoted merely by citation.

This map selects the following candidate mechanics for the real checkpoint prerequisite:

- Invocation-private anonymous descriptor; no durable transcript, pathname reopening, named history journal, configuration member, generic metadata extension, provider or callback.
- An actual standalone REPEATABLE READ READ ONLY capture root on the same operations Connection as F2/F5, outside both floor locks; complete successful commit before locked authentication.
- One-record streaming framing, bounded per-record parser state, complete required historical issuer proof under the control floor, fresh phase time, then explicit unlock/close and exact fenced native recheck.
- Separate before/after F2 phases. A successful before-prepare acquisition is not the post-prepare work-extension acquisition; the same distinction holds for before-persist versus artifact-persisted outcome.
- Ordinary/control origin remains owned by distinct real constructors and the actual ordinary evaluator/handler path. Inspection grants no production permission.
- No F4 frame, revision, receipt reservation, exact-C lock, receipt production, real D ingress producer, external artifact release or final seal is introduced by these tags or APIs. Read-only verification of already referenced acknowledgement records does not issue their producer.
- No field is added to F2's preparation exact7, complete exact3, F1 wires or F3 frames. Native projection groups below are private transcript records, never new Product storage columns or wire members.

Root explicitly selected the finite pure-F1 exception in section6.1 after this draft began. It is a proposed delta to r2's overbroad no-F1 sentence, requiring bounded independent review/adoption; it is not a reinterpretation of unchanged reviewed r2. All other floor restrictions are preserved.

The numbers and shapes below are a private v1 capture format, not a public interchange or recovery format. The capsule's checksum is not authentication. No individual frame, decoded array, completed trailer, inspected fact, archive signature, or current root-policy file grants production origin by itself.

## 2. Transport, scalar admission and ownership

### 2.1. Exact outer framing

The descriptor starts with seven bytes hex 424f4635480001. Every nonterminal record is uint8 tag, uint32be payload length, exact payload bytes. Only the numeric tags in section 3 exist. All other tags, including unassigned gaps, fail. There is no opaque extension tag.

The last record is tag 255, length exactly 48, payload uint64be recordCount, uint64be precedingByteCount, raw SHA-256 digest32. recordCount excludes magic and trailer; precedingByteCount includes the seven magic bytes and every preceding five-byte record prefix and payload. The streaming SHA-256 covers those same preceding bytes. No terminal record is hashed or counted into itself. Require one trailer followed immediately by physical EOF.

64-bit PHP is mandatory. Counts/offsets are checked nonnegative native integers no greater than PHP_INT_MAX; reject a uint64 with the high sign bit set, overflow before addition, failed seek/tell, negative/float/coerced values, or a value which cannot be represented. Encode/decode the two uint32 halves explicitly in network order; no native-endian packing or floating-point arithmetic. Reaching the native arithmetic/storage limit is unavailable, never successful truncation. There is no configurable total-byte, record-count, page-count, ancestor-distance or execution-time limit.

A reader checks tag legality and its tag-specific length before allocating/reading payload. An ordinary frame payload is nonempty. The dedicated absence/end markers explicitly listed as zero bytes are the only zero-length records. Never interpret an omitted record as absence. A group/envelope pair cannot be split by an unrelated tag.

The writer finalizes, checks fflush and repositions the same descriptor before committing its read transaction; commit/connection-state success is required before handing it to locked replay. A commit failure discards the entire capture and follows the shared graph's ambiguity policy. Before any returned proof, replay verifies the complete trailer/count/hash/EOF and the complete legal grammar. It may do a structural first pass followed by semantic rereads; it never returns an early accepted prefix.

### 2.2. Closed scalar groups

All native/header/endpoint groups use canonical no-LF JSON objects no larger than 16,384 bytes. They contain only their enumerated scalar properties. No list, nested object, arbitrary metadata, float, object/resource, missing/extra member or PHP reference is admitted. Before constructing such bytes, detach each field from its exact native projection, with reference rejection; before decoding, use the dedicated flat group preflight and then existing JCS, exact key set/type validation and exact byte equality.

The notation below is exact:

| Symbol | Admission |
| --- | --- |
| I | Valid UTF-8/NFC/no Cc/Cf, 1..256 UTF-8 bytes; no trimming or coercion. |
| S | Configured schema grammar [a-z_][a-z0-9_]{0,62}; equality to this graph's configured schema. It never selects a Connection or table. |
| P | Exact system:global or tenant: plus 64 lowercase hex. |
| U | Canonical lowercase UUIDv7 with the accepted variant bits. |
| H | Exactly 64 lowercase hexadecimal bytes. |
| N | Native integer 1..9007199254740991. SQL canonical decimal strings are normalized only after checked range/syntax admission. |
| Z | Native integer 0..9007199254740991. |
| T | Exact valid UTC YYYY-MM-DDTHH:mm:ss.ffffffZ, with the existing date/time validation. |
| F | Exact aft:v1: plus 64 lowercase hex. |
| B | Native JSON boolean; never an authorization or production-class input. |
| ? | Actual null or the stated non-null type. Null pairs are constrained below. |

For native SQL input, accept int or exact canonical decimal int-string only where the actual DBAL projection permits it; false, float, exponent, leading plus/zero and numeric coercion fail. Output groups encode checked native JSON integers. F2 closed_state_version remains 2..9007199254740990, exactly its accepted preparation rule, not merely generic N.

Bytea inputs are read with an explicit per-record cap+1 before new F5 normalization. A resource is consumed in at most 16 KiB chunks; do not route new F5 streaming through the unbounded Transport PostgreSqlBytea::string helper. New Checkpoint Admission may bound its actual exact7/exact3 byte value before giving the unchanged accepted Checkpoint value constructor a string; do not weaken or rewrite the accepted helper. Never fetch all byte-bearing history rows or retain page-sized candidate blobs.

### 2.3. Custody and memory

Reuse r2's exact empty tmpfile UID witness, trusted temporary path validation, new private0700 directory, plain x+b capture, verified chmod0600, path/descriptor identity, unlink to nlink0, close/remove private directory before any evidence write, and checked explicit closure. tmpfile is empty witness only. No fchmod, ext-posix requirement, openat promise, hidden fallback or generic filesystem provider.

The anonymous descriptor has no reopen/hydrate/resource-getter API. Its only reader/writer owners are the closed transport classes in section 8. Every native warning is intercepted in its narrow owned boundary, previous error handler restored in finally, and replaced with a fixed finite failure without a previous Throwable or raw document/path/SQL/seed. Destructors only make last-resort cleanup attempts; they do not establish successful operation.

One current transport payload and bounded parser state are retained. A second bounded archive document/current-policy object needed to verify it is a fixed auxiliary bound, not a list of past records. Retain at most four work offsets/digests, the exact finite endpoint summaries, at most two digest cursors for cycle detection and the finite path components (4096-byte path/255-byte components). Re-read records instead of retaining byte strings, keys, policies, issuers, visited digests or publications for the lifetime of a walk. One exact artifact byte string may be re-read for the final F2 equality; no decoded history aggregate survives.

Scratch needs fflush/seek/read checks, but no fsync durability claim. Floors and archive publication retain their required fsync guards. Root exclusively owns measuring native fflush/fclose failure observability; this proposal neither assumes an outcome nor waives those guards.

## 3. Exact tags and payload schemas

All field lists below are closed. Names in snake_case reproduce native SQL labels; camelCase occurs only in already adopted semantic shapes or the explicit private header. JSON order is canonical JCS, not SQL column order. The native row constructor still checks the accepted SQL column order before detachment.

### 3.1. Header and external facts

| Tag | Name / cap | Exact payload |
| --- | --- | --- |
| 1 | Header / 16 KiB | {schemaVersion:1,phase:integer1..15,installationRef:I,schemaName:S,tenantType:I?,tenantId:I?,partitionId:P,segmentId:U?,workId:U?}. Tenant is null/null for system or the exact actual pair deriving P; no half-null pair. Checkpoint phases require segmentId, publication phases bind system:global. workId is null only when no actual reservation is captured. |
| 2 | AcceptedAuthorityBytes / 1 MiB | Exact original audit_control_authority wrapper bytes from the structurally read installed source. It is only a capture hint until current locked acquisition authenticates the same bytes and head. |
| 3 | ExternalAnchor / 16 KiB | {document_sha256:H,policy_version:N,root_policy_digest:H,head_digest:H,revision:N,current_segment_id:U,current_first_sequence:N,current_predecessor_record_hash:H?,closed_segment_tail_digest:H?,policy_state_digest:H,transition_tail_digest:H,acknowledged_transition_digest:H?,transition_ack_digest:H?}. All fields must equal tag2's signed content after verification. The acknowledgement pair is both null or both present. |
| 4 | FloorHint / 4 KiB | Exact original audit_control_floor canonical bytes, with the exact 13-field shape/state matrix in 130 section9. Publication capture only. Read outside a lock as an untrusted selection hint; later compare the expected complete floor under the lock. This record does not establish durability/currentness. |
| 5 | InstalledNextPolicy / 16 KiB | {policy_digest:H,policy_version:N}. Only the stopped/bootstrap/root-revision owner supplies its independently selected archived next policy. Locked verification reads that exact archived signed object; SQL mirrors never supply root trust. This is not a new path/configuration member. |
| 6 | EmptyInstalledSource / zero bytes | Only first-use candidate/publication capture with the independently provisioned zero floor. It records actually empty source bytes; it is never accepted by ordinary acquisition. |

Policy source objects, historical-root records and compromise markers are read from their fixed installed namespaces during locked verification. They are not a caller-provided authority tag. No policy-mirror frame is necessary: SQL policy rows are FK/diagnostic mirrors, while exact authenticated policy bytes come from the installed archive. If the SQL owner reads a mirror for an insert/recheck, it must compare it against those exact externally authenticated policy bytes; the transcript cannot bless a DB-only policy.

### 3.2. B2, work and exact F2 facts

| Tag | Name / cap | Exact payload |
| --- | --- | --- |
| 16 | PartitionNative / 16 KiB | {partition_id:P,tenant_type:I?,tenant_id:I?,active_segment_id:U,last_sequence:Z,head_record_hash:H?,last_assigned_recorded_at:T?,state_version:N}. The complete native8 Q1 projection and accepted genesis/null relations apply. |
| 17 | TargetSegmentNative / 16 KiB | The exact Segment12 shape below, state closed or sealed. |
| 18 | SuccessorSegmentNative / 16 KiB | Segment12, exact actual successor; no synthesized open slot. |
| 19 | PredecessorSegmentNative / 16 KiB | Segment12, state closed or sealed, actual immediate predecessor. |
| 20 | NoPredecessorSegment / zero bytes | Only native absence consistent with target first_sequence=1 and null predecessor hash. |
| 21 | PreviousRecordEndpoint / 16 KiB | {segment_id:U,sequence:N,previous_record_hash:H?,record_hash:H}. Exact actual record endpoint before the target, never protected body bytes. |
| 22 | NoPreviousRecordEndpoint / zero bytes | Only first_sequence=1 with tag20. |
| 23 | CurrentOpenSegmentNative / 16 KiB | Segment12, state open, exact actual partition active_segment_id. Closed/advanced state may force fresh capture; a stale published control pointer is not silently moved. |
| 24 | OwnedClosedProof / 16 KiB | Exact Proof14 below, from the actual completed AuditControlSegmentProofWorkflow result. Capture is not allowed to manufacture it from B2 metadata. |
| 25 | CheckpointPresence / 16 KiB | {reservation_present:B,preparation_present:B,complete_present:B,step_count:integer0..4}. Booleans report actual distinct queries, never trust or class. complete implies preparation; steps imply reservation. A contradiction is integrity failure. |
| 26 | ReservationNative / 16 KiB | {work_id:U,installation_ref:I,reservation_digest:H,policy_digest:H,artifact_kind:"audit_checkpoint",partition_id:P,segment_id:U,production_class:"ordinary"|"control",segment_binding_digest:H?,checkpoint_reservation_digest:null,intent_id:null,receipt_checkpoint_id:null,anchor_reservation_digest:null,anchor_receipt_id:null}. These are Q3's 14 scalar columns; no receipt projection is admitted. |
| 27 | ReservationEnvelope / 64 KiB | Exact original canonical control-lineage envelope, payload audit_f_reservation. |
| 28 | WorkStepNative / 16 KiB | {work_id:U,ordinal:integer1..4,reservation_digest:H,previous_step_ordinal:integer1..3?,previous_step_digest:H,authorizing_policy_digest:H,phase:one of the exact four adopted step strings,step_digest:H}. Q4's eight scalar columns. |
| 29 | WorkStepEnvelope / 64 KiB | Exact original canonical control-lineage envelope, payload audit_f_work_step. |
| 30 | CompleteCandidateBytes / 1,048,575 | Exact original canonical F1 checkpoint bytes from the same ordinal2 native row. Mandatory immediately after ordinal2 tag29 and forbidden at every other ordinal. |
| 31 | PreparationNative / 16 KiB | {checkpoint_id:U,partition_id:P,segment_id:U,closed_state_version:integer2..9007199254740990,close_fencing_token:F,signing_key_fingerprint:H}. Exact six scalar members of Q5, with unsigned_checkpoint in tag32. |
| 32 | PreparedUnsignedBytes / 1,048,575 | Exact original Q5 unsigned_checkpoint bytes; not re-encoded from a decoded array. |
| 33 | CompleteCheckpointNative / 16 KiB | {checkpoint_id:U,checkpoint_hash:H}. Exact two scalar members of Q6, whose third member is tag34. |
| 34 | PersistedCompleteBytes / 1,048,575 | Exact original Q6 complete_bytes. Q6 uses only the actual checkpoint_id from Q5. |
| 35 | CheckpointEnd / 16 KiB | {work_id:U?,reservation_digest:H?,last_ordinal:integer0..4,last_step_digest:H?,preparation_checkpoint_id:U?,unsigned_sha256:H?,unsigned_length:integer1..1048575?,candidate_sha256:H?,candidate_length:integer1..1048575?,complete_sha256:H?,complete_length:integer1..1048575?,higher_work_ordinal_present:B}. Absence/null pairs agree with actual records; last_ordinal0 means no step and null last_step_digest. higher_work_ordinal_present must be false after the complete actual Q4 lookup, never after a filtered tail query. Derived byte digests/lengths must match the actual separate byte records. |

Segment12 is exactly:

~~~text
{partition_id:P,segment_id:U,state:"open"|"closed"|"sealed",
 first_sequence:N,final_sequence:N?,head_record_hash:H?,
 predecessor_record_hash:H?,opened_at:T,closed_at:T?,
 successor_segment_id:U?,close_fencing_token:F?,state_version:N}
~~~

Retain the actual accepted lifecycle/nullability rules. Do not infer closedStateVersion from a sealed row without F2's exact captured-version+1 binding. A mutable successor/partition-state change is rechecked under the fence, not authorized from its capture label.

Proof14 is exactly the accepted Reader's result, not a new F2 value:

~~~text
{partitionId:P,segmentId:U,state:"closed"|"sealed",stateVersion:N,
 firstSequence:N,lastSequence:N,recordCount:N,firstRecordHash:H,
 chainHeadHash:H,predecessorRecordHash:H?,previousSegmentId:U?,
 successorSegmentId:U,closeFencingToken:F,closedAt:T}
~~~

AuditControlSegmentProofWorkflow obtains it in its own RR read-write B2/publication-fenced root via the existing proveInCurrentTransaction call and commits first. The later read-only capture records that held exact result and matching native endpoints. The final control mutation reruns the actual complete proof under the same fence order and compares it in full. No proof reader, hydrator, decryption provider or protected body is used by READ ONLY capture or locked replay.

### 3.3. Published/staged segment and transition paths

| Tag | Name / cap | Exact payload |
| --- | --- | --- |
| 48 | PublishedSegmentsBegin / 16 KiB | {tail_digest:H?,requested_binding_digest:H?}. Tail must equal the authenticated head; requested digest is the exact control reservation/selection binding, or null for publication continuity alone. |
| 49 | PublishedSegmentNative / 16 KiB | SegmentBinding9 below. |
| 50 | PublishedSegmentEnvelope / 64 KiB | Exact original control-lineage audit_control_segment envelope. |
| 51 | PublishedSegmentsEnd / 16 KiB | {oldest_binding_digest:H?}. Null only for a genuinely null external tail; non-null path must terminate at null previous binding and the externally authenticated bootstrap slot. |
| 52 | StagedSegmentsBegin / 16 KiB | {published_tail_digest:H?,staged_tail_digest:H?,actual_current_segment_id:U,actual_current_first_sequence:N,actual_current_predecessor_record_hash:H?}. Staged status never changes the published tail or establishes new control eligibility. |
| 53 | StagedSegmentNative / 16 KiB | SegmentBinding9. |
| 54 | StagedSegmentEnvelope / 64 KiB | Exact original audit_control_segment envelope. |
| 55 | StagedSegmentsEnd / 16 KiB | {reached_published_tail_digest:H?,staged_tip_next_binding_digest:null}. The full staged predecessor path reaches exactly the published tail, and the actual next-binding lookup at the staged tip was absent. A new actual successor at fenced recheck requires a new capture. |
| 64 | TransitionsBegin / 16 KiB | {tail_digest:H,acknowledged_transition_digest:H?}. These are the exact head/candidate roots for the current phase. |
| 65 | TransitionNative / 16 KiB | {installation_ref:I,transition_id:H,transition_digest:H,authorizing_policy_digest:H,previous_transition_digest:H?,previous_policy_state_digest:H?,new_policy_state_digest:H,cause:"root_revision"|"segment_advance"}. |
| 66 | TransitionEnvelope / 64 KiB | Exact original audit_control_policy_transition envelope. |
| 67 | TransitionsEnd / 16 KiB | {oldest_transition_digest:H}. Chain terminates at null previous transition/state and root_revision; this end marker alone cannot prove bootstrap. |
| 68 | TransitionAckNative / 16 KiB | {installation_ref:I,acknowledgement_digest:H,authorizing_policy_digest:H,transition_id:H,transition_digest:H,ingress_key:I}. |
| 69 | TransitionAckEnvelope / 64 KiB | Exact original audit_control_transition_ack envelope referenced by the external/current candidate head. It must bind the exact cursor transition and its old/new states. |
| 70 | NoTransitionAcknowledgement / zero bytes | Only when the authenticated head's acknowledgement pair is null/null. |

SegmentBinding9 is exactly:

~~~text
{installation_ref:I,binding_digest:H,policy_digest:H,partition_id:"system:global",
 segment_id:U,first_sequence:N,last_sequence:N,successor_segment_id:U,
 previous_binding_digest:H?}
~~~

Full close proof members are in the signed envelope and must be semantically verified. Native9 is neither the accepted Proof14 nor a substitute for it. The published path is newest-to-oldest all the way to the root bootstrap; it must contain the exact requested binding once. A root policy revision retains continuity; it does not reset bootstrap to that revision's currentSegment. First policy/bootstrap can legitimately begin after sequence1.

Staged paths are separately identified and run staged tip back to the exact published tail, excluding the already repeated published-tail record. Capture may discover the tip by following the unique native nextBinding links, but those hints gain no authority until full locked replay. Each edge is checked for signed sequence/successor/predecessor continuity. The 32-new-edges-per-control-tick bound is preserved by the actual producer; it is not a bound on captured staged/history path length.

Transition capture follows exact previous digests to bootstrap. There is no ordering inference from transitionId. Before trusting structural progress during capture, use at most two digest cursors for cycle detection with bounded exact lookups; do not retain a visited set. Locked replay independently verifies every edge, installation, policy/grant/signature/restriction, recomputed transition ID, state continuity and bootstrap. Count only transitions strictly after the signed acknowledged cursor toward the adopted maximum32 pending entries; acknowledged retained history is uncapped. Missing cursor, skipped pending edge, extra branch or false drained assertion fails.

### 3.4. Publication history and exact candidate

| Tag | Name / cap | Exact payload |
| --- | --- | --- |
| 72 | PublicationsBegin / 16 KiB | {tail_revision:N,tail_head_digest:H}. The accepted predecessor root named by the installed hint, not the greatest SQL revision. |
| 73 | PublicationNative / 16 KiB | Publication12 below. |
| 74 | PublicationDocument / 1 MiB | Exact original audit_control_authority wrapper from that immutable SQL row. |
| 75 | PublicationsEnd / 16 KiB | {bootstrap_head_digest:H}. The chain reaches revision1 with the exact null predecessor pair and bootstrap transition. |
| 76 | CandidatePublicationNative / 16 KiB | Publication12 for the single exact committed/recovery candidate. |
| 77 | CandidatePublicationDocument / 1 MiB | Exact original immutable candidate wrapper; never locally regenerated after a lost response. |
| 78 | NoNextPublication / zero bytes | Actual absence of the unique next revision/head association in a candidate-creation attempt. No SQL filter may hide another native candidate. |
| 79 | PublicationEnd / 16 KiB | {accepted_revision:Z,accepted_head_digest:H?,next_revision:N,next_head_digest:H?,next_document_sha256:H?,higher_than_next_present:B,staged_tip_binding_digest:H?}. The next-head/document pair is both null or both present; bootstrap accepted_revision0 implies null accepted head. A candidate may be staged beyond the accepted head; it is not current authority. A contradictory later row is not ignored. |

Publication12 is exactly:

~~~text
{installation_ref:I,revision:N,head_digest:H,previous_revision:N?,
 previous_head_digest:H?,policy_digest:H,policy_state_digest:H,
 closed_segment_tail_digest:H?,transition_tail_digest:H,
 acknowledged_transition_digest:H?,transition_ack_digest:H?,document_sha256:H}
~~~

The thirteenth actual SQL member is tag74/tag77 document_bytes. Compare all native columns to original wrapper content, both envelope digests and exact full-document ordinary SHA. previous_revision decrements by exactly1. A head refresh or acknowledgement-only historical record must not create a semantic transition; a root/segment change must bind its exact transition. The paired acknowledgement is not a DB published flag. Historical publication verification may re-scan the descriptor's segment/transition blocks for each required association instead of retaining a map.

## 4. Exact phase vocabulary and legal record order

The private header phase is set by a fixed named method and held in the live capture scope. It cannot be chosen by an application, array, request, SQL class column or deserializer.

| Number | Fixed phase | Named caller / meaning |
| --- | --- | --- |
| 1 | inspect_ordinary_checkpoint | PostgreSqlOrdinaryCheckpointSelector via HistoryInspection; authenticated facts only. |
| 2 | inspect_control_checkpoint | Actual control selector via HistoryInspection; authenticated facts only. |
| 3 | reserve_ordinary_checkpoint | AuditOrdinaryCheckpointRegistration; distinct ordinary origin. |
| 4 | reserve_control_checkpoint | AuditControlCheckpointIssuer; distinct control origin and published membership. |
| 5 | before_checkpoint_prepare | AuditCheckpointPreparationWorkflow; authentic existing reservation/current selection before its separate F2 call. |
| 6 | bind_checkpoint_preparation | Same workflow after actual committed/replayed F2 prepare; exact owned PreparedCheckpointWork. |
| 7 | checkpoint_candidate | AuditCheckpointCandidateWorkflow after actual ordinary allowed/requested or closed control invocation; recover/reuse exact committed candidate. |
| 8 | before_checkpoint_persist | AuditCheckpointPersistenceWorkflow before actual F2 persist/current online check; no production-outcome signature. |
| 9 | checkpoint_persisted_outcome | Same workflow only after actual F2 normal committed/reconciled return and native exact candidate equality. |
| 10 | control_bootstrap_candidate | Closed independently selected initial publisher; zero sentinel and separately archived policy1. Normal acquisition cannot call this path. |
| 11 | control_root_revision_candidate | Closed stopped installation/root-revision publisher with independently installed new binding and retained predecessor. |
| 12 | control_segment_advance_candidate | Actual control publication owner with OwnedClosedProof and exact published/staged continuity. |
| 13 | control_head_refresh_candidate | Actual unchanged-state head refresh owner. |
| 14 | control_publication_recovery | Closed publisher, exact existing pending or already accepted revision/document; not latest-wins. |
| 15 | control_publish_committed_candidate | Closed publisher after the exact candidate/transition SQL commit. This does not mint a work-signing permission. |

Grammar notation uses decimal tags. Parentheses group a fixed block; [x] is legal only under the explicit condition; x* is streamed repetition with no history cap; | is the stated disjoint native case. There is no free tag ordering.

~~~text
A = 2 3
B = 16 17 18 (19|20) (21|22)
W = 25 [26 27] (28 29 [30]){0..4}
F = [31 32] [33 34] 35
S = 48 (49 50)* 51
G = 52 (53 54)* 55
T = 64 (65 66)+ 67 (68 69|70)
P = 72 (73 74)+ 75
C = 76 77
~~~

For W, the reservation pair exists iff actual reservation_present; steps are strictly ordinal1..step_count and match the exact adopted phase map. Tag30 exists iff that row is ordinal2. For F, bytes/groups follow exact presence; complete cannot precede a preparation. An F2 row without authentic reservation is rejected, not converted into a new reservation. A fifth Q4 row is rejected before a legal transcript can be finalized; tag35 independently binds the no-higher condition.

Phases1..9 are exactly:

~~~text
1 A B W F [23 S] 255
~~~

Here the first 1 is Header, not a second phase. The S block is mandatory when the requested control phase or captured reservation's exact signed class/binding requires it; ordinary work with null segmentBindingDigest has no S block. Capture may use bounded structural hints to collect the appropriate path, but replay must derive and enforce its necessity from authenticated content. Supplying an unrelated path or omitting a needed one fails; it cannot conservatively reject an unrelated compromised key.

For a new ordinary/control reservation, actual reservation and F2 absence are required at the final fence; a present reservation is inspected/recovered as its existing class, never reclassified. For phases5..9, captured workId/reservation and the relevant previous step must exist; exact phase preconditions follow the real owner, not the capture label. Phases6..9 require actual preparation. Phase9 requires actual complete bytes equal to the signed candidate.

Publication phases use the following fixed order, with actual ordinary capture hints granting nothing:

| Phase | Complete order after Header and before255 |
| --- | --- |
| 10 | 6 4 5 16 23 78 79. SQL must contain no predecessor/publication; installed bootstrap root policy and exact actual open slot are independently checked. |
| 11 | A 4 5 16 23 P S T 78 79. The historical old head proves continuity only; current new grant comes from the independently installed selected policy. |
| 12 | A 4 B 23 24 P S G T 78 79. The proof was obtained in a completed separate fenced root; final mutation reproves it. |
| 13 | A 4 16 23 P S T 78 79. Root policy/state/segment/tails are unchanged by refresh. Actual newer B2 progress does not authorize silently changing the published slot. |
| 14/15, positive accepted predecessor | A 4 16 23 P S [G] T C 79. G only if the exact candidate's closed tail extends the accepted published tail. |
| 14/15, initial predecessor | 6 4 5 16 23 S T C 79. S is the exact empty bootstrap path; candidate transition/head is the real first root revision, not an acknowledgement. |

For phase14/15, T starts at the exact candidate transition tail (including a newly committed transition); P starts at the accepted predecessor, not at a higher SQL candidate. Segment/history blocks include the candidate's actual required association; candidate's unchanged/extended links are checked against accepted S/G. If the floor already accepted the exact candidate after lost response, A/P may begin at that same accepted candidate; C must be byte-identical and replay cannot allocate a new revision. This is an exact replay case, not a different candidate-selection rule.

A next-publication row discovered during phases10..13 prevents new candidate creation and requires a distinct recovery capture; do not omit the row by a WHERE clause or adopt it under the create phase. For later D acknowledgement-only creation a new exact phase/API/grammar must be issued; no producer is enabled by permitting historical tag68/69.

## 5. Per-record source, semantic owner and reread obligation

All SQL uses the companion map's fixed configured identifiers, actual Connection, scalar bindings, explicit columns and bounded lookups. This document deliberately does not duplicate the query implementation or selector decision algorithm.

| Records | Actual source / semantic verifier | Required later check |
| --- | --- | --- |
| 1 | Live trusted constructor context and configured schema/installation; CaptureScope + replay grammar | Equality to the still-private scope object and same graph. A well-formed serialized header cannot recreate it. |
| 2/3 | Installed control source structural hint; CurrentControlDocumentVerifier plus current floor | While locked, reread actual wrapper and authenticate live root/current delegate; exact wrapper/head/floor equality. A changed tip requires a new whole capture. |
| 4/5/6 | Nonlocking floor hint / independently selected archive digest / actual empty initial source; dedicated publication verifier | Locked exact state matrix, full expected-floor match, installed root/delegate and actual current restrictions. Normal acquisition rejects zero/pending. |
| 16..23 | Q1, exact existing B2 segment12 and endpoint4 projections via closed capture query owner; Checkpoint native admission and replay association | After floor unlock, actual B2 fence then native state/target/successor/predecessor/endpoint reread in accepted order. Reject changed target, tenant or native boundary. |
| 24 | Actual accepted Reader proof result after AuditControlSegmentProofWorkflow commit; closed proof parser | Re-run actual Reader in final read-write B2/publication-fenced root, compare all14. No copied row/hash substitutes for protected-record proof. |
| 25..29/35 | SQL work reservation/steps Q3/Q4, actual bounded fifth-row/no-tail check; CheckpointWorkReplay plus historical document verification | Lock/read exact slot, reservation digest, full <=4 phase chain and actual tail/no-higher; compare envelopes and endpoint facts. No dispatch-derived tail. |
| 30 | Actual ordinal2 complete_bytes; signed complete_candidate data and actual F1 outer-phase checks | Reread exact original candidate bytes and compare byte-for-byte, length, ordinary SHA, preparation step, F1 identity/hash. |
| 31..34 | New Checkpoint-owned Admission, actual exact7 and exact3 Q5/Q6; native preparation association and work replay | Lock/re-read actual immutable bytes, fingerprint, captured version/token, unsigned/body/id/hash, complete absence/presence. No transcript-only SHA comparison. |
| 48..55 | audit_f_segment_bindings exact digest/unique next-binding lookups, source head root; SegmentHistoryReplay | Authenticate full published/staged paths under current restrictions; final fence rechecks native published/staged endpoints, actual next-binding absence/presence and actual B2 current slot. Staged never becomes current by its label. |
| 64..67 | audit_f_policy_transitions exact previous links; TransitionHistoryReplay | Verify every necessary historical issuer, derived transition IDs and bootstrap/backlog; final root checks exact predecessor/tail/cursor association and any new transition slot. |
| 68..70 | audit_f_transition_acks exact referenced digest/transition, or actual null head pair; TransitionHistoryReplay | Compare exact transition/ack bytes/association at relevant publication mutation. Historical ack is not a new D result; no signed ack insertion in this stage. |
| 72..79 | audit_f_publication_candidates exact installation/revision/head/document keys; PublicationHistoryReplay | Compare actual accepted predecessor, next candidate/revision/document, no unexpected successor and candidate's exact transition/segment association under publication fence before any SQL mutation. For external publication, immutable committed candidate is captured before floor lock and matched to pending/accepted floor; no SQL under it. |
| 255 | Private same-descriptor framing only; HistoryReader | Verify full seal/EOF on that descriptor; close successfully. Never substitute seal success for any signature/path/native recheck. |

The read-only capture closes/commits before any acquisition. The final mutation uses the actual write root's B2 fence, optional publication fence, B2 rows, F2 preparation then complete, reservation then steps, affected control history/index. It verifies native RR/read-write state. No unrelated SQL, provider, key lookup, live floor, nested transaction, callback or new target is inserted into that endpoint handoff.

The complete authenticated immutable history justifies bounded endpoint rereading. A lower mutable head, native queue row, terminal signature, stored issuer list or a previously checked current grant cannot do so. Any endpoint change prevents mutation and requires a new invocation capture/acquisition; invisible RR winners and ambiguous native state discard the entire affected graph according to the companion correction. No same-object reconnect or within-root retry exists.

## 6. Full replay, historical issuers and exact pins

Replay has three deliberately separate layers:

1. Transport validity and a phase-legal complete frame sequence.
2. Original canonical document/closed native shape equality and complete semantic linkage.
3. Current locked installed source/grant plus every required historical issuer/restriction and the fresh phase Clock. Only successful completion of all layers may produce the internal phase result.

Under the control floor, open the installed accepted wrapper afresh, verify against the live configured root and exact configured delegate, then require equality to tag2/3 and the accepted floor. Resolve each required historical policy only by walking the external archive from that authenticated current policy through exact previousPolicyDigest/version decrement. Read one policy at a time, using independently installed historical-root records for historical policy signatures only. A DB policy mirror, policy supplied by a transcript, or an otherwise valid unrelated root signature is never fallback authority.

For each actually required root/delegate material, check the exact live installed revocation path under this same lock. Re-read where needed; do not keep a growing seen-issuer set or reuse a previous acquisition's absence check. Unrelated markers are never enumerated or used to deny this operation. A malformed/unreadable relevant namespace/object fails closed.

Historical compromise handling retains 130 section10: an exact envelope/fingerprint pin in an authenticated later root policy can vouch for that exact history, and an authenticated terminal pin carries only its exact signed predecessor path. It cannot authorize a new signature, override the current root/current-delegate restriction or authenticate arbitrary sibling records. Search pins for the exact needed digest/material while walking the external policy chain; no aggregated pin cache.

Work SQL is ASC ordinal1..4. Store only up to four fixed offsets/digests/ordinals while scanning it. Authenticate from the actual tail back through every exact previousStepDigest to the reservation, so terminal-pin propagation cannot be lost by blindly verifying ASC. Then reread forward for phase data/F2 associations. Each original envelope is re-read from the same sealed anonymous descriptor; no retained decoded envelope list. The reservation's policy/installation/slot/origin/class/key binding and every authorizingPolicyDigest are verified.

Unlimited segment/publication chains are already captured newest-to-oldest. Each next original record must have the digest required by its authenticated successor and valid monotone sequence/revision relation. A pin propagated along one such path cannot cross into an unrelated policy, sibling step, transition or artifact. Cross-associations between publication, segment and transition history use fixed cursors and repeated scans. Extra I/O is permitted; a lifetime-sized lookup table is not.

Transition paths lack a signed ordinal; the capture owner rejects native cycles with bounded cursors and replay verifies the exact authenticated chain/root/state links rather than believing the capture's cycle label. A missing middle record, wrong bootstrap, stale/lower tail, extra record, unrelated correctly signed envelope, unresolved policy or changed compromise marker prevents success even if the transcript checksum is valid.

No SQL, seed read, provider invocation or artifact signing occurs under the floor. Root's explicit finite pure-codec exception below binds original captured artifact bytes without asserting current online trust. Current online/F1 signature verification always remains independently required by its actual phase after control lock release. There is no implied permission to change a stored F2 ref/time/body based on a new online selection.

Only after all current/historical proof does acquisition sample its composition-owned Clock once. Validate the current policy/delegate windows, head observation, stored rollback watermark and fixed phase permission. Persist observedNow in the same floor when changed; checked unlock and close precede any returned result. No caller supplies now, a phase time or a snapshot to this acquisition. Online and control locks are never nested.

### 6.1. Explicit Root-selected pure F1 delta and exact downstream graph

Root selected this clarification on 2026-10-13: replace r2 section7's blanket no-F1 phrase with a finite pure-codec exception, rather than duplicate F1 body/hash semantics or trust caller scalar projections. Only H AuditCheckpointArtifactReplay.php may directly call these three existing public static methods while the control floor is locked:

~~~text
AuditCheckpointCodec::signingInput(array unsigned): array{bytes,hash,preimage}
AuditCheckpointCodec::decode(string originalCompleteBytes): exact complete array
AuditCheckpointCodec::bytesHash(string originalCompleteBytes): string
~~~

The fixed replay owner has constructor(AuditAuthorityJsonPreflight), unsigned(string originalUnsignedBytes):AuditCheckpointUnsignedFacts, complete(string originalCompleteBytes):AuditCheckpointCompleteFacts, and at most six fixed parse/input/binding helpers, at most10 total methods. Sole production callers are AuditWorkHistoryReplay and the F2 association portion of AuditCheckpointHistoryReplay. It is not a generic Artifact factory, signer or a new public parser.

Every entry first applies the actual category byte cap (1,048,575), no BOM/CR/LF/suffix, and finite raw preflight. For unsigned bytes, existing JCS decode/toNative produces a detached object-shaped array, then the one fixed signingInput call site supplies all F1 exact16 shape/relation/hash rules; require input.bytes byte-equal to the original captured unsigned bytes. Do not use a caller-supplied normalized object. For complete/candidate bytes, decode performs F1's existing flat preflight and exact18 canonical/hash/signature-encoding admission; bytesHash computes its unchanged domain-separated complete-byte identity. The shared private input helper owns the single direct signingInput call site. The complete path has one direct decode and one direct bytesHash call site. bytesHash's internal decode repetition remains accepted existing behavior, not a new external call edge.

Bind the original byte strings to authenticated work data: exact checkpoint ID/partition/segment, frozen key ref/signedAt, F1 unsigned hash, byte-for-byte canonical unsigned body, ordinary unsigned/complete SHA and length, preparation step/candidate/outcome links, signing fingerprint and native captured version/token. Use bounded rereads when two bodies must be compared; do not substitute native hash labels. The later actual phase independently verifies Ed25519 checkpoint signature against its freshly acquired online snapshot; these three codecs do not establish that signature's authenticity or current status.

Exact existing reachable application-source graph (only checkpoint branches):

| Source | Reachable methods / effect |
| --- | --- |
| Artifact/AuditCheckpointCodec.php | signingInput -> Shape.checkpointUnsigned, Relation.checkpoint, Canonical.artifact, private frame, core hash and sodium_hex2bin. decode -> Canonical.decode, Shape.checkpointComplete, Relation.checkpoint, Integrity.checkpoint. bytesHash -> decode, private frame, core hash. complete is excluded. |
| Artifact/AuditArtifactShape.php | checkpointComplete -> Scalar.closed, digest, Scalar.string, checkpointUnsigned; checkpointUnsigned -> Scalar.closed, private checkpointFields/partition/segment/digest, RecordTime.assertTimestamp. Fixed scalar detachment only; anchor branches excluded. |
| Artifact/AuditArtifactScalar.php | closed, string, nullableString, boundedInteger -> AuditStringValidator.assertSafe and MAX_SAFE_INTEGER. No reference-bearing caller array is accepted by this replay entry. |
| Artifact/AuditArtifactRelation.php | checkpoint plus private invalid only; exact range/count/hash/predecessor relations. |
| Artifact/AuditArtifactCanonical.php | artifact -> new AuditJcsCodec.canonicalJson; decode -> WirePreflight.assertFlatObject, new AuditJcsCodec.decode/toNative; finite failures. trustSnapshot is not an allowed outside edge. |
| Artifact/AuditArtifactWirePreflight.php | assertFlatObject -> private invalid; bounded bytes/18 top-level members/quote/escape scan rejects nested objects/lists. Existing internal call remains unchanged; H does not gain direct access to this guarded helper. |
| Artifact/AuditArtifactIntegrity.php | checkpoint -> Canonical.artifact, private frame/signature/invalid, core hash/hash_equals, sodium base64 conversion. Signature here means canonical64-byte encoding only; no Ed25519 verification or trust lookup. |
| Internal/Audit/AuditJcsCodec.php and AuditJcsObject.php | canonicalJson/decode/toNative and their private parsers/encoders, bounded by preflight before recursion. UTF-8, duplicate decoded keys, safe integer, exact canonical encoding. Existing default validation only; no deferUtf8Validation call. |
| Internal/Audit/AuditStringValidator.php; Core/Audit/AuditValidationException.php | assertSafe -> assertSafeString/assertUtf8; regex, byte length and existing optional Normalizer normalization check. No new ext-intl dependency or normalization policy is added. |
| Core/Audit/AuditRecordTime.php | assertTimestamp -> DateTimeImmutable::createFromFormat with explicit reset format and UTC, getLastErrors/roundtrip. It parses supplied time; it never reads Clock or current time. |
| Artifact/AuditArtifactException.php, Artifact/AuditArtifactFailure.php, Core/Audit/AuditValidationFailure.php | Finite failures only. Reconstruct the owned acquisition failure without lower raw cause retention. |

There is no reachable Connection, filesystem/network I/O, Clock sampling, provider, seed reader, sodium signing, public-key discovery or current trust validator in these selected branches. Native sodium hex/base64 conversion is data conversion; it does not broaden the permitted signing surface.

Add one exact outside-caller/static inventory row for Internal/Audit/Authority/History/AuditCheckpointArtifactReplay.php in the existing AuditArtifactArchitectureTest: AuditCheckpointCodec signingInput x1, decode x1, bytesHash x1. Preserve every existing owner/count and guard the new role-specific no-sign/no-trust negative cases, aliases/group imports/class strings/indirect calls and locked-call graph. Do not allow the whole Authority or History namespace through Artifact guards. Hashes for all selected graph sources and this architecture source are recorded in the companion input manifest. This is source evidence and a Root-selected reviewed-delta candidate, not executed guard proof or adoption.

## 7. Finite capture/replay external APIs

Paths in this section are proposed exact new paths relative to C, not existing APIs or issued files. H denotes src/Internal/Audit/Authority/History/. A denotes src/Internal/Audit/Authority/. The companion map owns the real workflow/type construction chain and all SQL writers.

Every result/descriptor is final, held in SensitiveParameterValue or equivalent private sensitive storage, with safe debug, throwing deserialize, non-cloneability and no public resource/bytes/path getter. Constructors/factories and every production call edge are exact source guarded. Merely using readonly/private visibility is not a provenance proof. No Application container registration, reflection/class-string/indirect invocation, arbitrary factory, hydration or generic callable is allowed.

| Candidate path | Exact public surface / sole production callers | Maximum total declared methods |
| --- | --- | --- |
| H AuditCheckpointReservationCapture.php | constructor(AuditCheckpointCaptureWriter); ordinary(OrdinaryCheckpointSelection):AuditCapturedCheckpointHistory; control(AuditControlCheckpointSelection):AuditCapturedCheckpointHistory. Only corresponding ordinary registration/control issuer. Hardcoded phases3/4. | 8 |
| H AuditCheckpointPreparationCapture.php | constructor(AuditCheckpointCaptureWriter); beforePrepare(ReservedCheckpointWork):AuditCapturedCheckpointHistory; afterPrepare(PreparedCheckpointWork):AuditCapturedCheckpointHistory. Only PreparationWorkflow; phases5/6. | 8 |
| H AuditCheckpointCandidateCapture.php | constructor(AuditCheckpointCaptureWriter); beforeCandidate(PreparedCheckpointWork):AuditCapturedCheckpointHistory. Only CandidateWorkflow after its real invocation gate; phase7. | 7 |
| H AuditCheckpointPersistenceCapture.php | constructor(AuditCheckpointCaptureWriter); beforePersist(HeldCheckpointCandidate):AuditCapturedCheckpointHistory; afterPersist(HeldCheckpointCandidate):AuditCapturedCheckpointHistory. Only PersistenceWorkflow; phases8/9, fresh capture each time. | 8 |
| H AuditCheckpointHistoryInspection.php | constructor(AuditCheckpointCaptureWriter,AuditControlAcquisition); ordinary(OrdinaryCheckpointSelection):AuditInspectedCheckpointSlot; control(AuditControlCheckpointSelection):AuditInspectedCheckpointSlot. Only actual selectors; phases1/2. It owns fresh capture -> locked inspection -> explicit cleanup, returns facts only. | 9 |
| H AuditCheckpointCaptureWriter.php | constructor(PostgreSqlAuditProvenanceReadTransaction,AuditCheckpointCaptureRows,AuditControlHistoryCaptureRows,AuditPrivateHistoryFiles,AuditControlSourceHintReader); capture(AuditCheckpointCaptureScope):AuditCapturedCheckpointHistory. Only five closed adapters above. CaptureScope is a detached fixed fact produced by those adapters, no payload/class/phase string from arbitrary callers. | 10 |
| H AuditControlHistoryCapture.php | constructor(AuditControlCaptureWriter); bootstrap(AuditControlBootstrapSelection); rootRevision(AuditControlRootRevisionSelection); segmentAdvance(AuditControlClosedSegmentProof); refresh(AuditControlHeadSelection); recover(AuditControlPendingPublication); publish(AuditControlPublicationCandidate); each returns AuditCapturedControlHistory. Only actual closed publication workflow. Phases10..15. | 10; shared safety relies on the opaque contained writer/results and exact source guards, no extra helper methods hidden beyond this budget |
| H AuditControlCaptureWriter.php | constructor(ReadTransaction,AuditControlHistoryCaptureRows,AuditControlNativeCaptureRows,AuditPrivateHistoryFiles,AuditControlSourceHintReader); capture(AuditControlCaptureScope):AuditCapturedControlHistory. Only previous closed adapter. | 10 |
| A AuditControlAcquisition.php | constructor(AuditControlConfiguration,AuditControlLockedSession,AuditCheckpointHistoryReplay,ClockInterface); inspectCheckpoint(AuditCapturedCheckpointHistory):AuditInspectedCheckpointSlot; checkpoint(AuditCapturedCheckpointHistory):AuditCheckpointPhaseAuthority. First accepts only phases1/2; second only3..9. Constructed once by the closed shared composition; invoked only by inspection or named real checkpoint phase workflows. Normal paths reject zero/pending. | 10 |
| H AuditCheckpointHistoryReplay.php | constructor(AuditHistoryReader,AuditWorkHistoryReplay,AuditSegmentHistoryReplay,AuditHistoricalAuthority); replay(AuditCapturedCheckpointHistory,AuditAuthenticatedControlHead):AuditVerifiedCheckpointEndpoints. It has no Clock/DB/seed/provider/floor writer and cannot construct a live phase authority. Sole caller current acquisition while its floor is locked. | 10 |
| H AuditControlHistoryReplay.php | constructor(AuditHistoryReader,AuditPublicationHistoryReplay,AuditTransitionHistoryReplay,AuditSegmentHistoryReplay,AuditHistoricalAuthority); replay(AuditCapturedControlHistory,AuditAuthenticatedControlHead):AuditVerifiedControlEndpoints. Dedicated publication owner supplies an exact admitted bootstrap/replacement-head context instead of a fabricated ordinary current result. | 10 |
| A AuditControlPublicationAcquisition.php | constructor(AuditControlCandidateAcquisition,AuditControlPublisher); bootstrapCandidate(AuditCapturedControlHistory); rootRevisionCandidate(AuditCapturedControlHistory); segmentCandidate(AuditCapturedControlHistory); refreshCandidate(AuditCapturedControlHistory), each -> AuditControlPublicationAuthority and only matching phase10/11/12/13; publish(AuditCapturedControlHistory):AuditControlPublicationResult and recover(AuditCapturedControlHistory):AuditControlPublicationResult delegate fixed phases15/14 to Publisher. Only the actual fixed publication workflow. No callback/mode/sign payload. | 10: ctor+6 fixed public methods, at most3 representation methods; clone/indirect construction additionally denied by exact source guards |
| A AuditControlCandidateAcquisition.php | constructor(AuditControlConfiguration,AuditControlLockedSession,AuditControlHistoryReplay,ClockInterface); acquire(AuditCapturedControlHistory):AuditControlPublicationAuthority. Only the previous facade's four fixed candidate methods, exact private scope match; its bounded bootstrap/replacement/ordinary-current state helpers never accept phase from a public scalar. | <=10 |
| A AuditControlPublisher.php | constructor(configuration,lockedSession,controlReplay,ClockInterface); publish(AuditCapturedControlHistory):AuditControlPublicationResult; recover(AuditCapturedControlHistory):AuditControlPublicationResult. Only phases15/14 respectively, exact immutable committed candidate. Sole caller is the two fixed delegation methods of PublicationAcquisition, keeping the actual Workflow within five constructor collaborators. It executes the adopted pending/source/accepted protocol without SQL under lock, not a work-signing result. | 10 |

ReadTransaction is exactly the companion's Provenance/PostgreSqlAuditProvenanceReadTransaction(Connection,AuditSigningGraphState), beginRoot(), commit(), abort(Throwable):AuditDeliveryException. beginRoot checks inactive/auto-commit/level0, begins, executes literal SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, SET TRANSACTION READ ONLY, selected local timeouts, and verifies actual native state. No callback or caller-owned-root flag. Capture query methods verify the expected current native root, use no FOR UPDATE/advisory lock, and do not call the accepted full proof reader.

AuditCheckpointCaptureRows is a private constructor-bound collaboration containing only the actual Admission and WorkRows needed by this capture; its fixed methods are nativeSlot(selection), reservation(slot), steps(work), each returns one closed bounded row/result. It has no raw Connection/table/getter or authority, and cannot be injected separately with another connection. It is constructed by the exact shared composition from the same operations Connection. ControlHistoryCaptureRows similarly exposes only binding/nextBinding/transition/ack/publication/predecessor fixed exact lookups from the companion's existing proposed row owners. These are cohesive source plumbing, not generic query services or a second source of SQL design.

The capture adapters never return a permission. Captured result ownership is lent once to its acquisition/inspection/publisher and retained by that exact phase through the native endpoint handoff; it is closed at the end of that phase. Internal reader access is guarded to the listed replay/endpoint-check owners only. No public clone, re-open-from-path, byte export or repeat-acquire with the same capture exists. A reader borrows the descriptor for a bounded pass; finishPass checks its pass boundary and never masquerades as native closure. The capture owner performs actual checked close after the final exact byte reread and before its phase can escape a result. For a mutation this is before delegate seed/sign or the committed mutation result; failure aborts the owned root. Inspection closes before returning inspected facts. For pre-F2 phases it closes before calling F2. Floor unlock/close is independent and always succeeds before leaving acquisition, while the anonymous evidence descriptor may remain held solely for the exact fenced reread. No file pathname remains live and no external floor spans SQL.

AuditCheckpointPhaseAuthority stores privately: exact live capture-scope identity, fixed phase/installation/tenant/partition/segment/work, the fully verified finite endpoints, current policy/delegate binding and its one owned now. It cannot select a different target. The sole consumer is Provenance/AuditCheckpointMutationAdmission::consume(AuditCheckpointPhaseAuthority,AuditCheckpointCaptureScope):AuditVerifiedCheckpointEndpoints. It checks the exact real phase/input/target and calls authority.consume(scope) once before opening the write root. Its assertUnchanged(AuditVerifiedCheckpointEndpoints):void then rereads inside that named fenced root. Only the fixed Recorder phase or pre-F2 workflow calls this consumer; no pre-F2 result can be accepted by the post-F2 append. The result contains noncredential facts, never permission for another phase. Consumption offers no sign(payload), callback or production-class input.

For phase5/8, the named workflow additionally obtains its own fresh online acquisition after control lock release and calls actual F2 prepare/persist in F2's unchanged root. That before-call result is spent; after actual return it obtains phase6/9 through a new capture and control acquisition. F2 keeps its own Clock/UUID/trust/replay behavior. Candidate signing reacquires current online authority in the concrete signer itself and never inherits an old capture's snapshot.

### 7.1. Exact held scopes, result ownership and publication facts

The following are private data facts, not new authority wires. Only native trusted workflow constructors listed here create them; each copies its exact closed shape and rejects references. They use the four representation controls (safe debug, rejecting serialize/unserialize, private/non-cloneable clone) unless explicitly delegated by the service-only budget above. All are <=10 total methods and <=5 constructor/method parameters.

- AuditCheckpointCaptureScope: guarded Internal constructor(AuditCheckpointCapturePhase,AuditHistoryInstallation,actualTarget), with actualTarget the exact union OrdinaryCheckpointSelection|AuditControlCheckpointSelection|ReservedCheckpointWork|PreparedCheckpointWork|HeldCheckpointCandidate. Only the seven fixed capture adapter methods and two inspection entries construct it, each with its own literal enum case. The closed enum maps exactly phases1..9; no string, arbitrary array or public deserialization factory chooses a phase. AuditHistoryInstallation is the constructor-owned exact configured schema/installation and graph identity; it has no requester-controlled values or resource getter. Accessors phase(), target(), installation() are guarded to writer/replay/MutationAdmission; constructor+3 accessors+4 controls=8. This scope is an untrusted capture fact, not authority. The live object identity is retained outside Header; a Header cannot recreate it. The fixed method literal, typed target, phase preconditions and actual invocation owner must all agree. ControlCaptureScope uses only phases10..15 and the exact publication selection/proof/candidate union, with the same budget and source restrictions.
- AuditCapturedCheckpointHistory: private constructor(scope,sealedHistory,expectedNativeEndpoints); static fromCapture(same3) only CaptureWriter after successful RR commit; scope(), borrowForReplay() and close() only the acquired phase owner/reader/MutationAdmission. Borrow is invocation-bound and cannot create a second acquired phase; ownership closure is explicit, total9. No resource/path/bytes export or successful reuse after close. CapturedControlHistory has the same ownership shape with ControlCaptureScope and no checkpoint authority conversion.
- AuditCheckpointPhaseAuthority: private constructor(scope,verifiedEndpoints,currentGrant,now); static fromAcquisition(same4) only successful normal ControlAcquisition; consume(scope):AuditVerifiedCheckpointEndpoints only MutationAdmission, one-use and exact object/phase equality; boundPhase() only that same consumer for its fixed case match; total8. Current grant/time are private to the closed recorder/delegate join and never arbitrary metadata returned to applications. If its concrete signer needs those fields, Root must grant one fixed accessor within the remaining two methods, not an unrestricted data getter or generic signature method.
- AuditInspectedCheckpointSlot: private constructor(actualSlot,verifiedWork,verifiedMembership); static fromInspection(same3) only successful inspectCheckpoint; checkpoint(), work(), membership() only respective selectors; total9. No current phase grant, seed path, signer or mutable class flag; inspection cannot be supplied to Recorder as permission.
- AuditVerifiedCheckpointEndpoints / AuditVerifiedControlEndpoints: fixed scalar groups and bounded <=4 work offset facts in private storage, plus internal same-capture references needed for exact byte rereads. One guarded constructor/factory, at most3 fixed internal accessors and four controls, <=9. They are comparison facts after the single-use grant has been consumed; they do not authorize another operation.
- Control/AuditControlPublicationCandidate: private constructor(schemaName,exactNative13Row); static fromCommitted(same2) only actual PublicationWriter after successful commit or exact fresh-root reconciliation; native():Publication12, documentBytes():string, schemaName():string for the fixed capture/publisher only; total9. Native13 is the actual immutable row including document_bytes. It is a committed SQL fact, not acquired current permission or a signed payload factory. recover/publish never allocate different bytes/revision.
- Control/AuditControlHeadSelection: private constructor(configuredScope,actualSourceHint,nativePartition8,nativeActiveSegment12); static fromSourceHint(same4) only actual PublicationWorkflow; source(), native() only control capture, <=8. This is refresh selection data; the later locked verifier supplies authority.
- Control/AuditControlPendingPublication: private constructor(configuredScope,actualPublicationHint); static fromFloorHint(same2) only actual PublicationWorkflow; expectedFloor(), candidateIdentity() only exact capture/publisher; <=8. The identity is exactly floor-pinned revision/document SHA or a previously retained exact candidate identity for accepted lost-response replay, never SQL max/revision inference. It has no current grant.
- Control/AuditControlBootstrapSelection and Control/AuditControlRootRevisionSelection: each carries only independently configured scope, exact independently selected archived policy digest/version, expected initial/retained predecessor, and actual bounded native B2 target. Their private constructor has at most4 such fixed typed inputs; one installation-only factory and at most2 fixed accessors plus controls =><=8. **The actual installation-owned constructor entry is not yet present or issued.** See section9's precise bootstrap-selection gap. A Runtime.progress no-argument call cannot create this trusted new-policy choice from an empty policy file or enumerate the archive. No generic fromArray, request flag, SQL-mirror or latest-policy fallback is proposed.

The control publication native writer retains its companion-owned <=5 constructor and creates its own stateless row/transaction helpers using the same Connection; after capture/current proof it consumes the exact candidate phase result and rechecks its actual native predecessor under the B2 then publication fence. For segmentAdvance it calls ControlSegmentProofWorkflow::rereadInCurrentTransaction(actualClosedProof):void in that already-owned root; that sole caller reruns actual Reader and compares all14. Then transition/binding/candidate insertion and actual commit precede PublicationAcquisition.publish and its internal Publisher. recover delegates directly to the exact captured/pinned candidate; it is not a new signature branch.

## 8. Pure/parser/native decomposition and method budgets

All proposed classes below remain under A or H unless a more specific suffix is written. No new type exceeds10 total declared methods including constructor/magic methods, no ordinary method exceeds5 parameters, and existing accepted large constructors retain only their existing exact exceptions. Budget values are issuance ceilings, not a claim that future code already passes complexity/static analysis. Split a cohesive parser state machine further if needed; do not hide a giant mode switch or context bag to satisfy a count.

| Pure owner | Fixed responsibility and signatures | Ceiling / callers |
| --- | --- | --- |
| AuditAuthorityJsonPreflight.php | assertDocument(string):void; explicit bounded byte scanner. Construct/private state plus <=7 scan/string/container/value helpers. Max1MiB, depth8,32 object members,256 list items,8192 values before recursive decode. No configured budget or deferred UTF8. | <=10; DocumentCodec and fixed control/online wrapper/floor parsers only. |
| AuditAuthorityDocumentCodec.php | decode(string):array exact five-field envelope; hash(string canonicalEnvelope):string exact LP-domain envelope digest. Constructor/private closed/canonical helpers only. | <=8; verifier, fixed purpose parsers and closed provenance shaping owners, never public/application callers. |
| AuditAuthorityDocumentVerifier.php | verify(string canonicalBytes,string expectedPurpose,string expectedIssuerRef,string trustedRaw32Key):array. Fixed three-purpose vocabulary, version1, canonical base64url64 signature, native pure Ed25519; returns detached payload only. | <=7; current/historical fixed semantic validators only. No sign/key-resolution/seed/Clock/DB/path method. |
| H AuditHistoryGroupCodec.php | decode(AuditHistoryTag,string):AuditHistoryNativeGroup; encode(AuditHistoryNativeGroup):string. Exact closed enum/native constructors and flat <=16KiB preflight; tag is not caller metadata. | <=8; only capture serializer and reader. |
| H AuditCheckpointNativeGroups.php | partition(array), segment(array), endpoint(array), preparation(array), complete(array), proof(array), all detach exact named scalar shapes to typed groups. At most constructor+6 factories+3 helpers. | <=10; only owned capture projection/replay parser. No fromArray authority factory. |
| H AuditWorkNativeGroups.php | reservation(array), step(array), presence(finiteActualFacts), end(finiteActualEndpoints); exact scalar groups only. | <=9; capture writer and grammar reader. |
| H AuditControlNativeGroups.php | segment(array), transition(array), acknowledgement(array), publication(array), endpoint(finiteActualFacts); no open key list. | <=10; control capture writer and grammar reader. |
| H AuditHistoryGrammar.php | constructor(fixed live CaptureScope); accept(AuditHistoryRecord):void; finish():void; finite checkpoint/publication grammar helpers. Cannot accept a document-provided phase as the live expected scope. | <=10; writer and reader only. |
| H AuditWorkHistoryReplay.php | constructor(AuditHistoricalAuthority,AuditCheckpointArtifactReplay); verify(AuditHistoryReader,AuditCheckpointCaptureScope,AuditAuthenticatedControlHead):AuditVerifiedWorkEndpoints; bounded offset/forward-backward helpers. | <=10; CheckpointHistoryReplay only. |
| H AuditSegmentHistoryReplay.php | published(reader,currentHead,requestedBinding):AuditVerifiedSegmentEndpoints; staged(reader,currentHead):AuditVerifiedStagedEndpoints; fixed semantic helpers. | <=10; the two replay owners only. |
| H AuditTransitionHistoryReplay.php | verify(reader,head):AuditVerifiedTransitionEndpoints; no D delivery/ack creation. | <=10; ControlHistoryReplay only. |
| H AuditPublicationHistoryReplay.php | verify(reader,scope,head):AuditVerifiedPublicationEndpoints; exact wrapper/transition/segment associations via repeat scans, no growing map. | <=10; ControlHistoryReplay only. |

Pure shape helpers may return detached closed scalar arrays in private sensitive storage rather than a class for every field. The transport record is a final closed tag+bounded bytes object, constructed only by the fixed serializer; its tag/bytes are data, never grant. Its getters are available only to grammar/reader/semantic owner source sites. No native group accepts arbitrary caller fields or arbitrary recursive canonicalization.

The proposed native resource owners are:

| Native owner | Fixed surface / ownership | Ceiling |
| --- | --- | --- |
| Custody/AuditCustodyIdentity.php | constructor from verified empty UID witness and installed runtime temp identity; checkConfiguredPath(path), checkArchiveObject(digestCategory,path), checkDescriptor(fixedExpectedIdentity,descriptor). No ext-posix/fchmod and no public credential override. Helpers detach only bounded stat scalars/component identities. | <=10 |
| Custody/AuditNativeFile.php | Internal constructor only from verified plain descriptor; readBounded(cap), writeAll(bytes), seek(offset), truncate(length), flush(), sync(), close(), stat(). No resource getter, caller operation callback or arbitrary stream wrapper. All native warnings have owned finite cleanup. | <=10 including constructor/destructor; source guard prohibits clone/serialization, resource is private sensitive storage and construction never deserializes. |
| Custody/AuditPrivateHistoryFiles.php | constructor(custodyIdentity); create():AuditPrivateHistoryFile. Exact directory/file/unlink lifecycle with <=6 private create/identity/cleanup helpers; no caller path/mode/provider. | <=10 |
| H AuditPrivateHistoryFile.php | Final anonymous one-invocation descriptor owner; appendChunk(string), readExact(int), seek(int), position():int, finishWrites(), close(); private constructor/destructor plus one bounded native helper maximum. No fsync, pathname or stream getter. | <=10 |
| H AuditHistoryWriter.php | constructor(privateFile,grammar); append(AuditHistoryRecord):void; finish():AuditSealedHistory; checked count/hash/chunk helpers. The append type/tag set is closed and guarded; no sign or authority result. | <=10 |
| H AuditHistoryReader.php | constructor(sealedHistory,expectedScope); checkComplete():void; next():?AuditHistoryRecord; position():int; seekRecord(int):void; finishPass():void; <=3 fixed prefix/count helpers. seekRecord accepts only previously observed bounded offsets held by the semantic owner, never file path or external arbitrary offset. | <=10 |
| Custody/AuditControlLockedSession.php | constructor(configuration,custodyIdentity); open():AuditControlFloorSession. Existing0600 inode/nonblocking exclusive lock; no SQL/provider. | <=10 |
| Custody/AuditControlFloorSession.php | readFloor(), readSource(), replaceObservedNow(validatedFloor), publishExact(validatedCandidate), finish():void, plus fixed private write/identity/unlock/close helpers within budget. Only acquisition or publisher owns a session at one time. publishExact belongs exclusively to Publisher caller; it cannot be called by normal acquisition. | <=10 |
| AuditControlSourceHintReader.php | constructor(configuration,custodyIdentity); currentHint():AuditControlSourceHint; publicationHint():AuditControlPublicationHint. Reads only bounded installed source/floor, no Clock/floor lock/permission. | <=8 |
| AuditHistoricalAuthority.php | constructor(configuration,AuditPolicyArchiveReader,AuditAuthorityDocumentVerifier); policyFor(exact signed policyDigest):AuditHistoricalPolicy; verifyRequiredEnvelope(closed purpose-owned historical request):AuditHistoricalEnvelope. No live grant/signature factory. Its request is constructed only by the four named semantic replay owners with exact expected digest/link/purpose/kind. | <=10 |
| Custody/AuditPolicyArchiveReader.php | constructor(configuration,custodyIdentity); policy(digest):bounded bytes; historicalRoot(issuerRef):closed installed record; restriction(fingerprint):closed restriction or actual absence. Internal fixed filename derivation only, no list/glob/caller path/provider. | <=10 |

The generic-looking native read/write methods are private transport plumbing with exact source guards, not application providers, test seams or production callbacks. They never authenticate semantic bytes. File identity/mode/cap decisions come from concrete custodians, not arbitrary user modes. NativeFile close/sync failure guards stay present regardless of Root's calibration result; an unobservable native failure needs an honest qualification disposition.

Opaque captured/result types each have at most10 methods: one guarded constructor/factory, no more than four fixed internal ownership/data accessors, and the four defensive representation/clone controls. Their private payload groups may contain the fixed actual F2 preparation or finite endpoint facts; no path/seed/current-authority serialization. No capability result is reconstructed from transcript bytes alone.

## 9. Assembly joins and verification still required

The two maps agree on the real work types: OrdinaryCheckpointSelection, AuditControlCheckpointSelection, ReservedCheckpointWork, PreparedCheckpointWork, HeldCheckpointCandidate and PersistedCheckpointOutcome. They agree on actual shared Connection/graph state; RR READ ONLY capture versus independent RW proof/mutation; no receipt/D producer; exact F2 projections; tail-backward work authentication; and fresh before/after phases.

Root owns native qualification, the one exact installation selection decision, and complete-packet adoption:

1. Native fflush/fclose actual PHP error observability is exclusively Root's E13 calibration. This map adds no probe/compiler run and claims no failure-path PASS. Unhit or masked errors remain explicit qualification gaps; no Product callback/test flag or false return substitute.
2. Root has selected the exact pure-codec exception in section6.1. Its scope/source allowlist requires bounded independent review/adoption, with no F1 implementation change or current-trust exception. This is a resolved candidate choice, not an unresolved API alternative.

3. The independently owned next-policy selection entry remains concrete and unresolved: policy_file is zero at bootstrap, policy archive names are digest-only and non-enumerable, adopted audit.control configuration has no bootstrap/next-policy digest, and a SQL mirror grants no installation authority. Root must name the installation publisher's actual entry that supplies the exact already archived policy to BootstrapSelection/RootRevisionSelection. No CLI/configuration member or constructor permission is silently invented here. Existing source/floor hints suffice for refresh and exact pending recovery, not initial/new-root selection. This does not reopen the adopted policy shape or archive protocol; it closes their still-unissued constructor origin. Root was notified directly during this consolidation.

The recorder consumer is consolidated as AuditCheckpointMutationAdmission; the publication facade/candidate fact and ordinary refresh/recovery selection constructors are consolidated below with the composition owner. The independently owned bootstrap/new-policy selection origin remains the one concrete installation API choice requiring Root resolution. Their matching scope/phase/one-use constraints above are mandatory, not a later optional enhancement. A mismatch between the two maps is an issuance blocker until Root chooses one exact edge; implementation must not invent a ready port.

Required discrimination includes: >32 acknowledged transitions and many policy/segment/publication ancestors with bounded memory; same per-record cap at last legal/first illegal byte; decoder preflight before recursion; unknown/extra/reordered/missing/truncated tags; trailer/count/EOF mutation; fifth work phase; ASC work with terminal compromise pin; wrong pin/issuer/unrelated compromise; issuer marker added after capture and before locked replay; changed source tip; exact pending/zero floor; all native absence matrices; actual candidate byte mismatch; changed B2/F2/work/publication endpoint; no SQL/seed/provider/artifact signing under floor; every before/after phase result misuse; replay after close/use/clone/deserialization; writer/read/final close failure; actual process death at named scratch/floor/archive points. Native and actual-composition cases, not synthetic metadata, must prove those boundaries.

No test, PHP/Node, Docker/DB, compiler, network, Git or Product command ran in this investigation. Source reads/hash comparisons only; first narrow guessed class paths AuditClosedSegmentProof.php and AuditCheckpointBytea.php were absent and corrected to the actual Reader result/PostgreSqlAuditCheckpointBytes source. Existing accepted APIs were not changed.

残り工程: Root consolidates and independently reviews this exact map with the companion SQL/composition map and measured fault observability, then issues the complete real checkpoint packet to Luna. Full F4/F5/F6 and release gates remain.

Next Action: Root chooses the exact independently owned next-policy installation entry, incorporates measured native fault observability and the separate online/seed/delegate map, and obtains complete-packet independent review including the explicit pure-codec delta before Luna issuance.

Evidence closeout: all40 selected file hashes matched again at completion. The composition investigator confirmed no remaining history-shape mismatch and will pin this final map in its own manifest; this map intentionally does not pin a moving companion draft or create a mutual hash cycle. Hash equality and this bounded source consistency check are not Product tests, native qualification or independent Acceptance.

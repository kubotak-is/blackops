# Pure checkpoint codec calls during control-floor replay — candidate delta r1

Status: **author's source-backed amendment proposal for independent review; not independent review, adoption, Product implementation or native qualification.**

Owner: `/root/f4_shared_source_inventory`. Date: 2026-10-13. C is `.phpunit.cache/f2-orchestration/accepted-integration`; E5 is `.phpunit.cache/f5-orchestration/20261012`; E13 is `.phpunit.cache/f5-orchestration/20261013`. Paths below are relative to C unless qualified. Only this document and its selected-input JSON are written. The original E13 history map remains byte-for-byte frozen at `4527806ba7734c5480dda53a202d1351a8e8e4753a2f1d484658308d2450b2df`.

## 1. Exact reason for the amendment

E5 `authority-filesystem-history-contract-proposal-r2.md:232–246` requires all relevant historical issuer checks under the current control floor, forbids SQL/seed/F1/provider calls there, and samples Clock only after the proof. E13 history map section6.1 already records Root's explicit selection to refine the blanket F1 prohibition: authenticate the original captured F2/candidate bytes using three existing data-only checkpoint codec methods. Copying their shape/hash rules into another parser would create a second artifact implementation; trusting caller/native scalar projections would fail to bind provenance to the original bytes.

The existing three methods do not obtain a trust snapshot, discover a key, sign, verify an Ed25519 artifact signature, sample current time, invoke F2 or perform SQL. Their accepted algorithms may be reused without changing a byte of F1/F2/F3 or accepting their outputs as current trust or a production grant. However, **the cold dependency graph is not unconditionally free of filesystem I/O**: the required Normalizer fallback loads PHP Unicode tables lazily. Root therefore selected a second explicit candidate amendment: a finite composition-time initialization outside either floor. Neither selection is silently treated as already adopted.

`develop/spec/130-audit-control-authority-and-production-provenance.md:839–853` remains the ordering authority: historical restrictions under the floor precede its sole trusted Clock read; the archive never supplies live-grant fallback. The historical/current distinction at lines710–714 and online phase requirements in spec129 remain unchanged.

## 2. Proposed authority wording

Replace the r2 blanket phrase “SQL/seed/F1/provider calls are forbidden here” with this complete, narrow rule, and carry the same clarification into the assembled specification130 history-replay boundary:

> No SQL, F2 operation, provider invocation, seed read, artifact signing, current-online acquisition, current F1 trust lookup/verification, nested floor lock or external delivery occurs while the control floor is held. There is one finite exception to the prohibition on invoking F1: `BlackOps\Internal\Audit\Authority\History\AuditCheckpointArtifactReplay` may call `AuditCheckpointCodec::signingInput`, `AuditCheckpointCodec::decode` and `AuditCheckpointCodec::bytesHash` solely to validate and bind original captured checkpoint bytes to authenticated historical work. It has exactly one direct source call site for each method. It may not call `complete`, any ArtifactSigner/ArtifactVerifier/TrustSnapshot/TrustLookup or anchor codec method, or any Artifact shape/canonical/preflight helper directly.
>
> Before either replay entry calls recursive JSON code, its original byte string must have passed the exact record-category byte cap and the fixed bounded raw preflight. The unsigned entry accepts original bytes, decodes a detached object itself, invokes the one private signing-input site and requires byte-for-byte equality with the original canonical unsigned bytes. The complete entry uses the existing complete checkpoint decoder and domain-separated byte hash; any unsigned projection used for binding also passes through the same one private signing-input site. No caller-normalized object, native digest label, transcript seal or historical signature substitutes for those checks. The helper's signing preimage is local data and is neither a returned authority nor an exposed signing surface.
>
> These codec outputs establish shape, canonical identity, internal hash relations and signature encoding only. They do not establish artifact Ed25519 authenticity, current online trust, an acquired permission, invocation origin or a later phase grant. Every actual phase retains its independently required fresh online/F1 checks after the control floor is released. The control acquisition samples its one composition-owned Clock only after complete historical replay and current restriction checks, never in or before this codec replay. No caller time is accepted.
>
> Before any floor-capable F graph can be used, the actual F assembly must complete the finite runtime prerequisite defined in section4 below outside both floors. This eagerly resolves only the listed existing codec types and warms the already required Normalizer FORM_C implementation with a nonempty decomposed Unicode value and an exact result check. This prerequisite invokes no artifact codec method, reads no authority/seed/configured material and grants no permission. No new extension, dependency, generic class loader, provider or injected callback is introduced. Failure prevents the actual graph from becoming available; the actual assembly's existing sticky failure rule applies.

Only `AuditWorkHistoryReplay` and the F2-association portion of `AuditCheckpointHistoryReplay` may invoke the two replay entries. The frozen map's caller/constructor mismatches listed in section7 must be explicitly reconciled before issuance; this text does not silently modify that map.

## 3. Exact existing callable graph and its meaning

The allowed signatures are the actual public static signatures in `src/Internal/Audit/Artifact/AuditCheckpointCodec.php`:

~~~text
signingInput(#[SensitiveParameter] array $unsigned): array
  // exact return: array{bytes:string,hash:string,preimage:string}
decode(#[SensitiveParameter] string $completeBytes): array
  // exact accepted complete checkpoint shape with 18 fields
bytesHash(#[SensitiveParameter] string $completeBytes): string
~~~

Their only new direct production source inventory row is:

~~~text
Internal/Audit/Authority/History/AuditCheckpointArtifactReplay.php
  AuditCheckpointCodec::signingInput  1
  AuditCheckpointCodec::decode        1
  AuditCheckpointCodec::bytesHash     1
~~~

`bytesHash` internally calls `decode` again. That accepted internal call remains unchanged; it does not create another external call site. The replay owner's shared private input helper owns the signingInput site; its complete entry owns the decode/bytesHash sites. Its already proposed constructor takes only `AuditAuthorityJsonPreflight`; public entries remain `unsigned(string):AuditCheckpointUnsignedFacts` and `complete(string):AuditCheckpointCompleteFacts`.

| Existing source and lines | Reachable checkpoint branch and source conclusion |
| --- | --- |
| `Artifact/AuditCheckpointCodec.php:13–23,46–66` | Shape/relation admission, canonical unsigned body, `blackops.audit.checkpoint.hash.v1` and signature preimage domains, existing complete decode, and `blackops.audit.anchor.checkpoint-bytes.v1`. Core hash/strlen and sodium hex conversion only. `complete:27–42` is not reachable from the permitted entry graph and is not granted. |
| `Artifact/AuditArtifactShape.php:15–91,180–204,236–265` | Exact16 unsigned and exact18 complete members; scalar detachment, checkpoint kind/suite/algorithm, timestamp ordering, partition/segment/digest syntax. Anchor branches are excluded. |
| `Artifact/AuditArtifactScalar.php:17–66` | Exact key set, native integers in the accepted safe range, strings1..256 bytes, nullable previous hash, accepted string validation. It is not a general PHP-reference validator; the new replay entry accepts bytes and builds its own detached decoded values. |
| `Artifact/AuditArtifactRelation.php:10–32,48–50` | First/last/count relations, previous hash presence and the single-record head relation. No I/O. |
| `Artifact/AuditArtifactCanonical.php:12–22,40–59` | Existing JCS encoding, artifact byte ceiling, flat complete-byte preflight before JCS decode/toNative, and finite failures. The trustSnapshot branch is excluded. |
| `Artifact/AuditArtifactWirePreflight.php:9–60` | Length2..1,048,575, root braces, finite quote/escape scan, no nested object/list outside strings, at most18 top-level members. Invalid syntax still passes to the accepted JCS syntax validator, never to an unbounded nested parser. No direct new caller to this helper. |
| `Artifact/AuditArtifactIntegrity.php:10–24,45–67` | Exact complete canonical bytes, unsigned checkpointHash equality, URL-safe/no-padding canonical64-byte signature encoding. It performs **no Ed25519 verification**. |
| `AuditJcsCodec.php:44–219,223–563,577–612,686–855` | Existing scalar/object/list parsing and canonicalization; duplicate decoded keys, UTF-8/surrogates, safe integers, sorting. Its `usort` callbacks are fixed private comparator code, not an injected application/provider callback. Recursive paths are only entered after the applicable bounded raw preflight. `deferUtf8Validation` is never called. |
| `AuditJcsObject.php:7–20` | Fixed decoded object representation preserving string keys. No I/O or authority. |
| `AuditStringValidator.php:12–24`; `Core/Audit/AuditValidationException.php:31–56` | UTF-8, Cc/Cf, NFC and byte bounds. Existing Normalizer resolution at `AuditValidationException:48–49` has the cold-load caveat in section4. |
| `Core/Audit/AuditRecordTime.php:24–35` | Supplied timestamp parsing using reset format `!Y-m-d\\TH:i:s.u\\Z` and explicit UTC, error/roundtrip checks. This is not a Clock read or current-time default. |
| `Artifact/AuditArtifactException.php:13–16`, `AuditArtifactFailure.php`; `Core/Audit/AuditValidationException.php:15–18`, `AuditValidationFailure.php` | Finite exceptions/enums. The owned acquisition still emits a fixed sanitized failure without retaining a lower raw Throwable/document/path. |

The source-root prefix for Artifact rows is `src/Internal/Audit/`; AuditJcs/StringValidator rows use the same prefix, and Core rows use `src/`. Selected hashes cover complete source files, including unreachable branches, while the permission remains limited to the listed reachable checkpoint methods. No public key, seed, snapshot, Clock or provider argument enters these three codec methods. Sodium base64/hex conversion is not native signing. DateTime parsing and in-process parser/cache state do not become signing authority.

## 4. Root-selected prerequisite for the concrete cold-load path

### 4.1 Source evidence

`composer.json:7–10` requires PHP>=8.5 and `symfony/polyfill-intl-normalizer:^1.38`; ext-intl is not required. The lock selects v1.38.0, source `2d446c214bdbe5b71bde5011b060a05fece3ae6b`. The installed dependency source is explicitly hashed as evidence, not assumed to be a committed Product file.

`AuditJcsCodec.php:56–58` and `AuditValidationException.php:48–49` call `class_exists('Normalizer')` with its normal autoload behavior. The fallback's classmap stub extends `Symfony\Polyfill\Intl\Normalizer\Normalizer`; package bootstrap defines functions but does not pre-warm the normalization tables. In installed `vendor/symfony/polyfill-intl-normalizer/Normalizer.php`, normalize(FORM_C) selects C=true/K=false at116–119. A nonempty value reaches canonicalDecomposition/combiningClass loading at154–156 and canonicalComposition loading at170–171. Its getData at373–376 performs `file_exists` and `require`. Merely resolving the class or normalizing an empty string (early return146–147) does not close that lazy data path.

### 4.2 Exact proposed owner and sole caller

New candidate path:

~~~text
src/Internal/Audit/Authority/History/AuditCheckpointReplayRuntimePrerequisite.php
final class AuditCheckpointReplayRuntimePrerequisite
  private __construct()
  public static initialize(): void
~~~

Two declared methods, zero parameters, no retained state, readiness getter, reset, retry, callback or generic class-loading method. No Product implementation is issued here.

The sole production caller is the proposed `src/Internal/Application/ApplicationRetentionRuntime.php::signingTasks()`, after the actual installed-signing parser admits the F configuration and before it constructs/exposes any floor-capable acquisition/signer/task. A single literal initialize call must dominate both ordinary/control F graph construction. No call is added to its constructor, read-only CLI list/help, unrelated plan/purge, or signing-absent branch. The current source has only its existing constructor at31–62; signingTasks is the independently frozen composition proposal's new method, not an already implemented API.

Initialization eagerly resolves this exact **15-type existing Framework codec closure**, using closed literal type identities with no caller/configuration/serialized input:

~~~text
BlackOps\Internal\Audit\Artifact\AuditCheckpointCodec
BlackOps\Internal\Audit\Artifact\AuditArtifactShape
BlackOps\Internal\Audit\Artifact\AuditArtifactScalar
BlackOps\Internal\Audit\Artifact\AuditArtifactRelation
BlackOps\Internal\Audit\Artifact\AuditArtifactCanonical
BlackOps\Internal\Audit\Artifact\AuditArtifactWirePreflight
BlackOps\Internal\Audit\Artifact\AuditArtifactIntegrity
BlackOps\Internal\Audit\Artifact\AuditArtifactException
BlackOps\Internal\Audit\Artifact\AuditArtifactFailure             [enum]
BlackOps\Internal\Audit\AuditJcsCodec
BlackOps\Internal\Audit\AuditJcsObject
BlackOps\Internal\Audit\AuditStringValidator
BlackOps\Core\Audit\AuditRecordTime
BlackOps\Core\Audit\AuditValidationException
BlackOps\Core\Audit\AuditValidationFailure                       [enum]
~~~

The exact new replay owner and its two already named fact types must also be resolved before the floor: `BlackOps\Internal\Audit\Authority\History\AuditCheckpointArtifactReplay`, `AuditCheckpointUnsignedFacts` and `AuditCheckpointCompleteFacts`. These three names are candidate types from the frozen map, not existing source. Their eventual exact implementation must introduce no additional lazy dependency into this codec branch without an explicit reviewed inventory amendment. `AuditAuthorityJsonPreflight` is constructed by the same closed graph before replay and therefore must already be resolved before either floor. The complete authority/parser graph has its own assembly inventory; this is no blanket claim that every future Authority class is preloaded by these18 names.

Then resolve `\Normalizer`, call exactly:

~~~text
\Normalizer::normalize("e\u{0301}", \Normalizer::FORM_C) === "\u{00E9}"
~~~

Require exact string equality and successful prior type resolution. Catch escaping failures at this owned assembly boundary and reconstruct `AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable)` without a previous/raw cause. These failure types are existing Orchestration types and are resolved outside either floor. Never substitute ASCII or empty input, a result cast, a normalization-disabled mode, an optional-success branch, a new ext-intl requirement or a custom Normalizer implementation. Dependency/class resolution is allowed only here before the locks, not as a general exception during replay.

### 4.3 Remaining lazy paths and limits of the source conclusion

For the inspected v1.38.0 **FORM_C** path, successful nonempty initialization sets D, cC and C. Subsequent normalize(FORM_C) does not enter getData because the relevant null guards are false. KD is reached only with K=true; rawD/rawKD are accessed only by getRawDecomposition, which the selected codec graph never calls. No remaining lazy Unicode-table path was found in this fixed FORM_C branch. The exact three data files are part of the selected-input hashes. The fallback still reads its fixed mbstring compatibility setting and may save/restore legacy encoding state in its own code; no seed/Clock/SQL/provider authority is introduced by that dependency behavior.

The existing Framework classes must likewise be resolved before the floor; a `::class` constant alone is not eager resolution. Once loaded, the fixed default JCS instances and non-overridable private comparator contain no new class-selection seam. Native built-in classes/functions (DateTimeImmutable/DateTimeZone/JsonException/RuntimeException, hash, PCRE, JSON, sodium conversions) are not new Framework providers. No cold-process execution, filesystem trace, native extension test or warm-up result was run by this author. A successful source argument is conditional on the exact initialized dependency graph and must be qualified by section8, not generalized to arbitrary autoloaders, replacement dependencies or all PHP internals.

## 5. Bound every actual material read before decoding

The frozen history map sections2/3 are unchanged. Its reader admits the known uint8 tag and uint32 payload length against the tag-specific maximum **before** allocating/reading the payload; unknown tags, invalid zero lengths, truncation, count/hash/EOF violations fail. Native bytea resources are consumed in <=16KiB chunks with cap+1 detection before F5 normalization. The pure codec reads no stream/file/SQL value itself; only original bounded strings reach it.

| Material and exact tags | Bound and next admission |
| --- | --- |
| 30 CompleteCandidateBytes; 34 PersistedCompleteBytes | Each <=1,048,575 bytes. Replay category check plus Authority raw preflight precedes the F1 decoder, whose own flat preflight enforces <=18 scalar members/no nested containers before JCS. Only original complete bytes are hashed. |
| 32 PreparedUnsignedBytes | <=1,048,575 bytes. Dedicated replay entry uses the fixed Authority raw preflight before JCS decode/toNative; then exact16 scalar F1 normalization and canonical original-byte equality. No arbitrary caller PHP array reaches signingInput. |
| 2 AcceptedAuthorityBytes; 74 PublicationDocument; 77 CandidatePublicationDocument | Each <=1,048,576 bytes; same explicit bounded Authority raw scan before recursive JCS, followed by its own exact wrapper/semantic rules. No checkpoint codec permission for these tags. |
| 27/29/50/54/66/69 signed provenance envelopes | Each <=65,536 bytes; fixed five-field envelope plus bounded preflight and the existing proposed purpose-specific verifier. No checkpoint codec permission for these tags. |
| 4 FloorHint and actual installed control floor/historical root/compromise files | <=4,096 bytes per object; actual native file reads detect cap+1. File custody, semantic current checks and lock ownership remain in their original owners. |
| Enumerated native/header/endpoint groups | Each <=16,384 bytes, closed flat scalar group only. Decoder checks exact native schema; no generic metadata, array/list/object/member extension. |
| 6/20/22/70/78 absence markers; terminal255 | Exact zero only for these absence markers; trailer exactly48 bytes plus physical EOF. They cannot carry artifact material. |

The Authority raw preflight has fixed depth8, <=32 members/object, <=256 entries/list and <=8192 value tokens, with no BOM/CR/LF/trailing bytes, before recursive decoding. Root container/value counting is the existing r2 rule. JCS then owns complete syntax, duplicate decoded keys, UTF-8/surrogates, safe integers and canonical equality; no deferred-UTF8 mode. Unsigned nested/malformed objects may fail later exact16 F1 shape admission, but their recursive parse was already bounded. Complete artifacts additionally have their accepted stricter flat preflight.

The codec's internal domain frame is a deterministic in-memory prefix plus the already bounded bytes; it performs no new material read. Accepted scalar bodies reduce to fixed16/18 members with <=256-byte strings before canonical encoding. Parser working copies and fixed Normalizer tables are finite per-process/per-record overhead, not an issuer/history collection. Histories themselves remain uncapped and streamed; no successful prefix, ancestry count limit or total-byte cap is added. The anonymous descriptor may be reread for final exact fenced equality and is checked closed at its original phase boundary, never exported as a permission or seed handle.

## 6. Exact guard changes required for this candidate

`tests/Architecture/AuditArtifactArchitectureTest.php:196–241,2517–2535` currently excludes every new History owner and pins all outside F1 calls. Preserve every current row/count and all19 Artifact type declarations. Add only the replay owner's exact Codec reference row and the three method counts in section3. Existing internal operation inventory at1971–2003 remains unchanged. Direct calls to ArtifactCanonical/WirePreflight/Shape/Integrity stay forbidden outside their current Artifact owners.

The new runtime prerequisite needs a **separate non-invocation class-resolution exception**, not another F1 method owner. Pin only its exact listed literal class/enum resolution operations in initialize, with no runtime-provided name or callback. Current `hasArtifactReference` also treats class_exists callbacks as forbidden artifact references (:1541–1577); adding an allowed-types row alone does not authorize this prerequisite. Root must explicitly amend that one resolved-AST case and its literal/count mutation tests. No exception applies to the whole Authority/History namespace, arbitrary new class references, class strings from configuration, reflection, dynamic methods or another caller. The prerequisite may invoke no Codec/Signer/Verifier/Trust static method at all.

Pin the sole RetentionRuntime.signingTasks -> prerequisite.initialize site and require it before every control/online floor acquisition edge in the real F assembly. Pin replay's two caller types, its original-string parameters, preflight dominance, source site counts and fixed result factories. Keep negative guards for extra F1 call, complete/anchor/sign/trust/Clock/DB/provider/seed/nested lock, altered time ordering, caller-supplied normalized object, raw authority result construction, and removed/late/empty initialization. These are test obligations for an issued worker packet; no architecture test was run here.

## 7. Exact frozen-map joins and method budgets Root must reconcile

These are identified explicitly without modifying the frozen map or silently inserting a helper:

| Join | Exact issue and bounded correction needed before issuance |
| --- | --- |
| Raw preflight caller | History map316 gives ArtifactReplay a Preflight constructor and both bounded entries. Map397 limits Preflight callers to DocumentCodec and control/online wrapper/floor parsers, omitting ArtifactReplay. Add the exact ArtifactReplay preflight call edge in the assembled candidate; do not widen to every History type. |
| Direct F2-association replay dependency | Map316 names CheckpointHistoryReplay as a direct ArtifactReplay caller, but its exact constructor at359 lists Reader, WorkReplay, SegmentReplay, HistoricalAuthority only. Add an explicitly adopted fifth ArtifactReplay collaborator or choose a concrete existing delegated edge; no hidden service lookup/static state. A fifth collaborator fits the declared <=5 parameter ceiling, but it is not present in the frozen signature. |
| Native descriptor representation versus count | Map346 requires safe debug, rejecting deserialize and non-cloneability for every descriptor; section7.1/8 describe four representation controls. NativeFile at417 already has ctor + eight operations + destructor =10. PrivateHistoryFile at419 has ctor/destructor + six operations =8, with at most one additional helper. Adding all four magic methods would make14 and12 before that helper, respectively. NativeFile instead mentions source guards/private sensitive storage. Root must expressly reconcile whether each descriptor's existing proposed guarded-storage policy satisfies the runtime representation requirement, or revise its cohesive surface. It cannot silently add four methods, omit cleanup, or label a source-only clone guard as a runtime rejecting clone method. |
| Replay and prerequisite ceilings | ArtifactReplay's ctor+two entries+at most six private helpers total at most9; this delta adds no new helper beyond that allowance. Prerequisite has only private ctor and initialize =2, no added constructor argument to RetentionRuntime. Its new fact types retain their own <=10-method controls. These counts fit; exact helper bodies/declarations still require the final issued inventory. |
| Service exception already explicit | PublicationAcquisition's ctor+six entries =7 and the map expressly allows at most three representation methods with source clone guards. That is an explicit service-budget choice, not permission to copy the same exception silently to owned file descriptors. |

New required literal resolution of the three named proposed replay/fact types in section4 adds no methods to them. Their exact paths/names and inherited dependency closure must be pinned when Root assembles the full map; there is no implemented Product source from which to certify those future bodies now.

## 8. Independent review and later proof obligations

Independent review must assess both deltas together: the finite three-call historical exception and the explicit pre-floor initialization. It must also resolve the exact joins in section7. Required later execution evidence includes:

- A fresh process using the actual supported Composer polyfill path, without requiring ext-intl, executes actual F assembly. Record that listed classes and the three FORM_C tables initialize before either floor; warming only an empty string or moving initialization after the lock fails a discriminating case. Exercise valid non-ASCII checkpoint strings so an ASCII-only fixture cannot conceal the normalization path.
- Once the actual floor is held, selected codec replay—including finite invalid-input/exception paths—adds no unresolved listed class load or Unicode-table file read, SQL, seed, provider, online acquisition, artifact signing or current-trust assertion. Fixed archive/custody I/O required by historical authority remains in its separate already allowed owner. A cold process and file/lock timing evidence must distinguish “already loaded before the test” from actual initialization by F assembly.
- All three static call sites are exact; missing/duplicated/dynamic/aliased extra forbidden calls and a sibling History caller are rejected. A canonical fake64-byte signature can pass data codec admission but must never thereby pass actual later fresh F1 verification.
- Last legal/first illegal material byte bounds, complete19th-member/nested input, unsigned depth/member/list/value overflow, invalid UTF-8/duplicate decoded keys, wrong original canonical bytes/hash and bounded rereads fail at the required layer. No first-byte prefix or partial history grants success.
- Current compromise changes after capture are still observed in complete locked history replay, Clock occurs only after that proof, floor release precedes SQL/F2/current-online phases, and checked descriptor closure precedes seed/sign or phase return.

Cold-process/native ownership, commands, traces and resources remain Root's responsibility. No PHP, compiler, Docker, DB, tests, network, Git or Product command ran for this document. Reads of the actual optional fallback revealed the lazy-load issue; source inspection after Root's selection found no additional lazy FORM_C table path beyond the explicit preload. Narrow initial guessed paths under `docker/` and `src/Internal/Retention/RetentionRuntime.php` did not exist; the actual sources used are root Dockerfile and Internal/Application/ApplicationRetentionRuntime.php. No credential or .env was read.

Release Documentation Impact: none from this ignored author proposal; no public API/Capability/Command/Configuration/source/artifact is changed or delivered. No release readiness is claimed.

残り工程: Root reconciles the finite caller/constructor/descriptor-budget joins, obtains independent review of both explicit deltas, incorporates them into authority and the complete worker packet, and qualifies the actual implementation/cold-process boundaries. Full F4/F5/F6 and release gates remain.

Next Action: Submit this candidate with its immutable selected-input manifest for independent review; keep the original4527806b map frozen and record any adopted corrections separately.

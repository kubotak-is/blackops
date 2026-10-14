# Online/delegate import correction r2 — CIR-01, CIR-02 and PI-04

Status: author amendment for independent review, not adoption, implementation or qualification. Owner: `/root/f5_native_contract_plan`. This changes only the finite construction, comparison and configuration joins identified by authority review `f6ab5e96d5ac3ef42f8c1381f3bedb946d83abb3563f3add988109780aa80445` and mechanical inventory `ff20b23aa9705a0b826839deb6d1546f8c0a9c7a24a163d308d6d3b87102f3d5` / `83d0b5d813189f0b5455024202170bc2cfd54f6704b09f1867d20af9c00d11c5`.

The original online map `4b3d173075ccc83bd462dc09e60a65954c5c40cc1fa6be06acb7d57dddff1c4b` and checkpoint/history/installation maps remain immutable. This author edits no adopted authority and changes no wire/SQL/seed/Clock/floor protocol. The amendment supersedes only the named rows/callers below. Root-reported starting C base is `ba026679c258a48414e5dd39d3551d9c1913cb51`; no Git query was performed. P is `/home/kubotak/projects/blackops`; C is its accepted-integration checkout; E13 is `.phpunit.cache/f5-orchestration/20261013`. All commands use P. Only this file and its selected-input JSON are written.

The separately authored history closure is pinned at `checkpoint-history-type-closure-r1.md` SHA256 `67af749fc953f0ffbe9da9fdd3ecd0938d15fe29b87320abc6d230fe59bba817`, inputs `7390836f0af35987d4ac6681efff16c38c98c3c0506c94b37f85853c19fbc854`. Its exact type/reader/preload changes are a companion candidate awaiting independent review, not authority inferred from this author amendment. Its section2.5 supplies the closed comparison group names used below; its section5 supplies the two capsule-loan edges.

## 1. CIR-01: one concrete pre-floor construction graph

Choose `ApplicationRetentionRuntime::signingTasks` as the sole constructor owner of **both** `AuditOnlineDocument` and `AuditOnlineFloorCodec`. `AuditOnlineAcquisition` receives the constructed FloorCodec as its fifth argument and creates its fixed `AuditOnlinePayload($configuration)` itself. This removes the inaccessible-Preflight edge without a sixth argument, dependency bag, accessor or factory callback.

The exact new assembly sequence, after installed configuration admission and the already selected real operations/C3 graph, is:

```text
ApplicationRetentionRuntime::signingTasks
  actual operations.clock; the same installed OnlineConfiguration
  actual shared CustodyIdentity, Preflight, DocumentCodec and DocumentVerifier
  existing AuditCheckpointReplayRuntimePrerequisite::initialize() exactly once
  AuditOnlineRuntimePrerequisite::initialize() exactly once
  AuditSigningFiles(identity)
  AuditOnlineDocument(onlineConfiguration, documentCodec, documentVerifier)
  AuditOnlineFloorCodec(onlineConfiguration, commonPreflight)
  AuditOnlineAcquisition(onlineConfiguration, operations.clock,
                         signingFiles, onlineDocument, onlineFloorCodec)
    -> new AuditOnlinePayload(the same onlineConfiguration), exactly once
  AuditCheckpointOnlineChecks(acquisition, onlineConfiguration)
```

Existing common-parser construction may precede the two initialization calls; **both initializations and all the displayed constructors finish before either floor can be acquired**. No constructor acquires a floor, samples a Clock, reads a seed, invokes a provider or asserts current authority. The actual common Preflight object is passed directly to FloorCodec; it is not obtained from Document, Settings, Configuration or a container. Shared DocumentCodec/Verifier remain the already selected concrete owners. The fixed runtime source guard checks those actual argument identities and order, not just type names.

Exact surfaces and source callers:

| Path relative to src/Internal/ | Complete affected surface / unchanged remainder | Methods / maximum ordinary arguments |
| --- | --- | --- |
| Audit/Signing/AuditOnlineDocument.php | `__construct(AuditOnlineConfiguration,AuditAuthorityDocumentCodec,AuditAuthorityDocumentVerifier)`; `authenticate(string):array`; private `envelope(array):void`; R4. Only RetentionRuntime.signingTasks constructs; only Acquisition.source calls authenticate. The old “Acquisition constructs” row is withdrawn. | 7 / 3 |
| Audit/Signing/AuditOnlineFloorCodec.php | `__construct(AuditOnlineConfiguration,AuditAuthorityJsonPreflight)`; `decode(string):array`; `advance(array,array,array,string):string`; private binding/epoch/canonical/invalid as r1. Only RetentionRuntime.signingTasks constructs; Acquisition.floor alone calls decode/advance. | 7 / 4 |
| Audit/Signing/AuditOnlineAcquisition.php | `__construct(AuditOnlineConfiguration,ClockInterface,AuditSigningFiles,AuditOnlineDocument,AuditOnlineFloorCodec)`; acquire/source/floor/failure and R4 unchanged. Constructor stores the supplied five dependencies and creates exactly one private Payload from the same configuration. It does not construct FloorCodec. | 9 / 5 |
| Audit/Signing/AuditOnlinePayload.php | `__construct(AuditOnlineConfiguration)`; current/scope/snapshots/selected/interval/exact/invalid unchanged. Only Acquisition.__construct constructs; Acquisition.floor alone calls current at its actual locked Clock sample. | 8 / 3 |
| Application/ApplicationRetentionRuntime.php | Its already proposed six methods remain six; signingTasks performs these literal new expressions. No extra composer, service registration, scheduler method or eager constructor work. | 6 / unchanged |

`R4` retains all four declared representation controls: empty safe `__debugInfo`, rejecting `__serialize`/`__unserialize`, and private noncloneable `__clone`. No method is omitted from the ceiling count. This constructor correction adds no callable to the preloaded graph and removes the old Runtime-to-Payload constructor edge. It does not override the companion history closure's separate sixteen literal additional resolutions or its four per-capture Reader construction sites; both actual initialization callers remain those frozen there.

### 1.1 Exact Configuration and Settings readers

`AuditOnlineConfiguration::settings()` has exactly five direct call sites. Replace its incomplete old “OnlineAcquisition” reader cell with:

| Configuration.settings caller | Settings reader at that same site | Purpose |
| --- | --- | --- |
| AuditOnlineDocument.__construct | identity(), once | Installed envelope/root binding for source authentication. |
| AuditOnlinePayload.__construct | identity(), once | Whole payload installation/authority relation. |
| AuditOnlineFloorCodec.__construct | identity(), once; minimumEpoch(), once | Exact configured floor binding/minimum. |
| AuditSigningFiles.onlineSource | paths(), once | Fixed installed online source path and identity rules. |
| AuditSigningFiles.onlineFloor | paths(), once | Fixed preprovisioned high-water path/identity. |

Document/Payload/FloorCodec retain only their exact cohesive Settings facts/configuration needed by those fixed methods; no Settings field is a collaborator, Clock, Preflight, verifier, provider or resource. Acquisition no longer reads settings directly. `AuditOnlineAuthoritySettings::fromConfiguration` remains called only by OnlineConfiguration.fromConfiguration; its constructor and identity/paths/minimumEpoch/R4 inventory remains9 methods/max3 arguments. OnlineConfiguration remains10/max3. SigningFiles remains9/max2.

`OnlineConfiguration::seedIdentities()` remains solely an AuditSigningFiles read; bind its exact site to `AuditSigningFiles::onlineSource` for the opened replacement-source versus installed seed-identity exclusion. Configuration admission still checks the complete configured alias set; onlineFloor uses its fixed non-rebindable floor identity. This does not convert the source inode inspected at configuration time into a lifetime pin. Seed content is not read by any of these settings operations.

Normalize the r1 §8.2 spelling `SignatureFiles` to the sole concrete type `BlackOps\Internal\Audit\Authority\Custody\AuditSigningFiles`. No second type or path is created. InstallationComposer continues to construct only the shared ControlConfiguration/SigningFiles/DelegateCustody publication graph and its already selected replay initialization; it has no OnlineAcquisition, FloorCodec, Payload, online initialization or checkpoint seed opener.

One exact shared-control reader accompanies the pinned history type closure: `AuditHistoryInstallation::__construct` reads `AuditControlConfiguration::identity()` exactly once to copy installationRef/authorityRef. Only the two actual composers invoke HistoryInstallation.fromComposition(schemaName,the admitted ControlConfiguration,the same GraphState); its private constructor retains that graph without a getter. ControlConfiguration remains10 methods/max4 arguments. This grants no other Configuration reader, descriptor access or successful authority.

## 2. CIR-02: actual closed comparison data and original identity

The two observations are internal facts from a currently owned write root. They are not independently reusable grants. They retain the original VerifiedEndpoints object by identity, a detached bounded copy of the **actual** native rows just compared, and the particular root generation in which that comparison happened. They never infer success from a private constructor alone.

### 2.1 Endpoint comparison access and checked closure

Both `Authority/History/AuditVerifiedCheckpointEndpoints.php` and `AuditVerifiedControlEndpoints.php` retain exactly8 methods: private constructor(scope,finiteFacts,capture), guarded fromReplay(same3), comparison(), finishComparison(array exactActualBoundedRows), R4. No getter or setter is added.

`comparison()` has the exact four-key result `{scope, finiteFacts, capture, actual}`:

- `scope` is the original live typed capture-scope object, not one reconstructed from header/scalars.
- `finiteFacts` is the original authenticated bounded endpoint group of the frozen replay contract. It retains full required original artifact bytes and fixed boundary/head/link facts, never a lifetime issuer/history list.
- Before successful native comparison/checked capture close, `capture` is the original capsule and `actual` is null. This state grants no NativeObservation.
- `finishComparison(actual)` is called only by the fixed actual comparison owner, after its complete original-byte/native-row comparison. It completes the required checked capture close before recording `actual` and clearing `capture` to null. Failure records no usable closed comparison and aborts/poisons through the existing outer owner.
- After success, `capture` is null and `actual` is the detached bounded native group obtained from that actual reread. It is not a copied selector guess, digest-only substitute or caller presence boolean. The original finiteFacts remain the expected basis. A later phase7 final root compares them again and replaces actual only after its own successful comparison; it does not reopen a transcript or pathname.

Here `actual` and the later observation `native` member mean exactly the document-only `CheckpointNativeComparison` or `ControlNativeComparison` shapes in the pinned history closure section2.5, with their original tag/phase/null rules. They are not new Product classes or open metadata arrays. Checkpoint comprises the B2 boundary, exact F2 native6+unsigned/native2+complete slot, reservation plus at most four actual step groups/envelopes/candidate bytes, and the fixed control endpoint group. Control comprises partition/open and phase-required B2 boundary/real proof14, published/staged/transition tails, exact accepted/candidate publication rows/documents, floor and source context. Unlimited histories remain solely in the authenticated transcript/repeat scans; they are never copied into either group.

The names describe a closed **comparison** result: sourceBytes and checkpoint anchor/control floor are retained authenticated expected external context, not a new SQL read or current-floor proof. B2/F2/work/publication members are the actual fenced native read. Original full bytes, immutable accepted document/source equality and required row/absence matches remain mandatory. Each optional block is dictated by the fixed phase; an expected row cannot be dropped. Checkpoint work.steps carries native T28's exact eight columns plus its separate envelope and completeBytes, and candidateFor projects exactly these actual ten Q4 columns for ordinal2. No caller-supplied successful presence or copied expected SQL row can create this result.

The exact checkpoint comparison readers are: MutationAdmission.assertUnchanged and its already assigned fixed comparison helpers; CheckpointHistoryReplay.replay; ControlPhaseGrant.fromAcquisition; **CapturedCheckpointHistory.borrowForComparison**; and **CheckpointNativeObservation.__construct**. The last two are the explicit finite additions. The control readers are ControlPhaseGrant.fromAcquisition, PublicationWriter.recheck, **CapturedControlHistory.borrowForComparison**, and **ControlNativeObservation.__construct**. Each observation constructor adds one direct comparison call, not a namespace permission. Only MutationAdmission finishes checkpoint comparison; only PublicationWriter.recheck finishes control comparison.

The two capsule loans are the separate history closure's fixed tenth method, returning its sealed history only to the matching native comparison owner. The loan requires `expected.comparison().capture === this`, identical held scope, and the capsule's claimed/open state. This amendment imports that exact edge; it introduces no raw stream accessor or alternative replay path. Complete successful replay and physical EOF remain the only endpoint-factory origin.

### 2.2 Root identity survives neither commit nor a replacement root

Refine the existing `Provenance/PostgreSqlAuditProvenanceTransaction::assertCurrent` to:

```text
assertCurrent(Connection connection, ?int expectedGeneration = null): int
```

It still checks the exact supplied Connection, its own active RR read-write root at nesting level1, configured autoCommit=true, shared healthy graph and non-disposed owner. It additionally owns a private monotone native integer generation and nullable active generation. beginRoot reserves a new generation before its root attempt; it never reuses a generation, including failed attempts. Overflow fails before beginning a root; it is not a database/history lifetime cap. Successful commit, abort or disposal invalidates active generation on every path. When expectedGeneration is supplied, equality is required. Return only the currently owned generation, never a Connection/resource/authority object. No extra SQL, Clock, UUID, native lock or transaction is introduced.

Transaction remains8 declared methods, maximum2 ordinary arguments. MutationAdmission.assertUnchanged and PublicationWriter.recheck record its generation before comparison and require the same value after comparison/checked capture closure. A returned NativeObservation's private constructor obtains and retains the still-active generation. Its later data operation passes that retained value back to assertCurrent. An unused observation from root N therefore fails after commit and also after the same owner/Connection starts root N+1; an active level1 alone is insufficient.

Literal assertCurrent callers are those two comparison owners, the two NativeObservation private constructors and their three fixed data methods: Checkpoint.factsFor, Checkpoint.candidateFor and Control.factsFor. No scheduler, ordinary Handler, frame, application or generic row helper gets the generation interface. Existing end-of-root inactive/level0/autoCommit checks, failure precedence, ambiguity disposal and fresh-graph recovery stay unchanged.

### 2.3 Exact observation-to-Grant handoff

The checkpoint observation surface remains private ctor(Connection,Transaction,VerifiedCheckpointEndpoints), fromAdmission(same3), factsFor(Grant), candidateFor(PreparedWork,PhaseAuthority), R4:8 methods/max3 arguments. Control remains private ctor(Connection,Transaction,VerifiedControlEndpoints), fromWriter(same3), factsFor(Grant), R4:7/max3. Only the original MutationAdmission/PublicationWriter factory sites exist.

Each private constructor:

1. Verifies the actual root and obtains its current generation.
2. Reads the matching original endpoint's comparison exactly once, requires `capture === null` and a present exact `actual`, and retains that same scope and endpoint object.
3. Copies only the bounded actual native group into private sensitive storage; retains no capsule/sealed-history/descriptor from comparison. Expected and actual values are not merged permissively.

Each observation has one private monotone use state shared by its data methods. Entering either method consumes that observation attempt before validation; failed relation/root checks cannot leave it available for another grant. Every access reasserts its exact Connection/transaction/generation. Both `factsFor` methods are called only by `AuditControlPhaseGrant::claim` and return **exactly**:

```text
{
  scope: the ORIGINAL AuditCheckpointCaptureScope | AuditControlCaptureScope,
  endpoints: the ORIGINAL AuditVerifiedCheckpointEndpoints | AuditVerifiedControlEndpoints,
  native: the detached actual bounded native comparison group
}
```

The type pairing is exact: checkpoint observation never returns a control scope/endpoint, and vice versa. factsFor reads the supplied Grant's existing facts only to compare that original scope and the fixed purpose/target relation; it exports no Grant, Connection, transaction, capsule, stream or arbitrary metadata. The endpoint member is an identity witness confined to this single Grant.claim return, not a new Grant/authority getter or application result.

`Grant::claim` must perform these actual checks in this order: not previously claim-started; main consumption completed; set claim-started before calling factsFor; exact `returned.scope === private originalScope`; exact `returned.endpoints === private originalEndpoints`; correct typed scope/endpoint pairing and fixed phase/target/native relation. It then returns only its already specified closed authenticated signing facts joined to those actual native bytes. A matching digest, matching scope scalar fields, or a newly constructed equal-valued endpoint does not pass. Its private endpoint is never returned by `facts()`. Claim failure is sticky; a second claim fails. `markConsumed` likewise checks not already consumed/claim-started and exact original scope+endpoint identity before setting consumption.

Grant remains the r1 ten-method/five-argument type. Its constructor/factory have the original paired scope/endpoints/current-policy/current-delegate/now inputs; facts/assertCheckpoint/markConsumed/claim and R4 remain. The exact new behavior is inside those methods; no general authority accessor or observer callback is added. Grant.facts readers remain the existing named OnlineChecks, two observations and DelegateUnsignedEnvelope sites. Publication replay/recovery that creates no frame does not obtain a signing claim merely because Writer performed a native comparison.

### 2.4 Phase7 original-authority check within the method ceiling

Replace the redundant `AuditCheckpointPhaseAuthority::boundPhase()` with this fixed void check:

```text
assertCandidateComparison(PreparedCheckpointWork prepared,
                          AuditVerifiedCheckpointEndpoints endpoints): void
```

Its sole caller is `AuditCheckpointNativeObservation::candidateFor`. The authority remains10 declared methods: private ctor(scope,endpoints,grant,now), fromAcquisition(same4), consume(scope), candidatePermit(prepared), assertCandidateComparison(prepared,endpoints), currentGrant(), R4. The publication authority's separate boundPhase method is unchanged.

The checkpoint authority privately retains the one permit it actually issued. candidatePermit requires the still-unused authority, literal phase7 and `scope.target() === prepared`, constructs the original permit once, retains it and returns it. The permit still holds that same authority/scope/endpoints; add only `assertTaken(PreparedCheckpointWork,AuditCheckpointCaptureScope,AuditVerifiedCheckpointEndpoints):void`, sole caller Authority.assertCandidateComparison. Permit now has8 methods/max3: private ctor, fromAuthority, take, assertTaken, R4.

Permit.take remains called only by CandidateAdmission.admit before its root. It sets a take-started guard, verifies the actual scope's phase7/target/prepared relation and exact retained identities, and only on success marks the take completed and returns its original authority/scope/endpoints tuple. Failed or repeated take never succeeds. assertTaken requires that successful take, those same private scope/endpoints and `scope.target() === prepared`; it returns nothing and does not consume the main authority or create a new permit.

Authority.assertCandidateComparison sets its own comparison-attempt guard before work, rejects a consumed authority, requires phase7, the same private endpoint object, exact target/prepared identity and its actual retained successfully taken permit, then marks comparison complete. It neither consumes the main Grant nor returns it. Its own original scope/endpoint fields, rather than Grant.facts or caller projections, supply the identity comparison. A second comparison attempt fails. Phase7 consume subsequently requires this completed comparison before its normal one main consume/markConsumed; other fixed phases do not acquire this permit.

`candidateFor(prepared,authority)` first consumes the NativeObservation attempt and rechecks its original root generation, then calls that exact void authority check with its own original endpoints. It confirms the detached scope is that endpoint's original phase7 scope and returns only the actual ordinal2 row from its detached Q4 result, or null for a successfully proved absence. The exact row is the frozen ten-column Q4 shape: work_id, ordinal, reservation_digest, previous_step_ordinal, previous_step_digest, authorizing_policy_digest, phase, step_digest, envelope, complete_bytes. Ordinal is exactly2/phase complete_candidate; envelope and complete bytes are the actual native original bytes. It cannot accept a presence flag, scalar hash, arbitrary candidate or another preparation.

CandidateObservation.fromAdmission keeps its existing three arguments and calls candidateFor once; it copies PreparedWork, the same PhaseAuthority and that nullable actual row only. CandidateAdmission stages it inside the root, commits successfully, verifies the ended root, and only then returns it. No root handle escapes in CandidateObservation. Checked-close or commit failure returns no usable fact, poisons the common graph and reaches no seed opener. CandidateSigner then performs its existing fresh online check; recovery verifies original bytes without seed, while absence alone permits its existing seed/F1 path. SignedCandidate remains bound to the **same** original authority. Final Recorder asserts that identity, consumes once, opens a new root and performs the second real reread. Drift blocks append. No SQL root is held over artifact seed/signing.

MutationAdmission.consume no longer calls the removed boundPhase getter. The fixed Recorder/pre-F2 caller supplies its actual typed capture scope; Admission checks its existing fixed phase/input rule through that scope's literal phase/target and then authority.consume(scope) checks exact same scope identity/private phase before returning endpoints. Removing the duplicate phase getter does not weaken either check.

Scope reader deltas are finite: PhaseAuthority.candidatePermit/assertCandidateComparison/consume and CandidatePermit.take/assertTaken may read their held checkpoint scope's phase/target; MutationAdmission retains its existing reads. The observation's candidateFor uses its detached scope only for this fixed relation. Grant.fromAcquisition/markConsumed/claim and the matching observation factsFor use the original scope object for identity/typed pairing; they do not deserialize a scope or choose another phase. The history type-closure packet records these named readers alongside its existing writer/replay readers.

Remove the original NativeObservation.candidateFor -> PhaseAuthority.currentGrant reader edge: the void original-identity check above replaces it. The remaining exact currentGrant readers are OnlineChecks.reservation/key and the five fixed work Frame.fromRecorder factories. PublicationAuthority's three Frame.fromWriter readers are unchanged. The two NativeObservation -> Grant.facts sites are specifically their factsFor methods, one each; candidateFor has no Grant.facts read. This is a caller-map reduction, not a second authority accessor.

## 3. PI-04: exact existing ApplicationAuditConfiguration join

The accepted class currently has one private AuditOutboxConfiguration property and three methods: private constructor, fromConfiguration(array), outbox(). It rejects every audit sibling other than outbox. Therefore the real C3 build would reject an installed signing/control snapshot unless this actual class changes; creating unused new parser types would not close the path.

Retain its final readonly class, its sole outbox property, one-argument private constructor, public fromConfiguration(array):self and outbox():AuditOutboxConfiguration. Add one private static `assertAuthorityObjects(array audit):void`, giving **four declared methods/max1 argument**. No signing/control data, acquired fact, path, public key, dependency or success flag is stored or exposed by this existing object. Its outbox getter and all outbox behavior stay as accepted.

`fromConfiguration` validates the actual complete snapshot as before. The exact closed audit sibling set becomes `outbox`, `signing`, `control`. After requiring an array and rejecting other/numeric keys, invoke assertAuthorityObjects before the existing missing-outbox early return. Keep the original outbox native-integer checks, four accepted settings/defaults/ranges, AuditOutboxConfiguration constructor, pass-through AuditDeliveryException and sanitized ConfigurationInvalid mapping. Do not unset signing/control or validate a filtered reconstruction.

The new private helper is pure structural admission, not an authority parser or a claim that installed file/material configuration is usable:

- When signing is present, require an array with exactly the eight mandatory129 keys (installation_ref, authority_ref, authority_file, authority_key_ref, authority_public_key, minimum_epoch, high_water_file, seed_files) and only optional ordinary. Missing/extra keys and null/scalar/list containers fail; no default signing object is created. The actual ordinary object/ref/kind semantics remain the named Ordinary configuration factory below.
- When control is present, require an array with exactly the twelve130 keys (installation_ref, authority_ref, authority_key_ref, authority_public_key, policy_archive_directory, policy_file, minimum_policy_version, minimum_lineage_revision, lineage_high_water_file, delegate_key_ref, delegate_public_key_fingerprint, delegate_seed_file). Missing/extra keys and null/scalar/list containers fail. Absent control does not fabricate one.
- This helper opens no file, constructs no authority/ordinary identity, samples no Clock and resolves no provider. The syntax/physical-identity/current-authority duties of the strict finite leaf parsers are not transferred to it, and its return is never treated as their success.

Exact full-parser call map:

1. `ApplicationRetentionRuntime.signingTasks` reads the actual immutable snapshot configuration and calls ApplicationAuditConfiguration.fromConfiguration before selecting its raw audit subtree. Signing absence returns no F tasks. Presence calls the real OnlineConfiguration.fromConfiguration(audit,identity) and ControlConfiguration.fromConfiguration(audit,identity) with that same unfiltered audit subtree **before** operations providers/build requirements, target selection, authority acquisition or a seed. Required missing control and malformed semantic values fail in those actual parsers. Full OnlineConfigurationShape/signing mapping validation and actual configured identity inspection remain required even when no eligible segment exists. OnlineConfiguration.fromConfiguration remains the sole optional-ordinary parser caller of ApplicationAuditOrdinarySignConfiguration.fromSigningObject; Runtime reads only its admitted ordinary() result. No identity default is added.
2. `ApplicationAuditControlInstallationComposer.compose` validates the same full audit container through ApplicationAuditConfiguration.fromConfiguration, then invokes the actual ControlConfiguration.fromConfiguration with that same subtree and real CustodyIdentity. It requires the installed signing/control common-root scalar bindings already specified by r1; executing with missing configuration fails. It does not construct OnlineConfiguration, an ordinary identity or checkpoint seed custody, nor inspect online seed contents/online authority currentness. Its existing control material admission and independently installed root agreement remain mandatory.
3. The existing `ApplicationAuditRuntimeComposer` call is retained in its proposed private build method after the composition extraction. It passes the actual full snapshot to ApplicationAuditConfiguration.fromConfiguration and still consumes only outbox(). Its public compose and composeForCheckpointProduction share that build. No successful authority is inferred from the outbox configuration, and no alternate snapshot/filtered array circumvents the strict lazy leaf calls.

Those are the three fixed production call sites for ApplicationAuditConfiguration.fromConfiguration; tests are separately scoped. Help/list still does not construct any of these graphs. Unrelated retention plan/purge remains outside scheduler() and signingTasks, so installing F adds no signing file/provider/build requirement there. Scalar snapshot validation is not confused with actual current online/control permission. The original map's statement that no other existing Product file changes is overridden **only** for this already inventoried ApplicationAuditConfiguration path and its exact guard/test assertions.

### 3.1 One approved existing test-file addition

Root explicitly approved the one additive existing path `tests/Internal/Application/ApplicationAuditConfigurationTest.php`, whose selected accepted source is6637 bytes/SHA256 `69a9e965943b03dbc4673d10b5e097c81cd8dd6ea26a34274ae60d877dfaf487`. It was absent from the original binder's25 existing edit paths. The complete verification inventory must therefore name **26 existing test edits**, while retaining exactly the same35 new PHP paths. This grants no additional file and changes no predecessor ID, partition ownership, whole-discovery rule or native case count. The new methods below stay in the existing `legacy-runtime` partition; this file is not admitted as a new checkpoint-production path.

Retain all six existing test methods and the private property(string):string helper, with names unchanged:

```text
testMissingAuditAndOutboxUseOneAcceptedConfiguration
testClosedNativeIntegerConfigurationIsPassedToC1
testEmptyOutboxObjectUsesAcceptedDefaults
testEveryC1MemberAcceptsItsClosedBoundsAndRejectsOutsideThem
testEveryC1MemberRejectsNonNativeScalarAndArrayValues
testUnknownParentsMembersNullAndNonNativeValuesAreConfigurationFailures
property
```

Their exact retained assertions include defaults300/5/1/3600, explicit42/7/3/900, native integer closed bounds lease1..86400/maxAttempts1..32/backoffBase1..3600/backoffCap1..86400, rejection of numeric string/float/bool/null/array values, unknown parent/member rejection, ConfigurationInvalid/code configuration_invalid, message `Audit delivery failed.`, and null previous exception.

Add exactly three methods, making ten declared methods/max1 ordinary argument including that existing helper:

| New method | Required distinguishing assertions |
| --- | --- |
| `testInstalledSigningAndControlObjectsPreserveOutboxConfiguration` | Structurally complete signing and control siblings reach the actual ApplicationAuditConfiguration with absent/empty outbox defaults and explicit accepted outbox values unchanged; no filtered snapshot or alternative outbox result. Include the optional ordinary member without creating its runtime identity here. |
| `testAuthorityContainerKeysRemainClosedBeforeOutboxDefaults` | Reject each missing required signing/control key, an extra key, numeric/list keys and null/scalar containers, including when outbox is absent so the early return cannot bypass admission. Preserve the actual sanitized failure/code/previous assertions. |
| `testAuthorityAdmissionRemainsPureAndDoesNotResolveSigningRuntime` | Structurally complete literal configuration with synthetic nonexistent custody paths yields only the actual outbox configuration without file access, provider/build resolution, Clock or acquisition. This verifies the pure outer admission boundary; it does not claim these paths/materials are valid authority. Exact actual-composition/leaf tests separately require semantic failures before provider/selection/seed. |

Use bounded literal local fixture arrays inside these methods; add no eleventh helper/provider method. Existing architecture guards cover the absence of I/O/provider/Clock/authority construction in the pure helper. Real lazy composition tests, already within the35 new-path union, remain mandatory for strict semantic admission and real origin; the pure test cannot replace them.

## 4. Exact affected files, guards and discriminating cases

No new Product type/path is introduced by this amendment. The affected proposed paths are the four Online construction types, OnlineConfiguration/AuthoritySettings, SigningFiles, CheckpointPhaseAuthority/CandidatePermit, both NativeObservations, both VerifiedEndpoints, ControlPhaseGrant, Transaction, MutationAdmission and ControlPublicationWriter already named above. CandidateObservation and CandidateAdmission retain their signatures and gain only the named checks/order. The already proposed ApplicationRetentionRuntime and InstallationComposer keep6 methods; existing ApplicationAuditRuntimeComposer keeps its proposed6; existing ApplicationAuditConfiguration becomes4. All complete surfaces retain max10 declared methods, max5 ordinary arguments, complexity15 and512M; future source gates must measure these, not infer them from this author table.

Update only the already issued exact caller/constructor/property maps in the proposed AuditCheckpointProductionArchitectureTest, AuditProvenanceArchitectureTest, AuditAuthorityArchitectureTest and AuditAuthorityHistoryArchitectureTest, and the selected existing AuditDeliveryArchitectureTest, AuditCheckpointArchitectureTest and ApplicationAuditAuthoringArchitectureTest. Guards must detect moved/aliased/dynamic/first-class/extra constructor calls, an extra settings reader, another common Preflight instance, missing/late initialization, a missing scope/endpoint identity comparison, an exposed authority/resource getter, a dropped one-use/root-generation check, restored boundPhase plus an eleventh method, or another source creating/consuming a permit/observation. Exact enum/target/owner checks cannot be weakened to a namespace permission.

No extra F1 or native secret operation is added. The five r1 F1 direct sites remain exactly one each: Payload.snapshots validate; Checks.key key/assertSignedAt; Checks.complete checkpoint verify; CandidateSigner.signPrepared checkpoint sign. SigningFiles retains its four fixed fopen sites; DelegateCustody retains its five purpose-specific sign blocks. Common DocumentCodec/Verifier/Preflight reuse adds no replacement parser, new signature domain, wire field, SQL statement, mutation collection, Clock sample, UUID or cached credential.

Meaningful qualification uses the unchanged35 new-file union, especially AuditOnlineConfigurationTest, AuditOnlineAcquisitionTest, AuditCheckpointOnlineChecksTest, AuditCheckpointPhaseAcquisitionTest, AuditCheckpointCandidateSignerTest, AuditSigningImportNativeIntegrationTest and the real checkpoint/concurrency/ambiguity tests, plus the26 existing test edits including the explicit one-path addition in section3.1. Required affected negatives are:

- Actual accepted outbox defaults and bounds remain; valid full sibling configuration reaches the same C3 outbox graph. Unknown audit sibling, null/list/missing/extra signing/control object keys fail through the existing sanitized boundary. Correct outer shape with invalid semantic ref/key/path/seed/ordinary values still fails in the actual relevant lazy parser before provider/selection/seed; no AppAuditConfiguration success stub replaces it. Actual help/list/plan/purge keeps the frozen laziness cases.
- The literal constructor graph and shared Preflight/Settings readers agree with the table. Cold real assembly reaches both prerequisites before either floor; a direct helper initialization or missing/late/wrong instance is insufficient. Actual operations Clock is retained with no constructor sampling; whole current trust and existing failure precedence remain.
- Actual Q4 ordinal2 absent versus present returns respectively null versus the original ten-column candidate with identical envelope/complete bytes. A malformed/extra step, another preparation, another scope, a different authority or an equal-valued but distinct endpoint object fails before seed. Recovered candidate remains current-verified original bytes with zero seed/sign calls.
- Repeated/failed permit take, a comparison before successful take, repeated candidate comparison, reused observation, reused/early/unconsumed Grant claim and repeated main consume fail. A consumed phase7 authority cannot obtain a new comparison; a phase5/8 result cannot append a phase6/9 record. Guard mutations that remove any identity or state check fail.
- Wrong Connection, disposed/inconsistent root, ended root and a new active root on the same Transaction/Connection all reject a stale observation. Actual checked-close, comparison or commit failure yields no usable returned candidate and no seed access. Real drift between the pre-seed root and final recorder root blocks append; F2/SQL/native observations remain actual rather than mocked origins.

Native holder fault mapping, full-production/DB proof, test-list admission and all execution/CI/resource commands remain in the separately corrected verification packet. This author amendment does not self-approve those tests or repeat an accepted calibration. The other history closure owns its exact missing fact/helper paths; this amendment grants only the stated comparison/scope-reader edges.

## 5. Evidence, release boundary and handoff

Selected source hashes are recorded before/after in the companion JSON. Work consists only of targeted repository/evidence reads, JSON/path/method bookkeeping and hashes plus the two ignored output writes. Two targeted reads returned exit2 for a not-yet-existing ApplicationAuditRecordComposition path and a shortened130 filename; each was corrected to its actual accepted source, with no mutation or runtime result. No Product/test/governance/STATE/Git/PHP/Node/native/DB/Docker/network action or lease was performed. No syntax, complexity, runtime, cold, SQL, native fault, full-suite or CI PASS is claimed.

Named concurrent drift is restricted to Root's P/C STATE and F5 Task checkpoints and adopted C130 installation-entry sections6/9.4. The Task records candidate5e20e792 adopted after independent Green b5ead1c5/df497d16 at2026-10-14T03:13:25+09:00. That completed authority unit names only installer publication ownership and the exact CLI/digest/recovery entry; it does **not** adopt this CIR/PI amendment, the companion Reader/type delta or test/native corrections. Their original selected before hashes and observed after hashes are both retained. Of40 selected inputs,35 are unchanged and exactly those5 Root governance inputs changed. All selected frozen semantic maps and Product/test sources remain unchanged; the two final history artifacts were pinned after their author freeze. No independent Git/commit conclusion is made. The companion records24 unique affected type entries, each within10 methods/5 arguments, and the existing test class's measured seven source declarations plus the exact three proposed additions; these are text/inventory checks only.

This is the proposed exact correction to CIR-01/CIR-02/PI-04, awaiting independent `/root/f3_authority_review`. It changes no released public API, capability, command or documentation source. Stable1.2.1 remains current and1.3 unreleased; no Required release, F4/D acknowledgement, F5 Acceptance or delivery claim follows.

Remaining work: independent affected review; Root's complete literal type/test inventory and durable child packet; Luna implementation and all assigned native/strict/full/same-SHA/export gates; actual F4/Required/current-trust/seal/D-control and remaining1.3 release/documentation work.

Next Action: Root supplies this finite amendment with the separately frozen history and verification corrections to the independent reviewers. The author awaits that review; original maps remain unchanged and no Product work is issued by this artifact.

# Installation policy publication — exact issuance delta r1

Status: frozen candidate implementation contract for Root's complete packet; not Product issuance, implementation, qualification or Acceptance. The selected entry is `blackops audit:control:publish <policy-digest>`. It closes the independent initial/new policy-selection origin left explicitly open by the frozen checkpoint/history maps. It creates no ordinary/control work-signing entry, F1/F2/F3 wire, new archive format, D acknowledgement or normal scheduler new-policy selector.

P is `/home/kubotak/projects/blackops`; C is P's `.phpunit.cache/f2-orchestration/accepted-integration`; E13 is `.phpunit.cache/f5-orchestration/20261013`. All source paths below are relative to C. Root reported starting base `046fa8a1d122d4d031622b6bc880f8fd40f5a990`, then separately reported governance commits509ca1e/070c752 during this investigation. No Git command ran here. Only this Markdown and its input JSON may be written. Root retains authority edits, real online/seed/delegate imports, migration reservation, resources, complete independent review and implementation issuance.

## 1. Retained basis and exact delta

| Input | Fixed identity / use |
| --- | --- |
| E13/installation-publication-entry-decision-r1.md | Root selects the visible lazy command, exact archived digest, independent installation owner and real publication-only success. Full hash in companion. |
| E13/checkpoint-issuance-map-r1.md | `e39f62937df1afb4140072b843ec10d3473de889e660f23c764da9b975188647`; shared same-Connection graph, native rows, transactions, locks,29 DDL, publication writer and guards reused. Frozen file is not edited. |
| E13/checkpoint-issuance-map-r1-inputs.json | `4472243fd865b5f36a4d50e01bc02b16cecfcfcff4c4d664c7067faff995b1d0`. |
| E13/history-capture-issuance-map-r1.md | `4527806ba7734c5480dda53a202d1351a8e8e4753a2f1d484658308d2450b2df`; fixed phases10/11/14/15, exact histories and private capture scope. No tag or record schema is added here. |
| E13/history-capture-issuance-map-r1-inputs.json | `01ad90ad41c98ff72254905c6a5452487bd306bb7f663fe2b991e68e7796bfce`. |
| E13/native-calibration-contract-proposal-r2.md | `847ca0bdfd192a72a631211d5ef3adff24684c2b2117981b2daba7fb1d61f118`; supported observable PHP contract and Linux/glibc exclusive-descriptor disposal premise. |
| E13/native-calibration-contract-review-r2.md | `79530b88` full hash in companion; bounded Green. The separately pinned Green native-custody adoption review f97994ce records the adopted supported contract; neither review is Product qualification. |
| E13/pure-checkpoint-codec-floor-delta-r2.md | `a2dbcfecf79f9a7d9ecd1579ed58ef2f3c06204fc96ecd8b1a4e0f56a694144f`; finite native owners, immutable archive replay durability and exact second initialize caller reused. This delta narrows its compose return annotation to the closed lifecycle Composition below. |
| E13/pure-checkpoint-codec-floor-delta-r2-inputs.json | `163a4f2e5dbb9a10730006cc4449d036bae4e27a4804b547854f674fd2e1cca0`. |
| Current130 §§4–6,9–10;131 §2/§4;105 release boundary | More-specific authority prevails over older shorthand. The narrowly proposed §6 ownership amendment below still requires Root adoption. |

This delta adds the exact installation composer, command/selector/workflow and actual fact constructors. It adds one native committed-row reconciliation method to the proposed publication writer and binds the installer-selected digest through the private recovery capture scope. It removes bootstrap/root-policy selection from the earlier descriptive no-argument scheduler workflow. Root's actual cryptographic/custody leaf constructors remain the same finite imports already assigned to Root; this document does not advertise unimplemented services as available.

## 2. Actual Console path and lazy behavior

The complete path is:

```text
existing project-owned blackops -> Application::console()->ConsoleKernel::run
 -> existing Internal ApplicationConsoleKernel::frameworkCommands
 -> existing LazyFrameworkCommand::execute
 -> ApplicationAuditControlInstallationCommandFactory::publish
 -> AuditControlPublishCommand::execute
 -> ApplicationAuditControlInstallationRuntime::publish(exact argument)
 -> ApplicationAuditControlInstallationComposer::compose(actual snapshot)
 -> ApplicationAuditControlInstallationComposition::publish(exact digest)
 -> actual ApplicationOperationInvocationLifecycle::run(fixed invoke, fixed result-status)
 -> AuditControlInstallationPublicationWorkflow::publish(exact digest)
 -> actual selection -> fixed capture/acquisition -> existing SQL writer
 -> existing AuditControlPublisher pending/source/accepted protocol
 -> private publication result -> finite command output
```

`Application` exposes only the existing http()/console() entry points and keeps its snapshot private. No new public Application/Builder method, application command/service registration, generic Console factory method, deployment PHP script or environment/configuration selector is added. A direct helper script would duplicate that bootstrap boundary; the selected Framework command uses the existing one.

`ApplicationConsoleKernel` creates one additional local `ApplicationAuditControlInstallationCommandFactory($configuration)` in its constructor. Add that factory as the third ordinary argument of `frameworkCommands($factory,$retention,$installation)`. Register exactly one `LazyFrameworkCommand` using the fixed `$installation->publish(...)` first-class callable and the fixed `AuditControlPublishCommand::define(...)` definition callable. These are the already accepted lazy-definition API, never caller-provided business/release callbacks. Factory.publish constructs `new AuditControlPublishCommand(new ApplicationAuditControlInstallationRuntime($this->snapshot))` at that fixed source site; construction of these two small objects does not compose the signing graph. No runtime is cached in the installation factory. The new command is visible even when signing configuration is absent; executing it then fails safely. Read-only help/list must not attempt to make configuration valid by touching files or resolving providers.

The registry row is exactly:

```text
name: audit:control:publish
aliases: []
description: Publish an installed audit control policy by its archived digest.
origin: framework
hidden: false
```

Append its `makeTarget(AuditControlPublishCommand::NAME, description)` row immediately after the existing scheduler rows in `CommandProjectionTargetRegistry::framework()`. Runtime registration uses the same description lookup. `FrameworkCommandNames::all()` already derives from that registry; it needs no hand-maintained duplicate list. The projection reservation registry also remains unchanged: this is an ordinary registered Framework name, not one of its special projection/default commands. Canonical/alias/Operation-command collisions use the existing collision validator. Existing namespace selection automatically gains `audit` and `audit:control` from metadata.

`AuditControlPublishCommand` defines `public const NAME = 'audit:control:publish'`; its constructor retains the provided Runtime and invokes parent::__construct(self::NAME). Its configure method invokes the same fixed define method and description. `define(Command)` installs one `InputArgument::REQUIRED` named `policy-digest`, no default, no array mode, description `Digest of the already archived root-signed control policy (64 lowercase hex).` It adds no command-specific option, alias, JSON mode, dry run, confirmation, path, actor, tenant, key, class, revision or callback. The inherited Symfony help/version/quiet/verbosity/completion options remain their existing behavior. `configure()` calls the same fixed definition method; tests compare the lazy and resolved definitions. Neither definition method composes a runtime.

Actual `LazyFrameworkCommand` invokes its factory only inside execute. Its inherited Symfony run binds and validates required arguments first; missing/extra arguments or unknown options therefore fail before installation factory/runtime composition. Preserve the existing public `ConsoleKernel` sanitized `ApplicationBootstrapException` boundary for those framework binding failures; do not falsely promise this unchanged branch returns Command::INVALID. A stock project entry exits nonzero on that exception. The new command's own malformed-value branch returns INVALID2; owned publication failure returns FAILURE1; actual publication result returns SUCCESS0. No generic LazyFrameworkCommand/ConsoleKernel error behavior is changed to make this command special.

## 3. Argument, result and resource admission

The input is untrusted selection data. Check `is_string`, exact `strlen===64`, then `preg_match('/\A[0-9a-f]{64}\z/', value)===1` before composition. No trim, lowercase conversion, Unicode equivalence, path normalization, URL, arbitrary payload, digest discovery or archive enumeration occurs. Uppercase, whitespace/LF/NUL, wrong length, nonhex, array and object values fail;64 zero hex is syntactically valid and normally fails later because no exact archived policy exists. The raw input never supplies authority, a Clock sample or secret location.

`AuditControlPublishCommand::execute` obtains the one argument and validates it before Runtime.publish. Runtime.publish repeats the exact admission at its closed boundary and sets a monotone invocation-started guard before creating the graph. A second call on that same runtime fails even after healthy success; a repeated command execution gets a fresh runtime from the uncached factory. A new public Application is needed to load changed installed configuration; no live root/delegate-pin reload is implied.

`AuditSelectedControlPolicy::fromArchive` receives only the exact bounded bytes returned by the configured `AuditPolicyArchiveReader::policy(digest)`. It reuses `AuditAuthorityDocumentCodec` raw preflight/canonical envelope decode and shared envelope hash to require the filename selector equal the complete policy envelope digest. It admits only the existing control-policy purpose, audit_control_policy kind and positive JSON-safe policyVersion before recording the two scalar selection facts. This is structural selection, not signature/currentness authentication; complete root-policy/grant/history validation is the later fixed acquisition. Do not duplicate the authority parser or use raw SHA of a wrapper as this envelope digest.

Success is a final `AuditControlPublicationResult` constructed only by the actual Publisher after the accepted floor is durably established or the identical accepted document is freshly reauthenticated under the same lock. It retains policy digest, revision, document SHA, authenticated pending-transition count0..32 and a closed status Published/AlreadyPublished. It is neither acquired authority nor a D result. The workflow compares its policyDigest() with the exact invocation selector before returning. Pending count comes from the authenticated transition/ack chain used by that final publication replay, never a mutable dispatch count or SQL MAX. No acknowledgement is inserted by this command; existing authenticated acknowledgements, if present, remain unchanged.

Human output is two fixed lines, emitted only after that result:

```text
Control policy published.
Pending policy-event deliveries: <actual bounded count>.
```

The first line is `Control policy already published.` for AlreadyPublished. A first real bootstrap has one pending transition. This output reports the publication protocol only; it is not ordinary/control work authority, completed D ingress, scheduler activation or F5 completion. A concurrent later operation may change the count; it describes the publisher's authenticated phase. No artifact/policy bytes, digest, path, seed, delegate key, raw exception or SQL is printed.

Own validation error is fixed `audit.control.publish.input_invalid`; own runtime error is fixed `audit.control.publish.unavailable`. The command's error helper writes only these literal codes to ConsoleOutputInterface's error output, or the supplied OutputInterface when no separate error channel exists. It never formats Throwable/input values or chains previous exceptions. Catch failures only after the lower owner has applied its monotone graph invalidation and cleanup; do not turn uncertainty into successful output. Output transport failure after a committed publication cannot undo that commit; a fresh exact retry uses the recovery rules below.

## 4. Additional installation composition owner

Name the additional owner exactly `BlackOps\Internal\Application\ApplicationAuditControlInstallationComposer::compose(ApplicationConfigurationSnapshot): ApplicationAuditControlInstallationComposition`. Only ApplicationAuditControlInstallationRuntime.publish calls it. This owner is added explicitly alongside, not inside, RetentionRuntime's scheduler task/issuer ownership.

One invocation performs this sequence:

1. Admit the real closed installed signing/control configuration from the actual snapshot. Invoke `AuditCheckpointReplayRuntimePrerequisite::initialize()` once before any acquisition/publication floor. The history lane's pure-codec r2 caller list adds exactly this compose method beside RetentionRuntime.signingTasks; it is a fixed prerequisite, not a configurable autoload/preload provider.
2. Invoke the actual `ApplicationOperationRuntimeComposer::compose(snapshot)` once. Retain its physical Connection, Clock, identifiers, container, build identity and lifecycle. No test-created replacement operations object, service-provided Connection or second database manager is accepted.
3. Resolve the existing optional AuditCatalogProvider once from that actual container, failing on a wrong/throwing provider; construct BlackOps\Internal\Audit\Authoring\ApplicationAuditCatalogRegistry and use its actual lookup():BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup with Framework catalogs. Invoke the frozen `ApplicationAuditRuntimeComposer::composeForCheckpointProduction(snapshot,operations,catalogs)` once. Assert its audit delivery uses operations.connection. Read only audit() for that assertion and closedSegmentReader() for the accepted protected B2 proof owner.
4. Reuse that smallest existing graph even though its private construction also creates the F2 store/admission. The installation composer never calls checkpointStore()/checkpointAdmission(), F2 prepare()/persist(), Clock/UUID allocation for preparation, or any work signer. The two unused private components do not become constructor parameters of the installation workflow. This avoids a second body hydrator/protection provider and avoids another broad composer getter. Exact guard additions are limited to the two named getters and composer call.
5. Construct one AuditSigningGraphState on operations.connection. Construct the same configured provenance schema/row helpers, one actual control-history installation scope bound to that graph, one ordinary write transaction owner and one separate RR READ ONLY capture/read owner as required by their existing construction. Every row/capture/writer/proof object shares this Connection and validated schema. The source/archive/floor custody and one private delegate holder use the same installed configuration and actual operations Clock.
6. Construct the frozen AuditControlSegmentProofWorkflow with the actual existing closed-segment reader and same SQL row owners, even though installation bootstrap/root revision creates no segment advance. Construct the actual SQL PublicationWriter with that proof workflow, the one delegate holder and graph. Construct the actual ControlHistoryCapture, CandidateAcquisition, Publisher and PublicationAcquisition using the frozen signatures. The installation selector gets only installed scope, source hint reader, exact archive reader, native read owner and pure DocumentCodec.
7. Construct the closed installation Workflow with exactly selector, publication acquisition, history capture, SQL writer and graph. It receives no ordinary identity/provider, real sign Operation, evaluator, HandlerInvoker, work recorder, checkpoint selector, control maintenance task or F4/D consumer. Construct ApplicationAuditControlInstallationComposition.fromComposition(operations.lifecycle,workflow,graph) and return that closed composition only to this invocation's runtime.

**Exact lifecycle binding:** Runtime retains the returned Composition, which holds the actual operations.lifecycle, closed Workflow and shared GraphState. Composition.publish first consumes its own one-use guard and calls graph.assertUsable before lifecycle preparation. It then calls the actual `ApplicationOperationInvocationLifecycle::run(Closure $invoke, Closure $failed)` once. The first lexical closure invokes only its captured Workflow.publish(exact digest). The second is `static fn (AuditControlPublicationResult $result): bool => match ($result->status()) { AuditControlPublicationStatus::Published, AuditControlPublicationStatus::AlreadyPublished => false };`. These are fixed internal arguments at the accepted lifecycle API, never callbacks supplied by an application, selector or authority request. The second closure only classifies the actual private Publisher result for existing Connection cleanup; all publication failures throw, and this boolean does not authorize publication, prove rollback or turn pending D work into completion. A future status must change this exhaustive match explicitly.

After any escaping lifecycle failure, Composition invalidates the common graph before propagating the safe owned failure, even if publication may already have committed. The real lifecycle's own failed-invocation cleanup remains intact; it cannot reconnect or reset a poisoned graph. No acquired permission survives into another invocation. A second Composition.publish is refused even after success. This final compose return type refines only the history lane's frozen pure-codec r2 return annotation; the sole composer/method/initialize call edge is unchanged, and that frozen file is not edited.

If composition fails after the common graph exists, invalidate it before best-effort disposal; no partially built graph is cached or returned. Before that point, use the existing operations composition failure boundary and return no installation capability. Each successful or failed invocation is private to its runtime. Actual parser/online/seed/delegate leaf constructor arguments remain Root-owned imports from the complete authority packet, not new installation-service APIs invented here. In particular this command does not need an online artifact-signing acquisition, checkpoint seed provider or F1 signer; only the actual policy/delegate publication holder is wired.

## 5. Exact finite types, constructors and callers

New types are final; readonly is used where one-use state permits. R4 means safe empty __debugInfo, rejecting __serialize/__unserialize and private non-cloneable __clone. Constructors and all magic methods count. Maximum ordinary arguments5, total declared methods10, complexity15, PHP512M; these are proposed budgets, not measured static PASS. Arrays mentioned below are closed native row projections in private sensitive storage, never arbitrary payload/authority bags.

### 5.1 New command and installation owners

| Candidate path | Complete declared surface | Only callers / total |
| --- | --- | --- |
| src/Internal/Application/ApplicationAuditControlInstallationCommandFactory.php | ctor(ApplicationConfigurationSnapshot); publish():Command; R4 | Kernel alone constructs; LazyFrameworkCommand's fixed factory calls publish. Stores only snapshot, no runtime cache. 6. |
| src/Internal/Console/AuditControlPublishCommand.php | ctor(ApplicationAuditControlInstallationRuntime); configure():void; static define(Command):void; execute(InputInterface,OutputInterface):int; private error(OutputInterface,string fixedCode,int exit):int; R4 | Factory constructs. Existing Symfony runs. Only Kernel and self call define. 9. |
| src/Internal/Application/ApplicationAuditControlInstallationRuntime.php | ctor(ApplicationConfigurationSnapshot); publish(string policyDigest):AuditControlPublicationResult; R4 | Only Factory.publish constructs; only Command.execute calls publish. Private snapshot and started state; no graph getter. 6. |
| src/Internal/Application/ApplicationAuditControlInstallationComposer.php | compose(ApplicationConfigurationSnapshot):ApplicationAuditControlInstallationComposition; private catalogs(ContainerInterface):ImmutableAuditEventCatalogLookup; R4 | Only Runtime.publish; fixed initialize edge occurs here before either floor. 6; no constructor/dependency registry. The lookup is the existing Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup, not a new interface. |
| src/Internal/Application/ApplicationAuditControlInstallationComposition.php | private ctor(ApplicationOperationInvocationLifecycle,AuditControlInstallationPublicationWorkflow,AuditSigningGraphState); static fromComposition(same3):self; publish(string):AuditControlPublicationResult; R4 | Composer alone creates; Runtime alone publishes once. Graph check before actual lifecycle.run(invoke,failed); exactly the two fixed lexical closures in §4. 7. |
| src/Internal/Audit/Control/AuditControlInstallationPublicationWorkflow.php | ctor(AuditControlInstallationSelector,AuditControlPublicationAcquisition,AuditControlHistoryCapture,PostgreSqlAuditControlPublicationWriter,AuditSigningGraphState); publish(string):AuditControlPublicationResult; private bootstrap(AuditControlBootstrapSelection):AuditControlPublicationResult; private rootRevision(AuditControlRootRevisionSelection):AuditControlPublicationResult; private staged(AuditControlStagedPublicationSelection):AuditControlPublicationResult; private pinned(AuditControlPinnedPublicationSelection):AuditControlPublicationResult; R4 | Only composition.publish. Owns the four fixed paths in §6. 10. |
| src/Internal/Audit/Control/AuditControlInstallationSelector.php | ctor(AuditHistoryInstallation,AuditControlSourceHintReader,AuditPolicyArchiveReader,PostgreSqlAuditControlInstallationRead,AuditAuthorityDocumentCodec); select(string):BootstrapSelection\|RootRevisionSelection\|StagedPublicationSelection\|PinnedPublicationSelection; private bootstrap(AuditSelectedControlPolicy,AuditControlPublicationHint,AuditControlInstallationRows):BootstrapSelection; private current(same3):RootRevisionSelection\|StagedPublicationSelection\|PinnedPublicationSelection; private pinned(same3):PinnedPublicationSelection; private staged(same3):StagedPublicationSelection; R4 | Composer alone constructs; Workflow alone selects. Selection return short names denote the exact AuditControl-prefixed types in this table; same3 denotes the explicit policy/hint/rows triple. No unlisted alias type exists. Never returns null/no_work. 10. |
| src/Internal/Audit/Provenance/PostgreSqlAuditControlInstallationRead.php | ctor(Connection,PostgreSqlAuditProvenanceSchema,AuditSigningGraphState); read(AuditControlPublicationHint):AuditControlInstallationRows; private partition():array; private openSegment(array exactPartition8):array; private publications(AuditControlPublicationHint):array; private assertAligned(AuditControlPublicationHint,array exactPublications):void; R4 | Composer constructs. Selector alone reads. Constructor creates same-Connection ReadTransaction and the existing stateless PublicationRows. 10. |

The source-confirmed catalog seam is `BlackOps\Internal\Audit\Authoring\ApplicationAuditCatalogRegistry::lookup(): BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup`. Composer.catalogs receives the actual operations container, calls its has/get for the existing AuditCatalogProvider only, rejects wrong types/provider failures, and invokes that registry once. There is no `BlackOps\Audit\AuditCatalogLookup` type. The companion pins both actual source files.

### 5.2 Data facts, fixed origins and one-use projections

| Candidate path/type | Exact fields and surface | Origin / total |
| --- | --- | --- |
| Control/AuditSelectedControlPolicy.php | private ctor(string digest,int version); static fromArchive(string selectedDigest,string exactBytes,AuditAuthorityDocumentCodec):self; digest():string; version():int; R4 | Selector alone passes actual ArchiveReader bytes. Structural fact only. 8. |
| Control/AuditControlInstallationRows.php | private ctor(AuditControlPublicationHint,array partition8,array activeSegment12,array publicationEndpoints,string schemaName); static fromRead(same5):self; target():closed schema/partition/segment group; publications():closed accepted/next/higher-presence group; hint():AuditControlPublicationHint; R4 | NativeRead alone, only after its successful RO commit. At most two distinct bounded publication rows, not history aggregation. 9. |
| Control/AuditControlStagedPublicationSelection.php | private ctor(AuditHistoryInstallation,AuditSelectedControlPolicy,AuditControlPublicationHint,AuditControlInstallationRows); static fromInstallation(same4):self; scope():AuditHistoryInstallation; hint():AuditControlPublicationHint; takeForRead():closed same4 tuple; R4 | Selector.staged alone. takeForRead is single-use and only Writer.readCommittedCandidate calls it. 9. |
| Control/AuditControlPinnedPublicationSelection.php | same private ctor4/factory4; scope(); hint(); policyDigest():string; takeForRead():closed same4 tuple; R4 | Selector.pinned alone. Writer consumes once; fixed recovery factory may read scalar policy/scope/hint afterward, never regain a read/grant. 10. |
| Control/AuditControlPublicationStatus.php | enum Published,AlreadyPublished; no methods | Only Publisher constructs a status from actual final floor outcome. It is output state, never production classification or a caller-supplied mode. 0. |
| Control/AuditControlPublicationResult.php | private ctor(string policyDigest,int revision,string documentSha,int pendingTransitions,AuditControlPublicationStatus); static fromPublisher(same5):self; policyDigest():string; pendingTransitions():int; status():AuditControlPublicationStatus; R4 | Only Publisher after checked completion creates it; Workflow checks digest, Command prints finite status/count. Existing scheduler may discard it. 9. This fills the already named history-map result, not a second success fact. |

`publicationEndpoints` is exactly `{accepted:?Publication13,next:?Publication13,higherThanNextPresent:bool}`. Presence is native evidence, not an authority/classification flag. Each nonnull Publication13 retains the exact12 indexed fields plus exact document_bytes defined by the frozen SQL map. The imported actual PublicationHint contains the bounded original source bytes and exact structurally decoded floor fields: installation/authority identity, configured and stored minima, accepted policy version/revision/document digest, nullable pending revision/document digest, sourceObservedAt and observedNow. SourceHintReader is its only origin; a pending partial source is retained as bytes and cannot be promoted to an accepted head. The native read derives the accepted head from the exact accepted revision/document row, never from a caller-selected head. Pending pins must be the next row; accepted replay pins the accepted row. `target` contains schema name, the exact Q1 partition8 and actual native active segment12; no proof summary is manufactured from them.

Complete the formerly unbound BootstrapSelection/RootRevisionSelection factories as follows. Each private ctor takes `(AuditHistoryInstallation,AuditSelectedControlPolicy,AuditControlPublicationHint,AuditControlInstallationRows)`; each has only static fromInstallation(same4), scope():AuditHistoryInstallation, policyDigest():string, takeForCapture():closed same4 tuple and R4 —9 methods. Only Selector.bootstrap/current respectively creates it from the real read result; only AuditControlCaptureScope.__construct consumes the tuple once after the matching HistoryCapture.bootstrap/rootRevision method supplies that exact typed target and fixed phase. The matching HistoryCapture method obtains only selection.scope() to call `new AuditControlCaptureScope(fixedPhase,selection.scope(),selection)`; Scope then consumes the private facts and verifies exact scope identity. Its typed identity and original fields are retained by the live ControlCaptureScope. Reuse/different graph/mismatched configured schema or actual target fails. These facts are untrusted evidence until locked authentication; there is no fromArray/deserialization/installer boolean or caller-held grant.

For the existing `Control/AuditControlPendingPublication`, replace the two separate expectedFloor/candidateIdentity accessors with a single one-use `takeForCapture()` closed projection. Private ctor is `(AuditHistoryInstallation,AuditControlPublicationHint,?string selectedPolicyDigest)`. Fixed factories are fromFloorHint(scope,hint), used only by normal PublicationWorkflow.recoverExact and internally passing null, and fromInstallation(AuditControlPinnedPublicationSelection,AuditControlPublicationCandidate), used only by InstallationWorkflow.pinned. The latter verifies the exact candidate revision/document identity against the selected actual floor pin and the candidate's native policy digest against the original input before retaining that input digest. Add scope():AuditHistoryInstallation for HistoryCapture.recover to pass the configured scope to the same fixed Scope constructor. ctor+2 factories+scope+takeForCapture+R4=9. The nullable member is optional selection data for those two fixed origins, not permission, work class or a caller-chosen phase. Only AuditControlCaptureScope.__construct consumes that tuple once for the fixed recovery phase; capture and replay get all three retained facts through its fixed recoverySelection projection; no public scalar choice switches acquisition behavior.

Phase14 locked replay additionally requires the actual authenticated candidate root-policy envelope digest equal this nonnull selectedPolicyDigest **before any pending/source/floor write or successful replay result**. A prior unsigned SQL field comparison is insufficient. The original live capture scope retains this expectation, so no new numeric tag, Header field or serialized phase is introduced. The scheduler's exact floor-pin recovery remains unselected and still authenticates the same candidate/current grant; it cannot use this nullable datum to choose a different candidate or select a new root. Only the two factories above may populate the private field.

### 5.3 Reused classes with precise deltas

- `ApplicationConsoleKernel`: method count remains3; frameworkCommands has3 arguments; one new local factory and one explicit LazyFrameworkCommand. No new property/cache or broad service lookup.
- `CommandProjectionTargetRegistry`: same10 declared methods; one exact row/import. FrameworkCommandNames remains1; ReservationRegistry and NamespaceResolver remain unchanged.
- Frozen `ApplicationAuditRuntimeComposer::composeForCheckpointProduction`: add InstallationComposer.compose as the second exact production caller. No signature/method-count change. ApplicationAuditCheckpointComposition.audit()/closedSegmentReader() gain that caller; checkpointStore()/checkpointAdmission() do not. Record/proof components' existing construction callers stay unchanged.
- `PostgreSqlAuditControlPublicationWriter`: retain ctor5 and existing6 methods; add `readCommittedCandidate(AuditControlStagedPublicationSelection|AuditControlPinnedPublicationSelection):AuditControlPublicationCandidate`, for7 total. It consumes takeForRead before an owned fenced read-write root, rereads exact selected native facts and immutable bytes, and returns fromCommitted only after a normal committed read/reconciliation. It makes no signature, new transition, UUID, Clock call or mutation. Sole callers are InstallationWorkflow.staged/pinned. Existing row owners, SQL projection list and timeouts are reused.
- `AuditControlHistoryCapture`: keep its10-method ceiling and six phase methods. bootstrap/rootRevision gain only their two installation Workflow callers; they pass the original typed selection to the fixed Scope constructor, which consumes it once. recover likewise reads PendingPublication.scope() and passes that exact scope/original target to the constructor. Scope getters on the three facts have only these named capture callers; they expose no Connection, descriptor or grant. publish gains only the new installation Workflow's fixed post-commit/staged paths. No callback, generic phase entry or extra capture method.
- `AuditControlCaptureScope`: retain ctor(AuditControlCapturePhase,AuditHistoryInstallation,exact existing target union), phase(), target(), installation() and R4. Its constructor verifies the literal phase/type and exact installed scope object, then alone calls the relevant BootstrapSelection/RootRevisionSelection/PendingPublication.takeForCapture once. It stores detached fixed fields and retains the original typed target identity. Add exactly two guarded getters: installationSelection():array{policy:AuditSelectedControlPolicy,hint:AuditControlPublicationHint,rows:AuditControlInstallationRows}, only for phases10/11; recoverySelection():array{hint:AuditControlPublicationHint,selectedPolicyDigest:?string}, only for phase14. Wrong-phase access throws; other existing target cases have neither projection. Total10 methods, ctor still3 arguments. Only ControlCaptureWriter and ControlHistoryReplay read these two fixed projections. They are observations from actual named owners, not an authority/metadata bag or caller-selected mode.
- `AuditControlHistoryReplay`: retain its fixed verify method, live scope match and phase vocabulary. For phase14 use Scope.recoverySelection and compare its retained selected digest with the authenticated signed candidate before publication I/O. For10/11 use Scope.installationSelection to bind the exact selected policy, expected source/floor and native target through the existing original-byte checks. No new replay method or decoder is added.
- `AuditControlPublicationAcquisition`: same constructor and6 methods; installation's bootstrap/rootRevision/staged/pinned paths gain only their matching candidate/publish/recover calls. `AuditControlCandidateAcquisition` and `AuditControlPublisher` still have one facade caller; no Command/Selector direct call. Publisher gains only the explicit result construction/projection above, not a second signing entry.
- `AuditControlPublicationWorkflow`: remove the descriptive no-argument rootRevision producer from the frozen map; ctor5, progress(), segmentAdvance(), refresh(), recoverExact() and R4 total9. A zero source/floor has no current maintenance authority and fails unavailable. No argument, service, latest SQL row or archive enumeration can select the next root policy. Existing progress/maintenance fail-fast and D-absent behavior remain.
- `AuditControlMaintenanceRuntime`: its initial progress description becomes recovery/current-head/segment work only. It gains no installation runtime/selector import. Ordinary runtime and both Required/F4 continuation gaps are unchanged.

## 6. Literal selection/recovery state machine

Every selector read is evidence only. The dedicated acquisition/publisher must reauthenticate actual files, original signed bytes, current root/delegate restrictions and the entire required historical path under the floor; an unsigned discriminator cannot authorize any branch. The selected input binds the eventual authenticated policy for every branch.

The selector reads `publicationHint()` from the installed bounded source/floor reader, reads the exact requested archived policy, then opens one native RR READ ONLY root for actual B2/publication endpoint projections. It commits that root before any floor acquisition. It uses system:global with the real null/null tenant pair; no caller tenant or partition enters this command. It returns exactly one of four final facts, never no_work.

Priority is fixed:

1. **Pending floor:** select only pendingRevision/pendingDocumentDigest from the intact floor. The actual candidate must exist at that exact installation/revision/document, have the actual accepted predecessor (or genuine first-use null predecessor), and claim the selected policy digest. Any other selected digest, missing exact row, malformed state or higher contradictory branch fails; do not publish another policy, clear pending, sign again or fall back to the accepted source. Return PinnedSelection; later authentication still proves every claim.
2. **No pending, actual unique next candidate exists:** inspect the actual successor of the accepted native head, or the actual bootstrap null-predecessor slot. A mismatching policy digest blocks this request; it is not filtered out. Return StagedSelection for the exact same-policy row after native alignment. This handles SQL-commit-before-pending response loss without allocating another timestamp/signature/transition/revision.
3. **No pending/next, accepted policy equals requested digest:** select the accepted row by the actual floor's revision and document SHA, with exact source/native alignment. Return PinnedSelection. It is an already-published candidate for phase14 recovery, not permission to return immediately. Fresh locked policy/delegate/time/restriction/history validation and checked completion remain mandatory. An older once-published policy that is no longer current is not an already-published success route.
4. **True zero sentinel, empty source, no accepted/next row:** require the genuine configured first-use minima, structural policyVersion1, no native predecessor and actual native B2 open target. Return BootstrapSelection; candidate acquisition then authenticates the signed policy's required null predecessors and matching current segment. The selection fact alone does not grant bootstrap. The command never initializes/replaces a floor or manufactures a B2 partition/segment. Missing actual B2 state is unavailable; initial authority zero does not mean no Audit stream exists.
5. **Positive accepted predecessor, no pending/next, exact next archived policy:** require the selected structural version=acceptedPolicyVersion+1 and aligned actual native open target/predecessor observations. Return RootRevisionSelection; candidate acquisition then proves the selected signed policy's previous policy/head and unchanged accepted current segment/tails. The selector's two-scalar policy fact does not claim those signed relationships have already been verified. Stopped installation root/delegate cutover uses the newly installed grant; the old source/keys prove history only. If B2 has progressed beyond the retained accepted slot, synchronize under the old valid installation before cutover or use separately authorized stopped recovery; this command never folds a segment advance into root revision. Counter overflow, stale/foreign/skipped policy, bad previous head or minimum violation fails.

No command branch treats a mutable dispatch row as the source of selection, ignores an earlier candidate, elects MAX(revision), chooses arbitrary SQL policy mirrors, scans archive files or infers currentness from a local cache. The partial unique successor/bootstrap indexes and native revision PK detect conflict; signatures and external floor establish authority. Current compromise markers are checked for every required historical issuer, with no lifetime/history cap or scalar issuer-list substitute.

### 6.1 New bootstrap/root revision

The fixed Workflow.bootstrap path is HistoryCapture.bootstrap(selection) -> PublicationAcquisition.bootstrapCandidate(capture) -> actual PublicationWriter.rootRevision(one-use authority) -> HistoryCapture.publish(actual committed candidate) -> PublicationAcquisition.publish(capture). The rootRevision path is the same fixed sequence through rootRevision capture/acquisition. Writer.rootRevision admits only the exact phase10/11 authority it was given, consumes it once before its root, and derives the closed root_revision frame from the authenticated selected policy and actual predecessor. No classification/phase boolean or arbitrary payload enters the signer.

Candidate capture/acquisition closes its native RO root before the floor. The real fixed candidate acquisition validates zero/new-root cases, actual installed policy/delegate public-key binding, retained predecessor/history and current restrictions, takes its trusted phase Clock and updates/finishes the floor according to the existing contract. It releases the floor before the actual native SQL/signing owner. No seed is opened/read under that floor; the private delegate holder checks seed-to-public-key binding only at its separately owned non-floor signing edge. The final writer then uses the existing order: B2 fence, publication fence, B2 state/target/neighbor checks, exact policy/publication/transition endpoints. It compares the authenticated capture's exact original bytes and confirms no next candidate/transition before delegate signing and immutable inserts. A staged winner appearing after capture does not authorize reuse of that candidate-creation grant.

The actual write root inserts/reconciles the selected policy envelope mirror, one root_revision transition, matching unsigned policy-dispatch cursor and one complete immutable publication candidate, atomically. The first transition has null predecessors; later transition links the accepted transition/state and preserves lineage/ack cursors. The selected policy must already be durably archived under the reviewed archive protocol; SQL mirror presence is neither root trust nor archive durability. Backlog32 denies creation of transition33. Publication recovery of an already committed candidate does not consume a new backlog slot.

Only after actual normal commit does fromCommitted construct PublicationCandidate. The phase15 transcript then captures that exact immutable document/history, and actual Publisher validates current grant, candidate predecessor, minima, phase Clock and complete expected floor. It performs pending fsync -> same policy inode exact candidate write/truncate/flush/fsync -> accepted floor fsync, preserving required identity/owner/mode and all watermarks. Only its checked finish returns PublicationResult. SQL commit alone is never publication success.

### 6.2 Committed staged candidate

Workflow.staged calls Writer.readCommittedCandidate(selectedStaged). That method verifies same scope/schema, actual inactive autoCommit=true level0, begins the fixed RR read-write root, takes B2 then publication fence, and rereads the exact actual accepted/next identities and native B2 target. Compare complete document bytes, length, ordinary SHA and every Publication13 projection against the selected original row; ensure no contradictory higher row. No fresh signing grant is required merely to read this committed data fact. Normal commit returns fromCommitted; any uncertain root invalidates the whole graph and yields no candidate.

Workflow then calls HistoryCapture.publish(candidate) and PublicationAcquisition.publish, fixed phase15. Current authentication must show its signed policy digest equals the native policy digest that was bound to the original input. The candidate's document SHA and all immutable bytes stay identical. An unchanged no-pending floor may establish pending; an already identical pending/accepted candidate is handled only by the Publisher's specified exact idempotent protocol. A different pending/accepted floor requires a fresh invocation or fails; it is never overwritten by this retained candidate.

This path covers bootstrap candidate revision1 and later candidates, including an already signed same-policy head/segment publication left by the existing publisher. It authorizes only completion of those exact immutable bytes under their real current grant/proof. It does not let the installation command create a segment binding/head refresh or work reservation itself.

### 6.3 Floor-pinned pending/accepted candidate

Workflow.pinned first retains the exact selected scope/hint and calls Writer.readCommittedCandidate(selectedPinned) using the same non-mutating fenced reconciliation. Then it calls PendingPublication.fromInstallation(selection,candidate). That factory stores the original installer-selected policy digest in the closed recovery expectation after verifying the actual floor pin/candidate identity. HistoryCapture.recover passes it to the exact Scope constructor, which consumes it once; PublicationAcquisition.recover delegates fixed phase14 to the existing Publisher.

Locked replay checks the full required immutable history and actual signed candidate against the retained requested digest before any publication I/O. For pending, recover exactly the pinned bytes/revision even when source bytes are old/partial/new; no re-sign, fresh root transition or new head. For already accepted, compare exact floor/source/document and reauthenticate under current installed root/delegate/minima/restrictions/time. Preserve document/revision/transition identity; observedNow alone may advance durably. Return AlreadyPublished only after checked finish. A same digest in an untrusted SQL column, an old source or successful historical verification cannot bypass a current restriction or a failed close/flush/lock.

Maximum safe counters permit exact existing-candidate recovery without allocating a successor. Arithmetic for new candidates is checked before increment. Freeze this narrow PublicationEnd tag79 interpretation for Root's combined adoption: in phase14/15 exact already-accepted replay, accepted_revision and next_revision both equal the actual replayed candidate revision; accepted_head_digest and next_head_digest both equal that candidate's head; next_document_sha256 equals its exact document SHA. higher_than_next_present probes revisions strictly greater than that candidate. This retains the existing positive next_revision encoding and the already-accepted A/P/C byte-equality rule without computing max+1. In every creation/actually-next case, next_revision remains the checked accepted_revision+1. No counter reset, new tag/header or lifetime cap is introduced. This precise endpoint interpretation is a candidate integration delta, not silently adopted grammar.

### 6.4 Race, ambiguity and fresh recovery

No loop inside the invocation changes selection or reconnects a failed graph. If a root/floor changes between hint, capture, locked replay or fence, return a finite failure before new work; the next explicit command creates a fresh operations/database-manager/custody graph and repeats actual selection/current authentication. A native uniqueness conflict whose winner is not visible in the current RR snapshot is unresolved, not a same/opposite-policy verdict. Roll back/quarantine; only a fresh root can admit the visible exact winner.

Unknown SQL commit response invalidates before any floor publication. A committed candidate stays discoverable through the exact successor slot; no success is returned on the uncertain connection. Floor/source write/flush/fsync/unlock/observable-close failure preserves the actual pending/accepted state and denies a result. A fresh invocation recovers the exact pinned or committed candidate; no new semantic transition is created for the same completed policy. Interrupted/stale seed/current grant cannot be replaced by a cached old grant, historic root fallback or manually created authority fact.

## 7. Native read and29-DDL reuse

No migration statement, new table/index/column or F2 wire change is introduced by this delta. Reuse exactly the checkpoint map's29 statements: first28 from6eb42122 plus the B2 selection index. `Version20261012000000.php` remains proposed/unreserved until Root names it. The installation root never executes migrations, build:compile, seeding, floor initialization or archive installation implicitly.

Native read uses the exact existing validated PostgreSQL schema quoting. For the default schema the new observation has these literal predicates over already frozen projections:

```sql
-- Exact Q1 partition8 projection, unchanged column list from the checkpoint map.
WHERE p."partition_id" = 'system:global'
  AND p."tenant_type" IS NULL AND p."tenant_id" IS NULL

-- Exact segment12 projection, actual active_segment_id read from that partition.
WHERE s."partition_id" = 'system:global'
  AND s."segment_id" = CAST(:actual_active_segment_id AS uuid)

-- Exact Publication13, including bounded original document_bytes.
WHERE p."installation_ref" = :installation_ref
  AND p."revision" = CAST(:accepted_revision AS bigint)

-- Actual unique next slot; never filter by requested policy digest.
WHERE p."installation_ref" = :installation_ref
  AND p."previous_head_digest" IS NOT DISTINCT FROM
      CAST(:actual_accepted_head_digest AS text)
LIMIT 2

-- Contradictory later row detection, not a current-head selection.
WHERE p."installation_ref" = :installation_ref
  AND p."revision" > CAST(:actual_next_revision AS bigint)
LIMIT 1
```

These are literal predicate fragments on the existing schema-qualified tables/projection owners, not alternate queries or a claim of execution. The last parameter is the actual existing next candidate revision when one exists; for exact accepted replay it is that accepted revision; otherwise it is the checked next-revision candidate slot. No accepted replay computes an overflowing successor. NativeRead and later capture preserve this distinction in the fixed endpoint cases, not a supplied classification flag. PublicationRows.byRevision/nextHead retain their exact full selected columns; the Read owner's only additional existence query is the last predicate on `"blackops"."audit_f_publication_candidates"`, `SELECT 1 AS "present"`. It cannot be used to return an authority row. Bootstrap uses actual null accepted head and requires no accepted row. Positive accepted hints require matching native revision/document hash and native next.previous_revision/head; pending hints must match the exact next identity. More than one purported next row, missing expected row, bad native encoding or any contradictory higher row fails. The existing PK and partial unique successor/bootstrap indexes support these finite point/range probes; no query-plan or performance proof is claimed.

Publication documents are read using the existing bounded bytea projection/cap+1 admission, never raw unbounded fetch. Native bigint/UUID and canonical UTC encodings are unchanged. Observation returns after actual RO commit with at most two distinct publication documents held; unlimited published/history chains are streamed later by the existing capture/replay owners. NativeRead uses RR READ ONLY and1s lock/5s statement locals, no advisory lock/FOR UPDATE/full protected reader. Writer reconciliation and mutations use separate RR read-write roots with the frozen lock order. No SQL, F2, provider, seed or new native graph occurs under the external floor lock.

## 8. Exact130 ownership amendment for Root

The following is the proposed narrow authority text; this investigation does not edit/adopt it:

> ApplicationRetentionRuntime remains the sole constructor of the scheduler task/control-work issuer relationship and its private work-signing recorder/delegate holder. A separate fixed installation composition is additionally permitted: the visible Framework command audit:control:publish <policy-digest> is registered only by ApplicationConsoleKernel through its fixed lazy command factory and constructs one ApplicationAuditControlInstallationComposer graph for that invocation. The exact64-lowercase-hex argument selects one already archived root-signed policy and is untrusted data, not a path, current grant, payload, class or signing capability.
>
> Only that installation selector may construct the closed bootstrap/root-revision selection facts from independently installed configuration, exact selected archive bytes and actual native predecessor/B2 observations. The dedicated candidate acquisition and actual publisher must authenticate the selected policy and current installed root/delegate binding, all required historical restrictions, minima and expected floor. The initial path requires the independently provisioned zero sentinel; a replacement path preserves the accepted lineage and uses exactly the next root policy with the prescribed stopped installation cutover. Normal scheduler progress and normal control/ordinary acquisition cannot select either path.
>
> This additional graph may own the existing publication-only delegate path to sign the closed root_revision transition and corresponding lineage head. It exposes no work-reservation, artifact-signing, release, F4 frame or ingress-ack capability. Each invocation uses one actual Connection/Clock/custody graph; no application service registration, caller callback, arbitrary sign(payload), direct seed export or reusable authority result is added. The existing SQL-before-floor, exact expected-floor, candidate-before-publication, checked durability and current-restriction protocol remains unchanged.
>
> Repeated invocation first recovers an exact floor-pinned or already committed candidate for that selected policy, or freshly authenticates the exact accepted document. It never issues another semantic root transition for an already staged/published policy, ignores a conflicting successor, regenerates pending bytes, clears pending to the old source, or declares an older policy current. An installation-specific recovery scope binds the selected policy digest to the authenticated candidate before publication writes. Publication success reports only a durable accepted authority document and the authenticated pending policy-event count; it is neither D acknowledgement nor work authority. Missing D consumer remains pending under the ordinary existing activation boundary.

Root also updates §9's explicit first-publication/§10 stopped replacement caller to this exact entry, and keeps the existing supported API/platform limitation.131 needs only the cross-reference that this deployment publication command is outside the real ordinary sign Operation/Required barrier and cannot release artifact bytes.105 needs the unreleased admin command/capability boundary, not a Stable1.2 promise. No broader constructor exception, root trust source, configuration key or ordinary bypass is justified by this amendment.

## 9. Exact implementation path allowlist and guard changes

This is a candidate future worker scope, not permission to edit now. Paths listed as reused imports need only the finite caller/signature changes specified above; other frozen source bodies are reused.

New Product paths from this delta:

```text
src/Internal/Application/ApplicationAuditControlInstallationCommandFactory.php
src/Internal/Application/ApplicationAuditControlInstallationRuntime.php
src/Internal/Application/ApplicationAuditControlInstallationComposer.php
src/Internal/Application/ApplicationAuditControlInstallationComposition.php
src/Internal/Console/AuditControlPublishCommand.php
src/Internal/Audit/Control/AuditControlInstallationPublicationWorkflow.php
src/Internal/Audit/Control/AuditControlInstallationSelector.php
src/Internal/Audit/Control/AuditSelectedControlPolicy.php
src/Internal/Audit/Control/AuditControlInstallationRows.php
src/Internal/Audit/Control/AuditControlStagedPublicationSelection.php
src/Internal/Audit/Control/AuditControlPinnedPublicationSelection.php
src/Internal/Audit/Control/AuditControlPublicationStatus.php
src/Internal/Audit/Control/AuditControlPublicationResult.php
src/Internal/Audit/Provenance/PostgreSqlAuditControlInstallationRead.php
```

The last Result was already named as an unfilled fact by the history map; it must occur once in the combined inventory, not as two types. Previously proposed Product files with exact additive/replacing edges:

```text
src/Internal/Application/ApplicationConsoleKernel.php
src/Internal/Projection/Command/CommandProjectionTargetRegistry.php
src/Internal/Audit/Control/AuditControlBootstrapSelection.php
src/Internal/Audit/Control/AuditControlRootRevisionSelection.php
src/Internal/Audit/Control/AuditControlPendingPublication.php
src/Internal/Audit/Control/AuditControlPublicationWorkflow.php
src/Internal/Audit/Provenance/PostgreSqlAuditControlPublicationWriter.php
src/Internal/Audit/Authority/History/AuditControlHistoryCapture.php
src/Internal/Audit/Authority/History/AuditControlCaptureScope.php
src/Internal/Audit/Authority/History/AuditControlHistoryReplay.php
src/Internal/Audit/Authority/AuditControlPublisher.php
```

Caller-only import paths in the combined packet are exact:

| Reused path | Additional allowed source site, no broad import |
| --- | --- |
| src/Internal/Application/ApplicationAuditRuntimeComposer.php | InstallationComposer.compose -> composeForCheckpointProduction only. |
| src/Internal/Application/ApplicationAuditCheckpointComposition.php | InstallationComposer.compose -> audit and closedSegmentReader only; no other new getter. |
| src/Internal/Audit/Signing/AuditSigningGraphState.php | InstallationComposer.compose constructs once; Composition.publish, Workflow.publish and their owned native roots check/invalidate this same instance. Existing7 methods stay7. |
| src/Internal/Audit/Control/AuditControlSegmentProofWorkflow.php | InstallationComposer.compose constructs the existing proof owner from actual protected reader/rows/graph; no new prove/reread caller. |
| src/Internal/Audit/Authority/AuditControlPublicationAcquisition.php | InstallationComposer.compose constructs; four named installation Workflow branches call only the exact methods in §6. Existing10-method ceiling retained. |
| src/Internal/Audit/Authority/AuditControlCandidateAcquisition.php | InstallationComposer.compose constructs; facade remains its sole acquire caller. |
| src/Internal/Audit/Authority/History/AuditCheckpointReplayRuntimePrerequisite.php | One literal initialize call in InstallationComposer.compose before either floor. |
| src/Internal/Audit/Authority/History/AuditControlCaptureWriter.php | Existing capture body reads only Scope.installationSelection/recoverySelection in their fixed phases; caller and method count unchanged. |
| src/Internal/Audit/Authority/History/AuditHistoryGrammar.php | Fixed phase14/15 already-accepted tag79 endpoint interpretation in §6.3, no new tag or phase. |
| src/Internal/Audit/Authority/History/AuditPublicationHistoryReplay.php | Same exact accepted-candidate endpoint interpretation and higher-row rejection; no new public method. |

SourceHintReader, ArchiveReader and native custody implementations retain their existing bodies and exact publication consumers. Root's imported custody assembly may construct those fixed owners at InstallationComposer.compose only; its explicit final constructor/import manifest supplies their existing leaf types and arguments. No installation reader calls their raw native helpers. The completed combined packet must enumerate those imports, not permit a directory. This lane adds no alternate leaf API. Root's pure-codec prerequisite caller delta explicitly names InstallationComposer.compose. Its sole literal initialize call precedes operations/custody assembly and either floor. Native publication and immutable archive confirmation remain solely under the frozen Publisher/ControlFloorSession consumers; the new selector/workflow/composer receives no descriptor or native cleanup method. Existing accepted PublicApi classes, ordinary Operation/evaluator/handler, F2 Store/Reader internals, F3, generic Console factory, generic LazyFrameworkCommand, application service registration, namespace resolver and project entrypoint need no Product edit.

Extend proposed AuditCheckpointProductionArchitectureTest/AuditProvenanceArchitectureTest with exact file/type/constructor/method manifests and these literal edges. Actual existing guards needing explicit additions are ApplicationAuditAuthoringArchitectureTest (one actual composition/catalog use), AuditDeliveryArchitectureTest (assembly/Connection identity only, zero new delivery calls), AuditCheckpointArchitectureTest (closed composer/reader construction only, zero new Store methods), AuditStoreArchitectureTest (native observation/proof owner), AuditArtifactArchitectureTest (no installation artifact codec/signer/seed caller), PublicApiArchitectureTest/AuditPublicApiTest (zero new markers/interfaces). Guard additions to Authority/History owners include only bootstrap/rootRevision selectors, the fixed recovery expectation and same one-use capture; normal acquisition caller lists stay unchanged.

Mutation cases must reject: alias/import/class-string/reflection/closure/serialized factory entry; application provider substitution; arbitrary command registration obtaining this graph; a scheduler call to the installation selector; a normal acquisition call to bootstrap; input digest converted into permission; wrong expected policy omitted from phase14; same Connection test replaced with structural equality; alternative database manager; callback signer; direct seed/Clock/publication session access; bypassed predecessor or native reread; raw output from Throwable/result; reusable/different-graph selection/capture/authority; installation access to either unused F2 getter or any ordinary/control work signer. Representation controls remain required on facts/descriptors; source guards are exact internal trusted-code ownership checks, not a claim that arbitrary hostile PHP in the same process is sandboxed.

## 10. Meaningful future checks — no PASS claimed

Exact existing test paths for changed assertions/cases:

- `tests/Internal/Application/ApplicationConsoleKernelTest.php`: extend testActivatedProjectionMetadataUsesTargetRegistryAndAliases and testKernelFrameworkCommandsMatchTargetMetadataForEveryField; retain lazy read-only/no-runtime cases and add the selected command.
- `tests/Integration/ApplicationConsoleKernelTest.php`: actual public Application/Console composition, unchanged safe exception boundary and no alternate bootstrap API.
- `tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php`: exact35->36 all rows,29->30 registered Framework names, new complete tuple and renamed exact-count method. Default aliases/hidden row remain unchanged.
- `tests/Internal/Projection/Command/CommandProjectionProducerTest.php`: exact framework union and38->39 three-source fixture rows; all new name collisions remain real existing validation.
- `tests/Internal/Projection/Command/CommandProjectionListCommandTest.php`: embedded fallback visible34->35 rows, exact new command in human/raw/JSON/XML/Markdown; existing hidden total gains one under --all. Namespace audit/audit:control selection, invalid projection fallback and no runtime construction.
- `tests/Internal/Console/ApplicationCommandCollisionValidatorTest.php` and `tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php`: actual canonical/alias/discovered/Operation collisions against audit:control:publish. No command-prefix blacklist or new generic resolver behavior.
- Existing selected Architecture tests and `tests/Consumer/framework-package-export.sh`: exact named Product/migration/test inventory, no wildcard exception. Existing public guides are not used as a substitute for package/native proof.

New meaningful test paths:

```text
tests/Internal/Console/AuditControlPublishCommandTest.php
tests/Internal/Application/ApplicationAuditControlInstallationIntegrationTest.php
tests/Internal/Audit/Control/AuditControlInstallationPublicationTest.php
tests/Internal/Audit/Provenance/PostgreSqlAuditControlInstallationPostgreSqlTest.php
tests/Architecture/AuditControlInstallationArchitectureTest.php
```

Add actual lifecycle cases to ApplicationAuditControlInstallationIntegrationTest: one prepare/finish around the real publication path, closed result-status branch, first graph check before prepare, no second publish on a spent Composition, and an actual lifecycle finish failure after accepted publication that returns no success, invalidates the graph and requires a fresh command for exact confirmation. No test supplies a publication-success callback.

The command test covers exact argument definition and malformed-value codes with no runtime composition; it does not mock a successful private publication fact as final proof. Use actual public composition with broken/missing signing paths, absent build/DB prerequisites and provider spies to prove list/help/all formats, command --help, missing/extra argument and malformed digest reach no signing/DB/floor graph. Required-argument binding failures use the actual inherited boundary; no fabricated exit2 assertion. Test a valid-shaped missing digest/configuration through the actual owned failure path, safe code/exit1, empty policy/seed/path/SQL/raw Throwable output. Help is still available in those installations.

The native installation cases use actual built application configuration/providers and actual SQL/files under Root's later resource scope:

| Case | Discriminating proof |
| --- | --- |
| Genuine initial publication | Actual B2 open slot, durable preinstalled zero source/floor, real archived root-signed policy1 and installed matching delegate. Actual command creates exactly one policy mirror/root_revision/dispatch/candidate; immutable signatures verify; real pending/source/accepted fsync yields output0 and authenticated backlog1. Zero work reservation/preparation/artifact/Required/ack writes. |
| Actual next policy / stopped delegate or root replacement | Actual retained accepted source/history, next archived policy and independently changed matching pins/raw32 seed. New current grant signs one actual next root transition/head; old source is historical only. Exact unchanged B2/lineage/ack boundary; separate current acquisition succeeds afterward. Missing stopped/install prerequisites fail; no old-grant fallback. |
| Duplicate invocation after success | Fresh full Application/operations graph, same selector. Exact source bytes/revision/document/transition/signatures unchanged; only permitted observedNow advancement. No new seed/sign/Clock-for-candidate/UUID/SQL insert, return AlreadyPublished after actual current checks. |
| Staged candidate before pending | Lose response after actual SQL candidate commit. Fresh invocation identifies the exact successor and publishes exact committed bytes. One semantic transition/candidate total, zero re-sign/new timestamp. Concurrent same-policy invocations use real uniqueness/fences and fresh-root visibility, not fake returned outcomes. |
| Pending first/later publication | Crash/fault after pending fsync with source old/partial/new, then after accepted fsync before response. Retry exact requested policy; preserve floors and publish or confirm only pinned bytes. Different selector/foreign row/missing candidate is failure, never rollback to old source. |
| Current-auth denial on reuse | Revoke/expire current root/delegate or historical required material, advance minima, change installed pins, or roll Clock back between invocations. Existing published/staged history is not authority; no success/output/new seed/sign. Pins only recover the precise permitted historical envelope chain. |
| Selection and race tampering | Exact native row policy_digest forged to requested digest, then changed before capture; phase14 must still compare authenticated candidate with original requested digest. Contradictory next/higher rows, stale source/floor, wrong schema/null tenant/open slot, swapped candidate bytes and predecessor drift fail. A different staged policy blocks even when old accepted policy matches the request. |
| Real SQL ambiguity | Before/during RR fence winner, unique conflict invisible to snapshot, and actual commit-response loss. Shared graph poisoned, no floor publication or within-graph retry. Fresh command reads/authenticates actual winner; no latest-wins or cross-policy adoption. |
| Backpressure/history |32 existing authenticated pending transitions denies a33rd new root transition; existing exact candidate recovery and exact accepted confirmation add no transition. More than32 historical policies/issuers/acknowledged transitions remain usable with bounded memory/records and current restrictions. No lifetime cap. |
| Native ownership/failure | Actual controlled write/flush/fsync/unlock and supported observable close branches, file/directory durability for already archived replay, process interruption/pending recovery and descriptor cleanup. No SQL under floor or nested online/control lock. Existing native calibration alone is not this proof. |
| Partial operational boundary | Publication may return0 after real accepted floor while D remains pending. Actual scheduler with absent F4/D still fails safely and retains real work; command success does not activate or satisfy its task. No fake D consumer, fabricated ack, callback release or full F5 claim. |

The native contract proposal847ca0bd, bounded Green review79530b88 and adopted-custody Greenf97994ce apply: final same-descriptor writes/truncate/flush/fsync and necessary archive-directory fsync precede durable results; scratch has no invented fsync. Explicit unlock and one PHP close check all observable failures. Normal PHP fclose cannot expose masked native errors; genuine Linux/glibc consuming-close semantics and exclusive ownership remain stated support premises. Keep the three non-consuming replacement counterexamples and six consumed-but-masked errors distinct; neither category becomes a Product negative PASS. No FFI/helper/native extension,/proc Product probe or blind close retry is introduced here.

Assigned commands, fault image/source hashes, resources, time bounds and strict/full/package gates are Root's complete-packet work. The future worker must qualify actual observers/calls and report unhit/masked cases honestly. Existing successful unchanged gates are reused; no runtime/test/DB command ran for this design.

## 11. Reader/release paths and remaining joins

Future guide changes are limited to `docs/guide/project-cli.md` (new admin row, required argument/exit/help/repeat-recovery behavior), `docs/guide/configuration.md` (existing installed control custody prerequisites; no new key), and `docs/guide/console-command.md` (short explicit link distinguishing this fixed Framework deployment command from the existing Operation attribute/value binding; no claim that Operation positional arguments are newly supported). `docs/internal/maintenance-scheduler.md` explains maintenance versus installation composition and retained fail-fast/partial status. Add `docs/internal/audit-control-installation.md` for exact invocation ownership, stop/drain/provision/archive steps, supported native limitation, floors/recovery and pending D boundary. Public rollout of these guides remains gated by105/106 and Owner release review; an implementation packet may prepare them but cannot describe unreleased1.3 as Stable1.2.1.

The source-confirmed routes in docs/website/content-map.mjs are `/reference/project-cli/`, `/reference/configuration/`, `/execution/console-command/`; no route is added. Internal sources and generated Source/Search/LLM/website artifacts require their normal exact-source/positive-negative checks. They were not generated or tested here. The changed existing public capability mapping is `framework-core` (project-cli); this proposal creates no new capability ID. Root must map the two other affected pages in the release packet without manufacturing a released capability.

The retained release-authority tuple is experimental-stable1.2.1: Framework tag1.2.1 directRef `1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3`, peeledSource `4efee09f13bebedc1639333f79550a36a4e8ca91`; Skeleton tag1.2.1 directRef `7a47e344ecba6193628119f0e95cb23dc5e0be83`, peeledSource `87a025380df8abcd0c514abf50e139dbbc1953c7`. Roadmap1.3.0 remains unreleased. This delta adds only a proposed1.3 administration command; it changes no stable tuple or historical allowlist. Stable1.2.1 occurrences remain valid before/after, with zero public-source edits in this investigation. Same-SHA CI/export, all-public Source/Search/LLM validation, Owner preview, publication/deploy and live delivery proof remain Not Run here.

The installation constructor-origin gap is closed at design level by the exact selector/composer/factory above. Remaining concrete Root work is binding the already assigned actual parser/delegate/private custody leaves, adopting §8 and the private recovery-expectation delta, reconciling the actual pure-codec r2 prerequisite/result callers, reserving the existing migration, assigning native resources and independently reviewing the complete combined packet. The fixed accepted-counter PublicationEnd interpretation in §6.3 is included in that same bounded adoption review. These are not invitations to broaden the command or replace actual production with a mock/unavailable stub.

Remaining work: complete combined authority/issuance review, then Luna implementation and actual native/strict/full/package qualification; afterward real F4/D/Required continuations, full F5 AC2–6 and remaining1.3 delivery/release gates.

Next Action: Root reviews this exact installation delta against the two frozen maps, adds only the named constructor/caller/phase14 expectation amendments and real custody imports, and submits the complete implementation packet for independent review. This read-only lane performs no commit under its assigned scope; Root owns the next governance unit and its separately authorized commit checkpoint.

## 12. Evidence-only checks and freeze

The companion records66 exact selected inputs before/after; all66 remained byte-identical through this freeze, including the selected accepted Product/test/vendor/guide sources and immutable prior maps. Late source/companion additions are explicitly marked as late alignment inputs. The reported Git base/governance commits were not independently queried in this lane.

Bookkeeping checks parsed the companion JSON, compared all selected SHA256/byte lengths, matched the frozen companion identities, checked every proposed Product/test/guide path against this document, checked unique Product paths and explicit method/argument budgets, and checked paired Markdown code fences. These are text/inventory checks only. They do not establish PHP syntax, static complexity, call-guard execution, native semantics, query plans, CLI behavior or Product Acceptance. The14 listed Product types include the one already named but unfilled PublicationResult; it is deduplicated in the combined packet.

Only this file and installation-publication-issuance-r1-inputs.json were written. No Product/test/migration/vendor/governance/STATE/Git/resource files were changed by this lane; no PHP/Node/runtime/DB/native/test/network command ran. The final Markdown identity and exact check status are in the companion; no mutual self-hash is attempted. This r1 is frozen as a recommendation for Root's bounded complete-packet review, with the finite import/adoption/verification work in §11 still required.

# Real checkpoint prerequisite — consolidated candidate issuance map r1

Status: **frozen candidate file/API/caller map for Root's assembled packet; not issuance, implementation, native qualification or Acceptance.** This map incorporates the reviewed same-graph composition, lazy scheduler, SQL and selector proposals and Root's reviewed CS-01 correction. It supplies a real attributed reservation, actual F2 preparation, actual ordinary authorization/handler, committed complete candidate, actual F2 persistence and authenticated persisted outcome. Its terminal operational result remains held/pending ResourceUnavailable until the actual F4/D owners exist. A blanket unavailable implementation that never reaches these real phases does not satisfy this prerequisite.

All paths below are relative to C unless prefixed P/E5/E13. P is `/home/kubotak/projects/blackops`; C is `.phpunit.cache/f2-orchestration/accepted-integration`; E5 is `.phpunit.cache/f5-orchestration/20261012`; E13 is `.phpunit.cache/f5-orchestration/20261013`. Root reports C base `35df513e65d687086d47517df9dca6f3b5081457`; this investigation did not run Git. Root owns the final authority clarifications, complete verification packet, migration reservation, worker scopes and Acceptance. The current F5 Task's 2026-10-13 scope authorizes these two ignored evidence files only.

## 1. Frozen basis and precedence

| Retained input | SHA-256 | Use |
| --- | --- | --- |
| E5/checkpoint-production-composition-proposal-r1.md | `11a695ff38c2093f1addd38095ca1c9e584e585e9e6b62680a3b9553f98d488d` | Real ordinary definition, identity, evaluator/handler and held-outcome contract. |
| E5/checkpoint-selector-lazy-composition-proposal-r1.md | `f2391720411a9f52c33897aa773a5b9bf5397da1ebea56b8f768fd729bcd15cf` | Literal Q1–Q7, new index29, lazy scheduler and refined same-graph factory. Supersedes eager retention composition. |
| E5/shared-provenance-exact-ddl-proposal-r1.md | `6eb421227d30fcf7b9e6574058b6934d9900faf85b149d1feb4953bf4dc63cc7` | Exact first28 emitted DDL statements; copied unchanged into Appendix A. |
| E5/shared-provenance-sql-contract-proposal-r1.md | `d3dc8e4ba5b29727b5e38ef356e27f26d057f094ec32ecca9f05d00ee040a9cc` | Native encodings, F2 exact7/exact3 projection, immutable collections, ordering and recovery. |
| E5/shared-provenance-sql-contract-review-r1.md | `64f9b74f56b18fc33fb411c08fd74489910f6914e71490cfc93525ba54d3e2ef` | Reused bounded Green SQL review. |
| E5/checkpoint-composition-sql-review-r1.md | `504e0f9da6b129b1dda65e94541484d1b2910d84f53b2a1c34d46ef1ab9fad5a` | Compatible composition/selector/DDL, mandatory native READ ONLY capture, and CS-01. |
| E13/checkpoint-composition-correction-r2.md | `f8ec05b748190d249d7cbc09956b7ed847e34edd91eec4643e7eb06632846d77` | Both actual Durable call edges invalidate the common graph on escaping failure. Overrides earlier incomplete failure text. |
| E13/checkpoint-composition-correction-review-r2.md | Exact hash in companion manifest | Green for CS-01 at proposal level. |
| E13/history-capture-issuance-map-r1.md | `4527806ba7734c5480dda53a202d1351a8e8e4753a2f1d484658308d2450b2df` | Final nonoverlapping history/custody map pinned after cross-lane signature alignment; no mutual companion hash cycle. |

The retained evidence is not rewritten. This consolidation selects concrete names where the older tables intentionally used candidate names; additional type splits below are limited to the genuine RO/RW, phase-freshness and method-budget joins. No broad F1/F2 inventory or authority-direction review was repeated. The filesystem investigator owns E13/history-capture-issuance-map-r1.md: raw parser, anonymous descriptor/framing, archive/floor custody, historical issuer replay and native filesystem ownership remain in that lane.

## 2. Existing files and exact construction graph

### 2.1 Existing Product changes proposed

| File | Exact change and sole new callers | Resulting declared method count |
| --- | --- | --- |
| `src/Internal/Application/ApplicationRetentionRuntime.php` | Retain final readonly, constructor(snapshot), public configuration/planner/purge/clock. Store private snapshot. Replace public scheduler property with private uninitialized MaintenanceScheduler scheduler and private uninitialized bool schedulerStarted. Add scheduler(), composeScheduler(), existingTasks(), signingTasks(), auditCatalogs(container). Only the two factory scheduler methods call scheduler(). | 6; no new constructor arguments. |
| `src/Internal/Application/ApplicationRetentionCommandFactory.php` | schedulerRun() and schedulerDaemon() replace `$runtime->scheduler` with `$runtime->scheduler()`. plan(), purge(), runtime cache and defaults stay as accepted. | 7, unchanged. |
| `src/Internal/Application/ApplicationAuditRuntimeComposer.php` | Keep compose(snapshot,operations,catalogs):ApplicationAuditRuntime. Add composeForCheckpointProduction(same3):ApplicationAuditCheckpointComposition. Extract the current single graph into private build(same3):ApplicationAuditRecordComposition. Existing database/assertConnection/providers remain. | 6. |
| `src/Internal/Application/ApplicationAuditConfiguration.php` | Join Root's exact installed signing parser to the accepted closed audit object. Retain outbox behavior, reject unknown keys; do not strip signing before existing validation or add a fallback/default policy. The complete parser's method/property map is Root/history-lane owned. | Exact parser budget belongs in assembled authority map; no speculative methods here. |
| `tests/Architecture/*` selected in §10 | Add exact named source/type/call edges and discriminating mutations; retain all old guards. No directory-wide signing/provenance exception. | Existing test class budgets are not changed by this design. |
| `tests/Consumer/framework-package-export.sh` | Add exactly the newly issued source/migration/test inventory where its existing finite package checks require it; no glob that silently includes unreviewed files. | Shell inventory only. |

No production changes to Core Operation, AuthorizationEvaluator, AuthorizationPolicyResolver, HandlerInvoker, ExecutionContextFactory, InlineDispatcher, MaintenanceScheduler, LazyFrameworkCommand, the existing public F2 methods/columns or F3 state machine are proposed. Their existing behavior is used by actual callers and qualified by meaningful tests.

### 2.2 Lazy lifetime and factory sequence

The constructor builds the existing retention planner/purge graph only. `scheduler()` returns its already initialized scheduler. If schedulerStarted is initialized without a scheduler, it throws finite ResourceUnavailable. Otherwise it sets schedulerStarted before calling composeScheduler and caches only a successful result. No reset, retry, close/reconnect or replacement graph occurs inside that instance. The successful cache remains even when its signing graph later becomes unusable; both task entries then refuse reuse. A new command from the same factory is not recovery.

`composeScheduler()` assembles `existingTasks()` followed by `signingTasks()`, then constructs the actual MaintenanceScheduler with the snapshot's actual telemetry/metrics. `existingTasks()` reconstructs the accepted retention task and optional legacy OutboxRelayMaintenanceTask exactly once. `signingTasks()` returns no F tasks when signing is absent; when present it performs this fixed sequence:

1. Invoke the real closed installed parser. Build one actual ApplicationOperationRuntimeComposer::compose(snapshot). All F roots use this operations.connection and operations.clock. Retention's earlier independent planner Connection/Clock and the existing legacy outbox runtime are not asserted to be identical to F's graph.
2. `auditCatalogs(operations.container)` checks the actual optional AuditCatalogProvider service. Null is permitted only when absent. Wrong/throwing provider fails. Construct one actual ApplicationAuditCatalogRegistry(provider)->lookup(); the actual provider executes once and the accepted Framework catalogs remain present.
3. Build the actual C3/F2 graph through one composeForCheckpointProduction call. Assert the returned audit delivery uses exactly operations.connection. No second provider lookup, opaque key, protected-body codec, record store or F2 store is fabricated.
4. If ordinary is installed, construct its real fixed Definition/Policy and Identity with the actual Console providers, compile/assert the full Operation metadata tuple and policy object identity before signing authority/SQL selection. Protection-key resolution in C3 composition is an existing separate concern; it never grants a signing seed.
5. Construct one AuditSigningGraphState(operations.connection), one Root-owned delegate custody and one provenance recorder. Construct the ordinary-registration and control-issuer relationships as distinct final concrete entries; no class flag or application container service selects them. Only this ApplicationRetentionRuntime method constructs their task relationship. Closed helper construction is permitted only within the exact graph in §3.
6. Register retention, optional legacy outbox, optional ordinary and control in that order. Actual MaintenanceScheduler retains fail-fast behavior. Ordinary failure can prevent control in the same sweep; separate real installations (ordinary enabled/absent) qualify both paths. No test-only skip/filter, catch-and-continue scheduler or fake successful task result is added.

Actual ApplicationConsoleKernel/LazyFrameworkCommand list/help remains lazy and cannot resolve these providers, read authority/seed files, require built operations or touch F SQL. Explicit plan/purge likewise do not call scheduler(). Explicit scheduler execution owns the F requirements. The first-stage tasks cannot both promise normal operational completion without the missing F4/D joins.

### 2.3 Three finite assembly results

The following files are new under `src/Internal/Application/`. `R4` below means exactly `__debugInfo():array` returning no held values; `__serialize():array` and `__unserialize(array):void` throwing fixed safe exceptions; private `__clone():void`. No magic getter or dynamic dispatch is allowed. Parameters bearing configuration, rows, contexts, authority or artifacts are SensitiveParameter; retained data is private/sensitive. Existing accepted oversized constructors are not copied into new types.

| Type | Exact constructor and methods | Caller/field constraints; count |
| --- | --- | --- |
| ApplicationAuditRecordComposition | constructor(ApplicationAuditRuntime audit, ApplicationAuditCheckpointProofComponents proof, PostgreSqlAuditCheckpointSchema checkpointSchema); audit():ApplicationAuditRuntime; forCheckpointProduction(ApplicationOperationRuntimeComposition operations):ApplicationAuditCheckpointComposition; R4 | Constructed only by Composer.build. Holds those3 and one initially uninitialized readonly factoryStarted. Factory sets its once guard on entry before Connection identity assertion or F2 construction, so any failure is sticky. Called only by the two composer methods. 7. |
| ApplicationAuditCheckpointProofComponents | constructor(AuditPartitionIdDeriver partitions, PostgreSqlAuditStoredRecordHydrator hydrator, AuditProtectedBodyCodec body, Uuidv7Generator identifiers); store(Connection,PostgreSqlAuditCheckpointSchema,ClockInterface):PostgreSqlAuditCheckpointStore; reader(Connection,PostgreSqlAuditCheckpointSchema):PostgreSqlAuditClosedSegmentReader; admission(Connection,PostgreSqlAuditCheckpointSchema):PostgreSqlAuditCheckpointAdmission; R4 | Built only by Composer.build from its actual local components. Factory methods called once each only by RecordComposition.forCheckpointProduction. F2 retains its accepted7-argument constructor at this one store() site. reader derives the same B2 schema. No raw codec/key getter. 8. |
| ApplicationAuditCheckpointComposition | private constructor(ApplicationAuditRuntime audit, PostgreSqlAuditCheckpointStore store, PostgreSqlAuditClosedSegmentReader reader, PostgreSqlAuditCheckpointAdmission admission); static fromComposition(same4):self; audit(),checkpointStore(),closedSegmentReader(),checkpointAdmission() with exact respective return types; R4 | Only RecordComposition calls factory; only RetentionRuntime.signingTasks reads these four accessors for fixed wiring. No operations/secret getter. 10. |

Composer.build creates the same validated configured schema as accepted C3 and the same stored-record hydrator for both the record and checkpoint proof graph. Ordinary compose() returns `build(...)->audit()` and does not construct an F2 store/reader/Admission. Constructing the Connection-free checkpoint schema on that path is permitted. Root's concurrent specification131 §2 clarification now names this composeForCheckpointProduction seam and the lazy scheduler lifetime; the byte comparison below records that authority change without claiming Product implementation.

## 3. Closed type, constructor, method and caller map

These are proposed production paths, not current services. All new types are final; value holders are readonly where no single-use state is needed. New types have at most10 total declared methods including constructor/magic methods and at most5 ordinary arguments. Complexity15 and512M remain mandatory. R4 is counted explicitly. Small private helpers listed here are the complete allowance; a new helper/type/caller is a packet delta, not implicit permission.

### 3.1 Ordinary definition and invocation owners

Default directory: `src/Internal/Audit/Signing/`; Application and Scheduler exceptions are explicit.

| Type/file | Complete proposed surface (ordinary argument types shown) | Sole constructor/caller and private state; methods |
| --- | --- | --- |
| `Application/ApplicationAuditOrdinarySignConfiguration.php` | private ctor(AuditTypedReference principal,?AuditTypedReference tenant,array artifactKinds); static fromSigningObject(object ordinary):self; principal(),tenant(),artifactKinds(); private assertShape(object):void; R4 | Root installed parser alone constructs. Exact3-key object; refs/kinds immutable, no default identity. Runtime/Definition/Identity/Policy consume named getters. 10. |
| OrdinaryAuditSignDefinition | ctor(Symfony Container container,ApplicationAuditOrdinarySignConfiguration installation,AuditPartitionIdDeriver partitions); metadata():OperationMetadata; assertInstalled():void; private assertIdAvailable():void; private assertMetadata(OperationMetadata):void; R4 | RetentionRuntime alone constructs/installs actual Policy and compiles actual Operation once. Keeps container, exact owned policy and compiled metadata; Execution asserts before selection/evaluation. 9. |
| OrdinaryAuditSignIdentity | ctor(ConsoleActorProvider actorProvider,ConsoleTenantProvider tenantProvider,ApplicationAuditOrdinarySignConfiguration installation,ExecutionContextFactory contexts); start():ExecutionContext; private validate(ActorRef,?TenantRef):void; private matches(ActorRef,?TenantRef):void; R4 | Runtime is sole start caller. Keeps real providers/config/factory, no cached identity/context. 8. |
| OrdinaryAuditSignOperation | No methods. Exactly Operation plus the five attributes in §4. | Defined/compiled only by Definition; instantiated only by Execution. No registry/manifest discovery. 0. |
| OrdinaryAuditSignValue | private ctor(array exactTarget); static fromCheckpoint(AuditCheckpointPreparation preparation):self; target():array; R4 | Execution's fixed post-prepare block alone calls factory; Policy/Handler/context factory read the closed7-field target. Factory invokes the fixed artifact facts helper, never accepts an array from callers. 7. |
| OrdinaryAuditSignPolicy | ctor(ApplicationAuditOrdinarySignConfiguration installation,AuditPartitionIdDeriver partitions); decide(AuthorizationRequest):AuthorizationDecision; private identity(AuthorizationRequest):bool; private target(AuthorizationRequest):bool; private kind(OrdinaryAuditSignValue):bool; R4 | Definition alone constructs; actual existing AuthorizationEvaluator/Resolver is sole production decision route. These private predicates are validation, never a public origin/allow flag. 9. |
| `Scheduler/OrdinaryAuditSignMaintenanceTask.php` | ctor(OrdinaryAuditSignRuntime); name():string; run(DateTimeImmutable now):MaintenanceTaskResult | RetentionRuntime alone constructs. Fixed name audit.ordinary_sign. Actual scheduler calls run; it ignores now for authority and calls runOne without data arguments. 3. |
| OrdinaryAuditSignRuntime | ctor(ApplicationOperationRuntimeComposition operations,OrdinaryAuditSignIdentity identity,OrdinaryAuditCheckpointPreparation preparation,OrdinaryAuditSignExecution execution,AuditSigningGraphState graph); runOne():MaintenanceTaskResult; private invoke():MaintenanceTaskResult; private failure(Throwable):never; R4 | Task alone calls runOne. First graph.assertUsable then execution.assertAdmission before lifecycle preparation/provider access/selection. Owns actual lifecycle.run with lexical closures only at this accepted existing API, not a supplied release callback. 8. |
| OrdinaryAuditCheckpointPreparation | ctor(PostgreSqlOrdinaryCheckpointSelector selector,AuditOrdinaryCheckpointRegistration registration,AuditPartitionIdDeriver partitions); next(ExecutionContext):PreparedCheckpointWork; private tenant(ExecutionContext):?AuditTypedReference; R4 | Runtime.invoke only. Derives actual tenant/partition, selects, then ordinary registration; no control fallback. 7. |
| OrdinaryAuditSignExecution | ctor(ApplicationOperationRuntimeComposition operations,OrdinaryAuditSignDefinition definition,OrdinaryAuditSignAudit audit,OrdinaryAuditCheckpointProduction production,AuditPartitionIdDeriver partitions); assertAdmission():void; execute(ExecutionContext,PreparedCheckpointWork):MaintenanceTaskResult; private evaluate(OperationEnvelope,OwnedSignRequestBasis):OwnedSignContext; private unavailable(OwnedSignContext):never; R4 | Runtime alone consumes. Single exact envelope/evaluation/requested/invocation; no application metadata/handler. Execution has no successful result branch in this prerequisite after the held persisted result. 9. |
| OrdinaryAuditCheckpointProduction | ctor(AuditCheckpointCandidateWorkflow candidate,AuditCheckpointPersistenceWorkflow persistence,HandlerInvoker invoker); invoke(OperationMetadata,OperationEnvelope,PreparedCheckpointWork):OperationResult; private handler(OperationEnvelope,PreparedCheckpointWork):OrdinaryAuditSignHandler; R4 | RetentionRuntime constructs; Execution's post-allow/requested-success block alone calls invoke. Exact handler construction and real HandlerInvoker, no container resolution. 7. |
| OrdinaryAuditSignHandler | ctor(OperationEnvelope capturedEnvelope,PreparedCheckpointWork prepared,AuditCheckpointCandidateWorkflow candidate,AuditCheckpointPersistenceWorkflow persistence); handle(OperationEnvelope):OperationResult; private assertInvocation(OperationEnvelope):void; R4 | Production.handler alone constructs after allow/requested success. Exact envelope object identity, value/target/work match, one uninitialized/monotone used field. Calls real workflows; returns actual completed HeldOutcome or rejection. 7. |
| OrdinaryAuditSignContextFactory | ctor(ClockInterface clock,AuditJcsCodec jcs); begin(ExecutionContext,OrdinaryAuditSignValue):OwnedSignRequestBasis; decided(OwnedSignRequestBasis,OrdinaryAuditSignDecision):OwnedSignContext; private requestRef(array exactContext):string; private requestedAt():string; R4 | Only Audit wrapper calls; nonce random_bytes16 once in begin, actual Clock once after target. Closed enum is allow/deny/unavailable; never production classification. 9. |
| OrdinaryAuditSignDrafts | ctor(HmacAuditOpaqueIdDeriver opaqueIds,string applicationBuildId); requested(OwnedSignContext):AuditDeliveryRequest; unavailable(OwnedSignContext):AuditDeliveryRequest; private resource(OwnedSignContext):AuditRecordResource; private requestedDraft(OwnedSignContext):AuditRecordDraft; private unavailableDraft(OwnedSignContext):AuditRecordDraft; R4 | Only actual Audit wrapper calls. No accessed method/call in this prerequisite: actual Required-to-F4 implementation is deferred as §9 specifies. The two explicit Draft helpers retain fixed event tuples, not event/mode arguments. 10. |
| OrdinaryAuditSignAudit | ctor(AuditDeliveryCoordinator delivery,OrdinaryAuditSignDrafts drafts,OrdinaryAuditSignContextFactory contextFactory,AuditSigningGraphState graph); begin(ExecutionContext,OrdinaryAuditSignValue):OwnedSignRequestBasis; decided(OwnedSignRequestBasis,OrdinaryAuditSignDecision):OwnedSignContext; requested(OwnedSignContext):void; unavailable(OwnedSignContext):void; R4 | Execution is sole caller. Both actual delivery calls have the exact CS-01 narrow catch in §4. No pure-draft exception masquerades as ambiguous commit. 9. |
| AuditSigningGraphState | ctor(Connection connection); assertUsable():void; invalidate():void; R4 | RetentionRuntime alone constructs exactly one. Shared by both runtime entries, transactions, phase owners and Audit wrapper. Private usability bool is monotone; invalidate sets false first, closes best-effort once, is idempotent/nonthrowing. No reset/reconnect/usable getter. 7. |
| OrdinaryAuditSignDecision | enum cases Allow,Deny,Unavailable only; no methods. | Created only by Execution from actual evaluator result/exception; context factory consumes. It is not a reusable authorization token. 0. |

Short names bind exact accepted namespaces: `BlackOps\Core\Registry\OperationMetadata`; `BlackOps\Core\OperationEnvelope`, `ExecutionContext`, `OperationResult`, `Operation`, `OperationValue`, `OperationHandler`, `Outcome`, `ActorRef`, `TenantRef`; `BlackOps\Core\Authorization\AuthorizationRequest`, `AuthorizationDecision`, `AuthorizationPolicy`; `BlackOps\Console\ConsoleActorProvider`/`ConsoleTenantProvider`; `BlackOps\Internal\Registry\OperationMetadataCompiler`; `BlackOps\Internal\Execution\HandlerInvoker`; `BlackOps\Internal\ExecutionContext\ExecutionContextFactory`; `BlackOps\Core\Audit\AuditRecordResource` and the accepted Draft/reference types. Value implements actual OperationValue, Handler actual OperationHandler, Policy actual AuthorizationPolicy and HeldOutcome actual Outcome. This map authorizes no replacement local equivalent. Attributes bind the actual Core Attribute classes and existing Inline strategy.

### 3.2 Provenance, admission and artifact owners

Default directory: `src/Internal/Audit/Provenance/`. Authority/history import names in §8 are owned by the other lane. Constructors below name cohesive collaborators; none is a generic state/dependency bag or callable.

| Type | Complete proposed surface | Caller/ownership and count |
| --- | --- | --- |
| PostgreSqlAuditProvenanceTransaction | ctor(Connection,AuditSigningGraphState); beginRoot():void; commit():void; abort(Throwable):AuditDeliveryException; private rollbackOwned():void; private closeOnce():void; private assertRoot():void | Mutation recorder, control publication/proof owners only. Writes RR; §6 fixes state/timeouts and poison. 7. |
| PostgreSqlAuditProvenanceReadTransaction | ctor(Connection,AuditSigningGraphState); beginRoot():void; commit():void; abort(Throwable):AuditDeliveryException; private rollbackOwned():void; private closeOnce():void; private assertRoot():void | Only exact history-capture/query owners. Native RR then READ ONLY; no selector boolean on write transaction. 7. |
| PostgreSqlAuditCheckpointSelectionQueries | ctor(Connection,PostgreSqlAuditSchema); partition(?AuditTypedReference,string partition):array\|false; firstPage(string partition):array; nextPage(string partition,string afterSequence,string afterSegment):array; private projection():string | Selectors/capture only; literal Q1/Q2. Current admitted first stage cannot skip unresolved prefix merely by supplying cursor. 5. |
| PostgreSqlAuditWorkRows | ctor(Connection,PostgreSqlAuditProvenanceSchema); byCheckpoint(string partition,string segment):array; byWork(string workId):array\|false; steps(string workId):array; lockCheckpoint(string partition,string segment):array; lockWork(string workId):array; insertReservation(array exact15):void; insertStep(array exact10):void; updateDispatch(array exact5):void | Nonlocking three reads only in capture; fixed locking two reads only in checkpoint mutation admission. lockWork locks reservation then ordinal steps; no public lock/read bool. Writes only Recorder, dispatch untrusted. 9. |
| AuditCheckpointMutationAdmission | ctor(Connection,PostgreSqlAuditCheckpointAdmission,PostgreSqlAuditWorkRows,PostgreSqlAuditCheckpointSelectionQueries); consume(AuditCheckpointPhaseAuthority,AuditCheckpointCaptureScope):AuditVerifiedCheckpointEndpoints; assertUnchanged(AuditVerifiedCheckpointEndpoints):void; private fence(string partition):void; private selectedBoundary(AuditVerifiedCheckpointEndpoints):void; private workEndpoint(AuditVerifiedCheckpointEndpoints):void | Recorder and pre-F2 phase owner only. consume checks the corresponding typed operation's exact scope/phase before calling the authority's one-use consume, before beginning the root. assertUnchanged runs inside the named root and checks all native endpoints/absence/full bytes; returned endpoints are private facts, not later phase permission. 6. |
| AuditOrdinaryCheckpointRegistration | ctor(AuditCheckpointReservationCapture,AuditCheckpointReservationAcquisition,AuditWorkProvenanceRecorder,AuditCheckpointPreparationWorkflow,AuditSigningGraphState); prepareSelected(OrdinaryCheckpointSelection):PreparedCheckpointWork; R4 | OrdinaryPreparation only. Hardcodes ordinary_sign_owner/ordinary and never calls control reservation. Capture plus actual current control/online key acquisition precedes recorder; no seed. 6. |
| AuditControlCheckpointIssuer | ctor(AuditCheckpointReservationCapture,AuditCheckpointReservationAcquisition,AuditWorkProvenanceRecorder,AuditCheckpointPreparationWorkflow,AuditSigningGraphState); prepareSelected(AuditControlCheckpointSelection):PreparedCheckpointWork; R4 | Actual ControlRuntime only. Hardcodes framework_control_maintenance/control; requires actual authenticated published lineage, not task membership/name. 6. |
| AuditCheckpointReservationAcquisition | ctor(AuditControlAcquisition,Root-owned concrete online acquisition,AuditSigningGraphState); ordinary(OrdinaryCheckpointSelection,AuditCapturedCheckpointHistory):AuditCheckpointReservationResources; control(AuditControlCheckpointSelection,AuditCapturedCheckpointHistory):AuditCheckpointReservationResources; R4 | Only matching Registration/Issuer calls. Sequential normal control acquisition then actual current online key selection, zero nested floors/seed. New reservation receives actual current checkpoint ref/fingerprint; resume requires authenticated original ref/fingerprint, never selects a replacement key. The concrete online import remains §8's finite Root join. 7. |
| AuditCheckpointPreparationWorkflow | ctor(PostgreSqlAuditCheckpointStore,AuditCheckpointPreparationCapture,AuditCheckpointPhaseAcquisition,AuditWorkProvenanceRecorder,AuditSigningGraphState); prepare(ReservedCheckpointWork):PreparedCheckpointWork; private prepareActual(ReservedCheckpointWork):PreparedCheckpointWork; R4 | The two registration entries only. Acquisition here is the finite cross-lane phase join in §8, not an invented ready generic acquisition service. Real F2 prepare uses original key/ref and fresh online snapshot; afterPrepare gets a different control authority for preparation_bound. 7. |
| AuditCheckpointCandidateWorkflow | ctor(AuditCheckpointCandidateCapture,AuditCheckpointPhaseAcquisition,AuditCheckpointCandidateSigner,AuditWorkProvenanceRecorder,AuditSigningGraphState); complete(PreparedCheckpointWork):HeldCheckpointCandidate; R4 | Actual ordinary Handler or actual ControlCheckpointProduction only. Calls current phase acquisition before new/recovered candidate, fixed seed/sign owner, then committed recorder. Seed signer is §8's required Root custody join. 6. |
| AuditCheckpointPersistenceWorkflow | ctor(PostgreSqlAuditCheckpointStore,AuditCheckpointPersistenceCapture,AuditCheckpointPhaseAcquisition,AuditWorkProvenanceRecorder,AuditSigningGraphState); persist(HeldCheckpointCandidate):PersistedCheckpointOutcome; private persistActual(HeldCheckpointCandidate):void; R4 | Same two real production callers. Separate beforePersist/afterPersist captures and authority; actual F2 persist then exact native reconciliation/outcome. Recovery is the same fresh authorized invocation path, not a public recover(workId) shortcut. 7. |
| AuditWorkProvenanceRecorder | ctor(PostgreSqlAuditProvenanceTransaction,PostgreSqlAuditWorkRows,AuditCheckpointMutationAdmission,AuditControlDelegateCustody,Uuidv7Generator); reserveOrdinary(OrdinaryCheckpointSelection,AuditCheckpointPhaseAuthority,AuditCheckpointCaptureScope,AuditCheckpointSigningKey):ReservedCheckpointWork; reserveControl(AuditControlCheckpointSelection,AuditCheckpointPhaseAuthority,AuditCheckpointCaptureScope,AuditCheckpointSigningKey):ReservedCheckpointWork; preparationBound(PreparedCheckpointWork,AuditCheckpointPhaseAuthority,AuditCheckpointCaptureScope):PreparedCheckpointWork; completeCandidate(SignedCheckpointCandidate,AuditCheckpointPhaseAuthority,AuditCheckpointCaptureScope):HeldCheckpointCandidate; artifactPersisted(HeldCheckpointCandidate,AuditCheckpointPhaseAuthority,AuditCheckpointCaptureScope):PersistedCheckpointOutcome; R4 | One custody holder, constructed by RetentionRuntime only. Entry callers are respectively Registration, Issuer, PreparationWorkflow, CandidateWorkflow, PersistenceWorkflow. Each checks the fixed corresponding scope against its typed input, consumes the exact one-use authority through MutationAdmission before the root, and rechecks its native endpoints under the fence before its fixed envelope write. Reservation's fourth argument comes only from its actual same-call reservation acquisition; it is key metadata, not a seed or caller choice. No ingressAcknowledged method until actual D; no F4 frame method, classification flag, sign(payload), callback or alternate factory. 10. |
| AuditCheckpointArtifactFacts | private ctor(); static preparation(AuditCheckpointPreparation):array; static complete(string exactBytes):array | Pure finite F1 codec adapter; preparation owns one AuditCheckpointCodec::signingInput call, complete owns one AuditCheckpointCodec::decode call. Fact shapes in §5. No seed/trust/provider/state. 3. |

`AuditCheckpointPhaseAcquisition`, `AuditCheckpointCandidateSigner` and `AuditControlDelegateCustody` in this table are **finite required imported seams, not services claimed to exist**. Section8 fixes what they must join and who can call them. The final overall authority packet must bind their exact Root-owned definitions to this map; implementing an allow stub or arbitrary signing callback is forbidden. These are the only unbound crypto/custody joins in this lane, rather than an open menu of implementation alternatives.

### 3.3 New Checkpoint-owned projection

These three types remain under `src/Internal/Audit/Checkpoint/`; existing Store/Rows/Trust helpers are not exported.

| Type | Complete surface | Owned behavior and count |
| --- | --- | --- |
| PostgreSqlAuditCheckpointAdmission | ctor(Connection,PostgreSqlAuditCheckpointSchema,AuditPartitionIdDeriver); captureInCurrentTransaction(?AuditTypedReference,string partition,string segment):AuditCheckpointSlot; readInCurrentTransaction(same3):AuditCheckpointSlot; private assertCapture():void; private assertMutation():void; private identifiers(?AuditTypedReference,string,string):void; private slot(array target,array\|false preparation,array\|false complete):AuditCheckpointSlot; private boundedBytes(mixed):string; private failure(Throwable):AuditStoreException | Actual proof-components admission factory only. Capture owns no root/lock/protected-body read; mutation takes exact B2 fence and locked boundary/slot order using existing Checkpoint-owned helpers. boundedBytes consumes a native string/resource at most1,048,575+1 bytes in bounded chunks before giving unchanged accepted Preparation/StoredBinding helpers a string; it does not call an unbounded bytea conversion on raw input. 9. |
| PostgreSqlAuditCheckpointAdmissionQueries | ctor(Connection,PostgreSqlAuditCheckpointSchema,PostgreSqlAuditSchema); partition(?AuditTypedReference,string):array\|false; segment(string,string):array\|false; preparation(string,string):array\|false; complete(string checkpointId):array\|false | Admission alone constructs/calls. Exact nonlocking native projections for READ ONLY. Existing Rows remains locked and unchanged; no read-mode switch or external Rows caller. 5. |
| AuditCheckpointSlot | private ctor(?AuditCheckpointPreparation preparation,?array exactComplete,array nativeBoundary); static fromAdmission(same3):self; preparation():?AuditCheckpointPreparation; complete():?array; boundary():array; R4 | Only Admission creates; exact capture/mutation/history readers access. Absence/prepared/complete is derived from this closed nullable pair; complete-without-preparation is rejected, not an enum supplied by caller. Same-version/seal+1 enforced. 9. |

Capture Admission verifies actual active level1/autoCommit/RR/READ ONLY before SQL; mutation verifies level1/autoCommit/RR/read-write and actual native fence/rows. New capturers read native bytes through these exact Checkpoint-owned methods. F2's exact7 preparation and exact3 complete remain unchanged. No public preparation/complete Store getter, F2 owned-root override, eighth column, replacement Clock/UUID or protected-hydrator export is introduced.

### 3.4 Selector and control ownership

| Type/directory | Complete surface | Private state/callers and count |
| --- | --- | --- |
| Signing/PostgreSqlOrdinaryCheckpointSelector | ctor(PostgreSqlAuditCheckpointSelectionQueries,PostgreSqlAuditCheckpointAdmission,PostgreSqlAuditWorkRows,AuditCheckpointHistoryInspection,AuditSigningGraphState); select(ExecutionContext):?OrdinaryCheckpointSelection; private page(ExecutionContext,?array exactCursor):array; private inspect(OrdinaryCheckpointSelection):AuditInspectedCheckpointSlot; private choose(AuditInspectedCheckpointSlot):?OrdinaryCheckpointSelection; R4 | OrdinaryPreparation only. No configurable cursor/phase/class/receipt-ready input. page private cursor is exactly native sequence+UUID and originates only from prior same snapshot page. 9. |
| Control/PostgreSqlAuditControlCheckpointSelector | ctor(PostgreSqlAuditCheckpointSelectionQueries,PostgreSqlAuditCheckpointAdmission,PostgreSqlAuditWorkRows,AuditCheckpointHistoryInspection,AuditSigningGraphState); select():?AuditControlCheckpointSelection; private page(?array exactCursor):array; private inspect(AuditControlCheckpointSelection):AuditInspectedCheckpointSlot; R4 | Actual ControlRuntime only; system:global native stream, then exact external published membership from fresh history inspection. Only a verified eligible binding/reservation can be selected; unpublished/ordinary work cannot become control. It does not classify from contents/task name or trust a native binding locator. 8. |
| Control/AuditControlCheckpointProduction | ctor(AuditCheckpointCandidateWorkflow,AuditCheckpointPersistenceWorkflow); produce(PreparedCheckpointWork):PersistedCheckpointOutcome; R4 | Actual ControlRuntime only after actual control Issuer; checks authenticated reservation origin=framework_control_maintenance/class=control before either workflow. Ordinary Handler checks origin=ordinary_sign_owner/class=ordinary and its actual envelope. Neither accepts a cross-entry fact or evaluator fallback. 6. |
| Scheduler/AuditControlMaintenanceTask | ctor(AuditControlMaintenanceRuntime); name():string; run(DateTimeImmutable):MaintenanceTaskResult | RetentionRuntime only constructs; fixed audit.control; ignores supplied now as authority. 3. |
| Control/AuditControlMaintenanceRuntime | ctor(AuditControlPublicationWorkflow,PostgreSqlAuditControlCheckpointSelector,AuditControlCheckpointIssuer,AuditControlCheckpointProduction,AuditSigningGraphState); runOne():MaintenanceTaskResult; private checkpoint():never; R4 | Actual task only. First shared-state check, real publication recovery/bootstrap/advance selection then real checkpoint phases. No D completion adapter. It preserves pending and throws safe ResourceUnavailable at absent actual consumer/continuation. 7. |
| Control/AuditControlSegmentProofWorkflow | ctor(PostgreSqlAuditProvenanceTransaction,PostgreSqlAuditClosedSegmentReader,PostgreSqlAuditControlBindingRows,PostgreSqlAuditControlPublicationRows,AuditSigningGraphState); prove(AuditControlSegmentSelection):AuditControlClosedSegmentProof; rereadInCurrentTransaction(AuditControlClosedSegmentProof):void; private fence(AuditControlSegmentSelection):void; R4 | PublicationWorkflow alone calls prove: bounded RR write root, B2 then publication fence, actual existing full protected proof, commit. PublicationWriter.segmentAdvance alone calls reread under its already-owned same-Connection RR write root; reruns the actual reader and exact14 comparison without opening/nesting a root. No full proof in READ ONLY/floor. 8. |
| Control/AuditControlPublicationWorkflow | ctor(AuditControlPublicationAcquisition,AuditControlHistoryCapture,PostgreSqlAuditControlPublicationWriter,AuditControlSegmentProofWorkflow,AuditSigningGraphState); progress():void; rootRevision():void; segmentAdvance():void; refresh():void; recoverExact():void; R4 | Only ControlRuntime calls progress; its four exact private-dispatch targets are methods of this same owner, not callbacks. Explicit zero-sentinel bootstrap is owned by the dedicated publication acquisition, not ordinary/control acquisition. No acknowledgement-producing method is present. 10. |
| Provenance/PostgreSqlAuditControlPublicationWriter | ctor(Connection,PostgreSqlAuditProvenanceSchema,AuditControlDelegateCustody,AuditControlSegmentProofWorkflow,AuditSigningGraphState); rootRevision(AuditControlPublicationAuthority):AuditControlPublicationCandidate; segmentAdvance(AuditControlPublicationAuthority,AuditControlClosedSegmentProof):AuditControlPublicationCandidate; refresh(AuditControlPublicationAuthority):AuditControlPublicationCandidate; recoverExact(AuditControlPublicationAuthority):AuditControlPublicationCandidate; private recheck(AuditControlPublicationAuthority):void | RetentionRuntime alone constructs; constructor creates one write Transaction and its BindingRows/TransitionRows/PublicationRows from the same supplied Connection/schema. Those stateless row helpers hold no second authority graph. PublicationWorkflow alone calls methods. Writer.segmentAdvance invokes the passed proof owner's rereadInCurrentTransaction before signatures/inserts. Other paths reread their actual expected predecessor/head. No independent F4 signer. 6. |

Control publication progress must perform real owned recovery/current-head/segment work when required; a method that always throws unavailable would not qualify bootstrap/rotation/real control checkpoint production. Detailed archive/floor publication and current-history authentication remain Root's imported implementation. The live-control acquisition rejects zero/pending floors; the separate exact publication owner alone handles their permitted paths. A bootstrap/root revision additionally needs the independent installer-selected policy input described in §8; progress() cannot invent that origin from a zero source or digest-only archive.

The proof owner must re-run the same accepted full B2 reader inside the final segment-advance mutation root, outside the external lock, and compare its exact14-field summary to the captured proof. This concrete dependency is passed to the final publication writer through the fixed segment proof owner/result relationship; no caller-created summary is accepted. Root must retain this rerun edge when assembling the two lane maps (see §8).

## 4. Actual ordinary authorization, audit and failure order

Before identity/selection/signing authority, Definition asserts the complete compiler tuple: typeId audit.sign.internal; exact final definition/value/handler/outcome; Inline strategy; typedSelfHandled=false, typedSelfHandledContext=false, typedSelfHandledMode=null; authorizationPolicy exactly the non-null owned final policy; transactionConnection=null; schedule=null. Exactly five attributes are allowed: OperationType, Accepts, Returns, HandledBy, Authorize. No Route, ConsoleCommand, ScheduledBy, Transactional, Deferred or ExecuteWith; no discovery/serializer registration.

Definition rejects existing policy ID in Container.has, getServiceIds, initialized or getRemovedIds; then sets the exact owned policy and checks get(ID) object identity. That identity and metadata tuple are checked again before each invocation and immediately before actual evaluate. Existing evaluator's null-policy early return cannot become implicit allow.

Identity calls actual ConsoleActorProvider.actor and ConsoleTenantProvider.tenant once each per invocation. Validate actual refs with strong AuditTypedReference rules, preserving exact raw installed pins. Null actor/provider failure/mismatch fails before selection or reservation. Tenant may be null only against the explicit null pin. Construct actual ExecutionContextFactory.receive/startAttempt1 using actual principal as authorization and origin, ActorRef(id=`console-runtime`,type=`system`) as execution, actual tenant and operations identifiers/clock. Do not synthesize identity from the pin, scheduler label, environment input or scope.current.

The pin grants pre-target preparation. Installed artifactKinds does not filter selection or preparation: a genuinely pinned principal whose installed list disallows checkpoint must produce the real immutable preparation, then encounter actual policy denial. New reservation retains its original selected key; F2 alone samples its new preparation Clock/UUID and preserves replay's zero-sampling behavior.

After preparation, Execution constructs the exact Value from the actual unsigned body, actual Operation and one actual envelope/context. The target is exactly schemaVersion1/kind audit_sign_target/artifactKind/partitionId/segmentId/artifactId/artifactHash, where artifactHash is F1 unsigned hash. Begin captures one trusted requestedAt plus random16-byte nonce. Call actual `operations.authorization.evaluate(metadata,sameEnvelope)` once. Null is allow only after fixed admissions; non-null reason is deny; exception is unavailable. Freeze the exact10-field sign context and requestRef from adopted LP/JCS framing, not a stored requestRef/OperationId/complete SHA.

Commit actual Durable requested before actual handler construction/invocation. Deny/unavailable commits actual Durable unavailable and throws byte-free failure; no seed/candidate/persist follows denial. Allow invokes the fixed actual Handler through actual HandlerInvoker once. Handler returns actual OperationResult; inspect isRejected before outcome. Wrong outcome/rejection/current-trust/persistence failure retains decision allow and the same requestRef, releases nothing and attempts unavailable only on a still-usable graph. Actual held persisted success also commits unavailable and throws at the absent F4 boundary. There is no accessed Required code or success result added merely to satisfy a source test.

All fixed drafts retain the accepted Framework catalog definitions, actual source/build ID, exact actor/tenant/target/context, policy audit-sign version1, empty correlation/evidence version1, Resource-purpose HMAC ID with `audit.sign.v1:<artifactKind>:<unsignedHash>`. Requested uses unknown/audit_access_requested and not_applicable/audit_access_requested, Durable key `audit.sign.v1:requested:<requestRef>`. Unavailable uses unknown/resource_unavailable and not_applicable/resource_unavailable, Durable key `audit.sign.v1:unavailable:<requestRef>`. No application-provided Draft, delivery mode, key or event string is accepted.

For each Audit.requested/unavailable method: graph.assertUsable; build its fixed Draft/request; then a narrow try contains exactly the actual delivery.deliverStandalone(request) call. Every escaping Throwable invalidates the shared graph before rethrowing the original finite audit failure. Cleanup never masks it. Draft validation is outside that narrow try. A failed requested call cannot trigger unavailable; a failed unavailable call cannot trigger further audit/production/release. The Runtime's failure/finally path checks that same graph before any terminal append. No held candidate/outcome survives delivery failure.

An intentional final ResourceUnavailable after both Durable calls returned normally is a healthy pending result, not commit ambiguity; known committed denial is likewise distinct. The cached graph still performs a fresh identity/context/decision next invocation. Conversely any ambiguous reservation/F2/step/actual delivery failure poisons both ordinary/control entries before their next lifecycle/provider/selection action. The accepted lifecycle's reconnect/health check and C3 store's local quarantine reset do not restore F usability. Recovery requires a genuinely new runtime/operations/database-manager/F2/F5 graph and current authentication of the visible winner.

## 5. Private facts and exact read/mutation projections

Fact results below are data, never reusable allow/control credentials. Each has private SensitiveParameterValue storage, R4 and a single exact guarded factory; no public properties, fromArray/deserialization, container registration, reflection construction or callback. Shapes are closed and bounded; any arrays here are one semantic native record or at most4 work steps, not a general state bag or lifetime history cache.

| Fact type (default Provenance directory) | Exact retained fields and proposed surface | Construction/read owners; total methods |
| --- | --- | --- |
| Signing/OrdinaryCheckpointSelection | actual ExecutionContext, native partition8, target segment12, native F2 AuditCheckpointSlot, checkpoint reservation/tail endpoint | private ctor(ExecutionContext,array partition,array segment,AuditCheckpointSlot,array workEndpoint); static fromSelection(same5); context(); native(): exact partition/segment/work record group; slot(); R4 =9. Selector alone creates; inspection/Registration/capture consume. No authenticated bit. |
| Control/AuditControlCheckpointSelection | system partition8, target12, actual Slot and work endpoint | private ctor(array partition,array segment,AuditCheckpointSlot,array workEndpoint); static fromSelection(same4); native(); slot(); R4=8. Control selector alone creates; inspection/Issuer/capture consume. Published binding membership is established by actual history replay, never a caller locator/boolean. |
| AuditCheckpointSigningKey | actual freshly acquired checkpoint key ref and fingerprint; no private material/snapshot/grant | private ctor(string keyRef,string fingerprint); static fromCurrent(array exactAdmittedOnlineKey):self; reference():string; fingerprint():string; R4=8. Only ReservationAcquisition creates after actual current online checks and original-key equality on resume. Matching Registration/Issuer and Recorder consume; no user/caller array factory edge. |
| AuditCheckpointReservationResources | actual one-use AuditCheckpointPhaseAuthority and actual AuditCheckpointSigningKey from this same acquisition | private ctor(AuditCheckpointPhaseAuthority,AuditCheckpointSigningKey); static fromAcquisition(same2); authority(); signingKey(); R4=8. Only ReservationAcquisition creates; matching Registration/Issuer reads and immediately passes fixed Recorder call. No cached/reserialized/reconstructed resource can authorize a later invocation. |
| ReservedCheckpointWork | exact reservation15/envelope, captured immutable checkpoint identity/boundary and selected key, existing verified work tail≤4 | private ctor(array reservation,array boundary,array steps); static fromCommitted(same3); reservation(); boundary(); steps(); R4=9. Recorder only creates after commit or authenticated exact winner; Preparation/capture consume. |
| PreparedCheckpointWork | ReservedCheckpointWork, actual AuditCheckpointPreparation, nullable exact preparation_bound row10 | private ctor(ReservedCheckpointWork,AuditCheckpointPreparation,?array boundStep); static fromActual(same3); reservation(); preparation(); boundStep(); R4=9. PreparationWorkflow creates unbound result only after actual F2 normal return; Recorder creates bound result only after ordinal1 commit. Handler/candidate requires non-null authentic bound step. |
| SignedCheckpointCandidate | PreparedCheckpointWork, exact complete bytes, canonical decoded F1 complete fact | private ctor(PreparedCheckpointWork,string completeBytes,array completeFact); static fromSigner(same3); prepared(); bytes(); complete(); R4=9. Only Root's fixed real CandidateSigner; Recorder consumes. It grants no current authority. |
| HeldCheckpointCandidate | PreparedCheckpointWork, exact committed ordinal2 native row10/envelope and exact complete bytes | private ctor(PreparedCheckpointWork,array candidateStep,string bytes); static fromCommitted(same3); prepared(); step(); bytes(); R4=9. Recorder only; Persistence/capture only consume. No caller's unsigned body/bytes replaces the committed candidate. |
| PersistedCheckpointOutcome | HeldCheckpointCandidate, actual complete native3, committed artifact_persisted native10/envelope | private ctor(HeldCheckpointCandidate,array completeRow,array persistedStep); static fromCommitted(same3); candidate(); complete(); step(); R4=9. Recorder only after native equality/ordinal3 commit or exact authenticated recovery. Handler/ControlProduction hold; no ingress/release method. |
| Signing/HeldAuditArtifactOutcome | PersistedCheckpointOutcome and exact target7 | private ctor(PersistedCheckpointOutcome,array target); static fromCheckpoint(PersistedCheckpointOutcome,OrdinaryAuditSignValue); checkpointForRelease():PersistedCheckpointOutcome; R4=7. Real Handler only creates. Execution alone inspects as held data; first-stage never exports bytes. Future F4 handoff is separately guarded. |
| Signing/OwnedSignRequestBasis | exact actor/tenant, target, requestedAt, nonce | private ctor(array requestBasis); static fromInvocation(ExecutionContext,OrdinaryAuditSignValue,string requestedAt,string nonce); basis():array; R4=7. ContextFactory alone creates; Execution/Audit/ContextFactory only use. Shape has no decision/requestRef yet. |
| Signing/OwnedSignContext | exact10-field adopted context including actual decision/requestRef | private ctor(array context); static fromDecision(OwnedSignRequestBasis,OrdinaryAuditSignDecision,string requestRef); context():array; R4=7. ContextFactory only creates from actual evaluation owner; Drafts/Execution only read. No allow token is accepted by handler. |
| Control/AuditControlSegmentSelection | system partition8, target12, actual immediate successor/predecessor12, exact external expected lineage endpoints | private ctor(array partition,array target,array neighbors,array lineage); static fromSelection(same4); native(); lineage(); R4=8. Actual control publication selection only; proof/capture/writer consume. Neighbors is fixed successor and nullable predecessor, not an arbitrary list. |
| Control/AuditControlClosedSegmentProof | exact selected segment relation plus accepted Reader's exact14-field scalar summary | private ctor(AuditControlSegmentSelection,array proof); static fromReader(same2); selection(); proof(); R4=8. ProofWorkflow only after actual Reader and root commit; capture/publication final reread consume. |
| Control/AuditControlPublicationCandidate | validated configured schemaName and exact committed native13 publication row including document_bytes | private ctor(string schemaName,array nativeRow); static fromCommitted(same2); schemaName():string; native():array exact12 scalars; documentBytes():string; R4=9. Only PublicationWriter after its actual successful commit or authenticated exact committed recovery. ControlHistoryCapture.publish consumes; no permission/currentness claim follows SQL presence. |

The control capture lane's selection names are `AuditControlBootstrapSelection`, `AuditControlRootRevisionSelection`, `AuditControlHeadSelection` and `AuditControlPendingPublication`. All are private facts with R4. HeadSelection has private constructor(AuditHistoryInstallation configuredScope,AuditControlSourceHint source,array nativePartition8,array nativeActiveSegment12), static fromSourceHint(same4), source():AuditControlSourceHint and native(): the exact partition/active-segment group —8 methods. Only actual PublicationWorkflow creates it from the bounded installed SourceHintReader and its admitted native projections; control capture alone reads it. PendingPublication has private constructor(AuditHistoryInstallation configuredScope,AuditControlPublicationHint hint), static fromFloorHint(same2), expectedFloor(): exact floor fact and candidateIdentity(): exact revision/document digest —8 methods. PublicationWorkflow alone constructs; exact capture/publisher consume. Its candidate identity is the actual floor pin or an already retained exact committed candidate for accepted-response-loss recovery, never SQL MAX. Hints grant no publication authority; locked replay must authenticate the complete expected floor and immutable candidate.

BootstrapSelection and RootRevisionSelection each have a private4-argument constructor(configured AuditHistoryInstallation, exact independently selected archived policy digest/version fact, expected zero or accepted predecessor fact, actual bounded native B2 target), one installation-only factory and policy()/expected() fixed accessors plus R4 (8 methods). Their **factory caller is not available in current Product/adopted configuration**. Root must name the independent installer entry before issuing their producers; no generic fromArray, SQL/latest/archive-enumeration, task argument or normal acquisition is an allowed constructor. This is the one unresolved publication-selection origin, not a claimed ready installer API.

Actual partition projection8: partition_id, tenant_type, tenant_id, active_segment_id, last_sequence, head_record_hash, last_assigned_recorded_at, state_version. Actual segment projection12: partition_id, segment_id, state, first_sequence, final_sequence, head_record_hash, predecessor_record_hash, opened_at, closed_at, successor_segment_id, close_fencing_token, state_version. UUID output uses ::text, timestamps canonical UTC microseconds, integers safe canonical native/string admission. No native projection is trusted merely because SQL returned it.

F2 preparation exact7: checkpoint_id, partition_id, segment_id, closed_state_version, close_fencing_token, unsigned_checkpoint, signing_key_fingerprint. Complete exact3: checkpoint_id, checkpoint_hash, complete_bytes. Admission validates canonical body/ID/hash/key/fingerprint, closed-version/token and sealed-version=closed+1. It retains actual bytes, not only digests. An absent complete row does not mean absent preparation; sealed without its valid exact prepared+complete pair is failure.

Reservation native15 and step native10 are exactly AppendixA's column declaration order. Step ordinal1/2/3/4 is respectively preparation_bound/complete_candidate/artifact_persisted/ingress_acknowledged; complete_bytes exists only on ordinal2. Our prerequisite produces only1–3; it can validate a historical4 but cannot infer full F4 retirement or invent an ack producer. SQL Q4 LIMIT5 detects an illegal extra row and never truncates to a seemingly valid four.

ArtifactFacts.preparation returns the actual canonical unsigned bytes, unsigned F1 hash, byte SHA/length, target7 and original boundary/fingerprint from accepted Preparation. ArtifactFacts.complete returns exact canonical decoded complete body, checkpointHash and byte SHA/length; it never asserts current trust. Current F1 verification and exact candidate/native equality remain mandatory at their phase owners. This centralizes the new direct F1 codec call sites without exporting Checkpoint helpers.

## 6. SQL roots, selection and persistence ownership

### 6.1 Connection, locks and errors

Every phase checks shared state and actual Connection inactive/autoCommit=true/level0 before authority, Clock/provider or SQL-root work. Capture begins one RR root then sets native READ ONLY, then SET LOCAL lock_timeout='1000ms' and statement_timeout='5000ms'. It contains no FOR UPDATE/advisory locks/protected-body proof. Capture commits and closes its root before external floor locking. Read transactions never call F2 Store or its locking Rows/Reader.

Mutation begins its separate RR read-write root and applies the same local limits. Lock order is exact B2 advisory key `blackops.audit.partition.v1:<schema>:<partition>`; publication-only key `blackops.audit.control.publication.v1:<schema>:<installationRef>` second; B2 state then target/successor/predecessor; F2 preparation then complete; reservation then work steps ordinal order; relevant control/publication rows and dispatch. Semantic publication always uses system:global and that B2-before-publication order. No lifetime scan, external lock or F2-owned root is nested. F2 retains its own accepted state/order/timeouts; this proposal does not add SET LOCAL to F2 or change its APIs.

Successful commit verifies inactive/autoCommit/level0 before a result escapes. Any native transaction uncertainty discards tentative values, invalidates the common graph first and attempts only certainly owned cleanup/close. Do not touch caller-owned/inconsistent roots. Safe finite failures have no prior Throwable/path/SQL/value diagnostic. Integrity mismatch is distinct from unavailable current authority; timeout/deadlock/serialization/unknown-winner uniqueness/commit-response failure never grants origin or produces a result. A zero-row read after an RR fence wait is not proof of absence if a conflicting winner may be invisible.

New fresh-graph recovery authenticates the visible immutable winner, exact production class/reservation, original body/ID/time/key/candidate and native F2 equality. It cannot reconnect the same stores, infer rollback from level0 or synthesize replacement bytes/IDs. Dispatch due/complete/retry fields are advisory, rebuildable from immutable rows and never hide a winner or authorize a phase.

### 6.2 Literal selector ownership and frontier

The frozen selector addendum's Q1, Q2a/Q2b, Q3, Q4, Q5/Q6 and Q7 remain the exact query texts. Their copies are in AppendixB. Only validated schema owners construct quoted identifiers. Parameters bind after grammar/type validation; nullable tenants use NULL, UUID remains native UUID and sequence cursor is canonical decimal STRING cast to bigint. No search_path, OFFSET, user identifier, mutable dispatch-first/anti-join or producer-filter query is permitted.

Q1 binds the full tenant pair and the actual derived partition. Q2 scans closed **and sealed** native segments ordered by first_sequence/segment_id with page32. Q3 queries the unique checkpoint slot by partition/segment/artifact_kind only, without installation/classification/retry filters; LIMIT2 is an integrity sentinel. Q4 returns all≤4 work steps in ordinal ASC including exact candidate bytes. Q5/Q6 are Checkpoint-owned exact native projections. The mutation root takes Q7 and rereads the selected facts under the same fence/locks.

Selector uses current authenticated history inspection, not decoded SQL phase/class or a cached grant, before deciding pending/new/blocker. Authenticated ordinary pending work precedes any new slot; a prior unresolved slot cannot be skipped. Reservation absent+F2 present is unattributed failure. Ordinary cannot reclassify authentic control work. Malformed/incomplete/unverifiable rows remain blockers, not absence. Earliest new closed slot requires both reservation and F2 absence plus actual complete immediate predecessor, or real genesis firstSequence1/no predecessor. Predecessor comes from actual successor_segment_id edge and accepted proof, never UUID order/MAX alone.

The complete future ordering is receipt continuations, pending checkpoints, then new checkpoints. This prerequisite supplies only checkpoint selection and therefore has no receipt-ready/no-work authority. It does not retire a completed-looking older slot using ordinal3/4, sealed flag or mutable index. No candidate or blocked/missing continuation yields safe pending ResourceUnavailable; no global ordinary no_work success is fabricated. All existing pending resumes re-enter actual identity, context, target evaluation and current trust before seed/persistence. Installed kind denial remains after real preparation.

### 6.3 Exact durable sequence

| Phase | Real caller/SQL boundary | Mutation/read postcondition |
| --- | --- | --- |
| Reservation | Actual Registration/Issuer -> fixed capture -> locked current replay -> recorder's fenced write root | Recheck absent/present slot/native F2 and frontier. New UUIDv7 only after proved absence; hardcoded origin/class; selected current key/fingerprint. Insert reservation+dispatch atomically, or return only an authenticated exact winner. |
| Before prepare | PreparationWorkflow.beforePrepare capture/current online+control checks and a fresh bounded endpoint recheck root; close it | Same original reserved work/key. Current authority is not derived from F2 replay. No externally locked SQL. |
| Actual F2 prepare | Existing Store.prepare(expectedTenant,partition,segment,originalKeyRef,freshSnapshot) in its own real root | F2 alone owns new UUID/time; replay retains original bytes/time and no sampling. Failure exposes no target and poisons the F graph conservatively. |
| Preparation binding | afterPrepare captures actual returned/prepared/native facts; fresh phase authority; recorder write root | Actual F2 canonical unsigned bytes/boundary/fingerprint must match the returned preparation and reservation. Append/recover exact ordinal1 only, no new F2 fields or resigning conflict. |
| Ordinary decision | Actual one evaluator, context and Durable requested; actual one HandlerInvoker | Deny/unavailable retains preparation, zero seed/candidate/persist. Control enters only its separate constructed owner without ordinary exemption leakage. |
| Complete candidate | CandidateWorkflow fresh phase capture/replay+online signing acquisition; fixed real F1 signer; recorder write root | Verify same unsigned body/reserved ref/fingerprint and current trust; commit exact complete bytes plus delegate-signed ordinal2 before native artifact. Existing candidate is authenticated and reused exactly, never resigned after rotation. |
| Actual F2 persist/reconcile | beforePersist capture/current acquisition and bounded endpoint recheck close; actual Store.persist(expectedTenant,partition,segment,exactCandidateBytes,freshSnapshot) | Own F2 root. Replay's early identical-byte return is not current-trust proof. After uncertain result no follow-up phase/C3 on this graph. |
| Persisted outcome | afterPersist captures actual exact3 native complete and candidate; separately fresh phase acquisition; recorder write root | Compare full exact bytes, F1 hash, unsigned body/boundary and origin chain. Append/recover exact ordinal3 only. Return actual PersistedCheckpointOutcome after commit. |
| Held operational boundary | Real Handler returns HeldOutcome; Execution verifies type/target, commits Durable unavailable if healthy and throws safe ResourceUnavailable | Zero actual Required access, external submit, receipt ack, D ingress ack or seal. Pending work remains. Full F4/D is still required for normal task completion. |

F5 does not wrap actual F2 prepare/persist in its own open root. Before each later action use a new capture/acquisition, not a cached control or online result. The external floor must be unlocked/closed before the fenced DB root. The private anonymous transcript may remain owned solely through the exact fenced original-byte reread; MutationAdmission closes it with a checked close after comparison and before delegate seed/sign or a committed phase result. Close failure aborts the owned root and returns no result. Inspection closes before returning inspected facts; pre-F2 phases close before calling actual F2. A bounded reader pass is not native closure. The phase result remains exact-capture/target/head-bound and one-use; no files/SQL atomicity claim follows.

## 7. Immutable SQL schema, history readers and migration

Recommend the still-unreserved path `migrations/postgresql/Version20261012000000.php`, class `BlackOps\Migrations\PostgreSql\Version20261012000000`. Root alone reserves/issues it. Existing latest inspected migration is Version20261001000000; no historical migration/schema statements are rewritten. Constructor(Connection,LoggerInterface,string schema) retains the accepted configured-schema factory pattern. getDescription() returns `Add the PostgreSQL audit signing provenance schema.` up(Schema) loops the schema owner's29 statements; down(Schema) throws irreversible. Four methods; no extra factory/discovery/configuration change or database command has run.

`Provenance/PostgreSqlAuditProvenanceSchema` has ctor(PostgreSqlMigrationSchema), names(): exact9-name map, statements():list<string>, private immutableFunction():string, private immutableTriggers():list<string>, private checkpointSelectionIndex():string —6 methods. Names map keys are policyEnvelopes, segmentBindings, workReservations, workSteps, policyTransitions, transitionAcks, publicationCandidates, workDispatch, policyDispatch. They map to the exact nine audit_f_* tables in AppendixA and retain exactDDL6eb42122's names map.

Two Connection-free pure DDL helpers keep the owner finite. `PostgreSqlAuditProvenanceWorkDdl`: private ctor, static statements(schema), private workReservations/schema, workSteps/schema, workDispatch/schema, indexes/schema —6 methods. `PostgreSqlAuditControlHistoryDdl`: private ctor, static statements(schema), private policies/schema, segments/schema, transitions/schema, acknowledgements/schema, publications/schema, dispatch/schema, indexes/schema —9 methods. Their statement results are closed named table/unique-index/due-index groups, not arbitrary SQL builders. Schema.statements assembles the fixed global order below. No dynamic table/type/constraint name enters either helper.

Order is9 tables,9 partial unique indexes,2 dispatch due indexes,1 immutable rejection function,7 immutable statement triggers, then index29 `audit_segments_checkpoint_selection_idx` on B2 `(partition_id,first_sequence,segment_id) WHERE state IN ('closed','sealed')`. The helper produces schema-qualified tables from the validated schema object; index/constraint/trigger names are fixed quoted identifiers. No IF NOT EXISTS/CONCURRENTLY/default schema assumption or owner GRANT is added. All exact PK/FK/unique/check/byte bounds and C-collation text encodings are in the unchanged28-statement body plus29.

Nine tables are seven immutable policy/segment/work/step/transition/ack/publication collections plus two mutable dispatch tables. Native UUIDv7 and safe-range bigint/string checks remain explicit; canonical payloads are bytea, not JSON/serialized objects. Signed equality/current permission is always application verification, never inferred from a SQL CHECK/FK. Actual B2 `(partition_id,segment_id)` and actual F2 complete checkpoint_id keys are used; there is no fabricated F2 complete partition/segment key.

No UPDATE/DELETE/TRUNCATE of the seven immutable tables is permitted through legitimate runtime SQL; fixed SECURITY INVOKER function with pg_catalog search_path rejects every such statement including no-op statements. Native tests must qualify trigger/role ownership and default/nondefault schema; owner/superuser bypass is outside any claim of tamper-proof storage. Dispatch updates stay unsigned and cannot change authenticated work/transition/head facts.

Finite history SQL owners (new Provenance files):

| Type | Exact methods (all row arguments are fixed closed AppendixA shapes) | Production callers/bound |
| --- | --- | --- |
| PostgreSqlAuditControlBindingRows | ctor(Connection,Schema); policy(string digest):array\|false; binding(string digest):array\|false; nextBinding(string installation,?string predecessor):array; insertPolicy(array):void; insertBinding(array):void | Capture history reads, actual publication writer writes. Successor lookup uses exact null-aware predecessor and LIMIT2; no installation-less traversal. 6 methods. |
| PostgreSqlAuditControlTransitionRows | ctor(Connection,Schema); transition(string digest):array\|false; acknowledgement(string digest):array\|false; nextTransition(string installation,?string predecessor):array; insertTransition(array):void; updateDispatch(array exact4):void | Capture and publication writer only. No acknowledgement insert before actual D owner; preserved ack table is read/verified when actually present. 6. |
| PostgreSqlAuditControlPublicationRows | ctor(Connection,Schema); byRevision(string installation,string revision):array\|false; byDocumentSha(string digest):array\|false; previous(string headDigest):array\|false; nextHead(string installation,?string predecessor):array; insertCandidate(array):void | Capture/proof/publication writer and exact pending recovery only. No mutable published flag/latest-wins lookup. 6. |

Each exact history row read uses its PK/unique endpoint and LIMIT2 where duplicate detection is not implied by the actual native key; each predecessor/successor link is checked. Published/staged distinction and exact external floor pins drive which rows are needed. Unlimited segment/transition/publication/root history streams one bounded original envelope at a time, with no total-history LIMIT, retained issuer set, scalar grant or superset revocation substitute. A bounded≤32 **pending transition** backlog is the adopted backpressure rule, not a historical lifetime cap. The Row owners never expose raw DB rows to application services or call providers/signers.

The following closes the remaining mutation/read SQL projection choices. For every collection, SELECT/INSERT explicitly lists the AppendixA declaration-order columns; UUID outputs use `::text AS <same_name>`, timestamptz outputs use canonical UTC microseconds, bytea remains the bounded original bytes. No SELECT*/RETURNING*, JSON, dynamic column list, automatic row object serialization or permissive merge is allowed. Exact non-byte scalar groups are split by the history lane only after this native record is admitted.

| Fixed Row method | Literal predicate/order or mutation contract |
| --- | --- |
| WorkRows.byCheckpoint | AppendixB Q3 exact15, `partition_id = :partition_id AND segment_id = CAST(:segment_id AS uuid) AND artifact_kind = 'audit_checkpoint' LIMIT 2`. No owner/install filter. |
| WorkRows.byWork / steps | Exact15 by `work_id = CAST(:work_id AS uuid)`; exact10 Q4 same work predicate `ORDER BY ordinal ASC LIMIT 5`. Native work UUID was admitted before query. |
| WorkRows.lockCheckpoint / lockWork | The same fixed slot/reservation projections with FOR UPDATE; lockWork then reads exact10 ordered steps with FOR UPDATE. Only mutation Admission calls them after B2/actual F2 lock order. Capture cannot call these names. |
| BindingRows.policy / binding | Exact policy8 by `policy_digest = :digest`; exact binding10 by `binding_digest = :digest`. Installation/semantic fields are authenticated, never silently inferred from these lookups. |
| BindingRows.nextBinding | Exact binding10 by `installation_ref = :installation_ref AND previous_binding_digest IS NOT DISTINCT FROM CAST(:previous_digest AS text) LIMIT 2`. Null is the actual bootstrap predecessor, not wildcard. |
| TransitionRows.transition / acknowledgement / nextTransition | Exact transition9 by transition_digest; exact ack7 by acknowledgement_digest; exact transition9 successor by installation_ref and null-aware previous_transition_digest, LIMIT2. No due-time/current class filter. |
| PublicationRows.byRevision / byDocumentSha / previous / nextHead | Exact publication13 by installation_ref plus `revision = CAST(:revision AS bigint)`; by document_sha256; by the supplied exact predecessor head_digest; successor by installation_ref and null-aware previous_head_digest LIMIT2. No latest/ORDER BY revision DESC fallback. |
| WorkRows.insertReservation / insertStep | INSERT every exact15/exact10 column with named bound value; cast native UUIDs, bigint ordinal/previous ordinal, bytea envelope/candidate explicitly as required. Reservation/key/time are already owned fixed signed input. Return only after affected-row count exactly1 and root commit; no ON CONFLICT DO NOTHING suppresses an invisible winner or contradiction. |
| BindingRows.insertPolicy / insertBinding; TransitionRows.insertTransition; PublicationRows.insertCandidate | INSERT every exact table column, including the original envelope/document bytes and all projected signed fields. Existing exact immutable rows are read/authenticated/reconciled before deciding no insert; uniqueness failure alone is never success. Policy mirror equality does not grant external root authority. |
| WorkRows.updateDispatch / TransitionRows.updateDispatch | Fixed INSERT into exact work5/policy4 columns, ON CONFLICT at the actual work_id/transition_id PK DO UPDATE of only the remaining unsigned columns from EXCLUDED. Called only after immutable insert/exact read and under the same owned root/fence; source_digest is actual authenticated tail. Retry/due/state is advisory and can be rebuilt. No signed row UPDATE or ingress completion write. |

Counts in this matrix are native storage columns, not semantic envelope member counts. The frozen DDL remains authoritative for exact columns/nullable/cast checks; the companion manifest records its per-statement bytes. Native SQL parsing, DBAL conversion, installed constraints and role enforcement remain qualification obligations, not results of this read-only map.

## 8. Exact cross-lane handoff — history, current acquisition and custody

The history investigator confirmed these candidate imports under `Internal/Audit/Authority/History/`:

| Capture/inspection method | Input | Output/purpose; exact callers |
| --- | --- | --- |
| AuditCheckpointReservationCapture.ordinary / .control | OrdinaryCheckpointSelection / AuditControlCheckpointSelection | AuditCapturedCheckpointHistory, separate fixed reservation phases. Called only by the matching Registration/Issuer. |
| AuditCheckpointPreparationCapture.beforePrepare / .afterPrepare | ReservedCheckpointWork / PreparedCheckpointWork | Separate fixed pre-F2 and preparation_bound captures. PreparationWorkflow only. |
| AuditCheckpointCandidateCapture.beforeCandidate | PreparedCheckpointWork | Candidate capture, CandidateWorkflow only. |
| AuditCheckpointPersistenceCapture.beforePersist / .afterPersist | HeldCheckpointCandidate | Separate pre-F2 persistence and artifact_persisted captures. PersistenceWorkflow only. |
| AuditCheckpointHistoryInspection.ordinary / .control | Matching untrusted selection | AuditInspectedCheckpointSlot. Internally capture/replay actual history/current restrictions; return authenticated native facts only, never phase authority/seed. Matching selectors only. |

`Authority/AuditControlAcquisition::checkpoint(AuditCapturedCheckpointHistory):AuditCheckpointPhaseAuthority` is the normal current acquisition, and inspectCheckpoint is its non-producing inspection seam. It rejects zero/pending floor, performs full locked replay/current historical-issuer restrictions/one trusted phase Clock/floor update, closes/unlocks, and returns only the exact capture/phase/target/endpoint-bound one-use result to the named phase owner. No SQL, seed/provider/key lookup, current-online acquisition/verification, F2 or native artifact signing occurs under that floor lock. Publication uses the separate `AuditControlPublicationAcquisition`, never a mode flag or normal-acquisition fallback for bootstrap/pending. The detailed concrete acquisition/representation count and descriptor parser internals are owned by the other lane.

Fixed publication capture methods are bootstrap(BootstrapSelection), rootRevision(RootRevisionSelection), segmentAdvance(actual ClosedSegmentProof), refresh(HeadSelection), recover(PendingPublication), publish(actual committed PublicationCandidate). Each returns its own phase-bound AuditCapturedControlHistory. The dedicated `Authority/AuditControlPublicationAcquisition` has constructor(AuditControlCandidateAcquisition,AuditControlPublisher), fixed bootstrapCandidate/rootRevisionCandidate/segmentCandidate/refreshCandidate(AuditCapturedControlHistory):AuditControlPublicationAuthority, and publish/recover(AuditCapturedControlHistory):AuditControlPublicationResult. It delegates the first4 to the exact CandidateAcquisition and the last2 to the exact Publisher; constructor plus6 operations leaves3 methods within10. PublicationWorkflow retains its5 dependencies. A writer's actual committed candidate must pass Capture.publish (phase15) and PublicationAcquisition.publish before publication success; SQL commit alone cannot be returned as published. Pending recovery uses Capture.recover (phase14) with exact floor pins and immutable candidate authentication; it never signs a replacement head/revision. Publisher performs no SQL under floor and returns only a publication fact, never phase permission or D completion.

The fixed private capture phase set is1 inspectOrdinary,2 inspectControl,3 reserveOrdinary,4 reserveControl,5 beforePrepare,6 afterPrepare,7 beforeCandidate,8 beforePersist,9 afterPersist,10 bootstrap,11 rootRevision,12 segmentAdvance,13 headRefresh,14 publicationRecovery,15 publishCommittedCandidate. Only the named capture methods construct those scopes. The numbers are history-lane private framing, not a public phase selector argument.

Root has selected one explicit refinement of the earlier blanket no-F1-under-floor statement, still requiring bounded review/adoption: only `AuditCheckpointCodec::signingInput`, `decode` and `bytesHash`, through the single `Authority/History/AuditCheckpointArtifactReplay` owner after bounded raw preflight, may perform their existing pure validation/hash work under the control floor. No complete(), ArtifactSigner, current online key/trust lookup, SQL/provider/Clock/seed or nested floor acquisition is granted by that exception. The history lane owns the exact downstream pure graph/source proof; ArtifactArchitectureTest must add precisely this owner's three calls, not an F1 directory exemption. ArtifactFacts in this map stays outside the lock. This avoids a second artifact parser or trusted caller-supplied scalar hash projection.

Minimum retained/checkable facts are: configured schema and installation; actual tenant/derived partition/selected segment; fixed invocation phase; exact accepted external wrapper SHA/rootPolicyDigest/head digest/policy version/revision; head currentSegment triple, closedSegmentTailDigest, policyStateDigest, transitionTailDigest and ack cursors; native partition8/segment12 and actual immediate neighbors; reservation15 and≤4 step10 envelopes; exact original unsigned and complete F2 bytes/projections or explicit absence; published closed-binding path through bootstrap; separately marked staged successor path; selected oldest pending transition/ack relationship; exact publication candidate/previous-head/floor-pending document bytes/digest. Native step rows are captured ASC; their≤4 envelope authentication may replay tail-to-reservation using bounded offsets before forward semantics. Unlimited lineage/publication paths run tail-to-bootstrap with repeat scans where needed, not unbounded indexes.

For final mutation compare actual bytes/boundary/absence, original selected frontier, work digest/tail, actual F2 pair, and relevant B2/head/transition/publication endpoint under the named fence. Authentication of a captured chain is necessary but endpoint equality is also required. Drift returns safe failure and a new invocation/capture, never rebinding the same phase authority to a new head/target. SQL winner absence after RR waiting follows fresh-graph recovery. The authority result cannot authenticate a later phase merely because historical restrictions once passed.

Three finite crypto/custody joins remain for Root's overall authority file map, rather than invented ready implementations. Candidate import paths are `Authority/AuditCheckpointPhaseAcquisition.php`, `Authority/AuditCheckpointCandidateSigner.php` and `Authority/Custody/AuditControlDelegateCustody.php`; Root's concrete online-acquisition path/type must be supplied by that owner. Their constructor and method requirements below are binding handoff facts; those paths are not claimed existing or issued. ReservationAcquisition remains a Provenance owner and imports the same actual online acquisition.

1. **Actual online checkpoint acquisition**, joined through ReservationAcquisition and PhaseAcquisition. ReservationAcquisition has the two fixed typed methods in §3, producing current key metadata before the signed reservation; this is necessary even though actual F2 prepare occurs later. PhaseAcquisition methods are beforePrepare(ReservedCheckpointWork,AuditCapturedCheckpointHistory):array exactValidatedF1Snapshot; afterPrepare(PreparedCheckpointWork,AuditCapturedCheckpointHistory):AuditCheckpointPhaseAuthority; candidate(PreparedCheckpointWork,AuditCapturedCheckpointHistory):AuditCheckpointPhaseAuthority; beforePersist(HeldCheckpointCandidate,AuditCapturedCheckpointHistory):array exactValidatedF1Snapshot; afterPersist(HeldCheckpointCandidate,AuditCapturedCheckpointHistory):AuditCheckpointPhaseAuthority. These receive the workflow's actual one capture; they do not recapture or accept a phase flag. The two pre-F2 methods consume control authority, perform the short fenced exact endpoint reread/checked transcript close and acquire the real fresh online snapshot after control lock release; beforePersist also verifies the actual candidate against that current online snapshot before unchanged F2 replay can return. Returned snapshot data goes only to the named actual F2 call and is not production permission. The other3 methods return the control result to the fixed recorder path; CandidateSigner independently reacquires current online authority before seed/sign. Proposed PhaseAcquisition constructor dependencies are actual ControlAcquisition, Root's concrete online acquisition, write Transaction, MutationAdmission and GraphState (5); ctor+5 fixed methods+R4=10. Root must bind the concrete online acquisition and exact verifier/key/custody methods rather than let a worker invent them. No cached grant, caller-supplied snapshot/time, public credential, provider callback or seed for a denied target is allowed.
2. **AuditCheckpointCandidateSigner** is the sole new actual `AuditArtifactSigner::checkpoint(unsigned,privateSeed,snapshot,trustedNow)` caller. Its fixed `complete(PreparedCheckpointWork,AuditCheckpointPhaseAuthority):SignedCheckpointCandidate` performs actual online acquisition/seed custody and same-key/fingerprint checks, or authenticates the exact already-committed candidate before returning a typed recovery fact. Private raw seed never leaves that owner/custody block. Actual F1 verifier must check current snapshot on recovery before persistence; unchanged F2 replay is insufficient. No application-supplied unsigned/complete bytes, key path or allow flag is accepted.
3. **AuditControlDelegateCustody** is the one private delegate holder behind Recorder and PublicationWriter; Root's fixed frame builders must issue only the exact reservation/step/segment/transition/head domains and permissions, with raw parser/delegate checks from adopted130. Work recorder has five fixed methods and no committing Anchor-frame method. Its exact leaf native signing/custody callers and safe representation belong in Root's map, not an arbitrary `sign(payload)` method added here.

These names are a finite imported contract to reconcile with the authority map, not authorization for a worker to improvise them. Constructor dependencies/seed/floor/frame APIs must be fully bound in the overall packet before Product issuance. No remaining SQL/ordinary caller choice depends on selecting an arbitrary parser or filesystem API.

One additional genuine current-source gap is publication selection: the first policy source is zero, history archive lookup is digest-only/non-enumerable, and existing installed configuration has no next-policy digest. Root must name an independently authenticated installer -> exact archived policy selection input for bootstrap/root rotation. This map deliberately gives their facts no invented live constructor. Refresh and exact pending recovery can use bounded installed source/floor hints, but those routes do not manufacture a first/new root policy. First-prerequisite bootstrap/rotation proof remains contingent on that exact entry, not satisfiable by preseeded mock provenance.

Control proof join: select actual system lineage; run actual full B2 proof in its own RR write root; capture only the resulting exact14 scalar proof/native neighbors into a later RO transcript; current locked replay; final publication writer's RR root invokes `proofs.rereadInCurrentTransaction(actualProof)` and exact endpoint comparison outside the floor before fixed binding/transition/head signatures/inserts. Section3's five-argument writer constructor supplies that exact proof owner and constructs only its stateless same-Connection SQL helpers. No fake validated proof constructor or second protected record graph is acceptable.

## 9. Required, D-control and F4 ownership retained

The implemented prerequisite stops after actual internal persistence/outcome and requested/unavailable auditing. A future runtime-owned ordinary handoff must revalidate the same held bytes/target/current authority and actual F4 checkpoint claim/call marker, close all roots/locks, assert the same actual operations Connection at level0/autoCommit, and call the real C3 `deliverStandalone(fixed accessed Required/null-key request)`. Only its normal return permits the exact actual F4 checkpoint submit owner for that already-bound claim/target. The receipt continuation separately validates actual A5 accepted intent/input/receipt/checkpoint/claim and performs its real Required gate before actual F4 acknowledgement. Neither join is an application callback, C3 lookup receipt, successful stub or second context.

The exact missing F4 owners are receipt-first restored-intent selector, checkpoint claim/call preparation and post-Required submission, receipt input/persistence owner and post-Required acknowledgement. They retain native14, exact-C, original-claim delta0/1/2 and atomic signed Anchor genesis/revision/head mutation. Shared F5 recorder must not commit detached F4 signatures. Actual C3 Required failures/future post-check failures preserve zero external release and finite audit precedence; no Durable downgrade or release in catch/finally.

D-control absence leaves typed persisted work/policy transitions and unsigned dispatch pending. No raw complete/index flag, fabricated mock result or signed ack can stand in for actual ingress completion. The schema's ack collections support the full adopted contract, but this packet exposes no ingressAcknowledged/insertAcknowledgement/ack publication writer. Future actual D may append its exact signed acknowledgements only through independently frozen typed owner joins. Publication success alone is neither work permission nor D completion. First-stage scheduler status remains explicitly incomplete; full F5 AC2–6 and F4/D operational acceptance remain open.

## 10. Actual architecture owners and meaningful verification map

All entries below are required future implementation proof, **not tests run here**. The map names actual current test files/methods inspected in C. Current successful predecessor results are not invalidated merely by this evidence file; Product changes require their assigned verification.

| Actual existing guard owner | Exact delta and mandatory discriminators |
| --- | --- |
| `tests/Architecture/AuditDeliveryArchitectureTest.php`: testPublicAndProtectedCallableSurfacesAndSensitiveMatrixAreExact; testDirectCallAndConstructionMapsRemainBoundedAndActivationIsAbsent; testC3PropertyDependencyAndForbiddenReferenceMatrixIsNormative; testC3ReferenceOracleIsSemanticAndRejectsIndirectOrDynamicCallers | Pin both composer public signatures/private build and three assembly result inventories/fields. Permit only RetentionRuntime.signingTasks -> composeForCheckpointProduction and fixed Audit wrapper's two deliverStandalone calls; actual C3 instances remain same operations Connection. Added/removed/replaced/aliased/dynamic delivery/composer calls fail; no direct Required/appendRequired/outbox-store bypass added. Preserve exact old construction counts (one Hmac/provider/mutation/Runtime graph) and actual standalone boundary. |
| `tests/Architecture/ApplicationAuditAuthoringArchitectureTest.php`: testRegistrySourceHasOnlyTheTrustedAssemblyAndReferenceSeam; testRegistrySourceMutationControlsUseParseValidResolvedSemanticEdges; productionCallerViolations | Add only RetentionRuntime.auditCatalogs's actual constructor/lookup and optional actual provider has/get/type path. Preserve Framework catalogs and one provider invocation. Wrong/null fallback, second registry, copied prepared-catalog codec, unlisted sibling/application/dynamic constructor fails; legitimate namespace aliases/FQCN pass. |
| `tests/Architecture/AuditCheckpointArchitectureTest.php`: testExactTypeInventoryAndCallableSurfaces; testCheckpointStateMapIsExactAndPrivate; testCheckpointDependencyMapAndConstructorGraphAreExact; testCheckpointLocalCallerOwnershipIsExactAndMutationVisible; testCheckpointOwnInstanceCallerOwnershipIsExactAndMutationVisible; testSourceGraphHasNoActivationOrExternalRegistrationEdge | Add exactly Admission/AdmissionQueries/Slot and proof-components store/reader/admission constructors. Store.prepare sole outside owner PreparationWorkflow; Store.persist sole outside owner PersistenceWorkflow; actual full Reader only ControlSegmentProofWorkflow/final same proof join. Add only exact preparation/slot read owners in §§3/5/8. Reject any outside Rows/Transaction/Trust helper reference, public getter, constructor copy, nested-root flag, artifact kind filter before prep, extra preparation column or altered replay sampling. Replace empty-outside map with reviewed exact entries, never a directory exemption. |
| Same Checkpoint file: testStoreRootSetsRepeatableReadImmediatelyAfterBegin; testCheckpointSourceMutationsAreRejectedAndReadLocksRemainValid | Existing F2 roots unchanged. New RO capture code cannot call locking F2 helpers; mutation Admission cannot omit B2 fence/tenant/boundary/native exact equality. Alias-positive and parse-valid lock/read-state mutations are mandatory. |
| `tests/Architecture/AuditArtifactArchitectureTest.php`: testNoOutsideProductionCallerOrRegistrationExists; testF2ArtifactOwnersAndCallCountsAreExactAndMutationResistant; testResolvedCallerGuardCoversDirectAliasGroupAndDynamicMutations; testResolvedStaticCallsStayWithinTheArtifactInventory | Preserve current F2/F3 codec/verifier counts. New exact codec owner ArtifactFacts has signingInput1/decode1. Root's sole CandidateSigner has checkpoint signer1 and explicit exact current-verifier call map; no anchorReceipt signer grant. The authority lane must list its actual trust-snapshot codec callers. Unlisted signer, direct sodium from ordinary/SQL code, scalar grant, reused snapshot or seed getter fails. No unspecified wildcard allows Root-owned types. |
| `tests/Architecture/AuditStoreArchitectureTest.php`: testB2SemanticDependenciesAndIndirectCallGuardsAreMutationResistant; testB2DeclaredPropertyInventoryIsExactAndMutationResistant; testStoreDependencyAndActivationBoundaryAreStaticAndBounded; testStorePipelineAndMutationBoundaryRejectUnexpectedCallsOrReferences | Exact B2 references for proof-components, selection queries, AdmissionQueries and migration index owner; new code never appends canonical control records directly. Preserve record protection/row tenant admission/record-chain proof ordering. Reject wrong-table schema, SQL-under-floor, proof-in-RO, or independent DB manager/Connection substituted into F. |
| `tests/Architecture/AuditRecordArchitectureTest.php`: testLogicalAndCanonicalConstructionCallsitesAreBounded; testB1ProtectedConstructionAndHydrationCallsitesHaveOneOwner; testA2TypesHaveNoPublicApiMarker; testA2DeliveryTimestampExceptionIsNarrowAndMutationResistant; testD1OperationalProviderReferenceAndNonactivationAreExact | Pin only two fixed sign Draft builders, exact operational catalog definition lookup, real context timestamp/source/opaque resource derivation. Keep protection codec/record read counts and no direct canonical control-event writer. Class/field/call tuple mutations reject substituted event/action/policy/delivery/Clock/context; no full ordinary-D activation is inferred. |
| `tests/Architecture/AuditOutboxStorageArchitectureTest.php`: testDependencyAndRuntimeBoundaryRejectsSubstitutionAndRegistration; testConstructionProtectionTransitionAndFinalizerCallsitesHaveOneOwner; testImmutableSqlColumnsAndForbiddenClearFieldsAreClosed | Composer refactor retains one existing outbox store/protection chain. New signing code may only use actual C3, never native outbox mutation/status getters or clear context/seed SQL columns. Do not change C3 quarantine semantics to fake common graph invalidation. |
| `tests/Architecture/AuditRelayArchitectureTest.php`: testDependenciesReferencesAndActivationMapsAreExact; testConstructionDirectCallsAndApplicationGraphAreExact; testAuthorityMapRejectsCandidateSelfBaselineAndSharedWholePathBypass | Keep existing relay construction/calls within private Composer.build and optional legacy runtime unchanged. F composition does not call relay as an ordinary-D/Required substitute. Exact path/type/receiver inventory and old mutation controls remain. |
| `tests/Architecture/AuditAnchorStateArchitectureTest.php`; `AuditPublicApiTest.php` | No F3 signer/storage/provider/public activation edge or PublicApi marker is added. New F5 types are internal; no F4 fourteen operations claimed from a shared signature helper. Keep current full guard inventories untouched unless a concrete imported reference requires a separately exact grant. |

Add two cohesive architecture owners rather than placing all new responsibility inside old giant guards: `tests/Architecture/AuditCheckpointProductionArchitectureTest.php` for fixed ordinary/control construction, Operation/policy/envelope/handler graph, every fact factory/accessor, state poisoning, lazy scheduler and F2 edges; `tests/Architecture/AuditProvenanceArchitectureTest.php` for exact nine tables/29 statements, native projections, Row/transaction/signature owner graph, RO/RW separation and no SQL-under-floor. Their literal expected manifests must enumerate all issued files/types/properties/constructors/public+private methods and SensitiveParameter rules from this map and the final imported authority map. They must not derive the expected manifest from the candidate source or compare a parser to itself. Positive aliases/group imports/inert comments and negative extra getter/factory/callable/class-string/Reflection/serialization/clone/callback/file/socket/native-signature mutations are required.

Meaningful behavior/native suites and actual existing anchors:

| Proposed test ownership and existing anchors | Required cases and practical limits |
| --- | --- |
| Existing `tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php`: exact signature, real one-graph/provider count, configuration failures, source caller bound; add focused `ApplicationAuditCheckpointCompositionTest.php` | Existing compose creates no F2; explicit seam constructs one same-Connection F2/Reader/Admission from same protected components. Duplicate/reentrant factory and wrong operations Connection fail. Actual provider/catalog failures don't become null fallback. No mock F2 outcome demonstrates production origin. |
| Existing `tests/Internal/Application/ApplicationConsoleKernelTest.php`: testListsAndHelpsAllFrameworkCommandsWithoutRuntimeConfiguration; testOperationalStatusCommandsAreConcreteAndDoNotResolveRuntimeOnReadOnlyPaths; testFreshKernelRetentionDefinitionsExposeAndBindTheIdempotencyOption; new `ApplicationRetentionRuntimeTest.php` | Signing installed + absent/unavailable build/providers still allows actual list/help and unrelated plan/purge construction. First scheduler constructs once, second command shares it, failed/reentrant construction is sticky. Same cached poisoned graph refuses both entries before provider/selection. New whole graph is necessary recovery. |
| Existing `tests/Internal/Scheduler/MaintenanceSchedulerTest.php`: testRunsRegisteredTasksOnce; testRethrowsTaskFailureAndRecordsFailedSchedulerMetrics; new signing composition PostgreSQL suite | Real task names/order and fail-fast preserved. Explicit first-stage failure emits no “completed” success. Separate actual ordinary-present/absent installations exercise ordinary and control; no test-only production origin or task bypass. |
| New `tests/Internal/Audit/Signing/OrdinaryAuditSignCompositionPostgreSqlTest.php` | Real built container/providers + Definition compiler/evaluator/resolver/Policy + same envelope actual HandlerInvoker. Wrong service/alias/removed policy ID/admission tuple fails before selection. Actual identity pin mismatch creates no reservation; allowed identity but checkpoint kind denied creates actual preparation then actual denial and two Durable records, zero signing/persist. Real allowed invocation creates signed reservation/bound/candidate/native complete/persisted outcome and held unavailable terminal. Fresh resume reauthorizes; no Journal/InlineDispatcher claim. |
| New `tests/Internal/Audit/Provenance/PostgreSqlAuditCheckpointProductionTest.php`; existing F2 `PostgreSqlAuditCheckpointStorePostgreSqlTest.php` | Actual F2 Clock/UUID once for new preparation and zero on replay;7/3 columns remain. Crashes/failures at reservation, F2 prepare, bound, candidate, F2 persist, outcome. Native candidate persistence before complete; exact recovery with original key/time/bytes after rotation/current restrictions. Denied resumed work cannot acquire seed/persist. Wrong tenant/null pair, unattributed existing F2, ordinary/control slot collision, malformed signature/projection, missing/reordered step, sealed mismatch and wrong predecessor fail. |
| New `tests/Internal/Audit/Provenance/PostgreSqlAuditProvenanceSchemaTest.php` (pure emitted statements) and `PostgreSqlAuditProvenanceSchemaPostgreSqlTest.php` (native); existing migration factory/schema tests | Byte-comparison of AppendixA29, configured/default schema, actual install/FK/unique/check violations, every immutable UPDATE/DELETE/TRUNCATE including no-op, dispatch-only mutations. Inspect actual index/catalog definitions. Real role permissions/owner-bypass limit and timeout/deadlock cases. No query-plan/index-benefit assertion without EXPLAIN/native data proof. |
| New `tests/Internal/Audit/Provenance/PostgreSqlAuditCheckpointSelectorTest.php` plus native selector cases | Full native tenant pair, native bigint/UUID order, page boundary32, pending before new, earliest blocker, control/unattributed/corrupt rows never filtered away, complete/sealed still pending, no-work/receipt-ready never fabricated, dispatch deletion/corruption does not lose immutable work. READ ONLY enforces no lock/mutation; full proof in separate write root; final endpoint change fails before new signature/mutation. Long histories stream without lifetime caps. |
| New native fault/composition cases, Root-owned resources | Actual COMMIT-response loss separately after requested and unavailable append; original C3 failure precedence, zero terminal follow-up, zero release, both cached entries refuse before lifecycle/providers/selection. Normal committed requested+unavailable pending remains distinguishable and does not poison by itself. Actual F5/F2 uncertainty prevents C3 follow-up. Fresh actual graph recovers visible winner; close/reconnect does not. A fake coordinator return/boolean reset is not native proof. |
| New control publication/production native cases joined to Root history/custody suite | Actual zero-sentinel bootstrap policy/transition/candidate/publication, root rotation, actual B2 close binding/segment advance and first real control checkpoint. Staged versus published path, exact pending candidate recovery, no-current-source fail closed,≥2 publishers/writers, backlog32/33, current compromised historical issuer path and long archive history. D absent means pending transition/outcome with no ack and safe task failure. No fixture grants stand in for real production origin. |

Native concurrency must discriminate a winner committed before and during RR fence wait, invisible uniqueness conflict, exact same winner recovery and authentic opposite production class. Fault instrumentation must demonstrate the actual call/effect and label unhit/masked native edges Unobservable, not PASS. Root owns commands/resources/leases; this file neither executes nor selects an alternate native runner. The assembled packet must retain required format/comment-ID checks, strict static/dependency checks, changed relevant suites, one final broad/full suite at512M, consumer/package inventory and same-SHA export/remote release conditions. No repeated unchanged successful check is required solely for an extra reviewer.

## 11. Finite Root joins, release impact and evidence status

Root decisions left by this consolidation are bounded: complete the remaining authority review/adoption for native RR READ ONLY capture and the pure-codec replay exception; reserve or rename the concrete29-statement migration; bind the three imported crypto/custody seams and independent installer-selected bootstrap/rotation input; freeze the whole authority+verification packet and independently review its combined caller lists. The selected131 composer/lazy lifetime and common130/131 graph-failure contract are now present in Root's concurrent authority updates. The control proof/writer5-argument constructor and one-use MutationAdmission consumer have been reconciled explicitly above. These are concrete issuance joins, not a proposal to resume general design research. No small fake producer, authority flag, blind unavailable service or final acknowledgement may fill them.

Release impact remains the new proposed BlackOps1.3 audit-signing installation/runtime behavior, not Stable1.2 capability. Actual affected reader routes when implementation is issued: configuration (`docs/guide/configuration.md`, `/reference/configuration/`), console command (`/execution/console-command/`), authorization (`/auth/authorization/`), retention (`/database/retention/`), scheduled Operation (`/operations/scheduled-operation/`), lifecycle (`/concepts/lifecycle/`), execution context (`/execution/context/`). Internal maintenance-scheduler, audit-checkpoint-persistence, audit-anchor-intent-state and Operation metadata/envelope docs need the exact ownership/partial-status explanation. Current authority is experimental-stable1.2.1: Framework direct `1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3`, source `4efee09f13bebedc1639333f79550a36a4e8ca91`; Skeleton direct `7a47e344ecba6193628119f0e95cb23dc5e0be83`, source `87a025380df8abcd0c514abf50e139dbbc1953c7`; roadmap1.3.0 unreleased. No Capability ID/Stable readiness/source/Search/LLM/deploy proof is invented.

Investigation used read-only source/proposal inspection and standard-library text/hash/JSON bookkeeping only. No Product/governance/STATE/frozen-evidence edit, Git, PHP/Node/native runtime, test, DB/SQL, network or lease operation ran. Only this map and its selected-input JSON are owned outputs. Hashes before/after and statement counts in the companion are byte bookkeeping, not SQL parsing or runtime qualification. AppendixA's first28 statements are copied from exactDDL6eb42122; AppendixB preserves reviewed literal selector SQL. Source map counts are proposed budgets, not measured static-analysis PASS.

The final comparison retains116 selected inputs:113 unchanged and3 concurrent Root-owned governance changes. The F5 Task changed from41263ef8 to150ef69e (including the native-calibration and pure-codec investigation scopes);130 changed fromef85bcb7 toe514b180;131 changed from7a81c24c tobd3e80ff. The relevant construction/monotone-graph text and current task tail were read at freeze; their full before/after hashes remain in the manifest. No Product or retained proposal drift was observed. The independent installation-selection origin stays explicitly unresolved in this frozen map; Root's subsequent installation-entry packet must name its real constructors and callers.

Remaining work: assemble/review/freeze the full prerequisite packet, implement by the designated Luna worker only after issuance, run assigned meaningful/native/strict/full/package qualification, then integrate actual F4/D/Required continuations, full F5 acceptance and remaining1.3 release gates.

Next Action: Root reconciles §8's finite imported seams with E13/history-capture-issuance-map-r1.md, freezes exact combined file/caller/native fault inventories and submits that complete packet for independent review before implementation.

## Appendix A. Consolidated exact29-statement DDL

The following block is the unchanged default-schema emitted28-statement block from exactDDL6eb42122 followed by the reviewed B2 selector index29. Product uses the configured validated schema owner, not textual substitution or search_path. The companion JSON records the combined block and per-statement SHA-256 values.

```sql
CREATE TABLE "blackops"."audit_f_policy_envelopes" (
    "installation_ref" text COLLATE "C" NOT NULL,
    "policy_version" bigint NOT NULL,
    "policy_digest" text COLLATE "C" NOT NULL,
    "previous_policy_version" bigint NULL,
    "previous_policy_digest" text COLLATE "C" NULL,
    "authority_ref" text COLLATE "C" NOT NULL,
    "policy_id" text COLLATE "C" NOT NULL,
    "envelope" bytea NOT NULL,
    CONSTRAINT "af_policy_installation_ref_check" CHECK (octet_length("installation_ref") BETWEEN 1 AND 256),
    CONSTRAINT "af_policy_policy_version_check" CHECK ("policy_version" BETWEEN 1 AND 9007199254740991),
    CONSTRAINT "af_policy_policy_digest_check" CHECK (octet_length("policy_digest") = 64 AND "policy_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_policy_previous_policy_version_check" CHECK ("previous_policy_version" IS NULL OR ("previous_policy_version" BETWEEN 1 AND 9007199254740991)),
    CONSTRAINT "af_policy_previous_policy_digest_check" CHECK ("previous_policy_digest" IS NULL OR (octet_length("previous_policy_digest") = 64 AND "previous_policy_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_policy_authority_ref_check" CHECK (octet_length("authority_ref") BETWEEN 1 AND 256),
    CONSTRAINT "af_policy_policy_id_check" CHECK (octet_length("policy_id") BETWEEN 1 AND 256),
    CONSTRAINT "af_policy_envelope_check" CHECK (octet_length("envelope") BETWEEN 1 AND 65536),
    CONSTRAINT "af_policy_pk" PRIMARY KEY ("policy_digest"),
    CONSTRAINT "af_policy_version_uq" UNIQUE ("installation_ref", "policy_version"),
    CONSTRAINT "af_policy_installation_digest_uq" UNIQUE ("installation_ref", "policy_digest"),
    CONSTRAINT "af_policy_version_digest_uq" UNIQUE ("installation_ref", "policy_version", "policy_digest"),
    CONSTRAINT "af_policy_previous_fk" FOREIGN KEY ("installation_ref", "previous_policy_version", "previous_policy_digest")
        REFERENCES "blackops"."audit_f_policy_envelopes" ("installation_ref", "policy_version", "policy_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_policy_previous_check" CHECK (("policy_version" = 1 AND "previous_policy_version" IS NULL AND "previous_policy_digest" IS NULL) OR ("policy_version" > 1 AND "previous_policy_version" IS NOT NULL AND "previous_policy_digest" IS NOT NULL AND "previous_policy_version" = "policy_version" - 1))
);

CREATE TABLE "blackops"."audit_f_segment_bindings" (
    "installation_ref" text COLLATE "C" NOT NULL,
    "binding_digest" text COLLATE "C" NOT NULL,
    "policy_digest" text COLLATE "C" NOT NULL,
    "partition_id" varchar(71) COLLATE "C" NOT NULL,
    "segment_id" uuid NOT NULL,
    "first_sequence" bigint NOT NULL,
    "last_sequence" bigint NOT NULL,
    "successor_segment_id" uuid NOT NULL,
    "previous_binding_digest" text COLLATE "C" NULL,
    "envelope" bytea NOT NULL,
    CONSTRAINT "af_segment_installation_ref_check" CHECK (octet_length("installation_ref") BETWEEN 1 AND 256),
    CONSTRAINT "af_segment_binding_digest_check" CHECK (octet_length("binding_digest") = 64 AND "binding_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_segment_policy_digest_check" CHECK (octet_length("policy_digest") = 64 AND "policy_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_segment_partition_id_check" CHECK ("partition_id" = 'system:global' OR (octet_length("partition_id") = 71 AND "partition_id" ~ '^tenant:[0-9a-f]{64}$')),
    CONSTRAINT "af_segment_segment_id_check" CHECK ("segment_id"::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
    CONSTRAINT "af_segment_first_sequence_check" CHECK ("first_sequence" BETWEEN 1 AND 9007199254740991),
    CONSTRAINT "af_segment_last_sequence_check" CHECK ("last_sequence" BETWEEN 1 AND 9007199254740991),
    CONSTRAINT "af_segment_successor_segment_id_check" CHECK ("successor_segment_id"::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
    CONSTRAINT "af_segment_previous_binding_digest_check" CHECK ("previous_binding_digest" IS NULL OR (octet_length("previous_binding_digest") = 64 AND "previous_binding_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_segment_envelope_check" CHECK (octet_length("envelope") BETWEEN 1 AND 65536),
    CONSTRAINT "af_segment_pk" PRIMARY KEY ("binding_digest"),
    CONSTRAINT "af_segment_installation_digest_uq" UNIQUE ("installation_ref", "binding_digest"),
    CONSTRAINT "af_segment_segment_uq" UNIQUE ("partition_id", "segment_id"),
    CONSTRAINT "af_segment_first_uq" UNIQUE ("partition_id", "first_sequence"),
    CONSTRAINT "af_segment_successor_uq" UNIQUE ("partition_id", "successor_segment_id"),
    CONSTRAINT "af_segment_policy_fk" FOREIGN KEY ("installation_ref", "policy_digest")
        REFERENCES "blackops"."audit_f_policy_envelopes" ("installation_ref", "policy_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_segment_previous_fk" FOREIGN KEY ("installation_ref", "previous_binding_digest")
        REFERENCES "blackops"."audit_f_segment_bindings" ("installation_ref", "binding_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_segment_segment_fk" FOREIGN KEY ("partition_id", "segment_id")
        REFERENCES "blackops"."audit_segments" ("partition_id", "segment_id") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_segment_successor_fk" FOREIGN KEY ("partition_id", "successor_segment_id")
        REFERENCES "blackops"."audit_segments" ("partition_id", "segment_id") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_segment_partition_check" CHECK ("partition_id" = 'system:global'),
    CONSTRAINT "af_segment_range_check" CHECK ("last_sequence" >= "first_sequence"),
    CONSTRAINT "af_segment_successor_check" CHECK ("successor_segment_id" <> "segment_id")
);

CREATE TABLE "blackops"."audit_f_work_reservations" (
    "work_id" uuid NOT NULL,
    "installation_ref" text COLLATE "C" NOT NULL,
    "reservation_digest" text COLLATE "C" NOT NULL,
    "policy_digest" text COLLATE "C" NOT NULL,
    "artifact_kind" text COLLATE "C" NOT NULL,
    "partition_id" varchar(71) COLLATE "C" NOT NULL,
    "segment_id" uuid NOT NULL,
    "production_class" text COLLATE "C" NOT NULL,
    "segment_binding_digest" text COLLATE "C" NULL,
    "checkpoint_reservation_digest" text COLLATE "C" NULL,
    "intent_id" text COLLATE "C" NULL,
    "receipt_checkpoint_id" uuid NULL,
    "anchor_reservation_digest" text COLLATE "C" NULL,
    "anchor_receipt_id" text COLLATE "C" NULL,
    "envelope" bytea NOT NULL,
    CONSTRAINT "af_work_work_id_check" CHECK ("work_id"::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
    CONSTRAINT "af_work_installation_ref_check" CHECK (octet_length("installation_ref") BETWEEN 1 AND 256),
    CONSTRAINT "af_work_reservation_digest_check" CHECK (octet_length("reservation_digest") = 64 AND "reservation_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_work_policy_digest_check" CHECK (octet_length("policy_digest") = 64 AND "policy_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_work_partition_id_check" CHECK ("partition_id" = 'system:global' OR (octet_length("partition_id") = 71 AND "partition_id" ~ '^tenant:[0-9a-f]{64}$')),
    CONSTRAINT "af_work_segment_id_check" CHECK ("segment_id"::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
    CONSTRAINT "af_work_segment_binding_digest_check" CHECK ("segment_binding_digest" IS NULL OR (octet_length("segment_binding_digest") = 64 AND "segment_binding_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_work_checkpoint_reservation_digest_check" CHECK ("checkpoint_reservation_digest" IS NULL OR (octet_length("checkpoint_reservation_digest") = 64 AND "checkpoint_reservation_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_work_intent_id_check" CHECK ("intent_id" IS NULL OR (octet_length("intent_id") BETWEEN 1 AND 256)),
    CONSTRAINT "af_work_receipt_checkpoint_id_check" CHECK ("receipt_checkpoint_id" IS NULL OR ("receipt_checkpoint_id"::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')),
    CONSTRAINT "af_work_anchor_reservation_digest_check" CHECK ("anchor_reservation_digest" IS NULL OR (octet_length("anchor_reservation_digest") = 64 AND "anchor_reservation_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_work_anchor_receipt_id_check" CHECK ("anchor_receipt_id" IS NULL OR (octet_length("anchor_receipt_id") BETWEEN 1 AND 256)),
    CONSTRAINT "af_work_envelope_check" CHECK (octet_length("envelope") BETWEEN 1 AND 65536),
    CONSTRAINT "af_work_pk" PRIMARY KEY ("work_id"),
    CONSTRAINT "af_work_digest_uq" UNIQUE ("reservation_digest"),
    CONSTRAINT "af_work_installation_digest_uq" UNIQUE ("installation_ref", "reservation_digest"),
    CONSTRAINT "af_work_work_digest_uq" UNIQUE ("work_id", "reservation_digest"),
    CONSTRAINT "af_work_policy_fk" FOREIGN KEY ("installation_ref", "policy_digest")
        REFERENCES "blackops"."audit_f_policy_envelopes" ("installation_ref", "policy_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_work_segment_fk" FOREIGN KEY ("partition_id", "segment_id")
        REFERENCES "blackops"."audit_segments" ("partition_id", "segment_id") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_work_binding_fk" FOREIGN KEY ("installation_ref", "segment_binding_digest")
        REFERENCES "blackops"."audit_f_segment_bindings" ("installation_ref", "binding_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_work_checkpoint_reservation_fk" FOREIGN KEY ("installation_ref", "checkpoint_reservation_digest")
        REFERENCES "blackops"."audit_f_work_reservations" ("installation_ref", "reservation_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_work_receipt_checkpoint_fk" FOREIGN KEY ("receipt_checkpoint_id")
        REFERENCES "blackops"."audit_checkpoints" ("checkpoint_id") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_work_artifact_kind_check" CHECK ("artifact_kind" IN ('audit_checkpoint', 'audit_anchor_receipt')),
    CONSTRAINT "af_work_production_class_check" CHECK ("production_class" IN ('ordinary', 'control')),
    CONSTRAINT "af_work_class_check" CHECK (("production_class" = 'ordinary' AND "segment_binding_digest" IS NULL) OR ("production_class" = 'control' AND "segment_binding_digest" IS NOT NULL)),
    CONSTRAINT "af_work_artifact_check" CHECK ((("artifact_kind" = 'audit_checkpoint' AND "checkpoint_reservation_digest" IS NULL AND "intent_id" IS NULL AND "receipt_checkpoint_id" IS NULL AND "anchor_reservation_digest" IS NULL AND "anchor_receipt_id" IS NULL) OR ("artifact_kind" = 'audit_anchor_receipt' AND "checkpoint_reservation_digest" IS NOT NULL AND "intent_id" IS NOT NULL AND "receipt_checkpoint_id" IS NOT NULL AND "anchor_reservation_digest" IS NOT NULL AND "anchor_receipt_id" IS NOT NULL)) AND ("checkpoint_reservation_digest" IS NULL OR "checkpoint_reservation_digest" <> "reservation_digest"))
);

CREATE TABLE "blackops"."audit_f_work_steps" (
    "work_id" uuid NOT NULL,
    "ordinal" bigint NOT NULL,
    "reservation_digest" text COLLATE "C" NOT NULL,
    "previous_step_ordinal" bigint NULL,
    "previous_step_digest" text COLLATE "C" NOT NULL,
    "authorizing_policy_digest" text COLLATE "C" NOT NULL,
    "phase" text COLLATE "C" NOT NULL,
    "step_digest" text COLLATE "C" NOT NULL,
    "envelope" bytea NOT NULL,
    "complete_bytes" bytea NULL,
    CONSTRAINT "af_step_work_id_check" CHECK ("work_id"::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
    CONSTRAINT "af_step_ordinal_check" CHECK ("ordinal" BETWEEN 1 AND 9007199254740991),
    CONSTRAINT "af_step_reservation_digest_check" CHECK (octet_length("reservation_digest") = 64 AND "reservation_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_step_previous_step_ordinal_check" CHECK ("previous_step_ordinal" IS NULL OR ("previous_step_ordinal" BETWEEN 1 AND 9007199254740991)),
    CONSTRAINT "af_step_previous_step_digest_check" CHECK (octet_length("previous_step_digest") = 64 AND "previous_step_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_step_authorizing_policy_digest_check" CHECK (octet_length("authorizing_policy_digest") = 64 AND "authorizing_policy_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_step_step_digest_check" CHECK (octet_length("step_digest") = 64 AND "step_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_step_envelope_check" CHECK (octet_length("envelope") BETWEEN 1 AND 65536),
    CONSTRAINT "af_step_complete_bytes_check" CHECK ("complete_bytes" IS NULL OR (octet_length("complete_bytes") BETWEEN 1 AND 1048575)),
    CONSTRAINT "af_step_pk" PRIMARY KEY ("work_id", "ordinal"),
    CONSTRAINT "af_step_digest_uq" UNIQUE ("step_digest"),
    CONSTRAINT "af_step_work_digest_uq" UNIQUE ("work_id", "step_digest"),
    CONSTRAINT "af_step_link_uq" UNIQUE ("work_id", "ordinal", "step_digest"),
    CONSTRAINT "af_step_phase_uq" UNIQUE ("work_id", "phase"),
    CONSTRAINT "af_step_next_uq" UNIQUE ("work_id", "previous_step_digest"),
    CONSTRAINT "af_step_reservation_fk" FOREIGN KEY ("work_id", "reservation_digest")
        REFERENCES "blackops"."audit_f_work_reservations" ("work_id", "reservation_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_step_previous_fk" FOREIGN KEY ("work_id", "previous_step_ordinal", "previous_step_digest")
        REFERENCES "blackops"."audit_f_work_steps" ("work_id", "ordinal", "step_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_step_policy_fk" FOREIGN KEY ("authorizing_policy_digest")
        REFERENCES "blackops"."audit_f_policy_envelopes" ("policy_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_step_phase_check" CHECK (("ordinal" = 1 AND "phase" = 'preparation_bound') OR ("ordinal" = 2 AND "phase" = 'complete_candidate') OR ("ordinal" = 3 AND "phase" = 'artifact_persisted') OR ("ordinal" = 4 AND "phase" = 'ingress_acknowledged')),
    CONSTRAINT "af_step_previous_check" CHECK (("ordinal" = 1 AND "previous_step_ordinal" IS NULL AND "previous_step_digest" = "reservation_digest") OR ("ordinal" > 1 AND "previous_step_ordinal" IS NOT NULL AND "previous_step_ordinal" = "ordinal" - 1)),
    CONSTRAINT "af_step_candidate_check" CHECK (("phase" = 'complete_candidate' AND "complete_bytes" IS NOT NULL) OR ("phase" <> 'complete_candidate' AND "complete_bytes" IS NULL))
);

CREATE TABLE "blackops"."audit_f_policy_transitions" (
    "installation_ref" text COLLATE "C" NOT NULL,
    "transition_id" text COLLATE "C" NOT NULL,
    "transition_digest" text COLLATE "C" NOT NULL,
    "authorizing_policy_digest" text COLLATE "C" NOT NULL,
    "previous_transition_digest" text COLLATE "C" NULL,
    "previous_policy_state_digest" text COLLATE "C" NULL,
    "new_policy_state_digest" text COLLATE "C" NOT NULL,
    "cause" text COLLATE "C" NOT NULL,
    "envelope" bytea NOT NULL,
    CONSTRAINT "af_transition_installation_ref_check" CHECK (octet_length("installation_ref") BETWEEN 1 AND 256),
    CONSTRAINT "af_transition_transition_id_check" CHECK (octet_length("transition_id") = 64 AND "transition_id" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_transition_transition_digest_check" CHECK (octet_length("transition_digest") = 64 AND "transition_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_transition_authorizing_policy_digest_check" CHECK (octet_length("authorizing_policy_digest") = 64 AND "authorizing_policy_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_transition_previous_transition_digest_check" CHECK ("previous_transition_digest" IS NULL OR (octet_length("previous_transition_digest") = 64 AND "previous_transition_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_transition_previous_policy_state_digest_check" CHECK ("previous_policy_state_digest" IS NULL OR (octet_length("previous_policy_state_digest") = 64 AND "previous_policy_state_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_transition_new_policy_state_digest_check" CHECK (octet_length("new_policy_state_digest") = 64 AND "new_policy_state_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_transition_envelope_check" CHECK (octet_length("envelope") BETWEEN 1 AND 65536),
    CONSTRAINT "af_transition_pk" PRIMARY KEY ("transition_id"),
    CONSTRAINT "af_transition_digest_uq" UNIQUE ("transition_digest"),
    CONSTRAINT "af_transition_installation_digest_uq" UNIQUE ("installation_ref", "transition_digest"),
    CONSTRAINT "af_transition_identity_digest_uq" UNIQUE ("transition_id", "transition_digest"),
    CONSTRAINT "af_transition_policy_fk" FOREIGN KEY ("installation_ref", "authorizing_policy_digest")
        REFERENCES "blackops"."audit_f_policy_envelopes" ("installation_ref", "policy_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_transition_previous_fk" FOREIGN KEY ("installation_ref", "previous_transition_digest")
        REFERENCES "blackops"."audit_f_policy_transitions" ("installation_ref", "transition_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_transition_cause_check" CHECK ("cause" IN ('root_revision', 'segment_advance')),
    CONSTRAINT "af_transition_bootstrap_check" CHECK (("previous_transition_digest" IS NULL AND "previous_policy_state_digest" IS NULL AND "cause" = 'root_revision') OR ("previous_transition_digest" IS NOT NULL AND "previous_policy_state_digest" IS NOT NULL))
);

CREATE TABLE "blackops"."audit_f_transition_acks" (
    "installation_ref" text COLLATE "C" NOT NULL,
    "acknowledgement_digest" text COLLATE "C" NOT NULL,
    "authorizing_policy_digest" text COLLATE "C" NOT NULL,
    "transition_id" text COLLATE "C" NOT NULL,
    "transition_digest" text COLLATE "C" NOT NULL,
    "ingress_key" text COLLATE "C" NOT NULL,
    "envelope" bytea NOT NULL,
    CONSTRAINT "af_ack_installation_ref_check" CHECK (octet_length("installation_ref") BETWEEN 1 AND 256),
    CONSTRAINT "af_ack_acknowledgement_digest_check" CHECK (octet_length("acknowledgement_digest") = 64 AND "acknowledgement_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_ack_authorizing_policy_digest_check" CHECK (octet_length("authorizing_policy_digest") = 64 AND "authorizing_policy_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_ack_transition_id_check" CHECK (octet_length("transition_id") = 64 AND "transition_id" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_ack_transition_digest_check" CHECK (octet_length("transition_digest") = 64 AND "transition_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_ack_ingress_key_check" CHECK (octet_length("ingress_key") BETWEEN 1 AND 256),
    CONSTRAINT "af_ack_envelope_check" CHECK (octet_length("envelope") BETWEEN 1 AND 65536),
    CONSTRAINT "af_ack_pk" PRIMARY KEY ("acknowledgement_digest"),
    CONSTRAINT "af_ack_installation_digest_uq" UNIQUE ("installation_ref", "acknowledgement_digest"),
    CONSTRAINT "af_ack_transition_uq" UNIQUE ("transition_digest"),
    CONSTRAINT "af_ack_ingress_uq" UNIQUE ("ingress_key"),
    CONSTRAINT "af_ack_policy_fk" FOREIGN KEY ("installation_ref", "authorizing_policy_digest")
        REFERENCES "blackops"."audit_f_policy_envelopes" ("installation_ref", "policy_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_ack_transition_fk" FOREIGN KEY ("transition_id", "transition_digest")
        REFERENCES "blackops"."audit_f_policy_transitions" ("transition_id", "transition_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_ack_ingress_check" CHECK ("ingress_key" = 'control-policy:' || "transition_id")
);

CREATE TABLE "blackops"."audit_f_publication_candidates" (
    "installation_ref" text COLLATE "C" NOT NULL,
    "revision" bigint NOT NULL,
    "head_digest" text COLLATE "C" NOT NULL,
    "previous_revision" bigint NULL,
    "previous_head_digest" text COLLATE "C" NULL,
    "policy_digest" text COLLATE "C" NOT NULL,
    "policy_state_digest" text COLLATE "C" NOT NULL,
    "closed_segment_tail_digest" text COLLATE "C" NULL,
    "transition_tail_digest" text COLLATE "C" NOT NULL,
    "acknowledged_transition_digest" text COLLATE "C" NULL,
    "transition_ack_digest" text COLLATE "C" NULL,
    "document_sha256" text COLLATE "C" NOT NULL,
    "document_bytes" bytea NOT NULL,
    CONSTRAINT "af_publication_installation_ref_check" CHECK (octet_length("installation_ref") BETWEEN 1 AND 256),
    CONSTRAINT "af_publication_revision_check" CHECK ("revision" BETWEEN 1 AND 9007199254740991),
    CONSTRAINT "af_publication_head_digest_check" CHECK (octet_length("head_digest") = 64 AND "head_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_publication_previous_revision_check" CHECK ("previous_revision" IS NULL OR ("previous_revision" BETWEEN 1 AND 9007199254740991)),
    CONSTRAINT "af_publication_previous_head_digest_check" CHECK ("previous_head_digest" IS NULL OR (octet_length("previous_head_digest") = 64 AND "previous_head_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_publication_policy_digest_check" CHECK (octet_length("policy_digest") = 64 AND "policy_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_publication_policy_state_digest_check" CHECK (octet_length("policy_state_digest") = 64 AND "policy_state_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_publication_closed_segment_tail_digest_check" CHECK ("closed_segment_tail_digest" IS NULL OR (octet_length("closed_segment_tail_digest") = 64 AND "closed_segment_tail_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_publication_transition_tail_digest_check" CHECK (octet_length("transition_tail_digest") = 64 AND "transition_tail_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_publication_acknowledged_transition_digest_check" CHECK ("acknowledged_transition_digest" IS NULL OR (octet_length("acknowledged_transition_digest") = 64 AND "acknowledged_transition_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_publication_transition_ack_digest_check" CHECK ("transition_ack_digest" IS NULL OR (octet_length("transition_ack_digest") = 64 AND "transition_ack_digest" ~ '^[0-9a-f]{64}$')),
    CONSTRAINT "af_publication_document_sha256_check" CHECK (octet_length("document_sha256") = 64 AND "document_sha256" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_publication_document_bytes_check" CHECK (octet_length("document_bytes") BETWEEN 1 AND 1048576),
    CONSTRAINT "af_publication_pk" PRIMARY KEY ("installation_ref", "revision"),
    CONSTRAINT "af_publication_head_uq" UNIQUE ("head_digest"),
    CONSTRAINT "af_publication_installation_head_uq" UNIQUE ("installation_ref", "head_digest"),
    CONSTRAINT "af_publication_link_uq" UNIQUE ("installation_ref", "revision", "head_digest"),
    CONSTRAINT "af_publication_document_uq" UNIQUE ("document_sha256"),
    CONSTRAINT "af_publication_previous_fk" FOREIGN KEY ("installation_ref", "previous_revision", "previous_head_digest")
        REFERENCES "blackops"."audit_f_publication_candidates" ("installation_ref", "revision", "head_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_publication_policy_fk" FOREIGN KEY ("installation_ref", "policy_digest")
        REFERENCES "blackops"."audit_f_policy_envelopes" ("installation_ref", "policy_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_publication_segment_tail_fk" FOREIGN KEY ("installation_ref", "closed_segment_tail_digest")
        REFERENCES "blackops"."audit_f_segment_bindings" ("installation_ref", "binding_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_publication_transition_tail_fk" FOREIGN KEY ("installation_ref", "transition_tail_digest")
        REFERENCES "blackops"."audit_f_policy_transitions" ("installation_ref", "transition_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_publication_acknowledged_transition_fk" FOREIGN KEY ("installation_ref", "acknowledged_transition_digest")
        REFERENCES "blackops"."audit_f_policy_transitions" ("installation_ref", "transition_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_publication_ack_fk" FOREIGN KEY ("installation_ref", "transition_ack_digest")
        REFERENCES "blackops"."audit_f_transition_acks" ("installation_ref", "acknowledgement_digest") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_publication_previous_check" CHECK (("revision" = 1 AND "previous_revision" IS NULL AND "previous_head_digest" IS NULL) OR ("revision" > 1 AND "previous_revision" IS NOT NULL AND "previous_head_digest" IS NOT NULL AND "previous_revision" = "revision" - 1)),
    CONSTRAINT "af_publication_ack_pair_check" CHECK (("acknowledged_transition_digest" IS NULL AND "transition_ack_digest" IS NULL) OR ("acknowledged_transition_digest" IS NOT NULL AND "transition_ack_digest" IS NOT NULL))
);

CREATE TABLE "blackops"."audit_f_work_dispatch" (
    "work_id" uuid NOT NULL,
    "source_digest" text COLLATE "C" NOT NULL,
    "pending_state" text COLLATE "C" NOT NULL,
    "next_attempt_at" timestamptz(6) NOT NULL,
    "retry_count" bigint NOT NULL,
    CONSTRAINT "af_work_dispatch_work_id_check" CHECK ("work_id"::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
    CONSTRAINT "af_work_dispatch_source_digest_check" CHECK (octet_length("source_digest") = 64 AND "source_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_work_dispatch_retry_count_check" CHECK ("retry_count" BETWEEN 0 AND 9007199254740991),
    CONSTRAINT "af_work_dispatch_pk" PRIMARY KEY ("work_id"),
    CONSTRAINT "af_work_dispatch_work_fk" FOREIGN KEY ("work_id")
        REFERENCES "blackops"."audit_f_work_reservations" ("work_id") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "af_work_dispatch_pending_state_check" CHECK ("pending_state" IN ('prepare', 'candidate', 'persist', 'outcome', 'ingress', 'complete'))
);

CREATE TABLE "blackops"."audit_f_policy_dispatch" (
    "transition_id" text COLLATE "C" NOT NULL,
    "source_digest" text COLLATE "C" NOT NULL,
    "next_attempt_at" timestamptz(6) NOT NULL,
    "retry_count" bigint NOT NULL,
    CONSTRAINT "af_policy_dispatch_transition_id_check" CHECK (octet_length("transition_id") = 64 AND "transition_id" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_policy_dispatch_source_digest_check" CHECK (octet_length("source_digest") = 64 AND "source_digest" ~ '^[0-9a-f]{64}$'),
    CONSTRAINT "af_policy_dispatch_retry_count_check" CHECK ("retry_count" BETWEEN 0 AND 9007199254740991),
    CONSTRAINT "af_policy_dispatch_pk" PRIMARY KEY ("transition_id"),
    CONSTRAINT "af_policy_dispatch_transition_fk" FOREIGN KEY ("transition_id")
        REFERENCES "blackops"."audit_f_policy_transitions" ("transition_id") MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE
);

CREATE UNIQUE INDEX "af_segment_next_uq" ON "blackops"."audit_f_segment_bindings" ("installation_ref", "previous_binding_digest") WHERE "previous_binding_digest" IS NOT NULL;

CREATE UNIQUE INDEX "af_segment_bootstrap_uq" ON "blackops"."audit_f_segment_bindings" ("installation_ref") WHERE "previous_binding_digest" IS NULL;

CREATE UNIQUE INDEX "af_work_checkpoint_slot_uq" ON "blackops"."audit_f_work_reservations" ("partition_id", "segment_id") WHERE "artifact_kind" = 'audit_checkpoint';

CREATE UNIQUE INDEX "af_work_receipt_slot_uq" ON "blackops"."audit_f_work_reservations" ("partition_id", "intent_id") WHERE "artifact_kind" = 'audit_anchor_receipt';

CREATE UNIQUE INDEX "af_work_receipt_id_uq" ON "blackops"."audit_f_work_reservations" ("anchor_receipt_id") WHERE "artifact_kind" = 'audit_anchor_receipt';

CREATE UNIQUE INDEX "af_transition_next_uq" ON "blackops"."audit_f_policy_transitions" ("installation_ref", "previous_transition_digest") WHERE "previous_transition_digest" IS NOT NULL;

CREATE UNIQUE INDEX "af_transition_bootstrap_uq" ON "blackops"."audit_f_policy_transitions" ("installation_ref") WHERE "previous_transition_digest" IS NULL;

CREATE UNIQUE INDEX "af_publication_next_uq" ON "blackops"."audit_f_publication_candidates" ("installation_ref", "previous_head_digest") WHERE "previous_head_digest" IS NOT NULL;

CREATE UNIQUE INDEX "af_publication_bootstrap_uq" ON "blackops"."audit_f_publication_candidates" ("installation_ref") WHERE "previous_head_digest" IS NULL;

CREATE INDEX "af_work_dispatch_due_idx" ON "blackops"."audit_f_work_dispatch" ("pending_state", "next_attempt_at", "work_id");

CREATE INDEX "af_policy_dispatch_due_idx" ON "blackops"."audit_f_policy_dispatch" ("next_attempt_at", "transition_id");

CREATE FUNCTION "blackops"."audit_f_reject_immutable_change"()
RETURNS trigger
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path = pg_catalog
AS $audit_f_immutable$
BEGIN
    RAISE EXCEPTION USING
        ERRCODE = '55000',
        MESSAGE = 'Audit provenance rows are immutable.';
END;
$audit_f_immutable$;

CREATE TRIGGER "af_policy_immutable"
BEFORE UPDATE OR DELETE OR TRUNCATE ON "blackops"."audit_f_policy_envelopes"
FOR EACH STATEMENT
EXECUTE FUNCTION "blackops"."audit_f_reject_immutable_change"();

CREATE TRIGGER "af_segment_immutable"
BEFORE UPDATE OR DELETE OR TRUNCATE ON "blackops"."audit_f_segment_bindings"
FOR EACH STATEMENT
EXECUTE FUNCTION "blackops"."audit_f_reject_immutable_change"();

CREATE TRIGGER "af_work_immutable"
BEFORE UPDATE OR DELETE OR TRUNCATE ON "blackops"."audit_f_work_reservations"
FOR EACH STATEMENT
EXECUTE FUNCTION "blackops"."audit_f_reject_immutable_change"();

CREATE TRIGGER "af_step_immutable"
BEFORE UPDATE OR DELETE OR TRUNCATE ON "blackops"."audit_f_work_steps"
FOR EACH STATEMENT
EXECUTE FUNCTION "blackops"."audit_f_reject_immutable_change"();

CREATE TRIGGER "af_transition_immutable"
BEFORE UPDATE OR DELETE OR TRUNCATE ON "blackops"."audit_f_policy_transitions"
FOR EACH STATEMENT
EXECUTE FUNCTION "blackops"."audit_f_reject_immutable_change"();

CREATE TRIGGER "af_ack_immutable"
BEFORE UPDATE OR DELETE OR TRUNCATE ON "blackops"."audit_f_transition_acks"
FOR EACH STATEMENT
EXECUTE FUNCTION "blackops"."audit_f_reject_immutable_change"();

CREATE TRIGGER "af_publication_immutable"
BEFORE UPDATE OR DELETE OR TRUNCATE ON "blackops"."audit_f_publication_candidates"
FOR EACH STATEMENT
EXECUTE FUNCTION "blackops"."audit_f_reject_immutable_change"();

CREATE INDEX "audit_segments_checkpoint_selection_idx" ON "blackops"."audit_segments" ("partition_id", "first_sequence", "segment_id") WHERE "state" IN ('closed', 'sealed');
```

## Appendix B. Retained literal selector statements

The following fixed default-schema statements are copied from selector/lazy f2391720. Their bindings, owner/native-state requirements and non-authoritative return semantics are specified in §§5–6. They are not executed SQL or evidence of a native plan.

### Q1 — exact tenant partition projection

```sql
SELECT p."partition_id", p."tenant_type", p."tenant_id",
       p."active_segment_id"::text AS "active_segment_id",
       p."last_sequence", p."head_record_hash",
       to_char(p."last_assigned_recorded_at" AT TIME ZONE 'UTC',
               'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS "last_assigned_recorded_at",
       p."state_version"
FROM "blackops"."audit_partition_state" AS p
WHERE p."partition_id" = :partition_id
  AND p."tenant_type" IS NOT DISTINCT FROM CAST(:tenant_type AS varchar(256))
  AND p."tenant_id" IS NOT DISTINCT FROM CAST(:tenant_id AS varchar(256));
```

### Q2a/Q2b — authoritative B2 frontier, first and later pages

```sql
SELECT s."partition_id", s."segment_id"::text AS "segment_id", s."state",
       s."first_sequence", s."final_sequence", s."head_record_hash",
       s."predecessor_record_hash",
       to_char(s."opened_at" AT TIME ZONE 'UTC',
               'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS "opened_at",
       CASE WHEN s."closed_at" IS NULL THEN NULL
            ELSE to_char(s."closed_at" AT TIME ZONE 'UTC',
                         'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') END AS "closed_at",
       s."successor_segment_id"::text AS "successor_segment_id",
       s."close_fencing_token", s."state_version"
FROM "blackops"."audit_segments" AS s
WHERE s."partition_id" = :partition_id
  AND s."state" IN ('closed', 'sealed')
ORDER BY s."first_sequence" ASC, s."segment_id" ASC
LIMIT 32;
```

```sql
SELECT s."partition_id", s."segment_id"::text AS "segment_id", s."state",
       s."first_sequence", s."final_sequence", s."head_record_hash",
       s."predecessor_record_hash",
       to_char(s."opened_at" AT TIME ZONE 'UTC',
               'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') AS "opened_at",
       CASE WHEN s."closed_at" IS NULL THEN NULL
            ELSE to_char(s."closed_at" AT TIME ZONE 'UTC',
                         'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') END AS "closed_at",
       s."successor_segment_id"::text AS "successor_segment_id",
       s."close_fencing_token", s."state_version"
FROM "blackops"."audit_segments" AS s
WHERE s."partition_id" = :partition_id
  AND s."state" IN ('closed', 'sealed')
  AND (s."first_sequence", s."segment_id") >
      (CAST(:after_sequence AS bigint), CAST(:after_segment_id AS uuid))
ORDER BY s."first_sequence" ASC, s."segment_id" ASC
LIMIT 32;
```

### Q3 — exact checkpoint reservation slot

```sql
SELECT r."work_id"::text AS "work_id", r."installation_ref",
       r."reservation_digest", r."policy_digest", r."artifact_kind",
       r."partition_id", r."segment_id"::text AS "segment_id",
       r."production_class", r."segment_binding_digest",
       r."checkpoint_reservation_digest", r."intent_id",
       r."receipt_checkpoint_id"::text AS "receipt_checkpoint_id",
       r."anchor_reservation_digest", r."anchor_receipt_id", r."envelope"
FROM "blackops"."audit_f_work_reservations" AS r
WHERE r."partition_id" = :partition_id
  AND r."segment_id" = CAST(:segment_id AS uuid)
  AND r."artifact_kind" = 'audit_checkpoint'
LIMIT 2;
```

### Q4 — actual fixed work chain and candidate

```sql
SELECT w."work_id"::text AS "work_id", w."ordinal",
       w."reservation_digest", w."previous_step_ordinal",
       w."previous_step_digest", w."authorizing_policy_digest", w."phase",
       w."step_digest", w."envelope", w."complete_bytes"
FROM "blackops"."audit_f_work_steps" AS w
WHERE w."work_id" = CAST(:work_id AS uuid)
ORDER BY w."ordinal" ASC
LIMIT 5;
```

### Q5/Q6 — Checkpoint-owned exact7/exact3 native facts

```sql
SELECT p."checkpoint_id"::text AS "checkpoint_id", p."partition_id",
       p."segment_id"::text AS "segment_id", p."closed_state_version",
       p."close_fencing_token", p."unsigned_checkpoint",
       p."signing_key_fingerprint"
FROM "blackops"."audit_checkpoint_preparations" AS p
WHERE p."partition_id" = :partition_id
  AND p."segment_id" = CAST(:segment_id AS uuid);
```

```sql
SELECT c."checkpoint_id"::text AS "checkpoint_id", c."checkpoint_hash",
       c."complete_bytes"
FROM "blackops"."audit_checkpoints" AS c
WHERE c."checkpoint_id" = CAST(:checkpoint_id AS uuid);
```

### Q7 — the actual selected-root B2 fence

```sql
SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0));
```

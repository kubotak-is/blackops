# P18-006B: Ephemeral Outcome Contract Report

## Summary

HTTP Clientへ成功値を一度だけ返しながら、Credential Input／OutputをCanonical Journal、Observer、Outcome Store、Status、Console、Deferred、Generated Artifactへ残さないPublic `EphemeralOutcome extends Outcome` Markerを実装した。

Ephemeral Operationも通常のInline Lifecycle、Operation ID、Actor相関、Attempt、Rejected／Failedの安全なSurfaceを維持する。実Outcomeは直接Inline Callerと同一HTTP Responseだけへ返し、Canonical Lifecycleには空Dataだけを渡す。Community Board、Auth Core、Auth Generator、外部Publication／Deployは変更していない。WorkerはCommitしていない。

## Changed Files

- Public Marker: `src/Core/EphemeralOutcome.php`
- Build／Manifest: `src/Internal/Registry/**`、`src/Http/Routing/**`
- Lifecycle／Persistence: `src/Internal/Execution/**`、`src/Internal/Journal/JournalRecordFactory.php`、`src/Transport/PostgreSql/**`
- HTTP／Status／Console／Deferred runtime guard: `src/Http/**`、`src/Internal/Application/**`、`src/Internal/Status/**`、`src/Internal/Console/**`、`src/Internal/Http/**`
- Frontend Contract／Generator: `src/Internal/Frontend/**`
- Unit／Integration／PostgreSQL／Permanent Frontend tests: 対応する`tests/**`
- Guide／Internal Documentation／Public API Inventory: `docs/guide/**`、`docs/internal/**`、`docs/website/tests/reader-experience.test.mjs`
- Delivery Plan／TODO／STATE: `develop/spec/75-phase-18-delivery-plan.md`、`develop/TODO.md`、`develop/STATE.md`、本Report

## Final Public API, Build Matrix, and Manifest Schema

追加Public APIはMethodを持たない`BlackOps\Core\EphemeralOutcome extends Outcome` Marker 1型だけである。既存Public `OperationMetadata`へEphemeral Propertyを追加せず、RuntimeはDeclared Outcomeから判定する。Reflection TestでMarkerにMethodがないこと、`#[PublicApi]`が一つであること、`OperationMetadata`にEphemeral Propertyがないことを固定した。Public API Inventoryは162型から163型へ更新した。

| Contract | Result |
| --- | --- |
| Route一つ＋明示`#[ExecuteWith(Inline::class)]` | Build成功 |
| Implicit Inline／Deferred／Routeなし／Console | Safe Build Failure |
| final readonly／public promoted Structured Shape | Build成功 |
| Credential Root Propertyの`#[Sensitive]`欠落 | Safe Build Failure |
| Nested Sensitive／Unsupported Shape／Mutable Outcome | Safe Build Failure |

Operation Manifest Schemaは2、HTTP Manifest Schemaは3、Frontend Contract Schemaは4、Frontend Generation Markerは7へ更新した。各DecoderはFlagのMissing／Invalid TypeとDeclared Outcomeの両方向不一致を拒否する。Operation ManifestはEphemeral＋Deferred改ざんを拒否し、HTTP ManifestはRoute欠落と非Inlineを拒否する。Application BootstrapはOperation／HTTP Metadataを相互照合し、Ephemeral HTTP Entryの削除も検出する。Command ManifestへEphemeral Typeを差し込んだ場合はComposerとConsole Runtimeの双方が実行前に拒否する。

## Lifecycle, Transaction, and HTTP Evidence

Ephemeral OperationのCanonical Lifecycleは次の4 Eventを維持する。

```text
operation.received(EmptyJournalData)
attempt.started(EmptyJournalData)
attempt.succeeded(EmptyJournalData)
operation.completed(OperationCompletedData(EmptyOutcome))
```

Inline DispatcherはHandlerから返った実OutcomeをCallerへ返す。Runtime Validatorは同じTransaction内かつCommit前にDeclared／Actual Ephemeral性の両方向一致、Declared Class完全一致、Structured Shape、JSON Encoding可能性を検証する。通常Outcome Metadataから実Ephemeral Outcomeを返すLegacy改ざんもCanonical Writer前にOperation ID付き`OperationExecutionFailed`へ閉じ、実OutcomeをJournalへ渡さない。Encoding不能な結果は業務Database更新と成功TerminalをRollbackする。Failure MessageへObject Dump、Property値、Encoder Detailを含めない。

HTTP ResponderはManifestのDeclared Class／Ephemeral Flagを再検証し、Structured JSONを200で一度返す。PropertyなしEphemeral Outcomeは204ではなく`{}`の200となる。通常Outcome 200、Void 204、Deferred 202、Rejected／Validation／Internal Contractは回帰していない。

## Persistence, Status, Console, Deferred, and Frontend Matrix

| Surface | Ephemeral Result |
| --- | --- |
| Direct PHP Inline Caller | 実Outcomeを返す |
| HTTP | Exact JSON 200を一度返す |
| Canonical Journal／Observer | Empty Dataだけを受け取る |
| PostgreSQL Outcome Store | Rowを作らず、防御Codecも実Objectを拒否する |
| Status | Subject取得とAuthorization後にUnavailable |
| Console | BuildとRuntimeの双方で拒否する |
| Deferred | Build、Manifest Decode、Acceptorで拒否する |
| Frontend | `.fetch()`／`.toRequest()`／`.url()`だけを公開する |

Permanent Frontend FixtureへEphemeral Credential Operationを追加した。Bound／Unbound ObjectのTypeとRuntimeからOperation固有`.status()`／`.wait()`を除外し、Direct Fetch 200、Strict Decoder、Malformed Responseの安全な`unexpected_response`、Module Export、生成Treeに値／Default／Exampleがないことを確認した。通常OperationのStatus／有限Waitは同じFixtureで継続成功している。

## Sensitive Non-persistence Evidence

In-memory Spyと実PostgreSQL Integrationで、Canonical Writer／Observerへ実Value／実Outcomeが到達しないことを確認した。DatabaseのReceivedは`EmptyJournalData`、Completedは`EmptyOutcome`であり、Outcome Tableは0 Rowである。StatusはEmpty OutcomeをDeclared Ephemeral ClassへDecodeしない。生成Manifest／TypeScriptはProperty名と型だけを持ち、値、Default、Example、Fixture Helperを持たない。

Safe Failure TestはBuild、Runtime Wrong Type、Encoding Failure、Manifest改ざん、Outcome Store防御を通し、例外、Observed Surface、Database Encoding、Artifact、Documentation、ReportへCredential実値を残さないことを検証した。

## Decisions and Assumptions

Runtime JSON検証をHTTP Responder任せにせず、Inline DispatcherのTransaction内でHandler結果直後かつCommit前に実行した。これによりHTTP投影不能な成功値が業務更新だけCommitされる状態を防ぐ。

Ephemeral OutcomeのCredentialはRoot Propertyで明示し、Nested DTO内のSensitive Propertyを拒否した。通常OutcomeのSensitive Property拒否は維持している。

Manifestには明示Ephemeral Flagを保存するが、Public `OperationMetadata`へPropertyを増やさずDeclared OutcomeからInternal判定する。Artifact DecoderはFlagとDeclared Typeを照合するため、Explicit MetadataとPublic API最小性を両立する。

Status SourceはRegistryにMetadataがありEphemeralの場合だけUnavailableへ切り替える。既存の手動Status FixtureでMetadataがない場合は従来Pathを維持し、Productionでは完全なOperation Manifest RegistryとHTTP Bootstrap Cross-artifact Guardを使用する。

Orchestrator Reviewで、Declared Outcomeが通常型でもLegacy Handlerが実Ephemeral Outcomeを返せる防御経路を確認した。Completed ResultではActual Outcomeを先に取得し、Declared／ActualのEphemeral性を両方向比較するよう修正した。In-memory Canonical Writer Testは安全なFailure Lifecycleだけが残り、Credential OutcomeがWriterへ到達しないことを固定する。

公開ガイドの新規単独PageはWebsite IA／Navigation変更がTask Scope外となるため作らず、既存Operation Authoring、Security、Outcome Retrievalへ統合した。Internal Designは独立Pageにした。

## Commands and Results

- Ephemeral focused PHPUnit: success、75 tests／258 assertions
- Orchestrator finding focused PHPUnit: success、66 tests／212 assertions
- Full PHPUnit: success、1641 tests／6539 assertions
- 最終HTTP／Frontend codec focused regression: success、21 tests／90 assertions
- `docker compose run --rm app mago format --check src tests`: success、all files formatted
- `docker compose run --rm app mago lint`: success、no issues
- `docker compose run --rm app mago analyze`: success、no issues
- `docker compose run --rm app vendor/bin/deptrac analyse --no-progress`: success、0 violations／0 skipped／0 uncovered／0 warnings／0 errors、2782 allowed
- Root／Quickstart `composer validate --strict`: valid
- `bash tests/Consumer/quickstart-setup.sh`: success
- `bash tests/Consumer/quickstart-e2e.sh`: success
- `bash tests/Consumer/skeleton-create-project.sh`: success
- `bash tests/Consumer/framework-update-generators.sh`: success
- Permanent Frontend Build／Generate／Fresh Check: success、7 generated files
- Permanent Frontend TypeScript／Runtime／Bound／Status-Wait／Module Shape: success、3 operations
- Website `pnpm test`: success、42／42
- Website `pnpm build`: success、31 routes／30 public pages、Artifact／Navigation／Accessibility／Search Check成功。既存Vite chunk-size warningだけを確認
- Management ID Guard、Community Board差分0、`git diff --check`: success
- Generated Frontend／Website Content、Build、Dependency Artifact: cleanup済み

## Acceptance Criteria

- [x] Public `EphemeralOutcome`が通常Outcome Authoringと整合し、追加Public Method／Metadata Propertyを持たない
- [x] Route付き明示Inline以外の利用をBuild時に拒否する
- [x] 実Value／実OutcomeがCanonical Journal／Observer／Outcome Storeへ到達しない
- [x] Lifecycle／Operation ID／Actor／Attempt／Safe Failureを通常Inlineと同様に追跡できる
- [x] HTTPが実Outcomeを一度だけExact JSONで返し、通常Responseを回帰させない
- [x] Status／Console／Deferred／ReplayからEphemeral Valueを取得できない
- [x] Frontend Clientが直接Fetchを型付き提供し、Status／Waitを公開しない
- [x] Credential実値がDatabase、JSONL、Log、Exception、Artifact、Reportへ残らない
- [x] Quickstart／Skeleton／Framework Update／Full Quality Gateが回帰する
- [x] Auth Core／Community Board差分0、外部Publication／Deployなし、Worker Commitなし

## Remaining Issues

Active Implementation Blockerはない。`make:auth` GeneratorとFresh Consumer、Register／Login／Logout Application Code、Community BoardのSession Core移行は後続P18-006C／P18-007 Scopeである。

## Suggested Next Action

OrchestratorがPublic API最小性、Manifest改ざん防御、Transaction内Shape検証、Canonical Writer到達前のValue／Outcome置換、Authorization後Status Unavailable、Generated Bound／Unbound Surfaceを独立Reviewする。Accept後はP18-006C Auth Generator and Fresh Consumerへ進む。

## Orchestrator Review

Reviewed At: 2026-07-22T14:19:14+09:00

Status: Accepted

Public Markerの最小性、Route付き明示InlineのBuild制約、Operation／HTTP／Frontend Manifestの相互整合、Transaction Commit前のRuntime検証、Canonical Journal／Observer／Outcome Storeへの非永続化、Authorization後のStatus Unavailable、Generated ClientのStatus／Wait非公開を独立Reviewした。

Review中に、通常Outcomeを宣言したLegacy Handlerが実際には`EphemeralOutcome`を返した場合、初期実装ではRuntime Validatorを迂回し、Canonical Writerへ実Outcomeが到達し得る経路を検出した。Completed ResultではDeclared／ActualのEphemeral性を必ず両方向比較し、不一致をCanonical Writer前に安全なFailureへ閉じるよう修正した。Regression TestはCredentialを含む実OutcomeがIn-memory WriterとFailure Messageへ到達しないことを固定している。

Orchestratorは対象PHPUnit 101 tests／322 assertions、`mago format --check src tests`、通常`mago lint` 0 issue、Deptrac 0 violations／0 uncovered／2782 allowed、`git diff --check`を独立再確認した。Worker実行のFull PHPUnitは1641 tests／6539 assertionsで、Quickstart／Skeleton／Framework Update／Permanent Frontend／Website／Management ID／Community Board Guardも成功している。

P18-006BのAcceptance Criteriaはすべて満たされ、Remaining Implementation Blockerはない。

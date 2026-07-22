# P18-006B: Ephemeral Outcome Contract

Status: Ready

## Goal

HTTP ClientへSecretを一度だけ返すPublic `EphemeralOutcome extends Outcome` Contractを実装する。既存のTyped Self-handled Operation Authoringを維持しながら、Ephemeral OperationのCredential Input／OutputをCanonical Journal、Outcome Store、Status、Console、Log、Generated Artifactへ永続化しない。

Operation Lifecycle、Operation ID、Actor相関、Attempt、Rejected／FailedのSafe Surfaceは通常どおり記録し、「No operation stays in the dark」を維持する。通常OperationのCanonical Value／Outcome、Deferred、Console、Frontend、Status Contractは回帰させない。

## In Scope

- Public `BlackOps\Core\EphemeralOutcome extends Outcome` Marker Interface
- Typed Self-handled／Legacy Handler Return TypeのEphemeral判定
- Route付き明示Inline限定のBuild-time Contract
- Operation／HTTP／Frontend ManifestのEphemeral MetadataとSchema更新
- Ephemeral Receivedの`EmptyJournalData`化、Completedの`EmptyOutcome`化
- 同一Inline ResultからHTTP JSON Responseへの一度だけの実Outcome投影
- Sensitive Property、Runtime Type、Responder Encoding、Safe Failure Guard
- Status Query Unavailable、Console／Deferred Build拒否
- Frontend直接`.fetch()`型と`.status()`／`.wait()`非生成
- Unit／Integration／実PostgreSQL／Permanent Frontend Fixture
- Public API Inventory、Guide／Internal Documentation、Specification／Report／STATE

## Out of Scope

- Session Auth Coreの変更
- `make:auth` Command／Generator／Stub／Migration Publish（P18-006C）
- Register／Login／Logout Application Code生成（P18-006C）
- Community Board Source／Configuration／Migration／Frontend変更（P18-007）
- Ephemeral Deferred、Console Secret Output、Status Outcome再取得、Replay
- Cookie発行、CSRF、Browser Storage、Encryption、Secret Retention
- 任意HTTP Status／Header／Streaming Responderの一般化
- Documentation Website／Community Boardの外部Publication／Deploy

## Relevant Decisions and Specifications

- `develop/decisions/089-validation-rejection-sensitive-journal.md`
- `develop/decisions/100-phase-15-operation-frontend-bridge.md`
- `develop/decisions/111-session-auth-package-contract.md`
- `develop/decisions/112-authentication-credential-response-boundary.md`
- `develop/spec/04-handler-and-result.md`
- `develop/spec/05-http.md`
- `develop/spec/17-core-api.md`
- `develop/spec/24-lifecycle-event-data.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/73-structured-outcome-contract.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/75-phase-18-delivery-plan.md`

## Public Contract

次のPublic Markerだけを追加する。

```php
namespace BlackOps\Core;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface EphemeralOutcome extends Outcome
{
}
```

- Public Method、Factory、Serializer、Raw Getterは追加しない。
- Ephemeral Outcome具象Classは通常Outcomeと同じくFinal Readonly、Public Constructor-promoted Property、Supported Structured Scalar Shapeを使う。
- Credentialを持つPublic Propertyは`#[Sensitive]`を必須とする。Ephemeral Outcome全体はPropertyのSensitive有無にかかわらず非永続化する。
- PropertyなしEphemeral Outcomeを許可し、Secret Inputだけを持つLogout等を同じ非永続化境界へ入れる。
- 通常OutcomeのSensitive Propertyは従来どおりBuild Errorとする。

## Build and Manifest Contract

Declared Outcomeが`EphemeralOutcome`を実装する場合、Build Compilerは次をすべて検証する。

- `#[Route]`が一つある
- `#[ExecuteWith(Inline::class)]`が明示されている。Default Inlineへの暗黙依存を許可しない
- `#[ConsoleCommand]`がない
- Outcome ShapeはHTTP JSONへ決定的に投影可能である
- Credential／Reserved Sensitive Keyに該当するPropertyは`#[Sensitive]`を持つ
- Operation／HTTP／Frontend Manifestが同じEphemeral FlagとOutcome Shapeを持つ

Deferred、Console、Routeなし、Implicit Inline、Unsupported Shape、通常OutcomeのSensitive Property、Manifest Flag／Declared Type不一致はSafe Build Failureにする。Class名／Property名はErrorへ含めてよいが、Source全文、Default／Example／Raw Value、Absolute Pathは含めない。

Artifact Schemaを必要な範囲で明示的に上げ、旧Schema／Missing Flag／Invalid TypeをFreshness／Exact Shape検証で拒否する。失敗Buildは既存の有効Artifactを維持し、Runtime Source Scan／Reflection Fallbackを追加しない。

## Lifecycle and Persistence Contract

Ephemeral Operationも通常のInline State Machineを通る。

```text
operation.received -> attempt.started -> attempt.succeeded -> operation.completed
```

- `operation.received`のDataは`EmptyJournalData`とし、実OperationValueをCanonical Writer／Observer／Codecへ渡さない。
- `operation.completed`のDataは`OperationCompletedData(new EmptyOutcome())`とし、実Ephemeral OutcomeをCanonical Writer／Observer／Codecへ渡さない。
- Rejected／Failedは既存のSafe Category／Code／Errorだけを記録し、Value／Outcomeを追加しない。
- Validation前後のRejected Pathでも、Ephemeral Routeが確定した後はBinding済みValueをCanonical Journalへ渡さない。
- Ephemeral OutcomeをPostgreSQL Outcome Storeへ保存しない。Inline Contract外で保存しようとした場合はSafe Integrity Failureにする。
- DiagnosticsはLifecycleとEmpty Dataを表示できるが、入力／出力を復元／推測しない。

Journal／Observer／Transport Codecへ実Ephemeral Objectが到達しないことをSpy／実PostgreSQLで固定する。Raw Secret、Credential Key／ValueをJSONL、DB Dump、Exception、Log、Reportへ出さない。

## HTTP Runtime Contract

- Inline Dispatcherの`OperationResult`は実Ephemeral Outcomeを呼出し元へ返す一方、Lifecycle記録には`EmptyOutcome`を使う。
- `OperationRequestHandler`／`JsonOperationResponder`はManifestでEphemeralと宣言されたRouteの実値が同じDeclared Ephemeral Classであることを検証する。
- ResponderはBuild検証済みShapeを一度だけJSONへ投影し、HTTP 200を返す。Propertyなしは空Object `{}`であり204へ変換しない。
- Runtime Wrong Type、Manifest不一致、JSON Encoding Failureは既存のOperation ID付きSafe Internal Errorへ閉じ、Raw Value／Object Dump／Throwable Detailを出さない。
- Response生成後にFramework Cache、Request Scope、Static StateへEphemeral Outcomeを保持しない。FrankenPHP Worker Modeの次Requestへ漏らさない。

通常Outcome 200、Void 204、Deferred 202、Rejected／Validation／Internal Shapeは変更しない。

## Status and Console Contract

- Ephemeral OperationはStatus Outcome再取得対象外とする。既存Authorizationを先に通し、許可後は`OperationStatusUnavailable`を返す。
- Status SourceはJournalの`EmptyOutcome`をDeclared Ephemeral ClassとしてDecode／Castしない。
- `#[ConsoleCommand]`との併用はBuild Errorであり、Human／JSON Console OutputへEphemeral Outcomeを追加しない。
- PHPのInline Dispatcherを直接利用した呼出し元は実Ephemeral Outcomeを受け取れるが、FrameworkのJournal／Observation Surfaceには残らない。

## Frontend Contract

- Ephemeral OperationのInput、直接`.fetch()`、`.toRequest()`、`.url()`を生成する。
- Unbound／Bound Operation Objectの双方で`.status()`／`.wait()`を型／Runtimeから省く。
- Direct Fetch成功型は通常Inlineと同じ`kind: 'completed'`、HTTP 200、Declared Ephemeral Outcome Shapeとする。
- Sensitive Propertyは名前とTypeだけを生成し、Value、Default、Example、Fixture、Log HelperをManifest／Generated Treeへ埋め込まない。
- Generated RuntimeはResponse Shapeを厳密検証するが、Validation ErrorへRaw Body／Tokenを含めない。
- Non-ephemeral Operation Objectと既存Generated APIは互換維持する。

## Allowed Files

- `src/Core/EphemeralOutcome.php`
- Ephemeral Metadata／Build検証に必要な`src/Core/Registry/**`、`src/Internal/Discovery/**`、`src/Internal/Registry/**`、`src/Internal/Manifest/**`、`src/Internal/Console/**`の最小差分
- Lifecycle非永続化に必要な`src/Internal/Execution/**`、`src/Internal/Journal/**`、`src/Journal/**`、`src/Transport/PostgreSql/**`の最小差分
- HTTP／Statusに必要な`src/Http/**`、`src/Internal/Status/**`、`src/Status/**`、`src/Internal/Application/**`の最小差分
- Frontend Contract／Generatorに必要な`src/Internal/Frontend/**`
- 対応する`tests/**`、P18-006B専用Permanent Fixture／Consumer
- `develop/spec/04-handler-and-result.md`、`develop/spec/05-http.md`、`develop/spec/17-core-api.md`、`develop/spec/24-lifecycle-event-data.md`、`develop/spec/50-operation-authoring-and-build-discovery.md`、`develop/spec/67-operation-frontend-bridge.md`、`develop/spec/69-deferred-status-and-outcome-api.md`、`develop/spec/73-structured-outcome-contract.md`、`develop/spec/74-application-ergonomics.md`、`develop/spec/75-phase-18-delivery-plan.md`
- `docs/guide/**`、`docs/internal/**`、Public API Inventory／Website Test同期に必要な`docs/website/tests/reader-experience.test.mjs`
- `develop/TODO.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-006B-ephemeral-outcome-contract.md`

`src/Auth/**`、`src/Internal/Auth/**`、`resources/stubs/auth-*`、`examples/community-board/**`は変更禁止とする。Quickstart／Skeleton／Framework UpdateはRegression Test対象だが、Auth Scaffoldは追加しない。Allowed File追加が必要ならProduction Codeを広げずReportでOrchestratorへ返す。

## Required Verification

1. Public API／Marker／Typed Self-handled／Legacy Return Contract Unit Test
2. Route＋Explicit Inline成功、Implicit Inline／Deferred／Console／Routeなし／Wrong Type Build Failure Matrix
3. In-memory／実PostgreSQL LifecycleでReceived Value／Completed実Outcome非永続化、Empty Data、Raw Secret不在
4. HTTP 200 Exact JSON、Propertyなし `{}`、Rejected／Validation／Internal、Encoding Failure、Worker Reuse
5. Status Authorization後Unavailable、Outcome Store 0 Row、Diagnostics Safe Surface
6. Frontend Manifest／Generated Bound・Unbound Client、`.fetch()`成功、`.status()`／`.wait()`型不在、Strict TypeScript、Drift
7. Existing Full PHPUnit、Quickstart Setup／E2E、Skeleton Create-project、Framework Update Generator
8. Root／Quickstart `composer validate --strict`
9. Website Reader Test／Build（Public API数とDocumentationを変更するため必須）
10. Mago format／lint／analyze、Deptrac、Management ID Guard、`git diff --check`
11. Community Board差分0、Generated／Dependency／Runtime Artifact Cleanup

## Acceptance Criteria

- [ ] Public `EphemeralOutcome`が通常Outcome Authoringと整合し、余計なPublic Methodを持たない
- [ ] Route付き明示Inline以外の利用をBuild時に拒否する
- [ ] Ephemeral Operationの実Value／実OutcomeがCanonical Journal／Observer／Outcome Storeへ一度も渡らない
- [ ] Lifecycle／Operation ID／Actor／Attempt／Safe Failureは通常Inlineと同様に追跡できる
- [ ] HTTPは実Ephemeral Outcomeを一度だけExact JSONで返し、通常Response Contractを回帰させない
- [ ] Status／Console／Deferred／ReplayからEphemeral Valueを取得できない
- [ ] Frontend Clientが直接Fetchを型付き提供し、Status／Waitを公開しない
- [ ] Raw SecretがDatabase、JSONL、Log、Exception、Artifact、Reportへ残らない
- [ ] Quickstart／Skeleton／Framework Update／Full Quality Gateが回帰する
- [ ] Auth Core／Community Board差分0、外部Publication／Deployなし、Worker Commitなし

## Completion Report

`develop/orchestration/reports/P18-006B-ephemeral-outcome-contract.md`にAGENTS.mdの必須Sectionに加え、次を記録する。

- Final Public API、Build Matrix、Manifest Schema
- Received／Completed Canonical DataとActual HTTP Resultの分離Evidence
- Status／Console／Deferred／Frontend Matrix
- Raw Secret Non-persistence Evidence
- Consumer／Website／Full Quality Gate結果
- Allowed Scope外の必要性が発生した場合のBlocker
- Commandsと実結果、未実行理由、Remaining Issue

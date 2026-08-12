# Namespace Dependencies

## 許可する依存方向

この表が許可する依存方向の正本である。`deptrac.yaml`はこの表へ同期し、Release Gateでその方向を強制する。矢印の左側は右側へ依存できる。

| Layer | 許可する依存先 |
| --- | --- |
| Core | Telemetry, Idempotency |
| Identifier | Core |
| Idempotency | Core |
| Outbox | Core |
| Scheduling | Core |
| Application | Core, InternalApplication, Library |
| Auth | Core, Http, InternalAuth, Library |
| Database | Core, Library |
| Journal | Core, Telemetry |
| Execution | Core, Journal, Telemetry, Idempotency |
| Transport | Core, Journal, Execution, Outcome, Library, Telemetry, StorageProtection, InternalStorageProtection, DeferredIntegrity |
| Outcome | Core |
| Status | Core |
| OperationData | Core, Journal, Outcome |
| Http | Core, Observability, Application, Execution, InternalHttp, InternalIdempotency, InternalSapiRuntime, Outcome, Status, Library, Telemetry, Idempotency |
| Logging | Core, Journal, Library |
| Console | Core, Observability, Journal, Execution, Transport, Library |
| StorageProtection | Core |
| Telemetry | Core, Library |
| Observability | Core, Library |
| Internal | Core, Scheduling, Journal, Execution, Transport, Http, Logging, Console, Outcome, Status, OperationData, Database, Auth, StorageProtection, Observability, Library, Telemetry, InternalTelemetry, InternalStorageProtection, DeferredIntegrity, Identifier, Idempotency, Outbox, InternalApplication, InternalHttp, InternalIdempotency |
| InternalApplication | Core, Database, Execution, Http, Internal, InternalHttp, InternalIdempotency, InternalStorageProtection, InternalTelemetry, Journal, Library, Logging, OperationData, Outbox, Scheduling, StorageProtection, Transport |
| InternalAuth | Auth, Core, Database, Library |
| InternalHttp | Core, Http, Idempotency, Internal, InternalIdempotency, InternalTelemetry, Library, Status, Telemetry |
| InternalIdempotency | Core, Http, Idempotency, Internal, InternalStorageProtection, Journal, Library, StorageProtection, Transport |
| InternalSapiRuntime | Library |
| InternalTelemetry | Core, Telemetry, Library |
| InternalStorageProtection | Core, StorageProtection, InternalTelemetry |
| DeferredIntegrity | Core |
| Library | External adopted namespaces: Monolog, FastRoute, Doctrine DBAL/Migrations, PSR Clock/Cache/Container/HTTP/Log, Symfony Console/DependencyInjection/Stopwatch/Uid/Validator, OpenTelemetry, Dotenv, Nyholm |

`Identifier`、`Idempotency`、`Outbox`は公開Contractとして独立Layer化し、各Layer自身はCoreへだけ依存する。Coreはoptional execution-context idempotency hashのため`Idempotency`へ依存し、Core↔IdempotencyにはD141で明示した狭い循環がある。採用Libraryの`Dotenv`と`Nyholm`はLibrary collectorへ明示する。`Telemetry`はHTTP、Execution、Journal、Transport、Internalから伝播／安全な相関値のために参照されるcross-cutting Public Layerである。

`InternalTelemetry`、`InternalStorageProtection`、`DeferredIntegrity`（`Internal\\Execution\\DeferredOperationContextValidator`）はcatch-all `Internal`から除外したbounded collectorである。TransportはStorageProtection Public Contract、`InternalStorageProtection`、`DeferredIntegrity`だけを利用でき、catch-all `Internal`および`InternalTelemetry`へは依存できない。`InternalStorageProtection`は`InternalTelemetry`へ依存できる。`Transport -> Internal`のgeneric permission、skip、uncovered ignoreは存在しない。

矢印は左側が右側へ依存できることを表す。現行Rulesetの非自明なSCCは`Core / Idempotency / Telemetry`と、`Application / Auth / Http / Internal / InternalApplication / InternalAuth / InternalHttp / InternalIdempotency`の2つだけである。後者はD064／D069／D111／D114のfacade compositionとimplementation間依存から生じるD142の明示受入れ範囲であり、`InternalSapiRuntime`は一方向依存でSCC外にある。これら以外の非自明な循環依存は禁止する。

現行P22-003BはD142 Option Bを適用する。`InternalApplication`、`InternalAuth`、`InternalHttp`、`InternalIdempotency`、`InternalSapiRuntime`をcatch-all `Internal`と重複しないnarrow collectorへ分離し、Public facadeからの許可方向を`Application -> InternalApplication`、`Auth -> InternalAuth`、`Http -> InternalHttp, InternalIdempotency, InternalSapiRuntime`だけに限定する。boundedの意味はPublic facadeからcatch-all `Internal`へのdirect permissionを禁止し、列挙5 collectorだけを入口にすることである。D064／D069／D111／D114で確定したPublic facade／Internal implementationの8-layer SCCだけを明示受入れし、任意の循環を許可しない。D022の一般的な逆流／循環防止はこの列挙済み例外だけ部分的に置換され、D141のTransport／Telemetry／Storage Protection／Deferred Integrity boundaryと`Internal -> Application`禁止は維持する。

公開APIのSignatureへ `BlackOps\Internal` の型を露出させてはならない。

`BlackOps\Database\Seeder`と`SeederRunner`はCoreの`#[PublicApi]`だけへ依存する。Compiled Locator、Runner実装、Root Runtimeは`BlackOps\Internal`へ置き、Database Public NamespaceからInternalへ逆依存しない。

## 検証

Deptracを開発依存として採用し、NamespaceをLayerとして定義する。

- 設定は `deptrac.yaml` としてRepositoryで管理する
- CIで解析を実行する
- 違反がある場合はCIを失敗させる
- Namespaceを追加する場合はLayerとRulesetも更新する
- `StorageProtection` Public ContractはCoreのTenant Identity／PublicApi Attributeだけへ依存し、暗号実装はInternalへ閉じる
- `OperationData` Public Query／Result／Authorization ContractはCoreのActor／Tenant／Operation ID、Journal Record、およびOutcome Recordだけへ依存する。PostgreSQL Adapter、Default-deny Resolver、Codec境界は`BlackOps\Internal\OperationData`へ置き、Raw ReaderをPublic Application Bindingへ公開しない。
- `BlackOps\Telemetry`は独立したPublic NamespaceとしてLayer化し、`OpenTelemetry\`は採用Libraryへ明示する。`Core\ExecutionContext`の末尾optional telemetry extensionがTelemetryへ向くため、Core -> TelemetryとTelemetry -> Core（PublicApi marker／safe correlation value）の狭い循環を意図的に許可する。TelemetryからCoreの業務型・Value・Tenant・Actorへ依存してはならない。
- `BlackOps\Observability`はHealth／ReadinessのPublic Query、Report、Check Contractを提供する独立Layerとし、CoreのPublicApi marker／safe valueとLibraryのClockだけへ依存する。HTTP／Consoleの明示Probe AdapterとInternalのbounded check compositionだけがObservabilityへ依存し、FrameworkはProbe Route／Commandを自動登録しない。

Dependency競合が生じる場合は、PHARまたは分離したComposer Binaryとして導入する。

対象RuntimeであるPHP 8.5の構文を正しく解析できることをCIで継続的に確認する。

Release GateのQuality ToolingはD140に従う。DeptracはPHP 8.5でProject Graph全体へ到達するexact対応Versionを使用し、vendor parse failureをArchitecture成功へ読み替えない。Magoの既存DebtはMago生成のtracked strict baselineへissue単位で固定し、通常Lintに加えてbaseline同期検査をCIで実行する。Rule無効化、Severity downgrade、手書きIssue追加で新規問題を隠さない。

P22-003Aの4.7.1実測では、変更前Rulesetで857/857 fileの解析まで到達した後、152 violations／59 uncovered（warnings／errors 0）が報告された。この値はP22-003Aのhistorical blockerであり、受入れ値ではない。P22-003BでLayer／RulesetをD141へ同期した現行値は857/857、0 violations、0 skipped、0 uncovered、0 warnings、0 errorsである。vendor parser停止の解消とArchitecture成功を混同しない。

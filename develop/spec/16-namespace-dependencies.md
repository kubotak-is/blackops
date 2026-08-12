# Namespace Dependencies

## 許可する依存方向

```text
Core       -> 外部Adapter Namespaceへ依存しない（TelemetryはExecutionContextのoptional extensionとして例外的に許可）
Scheduling -> Core
Database   -> Core, Library
Journal    -> Core
Execution  -> Core, Journal
Transport  -> Core, Journal, Execution
Http       -> Core, Execution, Observability
Logging    -> Core, Journal
Console    -> Core, Journal, Execution, Transport, Observability
Observability -> Core, Library
StorageProtection -> Core
Telemetry  -> Core（PublicApi marker／correlation valueの最小依存）, Library（OpenTelemetry API validator）
OperationData -> Core, Journal, Outcome
Internal   -> 対応する公開Namespaceおよび採用Library（Scheduled RuntimeはSchedulingへ依存可能）
```

矢印は左側が右側へ依存できることを表す。記載のない逆向き依存と循環依存は禁止する。

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

P22-003Aの4.7.1実測では857/857 fileの解析まで到達した後、現行Ruleset上の152 violations／59 uncovered（warnings／errors 0）が報告された。Ruleset／Layerの修正はP22-003Aの範囲外であり、Architecture gateは未達のままP22-003B専用Taskで扱う。vendor parser停止の解消とArchitecture成功を混同しない。

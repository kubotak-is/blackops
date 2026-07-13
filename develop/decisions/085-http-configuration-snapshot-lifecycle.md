# D085: HTTP Configuration Snapshot Lifecycle

Status: Decided

## Context

`bootstrap/app.php`はProcess Environmentと`.env`を読み、`config/*.php`を評価して`ApplicationConfigurationSnapshot`を作る。ConsoleとWorkerではProcess起動時に一度だけ実行する。

現在の公式FrankenPHP Classic Modeでは`public/index.php`をRequestごとに実行するため、Application、Environment配列、Config FileもRequestごとに再構成する。`$_ENV`の個別参照よりBootstrap全体の再評価が主なCostであり、「RequestごとにApplication Configurationを再読込しない」という公開Bootstrap仕様とも整合していない。

## Question 1: HTTP ProcessでConfigurationをどう再利用するか

### Options

- A: Build時に正規化済みConfiguration Artifactを生成し、HTTP RuntimeはArtifactだけを読む
- B: FrankenPHP Worker Modeを公式標準にし、ApplicationをProcess起動時に一度構成してRequest間で再利用する
- C: Classic Modeを維持し、Config Fileの`require`だけをProcess-local Cacheで省略する
- D: 現状を維持し、Classic ModeではRequestごとのBootstrap Costを許容する

### Recommendation

Bを最終到達点として推奨する。ただし、Classic Modeから即時にDefaultを切り替えず、専用Taskで安全性を証明してから昇格する。

BlackOpsはD058でFrankenPHPを公式Reference Runtimeとし、Long-running ProcessでOperation Scope、Journal Flush、Connection Healthを明示管理する設計を採用している。Worker ModeならEnvironmentとConfigurationだけでなくCompiled Container／ManifestのRuntime CompositionもProcess起動時に一度へ集約できる。

RequestごとのState Reset、Observer Flush、Connection Recoveryを実証する必要がある。最初は明示Opt-inとしてConsumer E2Eと複数Request Testを通し、安全性を確認してから公式Defaultへ昇格する。

AはRuntime非依存性と決定性に優れるが、現行SnapshotをそのままArtifact化するとDatabase Password等を保存するため採用しない。将来、Secretを含まないBuild-time StructureだけをArtifact化し、Bと併用する余地はある。CはClassic ModeのRequest終了で通常のPHP Static／Object Cacheが消えるため成立せず、APCu等を追加するとInvalidationとSecret残存が複雑になる。

## Investigation Evidence

- 公式CaddyfileはWorker指定なしの`php_server`であり、Classic Modeで動く。
- `public/index.php`はRequestごとに`bootstrap/app.php`を読み、BootstrapはEnvironment、Dotenv、Applicationを再構成する。
- `ApplicationBuilder::withConfiguration()`はその都度`config/*.php`を`require`する。
- `Application::http()`のRuntime CacheはApplication Instance内だけであり、Classic ModeのRequest間では再利用しない。
- Worker Modeへ移る場合、複数Request間のState Leak、例外後Cleanup、JSONL Flush、Database Reconnect、Memory Growth、`max_requests`／Restartを検証する必要がある。

[ANSWER]

B

[/ANSWER]

## Decision

[DECISION]

1. 公式HTTP RuntimeはFrankenPHP Worker Modeを最終Defaultとし、Application、Environment、Configuration、Compiled RuntimeをProcess起動時に一度だけ構成する。
2. Classic Modeから即時に切り替えず、最初は明示Opt-inで導入する。
3. 複数Request間のState Leak、例外後Cleanup、Journal Observer Flush、Database Connection Recovery、Memory Growth、Worker Restart／`max_requests`をConsumer E2Eで検証する。
4. 安全性を証明した後にSkeletonの公式Defaultへ昇格する。
5. Secretを含むConfiguration SnapshotをBuild Artifactとして保存しない。
6. 将来、Secretを含まないBuild-time StructureだけをArtifact化してWorker Modeと併用できる。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Requestごとの`$_ENV`走査とConfig File評価だけでなく、Application Runtime Composition全体をProcess単位へ移せる。
- Long-running Processの安全なRequest境界が新しいFramework Contractになる。
- RequestごとにService Stateが消えることへ依存したApplicationはWorker Mode互換ではないため、Opt-in期間とUpgrade Guideが必要になる。
- 部分Cacheは採用しない。

[/CONSEQUENCES]

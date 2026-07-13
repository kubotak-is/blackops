# D085: HTTP Configuration Snapshot Lifecycle

Status: Discussing

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

Bを推奨する。

BlackOpsはD058でFrankenPHPを公式Reference Runtimeとし、Long-running ProcessでOperation Scope、Journal Flush、Connection Healthを明示管理する設計を採用している。Worker ModeならEnvironmentとConfigurationだけでなくCompiled Container／ManifestのRuntime CompositionもProcess起動時に一度へ集約できる。

ただし、RequestごとのState Reset、Observer Flush、Connection Recoveryを実証する必要がある。AはRuntime非依存性と決定性に優れるが、Secretを含むDatabase Configuration Artifactの保存境界、Environment差し替え、Deploy手順を追加設計する必要がある。Cは一部だけをCacheしてSnapshotの意味が分かりづらくなる。

[ANSWER]


[/ANSWER]

## Pending Consequences

回答後にHTTP Entrypoint、FrankenPHP Configuration、Application Lifecycle、Secret境界、Performance Test、DocumentationをTask Packetへ落とし込む。


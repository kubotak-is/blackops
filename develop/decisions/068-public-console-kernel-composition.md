# D068: Public Console Kernel Composition

Status: Proposed

## Context

P7-003で `Application::http()` と公開HTTP Runtime Compositionを受け入れた。P7-004では、Project所有の薄い `bin/blackops` がFramework所有Commandを起動できるPublic `ConsoleKernel` を実装する。

既存CommandはSymfony Console Commandとして実装済みだが、Build Commandは低レベルのPath引数を要求し、Migration／Worker／Retention／Scheduler CommandはDB Connection、Artifact、Worker Signal、Policy等をApplication側で直接構成する前提になっている。Installed Applicationへ `BlackOps\Internal` を露出せず、Framework UpdateでCommand実装が更新される境界を決定する必要がある。

## Question 1: Public Console Kernel API

`ConsoleKernel` の公開実行Contractをどうするか。

### Options

- A: `run(?InputInterface $input = null, ?OutputInterface $output = null): int` を公開し、Symfony Console Application自体のGetterは公開しない
- B: 引数なしの `run(): int` だけを公開する
- C: `ConsoleKernel` をSymfony Console ApplicationのSubclassとして公開する

### Recommendation

Aを推奨する。

通常の `bin/blackops` は引数なしで実行でき、Test／埋込み利用ではSymfonyのInput／Outputを明示できる。Symfony ApplicationそのものをService Locatorとして公開せず、FrameworkがCommand登録とApplication Name／Versionを所有できる。

[ANSWER]



[/ANSWER]

## Question 2: Command Composition Timing

Database、Artifact、PCNTL等を必要とするFramework Commandをいつ構成するか。

### Options

- A: Command名とDescriptionは常に登録し、Command実行時に必要なDependencyだけを遅延構成する
- B: `Application::console()` の初回呼出時に全Framework CommandとDependencyを一括構成する
- C: Configが完全に揃ったCommandだけを登録し、不足するCommandは一覧から除外する

### Recommendation

Aを推奨する。

`list`、`help`、Build CommandがDatabase接続やPCNTL availabilityに巻き込まれない。Config不足は対象Commandを実行した時点で責務とKeyを示してFail-fastし、Commandが環境によって黙って消える挙動も避けられる。

[ANSWER]



[/ANSWER]

## Question 3: Application-aware Build Command

Installed Applicationの `blackops:build:compile` をどの入力Contractにするか。

### Options

- A: Application SnapshotのOperation／Service Providerと `config/app.php` のBuild Pathを使い、必須Path引数なしでCompileする公開Commandへ更新する
- B: 現在の低レベルCommandをそのまま登録し、Provider Configと出力Pathを毎回CLI引数で渡す
- C: 現在の低レベルCommandを残し、別名のApplication-aware Commandを追加する

### Recommendation

Aを推奨する。

Skeletonの標準手順を `php bin/blackops blackops:build:compile` にできる。ProviderとPathの正本がBootstrap／ConfigとCLI引数に分裂せず、HTTP Runtimeが読むArtifactと同じ出力先を使える。低レベルCompiler Serviceは内部で再利用する。

[ANSWER]



[/ANSWER]

## Question 4: Worker Configuration

Worker固有値をどこまでApplication Configで要求するか。

### Options

- A: `config/execution.php` の `worker` SectionでWorker IDを必須とし、Lease 60秒、Heartbeat 10秒、Grace 20秒、Handler Failure後継続をFramework Defaultとして上書き可能にする
- B: Worker IDをHostname／Process IDからFrameworkが自動生成し、時間値も固定する
- C: Worker IDを含むすべての値をCLI Optionとして毎回必須入力にする

### Recommendation

Aを推奨する。

Lease Ownerは運用上追跡できる明示値にしつつ、安全な時間DefaultでSkeletonの設定量を抑えられる。Environment Variableとの対応は `config/execution.php` が所有し、Frameworkは特定のEnvironment Variable名をHard Codeしない。Worker用ConnectionとHeartbeat用Connectionは同じParameterから生成する別Instanceとする。

[ANSWER]



[/ANSWER]

## Question 5: Retention and Scheduler Configuration

Retention CommandとSchedulerのPolicy入力をどう管理するか。

### Options

- A: `config/retention.php` に4対象の保持日数、Policy Ref、Actorを定義し、Retention CommandとSchedulerのRetention Taskが同じ設定を使う
- B: Retention Commandは全値をCLI Optionで受け、Scheduler用設定だけを別途持つ
- C: Phase 7ではRetention／Scheduler Commandを登録せず、後続Phaseへ延期する

### Recommendation

Aを推奨する。

手動Plan／PurgeとSchedulerでPolicyが分岐しない。CLIの保持日数Optionは一時的な明示Overrideとして残せるが、Schedulerは同じAccepted Configuration Snapshotから既定Policyを構成する。Config不足は対象Command実行時に安全に失敗させる。

[ANSWER]



[/ANSWER]

## Decision

[DECISION]

回答待ち。

[/DECISION]

## Consequences

[CONSEQUENCES]

回答後に確定する。

[/CONSEQUENCES]

## References

- [Public Application Bootstrap API](../spec/44-public-application-bootstrap-api.md)
- [Phase 7 Delivery Plan](../spec/45-phase-7-delivery-plan.md)
- [Public HTTP Runtime Configuration](../spec/47-public-http-runtime-configuration.md)
- [Worker Runtime](../../docs/internals/worker-runtime.md)
- [Maintenance Scheduler](../../docs/internals/maintenance-scheduler.md)

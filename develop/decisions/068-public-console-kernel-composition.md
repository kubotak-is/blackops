# D068: Public Console Kernel Composition

Status: Decided

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

A

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

A

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

A

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

A

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

A

[/ANSWER]

## Decision

[DECISION]

1. Public `ConsoleKernel` は `run(?InputInterface $input = null, ?OutputInterface $output = null): int` を公開する。
2. `ConsoleKernel` はSymfony Console Applicationを内部で使用し、そのInstance GetterやSubclass Contractを公開しない。
3. Framework Commandの名前とDescriptionは常に登録し、Database、Artifact、PCNTL等のDependencyは対象Commandの実行時に遅延構成する。
4. `list`、`help`、Build CommandはDatabase接続、Artifact Load、PCNTL availabilityを要求しない。
5. Config不足またはRuntime Dependency不足でCommandを一覧から黙って除外せず、対象Command実行時に安全なBootstrap Errorとして失敗させる。
6. `blackops:build:compile` はApplication SnapshotのOperation／Service Providerと `config/app.php` のBuild設定を使用するApplication-aware Commandとする。
7. Installed ApplicationのBuild CommandはProvider Config／Artifact出力Pathの必須CLI引数を要求しない。
8. `config/app.php` のBuild設定へApplication Build IDを追加し、HTTP Runtimeが読むArtifactと同じPathへOperation Manifest、HTTP Manifest、Containerを生成する。
9. `config/execution.php` の `worker` SectionでWorker IDを必須とする。
10. WorkerはLease 60秒、Heartbeat 10秒、Grace 20秒、Handler Failure後継続をFramework Defaultとし、Application Configで上書きできる。
11. Worker用ConnectionとHeartbeat用Connectionは、同じ解決済みDBAL Parameterから生成する別Instanceとする。
12. FrameworkはWorker IDをHostname／Process IDから推測せず、特定のEnvironment Variable名をHard Codeしない。
13. `config/retention.php` にTransport Payload、Journal、Outcome、Dead Letterの保持日数、Policy Ref、Actorを定義する。
14. Retention Plan／Purge CommandとSchedulerのRetention Taskは同じAccepted Configuration SnapshotのPolicyを使う。
15. Project所有の `bin/blackops` はAutoloaderと `bootstrap/app.php` を読み、`$application->console()->run()` の終了Codeを返すだけとする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Framework Command実装とDefaultはFramework Updateで更新され、Project Entrypointを更新する必要がない。
- Command一覧の取得とBuildは、DatabaseやWorker Signal Runtimeが利用できない環境でも実行できる。
- Runtime Configの検証はCommand単位で遅延するため、無関係なCommandをConfig不足へ巻き込まない。
- Build Provider、Build ID、Artifact PathはApplication BootstrapとConfigが正本になり、CLI引数とのDriftを避けられる。
- Worker IDはApplicationが明示し、Lease Ownerを運用上追跡できる。
- Worker HeartbeatはMain Transactionと別Connectionを使うため、Signal中断時のConnection再入を避けられる。
- 手動RetentionとScheduler Retentionは同じPolicyを使い、保持期間の分岐を避けられる。
- Symfony Console Input／OutputはPublic Method Signatureへ現れるため、Symfony ConsoleはPublic Runtime Dependencyとして互換性管理対象になる。

[/CONSEQUENCES]

## References

- [Public Application Bootstrap API](../spec/44-public-application-bootstrap-api.md)
- [Phase 7 Delivery Plan](../spec/45-phase-7-delivery-plan.md)
- [Public HTTP Runtime Configuration](../spec/47-public-http-runtime-configuration.md)
- [Worker Runtime](../../docs/internal/worker-runtime.md)
- [Maintenance Scheduler](../../docs/internal/maintenance-scheduler.md)

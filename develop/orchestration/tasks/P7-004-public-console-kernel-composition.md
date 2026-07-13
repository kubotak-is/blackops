# P7-004: Public Console Kernel Composition

Status: Accepted

## Goal

Accepted Application Configuration SnapshotからFramework標準CommandとApplication独自Commandを登録したPublic `ConsoleKernel` を構成し、Installed Applicationが `BlackOps\Internal` を参照せず、Project所有の薄い `bin/blackops` からBuild、Migration、Worker、Retention、Schedulerを明示実行できるようにする。

## In Scope

- Public `Application::console(): ConsoleKernel`
- Public final `ConsoleKernel::run(?InputInterface, ?OutputInterface): int`
- Application Instance単位のConsoleKernel遅延構成と再利用
- Symfony Application／Command LoaderのInternal隠蔽
- Framework Command名／Descriptionの常時登録と実Command Factoryの遅延構成
- Application Command登録、重複除去、Framework Command名競合拒否
- Application-aware Build CompileとOperation List
- Database Migration Status／Migrate Composition
- Deferred Worker Runtime／Loop／Signal Heartbeat Composition
- Retention Plan／Purge Composition
- Retention Maintenance Scheduler Run／Daemon Composition
- Build、Execution、Retention Config Validation
- Credential非露出と責務別Bootstrap Error
- Public API／Architecture／Integration Test
- Guide／Internals Documentation

## Out of Scope

- `examples/quickstart/` と実 `bin/blackops` 配置
- Generator Command
- Individual Manifest／Container Compile Commandの標準Kernel登録
- HTTP Status／Outcome Endpoint
- Journal Observer／Remote Logging Config
- Scheduler Multi-start Lock
- Dotenv Loading
- Migration／Build／Worker／Purgeの暗黙実行

## Relevant Specifications and Decisions

- `develop/decisions/068-public-console-kernel-composition.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/48-public-console-kernel-composition.md`

## Files Allowed to Change

- `src/Application/Application.php`
- `src/Application/ApplicationBootstrapException.php`
- `src/Application/ConsoleKernel.php`
- `src/Internal/Application/**`
- `src/Internal/Console/**`
- `tests/Application/**`
- `tests/Internal/Application/**`
- `tests/Internal/Console/**`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `deptrac.yaml`
- `docs/guide/application-bootstrap.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/database-migrations.md`
- `docs/guide/retention.md`
- `docs/internal/application-bootstrap.md`
- `docs/internal/bootstrap.md`
- `docs/internal/worker-runtime.md`
- `docs/internal/maintenance-scheduler.md`
- `develop/orchestration/tasks/P7-004-public-console-kernel-composition.md`
- `develop/orchestration/reports/P7-004-public-console-kernel-composition.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Required Public Contract

`Application` に次だけを追加する。

```php
public function console(): BlackOps\Application\ConsoleKernel;
```

`ConsoleKernel` は `#[PublicApi]` final classとし、Public Methodを次だけに限定する。

```php
public function run(
    ?Symfony\Component\Console\Input\InputInterface $input = null,
    ?Symfony\Component\Console\Output\OutputInterface $output = null,
): int;
```

- 同じApplicationでは同じConsoleKernelを返す
- ConstructorはPublic APIにしない
- Symfony Application Getter、Command Getter、Container Getter、Config Getterを追加しない
- Public SignatureへInternal型、Connection、Raw Configを露出しない

## Lazy Command Contract

次のCommand名とDescriptionをDatabase、Artifact、PCNTLなしで一覧表示できるよう登録する。

```text
blackops:build:compile
blackops:operation:list
blackops:database:status
blackops:database:migrate
blackops:worker:run
blackops:retention:plan
blackops:retention:purge
blackops:scheduler:run
blackops:scheduler:daemon
```

- 実CommandとRuntime Dependencyは対象Command実行時まで生成しない
- Config不足でCommandを一覧から除外しない
- `list`／`help` はConnection、Artifact Load、PCNTL availabilityを要求しない
- Application CommandはFramework Commandと競合しない場合だけ登録する
- Generatorと低レベル個別Compile Commandは登録しない

## Application-aware Build Contract

`app.build.application_build_id` を空でないStringとして検証する。既存のArtifact Path、Container Class／NamespaceとSnapshotのOperation／Service Providerを使い、必須CLI Path引数なしで3 ArtifactをCompileする。

- Operation／HTTP Manifestは同じApplication Build IDを持つ
- HTTP Runtimeが読むPathへ書き込む
- Source DiscoveryへFallbackしない
- BuildでDatabase接続、Migration、PCNTLを要求しない
- `operation:list` もSnapshot Providerを使い、Source Path必須引数を要求しない

## Database and Worker Contract

- Migration Commandは `database.connection` と `database.schema` だけを遅延検証・構成する
- StatusはRead-only、MigrateだけがDDLを明示実行する
- WorkerはBuild、Database、`execution.worker` を実行時に検証する
- Worker IDは必須非空String
- Lease 60、Heartbeat 10、Grace 20、Handler Failure後継続trueをDefaultとする
- Durationは正のInteger、HeartbeatはLease未満、継続FlagはBoolean
- Main Worker ConnectionをReceiver／Settlement／Lifecycle／Journal／Outcome／Recoveryで共有する
- Heartbeatは同じParameterから作る別Connectionを使う
- 同一Pcntl Signal Heartbeat InstanceをGuardとSignal Runtimeへ渡す
- WorkerはCompile、Migration、DDLを実行しない

## Retention and Scheduler Contract

`config/retention.php` の次を検証する。

```text
transport_payload_days  positive integer
journal_days            positive integer
outcome_days            positive integer
dead_letter_days        positive integer
policy_ref              non-empty string
actor                   non-empty string
```

- Plan／Purge／Scheduler Retention Taskは同じPolicy、Policy Ref、Actorを使う
- Planner、Audit、Tombstone、Outcome／Dead Letter／Journal Deleteを同一Connectionから構成する
- Purgeは明示 `--confirm` の場合だけ変更する
- Schedulerは明示Command実行時だけRetention Taskを実行する
- Kernel構成／一覧表示でRetention処理を行わない

## Constraints

- Production CodeとTestのComment／DocBlockへDecision、Spec、Task、TODOの管理番号を書かない
- Existing Internal Commandの名前をInstalled Applicationへ公開するためだけにPublic化しない
- Lazy FactoryがCredentialをError Messageへ含めない
- `ConsoleKernel` からSymfony Application Instanceを取得できるBackdoorを追加しない
- Missing Configで無関係なCommandを使用不能にしない
- Build、Worker、Kernel構成でMigrationを暗黙実行しない
- Worker Main ConnectionをHeartbeatへ使い回さない
- SchedulerやPurgeをDefault Command実行またはKernel構成で起動しない

## Acceptance Criteria

- [x] Public ConsoleKernel ContractとInstance Cacheが成立する
- [x] Public APIへInternal／Symfony Application／Connection／Raw Configを露出しない
- [x] 9 Framework CommandをDB／Artifact／PCNTLなしで一覧表示できる
- [x] Application Commandを実行でき、Framework Command名競合を拒否する
- [x] Application-aware BuildがSnapshot ProviderとBuild Configから3 Artifactを生成する
- [x] Operation ListがSnapshot Providerを表示する
- [x] Migration Status／Migrateが明示Commandとして動作する
- [x] Workerが別Heartbeat Connectionと同一Signal Instanceを使う
- [x] WorkerがCompile済みArtifactからDeferred Operationを処理する
- [x] Retention Plan／Purge／Schedulerが同一Config Policyを使う
- [x] Kernel／List／Build／WorkerがMigrationやPurgeを暗黙実行しない
- [x] Config／Runtime ErrorへCredentialを露出しない
- [x] Focused／Full Test、Mago、Deptrac、Composer Validationが成功する
- [x] Guide、Internals、Report、Checkpointが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Application tests/Internal/Application tests/Internal/Console tests/Integration/ApplicationConsoleKernelTest.php tests/Architecture/PublicApiArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P7-004-public-console-kernel-composition.md` に次を記録する。

- Summary
- Public API and Lazy Command Evidence
- Application-aware Build Evidence
- Migration and Worker Composition Evidence
- Retention and Scheduler Evidence
- Secret and Process Safety Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

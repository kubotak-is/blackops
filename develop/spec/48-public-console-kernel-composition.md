# Public Console Kernel Composition

## Purpose

`Application::console()` はAccepted Application Configuration SnapshotからFramework標準CommandとApplication独自Commandを登録したPublic `ConsoleKernel` を遅延構成する。Installed ApplicationはInternal Console、Worker、Migration、Retention、Scheduler Classを直接生成しない。

## Public API

`Application` は次を追加する。

```php
public function console(): BlackOps\Application\ConsoleKernel;
```

同じApplication Instanceでは同じConsoleKernel Instanceを返す。`http()` と `console()` は同じAccepted Snapshotを使うが、HTTP HandlerとConsole Dependencyは独立して遅延構成する。

`ConsoleKernel` は `BlackOps\Application` Namespaceの `#[PublicApi]` final classとし、次だけを公開する。

```php
public function run(
    ?Symfony\Component\Console\Input\InputInterface $input = null,
    ?Symfony\Component\Console\Output\OutputInterface $output = null,
): int;
```

Symfony Console Application、Command Loader、Container、Connection、Raw ConfigのGetterは公開しない。Projectの `blackops` は `$application->console()->run()` の終了CodeをProcess終了Codeとして返す。

## Lazy Command Boundary

Framework Commandの名前、Description、AliasesはKernel構成時に登録する。実CommandとRuntime Dependencyは対象Commandの実行時まで生成しない。

次を満たす。

- `list` と `help` はDatabase接続、Artifact Load、PCNTL availabilityを要求しない
- Build／Operation ListはDatabase、Retention、Worker Configを要求しない
- MigrationはBuild ArtifactまたはPCNTLを要求しない
- Workerは実行時だけArtifact、Main Connection、Heartbeat Connection、PCNTLを構成する
- Retention／Schedulerは実行時だけRetention ConfigとConnectionを構成する
- Config不足でCommandを一覧から除外しない
- Factory失敗はCredentialを含まない責務別Bootstrap Errorへ変換する

Application独自CommandはValidated Snapshotから登録する。Framework Command名と競合するApplication CommandはBootstrap Errorとする。同一Application Commandを二重登録しない。

## Framework Command Set

P7-004のPublic Kernelは次を登録する。

```text
build:compile
operation:list
database:status
database:migrate
worker:run
retention:plan
retention:purge
scheduler:run
scheduler:daemon
```

Project Root Entrypointと組み合わせる公式形式は`php blackops build:compile`とする。Stable `1.0.0`の既存Application互換のため、従来の`blackops:*`名は同じCommandを実行するAliasとして維持する。Canonical名とAliasはどちらもFramework予約名であり、Application独自Commandは上書きできない。Generatorの`make:operation`と`make:migration`にはPrefixを付けない。

個別Manifest／Container Compile Commandは内部の低レベルToolingとして維持するが、Installed Applicationの標準Kernelへ登録しない。Generator CommandはPhase 9まで追加しない。

## Application-aware Build

`config/app.php` の `build` SectionはHTTP Runtime用Keyに加えて次を持つ。

```php
return [
    'build' => [
        'application_build_id' => 'my-app',
        'operation_manifest' => dirname(__DIR__) . '/var/build/operations.php',
        'http_manifest' => dirname(__DIR__) . '/var/build/http.php',
        'container' => dirname(__DIR__) . '/var/build/container.php',
        'container_class' => 'CompiledContainer',
        'container_namespace' => 'App\\Generated',
    ],
];
```

`application_build_id` は空でないStringとする。Application-aware Build CommandはSnapshotのOperation Provider／Service ProviderをCompileし、同じBuild IDでOperation ManifestとHTTP Manifestを生成し、指定Class／NamespaceのContainerを指定Pathへ生成する。

Provider Config FileとArtifact Pathの必須CLI引数は持たない。既存のLock、Fingerprint等の任意Build最適化は、Application-aware Contractへ安全に適用できる範囲でOptionとして維持できる。

`operation:list` は同じSnapshotのOperation ProviderをCompileして表示し、Source Discovery用必須CLI引数を要求しない。Production BuildとHTTP起動はSource DiscoveryへFallbackしない。

## Database and Migration

Migration Commandは `config/database.php` のConnection ParameterとSchemaを使用し、Command実行時に一つのDBAL ConnectionとDatabase Migration Runnerを構成する。

`database:status` はSchemaを変更しない。`database:migrate` だけが明示実行によりFramework Migrationを適用する。Kernel構成、Command一覧、HTTP、Build、Worker起動はMigrationまたはDDLを暗黙実行しない。

## Worker Configuration

`config/execution.php` は次を持つ。

```php
return [
    'worker' => [
        'id' => 'report-worker-1',
        'lease_seconds' => 60,
        'heartbeat_seconds' => 10,
        'grace_seconds' => 20,
        'continue_after_handler_failure' => true,
    ],
];
```

`id` は空でないStringで必須とする。その他は省略時に例の値をFramework Defaultとして使う。Durationは正のInteger、HeartbeatはLeaseより短く、継続FlagはBooleanでなければならない。

Worker Command実行時に次を構成する。

- Compile済みOperation ManifestとContainer
- Reflection JSON Operation Codec
- System ClockとUUIDv7 Identifier Factory
- Exponential Backoff Supervision Policy
- PostgreSQL Receiver／Settlement、Lifecycle、Journal、Outcome Store
- Lease Expiry Recovery
- Deferred Worker Runtime／Loop
- PCNTL Signal Heartbeat

Main Worker StorageとReceiverは一つのDBAL Connectionを共有する。Heartbeat Receiverは同じParameterから生成した別Connectionを使う。同じPCNTL Signal Heartbeat InstanceをClaim Execution GuardとWorker Signal Runtimeへ渡す。

Worker CommandはArtifact Compile、Migration、DDLを実行しない。Artifact不足、Build ID不一致、PCNTL不足、Config不正は実行前にFail-fastする。

## Retention Configuration

`config/retention.php` は次を持つ。

```php
return [
    'transport_payload_days' => 30,
    'journal_days' => 90,
    'outcome_days' => 30,
    'dead_letter_days' => 90,
    'policy_ref' => 'default-retention-v1',
    'actor' => 'blackops-maintenance',
];
```

保持日数は正のInteger、Policy RefとActorは空でないStringとする。Environment Variableの対応と型変換はApplication Configが所有する。

Retention Plan／PurgeとScheduler Retention Taskは同じPolicy、Policy Ref、Actorを使う。CLI Optionによる保持日数の明示Overrideを維持する場合も、未指定値はAccepted ConfigのPolicyから取得する。

Retention Runtimeは単一ConnectionからPlanner、Purge Audit、Transport Payload Tombstone、Outcome／Dead Letter／Journal Delete Serviceを構成する。Schedulerは同じPurge ServiceとPolicyを使うRetention Maintenance Taskを登録する。

Kernel構成またはCommand一覧でPurgeを実行しない。Retention Purgeは明示 `--confirm`、Schedulerは明示Command実行時だけ変更を行う。

## Failure and Secret Safety

Command FactoryのConfig、Artifact、Connection Parameter、Runtime Dependency Errorは、問題の責務またはConfig Keyを示す。Password、DSN Credential、Token、Connection ValueをMessageへ含めない。

Symfony ConsoleがExceptionを表示する場合もPrevious ExceptionのCredential値を展開しない。Application Bootstrap Exceptionは安全なMessageだけをPublic Boundaryへ渡す。

## Verification

- `Application::console()` がPublic ConsoleKernelを返し、Instanceを再利用する
- Public SignatureへInternal型、Symfony Application、Container、Connection、Raw Configを露出しない
- `list`／`help` がDatabase、Artifact、PCNTLなしでFramework Commandを表示する
- Custom Commandを実行でき、Framework Command名競合を拒否する
- Application-aware Build／Operation ListがSnapshot ProviderとBuild Configを使う
- Migration Status／Migrateが明示実行だけで動作する
- Workerが別Heartbeat Connection、同一Signal Instance、Compile済みArtifactを使う
- Retention CommandとSchedulerが同じAccepted Policyを使う
- Kernel／Build／Worker起動が暗黙Migrationを行わない
- ErrorへCredentialを露出しない

## Traceability

- Decision: [Public Console Kernel Composition](../decisions/068-public-console-kernel-composition.md)
- Command Names: [Project CLI Command Names](../decisions/092-project-cli-command-names.md)
- Bootstrap API: [Public Application Bootstrap API](44-public-application-bootstrap-api.md)
- HTTP Runtime: [Public HTTP Runtime Configuration](47-public-http-runtime-configuration.md)
- Worker: [Worker Runtime](../../docs/internal/worker-runtime.md)
- Scheduler: [Maintenance Scheduler](../../docs/internal/maintenance-scheduler.md)

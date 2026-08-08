# Deployment

DeploymentはApplication／運用環境の責務です。FrameworkはKubernetes、systemd、Supervisor、TLS、Secret配布、Health Check、Resource Limit、Restart Policyを提供しません。Stable `1.1.0`のReleaseとRepository `main`のExperimental Surfaceを混ぜず、同じBuild IDのArtifactを全Processへ渡します。Tenant／Protected StorageとRotation CLIはRepository `main`のExperimental Surfaceです。

:::warning[Operator responsibility]
Process監督、TLS、Secret配布、Health Check、Resource Limit、Restart Policyは運用環境で構成します。Frameworkはこれらを自動提供しません。
:::

## リリース手順

すべてProject Rootで実行します。Docker構成では`docker compose run --rm app`を各コマンドの前に付けます。

1. **Releaseを固定する。** Git revision、Composer／Node lockfile、PHP Runtime、環境設定を記録します。Runtime起動時のSource DiscoveryやCompileへFallbackさせません。
2. **Databaseを確認する。** `php blackops database:status`でApplied／Pendingを確認し、`php blackops database:migrate --dry-run`でSQLを確認します。
3. **Migrationを明示適用する。** 承認した同じArtifactで`php blackops database:migrate`を実行します。FrameworkはRollback SQLや後方互換性を自動提供しません。
4. **ArtifactをCompileする。** `php blackops build:compile`を一度実行し、Operation／HTTP／Frontend ManifestとDI Containerを同じBuild IDで作ります。
5. **Frontendを検証する。** Frontendを使う場合だけ`php blackops frontend:generate`、`php blackops frontend:check`、Applicationの`pnpm test`を行います。Generated Treeは手編集しません。
6. **Processへ配布する。** HTTP Worker、Deferred Worker、Outbox Relayへ同じArtifact、Database Configuration、Secretを渡します。RelayはDeferred Workerと別Processです。
   Protected RuntimeではApplication Service Providerへ`StorageKeyProvider`、Status／Data Read Authorizer、Console／Scheduled Tenant ProviderをBindingします。Providerが解決したKey MaterialやCredentialをBuild Artifact、Manifest、Log、Journalへコピーしません。TenantをHTTP Header、Console引数、Schedule名から暗黙推測しないでください。
7. **ScheduleとMaintenanceを分離する。** Application Scheduleは外部Supervisorから`operation:schedule:run`を一回ずつ起動し、Deferredだけ別Processの`worker:run`で完了させます。Retentionを使う場合の`scheduler:run`／`scheduler:daemon`はFramework Maintenance専用です。
8. **Smokeする。** HTTPの`200`／`202`、Status／Outcome、Journal／Log、Worker、Outbox Relay、必要なRetention結果を確認します。Credential、Canonical Payload、Throwable、SQLは公開Outputへ出しません。
9. **停止とRollbackを判断する。** Deferred Worker／Relayは実装されたSignal境界で新規Claim／Batchを止め、Maintenance Scheduler DaemonはSupervisorへ停止を委ねます。Application Scheduleの確実な一回評価には外部Schedulerから`operation:schedule:run --json`を起動し、`scheduler:run`はRetention等のMaintenanceだけに使います。Lease Recovery、Database状態、Artifact互換性を確認します。Migrationを戻せない場合はRollbackを宣言せず、Forward Fixまたは復旧手順を選びます。
10. **Protected Storageを確認する。** Fresh DatabaseではMigrationを適用してからBuild／Seedを実行します。旧Plaintext Rowを含む保護対象TableはMigrationが安全に停止するため、Reset／Recreateまたは承認済みOffline変換を選びます。RuntimeのMalformed／Unknown／Tampered EnvelopeはProtection Failureとして扱い、本文を出力しません。
11. **Key Rotationを段階実行する。** Providerで旧Keyと新KeyをRead可能にし、`storage:protection:plan`（Read-only）→`storage:protection:rotate`のDry-run→明示`--confirm`→同じCheckpointでResume→`remaining=0`確認の順に進めます。Replica、Backup、Dead Letter、Retention Windowを別途確認し、旧Keyを先に削除しません。

## プロセス一覧

| Process | Command／入口 | 常駐条件 | 監視するもの | 停止境界 |
| --- | --- | --- | --- | --- |
| HTTP Worker | ApplicationのHTTP Runtime（FrankenPHP等） | HTTP Requestを受ける間 | Status、5xx、DB接続、Request上限 | 新規Requestを止め、処理中Requestの終了を待つ |
| Deferred Worker | `php blackops worker:run`（`--iterations`省略で常駐） | Deferred RowをClaimする間 | Claim、Heartbeat、Retry、Dead Letter、Process Exit | 新規Claimを止め、処理中AttemptをGrace期間で終了／Lease Recovery |
| Outbox Relay | `php blackops outbox:relay:run`または`outbox:relay:daemon` | Pending Outbox Rowを配送する間 | claimed／sent／retried／dead-lettered／stale | Batch境界で停止。Relay完了はchild Handler完了を意味しない |
| Maintenance Scheduler | `php blackops scheduler:run`または`scheduler:daemon` | Retention等のDue Maintenanceを実行する間 | task数、affected数、Purge Audit、Exit | `scheduler:run`は1回で終了。Daemonの停止はSupervisorへ委ね、Application Operationを起動しない |
| Application Schedule | `php blackops operation:schedule:run --json` | 外部Cron／systemd／Kubernetesが一回起動 | evaluated／accepted／misfire／overlap／failed、Occurrence、Journal | Commandは一回で終了。Application Schedule Daemonは提供せず、Maintenance `scheduler:*`と混ぜない |

WorkerとRelayのDatabase／Schemaが異なると`202`後にOutcomeが進みません。Health Checkの復旧は業務QueryやTransactionの自動Retry、Exactly-onceを意味しません。再配送可能な外部副作用にはIdempotency KeyまたはTransactional Outboxを設計します。

## Smoke／Shutdown／Recovery

最低限、次の順で安全な値だけを確認します。

```bash
php blackops database:status
php blackops operation:list
curl -i 'http://127.0.0.1:8080/<application-health-route>'
php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
php blackops outbox:relay:run --until-empty
php blackops operation:inspect <operation-id> --json
```

`<application-health-route>`と`<operation-id>`はApplicationが定義した値へ置き換えます。Frameworkは標準Health Routeを追加しません。HTTP、Deferred、Outbox、Status／Outcome、Journal／Logの各結果を別々に記録し、Relayの`sent`だけでHandler成功と判断しません。

Deferred WorkerとRelay Daemonは実装された`SIGTERM`／`SIGINT`境界で新しいClaim／Batchを止め、Grace Period後に終了します。Maintenance Scheduler DaemonにはFrameworkのGraceful Signal Handlerがないため、停止はSupervisorへ委ねます。Application Scheduleの一回評価と停止境界には外部Schedulerから`operation:schedule:run --json`を起動し、Maintenanceの一回実行だけに`scheduler:run`を使います。HeartbeatまたはLeaseが失効したAttemptは成功扱いにせずRecoveryへ委ねます。Database復旧後は新しいRequest／Attemptで再確認します。

## Rollback判断

- Artifactだけを戻しても適用済みMigrationと互換性がない場合はRollbackしない。
- Pending Migration、Build ID、Frontend Generated Tree、HTTP／Worker／RelayのVersionが一致しない場合はReleaseを停止します。
- 失敗したMigrationのSQL、Credential、Canonical PayloadをSupport Ticketや公開Logへコピーしない。
- RetentionのPurge後はCanonical Recordの復元を前提にせず、Hold／Auditを先に確認します。

Release Surfaceと既知制約は[Releases](mvp-status.md)を、Artifact／Migration失敗は[Troubleshooting](troubleshooting.md)を参照してください。

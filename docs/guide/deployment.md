# Production Worker Operations

Productionでは、Requestを受けるHTTP WorkerとDeferred Operationを処理するWorkerを別Processとして管理します。BlackOpsはKubernetes、systemd、Supervisor等のOrchestrator Integrationを提供しません。Process配置、TLS、Secret配布、Health Check、Resource Limit、Restart Policy、MonitoringはApplicationと運用環境が所有します。

## Deploy前の順序

1. Releaseと同じSourceからDependencyとCompile済み[Build Artifact](application-bootstrap.md)を作ります。
2. [Database Migration](database-migrations.md)のStatusとDry Runを確認し、明示的に適用します。
3. HTTP WorkerとDeferred Workerへ同じBuild ID、Artifact、Database Configurationを渡します。
4. HTTP WorkerのRequest上限とRestart、Deferred WorkerのHeartbeat、Retry、Graceful Shutdownを設定します。
5. Journal、Outcome、Dead Letter、Process Exit、Database接続を監視します。
6. [Data Retention](retention.md)の期間、Hold、Purge Auditを運用Policyとして確定します。

[Local Runtime](runtime-bootstrap.md)のCompose構成はApplicationを手元で動かすReferenceであり、そのままProduction Topologyを規定しません。Production ImageはRuntime起動時にSource Discovery、Artifact Compile、MigrationへFallbackしない構成にしてください。

## Workerの停止と回復

Deferred Workerへ`SIGTERM`または`SIGINT`を送り、新しいClaimを止めてGrace Period内のHandler完了を待ちます。Heartbeat失敗やGrace Timeout時は成功として確定せず、Lease ExpiryとRecoveryへ委ねます。Retry Scheduled、Failed、Dead Letterを同じ成功扱いにせず、[Execution & Workers](execution.md)と[Troubleshooting](troubleshooting.md)のLifecycleを監視へ反映してください。

公開前には[Security](security.md)の責任分界と[Current Status](mvp-status.md)のStable／`main`差、既知制約を確認してください。

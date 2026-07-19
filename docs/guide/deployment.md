# Production Worker Operations

Productionでは、Requestを受けるHTTP WorkerとDeferred Operationを処理するWorkerを別Processとして管理します。BlackOpsはKubernetes、systemd、Supervisor等のOrchestrator Integrationを提供しません。Process配置、TLS、Secret配布、Health Check、Resource Limit、Restart Policy、MonitoringはApplicationと運用環境が所有します。

## Deploy前の順序

1. Releaseと同じSourceからDependencyとCompile済み[Build Artifact](application-bootstrap.md)を作ります。
2. [Database Migration](database-migrations.md)のStatusとDry Runを確認し、明示的に適用します。
3. Frontend利用時は同じBuildから`frontend:generate`と`frontend:check`を実行します。
4. HTTP WorkerとDeferred Workerへ同じBuild ID、Artifact、Database Configurationを渡します。
5. `OperationStatusAuthorizer`をProduction PolicyへBindingします。
6. HTTP WorkerのRequest上限とRestart、Deferred WorkerのHeartbeat、Retry、Graceful Shutdownを設定します。
7. Journal、Outcome、Dead Letter、Process Exit、Database接続を監視します。
8. [Data Retention](retention.md)の期間、Hold、Purge Auditを運用Policyとして確定します。

[Local Runtime](runtime-bootstrap.md)のCompose構成はApplicationを手元で動かすReferenceであり、そのままProduction Topologyを規定しません。Production ImageはRuntime起動時にSource Discovery、Artifact Compile、MigrationへFallbackしない構成にしてください。

QuickstartのSame-origin Status AuthorizerをProduction Tenant Policyとしてそのまま使わず、Authentication、Tenant／Resource認可、TLS、Canonical Store暗号化、Access Control、Retentionを構成します。FrontendとBackendのBuildがずれると`.status()`／`.wait()`は`unexpected_response`で安全に停止します。

## Workerの停止と回復

Deferred Workerへ`SIGTERM`または`SIGINT`を送り、新しいClaimを止めてGrace Period内のHandler完了を待ちます。Heartbeat失敗やGrace Timeout時は成功として確定せず、Lease ExpiryとRecoveryへ委ねます。Retry Scheduled、Failed、Dead Letterを同じ成功扱いにせず、[Execution & Workers](execution.md)と[Troubleshooting](troubleshooting.md)のLifecycleを監視へ反映してください。

`.wait()`のDeadline超過はOperationのCancelではありません。Clientが`poll_timeout`を返してもWorkerは処理を継続できます。Client待機時間とWorker処理SLOを別に監視し、無限WaitでProcess Supervisorの責務を代替しません。

HTTP Request／Deferred Attemptの開始時、BlackOpsは生成済みApplication ConnectionをHealth Checkします。切断を検知すると同じDBAL ObjectをCloseして一度だけ再接続し、復旧しなければそのRequest／Attemptを失敗させます。正常終了時はConnectionを再利用しますが、Transaction Leakまたは処理中のThrowableでは生成済みApplication ConnectionをCloseするため、次回の開始境界で再接続されます。Heartbeat ConnectionはApplication Connectionとは別Lifecycleです。

この回復境界は業務QueryやTransactionのRetryではありません。Database停止中の5xx／Worker Exitを監視し、Database復旧後に新しいRequest／Attemptが成功することをDeploy前に確認してください。Commit結果不明時の自動再実行やExactly-onceをBlackOpsへ期待せず、重複可能性がある処理にはIdempotency KeyやTransactional Outboxを用意します。

公開前には[Security](security.md)の責任分界と[Current Status](mvp-status.md)のStable／`main`差、既知制約を確認してください。

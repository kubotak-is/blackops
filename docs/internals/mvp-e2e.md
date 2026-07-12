# MVP End-to-End Composition

MVP Sample E2EはProduction CodeへSample専用の近道を追加せず、既存のCompile、HTTP、PostgreSQL、Worker、Outcome、Projection境界を組み合わせる。

`tests/Consumer/quickstart-e2e.sh` はChecked-in Quickstartを一時DirectoryへCopyし、`symlink=false` とversion `1.0.0` の一時Composer Path RepositoryからFrameworkをConsumer Vendorへmirror installする。RuntimeはConsumerのAutoloaderだけを使う。

ScriptはBuild、明示Migration、FrankenPHP HTTP、Sensitive JSONL、Deferred Retry／Completion、Encoded Outcome、Retention Plan／Dry Runを機械検証する。Trapは成功／失敗の両方でCompose Project、Container、Named Volume、Local Image、一時Consumerを削除する。

## Build and Load

`CompileBuildArtifactsCommand` がSampleのOperation ProviderとService Providerから次を同じApplication Build IDで生成する。

```text
Operation Provider ─┬─> Operation Manifest
                    ├─> HTTP Manifest + FastRoute Dispatcher Data
Service Provider ───┴─> Compiled Symfony DI Container
```

E2E Runtimeは `ProductionRuntimeArtifactLoader` だけからRegistry、Routes、Containerを取得する。Production実行中にProvider Config、Reflection Discovery、Source Scanへ戻らない。

## HTTP Composition

Inline側は `ProductionRuntimeComposer` がCompile済みArtifactsからDispatcherとRoute Registryを作る。Deferred側は同じRegistry／Route Registryへ既存の `DeferredHttpOperationAcceptor` を明示的に追加する。

```text
GET /welcome
  -> compiled route
  -> InlineDispatcher
  -> compiled container handler
  -> PostgreSQL canonical journal
  -> JSON 200

POST /reports
  -> compiled route
  -> DeferredHttpOperationAcceptor
  -> operation state + received + accepted in one transaction
  -> JSON 202
```

HTTP compositionはVersioned Migrationを呼ばない。E2E setupが `DatabaseMigrationRunner` をDeployment stepとして先に実行する。

## Worker Restart Boundary

HTTP受付後のWorkerは新しいDBAL Connectionと、Artifact Loaderから新しく作ったDI Containerを使う。Handler実行中にDatabase Transactionは保持しない。

Attempt 1はretryable exceptionとなり、State、Sequence、`attempt.failed`、`attempt.retry_scheduled`を既存Supervision境界でcommitする。TestはStateを直接変更しない。

Attempt 2はさらに新しいConnection／Containerからclaimする。成功時はState、`attempt.succeeded`、`operation.completed`、typed Outcomeを同じTransactionでcommitする。

```text
HTTP connection: accept and commit
  |
  +-> Worker connection A: claim -> attempt 1 -> retry scheduled
        process boundary
      Worker connection B: claim -> attempt 2 -> completed + outcome
```

HeartbeatはReference Worker Loopの既存機能であり、この短時間Handlerを直接実行するE2Eでは `DirectClaimExecutionGuard` を使う。Heartbeat／Signalの専用TestはWorker Runtime suiteが担当する。

## Journal and Projection

Canonical JournalはOperation IDごとにInline 4件、Deferred 8件の順序を検証する。

Sensitive検証はInlineの `JournalObservationPipeline`へJSONL Observerを接続して行う。Canonical Received Recordが再現可能な値を持つ一方、Observed JSONLは `#[Sensitive]` Propertyをmaskすることを同じ実行で確認する。

Deferred RuntimeにはSample専用Observer配送を追加しない。Canonical Journalを正本とし、既存のObserver配送／将来のReplay境界を保つ。

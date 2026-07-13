# 用語集（Glossary）

BlackOps固有の実行、追跡、運用用語をまとめます。各用語はApplication Codeから直接触れるPublic Conceptと、Framework／運用が管理するRuntime Conceptを区別して説明します。

## Operation

Applicationが実行したい一つの意図と処理単位です。Typed Self-handled形式では`Operation`を実装するClass自身が`handle()`を持ちます。

## Attempt

Operation Handlerを一回実行する単位です。InlineはRequest内で一回目のAttemptを開始し、DeferredはClaim成功後にAttempt IDと1始まりのAttempt番号を発行します。Retryでは同じOperation IDのまま新しいAttemptになります。

## Claim

Workerが一つのDeferred Operationを処理する権利をTransportから取得することです。Claimには有効期限のあるLeaseと単調増加するFencing Tokenが結び付きます。

## Lease

Deferred OperationのClaimが有効である期限です。WorkerはHandler実行中のHeartbeatでLeaseを延長し、期限切れ後は別WorkerがRecoveryして再Claimできます。

## Fencing Token

Claimごとに増加するTokenです。FrameworkはState、Outcome、Journalの完了更新時に現在のTokenと一致するか検証し、古いWorkerによるStale Writeを拒否します。外部副作用の冪等性はApplicationが別途設計します。

## Heartbeat

WorkerがHandler実行中に定期送信し、ClaimのLeaseを延長するSignalです。Heartbeat失敗後のWorkerはClaimを失ったものとして完了を書き込みません。

## Projection

Canonical Dataから用途に必要なFieldだけを選び、Mask／Exclude／Hash等を適用した表現です。Sensitive ProjectionはObserverやLog Sinkへ秘密値をそのまま渡さないために使います。

## Manifest

Build時にOperation SourceとMetadataから生成するRuntime検索Artifactです。Operation ManifestはTypeとHandler、HTTP ManifestはRouteとOperationを結び付け、Production RuntimeはSource Discoveryを行わずManifestを読みます。

## Dead Letter

Supervision PolicyがRetryを終了し、Deferred Operationを通常Queueから隔離したTerminal状態または隔離Recordです。調査後のReplayは新しいOperation IDで行います。

## Journal

Operation Lifecycleで起きた事実を順序付きで追記するRecord列です。Application Log、Transport Payload、Outcome Storeとは別の責務を持ちます。

## Outcome

Operationが正常完了したときの型付きOutputです。CompletedだけがOutcomeを保存し、Rejected、Failed、Retry Scheduled、Dead Letterには成功Outcomeを作りません。

## Correlation

関連する複数Operationを一つのTraceとしてまとめる関係です。Root OperationではCorrelation IDを初期化し、子Operationは同じCorrelation IDを引き継ぎます。

## Causation

どのOperationが現在のOperationを発生させたかを表す因果関係です。子OperationのCausation IDには親Operation IDと同じUUID値を別の型として保持します。

## Retention

Transport Payload、Journal、Outcome、Dead Letter等を保持する期間と削除手順です。Data種別ごとに期間を設定し、Legal Hold、Tombstone、Purge Auditを考慮して運用します。

# Glossary

BlackOps固有の実行、追跡、運用用語をまとめます。各用語はApplication Codeから直接触れるPublic Conceptと、Framework／運用が管理するRuntime Conceptを区別して説明します。

## Operation

**区分**: Public

Applicationが実行したい一つの意図と処理単位です。Typed Self-handled形式では`Operation`を実装するClass自身が`handle()`を持ちます。

## Attempt

**区分**: Runtime

Operation Handlerを一回実行する単位です。InlineはRequest内で一回目のAttemptを開始し、DeferredはClaim成功後にAttempt IDと1始まりのAttempt番号を発行します。Retryでは同じOperation IDのまま新しいAttemptになります。

## Claim

**区分**: Runtime

Workerが一つのDeferred Operationを処理する権利をTransportから取得することです。Claimには有効期限のあるLeaseと単調増加するFencing Tokenが結び付きます。

## Lease

**区分**: Runtime

Deferred OperationのClaimが有効である期限です。WorkerはHandler実行中のHeartbeatでLeaseを延長し、期限切れ後は別WorkerがRecoveryして再Claimできます。

## Fencing Token

**区分**: Runtime

Claimごとに増加するTokenです。FrameworkはState、Outcome、Journalの完了更新時に現在のTokenと一致するか検証し、古いWorkerによるStale Writeを拒否します。外部副作用の冪等性はApplicationが別途設計します。

## Heartbeat

**区分**: Runtime

WorkerがHandler実行中に定期送信し、ClaimのLeaseを延長するSignalです。Heartbeat失敗後のWorkerはClaimを失ったものとして完了を書き込みません。

## Projection

**区分**: Runtime

Canonical Dataから用途に必要なFieldだけを選び、Mask／Exclude／Hash等を適用した表現です。Sensitive ProjectionはObserverやLog Sinkへ秘密値をそのまま渡さないために使います。

## Manifest

**区分**: Runtime

Build時にOperation SourceとMetadataから生成するRuntime検索Artifactです。Operation ManifestはTypeとHandler、HTTP ManifestはRouteとOperationを結び付け、Production RuntimeはSource Discoveryを行わずManifestを読みます。

## Dead Letter

**区分**: Public

Supervision PolicyがRetryを終了し、Deferred Operationを通常Queueから隔離したTerminal状態または隔離Recordです。調査後のReplayは新しいOperation IDで行います。

## Journal

**区分**: Public

Operation Lifecycleで起きた事実を順序付きで追記するRecord列です。Application Log、Transport Payload、Outcome Storeとは別の責務を持ちます。

## Outcome

**区分**: Public

Operationが正常完了したときの型付きOutputです。DeferredのCompletedだけがOutcome Recordを保存し、Inline completedはHTTP Responseだけへ返します。Rejected、Failed、Retry Scheduled、Dead Letterには成功Outcomeを作りません。

## Idempotency Key

**区分**: Public

Mutationの再送を同じ論理Operationとして識別するCaller提供のPrintable ASCII値です。FrameworkはRaw Keyを保存せず、Version付きSHA-256のOpaque Hashへ変換します。GET／HEAD、Anonymous Actor、Ephemeral Outcomeでは受け付けません。

## Idempotency Record

**区分**: Runtime

認証・認可後のScope、Canonical Value Fingerprint、Operation ID、Claim／Terminal State、期限、再投影に必要な安全なSnapshotを保持するDurable Recordです。Scopeの一意制約でAtomic Claimし、既存RecordのFingerprint比較で同一再送かConflictかを判定します。Retentionで独立した対象として管理します。

## Replay

**区分**: Public

同じIdempotency Keyの再送に対して、元のOperationを再実行せずTerminal Resultまたは受付Acknowledgementを再投影することです。Core Resultの`isReplayed()`とDeferred Acknowledgementの同名メソッドで判定し、HTTP専用のReplayヘッダーはResponderが付与します。

## Correlation

**区分**: Public

関連する複数Operationを一つのTraceとしてまとめる関係です。Root OperationではCorrelation IDを初期化し、子Operationは同じCorrelation IDを引き継ぎます。

## Causation

**区分**: Runtime

どのOperationが現在のOperationを発生させたかを表す因果関係です。子OperationのCausation IDには親Operation IDと同じUUID値を別の型として保持します。

## Retention

**区分**: Public

Transport Payload、Journal、Outcome、Dead Letter、Idempotency Record等を保持する期間と削除手順です。Data種別ごとに期間を設定し、Legal Hold、Tombstone、Purge Auditを考慮して運用します。

## Inline

**区分**: Public

Request内でHandlerを実行し、結果をそのHTTP Responseへ返すExecution Strategyです。Inline OutcomeはOutcome Recordを保存しません。

## Deferred

**区分**: Public

受付とHandler実行を分離し、WorkerがClaimしたAttemptで処理するExecution Strategyです。完了したOutcomeだけをOutcome Storeへ保存します。

## Value

**区分**: Public

Operationへ渡すTyped Inputです。HTTP／ConsoleのWire値を宣言的Validation後にApplicationのValueへ変換します。

## Execution Strategy

**区分**: Public

OperationをInlineまたはDeferredで実行する方式です。Stable `1.1.0`のDeferred指定は`#[ExecuteWith(Deferred::class)]`、Stable `1.2.0`のCanonical Authoringは`#[Deferred]`です。

## Terminal State

**区分**: Public

以後Lifecycle EventもHandler実行も発生しない最終状態です。Completed、Rejected、Failed、Dead Letterの4つがあります。

## Canonical Journal

**区分**: Runtime

Operation Lifecycleの事実を順序付きで保存する正本です。汎用Business／Security Audit Trailではなく、Observed JournalやApplication Logとは別にRetentionとAccess Controlを適用します。

## Observed Journal

**区分**: Public

Canonical Journalから安全なFieldだけをProjectionしてJSONL等へ配送する観測用表現です。Sensitive値はMask／Omitされます。

## Ephemeral Outcome

**区分**: Public

Credentialなどを現在のHTTP Responseへ一度だけ返すOutcomeです。HTTP Routeを持ち、Execution Strategy Attributeを省略するとInlineへ解決され、Outcome Store／Status／Deferredへ保存しません。

## Scheduled Application Operation

公開済みExperimental Stable `1.2.0`の入口です。`#[ScheduledBy]`でCalendar Scheduleを宣言し、`operation:schedule:run`を一回実行してInline完了またはDeferred受理へ進めます。Framework Maintenance Schedulerとは別Capabilityです。

## Schedule Context

Scheduled Rootの`ExecutionContext::schedule()`から読むRead-only値です。Schedule名、UTCへ正規化されたCalendar定刻、設定Timezoneだけを持ちます。HTTP、通常のConsoleCommand、child dispatchでは`null`です。

## Occurrence

PostgreSQLへ保存するScheduleの一回分のCalendar Slotです。`(schedule_name, scheduled_at UTC)`が一意で、実行候補だけがOperation IDを持ちます。Misfire／Overlap SkipにはOperation IDがありません。

## Misfire／Overlap

Cursorより古い未処理Slotを`skipped_misfire`、直前のOccurrenceが非Terminalで新しいSlotを受理できない場合を`skipped_overlap`として記録します。初期ContractはFireOnce／Overlap禁止です。

## 次に関係するモデルを読む

用語をOperationの構造へ戻す場合は、[Core Concepts](core-concepts.md)の関係図と定義を参照します。

# D014: LoggingとTraceability

Status: Decided

## Context

本FWは、Operationを軸とした追跡可能性を強みとする。

ユーザーがFW LoggerでApplication Logを出力した場合、Operation ID等を毎回明示しなくても、自動的に現在のOperation Contextを付与する。またFW自身がOperation lifecycleに対応するJournal Logを自動生成する。

## Question 1: Loggerの公開Contract

FW LoggerとPSR-3をどう関係付けるか。

### Options

- A: FW LoggerがPSR-3 `LoggerInterface`を実装し、Context自動付与機能を追加する
- B: 完全な独自Logger Contractを作り、PSR-3とはAdapterだけで接続する
- C: PSR-3 Loggerをそのまま使い、利用者がOperation IDを毎回渡す

### Recommendation

Aを推奨する。

```php
interface JournalLogger extends Psr\Log\LoggerInterface
{
}
```

Handlerへ `LoggerInterface` または `JournalLogger` をConstructor Injectionできる。FWのDecoratorが現在のExecution Scopeから追跡Contextを自動追加し、実際の出力はMonolog等のPSR-3実装へ委ねる。

[ANSWER]

A

[/ANSWER]

## Question 2: 自動付与するContext

Operation内のApplication Logへ、既定で何を付与するか。

### Options

- A: Operation IDだけを付与する
- B: 追跡に必要な標準Context一式を構造化フィールドとして付与する
- C: Operationごとに付与項目を必須設定する

### Recommendation

Bを推奨する。

既定フィールド：

```text
operation.id
operation.type
attempt.id
correlation.id
causation.id
execution.strategy
actor.origin.id
actor.execution.id
actor.authorization.id
```

存在しないOptional値は省略する。Actor属性やOperationValue本体は、漏えいとログ肥大化を避けるため自動付与しない。

[ANSWER]

B

[/ANSWER]

## Question 3: Application LogとJournal Log

ユーザーが出すApplication Logと、FWが出すLifecycle Journal Logをどう扱うか。

### Options

- A: 同じRecord種別として区別しない
- B: 共通Envelopeを使いつつ、`application` と `journal` のRecord Kindで区別する
- C: 完全に異なるSchemaと出力経路にする

### Recommendation

Bを推奨する。

```json
{
  "schemaVersion": 1,
  "kind": "application",
  "level": "info",
  "message": "Order persisted",
  "operation": {
    "id": "019...",
    "type": "order.create"
  }
}
```

```json
{
  "schemaVersion": 1,
  "kind": "journal",
  "event": "OperationCompleted",
  "operation": {
    "id": "019...",
    "type": "order.create"
  }
}
```

共通の検索Fieldを持ちながら、監査上必須のLifecycle Eventと任意のApplication Logを区別できる。

[ANSWER]

B

[/ANSWER]

## Question 4: Lifecycle Journal Logの自動生成

FWがどのLifecycle Eventを自動記録するか。

### Options

- A: OperationReceivedとCompletedだけを自動記録する
- B: OperationとAttemptの全標準Lifecycle Eventを自動記録する
- C: 自動記録せず、ユーザーがHandlerから出力する

### Recommendation

Bを推奨する。

対象：

- OperationReceived
- OperationAccepted
- AttemptStarted
- AttemptSucceeded
- AttemptFailed
- OperationCompleted
- OperationRejected
- OperationFailed
- OperationDeadLettered

ユーザーコードが同じLifecycle Logを手動出力する必要はない。

[ANSWER]

B

[/ANSWER]

## Question 5: Execution Scope

Loggerが現在のOperation Contextをどう取得するか。

### Options

- A: Global static変数へ現在のContextを保存する
- B: FWが実行境界でExecution Scopeを開始・終了し、LoggerがScope Providerから読み取る
- C: Logger呼び出しごとにOperation Envelopeを引数で渡す

### Recommendation

Bを推奨する。

```text
Operation Runtime
  -> Scope開始
  -> Handler / Middleware / Service
     -> LoggerがScope Contextを自動参照
  -> finallyでScope終了
```

ScopeはStackとして管理し、子Operationやネスト実行から戻った時に親Contextを復元する。PHP-FPM Request間、長期WorkerのOperation間、Fiber並行実行間でContextが混線しない実装を要求する。

[ANSWER]

B

[/ANSWER]

## Question 6: ユーザーContextとの衝突

ユーザーがLogger Contextへ `operation.id` などの予約Fieldを渡した場合どうするか。

### Options

- A: ユーザー値で自動Contextを上書きできる
- B: FW予約Fieldは上書き禁止とし、ユーザーContextは別namespaceへ格納する
- C: 衝突時に該当Fieldだけ削除する

### Recommendation

Bを推奨する。

```json
{
  "operation": {
    "id": "FWが保証する値"
  },
  "context": {
    "orderId": "ユーザー指定値"
  }
}
```

追跡IDの偽装や検索破壊を防ぐ。開発環境では予約Field指定を例外または警告として検出する。

[ANSWER]

B

[/ANSWER]

## Question 7: Sensitive Filtering

Loggerへ渡されたContextとJournal Recordへ、どの段階でSensitive Filterを適用するか。

### Options

- A: 各出力Adapterへ任せる
- B: FW Loggerの共通Pipelineで必ずFilterし、Adapterでも追加Filterできる
- C: ユーザー責任とする

### Recommendation

Bを推奨する。

`#[Sensitive]` Metadata、予約Key、登録済みRedactorを使い、Adapterへ渡す前に共通Filterを適用する。Adapter固有の追加マスクも許可する。

[ANSWER]

B

[/ANSWER]

## Question 8: Operation外のログ

Boot、Manifest Compile、Worker待機中など、Operation外でLoggerを使った場合どうするか。

### Options

- A: Operation IDなしのSystem Logとして出力する
- B: Logger利用を禁止する
- C: ダミーOperation IDを発行する

### Recommendation

Aを推奨する。

```text
kind: application
runtime.component: manifest-compiler
operation: 省略
```

Operationに属さないFW処理を、偽のOperationへ結び付けない。

[ANSWER]

A

[/ANSWER]

## Question 9: OpenTelemetryとの対応

Operation IDsとOTel Trace／Spanをどう関係付けるか。

### Options

- A: Correlation IDをOTel Trace IDとしてそのまま使用する
- B: OTel固有のTrace ID／Span IDは別に持ち、Operation IDとの対応をLog Fieldへ記録する
- C: OTel連携を行わない

### Recommendation

Bを推奨する。

UUIDv7のOperation IDとOTel Trace ID／Span IDは形式や生成規則が異なるため、同一値へ無理に統一しない。ExecutionContextへOTel Contextを伝播し、Logに両方を記録して関連付ける。

[ANSWER]

B

[/ANSWER]

## Decision

[DECISION]

1. FW LoggerはPSR-3 `LoggerInterface` を実装する。
2. FW Loggerは現在のExecution ScopeからOperation Contextを自動付与し、実際の出力をMonolog等のPSR-3 Loggerへ委譲できるDecoratorとして設計する。
3. HandlerやServiceはFW LoggerまたはPSR-3 `LoggerInterface` をConstructor Injectionできる。
4. Operation内のApplication Logには、Operation ID、Operation Type ID、Attempt ID、Correlation ID、Causation ID、Execution Strategyを自動付与する。
5. originActor、executionActor、authorizationActorのIDは存在する場合だけ自動付与する。
6. Actor属性とOperationValue本体は、漏えいとログ肥大化を避けるため自動付与しない。
7. Application LogとLifecycle Journal Logは共通の構造化Envelopeを使い、Record Kindを `application` と `journal` に分ける。
8. FWはすべての標準Operation／Attempt Lifecycle EventをJournal Logとして自動生成する。
9. 自動生成対象はOperationReceived、OperationAccepted、AttemptStarted、AttemptSucceeded、AttemptFailed、OperationCompleted、OperationRejected、OperationFailed、OperationDeadLetteredとする。
10. FW RuntimeはOperation実行境界でExecution Scopeを開始し、`finally` で必ず終了する。
11. Execution ScopeはStackとして管理し、ネスト実行後に親Contextを復元する。
12. Execution ScopeはPHP-FPM Request間、長期WorkerのOperation間、Fiber並行実行間でContextを混線させない。
13. FW予約FieldはユーザーContextから上書きできない。
14. ユーザーContextは予約Fieldとは別の `context` namespaceへ格納する。
15. Logger ContextとJournal Recordは、Adapterへ渡す前にFW共通PipelineでSensitive Filterを必ず通す。
16. 各Adapterは出力先固有の追加Filterを適用できる。
17. Operation外で出力されたLogは、Operation IDを持たないSystem／Application Logとして記録する。
18. OTel Trace ID／Span IDはOperation ID／Correlation IDと別に保持する。
19. ExecutionContextへOTel Contextを伝播し、構造化Logへ両方のIDを記録して関連付ける。

[/DECISION]

## Consequences

[CONSEQUENCES]

- ユーザーは各Log呼び出しでOperation ID等を手動指定せず、一貫した追跡情報を得られる。
- Operation lifecycleの記録漏れがユーザー実装に依存しなくなる。
- Application LogとJournal Logを同じOperation IDで横断検索しつつ、Record Kindで用途を区別できる。
- PSR-3互換によりMonolog等の既存Loggerと出力先を再利用できる。
- Execution Scope Provider、Fiber-safeなScope Storage、Context Enricher、Sensitive Filter、Record Formatterを実装する必要がある。
- FW予約Field一覧と構造化Log Schema Versionを公開仕様として管理する必要がある。
- Lifecycle Journal LogのLevel、Sampling禁止対象、保持期間を決める必要がある。
- Logger／Journal Observer障害時にOperationを継続するか失敗させるかを決める必要がある。
- OpenTelemetry Context PropagatorとLog／Trace相関Adapterを設計する必要がある。

[/CONSEQUENCES]

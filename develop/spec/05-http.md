# HTTP Adapter

## Route

HTTP RouteはOperation Definitionの `#[Route(method, path)]` で宣言する。

Routeを持たないOperationはHTTPへ公開しない。初期設計では一つのOperationにつき一つのRouteだけを許可する。

```php
#[Route(method: 'POST', path: '/orders')]
final class CreateOrder implements Operation
{
}
```

HTTP公開とExecution Strategyは独立させる。

- Routeなし＋Deferred：内部専用の非同期Operation
- Routeあり＋Inline：通常の同期API
- Routeあり＋Deferred：配送後にAcknowledgementを返す非同期API

## HTTP Binding

Path、Query、Header、BodyからOperationValueへのBindingは、Valueプロパティの入力元Attributeで明示する。

壊れたJSONまたはJSON Object以外のBodyはOperation受理前のProtocol ErrorとしてHTTP 400を返し、Operation IDとLifecycle Journalを作らない。

Route特定後の必須Field欠落、型不一致、Binding成功後のValue Validation FailureはHTTP 422を返す。FrameworkはOperation IDを発行し、Handlerを実行せず`OperationRejected`をJournalへ記録する。ResponseはCategory `validation`、Code `validation.failed`、Raw Valueを含まないField Violationを返す。

```php
final readonly class UpdateOrderValue implements OperationValue
{
    public function __construct(
        #[FromPath('id')]
        public string $orderId,
        #[FromBody]
        public string $status,
        #[FromHeader('If-Match')]
        public ?string $version,
    ) {
    }
}
```

単純なJSON Bodyでは、入力元Attributeがないプロパティを同名キーからBindingできる。

## HTTPメソッド

GETとHEADはInline Strategyに限定し、Request Bodyを禁止する。

GETとHEAD以外ではExecution StrategyをHTTPメソッドによって制限しない。HTTPメソッドからCommand/Query型を自動分類しない。

## Responder

Completed OutcomeからHTTPレスポンスへの変換はWeb AdapterのResponderが行う。

既定動作：

- Inline＋Outcomeあり：JSON、HTTP 200
- Inline＋値なしCompleted：HTTP 204
- Deferred配送成功：HTTP 202とOperation IDを含むAcknowledgement
- 状態確認URLが構成されている場合：Location Headerを追加可能

HTTP 201、独自Header、ファイル出力などは専用Responderで上書きする。Rejection ReasonとFailure ReasonもWeb Adapterが具体的な4xx／5xxへ変換する。

## Operation Manifest

Routeを含むOperation MetadataをCLIでPHP配列のManifestへコンパイルする。

対象：

- Operation Type ID
- Route
- Operation Definition
- OperationValue
- Handler
- Outcome
- Execution Strategy
- Supervision Policy
- Responder

運用：

- 開発環境ではAttributeの動的探索を許可する
- 本番環境では生成済みManifestを読み込み、毎リクエストの全クラス探索とReflectionを行わない
- PHP配列ファイルとして出力し、OPcacheを利用する
- CIでManifestを生成し、重複Route、重複Type ID、存在しないクラス、型不整合を検出する

動的Path Parameterのマッチング、Route優先順位、正規化規則は未決定である。

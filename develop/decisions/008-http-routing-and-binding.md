# D008: HTTP RoutingとBinding

Status: Decided

## Context

Operation Definitionは `#[Route]` を持つ場合だけHTTPへ公開される。入力はOperationValueへBindingされ、Handlerの結果はResponderによってHTTPレスポンスへ変換される。

この設計対話では、Routeの宣言形式、HTTP入力とOperationValueの対応、Inline／Deferredの応答規則を決める。

## Question 1: Route Attribute

HTTP Routeをどのように宣言するか。

### Options

- A: Operation DefinitionへHTTPメソッドとPathを持つ `#[Route]` Attributeを付与する
- B: ConfigファイルだけでRouteを定義する
- C: Operation Definitionのstatic methodでRouteを返す

### Recommendation

Aを推奨する。

```php
#[Route(method: 'POST', path: '/orders')]
final class CreateOrder implements Operation
{
}
```

Operationの型契約とHTTP入口を近くに置ける。RouteなしOperationは内部専用になる。

[ANSWER]

A

[/ANSWER]

## Question 2: 一つのOperationに複数Route

一つのOperation Definitionを、複数のHTTPメソッドやPathから呼び出せるようにするか。

### Options

- A: `#[Route]` をrepeatableにして複数Routeを許可する
- B: 一つのOperationにつき一つのRouteだけを許可する
- C: 複数Routeが必要な場合は別のEndpointクラスを作る

### Recommendation

Bを推奨する。

一つのOperationに複数の外部表現を持たせると、Binding、認可、Responderが複雑になりやすい。最初は一対一に限定し、必要性が具体化してから拡張する。

[ANSWER]

B

[/ANSWER]

## Question 3: HTTP入力のBinding

Path、Query、Header、Bodyの値をOperationValueへどう対応付けるか。

### Options

- A: OperationValueのプロパティへ入力元Attributeを付与する
- B: 名前が一致する値をFWが自動的に統合する
- C: Operationごとに専用Binderを必須実装する

### Recommendation

Aを推奨する。

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

入力元が明示され、同名キーの衝突や意図しない上書きを避けられる。単純なJSON Bodyでは、Attribute省略時にプロパティ名でBindingする規則を併用できる。

[ANSWER]

A

[/ANSWER]

## Question 4: HTTPメソッドとOperationの制約

FWがHTTPメソッドに応じた制約を設けるか。

### Options

- A: GETとHEADはInline限定かつBodyなしとし、その他はStrategyを制限しない
- B: HTTPメソッドによる制約を設けず、すべてユーザーへ任せる
- C: GET/HEADをQuery、POST/PUT/PATCH/DELETEをCommandとして再分類する

### Recommendation

Aを推奨する。

GETとHEADは安全な参照として扱い、DeferredやBodyを禁止する。一方、Command/Queryという型分類は復活させず、その他のメソッドではOperationのExecution Strategyを自由に選択できる。

[ANSWER]

A

[/ANSWER]

## Question 5: Deferred OperationのHTTP応答

Routeを持つDeferred Operationが配送に成功した場合、既定で何を返すか。

### Options

- A: HTTP 202とOperation IDを含むAcknowledgementを返す
- B: HTTP 200と空Bodyを返す
- C: Operationごとに必ず専用Responderを実装させる

### Recommendation

Aを推奨する。

```json
{
  "operationId": "019...",
  "status": "accepted"
}
```

状態確認APIを提供する場合はLocation Headerも追加できる。専用Responderによる上書きも許可する。

[ANSWER]

A

[/ANSWER]

## Question 6: Inline Operationの既定Responder

専用Responderが定義されていない場合、Completed Outcomeをどう返すか。

### Options

- A: OutcomeをJSONへ変換してHTTP 200、値なしCompletedは204を返す
- B: 専用Responderを必須にする
- C: 常にHTTP 200と空Bodyを返す

### Recommendation

Aを推奨する。

一般的なケースでは追加実装なしで動かせる。HTTP 201、独自Header、ファイルレスポンスなどが必要なOperationだけ専用Responderを使用する。

[ANSWER]

A

[/ANSWER]

## Follow-up 1: RouteとOperation Metadataのコンパイル

Operation DefinitionのAttributeを毎リクエスト探索・Reflectionすると、特にPHP-FPM環境ではOperation数に応じた不要な起動コストが発生する。

そこでRouteだけでなく、Operationに関するMetadata全体をCLIで一つのManifestへコンパイルする。

対象例：

- Operation Type ID
- RouteのHTTP MethodとPath
- Operation Definitionクラス
- OperationValueクラス
- Handlerクラス
- Outcomeクラス
- Execution Strategy
- Supervision Policy
- Responder

```text
Operation Definition群
    -> CLI: operation:compile
    -> var/cache/operations.php
    -> PHP-FPMは配列をrequire
    -> OPcacheへ格納
```

出力はPHPの配列ファイルとし、実行時にunserializeや全クラスのReflectionを必要としない形式にする。

```php
<?php

return [
    'routes' => [
        'POST' => [
            '/orders' => 'order.create',
        ],
    ],
    'operations' => [
        'order.create' => [
            'definition' => CreateOrder::class,
            'value' => CreateOrderValue::class,
            'handler' => CreateOrderHandler::class,
            'outcome' => OrderCreated::class,
            'strategy' => Inline::class,
        ],
    ],
];
```

### Question

Attribute探索とManifestをどのように運用するか。

### Options

- A: 開発環境は動的探索を許可し、本番環境はCLIで生成したOperation Manifestを使用する
- B: すべての環境でManifest生成を必須にする
- C: Manifestは作らず、実行時Reflectionとプロセス内キャッシュだけを使用する

### Recommendation

Aを推奨する。

開発中はAttributeを変更するたびに手動コンパイルせずに済み、本番ではファイル読込と配列検索だけでRouteおよびHandlerを解決できる。CIではManifest生成を実行し、重複Route、重複Type ID、存在しないクラス、型の不整合も検出する。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. HTTP RouteはOperation Definitionの `#[Route(method, path)]` Attributeで宣言する。
2. `#[Route]` を持たないOperationはHTTPへ公開しない。
3. 初期設計では一つのOperation Definitionにつき一つのRouteだけを許可する。
4. Path、Query、Header、BodyからOperationValueへのBindingは、OperationValueプロパティの入力元Attributeで明示する。
5. 単純なJSON Bodyでは、入力元Attributeがないプロパティを同名キーからBindingできる。
6. GETとHEADはInline Strategyに限定し、Request Bodyを禁止する。
7. GETとHEAD以外のHTTPメソッドでは、Execution StrategyをHTTPメソッドによって制限しない。
8. HTTPメソッドからCommand/Query型を自動分類しない。
9. Routeを持つDeferred Operationの配送成功時は、既定でHTTP 202とOperation IDを含むAcknowledgementを返す。
10. 状態確認URLが構成されている場合、AcknowledgementへLocation Headerを追加できる。
11. Inline Operationに専用Responderがない場合、OutcomeをJSONへ変換してHTTP 200を返す。
12. 値なしCompletedで専用Responderがない場合、HTTP 204を返す。
13. HTTP 201、独自Header、ファイル出力などが必要な場合は専用Responderで既定変換を上書きする。
14. Routeを含むOperation Metadata全体を、CLIでPHP配列のOperation Manifestへコンパイルできるようにする。
15. 開発環境ではAttributeの動的探索を許可する。
16. 本番環境では生成済みOperation Manifestを使用し、毎リクエストの全クラス探索とReflectionを行わない。
17. CIでOperation Manifestを生成し、重複Route、重複Type ID、存在しないクラス、Attributeと型の不整合を検出する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Operation DefinitionがHTTP入口の宣言元になり、RouteなしOperationを内部専用として扱える。
- OperationValueの各値がどのHTTP入力元から来たか明示でき、同名キーの衝突を避けられる。
- GET/HEADの安全な参照という意味を維持しつつ、Command/Query型を導入せずに済む。
- Deferred HTTP APIはHandler完了を待たず、Operation IDを返して追跡可能にできる。
- 一般的なJSON APIは専用Responderなしで実装できる。
- 本番のRouteおよびHandler解決は、Manifestのファイル読込と配列検索を中心に実行でき、OPcacheを利用できる。
- Manifest Compiler、開発用Scanner、Manifest Loaderを実装する必要がある。
- 動的Path Parameterのマッチング方式、Route優先順位、末尾スラッシュ等の正規化規則を別途決める必要がある。
- Manifestの生成忘れや古いManifestを検知するデプロイ・CI手順が必要になる。

[/CONSEQUENCES]

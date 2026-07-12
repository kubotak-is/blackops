# D001: Operationの定義

Status: Decided

## Context

Operationは、本フレームワークにおける実行と追跡の中心概念である。

現在は、Operationを実行される処理単位、JournalをOperationのライフサイクルを記録するログとして扱う方向で検討している。この判断は、型設計、Journalのスキーマ、Immediate/Durable実行、冪等性、HTTP境界へ影響する。

## Question 1: Operationが表す範囲

Operationは何を表すか。

### Options

- A: アプリケーションに対する要求そのもの
- B: 要求の受付から最終結果の確定まで続く、論理的な処理単位
- C: Handlerによる一回の実行試行

### Recommendation

Bを推奨する。

一回の実行試行はAttemptとして分離する。これにより、Durable処理が再試行されてもOperation IDを維持でき、Journal上で要求から最終結果までを一貫して追跡できる。

[ANSWER]

B

[/ANSWER]

## Question 2: CommandとQuery

状態を変更するCommandと、状態を参照するQueryを、ともにOperationとして扱うか。

### Options

- A: CommandとQueryの両方をOperationとして扱う
- B: CommandだけをOperationとし、Queryは別の仕組みにする
- C: 共通の上位概念を設け、その下でCommandとQueryを分ける

### Recommendation

Aを推奨する。

参照処理も障害調査、監査、性能観測の対象になるため、共通の追跡モデルを利用する価値がある。ただし、Queryは原則Immediateとするなど、種類ごとの制約を設けられるようにする。

[ANSWER]

A
Operationのクラスのアトリビュートでなにか判断できるものをつけたい。例えばHTTPメソッドで判断しても良いかも。
GET/HEAD=Query
POST/PUT/DELET=Command
また、エンドポイントのパスもアトリビュートで管理できたら良さそう。これでルーティングを兼ねる。

[/ANSWER]

## Question 3: Operationとメタデータ

業務上の入力と、Operation IDやTrace IDなどの実行メタデータをどのように構成するか。

### Options

- A: Operationが業務入力と実行メタデータをすべて保持する
- B: Operationは業務入力だけを表し、メタデータはOperation Envelopeへ分離する
- C: Operationに必須メタデータだけを持たせ、観測用Contextのみ分離する

### Recommendation

Bを推奨する。

利用者は業務上のOperationだけを生成し、DispatcherがEnvelopeを自動生成する。これにより、業務コードへ配送・観測上の関心事を混ぜずに済む。

```php
$outcome = $dispatcher->dispatch(
    new CreateOrder($customerId, $items),
);
```

[ANSWER]

質問が理解できていない、メタデータは何を指してる？UUID等はユーザーが意識しない形で管理されるようにしたい。

[/ANSWER]

## Question 4: 識別子

Operation IDと、呼び出し元が指定する重複防止用の値を分けるか。

### Options

- A: Operation IDだけを使用し、外部からも指定可能にする
- B: Operation IDはFWが生成し、Idempotency Keyを別に持つ
- C: Operation IDはFWが生成し、重複防止はアプリケーションへ任せる

### Recommendation

Bを推奨する。

Operation IDはフレームワーク内部で一意な追跡識別子としてUUIDv7を発行する。外部の再送制御には、任意のIdempotency Keyを別途使用する。

[ANSWER]

B

[/ANSWER]

## Question 5: Operationの連鎖

Handlerが別のOperationを発行できることを、初期設計に含めるか。

### Options

- A: 初期設計からOperationの連鎖を正式に扱う
- B: 発行は許可するが、因果関係の高度な制御は後回しにする
- C: 最初はOperationから別のOperationを発行できないようにする

### Recommendation

Bを推奨する。

Operation IDに加えてCorrelation IDとCausation IDをEnvelopeへ持たせ、追跡可能性だけは初期設計に含める。Sagaや連鎖全体のトランザクション制御は後から検討する。

[ANSWER]

B

[/ANSWER]

## Follow-up 1: メタデータの分離

Question 3でいうメタデータとは、業務上の入力ではないが、FWがOperationを実行・追跡するために使う値を指す。

例：

- Operation ID（UUIDv7）
- Operationの作成日時
- Immediate/Durableなどの実行方式
- Trace ID
- Correlation ID
- Causation ID
- Idempotency Key
- 認証済みユーザーの識別情報

たとえば、注文作成の業務入力は次の部分である。

```php
final readonly class CreateOrder implements Operation
{
    public function __construct(
        public CustomerId $customerId,
        public array $items,
    ) {
    }
}
```

FWは、このOperationを内部的に次のような実行単位で包む。

```php
final readonly class OperationEnvelope
{
    public function __construct(
        public OperationId $id,
        public DateTimeImmutable $createdAt,
        public Operation $operation,
        public OperationContext $context,
        public DispatchMode $dispatchMode,
    ) {
    }
}
```

利用者が作成するのは `CreateOrder` だけであり、UUIDなどを含むEnvelopeはFWが自動生成する。通常のアプリケーションコードではEnvelopeを意識しない。

### Question

この分離を採用するか。

### Options

- A: 採用する。利用者はOperationだけを作り、FWがEnvelopeを生成する
- B: 分離せず、FWがOperation本体へメタデータを設定する
- C: Envelopeという公開概念は作らず、同等の情報をFW内部だけで管理する

### Recommendation

Aを推奨する。

FW内部では明確な型として扱える一方、通常の利用者には隠蔽できる。WorkerやJournal Storeなど、メタデータが必要な拡張ポイントだけEnvelopeを扱う。

[ANSWER]

A
DispatchModeはユーザーが定義したほうがいいかもしれない。アノテーションとかで。デフォルトはSync（即時）だけど場合によっては遅延していいかも。
別途検討でもいいけど、例えばお気に入り追加みたいな何度も押される処理はとりあえず成功を返してDeferで一定間隔の時間をまとめて（UpdateModelみたいな）保存させるみたいな仕組みあったらうれしい（これは今決めなくても良い）

[/ANSWER]

## Follow-up 2: Command/QueryとHTTPルーティング

回答により、次の二つの要求が確認できた。

1. OperationをCommandまたはQueryとして分類したい
2. OperationクラスのAttributeでHTTPメソッドとパスを定義し、ルーティングを兼ねたい

HTTPメソッドからCommand/Queryを自動判定する場合、`GET` と `HEAD` は安全な参照としてQueryにできる。一方、`POST` は必ずしもCommandとは限らず、複雑な検索条件を送信するQueryにも使われる。また、HTTP以外から発行されるOperationにはHTTPメソッドが存在しない。

そのため、Command/Queryという意味とHTTPルートを別々に宣言する案を提案する。

```php
#[Query]
#[Route(method: 'GET', path: '/orders/{id}')]
final readonly class GetOrder implements Operation
{
}

#[Command]
#[Route(method: 'POST', path: '/orders')]
final readonly class CreateOrder implements Operation
{
}
```

または、Attributeを一つにまとめる。

```php
#[OperationRoute(
    kind: OperationKind::Command,
    method: 'POST',
    path: '/orders',
)]
final readonly class CreateOrder implements Operation
{
}
```

### Question

分類とルーティングをどのように定義するか。

### Options

- A: Command/Queryは明示し、HTTP Routeとは別のAttributeで定義する
- B: 一つのAttributeにCommand/Query、HTTPメソッド、パスをまとめる
- C: HTTPメソッドからCommand/Queryを自動判定する
- D: Command/QueryはPHPのinterfaceで表し、HTTP RouteだけをAttributeにする

### Recommendation

Dを推奨する。

```php
interface Operation {}
interface Command extends Operation {}
interface Query extends Operation {}

#[Route(method: 'POST', path: '/orders')]
final readonly class CreateOrder implements Command
{
}
```

Command/QueryはOperationの本質的な意味なので型として表し、HTTPルートはWebアダプタ固有の情報としてAttributeで付加する。これにより、静的解析が可能で、HTTP以外の入口でも分類を維持できる。

[ANSWER]

そもそもCommandとQueryで分けたらその先の処理がどう変わるかまだイメージできてなかった。

[/ANSWER]

## Follow-up 3: Command/Query分類の用途

CommandとQueryを分ける場合、FWは種類ごとに異なる制約や既定動作を提供できる。

### Queryに適用できる規則

- 原則として業務状態を変更しない
- 既定のDispatch ModeをImmediateにする
- Query Cacheを適用できる
- 読み取り専用DBへ送れる
- Query用のタイムアウトや観測指標を設定できる

### Commandに適用できる規則

- ImmediateとDurableの両方を許可する
- Idempotency Keyを利用できる
- リトライやデッドレターを適用できる
- 業務状態を変更するものとして監査できる
- Query Cacheを無効化する契機にできる

ただし、最初からこれらを実装しないのであれば、Command/Query分類は現時点では名前以上の意味を持たない。また、FWが「副作用がない」ことを完全に検証することはできず、最終的にはHandler実装者との契約になる。

### Question

初期設計でCommand/Queryを区別するか。

### Options

- A: 区別する。上記の制約や拡張ポイントを将来提供する前提でmarker interfaceを設ける
- B: 現時点では区別せず、すべてOperationとして扱う。必要性が具体化してから導入する
- C: HTTP Route上では分類するが、Operationの型としては区別しない

### Recommendation

Bを推奨する。

現段階では、Operation、Journal、実行保証の定義がより重要である。Command/Queryによって実際の処理を変えたい要件が明確になった時点で、後方互換性を考慮して導入する。

[ANSWER]

B

読み取り専用DBかどうかはもっと下層で定義したほうがいいかも。Infrastructure層とか。
リトライやデットレターはいいかもしれないが、それもDispatchModeをアノテーションでDefer等にしたときに設定可能にしたら良さそう

[/ANSWER]

## Decision

[DECISION]

1. Operationは、要求の受付から最終結果の確定まで続く論理的な処理単位とする。
2. Handlerによる個々の実行試行はOperationとは分け、Attemptとして扱う。
3. 利用者が定義するOperationは業務入力だけを保持する。
4. Operation ID、作成日時、Context、Dispatch Modeなどの実行メタデータはOperation Envelopeへ分離し、FWが自動生成する。
5. Operation IDはFWがUUIDv7で生成する。呼び出し元による重複防止には、Operation IDとは別のIdempotency Keyを使用する。
6. Handlerから別のOperationを発行できる。初期設計ではCorrelation IDとCausation IDによる追跡を可能にするが、Sagaなどの高度な制御は後回しにする。
7. 初期設計ではCommandとQueryを型として区別せず、どちらもOperationとして扱う。分類によって実際の制約や処理を変える要件が具体化した時点で再検討する。
8. 読み取り専用DBなどの接続先は、Command/Query分類ではなくInfrastructure層で定義する。
9. HTTPルートはOperationに付与するAttributeとして定義する方向とする。詳細はHTTP境界の設計で決定する。
10. Dispatch Modeは既定値を即時実行とし、Operation側のAttributeで指定可能にする方向とする。モード名、上書き規則、リトライ設定などの詳細は別の設計対話で決定する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- アプリケーション開発者はUUIDやTrace IDを通常意識せず、業務入力を表すOperationだけを定義する。
- Dispatcher、Worker、Journal StoreなどのFW内部および拡張ポイントはOperation Envelopeを扱う。
- 一つのOperationに複数Attemptを関連付けられるため、再試行後も同一の処理として追跡できる。
- Operationの連鎖を追跡できる一方、連鎖全体の整合性や補償処理は初期スコープに含めない。
- Command/Query専用のキャッシュ、読取DB振り分け、制約は初期スコープに含めない。
- Dispatch ModeのAttribute、HTTP RouteのAttribute、Operation Envelopeの詳細スキーマを後続の設計対話で決める必要がある。
- 一定期間のOperationをまとめて反映するCoalesceは、Durable実行の発展機能として別途検討する。

[/CONSEQUENCES]

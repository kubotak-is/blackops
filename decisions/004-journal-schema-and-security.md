# D004: Journal Schemaとセキュリティ

Status: Decided

## Context

D003により、`OperationReceived` のJournal RecordからOperation Envelopeを再現できること、正規Journal形式をJournal ObserverとExecution Transportで共有することを決定した。

この設計対話では、型識別、スキーマ変更、センシティブ値、再現と再実行の境界を決める。

## Question 1: Operationの型識別子

Journal Recordに保存するOperationの型を、どのように識別するか。

### Options

- A: PHPの完全修飾クラス名を保存する
- B: Operationごとに安定した文字列のType IDを明示する
- C: FWがクラス名からType IDを自動生成する

### Recommendation

Bを推奨する。

```php
#[OperationType('order.create')]
final readonly class CreateOrder implements Operation
{
}
```

完全修飾クラス名を保存すると、namespaceやクラス名の変更が過去データとの互換性を壊す。Type IDを明示すれば、PHPコードの構造と外部データ形式を分離できる。

[ANSWER]

B

[/ANSWER]

## Question 2: スキーマバージョニング

Operationのプロパティが変更された場合、古いJournal Recordをどう扱うか。

### Options

- A: 古いバージョンのOperationクラスを残し続ける
- B: Journal Recordにschema versionを持たせ、Upcasterで現在形式へ変換する
- C: 後方互換性のある変更だけを許可する

### Recommendation

Bを推奨する。

```text
order.create v1
    -> CreateOrderV1ToV2 Upcaster
    -> order.create v2
    -> CreateOrder
```

Upcasterはデータ形式だけを変換し、業務処理や外部I/Oを行わない純粋な変換とする。

[ANSWER]

B
これって技術的に可能？いいですね

[/ANSWER]

## Question 3: センシティブ値の宣言

Operationのセンシティブなプロパティを、どのように宣言するか。

### Options

- A: `#[Sensitive]` Attributeをプロパティへ付与する
- B: Operationが観測用データを返すメソッドを実装する
- C: Configへプロパティパスを列挙する

### Recommendation

Aを基本とし、必要に応じてカスタム変換を追加できる設計を推奨する。

```php
final readonly class SendEmail implements Operation
{
    public function __construct(
        public string $recipient,
        #[Sensitive]
        public string $body,
    ) {
    }
}
```

Attributeなら宣言がデータ定義の近くにあり、ログ出力側の設定漏れを減らせる。

[ANSWER]

A
サンプルコードはOperationのコンストラクタにOperationValueがあるが、別クラスにしたい。
そのクラスではバリデーションルールとかも管理できるようにしたい。
OperationとValueの関係性はOperationのアトリビュートで管理でいいかも（関連するValueオブジェクトの設定）

[/ANSWER]

## Question 4: ObserverとTransportへ渡すデータ

センシティブ値を含むJournal Recordをどう扱うか。

### Options

- A: Journal Observerにはマスク済みProjectionを渡し、Execution Transportには再現可能な完全データを暗号化して渡す
- B: ObserverとTransportの両方へ同じ完全データを渡し、各Adapterへ安全性を委ねる
- C: センシティブ値を含むOperationではDeferred Strategyを常に禁止する

### Recommendation

Aを推奨する。

観測用途では再現性より漏えい防止を優先し、実行配送ではWorkerがOperationを復元できる必要がある。完全データを扱えるExecution Transportには、暗号化などのCapabilityを要求する。

[ANSWER]

A
暗号化かー、考えてなかった。いいですね。HMACみたいな感じで行けるかな

[/ANSWER]

## Question 5: 安全でないStrategy/Transportの扱い

センシティブ値を持つOperationが、安全な配送Capabilityを持たないExecution Transportへ割り当てられた場合にどうするか。

### Options

- A: アプリケーション起動時またはOperation受付時に拒否する
- B: 自動的にInline Strategyへ切り替える
- C: 警告ログを出して、そのまま配送する

### Recommendation

Aを推奨する。

実行方式の暗黙変更は性能や整合性を変える。安全要件を満たさない構成は明示的に失敗させる。

[ANSWER]

A
CI向けの手段もあるといいですね。PHPStanの拡張とか

[/ANSWER]

## Question 6: 再現と再実行

Journal RecordからOperationを再現できることと、完了済みOperationを再実行することをどう区別するか。

### Options

- A: 未完了Operationの配送では同じOperation IDを復元する。完了済みOperationの再実行は新しいOperation IDを発行する
- B: Journal Recordからの実行は、常に元のOperation IDを使用する
- C: Journal Recordからの実行は、常に新しいOperation IDを発行する

### Recommendation

Aを推奨する。

Workerによる通常の配送は同じOperationの継続であり、同じIDが必要になる。一方、完了後の手動ReplayはD002の決定どおり新しいOperationとして扱い、元Operationとの因果関係を記録する。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. Operationの型は、PHPの完全修飾クラス名ではなく、Operationごとに明示する安定したType IDで識別する。
2. Journal RecordはType IDに加えてschema versionを持つ。
3. 古いschema versionは、Upcasterによって現在のデータ形式へ段階的に変換してからOperationを復元する。
4. Upcasterは配列などのデータ形式だけを扱う純粋な変換とし、業務処理や外部I/Oを行わない。
5. センシティブ値は `#[Sensitive]` Attributeを基本として宣言する。複雑な要件向けのカスタム変換方法は後続設計で決める。
6. Journal Observerには、センシティブ値を除外またはマスクした観測用Projectionを渡す。
7. Execution Transportには、Operationを再現可能な完全データを暗号化して渡せるようにする。
8. センシティブ値を持つOperationを、安全な配送CapabilityのないExecution Transportへ割り当てた構成は拒否する。
9. 構成不備は可能な限りアプリケーション起動時またはCIで検出し、実行時にも防御的に検証する。
10. 未完了Operationの通常配送では同じOperation IDを復元する。
11. 完了済みOperationの手動Replayでは新しいOperation IDを発行し、元Operationとの因果関係を記録する。
12. Operationと別クラスのOperationValueを関連付け、Value側でバリデーションルールを管理する方向とする。具体的な型構造はD005で決定する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- namespaceやクラス名を変更しても、Type IDを維持すれば過去のJournal Recordを読み取れる。
- Type IDの重複検査と、Type IDから現在のOperation定義を解決するRegistryが必要になる。
- デシリアライズ前に、Type IDとschema versionに対応するUpcaster Chainを適用する。
- Observer用ProjectionとTransport用Payloadは、同じ正規Journal Recordから異なるセキュリティ方針で生成される。
- HMACは改ざん検知には利用できるが暗号化ではない。機密性には認証付き暗号を使用し、具体的な暗号方式と鍵管理はInfrastructure設計で決める。
- Execution Transportは暗号化などのCapabilityを宣言し、FWがOperationの要件との適合性を検証する必要がある。
- PHPStan拡張などにより、Type ID重複、Attribute不足、StrategyとTransport Capabilityの不整合をCIで検出する余地を設ける。
- OperationValueとOperation定義の関係、およびバリデーション境界をD005で決める必要がある。

[/CONSEQUENCES]

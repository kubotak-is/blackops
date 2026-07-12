# D031: Sensitive Projection

Status: Decided

## Context

Canonical JournalはOperation PayloadとOutcomeを保持する。一方、OTel、CloudWatch、JSON Lines等のObserverへCanonical Dataを無条件に渡してはならない。

Sensitive FilterをAdapter任せにすると、Adapter追加時の実装漏れによって機密値が流出する。Framework共通Pipelineの境界、Fieldごとの処理、Canonical Dataを受け取れるPortを決める。

## Question 1: Projection Pipeline

### Options

- A: FrameworkがCanonical Journalから安全なObserver Projectionを生成してからAdapterへ渡す
- B: 各Observer AdapterがCanonical Journalを受け取り、自身でFilterする
- C: Canonical Journal生成時点で機密値を削除する

### Recommendation

Aを推奨する。

```text
Canonical Journal
  -> Framework Sensitive Filter
  -> Observer Projection
  -> JSONL / OTel / CloudWatch Adapter
```

AdapterがRaw Payloadへ触れず、全Observerへ同じ最低安全基準を適用できる。Adapter固有の追加Filterは許可する。

[ANSWER]

A

[/ANSWER]

## Question 2: `#[Sensitive]` の処理方式

### Options

- A: `Omit`、`Mask`、`Hash` を選択可能にし、既定は `Omit`
- B: すべて固定文字列へMaskする
- C: すべてFieldごと暗号化してObserverへ渡す

### Recommendation

Aを推奨する。

```php
#[Sensitive]                         // Omit
#[Sensitive(SensitiveMode::Mask)]
#[Sensitive(SensitiveMode::Hash)]
```

Hashは平文のHashではなく、設定した秘密鍵によるHMACを使用する。暗号化はObserver ProjectionではなくCanonical Durable Storeの保存Policyとして扱う。

[ANSWER]

A

[/ANSWER]

## Question 3: 防御的なFallback

Attribute指定漏れへの補助策をどうするか。

### Options

- A: `password`、`token`、`secret` 等の予約Key Patternを常にOmitし、検出時に警告する
- B: `#[Sensitive]` だけを信頼する
- C: PayloadとOutcomeをObserverへ一切出さない

### Recommendation

Aを推奨する。

型付きPropertyのAttributeを正本としつつ、Array、Logger Context、外部Error Details等へ予約Key Patternを適用する。Patternは設定で追加可能にするが、Framework既定Patternの無効化は明示的な危険設定とする。

[ANSWER]

A

[/ANSWER]

## Question 4: Canonical Dataを受け取るPort

### Options

- A: ObserverはProjectionだけを受け取り、Canonical Dataは別の `CanonicalJournalStore` Portだけが受け取る
- B: ObserverがCapabilityを宣言すればCanonical Dataを受け取れる
- C: すべてのObserverがCanonical Dataを受け取る

### Recommendation

Aを推奨する。

観測用Sinkと再現・復旧用Storeを型レベルで分離できる。Canonical Storeは暗号化、Access Control、Retention等のCapability検証を通過した構成だけを許可する。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

FrameworkはCanonical Journalを生成した後、共通Sensitive Filterで安全なObserver Projectionへ変換してからObserver Adapterへ渡す。AdapterはCanonical Payloadへアクセスできず、必要に応じて追加Filterだけを適用できる。

`#[Sensitive]` は `Omit`、`Mask`、`Hash` を選択可能とし、既定を `Omit` とする。`Hash` は設定された秘密鍵によるHMACを使用する。暗号化はObserver ProjectionではなくCanonical Durable Storeの保存Policyとして扱う。

Attribute指定漏れに対する防御として、`password`、`token`、`secret` 等のFramework予約Key Patternを常にOmitし、検出時に警告する。Patternは追加設定可能とする。

Observerは安全なProjectionだけを受け取る。Canonical Dataは別の `CanonicalJournalStore` Portだけが受け取り、暗号化、Access Control、Retention等のCapability検証を通過した構成でのみ利用できる。

[/DECISION]

## Consequences

[CONSEQUENCES]

- JSON Lines、OTel、CloudWatch Adapterの実装漏れでRaw Payloadが流出する経路を減らせる。
- 全Observerへ共通の最低安全基準を適用できる。
- Omit、Mask、HMACによる相関分析を用途に応じて選択できる。
- Key PatternはAttribute指定漏れへの補助であり、`#[Sensitive]` による型Metadataを正本とする。
- 観測用Sinkと復元・復旧用Storeを型レベルで分離できる。
- Canonical Storeを構成する場合は、保存時暗号化、Access Control、RetentionのCapability検証が必要になる。

[/CONSEQUENCES]

# Sensitive Projection

## Pipeline

Observer AdapterへCanonical Journalを直接渡してはならない。

```text
Canonical Journal
  -> Framework Sensitive Filter
  -> Observer Projection
  -> JSONL / OTel / CloudWatch Adapter
```

Framework共通Sensitive Filterが最低安全基準を適用する。AdapterはRaw Payloadへアクセスできず、出力先固有の追加Filterだけを適用できる。

## Sensitive Mode

`#[Sensitive]` は次の処理方式を持つ。

| Mode | 動作 |
| --- | --- |
| `Omit` | FieldをProjectionから除外する。既定値 |
| `Mask` | 安全な固定表現へ置換する |
| `Hash` | 秘密鍵を使ったHMACへ置換する |

```php
#[Sensitive]
#[Sensitive(SensitiveMode::Mask)]
#[Sensitive(SensitiveMode::Hash)]
```

平文のHashは使用しない。暗号化はObserver ProjectionではなくCanonical Durable Storeの保存Policyとして扱う。

## 防御的Fallback

`password`、`token`、`secret` 等のFramework予約Key Patternを常にOmitし、検出時に警告する。

予約PatternはArray、Logger Context、外部Error Details等にも適用する。利用者はPatternを追加できる。

PatternはAttribute指定漏れへの補助であり、型付きPropertyの `#[Sensitive]` Metadataを正本とする。

## Portの分離

Observerは安全なProjectionだけを受け取る。

Canonical Dataは別の `CanonicalJournalStore` Portだけが受け取る。Canonical Storeは保存時暗号化、Access Control、Retention等のCapability検証を通過した構成でのみ利用できる。

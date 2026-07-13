# D086: OperationValue Validation Runtime

Status: Decided

## Context

D005と確定仕様はOperationValueのProperty Attributeで文字数、範囲、形式を検証するValue Validationを定義している。しかし現行Public Sourceに`NotBlank`、`Length`、`Range`等のValidation Attributeと`OperationValueValidator`は存在しない。

現在利用者が実装できるのは、Binding成功後のTyped Self-handled `handle()`内で値を確認し、`OperationRejectedException::validation($code)`をthrowする方法である。InlineはHTTP 422と`OperationRejected` Journalを返す。DeferredはHTTP受付時に202を返し、Worker実行後にRejected StateとJournalを記録する。

Binding FailureにもGapがある。不正JSON、JSON Object以外、必須Field欠落、非Scalar値はBinderから例外になるが、現行HTTP Handlerは4xxへ変換せず、Operation IDとJournalも生成しない。これはBinding FailureをOperation ID付きRejectedとして記録する既存仕様と矛盾する。

## Question 1: 宣言的Value ValidationをどのAPIで提供するか

### Options

- A: BlackOps所有のValidation AttributeをPublic APIとして提供する
- B: `symfony/validator`を導入し、Symfony Constraint AttributeをApplication APIとして直接利用する
- C: 宣言的Attributeを実装せず、`handle()`内の手動Validationだけを正式Contractにする

### Recommendation

Aを推奨する。

BlackOpsのLifecycle、Rejected Code、Sensitive境界へ一貫して接続でき、Validator BackendをPublic Contractへ露出しない。初期Ruleを小さく保ち、複雑な外部状態照合はHandler／Domainへ残す。

[ANSWER]

A

[/ANSWER]

## Question 2: 初期Validation Ruleをどこまで含めるか

### Options

- A: `NotBlank`、`Length`、`Range`、`Email`、`Regex`、`Count`、`Choice`
- B: `NotBlank`、`Length`、`Range`の最小3種類
- C: Ruleごとに別Decisionで追加する

### Recommendation

Aを推奨する。

文字列、数値、配列、選択肢という一般的なHTTP Inputを一通り表現できる。Nested Object変換、DB照合、Cross-field Validation、Custom Callbackは初期Scopeへ含めない。

[ANSWER]

A
Max,MinはCount？

[/ANSWER]

### Follow-up: Range、Length、Countの違い

曖昧な`Min`／`Max` Attributeは用意しない。対象の意味ごとに次へ分ける。

| Rule | Target | `min`／`max`の意味 |
| --- | --- | --- |
| `Range` | `int`／`float` | 数値そのものの下限／上限 |
| `Length` | `string` | 文字数の下限／上限 |
| `Count` | `array`等 | 要素数の下限／上限 |

これによりLaravelの`min`のように入力型で意味が変わる曖昧さを避ける。

## Question 3: HTTP Binding Failureをどう分類するか

### Options

- A: 壊れたJSON／JSON Object以外はOperation受理前のProtocol Errorとして400・Journal対象外、Route特定後のField欠落／型不一致とValue ValidationはOperation ID付き422・Rejected Journal
- B: Route特定後の全Binding FailureをOperation ID付き422・Rejected Journal
- C: 現状どおり外側のApplication Error Handlerへ委ねる

### Recommendation

Aを推奨する。

Protocol SyntaxとOperation Input Validationを分離し、「No operation stays in the dark」をOperationとして受理できる入力へ適用できる。Error JSONはCategory、安定Code、FieldごとのViolationを持つが、Raw Sensitive Valueを含めない。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. BlackOps所有のValidation Attributeを`BlackOps\Core\Validation\Attribute` Public APIとして提供する。
2. 初期Ruleは`NotBlank`、`Length`、`Range`、`Email`、`Regex`、`Count`、`Choice`とする。
3. `Range`は数値、`Length`は文字列、`Count`はCollection要素数を扱い、曖昧な`Min`／`Max` Attributeは提供しない。
4. Nested Object変換、DB照合、Cross-field Validation、Custom Callbackは初期Scopeへ含めない。
5. FrameworkはBinding成功後、Execution Strategyを選ぶ前にOperationValueを検証する。
6. 複数Violationを集約し、Field、Rule、安定Codeだけを保持する。Raw Input Value、Sensitive Value、Validation Attributeの内部設定をError／Journalへ含めない。
7. Inline／DeferredのどちらもValue Validation FailureをOperation ID付き`OperationRejected`として記録し、Handlerを実行しない。
8. HTTP Responseは422、Category `validation`、Code `validation.failed`、FieldごとのViolationを返す。
9. 壊れたJSON／JSON Object以外はOperation受理前のProtocol Errorとして400を返し、Operation IDとLifecycle Journalを作らない。
10. Route特定後の必須Field欠落、型不一致、Value Validation FailureはOperation ID付き422とRejected Journalにする。
11. `handle()`内の`OperationRejectedException::validation()`はCustom／Cross-field Validationの互換手段として維持する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 利用者はOperationValueの近くで一般的な形式検証を宣言できる。
- BindingとValue Validationの失敗境界をHTTP Status、Operation ID、Journalで判別できる。
- Existing Rejection／Responder／Journal Data ContractへViolation Detailを安全に追加する設計が必要になる。
- Generator、Reference、Validation Guide、Core API／Attribute一覧を同じ変更単位で更新する必要がある。

[/CONSEQUENCES]

# D086: OperationValue Validation Runtime

Status: Discussing

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


[/ANSWER]

## Question 3: HTTP Binding Failureをどう分類するか

### Options

- A: 壊れたJSON／JSON Object以外はOperation受理前のProtocol Errorとして400・Journal対象外、Route特定後のField欠落／型不一致とValue ValidationはOperation ID付き422・Rejected Journal
- B: Route特定後の全Binding FailureをOperation ID付き422・Rejected Journal
- C: 現状どおり外側のApplication Error Handlerへ委ねる

### Recommendation

Aを推奨する。

Protocol SyntaxとOperation Input Validationを分離し、「No operation stays in the dark」をOperationとして受理できる入力へ適用できる。Error JSONはCategory、安定Code、FieldごとのViolationを持つが、Raw Sensitive Valueを含めない。

[ANSWER]


[/ANSWER]

## Pending Consequences

回答後にPublic Attribute、Violation Model、Validator Invocation Boundary、Inline／Deferred Rejection、Journal Data、HTTP Error Shape、Generator Example、Documentation、TestをTask Packetへ落とし込む。


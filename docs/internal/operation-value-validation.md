# Operation Value Validation

OperationValueの一般的な形式RuleはConstructor Promotion PropertyへPublic Validation Attributeとして宣言する。Framework内部の`OperationValueValidator`は全対象Propertyを検証し、Raw Valueを含まないViolationを返す。HTTP AdapterはBindingとValue ValidationをExecution Strategyの選択前に完了させる。

## Public rules

Attributeは`BlackOps\Core\Validation\Attribute`に置き、PropertyだけをTargetにする。Repeatable Attributeにはしない。

| Attribute | Target value | Boundary |
| --- | --- | --- |
| `NotBlank` | `string` | 空文字とUnicode空白だけの文字列を拒否する |
| `Length` | `string` | Unicode Code Point数をInclusiveな`min`／`max`で検証する |
| `Range` | `int`／`float` | 有限数値をInclusiveな`min`／`max`で検証する |
| `Email` | `string` | Symfony ValidatorのEmail Constraintで形式を検証する |
| `Regex` | `string` | Constructorで検証済みのPCRE Patternとの一致を検証する |
| `Count` | `array` | 初期Scopeでは配列要素数だけをInclusiveな`min`／`max`で検証する |
| `Choice` | Scalar | 非空の許可ListへStrict Comparisonで含まれるか検証する |

`Length`、`Range`、`Count`は少なくとも一方のBoundを必要とする。負のLength／Count、非有限Range、逆転したBoundはAttribute生成時に拒否する。`Regex`は有効な非空PCRE Pattern、`Choice`は重複のない非空Scalar Listを必要とする。

Ruleを対象外のValue型へ付与した場合、ValidatorはRaw Valueを含まない通常のViolationを返す。Nested Object、Collection Object、Cross-field Rule、DB照合、Custom Callbackはこの境界へ含めない。

## Internal backend

Applicationが使用する型はBlackOpsのAttributeとViolationだけである。Internal Adapterが各Ruleを`symfony/validator` Constraintへ変換し、Raw ScalarまたはArrayを検証する。SymfonyのConstraint、Violation、Message、Invalid ValueはPublic API、HTTP Response、Journalへ渡さない。

BlackOps側には次の境界を残す。

- Ruleの対象型を先に確認する
- `Length`はUnicode Code Point、`Choice`はStrict Comparisonとして固定する
- `Range`は有限な`int`／`float`、`Count`はArrayだけを受け付ける
- Property名と固定Rule順で全Violationを集約する
- BlackOpsの安定Rule名とCodeへ変換する

## Violation model

Public `BlackOps\Core\Validation\Violation`は次の3 Fieldだけを保持する。

- `field`: Constructor Promotion Property名
- `rule`: `not_blank`、`length`等の安定Rule名
- `code`: `validation.not_blank`、`validation.length`等の安定Code

Raw Input、Normalized Input、Attribute設定、Messageは保持しない。`#[Sensitive]` Propertyも同じViolation Shapeを使うため、Serialize、Dump、後続Response／Journal ProjectionへRaw Secretを渡さない。

## Deterministic aggregation

ValidatorはConstructor Promotion Propertyだけを対象にする。Property名を昇順に並べ、各Propertyでは次の固定順でRuleを評価する。

```text
not_blank -> length -> range -> email -> regex -> count -> choice
```

最初のFailureで停止せず、すべてのViolationをこの順序で返す。後続のHTTP／Lifecycle Adapterは順序を並べ替えたり、Raw Valueを付加したりしない。

## HTTP and lifecycle boundary

| Failure | HTTP | Operation ID | Canonical journal | Deferred persistence |
| --- | --- | --- | --- | --- |
| 壊れたJSON／JSON Object以外 | 400 | なし | なし | なし |
| 必須Field欠落／型不一致 | 422 | あり | Sequence 1 `operation.rejected` | なし |
| Value Rule違反 | 422 | あり | `operation.received`、`operation.rejected` | なし |

JSON BodyはObjectだけを受け付ける。BindingはNative型を厳密に扱い、文字列`"false"`をBooleanへ、文字列`"1"`をIntegerへ暗黙変換しない。Path、Query、Headerは文字列としてBindingする。Nested Object／Array変換は初期Scope外であり、Scalar Propertyへ渡された場合は型Violationになる。

422 Responseは`status`、`operationId`、Category `validation`、Code `validation.failed`、Field／Rule／Codeだけの`violations`を返す。Handler内の`OperationRejectedException::validation()`によるManual Rejectionは既存Response Shapeを維持する。

## Sensitive boundary

Value Validation FailureのCanonical `OperationReceivedData`は、再現可能性のため実OperationValueを保持するRestricted Dataである。`#[Sensitive]` Propertyを含む場合もこのContractは変えない。

一方、Observed Journalでは既存ProjectionがSensitive Propertyをマスクまたは除外する。HTTP Response、Binding Exception、Validation Violation、`OperationRejectedData`、Observed Rejection DetailはField／Rule／Codeだけを扱い、Raw Value、Symfony Message、Constraint設定を複製しない。

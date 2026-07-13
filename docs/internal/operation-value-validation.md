# Operation Value Validation

OperationValueの一般的な形式RuleはConstructor Promotion PropertyへPublic Validation Attributeとして宣言する。Framework内部の`OperationValueValidator`は全対象Propertyを検証し、Raw Valueを含まないViolationを返す。

このCore実装はValidation Contractだけを所有する。HTTP Status、Operation ID、Lifecycle Journal、Inline／Deferred実行境界への接続は上位Adapterが担当する。

## Public rules

Attributeは`BlackOps\Core\Validation\Attribute`に置き、PropertyだけをTargetにする。Repeatable Attributeにはしない。

| Attribute | Target value | Boundary |
| --- | --- | --- |
| `NotBlank` | `string` | 空文字とUnicode空白だけの文字列を拒否する |
| `Length` | `string` | Unicode Code Point数をInclusiveな`min`／`max`で検証する |
| `Range` | `int`／`float` | 有限数値をInclusiveな`min`／`max`で検証する |
| `Email` | `string` | PHPのEmail Filterで形式を検証する |
| `Regex` | `string` | Constructorで検証済みのPCRE Patternとの一致を検証する |
| `Count` | `array` | 初期Scopeでは配列要素数だけをInclusiveな`min`／`max`で検証する |
| `Choice` | Scalar | 非空の許可ListへStrict Comparisonで含まれるか検証する |

`Length`、`Range`、`Count`は少なくとも一方のBoundを必要とする。負のLength／Count、非有限Range、逆転したBoundはAttribute生成時に拒否する。`Regex`は有効な非空PCRE Pattern、`Choice`は重複のない非空Scalar Listを必要とする。

Ruleを対象外のValue型へ付与した場合、ValidatorはRaw Valueを含まない通常のViolationを返す。Nested Object、Collection Object、Cross-field Rule、DB照合、Custom Callbackはこの境界へ含めない。

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

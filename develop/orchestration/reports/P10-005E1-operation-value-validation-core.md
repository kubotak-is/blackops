# P10-005E1: OperationValue Validation Core Report

Status: Accepted

## Summary

BlackOps所有の宣言的OperationValue Validation Coreを実装した。`BlackOps\Core\Validation\Attribute`へ7 Ruleを追加し、Field、Rule、Codeだけを保持するPublic `Violation`を追加した。Internal ValidatorはConstructor Promotion Propertyだけを対象に、全Violationを決定的順序で集約する。

HTTP Binding、HTTP Status、Operation ID、Journal、Inline／Deferred実行境界への接続は行っていない。これらは後続Taskが本Core Contractを利用する。

## Public API

- `NotBlank`: 文字列の空文字とUnicode空白だけの値を拒否する
- `Length`: Unicode Code Point数をInclusiveな`min`／`max`で検証する
- `Range`: 有限な`int`／`float`値をInclusiveな`min`／`max`で検証する
- `Email`: PHP Email Filterで文字列形式を検証する
- `Regex`: Constructorで検証済みの非空PCRE Patternへ一致するか検証する
- `Count`: 初期Scopeでは配列要素数だけをInclusiveな`min`／`max`で検証する
- `Choice`: 重複のない非空Scalar ListへStrict Comparisonで含まれるか検証する
- `Violation`: Public final readonly Modelとして`field`、`rule`、`code`だけを持つ

7 Attributeはfinal readonly、`#[PublicApi]`、non-repeatable `Attribute::TARGET_PROPERTY`で統一した。Boundなし、負のLength／Count、非有限Range、逆転Bound、無効Regex、空／連想／非Scalar／非有限／重複ChoiceをAttribute生成時に拒否する。

## Rule Matrix

| Rule | Target | Boundary evidence | Violation code |
| --- | --- | --- | --- |
| `NotBlank` | `string` | Content成功、空文字／空白失敗 | `validation.not_blank` |
| `Length` | `string` | Unicode 2文字とmax成功、min未満／max超過失敗 | `validation.length` |
| `Range` | `int`／`float` | min／max／範囲内float成功、上下範囲外失敗 | `validation.range` |
| `Email` | `string` | 有効Address成功、不正形式失敗 | `validation.email` |
| `Regex` | `string` | Pattern一致成功、不一致失敗 | `validation.regex` |
| `Count` | `array` | min／max成功、要素数不足／超過失敗 | `validation.count` |
| `Choice` | Scalar | string／int Strict一致成功、型違い／未知値失敗 | `validation.choice` |

対象外のValue型をUnion Property経由で渡した場合、ExceptionやRaw Valueではなく該当RuleのViolationになることを全対象Ruleで検証した。

## Deterministic Aggregation

`OperationValueValidator`はReflectionでConstructor Promotion Propertyだけを取得し、Property名の昇順で検証する。各PropertyのRule順は`not_blank`、`length`、`range`、`email`、`regex`、`count`、`choice`へ固定した。最初のFailureで停止せず全Violationを返す。

Rule評価は`OperationValueRuleEvaluator`へ分離し、集約責務と個別Rule判定を分けた。Runtime Dependencyは追加していない。

## Sensitive Boundary

ViolationのReflection Propertyが`field`、`rule`、`code`の3件だけであることをTestした。`#[Sensitive]`付きPropertyへ不一致Secretを渡し、ViolationのSerialize、`var_export`、JSON出力のいずれにもRaw Secretが含まれないことを確認した。

Attribute Constructor ErrorもRaw OperationValueを受け取らず、設定値そのものをError Messageへ展開しない。

## Changed Files

- `src/Core/Validation/Attribute/NotBlank.php`
- `src/Core/Validation/Attribute/Length.php`
- `src/Core/Validation/Attribute/Range.php`
- `src/Core/Validation/Attribute/Email.php`
- `src/Core/Validation/Attribute/Regex.php`
- `src/Core/Validation/Attribute/Count.php`
- `src/Core/Validation/Attribute/Choice.php`
- `src/Core/Validation/Violation.php`
- `src/Internal/Validation/OperationValueValidator.php`
- `src/Internal/Validation/OperationValueRuleEvaluator.php`
- `tests/Core/Validation/ValidationAttributeTest.php`
- `tests/Core/Validation/ViolationTest.php`
- `tests/Internal/Validation/OperationValueValidatorTest.php`
- `docs/internal/operation-value-validation.md`
- `docs/internal/README.md`
- `develop/orchestration/reports/P10-005E1-operation-value-validation-core.md`
- `develop/STATE.md`

既存`deptrac.yaml`のCore／Internal Regex LayerとRulesetが新Namespaceを正しく包含するため、設定変更は不要だった。

## Decisions and Assumptions

- Property Declaration順ではなくProperty名昇順を集約順Contractとした
- Rule順は仕様の7 Rule列挙順へ固定した
- `Length`はRuntime Dependencyを追加せず、UTF-8 Code Point数をPCREで数える
- `Count`はTask制約どおり配列だけを扱い、`Countable`やCollection Objectへ拡張しない
- Wrong Target TypeはBootstrap Exceptionにせず、安全なRule Violationとして集約する
- HTTP／Journal／Deferred接続と`validation.failed`全体Responseは後続Taskへ残した

## Commands and Results

```text
docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Core/Validation tests/Internal/Validation tests/Architecture
Result: OK (79 tests, 241 assertions). Rule成功／失敗／境界、Constructor拒否、Wrong Target、決定的集約、Sensitive非露出、Public API Architectureを検証。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1597 / Warnings 0 / Errors 0.

PHP Management ID Guard
Result: No matches.

git diff --check
Result: Success.
```

## Acceptance Criteria

- [x] 7 AttributeがPublic APIとして正しいTarget／Constructor Contractを持つ
- [x] 不正Rule設定をAttribute生成時に拒否する
- [x] 全Ruleの成功／失敗／境界値をUnit Testする
- [x] 複数Property／複数RuleのViolationを決定的順序で集約する
- [x] ViolationがField、Rule、Codeだけを持ちRaw Valueを持たない
- [x] Sensitive Propertyの入力値がException／Dump／Test Outputへ露出しない
- [x] DeptracとPublic API Guardが成功する

## Remaining Issues

P10-005E1 Scope内の既知Issueはない。ValidatorはまだHTTP／Executionから呼び出されないため、現時点では利用者がAttributeを付与してもRuntime Lifecycleへ影響しない。

Protocol Error 400、Binding／Value ValidationのOperation ID付き422、Rejected Journal、Inline／Deferred Handler非実行は後続Taskで接続する。

## Suggested Next Action

P10-005E1を単独Commitし、HTTP Validation Lifecycle接続へ進む。

## Orchestrator Review

2026-07-13T23:29:30+09:00にPublic Constructor Contract、Violation Shape、Rule Boundary、決定的順序、Sensitive非露出、Scope境界をReviewした。Workerと独立してMago format／lint／analyze、79 tests／241 assertions、Deptracを再実行し、すべて成功した。Acceptance Criteriaを満たし、Scope逸脱とBlockerがないためAcceptedとする。

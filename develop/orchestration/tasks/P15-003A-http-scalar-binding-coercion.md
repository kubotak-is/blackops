# P15-003A: HTTP Scalar Binding Coercion

Status: Ready

## Goal

D101で確定した厳密なNon-body Scalar CoercionをHTTP Bindingへ実装し、Path／Query／HeaderのCanonical文字列をOperationValueの`string`／`int`／`float`／`bool`宣言型へ安全に変換する。

Invalid値は既存のOperation ID付き422 Binding Rejectionへ統合し、BodyのNative JSON Scalar、Missing／Default／Required、Nullable、Sensitive／Raw Value非露出を回帰させない。P15-003のGenerated Request Runtimeが同じCanonical形式を実装できるServer正本を先に固定する。

## In Scope

- Path／Query／HeaderだけのSource-aware Scalar Decode
- `string`、`int`、`float`、`bool`とNullableの厳密形式
- PHP Integer RangeとFinite Floatの検査
- Body Native Scalarの非Coercion
- Missing／Default／Required／Empty／Null境界
- Invalid Scalarの既存422 Binding Failure統合
- Path／Query／Header全Sourceの成功／失敗Test
- Actual HTTP HandlerでのOperation ID付き422回帰
- Specification、Guide、Report、STATE同期

## Out of Scope

- Frontend TypeScript生成、`.url()`、`.toRequest()`、`.fetch()`
- Array、Nested DTO、Enum、DateTime、Upload、Custom Parser／Codec
- HTML Form Boolean Alias、Locale Number、Loose PHP Cast
- Binding Attribute、Public PHP API、Migration、Database Schemaの追加
- Validation Rule、Responder Shape、Lifecycle State Machineの変更
- Quickstart／Skeleton Operation、Website Publication／Deploy

## Relevant Specifications and Decisions

- `develop/spec/05-http.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/68-phase-15-delivery-plan.md`
- `develop/decisions/086-operation-value-validation.md`
- `develop/decisions/087-http-binding-rejection-lifecycle.md`
- `develop/decisions/101-http-scalar-binding-coercion.md`

## Files Allowed to Change

### Production

- New `src/Http/Binding/HttpBoundScalarDecoder.php`
- `src/Http/Binding/HttpParameterBinder.php`
- `src/Http/Binding/HttpBoundValueTypeMatcher.php`（Decode後型検査の同期が必要な場合だけ）
- `src/Http/Binding/BoundHttpValue.php`（Source Metadataが必要な場合だけ）
- `src/Http/Binding/OperationValueBinder.php`（Source-aware Decode接続が必要な場合だけ）

### Tests

- New `tests/Http/Binding/**`
- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Http/HttpValidationLifecycleTest.php`
- `tests/Http/DeferredOperationRequestHandlerTest.php`（Deferred 422回帰が必要な場合だけ）
- `tests/Integration/ApplicationHttpRuntimeTest.php`（Production Composition回帰が必要な場合だけ）

### Documentation and Orchestration

- `docs/guide/validation.md`
- `docs/guide/attributes.md`
- `docs/internal/bootstrap.md`（HTTP Binding内部境界の追記が必要な場合だけ）
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P15-003A-http-scalar-binding-coercion.md`

変更可能Fileの追加が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Canonical Decode Contract

| Declared Type | Accepted Non-body String | Rejected Examples |
| --- | --- | --- |
| `string` | 任意の文字列。空文字も文字列として維持 | 文字列以外 |
| `int` | `0`、`-1`、`42`、PHP Integer範囲内の10進 | `+1`、`01`、`-0`、空白、`1.0`、`1e2`、範囲外 |
| `float` | JSON Number相当かつ変換後Finite。整数形式も許可 | `+1`、`01`、`.5`、`1.`、空白、NaN、Infinity、Overflow |
| `bool` | `true`、`false` | `TRUE`、`False`、`1`、`0`、`yes`、空白 |

`int`のCanonical形式は`0`または`-?[1-9][0-9]*`とし、Negative Zeroを拒否する。`float`はJSON Number Grammarを使い、`-0`、小数、指数を許可するが、Decode結果がFiniteでなければ拒否する。整数形式を`float`へDecodeした場合もPHP `float`としてConstructorへ渡す。

Nullableでも空文字や文字列`null`を`null`へ変換しない。Query／HeaderのMissingだけをMissingとして扱い、Constructor Defaultを適用できる。Path ParameterはRoute成立後にMissingなら既存Required Failureとする。

BodyはJSON Decoderが返したNative型を`HttpBoundValueTypeMatcher`で検査する。Bodyの`"42"`を`int`へ、`"false"`を`bool`へ変換しない。

## Failure and Security Contract

- Decode失敗は`OperationValueBindingException::type(field)`へ統合する
- HTTP Surfaceは既存のOperation ID付き422、Category `validation`、Code `validation.failed`を維持する
- Handlerを実行せず、Binding Failure LifecycleはSequence 1 `OperationRejected`だけを維持する
- Raw入力、Decode理由、PHP Type Error、Sensitive値をResponse／Violation／Observed Journal／Logへ含めない
- Inline／DeferredのどちらもInvalid BindingをTransportへ永続化せず202を返さない

## Acceptance Criteria

- [ ] Path／Query／Headerの`string`／`int`／`float`／`bool`成功Caseを型まで検証する
- [ ] Canonical境界値、Integer Overflow、Float Overflow、Boolean Alias、空白、Emptyを拒否する
- [ ] Nullable、Missing、Default、Requiredを既存Contractどおり扱う
- [ ] Body Native Scalarは成功し、Body Stringの別Scalar型Coercionを拒否する
- [ ] Invalid値はOperation ID付き422とSequence 1 Rejectedへ統合される
- [ ] Inline／Deferred Handlerを実行せず、Deferred 202／Transport永続化を行わない
- [ ] Raw／Sensitive値とDecode理由を公開Surfaceへ出さない
- [ ] Existing string Binding、Validation、Protocol 400、Business Rejectionを回帰させない
- [ ] Public PHP API、Migration、Database Schema、TypeScriptを追加しない
- [ ] GuideがCanonical形式と422境界を読者向けに説明する
- [ ] Required PHP Quality Gateが成功する
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Http/Binding \
  tests/Http/OperationRequestHandlerTest.php \
  tests/Http/HttpValidationLifecycleTest.php \
  tests/Http/DeferredOperationRequestHandlerTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P15-003A-http-scalar-binding-coercion.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Canonical Decode Matrix
- Lifecycle／Security Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

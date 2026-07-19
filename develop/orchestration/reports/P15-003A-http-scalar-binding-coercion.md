# P15-003A HTTP Scalar Binding Coercion Report

Status: Accepted

## Summary

Path、Query、HeaderのWire文字列をOperationValue Constructorの宣言型に従って厳密にDecodeする`HttpBoundScalarDecoder`を追加し、`HttpParameterBinder`のNon-body Sourceだけへ接続した。

`string`、`int`、`float`、`bool`のCanonical形式、PHP Integer範囲、Finite Float、Nullable／Missing／DefaultをTestした。Bodyは従来のJSON Native Scalar検査経路のままで、Body文字列から別Scalar型へのCoercionは行わない。

Decode Failureは既存`OperationValueBindingException::type(field)`に統合し、Inline／DeferredともOperation ID付き422、Sequence 1の`operation.rejected`だけ、Handler／Deferred Acceptor未到達を維持した。Raw Wire Value、Decode理由、Sensitive値はResponse／Rejected Data／Observed Journalへ出していない。

## Changed Files

### Production

- `src/Http/Binding/HttpBoundScalarDecoder.php`
- `src/Http/Binding/HttpParameterBinder.php`

`HttpBoundValueTypeMatcher`、`BoundHttpValue`、`OperationValueBinder`は変更していない。Public PHP API、Migration、Database Schema、TypeScript／JavaScriptも追加していない。

### Tests

- `tests/Http/Binding/HttpBoundScalarDecoderTest.php`
- `tests/Http/Binding/OperationValueBinderScalarCoercionTest.php`
- `tests/Http/HttpValidationLifecycleTest.php`

### Documentation and Orchestration

- `docs/guide/validation.md`
- `docs/guide/attributes.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P15-003A-http-scalar-binding-coercion.md`

## Decisions and Assumptions

- Source Metadataを`BoundHttpValue`へ永続化せず、入力元を把握する`HttpParameterBinder`でNon-bodyだけをDecodeする。Bodyの非Coercionを構造的に維持できる。
- Non-body値はWire Contractどおり文字列だけを受理し、ProgrammaticなQuery `null`やNative Numberを暗黙Castしない。
- NullableはMissing時のDefaultまたはBody Native `null`だけで成立し、Non-bodyの空文字と文字列`null`は宣言型に従って扱う。
- Unsupported／AmbiguousなReflection Typeを新たに変換せず、既存Type Matcherによる最終検査を維持する。
- FloatはJSON Number GrammarをRegexで確定した後、`FILTER_VALIDATE_FLOAT`と`is_finite()`でOverflowを拒否する。

## Canonical Decode Matrix

| Declared Type | Accepted | Rejected |
| --- | --- | --- |
| `string` | 全文字列、空文字 | Non-string Wire Value |
| `int` | `0`、`-1`、`42`、`PHP_INT_MIN`／`PHP_INT_MAX` | `+1`、`01`、`-0`、空白、小数、指数、Overflow |
| `float` | 整数形式、`-0`、小数、指数のFinite JSON Number | `+1`、`01`、`.5`、`1.`、空白、NaN、Infinity、Overflow |
| `bool` | 小文字`true`／`false` | Case Variant、`1`／`0`、`yes`、空白 |
| Nullable | Missing時のDefault／`null`、Body Native `null` | Non-body空文字をScalar以外への`null`として扱わない |
| Body | JSON Native `int`／`float`／`bool`／`null` | `"42"` to `int`、`"1.5"` to `float`、`"false"` to `bool` |

Path `int`、Query `float`／`string`、Header `bool`の成功時Constructor Typeと、Path／Query／HeaderそれぞれのInvalid値をBinder Testで検証した。

## Lifecycle / Security Evidence

- Invalid Sensitive Query ScalarはHTTP 422、Category `validation`、Code `validation.failed`、Violation `page/type/binding.type`、Operation IDだけを公開する。
- Binding Failure JournalはSequence 1の`OperationRejected`一件だけで、`OperationReceived`とAttemptを作らない。
- Inline Handler ResolverはFailing Fixtureのままで呼ばれず、Deferred Acceptorも`accepted=false`のままである。
- Actual PostgreSQL Deferred Testの既存Binding Failureと併せ、Invalid BindingでOperations Tableへ永続化せず202を返さない回帰を必須Targetで通過した。
- Raw Query値はResponse、Canonical Rejected Data、Observed JournalのSerialize結果に存在しない。Exception MessageもGenericでDecode値を含まない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All files formatted。No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Http/Binding \
  tests/Http/OperationRequestHandlerTest.php \
  tests/Http/HttpValidationLifecycleTest.php \
  tests/Http/DeferredOperationRequestHandlerTest.php
Result: OK (88 tests, 386 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1295 tests, 4787 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2257 / Warnings 0 / Errors 0。

Management Comment ID、TypeScript／JavaScript追加、Migration追加、git diff --check Guard
Result: 成功。
```

最初のMago AnalyzeはRegex確認後の明示`(float)` CastをPotential non-numeric Castとして1 Warningを報告した。`FILTER_VALIDATE_FLOAT`に変更し、Binding Test、Mago Analyze、Target、Full Suiteを最終Codeで再実行して成功した。

## Acceptance Criteria

- [x] Path／Query／Headerの`string`／`int`／`float`／`bool`成功CaseをConstructor Typeまで検証した
- [x] Canonical境界、Integer Overflow、Float Overflow、Boolean Alias、空白、Emptyを拒否した
- [x] Nullable、Missing、Default、Requiredを既存Contractどおり扱った
- [x] Body Native Scalarは成功し、Body Stringの別Scalar型Coercionを拒否した
- [x] Invalid値はOperation ID付き422とSequence 1 Rejectedへ統合した
- [x] Inline／Deferred Handlerを実行せず、Deferred 202／Transport永続化を行わない回帰が成功した
- [x] Raw／Sensitive値とDecode理由を公開Surfaceへ出していない
- [x] Existing String Binding、Validation、Protocol 400、Business RejectionをFull Suiteで回帰した
- [x] Public PHP API、Migration、Database Schema、TypeScriptを追加していない
- [x] GuideがCanonical形式と422境界を読者向けに説明する
- [x] Required PHP Quality Gateが成功した
- [x] WorkerはCommitしていない

## Remaining Issues

P15-003Aを妨げるBlockerはない。

Frontend TypeScriptの`.url()`／`.toRequest()`が同じCanonical Encodeを使う実装はP15-003のScopeである。Array、Nested DTO、Enum、DateTime、Form Boolean Alias、Locale Number、Custom CodecはOut of Scopeのままである。

Documentation WebsiteはUser判断どおり未公開であり、Publication／Deployを実行していない。

## Suggested Next Action

OrchestratorがCanonical Regex／Range／Finite境界、Body非Coercion、Operation ID付き422 Lifecycle、Deferred非永続化、Sensitive／Raw非露出を独立Reviewする。Accepted後、P15-003 Operation Object and Request Generationへ進む。

## Orchestrator Review

Canonical Integer／Float／Boolean形式、PHP Integer Range、Finite Float、全Non-body Source、Nullable／Missing／Default、Body非Coercion、Operation ID付き422、Sequence 1 Rejected、Deferred Acceptor未到達、Sensitive／Raw非露出を独立Reviewし、Acceptance Criteriaを満たすと判断した。

Task Packet記載のTargetを再実行し、OK（88 tests、386 assertions）を確認した。Full PHPUnitはOK（1295 tests、4787 assertions）、Composer Root／Quickstart、Mago format／lint／analyze、Deptracも成功し、DeptracはViolations 0／Warnings 0／Errors 0だった。Management Comment ID、TypeScript／JavaScript追加、Migration追加、`git diff --check`のGuardも成功した。

次工程監査で、P15-002 Frontend ContractがPHP `int`と`float`を同じ`number`へ正規化し、D101のCanonical Encode規則をGenerated Runtimeが区別できないことを検出した。P15-003AのServer実装を妨げる問題ではないため本TaskはAcceptedとし、Frontend Contract SchemaでNative Scalar Kindを保持する補正をP15-003へ追加する。

# D101: HTTP Scalar Binding Coercion

Status: Decided

## Context

P15-003ではFrontend Contractから`.url()`と`.toRequest()`を生成し、Path／Query／Header／Body Bindingを実HTTP Runtimeと一致させる。

P15-002のFrontend Contractは`string`、`int`、`float`、`bool`を全Binding Sourceで表現できる。しかし現在のHTTP Runtimeでは、FastRouteのPath Parameter、`parse_str()`由来のQuery、PSR-7 Headerは文字列として`HttpParameterBinder`へ渡る。`HttpBoundValueTypeMatcher`はPHP宣言型へ暗黙変換せず、`int`は`is_int()`、`float`は`is_int() || is_float()`、`bool`は`is_bool()`だけを許可する。

そのため、次のOperationValueはFrontend生成の有無にかかわらず、現在のHTTP Requestから成功Bindingできない。

```php
final readonly class SearchValue implements OperationValue
{
    public function __construct(
        #[FromQuery]
        public int $page,
        #[FromHeader('X-Dry-Run')]
        public bool $dryRun,
    ) {}
}
```

Frontend側だけで`page=2`や`X-Dry-Run: true`を生成してもServer側では文字列のままなので、422 Binding Rejectionになる。生成ClientでCastして隠せず、HTTP Binding Contractを先に確定する必要がある。

## Question 1: Non-body Scalar Contract

Path／Query／HeaderでNative Scalar型をどう扱うか。

### Options

- A: HTTP Binderが宣言型に基づいてCanonical文字列を`int`／`float`／`bool`へ厳密変換する。Frontend Contractは全Scalarを全Binding Sourceで維持する
- B: Path／Query／Headerは`string`／`?string`だけを許可し、`int`／`float`／`bool`はBuild Errorにする。数値／BooleanはApplicationがstringとして受けて変換する
- C: 現状を維持し、Frontend生成側も文字列化するがServerの422はApplication側で回避する

### Recommendation

Aを推奨する。

URLとHeaderがWire上で文字列なのは通常であり、利用者が`#[FromQuery] public int $page`と宣言した時点でFrameworkが厳密変換する方が直感的である。Bは安全だが、OperationValueの型をTransport表現へ引きずり、毎回Application側に変換処理を要求する。Cは型付きBridgeとして成立しない。

[ANSWER]

A

[/ANSWER]

## Question 2: Canonical Conversion Rules

Q1でAを選ぶ場合、曖昧なPHP Castを避けるためどの文字列表現を許可するか。

### Options

- A: 厳密形式だけを許可する。`int`は符号付き10進整数、`float`はJSON Number相当の有限数、`bool`は小文字`true`／`false`だけとする。前後空白、空文字、`1`／`0`、`yes`／`no`、Locale表現、NaN／Infinity、部分一致を拒否する
- B: HTML Form互換を広く取り、`bool`へ`1`／`0`／`on`／`off`／`yes`／`no`も許可する。数値の前後空白と先頭`+`も許可する
- C: PHPの弱い型変換へ委ねる

### Recommendation

Aを推奨する。

Frontend生成とServer Decodeを同一規則へ固定でき、`"false"`がPHP Castで`true`になるような事故を防げる。別のForm表現が必要ならApplication Middlewareまたは将来の明示Codecとして追加できる。

[ANSWER]

A

[/ANSWER]

## Question 3: Failure Surface and Source Scope

変換不能値とNullable／Missingをどう扱うか。

### Options

- A: 変換不能は既存のOperationValue Binding FailureとしてOperation ID付き422へ統合する。Missingは既存Default／Required規則、明示`null`はBodyだけで扱い、Path／Query／Headerの空文字を`null`へ暗黙変換しない
- B: 変換不能はProtocol Error 400とし、Operationを成立させない。空文字はNullableなら`null`へ変換する
- C: Sourceごとに異なるStatus／Null規則を持つ

### Recommendation

Aを推奨する。

Field名と宣言型に対する不一致は既存Binding Failureの責務であり、同じ422 Surfaceを維持できる。EmptyとMissingとNullを混同せず、Constructor Defaultも従来どおりMissing時だけ適用できる。

[ANSWER]

A

[/ANSWER]

## Proposed Impact of A / A / A

- Source-aware Scalar DecoderをHTTP Binding内部へ追加する
- BodyはJSON Decode後のNative Scalarを従来どおり型検査し、文字列からのCoercionを行わない
- Path／Query／Headerだけを宣言型へ厳密変換する
- NullableはMissing／Body `null`だけに影響し、空文字を`null`へしない
- Frontend `.url()`／`.toRequest()`は同じCanonical文字列表現を生成する
- `int`／`float`／`bool`のPath／Query／Headerを実RequestでRound-trip Testする
- Invalid文字列は既存のOperation ID付き422 Binding Rejectionへ統合する
- Coercion RuleをPublic Guide／Validation／Troubleshootingへ後続Consumer Taskで記載する

## Decision

[DECISION]

1. Path／Query／HeaderのCanonical文字列は、OperationValueの宣言型に基づいてFrameworkが`int`／`float`／`bool`へ厳密変換する。
2. `int`は符号付き10進整数、`float`はJSON Number相当の有限数、`bool`は小文字`true`／`false`だけを許可する。空白、空文字、`1`／`0`、`yes`／`no`、Locale表現、NaN／Infinity、部分一致を拒否する。
3. 変換不能値は既存のOperation ID付き422 Binding Rejectionへ統合する。空文字を`null`へ変換せず、Missing／Default／Required規則を維持する。
4. BodyはJSON Decode後のNative Scalarを従来どおり検査し、文字列からのCoercionを行わない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- `#[FromPath] public int $id`、`#[FromQuery] public float $rate`、`#[FromHeader] public bool $dryRun`を実HTTP Requestから型どおりに受け取れる。
- Frontend生成Runtimeは同じCanonical形式をPath／Query／Headerへ書き、ServerとRound-tripできる。
- PHPの弱いCastやHTML Form固有のBoolean Aliasに依存しない。
- NullableはMissingまたはBodyの明示`null`だけで扱い、Non-bodyの空文字とMissingを区別する。
- Invalid ScalarはProtocol 400ではなくField Bindingの422となり、Raw InputをResponse／Journalへ含めない。
- Form固有Codec、Enum、DateTime、Custom Parserは別の明示Contractとして将来追加する。

[/CONSEQUENCES]

## References

- [HTTP Adapter](../spec/05-http.md)
- [Operation Frontend Bridge](../spec/67-operation-frontend-bridge.md)
- `src/Http/Binding/HttpParameterBinder.php`
- `src/Http/Binding/HttpBoundValueTypeMatcher.php`

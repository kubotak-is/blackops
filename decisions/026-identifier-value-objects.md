# D026: Identifier Value Objects

Status: Decided

## Context

Operation ID、Attempt ID、Journal Record ID等はUUIDv7を用いることが決定している。Symfony UIDを生成に利用するが、公開APIへComponent固有型を露出させない。

MVP実装前に、各IDの型構造、生成責務、文字列表現を決める。

## Question 1: ID型の構造

### Options

- A: IDごとに独立した `final readonly class` を定義する
- B: 共通のAbstract UUID Classを各IDが継承する
- C: `Identifier<T>` のような単一ClassをPHPDoc Genericで使い分ける

### Recommendation

Aを推奨する。

```text
OperationId
AttemptId
JournalRecordId
CorrelationId
```

各型を取り違えるとPHPの型検査で失敗する。実装の重複はInternalなCodecやTraitで抑え、公開型に継承階層を持ち込まない。

[ANSWER]

A

[/ANSWER]

## Question 2: ID生成

### Options

- A: 各ID Classに `generate()` Static Methodを持たせる
- B: Framework内部の `IdentifierFactory` がSymfony UIDとClockを使って生成する
- C: IDごとのGenerator Interfaceを公開し、DIする

### Recommendation

Bを推奨する。

生成をFramework内部へ集約し、Symfony UIDへの依存を公開型から隠す。テストではIdentifierFactoryを差し替えるのではなく、ClockとUUID生成源を内部Portとして注入可能にする。

[ANSWER]

B

[/ANSWER]

## Question 3: 文字列との変換

### Options

- A: `fromString()`、`toString()`、`__toString()` を提供する
- B: `fromString()` と `toString()` だけを提供する
- C: 文字列変換をInternal Codecだけに限定する

### Recommendation

Aを推奨する。

Log、HTTP Response、Database、Queueとの境界では文字列表現が不可欠である。正規表現は小文字のRFC 4122形式へ統一し、`fromString()` はUUIDv7であることも検証する。

```php
$id = OperationId::fromString($value);
$value = $id->toString();
(string) $id;
```

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

Operation ID、Attempt ID、Journal Record ID、Correlation ID等は、それぞれ独立した `final readonly class` とする。公開型に共通の継承階層を設けない。

UUIDv7の生成はFramework内部の `IdentifierFactory` へ集約する。Symfony UIDは内部実装として利用し、公開ID型からは露出させない。

各ID型は次の文字列変換APIを提供する。

```php
$id = OperationId::fromString($value);
$value = $id->toString();
(string) $id;
```

正規文字列表現は小文字のRFC 4122形式とする。`fromString()` は形式だけでなくUUID Version 7であることを検証し、不正値を拒否する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 異なる意味を持つIDの取り違えをPHPの型検査で防止できる。
- Symfony UIDを将来置換してもPHP Public APIへ影響させない。
- Log、HTTP、Database、Execution Transportでは同じ正規文字列表現を利用できる。
- 外部入力から復元したIDもUUIDv7であることが保証される。
- ID生成源とClockは内部Portとして注入可能にし、決定的なTestを可能にする。
- 各ID型の重複実装はInternalなCodecまたはTraitで抑える。

[/CONSEQUENCES]

# Identifier Value Objects

## 型

Operation ID、Attempt ID、Journal Record ID、Correlation ID、Causation IDは、それぞれ独立した `final readonly class` とする。

```text
OperationId
AttemptId
JournalRecordId
CorrelationId
CausationId
```

公開型に共通の継承階層を設けず、異なる意味のIDをPHPの型検査で区別する。
各型は `BlackOps\Core\Identifier` Namespaceへ配置し、`#[PublicApi]` を付ける。

## 生成

すべてのFramework IDはUUID Version 7として生成する。

生成は `BlackOps\Internal` の `IdentifierFactory` へ集約する。Symfony UIDは内部実装として利用し、公開ID型から露出させない。

IdentifierFactoryが利用するUUID生成源とClockは内部Portとして注入可能にする。

Application Infrastructure向けには、Frameworkが`BlackOps\Identifier\Uuidv7Generator::generate(): string`をDefault Serviceとして提供する。Defaultおよび明示Overrideの結果はContainer境界でCanonical lowercase UUIDv7へ検証し、Vendor UUID型はPublic Signatureへ露出させない。

## 文字列表現

各ID型は次のPHP Public APIを提供する。

```php
public static function fromString(string $value): self;
public function toString(): string;
public function __toString(): string;
public function equals(self $other): bool;
```

正規文字列表現は小文字のRFC 4122形式とする。

`equals()` は同じ具象ID型かつ同じ正規文字列表現の場合だけ `true` を返す。

`fromString()` は入力がUUID Version 7であることを検証し、形式またはVersionが不正な場合は
`BlackOps\Core\Exception\InvalidIdentifierException` を投げる。この例外は
`\InvalidArgumentException` を継承するPHP Public APIとし、例外Messageへ入力値そのものを含めない。

Log、HTTP、Database、Execution Transportでは、この正規文字列表現を使用する。

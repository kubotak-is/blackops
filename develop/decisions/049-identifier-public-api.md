# D049: Identifier Public API

Status: Decided

## Context

P1-001で識別子Value Objectを実装するにあたり、D026では未確定だった値比較API、不正入力時の例外型、配置Namespaceを決める必要がある。これらはSemVer管理対象のPHP Public APIである。

## Decision

[DECISION]

`OperationId`、`AttemptId`、`JournalRecordId`、`CorrelationId`、`CausationId` は
`BlackOps\Core\Identifier` に配置し、それぞれ独立した `final readonly class` とする。
共通の公開Interfaceまたは基底Classは設けない。

各識別子は次のPHP Public APIを提供する。

```php
public static function fromString(string $value): self;
public function toString(): string;
public function __toString(): string;
public function equals(self $other): bool;
```

`equals()` は同じ具象ID型を受け取り、正規化済み文字列値が同じ場合だけ `true` を返す。

`fromString()` は、形式が不正またはUUID Version 7以外の入力に対して
`BlackOps\Core\Exception\InvalidIdentifierException` を投げる。この例外は
`\InvalidArgumentException` を継承し、例外Messageへ入力値そのものを含めない。

識別子Value Objectと `InvalidIdentifierException` は `#[PublicApi]` を付ける。

`BlackOps\Core\Time\TimeCodec` はFramework内部の共通実装として扱い、現時点では
`#[PublicApi]` を付けない。

[/DECISION]

## Consequences

- 利用者は具象ID型を保ったまま明示的な値比較を行える。
- 不正入力をFramework固有例外で捕捉でき、標準の `InvalidArgumentException` としても扱える。
- 公開識別子に共通継承階層を導入せず、意味の異なるIDを型で区別する。

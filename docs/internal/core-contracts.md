# Core Contracts

BlackOps Phase 1 の土台となる Core 契約、識別子Value Object、時刻Codecの実装を記録する。確定仕様の正本は `develop/spec/`、判断経緯は `develop/decisions/` である。本文書は実装上のPublic APIと不変条件をFramework実装者向けに整理する。

## Marker Interface

`Operation`、`OperationValue`、`Outcome` は共通Methodを持たないMarker Interfaceとする（Spec 17、D023）。3つとも `BlackOps\Core` Namespaceへ配置し `#[PublicApi]` を付与する。

| 型 | Namespace | 役割 |
| --- | --- | --- |
| `Operation` | `BlackOps\Core` | Operation Definitionを実装するMarker |
| `OperationValue` | `BlackOps\Core` | 型付けされた業務入力を実装するMarker |
| `Outcome` | `BlackOps\Core` | 成功時の業務結果を実装するMarker |

不変条件：
- Marker InterfaceへMethodを追加しない。
- 業務ClassとFrameworkの関連付けはAttributeで宣言し、継承階層を持ち込まない。

## PublicApi Attribute

`BlackOps\Core\Attribute\PublicApi` は、PHP Public API（SemVer上の後方互換性管理対象）であることを示すMarker Attributeとする（Spec 17、D023）。

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class PublicApi
{
}
```

- `TARGET_CLASS` によりClassとInterfaceへ付与可能（PHP 8.5ではInterfaceも `TARGET_CLASS` で扱う）。
- 実行時の振る舞いを追加しない。
- `#[PublicApi]` が付いた型は後方互換性の対象、付かない型は保証対象外とする。
- 公開APIのSignatureへ `BlackOps\Internal` の型を露出させない。

### Public API Architecture Guard

通常のPHPUnit Test Suiteは、`src/` 配下の全PHP FileをComposerのPSR-4配置から型名へ変換し、すべての型をReflectionで検査する。

- `BlackOps\Internal` Namespaceの型へ `#[PublicApi]` を付与してはならない。
- `#[PublicApi]` 型のPublic Constructor、Method、Property、親Class、実装Interfaceへ `BlackOps\Internal` 型を露出させてはならない。
- Nullable、Union、Intersection Typeは構成するNamed Typeまで再帰的に検査する。
- PHPDoc GenericはこのGuardの対象外とし、MagoのStatic AnalysisとManifest Compilerで検証する。

DeptracとPublic API Architecture Guardは異なる境界を検査する。Deptracは実装本体のNamespace間依存方向と循環を静的に検査する。一方、Public API Architecture Guardは`#[PublicApi]`を互換性境界として、ReflectionでPHP SignatureとInternal Namespaceへの誤った公開指定を検査する。どちらか一方で他方を代替せず、`vendor/bin/deptrac`と通常の`vendor/bin/phpunit`を継続して実行する。

## 識別子Value Object

`OperationId`、`AttemptId`、`JournalRecordId`、`CorrelationId`、`CausationId` は、それぞれ独立した `final readonly class` とする（Spec 20、D026、D049）。公開型に共通の継承階層を設けず、異なる意味のIDをPHPの型検査で区別する。

配置と公開API：

```php
namespace BlackOps\Core\Identifier;

#[PublicApi]
final readonly class OperationId
{
    public static function fromString(string $value): self;
    public function toString(): string;
    public function __toString(): string;
    public function equals(self $other): bool;
}
```

- 5型とも `BlackOps\Core\Identifier` Namespaceへ配置し `#[PublicApi]` を付与する。
- 公開復元APIは `fromString()` のみとし、Constructorは `private` でPHP Public APIにしない。
- 正規文字列表現は小文字のRFC 4122形式。`fromString()` は大文字入力を小文字へ正規化する。
- `equals()` は同じ具象ID型かつ同じ正規文字列値の場合だけ `true` を返す。異なる具象ID型を渡すと `TypeError` となる（`self` パラメータ型による静的保証）。
- 共通実装は非公開Trait `IdentifierBehavior` で集約し、各型は `use IdentifierBehavior;` するだけとする。このTraitはPHP Public APIではなく内部実装詳細で `#[PublicApi]` を付けない。

### 不正入力の拒否

`fromString()` は、形式が不正またはUUID Version 7以外の入力に対して `BlackOps\Core\Exception\InvalidIdentifierException` を投げる（D049）。

```php
namespace BlackOps\Core\Exception;

#[PublicApi]
final class InvalidIdentifierException extends \InvalidArgumentException
{
    public static function invalidUuidV7(string $identifierType): self;
}
```

- `\InvalidArgumentException` を継承し、呼び出し元は標準例外としても扱える。
- 例外Messageへ入力値そのものを含めない（入力値がLogやAudit痕跡へ漏れるのを防ぐ）。
- Messageは固定の安全なTemplateとし、失敗した識別子型名だけを含める。
- 検証対象：garbage、桁数不足/超過、dash不在、UUIDv1/v4/v6、nil UUID、variant 0等。すべて拒否する。

## IdentifierFactory

UUIDv7生成は `BlackOps\Internal\Identifier\IdentifierFactory` へ集約する（Spec 20、D026）。Symfony UIDは内部実装として利用し、公開ID型からSymfony型を露出させない。

```php
namespace BlackOps\Internal\Identifier;

final readonly class IdentifierFactory
{
    public function __construct(
        private Uuidv7Generator $generator,
        private \Psr\Clock\ClockInterface $clock,
    ) {}

    public function newOperationId(): \BlackOps\Core\Identifier\OperationId;
    public function newAttemptId(): \BlackOps\Core\Identifier\AttemptId;
    public function newJournalRecordId(): \BlackOps\Core\Identifier\JournalRecordId;
    public function newCorrelationId(): \BlackOps\Core\Identifier\CorrelationId;
    public function newCausationId(): \BlackOps\Core\Identifier\CausationId;
}
```

- `Uuidv7Generator` は内部Port（Interface）で、UUID文字列生成を抽象化する。既定実装 `SymfonyUuidv7Generator` は `Symfony\Component\Uid\UuidV7::generate(DateTimeInterface)` を使う。
- `Psr\Clock\ClockInterface` はPSR-20 Clockで、現在時刻取得をDIする。Framework内部で現在時刻を直接生成しない（Spec 21）。
- 生成源とClockはTestで差し替え可能。決定的なTestのため固定時刻Clockと固定文字列Generatorを注入できる。
- `IdentifierFactory`、`Uuidv7Generator`、`SymfonyUuidv7Generator` はPHP Public APIではなく `#[PublicApi]` を付けない。

## TimeCodec

`BlackOps\Core\Time\TimeCodec` は永続化／API境界の時刻文字列を統一する共通Codecとする（Spec 21、D027、D049）。

```php
final readonly class TimeCodec
{
    public function toUtc(\DateTimeImmutable $time): \DateTimeImmutable;
    public function format(\DateTimeImmutable $time): string;
}
```

- `toUtc()` は入力時刻をUTCへ正規化した新しい `DateTimeImmutable` を返す。
- `format()` はUTCへ正規化した上でRFC 3339拡張形式（マイクロ秒6桁、末尾 `Z`）へ変換する。例：`2026-07-02T12:34:56.123456Z`。
- マイクロ秒を持たない時刻は `.000000` へ桁埋めする。
- PHP内部表現は `DateTimeImmutable` とし、生成時にUTCへ正規化する（Spec 21）。
- Timestampは観測時刻であり、Journal順序の根拠として使用しない（Spec 21）。
- D049により、`TimeCodec` はFramework内部の共通実装として扱い、現時点では `#[PublicApi]` を付与しない。

## Namespaceと依存方向

Namespace別Layerと依存方向は `develop/spec/16-namespace-dependencies.md` に従い `deptrac.yaml` で検査する。

P1-001で追加した型の所属：
- Core Layer：`BlackOps\Core\Operation`、`BlackOps\Core\OperationValue`、`BlackOps\Core\Outcome`、`BlackOps\Core\Attribute\PublicApi`、`BlackOps\Core\Identifier\*`、`BlackOps\Core\Exception\InvalidIdentifierException`、`BlackOps\Core\Time\TimeCodec`
- Internal Layer：`BlackOps\Internal\Identifier\IdentifierFactory`、`BlackOps\Internal\Identifier\Uuidv7Generator`、`BlackOps\Internal\Identifier\SymfonyUuidv7Generator`

依存関係：
- Coreは外部Namespaceへ依存しない（Spec 16）。Core内の識別子型はCore内のTraitと例外のみへ依存する。
- Internal → Core、Internal → Library（PSR-20 Clock、Symfony UID）。Library Layerは `Psr\Clock` と `Symfony\Component\Uid` を `classNameRegex` Collectorで定義する。Core → Libraryは禁止し、Symfony UIDを公開APIのProperty、Parameter、戻り値へ露出させない。
- Deptrac検査結果：Violations 0 / Uncovered 0 / Allowed 12（Library依存は許可）/ Warnings 0 / Errors 0。

## 品質検査

P1-001のAcceptance Criteriaに基づき次の検査を実施し、すべて成功した。結果の詳細は `develop/orchestration/reports/P1-001-core-contracts-and-identifiers.md` に記録する。

- `composer validate --strict`
- `mago lint`
- `mago analyze`
- `vendor/bin/phpunit`
- `vendor/bin/deptrac`
